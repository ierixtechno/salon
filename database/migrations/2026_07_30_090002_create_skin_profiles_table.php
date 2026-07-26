<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per customer — persistent skin characteristics. Per-visit
        // assessment lives in skin_consultations. Requires the beauty module
        // enabled for the tenant (CLAUDE.md §16/§9).
        Schema::create('skin_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('skin_type')->nullable();
            $table->text('known_conditions')->nullable();
            $table->text('allergies')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skin_profiles');
    }
};
