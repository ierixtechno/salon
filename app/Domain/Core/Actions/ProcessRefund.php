<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\BusinessProfile;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\LoyaltyLedgerEntry;
use App\Domain\Core\Models\Refund;
use Illuminate\Support\Facades\DB;

/**
 * A dedicated reversal workflow (CLAUDE.md §14 Refunds) — never a mutation
 * of payment_status. Only valid once money has actually been collected
 * (`paid`/`partially_paid`); an unpaid invoice is voided instead. A partial
 * refund leaves the invoice status untouched (the refund ledger carries
 * the detail); only once cumulative refunds reach the amount actually
 * paid does the invoice move to `refunded`.
 *
 * `method` of 'wallet' or 'loyalty' (Phase 8) credits the reversal back to
 * the customer's own ongoing balance instead of handing back cash — the
 * natural "reverse loyalty/wallet on refund" CLAUDE.md §14 requires.
 * 'gift_card' is deliberately not a refund-credit target: crediting an
 * unrelated invoice refund into an arbitrary gift card is an ambiguous
 * business rule nobody has specified, unlike wallet/loyalty which are
 * unambiguously "this customer's own balance".
 */
class ProcessRefund
{
    public function execute(Invoice $invoice, float $amount, string $method, ?string $reason, ?int $refundedBy): Refund
    {
        abort_unless(
            in_array($invoice->status, ['paid', 'partially_paid'], true),
            409,
            "Cannot refund an invoice that is currently {$invoice->status}.",
        );

        return DB::transaction(function () use ($invoice, $amount, $method, $reason, $refundedBy) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $totalPaid = (float) $invoice->payments()->sum('amount');
            $alreadyRefunded = (float) $invoice->refunds()->sum('amount');
            $refundable = round($totalPaid - $alreadyRefunded, 2);

            abort_if($amount <= 0, 422, 'Refund amount must be greater than zero.');
            abort_if($amount > $refundable, 409, "Refund would exceed the refundable balance of {$refundable}.");

            $pointsCredited = null;
            if ($method === 'loyalty') {
                $profile = BusinessProfile::where('tenant_id', $invoice->tenant_id)->first();
                abort_unless($profile && $profile->loyaltyEnabled(), 409, 'Loyalty is not enabled for this business.');
                $pointsCredited = (int) round($amount / (float) $profile->loyalty_redemption_value);
            }

            $refund = Refund::create([
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'method' => $method,
                'reason' => $reason,
                'points_credited' => $pointsCredited,
                'refunded_by' => $refundedBy,
            ]);

            if ($method === 'wallet') {
                app(CreditWallet::class)->execute(
                    customer: $invoice->customer,
                    amount: $amount,
                    reason: "Refund credit for invoice {$invoice->invoice_number}",
                    referenceType: 'refund',
                    referenceId: $refund->id,
                    createdBy: $refundedBy,
                );
            } elseif ($method === 'loyalty') {
                LoyaltyLedgerEntry::create([
                    'customer_id' => $invoice->customer_id,
                    'type' => 'refund',
                    'points' => $pointsCredited,
                    'reference_type' => 'refund',
                    'reference_id' => $refund->id,
                    'reason' => "Refund credit for invoice {$invoice->invoice_number}",
                    'created_by' => $refundedBy,
                ]);
            }

            if (round($alreadyRefunded + $amount, 2) >= $totalPaid) {
                abort_unless($invoice->canTransitionTo('refunded'), 409, "Cannot mark this invoice refunded from its current state ({$invoice->status}).");
                $invoice->status = 'refunded';
                $invoice->save();
            }

            return $refund;
        });
    }
}
