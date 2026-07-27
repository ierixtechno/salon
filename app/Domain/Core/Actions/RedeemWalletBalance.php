<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\Payment;
use App\Domain\Core\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Concurrency: the customer row is locked before the balance check so two
 * simultaneous checkouts can never both spend the same wallet balance
 * (same "lock a proxy row" pattern as RecordStockMovement, Phase 7).
 */
class RedeemWalletBalance
{
    public function execute(Invoice $invoice, float $amount, string $idempotencyKey, ?int $redeemedBy): Payment
    {
        abort_if($amount <= 0, 422, 'Redemption amount must be greater than zero.');

        return DB::transaction(function () use ($invoice, $amount, $idempotencyKey, $redeemedBy) {
            Customer::whereKey($invoice->customer_id)->lockForUpdate()->firstOrFail();

            $balance = (float) WalletTransaction::where('customer_id', $invoice->customer_id)->sum('amount');
            abort_if($amount > $balance, 409, "Redemption would exceed the customer's available wallet balance of {$balance}.");

            $payment = app(RecordPayment::class)->execute(
                invoice: $invoice,
                method: 'wallet',
                amount: $amount,
                idempotencyKey: $idempotencyKey,
                recordedBy: $redeemedBy,
            );

            if (! WalletTransaction::where('reference_type', 'payment')->where('reference_id', $payment->id)->exists()) {
                WalletTransaction::create([
                    'customer_id' => $invoice->customer_id,
                    'type' => 'debit',
                    'amount' => -$amount,
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
