<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Tiny offline QR helper (wraps bacon/bacon-qr-code) used across the app so we
 * never depend on an external QR service. Renders inline-ready SVG.
 */
class Qr
{
    /**
     * Render $data as an SVG string, ready to embed inline in HTML.
     * The XML prolog is stripped so the markup drops straight into a page.
     *
     * `$dark` and `$light` recolour the symbol — the modules and the field they
     * sit on. They default to the only combination every scanner has always
     * agreed on, so every existing caller is untouched.
     *
     * ── Before inverting one ────────────────────────────────────────────────
     * A decoder finds a symbol by looking for DARK modules on a LIGHT field.
     * Inverting that is legal, and phone cameras and Google Lens read it, but
     * it is not universal — some library-based scanners only try one polarity.
     * So invert where a human has another way in (a printed code to type), and
     * do not where the QR is the only route.
     */
    public static function svg(
        string $data,
        int $size = 256,
        int $margin = 1,
        string $dark = '#000000',
        string $light = '#ffffff',
    ): string {
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin),
            new SvgImageBackEnd
        );

        $svg = (new Writer($renderer))->writeString($data);

        // Drop any XML prolog so the markup inlines anywhere — keep from "<svg".
        $pos = strpos($svg, '<svg');
        $svg = $pos === false ? $svg : substr($svg, $pos);

        // Only ever hex, and only ever into an attribute we write ourselves —
        // this string is echoed unescaped into a page.
        $hex = fn (string $c, string $fallback) => preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : $fallback;
        $dark = $hex($dark, '#000000');
        $light = $hex($light, '#ffffff');

        // The back end writes the quiet-zone rect explicitly and leaves the
        // modules to default black, so the field is a replace and the modules
        // are an attribute added to the one path.
        if ($light !== '#ffffff') {
            $svg = str_replace('fill="#ffffff"', 'fill="'.$light.'"', $svg);
        }

        if ($dark !== '#000000') {
            $svg = str_replace('<path fill-rule="evenodd"', '<path fill="'.$dark.'" fill-rule="evenodd"', $svg);
        }

        return $svg;
    }
}
