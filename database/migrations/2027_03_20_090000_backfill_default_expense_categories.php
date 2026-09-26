<?php

use App\Domain\Core\Support\DefaultExpenseCategories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tenants that never created an expense category could not record an
     * expense (empty Category dropdown). Give each of them the starter set.
     * A tenant that already has any category is left exactly as it is.
     */
    public function up(): void
    {
        $now = now();

        DB::table('tenants')->pluck('id')->each(function ($tenantId) use ($now) {
            if (DB::table('expense_categories')->where('tenant_id', $tenantId)->exists()) {
                return;
            }

            DB::table('expense_categories')->insert(array_map(fn ($name) => [
                'tenant_id' => $tenantId, 'name' => $name, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ], DefaultExpenseCategories::NAMES));
        });
    }

    public function down(): void
    {
        // Data backfill only — nothing safe to undo (tenants may have used them).
    }
};
