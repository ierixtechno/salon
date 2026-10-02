<?php

use App\Domain\Core\Actions\SendNotification;
use App\Domain\Core\Models\PushSubscription;
use App\Domain\Core\Support\WebPushSender;
use App\Jobs\SendWebPush;
use Illuminate\Support\Facades\Queue;

function pushOn(): void
{
    config(['webpush.public_key' => 'BPublicKeyForTests', 'webpush.private_key' => 'privateKeyForTests']);
}

function deviceBody(string $endpoint = 'https://push.example.com/send/abc'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-secret']];
}

/** Records what would have been pushed instead of calling a real push service. */
class FakeWebPushSender extends WebPushSender
{
    public array $sent = [];

    public ?int $status = 201;

    public function send(PushSubscription $subscription, array $payload): ?int
    {
        $this->sent[] = [$subscription->endpoint, $payload];

        return $this->status;
    }
}

test('a user can register and remove their device', function () {
    pushOn();
    $owner = onboard();

    $this->actingAs($owner, 'web')->postJson('/push/subscribe', deviceBody())->assertOk();

    $sub = PushSubscription::firstOrFail();
    expect($sub->user_id)->toBe($owner->id)->and($sub->tenant_id)->toBe($owner->tenant_id);

    $this->actingAs($owner, 'web')->postJson('/push/unsubscribe', ['endpoint' => 'https://push.example.com/send/abc'])->assertOk();
    expect(PushSubscription::count())->toBe(0);
});

test('registering the same device twice keeps one row', function () {
    pushOn();
    $owner = onboard();

    $this->actingAs($owner, 'web')->postJson('/push/subscribe', deviceBody());
    $this->actingAs($owner, 'web')->postJson('/push/subscribe', deviceBody());

    expect(PushSubscription::count())->toBe(1);
});

test('a user cannot remove someone else\'s device', function () {
    pushOn();
    $a = onboard();
    $b = onboard();
    $this->actingAs($a, 'web')->postJson('/push/subscribe', deviceBody());

    $this->actingAs($b, 'web')->postJson('/push/unsubscribe', ['endpoint' => 'https://push.example.com/send/abc'])->assertOk();

    expect(PushSubscription::count())->toBe(1);
});

test('registration is refused when push is not configured, or the endpoint is not https', function () {
    $owner = onboard();
    $this->actingAs($owner, 'web')->postJson('/push/subscribe', deviceBody())->assertStatus(422);

    pushOn();
    $this->actingAs($owner, 'web')->postJson('/push/subscribe', deviceBody('http://insecure.example.com/x'))->assertRedirect();
    expect(PushSubscription::count())->toBe(0);
});

test('push endpoints require login', function () {
    $this->postJson("/push/subscribe", deviceBody())->assertRedirect();
});

test('an in-app notification queues a push only when push is configured and the recipient is a user', function () {
    Queue::fake();
    $owner = onboard();
    $send = fn (string $type = 'user') => app(SendNotification::class)->execute($owner->tenant_id, 'in_app', $type, $owner->id, null, 'Hello', 'World');

    $send();
    Queue::assertNotPushed(SendWebPush::class); // not configured

    pushOn();
    $send('customer');
    Queue::assertNotPushed(SendWebPush::class); // customers have no devices

    $log = $send();
    Queue::assertPushed(SendWebPush::class, fn ($job) => $job->notificationLogId === $log->id);
});

test('the push job sends to the recipient\'s own devices only and drops dead ones', function () {
    pushOn();
    $a = onboard();
    $b = onboard();
    $this->actingAs($a, 'web')->postJson('/push/subscribe', deviceBody('https://push.example.com/a'));
    $this->actingAs($b, 'web')->postJson('/push/subscribe', deviceBody('https://push.example.com/b'));

    $log = app(SendNotification::class)->execute($a->tenant_id, 'in_app', 'user', $a->id, null, 'Renewal due', 'Pay soon');

    $fake = new FakeWebPushSender;
    (new SendWebPush($log->id))->handle($fake);

    expect($fake->sent)->toHaveCount(1);
    expect($fake->sent[0][0])->toBe('https://push.example.com/a');
    expect($fake->sent[0][1]['title'])->toBe('Renewal due');

    $fake->status = 410; // device gone
    (new SendWebPush($log->id))->handle($fake);
    expect(PushSubscription::where('endpoint', 'https://push.example.com/a')->exists())->toBeFalse();
    expect(PushSubscription::where('endpoint', 'https://push.example.com/b')->exists())->toBeTrue();
});
