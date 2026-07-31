<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `amount` keeps its existing meaning — "what was actually paid" — it's
 * just tax-inclusive going forward (copied from Quotation.total_amount by
 * PayQuotation). subtotal/cgst_amount/sgst_amount/gst_rate_percent are the
 * breakdown, copied verbatim from the paid Quotation so the invoice is a
 * self-contained historical record (CLAUDE.md §45), not dependent on
 * re-deriving anything from the Quotation or the platform's current rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->default(0)->after('amount');
            $table->decimal('cgst_amount', 12, 2)->default(0)->after('subtotal');
            $table->decimal('sgst_amount', 12, 2)->default(0)->after('cgst_amount');
            $table->decimal('gst_rate_percent', 5, 2)->default(0)->after('sgst_amount');
        });
    }

    public function down(): void
    {
        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'cgst_amount', 'sgst_amount', 'gst_rate_percent']);
        });
    }
};
