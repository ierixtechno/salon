<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The ledger (CLAUDE.md §46) — branch_stocks.quantity must always
        // be reconstructable as the sum of these rows for that
        // branch/product. `quantity` is SIGNED: positive for increases
        // (purchase, transfer_in), negative for decreases (sale,
        // service_consumption, transfer_out, adjustment-down, return,
        // damage, expiry). `unit_cost` doubles as this movement's "line
        // item" detail for goods receipts/purchase returns — no separate
        // GoodsReceiptLine/PurchaseReturnLine table exists; the ledger
        // rows tied to a given reference ARE its lines.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->string('type'); // purchase|sale|service_consumption|transfer_in|transfer_out|adjustment|return|damage|expiry
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'product_id']);
            $table->index(['tenant_id', 'type']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
