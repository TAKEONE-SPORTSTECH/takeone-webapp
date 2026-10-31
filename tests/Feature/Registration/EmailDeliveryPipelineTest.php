<?php

namespace Tests\Feature\Registration;

use App\Members\Mail\WelcomeEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Registration audit — the email pipeline itself.
 *
 * Staging and production share one database since 2026-10-30, and with it one
 * `jobs` queue. These tests pin down what that means for a verification email:
 * since 2026-10-31 the link is built when the mail is QUEUED, with the queuing
 * box's address and signing key — whichever worker sends it cannot change it.
 *
 * @group registration-audit
 */
class EmailDeliveryPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function verifyLink(string $html): string
    {
        $this->assertSame(1, preg_match('~href="([^"]*/email/verify/[^"]+)"~', $html, $m), 'the welcome mail carries a verification link');

        return html_entity_decode($m[1]);
    }

    /** AUDIT: FIXED (2026-10-31) — the link is built when the mail is QUEUED, so the sending worker's address cannot change it. */
    public function test_the_verification_link_is_built_by_the_queuing_box_not_the_sending_worker(): void
    {
        $user = $this->createUser(['email' => 'member@example.com', 'email_verified_at' => null]);

        URL::forceRootUrl('https://takeone.bh');          // production queues the mail…
        $mail = new WelcomeEmail($user);
        $mail = unserialize(serialize($mail));             // …it travels through the shared jobs queue…

        URL::forceRootUrl('https://stage.takeone.bh');    // …a staging worker picks it up and renders it
        $link = $this->verifyLink($mail->render());

        $this->assertStringStartsWith('https://takeone.bh/email/verify/', $link);
    }

    /** AUDIT: FIXED (2026-10-31) — the link is signed with the queuing box's key, so another box's worker sending it does not break it. */
    public function test_a_link_rendered_by_another_boxs_worker_still_verifies(): void
    {
        $user = $this->createUser(['email' => 'member@example.com', 'email_verified_at' => null]);
        $productionKey = config('app.key');
        $stagingKey = 'base64:'.base64_encode(str_repeat('s', 32));

        $mail = unserialize(serialize(new WelcomeEmail($user)));   // queued (and signed) on production

        app('url')->setKeyResolver(fn () => $stagingKey);  // rendered by the other box's worker
        $link = $this->verifyLink($mail->render());

        app('url')->setKeyResolver(fn () => $productionKey); // opened on the box the member uses
        $this->get($link)->assertRedirect(route('verification.notice'));
        $this->assertNotNull($user->fresh()->email_verified_at, 'verified');
    }

    /** AUDIT: OK — control: the same link signed and opened with one key verifies the member. */
    public function test_a_link_signed_and_opened_with_the_same_key_verifies(): void
    {
        $user = $this->createUser(['email' => 'member@example.com', 'email_verified_at' => null]);

        $link = $this->verifyLink((new WelcomeEmail($user))->render());

        $this->get($link)->assertRedirect(route('verification.notice'));
        $this->assertNotNull($user->fresh()->email_verified_at, 'verified');
    }

    /** AUDIT: FIXED (2026-10-31) — the link lasts 24 hours (was 60 minutes) from the moment it is QUEUED, so a late retry still delivers a live link. */
    public function test_the_link_lasts_a_day_from_queuing(): void
    {
        $user = $this->createUser(['email' => 'member@example.com', 'email_verified_at' => null]);
        $link = $this->verifyLink((new WelcomeEmail($user))->render());

        $this->travel(61)->minutes();                      // dead under the old 60-minute rule
        $this->get($link)->assertRedirect(route('verification.notice'));
        $this->assertNotNull($user->fresh()->email_verified_at, 'verified after an hour');
    }

    /** AUDIT: OK — and it does expire: past 24 hours the link is refused. */
    public function test_the_link_expires_after_a_day(): void
    {
        $user = $this->createUser(['email' => 'member@example.com', 'email_verified_at' => null]);
        $link = $this->verifyLink((new WelcomeEmail($user))->render());

        $this->travel(1441)->minutes();

        $this->get($link)->assertRedirect(route('login'));
        $this->assertNull($user->fresh()->email_verified_at, 'still unverified');
    }
}
