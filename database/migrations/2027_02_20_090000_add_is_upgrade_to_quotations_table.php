<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a Quotation as a tenant-initiated, prorated plan upgrade rather
 * than a standard new/renewal quotation — PayQuotation reads this to decide
 * whether to preserve the tenant's existing renewal date instead of
 * extending it (see RequestPlanUpgrade).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->boolean('is_upgrade')->default(false)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('is_upgrade');
        });
    }
};
