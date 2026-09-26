<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Total active branches a tenant on this plan may run. Every tenant
        // gets one branch by default; extra branches come from choosing a
        // plan with a higher limit (priced by Super Admin like any plan).
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('branch_limit')->default(1)->after('billing_interval');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('branch_limit');
        });
    }
};
