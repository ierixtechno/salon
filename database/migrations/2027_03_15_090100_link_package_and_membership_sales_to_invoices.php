<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A package/membership sale is now billed on a normal Invoice, whose
        // single line is the package/membership itself — not a service.
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id')->nullable()->change();
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('tax_rate_percent', 5, 2)->default(0)->after('price');
        });

        Schema::table('membership_plans', function (Blueprint $table) {
            $table->decimal('tax_rate_percent', 5, 2)->default(0)->after('price');
        });

        Schema::table('customer_packages', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
        });

        Schema::table('customer_memberships', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_memberships', fn (Blueprint $table) => $table->dropConstrainedForeignId('invoice_id'));
        Schema::table('customer_packages', fn (Blueprint $table) => $table->dropConstrainedForeignId('invoice_id'));
        Schema::table('membership_plans', fn (Blueprint $table) => $table->dropColumn('tax_rate_percent'));
        Schema::table('packages', fn (Blueprint $table) => $table->dropColumn('tax_rate_percent'));
        // invoice_lines.service_id is left nullable on purpose: rows without a service may exist by now.
    }
};
