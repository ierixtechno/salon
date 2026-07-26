<?php

use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Resource;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\Module;

beforeEach(function () {
    Module::firstOrCreate(['code' => 'salon'], ['name' => 'Salon']);
});

test('an owner can add and edit a resource for their branch', function () {
    $owner = onboard();
    $branch = Branch::factory()->forTenant($owner->tenant)->create();

    $this->actingAs($owner)
        ->post("/branches/{$branch->id}/resources", ['type' => 'chair', 'name' => 'Chair 1', 'capacity' => 1])
        ->assertRedirect();

    $resource = Resource::where('branch_id', $branch->id)->firstOrFail();
    expect($resource->name)->toBe('Chair 1');

    $this->actingAs($owner)
        ->put("/resources/{$resource->id}", ['type' => 'chair', 'name' => 'Chair 1 Renamed', 'capacity' => 1, 'is_active' => 1])
        ->assertRedirect();

    expect($resource->fresh()->name)->toBe('Chair 1 Renamed');
});

test('a user from tenant B cannot view, edit, or delete a resource belonging to tenant A', function () {
    $ownerA = onboard();
    $ownerB = onboard();
    $branch = Branch::factory()->forTenant($ownerA->tenant)->create();
    $resource = Resource::factory()->for($branch)->forTenant($ownerA->tenant)->create();

    $this->actingAs($ownerB)->get("/branches/{$branch->id}/resources")->assertNotFound();
    $this->actingAs($ownerB)->get("/resources/{$resource->id}/edit")->assertNotFound();
    $this->actingAs($ownerB)
        ->put("/resources/{$resource->id}", ['type' => 'chair', 'name' => 'Hijacked', 'capacity' => 1])
        ->assertNotFound();
    $this->actingAs($ownerB)->delete("/resources/{$resource->id}")->assertNotFound();

    expect($resource->fresh()->name)->not->toBe('Hijacked');
    expect(Resource::withoutGlobalScope(TenantScope::class)->find($resource->id))->not->toBeNull();
});

test('an invalid resource type is rejected', function () {
    $owner = onboard();
    $branch = Branch::factory()->forTenant($owner->tenant)->create();

    $this->actingAs($owner)
        ->post("/branches/{$branch->id}/resources", ['type' => 'not-a-real-type', 'name' => 'X', 'capacity' => 1])
        ->assertSessionHasErrors('type');
});
