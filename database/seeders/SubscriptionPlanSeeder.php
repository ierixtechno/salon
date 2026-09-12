<?php

namespace Database\Seeders;

use App\Domain\Platform\Models\Feature;
use App\Domain\Platform\Models\Module;
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

        $this->seedModuleBasedPlans();
    }

    /**
     * Per-vertical plans, requested directly by name — one per module and
     * per the specific combinations asked for (deliberately NOT every
     * mathematically possible combination — e.g. no salon+spa or
     * salon+tattoo pairing was requested). Prices are starter defaults
     * ("fix some prices according to you, I will edit") — scaled roughly
     * by module count, editable afterward from Platform > Subscription
     * Plans without touching this seeder again. All get the full feature
     * set; the module combination is the differentiator here, not feature
     * completeness (that's what Growth/Pro above already do).
     */
    private function seedModuleBasedPlans(): void
    {
        $allFeatures = Feature::pluck('id');
        $moduleIds = fn (array $codes) => Module::whereIn('code', $codes)->pluck('id');

        $plans = [
            ['code' => 'salon', 'name' => 'Salon', 'price' => 1999, 'modules' => ['salon']],
            ['code' => 'spa', 'name' => 'Spa', 'price' => 1999, 'modules' => ['spa']],
            ['code' => 'beauty', 'name' => 'Beauty Parlour', 'price' => 1999, 'modules' => ['beauty']],
            ['code' => 'beauty_spa', 'name' => 'Beauty Parlour + Spa', 'price' => 3499, 'modules' => ['beauty', 'spa']],
            ['code' => 'salon_beauty', 'name' => 'Salon + Beauty Parlour', 'price' => 3499, 'modules' => ['salon', 'beauty']],
            ['code' => 'salon_beauty_spa', 'name' => 'Salon + Beauty Parlour + Spa', 'price' => 4999, 'modules' => ['salon', 'beauty', 'spa']],
            ['code' => 'tattoo', 'name' => 'Tattoo Design', 'price' => 1999, 'modules' => ['tattoo']],
        ];

        foreach ($plans as $definition) {
            $plan = SubscriptionPlan::updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'price' => $definition['price'],
                    'billing_interval' => 'monthly',
                    'is_active' => true,
                ],
            );

            $plan->modules()->sync($moduleIds($definition['modules']));
            $plan->features()->sync($allFeatures);
        }
    }
}
