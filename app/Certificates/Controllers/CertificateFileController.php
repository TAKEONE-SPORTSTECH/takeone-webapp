<?php

namespace App\Certificates\Controllers;

use Ahl\Certificates\CertificateService;
use App\Http\Controllers\Controller;
use App\Support\StoragePath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The certificate as a file. Every request re-asks the policy (the holder once
 * approved; the organiser always), and the response is never cacheable by a
 * shared cache.
 *
 *   pdf    the stored PDF of an approved certificate (?download=1 to save it);
 *          a draft or a revoked one is drawn fresh with its stamp across it
 *   image  the same page as a PNG, for a phone's viewer (an Android WebView
 *          cannot show a PDF inline)
 *   print  the organiser's copy for the paper it will be printed on — text
 *          only when the design is for pre-printed paper
 */
class CertificateFileController extends Controller
{
    use Concerns;

    private const HEADERS = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

    public function pdf(Request $request, string $cert, CertificateService $service)
    {
        $c = $this->certificate($cert, $request);
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($service->pdfContents($c), 200, self::HEADERS + [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$c->certificate_no.'.pdf"',
        ]);
    }

    public function image(Request $request, string $cert, CertificateService $service)
    {
        $c = $this->certificate($cert, $request);

        // An approved page does not change, so its picture is drawn once. Derived,
        // so under cache/ (StoragePath), keyed by what would change it.
        $png = null;
        if ($c->isApproved()) {
            $path = StoragePath::file('cache/certificates', $c->uuid.'-'.substr(sha1($c->status->value.$c->updated_at), 0, 10), 'png');
            $png = Storage::disk('local')->exists($path) ? Storage::disk('local')->get($path) : null;
            if (! $png) {
                $png = $service->imageContents($c, 110);
                Storage::disk('local')->put($path, $png);
            }
        }
        $png ??= $service->imageContents($c, 110);

        return response($png, 200, self::HEADERS + [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="'.$c->certificate_no.'.png"',
        ]);
    }

    public function print(Request $request, string $cert, CertificateService $service)
    {
        $c = $this->certificate($cert, $request, 'print');

        return response($service->printContents($c), 200, self::HEADERS + [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$c->certificate_no.'-print.pdf"',
        ]);
    }
}
