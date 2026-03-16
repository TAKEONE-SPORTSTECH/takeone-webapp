<?php

namespace App\Services;

use App\Models\ClubMemberSubscription;
use App\Models\ClubPackage;
use App\Models\ClubTransaction;
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
}
