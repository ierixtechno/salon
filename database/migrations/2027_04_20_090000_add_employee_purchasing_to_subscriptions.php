<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A tenant can now buy extra employee slots directly, independent of
     * branches (e.g. one branch with 15 staff, not 5) — see
     * SubscriptionPlan::totalUserLimit. `additional_employee_price` is the
     * price per extra slot per billing cycle; 0 = not offered.
     * `max_users` optionally caps the TOTAL employee limit (branch-derived
     * + directly purchased); null = no explicit cap.
     *
     * `extra_user_count` mirrors `branch_count`'s existing pattern on both
     * quotations (what was billed for) and tenant_subscriptions (what the
     * tenant currently has).
     */
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('additional_employee_price', 12, 2)->default(0)->after('users_per_additional_branch');
            $table->unsignedInteger('max_users')->nullable()->after('additional_employee_price');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedInteger('extra_user_count')->nullable()->after('branch_count');
        });

        Schema::table('tenant_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('extra_user_count')->nullable()->after('branch_count');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_subscriptions', fn (Blueprint $table) => $table->dropColumn('extra_user_count'));
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn('extra_user_count'));
        Schema::table('subscription_plans', fn (Blueprint $table) => $table->dropColumn(['additional_employee_price', 'max_users']));
    }
};
