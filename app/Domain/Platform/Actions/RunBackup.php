<?php

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Backup\DatabaseDumper;
use App\Domain\Platform\Models\BackupRun;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * One complete backup: database dump + uploaded files, zipped, verified,
 * recorded, old ones pruned (CLAUDE.md §67).
 *
 * Never throws for an ordinary failure — it records the failed run, emails
 * every Super Admin, reports the exception into the error log, and hands
 * the failed BackupRun back to the caller. That single place for failure
 * handling is deliberate: both the scheduled command and the queued
 * "Run backup now" job get identical alerting without duplicating it (or
 * double-reporting the same exception).
 *
 * The dump reads through its own dedicated connection, in one
 * REPEATABLE READ snapshot: every table is captured at the same instant
 * (so a payment being recorded mid-backup can't leave an invoice without
 * its quotation), and the UTC time-zone pin never leaks into the
 * application's own connection.
 */
class RunBackup
{
    private const DUMP_CONNECTION = 'backup_dump';

    public function __construct(private readonly NotifyPlatformAdmins $notifyAdmins) {}

    public function execute(string $trigger = 'scheduled', ?int $platformAdminId = null): BackupRun
    {
        // A large database on shared hosting can exceed PHP's default
        // execution limit; harmlessly ignored where the host forbids it.
        @set_time_limit(0);

        $run = BackupRun::create([
            'trigger' => $trigger,
            'status' => BackupRun::RUNNING,
            'platform_admin_id' => $platformAdminId,
            'started_at' => now(),
        ]);

        $zipPath = null;
        $sqlPath = null;

        try {
            $directory = $this->prepareDirectory();
            $stamp = now()->format('Ymd-His');
            $zipPath = "{$directory}/backup-{$stamp}-{$run->id}.zip";
            $sqlPath = "{$directory}/.dump-{$run->id}.sql";

            $meta = $this->dumpDatabase($sqlPath);
            $this->buildArchive($zipPath, $sqlPath, $meta);
            clearstatcache();
            $this->verifyArchive($zipPath, $sqlPath);

            $run->update([
                'status' => BackupRun::SUCCESS,
                'file_name' => basename($zipPath),
                'size_bytes' => filesize($zipPath),
                'table_count' => $meta['tables'],
                'row_count' => $meta['rows'],
                'finished_at' => now(),
            ]);

            $this->prune($directory);
        } catch (Throwable $e) {
            if ($zipPath && is_file($zipPath)) {
                @unlink($zipPath);
            }

            $run->update([
                'status' => BackupRun::FAILED,
                'file_name' => null,
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
                'finished_at' => now(),
            ]);

            report($e);

            $this->notifyAdmins->execute(
                subject: 'Backup FAILED',
                body: "The {$trigger} backup did not complete.\n\nReason: {$e->getMessage()}\n\n"
                    ."Until this is fixed there is no fresh backup of your data. Details and history:\n"
                    .route('platform.backups.index'),
            );
        } finally {
            // The intermediate .sql is a full plaintext copy of the
            // database — never leave it lying around, success or failure.
            if ($sqlPath && is_file($sqlPath)) {
                @unlink($sqlPath);
            }
        }

        return $run->fresh();
    }

    /**
     * @return array{tables: int, rows: int, table_rows: array<string, int>}
     */
    private function dumpDatabase(string $sqlPath): array
    {
        $connection = $this->openSnapshotConnection();
        $handle = fopen($sqlPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Could not create the backup file — check that the backup folder is writable.');
        }

        try {
            // Resolved through the container (not `new`) so tests can swap
            // in a dumper that fails or truncates, to prove the failure
            // paths below genuinely catch it.
            $meta = app()->makeWith(DatabaseDumper::class, ['connection' => $connection])->dump($handle);
            $connection->getPdo()->exec('COMMIT');
        } finally {
            fclose($handle);
            DB::purge(self::DUMP_CONNECTION);
        }

        // A dump that stopped early (killed mid-run, host limits) still
        // leaves a file — check it actually reached the end.
        if (! $this->endsWithCompletionMarker($sqlPath)) {
            throw new RuntimeException('The database dump is incomplete (it did not reach the end).');
        }

        return $meta;
    }

    private function openSnapshotConnection(): Connection
    {
        config(['database.connections.'.self::DUMP_CONNECTION => config('database.connections.'.config('database.default'))]);
        DB::purge(self::DUMP_CONNECTION);

        $connection = DB::connection(self::DUMP_CONNECTION);
        $connection->statement("SET time_zone = '+00:00'");

        $pdo = $connection->getPdo();
        $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

        return $connection;
    }

    /**
     * @param  array{tables: int, rows: int, table_rows: array<string, int>}  $meta
     */
    private function buildArchive(string $zipPath, string $sqlPath, array $meta): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup archive.');
        }

        $zip->addFile($sqlPath, 'database.sql');
        $zip->addFromString('manifest.json', json_encode([
            'created_at_utc' => gmdate('c'),
            'database' => config('database.connections.'.config('database.default').'.database'),
            'tables' => $meta['tables'],
            'rows' => $meta['rows'],
            'table_rows' => $meta['table_rows'],
            'includes_uploads' => (bool) config('backup.include_uploads'),
            'app_env' => config('app.env'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'restore_hint' => 'Import database.sql into an EMPTY database, then copy uploads/ into storage/app/private/. See docs/DEPLOYMENT.md > Restore.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        if (config('backup.include_uploads')) {
            $disk = Storage::disk('local');

            foreach ($disk->allFiles() as $relative) {
                // Transient PII bundles — expire on their own, and copying
                // them into every backup just spreads personal data around.
                if (str_starts_with($relative, 'data-exports/')) {
                    continue;
                }

                // A file that can't be added (unreadable, vanished mid-run)
                // is a hole in the backup — fail loudly, don't skip it.
                if (! $zip->addFile($disk->path($relative), 'uploads/'.$relative)) {
                    throw new RuntimeException("Could not add uploaded file to the backup: {$relative}");
                }
            }
        }

        // close() is where the compression and writing actually happen —
        // a false here (disk full, unreadable file) is a real failure.
        if (! $zip->close()) {
            throw new RuntimeException('Could not finish writing the backup archive — the disk may be full.');
        }
    }

    /**
     * Re-open the finished archive from disk and check it: a backup that
     * can't be read back is not a backup.
     */
    private function verifyArchive(string $zipPath, string $sqlPath): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('The finished backup archive failed its integrity check.');
        }

        $entry = $zip->statName('database.sql');
        $zip->close();

        if ($entry === false || $entry['size'] !== filesize($sqlPath) || $entry['size'] === 0) {
            throw new RuntimeException('The backup archive does not contain a complete database dump.');
        }
    }

    private function endsWithCompletionMarker(string $sqlPath): bool
    {
        $handle = fopen($sqlPath, 'rb');
        if ($handle === false) {
            return false;
        }

        fseek($handle, -256, SEEK_END);
        $tail = stream_get_contents($handle) ?: '';
        fclose($handle);

        return str_contains($tail, DatabaseDumper::COMPLETION_MARKER);
    }

    private function prepareDirectory(): string
    {
        $directory = rtrim(str_replace('\\', '/', config('backup.path')), '/');

        File::ensureDirectoryExists($directory, 0750);

        // Belt and braces on top of the folder already being outside the
        // web root: if someone ever points BACKUP_PATH somewhere
        // web-reachable, Apache still refuses to serve it.
        $htaccess = "{$directory}/.htaccess";
        if (! is_file($htaccess)) {
            file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }

        if (! is_writable($directory)) {
            throw new RuntimeException("The backup folder is not writable: {$directory}");
        }

        return $directory;
    }

    /**
     * Only ever runs after a *successful* backup, and always keeps the
     * newest few regardless of age (config backup.always_keep_latest) — a
     * run of failures must never prune you down to no backups at all.
     */
    private function prune(string $directory): void
    {
        $files = glob("{$directory}/backup-*.zip") ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        $cutoff = now()->subDays((int) config('backup.retention_days'))->getTimestamp();

        foreach (array_slice($files, (int) config('backup.always_keep_latest')) as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
