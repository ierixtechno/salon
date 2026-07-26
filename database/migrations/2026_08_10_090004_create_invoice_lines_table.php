<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every line sells a Service (no standalone/misc line items —
        // sellable Products don't exist until Phase 7, and an untracked
        // arbitrary-charge line would be a revenue-tracking gap, not a
        // convenience). `appointment_id` is optional: a line may originate
        // from a completed Appointment (auto-filled, and unique so the
        // same appointment can never be invoiced twice) or be a standalone
        // walk-in sale with no prior booking.
        //
        // `description`/`unit_price`/`tax_rate_percent` are snapshotted at
        // the time the line was added — a later catalogue price/tax change
        // must never retroactively rewrite a historical invoice line
        // (CLAUDE.md §20/§45).
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained();
            $table->foreignId('service_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete()->unique();
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('taxable_value', 12, 2);
            $table->decimal('tax_rate_percent', 5, 2)->default(0);
            $table->decimal('cgst_amount', 12, 2)->default(0);
            $table->decimal('sgst_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
