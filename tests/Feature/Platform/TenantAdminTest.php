<?php

use App\Domain\Platform\Actions\CreateQuotation;
use App\Domain\Platform\Models\Module;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Models\Quotation;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Domain\Platform\Models\TenantSubscription;
use App\Domain\Platform\Support\PlatformHealth;
use Illuminate\Support\Facades\Cache;

function adminFlexPlan(array $overrides = []): SubscriptionPlan
{
    Module::firstOrCreate(['code' => 'salon'], ['name' => 'Salon']);
    $plan = SubscriptionPlan::create(array_merge([
        'code' => 'admin-flex-'.uniqid(), 'name' => 'Flex Plan', 'price' => 1999, 'billing_interval' => 'monthly',
        'branch_limit' => 1, 'additional_branch_price' => 999, 'max_branches' => 4,
        'users_included' => 5, 'users_per_additional_branch' => 3, 'is_active' => true,
    ], $overrides));
    $plan->modules()->sync(Module::where('code', 'salon')->pluck('id'));

    return $plan;
}

function subscribedTenant(SubscriptionPlan $plan, int $daysLeft = 15): App\Models\User
{
    $owner = onboard(['subscription_plan_code' => $plan->code, 'subscription_ends_at' => now()->addDays($daysLeft)->startOfDay()]);
    TenantSubscription::where('tenant_id', $owner->tenant_id)->update(['starts_at' => now()->addDays($daysLeft)->subDays(30)->startOfDay()]);
    Tenant::forgetSubscriptionCache($owner->tenant_id);

    return $owner;
}

// ------------------------------------------------ logo + quotation PDF

test('the app logo is drawn on both the quotation and the invoice PDF', function () {
    $logo = App\Domain\Platform\Support\PlatformPdf::logoDataUri();
    expect($logo)->toStartWith('data:image/png;base64,');

    $owner = onboard();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $quotation = app(CreateQuotation::class)->execute($owner->tenant, $plan, createdBy: null);

    $quotationHtml = view('pdf.quotation', ['quotation' => $quotation->load('tenant', 'plan.modules'), 'supplier' => App\Domain\Platform\Support\PlatformPdf::supplier(), 'timezone' => 'Asia/Kolkata'])->render();
    expect($quotationHtml)->toContain('data:image/png;base64,')->toContain($quotation->quotation_number);

    $invoice = app(App\Domain\Platform\Actions\PayQuotation::class)->execute($quotation, 'upi', 'UTR1');
    $invoiceHtml = view('pdf.platform-invoice', ['invoice' => $invoice->load('tenant', 'plan'), 'supplier' => App\Domain\Platform\Support\PlatformPdf::supplier(), 'timezone' => 'Asia/Kolkata'])->render();
    expect($invoiceHtml)->toContain('data:image/png;base64,');
});

test('a quotation can be downloaded as a PDF by Super Admin and by the tenant it belongs to', function () {
    $owner = onboard();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $quotation = app(CreateQuotation::class)->execute($owner->tenant, $plan, createdBy: null);
    $admin = PlatformAdmin::factory()->create();

    $tenantPdf = $this->actingAs($owner, 'web')->get(route('billing.quotations.pdf', $quotation));
    $tenantPdf->assertOk();
    expect(substr($tenantPdf->getContent(), 0, 5))->toBe('%PDF-');
    expect($tenantPdf->headers->get('Content-Disposition'))->toContain(str_replace('/', '-', $quotation->quotation_number).'.pdf');

    $adminPdf = $this->actingAs($admin, 'platform')->get(route('platform.quotations.pdf', $quotation));
    $adminPdf->assertOk();
    expect(substr($adminPdf->getContent(), 0, 5))->toBe('%PDF-');

    $this->actingAs($owner, 'web')->get(route('billing.quotations.show', $quotation))->assertOk()->assertSee(route('billing.quotations.pdf', $quotation), false);
    $this->actingAs($admin, 'platform')->get(route('platform.quotations.show', $quotation))->assertOk()->assertSee(route('platform.quotations.pdf', $quotation), false);
});

test('a tenant cannot download another tenant\'s quotation PDF, and a guest cannot download any', function () {
    $a = onboard();
    $b = onboard();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $quotation = app(CreateQuotation::class)->execute($a->tenant, $plan, createdBy: null);

    $this->actingAs($b, 'web')->get(route('billing.quotations.pdf', $quotation))->assertNotFound();

    auth()->guard('web')->logout();
    $this->get(route('billing.quotations.pdf', $quotation))->assertRedirect();
    $this->get(route('platform.quotations.pdf', $quotation))->assertRedirect();
});

// ------------------------------------------------ mobile number + tenant list

test('signup asks for a valid mobile number and keeps it on the tenant', function () {
    $plan = SubscriptionPlan::where('code', 'salon')->firstOrFail();
    $base = [
        'business_name' => 'Glow Salon', 'subscription_plan_id' => $plan->id, 'owner_name' => 'Alice', 'owner_email' => 'alice@glow.test',
        'owner_password' => 'password123', 'owner_password_confirmation' => 'password123', 'billing_state' => 'Haryana',
    ];

    $this->post('/register', $base)->assertSessionHasErrors('owner_phone');
    $this->post('/register', $base + ['owner_phone' => '12345'])->assertSessionHasErrors('owner_phone');
    $this->post('/register', $base + ['owner_phone' => '1234567890'])->assertSessionHasErrors('owner_phone'); // must start 6-9

    $this->post('/register', $base + ['owner_phone' => '9876543210'])->assertRedirect(route('login'));
    expect(Tenant::where('name', 'Glow Salon')->firstOrFail()->phone)->toBe('9876543210');
});

test('super admin must give a mobile number when creating a tenant', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = SubscriptionPlan::where('code', 'salon')->firstOrFail();
    $base = [
        'business_name' => 'Glow Salon', 'subscription_plan_id' => $plan->id, 'owner_name' => 'Alice', 'owner_email' => 'alice@glow.test',
        'owner_password' => 'password123', 'owner_password_confirmation' => 'password123', 'billing_state' => 'Haryana',
    ];

    $this->actingAs($admin, 'platform')->post('/platform/tenants', $base)->assertSessionHasErrors('owner_phone');
    $this->actingAs($admin, 'platform')->post('/platform/tenants', $base + ['owner_phone' => '9876543210'])->assertRedirect();
});

test('the tenant list shows each tenant\'s email, mobile and plan', function () {
    $admin = PlatformAdmin::factory()->create();
    $plan = adminFlexPlan(['name' => 'Flex Plan']);
    $active = subscribedTenant($plan);
    $active->tenant->update(['name' => 'Active Salon', 'phone' => '9811122233']);
    TenantSubscription::where('tenant_id', $active->tenant_id)->update(['branch_count' => 3]);

    $pending = onboard(['activated' => false]);
    $pending->tenant->update(['name' => 'Pending Spa', 'phone' => '9899988877']);
    app(CreateQuotation::class)->execute($pending->tenant, SubscriptionPlan::where('code', 'growth')->firstOrFail(), createdBy: null);

    $page = $this->actingAs($admin, 'platform')->get('/platform/tenants')->assertOk();
    $page->assertSee('Active Salon')->assertSee($active->email)->assertSee('+91 9811122233')->assertSee('Flex Plan')->assertSee('3 branches');
    $page->assertSee('Pending Spa')->assertSee($pending->email)->assertSee('+91 9899988877')->assertSee('awaiting payment');
});

test('a tenant with no mobile on file shows a dash, not an error', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $owner->tenant->update(['phone' => null]);

    $this->actingAs($admin, 'platform')->get('/platform/tenants')->assertOk()->assertSee($owner->email);
});

test('super admin can set or change a tenant\'s mobile number', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();

    $this->actingAs($admin, 'platform')->patch("/platform/tenants/{$owner->tenant_id}/billing-state", [
        'billing_state' => 'Haryana', 'gstin' => '', 'phone' => '9876501234',
    ])->assertRedirect();
    expect($owner->tenant->fresh()->phone)->toBe('9876501234');

    $this->actingAs($admin, 'platform')->patch("/platform/tenants/{$owner->tenant_id}/billing-state", [
        'billing_state' => 'Haryana', 'phone' => '123',
    ])->assertSessionHasErrors('phone');

    // A save that does not mention the phone leaves it alone.
    $this->actingAs($admin, 'platform')->patch("/platform/tenants/{$owner->tenant_id}/billing-state", ['billing_state' => 'Delhi']);
    expect($owner->tenant->fresh()->phone)->toBe('9876501234');
});

// ------------------------------------------------ Super Admin adds branches to a tenant

test('the tenant page shows branches and users against the limits', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = subscribedTenant(adminFlexPlan());

    $this->actingAs($admin, 'platform')->get("/platform/tenants/{$owner->tenant_id}")
        ->assertOk()->assertSee('Branches &amp; users', false)->assertSee('0 in use of 1 allowed')->assertSee('1 in use of 5');
});

test('super admin can grant extra branches free, which also raises the tenant\'s user limit', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = subscribedTenant(adminFlexPlan());
    $tenant = Tenant::findOrFail($owner->tenant_id);
    expect($tenant->branchLimit())->toBe(1);
    expect($tenant->userLimit())->toBe(5);

    $this->actingAs($admin, 'platform')->post("/platform/tenants/{$tenant->id}/branches", ['additional' => 2, 'mode' => 'grant'])->assertRedirect();

    $tenant = $tenant->fresh();
    expect($tenant->branchLimit())->toBe(3);
    expect($tenant->userLimit())->toBe(11); // 5 + 2 x 3
    expect(Quotation::where('tenant_id', $tenant->id)->count())->toBe(0);
    expect(PlatformAuditLog::where('action', 'tenant.branches_granted')->where('tenant_id', $tenant->id)->exists())->toBeTrue();
});

test('super admin can instead charge for extra branches with a pro-rata quotation', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = subscribedTenant(adminFlexPlan(), 15); // half the cycle left

    $response = $this->actingAs($admin, 'platform')->post("/platform/tenants/{$owner->tenant_id}/branches", ['additional' => 2, 'mode' => 'quote']);

    $quotation = Quotation::where('tenant_id', $owner->tenant_id)->firstOrFail();
    $response->assertRedirect(route('platform.quotations.show', $quotation));
    expect($quotation->branch_count)->toBe(3);
    expect($quotation->is_upgrade)->toBeTrue();
    expect((float) $quotation->amount)->toBe(round(2 * 999 / 30 * 15, 2));

    // Nothing changes for the tenant until it is paid.
    expect(Tenant::findOrFail($owner->tenant_id)->branchLimit())->toBe(1);

    app(App\Domain\Platform\Actions\PayQuotation::class)->execute($quotation, 'upi', 'UTR9');
    expect(Tenant::findOrFail($owner->tenant_id)->branchLimit())->toBe(3);
});

test('extra branches cannot go past the plan maximum or be added on a plan that does not sell them', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = subscribedTenant(adminFlexPlan());
    $flat = subscribedTenant(adminFlexPlan(['additional_branch_price' => 0, 'max_branches' => null]));

    $this->actingAs($admin, 'platform')->post("/platform/tenants/{$owner->tenant_id}/branches", ['additional' => 4, 'mode' => 'grant'])->assertStatus(422); // 1 + 4 > 4
    $this->actingAs($admin, 'platform')->post("/platform/tenants/{$flat->tenant_id}/branches", ['additional' => 1, 'mode' => 'grant'])->assertStatus(422);
    $this->actingAs($admin, 'platform')->post("/platform/tenants/{$owner->tenant_id}/branches", ['additional' => 0, 'mode' => 'grant'])->assertSessionHasErrors('additional');
    $this->actingAs($admin, 'platform')->post("/platform/tenants/{$owner->tenant_id}/branches", ['additional' => 1, 'mode' => 'bogus'])->assertSessionHasErrors('mode');

    expect(Tenant::findOrFail($owner->tenant_id)->branchLimit())->toBe(1);
});

test('a tenant with no subscription yet cannot be given branches', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard(['activated' => false]);
    TenantSubscription::where('tenant_id', $owner->tenant_id)->delete();

    $this->actingAs($admin, 'platform')->post("/platform/tenants/{$owner->tenant_id}/branches", ['additional' => 1, 'mode' => 'grant'])->assertStatus(422);
});

test('only Super Admin can add branches to a tenant', function () {
    $owner = subscribedTenant(adminFlexPlan());

    $this->actingAs($owner, 'web')->post("/platform/tenants/{$owner->tenant_id}/branches", ['additional' => 1, 'mode' => 'grant'])->assertRedirect();
    expect(Tenant::findOrFail($owner->tenant_id)->branchLimit())->toBe(1);
});

// ------------------------------------------------ cron banner + hint

test('the cron warning tells Super Admin the exact line to add', function () {
    Cache::flush();
    Cache::put(PlatformHealth::HEARTBEAT_KEY, now()->subMinutes(30)->getTimestamp());
    $admin = PlatformAdmin::factory()->create();

    $this->actingAs($admin, 'platform')->get('/platform/tenants')->assertOk()
        ->assertSee('Scheduled jobs are not running')
        ->assertSee('schedule:run', false)
        ->assertSee(base_path(), false);
});

test('the new-tenant form explains how extra branches are assigned', function () {
    $admin = PlatformAdmin::factory()->create();

    $this->actingAs($admin, 'platform')->get('/platform/tenants/create')->assertOk()->assertSee('Number of branches')->assertSee('Mobile number');
});
