<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A service is always bookable in-branch by default. These columns
        // let a tenant additionally opt a specific service into home-visit
        // and/or venue delivery, each with its own optional flat fee.
        // travel_buffer_minutes is a single shared buffer added on top of
        // the existing buffer_minutes (prep time) whenever the appointment
        // isn't in-branch, covering the employee's travel to/from the
        // customer's location — not split per mode, to keep this one new
        // concept rather than two. Defaults preserve existing services'
        // current (branch-only) behavior.
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('home_service_enabled')->default(false)->after('is_active');
            $table->decimal('home_service_fee', 12, 2)->nullable()->after('home_service_enabled');
            $table->boolean('venue_service_enabled')->default(false)->after('home_service_fee');
            $table->decimal('venue_service_fee', 12, 2)->nullable()->after('venue_service_enabled');
            $table->unsignedInteger('travel_buffer_minutes')->default(0)->after('venue_service_fee');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'home_service_enabled', 'home_service_fee',
                'venue_service_enabled', 'venue_service_fee',
                'travel_buffer_minutes',
            ]);
        });
    }
};
