<?php

use App\Domain\Core\Actions\AddInvoiceLine;
use App\Domain\Core\Actions\CheckoutSale;
use App\Domain\Core\Actions\CreateDraftInvoice;
use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Actions\CreateExpense;
use App\Domain\Core\Actions\OpenCashRegister;
use App\Domain\Core\Actions\ProcessRefund;
use App\Domain\Core\Actions\RecordPayment;
use App\Domain\Core\Actions\UpdateBranchModules;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\BusinessProfile;
use App\Domain\Core\Models\CashMovement;
use App\Domain\Core\Models\CashRegisterSession;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\Expense;
use App\Domain\Core\Models\ExpenseCategory;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Platform\Models\Module;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function phase10Fixture(): array
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

    return compact('owner', 'branch', 'service', 'customer');
}

// ---------------------------------------------------------------------------
// Expense categories + expenses
// ---------------------------------------------------------------------------

test('an owner can create an expense category and record an expense, auto-approved by default', function () {
    $fixture = phase10Fixture();
    $owner = $fixture['owner'];

    $this->actingAs($owner)->post('/expense-categories', ['name' => 'Insurance'])->assertRedirect();
    $category = ExpenseCategory::where('name', 'Insurance')->firstOrFail();
    expect($category->tenant_id)->toBe($owner->tenant_id);

    Storage::fake('local');

    $this->actingAs($owner)->post('/expenses', [
        'branch_id' => $fixture['branch']->id,
        'expense_category_id' => $category->id,
        'amount' => 5000,
        'tax_amount' => 0,
        'payment_method' => 'bank_transfer',
        'expense_date' => now()->toDateString(),
        'vendor_name' => 'Landlord Co',
        'attachment' => UploadedFile::fake()->create('receipt.pdf', 100),
    ])->assertRedirect();

    $expense = Expense::where('vendor_name', 'Landlord Co')->firstOrFail();
    expect($expense->status)->toBe('approved');
    expect($expense->approved_at)->not->toBeNull();
    expect($expense->attachment_path)->not->toBeNull();
    Storage::disk('local')->assertExists($expense->attachment_path);

    $this->actingAs($owner)->get("/expenses/{$expense->id}/attachment")->assertOk();
});

test('when approval is required, an expense starts pending and only debits the register once approved', function () {
    $fixture = phase10Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    BusinessProfile::updateOrCreate(['tenant_id' => $owner->tenant_id], ['display_name' => 'Test Salon', 'expense_approval_required' => true]);

    $category = ExpenseCategory::create(['name' => 'Utilities']);
    app(OpenCashRegister::class)->execute($fixture['branch'], 1000, $owner->id);

    $this->post('/expenses', [
        'branch_id' => $fixture['branch']->id,
        'expense_category_id' => $category->id,
        'amount' => 800,
        'payment_method' => 'cash',
        'expense_date' => now()->toDateString(),
    ])->assertRedirect();

    $expense = Expense::where('expense_category_id', $category->id)->firstOrFail();
    expect($expense->status)->toBe('pending');
    expect(CashMovement::where('type', 'expense')->count())->toBe(0);

    $this->post("/expenses/{$expense->id}/approve")->assertRedirect();

    expect($expense->fresh()->status)->toBe('approved');
    $movement = CashMovement::where('type', 'expense')->firstOrFail();
    expect((float) $movement->amount)->toBe(-800.0);
});

test('rejecting a pending expense works, and an already-decided expense cannot be re-approved', function () {
    $fixture = phase10Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    BusinessProfile::updateOrCreate(['tenant_id' => $owner->tenant_id], ['display_name' => 'Test Salon', 'expense_approval_required' => true]);
    $category = ExpenseCategory::create(['name' => 'Supplies']);

    $this->post('/expenses', [
        'branch_id' => $fixture['branch']->id,
        'expense_category_id' => $category->id,
        'amount' => 250,
        'payment_method' => 'card',
        'expense_date' => now()->toDateString(),
    ])->assertRedirect();

    $expense = Expense::where('expense_category_id', $category->id)->firstOrFail();

    $this->post("/expenses/{$expense->id}/reject", ['reason' => 'Not a valid business expense'])->assertRedirect();
    expect($expense->fresh()->status)->toBe('rejected');

    $this->post("/expenses/{$expense->id}/approve")->assertStatus(409);
});

test('a tenant cannot access or approve another tenant\'s expenses', function () {
    $fixtureA = phase10Fixture();
    $fixtureB = phase10Fixture();

    $this->actingAs($fixtureB['owner']);
    $categoryB = ExpenseCategory::create(['name' => 'Marketing']);
    $expenseB = app(CreateExpense::class)->execute(
        branch: $fixtureB['branch'],
        category: $categoryB,
        amount: 100,
        taxAmount: 0,
        paymentMethod: 'cash',
        expenseDate: now(),
        vendorName: null,
        description: null,
        attachmentPath: null,
        createdBy: $fixtureB['owner']->id,
    );

    $this->actingAs($fixtureA['owner'])->post("/expenses/{$expenseB->id}/approve")->assertNotFound();
    $this->actingAs($fixtureA['owner'])->get("/expenses/{$expenseB->id}/attachment")->assertNotFound();
});

// ---------------------------------------------------------------------------
// Cash register
// ---------------------------------------------------------------------------

test('a manager can open, cash-in/out, and close a register with the correct expected vs actual difference', function () {
    $fixture = phase10Fixture();
    $owner = $fixture['owner'];
    $branch = $fixture['branch'];

    $this->actingAs($owner)->post('/cash-register/open', [
        'branch_id' => $branch->id,
        'opening_cash' => 1000,
    ])->assertRedirect();

    $session = CashRegisterSession::where('branch_id', $branch->id)->where('status', 'open')->firstOrFail();
    expect((float) $session->opening_cash)->toBe(1000.0);

    // Opening a second session for the same branch must fail.
    $this->actingAs($owner)->post('/cash-register/open', [
        'branch_id' => $branch->id,
        'opening_cash' => 500,
    ])->assertStatus(409);

    $this->actingAs($owner)->post("/cash-register/{$session->id}/cash-in", [
        'amount' => 200, 'reason' => 'Float top-up',
    ])->assertRedirect();

    $this->actingAs($owner)->post("/cash-register/{$session->id}/cash-out", [
        'amount' => 50, 'reason' => 'Petty cash',
    ])->assertRedirect();

    // Expected: 1000 + 200 - 50 = 1150.
    expect($session->fresh()->runningTotal())->toBe(1150.0);

    $this->actingAs($owner)->post("/cash-register/{$session->id}/close", [
        'actual_closing' => 1140,
    ])->assertRedirect();

    $session->refresh();
    expect($session->status)->toBe('closed');
    expect((float) $session->expected_closing)->toBe(1150.0);
    expect((float) $session->actual_closing)->toBe(1140.0);
    expect((float) $session->difference)->toBe(-10.0);

    // A closed session can no longer take movements.
    $this->actingAs($owner)->post("/cash-register/{$session->id}/cash-in", [
        'amount' => 10, 'reason' => 'Late entry',
    ])->assertStatus(409);
});

test('a cash payment credits the open register and a cash refund debits it', function () {
    $fixture = phase10Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $session = app(OpenCashRegister::class)->execute($fixture['branch'], 500, $owner->id);

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute(invoice: $invoice, service: $fixture['service'], variant: null, appointment: null);
    $invoice = app(CheckoutSale::class)->execute($invoice);
    $grandTotal = (float) $invoice->grand_total;

    app(RecordPayment::class)->execute($invoice, 'cash', $grandTotal, (string) Str::uuid(), null, 0, $owner->id);

    expect($session->fresh()->runningTotal())->toBe(round(500 + $grandTotal, 2));

    app(ProcessRefund::class)->execute($invoice->fresh(), $grandTotal, 'cash', null, $owner->id);

    expect($session->fresh()->runningTotal())->toBe(500.0);
});

test('a cash payment with no open register session records no cash movement', function () {
    $fixture = phase10Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute(invoice: $invoice, service: $fixture['service'], variant: null, appointment: null);
    $invoice = app(CheckoutSale::class)->execute($invoice);

    app(RecordPayment::class)->execute($invoice, 'cash', (float) $invoice->grand_total, (string) Str::uuid(), null, 0, $owner->id);

    expect(CashMovement::count())->toBe(0);
});

test('staff without cash-register.manage cannot open or close a register', function () {
    $fixture = phase10Fixture();
    $branch = $fixture['branch'];

    $staff = app(CreateEmployee::class)->execute($fixture['owner']->tenant, [
        'name' => 'Front Desk', 'email' => fake()->unique()->safeEmail(), 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);

    // The seeded Staff role *is* granted cash-register.manage (front-desk
    // cashiers open/close the till) — revoke it here to prove the gate.
    $staff->removeRole('Staff');

    $this->actingAs($staff)->post('/cash-register/open', [
        'branch_id' => $branch->id,
        'opening_cash' => 100,
    ])->assertForbidden();
});
