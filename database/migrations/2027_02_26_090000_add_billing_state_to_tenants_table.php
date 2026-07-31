<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinct from Branch.state (where a tenant operates) — this is the
 * tenant's registered/billing state for Platform Billing GST purposes only
 * (config('platform.state') comparison in CreateQuotation). A tenant can
 * have branches across several states, so this is deliberately its own
 * field rather than derived from any branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('billing_state')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('billing_state');
        });
    }
};
