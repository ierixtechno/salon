<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // State machine (docs/modules/APPOINTMENT.md, extended — see
        // App\Domain\Core\Models\Appointment::TRANSITIONS for the
        // authoritative graph):
        //   pending -> confirmed -> checked_in -> in_service -> completed
        //   pending|confirmed -> cancelled
        //   confirmed -> no_show
        //
        // `starts_at`/`ends_at` are canonical UTC (config/app.php
        // timezone) per CLAUDE.md §22 — branch-local conversion happens at
        // the application layer, never in storage.
        //
        // `price` is snapshotted at booking time (server-resolved via
        // Service::priceForBranch/ServiceVariant::effectivePrice, never
        // client-submitted, CLAUDE.md §20) so a later catalogue price
        // change never retroactively rewrites a historical appointment.
        //
        // `group_uuid` links sibling appointments booked together (a
        // recurring series, or — future — a couple booking needing two
        // therapists/rooms simultaneously, docs/modules/SPA.md). Nothing
        // reads it yet beyond that grouping; it exists so that future work
        // doesn't need a schema change to associate rows.
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('service_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained(); // assigned employee/staff
            $table->foreignId('resource_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('group_uuid')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status')->default('pending');
            $table->string('source')->default('staff'); // staff | walk_in
            $table->decimal('price', 12, 2);
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('no_show_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'starts_at']);
            $table->index(['tenant_id', 'user_id', 'starts_at']);
            $table->index(['tenant_id', 'resource_id', 'starts_at']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'group_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
