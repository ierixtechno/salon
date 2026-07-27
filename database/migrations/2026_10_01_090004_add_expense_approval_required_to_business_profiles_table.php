<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Opt-in, off by default (CLAUDE.md §70: an additive, tenant-gated
        // behavior, never forced on) — mirrors the loyalty_points_per_100
        // pattern. When false, CreateExpense records expenses as already
        // `approved`; when true, they start `pending` for expenses.approve.
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->boolean('expense_approval_required')->default(false)->after('loyalty_points_expiry_days');
        });
    }

    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->dropColumn('expense_approval_required');
        });
    }
};
