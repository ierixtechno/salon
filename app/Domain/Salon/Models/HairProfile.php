<?php

namespace App\Domain\Salon\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Domain\Core\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HairProfile extends Model
{
    use BelongsToTenant;

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    protected $fillable = [
        'customer_id', 'hair_type', 'scalp_type', 'chemical_history', 'allergies', 'notes',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
