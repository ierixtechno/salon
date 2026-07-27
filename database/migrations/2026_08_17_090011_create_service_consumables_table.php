<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The "recipe" — how much of a product a service typically uses
        // (e.g. a haircut uses 10ml shampoo). Tenant-wide, not per-branch:
        // the recipe itself doesn't vary by branch, only which branch's
        // stock gets consumed when it's actually recorded.
        Schema::create('service_consumables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_per_use', 12, 3);
            $table->timestamps();

            $table->unique(['service_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_consumables');
    }
};
