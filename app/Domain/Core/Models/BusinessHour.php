<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic: `owner` is either a Tenant (organization-level default) or a
 * Branch (override). See docs/modules/ORGANIZATION.md and BRANCH.md.
 */
class BusinessHour extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'owner_type', 'owner_id', 'day_of_week', 'opens_at', 'closes_at', 'is_closed'];

    protected function casts(): array
    {
        return ['is_closed' => 'boolean'];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
