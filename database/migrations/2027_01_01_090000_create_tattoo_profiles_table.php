<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per customer — persistent skin/health/history profile used
        // to screen contraindications before a tattoo session. Requires the
        // tattoo module enabled for the tenant (CLAUDE.md §17/§9, same
        // pattern as spa_profiles).
        Schema::create('tattoo_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->text('skin_conditions')->nullable();
            $table->text('allergies')->nullable();
            $table->text('previous_tattoos')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tattoo_profiles');
    }
};
