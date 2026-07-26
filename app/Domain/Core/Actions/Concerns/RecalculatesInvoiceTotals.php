<?php

namespace App\Domain\Core\Actions\Concerns;

use App\Domain\Core\Models\Invoice;

/**
 * Shared by AddInvoiceLine/RemoveInvoiceLine (so a draft's running totals
 * stay visible to staff while they're building the sale) and CheckoutSale
 * (which recomputes it once more, authoritatively, at finalize time —
 * never trusting whatever the draft happened to have cached).
 */
trait RecalculatesInvoiceTotals
{
    private function recalculateTotals(Invoice $invoice): void
    {
        $lines = $invoice->lines()->get();

        $invoice->subtotal = round($lines->sum(fn ($line) => $line->quantity * $line->unit_price), 2);
        $invoice->discount_total = round($lines->sum('discount_amount'), 2);
        $invoice->cgst_total = round($lines->sum('cgst_amount'), 2);
        $invoice->sgst_total = round($lines->sum('sgst_amount'), 2);
        $invoice->tax_total = round($invoice->cgst_total + $invoice->sgst_total, 2);
        $invoice->grand_total = round($lines->sum('line_total'), 2);
        $invoice->save();
    }
}
