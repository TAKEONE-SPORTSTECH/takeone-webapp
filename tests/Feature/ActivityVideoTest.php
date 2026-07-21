<?php

namespace Tests\Feature;

use App\Models\ActivityCatalog;
use App\Services\ActivityVideoResearcher;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ActivityVideoTest extends TestCase
{
    /** A YouTube results page fake containing the given video ids. */
    private function fakeResults(array $ids): string
    {
        return implode('', array_map(fn ($id) => '{"videoId":"'.$id.'"}', $ids));
    }

    public function test_researcher_resolves_queries_into_verified_videos(): void
    {
        Http::fake([
            '*youtube.com/results*' => Http::response($this->fakeResults(['AAAAAAAAAAA', 'BBBBBBBBBBB']), 200),
            '*youtube.com/oembed*' => Http::response(['title' => 'Aikido explained', 'author_name' => 'Sensei Chan'], 200),
        ]);

        $videos = app(ActivityVideoResearcher::class)->resolvePlan([
            ['role' => 'intro', 'query' => 'what is aikido'],
            ['role' => 'technique', 'query' => 'aikido ikkyo'],
        ]);

        $this->assertCount(2, $videos);
        $this->assertSame('AAAAAAAAAAA', $videos[0]['id']);   // first candidate of query 1
        $this->assertSame('BBBBBBBBBBB', $videos[1]['id']);   // deduped: query 2 skips the used id
        $this->assertSame('intro', $videos[0]['role']);
        $this->assertSame('Aikido explained', $videos[0]['title']);
        $this->assertSame('Sensei Chan', $videos[0]['source']);
    }

    public function test_researcher_drops_videos_that_are_not_embeddable(): void
    {
        Http::fake([
            '*youtube.com/results*' => Http::response($this->fakeResults(['AAAAAAAAAAA']), 200),
            '*youtube.com/oembed*' => Http::response('', 401), // private / embedding disabled / gone
        ]);

        $videos = app(ActivityVideoResearcher::class)->resolvePlan([
            ['role' => 'intro', 'query' => 'what is aikido'],
        ]);

        $this->assertSame([], $videos);
    }

    public function test_verify_one_extracts_id_from_a_youtube_url(): void
    {
        Http::fake(['*youtube.com/oembed*' => Http::response(['title' => 'Clip', 'author_name' => 'Ch'], 200)]);

        $v = app(ActivityVideoResearcher::class)->verifyOne('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=5s');

        $this->assertSame('dQw4w9WgXcQ', $v['id']);
    }

    public function test_verify_video_endpoint_requires_super_admin(): void
    {
        $member = $this->createUser();
        $this->actingAs($member);

        // Non-admin AJAX call is forbidden (JSON path → 403).
        $this->postJson(route('admin.platform.activities.verify-video'), ['url' => 'dQw4w9WgXcQ'])
            ->assertForbidden();
    }

    public function test_verify_video_endpoint_verifies_and_rejects(): void
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);
        $this->actingAs($admin);

        Http::fake(['*youtube.com/oembed*' => Http::response(['title' => 'Real clip', 'author_name' => 'Chan'], 200)]);
        $this->postJson(route('admin.platform.activities.verify-video'), ['url' => 'https://youtu.be/dQw4w9WgXcQ'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('video.id', 'dQw4w9WgXcQ');

        // A string that carries no valid id never even reaches YouTube → 422.
        Http::fake(['*youtube.com/oembed*' => Http::response('', 404)]);
        $this->postJson(route('admin.platform.activities.verify-video'), ['url' => 'not a video'])
            ->assertStatus(422);
    }

    public function test_update_persists_sanitized_videos(): void
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);
        $this->actingAs($admin);

        $activity = ActivityCatalog::create(['name' => 'Aikido']);

        $this->putJson(route('admin.platform.activities.update', $activity), [
            'name' => 'Aikido',
            'videos' => [
                ['id' => 'aqz-KE-bpKQ', 'title' => 'Intro', 'source' => 'Chan'],
                ['id' => 'aqz-KE-bpKQ', 'title' => 'dup'],            // deduped
                ['id' => 'SVdY3AwlH_w', 'title' => 'Ikkyo', 'source' => 'Howcast'],
            ],
        ])->assertOk()->assertJsonPath('success', true);

        $activity->refresh();
        $ids = collect($activity->sanitizedVideos())->pluck('id')->all();
        $this->assertSame(['aqz-KE-bpKQ', 'SVdY3AwlH_w'], $ids);
    }

    public function test_update_rejects_a_malformed_video_id(): void
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);
        $this->actingAs($admin);

        $activity = ActivityCatalog::create(['name' => 'Boxing']);

        $this->putJson(route('admin.platform.activities.update', $activity), [
            'name' => 'Boxing',
            'videos' => [['id' => 'shell.php', 'title' => 'x']],
        ])->assertStatus(422)->assertJsonValidationErrors('videos.0.id');
    }
}
