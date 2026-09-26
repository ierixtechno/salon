<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Platform\Models\PlatformInvoice;
use App\Http\Controllers\Controller;
use App\Domain\Platform\Support\RenderPlatformInvoicePdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

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
        $invoice->load(['tenant', 'plan']);

        return view('platform.platform-invoices.show', ['invoice' => $invoice]);
    }

    public function pdf(PlatformInvoice $invoice, RenderPlatformInvoicePdf $renderer): Response
    {
        return response($renderer->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str_replace(['/', '\\'], '-', $invoice->invoice_number).'.pdf"',
        ]);
    }
}
