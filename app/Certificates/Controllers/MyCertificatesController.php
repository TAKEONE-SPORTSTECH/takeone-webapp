<?php

namespace App\Certificates\Controllers;

use Ahl\Certificates\Certificates as Engine;
use App\Certificates\Support\CertificatePresenter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A member's own certificates — approved ones, and revoked ones saying so.
 * Resolved from the session, never from a URL parameter: there is no
 * "somebody else's list" to ask for.
 */
class MyCertificatesController extends Controller
{
    use Concerns;

    public function index(Request $request, CertificatePresenter $presenter)
    {
        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'cert::mine.mobile' : 'cert::mine.desktop', [
            'certificates' => $this->rows($request, $presenter),
        ]);
    }

    public function list(Request $request, CertificatePresenter $presenter): JsonResponse
    {
        return response()->json(['success' => true, 'certificates' => $this->rows($request, $presenter)]);
    }

    private function rows(Request $request, CertificatePresenter $presenter): array
    {
        $locale = $this->locale();

        return Engine::model()::query()
            ->forRecipient($request->user())
            ->where('status', '!=', 'draft')
            ->with('context', 'template')
            ->orderByDesc('issued_on')->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($c) => $presenter->mine($c, $locale))
            ->all();
    }
}
