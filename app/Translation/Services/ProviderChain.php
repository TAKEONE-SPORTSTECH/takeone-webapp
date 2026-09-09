<?php

namespace App\Translation\Services;

use App\Models\AiProvider;
use App\Services\Ai\AiManager;
use App\Services\Ai\Contracts\TextDriver;
use App\Services\Ai\Drivers\OllamaTextDriver;
use App\Services\Ai\Drivers\OpenAiTextDriver;

/**
 * Which models may write a translation, in what order.
 *
 * ⚠️ This class is the whole answer to "we are not tied to one AI company".
 *
 * Translation asks a CHAIN, not a provider. Each link is tried in turn until
 * one answers usefully, so a dead key, an expired card, a rate limit or a
 * frontier lab having an outage costs a slightly plainer sentence rather than
 * the feature. Nobody reading a poster ever learns it happened.
 *
 * The chain assembles itself from what is configured under Admin → AI
 * Providers, so switching the platform onto a local model is an admin action,
 * not a deployment:
 *
 *   1. every ENABLED text provider, default first — the admin screen's order;
 *   2. then the built-in Ollama server (config/copilot.php), which runs on our
 *      own hardware and costs nothing, as the last resort.
 *
 * Pin the order explicitly with TRANSLATION_PROVIDERS when you want the cheap
 * local model to go FIRST and the paid one to be the safety net. See
 * config/translation.php, which also lists which self-hosted servers the
 * `openai` driver reaches (LM Studio, vLLM, llama.cpp, Groq, OpenRouter, …) —
 * nearly all of them speak that dialect, so "any local model" is a base_url.
 */
class ProviderChain
{
    /**
     * Where the super-admin's pick is stored. A platform setting rather than
     * config, because the whole point is that it changes from the browser at
     * any moment without a deploy.
     */
    public const PRIMARY_SETTING = 'translation.primary_provider_id';

    public function __construct(private AiManager $ai) {}

    /** The provider currently doing the translating, or null for automatic. */
    public static function primaryId(): ?int
    {
        $id = (int) \App\Models\PlatformSetting::get(self::PRIMARY_SETTING, 0);

        return $id > 0 ? $id : null;
    }

    /** Choose the model that translates. Null returns to automatic order. */
    public static function setPrimary(?int $providerId): void
    {
        \App\Models\PlatformSetting::set(self::PRIMARY_SETTING, (int) $providerId);
    }

    /**
     * The links to try, in order.
     *
     * @return array<int, Link>
     */
    public function links(): array
    {
        $links = [];

        foreach ($this->providers() as $provider) {
            try {
                $links[] = new Link(
                    driver: $this->ai->text($provider->id),
                    label: $provider->name ?: $provider->driver,
                    driverName: (string) $provider->driver,
                    model: (string) ($provider->model ?: '—'),
                    providerId: $provider->id,
                );
            } catch (\Throwable $e) {
                /*
                 * A provider row that cannot be turned into a driver — an
                 * unknown driver name, a half-filled form — is SKIPPED, not
                 * fatal. One bad row in the admin screen must not take
                 * translation down for every language.
                 */
                continue;
            }
        }

        if (config('translation.fallback_to_local', true)) {
            $links[] = $this->localFallback();
        }

        return $links;
    }

    /**
     * The configured providers, in the order they should be tried.
     *
     * @return array<int, AiProvider>
     */
    private function providers(): array
    {
        /*
         * The super-admin's choice, made in the browser and effective on the
         * next translation — Admin → AI Providers → "Which model translates".
         *
         * It goes FIRST and the rest of the chain follows it as fallbacks, so
         * "switch from Claude to my own Ollama" is one click and no deploy,
         * and switching back is the same click. A stored id whose provider has
         * since been deleted or disabled is ignored rather than fatal.
         */
        $primary = (int) \App\Models\PlatformSetting::get(self::PRIMARY_SETTING, 0);

        if ($primary > 0) {
            $chosen = AiProvider::query()->enabled()->modality('text')->find($primary);

            if ($chosen) {
                $rest = AiProvider::query()->enabled()->modality('text')
                    ->where('id', '!=', $primary)
                    ->orderByDesc('is_default')->orderBy('id')->get()->all();

                return array_merge([$chosen], $rest);
            }
        }

        $pinned = config('translation.providers');

        if (is_array($pinned) && $pinned !== []) {
            /*
             * An explicit order. Fetched by id and re-sorted into the order
             * given, because `whereIn` returns rows in whatever order the
             * database likes — and the order IS the configuration here.
             *
             * An id that no longer exists is simply absent: deleting a provider
             * must never be able to take translation down.
             */
            $rows = AiProvider::query()->enabled()->modality('text')
                ->whereIn('id', $pinned)->get()->keyBy('id');

            return array_values(array_filter(array_map(
                fn ($id) => $rows->get($id),
                $pinned,
            )));
        }

        // The admin screen's own order: the default first, then the rest.
        return AiProvider::query()->enabled()->modality('text')
            ->orderByDesc('is_default')->orderBy('id')->get()->all();
    }

    /**
     * The built-in Ollama server — ours, local, free, and last.
     *
     * Built directly rather than through AiManager::textFallback() so it can be
     * labelled honestly in a log and in `translate:compare` output.
     */
    private function localFallback(): Link
    {
        return new Link(
            driver: new OllamaTextDriver(
                (string) config('copilot.base_url'),
                (string) config('copilot.model'),
                (float) config('translation.temperature', 0.3),
                (int) config('translation.timeout', 90),
            ),
            label: 'Built-in local model',
            driverName: 'ollama',
            model: (string) config('copilot.model'),
            providerId: null,
        );
    }

    /**
     * Does this driver accept a "reply in JSON" instruction at the API level,
     * rather than only in the prompt?
     *
     * Only the two that reach self-hosted models. Anthropic and Gemini are
     * asked in the prompt and honour it; the parser tolerates a code fence from
     * anybody, so a driver that ignores the flag still works.
     */
    public static function supportsJsonMode(TextDriver $driver): bool
    {
        return $driver instanceof OllamaTextDriver || $driver instanceof OpenAiTextDriver;
    }
}
