<?php

use App\Domain\Core\Actions\UpdateBranchModules;
use App\Domain\Core\Models\Branch;
use App\Domain\Platform\Actions\UpdateTenantModules;

test('every response includes the baseline security headers', function () {
    $response = $this->get('/login');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('X-Request-Id');
});

test('HSTS is not set over plain HTTP', function () {
    $response = $this->get('/login');

    $response->assertHeaderMissing('Strict-Transport-Security');
});

test('registration is rate limited', function () {
    for ($i = 0; $i < 6; $i++) {
        $this->post('/register', [])->assertStatus(302);
    }

    $this->post('/register', [])->assertStatus(429);
});

test('forgot-password is rate limited', function () {
    // Laravel's own password-broker throttle (independent of the route-
    // level throttle:6,1 under test) may turn calls 2-6 into a redirect
    // with a validation error rather than a clean "sent" status — only
    // the status code (never 429 until the 7th call) is asserted here.
    for ($i = 0; $i < 6; $i++) {
        $this->post('/forgot-password', ['email' => 'nobody@example.test'])->assertStatus(302);
    }

    $this->post('/forgot-password', ['email' => 'nobody@example.test'])->assertStatus(429);
});

test('a branch\'s module-enabled check is cached and invalidated when its modules change', function () {
    $owner = onboard(['modules' => ['salon', 'beauty']]);
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    app(UpdateBranchModules::class)->execute($branch, ['salon']);

    // Warm the cache with the "beauty disabled" answer.
    expect($branch->hasModuleEnabled('beauty'))->toBeFalse();

    app(UpdateBranchModules::class)->execute($branch, ['salon', 'beauty']);

    // Must reflect the change immediately, not the cached false.
    expect($branch->hasModuleEnabled('beauty'))->toBeTrue();
});

test('disabling a module at the tenant level invalidates the branch-level cache too', function () {
    $owner = onboard(['modules' => ['salon', 'beauty']]);
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    app(UpdateBranchModules::class)->execute($branch, ['salon', 'beauty']);

    // Warm the branch-level cache with "enabled".
    expect($branch->hasModuleEnabled('beauty'))->toBeTrue();

    app(UpdateTenantModules::class)->execute($owner->tenant, ['salon']);

    // The tenant-level disable cascades to the branch — cache must not
    // keep serving the stale "enabled" answer.
    expect($branch->fresh()->hasModuleEnabled('beauty'))->toBeFalse();
    expect($owner->tenant->fresh()->hasModuleEnabled('beauty'))->toBeFalse();
});
