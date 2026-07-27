<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A ledger, never a mutable balance (CLAUDE.md §22 Commission) — an
        // employee's total commission is always sum(amount) for them.
        // Signed amount: positive for an accrual, negative for a reversal.
        // `reversed_entry_id` traces a reversal back to the exact accrual
        // it cancels out (CLAUDE.md §45 — every financial change traceable).
        Schema::create('commission_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('invoice_id')->constrained();
            $table->foreignId('invoice_line_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // accrual | reversal
            $table->decimal('amount', 12, 2);
            $table->decimal('rate_applied', 8, 2)->nullable();
            $table->foreignId('reversed_entry_id')->nullable()->constrained('commission_entries')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_entries');
    }
};
