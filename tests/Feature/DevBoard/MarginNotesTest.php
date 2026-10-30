<?php

namespace Tests\Feature\DevBoard;

use App\DevBoard\Models\Asset;
use App\DevBoard\Models\BoardMember;
use App\DevBoard\Models\Card;
use App\DevBoard\Models\Item;
use App\DevBoard\Models\Lane;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Margin's notes: formatted text with pictures, cleaned on the way in, kept to
 * board members, addressed through their own card only.
 */
class MarginNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        if (! \Illuminate\Support\Facades\Route::has('me.margin')) {
            $this->markTestSkipped('DEVBOARD_ENABLED is off');
        }
    }

    private function owner(): User
    {
        $u = $this->createUser();
        $this->makeSuperAdmin($u);

        return $u;
    }

    private function card(User $by): Card
    {
        $lane = Lane::create(['name' => 'Inbox', 'color' => '#7c6bf5', 'position' => 1024]);

        return Card::create(['lane_id' => $lane->id, 'title' => 'Idea', 'priority' => 'normal', 'position' => 1024, 'created_by' => $by->id]);
    }

    private function png(): UploadedFile
    {
        $img = imagecreatetruecolor(40, 30);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 50, 90));
        ob_start(); imagepng($img); $bytes = ob_get_clean();

        return UploadedFile::fake()->createWithContent('shot.png', $bytes);
    }

    public function test_a_note_is_written_cleaned_and_listed(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $res = $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), [
            'html' => '<h1>Plan</h1><p onclick="x()"><b>Bold</b> and <script>alert(1)</script><a href="javascript:alert(1)">bad</a> <a href="https://takeone.bh">good</a></p><img src="https://evil.test/x.png">',
        ])->assertOk();

        $html = $res->json('note.html');
        $this->assertStringContainsString('<h1>Plan</h1>', $html);
        $this->assertStringContainsString('<b>Bold</b>', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('evil.test', $html);
        $this->assertStringContainsString('href="https://takeone.bh"', $html);
        $this->assertSame('Plan', $res->json('note.title'));
        $this->assertSame(1, $res->json('card.counts.notes'));

        $this->actingAs($me)->getJson(route('me.margin.notes', $card))->assertOk()->assertJsonCount(1, 'notes');
    }

    public function test_each_paragraph_keeps_its_own_writing_direction(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $html = $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), [
            'html' => '<p dir="ltr">Hello</p><p dir="rtl">مرحبا</p><ul dir="rtl"><li dir="rtl">أ</li></ul><p dir="evil">x</p><p>auto</p><p>\u{E001}rtl\u{E001}sneaky</p>',
        ])->assertOk()->json('note.html');

        $this->assertStringContainsString('<p dir="ltr">Hello</p>', $html);
        $this->assertStringContainsString('<p dir="rtl">مرحبا</p>', $html);
        $this->assertStringContainsString('<ul dir="rtl"><li dir="rtl">أ</li></ul>', $html);
        $this->assertStringContainsString('<p>x</p>', $html, 'anything but ltr / rtl is dropped');
        $this->assertStringContainsString('<p>auto</p>', $html);
        $this->assertStringNotContainsString("\u{E001}", $html);
        $this->assertStringNotContainsString('evil', $html);
    }

    public function test_a_link_can_be_shown_as_a_button_and_nothing_else_rides_along(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $html = $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), [
            'html' => '<p><a href="https://example.com/form" data-look="button" onclick="x()" style="color:red">Fill it in</a> '
                .'<a href="https://example.com/plain">plain</a> <a href="javascript:alert(1)" data-look="button">bad</a> '
                ."\u{E002}<b>&#xE002;</b>&#57346;</p>",
        ])->assertOk()->json('note.html');

        $this->assertMatchesRegularExpression('#<a href="https://example.com/form"[^>]* data-look="button">Fill it in</a>#', $html);
        $this->assertDoesNotMatchRegularExpression('#example.com/plain"[^>]*data-look#', $html, 'only the link marked as a button');
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('style=', $html);
        $this->assertStringNotContainsString("\u{E002}", $html, 'no smuggled marker survives');
        $this->assertSame(1, substr_count($html, 'data-look'), 'a forged marker does not make a second button');
    }

    public function test_a_checklist_keeps_its_boxes_and_ticks(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $html = $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), [
            'html' => '<ul data-check="1" dir="rtl"><li data-checked="1">حليب</li><li>Bread</li></ul>'
                .'<ul><li data-checked="1" onclick="x()">not a checklist</li></ul>'
                ."<p>\u{E003}&#xE004;&#57347;sneaky</p>",
        ])->assertOk()->json('note.html');

        $this->assertStringContainsString('<ul dir="rtl" data-check="1"><li data-checked="1">حليب</li><li>Bread</li></ul>', $html);
        $this->assertSame(1, substr_count($html, 'data-check="1"'), 'only the list marked as a checklist');
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString("\u{E003}", $html);
        $this->assertStringNotContainsString("\u{E004}", $html);
        $this->assertStringContainsString('<p>sneaky</p>', $html, 'smuggled markers make nothing');
    }

    public function test_markers_cannot_be_smuggled_in_as_entities(): void
    {
        $me = $this->owner();
        $card = $this->card($me);
        $uuid = $this->actingAs($me)->post(route('me.margin.pictures.store', $card), ['image' => $this->png()], ['Accept' => 'application/json'])->json('asset.uuid');

        $html = $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), [
            'html' => '<p>Real <img data-asset="'.$uuid.'"></p>'
                .'<p><a href="https://takeone.bh/&#xE000;'.$uuid.'&#xE000;">link</a></p>'
                .'<p>&#57344;'.$uuid.'&#57344; &#x0e000;'.$uuid.'&#xe000 text</p>'
                .'<p><a href="https://takeone.bh/&#xE001;rtl&#xE001;">x</a>&#xE001;rtl&#xE001;</p>',
        ])->assertOk()->json('note.html');

        $this->assertSame(1, substr_count($html, '<img'), 'only the real picture comes back');
        $this->assertStringContainsString('<p>Real <img data-asset="'.$uuid.'" alt=""></p>', $html);
        $this->assertStringNotContainsString('dir=', $html);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*</', $html, 'nothing is spliced into an attribute');
        $this->assertDoesNotMatchRegularExpression('/[\x{E000}\x{E001}]|&#x?0*(e00[01]|5734[45])/iu', $html);
    }

    public function test_a_video_from_a_known_site_is_kept_as_its_site_and_id_alone(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $html = $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), [
            'html' => '<p>Watch</p><img data-embed="youtube:dQw4w9WgXcQ" src="https://evil.example/x.png" onerror="alert(1)">'
                .'<img data-embed="vimeo:76979871"><img data-embed="dailymotion:x8abcd1">'
                .'<img data-embed="evilsite:dQw4w9WgXcQ"><img data-embed="youtube:short"><img data-embed="youtube:dQw4w9WgXcQ&quot; onload=&quot;x">'
                .'<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>'
                .'<p>&#xE005;youtube:dQw4w9WgXcQ&#xE005; smuggled</p>',
        ])->assertOk()->json('note.html');

        $this->assertStringContainsString('<img data-embed="youtube:dQw4w9WgXcQ" alt="">', $html);
        $this->assertStringContainsString('<img data-embed="vimeo:76979871" alt="">', $html);
        $this->assertStringContainsString('<img data-embed="dailymotion:x8abcd1" alt="">', $html);
        $this->assertSame(3, substr_count($html, '<img'), 'an unknown site, a malformed id and a smuggled marker are all dropped');
        $this->assertStringNotContainsString('iframe', $html);
        $this->assertStringNotContainsString('evil', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertDoesNotMatchRegularExpression('/[\x{E005}]|&#x?0*(e005|57349)/iu', $html);
    }

    public function test_instagram_and_tiktok_videos_are_kept_too(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $html = $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), [
            'html' => '<img data-embed="instagram:C0ZgWZ3Ny6J"><img data-embed="tiktok:7106594312292453675"><img data-embed="tiktok:notanumber"><img data-embed="instagram:a/b">',
        ])->assertOk()->json('note.html');

        $this->assertSame('<img data-embed="instagram:C0ZgWZ3Ny6J" alt=""><img data-embed="tiktok:7106594312292453675" alt="">', $html);
    }

    public function test_a_tiktok_short_link_is_followed_only_on_tiktok(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'vm.tiktok.com/good*' => \Illuminate\Support\Facades\Http::response('', 301, ['Location' => 'https://www.tiktok.com/@someone/video/7106594312292453675?_r=1']),
            'vm.tiktok.com/away*' => \Illuminate\Support\Facades\Http::response('', 302, ['Location' => 'https://evil.example/@x/video/7106594312292453675']),
            '*' => \Illuminate\Support\Facades\Http::response('', 200),
        ]);
        $me = $this->owner();

        $this->actingAs($me)->getJson(route('me.margin.video-link', ['url' => 'https://vm.tiktok.com/good/']))
            ->assertOk()->assertJsonPath('embed', 'tiktok:7106594312292453675');
        $this->actingAs($me)->getJson(route('me.margin.video-link', ['url' => 'https://vm.tiktok.com/away/']))
            ->assertOk()->assertJsonPath('embed', null);
        $this->actingAs($me)->getJson(route('me.margin.video-link', ['url' => 'https://evil.example/t/x']))
            ->assertOk()->assertJsonPath('embed', null);
        $this->actingAs($me)->getJson(route('me.margin.video-link', ['url' => 'http://vm.tiktok.com/good/']))
            ->assertOk()->assertJsonPath('embed', null);

        \Illuminate\Support\Facades\Http::assertNotSent(fn ($r) => ! str_ends_with(parse_url($r->url(), PHP_URL_HOST), 'tiktok.com'));
        \Illuminate\Support\Facades\Http::assertNotSent(fn ($r) => $r->method() !== 'HEAD');
    }

    public function test_only_board_members_may_look_up_a_video_link(): void
    {
        $this->getJson(route('me.margin.video-link', ['url' => 'https://vm.tiktok.com/x/']))->assertUnauthorized();
        $this->assertContains($this->actingAs($this->createUser())->getJson(route('me.margin.video-link', ['url' => 'https://vm.tiktok.com/x/']))->status(), [403, 404]);
    }

    public function test_a_table_keeps_its_cells_spans_and_looks_and_nothing_else(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $html = $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), [
            'html' => '<table data-look="stripes" onclick="x" style="background:red"><tbody>'
                .'<tr><th colspan="2" data-fill="3" class="is-sel">Head</th><th>H2</th></tr>'
                .'<tr><td rowspan="2" style="text-align: center; background: url(x)">A</td><td colspan="99">B</td><td colspan="abc" data-fill="9">C</td></tr>'
                .'<tr><td>&#xE006;p1&#xE006;D</td><td><br></td></tr>'
                .'</tbody></table>',
        ])->assertOk()->json('note.html');

        $this->assertStringStartsWith('<table data-look="stripes"><tbody><tr><th colspan="2" data-fill="3">Head</th><th>H2</th></tr>', $html);
        $this->assertStringContainsString('<td rowspan="2" style="text-align: center">A</td><td>B</td><td>C</td>', $html);
        $this->assertStringContainsString('<td>p1D</td>', $html, 'a smuggled marker is dropped, never turned into a look');
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringNotContainsString('class=', $html);
        $this->assertStringNotContainsString('url(', $html);
    }

    public function test_an_empty_table_is_still_a_note_and_its_text_reads_on_the_tile(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), ['html' => '<table><tbody><tr><td><br></td></tr></tbody></table>'])
            ->assertOk()->assertJsonPath('note.html', '<table><tbody><tr><td><br></td></tr></tbody></table>');

        [$title, $rest] = \App\DevBoard\Services\NoteHtml::parts('<p>Plan</p><table><tbody><tr><td>Mon</td><td>Run</td></tr></tbody></table>');
        $this->assertSame('Plan', $title);
        $this->assertSame('Mon Run', $rest);
    }

    public function test_the_shared_sanitizer_still_drops_tables_for_everyone_else(): void
    {
        $this->assertSame('<p>x</p>', \App\Support\HtmlSanitizer::clean('<table><tbody><tr><td><p>x</p></td></tr></tbody></table>'));
    }

    public function test_a_list_item_can_hold_a_list_of_its_own(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), ['html' => '<ul><li>One<ul><li>Under one</li></ul></li><li>Two</li></ul>'])
            ->assertOk()->assertJsonPath('note.html', '<ul><li>One<ul><li>Under one</li></ul></li><li>Two</li></ul>');
    }

    public function test_markdown_becomes_the_notes_own_formatting(): void
    {
        $me = $this->owner();
        $md = "---\ntitle: x\n---\n# Title\n\nSome **bold** [link](https://a.com) [bad](javascript:alert(1))\n\n- [x] done\n- [ ] todo\n\n| a | b |\n|:-:|---|\n| 1 | 2 |\n\n```\nif (x<1) {}\n```\n\n![pic](https://x.com/a.png) <script>alert(1)</script><b onclick=\"x\">raw</b>";

        $html = $this->actingAs($me)->postJson(route('me.margin.markdown'), ['text' => $md])->assertOk()->json('html');

        $this->assertStringStartsWith('<h1>Title</h1>', $html, 'front matter is not content');
        $this->assertStringContainsString('<strong>bold</strong>', $html);
        $this->assertStringContainsString('<a href="https://a.com"', $html);
        $this->assertStringContainsString('<ul data-check="1"><li data-checked="1">done</li><li>todo</li></ul>', $html);
        $this->assertStringContainsString('<table><tbody><tr><th style="text-align: center">a</th><th>b</th></tr><tr><td style="text-align: center">1</td><td>2</td></tr></tbody></table>', $html);
        $this->assertStringContainsString('<pre><code>if (x&lt;1) {}</code></pre>', $html);
        $this->assertStringContainsString('<a href="https://x.com/a.png"', $html, 'a picture becomes a link to it');
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('javascript', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }

    public function test_markdown_is_converted_only_for_board_members_and_within_its_size(): void
    {
        $this->postJson(route('me.margin.markdown'), ['text' => '# x'])->assertUnauthorized();
        $this->assertContains($this->actingAs($this->createUser())->postJson(route('me.margin.markdown'), ['text' => '# x'])->status(), [403, 404]);
        $this->actingAs($this->owner())->postJson(route('me.margin.markdown'), ['text' => str_repeat('a', \App\DevBoard\Services\NoteHtml::MARKDOWN_MAX + 1)])->assertStatus(422);
    }

    public function test_a_code_block_survives_saving(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), ['html' => "<pre><code>a\n  b</code></pre>"])
            ->assertOk()->assertJsonPath('note.html', "<pre><code>a\n  b</code></pre>");
    }

    public function test_a_note_holding_only_a_video_is_not_blank(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), ['html' => '<img data-embed="youtube:dQw4w9WgXcQ">'])
            ->assertOk()->assertJsonPath('note.html', '<img data-embed="youtube:dQw4w9WgXcQ" alt="">');
    }

    public function test_a_picture_is_re_encoded_and_kept_only_inside_its_own_card(): void
    {
        $me = $this->owner();
        $card = $this->card($me);
        $other = $this->card($me);

        $up = $this->actingAs($me)->post(route('me.margin.pictures.store', $card), ['image' => $this->png()], ['Accept' => 'application/json'])->assertOk();
        $uuid = $up->json('asset.uuid');
        $asset = Asset::where('uuid', $uuid)->firstOrFail();
        $this->assertStringEndsWith('.webp', $asset->path);
        $this->assertStringContainsString('platform/devboard/cards/'.$card->uuid, $asset->path);
        Storage::disk('local')->assertExists($asset->path);

        // Its own card keeps it; another card's note drops it.
        $mine = $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), ['html' => '<p>See</p><img data-asset="'.$uuid.'" src="blob:x">'])->json('note');
        $this->assertStringContainsString('<img data-asset="'.$uuid.'" alt="">', $mine['html']);
        $this->assertSame([$uuid], $mine['assets']);

        $theirs = $this->actingAs($me)->postJson(route('me.margin.notes.store', $other), ['html' => '<p>See</p><img data-asset="'.$uuid.'">'])->json('note');
        $this->assertStringNotContainsString('<img', $theirs['html']);

        $this->actingAs($me)->get(route('me.margin.pictures.show', $uuid))->assertOk()->assertHeader('Content-Type', 'image/webp');
    }

    public function test_not_a_picture_is_refused(): void
    {
        $me = $this->owner();
        $card = $this->card($me);

        $this->actingAs($me)->post(route('me.margin.pictures.store', $card), [
            'image' => UploadedFile::fake()->createWithContent('x.png', '<?php echo 1; ?>'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertSame(0, Asset::count());
    }

    public function test_a_stale_edit_conflicts_instead_of_overwriting(): void
    {
        $me = $this->owner();
        $card = $this->card($me);
        $note = $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), ['html' => '<p>one</p>'])->json('note');

        $this->actingAs($me)->patchJson(route('me.margin.notes.update', [$card, $note['uuid']]), ['version' => 1, 'html' => '<p>two</p>'])->assertOk();
        $this->actingAs($me)->patchJson(route('me.margin.notes.update', [$card, $note['uuid']]), ['version' => 1, 'html' => '<p>three</p>'])
            ->assertStatus(409)->assertJsonPath('note.html', '<p>two</p>');
    }

    public function test_delete_restore_and_reorder(): void
    {
        $me = $this->owner();
        $card = $this->card($me);
        $a = $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), ['html' => '<p>a</p>'])->json('note');
        $b = $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), ['html' => '<p>b</p>'])->json('note');

        // newest first; move a above b
        $this->actingAs($me)->postJson(route('me.margin.notes.move', [$card, $a['uuid']]), ['above' => null, 'below' => $b['uuid']])->assertOk();
        $this->assertSame([$a['uuid'], $b['uuid']], array_column($this->actingAs($me)->getJson(route('me.margin.notes', $card))->json('notes'), 'uuid'));

        $this->actingAs($me)->deleteJson(route('me.margin.notes.destroy', [$card, $a['uuid']]))->assertOk();
        $this->assertSoftDeleted('devboard_items', ['uuid' => $a['uuid']]);
        $this->actingAs($me)->postJson(route('me.margin.notes.restore', [$card, $a['uuid']]))->assertOk();
        $this->assertNotSoftDeleted('devboard_items', ['uuid' => $a['uuid']]);
    }

    public function test_a_note_cannot_be_reached_through_another_card(): void
    {
        $me = $this->owner();
        $card = $this->card($me);
        $other = $this->card($me);
        $note = $this->actingAs($me)->postJson(route('me.margin.notes.store', $card), ['html' => '<p>secret</p>'])->json('note');

        $this->actingAs($me)->patchJson(route('me.margin.notes.update', [$other, $note['uuid']]), ['version' => 1, 'html' => '<p>x</p>'])->assertNotFound();
        $this->actingAs($me)->deleteJson(route('me.margin.notes.destroy', [$other, $note['uuid']]))->assertNotFound();
        $this->assertSame('<p>secret</p>', Item::where('uuid', $note['uuid'])->value('body'));
    }

    public function test_outsiders_see_nothing(): void
    {
        $me = $this->owner();
        $card = $this->card($me);
        $this->actingAs($me)->post(route('me.margin.pictures.store', $card), ['image' => $this->png()], ['Accept' => 'application/json']);
        $uuid = Asset::value('uuid');

        $stranger = $this->createUser();
        $this->actingAs($stranger)->getJson(route('me.margin.notes', $card))->assertNotFound();
        $this->actingAs($stranger)->postJson(route('me.margin.notes.store', $card), ['html' => '<p>x</p>'])->assertNotFound();
        // A browser fetch is turned away by the platform's web handler (redirect), never served.
        $pic = $this->actingAs($stranger)->get(route('me.margin.pictures.show', $uuid));
        $this->assertContains($pic->status(), [302, 404]);
        $this->assertNotSame('image/webp', $pic->headers->get('Content-Type'));

        // A listed member is let in.
        BoardMember::create(['user_id' => $stranger->id, 'ink' => '#f472b6']);
        $this->actingAs($stranger)->getJson(route('me.margin.notes', $card))->assertOk();
    }

    public function test_the_menu_shows_the_way_in_only_to_board_people(): void
    {
        $me = $this->owner();
        $this->actingAs($me);
        $this->assertTrue(\App\DevBoard\Support\BoardAccess::linkFor($me));

        $stranger = $this->createUser();
        $this->assertFalse(\App\DevBoard\Support\BoardAccess::linkFor($stranger));
    }
}
