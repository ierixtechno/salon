<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deliberately decoupled from the Appointment state machine — a
        // waitlist entry never holds a firm slot, it just records that a
        // customer wants to be notified/offered one. Turning an entry into
        // a real booking is a normal BookAppointment call that happens to
        // reference this row afterward (waitlist_entries.status ->
        // 'booked'), not a special path through availability checking.
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->date('preferred_date')->nullable();
            $table->string('status')->default('waiting'); // waiting | notified | booked | expired | cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
