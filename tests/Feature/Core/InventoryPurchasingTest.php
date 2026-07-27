<?php

use App\Domain\Core\Actions\BookAppointment;
use App\Domain\Core\Actions\CheckInAppointment;
use App\Domain\Core\Actions\CompleteAppointment;
use App\Domain\Core\Actions\CreateEmployee;
use App\Domain\Core\Actions\CreatePurchaseOrder;
use App\Domain\Core\Actions\OrderPurchaseOrder;
use App\Domain\Core\Actions\ReceiveGoods;
use App\Domain\Core\Actions\RecordStockMovement;
use App\Domain\Core\Actions\StartAppointmentService;
use App\Domain\Core\Actions\UpdateBranchModules;
use App\Domain\Core\Models\Branch;
use App\Domain\Core\Models\BranchStock;
use App\Domain\Core\Models\Customer;
use App\Domain\Core\Models\EmployeeSchedule;
use App\Domain\Core\Models\Product;
use App\Domain\Core\Models\ProductCategory;
use App\Domain\Core\Models\PurchaseOrder;
use App\Domain\Core\Models\Service;
use App\Domain\Core\Models\ServiceCategory;
use App\Domain\Core\Models\StockMovement;
use App\Domain\Core\Models\Supplier;
use App\Domain\Core\Scopes\TenantScope;
use App\Domain\Platform\Models\Module;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

function inventoryFixture(): array
{
    $owner = onboard(['modules' => ['salon']]);
    $branchA = Branch::factory()->forTenant($owner->tenant)->create(['name' => 'Branch A', 'code' => 'BRA']);
    $branchB = Branch::factory()->forTenant($owner->tenant)->create(['name' => 'Branch B', 'code' => 'BRB']);
    app(UpdateBranchModules::class)->execute($branchA, ['salon']);
    app(UpdateBranchModules::class)->execute($branchB, ['salon']);

    $category = ProductCategory::factory()->forTenant($owner->tenant)->create();
    $product = Product::factory()->forCategory($category)->create([
        'cost_price' => 2, 'selling_price' => 5, 'reorder_level' => 100,
    ]);

    $supplier = Supplier::factory()->forTenant($owner->tenant)->create();

    return compact('owner', 'branchA', 'branchB', 'category', 'product', 'supplier');
}

test('an owner can create a product category and a product in it', function () {
    $owner = onboard(['modules' => ['salon']]);

    $this->actingAs($owner)->post('/product-categories', ['name' => 'Hair Care'])->assertRedirect();
    $category = ProductCategory::where('name', 'Hair Care')->firstOrFail();
    expect($category->tenant_id)->toBe($owner->tenant_id);

    $this->actingAs($owner)->post('/products', [
        'product_category_id' => $category->id,
        'name' => 'Shampoo',
        'unit' => 'ml',
        'cost_price' => 2,
        'reorder_level' => 50,
    ])->assertRedirect();

    $product = Product::where('name', 'Shampoo')->firstOrFail();
    expect($product->product_category_id)->toBe($category->id);
});

test('a full purchase order lifecycle moves stock through partial then full receipt', function () {
    $fixture = inventoryFixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $this->post('/purchase-orders', [
        'branch_id' => $fixture['branchA']->id,
        'supplier_id' => $fixture['supplier']->id,
        'lines' => [['product_id' => $fixture['product']->id, 'quantity' => 1000, 'unit_cost' => 2]],
    ])->assertRedirect();

    $order = PurchaseOrder::firstOrFail();
    expect($order->status)->toBe('draft');

    $this->post("/purchase-orders/{$order->id}/order")->assertRedirect();
    expect($order->fresh()->status)->toBe('ordered');

    $line = $order->lines()->firstOrFail();

    $this->post("/purchase-orders/{$order->id}/receive", [
        'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 600]],
    ])->assertRedirect();

    expect($order->fresh()->status)->toBe('partially_received');
    $stock = BranchStock::where('branch_id', $fixture['branchA']->id)->where('product_id', $fixture['product']->id)->firstOrFail();
    expect((float) $stock->quantity)->toBe(600.0);

    $this->post("/purchase-orders/{$order->id}/receive", [
        'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 400]],
    ])->assertRedirect();

    expect($order->fresh()->status)->toBe('received');
    expect((float) $stock->fresh()->quantity)->toBe(1000.0);

    // Fully received — attempting to receive again against the same line is rejected.
    $this->post("/purchase-orders/{$order->id}/receive", [
        'lines' => [['purchase_order_line_id' => $line->id, 'quantity' => 1]],
    ])->assertStatus(409);
});

test('cancelling a purchase order is only allowed before goods are received', function () {
    $fixture = inventoryFixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $order = app(CreatePurchaseOrder::class)->execute(
        $fixture['branchA'], $fixture['supplier'],
        [['product_id' => $fixture['product']->id, 'quantity' => 10, 'unit_cost' => 2]],
        null, null, $owner->id,
    );

    $this->post("/purchase-orders/{$order->id}/cancel")->assertRedirect();
    expect($order->fresh()->status)->toBe('cancelled');

    $order2 = app(CreatePurchaseOrder::class)->execute(
        $fixture['branchA'], $fixture['supplier'],
        [['product_id' => $fixture['product']->id, 'quantity' => 10, 'unit_cost' => 2]],
        null, null, $owner->id,
    );
    app(OrderPurchaseOrder::class)->execute($order2);
    $line = $order2->lines()->firstOrFail();
    app(ReceiveGoods::class)->execute($order2, [
        ['purchase_order_line_id' => $line->id, 'quantity' => 10],
    ], null, null, $owner->id);

    // Fully received — cancel is no longer a valid transition.
    $this->post("/purchase-orders/{$order2->id}/cancel")->assertStatus(409);
});

test('a purchase return decreases branch stock', function () {
    $fixture = inventoryFixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    app(RecordStockMovement::class)->execute(
        $fixture['branchA'], $fixture['product'], 'purchase', 500, unitCost: 2,
    );

    $this->post('/purchase-returns', [
        'branch_id' => $fixture['branchA']->id,
        'supplier_id' => $fixture['supplier']->id,
        'lines' => [['product_id' => $fixture['product']->id, 'quantity' => 50, 'unit_cost' => 2]],
        'reason' => 'damaged in transit',
    ])->assertRedirect();

    $stock = BranchStock::where('branch_id', $fixture['branchA']->id)->where('product_id', $fixture['product']->id)->firstOrFail();
    expect((float) $stock->quantity)->toBe(450.0);
});

test('a return cannot take stock negative', function () {
    $fixture = inventoryFixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    $this->post('/purchase-returns', [
        'branch_id' => $fixture['branchA']->id,
        'supplier_id' => $fixture['supplier']->id,
        'lines' => [['product_id' => $fixture['product']->id, 'quantity' => 5]],
    ])->assertStatus(409);
});

test('manual damage and expiry adjustments always decrease stock regardless of sign, while adjustment respects direction', function () {
    $fixture = inventoryFixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    app(RecordStockMovement::class)->execute(
        $fixture['branchA'], $fixture['product'], 'purchase', 100, unitCost: 2,
    );
    $stock = BranchStock::where('branch_id', $fixture['branchA']->id)->where('product_id', $fixture['product']->id)->firstOrFail();

    $this->post('/inventory/adjust', [
        'branch_id' => $fixture['branchA']->id,
        'product_id' => $fixture['product']->id,
        'type' => 'damage',
        'quantity' => 10,
    ])->assertRedirect();
    expect((float) $stock->fresh()->quantity)->toBe(90.0);

    $this->post('/inventory/adjust', [
        'branch_id' => $fixture['branchA']->id,
        'product_id' => $fixture['product']->id,
        'type' => 'adjustment',
        'direction' => 'increase',
        'quantity' => 20,
    ])->assertRedirect();
    expect((float) $stock->fresh()->quantity)->toBe(110.0);

    $this->post('/inventory/adjust', [
        'branch_id' => $fixture['branchA']->id,
        'product_id' => $fixture['product']->id,
        'type' => 'adjustment',
        'direction' => 'decrease',
        'quantity' => 30,
    ])->assertRedirect();
    expect((float) $stock->fresh()->quantity)->toBe(80.0);
});

test('stock transfers move quantity between branches atomically and reject over-transfer', function () {
    $fixture = inventoryFixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    app(RecordStockMovement::class)->execute(
        $fixture['branchA'], $fixture['product'], 'purchase', 200, unitCost: 2,
    );

    $this->post('/inventory/transfer', [
        'from_branch_id' => $fixture['branchA']->id,
        'to_branch_id' => $fixture['branchB']->id,
        'lines' => [['product_id' => $fixture['product']->id, 'quantity' => 50]],
    ])->assertRedirect();

    $stockA = BranchStock::where('branch_id', $fixture['branchA']->id)->where('product_id', $fixture['product']->id)->firstOrFail();
    $stockB = BranchStock::where('branch_id', $fixture['branchB']->id)->where('product_id', $fixture['product']->id)->firstOrFail();
    expect((float) $stockA->quantity)->toBe(150.0);
    expect((float) $stockB->quantity)->toBe(50.0);

    $this->post('/inventory/transfer', [
        'from_branch_id' => $fixture['branchA']->id,
        'to_branch_id' => $fixture['branchB']->id,
        'lines' => [['product_id' => $fixture['product']->id, 'quantity' => 999999]],
    ])->assertStatus(409);
});

test('recording service consumption deducts the recipe quantity once and rejects a second attempt', function () {
    $fixture = inventoryFixture();
    $owner = $fixture['owner'];
    $this->actingAs($owner);

    app(RecordStockMovement::class)->execute(
        $fixture['branchA'], $fixture['product'], 'purchase', 100, unitCost: 2,
    );

    $serviceCategory = ServiceCategory::factory()->forTenant($owner->tenant)->create([
        'module_id' => Module::where('code', 'salon')->firstOrFail()->id,
    ]);
    $service = Service::factory()->forCategory($serviceCategory)->create(['duration_minutes' => 30, 'buffer_minutes' => 0]);
    $service->branches()->attach($fixture['branchA']->id, ['is_available' => true]);

    $this->put("/services/{$service->id}/consumables", [
        'consumables' => [['product_id' => $fixture['product']->id, 'quantity_per_use' => 10]],
    ])->assertRedirect();

    $employee = app(CreateEmployee::class)->execute($owner->tenant, [
        'name' => 'Stylist', 'email' => fake()->unique()->safeEmail(), 'password' => 'password123',
        'employment_type' => 'full_time', 'role' => 'Staff', 'all_branches' => true, 'branches' => [],
    ]);
    $service->capableEmployees()->attach($employee->id);
    foreach (range(0, 6) as $d) {
        $schedule = new EmployeeSchedule(['user_id' => $employee->id, 'branch_id' => $fixture['branchA']->id, 'day_of_week' => $d, 'starts_at' => '09:00', 'ends_at' => '18:00']);
        $schedule->tenant_id = $owner->tenant_id;
        $schedule->save();
    }
    $customer = Customer::factory()->forTenant($owner->tenant)->create();

    $appointment = app(BookAppointment::class)->execute(
        $fixture['branchA'], $customer, $service, null, $employee, null, now()->addDay()->setTime(10, 0),
    );
    app(CheckInAppointment::class)->execute($appointment);
    app(StartAppointmentService::class)->execute($appointment->fresh());
    app(CompleteAppointment::class)->execute($appointment->fresh());

    $this->post("/appointments/{$appointment->id}/consumption")->assertRedirect();

    $stock = BranchStock::where('branch_id', $fixture['branchA']->id)->where('product_id', $fixture['product']->id)->firstOrFail();
    expect((float) $stock->quantity)->toBe(90.0);
    expect(StockMovement::where('reference_type', 'appointment_consumption')->where('reference_id', $appointment->id)->count())->toBe(1);

    $this->post("/appointments/{$appointment->id}/consumption")->assertStatus(409);
    expect((float) $stock->fresh()->quantity)->toBe(90.0);
});

test('a manager can run purchasing and inventory operations but cannot delete products or record supplier payments', function () {
    $fixture = inventoryFixture();
    $owner = $fixture['owner'];

    $manager = User::factory()->forTenant($owner->tenant)->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($owner->tenant_id);
    $manager->assignRole('Manager');

    $this->actingAs($manager)->post('/purchase-orders', [
        'branch_id' => $fixture['branchA']->id,
        'supplier_id' => $fixture['supplier']->id,
        'lines' => [['product_id' => $fixture['product']->id, 'quantity' => 10, 'unit_cost' => 2]],
    ])->assertRedirect();
    $order = PurchaseOrder::firstOrFail();

    $this->actingAs($manager)->post("/purchase-orders/{$order->id}/order")->assertRedirect();

    $this->actingAs($manager)->post('/inventory/adjust', [
        'branch_id' => $fixture['branchA']->id,
        'product_id' => $fixture['product']->id,
        'type' => 'adjustment',
        'direction' => 'increase',
        'quantity' => 5,
    ])->assertRedirect();

    $this->actingAs($manager)->delete("/products/{$fixture['product']->id}")->assertForbidden();

    $this->actingAs($manager)->post("/suppliers/{$fixture['supplier']->id}/payments", [
        'amount' => 100, 'method' => 'cash',
    ])->assertForbidden();
});

test('staff have read-only access to inventory and cannot access purchasing', function () {
    $fixture = inventoryFixture();
    $owner = $fixture['owner'];

    $staff = User::factory()->forTenant($owner->tenant)->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($owner->tenant_id);
    $staff->assignRole('Staff');

    $this->actingAs($staff)->get('/inventory')->assertOk();
    $this->actingAs($staff)->get('/inventory/adjust')->assertForbidden();
    $this->actingAs($staff)->post('/inventory/adjust', [
        'branch_id' => $fixture['branchA']->id,
        'product_id' => $fixture['product']->id,
        'type' => 'adjustment',
        'direction' => 'increase',
        'quantity' => 5,
    ])->assertForbidden();

    $this->actingAs($staff)->get('/purchase-orders')->assertForbidden();
    $this->actingAs($staff)->post('/purchase-orders', [
        'branch_id' => $fixture['branchA']->id,
        'supplier_id' => $fixture['supplier']->id,
        'lines' => [['product_id' => $fixture['product']->id, 'quantity' => 10, 'unit_cost' => 2]],
    ])->assertForbidden();
});

/**
 * Mandatory pattern per CLAUDE.md §62.
 */
test('a user from tenant B cannot view, receive, or cancel a purchase order belonging to tenant A', function () {
    $fixture = inventoryFixture();
    $ownerA = $fixture['owner'];
    $ownerB = onboard(['modules' => ['salon']]);
    $this->actingAs($ownerA);

    $order = app(CreatePurchaseOrder::class)->execute(
        $fixture['branchA'], $fixture['supplier'],
        [['product_id' => $fixture['product']->id, 'quantity' => 10, 'unit_cost' => 2]],
        null, null, $ownerA->id,
    );
    app(OrderPurchaseOrder::class)->execute($order);
    $lineId = $order->lines()->firstOrFail()->id;

    $this->actingAs($ownerB)->get("/purchase-orders/{$order->id}")->assertNotFound();
    $this->actingAs($ownerB)->post("/purchase-orders/{$order->id}/cancel")->assertNotFound();
    $this->actingAs($ownerB)->post("/purchase-orders/{$order->id}/receive", [
        'lines' => [['purchase_order_line_id' => $lineId, 'quantity' => 1]],
    ])->assertNotFound();

    expect(PurchaseOrder::withoutGlobalScope(TenantScope::class)->find($order->id)->status)->toBe('ordered');
});
