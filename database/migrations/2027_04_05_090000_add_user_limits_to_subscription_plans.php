<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Employee (user) limit. `users_included` is the number of active users
        // allowed with the plan's included branches; null = unlimited (every plan
        // that pre-dates this keeps working exactly as before). Each additional
        // branch a tenant buys adds `users_per_additional_branch` more.
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('users_included')->nullable()->after('max_branches');
            $table->unsignedInteger('users_per_additional_branch')->default(0)->after('users_included');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', fn (Blueprint $table) => $table->dropColumn(['users_included', 'users_per_additional_branch']));
    }
};
