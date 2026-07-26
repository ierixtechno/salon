<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Staff capability (CLAUDE.md §14 Services Core / EMPLOYEE.md) —
        // which employees can perform this service. The Phase 5 Appointment
        // Engine will check this alongside schedule/branch/leave; this
        // table only records the raw capability.
        Schema::create('service_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['service_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_user');
    }
};
