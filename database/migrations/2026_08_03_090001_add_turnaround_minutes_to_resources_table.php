<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cleaning/reset time needed after a session before the resource
        // (room/chair/station) can be booked again — reduces actual
        // bookable capacity, not just a display nicety
        // (docs/modules/SPA.md). Default 0 preserves existing resources'
        // current (unbuffered) behavior.
        Schema::table('resources', function (Blueprint $table) {
            $table->unsignedInteger('turnaround_minutes')->default(0)->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn('turnaround_minutes');
        });
    }
};
