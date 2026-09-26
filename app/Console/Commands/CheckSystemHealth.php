<?php

namespace App\Console\Commands;

use App\Domain\Platform\Actions\NotifyPlatformAdmins;
use App\Domain\Platform\Support\PlatformHealth;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Hourly self-check that emails Super Admin when something that quietly
 * degrades over time needs attention: no recent good backup, disk nearly
 * full, the queue not draining (mail/WhatsApp piling up unsent), or jobs
 * failing. Each problem is emailed at most once per day until it clears —
 * a repeating alert gets ignored.
 *
 * It cannot report its own death: if the server's cron stops, nothing here
 * runs. That case is covered by the scheduler heartbeat shown on the
 * Platform pages and the optional external ping (HEALTHCHECK_PING_URL —
 * see docs/DEPLOYMENT.md).
 */
class CheckSystemHealth extends Command
{
    protected $signature = 'health:check';

    protected $description = 'Email Super Admin when backups, disk space, the queue or failed jobs need attention (each problem at most once a day).';

    public function handle(NotifyPlatformAdmins $notify): int
    {
        $problems = collect();

        if (PlatformHealth::backupIsStale()) {
            $problems->put('backup', 'No successful backup in the last '.config('backup.stale_after_hours').' hours. Check Platform > Backups.');
        }

        $freeMb = PlatformHealth::freeDiskMb();
        if ($freeMb !== null && $freeMb < (int) config('platform.health.min_free_disk_mb')) {
            $problems->put('disk', "Disk space is running low: about {$freeMb} MB free (alert threshold ".config('platform.health.min_free_disk_mb').' MB). Backups, uploads and logs will start failing when it runs out.');
        }

        $stuck = PlatformHealth::stuckQueueJobs();
        if ($stuck > 0) {
            $problems->put('queue', "{$stuck} queued job(s) (emails, WhatsApp, exports) have been waiting over 30 minutes — the queue is not being processed. Check the cron entry.");
        }

        $failed = PlatformHealth::recentFailedJobs();
        if ($failed > 0) {
            $problems->put('failed-jobs', "{$failed} background job(s) failed in the last 24 hours. See Platform > Error log for the cause.");
        }

        $fresh = $problems->filter(fn (string $text, string $key) => Cache::add('health-alert:'.$key.':'.today()->toDateString(), true, now()->addDay()));

        if ($fresh->isEmpty()) {
            $this->info($problems->isEmpty() ? 'All checks passed.' : 'Problems found, already reported today.');

            return self::SUCCESS;
        }

        try {
            $notify->execute(
                subject: 'StyloBiz needs attention: '.$fresh->count().' issue(s)',
                body: "The automatic health check found:\n\n- ".$fresh->implode("\n- ")."\n\nPlatform console:\n".route('platform.dashboard'),
            );
        } catch (Throwable $e) {
            report($e);
        }

        $this->warn($fresh->count().' problem(s) reported.');

        return self::SUCCESS;
    }
}
