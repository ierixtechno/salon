<?php

namespace App\Console\Commands;

use App\Domain\Platform\Actions\RunBackup;
use App\Domain\Platform\Models\BackupRun;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

class RunScheduledBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Back up the database and uploaded files to a zip archive under storage/backups (runs daily via the scheduler; also safe to run by hand).';

    public function handle(RunBackup $backup): int
    {
        $run = $backup->execute('scheduled');

        if ($run->status !== BackupRun::SUCCESS) {
            $this->error("Backup failed: {$run->error_message}");

            return self::FAILURE;
        }

        $this->info("Backup complete: {$run->file_name} (".human_file_size($run->size_bytes).", {$run->table_count} tables, {$run->row_count} rows).");

        return self::SUCCESS;
    }
}
