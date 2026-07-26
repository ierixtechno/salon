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
    ];
}
