<?php

namespace App\Services\Ai\Contracts;

/**
 * Speech-to-text provider. Takes base64-encoded audio + its MIME type and
 * returns the transcript text.
 */
interface SttDriver
{
    /**
     * @param  array<string,mixed>  $options  e.g. ['language' => 'ar']
     */
    public function transcribe(string $audioBase64, string $mime, array $options = []): string;
}
