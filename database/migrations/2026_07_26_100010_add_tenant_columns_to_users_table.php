<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenant users are scoped per tenant, so email must be unique per
        // tenant, not globally — the same person/email can be a customer at
        // one tenant and staff at another, or an owner running two accounts.
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->after('id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true)->after('password');
            // true = access to all of the tenant's branches; false = scoped via branch_user pivot.
            $table->boolean('all_branches')->default(false)->after('is_active');

            $table->unique(['tenant_id', 'email']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'email']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn(['is_active', 'all_branches']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });
    }
};
