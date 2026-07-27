<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Loyalty is tenant-configurable and opt-in: points_per_100 = 0
        // (the default) means the program is effectively off — RecordPayment
        // only awards points when this is greater than zero. Never editing
        // a deployed migration (CLAUDE.md §53) — additive columns only.
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->decimal('loyalty_points_per_100', 8, 2)->default(0)->after('cancellation_policy');
            $table->decimal('loyalty_redemption_value', 8, 4)->default(0)->after('loyalty_points_per_100');
            $table->unsignedInteger('loyalty_points_expiry_days')->nullable()->after('loyalty_redemption_value');
        });
    }

    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->dropColumn(['loyalty_points_per_100', 'loyalty_redemption_value', 'loyalty_points_expiry_days']);
        });
    }
};
