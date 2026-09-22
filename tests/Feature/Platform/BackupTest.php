<?php

use App\Domain\Platform\Actions\RunBackup;
use App\Domain\Platform\Backup\DatabaseDumper;
use App\Domain\Platform\Models\BackupRun;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Jobs\RunBackupJob;
use App\Mail\NotificationMail;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Backups are worthless unless they restore — so beyond checking the
 * archive is built, the dumper is proven by actually restoring a dump into
 * a second database and comparing it to the first (see the "round trip"
 * test). Those tests use throw-away scratch databases, never the shared
 * test database: creating/dropping tables there would implicitly commit
 * RefreshDatabase's transaction.
 */
$GLOBALS['backup_test_dirs'] = [];

function backupTestDir(): string
{
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'salon-backup-test-'.bin2hex(random_bytes(4));
    config(['backup.path' => $dir]);
    $GLOBALS['backup_test_dirs'][] = $dir;

    return $dir;
}

afterEach(function () {
    foreach ($GLOBALS['backup_test_dirs'] as $dir) {
        File::deleteDirectory($dir);
    }
    $GLOBALS['backup_test_dirs'] = [];
});

/** Server-level PDO (no database selected) for creating scratch databases, or null if we can't. */
function backupTestServerPdo(): ?PDO
{
    $c = config('database.connections.'.config('database.default'));

    try {
        return new PDO("mysql:host={$c['host']};port={$c['port']}", $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Throwable) {
        return null;
    }
}

function backupTestScratchConnection(PDO $server, string $name): Connection
{
    $server->exec("DROP DATABASE IF EXISTS `{$name}`");
    $server->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    config(["database.connections.{$name}" => array_merge(config('database.connections.'.config('database.default')), ['database' => $name])]);
    DB::purge($name);

    return DB::connection($name);
}

test('a dump restores into a fresh database exactly — awkward values, NULLs, binary, JSON, foreign keys', function () {
    $server = backupTestServerPdo();
    if (! $server) {
        $this->markTestSkipped('No MySQL server-level access available to create scratch databases.');
    }

    try {
        $source = backupTestScratchConnection($server, 'salon_backup_src_test');

        $source->unprepared('CREATE TABLE parents (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL) ENGINE=InnoDB');
        $source->unprepared('CREATE TABLE children (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            parent_id INT UNSIGNED NOT NULL,
            note TEXT NULL,
            amount DECIMAL(12,2) NOT NULL,
            payload JSON NULL,
            raw BLOB NULL,
            seen_at DATETIME NULL,
            CONSTRAINT children_parent_fk FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB');
        // Structure only by design — contents must NOT be carried into a backup.
        $source->unprepared('CREATE TABLE sessions (id VARCHAR(255) PRIMARY KEY, payload TEXT NOT NULL)');
        $source->unprepared('CREATE TABLE password_reset_tokens (email VARCHAR(255) PRIMARY KEY, token VARCHAR(255) NOT NULL)');

        $nasty = "O'Brien's \"Salon\" \\ back\\slash ; \n; DROP TABLE parents;\n line2 \r\n emoji 💇‍♀️ ₹1,999 -- not a comment";

        $source->table('parents')->insert([['id' => 1, 'name' => $nasty], ['id' => 2, 'name' => 'Plain']]);
        $source->table('children')->insert([
            ['parent_id' => 1, 'note' => null, 'amount' => '2358.82', 'payload' => json_encode(['a' => "x'y", 'b' => [1, 2]]), 'raw' => "\x00\x01\xFF binary \x7F", 'seen_at' => '2027-03-01 10:11:12'],
            ['parent_id' => 1, 'note' => '', 'amount' => '0.00', 'payload' => null, 'raw' => '', 'seen_at' => null],
            ['parent_id' => 2, 'note' => "tab\there", 'amount' => '99999999.99', 'payload' => null, 'raw' => null, 'seen_at' => null],
        ]);
        $source->table('sessions')->insert(['id' => 's1', 'payload' => 'SECRET SESSION DATA']);
        $source->table('password_reset_tokens')->insert(['email' => 'a@b.test', 'token' => 'SECRET RESET TOKEN']);

        // Enough rows to force several multi-row INSERT batches.
        $bulk = [];
        for ($i = 0; $i < 450; $i++) {
            $bulk[] = ['parent_id' => 2, 'note' => "bulk {$i}", 'amount' => '1.00', 'payload' => null, 'raw' => null, 'seen_at' => null];
        }
        $source->table('children')->insert($bulk);

        $handle = fopen('php://temp', 'w+b');
        $meta = (new DatabaseDumper($source))->dump($handle);
        rewind($handle);
        $sql = stream_get_contents($handle);
        fclose($handle);

        expect($sql)->toEndWith(DatabaseDumper::COMPLETION_MARKER."\n");
        expect($meta['tables'])->toBe(4);
        expect($meta['table_rows']['children'])->toBe(453);
        // Structure kept, contents deliberately dropped.
        expect($sql)->toContain('CREATE TABLE `sessions`');
        expect($sql)->not->toContain('SECRET SESSION DATA');
        expect($sql)->not->toContain('SECRET RESET TOKEN');
        expect($meta['table_rows']['sessions'])->toBe(0);

        // Restore into a brand-new database and compare.
        $target = backupTestScratchConnection($server, 'salon_backup_dst_test');
        $targetPdo = $target->getPdo();
        $statements = preg_split('/;\n/', preg_replace('/^--.*$/m', '', $sql));
        foreach ($statements as $statement) {
            if (trim($statement) !== '') {
                $targetPdo->exec($statement);
            }
        }

        foreach (['parents', 'children'] as $table) {
            $a = $source->table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
            $b = $target->table($table)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
            expect($b)->toBe($a);
        }

        // The awkward name survived byte for byte, and the blob too.
        expect($target->table('parents')->where('id', 1)->value('name'))->toBe($nasty);
        expect(bin2hex($target->table('children')->where('parent_id', 1)->orderBy('id')->value('raw')))->toBe(bin2hex("\x00\x01\xFF binary \x7F"));

        // Foreign keys were recreated and still enforced after the load.
        expect(fn () => $target->table('children')->insert(['parent_id' => 999, 'amount' => '1.00']))->toThrow(QueryException::class);
        expect($target->table('sessions')->count())->toBe(0);
    } finally {
        $server->exec('DROP DATABASE IF EXISTS `salon_backup_src_test`');
        $server->exec('DROP DATABASE IF EXISTS `salon_backup_dst_test`');
        DB::purge('salon_backup_src_test');
        DB::purge('salon_backup_dst_test');
    }
});

test('a backup produces a verified zip with the dump and a manifest, records the run, and leaves no plaintext dump behind', function () {
    $dir = backupTestDir();

    $run = app(RunBackup::class)->execute('scheduled');

    expect($run->status)->toBe(BackupRun::SUCCESS);
    expect($run->trigger)->toBe('scheduled');
    expect($run->table_count)->toBeGreaterThan(10);
    expect($run->size_bytes)->toBeGreaterThan(0);
    expect($run->file_name)->toStartWith('backup-')->toEndWith('.zip');
    expect($run->finished_at)->not->toBeNull();

    $zip = new ZipArchive;
    expect($zip->open($dir.'/'.$run->file_name, ZipArchive::CHECKCONS))->toBeTrue();

    $sql = $zip->getFromName('database.sql');
    expect($sql)->toContain('CREATE TABLE `users`')
        ->toContain('CREATE TABLE `tenants`')
        ->toContain("SET time_zone = '+00:00'")
        ->toEndWith(DatabaseDumper::COMPLETION_MARKER."\n");

    $manifest = json_decode($zip->getFromName('manifest.json'), true);
    expect($manifest['tables'])->toBe($run->table_count);
    expect($manifest)->toHaveKeys(['created_at_utc', 'database', 'php_version', 'laravel_version', 'restore_hint']);
    $zip->close();

    // The intermediate plaintext dump must never be left on disk.
    expect(glob($dir.'/.dump-*.sql'))->toBe([]);
    // Defence in depth on top of being outside the web root.
    expect(file_get_contents($dir.'/.htaccess'))->toContain('Deny from all');
});

test('uploaded files are included, but transient tenant data exports are not', function () {
    Storage::fake('local');
    Storage::disk('local')->put('expense-attachments/receipt.pdf', 'PDFBYTES');
    Storage::disk('local')->put('data-exports/tenant-9.zip', 'PII BUNDLE');
    $dir = backupTestDir();

    $run = app(RunBackup::class)->execute('manual');

    $zip = new ZipArchive;
    $zip->open($dir.'/'.$run->file_name);
    expect($zip->getFromName('uploads/expense-attachments/receipt.pdf'))->toBe('PDFBYTES');
    expect($zip->locateName('uploads/data-exports/tenant-9.zip'))->toBeFalse();
    $zip->close();
});

test('uploads can be switched off', function () {
    Storage::fake('local');
    Storage::disk('local')->put('expense-attachments/receipt.pdf', 'PDFBYTES');
    config(['backup.include_uploads' => false]);
    $dir = backupTestDir();

    $run = app(RunBackup::class)->execute();

    $zip = new ZipArchive;
    $zip->open($dir.'/'.$run->file_name);
    expect($zip->locateName('uploads/expense-attachments/receipt.pdf'))->toBeFalse();
    $zip->close();
});

test('a failed backup is recorded, emailed to every active Super Admin, and never throws', function () {
    Mail::fake();
    $adminA = PlatformAdmin::factory()->create(['is_active' => true]);
    $adminB = PlatformAdmin::factory()->create(['is_active' => true]);

    // A backup "folder" that is really a file, so it can never be created.
    $blocker = sys_get_temp_dir().DIRECTORY_SEPARATOR.'salon-backup-blocker-'.bin2hex(random_bytes(4));
    file_put_contents($blocker, 'x');
    config(['backup.path' => $blocker.DIRECTORY_SEPARATOR.'nested']);

    try {
        $run = app(RunBackup::class)->execute('scheduled');
    } finally {
        @unlink($blocker);
    }

    expect($run->status)->toBe(BackupRun::FAILED);
    expect($run->error_message)->not->toBeEmpty();
    expect($run->file_name)->toBeNull();

    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $m) => $m->mailSubject === 'Backup FAILED'
        && $m->hasTo($adminA->email) && $m->hasTo($adminB->email));
});

class BackupTestFailingDumper extends DatabaseDumper
{
    public function dump($handle): array
    {
        fwrite($handle, '-- half written
SET NAMES utf8mb4;
');

        throw new RuntimeException('simulated dump failure');
    }
}

class BackupTestTruncatedDumper extends DatabaseDumper
{
    // Looks like it finished (returns normally) but never wrote the end marker —
    // exactly what a dump killed by a host time limit leaves behind.
    public function dump($handle): array
    {
        fwrite($handle, '-- partial
SET NAMES utf8mb4;
');

        return ['tables' => 1, 'rows' => 0, 'table_rows' => []];
    }
}

test('a failed backup never prunes the backups you already have, and leaves no plaintext dump behind', function () {
    Mail::fake();
    $dir = backupTestDir();
    File::ensureDirectoryExists($dir);
    foreach (range(1, 5) as $i) {
        touch("{$dir}/backup-old-{$i}.zip", now()->subDays(60)->getTimestamp());
    }
    $this->app->bind(DatabaseDumper::class, BackupTestFailingDumper::class);

    $run = app(RunBackup::class)->execute('scheduled');

    expect($run->status)->toBe(BackupRun::FAILED);
    expect($run->error_message)->toContain('simulated dump failure');
    // 60 days old and past retention — but with no fresh backup to replace
    // them they are all you have, so none may be pruned.
    expect(count(glob("{$dir}/backup-old-*.zip")))->toBe(5);
    // The half-written plaintext copy of the database must not survive either.
    expect(glob($dir.'/.dump-*.sql'))->toBe([]);
    expect(glob($dir.'/backup-2*.zip'))->toBe([]);
});

test('a dump that stopped early is caught, not archived as if it were a good backup', function () {
    Mail::fake();
    $admin = PlatformAdmin::factory()->create(['is_active' => true]);
    $dir = backupTestDir();
    $this->app->bind(DatabaseDumper::class, BackupTestTruncatedDumper::class);

    $run = app(RunBackup::class)->execute('scheduled');

    expect($run->status)->toBe(BackupRun::FAILED);
    expect($run->error_message)->toContain('incomplete');
    expect(glob($dir.'/backup-*.zip'))->toBe([]);
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $m) => $m->mailSubject === 'Backup FAILED' && $m->hasTo($admin->email));
});

test('after a successful backup, old archives are pruned but the newest few are always kept', function () {
    $dir = backupTestDir();
    File::ensureDirectoryExists($dir);
    config(['backup.retention_days' => 14, 'backup.always_keep_latest' => 3]);

    // Six ancient archives, distinguishable by age.
    foreach (range(1, 6) as $i) {
        touch("{$dir}/backup-ancient-{$i}.zip", now()->subDays(30 + $i)->getTimestamp());
    }
    // One recent one that must survive on age alone.
    touch("{$dir}/backup-recent.zip", now()->subDays(2)->getTimestamp());

    app(RunBackup::class)->execute('scheduled');

    $names = array_map('basename', glob("{$dir}/backup-*.zip"));

    // New backup + recent (2 days) + the single newest ancient one = the 3 always kept;
    // every other ancient archive is past retention and gone.
    expect($names)->toHaveCount(3);
    expect($names)->toContain('backup-recent.zip');
    expect($names)->toContain('backup-ancient-1.zip');
});

test('the backup:run command reports success and the queued job runs a manual backup for the requesting admin', function () {
    backupTestDir();

    $this->artisan('backup:run')->expectsOutputToContain('Backup complete')->assertSuccessful();
    expect(BackupRun::where('trigger', 'scheduled')->where('status', 'success')->count())->toBe(1);

    $admin = PlatformAdmin::factory()->create();
    (new RunBackupJob($admin->id))->handle(app(RunBackup::class));

    $manual = BackupRun::where('trigger', 'manual')->firstOrFail();
    expect($manual->status)->toBe('success');
    expect($manual->platform_admin_id)->toBe($admin->id);
});

test('the daily backup, error digest and error prune are scheduled', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('backup:run')
        ->expectsOutputToContain('error-logs:send-digest')
        ->expectsOutputToContain('error-logs:prune');
});

test('Super Admin sees a red warning when there has never been a backup, and green once there is one', function () {
    $admin = PlatformAdmin::factory()->create();

    $this->actingAs($admin, 'platform')->get('/platform/backups')
        ->assertOk()
        ->assertSee('No successful backup has been made yet');

    BackupRun::create([
        'trigger' => 'scheduled', 'status' => 'success', 'file_name' => 'backup-x.zip', 'size_bytes' => 2048,
        'table_count' => 40, 'row_count' => 1000, 'started_at' => now()->subMinutes(5), 'finished_at' => now()->subMinutes(4),
    ]);

    $this->actingAs($admin, 'platform')->get('/platform/backups')
        ->assertOk()
        ->assertSee('Last successful backup')
        ->assertDontSee('No successful backup');
});

test('a backup older than the staleness threshold turns the warning red again', function () {
    $admin = PlatformAdmin::factory()->create();
    BackupRun::create([
        'trigger' => 'scheduled', 'status' => 'success', 'file_name' => 'backup-x.zip', 'size_bytes' => 2048,
        'table_count' => 40, 'row_count' => 1000, 'started_at' => now()->subDays(3), 'finished_at' => now()->subDays(3),
    ]);

    $this->actingAs($admin, 'platform')->get('/platform/backups')
        ->assertOk()
        ->assertSee('No successful backup in over 26 hours');
});

test('"Run backup now" queues the job for the logged-in admin and is audit-logged', function () {
    Queue::fake();
    $admin = PlatformAdmin::factory()->create();

    $this->actingAs($admin, 'platform')->post('/platform/backups')->assertRedirect();

    Queue::assertPushed(RunBackupJob::class, fn (RunBackupJob $job) => $job->platformAdminId === $admin->id);
    expect(PlatformAuditLog::where('action', 'backup.requested')->where('platform_admin_id', $admin->id)->exists())->toBeTrue();
});

test('a successful backup can be downloaded by Super Admin, and the download is audit-logged', function () {
    backupTestDir();
    $admin = PlatformAdmin::factory()->create();
    $run = app(RunBackup::class)->execute('scheduled');

    $response = $this->actingAs($admin, 'platform')->get("/platform/backups/{$run->id}/download");

    $response->assertOk()->assertDownload($run->file_name);
    expect(PlatformAuditLog::where('action', 'backup.downloaded')->where('entity_id', $run->id)->exists())->toBeTrue();
});

test('a failed run, or one whose file has been pruned, cannot be downloaded', function () {
    backupTestDir();
    $admin = PlatformAdmin::factory()->create();

    $failed = BackupRun::create(['trigger' => 'scheduled', 'status' => 'failed', 'error_message' => 'x', 'started_at' => now()]);
    $pruned = BackupRun::create(['trigger' => 'scheduled', 'status' => 'success', 'file_name' => 'backup-gone.zip', 'started_at' => now()]);

    $this->actingAs($admin, 'platform')->get("/platform/backups/{$failed->id}/download")->assertNotFound();
    $this->actingAs($admin, 'platform')->get("/platform/backups/{$pruned->id}/download")->assertNotFound();
});

test('a tampered file name in a backup record cannot reach outside the backup folder', function () {
    $dir = backupTestDir();
    File::ensureDirectoryExists($dir);
    $admin = PlatformAdmin::factory()->create();

    // A real file one level up that a naive path join would happily serve.
    $secret = dirname($dir).DIRECTORY_SEPARATOR.'salon-secret-'.bin2hex(random_bytes(4)).'.txt';
    file_put_contents($secret, 'TOP SECRET');

    try {
        $run = BackupRun::create([
            'trigger' => 'scheduled', 'status' => 'success', 'started_at' => now(),
            'file_name' => '../'.basename($secret),
        ]);

        $this->actingAs($admin, 'platform')->get("/platform/backups/{$run->id}/download")->assertNotFound();
    } finally {
        @unlink($secret);
    }
});

test('tenant users and guests cannot reach the backup pages or downloads', function () {
    $owner = onboard();
    $run = BackupRun::create(['trigger' => 'scheduled', 'status' => 'success', 'file_name' => 'backup-x.zip', 'started_at' => now()]);

    $this->get('/platform/backups')->assertRedirect(route('platform.login'));
    $this->get("/platform/backups/{$run->id}/download")->assertRedirect(route('platform.login'));
    $this->post('/platform/backups')->assertRedirect(route('platform.login'));

    $this->actingAs($owner, 'web')->get('/platform/backups')->assertRedirect(route('platform.login'));
    $this->actingAs($owner, 'web')->post('/platform/backups')->assertRedirect(route('platform.login'));
});
