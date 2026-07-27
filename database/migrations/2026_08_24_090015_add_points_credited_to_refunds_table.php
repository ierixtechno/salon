<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When a refund's method is 'loyalty', this records how many
        // points were credited back — so the reversal is itself traceable
        // (CLAUDE.md §45/§47), not just an opaque currency amount.
        Schema::table('refunds', function (Blueprint $table) {
            $table->unsignedInteger('points_credited')->nullable()->after('method');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn('points_credited');
        });
    }
};
