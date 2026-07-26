<?php

use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Actions\EraseCustomer;
use App\Domain\Core\Models\AuditLog;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\CustomerConsent;
use App\Domain\Core\Models\CustomerNote;
use App\Domain\Core\Scopes\TenantScope;

test('an owner can create and edit a customer', function () {
    $owner = onboard();

    $this->actingAs($owner)->post('/customers', [
        'name' => 'Priya Sharma',
        'phone' => '9999999999',
        'tags' => 'VIP, regular',
    ])->assertRedirect();

    $customer = Customer::where('phone', '9999999999')->firstOrFail();
    expect($customer->tenant_id)->toBe($owner->tenant_id);
    expect($customer->tags)->toBe(['VIP', 'regular']);

    $this->actingAs($owner)
        ->put("/customers/{$customer->id}", ['name' => 'Priya Sharma Verma', 'tags' => ''])
        ->assertRedirect();

    expect($customer->fresh()->name)->toBe('Priya Sharma Verma');
});

test('adding a note records who wrote it', function () {
    $owner = onboard();
    $customer = Customer::factory()->forTenant($owner->tenant)->create();

    $this->actingAs($owner)
        ->post("/customers/{$customer->id}/notes", ['body' => 'Prefers organic products.'])
        ->assertRedirect();

    $note = CustomerNote::where('customer_id', $customer->id)->firstOrFail();
    expect($note->body)->toBe('Prefers organic products.');
    expect($note->user_id)->toBe($owner->id);
});

test('recording consent creates a ledger entry and keeps a denormalized current-state flag in sync', function () {
    $owner = onboard();
    $customer = Customer::factory()->forTenant($owner->tenant)->create(['marketing_consent' => false]);

    $this->actingAs($owner)->post("/customers/{$customer->id}/consent", [
        'purpose' => 'marketing',
        'granted' => '1',
    ])->assertRedirect();

    expect($customer->fresh()->marketing_consent)->toBeTrue();
    expect(CustomerConsent::where('customer_id', $customer->id)->where('purpose', 'marketing')->where('granted', true)->exists())->toBeTrue();

    // Revoking creates a NEW ledger row rather than mutating the old one.
    $this->actingAs($owner)->post("/customers/{$customer->id}/consent", [
        'purpose' => 'marketing',
        'granted' => '0',
    ])->assertRedirect();

    expect($customer->fresh()->marketing_consent)->toBeFalse();
    expect(CustomerConsent::where('customer_id', $customer->id)->where('purpose', 'marketing')->count())->toBe(2);
});

test('erasing a customer anonymizes the row but never deletes it', function () {
    $owner = onboard();
    $customer = Customer::factory()->forTenant($owner->tenant)->create(['name' => 'Priya Sharma', 'phone' => '9999999999']);
    $this->actingAs($owner);
    $customer->notes()->create(['user_id' => $owner->id, 'body' => 'Sensitive note.']);

    $this->actingAs($owner)
        ->post("/customers/{$customer->id}/erase")
        ->assertRedirect();

    $fresh = $customer->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->name)->not->toBe('Priya Sharma');
    expect($fresh->phone)->toBeNull();
    expect($fresh->notes)->toBeEmpty();
    expect($fresh->erased_at)->not->toBeNull();
    expect(AuditLog::where('action', 'customer.erased')->where('entity_id', $customer->id)->exists())->toBeTrue();
});

test('an erased customer can still be viewed but can no longer be edited', function () {
    $owner = onboard();
    $customer = Customer::factory()->forTenant($owner->tenant)->create();
    app(EraseCustomer::class)->execute($customer, $owner->id);

    // Viewable — the edit page renders a read-only "erased" notice.
    $this->actingAs($owner)->get("/customers/{$customer->id}/edit")->assertOk();

    // Not a 403 (authorization) — a 409 business-rule conflict (CLAUDE.md
    // §39): the user is allowed to be here, the *state* forbids the write.
    $this->actingAs($owner)
        ->put("/customers/{$customer->id}", ['name' => 'Reconstructed'])
        ->assertStatus(409);

    expect($customer->fresh()->name)->not->toBe('Reconstructed');
});

test('only a role with customers.erase can erase a customer', function () {
    $owner = onboard();
    $customer = Customer::factory()->forTenant($owner->tenant)->create();

    $manager = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Manager Mia', 'email' => 'mia@glow.test', 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Manager', 'all_branches' => true, 'branches' => [],
    ]);

    // Manager can update but not erase (Manager's default permission set excludes customers.erase).
    $this->actingAs($manager)->post("/customers/{$customer->id}/erase")->assertForbidden();
    expect($customer->fresh()->isErased())->toBeFalse();
});

/**
 * Mandatory pattern per CLAUDE.md §62.
 */
test('a user from tenant B cannot view, edit, or erase a customer belonging to tenant A', function () {
    $ownerA = onboard();
    $ownerB = onboard();
    $customer = Customer::factory()->forTenant($ownerA->tenant)->create();

    $this->actingAs($ownerB)->get("/customers/{$customer->id}/edit")->assertNotFound();
    $this->actingAs($ownerB)->put("/customers/{$customer->id}", ['name' => 'Hijacked'])->assertNotFound();
    $this->actingAs($ownerB)->post("/customers/{$customer->id}/erase")->assertNotFound();
    $this->actingAs($ownerB)->post("/customers/{$customer->id}/notes", ['body' => 'leaked'])->assertNotFound();

    expect($customer->fresh()->name)->not->toBe('Hijacked');
    expect(Customer::withoutGlobalScope(TenantScope::class)->find($customer->id)->isErased())->toBeFalse();
});

test('deactivating a customer keeps the record but marks it inactive', function () {
    $owner = onboard();
    $customer = Customer::factory()->forTenant($owner->tenant)->create();

    $this->actingAs($owner)->delete("/customers/{$customer->id}")->assertRedirect();

    expect($customer->fresh())->not->toBeNull();
    expect($customer->fresh()->is_active)->toBeFalse();
    expect($customer->fresh()->isErased())->toBeFalse();
});
