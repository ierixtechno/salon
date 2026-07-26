<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CLAUDE.md §8 — a branch's enabled modules must be a subset of its
        // tenant's enabled modules. That invariant is enforced at the
        // application layer (see BranchModuleAssignment action), not here.
        Schema::create('branch_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->timestamps();

            $table->unique(['branch_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_modules');
    }
};
