<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'pending_payment' as the new default tenant status (self-signup no
 * longer auto-activates a free trial — see OnboardTenant). 'trial' stays in
 * the enum even though nothing writes it anymore, since existing rows may
 * already hold that value (CLAUDE.md §53 — never drop a value real data may
 * depend on). Raw ALTER TABLE because Laravel's fluent enum ->change()
 * requires doctrine/dbal, which isn't installed here.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tenants MODIFY status ENUM('pending_payment','trial','active','suspended','cancelled') DEFAULT 'pending_payment'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tenants MODIFY status ENUM('trial','active','suspended','cancelled') DEFAULT 'trial'");
    }
};
