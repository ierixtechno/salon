<?php

namespace App\Domain\Spa\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Domain\Core\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpaProfile extends Model
{
    use BelongsToTenant;

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    protected $fillable = [
        'customer_id', 'health_conditions', 'allergies', 'pressure_preference', 'areas_to_avoid', 'notes',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
