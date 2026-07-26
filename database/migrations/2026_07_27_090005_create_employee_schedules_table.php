<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Weekly recurring availability per branch (an employee working at
        // two branches can have different hours at each). This is a
        // template the Phase 5 Appointment Engine reads from — it is not
        // itself an attendance/leave record (Phase 9).
        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Sunday .. 6 = Saturday
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->unique(['user_id', 'branch_id', 'day_of_week'], 'employee_schedule_unique');
            $table->index(['branch_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_schedules');
    }
};
