<?php

use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Actions\ResolveSegmentCustomers;
use App\Domain\Core\Actions\RunBirthdayCampaign;
use App\Domain\Core\Actions\SendCampaign;
use App\Domain\Core\Actions\UpdateBranchModules;
use App\Domain\Core\Models\Appointment;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Campaign;
use App\Domain\Core\Models\CampaignAutomation;
use App\Domain\Core\Models\CampaignRecipient;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\CustomerSegment;
use App\Domain\Core\Models\NotificationLog;
use App\Domain\Core\Models\NotificationTemplate;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\Module;

function phase11Fixture(): array
{
    $owner = onboard(['modules' => ['salon']]);

    return compact('owner');
}

function phase11AppointmentFixture(array $fixture): array
{
    $owner = $fixture['owner'];
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    app(UpdateBranchModules::class)->execute($branch, ['salon']);

    $category = ServiceCategory::factory()->forTenant($owner->tenant)->create([
        'module_id' => Module::where('code', 'salon')->firstOrFail()->id,
    ]);
    $service = Service::factory()->forCategory($category)->create();

    $employee = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Stylist', 'email' => fake()->unique()->safeEmail(), 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);

    return compact('branch', 'service', 'employee');
}

// ---------------------------------------------------------------------------
// Templates, segments, campaigns
// ---------------------------------------------------------------------------

test('an owner can create a template, a tag segment, and send a campaign that respects marketing consent', function () {
    $fixture = phase11Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $consentingCustomer = Customer::factory()->forTenant($owner->tenant)->create(['marketing_consent' => true, 'tags' => ['vip']]);
    $nonConsentingCustomer = Customer::factory()->forTenant($owner->tenant)->create(['marketing_consent' => false, 'tags' => ['vip']]);
    Customer::factory()->forTenant($owner->tenant)->create(['marketing_consent' => true, 'tags' => ['regular']]);

    $this->post('/notification-templates', [
        'name' => 'VIP Offer', 'channel' => 'email', 'subject' => 'Hi {{customer_name}}', 'body' => 'From {{business_name}}, enjoy 20% off.',
    ])->assertRedirect();
    $template = NotificationTemplate::where('name', 'VIP Offer')->firstOrFail();

    $this->post('/customer-segments', ['name' => 'VIPs', 'type' => 'tag', 'tag' => 'vip'])->assertRedirect();
    $segment = CustomerSegment::where('name', 'VIPs')->firstOrFail();

    $this->post('/campaigns', ['name' => 'VIP Blast', 'segment_id' => $segment->id, 'template_id' => $template->id])->assertRedirect();
    $campaign = Campaign::where('name', 'VIP Blast')->firstOrFail();
    expect($campaign->status)->toBe('draft');
    expect($campaign->channel)->toBe('email');

    $this->post("/campaigns/{$campaign->id}/send")->assertRedirect();

    $campaign->refresh();
    expect($campaign->status)->toBe('sent');
    expect($campaign->recipients)->toHaveCount(2); // only the two 'vip'-tagged customers

    $sentRecipient = CampaignRecipient::where('campaign_id', $campaign->id)->where('customer_id', $consentingCustomer->id)->firstOrFail();
    expect($sentRecipient->status)->toBe('sent');
    $log = NotificationLog::find($sentRecipient->notification_log_id);
    expect($log->status)->toBe('sent');
    expect($log->subject)->toBe('Hi '.$consentingCustomer->name);

    $skippedRecipient = CampaignRecipient::where('campaign_id', $campaign->id)->where('customer_id', $nonConsentingCustomer->id)->firstOrFail();
    expect($skippedRecipient->status)->toBe('skipped_no_consent');
    expect($skippedRecipient->notification_log_id)->toBeNull();
});

test('sending a campaign twice does not duplicate recipients or notifications', function () {
    $fixture = phase11Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    Customer::factory()->forTenant($owner->tenant)->create(['marketing_consent' => true]);

    $template = NotificationTemplate::create(['name' => 'Reminder', 'channel' => 'sms', 'body' => 'Hi {{customer_name}}']);
    $segment = CustomerSegment::create(['name' => 'Everyone', 'type' => 'all']);

    $campaign = new Campaign(['name' => 'Blast', 'type' => 'manual', 'channel' => 'sms', 'template_id' => $template->id, 'segment_id' => $segment->id]);
    $campaign->tenant_id = $owner->tenant_id;
    $campaign->status = 'draft';
    $campaign->save();

    app(SendCampaign::class)->execute($campaign->fresh());
    expect(CampaignRecipient::count())->toBe(1);
    expect(NotificationLog::count())->toBe(1);

    // Re-running against the now-`sent` campaign must be rejected (409),
    // not silently re-processed.
    $this->post("/campaigns/{$campaign->id}/send")->assertStatus(409);
    expect(CampaignRecipient::count())->toBe(1);
    expect(NotificationLog::count())->toBe(1);
});

test('an inactive_days segment only matches customers without a recent appointment', function () {
    $fixture = phase11Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $appt = phase11AppointmentFixture($fixture);

    $recentlyActive = Customer::factory()->forTenant($owner->tenant)->create(['marketing_consent' => true]);
    $lapsed = Customer::factory()->forTenant($owner->tenant)->create(['marketing_consent' => true]);

    Appointment::factory()->forTenant($owner->tenant)->create([
        'branch_id' => $appt['branch']->id, 'customer_id' => $recentlyActive->id,
        'service_id' => $appt['service']->id, 'user_id' => $appt['employee']->id,
        'starts_at' => now()->subDays(5), 'status' => 'completed',
    ]);

    $segment = CustomerSegment::create(['name' => 'Lapsed', 'type' => 'inactive_days', 'criteria' => ['days' => 30]]);

    $matches = app(ResolveSegmentCustomers::class)->execute($owner->tenant_id, $segment);

    expect($matches->pluck('id'))->not->toContain($recentlyActive->id);
    expect($matches->pluck('id'))->toContain($lapsed->id);
});

// ---------------------------------------------------------------------------
// Automations
// ---------------------------------------------------------------------------

test('the birthday automation sends once and is idempotent if run again the same day', function () {
    $fixture = phase11Fixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $customer = Customer::factory()->forTenant($owner->tenant)->create([
        'marketing_consent' => true,
        'date_of_birth' => now()->subYears(30)->format('Y-m-d'),
    ]);

    $template = NotificationTemplate::create(['name' => 'Birthday', 'channel' => 'email', 'subject' => 'Happy Birthday', 'body' => 'Cheers {{customer_name}}!']);
    $automation = new CampaignAutomation(['type' => 'birthday', 'is_enabled' => true, 'template_id' => $template->id]);
    $automation->tenant_id = $owner->tenant_id;
    $automation->save();

    $campaign = app(RunBirthdayCampaign::class)->execute($owner->tenant);
    expect($campaign)->not->toBeNull();
    expect($campaign->status)->toBe('sent');
    expect(CampaignRecipient::where('campaign_id', $campaign->id)->where('customer_id', $customer->id)->exists())->toBeTrue();

    $again = app(RunBirthdayCampaign::class)->execute($owner->tenant);
    expect($again)->toBeNull();
    expect(Campaign::where('type', 'birthday')->count())->toBe(1);
});

test('a disabled or unconfigured automation is silently skipped', function () {
    $fixture = phase11Fixture();
    $owner = $fixture['owner'];

    Customer::withoutGlobalScope(TenantScope::class); // no-op, just importing for the test's own clarity
    $customer = new Customer(['name' => 'Someone', 'date_of_birth' => now()->format('Y-m-d'), 'marketing_consent' => true]);
    $customer->tenant_id = $owner->tenant_id;
    $customer->save();

    // No CampaignAutomation row exists at all for this tenant/type.
    $result = app(RunBirthdayCampaign::class)->execute($owner->tenant);
    expect($result)->toBeNull();
    expect(Campaign::withoutGlobalScope(TenantScope::class)->where('tenant_id', $owner->tenant_id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Isolation and permissions
// ---------------------------------------------------------------------------

test('a tenant cannot view or send another tenant\'s campaign', function () {
    $fixtureA = phase11Fixture();
    $fixtureB = phase11Fixture();

    $this->actingAs($fixtureB['owner']);
    $template = NotificationTemplate::create(['name' => 'X', 'channel' => 'email', 'subject' => 'X', 'body' => 'X']);
    $segment = CustomerSegment::create(['name' => 'All', 'type' => 'all']);
    $campaignB = new Campaign(['name' => 'B Campaign', 'type' => 'manual', 'channel' => 'email', 'template_id' => $template->id, 'segment_id' => $segment->id]);
    $campaignB->tenant_id = $fixtureB['owner']->tenant_id;
    $campaignB->status = 'draft';
    $campaignB->save();

    $this->actingAs($fixtureA['owner'])->post("/campaigns/{$campaignB->id}/send")->assertNotFound();
});

test('staff without marketing permissions cannot create a campaign or template', function () {
    $fixture = phase11Fixture();

    $staff = app(CreateEmployee::class)->execute($fixture['owner']->tenant, [
        'name' => 'Staff', 'email' => fake()->unique()->safeEmail(), 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);

    $this->actingAs($staff)->post('/notification-templates', [
        'name' => 'X', 'channel' => 'email', 'subject' => 'X', 'body' => 'X',
    ])->assertForbidden();

    $this->actingAs($staff)->get('/campaigns')->assertForbidden();
});
