<?php

namespace Tests\Feature\DevBoard;

use App\DevBoard\Mail\TakeNoteInvite;
use App\DevBoard\Models\Asset;
use App\DevBoard\Models\Card;
use App\DevBoard\Models\Notebook;
use App\DevBoard\Support\Notebooks;
use App\Members\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * TakeNote for friends (28 Oct 2026): the owner invites people by email and
 * each gets a notebook of their own. Nobody sees anybody else's notes — not
 * by listing, not by a uuid typed in, not through a picture's own address.
 */
class NotebookFriendsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notebooks::set(null);
        if (! Route::has('me.takenote.friends.store')) {
            $this->markTestSkipped('TAKENOTE_FRIENDS is off');
        }
    }

    private function owner(): User
    {
        $u = $this->createUser();
        $this->makeSuperAdmin($u);
        Notebook::where('is_main', true)->update(['owner_id' => $u->id]);

        return $u;
    }

    private function friend(string $email = 'amigo@example.com', bool $verified = true): User
    {
        $u = $this->createUser(['email' => $email]);
        $u->forceFill(['email_verified_at' => $verified ? now() : null])->save();

        return $u;
    }

    private function note(User $as, string $text): string
    {
        return $this->actingAs($as)->postJson(route('me.takenote.store'), ['html' => '<p>'.$text.'</p>'])->assertOk()->json('note.uuid');
    }

    private function titles(User $as): array
    {
        return collect($this->actingAs($as)->getJson(route('me.takenote.data'))->assertOk()->json('notes'))->pluck('title')->all();
    }

    public function test_a_friend_has_their_own_notebook_and_never_sees_the_owners(): void
    {
        $me = $this->owner();
        $mine = $this->note($me, 'Owner secret plan');
        $folder = $this->actingAs($me)->postJson(route('me.takenote.folders.store'), ['name' => 'Private'])->json('folder.uuid');

        $this->actingAs($me)->postJson(route('me.takenote.friends.store'), ['email' => 'Amigo@Example.com'])
            ->assertOk()->assertJsonPath('friends.0.joined', false);

        $friend = $this->friend();
        $this->assertSame([], $this->titles($friend), 'a new notebook is empty');
        $data = $this->actingAs($friend)->getJson(route('me.takenote.data'))->json();
        $this->assertSame([], $data['folders']);
        $this->assertSame([$friend->id], collect($data['people'])->pluck('id')->all(), 'nobody else is in a friend\'s notebook');
        $this->assertNotEmpty($data['lanes'], 'their board gets its own lists');

        $theirs = $this->note($friend, 'Friend diary');
        $this->assertSame(['Friend diary'], $this->titles($friend));
        $this->assertNotContains('Friend diary', $this->titles($me));
        $this->assertContains('Owner secret plan', $this->titles($me));

        // the owner's records do not exist for the friend — by uuid, by folder, by picture
        $this->actingAs($friend)->getJson(route('me.takenote.show', $mine))->assertNotFound();
        $this->actingAs($friend)->patchJson(route('me.takenote.update', $mine), ['html' => '<p>x</p>', 'version' => 1])->assertNotFound();
        $this->actingAs($friend)->postJson(route('me.takenote.pin', $mine), ['pinned' => true])->assertNotFound();
        $this->actingAs($friend)->deleteJson(route('me.takenote.destroy', $mine))->assertNotFound();
        $this->actingAs($friend)->postJson(route('me.takenote.file', $theirs), ['folder' => $folder])->assertNotFound();
        $this->actingAs($friend)->patchJson(route('me.takenote.folders.update', $folder), ['name' => 'Mine now'])->assertNotFound();
        $pic = Asset::create(['card_id' => Card::withoutGlobalScopes()->where('uuid', $mine)->value('id'), 'kind' => 'image', 'path' => 'x.png', 'width' => 1, 'height' => 1, 'bytes' => 1, 'created_by' => $me->id]);
        $res = $this->actingAs($friend)->get(route('me.margin.pictures.show', $pic->uuid)); // a web 404 is the platform's redirect home
        $this->assertContains($res->status(), [302, 404]);
        $this->assertStringNotContainsString('image/', (string) $res->headers->get('Content-Type'));

        // and the other way round
        $this->actingAs($me)->getJson(route('me.takenote.show', $theirs))->assertNotFound();

        // a friend cannot invite anyone
        $this->actingAs($friend)->getJson(route('me.takenote.friends'))->assertNotFound();
        $this->actingAs($friend)->postJson(route('me.takenote.friends.store'), ['email' => 'x@example.com'])->assertNotFound();
    }

    public function test_only_a_verified_account_with_that_email_opens_an_invitation(): void
    {
        $me = $this->owner();
        $this->actingAs($me)->postJson(route('me.takenote.friends.store'), ['email' => 'amigo@example.com'])->assertOk();

        $unverified = $this->friend('amigo@example.com', false);
        // refused — by the platform's own verified-email gate (403) before TakeNote's (404)
        $this->assertContains($this->actingAs($unverified)->getJson(route('me.takenote.data'))->status(), [403, 404]);
        $this->assertNull(Notebook::where('email', 'amigo@example.com')->value('owner_id'));

        $stranger = $this->friend('stranger@example.com');
        $this->actingAs($stranger)->getJson(route('me.takenote.data'))->assertNotFound();
    }

    public function test_removing_a_friend_closes_their_notebook_and_inviting_again_gives_it_back(): void
    {
        $me = $this->owner();
        $this->actingAs($me)->postJson(route('me.takenote.friends.store'), ['email' => 'amigo@example.com'])->assertOk();
        $friend = $this->friend();
        $this->note($friend, 'Kept safe');
        $book = Notebook::where('owner_id', $friend->id)->firstOrFail();
        $this->actingAs($me)->getJson(route('me.takenote.friends'))->assertJsonPath('friends.0.joined', true);

        $this->actingAs($me)->deleteJson(route('me.takenote.friends.destroy', $book->uuid))->assertOk()->assertJsonPath('friends', []);
        $this->actingAs($friend)->getJson(route('me.takenote.data'))->assertNotFound();

        $this->actingAs($me)->postJson(route('me.takenote.friends.store'), ['email' => 'amigo@example.com'])->assertOk();
        $this->assertSame(['Kept safe'], $this->titles($friend));
    }

    public function test_the_invited_address_gets_an_email_in_its_own_language(): void
    {
        Mail::fake();
        $me = $this->owner();

        // no account yet: the inviter's language
        $this->actingAs($me)->withCredentials()->withCookie('takenote_locale', 'pt_BR')->postJson(route('me.takenote.friends.store'), ['email' => 'nova@example.com'])->assertOk();
        Mail::assertQueued(TakeNoteInvite::class, fn ($m) => $m->hasTo('nova@example.com') && $m->locale === 'pt_BR' && $m->link === route('takenote.app'));

        // an account with a language of its own: theirs
        $this->defaultCookies = [];
        $this->createUser(['email' => 'ayse@example.com'])->forceFill(['locale' => 'tr'])->save();
        $this->actingAs($me)->postJson(route('me.takenote.friends.store'), ['email' => 'ayse@example.com'])->assertOk();
        Mail::assertQueued(TakeNoteInvite::class, fn ($m) => $m->hasTo('ayse@example.com') && $m->locale === 'tr');

        // a refused invitation sends nothing
        $this->actingAs($me)->postJson(route('me.takenote.friends.store'), ['email' => 'nova@example.com'])->assertStatus(422);
        Mail::assertQueuedCount(2);

        // it renders in every language TakeNote speaks, the invited address and the link in it
        foreach (array_keys(config('devboard.locales')) as $lang) {
            $html = (new TakeNoteInvite('Ghassan', 'nova@example.com', route('takenote.app')))->locale($lang)->render();
            $this->assertStringContainsString('nova@example.com', $html);
            $this->assertStringContainsString(route('takenote.app'), $html);
            $this->assertStringContainsString(e(trans('devboard::messages.mi_button', [], $lang)), $html); // as the page escapes it ("TakeNote'u aç")
        }
    }

    private function makeLink(User $as, ?string $label = null): string
    {
        return $this->actingAs($as)->postJson(route('me.takenote.friends.link'), ['label' => $label])->assertOk()->json('link');
    }

    public function test_a_link_lets_a_friend_sign_up_and_write_straight_away_without_confirming_their_email(): void
    {
        $me = $this->owner();
        $mine = $this->note($me, 'Owner secret plan');
        $link = $this->makeLink($me, 'Lucas (Brazil)');
        $this->assertMatchesRegularExpression('#/me/takenote/join/[A-Za-z0-9]{40}$#', $link);
        $this->actingAs($me)->getJson(route('me.takenote.friends'))->assertJsonPath('friends.0.label', 'Lucas (Brazil)')->assertJsonPath('friends.0.link', true);
        $this->assertNull(Notebook::where('label', 'Lucas (Brazil)')->value('email'));
        $this->assertStringNotContainsString(basename($link), (string) Notebook::where('label', 'Lucas (Brazil)')->value('invite_hash'), 'only a hash is kept');

        // a stranger opens it, signed out: TakeNote's own door with the form
        auth()->logout();
        $this->get($link)->assertOk()->assertSee('name="full_name"', false);
        $this->post($link, ['full_name' => 'Lucas Silva', 'email' => 'lucas@example.com.br', 'password' => 'Str0ng!Passw0rd', 'password_confirmation' => 'Str0ng!Passw0rd'])
            ->assertRedirect(route('takenote.app'));
        $lucas = User::where('email', 'lucas@example.com.br')->firstOrFail();
        $this->assertFalse($lucas->hasVerifiedEmail(), 'no confirmation needed to start');
        $this->assertAuthenticatedAs($lucas);

        // straight in: an empty notebook of his own; nothing of the owner's
        $this->assertSame([], $this->titles($lucas));
        $this->note($lucas, 'Lucas first note');
        $this->assertSame(['Lucas first note'], $this->titles($lucas));
        $this->actingAs($lucas)->getJson(route('me.takenote.show', $mine))->assertNotFound();
        $this->assertNotContains('Lucas first note', $this->titles($me));

        // ...and ONLY TakeNote: the rest of TAKEONE still wants the email confirmed
        $this->assertNotSame(200, $this->actingAs($lucas)->get(route('me.people'))->status());

        // the link is used up: the next person to open it is told so, and cannot sign up through it
        auth()->logout();
        $this->get($link)->assertNotFound()->assertDontSee('name="full_name"', false);
        $this->post($link, ['full_name' => 'Someone Else', 'email' => 'else@example.com', 'password' => 'Str0ng!Passw0rd', 'password_confirmation' => 'Str0ng!Passw0rd'])
            ->assertRedirect($link);
        $this->assertFalse(User::where('email', 'else@example.com')->exists());
    }

    public function test_a_signed_in_person_claims_a_link_once_and_nobody_else_is_opened_by_it(): void
    {
        $me = $this->owner();
        $link = $this->makeLink($me);

        // somebody who already has TakeNote goes in, and the link stays for whoever it was meant for
        $this->actingAs($me)->get($link)->assertRedirect(route('takenote.app'));
        $this->assertSame(1, Notebook::whereNotNull('invite_hash')->count());

        // a signed-in TAKEONE member with no TakeNote: the link becomes theirs
        $ana = $this->friend('ana@example.com');
        $this->actingAs($ana)->get($link)->assertRedirect(route('takenote.app'));
        $this->assertSame([], $this->titles($ana));
        $this->assertSame(0, Notebook::whereNotNull('invite_hash')->count());

        // a friend cannot make links; a nonsense or unknown token answers like a used one
        $this->actingAs($ana)->postJson(route('me.takenote.friends.link'), [])->assertNotFound();
        auth()->logout();
        $this->get('/me/takenote/join/'.str_repeat('a', 40))->assertNotFound();

        // an unconfirmed account that did NOT come through a link still meets `verified` on TakeNote
        $plain = $this->friend('plain@example.com', false);
        $this->actingAs($plain)->getJson(route('me.takenote.data'))->assertForbidden();
    }

    public function test_a_friend_invited_by_email_signs_up_in_takenote_and_the_confirmation_brings_them_back_to_takenote(): void
    {
        Mail::fake();
        $me = $this->owner();
        $this->actingAs($me)->postJson(route('me.takenote.friends.store'), ['email' => 'sofia@example.com'])->assertOk();
        auth()->logout();

        // TakeNote's sign-in door → "New here?" → TakeNote's own sign-up (not the platform's /register)
        $this->get(route('takenote.app'))->assertOk()->assertSee(route('takenote.signup'), false);
        $this->get(route('takenote.signup'))->assertOk()->assertSee('name="full_name"', false);
        $this->post(route('takenote.signup.store'), ['full_name' => 'Sofia Lima', 'email' => 'sofia@example.com', 'password' => 'Str0ng!Passw0rd', 'password_confirmation' => 'Str0ng!Passw0rd'])
            ->assertRedirect(route('takenote.signup'));
        $sofia = User::where('email', 'sofia@example.com')->firstOrFail();
        $this->assertFalse($sofia->hasVerifiedEmail(), 'an EMAIL invitation still needs the address confirmed');

        // waiting: TakeNote's own "check your email", not the TAKEONE page
        $this->get(route('takenote.app'))->assertRedirect(route('takenote.signup'));
        $this->get(route('takenote.signup'))->assertOk()->assertSee('sofia@example.com');

        // the confirmation email's link carries TakeNote as where it lands — wherever it is opened
        $sent = Mail::queued(\App\Members\Mail\WelcomeEmail::class)->filter(fn ($m) => $m->hasTo('sofia@example.com'));
        $this->assertCount(1, $sent, 'one confirmation email, not two');
        $verify = $sent->first()->content()->with['verificationUrl'];
        $this->assertStringContainsString('intended='.urlencode(route('takenote.app')), $verify);

        auth()->logout(); // opened from the Gmail app: no session
        $this->get($verify)->assertRedirect(route('verification.notice'))->assertSessionHas('verified_intended', route('takenote.app'));
        // ...and the "confirmed" page that follows sends her on to TakeNote, not to Explore (28 Oct 2026)
        $this->get(route('verification.notice'))->assertRedirect(route('takenote.app'));
        $this->assertTrue($sofia->fresh()->hasVerifiedEmail());
        $this->get(route('takenote.app'))->assertOk()->assertSee('tn-config', false);
        $this->assertSame([], $this->titles($sofia->fresh()), 'her own notebook, from the invitation');
        $this->assertSame($sofia->id, Notebook::where('email', 'sofia@example.com')->value('owner_id'));
    }

    public function test_resending_the_confirmation_from_takenote_still_brings_them_back_to_takenote(): void
    {
        Mail::fake();
        $u = $this->friend('late@example.com', false);
        $this->actingAs($u)->post(route('takenote.signup.resend'))->assertRedirect(route('takenote.signup'))->assertSessionHas('resent');
        $sent = Mail::queued(\App\Members\Mail\WelcomeEmail::class)->filter(fn ($m) => $m->hasTo('late@example.com'));
        $this->assertCount(1, $sent);
        $this->assertStringContainsString('intended='.urlencode(route('takenote.app')), $sent->first()->content()->with['verificationUrl']);
    }

    public function test_the_platforms_own_confirmation_still_lands_where_it_always_did(): void
    {
        $u = $this->friend('plain@example.com', false);
        $link = \Illuminate\Support\Facades\URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $u->id, 'hash' => sha1($u->email)]);
        $this->get($link)->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertRedirect(route('clubs.explore'));
    }

    public function test_signing_out_of_takenote_lands_on_takenotes_own_sign_in(): void
    {
        $me = $this->owner();
        $this->actingAs($me)->get(route('takenote.app'))->assertOk()->assertSee(route('takenote.signout'), false);
        $this->post(route('takenote.signout'))->assertRedirect(route('takenote.app'));
        $this->assertGuest();
        $this->get(route('takenote.app'))->assertOk()->assertSee('door-form', false);
        $this->post(route('takenote.signout'))->assertRedirect(route('takenote.app')); // twice is harmless
    }

    public function test_a_notes_file_can_be_renamed_by_its_owner_only(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $me = $this->owner();
        $note = $this->note($me, 'Plans');
        $pdf = \Illuminate\Http\UploadedFile::fake()->createWithContent('scan.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $a = $this->actingAs($me)->post(route('me.takenote.cards.files.store', $note), ['file' => $pdf], ['Accept' => 'application/json'])->assertOk()->json('attachment');

        // the pencil beside a note's file (28 Oct 2026): a new name, cleaned of slashes and control characters
        $this->actingAs($me)->patchJson(route('me.takenote.cards.attachments.update', [$note, $a['uuid']]), ['name' => "Club survey/2026\x07.pdf"])
            ->assertOk()->assertJsonPath('attachment.name', 'Club survey 2026 .pdf');

        // a friend's notebook never reaches it, however the address is typed
        $this->actingAs($me)->postJson(route('me.takenote.friends.store'), ['email' => 'amigo@example.com'])->assertOk();
        $friend = $this->friend();
        $this->actingAs($friend)->patchJson(route('me.takenote.cards.attachments.update', [$note, $a['uuid']]), ['name' => 'mine now.pdf'])->assertNotFound();
        $this->assertSame('Club survey 2026 .pdf', \App\DevBoard\Models\Attachment::withoutGlobalScopes()->where('uuid', $a['uuid'])->value('name'));
    }

    public function test_somebody_signed_in_without_an_invitation_is_told_takenote_is_invite_only(): void
    {
        $stranger = $this->friend('nobody@example.com');
        $this->actingAs($stranger)->get(route('takenote.app'))->assertRedirect(route('takenote.signup'));
        $this->actingAs($stranger)->get(route('takenote.signup'))->assertForbidden()->assertDontSee('tn-config', false);
    }

    public function test_invitations_are_checked(): void
    {
        $me = $this->owner();
        $this->actingAs($me)->postJson(route('me.takenote.friends.store'), ['email' => 'not an email'])->assertStatus(422);
        $this->actingAs($me)->postJson(route('me.takenote.friends.store'), ['email' => $me->email])->assertStatus(422);
        $this->actingAs($me)->postJson(route('me.takenote.friends.store'), ['email' => 'amigo@example.com'])->assertOk();
        $this->actingAs($me)->postJson(route('me.takenote.friends.store'), ['email' => 'AMIGO@example.com'])->assertStatus(422);

        $main = Notebook::where('is_main', true)->firstOrFail();
        $this->actingAs($me)->deleteJson(route('me.takenote.friends.destroy', $main->uuid))->assertNotFound();
    }

    public function test_takenote_speaks_the_language_chosen_on_this_device(): void
    {
        $me = $this->owner();
        $this->actingAs($me)->postJson(route('me.takenote.locale'), ['locale' => 'xx'])->assertStatus(422);
        $this->actingAs($me)->postJson(route('me.takenote.locale'), ['locale' => 'tr'])->assertOk()->assertCookie('takenote_locale', 'tr');

        $html = $this->actingAs($me)->withCookie('takenote_locale', 'ja')->get(route('takenote.app'))->assertOk()->getContent();
        $this->assertStringContainsString('<html lang="ja"', $html);
        $this->assertStringContainsString(trans('devboard::messages.tn_search', [], 'ja'), $html);

        // with nothing chosen, the browser's language: Brazilian Portuguese
        $this->defaultCookies = [];
        $html =$this->actingAs($me)->get(route('takenote.app'), ['Accept-Language' => 'pt-BR,pt;q=0.9'])->getContent();
        $this->assertStringContainsString('<html lang="pt-BR"', $html);
    }
}
