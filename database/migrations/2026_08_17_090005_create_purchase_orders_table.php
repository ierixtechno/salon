<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // State machine (App\Domain\Core\Models\PurchaseOrder::TRANSITIONS):
        //   draft -> ordered, cancelled
        //   ordered -> partially_received, received, cancelled
        //   partially_received -> received
        // Cancellation is only reachable before any goods have been
        // received — once a delivery lands, cancel no longer makes sense
        // (same "reachability enforces the business rule" pattern as
        // Invoice's void-only-while-unpaid, Phase 6).
        //
        // No sequence-locked statutory numbering like GST invoices — a
        // purchase order is an internal document, not a legally numbered
        // one, so its "number" is just `PO-{id}` derived from the PK.
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->string('status')->default('draft');
            $table->date('expected_date')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('ordered_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index(['tenant_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
