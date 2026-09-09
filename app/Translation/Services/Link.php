<?php

namespace App\Translation\Services;

use App\Services\Ai\Contracts\TextDriver;

/**
 * One model the translator may try: the driver, and enough about it to name in
 * a log, a stored row, or a side-by-side comparison.
 *
 * The label matters more than it looks. `content_translations` records WHICH
 * model wrote every row, and that is the only way to answer "why does the
 * Portuguese read badly" without guessing, or to find and re-run everything an
 * older model produced after switching providers.
 */
class Link
{
    public function __construct(
        public readonly TextDriver $driver,
        /** What a person calls it: the provider row's name. */
        public readonly string $label,
        /** ollama | openai | anthropic | gemini — how it is reached. */
        public readonly string $driverName,
        /** The model id, as configured. */
        public readonly string $model,
        /** The ai_providers row, or null for the built-in local fallback. */
        public readonly ?int $providerId,
    ) {}

    /** Whether this driver takes a JSON instruction at the API level. */
    public function supportsJsonMode(): bool
    {
        return ProviderChain::supportsJsonMode($this->driver);
    }

    /** "Claude (Anthropic) · claude-opus-5" — for a log line or a table. */
    public function describe(): string
    {
        return $this->label.' · '.$this->model;
    }
}
