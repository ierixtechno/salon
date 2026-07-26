<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // State machine (App\Domain\Core\Models\Invoice::TRANSITIONS):
        //   draft      -> finalized (CheckoutSale assigns invoice_number)
        //   finalized  -> partially_paid, paid, void (void only while unpaid)
        //   partially_paid -> paid, refunded
        //   paid       -> refunded
        // A draft with no assigned invoice_number is deletable outright —
        // it never became a legal document, so no numbering gap needs
        // explaining. Once finalized, CLAUDE.md §45/§48: never silently
        // edit/delete — void/refund only.
        //
        // customer_name/customer_phone are a deliberate snapshot, not just
        // a live join through customer_id: CLAUDE.md §36 requires that a
        // later DPDP erasure of the customer record must never corrupt a
        // finalized financial record. customer_id is kept for "this
        // customer's invoice history" queries while the customer isn't
        // erased.
        //
        // Money: DECIMAL(12,2) per D-002. Tax: cgst_total/sgst_total only
        // (v1 assumes single-state operation per D-003 — IGST is deferred).
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('financial_year')->nullable();
            $table->unsignedBigInteger('sequence_number')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('cgst_total', 12, 2)->default(0);
            $table->decimal('sgst_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'invoice_number']);
            $table->unique(['branch_id', 'financial_year', 'sequence_number']);
            $table->index(['tenant_id', 'branch_id', 'status']);
            $table->index(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
