<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deliberately NOT BelongsToTenant — this is the platform operator's view
 * across every tenant (see the migration's docblock). Only ever written by
 * RecordErrorLog and only ever read from the Platform (Super Admin) guard;
 * no tenant-facing route touches it, and it can contain internal file
 * paths/stack frames that tenants must never see (CLAUDE.md §40).
 */
class ErrorLog extends Model
{
    public const OPEN = 'open';

    public const RESOLVED = 'resolved';

    protected $fillable = [
        'fingerprint', 'exception_class', 'message', 'file', 'line', 'trace',
        'context', 'http_method', 'path', 'request_id', 'tenant_id', 'user_id',
        'first_seen_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }

    /** "App\Http\Controllers\Foo\BarException" -> "BarException" */
    public function shortClass(): string
    {
        return class_basename($this->exception_class);
    }
}
