<?php

use App\Domain\Core\Models\NotificationLog;

function inAppNote($user, string $subject, bool $read = false): NotificationLog
{
    $log = new NotificationLog;
    $log->forceFill([
        'tenant_id' => $user->tenant_id, 'channel' => 'in_app', 'recipient_type' => 'user', 'recipient_id' => $user->id,
        'subject' => $subject, 'body' => 'Body of '.$subject, 'status' => 'sent', 'read_at' => $read ? now() : null,
    ])->saveQuietly();

    return $log;
}

test('the bell shows the unread count on every page', function () {
    $owner = onboard();
    inAppNote($owner, 'Renewal due');
    inAppNote($owner, 'Old one', read: true);

    $this->actingAs($owner, 'web')->get('/dashboard')->assertOk()->assertSee('aria-label="Notifications"', false)->assertSee('Renewal due');

    $this->actingAs($owner, 'web')->getJson('/my-notifications/summary')->assertOk()
        ->assertJsonPath('unread', 1)->assertJsonCount(2, 'items')->assertJsonPath('items.0.unread', true);
});

test('mark all read clears only the current user\'s unread notifications', function () {
    $a = onboard();
    $b = onboard();
    inAppNote($a, 'For A');
    inAppNote($b, 'For B');

    $this->actingAs($a, 'web')->postJson('/my-notifications/read-all')->assertOk()->assertJsonPath('unread', 0);

    $this->actingAs($b, 'web')->getJson('/my-notifications/summary')->assertJsonPath('unread', 1);
});

test('the summary never includes another user\'s notifications', function () {
    $a = onboard();
    $b = onboard();
    inAppNote($b, 'Secret for B');

    $this->actingAs($a, 'web')->getJson('/my-notifications/summary')->assertOk()->assertJsonPath('unread', 0)->assertJsonCount(0, 'items');
});

test('the summary requires login', function () {
    $this->getJson("/my-notifications/summary")->assertRedirect();
});
