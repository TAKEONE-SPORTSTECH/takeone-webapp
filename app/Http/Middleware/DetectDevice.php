<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lightweight user-agent based device detection.
 *
 * Shares a boolean $isMobile flag with every view so controllers/layouts can
 * serve SEPARATE mobile vs desktop Blade files (see CLAUDE.md "Mobile / Desktop
 * Separation" rule). Phone detection is what matters here — the mobile-first
 * Business experience targets phones.
 */
class DetectDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $ua = (string) $request->header('User-Agent');

        $isMobile = $ua !== '' && (bool) preg_match(
            '/Mobile|Android|iP(hone|od)|IEMobile|BlackBerry|webOS|Opera Mini|Windows Phone/i',
            $ua
        );

        // iPad / Android tablets are treated as desktop (wider layout fits better).
        if ($isMobile && preg_match('/iPad|Tablet/i', $ua)) {
            $isMobile = false;
        }

        $request->attributes->set('is_mobile', $isMobile);
        View::share('isMobile', $isMobile);

        return $next($request);
    }
}
