<?php

namespace App\Domain\Platform\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deliberately NOT BelongsToTenant — Super Admin needs cross-tenant
 * visibility (same reasoning as Quotation). `status`/`decided_*` are
 * deliberately not in $fillable — set only via RequestBranchReduction /
 * DecideBranchReductionRequest (CLAUDE.md §28).
 */
class BranchReductionRequest extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    protected $fillable = [
        'tenant_id', 'requested_by', 'current_branch_count', 'requested_branch_count',
        'reason', 'status', 'decided_by', 'decided_at', 'decision_reason',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'decided_by');
    }
}
