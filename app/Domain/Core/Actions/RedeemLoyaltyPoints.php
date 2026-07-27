<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\BusinessProfile;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\LoyaltyLedgerEntry;
use App\Domain\Core\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Points are the input, currency is derived server-side from the tenant's
 * redemption rate — never trust a client-submitted currency amount for a
 * points-based redemption (.claude/skills/beauty-saas-development/SKILL.md
 * §19/§21). Concurrency: the customer row is locked before the balance
 * check so two simultaneous redemptions can never both succeed against the
 * same last few points (same "lock a proxy row" pattern as
 * RecordStockMovement, Phase 7).
 */
class RedeemLoyaltyPoints
{
    public function execute(Invoice $invoice, int $points, string $idempotencyKey, ?int $redeemedBy): Payment
    {
        abort_if($points <= 0, 422, 'Points to redeem must be greater than zero.');

        $profile = BusinessProfile::where('tenant_id', $invoice->tenant_id)->first();
        abort_unless($profile && $profile->loyaltyEnabled(), 409, 'Loyalty is not enabled for this business.');

        return DB::transaction(function () use ($invoice, $points, $idempotencyKey, $redeemedBy, $profile) {
            Customer::whereKey($invoice->customer_id)->lockForUpdate()->firstOrFail();

            $balance = (int) LoyaltyLedgerEntry::where('customer_id', $invoice->customer_id)->sum('points');
            abort_if($points > $balance, 409, "Redemption would exceed the customer's available balance of {$balance} points.");

            $amount = round($points * (float) $profile->loyalty_redemption_value, 2);

            $payment = app(RecordPayment::class)->execute(
                invoice: $invoice,
                method: 'loyalty',
                amount: $amount,
                idempotencyKey: $idempotencyKey,
                recordedBy: $redeemedBy,
                pointsRedeemed: $points,
            );

            // Idempotent replay (RecordPayment returned an existing payment
            // for this key) — the ledger entry for it already exists too,
            // so skip creating a second one.
            if (! LoyaltyLedgerEntry::where('reference_type', 'payment')->where('reference_id', $payment->id)->exists()) {
                LoyaltyLedgerEntry::create([
                    'customer_id' => $invoice->customer_id,
                    'type' => 'redeem',
                    'points' => -$points,
                    'reference_type' => 'payment',
                    'reference_id' => $payment->id,
                    'reason' => "Redeemed against invoice {$invoice->invoice_number}",
                    'created_by' => $redeemedBy,
                ]);
            }

            return $payment;
        });
    }
}
