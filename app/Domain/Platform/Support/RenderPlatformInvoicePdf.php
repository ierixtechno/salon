<?php

namespace App\Domain\Platform\Support;

use App\Domain\Platform\Models\PlatformInvoice;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders a platform (subscription) invoice as a PDF. Pure PHP (dompdf) —
 * no wkhtmltopdf/Chrome binary, so it works on shared hosting. The caller
 * is responsible for authorizing access to the invoice first.
 */
class RenderPlatformInvoicePdf
{
    public function render(PlatformInvoice $invoice): string
    {
        $invoice->loadMissing('tenant', 'plan');

        $html = view('pdf.platform-invoice', [
            'invoice' => $invoice,
            'supplier' => [
                'name' => config('platform.brand_name'),
                'gstin' => config('platform.gstin'),
                'sac' => config('platform.gst_sac_code'),
                'state' => config('platform.state'),
            ],
            'timezone' => config('platform.default_timezone', 'Asia/Kolkata'),
        ])->render();

        // dompdf writes font metrics/temp files; keep them under storage/
        // (writable everywhere) rather than its vendor directory.
        $workDir = storage_path('app/dompdf');
        if (! is_dir($workDir)) {
            @mkdir($workDir, 0755, true);
        }

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans'); // has the ₹ glyph
        $options->set('fontDir', $workDir);
        $options->set('fontCache', $workDir);
        $options->set('tempDir', $workDir);
        $options->set('chroot', base_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }
}
