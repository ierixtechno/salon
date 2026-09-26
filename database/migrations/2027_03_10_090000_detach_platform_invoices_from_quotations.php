<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A quotation is only a pre-payment document: once it is paid it is
     * removed (PayQuotation), and the PlatformInvoice is the record that
     * remains. So the invoice can no longer hard-depend on it — the FK
     * becomes nullable/nullOnDelete, and the quotation number is copied
     * onto the invoice as a plain reference.
     */
    public function up(): void
    {
        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->string('quotation_number')->nullable()->after('quotation_id');
        });

        DB::table('platform_invoices')
            ->join('quotations', 'quotations.id', '=', 'platform_invoices.quotation_id')
            ->update(['platform_invoices.quotation_number' => DB::raw('quotations.quotation_number')]);

        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
        });

        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('quotation_id')->nullable()->change();
        });

        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->foreign('quotation_id')->references('id')->on('quotations')->nullOnDelete();
        });

        // Quotations already paid before this change are removed as well;
        // their invoices (now carrying the quotation number) stay.
        DB::table('quotations')->where('status', 'paid')->delete();
    }

    public function down(): void
    {
        // Deleted quotations cannot be restored; only the schema is reverted.
        Schema::table('platform_invoices', function (Blueprint $table) {
            $table->dropColumn('quotation_number');
        });
    }
};
