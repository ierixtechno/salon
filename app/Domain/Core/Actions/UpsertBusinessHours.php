<?php

namespace App\Domain\Core\Actions;

use App\Domain\Core\Models\BusinessHour;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared by Organization settings (owner = Tenant) and Branch settings
 * (owner = Branch) — one weekly-hours upsert routine for both, since the
 * underlying table is intentionally the same shared/polymorphic concept
 * (CLAUDE.md §3: do not duplicate a shared concept).
 */
class UpsertBusinessHours
{
    public function execute(Model $owner, int $tenantId, array $hours): void
    {
        foreach ($hours as $day) {
            BusinessHour::updateOrCreate(
                [
                    'owner_type' => $owner->getMorphClass(),
                    'owner_id' => $owner->getKey(),
                    'day_of_week' => $day['day_of_week'],
                ],
                [
                    'tenant_id' => $tenantId,
                    'is_closed' => (bool) ($day['is_closed'] ?? false),
                    'opens_at' => ($day['is_closed'] ?? false) ? null : ($day['opens_at'] ?? null),
                    'closes_at' => ($day['is_closed'] ?? false) ? null : ($day['closes_at'] ?? null),
                ],
            );
        }
    }
}
