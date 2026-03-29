<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionExpiryEmail;
use App\Models\ClubMemberSubscription;
use App\Models\ClubNotification;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendSubscriptionExpiryReminders extends Command
{
    protected $signature = 'subscriptions:send-expiry-reminders';

    protected $description = 'Send expiry reminders to members whose package expires in 3 days';

    public function handle(): int
    {
        $targetDate = Carbon::today()->addDays(3)->toDateString();

        $subscriptions = ClubMemberSubscription::with(['user', 'package', 'tenant.owner'])
            ->where('status', 'active')
            ->whereDate('end_date', $targetDate)
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions expiring in 3 days.');
            return 0;
        }

        $count = 0;

        foreach ($subscriptions as $subscription) {
            $user   = $subscription->user;
            $tenant = $subscription->tenant;
            $owner  = $tenant->owner;

            if (! $user || ! $tenant || ! $owner) {
                continue;
            }

            $isPaid  = $subscription->payment_status === 'paid';
            $subject = $isPaid
                ? 'Your package expires in 3 days — Renew now'
                : 'Your package expires in 3 days — Renew & complete payment';

            $message = $isPaid
                ? "Your membership package \"{$subscription->package?->name}\" at {$tenant->club_name} will expire on {$subscription->end_date->format('F j, Y')}. Please renew to keep your access."
                : "Your membership package \"{$subscription->package?->name}\" at {$tenant->club_name} will expire on {$subscription->end_date->format('F j, Y')}. You also have an outstanding balance of {$subscription->amount_due}. Please renew and complete your payment.";

            // Create bell notification
            $notification = ClubNotification::create([
                'tenant_id'      => $tenant->id,
                'sender_user_id' => $owner->id,
                'subject'        => $subject,
                'message'        => $message,
                'recipient_type' => 'selected',
                'recipient_count' => 1,
                'sent_at'        => now(),
            ]);

            UserNotification::create([
                'club_notification_id' => $notification->id,
                'user_id'              => $user->id,
                'tenant_id'            => $tenant->id,
                'is_read'              => false,
            ]);

            // Queue email
            Mail::to($user->email)->queue(
                new SubscriptionExpiryEmail($subscription, $user)
            );

            $count++;
        }

        $this->info("Sent {$count} expiry reminder(s).");

        return 0;
    }
}
