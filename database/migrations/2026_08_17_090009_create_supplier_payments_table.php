<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A simple outgoing-payment ledger against a supplier (optionally
        // tied to the PO it settles). No TDS/TCS deduction logic — that is
        // its own compliance decision, deliberately deferred (same weight
        // as D-003's GST decision in Phase 6), not silently invented here.
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method'); // cash | card | upi | bank_transfer
            $table->string('reference')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at');
            $table->timestamps();

            $table->index(['tenant_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
