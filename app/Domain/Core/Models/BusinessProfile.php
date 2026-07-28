<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    use BelongsToTenant;

    /**
     * Cosmetic/display-only label (CLAUDE.md §10 — never a substitute for
     * module/feature/plan/permission). Null means no preset picked ("full
     * Salon/Beauty/Spa" or simply not specified) — there is no "general"
     * entry here since that's just the absence of a value, not a stored one.
     */
    public const BUSINESS_TYPES = ['barber', 'nail_studio', 'makeup_studio', 'bridal_studio'];

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session,
    // including on the updateOrCreate() create path used in
    // OrganizationSettingsController.
    protected $fillable = [
        'legal_name', 'display_name', 'business_type', 'logo_path',
        'contact_email', 'contact_phone', 'address', 'cancellation_policy',
        'loyalty_points_per_100', 'loyalty_redemption_value', 'loyalty_points_expiry_days',
        'expense_approval_required',
    ];

    protected function casts(): array
    {
        return [
            'loyalty_points_per_100' => 'decimal:2',
            'loyalty_redemption_value' => 'decimal:4',
            'expense_approval_required' => 'boolean',
        ];
    }

    /**
     * Loyalty is opt-in per tenant — 0 (the default) means the program is
     * off, and RecordPayment skips earning entirely (CLAUDE.md §70: an
     * additive, tenant-gated behavior, never forced on).
     */
    public function loyaltyEnabled(): bool
    {
        return (float) $this->loyalty_points_per_100 > 0 && (float) $this->loyalty_redemption_value > 0;
    }

    /**
     * Off by default — CreateExpense records an expense as already
     * `approved` unless a tenant explicitly opts into requiring
     * expenses.approve sign-off (CLAUDE.md §14 "Approval where configured").
     */
    public function expenseApprovalRequired(): bool
    {
        return (bool) $this->expense_approval_required;
    }
}
