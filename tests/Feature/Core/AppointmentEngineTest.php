<?php

use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Actions\UpdateBranchModules;
use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\EmployeeSchedule;
use App\Domain\Core\Models\Holiday;
use App\Domain\Core\Models\Resource;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Core\Models\WaitlistEntry;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\Module;
use App\Models\User;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;

/**
 * Builds a fully bookable fixture: a tenant with salon enabled, a branch
 * with salon enabled and no explicit business hours saved (so it falls
 * back to the settings UI's own 09:00-18:00 default), an employee capable
 * of the service and scheduled every day of the week at that branch, and a
 * customer. Individual tests then perturb one piece (schedule, module,
 * capability, ...) to exercise a specific rejection path.
 */
function bookingFixture(array $serviceOverrides = []): array
{
    $owner = onboard(['modules' => ['salon']]);
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    app(UpdateBranchModules::class)->execute($branch, ['salon']);

    $category = ServiceCategory::factory()->forTenant($owner->tenant)->create([
        'module_id' => Module::where('code', 'salon')->firstOrFail()->id,
    ]);
    $service = Service::factory()->forCategory($category)->create(array_merge([
        'duration_minutes' => 30,
        'buffer_minutes' => 10,
        'base_price' => 500,
    ], $serviceOverrides));
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

    $customer = Customer::factory()->forTenant($owner->tenant)->create();

    return compact('owner', 'branch', 'category', 'service', 'employee', 'customer');
}

function slot(int $daysFromNow = 1, int $hour = 10): Carbon
{
    return now()->addDays($daysFromNow)->setTime($hour, 0)->seconds(0);
}

test('an owner can book an appointment and price/status are resolved server-side', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();

    $response = $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'user_id' => $employee->id,
        'starts_at' => slot()->format('Y-m-d\TH:i'),
    ]);

    $response->assertRedirect();
    $appointment = Appointment::firstOrFail();
    expect($appointment->status)->toBe('confirmed');
    expect((float) $appointment->price)->toBe(500.0);
    expect($appointment->tenant_id)->toBe($owner->tenant_id);
});

test('booking is rejected when the branch does not have the service module enabled', function () {
    ['owner' => $owner, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();
    $otherBranch = Branch::factory()->forTenant($owner->tenant)->create(); // no modules enabled

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $otherBranch->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'user_id' => $employee->id,
        'starts_at' => slot()->format('Y-m-d\TH:i'),
    ])->assertSessionHasErrors('service_id');
});

test('booking is rejected when the staff member cannot perform the service', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'customer' => $customer] = bookingFixture();

    $incapableEmployee = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Receptionist', 'email' => fake()->unique()->safeEmail(), 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'user_id' => $incapableEmployee->id,
        'starts_at' => slot()->format('Y-m-d\TH:i'),
    ])->assertSessionHasErrors('user_id');
});

test('booking is rejected when the resource does not belong to the branch', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();
    $otherBranch = Branch::factory()->forTenant($owner->tenant)->create();
    $foreignResource = Resource::factory()->forTenant($owner->tenant)->create(['branch_id' => $otherBranch->id]);

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'user_id' => $employee->id,
        'resource_id' => $foreignResource->id,
        'starts_at' => slot()->format('Y-m-d\TH:i'),
    ])->assertSessionHasErrors('resource_id');
});

test('booking is rejected outside branch business hours', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'user_id' => $employee->id,
        'starts_at' => slot(1, 20)->format('Y-m-d\TH:i'), // 8pm, outside default 09:00-18:00
    ])->assertStatus(409);

    expect(Appointment::count())->toBe(0);
});

test('booking is rejected on a branch holiday', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();
    $slot = slot();

    $holiday = new Holiday(['branch_id' => $branch->id, 'date' => $slot->toDateString(), 'name' => 'Test Holiday']);
    $holiday->tenant_id = $owner->tenant_id;
    $holiday->save();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'user_id' => $employee->id,
        'starts_at' => $slot->format('Y-m-d\TH:i'),
    ])->assertStatus(409);
});

test('booking is rejected when the employee has no schedule for that branch/day', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();
    $this->actingAs($owner);
    EmployeeSchedule::where('user_id', $employee->id)->delete();

    $this->post('/appointments', [
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'user_id' => $employee->id,
        'starts_at' => slot()->format('Y-m-d\TH:i'),
    ])->assertStatus(409);
});

test('booking is rejected when it falls outside the employee\'s scheduled shift', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();
    $this->actingAs($owner);
    EmployeeSchedule::where('user_id', $employee->id)->update(['starts_at' => '14:00', 'ends_at' => '18:00']);

    $this->post('/appointments', [
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'user_id' => $employee->id,
        'starts_at' => slot(1, 10)->format('Y-m-d\TH:i'), // 10am, shift starts 14:00
    ])->assertStatus(409);
});

test('a second overlapping booking for the same employee is rejected, including buffer time', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();
    $slot = slot();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => $slot->format('Y-m-d\TH:i'),
    ])->assertRedirect();

    // Service is 30 min + 10 min buffer = busy until +40 minutes.
    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => $slot->copy()->addMinutes(35)->format('Y-m-d\TH:i'),
    ])->assertStatus(409);

    // Right after the buffer ends — must succeed.
    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => $slot->copy()->addMinutes(40)->format('Y-m-d\TH:i'),
    ])->assertRedirect();

    expect(Appointment::count())->toBe(2);
});

test('a resource with capacity 2 allows two overlapping bookings but rejects a third', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();

    $employee2 = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Stylist 2', 'email' => fake()->unique()->safeEmail(), 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);
    $service->capableEmployees()->attach($employee2->id);
    foreach (range(0, 6) as $dayOfWeek) {
        $schedule = new EmployeeSchedule(['user_id' => $employee2->id, 'branch_id' => $branch->id, 'day_of_week' => $dayOfWeek, 'starts_at' => '09:00', 'ends_at' => '18:00']);
        $schedule->tenant_id = $owner->tenant_id;
        $schedule->save();
    }

    $room = Resource::factory()->forTenant($owner->tenant)->create(['branch_id' => $branch->id, 'capacity' => 2]);
    $slot = slot();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'resource_id' => $room->id, 'starts_at' => $slot->format('Y-m-d\TH:i'),
    ])->assertRedirect();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee2->id, 'resource_id' => $room->id, 'starts_at' => $slot->format('Y-m-d\TH:i'),
    ])->assertRedirect();

    $employee3 = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Stylist 3', 'email' => fake()->unique()->safeEmail(), 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);
    $service->capableEmployees()->attach($employee3->id);
    foreach (range(0, 6) as $dayOfWeek) {
        $schedule = new EmployeeSchedule(['user_id' => $employee3->id, 'branch_id' => $branch->id, 'day_of_week' => $dayOfWeek, 'starts_at' => '09:00', 'ends_at' => '18:00']);
        $schedule->tenant_id = $owner->tenant_id;
        $schedule->save();
    }

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee3->id, 'resource_id' => $room->id, 'starts_at' => $slot->format('Y-m-d\TH:i'),
    ])->assertStatus(409);
});

test('the full appointment lifecycle enforces valid transitions only', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => slot()->format('Y-m-d\TH:i'),
    ])->assertRedirect();
    $appointment = Appointment::firstOrFail();

    // Cannot complete or mark no-show before checking in.
    $this->actingAs($owner)->post("/appointments/{$appointment->id}/complete")->assertStatus(409);

    $this->actingAs($owner)->post("/appointments/{$appointment->id}/check-in")->assertRedirect();
    expect($appointment->fresh()->status)->toBe('checked_in');

    // Cannot check in twice.
    $this->actingAs($owner)->post("/appointments/{$appointment->id}/check-in")->assertStatus(409);

    $this->actingAs($owner)->post("/appointments/{$appointment->id}/start")->assertRedirect();
    expect($appointment->fresh()->status)->toBe('in_service');

    $this->actingAs($owner)->post("/appointments/{$appointment->id}/complete")->assertRedirect();
    expect($appointment->fresh()->status)->toBe('completed');

    // A completed appointment can no longer be cancelled or rescheduled.
    $this->actingAs($owner)->post("/appointments/{$appointment->id}/cancel")->assertStatus(409);
});

test('an owner can cancel a confirmed appointment with a reason', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => slot()->format('Y-m-d\TH:i'),
    ])->assertRedirect();
    $appointment = Appointment::firstOrFail();

    $this->actingAs($owner)->post("/appointments/{$appointment->id}/cancel", ['reason' => 'Customer called to cancel'])
        ->assertRedirect();

    $appointment->refresh();
    expect($appointment->status)->toBe('cancelled');
    expect($appointment->cancellation_reason)->toBe('Customer called to cancel');
});

test('a confirmed appointment can be marked no-show', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => slot()->format('Y-m-d\TH:i'),
    ])->assertRedirect();
    $appointment = Appointment::firstOrFail();

    $this->actingAs($owner)->post("/appointments/{$appointment->id}/no-show")->assertRedirect();
    expect($appointment->fresh()->status)->toBe('no_show');
});

test('an owner can reschedule a confirmed appointment to a free slot, and a conflicting reschedule is rejected', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();
    $slot = slot();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => $slot->format('Y-m-d\TH:i'),
    ])->assertRedirect();
    $appointment = Appointment::firstOrFail();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => $slot->copy()->addHours(2)->format('Y-m-d\TH:i'),
    ])->assertRedirect();
    $secondAppointment = Appointment::where('id', '!=', $appointment->id)->firstOrFail();

    // Reschedule appointment #1 onto appointment #2's slot — must conflict.
    $this->actingAs($owner)->put("/appointments/{$appointment->id}/reschedule", [
        'starts_at' => $secondAppointment->starts_at->copy()->timezone($branch->effectiveTimezone())->format('Y-m-d\TH:i'),
    ])->assertStatus(409);

    // Reschedule to a genuinely free slot — must succeed.
    $newSlot = $slot->copy()->addHours(4);
    $this->actingAs($owner)->put("/appointments/{$appointment->id}/reschedule", [
        'starts_at' => $newSlot->format('Y-m-d\TH:i'),
    ])->assertRedirect();

    expect($appointment->fresh()->starts_at->timezone($branch->effectiveTimezone())->format('H:i'))->toBe($newSlot->format('H:i'));
});

test('rescheduling is rejected once an appointment has been checked in', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => slot()->format('Y-m-d\TH:i'),
    ])->assertRedirect();
    $appointment = Appointment::firstOrFail();

    $this->actingAs($owner)->post("/appointments/{$appointment->id}/check-in")->assertRedirect();

    $this->actingAs($owner)->put("/appointments/{$appointment->id}/reschedule", [
        'starts_at' => slot(2)->format('Y-m-d\TH:i'),
    ])->assertStatus(409);
});

/**
 * Mandatory pattern per CLAUDE.md §62.
 */
test('a user from tenant B cannot view, check in, or cancel an appointment belonging to tenant A', function () {
    ['owner' => $ownerA, 'branch' => $branchA, 'service' => $serviceA, 'employee' => $employeeA, 'customer' => $customerA] = bookingFixture();
    $ownerB = onboard(['modules' => ['salon']]);

    $this->actingAs($ownerA)->post('/appointments', [
        'branch_id' => $branchA->id, 'customer_id' => $customerA->id, 'service_id' => $serviceA->id,
        'user_id' => $employeeA->id, 'starts_at' => slot()->format('Y-m-d\TH:i'),
    ])->assertRedirect();
    $appointment = Appointment::firstOrFail();

    $this->actingAs($ownerB)->get("/appointments/{$appointment->id}")->assertNotFound();
    $this->actingAs($ownerB)->post("/appointments/{$appointment->id}/check-in")->assertNotFound();
    $this->actingAs($ownerB)->post("/appointments/{$appointment->id}/cancel")->assertNotFound();

    expect(Appointment::withoutGlobalScope(TenantScope::class)->find($appointment->id)->status)->toBe('confirmed');
});

test('a staff member without branch access cannot view an appointment at that branch', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => slot()->format('Y-m-d\TH:i'),
    ])->assertRedirect();
    $appointment = Appointment::firstOrFail();

    $otherBranch = Branch::factory()->forTenant($owner->tenant)->create();
    $limitedStaff = User::factory()->forTenant($owner->tenant)->create(['all_branches' => false]);
    $limitedStaff->branches()->attach($otherBranch->id);
    app(PermissionRegistrar::class)->setPermissionsTeamId($owner->tenant_id);
    $limitedStaff->assignRole('Staff');

    $this->actingAs($limitedStaff)->get("/appointments/{$appointment->id}")->assertForbidden();
});

test('waitlist: an owner can add a customer, then convert the entry into a real booking', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'customer' => $customer] = bookingFixture();

    $this->actingAs($owner)->post('/waitlist', [
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'preferred_date' => slot()->toDateString(),
    ])->assertRedirect();

    $entry = WaitlistEntry::firstOrFail();
    expect($entry->status)->toBe('waiting');

    $this->actingAs($owner)->post("/waitlist/{$entry->id}/book")
        ->assertRedirect(route('appointments.create', [
            'branch_id' => $branch->id, 'customer_id' => $customer->id,
            'service_id' => $service->id, 'waitlist_entry_id' => $entry->id,
        ]));
});

test('booking through the waitlist handoff marks the waitlist entry booked', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = bookingFixture();

    $this->actingAs($owner);
    $entry = WaitlistEntry::create([
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
    ]);

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => slot()->format('Y-m-d\TH:i'),
        'waitlist_entry_id' => $entry->id,
    ])->assertRedirect();

    expect($entry->fresh()->status)->toBe('booked');
});
