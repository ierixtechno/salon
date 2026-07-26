<?php

namespace Database\Factories;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Resource;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<resource>
 */
class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'type' => 'chair',
            'name' => 'Chair '.fake()->unique()->numberBetween(1, 999),
            'capacity' => 1,
            'is_active' => true,
        ];
    }

    /**
     * tenant_id is deliberately not fillable (CLAUDE.md §28). Auto-
     * provisions a fresh Tenant if the caller doesn't pass one via
     * forTenant() — mirrors UserFactory/BranchFactory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Resource $resource) {
            if (empty($resource->tenant_id)) {
                $resource->tenant_id = Tenant::factory()->create()->id;
            }
        });
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->afterMaking(function (Resource $resource) use ($tenant) {
            $resource->tenant_id = $tenant->id;
        });
    }
}
