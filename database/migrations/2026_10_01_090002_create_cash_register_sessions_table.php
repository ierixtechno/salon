<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only one `open` session per branch at a time — enforced in
        // OpenCashRegister (a partial unique index isn't portable to
        // MySQL, so this is an application-level check under a lock,
        // consistent with the concurrency pattern used for appointment
        // booking/stock decrement elsewhere, CLAUDE.md §24).
        //
        // `expected_closing`/`difference` are snapshotted at close time —
        // computed from the cash_movements ledger, never a live mutable
        // column while the session is open (CLAUDE.md §14 Cash Register).
        // `status`/`closed_by`/`closed_at`/`actual_closing`/
        // `expected_closing`/`difference` are deliberately not fillable —
        // set only via OpenCashRegister/CloseCashRegister.
        Schema::create('cash_register_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('opened_at');
            $table->decimal('opening_cash', 12, 2);
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->decimal('actual_closing', 12, 2)->nullable();
            $table->decimal('expected_closing', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->string('status')->default('open'); // open | closed
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_sessions');
    }
};
