<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `status`/`file_path`/`failure_reason`/`completed_at`/`expires_at` are
 * deliberately not in $fillable — set only via RequestTenantDataExport/
 * GenerateTenantDataExport (CLAUDE.md §28).
 */
class DataExport extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['pending', 'processing', 'completed', 'failed'];

    protected $fillable = ['requested_by'];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isDownloadable(): bool
    {
        return $this->status === 'completed' && $this->file_path && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
