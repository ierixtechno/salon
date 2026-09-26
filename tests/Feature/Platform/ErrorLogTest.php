<?php

use App\Domain\Core\Actions\ChargeWhatsappCredit;
use App\Domain\Core\Contracts\SmsProvider;
use App\Domain\Core\Contracts\WhatsAppProvider;
use App\Domain\Core\Models\NotificationLog;
use App\Domain\Core\Models\WhatsappCreditTransaction;
use App\Domain\Platform\Models\ErrorLog;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Support\RecordErrorLog;
use App\Jobs\DeliverNotification;
use App\Mail\NotificationMail;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

// These tests count the digest email; the instant new-error alert has its own tests
// (SecurityAndMonitoringTest) and would otherwise add to the tally.
beforeEach(fn () => config(['platform.health.instant_error_alerts' => false]));

function errorLogTestThrower(string $password): never
{
    throw new RuntimeException('exploded while handling '.strlen($password).' chars');
}

/** Registers a throwaway route that always throws, under the real web middleware. */
function errorLogTestBoomRoute(): void
{
    Route::middleware('web')->get('/_boom/{token}', fn () => throw new RuntimeException('boom happened'));
}

// ---------------------------------------------------------------- recording

test('a reported exception is recorded with its class, message, and a repo-relative location', function () {
    app(RecordErrorLog::class)->record(new RuntimeException('Something broke'));

    $log = ErrorLog::firstOrFail();
    expect($log->exception_class)->toBe(RuntimeException::class);
    expect($log->message)->toBe('Something broke');
    expect($log->file)->toBe('tests/Feature/Platform/ErrorLogTest.php');
    expect($log->occurrences)->toBe(1);
    expect($log->status)->toBe('open');
    expect($log->fingerprint)->toHaveLength(40);
});

test('errors differing only by an id or a quoted value are the same error, not a new row each time', function () {
    // One source line, so only the message can differ.
    $make = fn (int $id) => new RuntimeException("Order {$id} could not be saved for 'customer-{$id}'");

    app(RecordErrorLog::class)->record($make(412));
    app(RecordErrorLog::class)->record($make(977));

    expect(ErrorLog::count())->toBe(1);
    expect(ErrorLog::first()->occurrences)->toBe(2);
});

test('recurrences of an identical error increment the counter and move last-seen forward', function () {
    $make = fn () => new LogicException('same every time');

    // Same line, same class, same message => same fingerprint.
    foreach (range(1, 3) as $i) {
        app(RecordErrorLog::class)->record($make());
        $this->travel(1)->minutes();
    }

    $log = ErrorLog::firstOrFail();
    expect(ErrorLog::count())->toBe(1);
    expect($log->occurrences)->toBe(3);
    expect($log->last_seen_at->gt($log->first_seen_at))->toBeTrue();
});

test('different errors are kept as separate rows', function () {
    app(RecordErrorLog::class)->record(new RuntimeException('first problem'));
    app(RecordErrorLog::class)->record(new InvalidArgumentException('a different problem'));

    expect(ErrorLog::count())->toBe(2);
});

test('an error marked resolved reopens itself when it happens again', function () {
    $admin = PlatformAdmin::factory()->create();
    $make = fn () => new LogicException('comes back');

    app(RecordErrorLog::class)->record($make());
    $log = ErrorLog::firstOrFail();
    $log->forceFill(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => $admin->id])->save();

    app(RecordErrorLog::class)->record($make());

    $log->refresh();
    expect($log->status)->toBe('open');
    expect($log->resolved_at)->toBeNull();
    expect($log->resolved_by)->toBeNull();
    expect($log->occurrences)->toBe(2);
});

test('database error messages have their embedded values masked (no emails/IDs from the failing row)', function () {
    $pdo = new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'secret.person@example.com' for key 'users.users_email_unique'");
    $query = new QueryException('mysql', 'insert into `users` (`email`, `password`) values (?, ?)', ['secret.person@example.com', '$2y$hashedpassword'], $pdo);

    app(RecordErrorLog::class)->record($query);

    $log = ErrorLog::firstOrFail();
    expect($log->message)->toContain('Duplicate entry');
    expect($log->message)->not->toContain('secret.person@example.com');
    expect($log->message)->not->toContain('hashedpassword');
    expect($log->message)->not->toContain('insert into');
});

test('the stored call trace never contains argument values', function () {
    try {
        errorLogTestThrower('hunter2-super-secret');
    } catch (Throwable $e) {
        app(RecordErrorLog::class)->record($e);
    }

    $log = ErrorLog::firstOrFail();
    expect($log->trace)->toContain('errorLogTestThrower()');
    expect($log->trace)->not->toContain('hunter2');
    expect(json_encode($log->getAttributes()))->not->toContain('hunter2');
});

test('over-long messages are truncated', function () {
    app(RecordErrorLog::class)->record(new RuntimeException(str_repeat('x', 5000)));

    expect(mb_strlen(ErrorLog::firstOrFail()->message))->toBeLessThanOrEqual(1003);
});

test('a failure inside the error recorder never propagates — error logging must not cause errors', function () {
    ErrorLog::creating(fn () => throw new RuntimeException('the database is down'));

    try {
        // Must simply return.
        app(RecordErrorLog::class)->record(new RuntimeException('original problem'));
    } finally {
        ErrorLog::flushEventListeners();
    }

    expect(ErrorLog::count())->toBe(0);
});

// ------------------------------------------------------------- real requests

test('a real 500 is recorded against the route pattern — never the raw URL, its secrets, or its query string', function () {
    errorLogTestBoomRoute();

    $response = $this->get('/_boom/RESET-TOKEN-abc123?password=hunter2&email=victim@example.com');

    $response->assertStatus(500);

    $log = ErrorLog::firstOrFail();
    expect($log->context)->toBe('web');
    expect($log->http_method)->toBe('GET');
    expect($log->path)->toBe('/_boom/{token}');
    expect(json_encode($log->getAttributes()))
        ->not->toContain('RESET-TOKEN-abc123')
        ->not->toContain('hunter2')
        ->not->toContain('victim@example.com');
});

test('the reference ID on the error response matches the one stored, so support can find the exact failure', function () {
    errorLogTestBoomRoute();

    $response = $this->get('/_boom/x');

    $header = $response->headers->get('X-Request-Id');
    expect($header)->toHaveLength(26);
    expect(ErrorLog::firstOrFail()->request_id)->toBe($header);
});

test('an error hit by a logged-in tenant user records which tenant, so it can be traced to a customer', function () {
    errorLogTestBoomRoute();
    $owner = onboard();

    $this->actingAs($owner, 'web')->get('/_boom/x')->assertStatus(500);

    $log = ErrorLog::firstOrFail();
    expect($log->tenant_id)->toBe($owner->tenant_id);
    expect($log->user_id)->toBe($owner->id);
});

test('expected client errors (validation, 404, 403) are not logged as application errors', function () {
    $owner = onboard();

    $this->get('/definitely-not-a-page')->assertNotFound();
    $this->actingAs($owner, 'web')->post('/customers', [])->assertSessionHasErrors();

    expect(ErrorLog::count())->toBe(0);
});

test('failures inside a queued notification delivery are surfaced in the error log, not swallowed', function () {
    $owner = onboard();

    $failing = new class implements WhatsAppProvider
    {
        public function send(string $toPhone, string $message): string
        {
            throw new RuntimeException('WhatsApp provider outage');
        }
    };
    $this->app->instance(WhatsAppProvider::class, $failing);

    $log = new NotificationLog(['channel' => 'whatsapp', 'recipient_type' => 'user', 'recipient_id' => $owner->id, 'to_address' => '9999999999', 'body' => 'hi']);
    $log->tenant_id = $owner->tenant_id;
    $log->status = 'queued';
    $log->save();

    // Credit needed, otherwise it is skipped before reaching the provider.
    WhatsappCreditTransaction::create(['tenant_id' => $owner->tenant_id, 'type' => 'credit', 'amount' => 5]);

    (new DeliverNotification($log->id))->handle(app(SmsProvider::class), app(WhatsAppProvider::class), app(ChargeWhatsappCredit::class));

    expect($log->fresh()->status)->toBe('failed');
    expect(ErrorLog::where('message', 'WhatsApp provider outage')->exists())->toBeTrue();
});

// ---------------------------------------------------------- Super Admin pages

test('Super Admin sees open errors, with a count badge in the sidebar', function () {
    $admin = PlatformAdmin::factory()->create();
    app(RecordErrorLog::class)->record(new RuntimeException('Payment webhook exploded'));

    $this->actingAs($admin, 'platform')->get('/platform/error-logs')
        ->assertOk()
        ->assertSee('RuntimeException')
        ->assertSee('Payment webhook exploded');

    // The badge is on every platform page, not just this one.
    $this->actingAs($admin, 'platform')->get('/platform/dashboard')
        ->assertOk()
        ->assertSee('Error log');
});

test('the error list can be filtered by status and searched by message, class, path or reference ID', function () {
    $admin = PlatformAdmin::factory()->create();
    app(RecordErrorLog::class)->record(new RuntimeException('alpha failure'));
    app(RecordErrorLog::class)->record(new InvalidArgumentException('beta failure'));
    ErrorLog::where('message', 'beta failure')->first()->forceFill(['status' => 'resolved', 'request_id' => '01HZZZZZZZZZZZZZZZZZZZZZZZ'])->save();

    $this->actingAs($admin, 'platform')->get('/platform/error-logs')
        ->assertSee('alpha failure')->assertDontSee('beta failure');

    $this->actingAs($admin, 'platform')->get('/platform/error-logs?status=resolved')
        ->assertSee('beta failure')->assertDontSee('alpha failure');

    $this->actingAs($admin, 'platform')->get('/platform/error-logs?status=all&q=beta')
        ->assertSee('beta failure')->assertDontSee('alpha failure');

    $this->actingAs($admin, 'platform')->get('/platform/error-logs?status=all&q=01HZZZZZZZZZZZZZZZZZZZZZZZ')
        ->assertSee('beta failure')->assertDontSee('alpha failure');
});

test('Super Admin can open an error to see its details and call trace', function () {
    $admin = PlatformAdmin::factory()->create();
    try {
        errorLogTestThrower('pw');
    } catch (Throwable $e) {
        app(RecordErrorLog::class)->record($e);
    }
    $log = ErrorLog::firstOrFail();

    $this->actingAs($admin, 'platform')->get("/platform/error-logs/{$log->id}")
        ->assertOk()
        ->assertSee('exploded while handling')
        ->assertSee('Call trace')
        ->assertSee('errorLogTestThrower()');
});

test('Super Admin can resolve and reopen an error, both audit-logged', function () {
    $admin = PlatformAdmin::factory()->create();
    app(RecordErrorLog::class)->record(new RuntimeException('to be fixed'));
    $log = ErrorLog::firstOrFail();

    $this->actingAs($admin, 'platform')->patch("/platform/error-logs/{$log->id}/resolve")->assertRedirect();

    $log->refresh();
    expect($log->status)->toBe('resolved');
    expect($log->resolved_by)->toBe($admin->id);
    expect($log->resolved_at)->not->toBeNull();
    expect(PlatformAuditLog::where('action', 'error_log.resolved')->where('entity_id', $log->id)->exists())->toBeTrue();

    $this->actingAs($admin, 'platform')->patch("/platform/error-logs/{$log->id}/reopen")->assertRedirect();

    expect($log->fresh()->status)->toBe('open');
    expect(PlatformAuditLog::where('action', 'error_log.reopened')->where('entity_id', $log->id)->exists())->toBeTrue();
});

test('tenant users and guests can never see the error log — it contains internal file paths and traces', function () {
    $owner = onboard();
    app(RecordErrorLog::class)->record(new RuntimeException('internal detail'));
    $log = ErrorLog::firstOrFail();

    $this->get('/platform/error-logs')->assertRedirect(route('platform.login'));
    $this->get("/platform/error-logs/{$log->id}")->assertRedirect(route('platform.login'));
    $this->patch("/platform/error-logs/{$log->id}/resolve")->assertRedirect(route('platform.login'));

    $this->actingAs($owner, 'web')->get('/platform/error-logs')->assertRedirect(route('platform.login'));
    $this->actingAs($owner, 'web')->get("/platform/error-logs/{$log->id}")->assertRedirect(route('platform.login'));
    $this->actingAs($owner, 'web')->patch("/platform/error-logs/{$log->id}/resolve")->assertRedirect(route('platform.login'));

    expect($log->fresh()->status)->toBe('open');
});

// ------------------------------------------------------------ digest & prune

test('the daily digest emails every active Super Admin when there are recent open errors', function () {
    Mail::fake();
    $adminA = PlatformAdmin::factory()->create(['is_active' => true]);
    $adminB = PlatformAdmin::factory()->create(['is_active' => true]);
    $inactive = PlatformAdmin::factory()->create(['is_active' => false]);
    app(RecordErrorLog::class)->record(new RuntimeException('digest me'));

    $this->artisan('error-logs:send-digest')->assertSuccessful();

    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $m) => str_contains($m->mailSubject, 'Error digest')
        && str_contains($m->mailBody, 'digest me')
        && str_contains($m->mailBody, '[NEW]')
        && $m->hasTo($adminA->email) && $m->hasTo($adminB->email) && ! $m->hasTo($inactive->email));
});

test('no digest is sent on a quiet day', function () {
    Mail::fake();
    PlatformAdmin::factory()->create();

    $this->artisan('error-logs:send-digest')->expectsOutputToContain('no digest sent')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('resolved errors and errors last seen more than a day ago are left out of the digest', function () {
    Mail::fake();
    PlatformAdmin::factory()->create();
    app(RecordErrorLog::class)->record(new RuntimeException('old news'));
    ErrorLog::firstOrFail()->forceFill(['last_seen_at' => now()->subDays(3)])->save();
    app(RecordErrorLog::class)->record(new InvalidArgumentException('already handled'));
    ErrorLog::where('message', 'already handled')->first()->forceFill(['status' => 'resolved'])->save();

    $this->artisan('error-logs:send-digest')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('running the digest twice in one day sends it once', function () {
    Mail::fake();
    PlatformAdmin::factory()->create();
    app(RecordErrorLog::class)->record(new RuntimeException('only once'));

    $this->artisan('error-logs:send-digest')->assertSuccessful();
    $this->artisan('error-logs:send-digest')->expectsOutputToContain('already sent')->assertSuccessful();

    Mail::assertQueuedCount(1);
});

test('pruning removes errors not seen for the retention period, and keeps anything still happening', function () {
    config(['logging.error_log_retention_days' => 90]);
    app(RecordErrorLog::class)->record(new RuntimeException('long gone'));
    app(RecordErrorLog::class)->record(new InvalidArgumentException('still happening'));

    // "still happening" first appeared ages ago but was seen today — must survive.
    ErrorLog::where('message', 'long gone')->update(['last_seen_at' => now()->subDays(120)]);
    ErrorLog::where('message', 'still happening')->update(['first_seen_at' => now()->subDays(400)]);

    $this->artisan('error-logs:prune')->assertSuccessful();

    expect(ErrorLog::where('message', 'long gone')->exists())->toBeFalse();
    expect(ErrorLog::where('message', 'still happening')->exists())->toBeTrue();
});

// ------------------------------------------------------------- friendly pages

test('a 500 shows a friendly page with a reference ID — and never the exception message, a stack trace, or a file path', function () {
    config(['app.debug' => false]);
    errorLogTestBoomRoute();

    $response = $this->get('/_boom/x');

    $response->assertStatus(500)
        ->assertSee('Something went wrong on our side')
        ->assertSee($response->headers->get('X-Request-Id'))
        ->assertDontSee('boom happened')
        ->assertDontSee('RuntimeException')
        ->assertDontSee('ErrorLogTest.php')
        ->assertDontSee('vendor');
});

test('a 404 shows the branded page', function () {
    config(['app.debug' => false]);

    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertSee('Go to home');
});

test('a deliberate 403 business message is shown to the user, since the app wrote it for them', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/_forbidden', fn () => abort(403, 'The spa module is not enabled for this account.'));

    $this->get('/_forbidden')
        ->assertForbidden()
        ->assertSee('Not allowed')
        ->assertSee('The spa module is not enabled for this account.');
});

test('a 409 conflict message is shown too, but a 500 message never is', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/_conflict', fn () => abort(409, 'This quotation is no longer payable.'));
    Route::middleware('web')->get('/_leaky', fn () => abort(500, 'SQLSTATE secret internals'));

    $this->get('/_conflict')->assertStatus(409)->assertSee('This quotation is no longer payable.');
    $this->get('/_leaky')->assertStatus(500)->assertDontSee('SQLSTATE secret internals');
});

test('the error pages need no session, database or built assets — so they cannot fail themselves', function () {
    $layout = file_get_contents(resource_path('views/errors/layout.blade.php'));

    expect($layout)->not->toContain('@vite(');
    expect($layout)->not->toContain('<x-');
    expect($layout)->not->toContain('csrf_token');
});
