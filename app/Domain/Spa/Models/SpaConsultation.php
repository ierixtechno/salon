<?php

namespace App\Domain\Spa\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpaConsultation extends Model
{
    use BelongsToTenant;

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    // Append-only history — no update()/destroy() route exists for this
    // model (CLAUDE.md §46/§14 ledger principle). Room/resource allocation
    // and multi-resource availability belong to the Phase 5 Appointment
    // Engine, not this record (docs/modules/SPA.md).
    protected $fillable = [
        'customer_id', 'branch_id', 'consultant_id', 'consultation_date',
        'concerns', 'recommendation', 'notes',
    ];

    protected function casts(): array
    {
        return ['consultation_date' => 'date'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }
}
