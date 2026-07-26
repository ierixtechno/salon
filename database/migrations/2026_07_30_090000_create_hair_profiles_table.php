<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per customer (upsert target), never per-visit — persistent
        // hair/scalp characteristics. Per-visit assessment lives in
        // hair_consultations. Requires the salon module enabled for the
        // tenant (CLAUDE.md §15/§9), enforced in the application layer.
        Schema::create('hair_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('hair_type')->nullable();
            $table->string('scalp_type')->nullable();
            $table->text('chemical_history')->nullable();
            $table->text('allergies')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hair_profiles');
    }
};
