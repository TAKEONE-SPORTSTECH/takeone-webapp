<?php

namespace Ahl\Certificates\Support;

use Ahl\Certificates\Contracts\ImageRenderer;
use Ahl\Certificates\Contracts\PdfRenderer;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * dompdf through barryvdh/laravel-dompdf. Bind your own renderer for another engine.
 *
 * Remote loading stays off: every image arrives as a data: URI and the only files dompdf
 * may open are the package's own fonts (the chroot). The font metrics cache goes to
 * certificates.font_cache, which must be writable by the web user.
 */
class DompdfRenderer implements ImageRenderer, PdfRenderer
{
    public function render(string $view, array $data, string $orientation = 'landscape', string $paper = 'a4'): string
    {
        return Pdf::setOptions($this->options())->loadView($view, $data)->setPaper($paper, $orientation)->output();
    }

    /**
     * The same page as a PNG, through dompdf's GD backend — the same layout engine, so a
     * preview cannot drift from the PDF. For screens that cannot show a PDF inline (an
     * Android WebView, a thumbnail).
     */
    public function renderImage(string $view, array $data, string $orientation = 'landscape', string $paper = 'a4', int $dpi = 96): string
    {
        $dompdf = new \Dompdf\Dompdf($this->options() + ['pdfBackend' => 'GD']);
        $dompdf->getOptions()->setDpi($dpi);
        $dompdf->loadHtml(view($view, $data)->render());
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * A ready-made page set (CertificateService::bundle) as one PDF. $dirs are extra
     * folders dompdf may read — the bundle's own scratch folder of shared pictures.
     */
    public function renderHtml(string $html, string $orientation = 'landscape', string $paper = 'a4', array $dirs = []): string
    {
        $options = $this->options();
        $options['chroot'] = array_merge($options['chroot'], $dirs);

        return Pdf::setOptions($options)->loadHTML($html)->setPaper($paper, $orientation)->output();
    }

    private function options(): array
    {
        $cache = (string) config('certificates.font_cache', storage_path('fonts'));
        if (! is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }

        return [
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'isFontSubsettingEnabled' => true,
            'dpi' => 96,
            'chroot' => [Fonts::dir()],
            'fontDir' => $cache,
            'fontCache' => $cache,
            'defaultFont' => 'Inter',
        ];
    }
}
