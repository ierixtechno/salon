<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `usage_limit` is nullable: null means unlimited redemptions
        // during validity. Module/branch/service applicability live in
        // separate pivot tables (a plan may be scoped to none, some, or
        // all of each dimension independently — CLAUDE.md §14 Membership).
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('validity_days');
            $table->decimal('price', 12, 2);
            $table->decimal('discount_percent', 5, 2);
            $table->unsignedInteger('usage_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};
