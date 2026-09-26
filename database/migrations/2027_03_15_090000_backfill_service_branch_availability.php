<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Services created through the UI used to get no branch availability at
     * all, so they never appeared on the sale screen. Make every such
     * never-configured service available at its tenant's active branches.
     * A service that already has any branch row (even "unavailable") was
     * configured on purpose and is left alone.
     */
    public function up(): void
    {
        DB::statement('
            INSERT INTO service_branch (service_id, branch_id, is_available, created_at, updated_at)
            SELECT s.id, b.id, 1, NOW(), NOW()
            FROM services s
            JOIN branches b ON b.tenant_id = s.tenant_id AND b.is_active = 1
            WHERE NOT EXISTS (SELECT 1 FROM service_branch sb WHERE sb.service_id = s.id)
        ');
    }

    public function down(): void
    {
        // Data backfill only — nothing safe to undo.
    }
};
