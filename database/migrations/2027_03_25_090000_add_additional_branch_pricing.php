<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A plan's `price` covers `branch_limit` (included) branches. Extra
        // branches are sold at `additional_branch_price` each — 0 means the
        // plan does not sell extras. `max_branches` optionally caps the total.
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('additional_branch_price', 12, 2)->default(0)->after('branch_limit');
            $table->unsignedInteger('max_branches')->nullable()->after('additional_branch_price');
        });

        // What was billed / bought: the total number of branches. Null =
        // just the plan's included branches (every row that pre-dates this).
        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedInteger('branch_count')->nullable()->after('subscription_plan_id');
        });

        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('branch_count')->nullable()->after('subscription_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_subscriptions', fn (Blueprint $table) => $table->dropColumn('branch_count'));
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn('branch_count'));
        Schema::table('subscription_plans', fn (Blueprint $table) => $table->dropColumn(['additional_branch_price', 'max_branches']));
    }
};
