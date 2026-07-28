<?php

use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Actions\UpdateBranchModules;
use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\EmployeeSchedule;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Platform\Models\Module;
use Illuminate\Support\Carbon;

/**
 * Same shape as AppointmentEngineTest.php's bookingFixture(), extended with
 * a home/venue-enabled service — kept local to this file rather than
 * sharing bookingFixture() across test files (Pest test files don't share
 * top-level function declarations with each other).
 */
function serviceModeFixture(array $serviceOverrides = []): array
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
        'home_service_enabled' => true,
        'home_service_fee' => 150,
        'travel_buffer_minutes' => 20,
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

function modeSlot(int $daysFromNow = 1, int $hour = 10): Carbon
{
    return now()->addDays($daysFromNow)->setTime($hour, 0)->seconds(0);
}

test('a home-service booking succeeds, includes the fee in price, and stores the delivery address', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = serviceModeFixture();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'user_id' => $employee->id,
        'starts_at' => modeSlot()->format('Y-m-d\TH:i'),
        'service_mode' => 'home',
        'delivery_address' => '221B Baker Street',
    ])->assertRedirect();

    $appointment = Appointment::firstOrFail();
    expect($appointment->service_mode)->toBe('home');
    expect($appointment->delivery_address)->toBe('221B Baker Street');
    expect((float) $appointment->price)->toBe(650.0); // 500 base + 150 home fee
});

test('a home-service booking is rejected when the service does not have home service enabled', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = serviceModeFixture([
        'home_service_enabled' => false,
    ]);

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'user_id' => $employee->id,
        'starts_at' => modeSlot()->format('Y-m-d\TH:i'),
        'service_mode' => 'home',
        'delivery_address' => '221B Baker Street',
    ])->assertSessionHasErrors('service_mode');
});

test('a home-service booking is rejected when a room/resource is also selected', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = serviceModeFixture();

    $resource = App\Domain\Core\Models\Resource::factory()->forTenant($owner->tenant)->create(['branch_id' => $branch->id]);

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'user_id' => $employee->id,
        'resource_id' => $resource->id,
        'starts_at' => modeSlot()->format('Y-m-d\TH:i'),
        'service_mode' => 'home',
        'delivery_address' => '221B Baker Street',
    ])->assertSessionHasErrors('resource_id');
});

test('a home-service booking is rejected without a delivery address', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = serviceModeFixture();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'user_id' => $employee->id,
        'starts_at' => modeSlot()->format('Y-m-d\TH:i'),
        'service_mode' => 'home',
    ])->assertSessionHasErrors('delivery_address');
});

test('the travel buffer is respected between two home-service bookings for the same employee', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = serviceModeFixture();
    $slot = modeSlot();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => $slot->format('Y-m-d\TH:i'),
        'service_mode' => 'home', 'delivery_address' => 'Address 1',
    ])->assertRedirect();

    // Service is 30 min + 10 min prep buffer + 20 min travel buffer = busy until +60 minutes.
    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => $slot->copy()->addMinutes(55)->format('Y-m-d\TH:i'),
        'service_mode' => 'home', 'delivery_address' => 'Address 2',
    ])->assertStatus(409);

    // Right after the combined buffer ends — must succeed.
    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => $slot->copy()->addMinutes(60)->format('Y-m-d\TH:i'),
        'service_mode' => 'home', 'delivery_address' => 'Address 3',
    ])->assertRedirect();

    expect(Appointment::count())->toBe(2);
});

test('rescheduling a home-service appointment preserves its mode and re-applies the travel buffer', function () {
    ['owner' => $owner, 'branch' => $branch, 'service' => $service, 'employee' => $employee, 'customer' => $customer] = serviceModeFixture();
    $slot = modeSlot();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => $slot->format('Y-m-d\TH:i'),
        'service_mode' => 'home', 'delivery_address' => 'Address 1',
    ])->assertRedirect();
    $appointment = Appointment::firstOrFail();

    $this->actingAs($owner)->post('/appointments', [
        'branch_id' => $branch->id, 'customer_id' => $customer->id, 'service_id' => $service->id,
        'user_id' => $employee->id, 'starts_at' => $slot->copy()->addHours(3)->format('Y-m-d\TH:i'),
        'service_mode' => 'home', 'delivery_address' => 'Address 2',
    ])->assertRedirect();
    $secondAppointment = Appointment::where('id', '!=', $appointment->id)->firstOrFail();

    // Reschedule appointment #1 to within the travel buffer of #2 — must conflict.
    $this->actingAs($owner)->put("/appointments/{$appointment->id}/reschedule", [
        'starts_at' => $secondAppointment->starts_at->copy()->timezone($branch->effectiveTimezone())->subMinutes(55)->format('Y-m-d\TH:i'),
    ])->assertStatus(409);

    expect($appointment->fresh()->service_mode)->toBe('home');
});
