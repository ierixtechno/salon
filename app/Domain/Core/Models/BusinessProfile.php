<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    use BelongsToTenant;

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session,
    // including on the updateOrCreate() create path used in
    // OrganizationSettingsController.
    protected $fillable = [
        'legal_name', 'display_name', 'logo_path',
        'contact_email', 'contact_phone', 'address', 'cancellation_policy',
        'loyalty_points_per_100', 'loyalty_redemption_value', 'loyalty_points_expiry_days',
    ];

    protected function casts(): array
    {
        return [
            'loyalty_points_per_100' => 'decimal:2',
            'loyalty_redemption_value' => 'decimal:4',
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
}
