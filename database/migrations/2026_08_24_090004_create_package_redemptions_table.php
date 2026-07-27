<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The redemption history CLAUDE.md §14 Packages requires. Deliberately
        // not wired through the Invoice engine — see CustomerPackage's
        // migration comment and RecordServiceConsumption's precedent
        // (Phase 7): a manual, staff-triggered ledger entry against a
        // completed appointment, not a silent side effect of any other
        // already-shipped action.
        Schema::create('package_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_package_item_id')->constrained();
            $table->foreignId('appointment_id')->constrained()->unique();
            $table->foreignId('branch_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->dateTime('redeemed_at');
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'customer_package_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_redemptions');
    }
};
