<?php

namespace Database\Factories;

use App\Domain\Core\Models\Branch;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Branch',
            'code' => fake()->unique()->lexify('BR???'),
            'is_active' => true,
        ];
    }

    /**
     * tenant_id is deliberately not fillable (CLAUDE.md §28), so it can't
     * be set through definition(). Auto-provisions a fresh Tenant if the
     * caller doesn't pass one via forTenant() — mirrors UserFactory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Branch $branch) {
            if (empty($branch->tenant_id)) {
                $branch->tenant_id = Tenant::factory()->create()->id;
            }
        });
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->afterMaking(function (Branch $branch) use ($tenant) {
            $branch->tenant_id = $tenant->id;
        });
    }
}
