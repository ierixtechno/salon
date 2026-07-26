<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Actions\Concerns\RecalculatesInvoiceTotals;
use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\InvoiceLine;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceVariant;

/**
 * Only ever valid while the parent Invoice is `draft` — CLAUDE.md
 * §20/§45: price/tax/discount are resolved and snapshotted here, from the
 * server side, never trusted from client input.
 *
 * If `$appointment` is given, its already-resolved `price` (from
 * BookAppointment, Phase 5) is reused directly rather than re-deriving the
 * service's current price — the customer agreed to that price when the
 * appointment was booked, and it must not silently drift if the catalogue
 * changed since. Otherwise the price is resolved fresh via
 * Service::priceForBranch()/ServiceVariant::effectivePrice(), exactly like
 * a fresh booking would.
 */
class AddInvoiceLine
{
    use RecalculatesInvoiceTotals;

    public function execute(
        Invoice $invoice,
        Service $service,
        ?ServiceVariant $variant,
        ?Appointment $appointment,
        int $quantity = 1,
        float $discountAmount = 0.0,
    ): InvoiceLine {
        abort_unless($invoice->status === 'draft', 409, 'Lines can only be added while the invoice is a draft.');

        $unitPrice = $appointment
            ? (float) $appointment->price
            : (float) ($variant?->effectivePrice() ?? $service->priceForBranch($invoice->branch));

        abort_if($discountAmount > $quantity * $unitPrice, 422, 'Discount cannot exceed the line value.');

        $taxableValue = round(($quantity * $unitPrice) - $discountAmount, 2);
        $taxRate = (float) ($service->tax_rate_percent ?? 0);
        $cgstAmount = round($taxableValue * ($taxRate / 2) / 100, 2);
        $sgstAmount = $cgstAmount;
        $lineTotal = round($taxableValue + $cgstAmount + $sgstAmount, 2);

        $line = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'service_id' => $service->id,
            'service_variant_id' => $variant?->id,
            'appointment_id' => $appointment?->id,
            'description' => $service->name.($variant ? " ({$variant->name})" : ''),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => $discountAmount,
            'taxable_value' => $taxableValue,
            'tax_rate_percent' => $taxRate,
            'cgst_amount' => $cgstAmount,
            'sgst_amount' => $sgstAmount,
            'line_total' => $lineTotal,
        ]);

        $this->recalculateTotals($invoice);

        return $line;
    }
}
