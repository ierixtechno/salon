<?php

namespace App\Jobs;

use App\Domain\Platform\Actions\RunBackup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Behind Platform > Backups > "Run backup now". Queued rather than run
 * inside the web request: a backup of a real-sized database easily
 * outlasts the 30-60 second PHP limit typical of shared hosting, and the
 * request would just time out. The cron-driven queue worker picks this up
 * within a minute (CLAUDE.md §43).
 *
 * One attempt only — RunBackup already records and alerts on its own
 * failures, and never throws for an ordinary one, so there is nothing for
 * the queue to retry (and a blind retry of a half-finished backup would
 * just pile up partial files).
 */
class RunBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public ?int $platformAdminId = null) {}

    public function handle(RunBackup $backup): void
    {
        $backup->execute('manual', $this->platformAdminId);
    }
}
