<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Header only, same pattern as GoodsReceipt/PurchaseReturn — a
        // transfer's product/quantity detail lives as a pair of
        // stock_movements per product (transfer_out at from_branch,
        // transfer_in at to_branch), both referencing this row
        // (reference_type='stock_transfer'), created atomically in one
        // transaction (TransferStock).
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_branch_id')->constrained('branches');
            $table->foreignId('to_branch_id')->constrained('branches');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('transferred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'from_branch_id']);
            $table->index(['tenant_id', 'to_branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
