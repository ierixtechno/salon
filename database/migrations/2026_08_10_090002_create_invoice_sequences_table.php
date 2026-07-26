<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GST-compliant sequential invoice numbering must be unbroken per
        // branch per financial year (D-003) — a single counter row per
        // (branch, financial_year), incremented under a row lock
        // (`lockForUpdate()`) inside CheckoutSale's transaction, the same
        // "lock a proxy row" pattern used for Appointment Engine conflict
        // checks (CLAUDE.md §24).
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->string('financial_year'); // e.g. "2025-26"
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(['branch_id', 'financial_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
