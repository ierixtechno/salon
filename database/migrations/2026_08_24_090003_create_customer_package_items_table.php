<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-service entitlement, snapshotted from the package's recipe at
        // purchase time (a later change to the template's recipe must never
        // retroactively alter an already-sold instance — CLAUDE.md §45).
        // `quantity_redeemed` accumulates only via RedeemPackageItem, never
        // mass assignable.
        Schema::create('customer_package_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->unsignedInteger('quantity_purchased');
            $table->unsignedInteger('quantity_redeemed')->default(0);
            $table->timestamps();

            $table->unique(['customer_package_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_package_items');
    }
};
