<?php

namespace App\Domain\Platform\Support;

use App\Domain\Platform\Models\PlatformInvoice;

/**
 * Renders a platform (subscription) invoice as a PDF. The caller is
 * responsible for authorizing access to the invoice first.
 */
class RenderPlatformInvoicePdf
{
    public function render(PlatformInvoice $invoice): string
    {
        $invoice->loadMissing('tenant', 'plan');

        return PlatformPdf::render('pdf.platform-invoice', [
            'invoice' => $invoice,
            'supplier' => PlatformPdf::supplier(),
            'timezone' => config('platform.default_timezone', 'Asia/Kolkata'),
        ]);
    }
}
