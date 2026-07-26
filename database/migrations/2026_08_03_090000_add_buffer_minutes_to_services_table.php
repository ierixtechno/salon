<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prep/cleanup time the assigned employee needs after this service
        // before their next appointment can start — a real availability
        // constraint (docs/modules/APPOINTMENT.md), not a display nicety.
        // Default 0 preserves existing services' current (unbuffered)
        // behavior.
        Schema::table('services', function (Blueprint $table) {
            $table->unsignedInteger('buffer_minutes')->default(0)->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('buffer_minutes');
        });
    }
};
