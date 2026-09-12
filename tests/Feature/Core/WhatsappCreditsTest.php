<?php

use App\Domain\Core\Actions\ChargeWhatsappCredit;
use App\Domain\Core\Contracts\SmsProvider;
use App\Domain\Core\Contracts\WhatsAppProvider;
use App\Domain\Core\Models\NotificationLog;
use App\Domain\Core\Models\WhatsappCreditTransaction;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Models\Tenant;
use App\Jobs\DeliverNotification;

class FakeSucceedingWhatsAppProvider implements WhatsAppProvider
{
    public function send(string $toPhone, string $message): string
    {
        return 'wa-fake-message-id';
    }
}

class FakeFailingWhatsAppProvider implements WhatsAppProvider
{
    public function send(string $toPhone, string $message): string
    {
        throw new RuntimeException('Simulated provider failure.');
    }
}

function makeQueuedWhatsappLog(int $tenantId, int $userId): NotificationLog
{
    // tenant_id is deliberately not fillable (CLAUDE.md §28) — set
    // directly, mirroring SendNotification's own construction.
    $log = new NotificationLog([
        'channel' => 'whatsapp',
        'recipient_type' => 'user',
        'recipient_id' => $userId,
        'to_address' => '9999999999',
        'subject' => null,
        'body' => 'Test WhatsApp message',
    ]);
    $log->tenant_id = $tenantId;
    $log->status = 'queued';
    $log->save();

    return $log;
}

test('a super admin can top up a tenant\'s WhatsApp credit balance', function () {
    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();

    $this->actingAs($admin, 'platform')
        ->post("/platform/tenants/{$owner->tenant_id}/whatsapp-credits", [
            'amount' => 500,
            'reason' => 'Purchased 500 credits',
        ])
        ->assertRedirect();

    $tenant = Tenant::findOrFail($owner->tenant_id);
    expect($tenant->whatsappCreditBalance())->toBe(500);

    expect(PlatformAuditLog::where('action', 'tenant.whatsapp_credits_topped_up')
        ->where('tenant_id', $tenant->id)
        ->exists())->toBeTrue();
});

test('a queued WhatsApp notification is skipped, not sent, when the tenant has no credit balance', function () {
    $this->app->bind(WhatsAppProvider::class, FakeSucceedingWhatsAppProvider::class);

    $owner = onboard();
    $log = makeQueuedWhatsappLog($owner->tenant_id, $owner->id);

    (new DeliverNotification($log->id))->handle(
        app(SmsProvider::class),
        app(WhatsAppProvider::class),
        app(ChargeWhatsappCredit::class),
    );

    $log->refresh();
    expect($log->status)->toBe('skipped');
    expect($log->error_message)->toContain('Insufficient WhatsApp credits');
});

test('a successful WhatsApp send debits exactly 1 credit', function () {
    $this->app->bind(WhatsAppProvider::class, FakeSucceedingWhatsAppProvider::class);

    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $tenant = Tenant::findOrFail($owner->tenant_id);

    $this->actingAs($admin, 'platform')
        ->post("/platform/tenants/{$tenant->id}/whatsapp-credits", ['amount' => 10]);

    $log = makeQueuedWhatsappLog($tenant->id, $owner->id);

    (new DeliverNotification($log->id))->handle(
        app(SmsProvider::class),
        app(WhatsAppProvider::class),
        app(ChargeWhatsappCredit::class),
    );

    $log->refresh();
    expect($log->status)->toBe('sent');
    expect($tenant->whatsappCreditBalance())->toBe(9);

    $debit = WhatsappCreditTransaction::where('tenant_id', $tenant->id)->where('type', 'debit')->firstOrFail();
    expect((int) $debit->amount)->toBe(-1);
    expect($debit->reference_type)->toBe('NotificationLog');
    expect($debit->reference_id)->toBe($log->id);
});

test('a failed WhatsApp send does not debit any credit', function () {
    $this->app->bind(WhatsAppProvider::class, FakeFailingWhatsAppProvider::class);

    $admin = PlatformAdmin::factory()->create();
    $owner = onboard();
    $tenant = Tenant::findOrFail($owner->tenant_id);

    $this->actingAs($admin, 'platform')
        ->post("/platform/tenants/{$tenant->id}/whatsapp-credits", ['amount' => 10]);

    $log = makeQueuedWhatsappLog($tenant->id, $owner->id);

    (new DeliverNotification($log->id))->handle(
        app(SmsProvider::class),
        app(WhatsAppProvider::class),
        app(ChargeWhatsappCredit::class),
    );

    $log->refresh();
    expect($log->status)->toBe('failed');
    expect($tenant->whatsappCreditBalance())->toBe(10);
});
