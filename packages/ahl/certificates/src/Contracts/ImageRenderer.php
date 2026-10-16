<?php

namespace Ahl\Certificates\Contracts;

/** Optional, for a PdfRenderer that can also draw the page as a PNG (previews, thumbnails). */
interface ImageRenderer
{
    public function renderImage(string $view, array $data, string $orientation = 'landscape', string|array $paper = 'a4', int $dpi = 96): string;
}
