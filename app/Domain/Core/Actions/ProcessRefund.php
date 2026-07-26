<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\Refund;
use Illuminate\Support\Facades\DB;

/**
 * A dedicated reversal workflow (CLAUDE.md §14 Refunds) — never a mutation
 * of payment_status. Only valid once money has actually been collected
 * (`paid`/`partially_paid`); an unpaid invoice is voided instead. A partial
 * refund leaves the invoice status untouched (the refund ledger carries
 * the detail); only once cumulative refunds reach the amount actually
 * paid does the invoice move to `refunded`.
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

            $refund = Refund::create([
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'method' => $method,
                'reason' => $reason,
                'refunded_by' => $refundedBy,
            ]);

            if (round($alreadyRefunded + $amount, 2) >= $totalPaid) {
                abort_unless($invoice->canTransitionTo('refunded'), 409, "Cannot mark this invoice refunded from its current state ({$invoice->status}).");
                $invoice->status = 'refunded';
                $invoice->save();
            }

            return $refund;
        });
    }
}
