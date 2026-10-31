<?php

namespace Tests\Feature\DevBoard;

use App\DevBoard\Mcp\Tools\CreateNoteTool;
use App\DevBoard\Mcp\Tools\GetNoteTool;
use App\DevBoard\Mcp\Tools\ListNoteFoldersTool;
use App\DevBoard\Mcp\Tools\ListNotesTool;
use App\DevBoard\Mcp\Tools\UpdateNoteTool;
use App\DevBoard\Models\Notebook;
use App\DevBoard\Support\Notebooks;
use App\Members\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Request;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The notes MCP (/mcp/notes, and the same tools on /mcp): a person reaches
 * their own notebook and nobody else's, writes never overwrite a newer edit,
 * and a notes-only token opens the notes server and nothing more.
 */
class NotesMcpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notebooks::set(null);
        if (! config('devboard.enabled') || ! config('devboard.takenote')) {
            $this->markTestSkipped('TakeNote is off');
        }
    }

    protected function tearDown(): void
    {
        Notebooks::set(null);
        parent::tearDown();
    }

    private function person(): User
    {
        $u = $this->createUser();
        $u->forceFill(['email_verified_at' => now()])->save();
        Notebook::create(['owner_id' => $u->id, 'joined_at' => now()]);

        return $u;
    }

    private function tool(User $as, string $tool, array $args = []): array
    {
        $this->actingAs($as);
        $text = (string) (new $tool)->handle(new Request($args))->content();
        $json = json_decode($text, true);

        return is_array($json) ? $json : ['error' => $text];
    }

    public function test_a_person_sees_only_their_own_notes(): void
    {
        $me = $this->person();
        $other = $this->person();
        $mine = $this->tool($me, CreateNoteTool::class, ['markdown' => "# My plan\n\nBeat the competition"])['note']['uuid'];
        $theirs = $this->tool($other, CreateNoteTool::class, ['markdown' => "# Their secret\n\nPrivate words"])['note']['uuid'];

        $titles = collect($this->tool($me, ListNotesTool::class)['notes'])->pluck('title');
        $this->assertContains('My plan', $titles);
        $this->assertNotContains('Their secret', $titles);

        // a uuid from another notebook answers exactly like a missing one
        $this->assertStringContainsString('No note with that uuid', $this->tool($me, GetNoteTool::class, ['uuid' => $theirs])['error']);
        $this->assertStringContainsString('No note with that uuid', $this->tool($me, UpdateNoteTool::class, ['uuid' => $theirs, 'version' => 1, 'markdown' => 'hacked'])['error']);
        $this->assertStringContainsString('Private words', $this->tool($other, GetNoteTool::class, ['uuid' => $theirs])['text']);

        // search reaches the text, still only in my notebook
        $this->assertSame([$mine], collect($this->tool($me, ListNotesTool::class, ['query' => 'competition'])['notes'])->pluck('uuid')->all());
        $this->assertSame([], $this->tool($me, ListNotesTool::class, ['query' => 'Private'])['notes']);
    }

    public function test_update_replaces_appends_and_refuses_a_stale_version(): void
    {
        $me = $this->person();
        $uuid = $this->tool($me, CreateNoteTool::class, ['markdown' => "# Draft\n\nfirst"])['note']['uuid'];
        $v = $this->tool($me, GetNoteTool::class, ['uuid' => $uuid])['version'];

        $r = $this->tool($me, UpdateNoteTool::class, ['uuid' => $uuid, 'version' => $v, 'markdown' => "- second\n", 'mode' => 'append']);
        $this->assertTrue($r['updated']);
        $text = $this->tool($me, GetNoteTool::class, ['uuid' => $uuid])['text'];
        $this->assertStringContainsString('first', $text);
        $this->assertStringContainsString('- second', $text);

        // the old version is now stale: nothing changes
        $stale = $this->tool($me, UpdateNoteTool::class, ['uuid' => $uuid, 'version' => $v, 'markdown' => '# Overwritten']);
        $this->assertStringContainsString('out of date', $stale['error']);
        $this->assertStringContainsString('first', $this->tool($me, GetNoteTool::class, ['uuid' => $uuid])['text']);

        $r = $this->tool($me, UpdateNoteTool::class, ['uuid' => $uuid, 'version' => $r['version'], 'markdown' => "# Final\n\nclean"]);
        $note = $this->tool($me, GetNoteTool::class, ['uuid' => $uuid]);
        $this->assertSame('Final', $note['title']);
        $this->assertStringNotContainsString('first', $note['text']);
    }

    public function test_markdown_cannot_smuggle_html(): void
    {
        $me = $this->person();
        $uuid = $this->tool($me, CreateNoteTool::class, ['markdown' => "# Hi\n\n<script>alert(1)</script>\n\n[x](javascript:alert(1))"])['note']['uuid'];
        $html = $this->tool($me, GetNoteTool::class, ['uuid' => $uuid])['html'];

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_folders_are_per_notebook(): void
    {
        $me = $this->person();
        $other = $this->person();
        $this->actingAs($other);
        $theirFolder = $this->postJson(route('me.takenote.folders.store'), ['name' => 'Theirs'])->json('folder.uuid');
        Notebooks::set(null);

        $this->assertSame([], $this->tool($me, ListNoteFoldersTool::class)['folders']);
        $this->assertStringContainsString('No folder', $this->tool($me, CreateNoteTool::class, ['markdown' => '# x', 'folder' => $theirFolder])['error']);
    }

    public function test_gates(): void
    {
        config(['takeone-mcp.stdio_user_id' => null]);
        $this->assertStringContainsString('Not authenticated', (string) (new ListNotesTool)->handle(new Request([]))->content());

        $nobody = $this->createUser();
        $nobody->forceFill(['email_verified_at' => now()])->save();
        $this->assertStringContainsString('no notebook', $this->tool($nobody, ListNotesTool::class)['error']);

        $unverified = $this->person();
        $unverified->forceFill(['email_verified_at' => null])->save();
        $this->assertStringContainsString('Confirm your email', $this->tool($unverified, ListNotesTool::class)['error']);

        config(['takeone-mcp.allow_writes' => false]);
        $this->assertStringContainsString('disabled', $this->tool($this->person(), CreateNoteTool::class, ['markdown' => '# x'])['error']);
    }

    public function test_a_notes_token_opens_the_notes_server_only(): void
    {
        if (! collect(Route::getRoutes())->contains(fn ($r) => $r->uri() === 'mcp/notes')) {
            $this->markTestSkipped('/mcp/notes is not registered');
        }
        $me = $this->person();
        $rpc = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => (object) []];

        Sanctum::actingAs($me, ['notes']);
        $tools = collect($this->postJson('/mcp/notes', $rpc)->assertOk()->json('result.tools'))->pluck('name');
        $this->assertEqualsCanonicalizing(['list_notes', 'get_note', 'create_note', 'update_note', 'list_note_folders'], $tools->all());
        $this->postJson('/mcp', $rpc)->assertForbidden();

        Sanctum::actingAs($me, ['mcp:use']);
        $this->postJson('/mcp', $rpc)->assertOk();
        $this->postJson('/mcp/notes', $rpc)->assertForbidden();
    }
}
