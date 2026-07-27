<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A financial ledger, never a mutable balance column (same
        // convention as wallet_transactions/loyalty_ledger_entries,
        // CLAUDE.md §20/§45) — a session's running cash total is always
        // opening_cash + sum(amount) for its movements. `amount` is signed:
        // positive for cash in (sale, manual cash-in), negative for cash
        // out (refund, expense, manual cash-out).
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('cash_register_session_id')->constrained();
            $table->string('type'); // sale | refund | expense | cash_in | cash_out
            $table->decimal('amount', 12, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'cash_register_session_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
