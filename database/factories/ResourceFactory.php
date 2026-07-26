<?php

namespace Database\Factories;

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Resource;
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
}
