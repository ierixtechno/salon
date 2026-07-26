<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only per-visit history (CLAUDE.md §46/§14 ledger
        // principle). Room/resource allocation and multi-resource
        // availability (couple bookings, turnaround time) belong to the
        // Phase 5 Appointment Engine, not the consultation record itself —
        // docs/modules/SPA.md.
        Schema::create('spa_consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('consultant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('consultation_date');
            $table->text('concerns')->nullable();
            $table->text('recommendation')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'branch_id', 'consultation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spa_consultations');
    }
};
