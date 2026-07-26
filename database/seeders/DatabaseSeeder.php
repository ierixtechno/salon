<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Order matters: permissions/modules/features before plans (plans
     * attach features), and PlatformAdminSeeder needs nothing from the
     * others, so it can run last.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            ModuleSeeder::class,
            FeatureSeeder::class,
            SubscriptionPlanSeeder::class,
            PlatformAdminSeeder::class,
        ]);
    }
}
