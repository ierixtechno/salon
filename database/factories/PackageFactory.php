<?php

namespace Database\Factories;

use App\Domain\Core\Models\Package;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    protected $model = Package::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'validity_days' => 90,
            'price' => 2000,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Package $package) {
            if (empty($package->tenant_id)) {
                $package->tenant_id = Tenant::factory()->create()->id;
            }
        });
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->afterMaking(function (Package $package) use ($tenant) {
            $package->tenant_id = $tenant->id;
        });
    }
}
