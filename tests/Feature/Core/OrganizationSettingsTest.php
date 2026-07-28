<?php

use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Models\BusinessHour;
use App\Domain\Core\Models\BusinessProfile;

test('an owner can view and update the business profile', function () {
    $owner = onboard();

    $this->actingAs($owner)->get('/settings/organization')->assertOk();

    $this->actingAs($owner)->put('/settings/organization', [
        'display_name' => 'Glow Salon & Spa',
        'contact_email' => 'hello@glow.test',
    ])->assertRedirect();

    $profile = BusinessProfile::where('tenant_id', $owner->tenant_id)->firstOrFail();
    expect($profile->display_name)->toBe('Glow Salon & Spa');
    expect($profile->contact_email)->toBe('hello@glow.test');
});

test('an owner can set a business type preset and it round-trips', function () {
    $owner = onboard();

    $this->actingAs($owner)->put('/settings/organization', [
        'display_name' => 'Glow Salon & Spa',
        'business_type' => 'barber',
    ])->assertRedirect();

    $profile = BusinessProfile::where('tenant_id', $owner->tenant_id)->firstOrFail();
    expect($profile->business_type)->toBe('barber');

    $this->actingAs($owner)->get('/settings/organization')->assertOk()->assertSee('Barber', escape: false);
});

test('an invalid business type is rejected', function () {
    $owner = onboard();

    $this->actingAs($owner)->put('/settings/organization', [
        'display_name' => 'Glow Salon & Spa',
        'business_type' => 'not-a-real-type',
    ])->assertSessionHasErrors('business_type');
});

test('an owner can update weekly business hours', function () {
    $owner = onboard();

    $hours = collect(range(0, 6))->map(fn ($day) => [
        'day_of_week' => $day,
        'is_closed' => $day === 0, // closed Sundays
        'opens_at' => '09:00',
        'closes_at' => '18:00',
    ])->all();

    $this->actingAs($owner)
        ->put('/settings/organization/hours', ['hours' => $hours])
        ->assertRedirect();

    $sunday = BusinessHour::where('tenant_id', $owner->tenant_id)
        ->where('day_of_week', 0)->firstOrFail();

    expect($sunday->is_closed)->toBeTrue();
    expect($sunday->opens_at)->toBeNull();
});

test('an employee without the settings permission cannot access organization settings', function () {
    $owner = onboard();

    $staff = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Staffer', 'email' => 'staffer@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);

    $this->actingAs($staff)->get('/settings/organization')->assertForbidden();
});
