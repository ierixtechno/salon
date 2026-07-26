<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerConsent extends Model
{
    use BelongsToTenant;

    public const PURPOSES = ['service_delivery', 'marketing', 'data_sharing'];

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    protected $fillable = ['customer_id', 'purpose', 'granted', 'recorded_by', 'notes'];

    protected function casts(): array
    {
        return ['granted' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
