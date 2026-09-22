<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Actions\Concerns\GeneratesPlatformSequenceNumbers;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Bills a specific tenant for a subscription plan. Amount defaults to the
 * plan's price but the Super Admin may override it (e.g. a negotiated
 * discount) — never a freeform line-item builder (confirmed decision).
 */
class CreateQuotation
{
    use GeneratesPlatformSequenceNumbers;

    public function execute(
        Tenant $tenant,
        SubscriptionPlan $plan,
        ?PlatformAdmin $createdBy,
        ?string $amountOverride = null,
        ?string $notes = null,
        bool $isUpgrade = false,
    ): Quotation {
        abort_unless($plan->is_active, 422, 'Cannot quote an inactive plan.');
        // GST determination needs a place of supply to compare against the
        // platform's own registered state (config('platform.state')) — see
        // Tenant::$fillable / the billing_state migration. Required, not
        // guessed (CLAUDE.md §70): guessing same-state vs inter-state would
        // silently pick the wrong tax treatment.
        abort_if(blank($tenant->billing_state), 422, "Set this tenant's billing state (for GST) before creating a quotation.");

        return DB::transaction(function () use ($tenant, $plan, $createdBy, $amountOverride, $notes, $isUpgrade) {
            $quotationNumber = $this->nextPlatformNumber('quotation');
            $amount = (float) ($amountOverride ?? $plan->price);

            // GST on Platform Billing (config/platform.php). Snapshotted
            // here so a later rate/state change never affects a quotation
            // already created (CLAUDE.md §45). Same state as the platform
            // -> CGST+SGST split evenly (mirrors the Phase 6 tenant-invoice
            // precedent); different state -> IGST at the full rate instead.
            $gstRatePercent = (float) config('platform.gst_rate_percent');
            $sameState = strcasecmp($tenant->billing_state, config('platform.state')) === 0;

            if ($sameState) {
                $cgstAmount = round($amount * ($gstRatePercent / 2) / 100, 2);
                $sgstAmount = $cgstAmount;
                $igstAmount = 0.0;
            } else {
                $cgstAmount = 0.0;
                $sgstAmount = 0.0;
                $igstAmount = round($amount * $gstRatePercent / 100, 2);
            }

            $totalAmount = round($amount + $cgstAmount + $sgstAmount + $igstAmount, 2);

            $quotation = Quotation::create([
                'tenant_id' => $tenant->id,
                'subscription_plan_id' => $plan->id,
                'platform_admin_id' => $createdBy?->id,
                'quotation_number' => $quotationNumber,
                'amount' => $amount,
                'cgst_amount' => $cgstAmount,
                'sgst_amount' => $sgstAmount,
                'igst_amount' => $igstAmount,
                'gst_rate_percent' => $gstRatePercent,
                'total_amount' => $totalAmount,
                'notes' => $notes,
                'status' => 'pending',
                'is_upgrade' => $isUpgrade,
            ]);

            app(NotifyTenantBillingContacts::class)->quotationCreated($quotation);

            return $quotation;
        });
    }
}
