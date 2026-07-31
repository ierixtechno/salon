<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Models\PlatformInvoice;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Read-only — a PlatformInvoice is only ever produced by PayQuotation,
 * never created or edited directly (CLAUDE.md §45).
 */
class PlatformInvoiceController extends Controller
{
    public function index(): View
    {
        return view('platform.platform-invoices.index', [
            'invoices' => PlatformInvoice::with(['tenant', 'plan'])->latest('paid_at')->paginate(20),
        ]);
    }

    public function show(PlatformInvoice $invoice): View
    {
        $invoice->load(['tenant', 'plan', 'quotation']);

        return view('platform.platform-invoices.show', ['invoice' => $invoice]);
    }
}
