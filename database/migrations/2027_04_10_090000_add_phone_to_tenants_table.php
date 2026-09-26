<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The business owner's contact mobile (10-digit Indian number), shown in
        // Super Admin's tenant list. Null for tenants created before this existed.
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('phone', 15)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('phone'));
    }
};
