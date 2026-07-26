<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Domain\Platform\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Branch extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'code', 'timezone', 'address', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'branch_modules')
            ->withPivot('enabled')
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user')->withTimestamps();
    }

    /**
     * A branch's enabled modules are bounded by its tenant's enabled modules
     * (CLAUDE.md §8) — that invariant is enforced where modules are
     * assigned, not re-derived here on every read.
     */
    public function hasModuleEnabled(string $moduleCode): bool
    {
        return $this->modules()
            ->where('code', $moduleCode)
            ->wherePivot('enabled', true)
            ->exists();
    }
}
