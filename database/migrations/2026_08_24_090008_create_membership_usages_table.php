<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Traceability ledger for every discount a membership actually
        // applied (CLAUDE.md §47 Audit) — one row per invoice line the
        // discount landed on.
        Schema::create('membership_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_membership_id')->constrained();
            $table->foreignId('invoice_line_id')->constrained()->unique();
            $table->decimal('discount_amount', 12, 2);
            $table->dateTime('applied_at');
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_membership_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_usages');
    }
};
