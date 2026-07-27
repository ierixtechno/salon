<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\BusinessProfile;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\LoyaltyLedgerEntry;
use App\Domain\Core\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent by `idempotency_key` (CLAUDE.md §31/§37) — a retried/double-
 * clicked submission with the same key returns the already-recorded
 * payment rather than creating a duplicate. Overpayment beyond the
 * invoice's remaining balance is rejected as a business-rule conflict,
 * never silently allowed (a tip is recorded separately on the same
 * payment row and never counts toward the invoice balance).
 *
 * `$giftCardId`/`$pointsRedeemed` (Phase 8) are opaque passthrough columns
 * for RedeemGiftCard/RedeemLoyaltyPoints to record which ledger a
 * wallet/gift_card/loyalty payment drew from — this action itself doesn't
 * validate or touch those ledgers (the calling action already did, before
 * ever reaching here).
 *
 * Loyalty auto-earn (Phase 8): unlike RecordServiceConsumption's
 * deliberately-manual precedent (Phase 7, where wrongly auto-deducting
 * stock has real inventory-integrity consequences), awarding loyalty
 * points here is purely additive, harmless if wrong, tenant opt-in
 * (BusinessProfile::loyaltyEnabled(), default off), and easily reversed by
 * ProcessRefund — so it's safe to wire in automatically rather than
 * requiring a separate manual step for every single sale. A payment made
 * *with* loyalty points doesn't re-earn more points on itself.
 *
 * Commission accrual (Phase 9): same reasoning — fires exactly once, on
 * the transition into `paid`, never on a repeat/partial payment.
 */
class RecordPayment
{
    public function execute(
        Invoice $invoice,
        string $method,
        float $amount,
        string $idempotencyKey,
        ?string $reference = null,
        float $tipAmount = 0.0,
        ?int $recordedBy = null,
        ?int $giftCardId = null,
        ?int $pointsRedeemed = null,
    ): Payment {
        $existing = Payment::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        abort_unless(
            in_array($invoice->status, ['finalized', 'partially_paid'], true),
            409,
            "Cannot record a payment against an invoice that is currently {$invoice->status}.",
        );

        return DB::transaction(function () use ($invoice, $method, $amount, $idempotencyKey, $reference, $tipAmount, $recordedBy, $giftCardId, $pointsRedeemed) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $alreadyPaid = (float) $invoice->payments()->sum('amount');
            $remaining = round((float) $invoice->grand_total - $alreadyPaid, 2);

            abort_if($amount <= 0, 422, 'Payment amount must be greater than zero.');
            abort_if($amount > $remaining, 409, "Payment would exceed the invoice's remaining balance of {$remaining}.");

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'method' => $method,
                'amount' => $amount,
                'tip_amount' => $tipAmount,
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'gift_card_id' => $giftCardId,
                'points_redeemed' => $pointsRedeemed,
                'recorded_by' => $recordedBy,
            ]);

            $totalPaid = round($alreadyPaid + $amount, 2);
            $wasFullyPaid = $invoice->status === 'paid';
            $invoice->status = $totalPaid >= (float) $invoice->grand_total ? 'paid' : 'partially_paid';
            $invoice->save();

            if ($method !== 'loyalty') {
                $this->awardLoyaltyPoints($invoice, $amount, $payment, $recordedBy);
            }

            if ($invoice->status === 'paid' && ! $wasFullyPaid) {
                app(AccrueCommission::class)->execute($invoice);
            }

            return $payment;
        });
    }

    private function awardLoyaltyPoints(Invoice $invoice, float $amount, Payment $payment, ?int $recordedBy): void
    {
        $profile = BusinessProfile::where('tenant_id', $invoice->tenant_id)->first();
        if (! $profile || ! $profile->loyaltyEnabled()) {
            return;
        }

        $points = (int) floor($amount / 100 * (float) $profile->loyalty_points_per_100);
        if ($points <= 0) {
            return;
        }

        LoyaltyLedgerEntry::create([
            'customer_id' => $invoice->customer_id,
            'type' => 'earn',
            'points' => $points,
            'reference_type' => 'payment',
            'reference_id' => $payment->id,
            'reason' => "Earned from payment on invoice {$invoice->invoice_number}",
            'created_by' => $recordedBy,
        ]);
    }
}
