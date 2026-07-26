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

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    protected $fillable = ['name', 'code', 'timezone', 'address', 'state', 'gstin', 'is_active'];

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
     * `timezone` is nullable — null means "inherit the tenant's timezone"
     * (see the branches migration). Every appointment-time calculation must
     * use this, never the raw column, or a branch that has never
     * customized its timezone would compute against a null value.
     */
    public function effectiveTimezone(): string
    {
        return $this->timezone ?? $this->tenant->timezone;
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

    /**
     * Branch-level BusinessHour row if one was ever saved for this day,
     * else the tenant-level default, else the same 09:00–18:00/not-closed
     * default the settings UI itself shows before anything is saved
     * (OrganizationSettingsController/BranchController@edit) — so the
     * Appointment Engine's business-hours check never disagrees with what
     * the owner sees on the settings screen.
     */
    public function effectiveHoursFor(int $dayOfWeek): BusinessHour
    {
        $hour = BusinessHour::where('owner_type', $this->getMorphClass())
            ->where('owner_id', $this->id)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        if (! $hour) {
            $hour = BusinessHour::where('owner_type', $this->tenant->getMorphClass())
                ->where('owner_id', $this->tenant_id)
                ->where('day_of_week', $dayOfWeek)
                ->first();
        }

        return $hour ?? new BusinessHour([
            'day_of_week' => $dayOfWeek,
            'opens_at' => '09:00',
            'closes_at' => '18:00',
            'is_closed' => false,
        ]);
    }
}
