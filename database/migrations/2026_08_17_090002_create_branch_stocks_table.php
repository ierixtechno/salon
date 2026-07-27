<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `quantity` is a CACHED balance, never the authoritative record —
        // it must always equal the sum of this product/branch's
        // stock_movements and is only ever changed by RecordStockMovement
        // inside the same locked transaction as the movement it derives
        // from (CLAUDE.md §46). decimal(12,3) supports fractional
        // consumption (e.g. 10ml out of a 500ml bottle).
        Schema::create('branch_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('reorder_level_override', 12, 3)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_stocks');
    }
};
