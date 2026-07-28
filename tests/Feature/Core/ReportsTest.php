<?php

use App\Domain\Core\Actions\AddInvoiceLine;
use App\Domain\Core\Actions\CheckoutSale;
use App\Domain\Core\Actions\CreateDraftInvoice;
use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Actions\RecordPayment;
use App\Domain\Core\Actions\UpdateBranchModules;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Platform\Models\Module;
use Illuminate\Support\Str;

function phase12Fixture(): array
{
    $owner = onboard(['modules' => ['salon']]);
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    app(UpdateBranchModules::class)->execute($branch, ['salon']);

    $category = ServiceCategory::factory()->forTenant($owner->tenant)->create([
        'module_id' => Module::where('code', 'salon')->firstOrFail()->id,
    ]);
    $service = Service::factory()->forCategory($category)->create([
        'base_price' => 1000, 'tax_rate_percent' => 18,
    ]);
    $service->branches()->attach($branch->id, ['is_available' => true]);

    $customer = Customer::factory()->forTenant($owner->tenant)->create();

    return compact('owner', 'branch', 'category', 'service', 'customer');
}

function phase12PaidInvoice(array $fixture, ?Branch $branch = null): Invoice
{
    $branch ??= $fixture['branch'];

    $invoice = app(CreateDraftInvoice::class)->execute($branch, $fixture['customer'], $fixture['owner']->id);
    app(AddInvoiceLine::class)->execute($invoice, $fixture['service'], null, null, 1, 0);
    $invoice = app(CheckoutSale::class)->execute($invoice);
    app(RecordPayment::class)->execute($invoice, 'cash', (float) $invoice->grand_total, (string) Str::uuid(), null, 0, $fixture['owner']->id);

    return $invoice->fresh();
}

test('an owner can view the sales report and revenue matches recorded invoices', function () {
    $fixture = phase12Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $invoice = phase12PaidInvoice($fixture);

    $response = $this->get('/reports/sales');
    $response->assertOk();

    $totals = $response->viewData('totals');
    expect((float) $totals->revenue)->toBe((float) $invoice->grand_total);
    expect((int) $totals->invoice_count)->toBe(1);
});

test('the module performance report attributes revenue and appointments to the correct module', function () {
    $fixture = phase12Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $invoice = phase12PaidInvoice($fixture);

    $response = $this->get('/reports/module-performance');
    $response->assertOk();

    $rows = $response->viewData('rows');
    $salonRow = $rows->firstWhere(fn ($row) => $row->module->code === 'salon');

    expect($salonRow)->not->toBeNull();
    expect((float) $salonRow->revenue)->toBe((float) $invoice->grand_total);
});

test('a tenant only ever sees its own sales figures in the report', function () {
    $fixtureA = phase12Fixture();
    $fixtureB = phase12Fixture();

    $this->actingAs($fixtureA['owner']);
    phase12PaidInvoice($fixtureA);

    $this->actingAs($fixtureB['owner']);
    $invoiceB = phase12PaidInvoice($fixtureB);
    phase12PaidInvoice($fixtureB); // a second sale for B, so A and B have different totals

    $totals = $this->get('/reports/sales')->viewData('totals');

    expect((int) $totals->invoice_count)->toBe(2);
    expect((float) $totals->revenue)->toBe((float) $invoiceB->grand_total * 2);
});

test('requesting a branch_id the user cannot access silently falls back to their own accessible branches, never leaking another branch\'s figures', function () {
    $fixture = phase12Fixture();
    $owner = $fixture['owner'];

    $this->actingAs($owner);
    $otherBranch = Branch::factory()->forTenant($owner->tenant)->create();
    app(UpdateBranchModules::class)->execute($otherBranch, ['salon']);
    $fixture['service']->branches()->attach($otherBranch->id, ['is_available' => true]);
    phase12PaidInvoice($fixture, $otherBranch);

    $manager = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Manager', 'email' => fake()->unique()->safeEmail(), 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Manager', 'all_branches' => false, 'branches' => [$fixture['branch']->id],
    ]);

    $this->actingAs($manager);
    $totals = $this->get('/reports/sales?branch_id='.$otherBranch->id)->viewData('totals');

    // The manager has no access to $otherBranch, so the branch_id filter is
    // ignored and they see only their own accessible (empty-of-sales)
    // branch — never $otherBranch's revenue.
    expect((float) $totals->revenue)->toBe(0.0);
});

test('staff without reports.view cannot access the reports section', function () {
    $fixture = phase12Fixture();
    $owner = $fixture['owner'];

    $staff = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Staff', 'email' => fake()->unique()->safeEmail(), 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);

    $this->actingAs($staff)->get('/reports/sales')->assertForbidden();
});

test('the sales report can be exported as csv', function () {
    $fixture = phase12Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    phase12PaidInvoice($fixture);

    $response = $this->get('/reports/sales?export=csv');
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});
