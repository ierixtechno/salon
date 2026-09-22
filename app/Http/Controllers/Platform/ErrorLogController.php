<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Models\ErrorLog;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Platform\Models\Tenant;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Super Admin only (routes/platform.php's auth:platform group) — the log
 * holds internal file paths and stack frames, which tenants must never see
 * (CLAUDE.md §40). Nothing here is tenant-scoped on purpose: it is the
 * operator's cross-tenant view.
 */
class ErrorLogController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['open', 'resolved', 'all'], true) ? $request->query('status') : 'open';
        $search = trim((string) $request->query('q'));

        $logs = ErrorLog::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('request_id', $search)
                        ->orWhere('exception_class', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhere('path', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('last_seen_at')
            ->paginate(25)
            ->withQueryString();

        return view('platform.error-logs.index', [
            'logs' => $logs,
            'status' => $status,
            'search' => $search,
            'openCount' => ErrorLog::where('status', ErrorLog::OPEN)->count(),
            'resolvedCount' => ErrorLog::where('status', ErrorLog::RESOLVED)->count(),
            'newLast24h' => ErrorLog::where('first_seen_at', '>=', now()->subDay())->count(),
            'tenantNames' => Tenant::whereIn('id', $logs->pluck('tenant_id')->filter()->unique())->pluck('name', 'id'),
        ]);
    }

    public function show(ErrorLog $errorLog): View
    {
        return view('platform.error-logs.show', [
            'log' => $errorLog->load('resolvedBy'),
            'tenant' => $errorLog->tenant_id ? Tenant::find($errorLog->tenant_id) : null,
        ]);
    }

    public function resolve(ErrorLog $errorLog): RedirectResponse
    {
        $admin = Auth::guard('platform')->user();

        $errorLog->forceFill([
            'status' => ErrorLog::RESOLVED,
            'resolved_at' => now(),
            'resolved_by' => $admin->id,
        ])->save();

        PlatformAuditLog::record($admin, 'error_log.resolved', 'ErrorLog', $errorLog->id, $errorLog->tenant_id);

        return back()->with('status', 'Marked as resolved. It will reopen by itself if it happens again.');
    }

    public function reopen(ErrorLog $errorLog): RedirectResponse
    {
        $errorLog->forceFill([
            'status' => ErrorLog::OPEN,
            'resolved_at' => null,
            'resolved_by' => null,
        ])->save();

        PlatformAuditLog::record(Auth::guard('platform')->user(), 'error_log.reopened', 'ErrorLog', $errorLog->id, $errorLog->tenant_id);

        return back()->with('status', 'Reopened.');
    }
}
