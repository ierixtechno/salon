<?php

namespace App\Domain\Platform\Support;

use App\Domain\Platform\Models\BackupRun;
use App\Domain\Platform\Models\ErrorLog;
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
