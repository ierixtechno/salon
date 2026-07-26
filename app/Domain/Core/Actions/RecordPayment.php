<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent by `idempotency_key` (CLAUDE.md §31/§37) — a retried/double-
 * clicked submission with the same key returns the already-recorded
 * payment rather than creating a duplicate. Overpayment beyond the
 * invoice's remaining balance is rejected as a business-rule conflict,
 * never silently allowed (a tip is recorded separately on the same
 * payment row and never counts toward the invoice balance).
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

        return DB::transaction(function () use ($invoice, $method, $amount, $idempotencyKey, $reference, $tipAmount, $recordedBy) {
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
                'recorded_by' => $recordedBy,
            ]);

            $totalPaid = round($alreadyPaid + $amount, 2);
            $invoice->status = $totalPaid >= (float) $invoice->grand_total ? 'paid' : 'partially_paid';
            $invoice->save();

            return $payment;
        });
    }
}
