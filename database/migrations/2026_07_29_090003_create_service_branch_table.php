<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Branch pricing override (CLAUDE.md §14 Pricing: base pricing +
        // branch pricing). Pure pivot of two already tenant-scoped models
        // (Service, Branch) — no independent tenant_id needed, same
        // precedent as branch_modules/branch_user from Phase 1/2.
        Schema::create('service_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_available')->default(true);
            $table->decimal('price_override', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_branch');
    }
};
