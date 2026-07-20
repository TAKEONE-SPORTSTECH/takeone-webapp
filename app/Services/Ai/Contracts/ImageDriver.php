<?php

namespace App\Services\Ai\Contracts;

/**
 * An image-generation provider. Returns the generated image as a
 * `data:image/...;base64,...` URI so callers can hand it straight to
 * StoresBase64Images (which sniffs the real bytes and assigns the extension).
 */
interface ImageDriver
{
    /**
     * @param  array<string,mixed>  $options  e.g. ['size' => '1536x1024']
     */
    public function generate(string $prompt, array $options = []): string;
}
