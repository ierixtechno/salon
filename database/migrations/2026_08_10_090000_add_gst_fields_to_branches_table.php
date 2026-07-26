<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // D-003 (docs/decisions/README.md): GSTIN is legally tied to a
        // specific state registration, so it belongs on Branch, not Tenant
        // — a tenant with branches in different states would need a
        // distinct GSTIN per branch. `state` is stored separately (not
        // just parsed from the GSTIN) so invoicing still works sensibly
        // before a branch has entered its GSTIN. v1 assumes every branch
        // operates in its own state only (CGST+SGST) — cross-state IGST
        // determination is deliberately deferred, not precluded.
        Schema::table('branches', function (Blueprint $table) {
            $table->string('gstin')->nullable()->after('address');
            $table->string('state')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn(['gstin', 'state']);
        });
    }
};
