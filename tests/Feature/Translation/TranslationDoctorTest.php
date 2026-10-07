<?php

namespace Tests\Feature\Translation;

use App\Ai\Models\AiProvider;
use App\Members\Models\UserNotification;
use App\Translation\Jobs\TranslateInterfaceLocale;
use App\Translation\Services\TranslationDoctor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * translate:doctor — finds what broke translation and fixes what it can.
 */
class TranslationDoctorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        // The built-in fallback is a code model, which translation refuses by
        // design — so the configured Ollama provider is the only translator.
        config([
            'copilot.model' => 'qwen3-coder:30b',
            'copilot.base_url' => 'http://builtin.test',
            'translation.autofix.enabled' => true,
            // Lanes are their own test file; here one server is the whole chain.
            'translation.ollama_lanes' => [],
        ]);

        AiProvider::create([
            'name' => 'Ollama (self-hosted)',
            'modality' => 'text',
            'driver' => 'ollama',
            'base_url' => 'http://ollama.test:11434',
            'model' => 'gemma4:e4b',
            'enabled' => true,
            'is_default' => true,
        ]);
    }

    private function serverUp(array $models = ['gemma4:e4b']): void
    {
        Http::fake([
            'ollama.test:11434/api/tags' => Http::response(['models' => array_map(fn ($m) => ['name' => $m], $models)]),
            'ollama.test:11434/api/pull' => Http::response(['status' => 'success']),
        ]);
    }

    private function serverDown(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 7: Failed to connect'));
    }

    private function runState(string $locale, array $state): void
    {
        Cache::put(TranslateInterfaceLocale::statusKey($locale), $state + [
            'started_at' => null, 'finished_at' => now()->toIso8601String(), 'complete' => false,
        ], now()->addHours(3));
    }

    private function superAdmin(): \App\Members\Models\User
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);

        return $admin;
    }

    public function test_a_failed_run_is_resumed_with_the_same_tier_once_a_translator_answers(): void
    {
        $this->serverUp();
        $this->runState('ja', ['state' => 'failed', 'tier' => 'visitor']);

        $report = app(TranslationDoctor::class)->run();

        Queue::assertPushed(TranslateInterfaceLocale::class, fn ($job) => $job->locale === 'ja' && $job->tier === 'visitor' && $job->rewrite === false);
        $this->assertSame('resumed', $report['runs'][0]['verdict']);
        $this->assertSame('running', TranslateInterfaceLocale::status('ja')['state']);

        // The next pass sees it running and does not dispatch it twice.
        app(TranslationDoctor::class)->run();
        Queue::assertPushed(TranslateInterfaceLocale::class, 1);
    }

    public function test_nothing_is_resumed_while_every_translator_is_down_and_admins_hear_about_it_once(): void
    {
        $admin = $this->superAdmin();
        $this->serverDown();
        $this->runState('ja', ['state' => 'failed', 'tier' => 'event']);

        $report = app(TranslationDoctor::class)->run();
        app(TranslationDoctor::class)->run();

        Queue::assertNothingPushed();
        $this->assertFalse($report['can_translate']);
        $this->assertSame('unreachable', $report['providers'][0]['state']);
        $this->assertSame('waiting_for_translator', $report['runs'][0]['verdict']);

        // Two incidents (the server, and "nothing answers"), each told ONCE
        // across two passes.
        $this->assertSame(2, UserNotification::where('user_id', $admin->id)->count());
    }

    public function test_recovery_resumes_the_run_and_says_the_problem_cleared(): void
    {
        $admin = $this->superAdmin();
        $this->runState('ja', ['state' => 'failed', 'tier' => 'event']);

        // One fake whose answer flips — stacked fakes match the first stub.
        $up = false;
        Http::fake(function ($request) use (&$up) {
            if (! $up) {
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 7: Failed to connect');
            }

            return Http::response(['models' => [['name' => 'gemma4:e4b']]]);
        });

        app(TranslationDoctor::class)->run();

        $up = true;
        app(TranslationDoctor::class)->run();

        Queue::assertPushed(TranslateInterfaceLocale::class, 1);
        $this->assertSame(2, UserNotification::where('user_id', $admin->id)->where('title', 'Translation problem resolved')->count());
    }

    public function test_a_missing_model_is_downloaded(): void
    {
        $this->serverUp(['qwen3.5:latest']);

        $report = app(TranslationDoctor::class)->run();

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/pull') && $r['model'] === 'gemma4:e4b');
        $this->assertSame('ok', $report['providers'][0]['state']);
        $this->assertNotEmpty($report['actions']);
    }

    public function test_a_run_stuck_as_running_is_reset_and_resumed(): void
    {
        $this->serverUp();
        $this->runState('ja', ['state' => 'running', 'started_at' => now()->subHours(2)->toIso8601String(), 'tier' => 'event']);

        $report = app(TranslationDoctor::class)->run();

        $this->assertSame('resumed', $report['runs'][0]['verdict']);
        Queue::assertPushed(TranslateInterfaceLocale::class, 1);
    }

    public function test_a_run_still_inside_its_time_is_left_alone(): void
    {
        $this->serverUp();
        $this->runState('ja', ['state' => 'running', 'started_at' => now()->subMinutes(20)->toIso8601String(), 'tier' => 'event']);

        app(TranslationDoctor::class)->run();

        Queue::assertNothingPushed();
    }

    public function test_a_rewrite_is_never_resumed_automatically(): void
    {
        $this->serverUp();
        $this->runState('ja', ['state' => 'failed', 'tier' => 'event', 'rewrite' => true]);

        $report = app(TranslationDoctor::class)->run();

        Queue::assertNothingPushed();
        $this->assertSame('needs_person', $report['runs'][0]['verdict']);
    }

    public function test_self_hosted_translators_are_never_capped(): void
    {
        config(['translation.autofix.max_resumes_per_day' => 1]);
        $this->serverUp();

        foreach ([1, 2, 3] as $_) {
            $this->runState('ja', ['state' => 'failed', 'tier' => 'event']);
            app(TranslationDoctor::class)->run();
        }

        Queue::assertPushed(TranslateInterfaceLocale::class, 3);
    }

    public function test_resumes_stop_at_the_daily_cap_when_a_paid_provider_is_in_the_chain(): void
    {
        config(['translation.autofix.max_resumes_per_day' => 1]);
        $this->serverUp();
        AiProvider::create([
            'name' => 'Claude', 'modality' => 'text', 'driver' => 'anthropic',
            'api_key' => 'test-key', 'model' => 'claude-x', 'enabled' => true, 'is_default' => false,
        ]);

        $this->runState('ja', ['state' => 'failed', 'tier' => 'event']);
        app(TranslationDoctor::class)->run();

        $this->runState('ja', ['state' => 'failed', 'tier' => 'event']);
        $report = app(TranslationDoctor::class)->run();

        Queue::assertPushed(TranslateInterfaceLocale::class, 1);
        $this->assertSame('gave_up_today', $report['runs'][0]['verdict']);
    }

    public function test_a_finished_run_that_made_no_progress_is_not_resumed_again(): void
    {
        $this->serverUp();
        // One stored Japanese string, so the language has a coverage row.
        $file = array_key_first(app(\App\Translation\Services\LangFileCatalog::class)->filesFor('event', 'en'));
        \App\Translation\Translations::recordInterface('ja', $file, 'doctor_test_key', 'テスト');
        // Last resume left 0 missing on record, so any count now is no progress.
        Cache::put('translation-doctor:last-missing:ja:event', 0, now()->addDay());
        $this->runState('ja', ['state' => 'done', 'complete' => false, 'tier' => 'event']);

        $report = app(TranslationDoctor::class)->run();

        Queue::assertNothingPushed();
        $this->assertSame('stalled', $report['runs'][0]['verdict']);
    }

    public function test_check_mode_changes_nothing_and_tells_nobody(): void
    {
        $admin = $this->superAdmin();
        $this->serverUp(['qwen3.5:latest']);
        $this->runState('ja', ['state' => 'failed', 'tier' => 'event']);

        $this->artisan('translate:doctor', ['--check' => true])->assertSuccessful();

        Queue::assertNothingPushed();
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/api/pull'));
        $this->assertSame(0, UserNotification::where('user_id', $admin->id)->count());
        $this->assertSame('failed', TranslateInterfaceLocale::status('ja')['state']);
    }

    public function test_the_scheduled_command_is_a_no_op_while_autofix_is_off(): void
    {
        config(['translation.autofix.enabled' => false]);
        $this->serverUp();
        $this->runState('ja', ['state' => 'failed', 'tier' => 'event']);

        $this->artisan('translate:doctor')->assertSuccessful();

        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_translation_jobs_go_to_their_own_queue_only_when_configured(): void
    {
        config(['translation.queue' => null]);
        $this->assertNull((new TranslateInterfaceLocale('ja'))->connection);

        config(['translation.queue' => 'translation']);
        $job = new TranslateInterfaceLocale('ja');

        $this->assertSame('translation', $job->connection);
        $this->assertSame('translation', $job->queue);
        $this->assertGreaterThan($job->timeout, config('queue.connections.translation.retry_after'));
    }

    public function test_a_running_language_reports_its_progress_and_a_stale_count_is_ignored(): void
    {
        $this->runState('ja', ['state' => 'running', 'started_at' => now()->subMinute()->toIso8601String(), 'finished_at' => null, 'complete' => null]);
        Cache::put(TranslateInterfaceLocale::progressKey('ja'), ['done' => 120, 'total' => 4163, 'updated_at' => now()->toIso8601String()]);

        $this->assertSame(['done' => 120, 'total' => 4163], TranslateInterfaceLocale::status('ja')['progress']);

        // A count written before this run started belongs to an earlier one.
        Cache::put(TranslateInterfaceLocale::progressKey('ja'), ['done' => 9, 'total' => 9, 'updated_at' => now()->subHour()->toIso8601String()]);
        $this->assertNull(TranslateInterfaceLocale::status('ja')['progress']);

        // Not running: no bar.
        $this->runState('ja', ['state' => 'done']);
        $this->assertNull(TranslateInterfaceLocale::status('ja')['progress']);
    }
}
