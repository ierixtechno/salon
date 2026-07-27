<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Header only — the received quantities/costs per product live as
        // stock_movements rows (type=purchase, reference_type=
        // 'goods_receipt', reference_id=this row's id). A PO can be
        // received across multiple GoodsReceipts (partial deliveries).
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained();
            $table->string('supplier_invoice_number')->nullable();
            $table->decimal('supplier_invoice_amount', 12, 2)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('received_at');
            $table->timestamps();

            $table->index(['tenant_id', 'purchase_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
