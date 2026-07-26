<?php

namespace Database\Seeders;

use App\Domain\Platform\Models\PlatformAdmin;
use Illuminate\Database\Seeder;

/**
 * Seeds exactly one Super Admin account so the Platform layer is reachable
 * on a fresh install. Credentials come from environment config, not a
 * hard-coded default password — change PLATFORM_ADMIN_PASSWORD in .env
 * before any shared/production deployment.
 */
class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        PlatformAdmin::updateOrCreate(
            ['email' => config('platform.admin_email')],
            [
                'name' => 'Super Admin',
                'password' => config('platform.admin_password'),
                'is_active' => true,
            ],
        );
    }
}
