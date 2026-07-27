<?php

namespace Database\Factories;

use App\Domain\Core\Models\Product;
use App\Domain\Core\Models\ProductCategory;
use App\Domain\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'sku' => fake()->unique()->bothify('SKU-####'),
            'brand' => fake()->company(),
            'unit' => 'unit',
            'cost_price' => 100,
            'selling_price' => 200,
            'reorder_level' => 5,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Product $product) {
            if (empty($product->tenant_id)) {
                $product->tenant_id = Tenant::factory()->create()->id;
            }

            if (empty($product->product_category_id)) {
                $product->product_category_id = ProductCategory::factory()->forTenant($product->tenant)->create()->id;
            }
        });
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->afterMaking(function (Product $product) use ($tenant) {
            $product->tenant_id = $tenant->id;
        });
    }

    public function forCategory(ProductCategory $category): static
    {
        return $this->afterMaking(function (Product $product) use ($category) {
            $product->tenant_id = $category->tenant_id;
            $product->product_category_id = $category->id;
        });
    }
}
