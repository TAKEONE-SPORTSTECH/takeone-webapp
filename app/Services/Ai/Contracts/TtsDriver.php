<?php

namespace App\Services\Ai\Contracts;

/**
 * Text-to-speech provider. Returns ready-to-play audio.
 *
 * @return array{mime:string,data:string}  mime type + raw binary audio bytes
 */
interface TtsDriver
{
    /**
     * @param  array<string,mixed>  $options  e.g. ['voice' => 'Kore']
     * @return array{mime:string,data:string}
     */
    public function synthesize(string $text, array $options = []): array;
}
