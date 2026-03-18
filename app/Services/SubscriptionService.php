<?php

namespace App\Services;

use App\Models\ClubAffiliation;
use App\Models\ClubMemberSubscription;
use App\Models\ClubPackage;
use App\Models\ClubTransaction;
use App\Models\Membership;
use App\Models\SkillAcquisition;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ClubCache;
use Carbon\Carbon;

class SubscriptionService
{
    /**
     * Check if a user meets the age and gender requirements for a package.
     * Returns an error message string on failure, null on success.
     */
    public function checkEligibility(ClubPackage $package, string $memberName, ?int $age, ?string $gender): ?string
    {
        if ($package->age_min !== null && $age !== null && $age < $package->age_min) {
            return "'{$memberName}' does not meet the minimum age ({$package->age_min}) for package '{$package->name}'.";
        }

        if ($package->age_max !== null && $age !== null && $age > $package->age_max) {
            return "'{$memberName}' exceeds the maximum age ({$package->age_max}) for package '{$package->name}'.";
        }

        if ($package->gender && $package->gender !== 'mixed' && $gender) {
            $match = ($package->gender === 'male'   && $gender === 'm')
                  || ($package->gender === 'female' && $gender === 'f');
            if (!$match) {
                return "Package '{$package->name}' is restricted to " . ucfirst($package->gender) . " members. '{$memberName}' is not eligible.";
            }
        }

        return null;
    }

    /**
     * Check whether an active or pending subscription already exists for
     * this user + package + club combination.
     */
    public function isDuplicate(int $tenantId, int $userId, int $packageId): bool
    {
        return ClubMemberSubscription::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('package_id', $packageId)
            ->whereIn('status', ['active', 'pending'])
            ->exists();
    }

    /**
     * Create a pending subscription for a member joining via the explore page,
     * along with its corresponding income transaction.
     */
    public function createPending(
        Tenant $club,
        int $userId,
        ClubPackage $package,
        string $paymentStatus,
        ?string $proofPath,
        string $notes
    ): ClubMemberSubscription {
        $subscription = ClubMemberSubscription::create([
            'tenant_id'        => $club->id,
            'type'             => 'regular',
            'user_id'          => $userId,
            'package_id'       => $package->id,
            'start_date'       => now(),
            'end_date'         => now()->addMonths($package->duration_months),
            'status'           => 'pending',
            'payment_status'   => $paymentStatus,
            'amount_paid'      => 0,
            'amount_due'       => $package->price,
            'proof_of_payment' => $proofPath,
            'notes'            => $notes,
        ]);

        ClubTransaction::create([
            'tenant_id'        => $club->id,
            'user_id'          => $userId,
            'subscription_id'  => $subscription->id,
            'type'             => 'income',
            'category'         => 'subscription',
            'amount'           => $package->price,
            'description'      => 'Package: ' . $package->name,
            'transaction_date' => now(),
        ]);

        // Ensure the user appears in the club members index
        Membership::firstOrCreate(
            ['tenant_id' => $club->id, 'user_id' => $userId],
            ['status' => 'active']
        );

        $this->syncAffiliation($club, $userId, $subscription, $package);

        ClubCache::flushStats($club->id);
        ClubCache::flushFinancials($club->id);

        return $subscription;
    }

    /**
     * Create an active (already paid) subscription for a walk-in registration,
     * along with its corresponding income transaction.
     */
    public function createActive(
        Tenant $club,
        int $userId,
        ClubPackage $package,
        string $transactionDescription
    ): ClubMemberSubscription {
        $subscription = ClubMemberSubscription::create([
            'tenant_id'      => $club->id,
            'user_id'        => $userId,
            'package_id'     => $package->id,
            'type'           => 'regular',
            'status'         => 'active',
            'payment_status' => 'paid',
            'amount_paid'    => $package->price,
            'amount_due'     => 0,
            'start_date'     => now(),
            'end_date'       => now()->addMonths($package->duration_months),
        ]);

        ClubTransaction::create([
            'tenant_id'        => $club->id,
            'user_id'          => $userId,
            'subscription_id'  => $subscription->id,
            'type'             => 'income',
            'category'         => 'subscription',
            'amount'           => $package->price,
            'description'      => $transactionDescription,
            'transaction_date' => now(),
        ]);

        // Ensure the user appears in the club members index
        Membership::firstOrCreate(
            ['tenant_id' => $club->id, 'user_id' => $userId],
            ['status' => 'active']
        );

        $this->syncAffiliation($club, $userId, $subscription, $package);

        ClubCache::flushStats($club->id);
        ClubCache::flushFinancials($club->id);

        return $subscription;
    }

    /**
     * Approve a pending subscription payment and update financial records.
     */
    public function approvePayment(ClubMemberSubscription $subscription, ?string $proofPath, User $approvedBy): void
    {
        $subscription->update([
            'payment_status'   => 'paid',
            'amount_paid'      => $subscription->amount_due,
            'amount_due'       => 0,
            'proof_of_payment' => $proofPath ?? $subscription->proof_of_payment,
        ]);

        activity('financial')
            ->causedBy($approvedBy)
            ->performedOn($subscription)
            ->withProperties(['amount' => $subscription->amount_paid, 'club_id' => $subscription->tenant_id])
            ->log('Payment approved');

        ClubCache::flushFinancials($subscription->tenant_id);
    }

    /**
     * Find or create the ClubAffiliation for this member+club, link the
     * subscription to it, recalculate its date span, and sync skills from
     * the package's activities.
     */
    private function syncAffiliation(
        Tenant $club,
        int $userId,
        ClubMemberSubscription $subscription,
        ClubPackage $package
    ): void {
        // Find or create one affiliation per member per club
        $affiliation = ClubAffiliation::firstOrCreate(
            ['member_id' => $userId, 'tenant_id' => $club->id],
            [
                'club_name'   => $club->club_name,
                'logo'        => $club->logo ?? null,
                'location'    => $club->address ?? null,
                'start_date'  => $subscription->start_date,
                'end_date'    => $subscription->end_date,
                'description' => null,
                'coaches'     => [],
            ]
        );

        // Link the subscription to the affiliation
        $subscription->update(['club_affiliation_id' => $affiliation->id]);

        // Recalculate date span across ALL subscriptions for this member+club
        $allSubs = ClubMemberSubscription::where('tenant_id', $club->id)
            ->where('user_id', $userId)
            ->whereNotNull('start_date')
            ->get();

        $earliestStart = $allSubs->min('start_date');
        $hasActive     = $allSubs->whereIn('status', ['active', 'pending'])->isNotEmpty();
        $latestEnd     = $hasActive ? null : $allSubs->max('end_date');

        // Pull coach names from all package activities across all subs for this club
        $packageIds = $allSubs->pluck('package_id')->unique()->filter();
        $coaches = \App\Models\ClubPackageActivity::whereIn('package_id', $packageIds)
            ->with('instructor.user')
            ->get()
            ->pluck('instructor.user.full_name')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $affiliation->update([
            'club_name'  => $club->club_name,
            'logo'       => $club->logo ?? $affiliation->logo,
            'location'   => $club->address ?? $affiliation->location,
            'start_date' => $earliestStart,
            'end_date'   => $latestEnd,
            'coaches'    => $coaches ?: $affiliation->coaches,
        ]);

        // Sync skills from this package's activities (skip duplicates)
        $package->load('packageActivities.activity', 'packageActivities.instructor');

        $existingSkillMap = $affiliation->skillAcquisitions()
            ->whereIn('activity_id', $package->packageActivities->pluck('activity_id')->filter())
            ->pluck('instructor_id', 'activity_id')
            ->toArray();

        foreach ($package->packageActivities as $pkgActivity) {
            if (!$pkgActivity->activity) continue;

            if (array_key_exists($pkgActivity->activity_id, $existingSkillMap)) {
                // Update instructor if it was previously NULL
                if (is_null($existingSkillMap[$pkgActivity->activity_id]) && $pkgActivity->instructor_id) {
                    $affiliation->skillAcquisitions()
                        ->where('activity_id', $pkgActivity->activity_id)
                        ->update(['instructor_id' => $pkgActivity->instructor_id]);
                }
                continue;
            }

            $affiliation->skillAcquisitions()->create([
                'skill_name'        => $pkgActivity->activity->name,
                'icon'              => 'bi-star',
                'proficiency_level' => 'beginner',
                'start_date'        => $subscription->start_date,
                'duration_months'   => $package->duration_months ?? 1,
                'package_id'        => $package->id,
                'activity_id'       => $pkgActivity->activity_id,
                'instructor_id'     => $pkgActivity->instructor_id,
            ]);
        }
    }
}
