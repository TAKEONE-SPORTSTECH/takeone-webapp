<?php

namespace App\Traits;

use App\Models\ClubMemberSubscription;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds a member's unified "Payment History": explicit Invoice rows PLUS
 * club-package ClubMemberSubscription rows (what self-registration / "join club"
 * creates — amount_due, payment_status, proof_of_payment). Both are normalised to
 * one shape so the billing tab can render them in a single date-sorted list.
 */
trait BuildsMemberPayments
{
    /**
     * @param  \App\Models\User  $member
     * @param  \Illuminate\Support\Collection|iterable  $invoices  already-loaded invoices for the member
     * @return \Illuminate\Support\Collection<int,object>
     */
    protected function buildMemberPayments(User $member, $invoices): Collection
    {
        $subStatusMap = [
            'paid'             => ['paid', 'Paid'],
            'pending_approval' => ['pending', 'Pending review'],
            'unpaid'           => ['due', 'Due'],
        ];

        $payments = collect();

        foreach ($invoices as $inv) {
            $payments->push((object) [
                'type'         => 'invoice',
                'date'         => $inv->created_at,
                'club'         => $inv->tenant->club_name ?? 'N/A',
                'item'         => 'Invoice',
                'amount'       => $inv->amount,
                'status_key'   => $inv->status === 'paid' ? 'paid' : ($inv->status === 'due' ? 'due' : 'other'),
                'status_label' => ucfirst((string) $inv->status),
                'receipt_id'   => $inv->id,
                'has_proof'    => false,
            ]);
        }

        $subscriptions = ClubMemberSubscription::where('user_id', $member->id)
            ->with(['tenant', 'package'])
            ->get();

        foreach ($subscriptions as $sub) {
            [$key, $label] = $subStatusMap[$sub->payment_status]
                ?? ['other', ucfirst(str_replace('_', ' ', (string) $sub->payment_status))];
            $payments->push((object) [
                'type'         => 'subscription',
                'date'         => $sub->created_at,
                'club'         => $sub->tenant->club_name ?? 'N/A',
                'item'         => $sub->package->name ?? 'Subscription',
                'amount'       => $sub->amount_due,
                'status_key'   => $key,
                'status_label' => $label,
                'receipt_id'   => null,
                'has_proof'    => (bool) $sub->proof_of_payment,
            ]);
        }

        return $payments->sortByDesc('date')->values();
    }
}
