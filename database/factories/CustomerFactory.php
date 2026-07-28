<?php

namespace Database\Factories;

use App\Domain\Core\Models\Customer;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('9########'),
            'email' => fake()->unique()->safeEmail(),
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
        return $this->afterMaking(function (Customer $customer) {
            if (empty($customer->tenant_id)) {
                $customer->tenant_id = Tenant::factory()->create()->id;
            }
        });
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->afterMaking(function (Customer $customer) use ($tenant) {
            $customer->tenant_id = $tenant->id;
        });
    }
}
