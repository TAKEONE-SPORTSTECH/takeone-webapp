<?php

namespace App\Translation\Services;

use App\Ai\Models\AiProvider;
use App\Members\Models\User;
use App\Members\Models\UserNotification;
use App\Translation\Jobs\TranslateInterfaceLocale;
use App\Translation\Translations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Keeps translation working without anybody watching it.
 *
 * Every failure seen in the logs so far fell into one of a few shapes, and
 * each gets one answer here:
 *
 *   A translator is DOWN        probe it. Nothing the app can do about a server
 *   (server stopped, bound to   it does not own, so it waits — and the moment
 *   localhost, credit gone)     the probe answers, the runs it broke resume.
 *                               A person hears about it once, not every pass.
 *
 *   An Ollama server is up but  ask it to pull the model (a reinstalled server
 *   lacks the model             comes back empty). Rate-limited.
 *
 *   A run is wedged "running"   a killed worker leaves that behind forever, and
 *                               the screen then refuses to start it again.
 *                               Reset it to failed so it can be resumed.
 *
 *   A run failed, or finished   resume it — same language, same tier, gaps only.
 *   with strings still English  Runs are resumable by design (rows are written
 *                               as they go), so this costs only what is still
 *                               missing. Capped per day, and stopped as soon
 *                               as a resume makes no progress: a string a
 *                               model refuses every time is not transient.
 *
 *   Queued work nobody picks up no worker serves the queue. Reported.
 *
 * ⚠️ What it NEVER does: change a provider's settings, touch a key, resume a
 * REWRITE (that restarts from the top and pays twice), translate content
 * nobody asked for, or reach into a machine over SSH. Content translations
 * already retry themselves on the next visit after an hour's cool-off
 * (Translator::ensure), so they are left to that.
 */
class TranslationDoctor
{
    public const REPORT_KEY = 'translation-doctor:report';

    /** A run longer than the job's own timeout plus slack is not running. */
    private const STUCK_AFTER_MINUTES = 100;

    /** Queued translation work older than this, unreserved: no worker. */
    private const QUEUE_STALE_MINUTES = 15;

    /** Set per run: no paid provider can be reached, so no spending cap. */
    private bool $selfHostedOnly = false;

    public function __construct(private ContentLocales $locales) {}

    /**
     * Diagnose, and — when $fix — repair. Returns the report it also stores.
     *
     * @return array<string, mixed>
     */
    public function run(bool $fix = true): array
    {
        $actions = [];
        $incidents = [];

        $providers = $this->probeProviders($fix, $actions, $incidents);

        // Something in the chain can plausibly answer: a probe that came back
        // healthy, or a hosted provider we cannot probe for free (Claude,
        // Gemini…) and so do not assume is dead.
        $canTranslate = collect($providers)->contains(
            fn ($p) => in_array($p['state'], ['ok', 'unprobed'], true)
        );

        /*
         * Every translator that can answer is self-hosted Ollama: a retry costs
         * nothing but GPU time, so the daily cap on resumes does not apply.
         * The no-progress guard still does — re-asking for strings a model
         * refuses every time is a loop, not a fix.
         */
        $this->selfHostedOnly = $canTranslate && collect($providers)
            ->filter(fn ($p) => in_array($p['state'], ['ok', 'unprobed'], true))
            ->every(fn ($p) => $p['driver'] === 'ollama');

        if (! $canTranslate) {
            $incidents['no_translator'] = 'No translator is answering. Every language run will fail until one does.';
        }

        $runs = $this->healRuns($fix, $canTranslate, $actions, $incidents);

        $queue = $this->checkQueue($incidents);

        $report = [
            'checked_at' => now()->toIso8601String(),
            'fixing' => $fix,
            'can_translate' => $canTranslate,
            'self_hosted_only' => $this->selfHostedOnly,
            'providers' => $providers,
            'runs' => $runs,
            'queue' => $queue,
            'actions' => $actions,
            'incidents' => array_values($incidents),
        ];

        if ($fix) {
            $this->alert($incidents);
            Cache::forever(self::REPORT_KEY, $report);

            foreach ($actions as $action) {
                Log::info('translation.doctor_fixed', ['action' => $action]);
            }
        }

        return $report;
    }

    /** The last stored report, or null when the doctor has never run. */
    public static function lastReport(): ?array
    {
        $report = Cache::get(self::REPORT_KEY);

        return is_array($report) ? $report : null;
    }

    // ── Providers ───────────────────────────────────────────────────────────

    /**
     * @return array<int, array{label:string, driver:string, model:string, state:string, detail:?string}>
     */
    private function probeProviders(bool $fix, array &$actions, array &$incidents): array
    {
        $targets = [];

        foreach (AiProvider::query()->enabled()->modality('text')->orderByDesc('is_default')->orderBy('id')->get() as $p) {
            $targets[] = [
                'label' => (string) ($p->name ?: $p->driver),
                'driver' => (string) $p->driver,
                'model' => (string) $p->model,
                'base_url' => (string) $p->base_url,
            ];
        }

        // Every extra Ollama lane a run is split across is a translator too:
        // a lane that is down fails its half of every run.
        $laneModel = collect($targets)->firstWhere('driver', 'ollama')['model'] ?? null;

        foreach ((array) config('translation.ollama_lanes', []) as $i => $url) {
            if ($laneModel && ! collect($targets)->contains(fn ($t) => rtrim($t['base_url'], '/') === $url)) {
                $targets[] = ['label' => 'Ollama lane '.($i + 1), 'driver' => 'ollama', 'model' => $laneModel, 'base_url' => $url];
            }
        }

        if (config('translation.fallback_to_local', true)) {
            $targets[] = [
                'label' => 'Built-in local model',
                'driver' => 'ollama',
                'model' => (string) config('copilot.model'),
                'base_url' => (string) config('copilot.base_url'),
            ];
        }

        $out = [];

        foreach ($targets as $t) {
            $row = ['label' => $t['label'], 'driver' => $t['driver'], 'model' => $t['model'], 'state' => 'unprobed', 'detail' => null];

            if ($this->unfit($t['model'])) {
                // InterfaceAgent refuses it anyway — not a fault, not a translator.
                $row['state'] = 'refused';
                $row['detail'] = 'Not a translation model; skipped by design.';
                $out[] = $row;

                continue;
            }

            if ($t['driver'] === 'ollama' && $t['base_url'] !== '') {
                $row = array_merge($row, $this->probeOllama($t, $fix, $actions));

                if ($row['state'] !== 'ok') {
                    $incidents['provider:'.md5($t['base_url'].$t['model'])] =
                        "{$t['label']} ({$t['model']}): {$row['detail']}";
                }
            }

            $out[] = $row;
        }

        return $out;
    }

    /** @return array{state:string, detail:?string} */
    private function probeOllama(array $t, bool $fix, array &$actions): array
    {
        $base = rtrim($t['base_url'], '/');

        try {
            $response = Http::timeout((int) config('translation.autofix.probe_timeout', 5))
                ->acceptJson()->get($base.'/api/tags');
        } catch (\Throwable $e) {
            return [
                'state' => 'unreachable',
                'detail' => 'Server not reachable ('.$this->reason($e->getMessage()).'). If it is running, check it listens on the network and not only on 127.0.0.1 (OLLAMA_HOST=0.0.0.0:11434).',
            ];
        }

        if ($response->failed()) {
            return ['state' => 'unreachable', 'detail' => 'Server answered HTTP '.$response->status().'.'];
        }

        $installed = collect((array) $response->json('models', []))
            ->map(fn ($m) => $this->tagged((string) ($m['name'] ?? $m['model'] ?? '')))
            ->filter()->all();

        if (in_array($this->tagged($t['model']), $installed, true)) {
            return ['state' => 'ok', 'detail' => null];
        }

        if (! $fix || ! config('translation.autofix.pull_models', true)) {
            return ['state' => 'model_missing', 'detail' => 'The model is not installed on the server.'];
        }

        // One pull attempt per server+model every six hours: a pull that fails
        // (disk full, unknown model name) fails the same way on the next pass.
        if (! Cache::add('translation-doctor:pull:'.md5($base.'|'.$t['model']), 1, now()->addHours(6))) {
            return ['state' => 'model_missing', 'detail' => 'The model is not installed; a download was already attempted recently.'];
        }

        try {
            $pull = Http::timeout((int) config('translation.autofix.pull_timeout', 1800))
                ->acceptJson()->asJson()
                ->post($base.'/api/pull', ['model' => $t['model'], 'stream' => false]);

            if ($pull->successful()) {
                $actions[] = "Downloaded the missing model {$t['model']} onto {$t['label']}.";

                return ['state' => 'ok', 'detail' => 'Model was missing and has been downloaded.'];
            }

            return ['state' => 'model_missing', 'detail' => 'The model is not installed and the download failed (HTTP '.$pull->status().').'];
        } catch (\Throwable $e) {
            return ['state' => 'model_missing', 'detail' => 'The model is not installed and the download failed ('.$this->reason($e->getMessage()).').'];
        }
    }

    // ── Interface runs ──────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function healRuns(bool $fix, bool $canTranslate, array &$actions, array &$incidents): array
    {
        $rows = [];

        foreach (array_keys($this->locales->all()) as $locale) {
            if ($locale === 'en') {
                continue;
            }

            $status = TranslateInterfaceLocale::status($locale);
            $state = $status['state'] ?? 'idle';

            if ($state === 'idle') {
                continue;
            }

            $tier = in_array($status['tier'] ?? null, ['event', 'visitor', 'member', 'staff', 'all'], true)
                ? $status['tier'] : 'event';
            $rewrite = (bool) ($status['rewrite'] ?? false);
            $name = $this->locales->name($locale);

            $row = ['locale' => $locale, 'state' => $state, 'tier' => $tier, 'verdict' => 'fine'];

            // Wedged: marked running far longer than any run can last.
            if ($state === 'running') {
                $started = strtotime((string) ($status['started_at'] ?? '')) ?: null;

                if ($started === null || $started > now()->subMinutes(self::STUCK_AFTER_MINUTES)->getTimestamp()) {
                    $row['verdict'] = 'running';
                    $rows[] = $row;

                    continue;
                }

                $row['verdict'] = 'stuck';

                if ($fix) {
                    Cache::put(TranslateInterfaceLocale::statusKey($locale), [
                        'state' => 'failed',
                        'started_at' => null,
                        'finished_at' => now()->toIso8601String(),
                        'complete' => false,
                        'tier' => $tier,
                        'rewrite' => $rewrite,
                    ], now()->addHours(3));
                    $actions[] = "Reset the {$name} run, which was stuck as running.";
                    $state = 'failed';
                }
            }

            $broken = $state === 'failed' || ($state === 'done' && ($status['complete'] ?? null) === false);

            if (! $broken) {
                $rows[] = $row;

                continue;
            }

            $row['verdict'] = $this->resume($locale, $name, $tier, $rewrite, $state === 'done', $fix, $canTranslate, $actions, $incidents);
            $rows[] = $row;
        }

        return $rows;
    }

    private function resume(string $locale, string $name, string $tier, bool $rewrite, bool $finished, bool $fix, bool $canTranslate, array &$actions, array &$incidents): string
    {
        if ($rewrite) {
            // Resuming a rewrite redoes every string from the top.
            $incidents['rewrite:'.$locale] = "The {$name} rewrite did not finish. Not resumed automatically — a rewrite restarts from the beginning; start it again from Languages if you still want it.";

            return 'needs_person';
        }

        if (! $canTranslate) {
            return 'waiting_for_translator';
        }

        $missing = $this->missing($locale, $tier);

        if ($missing === 0) {
            return 'complete';
        }

        /*
         * A run that FINISHED (it was not interrupted) and still made no
         * progress since the last automatic resume: what is left is refused
         * every time. Stop spending on it. A run that FAILED is different — a
         * translator dropping out mid-run — and only counts against the cap.
         */
        $lastKey = 'translation-doctor:last-missing:'.$locale.':'.$tier;
        $last = Cache::get($lastKey);

        if ($finished && is_int($last) && $missing !== null && $missing >= $last) {
            $incidents['stalled:'.$locale] = "{$name} stopped making progress with {$missing} strings still missing. These are probably strings the model refuses; they need a person in Languages.";

            return 'stalled';
        }

        $countKey = 'translation-doctor:resumes:'.$locale.':'.now()->toDateString();
        $count = (int) Cache::get($countKey, 0);

        if (! $this->selfHostedOnly && $count >= (int) config('translation.autofix.max_resumes_per_day', 4)) {
            $incidents['capped:'.$locale] = "{$name} failed again after {$count} automatic retries today. It will try again tomorrow; check the translator under Admin → AI Providers.";

            return 'gave_up_today';
        }

        if (! $fix) {
            return 'would_resume';
        }

        Cache::put($countKey, $count + 1, now()->addDay());
        if ($missing !== null) {
            Cache::put($lastKey, $missing, now()->addDays(2));
        }

        // Marked running BEFORE the worker picks it up, so the next pass (and
        // the Languages screen) does not dispatch it a second time.
        Cache::put(TranslateInterfaceLocale::statusKey($locale), [
            'state' => 'running',
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'complete' => null,
            'tier' => $tier,
            'rewrite' => false,
        ], now()->addHours(3));

        TranslateInterfaceLocale::dispatch($locale, $tier, false);

        $actions[] = "Resumed the {$name} translation (".($missing ?? 'some')." strings still missing).";

        return 'resumed';
    }

    private function missing(string $locale, string $tier): ?int
    {
        try {
            $row = collect(Translations::interfaceCoverage($tier))->firstWhere('locale', $locale);

            return isset($row['missing']) ? (int) $row['missing'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Queue ───────────────────────────────────────────────────────────────

    /** @return array{connection:string, queue:string, waiting:int, stale:int} */
    private function checkQueue(array &$incidents): array
    {
        $connection = (string) (config('translation.queue') ?: config('queue.default'));
        $queue = config('translation.queue') ? 'translation' : (string) config("queue.connections.{$connection}.queue", 'default');
        $result = ['connection' => $connection, 'queue' => $queue, 'waiting' => 0, 'stale' => 0];

        if (config("queue.connections.{$connection}.driver") !== 'database') {
            return $result;
        }

        try {
            $table = DB::connection(config("queue.connections.{$connection}.connection"))
                ->table((string) config("queue.connections.{$connection}.table", 'jobs'))
                ->where('queue', $queue)
                ->where(fn ($q) => $q->where('payload', 'like', '%TranslateInterfaceLocale%')
                    ->orWhere('payload', 'like', '%TranslateContent%'));

            $result['waiting'] = (clone $table)->count();
            $result['stale'] = (clone $table)->whereNull('reserved_at')
                ->where('available_at', '<', now()->subMinutes(self::QUEUE_STALE_MINUTES)->getTimestamp())
                ->count();
        } catch (\Throwable) {
            return $result;
        }

        if ($result['stale'] > 0) {
            $incidents['queue'] = "{$result['stale']} translation jobs have waited over ".self::QUEUE_STALE_MINUTES." minutes on the \"{$queue}\" queue. No worker is picking them up.";
        }

        return $result;
    }

    // ── Telling a person ────────────────────────────────────────────────────

    /**
     * Notify super-admins once when a problem starts, and once when it clears.
     * Never every pass — a five-minute alarm nobody can silence is ignored.
     */
    private function alert(array $incidents): void
    {
        $open = (array) Cache::get('translation-doctor:open-incidents', []);
        $new = array_diff_key($incidents, $open);
        $cleared = array_diff_key($open, $incidents);

        Cache::forever('translation-doctor:open-incidents', $incidents);

        if ($new === [] && $cleared === []) {
            return;
        }

        $url = route('admin.languages.index');

        try {
            $admins = User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))->pluck('id');
        } catch (\Throwable) {
            return;
        }

        foreach ($admins as $id) {
            foreach ($new as $message) {
                UserNotification::notifyUser((int) $id, 'translation_alert', 'Translation needs attention', [
                    'body' => $message,
                    'action_url' => $url,
                    'icon' => 'bi-translate',
                ]);
            }

            foreach ($cleared as $message) {
                UserNotification::notifyUser((int) $id, 'translation_alert', 'Translation problem resolved', [
                    'body' => 'Fixed: '.$message,
                    'action_url' => $url,
                    'icon' => 'bi-check-circle',
                ]);
            }
        }
    }

    // ── Small helpers ───────────────────────────────────────────────────────

    private function unfit(string $model): bool
    {
        foreach ((array) config('translation.unfit_models', []) as $pattern) {
            if (@preg_match($pattern, $model) === 1) {
                return true;
            }
        }

        return false;
    }

    /** "gemma4" and "gemma4:latest" are the same model to Ollama. */
    private function tagged(string $name): string
    {
        $name = trim($name);

        return $name === '' || str_contains($name, ':') ? $name : $name.':latest';
    }

    /** The cause, without a URL or a response body that could carry a key. */
    private function reason(string $message): string
    {
        $message = preg_replace('~\(see https?://\S+\)|for https?://\S+~', '', $message) ?? $message;

        return trim(mb_substr($message, 0, 160));
    }
}
