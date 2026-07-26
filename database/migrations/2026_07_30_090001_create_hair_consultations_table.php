<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only per-visit history — never edited/deleted after the
        // fact (same ledger principle as Loyalty/Wallet, CLAUDE.md §46/§14).
        // color_formula doubles as color history since every consultation
        // row is itself a dated, immutable entry.
        Schema::create('hair_consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('consultant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('consultation_date');
            $table->text('concerns')->nullable();
            $table->text('recommendation')->nullable();
            $table->text('color_formula')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'branch_id', 'consultation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hair_consultations');
    }
};
