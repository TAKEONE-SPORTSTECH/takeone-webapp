<?php

namespace App\Support;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
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
     */
    public static function svg(string $data, int $size = 256, int $margin = 1): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin),
            new SvgImageBackEnd()
        );

        $svg = (new Writer($renderer))->writeString($data);

        // Drop any XML prolog so the markup inlines anywhere — keep from "<svg".
        $pos = strpos($svg, '<svg');

        return $pos === false ? $svg : substr($svg, $pos);
    }
}
