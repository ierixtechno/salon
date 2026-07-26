<?php

use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Actions\UpdateBranchModules;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\Module;

beforeEach(function () {
    Module::firstOrCreate(['code' => 'salon'], ['name' => 'Salon']);
    Module::firstOrCreate(['code' => 'beauty'], ['name' => 'Beauty Parlour']);
});

function salonModuleId(): int
{
    return Module::where('code', 'salon')->firstOrFail()->id;
}

test('an owner can create a category and a service in it', function () {
    $owner = onboard(['modules' => ['salon']]);

    $this->actingAs($owner)->post('/service-categories', [
        'module_id' => salonModuleId(),
        'name' => 'Hair Treatments',
    ])->assertRedirect();

    $category = ServiceCategory::where('name', 'Hair Treatments')->firstOrFail();
    expect($category->tenant_id)->toBe($owner->tenant_id);

    $this->actingAs($owner)->post('/services', [
        'service_category_id' => $category->id,
        'name' => 'Haircut',
        'duration_minutes' => 45,
        'base_price' => 500,
    ])->assertRedirect();

    $service = Service::where('name', 'Haircut')->firstOrFail();
    expect($service->module_id)->toBe($category->module_id);
    expect((float) $service->base_price)->toBe(500.0);
});

test('a category cannot use a module the tenant does not have enabled', function () {
    $owner = onboard(['modules' => ['salon']]); // beauty NOT enabled

    $this->actingAs($owner)->post('/service-categories', [
        'module_id' => Module::where('code', 'beauty')->firstOrFail()->id,
        'name' => 'Facials',
    ])->assertSessionHasErrors('module_id');
});

test('a service cannot be made available at a branch missing the service module', function () {
    $owner = onboard(['modules' => ['salon']]);
    $category = ServiceCategory::factory()->forTenant($owner->tenant)->create(['module_id' => salonModuleId()]);
    $service = Service::factory()->forCategory($category)->create();

    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    // Branch has no modules enabled at all.

    $response = $this->actingAs($owner)->put("/services/{$service->id}/branches", [
        'branches' => [['branch_id' => $branch->id, 'is_available' => '1']],
    ]);

    $response->assertSessionHasErrors('branches.0.is_available');
    expect($service->isAvailableAtBranch($branch))->toBeFalse();
});

test('a service can be made available at a branch with the matching module, with a price override', function () {
    $owner = onboard(['modules' => ['salon']]);
    $category = ServiceCategory::factory()->forTenant($owner->tenant)->create(['module_id' => salonModuleId()]);
    $service = Service::factory()->forCategory($category)->create(['base_price' => 500]);

    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    app(UpdateBranchModules::class)->execute($branch, ['salon']);

    $this->actingAs($owner)->put("/services/{$service->id}/branches", [
        'branches' => [['branch_id' => $branch->id, 'is_available' => '1', 'price_override' => '450']],
    ])->assertRedirect();

    expect($service->isAvailableAtBranch($branch))->toBeTrue();
    expect($service->priceForBranch($branch))->toBe('450.00');
});

test('an owner can add, update, and remove service variants', function () {
    $owner = onboard(['modules' => ['salon']]);
    $category = ServiceCategory::factory()->forTenant($owner->tenant)->create(['module_id' => salonModuleId()]);
    $service = Service::factory()->forCategory($category)->create();

    $this->actingAs($owner)->put("/services/{$service->id}/variants", [
        'variants' => [
            ['name' => 'Short hair', 'price' => 400],
            ['name' => 'Long hair', 'price' => 600],
        ],
    ])->assertRedirect();

    expect($service->variants()->count())->toBe(2);

    $keepVariant = $service->variants()->where('name', 'Short hair')->firstOrFail();

    // Resubmitting with only one variant (by id) removes the other.
    $this->actingAs($owner)->put("/services/{$service->id}/variants", [
        'variants' => [
            ['id' => $keepVariant->id, 'name' => 'Short hair', 'price' => 420],
        ],
    ])->assertRedirect();

    expect($service->variants()->count())->toBe(1);
    expect($keepVariant->fresh()->price)->toBe('420.00');
});

test('an owner can assign staff capability for a service', function () {
    $owner = onboard(['modules' => ['salon']]);
    $category = ServiceCategory::factory()->forTenant($owner->tenant)->create(['module_id' => salonModuleId()]);
    $service = Service::factory()->forCategory($category)->create();

    $employee = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Cara', 'email' => 'cara@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);

    $this->actingAs($owner)->put("/services/{$service->id}/staff", [
        'user_ids' => [$employee->id],
    ])->assertRedirect();

    expect($service->capableEmployees()->pluck('users.id')->all())->toBe([$employee->id]);
});

test('service names are unique per tenant but may repeat across tenants', function () {
    $ownerA = onboard(['modules' => ['salon']]);
    $ownerB = onboard(['modules' => ['salon']]);

    $categoryA = ServiceCategory::factory()->forTenant($ownerA->tenant)->create(['module_id' => salonModuleId()]);
    $categoryB = ServiceCategory::factory()->forTenant($ownerB->tenant)->create(['module_id' => salonModuleId()]);

    Service::factory()->forCategory($categoryA)->create(['name' => 'Haircut']);

    // Same name, different tenant — must succeed.
    $this->actingAs($ownerB)->post('/services', [
        'service_category_id' => $categoryB->id,
        'name' => 'Haircut',
        'duration_minutes' => 30,
        'base_price' => 300,
    ])->assertRedirect();

    // Same name, same tenant — must fail.
    $this->actingAs($ownerA)->post('/services', [
        'service_category_id' => $categoryA->id,
        'name' => 'Haircut',
        'duration_minutes' => 30,
        'base_price' => 300,
    ])->assertSessionHasErrors('name');
});

/**
 * Mandatory pattern per CLAUDE.md §62.
 */
test('a user from tenant B cannot view or edit a service or category belonging to tenant A', function () {
    $ownerA = onboard(['modules' => ['salon']]);
    $ownerB = onboard(['modules' => ['salon']]);

    $category = ServiceCategory::factory()->forTenant($ownerA->tenant)->create(['module_id' => salonModuleId()]);
    $service = Service::factory()->forCategory($category)->create();

    $this->actingAs($ownerB)->get("/service-categories/{$category->id}/edit")->assertNotFound();
    $this->actingAs($ownerB)->put("/service-categories/{$category->id}", ['name' => 'Hijacked'])->assertNotFound();

    $this->actingAs($ownerB)->get("/services/{$service->id}/edit")->assertNotFound();
    $this->actingAs($ownerB)->put("/services/{$service->id}", [
        'service_category_id' => $category->id, 'name' => 'Hijacked', 'duration_minutes' => 10, 'base_price' => 1,
    ])->assertNotFound();

    expect($service->fresh()->name)->not->toBe('Hijacked');
    expect(ServiceCategory::withoutGlobalScope(TenantScope::class)->find($category->id)->name)->not->toBe('Hijacked');
});

test('deactivating a service keeps the record but marks it inactive', function () {
    $owner = onboard(['modules' => ['salon']]);
    $category = ServiceCategory::factory()->forTenant($owner->tenant)->create(['module_id' => salonModuleId()]);
    $service = Service::factory()->forCategory($category)->create();

    $this->actingAs($owner)->delete("/services/{$service->id}")->assertRedirect();

    expect($service->fresh())->not->toBeNull();
    expect($service->fresh()->is_active)->toBeFalse();
});
