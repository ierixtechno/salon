<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\Invoice;

/**
 * Only reachable from `finalized` with zero payments recorded — the moment
 * any payment lands, status moves to partially_paid/paid and void is no
 * longer reachable in Invoice::TRANSITIONS (use a refund instead). This
 * preserves the numbering sequence's audit trail: a voided invoice still
 * accounts for its assigned invoice_number (GST requires the sequence to
 * be unbroken, including voided numbers), it just carries no financial
 * effect.
 */
class VoidInvoice
{
    public function execute(Invoice $invoice, ?string $reason = null): Invoice
    {
        abort_unless($invoice->canTransitionTo('void'), 409, "Cannot void an invoice that is currently {$invoice->status}.");
        abort_if((float) $invoice->payments()->sum('amount') > 0, 409, 'Cannot void an invoice that has payments — issue a refund instead.');

        $invoice->status = 'void';
        $invoice->voided_at = now();
        $invoice->void_reason = $reason;
        $invoice->save();

        return $invoice;
    }
}
