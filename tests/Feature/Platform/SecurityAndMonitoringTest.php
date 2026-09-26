<?php

use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Models\EmployeeProfile;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\ErrorLog;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Support\PlatformHealth;
use App\Domain\Platform\Support\RecordErrorLog;
use App\Mail\NotificationMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

function secEmployee($tenant, string $role, string $email): App\Models\User
{
    return app(CreateEmployee::class)->execute($tenant, [
        'name' => ucfirst($role), 'email' => $email, 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => $role, 'all_branches' => true, 'branches' => [],
    ]);
}

function secProfile(App\Models\User $user): EmployeeProfile
{
    return EmployeeProfile::withoutGlobalScope(TenantScope::class)->where('user_id', $user->id)->firstOrFail();
}

// ------------------------------------------------ owner cannot be taken over by a manager

test('a manager cannot edit the owner, change the owner\'s email, or deactivate the owner', function () {
    $owner = onboard();
    $manager = secEmployee($owner->tenant, 'Manager', 'mgr@glow.test');
    // Give the owner an employee profile like any other staff member.
    $ownerProfile = EmployeeProfile::withoutGlobalScope(TenantScope::class)->where('user_id', $owner->id)->first()
        ?? tap(new EmployeeProfile(['user_id' => $owner->id, 'employment_type' => 'full_time']), function ($p) use ($owner) {
            $p->tenant_id = $owner->tenant_id;
            $p->save();
        });

    $this->actingAs($manager)->get("/employees/{$ownerProfile->id}/edit")->assertForbidden();
    $this->actingAs($manager)->put("/employees/{$ownerProfile->id}", [
        'name' => 'Owner', 'email' => 'attacker@evil.test', 'employment_type' => 'full_time', 'role' => 'Owner', 'branches' => [],
    ])->assertForbidden();
    $this->actingAs($manager)->delete("/employees/{$ownerProfile->id}")->assertForbidden();

    expect($owner->fresh()->email)->not->toBe('attacker@evil.test');
    expect($owner->fresh()->is_active)->toBeTrue();
});

test('a manager cannot promote a colleague or themselves to Owner', function () {
    $owner = onboard();
    $manager = secEmployee($owner->tenant, 'Manager', 'mgr@glow.test');
    $staff = secEmployee($owner->tenant, 'Staff', 'staff@glow.test');

    foreach ([$staff, $manager] as $target) {
        $this->actingAs($manager)->put('/employees/'.secProfile($target)->id, [
            'name' => 'X', 'email' => $target->email, 'employment_type' => 'full_time', 'role' => 'Owner', 'branches' => [],
        ])->assertSessionHasErrors('role');
    }

    app(Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($owner->tenant_id);
    expect($manager->fresh()->hasRole('Owner'))->toBeFalse();
    expect($staff->fresh()->hasRole('Owner'))->toBeFalse();
});

test('a manager can still edit ordinary staff', function () {
    $owner = onboard();
    $manager = secEmployee($owner->tenant, 'Manager', 'mgr@glow.test');
    $staff = secEmployee($owner->tenant, 'Staff', 'staff@glow.test');

    $this->actingAs($manager)->put('/employees/'.secProfile($staff)->id, [
        'name' => 'Staff Renamed', 'email' => 'staff@glow.test', 'employment_type' => 'full_time', 'role' => 'Staff', 'branches' => [],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($staff->fresh()->name)->toBe('Staff Renamed');
});

test('an owner can still assign the Owner role', function () {
    $owner = onboard();
    $staff = secEmployee($owner->tenant, 'Staff', 'staff@glow.test');

    $this->actingAs($owner)->put('/employees/'.secProfile($staff)->id, [
        'name' => 'Staff', 'email' => 'staff@glow.test', 'employment_type' => 'full_time', 'role' => 'Owner', 'branches' => [],
    ])->assertRedirect()->assertSessionHasNoErrors();

    app(Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($owner->tenant_id);
    expect($staff->fresh()->hasRole('Owner'))->toBeTrue();
});

// ------------------------------------------------ monitoring

test('a brand-new error emails Super Admin straight away, but the same error again does not', function () {
    Mail::fake();
    PlatformAdmin::factory()->create(['is_active' => true]);
    Cache::flush();

    // Same throw site => same fingerprint.
    foreach ([1, 2] as $_) {
        app(RecordErrorLog::class)->record(new RuntimeException('Disk exploded'));
    }

    Mail::assertQueued(NotificationMail::class, 1);
    expect(ErrorLog::count())->toBe(1);
});

test('new-error emails are capped per hour so an outage cannot flood the inbox', function () {
    Mail::fake();
    PlatformAdmin::factory()->create(['is_active' => true]);
    Cache::flush();

    foreach (range(1, 9) as $i) {
        app(RecordErrorLog::class)->record(new RuntimeException("Distinct failure number {$i} ".str_repeat(chr(64 + $i), $i)));
    }

    Mail::assertQueued(NotificationMail::class, 5);
    expect(ErrorLog::count())->toBe(9); // every one is still logged for the digest
});

test('the health check emails Super Admin about a stale backup, once a day', function () {
    Mail::fake();
    PlatformAdmin::factory()->create(['is_active' => true]);
    Cache::flush();

    // No backup has ever succeeded -> stale.
    $this->artisan('health:check')->assertSuccessful();
    $this->artisan('health:check')->assertSuccessful();

    Mail::assertQueued(NotificationMail::class, 1);
});

test('the health check is silent when everything is fine', function () {
    Mail::fake();
    PlatformAdmin::factory()->create(['is_active' => true]);
    Cache::flush();
    config(['platform.health.min_free_disk_mb' => 0]);
    App\Domain\Platform\Models\BackupRun::create(['status' => 'success', 'started_at' => now(), 'finished_at' => now(), 'trigger' => 'scheduled']);

    $this->artisan('health:check')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('the scheduler heartbeat goes stale after 15 minutes and shows a warning banner', function () {
    Cache::flush();
    Cache::put(PlatformHealth::HEARTBEAT_KEY, now()->subMinutes(5)->getTimestamp());
    expect(PlatformHealth::schedulerIsStale())->toBeFalse();

    Cache::put(PlatformHealth::HEARTBEAT_KEY, now()->subMinutes(20)->getTimestamp());
    expect(PlatformHealth::schedulerIsStale())->toBeTrue();

    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($admin, 'platform')->get('/platform/dashboard')->assertOk()->assertSee('Scheduled jobs are not running');

    Cache::put(PlatformHealth::HEARTBEAT_KEY, now()->getTimestamp());
    $this->actingAs($admin, 'platform')->get('/platform/dashboard')->assertOk()->assertDontSee('Scheduled jobs are not running');
});

test('the scheduler is registered to beat every minute and run the health check hourly', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('health:check')->assertSuccessful();
});
