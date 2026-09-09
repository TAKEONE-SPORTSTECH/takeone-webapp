<?php

namespace App\Translation\Jobs;

use App\Translation\Contracts\TranslatableContent;
use App\Translation\Models\TranslationDocument;
use App\Translation\Services\ContentLocales;
use App\Translation\Services\TranslationAgent;
use App\Translation\Services\Translator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Writes one record into one language, once.
 *
 * ⚠️ Carries a morph ALIAS and an id, never a serialised model. A queued job
 * outlives the request that made it — the record can be edited, or deleted,
 * between dispatch and execution — and SerializesModels would resurrect a
 * snapshot of the row as it was, translating text that is no longer on the
 * page. Re-reading it here also means the job translates whatever the event
 * says at the moment it actually runs.
 */
class TranslateContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Two attempts, not three. A translation failure is almost never transient
     * in a way a retry fixes (a missing key, a refused prompt, a model that
     * cannot produce JSON), and the one case that IS transient — a timeout —
     * costs a full generation each time it is retried.
     */
    public int $tries = 2;

    public int $timeout = 180;

    /** Spread retries, so a provider having a bad minute is not hammered. */
    public array $backoff = [30, 120];

    public function __construct(
        public string $morphAlias,
        public int $recordId,
        public string $locale,
    ) {}

    /**
     * One job per record+language in flight. Belt and braces with the dispatch
     * lock in Translator::ensure(): that stops a hundred visitors queueing a
     * hundred jobs, this stops two jobs that did slip through from both writing
     * the same rows and both being billed for it.
     */
    public function uniqueId(): string
    {
        return $this->morphAlias.':'.$this->recordId.':'.$this->locale;
    }

    public function handle(Translator $translator, TranslationAgent $agent, ContentLocales $locales): void
    {
        $locale = $locales->normalise($this->locale);

        if ($locale === null) {
            return;   // a language we no longer serve; nothing to do, nothing to log
        }

        $record = $this->record();

        if (! $record instanceof TranslatableContent) {
            // Deleted between dispatch and execution. Nothing to write it on.
            return;
        }

        $this->mark($record, $locale, 'running');

        // Only what is missing or stale — a re-run after one edited field
        // translates one field. This is the whole cost model of the module.
        $pending = $translator->pending($record, $locale);

        if ($pending === []) {
            $this->mark($record, $locale, 'ready');
            $translator->forget($record, $locale);

            return;
        }

        try {
            $result = $agent->translate(
                $pending,
                $record->sourceLocale(),
                $locale,
                $record->translationContext(),
            );
        } catch (\Throwable $e) {
            /*
             * The message is truncated and the exception is NOT stored whole:
             * a provider's error body can echo the prompt back, and the prompt
             * carries the event's text. The full trace goes to the log, which
             * is not rendered to anyone.
             */
            Log::error('translation.failed', [
                'record' => $this->uniqueId(),
                'error' => $e->getMessage(),
            ]);

            $this->mark($record, $locale, 'failed', $this->publicError($e));
            $translator->forget($record, $locale);

            return;
        }

        $written = $this->store($record, $locale, $pending, $result);

        $translator->forget($record, $locale);

        /*
         * READY means READABLE, not perfect.
         *
         * Some fields come back refused — the agent's numeric check throws away
         * a division whose "60-" lost its bound, because a wrong division is
         * worse than an untranslated one. Observed on the very first real run,
         * on live data. Demanding every field would mean this language stayed
         * "failed" forever, every visitor's request would re-queue it after the
         * cool-off, and the same three fields would be refused again by the
         * same model on the same text — a bill for no change.
         *
         * So the bar is the page's IDENTITY: the title and the description, the
         * two fields that decide whether a reader can tell what this event is.
         * With those in hand the page reads as theirs, and the handful of
         * refused labels sit in the source language where the organiser can see
         * them and type the right words themselves.
         *
         * Uncached read — see the guard in Translator::for(): this runs in a
         * worker that may not be the web user, and must not create cache files.
         */
        $readable = $written > 0 && $this->coreCovered($translator, $record, $locale);

        $this->mark(
            $record,
            $locale,
            $readable ? 'ready' : 'failed',
            $readable ? null : 'Some parts of this page could not be translated.',
        );
    }

    /**
     * Are the fields that carry the page's identity in the reader's language?
     *
     * Deliberately a SHORT list. Every other field is a label beside a number
     * or one bullet of a list — legible in context even when it stays in the
     * source language — while a title and a description in the wrong language
     * mean the reader cannot tell what they are looking at.
     */
    private function coreCovered(Translator $translator, TranslatableContent&Model $record, string $locale): bool
    {
        // required(), not document(): a field already in the reader's language
        // is never translated, so demanding it here would mark the language
        // failed forever and re-queue the same job every hour.
        $document = $translator->required($record, $locale);
        $have = $translator->for($record, $locale, false)->all();

        foreach (['title', 'about'] as $core) {
            if (isset($document[$core]) && ! isset($have[$core])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Store the answer.
     *
     * Machine words only ever overwrite machine words — the guard lives in
     * TranslationDocument::mergeMachine, and it is what makes an organiser's
     * correction permanent. The write is a locked read-merge-save, because the
     * row carries every language and a plain save would drop a colleague's
     * work on a different one.
     *
     * @param  array<string,string>  $pending
     * @param  array{values: array<string,string>, provider: string, model: string}  $result
     */
    private function store(Model $record, string $locale, array $pending, array $result): int
    {
        $hashes = [];

        foreach ($pending as $field => $source) {
            $hashes[$field] = Translator::hash($source);
        }

        $written = 0;

        TranslationDocument::mutate(
            $record->getMorphClass(),
            (int) $record->getKey(),
            function (array $document) use ($locale, $result, $hashes, &$written) {
                [$document, $written] = TranslationDocument::mergeMachine(
                    $document,
                    $locale,
                    $result['values'],
                    $hashes,
                    mb_substr((string) ($result['provider'] ?? ''), 0, 64),
                    mb_substr((string) ($result['model'] ?? ''), 0, 120),
                );

                return $document;
            },
        );

        return $written;
    }

    /** The queue gave up entirely — say so on the row somebody is watching. */
    public function failed(\Throwable $e): void
    {
        Log::error('translation.job_failed', ['record' => $this->uniqueId(), 'error' => $e->getMessage()]);

        if (($record = $this->record()) instanceof Model) {
            $this->mark($record, $this->locale, 'failed', $this->publicError($e));
        }
    }

    private function record(): ?Model
    {
        $class = Relation::getMorphedModel($this->morphAlias) ?: $this->morphAlias;

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return $class::query()->find($this->recordId);
    }

    /** Record where a language stands, on the record's own document. */
    private function mark(Model $record, string $locale, string $status, ?string $error = null): void
    {
        TranslationDocument::mutate(
            $record->getMorphClass(),
            (int) $record->getKey(),
            fn (array $document) => TranslationDocument::setStatus($document, $locale, $status, $error),
        );
    }

    /**
     * What a person may see. Never the provider's body, never a URL, never a
     * key — an error string on this row is rendered in an admin panel.
     */
    private function publicError(\Throwable $e): string
    {
        $message = $e->getMessage();

        return match (true) {
            /*
             * ⚠️ Billing comes back as a 400, not a 402 or a 403.
             *
             * "Your credit balance is too low" is an `invalid_request_error`,
             * which reads like a bug in our request and is not one. It cost
             * hours: every Claude call failed, the chain quietly fell through
             * to the local model, that model was then overwhelmed by parallel
             * jobs, and the visible symptom was empty output — with nothing
             * anywhere saying "the account is out of money".
             */
            str_contains($message, 'credit balance') =>
                'The AI account is out of credit. Top it up, or switch the translator to a local model under Admin → AI Providers.',
            str_contains($message, 'HTTP 401'), str_contains($message, 'HTTP 403') =>
                'The AI provider rejected our credentials. Check the key under Admin → AI Providers.',
            str_contains($message, 'HTTP 429') =>
                'The AI provider is rate-limiting us. It will be retried.',
            str_contains($message, 'No image provider'), str_contains($message, 'not available yet') =>
                'No text AI provider is configured. Add one under Admin → AI Providers.',
            default => 'The translation could not be completed.',
        };
    }
}
