<?php

namespace Database\Seeders;

use App\Domain\Platform\Models\Feature;
use App\Domain\Platform\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $trial = SubscriptionPlan::updateOrCreate(
            ['code' => 'trial'],
            ['name' => 'Free Trial', 'price' => 0, 'billing_interval' => 'trial', 'is_active' => true],
        );

        $growth = SubscriptionPlan::updateOrCreate(
            ['code' => 'growth'],
            ['name' => 'Growth', 'price' => 999, 'billing_interval' => 'monthly', 'is_active' => true],
        );

        $pro = SubscriptionPlan::updateOrCreate(
            ['code' => 'pro'],
            ['name' => 'Pro', 'price' => 2499, 'billing_interval' => 'monthly', 'is_active' => true],
        );

        // Trial: everything on, so signups can evaluate the full product during the trial window.
        $trial->features()->sync(Feature::pluck('id'));

        // Growth: core operations, no advanced reports/WhatsApp yet.
        $growth->features()->sync(
            Feature::whereIn('code', ['inventory', 'online_booking', 'loyalty'])->pluck('id')
        );

        // Pro: everything.
        $pro->features()->sync(Feature::pluck('id'));
    }
}
