<?php

namespace App\Domain\Core\Models;

use App\Domain\Core\Concerns\BelongsToTenant;
use App\Domain\Platform\Models\Module;
use Database\Factories\MembershipPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipPlan extends Model
{
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): MembershipPlanFactory
    {
        return MembershipPlanFactory::new();
    }

    // tenant_id deliberately excluded — never mass-assignable (CLAUDE.md
    // §28). BelongsToTenant auto-fills it from the authenticated session.
    protected $fillable = ['name', 'description', 'validity_days', 'price', 'tax_rate_percent', 'discount_percent', 'usage_limit', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'tax_rate_percent' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'membership_plan_modules')->withTimestamps();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'membership_plan_branches')->withTimestamps();
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'membership_plan_services')->withTimestamps();
    }

    public function customerMemberships(): HasMany
    {
        return $this->hasMany(CustomerMembership::class);
    }

    /**
     * Empty-means-all convention per dimension (CLAUDE.md §14 Membership;
     * mirrors Service::isAvailableAtBranch's branch-pivot pattern) — never
     * trust a client-submitted discount; benefits are always resolved
     * server-side from this method (.claude/skills/.../SKILL.md §19).
     */
    public function appliesToServiceAtBranch(Service $service, Branch $branch): bool
    {
        $branchIds = $this->branches()->pluck('branches.id');
        if ($branchIds->isNotEmpty() && ! $branchIds->contains($branch->id)) {
            return false;
        }

        $moduleIds = $this->modules()->pluck('modules.id');
        if ($moduleIds->isNotEmpty() && ! $moduleIds->contains($service->module_id)) {
            return false;
        }

        $serviceIds = $this->services()->pluck('services.id');
        if ($serviceIds->isNotEmpty() && ! $serviceIds->contains($service->id)) {
            return false;
        }

        return true;
    }
}
