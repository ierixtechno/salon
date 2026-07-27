<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // `method` gains 'wallet' | 'gift_card' | 'loyalty' as valid values
        // (app-level validation only — the column is a plain string, not
        // a DB enum). `gift_card_id` records which card a gift_card payment
        // drew from; `points_redeemed` records how many loyalty points a
        // loyalty payment consumed — both needed so a later refund can
        // reverse the exact same ledger it came from.
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('gift_card_id')->nullable()->after('method')->constrained()->nullOnDelete();
            $table->unsignedInteger('points_redeemed')->nullable()->after('gift_card_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gift_card_id');
            $table->dropColumn('points_redeemed');
        });
    }
};
