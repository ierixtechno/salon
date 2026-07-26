<?php

namespace Database\Factories;

use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCategory>
 */
class ServiceCategoryFactory extends Factory
{
    protected $model = ServiceCategory::class;

    public function definition(): array
    {
        return [
            // The module catalog (salon/beauty/spa) is always seeded via
            // ModuleSeeder — see tests/Pest.php's global beforeEach — so
            // reference an existing row rather than faking one.
            'module_id' => fn () => Module::firstOrFail()->id,
            'name' => fake()->unique()->words(2, true),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ServiceCategory $category) {
            if (empty($category->tenant_id)) {
                $category->tenant_id = Tenant::factory()->create()->id;
            }
        });
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->afterMaking(function (ServiceCategory $category) use ($tenant) {
            $category->tenant_id = $tenant->id;
        });
    }
}
