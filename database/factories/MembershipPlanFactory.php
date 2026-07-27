<?php

namespace Database\Factories;

use App\Domain\Core\Models\MembershipPlan;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    protected $model = MembershipPlan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'validity_days' => 365,
            'price' => 5000,
            'discount_percent' => 10,
            'usage_limit' => null,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (MembershipPlan $plan) {
            if (empty($plan->tenant_id)) {
                $plan->tenant_id = Tenant::factory()->create()->id;
            }
        });
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->afterMaking(function (MembershipPlan $plan) use ($tenant) {
            $plan->tenant_id = $tenant->id;
        });
    }
}
