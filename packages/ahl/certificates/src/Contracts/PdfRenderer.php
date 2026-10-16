<?php

namespace Ahl\Certificates\Contracts;

interface PdfRenderer
{
    /** Render a Blade view to PDF bytes. */
    public function render(string $view, array $data, string $orientation = 'landscape', string|array $paper = 'a4'): string;
}
