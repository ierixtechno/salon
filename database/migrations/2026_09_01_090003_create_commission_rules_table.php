<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One active rule per employee (v1 scope — a single flat rate
        // applied to every service line they're credited on; per-category
        // slab rules are a deliberate deferral, see docs/modules/
        // EMPLOYEE.md). `rate` is a percentage when type=percentage, or a
        // flat currency amount per line when type=fixed.
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained();
            $table->string('type'); // percentage | fixed
            $table->decimal('rate', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rules');
    }
};
