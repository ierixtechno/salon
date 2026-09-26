<?php

use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Actions\ProcessRefund;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\CustomerMembership;
use App\Domain\Core\Models\CustomerPackage;
use App\Domain\Core\Models\Expense;
use App\Domain\Core\Models\ExpenseCategory;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\MembershipPlan;
use App\Domain\Core\Models\Package;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Platform\Models\Module;

function saleFixture(): array
{
    $owner = onboard(['modules' => ['salon']]);
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    $customer = Customer::factory()->forTenant($owner->tenant)->create();

    return compact('owner', 'branch', 'customer');
}

function sellPackagePayload(array $f, Package $package, array $overrides = []): array
{
    return array_merge([
        'branch_id' => $f['branch']->id, 'package_id' => $package->id,
        'price_paid' => 1000, 'purchase_method' => 'upi', 'purchase_reference' => 'UTR55',
    ], $overrides);
}

// ------------------------------------------------ 1. package/membership -> invoice

test('selling a package bills it on a paid GST invoice linked to the package', function () {
    $f = saleFixture();
    $package = Package::factory()->forTenant($f['owner']->tenant)->create(['price' => 1000, 'tax_rate_percent' => 18]);

    $response = $this->actingAs($f['owner'])->post("/customers/{$f['customer']->id}/packages", sellPackagePayload($f, $package));

    $sold = CustomerPackage::firstOrFail();
    $invoice = Invoice::findOrFail($sold->invoice_id);
    $response->assertRedirect(route('invoices.show', $invoice));

    expect($invoice->status)->toBe('paid');
    expect($invoice->invoice_number)->not->toBeNull();
    expect((float) $invoice->subtotal)->toBe(1000.0);
    expect((float) $invoice->cgst_total)->toBe(90.0);
    expect((float) $invoice->sgst_total)->toBe(90.0);
    expect((float) $invoice->grand_total)->toBe(1180.0);
    expect($invoice->customer_id)->toBe($f['customer']->id);

    // What was collected is the tax-inclusive total, taken with the chosen method.
    expect((float) $sold->price_paid)->toBe(1180.0);
    $payment = $invoice->payments()->firstOrFail();
    expect($payment->method)->toBe('upi');
    expect((float) $payment->amount)->toBe(1180.0);
    expect($payment->reference)->toBe('UTR55');

    $line = $invoice->lines()->firstOrFail();
    expect($line->service_id)->toBeNull();
    expect($line->description)->toBe('Package: '.$package->name);

    $this->actingAs($f['owner'])->get(route('invoices.show', $invoice))->assertOk()->assertSee('Package: '.$package->name);
});

test('selling a membership bills it on a paid GST invoice linked to the membership', function () {
    $f = saleFixture();
    $plan = MembershipPlan::factory()->forTenant($f['owner']->tenant)->create(['price' => 2000, 'tax_rate_percent' => 5]);

    $this->actingAs($f['owner'])->post("/customers/{$f['customer']->id}/memberships", [
        'branch_id' => $f['branch']->id, 'membership_plan_id' => $plan->id,
        'price_paid' => 2000, 'purchase_method' => 'card',
    ])->assertRedirect();

    $sold = CustomerMembership::firstOrFail();
    $invoice = Invoice::findOrFail($sold->invoice_id);

    expect($invoice->status)->toBe('paid');
    expect((float) $invoice->grand_total)->toBe(2100.0);
    expect((float) $sold->price_paid)->toBe(2100.0);
    expect($invoice->payments()->firstOrFail()->method)->toBe('card');
    expect($invoice->lines()->firstOrFail()->description)->toBe('Membership: '.$plan->name);
});

test('a package with no GST configured is invoiced at its plain price', function () {
    $f = saleFixture();
    $package = Package::factory()->forTenant($f['owner']->tenant)->create(['price' => 900]);

    $this->actingAs($f['owner'])->post("/customers/{$f['customer']->id}/packages", sellPackagePayload($f, $package, ['price_paid' => 900]));

    $invoice = Invoice::findOrFail(CustomerPackage::firstOrFail()->invoice_id);
    expect((float) $invoice->grand_total)->toBe(900.0);
    expect((float) $invoice->tax_total)->toBe(0.0);
});

test('a free package is given without an invoice, since there is nothing to bill', function () {
    $f = saleFixture();
    $package = Package::factory()->forTenant($f['owner']->tenant)->create(['price' => 0]);

    $this->actingAs($f['owner'])->post("/customers/{$f['customer']->id}/packages", sellPackagePayload($f, $package, ['price_paid' => 0]))->assertRedirect();

    expect(CustomerPackage::firstOrFail()->invoice_id)->toBeNull();
    expect(Invoice::count())->toBe(0);
});

test('the tax rate comes from the package, never from the browser', function () {
    $f = saleFixture();
    $package = Package::factory()->forTenant($f['owner']->tenant)->create(['price' => 1000, 'tax_rate_percent' => 18]);

    $this->actingAs($f['owner'])->post("/customers/{$f['customer']->id}/packages", sellPackagePayload($f, $package, ['tax_rate_percent' => 0, 'grand_total' => 1]));

    expect((float) Invoice::firstOrFail()->grand_total)->toBe(1180.0);
});

test('consecutive package sales get consecutive invoice numbers', function () {
    $f = saleFixture();
    $package = Package::factory()->forTenant($f['owner']->tenant)->create(['price' => 100]);

    foreach ([1, 2] as $_) {
        $this->actingAs($f['owner'])->post("/customers/{$f['customer']->id}/packages", sellPackagePayload($f, $package, ['price_paid' => 100]));
    }

    expect(Invoice::orderBy('id')->pluck('sequence_number')->all())->toBe([1, 2]);
});

test('a package and its invoice are created together or not at all', function () {
    $f = saleFixture();
    $package = Package::factory()->forTenant($f['owner']->tenant)->create(['price' => 100]);

    // Another tenant's branch cannot be billed against.
    $other = saleFixture();
    $this->actingAs($f['owner'])->post("/customers/{$f['customer']->id}/packages", sellPackagePayload($f, $package, ['branch_id' => $other['branch']->id]))
        ->assertSessionHasErrors('branch_id');

    expect(CustomerPackage::count())->toBe(0);
    expect(Invoice::count())->toBe(0);
});

test('package and plan forms require a GST rate', function () {
    $owner = onboard();

    $this->actingAs($owner)->post('/packages', ['name' => 'Bridal', 'validity_days' => 90, 'price' => 5000])
        ->assertSessionHasErrors('tax_rate_percent');
    $this->actingAs($owner)->post('/packages', ['name' => 'Bridal', 'validity_days' => 90, 'price' => 5000, 'tax_rate_percent' => 18])
        ->assertSessionHasNoErrors();
    expect((float) Package::firstOrFail()->tax_rate_percent)->toBe(18.0);
});

// ------------------------------------------------ 2. membership create + assign UI

test('the sidebar links to Memberships, and each plan/package has a Sell page', function () {
    $f = saleFixture();
    $plan = MembershipPlan::factory()->forTenant($f['owner']->tenant)->create();
    $package = Package::factory()->forTenant($f['owner']->tenant)->create();

    $this->actingAs($f['owner'])->get('/dashboard')->assertOk()->assertSee(route('membership-plans.index'), false);

    $this->actingAs($f['owner'])->get('/membership-plans')->assertOk()->assertSee(route('membership-plans.sell', $plan), false);
    $this->actingAs($f['owner'])->get('/packages')->assertOk()->assertSee(route('packages.sell', $package), false);

    $this->actingAs($f['owner'])->get(route('membership-plans.sell', $plan))
        ->assertOk()->assertSee($f['customer']->name)->assertSee('Sell &amp; create invoice', false);
    $this->actingAs($f['owner'])->get(route('packages.sell', $package))->assertOk()->assertSee($f['customer']->name);
});

test('a user without the sell permission cannot open the sell pages', function () {
    $f = saleFixture();
    $plan = MembershipPlan::factory()->forTenant($f['owner']->tenant)->create();
    $package = Package::factory()->forTenant($f['owner']->tenant)->create();
    $staff = app(CreateEmployee::class)->execute($f['owner']->tenant, [
        'name' => 'Sam', 'email' => 'sam@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);

    $this->actingAs($staff)->get(route('membership-plans.sell', $plan))->assertForbidden();
    $this->actingAs($staff)->get(route('packages.sell', $package))->assertForbidden();
});

test('another tenant\'s plan or package cannot be sold', function () {
    $a = saleFixture();
    $b = saleFixture();
    $planB = MembershipPlan::factory()->forTenant($b['owner']->tenant)->create();
    $packageB = Package::factory()->forTenant($b['owner']->tenant)->create();

    $this->actingAs($a['owner'])->get(route('membership-plans.sell', $planB))->assertNotFound();
    $this->actingAs($a['owner'])->get(route('packages.sell', $packageB))->assertNotFound();
});

// ------------------------------------------------ 3. payment ledger

function ledgerTenant(): array
{
    $f = saleFixture();
    $package = Package::factory()->forTenant($f['owner']->tenant)->create(['price' => 1000, 'tax_rate_percent' => 18]);
    test()->actingAs($f['owner'])->post("/customers/{$f['customer']->id}/packages", sellPackagePayload($f, $package));

    return $f + ['invoice' => Invoice::firstOrFail()];
}

test('the payment ledger lists money in from a package sale, money out from a refund and an expense', function () {
    $f = ledgerTenant();
    $this->actingAs($f['owner']);

    app(ProcessRefund::class)->execute($f['invoice'], 180.0, 'upi', 'Goodwill', $f['owner']->id);

    $category = new ExpenseCategory(['name' => 'Insurance']);
    $category->tenant_id = $f['owner']->tenant_id;
    $category->save();
    $expense = new Expense([
        'branch_id' => $f['branch']->id, 'expense_category_id' => $category->id, 'vendor_name' => 'Landlord Co',
        'amount' => 500, 'tax_amount' => 0, 'payment_method' => 'cash', 'expense_date' => now()->toDateString(), 'created_by' => $f['owner']->id,
    ]);
    $expense->tenant_id = $f['owner']->tenant_id;
    $expense->status = 'approved';
    $expense->save();

    $response = $this->get('/reports/ledger')->assertOk();
    $response->assertSee($f['invoice']->invoice_number)
        ->assertSee('Invoice payment')->assertSee('Refund')->assertSee('Expense')->assertSee('Landlord Co')
        ->assertSee('1,180.00')->assertSee('180.00')->assertSee('500.00');

    expect($response->viewData('totalIn'))->toBe(1180.0);
    expect($response->viewData('totalOut'))->toBe(680.0);

    $this->get('/reports/ledger?direction=out')->assertOk()->assertDontSee('Invoice payment');
    $this->get('/reports/ledger?direction=in')->assertOk()->assertDontSee('Landlord Co');
    $this->get('/reports/ledger?method=cash')->assertOk()->assertSee('Landlord Co')->assertDontSee('Invoice payment');
});

test('wallet, gift-card and loyalty redemptions are not counted as money in', function () {
    $f = saleFixture();
    $this->actingAs($f['owner']);

    $invoice = new Invoice(['branch_id' => $f['branch']->id, 'customer_id' => $f['customer']->id, 'customer_name' => 'X']);
    $invoice->tenant_id = $f['owner']->tenant_id;
    $invoice->status = 'paid';
    $invoice->invoice_number = 'T/1';
    $invoice->save();
    foreach (['wallet', 'gift_card', 'loyalty'] as $i => $method) {
        $p = new App\Domain\Core\Models\Payment(['invoice_id' => $invoice->id, 'method' => $method, 'amount' => 100, 'tip_amount' => 0, 'idempotency_key' => (string) Str::uuid()]);
        $p->tenant_id = $f['owner']->tenant_id;
        $p->save();
    }

    $response = $this->get('/reports/ledger')->assertOk();
    expect($response->viewData('totalIn'))->toBe(0.0);
});

test('the ledger can be exported as CSV', function () {
    $f = ledgerTenant();

    $response = $this->actingAs($f['owner'])->get('/reports/ledger?export=csv');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    $csv = $response->streamedContent();
    expect($csv)->toContain('Money in')->toContain($f['invoice']->invoice_number)->toContain('1180.00');
});

test('the ledger only shows the current tenant\'s money', function () {
    $a = ledgerTenant();
    $b = saleFixture();

    $this->actingAs($b['owner'])->get('/reports/ledger')
        ->assertOk()
        ->assertDontSee($a['invoice']->invoice_number)
        ->assertSee('No money in or out in this period.');
});

test('a branch-restricted user does not see other branches\' money in the ledger', function () {
    $f = ledgerTenant();
    $otherBranch = Branch::factory()->forTenant($f['owner']->tenant)->create();
    $manager = app(CreateEmployee::class)->execute($f['owner']->tenant, [
        'name' => 'Mia', 'email' => 'mia@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Manager', 'all_branches' => false, 'branches' => [$otherBranch->id],
    ]);

    $this->actingAs($manager)->get('/reports/ledger')->assertOk()->assertDontSee($f['invoice']->invoice_number);
    // ...and forcing the other branch through the query string doesn't help.
    $this->actingAs($manager)->get('/reports/ledger?branch_id='.$f['branch']->id)->assertOk()->assertDontSee($f['invoice']->invoice_number);
});

test('the ledger needs the reports permission', function () {
    $f = saleFixture();
    $staff = app(CreateEmployee::class)->execute($f['owner']->tenant, [
        'name' => 'Sam', 'email' => 'sam@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);

    $this->actingAs($staff)->get('/reports/ledger')->assertForbidden();
    auth()->guard('web')->logout();
    $this->get('/reports/ledger')->assertRedirect();
});

test('the ledger rejects a date range longer than a year', function () {
    $f = saleFixture();

    $this->actingAs($f['owner'])->get('/reports/ledger?from=2024-01-01&to=2026-01-01')->assertStatus(422);
});

// ------------------------------------------------ 4. services on the sale screen

test('a newly created service is available at every branch and shows up on a draft invoice', function () {
    $f = saleFixture();
    $module = Module::where('code', 'salon')->firstOrFail();
    $category = ServiceCategory::factory()->forTenant($f['owner']->tenant)->create(['module_id' => $module->id]);

    $this->actingAs($f['owner'])->post('/services', [
        'service_category_id' => $category->id, 'name' => 'Haircut', 'duration_minutes' => 30, 'base_price' => 300,
    ])->assertRedirect(route('services.index'));

    $service = Service::where('name', 'Haircut')->firstOrFail();
    expect($service->isAvailableAtBranch($f['branch']))->toBeTrue();

    $this->actingAs($f['owner'])->post('/invoices', ['branch_id' => $f['branch']->id, 'customer_id' => $f['customer']->id])->assertRedirect();
    $invoice = Invoice::firstOrFail();

    $this->actingAs($f['owner'])->get(route('invoices.show', $invoice))->assertOk()->assertSee('Haircut')->assertDontSee('No services are available');
});

test('a branch opened later gets the existing services too', function () {
    $owner = onboard(['modules' => ['salon']]);
    $module = Module::where('code', 'salon')->firstOrFail();
    $category = ServiceCategory::factory()->forTenant($owner->tenant)->create(['module_id' => $module->id]);
    $service = Service::factory()->forCategory($category)->create();

    $this->actingAs($owner)->post('/branches', ['name' => 'Main', 'code' => 'MAIN'])->assertRedirect();

    expect($service->fresh()->isAvailableAtBranch(Branch::where('code', 'MAIN')->firstOrFail()))->toBeTrue();
});

test('with no service available, the sale screen says why instead of showing an empty list', function () {
    $f = saleFixture();
    $module = Module::where('code', 'salon')->firstOrFail();
    $category = ServiceCategory::factory()->forTenant($f['owner']->tenant)->create(['module_id' => $module->id]);
    Service::factory()->forCategory($category)->create(); // never made available anywhere

    $this->actingAs($f['owner'])->post('/invoices', ['branch_id' => $f['branch']->id, 'customer_id' => $f['customer']->id]);

    $this->actingAs($f['owner'])->get(route('invoices.show', Invoice::firstOrFail()))
        ->assertOk()->assertSee('No services are available at');
});
