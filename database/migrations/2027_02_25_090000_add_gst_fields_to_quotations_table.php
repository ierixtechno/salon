<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `amount` keeps its existing meaning (the base/pre-tax figure — plan
 * price, or RequestPlanUpgrade's prorated difference). `total_amount` is
 * the new actual payable figure (amount + cgst_amount + sgst_amount) —
 * that's what Razorpay charges, not `amount`. gst_rate_percent is a
 * snapshot of the rate at creation time so historical quotations stay
 * accurate if the platform rate ever changes (CLAUDE.md §45).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->decimal('cgst_amount', 12, 2)->default(0)->after('amount');
            $table->decimal('sgst_amount', 12, 2)->default(0)->after('cgst_amount');
            $table->decimal('gst_rate_percent', 5, 2)->default(0)->after('sgst_amount');
            $table->decimal('total_amount', 12, 2)->default(0)->after('gst_rate_percent');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn(['cgst_amount', 'sgst_amount', 'gst_rate_percent', 'total_amount']);
        });
    }
};
