<?php

namespace App\Domain\Platform\Support;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Shared PDF plumbing for platform documents (quotations, invoices). Pure
 * PHP (dompdf) — no binary needed, so it works on shared hosting.
 */
class PlatformPdf
{
    /** The app logo as a data URI, so the PDF needs no remote or file access to draw it. */
    public static function logoDataUri(): ?string
    {
        $path = public_path('images/brand-icon.png');

        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    /** Values every platform document header needs. */
    public static function supplier(): array
    {
        return [
            'name' => config('platform.brand_name'),
            'gstin' => config('platform.gstin'),
            'sac' => config('platform.gst_sac_code'),
            'state' => config('platform.state'),
            'logo' => self::logoDataUri(),
        ];
    }

    public static function render(string $view, array $data): string
    {
        $html = view($view, $data)->render();

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
