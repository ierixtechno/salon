<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable once created (CLAUDE.md §45 — a finalized financial
        // record is never silently edited/deleted) — the only write path is
        // PayQuotation, and there is no update/destroy route at all.
        Schema::create('platform_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quotation_id')->unique()->constrained();
            $table->foreignId('subscription_plan_id')->constrained();
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method')->default('razorpay');
            $table->string('payment_reference')->nullable();
            $table->dateTime('paid_at');
            $table->timestamps();

            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_invoices');
    }
};
