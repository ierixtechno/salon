<?php

namespace App\Domain\Tattoo\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Domain\Core\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TattooProfile extends Model
{
    use BelongsToTenant;

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    protected $fillable = [
        'customer_id', 'skin_conditions', 'allergies', 'previous_tattoos', 'notes',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
