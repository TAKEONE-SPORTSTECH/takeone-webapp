<?php

namespace Tests\Feature;

use App\Members\Models\UserNotification;
use App\Support\Mail\MailHealth;
use App\Support\Mail\RetriesDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Email can never again fail silently for months (28 Oct 2026: the live
 * server's Gmail password went stale and verifications, invoices and reminders
 * all failed with nobody told). Every email retries with waits; mail:health
 * tells the super-admins in the app when sending stops and starts; failed
 * emails are put back once it works.
 */
class MailResilienceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        MailHealth::$probe = null;
        parent::tearDown();
    }

    public function test_every_email_and_queued_notification_waits_and_tries_again(): void
    {
        $classes = [];
        foreach (Finder::create()->files()->in(app_path())->name('*.php')->contains('/extends (Mailable|Notification) implements ShouldQueue|extends Mailable\b/') as $file) {
            $classes[] = $file->getRelativePathname();
            $this->assertStringContainsString('use RetriesDelivery;', $file->getContents(), $file->getRelativePathname().' sends email without the retry rule');
        }
        $this->assertGreaterThanOrEqual(10, count($classes));

        $policy = new class { use RetriesDelivery; };
        $this->assertGreaterThan(3, $policy->tries);
        $this->assertCount($policy->tries - 1, $policy->backoff);
    }

    public function test_the_super_admins_are_told_once_when_email_stops_and_once_when_it_comes_back(): void
    {
        $admin = $this->createUser();
        $this->makeSuperAdmin($admin);
        $member = $this->createUser();

        MailHealth::$probe = fn () => 'Expected response code "235" but got code "535"';
        $this->artisan('mail:health')->assertFailed();
        $this->artisan('mail:health')->assertFailed(); // still down: no second notice
        $this->assertSame(1, UserNotification::where('user_id', $admin->id)->where('type', 'mail_down')->count());
        $this->assertSame(0, UserNotification::where('user_id', $member->id)->count(), 'only super-admins are told');
        $this->assertFalse(MailHealth::state()['ok']);

        // a failed email waiting meanwhile is put back the moment sending works
        Queue::fake();
        $uuid = $this->failedJob('Illuminate\\Mail\\SendQueuedMailable');
        MailHealth::$probe = fn () => null;
        $this->artisan('mail:health')->assertSuccessful();
        $this->assertSame(1, UserNotification::where('user_id', $admin->id)->where('type', 'mail_up')->count());
        $this->assertFalse(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 'put back in the queue');
    }

    public function test_only_recent_emails_are_put_back_a_few_times_each_and_never_while_email_is_down(): void
    {
        Queue::fake();
        $mail = $this->failedJob('Illuminate\\Mail\\SendQueuedMailable');
        $notice = $this->failedJob('Illuminate\\Notifications\\SendQueuedNotifications');
        $other = $this->failedJob('App\\Translation\\Jobs\\TranslateContent');
        $old = $this->failedJob('Illuminate\\Mail\\SendQueuedMailable', now()->subDays(10));

        // while email is down, nothing is retried (it would only burn the attempts)
        MailHealth::record('535 bad credentials');
        $this->artisan('mail:retry-failed')->assertSuccessful();
        $this->assertSame(4, DB::table('failed_jobs')->count());

        MailHealth::record(null);
        $this->artisan('mail:retry-failed')->assertSuccessful();
        $left = DB::table('failed_jobs')->pluck('uuid')->all();
        $this->assertNotContains($mail, $left);
        $this->assertNotContains($notice, $left);
        $this->assertContains($other, $left, 'not an email: left alone');
        $this->assertContains($old, $left, 'too old: left alone');

        // the same email failing again and again is given up on after --max
        Cache::put('mail.retry.'.$mail, 3, now()->addDay());
        DB::table('failed_jobs')->insert(['uuid' => $mail, 'connection' => 'database', 'queue' => 'default', 'payload' => $this->payload('Illuminate\\Mail\\SendQueuedMailable', $mail), 'exception' => 'x', 'failed_at' => now()]);
        $this->artisan('mail:retry-failed')->assertSuccessful();
        $this->assertTrue(DB::table('failed_jobs')->where('uuid', $mail)->exists());
    }

    private function failedJob(string $command, $at = null): string
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid, 'connection' => 'database', 'queue' => 'default',
            'payload' => $this->payload($command, $uuid), 'exception' => 'Failed to authenticate on SMTP server', 'failed_at' => $at ?? now(),
        ]);

        return $uuid;
    }

    private function payload(string $command, string $uuid): string
    {
        return json_encode(['uuid' => $uuid, 'displayName' => $command, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'attempts' => 3, 'data' => ['commandName' => $command, 'command' => 'O:8:"stdClass":0:{}']]);
    }
}
