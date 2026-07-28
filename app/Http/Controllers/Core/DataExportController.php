<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Actions\RequestTenantDataExport;
use App\Domain\Core\Models\DataExport;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Deliberately Owner-only (see PermissionSeeder — data-export.request is
 * not granted to Manager/Staff in OnboardTenant): a full export bundles
 * customer PII across the whole tenant, a materially more sensitive
 * capability than viewing individual records or reports.
 */
class DataExportController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:data-export.request');
    }

    public function index(): View
    {
        return view('core.data-exports.index', [
            'exports' => DataExport::with('requestedBy')->latest()->paginate(20),
        ]);
    }

    public function store(RequestTenantDataExport $action): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        $action->execute($user->tenant_id, $user->id);

        return redirect()->route('data-exports.index')->with('status', "Export requested — we'll let you know when it's ready.");
    }

    public function download(DataExport $dataExport): StreamedResponse
    {
        abort_unless($dataExport->isDownloadable(), 404);

        return Storage::disk('local')->download($dataExport->file_path, "tenant-data-export-{$dataExport->id}.zip");
    }
}
