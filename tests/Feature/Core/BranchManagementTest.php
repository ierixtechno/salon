<?php

use App\Domain\Core\Models\AuditLog;
use App\Domain\Core\Models\Branch;
use App\Domain\Platform\Models\Module;

beforeEach(function () {
    Module::firstOrCreate(['code' => 'salon'], ['name' => 'Salon']);
    Module::firstOrCreate(['code' => 'beauty'], ['name' => 'Beauty Parlour']);
});

test('an owner can create and edit a branch', function () {
    $owner = onboard();

    $this->actingAs($owner)
        ->post('/branches', ['name' => 'Main Branch', 'code' => 'MAIN'])
        ->assertRedirect();

    $branch = Branch::where('code', 'MAIN')->firstOrFail();
    expect($branch->tenant_id)->toBe($owner->tenant_id);

    $this->actingAs($owner)
        ->put("/branches/{$branch->id}", ['name' => 'Main Branch Updated', 'code' => 'MAIN'])
        ->assertRedirect();

    expect($branch->fresh()->name)->toBe('Main Branch Updated');
});

test('a branch cannot enable a module the tenant does not have', function () {
    $owner = onboard(['modules' => ['salon']]); // beauty NOT enabled for this tenant
    $branch = Branch::factory()->forTenant($owner->tenant)->create();

    $response = $this->actingAs($owner)
        ->put("/branches/{$branch->id}/modules", ['modules' => ['beauty']]);

    $response->assertSessionHasErrors('modules.0');
    expect($branch->hasModuleEnabled('beauty'))->toBeFalse();
});

test('a branch can enable a module the tenant does have', function () {
    $owner = onboard(['modules' => ['salon']]);
    $branch = Branch::factory()->forTenant($owner->tenant)->create();

    $this->actingAs($owner)
        ->put("/branches/{$branch->id}/modules", ['modules' => ['salon']])
        ->assertRedirect();

    expect($branch->hasModuleEnabled('salon'))->toBeTrue();

    // Branch module changes are audited (CLAUDE.md §47).
    expect(AuditLog::where('action', 'branch.modules_changed')
        ->where('entity_id', $branch->id)->exists())->toBeTrue();
});

test('branch codes are unique per tenant but may repeat across tenants', function () {
    $ownerA = onboard();
    $ownerB = onboard();

    Branch::factory()->forTenant($ownerA->tenant)->create(['code' => 'MAIN']);

    // Same code, different tenant — must succeed.
    $this->actingAs($ownerB)
        ->post('/branches', ['name' => 'Main', 'code' => 'MAIN'])
        ->assertRedirect();

    // Same code, same tenant — must fail validation.
    $this->actingAs($ownerA)
        ->post('/branches', ['name' => 'Main Again', 'code' => 'MAIN'])
        ->assertSessionHasErrors('code');
});

/**
 * Mandatory pattern per CLAUDE.md §62.
 */
test('a user from tenant B cannot view or edit a branch belonging to tenant A', function () {
    $ownerA = onboard();
    $ownerB = onboard();
    $branch = Branch::factory()->forTenant($ownerA->tenant)->create();

    $this->actingAs($ownerB)->get("/branches/{$branch->id}/edit")->assertNotFound();
    $this->actingAs($ownerB)->put("/branches/{$branch->id}", ['name' => 'Hijacked', 'code' => 'HIJACK'])->assertNotFound();
    $this->actingAs($ownerB)->delete("/branches/{$branch->id}")->assertNotFound();

    expect($branch->fresh()->name)->not->toBe('Hijacked');
});

test('deactivating a branch keeps the record but marks it inactive', function () {
    $owner = onboard();
    $branch = Branch::factory()->forTenant($owner->tenant)->create();

    $this->actingAs($owner)->delete("/branches/{$branch->id}")->assertRedirect();

    expect($branch->fresh())->not->toBeNull();
    expect($branch->fresh()->is_active)->toBeFalse();
});
