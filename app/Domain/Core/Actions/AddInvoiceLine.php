<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Actions\Concerns\RecalculatesInvoiceTotals;
use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\CustomerMembership;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\InvoiceLine;
use App\Domain\Core\Models\MembershipUsage;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceVariant;
use Illuminate\Support\Facades\DB;

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
 *
 * `$membership` (Phase 8) is an additive, opt-in parameter — omitting it
 * leaves every pre-Phase-8 call site's behavior byte-for-byte unchanged.
 * When given, its discount_percent is resolved server-side (never a
 * client-submitted amount, .claude/skills/beauty-saas-development/SKILL.md
 * §19) and **replaces** `$discountAmount` entirely — a membership discount
 * and a manual discount are mutually exclusive on the same line, since
 * stacking them would need a business rule nobody has specified.
 *
 * `$performedBy` (Phase 9) records which employee to credit commission to.
 * For an appointment-based line it defaults to that appointment's own
 * assigned employee (the one who actually did the work) unless explicitly
 * overridden; for a standalone line it's whatever the caller passes, or
 * null (no employee attributed — simply never accrues commission, the
 * safe default per CLAUDE.md §22).
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
        ?CustomerMembership $membership = null,
        ?int $appliedBy = null,
        ?int $performedBy = null,
    ): InvoiceLine {
        abort_unless($invoice->status === 'draft', 409, 'Lines can only be added while the invoice is a draft.');

        $performedBy ??= $appointment?->user_id;

        $unitPrice = $appointment
            ? (float) $appointment->price
            : (float) ($variant?->effectivePrice() ?? $service->priceForBranch($invoice->branch));

        if ($membership) {
            abort_unless($membership->customer_id === $invoice->customer_id, 403, 'This membership does not belong to the invoice\'s customer.');
            abort_unless($membership->isUsable(), 409, 'This membership is not currently active, has expired, or has reached its usage limit.');
            abort_unless($membership->membershipPlan->appliesToServiceAtBranch($service, $invoice->branch), 422, 'This membership does not apply to this service at this branch.');

            $discountAmount = round($quantity * $unitPrice * ((float) $membership->membershipPlan->discount_percent / 100), 2);
        }

        abort_if($discountAmount > $quantity * $unitPrice, 422, 'Discount cannot exceed the line value.');

        $taxableValue = round(($quantity * $unitPrice) - $discountAmount, 2);
        $taxRate = (float) ($service->tax_rate_percent ?? 0);
        $cgstAmount = round($taxableValue * ($taxRate / 2) / 100, 2);
        $sgstAmount = $cgstAmount;
        $lineTotal = round($taxableValue + $cgstAmount + $sgstAmount, 2);

        return DB::transaction(function () use (
            $invoice, $service, $variant, $appointment, $quantity, $discountAmount,
            $unitPrice, $taxableValue, $taxRate, $cgstAmount, $sgstAmount, $lineTotal,
            $membership, $appliedBy, $performedBy,
        ) {
            $line = InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'service_id' => $service->id,
                'service_variant_id' => $variant?->id,
                'appointment_id' => $appointment?->id,
                'performed_by' => $performedBy,
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

            if ($membership) {
                MembershipUsage::create([
                    'customer_membership_id' => $membership->id,
                    'invoice_line_id' => $line->id,
                    'discount_amount' => $discountAmount,
                    'applied_at' => now(),
                    'applied_by' => $appliedBy,
                ]);
                $membership->increment('usage_count');
            }

            $this->recalculateTotals($invoice);

            return $line;
        });
    }
}
