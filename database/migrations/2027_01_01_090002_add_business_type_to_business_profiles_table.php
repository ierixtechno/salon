<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cosmetic/display-only label a tenant can pick (e.g. "Barber",
        // "Nail Studio") — it does NOT gate any module/feature/permission
        // and is deliberately kept off the `modules` table (CLAUDE.md §6/§10:
        // module/feature/plan/permission must never be substituted for one
        // another). Nullable/no default — "general" is simply the absence
        // of a preset, not a stored value.
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->string('business_type')->nullable()->after('display_name');
        });
    }

    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->dropColumn('business_type');
        });
    }
};
