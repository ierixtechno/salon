<?php

namespace App\Domain\Platform\Support;

use App\Domain\Platform\Models\Quotation;

/**
 * Renders a quotation as a PDF. The caller is responsible for authorizing
 * access to the quotation first (Super Admin, or the owning tenant).
 */
class RenderQuotationPdf
{
    public function render(Quotation $quotation): string
    {
        $quotation->loadMissing('tenant', 'plan.modules');

        return PlatformPdf::render('pdf.quotation', [
            'quotation' => $quotation,
            'supplier' => PlatformPdf::supplier(),
            'timezone' => config('platform.default_timezone', 'Asia/Kolkata'),
        ]);
    }
}
