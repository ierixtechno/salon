<?php

use App\Domain\Core\Actions\AddInvoiceLine;
use App\Domain\Core\Actions\BookAppointment;
use App\Domain\Core\Actions\CheckInAppointment;
use App\Domain\Core\Actions\CheckoutSale;
use App\Domain\Core\Actions\CompleteAppointment;
use App\Domain\Core\Actions\CreateDraftInvoice;
use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Actions\EraseCustomer;
use App\Domain\Core\Actions\RecordPayment;
use App\Domain\Core\Actions\StartAppointmentService;
use App\Domain\Core\Actions\UpdateBranchModules;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\EmployeeSchedule;
use App\Domain\Core\Models\Invoice;
use App\Domain\Core\Models\Payment;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\Module;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

function invoicingFixture(): array
{
    $owner = onboard(['modules' => ['salon']]);
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    app(UpdateBranchModules::class)->execute($branch, ['salon']);

    $category = ServiceCategory::factory()->forTenant($owner->tenant)->create([
        'module_id' => Module::where('code', 'salon')->firstOrFail()->id,
    ]);
    $service = Service::factory()->forCategory($category)->create([
        'duration_minutes' => 30,
        'base_price' => 500,
        'tax_rate_percent' => 18,
    ]);
    $service->branches()->attach($branch->id, ['is_available' => true]);

    $employee = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Stylist', 'email' => fake()->unique()->safeEmail(), 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);
    $service->capableEmployees()->attach($employee->id);

    foreach (range(0, 6) as $dayOfWeek) {
        $schedule = new EmployeeSchedule([
            'user_id' => $employee->id, 'branch_id' => $branch->id,
            'day_of_week' => $dayOfWeek, 'starts_at' => '09:00', 'ends_at' => '18:00',
        ]);
        $schedule->tenant_id = $owner->tenant_id;
        $schedule->save();
    }

    $customer = Customer::factory()->forTenant($owner->tenant)->create(['name' => 'Original Name', 'phone' => '9000000000']);

    return compact('owner', 'branch', 'category', 'service', 'employee', 'customer');
}

function completedAppointmentFor(array $fixture)
{
    $appointment = app(BookAppointment::class)->execute(
        branch: $fixture['branch'],
        customer: $fixture['customer'],
        service: $fixture['service'],
        variant: null,
        employee: $fixture['employee'],
        resource: null,
        startsAt: now()->addDay()->setTime(10, 0),
    );

    app(CheckInAppointment::class)->execute($appointment);
    app(StartAppointmentService::class)->execute($appointment->fresh());

    return app(CompleteAppointment::class)->execute($appointment->fresh());
}

test('the invoices index and create pages render', function () {
    $fixture = invoicingFixture();
    $owner = $fixture['owner'];

    $this->actingAs($owner)->get('/invoices?branch_id='.$fixture['branch']->id)->assertOk();
    $this->actingAs($owner)->get('/invoices/create')->assertOk();
});

test('an owner can build a draft sale and totals are computed server-side', function () {
    $fixture = invoicingFixture();
    $owner = $fixture['owner'];

    $this->actingAs($owner)->post('/invoices', [
        'branch_id' => $fixture['branch']->id,
        'customer_id' => $fixture['customer']->id,
    ])->assertRedirect();

    $invoice = Invoice::firstOrFail();
    expect($invoice->status)->toBe('draft');
    expect($invoice->customer_name)->toBe('Original Name');

    // The draft show page must actually render — a redirect-only test
    // suite would never catch a Blade compile error on this view.
    $this->actingAs($owner)->get("/invoices/{$invoice->id}")->assertOk();

    $this->actingAs($owner)->post("/invoices/{$invoice->id}/lines", [
        'service_id' => $fixture['service']->id,
        'quantity' => 2,
        'discount_amount' => 50,
    ])->assertRedirect();

    $invoice->refresh();
    expect((float) $invoice->subtotal)->toBe(1000.0);
    expect((float) $invoice->discount_total)->toBe(50.0);
    expect((float) $invoice->cgst_total)->toBe(85.5);
    expect((float) $invoice->sgst_total)->toBe(85.5);
    expect((float) $invoice->grand_total)->toBe(1121.0);

    // The finalized/paid show page renders too (different conditional
    // branches of the same view).
    $invoice = app(CheckoutSale::class)->execute($invoice);
    $this->get("/invoices/{$invoice->id}")->assertOk();

    app(RecordPayment::class)->execute($invoice, 'cash', (float) $invoice->grand_total, (string) Str::uuid(), null, 0, $owner->id);
    $this->get("/invoices/{$invoice->fresh()->id}")->assertOk();
});

test('an appointment can only be invoiced once and must be completed', function () {
    $fixture = invoicingFixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);
    $appointment = completedAppointmentFor($fixture);

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);

    $this->actingAs($owner)->post("/invoices/{$invoice->id}/lines", [
        'appointment_id' => $appointment->id,
    ])->assertRedirect();

    expect($invoice->lines()->count())->toBe(1);
    expect((float) $invoice->fresh()->grand_total)->toBeGreaterThan(0);

    // Same appointment again — must fail (already invoiced).
    $invoice2 = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    $this->actingAs($owner)->post("/invoices/{$invoice2->id}/lines", [
        'appointment_id' => $appointment->id,
    ])->assertSessionHasErrors('appointment_id');

    // A non-completed appointment can't be invoiced at all.
    $pending = app(BookAppointment::class)->execute(
        $fixture['branch'], $fixture['customer'], $fixture['service'], null, $fixture['employee'], null,
        now()->addDays(2)->setTime(11, 0),
    );
    $this->actingAs($owner)->post("/invoices/{$invoice2->id}/lines", [
        'appointment_id' => $pending->id,
    ])->assertSessionHasErrors('appointment_id');
});

test('checkout assigns a sequential GST invoice number per branch per financial year', function () {
    $fixture = invoicingFixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $invoice1 = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute($invoice1, $fixture['service'], null, null, 1, 0);

    $invoice2 = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute($invoice2, $fixture['service'], null, null, 1, 0);

    $this->actingAs($owner)->post("/invoices/{$invoice1->id}/checkout")->assertRedirect();
    $this->actingAs($owner)->post("/invoices/{$invoice2->id}/checkout")->assertRedirect();

    $invoice1->refresh();
    $invoice2->refresh();

    expect($invoice1->sequence_number)->toBe(1);
    expect($invoice2->sequence_number)->toBe(2);
    expect($invoice1->invoice_number)->toStartWith($fixture['branch']->code.'/');
    expect($invoice1->invoice_number)->not->toBe($invoice2->invoice_number);
});

test('checkout is rejected for a draft with no line items', function () {
    $fixture = invoicingFixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);

    $this->post("/invoices/{$invoice->id}/checkout")->assertStatus(422);
});

test('split payments accumulate correctly and overpayment is rejected', function () {
    $fixture = invoicingFixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute($invoice, $fixture['service'], null, null, 1, 0);
    $invoice = app(CheckoutSale::class)->execute($invoice);
    $grandTotal = (float) $invoice->grand_total;

    $this->actingAs($owner)->post("/invoices/{$invoice->id}/payments", [
        'method' => 'cash',
        'amount' => $grandTotal / 2,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect();

    expect($invoice->fresh()->status)->toBe('partially_paid');

    $this->actingAs($owner)->post("/invoices/{$invoice->id}/payments", [
        'method' => 'upi',
        'amount' => $grandTotal,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertStatus(409);

    $this->actingAs($owner)->post("/invoices/{$invoice->id}/payments", [
        'method' => 'upi',
        'amount' => $grandTotal / 2,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect();

    expect($invoice->fresh()->status)->toBe('paid');
    expect((float) $invoice->fresh()->totalPaid())->toBe($grandTotal);
});

test('a resubmitted payment with the same idempotency key does not create a duplicate', function () {
    $fixture = invoicingFixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute($invoice, $fixture['service'], null, null, 1, 0);
    $invoice = app(CheckoutSale::class)->execute($invoice);

    $key = (string) Str::uuid();

    $this->actingAs($owner)->post("/invoices/{$invoice->id}/payments", [
        'method' => 'cash', 'amount' => 100, 'idempotency_key' => $key,
    ])->assertRedirect();

    $this->actingAs($owner)->post("/invoices/{$invoice->id}/payments", [
        'method' => 'cash', 'amount' => 100, 'idempotency_key' => $key,
    ])->assertRedirect();

    expect(Payment::where('idempotency_key', $key)->count())->toBe(1);
});

test('voiding is only allowed while finalized and unpaid', function () {
    $fixture = invoicingFixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute($invoice, $fixture['service'], null, null, 1, 0);
    $invoice = app(CheckoutSale::class)->execute($invoice);

    $this->actingAs($owner)->post("/invoices/{$invoice->id}/payments", [
        'method' => 'cash', 'amount' => 1, 'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect();

    $this->actingAs($owner)->post("/invoices/{$invoice->id}/void")->assertStatus(409);

    // A fresh, unpaid finalized invoice CAN be voided.
    $invoice2 = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute($invoice2, $fixture['service'], null, null, 1, 0);
    $invoice2 = app(CheckoutSale::class)->execute($invoice2);

    $this->actingAs($owner)->post("/invoices/{$invoice2->id}/void", ['reason' => 'entered by mistake'])->assertRedirect();
    expect($invoice2->fresh()->status)->toBe('void');
});

test('partial then full refund flips the invoice to refunded once the paid amount is fully reversed', function () {
    $fixture = invoicingFixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute($invoice, $fixture['service'], null, null, 1, 0);
    $invoice = app(CheckoutSale::class)->execute($invoice);
    $grandTotal = (float) $invoice->grand_total;

    $this->actingAs($owner)->post("/invoices/{$invoice->id}/payments", [
        'method' => 'cash', 'amount' => $grandTotal, 'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect();

    $this->actingAs($owner)->post("/invoices/{$invoice->id}/refunds", [
        'method' => 'cash', 'amount' => $grandTotal / 2, 'reason' => 'partial dissatisfaction',
    ])->assertRedirect();

    expect($invoice->fresh()->status)->toBe('paid');

    // Refunding more than the refundable balance is rejected.
    $this->actingAs($owner)->post("/invoices/{$invoice->id}/refunds", [
        'method' => 'cash', 'amount' => $grandTotal,
    ])->assertStatus(409);

    $this->actingAs($owner)->post("/invoices/{$invoice->id}/refunds", [
        'method' => 'cash', 'amount' => $grandTotal / 2,
    ])->assertRedirect();

    expect($invoice->fresh()->status)->toBe('refunded');
});

test('erasing a customer does not corrupt the historical invoice snapshot', function () {
    $fixture = invoicingFixture();
    $owner = $fixture['owner'];
    $customer = $fixture['customer'];
    $this->actingAs($owner);

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $customer, $owner->id);
    app(AddInvoiceLine::class)->execute($invoice, $fixture['service'], null, null, 1, 0);
    $invoice = app(CheckoutSale::class)->execute($invoice);

    app(EraseCustomer::class)->execute($customer, $owner->id);

    $invoice->refresh();
    expect($invoice->customer_name)->toBe('Original Name');
    expect($invoice->customer_phone)->toBe('9000000000');
    expect($customer->fresh()->name)->toBe('Erased Customer');
});

test('a manager can create invoices and record payments but cannot void or refund', function () {
    $fixture = invoicingFixture();
    $owner = $fixture['owner'];

    $manager = User::factory()->forTenant($owner->tenant)->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($owner->tenant_id);
    $manager->assignRole('Manager');

    $this->actingAs($manager)->post('/invoices', [
        'branch_id' => $fixture['branch']->id,
        'customer_id' => $fixture['customer']->id,
    ])->assertRedirect();
    $invoice = Invoice::firstOrFail();

    $this->actingAs($manager)->post("/invoices/{$invoice->id}/lines", [
        'service_id' => $fixture['service']->id,
    ])->assertRedirect();

    $invoice = app(CheckoutSale::class)->execute($invoice->fresh());

    $this->actingAs($manager)->post("/invoices/{$invoice->id}/payments", [
        'method' => 'cash', 'amount' => (float) $invoice->grand_total, 'idempotency_key' => (string) Str::uuid(),
    ])->assertRedirect();

    $this->actingAs($manager)->post("/invoices/{$invoice->id}/refunds", [
        'method' => 'cash', 'amount' => 1,
    ])->assertForbidden();
});

test('staff cannot access invoicing at all', function () {
    $fixture = invoicingFixture();
    $owner = $fixture['owner'];

    $staff = User::factory()->forTenant($owner->tenant)->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($owner->tenant_id);
    $staff->assignRole('Staff');

    $this->actingAs($staff)->get('/invoices')->assertForbidden();
    $this->actingAs($staff)->post('/invoices', [
        'branch_id' => $fixture['branch']->id,
        'customer_id' => $fixture['customer']->id,
    ])->assertForbidden();
});

/**
 * Mandatory pattern per CLAUDE.md §62.
 */
test('a user from tenant B cannot view, pay, or void an invoice belonging to tenant A', function () {
    $fixture = invoicingFixture();
    $ownerA = $fixture['owner'];
    $ownerB = onboard(['modules' => ['salon']]);
    $this->actingAs($ownerA);

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $ownerA->id);
    app(AddInvoiceLine::class)->execute($invoice, $fixture['service'], null, null, 1, 0);
    $invoice = app(CheckoutSale::class)->execute($invoice);

    $this->actingAs($ownerB)->get("/invoices/{$invoice->id}")->assertNotFound();
    $this->actingAs($ownerB)->post("/invoices/{$invoice->id}/payments", [
        'method' => 'cash', 'amount' => 1, 'idempotency_key' => (string) Str::uuid(),
    ])->assertNotFound();
    $this->actingAs($ownerB)->post("/invoices/{$invoice->id}/void")->assertNotFound();

    expect(Invoice::withoutGlobalScope(TenantScope::class)->find($invoice->id)->status)->toBe('finalized');
});
