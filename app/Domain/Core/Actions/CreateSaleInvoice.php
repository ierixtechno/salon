<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Actions\Concerns\RecalculatesInvoiceTotals;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\InvoiceLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bills a package or membership sale on a normal, GST-numbered Invoice: one
 * line for the item, finalized, and paid in full with the chosen method —
 * so it appears in invoices, the payment ledger and reports like any other
 * sale (supersedes the "no invoice" default of D-006).
 *
 * `$price` is the price before GST; the tax rate is the package's/plan's
 * own `tax_rate_percent`, resolved server-side (never from the browser).
 * Returns null for a zero-value sale: there is nothing to bill or collect.
 *
 * Call inside the caller's transaction so the sale and its invoice succeed
 * or fail together (CLAUDE.md §23).
 */
class CreateSaleInvoice
{
    use RecalculatesInvoiceTotals;

    public function __construct(
        private readonly CreateDraftInvoice $createDraftInvoice,
        private readonly CheckoutSale $checkoutSale,
        private readonly RecordPayment $recordPayment,
    ) {}

    public function execute(
        Branch $branch,
        Customer $customer,
        string $description,
        float $price,
        float $taxRatePercent,
        string $paymentMethod,
        ?string $paymentReference,
        ?int $createdBy,
    ): ?Invoice {
        if ($price <= 0) {
            return null;
        }

        return DB::transaction(function () use ($branch, $customer, $description, $price, $taxRatePercent, $paymentMethod, $paymentReference, $createdBy) {
            $invoice = $this->createDraftInvoice->execute($branch, $customer, $createdBy);

            $cgst = round($price * ($taxRatePercent / 2) / 100, 2);

            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'service_id' => null,
                'description' => $description,
                'quantity' => 1,
                'unit_price' => $price,
                'discount_amount' => 0,
                'taxable_value' => $price,
                'tax_rate_percent' => $taxRatePercent,
                'cgst_amount' => $cgst,
                'sgst_amount' => $cgst,
                'line_total' => round($price + 2 * $cgst, 2),
            ]);

            $this->recalculateTotals($invoice);
            $invoice = $this->checkoutSale->execute($invoice->refresh());

            $this->recordPayment->execute(
                invoice: $invoice,
                method: $paymentMethod,
                amount: (float) $invoice->grand_total,
                idempotencyKey: (string) Str::uuid(),
                reference: $paymentReference,
                recordedBy: $createdBy,
            );

            return $invoice->refresh();
        });
    }
}
