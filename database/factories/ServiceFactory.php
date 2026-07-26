<?php

namespace Database\Factories;

use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'duration_minutes' => 30,
            'base_price' => 500,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Service $service) {
            if (empty($service->tenant_id)) {
                $service->tenant_id = Tenant::factory()->create()->id;
            }

            if (empty($service->service_category_id) || empty($service->module_id)) {
                $category = ServiceCategory::factory()->forTenant($service->tenant)->create();
                $service->service_category_id ??= $category->id;
                $service->module_id ??= $category->module_id;
            }
        });
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->afterMaking(function (Service $service) use ($tenant) {
            $service->tenant_id = $tenant->id;
        });
    }

    public function forCategory(ServiceCategory $category): static
    {
        return $this->afterMaking(function (Service $service) use ($category) {
            $service->tenant_id = $category->tenant_id;
            $service->service_category_id = $category->id;
            $service->module_id = $category->module_id;
        });
    }
}
