<?php

use App\Domain\Platform\Actions\NotifyPlatformAdmins;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

test('every active super admin gets an in-app entry, inactive ones do not', function () {
    Mail::fake();
    $a = PlatformAdmin::factory()->create();
    $b = PlatformAdmin::factory()->create();
    $off = PlatformAdmin::factory()->create(['is_active' => false]);

    app(NotifyPlatformAdmins::class)->execute('Hello', 'World', '/platform/tenants', email: false);

    expect(PlatformNotification::where('platform_admin_id', $a->id)->count())->toBe(1);
    expect(PlatformNotification::where('platform_admin_id', $b->id)->count())->toBe(1);
    expect(PlatformNotification::where('platform_admin_id', $off->id)->count())->toBe(0);
});

test('a new quotation and a payment each alert super admin', function () {
    Mail::fake();
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $quotation = app(App\Domain\Platform\Actions\CreateQuotation::class)->execute($owner->tenant, $owner->tenant->currentSubscription()->plan, null);

    expect(PlatformNotification::where('kind', 'quotation')->where('platform_admin_id', $admin->id)->exists())->toBeTrue();

    app(App\Domain\Platform\Actions\PayQuotation::class)->execute($quotation, 'upi', 'UTR-NOTIF-1');

    $note = PlatformNotification::where('kind', 'invoice')->where('platform_admin_id', $admin->id)->firstOrFail();
    expect($note->title)->toContain('Payment received');
    expect($note->url)->toStartWith('/platform/invoices/');
});

test('the daily renewal job alerts super admin about upcoming renewals', function () {
    Mail::fake();
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    PlatformNotification::query()->delete();

    App\Domain\Platform\Models\TenantSubscription::where('tenant_id', $owner->tenant_id)
        ->update(['ends_at' => today()->addDays(5)->setTime(23, 59, 59)]);
    App\Domain\Platform\Models\Tenant::forgetSubscriptionCache($owner->tenant_id);

    Artisan::call('subscriptions:process-renewals');

    expect(PlatformNotification::where('kind', 'renewal')->where('platform_admin_id', $admin->id)->where('title', 'like', 'Upcoming renewal%')->exists())->toBeTrue();
    expect(PlatformNotification::where('kind', 'quotation')->where('platform_admin_id', $admin->id)->exists())->toBeTrue(); // the pending renewal quotation
});

test('the bell shows on platform pages and the summary is the admin\'s own', function () {
    Mail::fake();
    $admin = PlatformAdmin::factory()->create();
    $other = PlatformAdmin::factory()->create();
    app(NotifyPlatformAdmins::class)->execute('Ping', 'Body', '/platform/tenants', email: false);

    $this->actingAs($admin, 'platform')->get('/platform/dashboard')->assertOk()->assertSee('aria-label="Notifications"', false);
    $this->actingAs($admin, 'platform')->getJson('/platform/notifications/summary')->assertOk()->assertJsonPath('unread', 1);

    $this->actingAs($admin, 'platform')->postJson('/platform/notifications/read-all')->assertOk()->assertJsonPath('unread', 0);
    $this->actingAs($other, 'platform')->getJson('/platform/notifications/summary')->assertJsonPath('unread', 1);
});

test('opening a notification marks it read and redirects, and others\' notifications are 404', function () {
    Mail::fake();
    $admin = PlatformAdmin::factory()->create();
    $other = PlatformAdmin::factory()->create();
    app(NotifyPlatformAdmins::class)->execute('Ping', 'Body', '/platform/tenants', email: false);
    $mine = PlatformNotification::where('platform_admin_id', $admin->id)->firstOrFail();

    $this->actingAs($other, 'platform')->get("/platform/notifications/{$mine->id}/open")->assertNotFound();
    expect($mine->fresh()->read_at)->toBeNull();

    $this->actingAs($admin, 'platform')->get("/platform/notifications/{$mine->id}/open")->assertRedirect('/platform/tenants');
    expect($mine->fresh()->read_at)->not->toBeNull();

    $this->actingAs($admin, 'platform')->get('/platform/notifications')->assertOk()->assertSee('Ping');
});

test('a tenant user cannot reach the platform notifications', function () {
    $owner = onboard();

    $this->actingAs($owner, 'web')->getJson('/platform/notifications/summary')->assertRedirect();
});
