<?php

use App\Domain\Core\Actions\EraseCustomer;
use App\Domain\Core\Actions\UpdateBranchModules;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Salon\Models\HairConsultation;
use App\Domain\Salon\Models\HairProfile;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

test('an owner can set a hair profile and record a hair consultation for a customer', function () {
    $owner = onboard(['modules' => ['salon']]);
    $customer = Customer::factory()->forTenant($owner->tenant)->create();
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    app(UpdateBranchModules::class)->execute($branch, ['salon']);

    $this->actingAs($owner)->put("/customers/{$customer->id}/salon/profile", [
        'hair_type' => 'Wavy',
        'scalp_type' => 'Oily',
        'allergies' => 'None known',
    ])->assertRedirect();

    expect($customer->fresh())->not->toBeNull();
    $this->actingAs($owner)->get("/customers/{$customer->id}/salon/profile/edit")->assertOk()->assertSee('Wavy');

    $this->actingAs($owner)->post("/customers/{$customer->id}/salon/consultations", [
        'branch_id' => $branch->id,
        'consultation_date' => now()->toDateString(),
        'concerns' => 'Dry ends',
        'recommendation' => 'Hair spa',
        'color_formula' => '6.0 + 20vol',
    ])->assertRedirect();

    $this->actingAs($owner)->get("/customers/{$customer->id}/salon/consultations")
        ->assertOk()
        ->assertSee('Dry ends')
        ->assertSee('6.0 + 20vol');
});

test('a hair consultation is rejected when the chosen branch does not have the salon module enabled', function () {
    $owner = onboard(['modules' => ['salon']]);
    $customer = Customer::factory()->forTenant($owner->tenant)->create();
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    // Branch has no modules enabled.

    $this->actingAs($owner)->post("/customers/{$customer->id}/salon/consultations", [
        'branch_id' => $branch->id,
        'consultation_date' => now()->toDateString(),
    ])->assertSessionHasErrors('branch_id');
});

test('salon consultation routes are inaccessible when the tenant does not have the salon module enabled', function () {
    $owner = onboard(['modules' => ['beauty']]);
    $customer = Customer::factory()->forTenant($owner->tenant)->create();

    $this->actingAs($owner)->get("/customers/{$customer->id}/salon/profile/edit")->assertForbidden();
    $this->actingAs($owner)->get("/customers/{$customer->id}/salon/consultations")->assertForbidden();
});

test('an owner can set a skin profile and record a skin consultation for a customer', function () {
    $owner = onboard(['modules' => ['beauty']]);
    $customer = Customer::factory()->forTenant($owner->tenant)->create();
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    app(UpdateBranchModules::class)->execute($branch, ['beauty']);

    $this->actingAs($owner)->put("/customers/{$customer->id}/beauty/profile", [
        'skin_type' => 'Combination',
    ])->assertRedirect();

    $this->actingAs($owner)->post("/customers/{$customer->id}/beauty/consultations", [
        'branch_id' => $branch->id,
        'consultation_date' => now()->toDateString(),
        'concerns' => 'Acne prone',
        'treatment_plan' => '4-session facial course',
    ])->assertRedirect();

    $this->actingAs($owner)->get("/customers/{$customer->id}/beauty/consultations")
        ->assertOk()
        ->assertSee('Acne prone')
        ->assertSee('4-session facial course');
});

test('an owner can set a spa profile and record a spa consultation for a customer', function () {
    $owner = onboard(['modules' => ['spa']]);
    $customer = Customer::factory()->forTenant($owner->tenant)->create();
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    app(UpdateBranchModules::class)->execute($branch, ['spa']);

    $this->actingAs($owner)->put("/customers/{$customer->id}/spa/profile", [
        'health_conditions' => 'Mild lower back pain',
        'pressure_preference' => 'Light',
    ])->assertRedirect();

    $this->actingAs($owner)->post("/customers/{$customer->id}/spa/consultations", [
        'branch_id' => $branch->id,
        'consultation_date' => now()->toDateString(),
        'concerns' => 'Lower back tension',
    ])->assertRedirect();

    $this->actingAs($owner)->get("/customers/{$customer->id}/spa/consultations")
        ->assertOk()
        ->assertSee('Lower back tension');
});

test('a staff member cannot update a hair profile without the update permission', function () {
    $owner = onboard(['modules' => ['salon']]);
    $customer = Customer::factory()->forTenant($owner->tenant)->create();

    $staff = User::factory()->forTenant($owner->tenant)->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($owner->tenant_id);
    $staff->assignRole('Staff');

    // Staff has salon-consultations.create but not .update.
    $this->actingAs($staff)->put("/customers/{$customer->id}/salon/profile", [
        'hair_type' => 'Straight',
    ])->assertForbidden();

    // Staff CAN create a consultation entry.
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    app(UpdateBranchModules::class)->execute($branch, ['salon']);

    $this->actingAs($staff)->post("/customers/{$customer->id}/salon/consultations", [
        'branch_id' => $branch->id,
        'consultation_date' => now()->toDateString(),
    ])->assertRedirect();
});

/**
 * Mandatory pattern per CLAUDE.md §62.
 */
test('a user from tenant B cannot view or create consultations for a customer belonging to tenant A', function () {
    $ownerA = onboard(['modules' => ['salon']]);
    $ownerB = onboard(['modules' => ['salon']]);

    $customer = Customer::factory()->forTenant($ownerA->tenant)->create();

    $this->actingAs($ownerB)->get("/customers/{$customer->id}/salon/profile/edit")->assertNotFound();
    $this->actingAs($ownerB)->get("/customers/{$customer->id}/salon/consultations")->assertNotFound();
    $this->actingAs($ownerB)->put("/customers/{$customer->id}/salon/profile", ['hair_type' => 'Hijacked'])->assertNotFound();

    expect(HairProfile::withoutGlobalScope(TenantScope::class)->where('customer_id', $customer->id)->exists())->toBeFalse();
});

test('erasing a customer purges their vertical consultation profiles and history', function () {
    $owner = onboard(['modules' => ['salon']]);
    $customer = Customer::factory()->forTenant($owner->tenant)->create();
    $branch = Branch::factory()->forTenant($owner->tenant)->create();
    app(UpdateBranchModules::class)->execute($branch, ['salon']);

    $this->actingAs($owner)->put("/customers/{$customer->id}/salon/profile", ['hair_type' => 'Curly'])->assertRedirect();
    $this->actingAs($owner)->post("/customers/{$customer->id}/salon/consultations", [
        'branch_id' => $branch->id,
        'consultation_date' => now()->toDateString(),
        'concerns' => 'Frizz',
    ])->assertRedirect();

    app(EraseCustomer::class)->execute($customer, $owner->id);

    expect(HairProfile::where('customer_id', $customer->id)->exists())->toBeFalse();
    expect(HairConsultation::where('customer_id', $customer->id)->exists())->toBeFalse();
});
