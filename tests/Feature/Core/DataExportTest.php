<?php

use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\DataExport;
use App\Domain\Core\Scopes\TenantScope;

test('an owner can request a data export and it completes with a downloadable ZIP of their own tenant\'s data', function () {
    $owner = onboard(['modules' => ['salon']]);
    $this->actingAs($owner);

    Customer::factory()->forTenant($owner->tenant)->create(['name' => 'Export Test Customer']);

    $this->post('/data-exports')->assertRedirect();

    $export = DataExport::withoutGlobalScope(TenantScope::class)->where('tenant_id', $owner->tenant_id)->firstOrFail();
    expect($export->status)->toBe('completed');
    expect($export->file_path)->not->toBeNull();
    expect($export->requested_by)->toBe($owner->id);

    $response = $this->get(route('data-exports.download', $export));
    $response->assertOk();

    $tmpZip = tempnam(sys_get_temp_dir(), 'export_test_').'.zip';
    file_put_contents($tmpZip, $response->streamedContent());

    $zip = new ZipArchive;
    $zip->open($tmpZip);
    expect($zip->locateName('customers.csv'))->not->toBeFalse();
    expect($zip->locateName('appointments.csv'))->not->toBeFalse();
    expect($zip->locateName('invoices.csv'))->not->toBeFalse();
    expect($zip->getFromName('customers.csv'))->toContain('Export Test Customer');
    $zip->close();
    unlink($tmpZip);
});

test('a manager cannot request a data export', function () {
    $owner = onboard(['modules' => ['salon']]);

    $manager = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Manager', 'email' => fake()->unique()->safeEmail(), 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Manager', 'all_branches' => true, 'branches' => [],
    ]);

    $this->actingAs($manager)->post('/data-exports')->assertForbidden();
});

test('a tenant cannot download another tenant\'s export', function () {
    $ownerA = onboard(['modules' => ['salon']]);
    $ownerB = onboard(['modules' => ['salon']]);

    $this->actingAs($ownerA)->post('/data-exports');
    $exportA = DataExport::withoutGlobalScope(TenantScope::class)->where('tenant_id', $ownerA->tenant_id)->firstOrFail();

    $this->actingAs($ownerB)->get(route('data-exports.download', $exportA))->assertNotFound();
});

test('an expired export is no longer downloadable', function () {
    $owner = onboard(['modules' => ['salon']]);
    $this->actingAs($owner);

    $this->post('/data-exports');
    $export = DataExport::withoutGlobalScope(TenantScope::class)->where('tenant_id', $owner->tenant_id)->firstOrFail();
    expect($export->status)->toBe('completed');

    $export->expires_at = now()->subDay();
    $export->save();

    $this->get(route('data-exports.download', $export))->assertNotFound();
});
