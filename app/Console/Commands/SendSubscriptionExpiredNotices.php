<?php

namespace App\Console\Commands;

use App\Models\ClubMemberSubscription;
use App\Models\ClubNotification;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendSubscriptionExpiredNotices extends Command
{
    protected $signature = 'subscriptions:send-expired-notices';

    protected $description = 'Notify members whose membership package expired yesterday (their subscription is now over)';

    public function handle(): int
    {
        // A subscription whose last day of access was yesterday is, as of today,
        // over. Targeting end_date = yesterday means each subscription is picked
        // up exactly once by this daily command — no duplicate notifications and
        // no need to mutate the subscription's status.
        $expiredOn = Carbon::yesterday()->toDateString();

        $subscriptions = ClubMemberSubscription::with(['user', 'package', 'tenant.owner'])
            ->where('status', 'active')
            ->whereDate('end_date', $expiredOn)
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions expired yesterday.');

            return 0;
        }

        $count = 0;

        foreach ($subscriptions as $subscription) {
            $user = $subscription->user;
            $tenant = $subscription->tenant;
            $owner = $tenant?->owner;

            if (! $user || ! $tenant || ! $owner) {
                continue;
            }

            $packageName = $subscription->package?->name ?? 'membership package';

            $subject = 'Your membership has expired — Renew now';
            $message = "Your \"{$packageName}\" subscription at {$tenant->club_name} expired on "
                ."{$subscription->end_date->format('F j, Y')}. Renew now to keep your access.";

            $notification = ClubNotification::create([
                'tenant_id' => $tenant->id,
                'sender_user_id' => $owner->id,
                'subject' => $subject,
                'message' => $message,
                'action_url' => route('bills.index'),
                'recipient_type' => 'selected',
                'recipient_count' => 1,
                'sent_at' => now(),
            ]);

            UserNotification::create([
                'club_notification_id' => $notification->id,
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'is_read' => false,
            ]);

            $count++;
        }

        $this->info("Sent {$count} expired-subscription notice(s).");

        return 0;
    }
}
