<?php

namespace App\Domain\Platform\Support;

use App\Domain\Platform\Models\BackupRun;
use App\Domain\Platform\Models\ErrorLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The two numbers behind the Platform sidebar's warning badges (open errors,
 * stale/missing backup).
 *
 * Every read is guarded: this runs on *every* Platform page, and the
 * tables behind it are created by migrations — if a release is uploaded
 * before `php artisan migrate` has been run, the sidebar must degrade to
 * "no badge", not turn every Super Admin page into a 500.
 */
class PlatformHealth
{
    public static function openErrorCount(): int
    {
        try {
            return ErrorLog::where('status', ErrorLog::OPEN)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /** Written every minute by the scheduler (bootstrap/app.php). */
    public const HEARTBEAT_KEY = 'scheduler:last-run';

    /**
     * True when the scheduler (the server's cron entry) has not run for 15
     * minutes — or has never run and we have been watching for 15 minutes.
     */
    public static function schedulerIsStale(): bool
    {
        try {
            $last = Cache::get(self::HEARTBEAT_KEY);
            $threshold = now()->subMinutes(15)->getTimestamp();

            if ($last === null) {
                // Never seen it run: only alarming once we have been watching for a while.
                return (int) Cache::rememberForever('scheduler:watch-started', fn () => now()->getTimestamp()) < $threshold;
            }

            return (int) $last < $threshold;
        } catch (Throwable) {
            return false;
        }
    }

    public static function freeDiskMb(): ?int
    {
        $free = @disk_free_space(storage_path());

        return $free === false ? null : (int) floor($free / 1048576);
    }

    /** Queued jobs that have sat unprocessed for over 30 minutes (database queue only). */
    public static function stuckQueueJobs(): int
    {
        try {
            if (config('queue.default') !== 'database') {
                return 0;
            }

            return DB::table(config('queue.connections.database.table', 'jobs'))
                ->whereNull('reserved_at')
                ->where('available_at', '<', now()->subMinutes(30)->getTimestamp())
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    public static function recentFailedJobs(): int
    {
        try {
            return DB::table(config('queue.failed.table', 'failed_jobs'))
                ->where('failed_at', '>=', now()->subDay())
                ->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * True when there has been no successful backup at all, or the newest
     * one is older than config('backup.stale_after_hours'). Tables not yet
     * migrated reads as "not stale" — nothing sensible to warn about yet,
     * and the migration itself is the fix.
     */
    public static function backupIsStale(): bool
    {
        try {
            $last = BackupRun::where('status', BackupRun::SUCCESS)->max('finished_at');

            return $last === null
                || now()->parse($last)->lt(now()->subHours((int) config('backup.stale_after_hours')));
        } catch (Throwable) {
            return false;
        }
    }
}
