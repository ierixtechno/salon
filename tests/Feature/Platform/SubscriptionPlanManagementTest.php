<?php

use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\SubscriptionPlan;

test('a platform admin can view the subscription plans list and edit a plan', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();

    $this->actingAs($admin, 'platform')->get('/platform/subscription-plans')
        ->assertOk()
        ->assertSee('Growth');

    $this->actingAs($admin, 'platform')->get("/platform/subscription-plans/{$plan->id}/edit")
        ->assertOk()
        ->assertSee('Inventory Management');

    $this->actingAs($admin, 'platform')->put("/platform/subscription-plans/{$plan->id}", [
        'name' => 'Growth',
        'price' => 1499,
        'billing_interval' => 'monthly',
        'is_active' => '1',
        'features' => ['whatsapp', 'inventory'],
        'modules' => [],
    ])->assertRedirect('/platform/subscription-plans');

    $plan->refresh();
    expect((float) $plan->price)->toBe(1499.0);
    expect($plan->features()->pluck('code')->all())->toEqualCanonicalizing(['whatsapp', 'inventory']);
});

test('deactivating a plan removes it from active but keeps its history', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = SubscriptionPlan::where('code', 'pro')->firstOrFail();

    $this->actingAs($admin, 'platform')->put("/platform/subscription-plans/{$plan->id}", [
        'name' => $plan->name,
        'price' => $plan->price,
        'billing_interval' => $plan->billing_interval,
        'features' => [],
        'modules' => [],
    ])->assertRedirect();

    expect($plan->fresh()->is_active)->toBeFalse();
    expect($plan->fresh()->features()->count())->toBe(0);
    expect(SubscriptionPlan::where('code', 'pro')->exists())->toBeTrue();
});

test('an unauthenticated guest cannot manage subscription plans', function () {
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();

    $this->get('/platform/subscription-plans')->assertRedirect('/platform/login');
    $this->get("/platform/subscription-plans/{$plan->id}/edit")->assertRedirect('/platform/login');
    $this->put("/platform/subscription-plans/{$plan->id}", ['name' => 'x'])->assertRedirect('/platform/login');
});

test('a tenant user cannot manage subscription plans', function () {
    $owner = onboard();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();

    $this->actingAs($owner)->get('/platform/subscription-plans')->assertRedirect('/platform/login');
});

test('a platform admin can create a subscription plan bundling modules', function () {
    $admin = PlatformAdmin::factory()->create();

    $this->actingAs($admin, 'platform')->post('/platform/subscription-plans', [
        'code' => 'salon-beauty-bundle',
        'name' => 'Salon + Beauty Bundle',
        'price' => 1999,
        'billing_interval' => 'monthly',
        'is_active' => '1',
        'features' => ['inventory'],
        'modules' => ['salon', 'beauty'],
    ])->assertRedirect('/platform/subscription-plans');

    $plan = SubscriptionPlan::where('code', 'salon-beauty-bundle')->firstOrFail();
    expect($plan->modules()->pluck('code')->all())->toEqualCanonicalizing(['salon', 'beauty']);
});

test('a platform admin can change which modules a plan bundles', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $plan->modules()->sync(Module::whereIn('code', ['salon'])->pluck('id'));

    $this->actingAs($admin, 'platform')->put("/platform/subscription-plans/{$plan->id}", [
        'name' => $plan->name,
        'price' => $plan->price,
        'billing_interval' => $plan->billing_interval,
        'is_active' => '1',
        'features' => [],
        'modules' => ['salon', 'spa'],
    ])->assertRedirect('/platform/subscription-plans');

    expect($plan->fresh()->modules()->pluck('code')->all())->toEqualCanonicalizing(['salon', 'spa']);
});
