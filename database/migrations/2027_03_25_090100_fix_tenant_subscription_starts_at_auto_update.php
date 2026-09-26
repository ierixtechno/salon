<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * MySQL gives the first NOT NULL `timestamp` column of a table an implicit
     * ON UPDATE CURRENT_TIMESTAMP. That made `starts_at` silently reset to "now"
     * on ANY update of a subscription row (status change, renewal, extra
     * branches), corrupting the billing-cycle start that proration relies on.
     * Redefine it as a plain value.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE tenant_subscriptions MODIFY starts_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(): void
    {
        // Intentionally left as-is: restoring the auto-update would reintroduce the bug.
    }
};
