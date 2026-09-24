<?php

namespace App\Federations\Support;

use App\Federations\Models\Federation;

/**
 * The federation's face in the browser tab. Its own logo when it has one;
 * otherwise a mark drawn from its short name in the dashboard's navy and gold
 * — and before anyone has signed in (no federation known yet), the generic
 * federation mark. Never the platform's icon: this is its own product.
 */
class FederationBrand
{
    public static function favicon(?Federation $f): string
    {
        if ($f && $f->logo && $f->isApproved()) {
            return route('federations.logo', $f->uuid).'?v='.substr(md5((string) $f->logo), 0, 8);
        }
        $text = $f ? mb_strtoupper(mb_substr(preg_replace('/[^\pL\pN]/u', '', (string) ($f->short_name ?: $f->name)), 0, 3)) : '';

        return 'data:image/svg+xml;charset=utf-8,'.rawurlencode(self::mark($text));
    }

    /** Navy rounded tile, gold bar — the dashboard's own mark — with up to three letters. */
    private static function mark(string $text): string
    {
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $size = mb_strlen($text) >= 3 ? 20 : 26;
        $inner = $text === ''
            ? '<rect x="17" y="30" width="30" height="5" rx="2.5" fill="#d8b25f"/>'
            : '<rect x="18" y="12" width="28" height="4" rx="2" fill="#d8b25f"/><text x="32" y="46" text-anchor="middle" font-family="Poppins,Arial,sans-serif" font-weight="700" font-size="'.$size.'" fill="#ffffff">'.$text.'</text>';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#0b132b"/>'.$inner.'</svg>';
    }
}
