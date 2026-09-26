<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Promo pricing: `price` stays the price actually charged; `compare_at_price`
        // is the regular price shown struck through beside it (null = no promo).
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('compare_at_price', 12, 2)->nullable()->after('price');
        });

        // A Super Admin-granted discount, recorded on the quotation and carried
        // to the invoice so both show what was given. `amount`/`subtotal` are
        // already the discounted figures; discount_amount is what came off.
        Schema::table('quotations', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->default(0)->after('amount');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_percent');
        });

        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->default(0)->after('subtotal');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('platform_invoices', fn (Blueprint $table) => $table->dropColumn(['discount_percent', 'discount_amount']));
        Schema::table('quotations', fn (Blueprint $table) => $table->dropColumn(['discount_percent', 'discount_amount']));
        Schema::table('subscription_plans', fn (Blueprint $table) => $table->dropColumn('compare_at_price'));
    }
};
