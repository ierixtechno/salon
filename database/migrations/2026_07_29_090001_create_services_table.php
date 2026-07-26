<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // module_id is denormalized from the category (a service always
        // belongs to a category, which is already module-tagged) purely so
        // "all salon services" doesn't require a join — it must always
        // match its category's module_id, enforced in the application
        // layer (StoreServiceRequest/UpdateServiceRequest), not the DB.
        //
        // Money: DECIMAL per D-002 (docs/decisions/README.md), never
        // float/double (CLAUDE.md §20). tax_rate_percent is a placeholder
        // flat rate — real jurisdiction-specific tax handling (GST/HSN/SAC
        // etc.) is D-003, still open before Phase 6.
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->decimal('base_price', 12, 2);
            $table->decimal('tax_rate_percent', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'module_id']);
            $table->index(['tenant_id', 'service_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
