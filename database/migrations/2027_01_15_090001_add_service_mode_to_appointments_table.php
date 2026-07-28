<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where this appointment is actually delivered: 'branch' (default,
        // preserves existing rows' behavior), 'home', or 'venue' — see
        // App\Domain\Core\Models\Appointment::SERVICE_MODES. branch_id
        // stays required regardless of mode: it remains the "owning
        // branch" for the employee's schedule, GST/invoice numbering, and
        // tenant/branch authorization even for a home/venue booking.
        // delivery_address only applies when service_mode isn't 'branch'
        // (enforced in StoreAppointmentRequest, not at the DB layer).
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('service_mode')->default('branch')->after('resource_id');
            $table->text('delivery_address')->nullable()->after('service_mode');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['service_mode', 'delivery_address']);
        });
    }
};
