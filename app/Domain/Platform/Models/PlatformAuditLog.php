<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAuditLog extends Model
{
    protected $fillable = [
        'platform_admin_id', 'action', 'entity_type', 'entity_id', 'tenant_id', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function platformAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function record(
        ?PlatformAdmin $admin,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?int $tenantId = null,
        array $meta = [],
    ): self {
        return static::create([
            'platform_admin_id' => $admin?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'tenant_id' => $tenantId,
            'meta' => $meta,
        ]);
    }
}
