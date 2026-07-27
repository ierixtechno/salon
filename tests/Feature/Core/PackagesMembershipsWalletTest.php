<?php

use App\Domain\Core\Actions\AddInvoiceLine;
use App\Domain\Core\Actions\BookAppointment;
use App\Domain\Core\Actions\CheckInAppointment;
use App\Domain\Core\Actions\CheckoutSale;
use App\Domain\Core\Actions\CompleteAppointment;
use App\Domain\Core\Actions\CreateDraftInvoice;
use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Actions\SellMembershipToCustomer;
use App\Domain\Core\Actions\SellPackageToCustomer;
use App\Domain\Core\Actions\StartAppointmentService;
use App\Domain\Core\Actions\UpdateBranchModules;
use App\Domain\Core\Actions\UpdateMembershipPlanApplicability;
use App\Domain\Core\Actions\UpdatePackageServices;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\BusinessProfile;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\CustomerMembership;
use App\Domain\Core\Models\CustomerPackage;
use App\Domain\Core\Models\EmployeeSchedule;
use App\Domain\Core\Models\GiftCard;
use App\Domain\Core\Models\MembershipPlan;
use App\Domain\Core\Models\Package;
use App\Domain\Core\Models\Refund;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\Module;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function phase8Fixture(): array
{
    $owner = onboard(['modules' => ['salon']]);
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    app(UpdateBranchModules::class)->execute($branch, ['salon']);

    $category = ServiceCategory::factory()->forTenant($owner->tenant)->create([
        'module_id' => Module::where('code', 'salon')->firstOrFail()->id,
    ]);
    $service = Service::factory()->forCategory($category)->create([
        'duration_minutes' => 30, 'buffer_minutes' => 0, 'base_price' => 500, 'tax_rate_percent' => 18,
    ]);
    $service->branches()->attach($branch->id, ['is_available' => true]);

    $employee = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Stylist', 'email' => fake()->unique()->safeEmail(), 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);
    $service->capableEmployees()->attach($employee->id);
    foreach (range(0, 6) as $d) {
        $schedule = new EmployeeSchedule(['user_id' => $employee->id, 'branch_id' => $branch->id, 'day_of_week' => $d, 'starts_at' => '09:00', 'ends_at' => '18:00']);
        $schedule->tenant_id = $owner->tenant_id;
        $schedule->save();
    }

    $customer = Customer::factory()->forTenant($owner->tenant)->create();

    return compact('owner', 'branch', 'service', 'employee', 'customer');
}

function phase8CompletedAppointment(array $fixture, int $dayOffset = 1)
{
    $appointment = app(BookAppointment::class)->execute(
        $fixture['branch'], $fixture['customer'], $fixture['service'], null, $fixture['employee'], null,
        now()->addDays($dayOffset)->setTime(10, 0),
    );
    app(CheckInAppointment::class)->execute($appointment);
    app(StartAppointmentService::class)->execute($appointment->fresh());

    return app(CompleteAppointment::class)->execute($appointment->fresh());
}

test('an owner can sell a package, redeem it against completed appointments, and it exhausts', function () {
    $fixture = phase8Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $package = Package::factory()->forTenant($owner->tenant)->create(['price' => 900]);
    app(UpdatePackageServices::class)->execute($package, [['service_id' => $fixture['service']->id, 'quantity' => 2]]);

    $this->post("/customers/{$fixture['customer']->id}/packages", [
        'branch_id' => $fixture['branch']->id,
        'package_id' => $package->id,
        'price_paid' => 900,
        'purchase_method' => 'cash',
    ])->assertRedirect();

    $customerPackage = CustomerPackage::firstOrFail();
    $item = $customerPackage->items()->firstOrFail();
    expect($item->quantity_purchased)->toBe(2);

    $appointment1 = phase8CompletedAppointment($fixture, 1);
    $this->post("/appointments/{$appointment1->id}/redeem-package", [
        'customer_package_item_id' => $item->id,
    ])->assertRedirect();
    expect($item->fresh()->quantityRemaining())->toBe(1);
    expect($customerPackage->fresh()->status)->toBe('active');

    $appointment2 = phase8CompletedAppointment($fixture, 2);
    $this->post("/appointments/{$appointment2->id}/redeem-package", [
        'customer_package_item_id' => $item->id,
    ])->assertRedirect();
    expect($item->fresh()->quantityRemaining())->toBe(0);
    expect($customerPackage->fresh()->status)->toBe('exhausted');

    $appointment3 = phase8CompletedAppointment($fixture, 3);
    $this->post("/appointments/{$appointment3->id}/redeem-package", [
        'customer_package_item_id' => $item->id,
    ])->assertStatus(409);
});

test('a manager can cancel a customer package', function () {
    $fixture = phase8Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $package = Package::factory()->forTenant($owner->tenant)->create();
    app(UpdatePackageServices::class)->execute($package, [['service_id' => $fixture['service']->id, 'quantity' => 3]]);
    $customerPackage = app(SellPackageToCustomer::class)->execute($fixture['branch'], $fixture['customer'], $package, 900, 'cash', null, $owner->id);

    $this->post("/customers/{$fixture['customer']->id}/packages/{$customerPackage->id}/cancel", [
        'reason' => 'customer requested',
    ])->assertRedirect();

    expect($customerPackage->fresh()->status)->toBe('cancelled');
});

test('a membership discount is applied server-side when adding an invoice line and cannot be combined with a manual discount', function () {
    $fixture = phase8Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $plan = MembershipPlan::factory()->forTenant($owner->tenant)->create(['discount_percent' => 20]);
    app(UpdateMembershipPlanApplicability::class)->execute($plan, [], [], []);

    $this->post("/customers/{$fixture['customer']->id}/memberships", [
        'branch_id' => $fixture['branch']->id,
        'membership_plan_id' => $plan->id,
        'price_paid' => 5000,
        'purchase_method' => 'cash',
    ])->assertRedirect();

    $membership = CustomerMembership::firstOrFail();

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);

    // Combining a manual discount with a membership is rejected.
    $this->post("/invoices/{$invoice->id}/lines", [
        'service_id' => $fixture['service']->id,
        'customer_membership_id' => $membership->id,
        'discount_amount' => 10,
    ])->assertSessionHasErrors('discount_amount');

    $this->post("/invoices/{$invoice->id}/lines", [
        'service_id' => $fixture['service']->id,
        'customer_membership_id' => $membership->id,
    ])->assertRedirect();

    $line = $invoice->lines()->firstOrFail();
    expect((float) $line->discount_amount)->toBe(100.0); // 20% of 500
    expect($membership->fresh()->usage_count)->toBe(1);
});

test('a membership belonging to a different customer cannot be applied to this invoice', function () {
    $fixture = phase8Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $otherCustomer = Customer::factory()->forTenant($owner->tenant)->create();
    $plan = MembershipPlan::factory()->forTenant($owner->tenant)->create();
    app(UpdateMembershipPlanApplicability::class)->execute($plan, [], [], []);
    $membership = app(SellMembershipToCustomer::class)->execute(
        $fixture['branch'], $otherCustomer, $plan, 5000, 'cash', null, $owner->id,
    );

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);

    $this->post("/invoices/{$invoice->id}/lines", [
        'service_id' => $fixture['service']->id,
        'customer_membership_id' => $membership->id,
    ])->assertSessionHasErrors('customer_membership_id');
});

test('loyalty points are earned automatically when enabled and can be redeemed as a payment method', function () {
    $fixture = phase8Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    BusinessProfile::updateOrCreate(
        ['tenant_id' => $owner->tenant_id],
        ['display_name' => 'Test Salon', 'loyalty_points_per_100' => 1, 'loyalty_redemption_value' => 0.5],
    );

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute($invoice, $fixture['service'], null, null, 1, 0);
    $invoice = app(CheckoutSale::class)->execute($invoice);
    $grandTotal = (float) $invoice->grand_total;

    $this->post("/invoices/{$invoice->id}/payments", [
        'method' => 'cash', 'amount' => $grandTotal, 'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect();

    // floor(590 / 100 * 1) = 5 points earned.
    expect($fixture['customer']->fresh()->loyaltyPointsBalance())->toBe(5);

    $invoice2 = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute($invoice2, $fixture['service'], null, null, 1, 0);
    $invoice2 = app(CheckoutSale::class)->execute($invoice2);

    $this->post("/invoices/{$invoice2->id}/redeem-loyalty", [
        'points' => 3, 'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect();

    expect($fixture['customer']->fresh()->loyaltyPointsBalance())->toBe(2);

    // Redeeming more than the available balance is rejected.
    $this->post("/invoices/{$invoice2->id}/redeem-loyalty", [
        'points' => 100, 'idempotency_key' => (string) Str::uuid(),
    ])->assertStatus(409);
});

test('wallet can be credited manually and redeemed against an invoice, and cannot overdraw', function () {
    $fixture = phase8Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $this->post("/customers/{$fixture['customer']->id}/wallet/credit", [
        'amount' => 100, 'reason' => 'goodwill credit',
    ])->assertRedirect();
    expect($fixture['customer']->fresh()->walletBalance())->toBe('100.00');

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute($invoice, $fixture['service'], null, null, 1, 0);
    $invoice = app(CheckoutSale::class)->execute($invoice);

    $this->post("/invoices/{$invoice->id}/redeem-wallet", [
        'amount' => 50, 'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect();
    expect($fixture['customer']->fresh()->walletBalance())->toBe('50.00');

    $this->post("/invoices/{$invoice->id}/redeem-wallet", [
        'amount' => 9999, 'idempotency_key' => (string) Str::uuid(),
    ])->assertStatus(409);
});

test('a gift card can be issued and redeemed against an invoice, and cannot be redeemed past its balance', function () {
    $fixture = phase8Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $this->post('/gift-cards', [
        'initial_value' => 300, 'purchase_method' => 'cash',
    ])->assertRedirect();
    $giftCard = GiftCard::firstOrFail();

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute($invoice, $fixture['service'], null, null, 1, 0);
    $invoice = app(CheckoutSale::class)->execute($invoice);

    $this->post("/invoices/{$invoice->id}/redeem-gift-card", [
        'code' => $giftCard->code, 'amount' => 100, 'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect();
    expect($giftCard->fresh()->balance())->toBe('200.00');

    $this->post("/invoices/{$invoice->id}/redeem-gift-card", [
        'code' => $giftCard->code, 'amount' => 9999, 'idempotency_key' => (string) Str::uuid(),
    ])->assertStatus(409);

    $this->post("/gift-cards/{$giftCard->id}/cancel")->assertRedirect();
    expect($giftCard->fresh()->status)->toBe('cancelled');
    expect($giftCard->fresh()->balance())->toBe('0.00');
});

test('refunding via wallet or loyalty credits the customer\'s own balance', function () {
    $fixture = phase8Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    BusinessProfile::updateOrCreate(
        ['tenant_id' => $owner->tenant_id],
        ['display_name' => 'Test Salon', 'loyalty_points_per_100' => 1, 'loyalty_redemption_value' => 0.5],
    );

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute($invoice, $fixture['service'], null, null, 1, 0);
    $invoice = app(CheckoutSale::class)->execute($invoice);

    $this->post("/invoices/{$invoice->id}/payments", [
        'method' => 'cash', 'amount' => (float) $invoice->grand_total, 'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect();

    $this->post("/invoices/{$invoice->id}/refunds", [
        'method' => 'wallet', 'amount' => 30,
    ])->assertRedirect();
    expect($fixture['customer']->fresh()->walletBalance())->toBe('30.00');

    $this->post("/invoices/{$invoice->id}/refunds", [
        'method' => 'loyalty', 'amount' => 1,
    ])->assertRedirect();
    // +2 points from the 1-rupee loyalty refund credit (1 / 0.5 = 2).
    $refund = Refund::where('method', 'loyalty')->firstOrFail();
    expect($refund->points_credited)->toBe(2);
});

test('a manager can operate packages/memberships/wallet/gift-cards but cannot delete a package template or cancel a gift card', function () {
    $fixture = phase8Fixture();
    $owner = $fixture['owner'];

    $manager = User::factory()->forTenant($owner->tenant)->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($owner->tenant_id);
    $manager->assignRole('Manager');

    $package = Package::factory()->forTenant($owner->tenant)->create();
    $this->actingAs($manager)->delete("/packages/{$package->id}")->assertForbidden();

    $this->actingAs($manager)->post('/gift-cards', [
        'initial_value' => 100, 'purchase_method' => 'cash',
    ])->assertRedirect();
    $giftCard = GiftCard::firstOrFail();
    $this->actingAs($manager)->post("/gift-cards/{$giftCard->id}/cancel")->assertForbidden();

    $this->actingAs($manager)->post("/customers/{$fixture['customer']->id}/wallet/credit", [
        'amount' => 50,
    ])->assertRedirect();
});

test('staff have read-only access and cannot sell packages, credit wallets, or issue gift cards', function () {
    $fixture = phase8Fixture();
    $owner = $fixture['owner'];

    $staff = User::factory()->forTenant($owner->tenant)->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($owner->tenant_id);
    $staff->assignRole('Staff');

    $this->actingAs($staff)->get('/packages')->assertOk();
    $package = Package::factory()->forTenant($owner->tenant)->create();
    $this->actingAs($staff)->post("/customers/{$fixture['customer']->id}/packages", [
        'branch_id' => $fixture['branch']->id, 'package_id' => $package->id,
        'price_paid' => 900, 'purchase_method' => 'cash',
    ])->assertForbidden();

    $this->actingAs($staff)->post("/customers/{$fixture['customer']->id}/wallet/credit", ['amount' => 50])->assertForbidden();
    $this->actingAs($staff)->get('/gift-cards/create')->assertForbidden();
});

/**
 * Mandatory pattern per CLAUDE.md §62.
 */
test('a user from tenant B cannot view or sell against a package belonging to tenant A, nor see its customer packages', function () {
    $fixture = phase8Fixture();
    $ownerA = $fixture['owner'];
    $ownerB = onboard(['modules' => ['salon']]);
    $this->actingAs($ownerA);

    $package = Package::factory()->forTenant($ownerA->tenant)->create();
    app(UpdatePackageServices::class)->execute($package, [['service_id' => $fixture['service']->id, 'quantity' => 1]]);
    $customerPackage = app(SellPackageToCustomer::class)->execute($fixture['branch'], $fixture['customer'], $package, 900, 'cash', null, $ownerA->id);

    $this->actingAs($ownerB)->get("/packages/{$package->id}/edit")->assertNotFound();
    $this->actingAs($ownerB)->get("/customers/{$fixture['customer']->id}/packages")->assertNotFound();
    $this->actingAs($ownerB)->post("/customers/{$fixture['customer']->id}/packages/{$customerPackage->id}/cancel")->assertNotFound();

    expect(Package::withoutGlobalScope(TenantScope::class)->find($package->id))->not->toBeNull();
    expect(CustomerPackage::withoutGlobalScope(TenantScope::class)->find($customerPackage->id)->status)->toBe('active');
});
