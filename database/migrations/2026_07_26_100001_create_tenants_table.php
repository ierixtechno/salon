<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // See docs/decisions/README.md D-001/D-002: internal bigint PK,
            // DECIMAL money, single currency per tenant (pending confirmation).
            $table->enum('status', ['trial', 'active', 'suspended', 'cancelled'])->default('trial');
            $table->string('timezone')->default('UTC');
            $table->string('currency', 3);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
