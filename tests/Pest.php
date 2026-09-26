<?php

use App\Domain\Platform\Actions\OnboardTenant;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;
use App\Models\User;
use Database\Seeders\FeatureSeeder;
use Database\Seeders\ModuleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Platform reference catalog (modules/features/plans/permissions)
        // is foundational data every environment has — not "not currently
        // in scope" fixture data individual tests should have to know
        // about. PlatformAdminSeeder is deliberately excluded: tests that
        // need a Super Admin use PlatformAdmin::factory() explicitly.
        $this->seed([
            PermissionSeeder::class,
            ModuleSeeder::class,
            FeatureSeeder::class,
            SubscriptionPlanSeeder::class,
        ]);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Onboards a full tenant + owner (roles, modules) via the real OnboardTenant
 * action, so feature tests exercise actual permission-bearing users rather
 * than bare factory Users with no RBAC context.
 *
 * Real signups now start 'pending_payment' with no subscription at all
 * (EnforceSubscriptionAccess blocks everything until paid — see
 * OnboardTenant). Since almost every existing test is exercising ordinary
 * in-app behavior, not the activation gate itself, this helper synthesizes
 * an already-active tenant by default (skipping the real Quotation/pay
 * ceremony for speed) so the other ~200 tests keep testing what they were
 * testing. Pass `'activated' => false` to get a genuinely pending tenant, or
 * `'subscription_ends_at' => now()->subDays(...)` to land directly in the
 * grace/blocked window, for tests that exercise the gate itself.
 */
function onboard(array $overrides = []): User
{
    $activated = $overrides['activated'] ?? true;
    $subscriptionEndsAt = $overrides['subscription_ends_at'] ?? now()->addMonth();
    $subscriptionPlanCode = $overrides['subscription_plan_code'] ?? 'growth';
    unset($overrides['activated'], $overrides['subscription_ends_at'], $overrides['subscription_plan_code']);

    $owner = app(OnboardTenant::class)->execute(array_merge([
        'business_name' => fake()->unique()->company(),
        'timezone' => 'Asia/Kolkata',
        'currency' => 'INR',
        'modules' => ['salon'],
        'owner_name' => 'Owner',
        'owner_phone' => '9876543210', 'owner_email' => fake()->unique()->safeEmail(),
        'owner_password' => 'password123',
        // Same state as the platform by default, so existing tests keep
        // getting CGST+SGST (not IGST) unless they explicitly override
        // this to exercise the inter-state path — see CreateQuotation.
        'billing_state' => config('platform.state'),
    ], $overrides));

    if ($activated) {
        $tenant = Tenant::findOrFail($owner->tenant_id);
        $tenant->update(['status' => 'active']);

        TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => SubscriptionPlan::where('code', $subscriptionPlanCode)->firstOrFail()->id,
            'status' => 'active',
            'starts_at' => now()->subMonth(),
            'ends_at' => $subscriptionEndsAt,
        ]);
    }

    return $owner;
}
