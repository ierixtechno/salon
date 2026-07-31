<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant's own GSTIN — shown as the recipient GSTIN on their Platform
 * Billing invoices (alongside the platform's own supplier GSTIN,
 * config('platform.gstin')) so the tenant can claim input tax credit.
 * Distinct from Tenant.billing_state (which drives CGST+SGST vs IGST) and
 * from Branch.gstin (a tenant's own branch-level GSTIN for their POS
 * invoices to their customers, Phase 6) — this one is the tenant's
 * registration as StyloBiz's customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('gstin', 15)->nullable()->after('billing_state');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('gstin');
        });
    }
};
