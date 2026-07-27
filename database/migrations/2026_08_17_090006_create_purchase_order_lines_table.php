<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `quantity_received` accumulates across possibly-multiple
        // GoodsReceipts against the same PO — the actual received-batch
        // detail lives in stock_movements (reference_type='goods_receipt'),
        // this column is just the running total used to determine
        // draft/ordered/partially_received/received status.
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity_ordered', 12, 3);
            $table->decimal('quantity_received', 12, 3)->default(0);
            $table->decimal('unit_cost', 12, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
