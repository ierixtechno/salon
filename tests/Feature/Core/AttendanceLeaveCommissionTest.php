<?php

use App\Domain\Core\Actions\AddInvoiceLine;
use App\Domain\Core\Actions\CheckoutSale;
use App\Domain\Core\Actions\CreateDraftInvoice;
use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Actions\ProcessRefund;
use App\Domain\Core\Actions\RecordPayment;
use App\Domain\Core\Actions\RequestLeave;
use App\Domain\Core\Actions\UpdateBranchModules;
use App\Domain\Core\Models\AttendanceRecord;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\CommissionEntry;
use App\Domain\Core\Models\CommissionRule;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\LeaveRequest;
use App\Domain\Core\Models\LeaveType;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\Module;
use Illuminate\Support\Str;

function phase9Fixture(): array
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

    $employee = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Stylist', 'email' => fake()->unique()->safeEmail(), 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => false, 'branches' => [$branch->id],
    ]);

    $customer = Customer::factory()->forTenant($owner->tenant)->create();

    return compact('owner', 'branch', 'service', 'employee', 'customer');
}

// ---------------------------------------------------------------------------
// Attendance
// ---------------------------------------------------------------------------

test('a manager can mark an employee present and it appears on the register', function () {
    $fixture = phase9Fixture();
    $owner = $fixture['owner'];

    $this->actingAs($owner)->post('/attendance/mark', [
        'branch_id' => $fixture['branch']->id,
        'user_id' => $fixture['employee']->id,
        'date' => now()->toDateString(),
        'status' => 'present',
    ])->assertRedirect();

    $record = AttendanceRecord::where('user_id', $fixture['employee']->id)->firstOrFail();
    expect($record->status)->toBe('present');
    expect($record->tenant_id)->toBe($owner->tenant_id);

    $this->actingAs($owner)->get('/attendance?branch_id='.$fixture['branch']->id)
        ->assertOk()
        ->assertSee('Stylist');
});

test('an employee can clock themselves in and out', function () {
    $fixture = phase9Fixture();
    $employee = $fixture['employee'];

    $this->actingAs($employee)->post('/attendance/clock-in', ['branch_id' => $fixture['branch']->id])
        ->assertRedirect();

    $record = AttendanceRecord::where('user_id', $employee->id)->firstOrFail();
    expect($record->status)->toBe('present');
    expect($record->check_in_at)->not->toBeNull();

    $this->actingAs($employee)->post('/attendance/clock-out', ['branch_id' => $fixture['branch']->id])
        ->assertRedirect();

    expect($record->fresh()->check_out_at)->not->toBeNull();

    // Self-service "my" page — no gate beyond auth, always scoped to self.
    $this->actingAs($employee)->get('/my-attendance')->assertOk();
});

test('a tenant cannot mark or view another tenant\'s attendance records', function () {
    $fixtureA = phase9Fixture();
    $fixtureB = phase9Fixture();

    $this->actingAs($fixtureA['owner'])->post('/attendance/mark', [
        'branch_id' => $fixtureB['branch']->id,
        'user_id' => $fixtureB['employee']->id,
        'date' => now()->toDateString(),
        'status' => 'present',
    ])->assertSessionHasErrors();

    expect(AttendanceRecord::withoutGlobalScope(TenantScope::class)->where('user_id', $fixtureB['employee']->id)->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Leave
// ---------------------------------------------------------------------------

test('an employee can request leave and a manager can approve it, which marks attendance on_leave', function () {
    $fixture = phase9Fixture();
    $owner = $fixture['owner'];
    $employee = $fixture['employee'];

    $leaveType = new LeaveType(['name' => 'Casual Leave', 'annual_days' => 12, 'is_paid' => true, 'is_active' => true]);
    $leaveType->tenant_id = $owner->tenant_id;
    $leaveType->save();

    $start = now()->addDays(3)->startOfDay();
    $end = now()->addDays(4)->startOfDay();

    $this->actingAs($employee)->post('/leave', [
        'leave_type_id' => $leaveType->id,
        'start_date' => $start->toDateString(),
        'end_date' => $end->toDateString(),
        'reason' => 'Family function',
    ])->assertRedirect();

    $leaveRequest = LeaveRequest::where('user_id', $employee->id)->firstOrFail();
    expect($leaveRequest->status)->toBe('pending');
    expect($leaveRequest->days)->toBe(2);

    $this->actingAs($owner)->post("/leave/{$leaveRequest->id}/approve")->assertRedirect();

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe('approved');
    expect($leaveRequest->decided_by)->toBe($owner->id);

    expect(AttendanceRecord::where('user_id', $employee->id)->where('status', 'on_leave')->count())->toBe(2);
});

test('a manager can reject a pending leave request, and an already-decided request cannot be re-approved', function () {
    $fixture = phase9Fixture();
    $owner = $fixture['owner'];
    $employee = $fixture['employee'];

    $leaveType = new LeaveType(['name' => 'Sick Leave', 'is_paid' => true, 'is_active' => true]);
    $leaveType->tenant_id = $owner->tenant_id;
    $leaveType->save();

    $this->actingAs($employee);
    $leaveRequest = app(RequestLeave::class)->execute(
        $employee, $leaveType, now()->addDay(), now()->addDay(), null, $employee->id,
    );

    $this->actingAs($owner)->post("/leave/{$leaveRequest->id}/reject", ['reason' => 'Insufficient staffing'])
        ->assertRedirect();

    expect($leaveRequest->fresh()->status)->toBe('rejected');

    $this->actingAs($owner)->post("/leave/{$leaveRequest->id}/approve")->assertStatus(409);
});

test('staff without leave.approve cannot approve leave requests', function () {
    $fixture = phase9Fixture();
    $employee = $fixture['employee'];

    $leaveType = new LeaveType(['name' => 'Casual Leave', 'is_paid' => true, 'is_active' => true]);
    $leaveType->tenant_id = $fixture['owner']->tenant_id;
    $leaveType->save();

    $this->actingAs($employee);
    $leaveRequest = app(RequestLeave::class)->execute(
        $employee, $leaveType, now()->addDay(), now()->addDay(), null, $employee->id,
    );

    // The Staff role (see OnboardTenant) is not granted leave.approve.
    $this->actingAs($employee)->post("/leave/{$leaveRequest->id}/approve")->assertForbidden();
});

// ---------------------------------------------------------------------------
// Commission
// ---------------------------------------------------------------------------

test('a fully paid invoice accrues commission for the performing employee, and a full refund reverses it', function () {
    $fixture = phase9Fixture();
    $owner = $fixture['owner'];
    $employee = $fixture['employee'];

    $this->actingAs($owner)->put("/commission/rules/{$employee->id}", [
        'type' => 'percentage',
        'rate' => 10,
        'is_active' => 1,
    ])->assertRedirect();

    $rule = CommissionRule::where('user_id', $employee->id)->firstOrFail();
    expect((float) $rule->rate)->toBe(10.0);
    expect($rule->is_active)->toBeTrue();

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute(
        invoice: $invoice,
        service: $fixture['service'],
        variant: null,
        appointment: null,
        performedBy: $employee->id,
    );
    $invoice = app(CheckoutSale::class)->execute($invoice);

    app(RecordPayment::class)->execute($invoice, 'cash', (float) $invoice->fresh()->grand_total, (string) Str::uuid(), null, 0, $owner->id);

    $accrual = CommissionEntry::where('user_id', $employee->id)->where('type', 'accrual')->firstOrFail();
    expect((float) $accrual->amount)->toBe(round(1000 * 0.10, 2));

    $this->actingAs($employee)->get('/my-commission')->assertOk()->assertSee(number_format($accrual->amount, 2));

    app(ProcessRefund::class)->execute($invoice->fresh(), (float) $invoice->fresh()->grand_total, 'cash', null, $owner->id);

    $reversal = CommissionEntry::where('user_id', $employee->id)->where('type', 'reversal')->firstOrFail();
    expect((float) $reversal->amount)->toBe(-(float) $accrual->amount);
    expect($reversal->reversed_entry_id)->toBe($accrual->id);
});

test('a line with no commission rule for its employee accrues no commission', function () {
    $fixture = phase9Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $invoice = app(CreateDraftInvoice::class)->execute($fixture['branch'], $fixture['customer'], $owner->id);
    app(AddInvoiceLine::class)->execute(
        invoice: $invoice,
        service: $fixture['service'],
        variant: null,
        appointment: null,
        performedBy: $fixture['employee']->id,
    );
    $invoice = app(CheckoutSale::class)->execute($invoice);

    app(RecordPayment::class)->execute($invoice, 'cash', (float) $invoice->fresh()->grand_total, (string) Str::uuid(), null, 0, $owner->id);

    expect(CommissionEntry::count())->toBe(0);
});


test('the Clock out control is a real submit button, so clicking it records the check-out', function () {
    $fixture = phase9Fixture();

    $html = $this->actingAs($fixture['employee'])->get('/my-attendance')->assertOk()->getContent();

    expect($html)->toMatch('/<button[^>]*type="submit"[^>]*>\s*Clock out\s*<\/button>/');
});

test('attendance times are shown in Indian time, not UTC', function () {
    $fixture = phase9Fixture();
    $employee = $fixture['employee'];

    $record = new AttendanceRecord([
        'user_id' => $employee->id, 'branch_id' => $fixture['branch']->id, 'date' => '2026-09-25',
        'check_in_at' => '2026-09-25 04:00:00', 'check_out_at' => '2026-09-25 12:30:00', // UTC
    ]);
    $record->tenant_id = $fixture['owner']->tenant_id;
    $record->status = 'present';
    $record->save();

    // 04:00 UTC = 09:30 IST, 12:30 UTC = 06:00 PM IST
    $this->actingAs($employee)->get('/my-attendance')->assertOk()->assertSee('09:30 AM')->assertSee('06:00 PM')->assertDontSee('04:00 AM');

    $this->actingAs($fixture['owner'])->get('/attendance?branch_id='.$fixture['branch']->id.'&date=2026-09-25')
        ->assertOk()->assertSee('09:30 AM');
});

test('a clock-in is recorded against the Indian calendar day', function () {
    $fixture = phase9Fixture();

    // 20:00 UTC on the 24th is already 01:30 IST on the 25th.
    $this->travelTo(Carbon\Carbon::parse('2026-09-24 20:00:00', 'UTC'));

    $this->actingAs($fixture['employee'])->post('/attendance/clock-in', ['branch_id' => $fixture['branch']->id])->assertRedirect();

    expect(AttendanceRecord::where('user_id', $fixture['employee']->id)->firstOrFail()->date->toDateString())->toBe('2026-09-25');
});

test('the leave requests page links admins to leave types, and the employee page tells them where to start', function () {
    $fixture = phase9Fixture();

    $this->actingAs($fixture['owner'])->get('/leave')->assertOk()->assertSee(route('leave-types.index'), false);

    $this->actingAs($fixture['owner'])->get('/my-leave')->assertOk()->assertSee(route('leave-types.create'), false);
    $this->actingAs($fixture['employee'])->get('/my-leave')->assertOk()->assertDontSee(route('leave-types.create'), false);
});
