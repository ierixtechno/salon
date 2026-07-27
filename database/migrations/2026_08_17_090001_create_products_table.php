<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `brand`/`unit` are plain descriptive strings, not separate
        // manageable catalogs — CLAUDE.md §68: avoid premature abstraction
        // for what's essentially a label, unlike categories which need
        // real reporting/filtering structure. `reorder_level` is the
        // product's tenant-wide default; a branch can override it via
        // branch_stocks.reorder_level_override.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('brand')->nullable();
            $table->string('unit')->default('unit'); // e.g. ml, g, unit, bottle
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('selling_price', 12, 2)->nullable();
            $table->decimal('reorder_level', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'product_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
