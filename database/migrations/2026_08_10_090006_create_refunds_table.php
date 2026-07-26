<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Invoice-level, not tied to one specific originating Payment row
        // — with split payments, "reverse THIS payment" doesn't cleanly
        // map to a partial refund. This is a dedicated reversal ledger
        // (CLAUDE.md §14 Refunds), never a mutation of payment_status.
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->string('method'); // cash | card | upi | bank_transfer
            $table->text('reason')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
