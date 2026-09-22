<?php

namespace App\Console\Commands;

use App\Domain\Platform\Models\ErrorLog;
use Illuminate\Console\Command;

/**
 * Error-log housekeeping. Only errors that have gone quiet are removed
 * (judged by last_seen_at, not first) — something still happening today
 * is never pruned however old its first occurrence.
 */
class PruneErrorLogs extends Command
{
    protected $signature = 'error-logs:prune';

    protected $description = 'Delete error-log entries not seen for ERROR_LOG_RETENTION_DAYS (default 90).';

    public function handle(): int
    {
        $days = (int) config('logging.error_log_retention_days', 90);

        $deleted = ErrorLog::where('last_seen_at', '<', now()->subDays($days))->delete();

        $this->info("Pruned {$deleted} error-log entr".($deleted === 1 ? 'y' : 'ies')." not seen for {$days}+ days.");

        return self::SUCCESS;
    }
}
