<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Models\BackupRun;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Support\PlatformHealth;
use App\Http\Controllers\Controller;
use App\Jobs\RunBackupJob;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Super Admin only (routes/platform.php's auth:platform group). A backup
 * archive is a complete copy of every tenant's data, so every download is
 * audit-logged (CLAUDE.md §47) and goes through this authorised endpoint —
 * the archives themselves live outside the web root (config backup.path).
 */
class BackupController extends Controller
{
    public function index(): View
    {
        return view('platform.backups.index', [
            'runs' => BackupRun::with('platformAdmin')->latest('started_at')->paginate(20),
            'lastSuccess' => BackupRun::where('status', BackupRun::SUCCESS)->latest('finished_at')->first(),
            'isStale' => PlatformHealth::backupIsStale(),
            'staleAfterHours' => (int) config('backup.stale_after_hours'),
            'retentionDays' => (int) config('backup.retention_days'),
        ]);
    }

    public function run(): RedirectResponse
    {
        $admin = Auth::guard('platform')->user();

        RunBackupJob::dispatch($admin->id);

        PlatformAuditLog::record($admin, 'backup.requested', 'BackupRun', null, null);

        return back()->with('status', 'Backup started — it runs in the background and should appear below within a minute or two. Refresh this page to check.');
    }

    public function download(BackupRun $backup): BinaryFileResponse
    {
        // Looked up by run id, never by a client-supplied file name (no
        // path-traversal surface); the name inside comes from our own
        // record and is basename()d again in BackupRun::path().
        abort_unless($backup->succeeded() && $backup->fileExists(), 404);

        PlatformAuditLog::record(Auth::guard('platform')->user(), 'backup.downloaded', 'BackupRun', $backup->id, null, [
            'file' => $backup->file_name,
        ]);

        return response()->download($backup->path(), $backup->file_name);
    }
}
