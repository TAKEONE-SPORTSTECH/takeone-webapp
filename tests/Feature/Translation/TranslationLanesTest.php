<?php

namespace Tests\Feature\Translation;

use App\Ai\Models\AiProvider;
use App\Translation\Jobs\TranslateInterfaceLocale;
use App\Translation\Services\ProviderChain;
use App\Translation\Services\TranslationDoctor;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * One language run split across several Ollama instances (one per GPU).
 */
class TranslationLanesTest extends TestCase
{
    private const LANE1 = 'http://ollama.test:11434';

    private const LANE2 = 'http://ollama.test:11435';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'translation.ollama_lanes' => [self::LANE1, self::LANE2],
            'translation.active_lane' => null,
            'copilot.model' => 'qwen3-coder:30b',
            'copilot.base_url' => 'http://builtin.test',
        ]);

        AiProvider::create([
            'name' => 'Ollama (self-hosted)', 'modality' => 'text', 'driver' => 'ollama',
            'base_url' => self::LANE1, 'model' => 'gemma4:e4b', 'enabled' => true, 'is_default' => true,
        ]);
    }

    public function test_every_run_starts_one_child_per_lane_even_for_a_single_file(): void
    {
        Process::fake();

        $this->artisan('translate:interface', ['locale' => 'ja', '--file' => 'auth'])->assertSuccessful();

        $commands = [];
        Process::assertRan(function (PendingProcess $process) use (&$commands) {
            $commands[] = implode(' ', (array) $process->command);

            return true;
        });

        $this->assertCount(2, $commands);
        $this->assertStringContainsString('--lane=1', $commands[0].$commands[1]);
        $this->assertStringContainsString('--lane=2', $commands[0].$commands[1]);
        $this->assertStringContainsString('--file=auth', $commands[0]);
    }

    public function test_the_lanes_share_a_file_string_by_string_with_no_overlap(): void
    {
        // Echo back whatever the agent asks for, so every string "translates".
        Http::fake(function ($request) {
            $asked = json_decode($request['messages'][1]['content'] ?? '{}', true) ?: [];

            return Http::response(['message' => ['content' => json_encode(
                // Letters become kana; :placeholders, {braces} and <tags> survive.
                array_map(fn ($v) => preg_replace('/(?<![:{<\/A-Za-z_])[A-Za-z]+/u', 'テ', (string) $v), $asked), JSON_UNESCAPED_UNICODE,
            )]]);
        });
        config(['translation.refine' => false]);

        $source = array_keys(app(\App\Translation\Services\LangFileCatalog::class)->strings(
            app(\App\Translation\Services\LangFileCatalog::class)->files('ja')['auth']['source']
        ));

        // --rewrite: lang/ja/auth.php on disk already answers most keys, and a
        // gap-filling run would only ask for what it lacks.
        $rows = fn () => \App\Translation\Models\InterfaceTranslation::query()
            ->where('locale', 'ja')->where('group', 'auth')->pluck('key')->all();

        $this->artisan('translate:interface', ['locale' => 'ja', '--file' => 'auth', '--tier' => 'all', '--rewrite' => true, '--lane' => 1]);
        $lane1 = $rows();

        $this->assertGreaterThan(40, count($lane1));
        // Lane 1 of 2 holds exactly the even positions, and none of the odd ones.
        $even = array_values(array_filter($source, fn ($k) => array_search($k, $source, true) % 2 === 0));
        $this->assertEqualsCanonicalizing(array_intersect($even, $lane1), $lane1);

        $this->artisan('translate:interface', ['locale' => 'ja', '--file' => 'auth', '--tier' => 'all', '--rewrite' => true, '--lane' => 2]);
        $both = $rows();

        // Together the two lanes cover the whole file.
        $this->assertEqualsCanonicalizing($source, array_values(array_intersect($source, $both)));
    }

    public function test_a_worker_lane_sends_content_translation_to_its_own_gpu(): void
    {
        Http::fake(['*' => Http::response(['message' => ['content' => 'ok']])]);

        putenv('TRANSLATION_WORKER_LANE=2');

        try {
            app(ProviderChain::class)->links()[0]->driver->chat([['role' => 'user', 'content' => 'hi']]);
        } finally {
            putenv('TRANSLATION_WORKER_LANE');
        }

        $this->assertStringStartsWith(self::LANE2, Http::recorded()[0][0]->url());
    }

    public function test_one_lane_configured_means_no_split(): void
    {
        config(['translation.ollama_lanes' => [self::LANE1]]);
        Process::fake();
        Http::fake(['*' => Http::response(['message' => ['content' => '{}']])]);

        $this->artisan('translate:interface', ['locale' => 'ja', '--tier' => 'event', '--dry-run' => true])->assertSuccessful();

        Process::assertNothingRan();
    }

    public function test_a_lane_sends_the_ollama_provider_to_its_own_server(): void
    {
        Http::fake(['*' => Http::response(['message' => ['content' => 'ok']])]);

        config(['translation.active_lane' => self::LANE2]);
        app(ProviderChain::class)->links()[0]->driver->chat([['role' => 'user', 'content' => 'hi']]);

        config(['translation.active_lane' => null]);
        app(ProviderChain::class)->links()[0]->driver->chat([['role' => 'user', 'content' => 'hi']]);

        $urls = collect(Http::recorded())->map(fn ($pair) => $pair[0]->url())->all();

        $this->assertStringStartsWith(self::LANE2, $urls[0]);
        $this->assertStringStartsWith(self::LANE1, $urls[1]);
    }

    public function test_a_provider_on_another_server_is_never_redirected_to_a_lane(): void
    {
        AiProvider::query()->update(['base_url' => 'http://elsewhere.test:11434']);
        Http::fake(['*' => Http::response(['message' => ['content' => 'ok']])]);

        config(['translation.active_lane' => self::LANE2]);
        app(ProviderChain::class)->links()[0]->driver->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertStringStartsWith('http://elsewhere.test:11434', Http::recorded()[0][0]->url());
    }

    public function test_progress_is_the_sum_of_every_lane(): void
    {
        Cache::put(TranslateInterfaceLocale::statusKey('ja'), [
            'state' => 'running', 'started_at' => now()->subMinute()->toIso8601String(),
        ], now()->addHour());

        Cache::put(TranslateInterfaceLocale::progressKey('ja', 1), ['done' => 100, 'total' => 2000, 'updated_at' => now()->toIso8601String()]);
        Cache::put(TranslateInterfaceLocale::progressKey('ja', 2), ['done' => 300, 'total' => 2100, 'updated_at' => now()->toIso8601String()]);

        $this->assertSame(['done' => 400, 'total' => 4100], TranslateInterfaceLocale::status('ja')['progress']);
    }

    public function test_the_doctor_checks_every_lane(): void
    {
        Queue::fake();
        Http::fake([
            'ollama.test:11434/*' => Http::response(['models' => [['name' => 'gemma4:e4b']]]),
            'ollama.test:11435/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 7'),
        ]);

        $report = app(TranslationDoctor::class)->run(fix: false);

        $lane2 = collect($report['providers'])->firstWhere('label', 'Ollama lane 2');
        $this->assertSame('unreachable', $lane2['state']);
    }

    public function test_translation_asks_ollama_for_a_context_big_enough_for_a_batch(): void
    {
        config(['translation.num_ctx' => 32768, 'translation.refine' => false]);
        Http::fake(['*' => Http::response(['message' => ['content' => json_encode(['0' => 'テ'])]])]);

        app(\App\Translation\Services\InterfaceAgent::class)->translate(['k' => 'Save'], 'ja', 'test');

        $this->assertSame(32768, Http::recorded()[0][0]['options']['num_ctx'] ?? null);
    }
}
