<?php

namespace Database\Seeders;

use App\Domain\Platform\Models\Module;
use Illuminate\Database\Seeder;

/**
 * The three vertical modules are a fixed, platform-level catalog
 * (CLAUDE.md §6) — stable machine-readable codes, never display labels.
 */
class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['code' => 'salon', 'name' => 'Salon'],
            ['code' => 'beauty', 'name' => 'Beauty Parlour'],
            ['code' => 'spa', 'name' => 'Spa'],
            ['code' => 'tattoo', 'name' => 'Tattoo Studio'],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(['code' => $module['code']], $module);
        }
    }
}
