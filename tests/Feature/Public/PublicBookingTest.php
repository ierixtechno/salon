<?php

use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Actions\UpdateBranchModules;
use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\EmployeeSchedule;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\Module;
use Illuminate\Support\Carbon;

/**
 * Mirrors AppointmentEngineTest's bookingFixture() — a single employee
 * scheduled every day 09:00-18:00 at a branch with no explicit business
 * hours saved (falls back to the 09:00-18:00 default, see Branch::
 * effectiveHoursFor()), capable of one bookable service.
 */
function publicBookingFixture(): array
{
    $owner = onboard(['modules' => ['salon']]);
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    app(UpdateBranchModules::class)->execute($branch, ['salon']);

    $category = ServiceCategory::factory()->forTenant($owner->tenant)->create([
        'module_id' => Module::where('code', 'salon')->firstOrFail()->id,
    ]);
    $service = Service::factory()->forCategory($category)->create([
        'duration_minutes' => 30, 'buffer_minutes' => 10, 'base_price' => 500,
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

    return compact('owner', 'branch', 'category', 'service', 'employee');
}

function publicSlot(int $daysFromNow = 1, int $hour = 10): Carbon
{
    return now()->addDays($daysFromNow)->setTime($hour, 0)->seconds(0);
}

test('a visitor can view the booking page and see the branch and service', function () {
    $fixture = publicBookingFixture();

    $response = $this->get('/book/'.$fixture['owner']->tenant->slug);

    $response->assertOk();
    $response->assertSee($fixture['branch']->name);
});

test('an unknown tenant slug and a suspended tenant both 404 identically', function () {
    $unknown = $this->get('/book/does-not-exist-at-all');
    $unknown->assertNotFound();

    $fixture = publicBookingFixture();
    $fixture['owner']->tenant->update(['status' => 'suspended']);

    $suspended = $this->get('/book/'.$fixture['owner']->tenant->slug);
    $suspended->assertNotFound();
});

test('a visitor can fetch available slots for a service', function () {
    $fixture = publicBookingFixture();

    $response = $this->getJson('/book/'.$fixture['owner']->tenant->slug.'/slots?'.http_build_query([
        'branch_id' => $fixture['branch']->id,
        'service_id' => $fixture['service']->id,
        'date' => publicSlot()->toDateString(),
    ]));

    $response->assertOk();
    $slots = $response->json('slots');
    expect($slots)->not->toBeEmpty();
    expect($slots[0])->toHaveKeys(['iso', 'label']);
});

test('a visitor can complete a booking, which lands as pending and auto-assigns a capable employee', function () {
    $fixture = publicBookingFixture();

    $response = $this->post('/book/'.$fixture['owner']->tenant->slug, [
        'branch_id' => $fixture['branch']->id,
        'service_id' => $fixture['service']->id,
        'starts_at' => publicSlot()->toIso8601String(),
        'name' => 'Priya Sharma',
        'phone' => '9876543210',
        'email' => 'priya@example.test',
        'marketing_consent' => '1',
    ]);

    $response->assertRedirect();

    $appointment = Appointment::withoutGlobalScope(TenantScope::class)->where('tenant_id', $fixture['owner']->tenant_id)->firstOrFail();
    expect($appointment->status)->toBe('pending');
    expect($appointment->source)->toBe('online');
    expect($appointment->user_id)->toBe($fixture['employee']->id);
    expect($appointment->public_token)->not->toBeNull();

    $response->assertRedirectContains($appointment->public_token);
});

test('a repeat visitor with the same phone reuses the existing customer without overwriting their name', function () {
    $fixture = publicBookingFixture();
    $slug = $fixture['owner']->tenant->slug;

    $this->post('/book/'.$slug, [
        'branch_id' => $fixture['branch']->id, 'service_id' => $fixture['service']->id,
        'starts_at' => publicSlot(1, 10)->toIso8601String(),
        'name' => 'Priya Sharma', 'phone' => '9876543210',
    ]);

    $this->post('/book/'.$slug, [
        'branch_id' => $fixture['branch']->id, 'service_id' => $fixture['service']->id,
        'starts_at' => publicSlot(2, 10)->toIso8601String(),
        'name' => 'Not Priya At All', 'phone' => '9876543210',
    ]);

    $customers = Customer::withoutGlobalScope(TenantScope::class)->where('tenant_id', $fixture['owner']->tenant_id)->where('phone', '9876543210')->get();
    expect($customers)->toHaveCount(1);
    expect($customers->first()->name)->toBe('Priya Sharma');

    $appointments = Appointment::withoutGlobalScope(TenantScope::class)->where('tenant_id', $fixture['owner']->tenant_id)->get();
    expect($appointments)->toHaveCount(2);
});

test('a filled honeypot field silently creates nothing', function () {
    $fixture = publicBookingFixture();

    $response = $this->post('/book/'.$fixture['owner']->tenant->slug, [
        'branch_id' => $fixture['branch']->id,
        'service_id' => $fixture['service']->id,
        'starts_at' => publicSlot()->toIso8601String(),
        'name' => 'Bot',
        'phone' => '9999999999',
        'website' => 'http://spam.example',
    ]);

    $response->assertRedirect();
    expect(Appointment::withoutGlobalScope(TenantScope::class)->where('tenant_id', $fixture['owner']->tenant_id)->count())->toBe(0);
    expect(Customer::withoutGlobalScope(TenantScope::class)->where('tenant_id', $fixture['owner']->tenant_id)->count())->toBe(0);
});

test('a booking created under one tenant is not reachable via another tenant\'s slug', function () {
    $fixtureA = publicBookingFixture();
    $fixtureB = publicBookingFixture();

    $this->post('/book/'.$fixtureA['owner']->tenant->slug, [
        'branch_id' => $fixtureA['branch']->id, 'service_id' => $fixtureA['service']->id,
        'starts_at' => publicSlot()->toIso8601String(),
        'name' => 'Priya', 'phone' => '9876500000',
    ]);

    $appointment = Appointment::withoutGlobalScope(TenantScope::class)->where('tenant_id', $fixtureA['owner']->tenant_id)->firstOrFail();

    $this->get('/book/'.$fixtureA['owner']->tenant->slug.'/confirmation/'.$appointment->public_token)->assertOk();
    $this->get('/book/'.$fixtureB['owner']->tenant->slug.'/confirmation/'.$appointment->public_token)->assertNotFound();
});

test('double-booking the same employee/slot is rejected server-side even if both looked available', function () {
    $fixture = publicBookingFixture();
    $slug = $fixture['owner']->tenant->slug;
    $slot = publicSlot(1, 10)->toIso8601String();

    $first = $this->post('/book/'.$slug, [
        'branch_id' => $fixture['branch']->id, 'service_id' => $fixture['service']->id,
        'starts_at' => $slot, 'name' => 'First', 'phone' => '9111111111',
    ]);
    $first->assertRedirect();

    // Only one capable employee exists in this fixture, so a second
    // request for the exact same slot has no candidate left to fall back
    // to — BookPublicAppointment must surface a clean conflict, never
    // silently double-book the same employee.
    $second = $this->post('/book/'.$slug, [
        'branch_id' => $fixture['branch']->id, 'service_id' => $fixture['service']->id,
        'starts_at' => $slot, 'name' => 'Second', 'phone' => '9222222222',
    ]);

    $second->assertStatus(409);
    expect(Appointment::withoutGlobalScope(TenantScope::class)->where('tenant_id', $fixture['owner']->tenant_id)->count())->toBe(1);
});

test('a booking can be cancelled via its public link, and cancelling twice fails cleanly', function () {
    $fixture = publicBookingFixture();
    $slug = $fixture['owner']->tenant->slug;

    $this->post('/book/'.$slug, [
        'branch_id' => $fixture['branch']->id, 'service_id' => $fixture['service']->id,
        'starts_at' => publicSlot()->toIso8601String(),
        'name' => 'Priya', 'phone' => '9876511111',
    ]);
    $appointment = Appointment::withoutGlobalScope(TenantScope::class)->where('tenant_id', $fixture['owner']->tenant_id)->firstOrFail();

    $cancel = $this->post('/book/'.$slug.'/confirmation/'.$appointment->public_token.'/cancel');
    $cancel->assertRedirect();
    expect($appointment->fresh()->status)->toBe('cancelled');

    $this->post('/book/'.$slug.'/confirmation/'.$appointment->public_token.'/cancel')->assertStatus(409);
});

test('the public booking page is rate limited per IP', function () {
    $fixture = publicBookingFixture();
    $slug = $fixture['owner']->tenant->slug;

    // The 'public-booking' limiter (AppServiceProvider) allows 20/min per
    // IP — the 21st request in the same window must be throttled.
    for ($i = 0; $i < 20; $i++) {
        $this->get('/book/'.$slug)->assertOk();
    }

    $this->get('/book/'.$slug)->assertStatus(429);
});
