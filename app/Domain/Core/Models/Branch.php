<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Domain\Platform\Models\Module;
use App\Models\User;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'name', 'code', 'timezone', 'address', 'is_active'];

    protected static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }

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

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
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
