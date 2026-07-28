<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only per-visit history (CLAUDE.md §46/§14 ledger
        // principle). Room/resource allocation and availability belong to
        // the Appointment Engine, not the consultation record itself — same
        // pattern as spa_consultations.
        Schema::create('tattoo_consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('consultant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('consultation_date');
            $table->text('design_description')->nullable();
            $table->string('placement')->nullable();
            $table->string('size_estimate')->nullable();
            $table->text('aftercare_instructions')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'branch_id', 'consultation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tattoo_consultations');
    }
};
