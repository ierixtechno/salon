<?php

namespace Database\Seeders;

use App\Domain\Platform\Models\Feature;
use Illuminate\Database\Seeder;

/**
 * The feature catalog (CLAUDE.md §10) — system capabilities gated by plan.
 * Seeded ahead of the modules that implement them so later phases only add
 * plan_features rows and the gating check, not a new migration.
 */
class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            ['code' => 'inventory', 'name' => 'Inventory Management', 'description' => 'Products, stock ledger, suppliers and purchasing.'],
            ['code' => 'online_booking', 'name' => 'Online Booking', 'description' => 'Public-facing customer self-service booking.'],
            ['code' => 'loyalty', 'name' => 'Loyalty Program', 'description' => 'Points-based customer loyalty ledger.'],
            ['code' => 'advanced_reports', 'name' => 'Advanced Reports', 'description' => 'Extended analytics and export capabilities.'],
            ['code' => 'whatsapp', 'name' => 'WhatsApp Notifications', 'description' => 'WhatsApp Business API notifications and marketing.'],
        ];

        foreach ($features as $feature) {
            Feature::updateOrCreate(['code' => $feature['code']], $feature);
        }
    }
}
