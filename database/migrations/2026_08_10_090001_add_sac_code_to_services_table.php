<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // D-003 (docs/decisions/README.md): SAC (Services Accounting Code)
        // for GST-compliant invoices — services only, no HSN, since
        // sellable products don't exist until Phase 7.
        Schema::table('services', function (Blueprint $table) {
            $table->string('sac_code')->nullable()->after('tax_rate_percent');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('sac_code');
        });
    }
};
