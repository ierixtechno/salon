<?php

namespace Database\Factories;

use App\Domain\Platform\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_active' => true,
            'all_branches' => true,
        ];
    }

    /**
     * tenant_id is deliberately not fillable (CLAUDE.md §28), so it can't be
     * set through definition(). If a test doesn't care which tenant, this
     * auto-provisions one; tests asserting cross-tenant behavior should
     * always use forTenant() explicitly instead.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            if (empty($user->tenant_id)) {
                $user->tenant_id = Tenant::factory()->create()->id;
            }
        });
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->afterMaking(function (User $user) use ($tenant) {
            $user->tenant_id = $tenant->id;
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
