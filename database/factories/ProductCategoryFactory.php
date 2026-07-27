<?php

namespace Database\Factories;

use App\Domain\Core\Models\ProductCategory;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ProductCategory $category) {
            if (empty($category->tenant_id)) {
                $category->tenant_id = Tenant::factory()->create()->id;
            }
        });
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->afterMaking(function (ProductCategory $category) use ($tenant) {
            $category->tenant_id = $tenant->id;
        });
    }
}
