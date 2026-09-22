<?php

use App\Domain\Core\Actions\SendNotification;
use App\Domain\Core\Models\NotificationLog;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Actions\CreateQuotation;
use App\Domain\Platform\Actions\PayQuotation;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\SubscriptionPlan;
use App\Domain\Platform\Models\Tenant;
use App\Mail\NotificationMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

function emailsFor(int $tenantId, ?string $referenceType = null)
{
    return NotificationLog::withoutGlobalScope(TenantScope::class)
        ->where('tenant_id', $tenantId)
        ->where('channel', 'email')
        ->when($referenceType, fn ($q) => $q->where('reference_type', $referenceType))
        ->get();
}

test('creating a quotation for a pending tenant emails the owner, with UPI payment details when configured', function () {
    config(['platform.upi_vpa' => 'nexbiz@okicici', 'platform.upi_payee_name' => 'NexBiz Technology']);

    $owner = onboard(['activated' => false]);
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $quotation = app(CreateQuotation::class)->execute(Tenant::findOrFail($owner->tenant_id), $plan, createdBy: null);

    $emails = emailsFor($owner->tenant_id, 'Quotation');

    expect($emails)->toHaveCount(1);
    $email = $emails->first();
    expect($email->to_address)->toBe($owner->email);
    expect($email->status)->toBe('sent');
    expect($email->subject)->toContain($quotation->quotation_number);
    expect($email->body)->toContain('nexbiz@okicici');
    expect($email->body)->toContain(number_format((float) $quotation->total_amount, 2));
    // A never-paid tenant cannot log in, so the email must not tell them
    // to pay from inside the app.
    expect($email->body)->toContain('activated as soon as your payment is received');
    expect($email->body)->not->toContain('/billing/quotations/');
});

test('a pending tenant without a configured UPI ID is told to contact us instead of being shown a blank', function () {
    config(['platform.upi_vpa' => null]);

    $owner = onboard(['activated' => false]);
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $quotation = app(CreateQuotation::class)->execute(Tenant::findOrFail($owner->tenant_id), $plan, createdBy: null);

    $email = emailsFor($owner->tenant_id, 'Quotation')->first();

    expect($email->body)->toContain('please contact us');
    expect($email->body)->toContain($quotation->quotation_number);
});

test('an already-active tenant is pointed at in-app billing for a renewal quotation', function () {
    $owner = onboard();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $quotation = app(CreateQuotation::class)->execute(Tenant::findOrFail($owner->tenant_id), $plan, createdBy: null);

    $email = emailsFor($owner->tenant_id, 'Quotation')->first();

    expect($email->body)->toContain("/billing/quotations/{$quotation->id}");
});

test('the in-app notification is still created alongside the email', function () {
    $owner = onboard(['activated' => false]);
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    app(CreateQuotation::class)->execute(Tenant::findOrFail($owner->tenant_id), $plan, createdBy: null);

    $inApp = NotificationLog::withoutGlobalScope(TenantScope::class)
        ->where('tenant_id', $owner->tenant_id)->where('channel', 'in_app')->where('subject', 'Billing update')->count();

    expect($inApp)->toBe(1);
});

test('the first payment emails the owner that their account is active, with a login link', function () {
    $owner = onboard(['activated' => false]);
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $quotation = app(CreateQuotation::class)->execute(Tenant::findOrFail($owner->tenant_id), $plan, createdBy: null);

    $invoice = app(PayQuotation::class)->execute($quotation, 'bank_transfer', 'UTR1');

    $email = emailsFor($owner->tenant_id, 'PlatformInvoice')->first();

    expect($email)->not->toBeNull();
    expect($email->subject)->toContain($invoice->invoice_number);
    expect($email->body)->toContain('Your account is now active');
    expect($email->body)->toContain(route('login'));
    expect($email->body)->toContain('Active until:');
});

test('a renewal payment emails a receipt pointing at invoices, not a first-activation message', function () {
    $owner = onboard();
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();
    $quotation = app(CreateQuotation::class)->execute(Tenant::findOrFail($owner->tenant_id), $plan, createdBy: null);

    app(PayQuotation::class)->execute($quotation, 'upi', 'UTR2');

    $email = emailsFor($owner->tenant_id, 'PlatformInvoice')->first();

    expect($email->body)->not->toContain('Your account is now active');
    expect($email->body)->toContain(route('billing.invoices.index'));
});

test('renewal reminders also go out as an email, not only in-app', function () {
    $owner = onboard(['subscription_ends_at' => now()->addDays(5)]);

    $this->artisan('subscriptions:process-renewals')->assertSuccessful();

    $emails = emailsFor($owner->tenant_id, 'TenantSubscription');

    expect($emails)->toHaveCount(1);
    expect($emails->first()->to_address)->toBe($owner->email);
    expect($emails->first()->body)->toContain(route('billing.quotations.index'));
});

test('rerunning the renewals command the same day does not send a second reminder email', function () {
    $owner = onboard(['subscription_ends_at' => now()->addDays(5)]);

    $this->artisan('subscriptions:process-renewals')->assertSuccessful();
    $this->artisan('subscriptions:process-renewals')->assertSuccessful();

    expect(emailsFor($owner->tenant_id, 'TenantSubscription'))->toHaveCount(1);
});

test('a tenant\'s billing emails only go to users allowed to manage billing', function () {
    $owner = onboard(['activated' => false]);
    $plan = SubscriptionPlan::where('code', 'growth')->firstOrFail();

    // A second user with no roles at all — must not receive billing mail.
    $staff = new User(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'password123', 'is_active' => true]);
    $staff->tenant_id = $owner->tenant_id;
    $staff->save();

    app(CreateQuotation::class)->execute(Tenant::findOrFail($owner->tenant_id), $plan, createdBy: null);

    $recipients = emailsFor($owner->tenant_id, 'Quotation')->pluck('to_address')->all();

    expect($recipients)->toBe([$owner->email]);
});

test('self-signup emails the new owner a confirmation and alerts every active Super Admin', function () {
    Mail::fake();

    $adminA = PlatformAdmin::factory()->create(['is_active' => true]);
    $adminB = PlatformAdmin::factory()->create(['is_active' => true]);
    $inactive = PlatformAdmin::factory()->create(['is_active' => false]);

    $this->post('/register', [
        'business_name' => 'Glow Salon',
        'modules' => ['salon'],
        'owner_name' => 'Alice Owner',
        'owner_email' => 'alice@glow.test',
        'owner_password' => 'password123',
        'owner_password_confirmation' => 'password123',
        'billing_state' => 'Haryana',
    ])->assertRedirect(route('login'));

    $tenant = Tenant::where('name', 'Glow Salon')->firstOrFail();

    $confirmation = emailsFor($tenant->id)->first();
    expect($confirmation->to_address)->toBe('alice@glow.test');
    expect($confirmation->subject)->toContain('received your registration');

    Mail::assertQueued(NotificationMail::class, function (NotificationMail $mail) use ($adminA, $adminB, $inactive) {
        return $mail->hasTo($adminA->email)
            && $mail->hasTo($adminB->email)
            && ! $mail->hasTo($inactive->email)
            && str_contains($mail->mailSubject, 'New signup: Glow Salon');
    });
});

test('a failure to send signup notifications never breaks the signup itself', function () {
    $this->app->bind(SendNotification::class, fn () => throw new RuntimeException('mail system is down'));

    $this->post('/register', [
        'business_name' => 'Resilient Salon',
        'modules' => ['salon'],
        'owner_name' => 'Bob Owner',
        'owner_email' => 'bob@resilient.test',
        'owner_password' => 'password123',
        'owner_password_confirmation' => 'password123',
        'billing_state' => 'Haryana',
    ])->assertRedirect(route('login'))->assertSessionHas('status');

    expect(Tenant::where('name', 'Resilient Salon')->exists())->toBeTrue();
});
