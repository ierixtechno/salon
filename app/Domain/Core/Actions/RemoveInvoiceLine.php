<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Actions\Concerns\RecalculatesInvoiceTotals;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\InvoiceLine;

class RemoveInvoiceLine
{
    use RecalculatesInvoiceTotals;

    public function execute(Invoice $invoice, InvoiceLine $line): void
    {
        abort_unless($invoice->status === 'draft', 409, 'Lines can only be removed while the invoice is a draft.');
        abort_unless($line->invoice_id === $invoice->id, 404);

        $line->delete();

        $this->recalculateTotals($invoice);
    }
}
