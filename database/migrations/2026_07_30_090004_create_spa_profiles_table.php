<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per customer — persistent health/preference profile used
        // to screen contraindications before a therapy session. Requires
        // the spa module enabled for the tenant (CLAUDE.md §17/§9).
        Schema::create('spa_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->text('health_conditions')->nullable();
            $table->text('allergies')->nullable();
            $table->string('pressure_preference')->nullable();
            $table->text('areas_to_avoid')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spa_profiles');
    }
};
