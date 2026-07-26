<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A category is tagged with the vertical it belongs to (CLAUDE.md
        // §14: "services must identify their originating vertical/module").
        // Reuses the existing `modules` catalog from Phase 1 rather than
        // duplicating it — do not create a second module concept per
        // vertical (CLAUDE.md §3).
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'module_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_categories');
    }
};
