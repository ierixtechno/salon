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
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->company().' Branch',
            'code' => fake()->unique()->lexify('BR???'),
            'is_active' => true,
        ];
    }
}
