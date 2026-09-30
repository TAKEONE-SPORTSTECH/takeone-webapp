<?php

namespace Tests\Feature\Federations;

use App\Federations\Models\Federation;
use App\Federations\Models\FederationEvent;
use App\Members\Models\User;
use Tests\TestCase;

/**
 * The club invitation in the event page's design (federations.invite_v2).
 *
 * What this protects: the prototype page keeps serving until the switch is on
 * (or a super-admin previews); the reader's language is only ever one the
 * organiser offers; and only a manager of the federation that runs the event
 * may change which languages those are.
 */
class InvitePageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['federations.enabled' => true, 'federations.invite_v2' => false]);
    }

    private function federation(User $owner): Federation
    {
        $f = new Federation;
        $f->forceFill([
            'owner_user_id' => $owner->id, 'name' => 'Test Karate Federation', 'short_name' => 'TKF',
            'country' => 'BH', 'currency' => 'BHD', 'sport' => 'Karate', 'color' => '#1e2c4f',
            'status' => Federation::STATUS_APPROVED, 'approved_at' => now(),
        ])->save();

        return $f->fresh();
    }

    private function event(Federation $f): FederationEvent
    {
        $e = new FederationEvent;
        $e->forceFill([
            'federation_id' => $f->id, 'created_by' => $f->owner_user_id,
            'name' => 'Spring Karate Open', 'sport' => 'Karate', 'status' => 'Planning',
            'starts_on' => now()->addMonth()->format('Y-m-d').'T09:00',
            'details' => [
                'venue' => 'Isa Sports City', 'city' => 'Manama', 'about' => '<p>Come and <b>compete</b>.</p>',
                'enrollEnd' => now()->addWeeks(2)->toDateString(), 'types' => ['Individual Kumite'],
                'fees' => [['label' => 'Individual Kumite', 'amount' => '15']], 'free' => false,
                'reqs' => ['Bring a passport.'],
            ],
        ])->save();

        return $e->fresh();
    }

    private function url(FederationEvent $e, string $extra = ''): string
    {
        return '/federation/app/Club%20Invitation.dc.html?event='.$e->uuid.$extra;
    }

    public function test_the_prototype_keeps_serving_while_the_switch_is_off(): void
    {
        $e = $this->event($this->federation($this->createUser()));

        $html = $this->get($this->url($e))->assertOk()->getContent();
        $this->assertStringNotContainsString('clubInvite()', $html);
        $this->assertStringContainsString('federation-store.platform.js', $html);

        // Its new doors are closed too.
        $this->get('/federation/invite/'.$e->uuid.'/settings')->assertNotFound();
        $this->put('/federation/invite/'.$e->uuid.'/locale', ['locale' => 'ar'])->assertNotFound();
    }

    public function test_switched_on_it_is_the_event_page_with_the_invitation_inside(): void
    {
        config(['federations.invite_v2' => true]);
        $e = $this->event($this->federation($this->createUser()));

        $this->get($this->url($e))->assertOk()
            ->assertSee('clubInvite()', false)
            ->assertSee('Spring Karate Open')
            ->assertSee('data-cover-enter', false)          // the event page's cover
            ->assertSee('margin-top:-46px', false)          // its quick-facts card
            ->assertSee('Isa Sports City')
            ->assertSee('Come and compete.');               // the organiser's words, as prose, never as HTML
    }

    public function test_a_super_admin_may_preview_it_and_nobody_else_can(): void
    {
        $e = $this->event($this->federation($this->createUser()));

        $member = $this->createUser();
        $this->actingAs($member)->get($this->url($e, '&preview=v2'))->assertOk()->assertDontSee('clubInvite()', false);

        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);
        $this->actingAs($admin->fresh())->get($this->url($e, '&preview=v2'))->assertOk()->assertSee('clubInvite()', false);
        // Remembered for that session, and turned off the same way.
        $this->actingAs($admin->fresh())->get($this->url($e))->assertSee('clubInvite()', false);
        $this->actingAs($admin->fresh())->get($this->url($e, '&preview=off'))->assertDontSee('clubInvite()', false);
    }

    public function test_the_reader_may_only_choose_a_language_the_organiser_offers(): void
    {
        config(['federations.invite_v2' => true]);
        $e = $this->event($this->federation($this->createUser()));   // default: English + Arabic

        $this->put('/federation/invite/'.$e->uuid.'/locale', ['locale' => 'ar'])->assertRedirect();
        $this->assertSame('ar', session('event_locale')[$e->uuid] ?? null);

        $this->put('/federation/invite/'.$e->uuid.'/locale', ['locale' => 'fr'])->assertRedirect();
        $this->assertSame('ar', session('event_locale')[$e->uuid] ?? null);   // French is not offered: ignored
    }

    public function test_only_a_manager_of_the_federation_changes_the_languages(): void
    {
        config(['federations.invite_v2' => true]);
        $owner = $this->createUser();
        $e = $this->event($this->federation($owner));
        $payload = ['offered' => ['en', 'fr'], 'source' => 'en'];

        // A guest is offered the sign-in, and cannot save.
        $this->get('/federation/invite/'.$e->uuid.'/settings')->assertOk()->assertSee('name="password"', false)
            ->assertDontSee(__('federations::invite.settings_save'));
        $this->putJson('/federation/invite/'.$e->uuid.'/settings', $payload)->assertUnauthorized();

        // Somebody who does not run it: refused, nothing changed.
        $stranger = $this->createUser();
        $this->actingAs($stranger)->get('/federation/invite/'.$e->uuid.'/settings')->assertOk()->assertDontSee(__('federations::invite.settings_save'));
        $this->actingAs($stranger)->putJson('/federation/invite/'.$e->uuid.'/settings', $payload)->assertForbidden();
        $this->assertSame(FederationEvent::DEFAULT_LOCALES, $e->fresh()->offeredLocales());

        // The federation's owner.
        $this->actingAs($owner->fresh())->get('/federation/invite/'.$e->uuid.'/settings')->assertOk()->assertSee(__('federations::invite.settings_save'));
        $this->actingAs($owner->fresh())->putJson('/federation/invite/'.$e->uuid.'/settings', $payload)
            ->assertOk()->assertJsonPath('success', true);
        $this->assertEqualsCanonicalizing(['en', 'fr'], $e->fresh()->offeredLocales());

        // A code that is not a language is refused.
        $this->actingAs($owner->fresh())->putJson('/federation/invite/'.$e->uuid.'/settings', ['offered' => ['xx'], 'source' => 'en'])
            ->assertStatus(422);
    }

    public function test_share_never_carries_a_clubs_personal_token(): void
    {
        config(['federations.invite_v2' => true]);
        $e = $this->event($this->federation($this->createUser()));

        $html = $this->get($this->url($e, '&club=secret-token-that-is-long-enough-000000'))->assertOk()->getContent();
        $this->assertStringContainsString("location.origin + location.pathname + '?event='", $html);
    }
}
