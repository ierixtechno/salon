<?php

namespace App\Console\Commands;

use App\Domain\Core\Models\DataExport;
use App\Domain\Core\Scopes\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * A completed export ZIP contains customer PII bundled across the whole
 * tenant (CLAUDE.md §34/§36) — it shouldn't sit on disk indefinitely.
 * Deletes the file and clears file_path/expires_at once past expiry; the
 * DataExport row itself is kept as a historical record of what was
 * exported, when, and by whom.
 */
class PruneExpiredDataExports extends Command
{
    protected $signature = 'data-exports:prune';

    protected $description = 'Delete expired tenant data export files from storage.';

    public function handle(): int
    {
        $expired = DataExport::withoutGlobalScope(TenantScope::class)
            ->where('status', 'completed')
            ->whereNotNull('file_path')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $export) {
            Storage::disk('local')->delete($export->file_path);
            $export->file_path = null;
            $export->save();
        }

        $this->info("Pruned {$expired->count()} expired data export(s).");

        return self::SUCCESS;
    }
}
