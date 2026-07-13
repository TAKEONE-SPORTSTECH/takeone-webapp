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
     * @param  \Illuminate\Support\Collection|iterable  $invoices  already-loaded invoices for the member
     * @return \Illuminate\Support\Collection<int,object>
     */
    protected function buildMemberPayments(User $member, $invoices): Collection
    {
        $subStatusMap = [
            'paid' => ['paid', 'Paid'],
            'pending_approval' => ['pending', 'Pending review'],
            'unpaid' => ['due', 'Due'],
        ];

        $payments = collect();

        foreach ($invoices as $inv) {
            $payments->push((object) [
                'type' => 'invoice',
                'date' => $inv->created_at,
                'club' => $inv->tenant->club_name ?? 'N/A',
                'club_logo' => $inv->tenant?->logo ? asset('storage/'.$inv->tenant->logo) : null,
                'item' => 'Invoice',
                'amount' => $inv->amount,
                'status_key' => $inv->status === 'paid' ? 'paid' : ($inv->status === 'due' ? 'due' : 'other'),
                'status_label' => ucfirst((string) $inv->status),
                'receipt_id' => $inv->id,
                'has_proof' => false,
                'subscription_id' => null,
                'currency' => $inv->tenant->currency ?? '',
                'period' => null,
                'settleable' => false,
            ]);
        }

        $subscriptions = ClubMemberSubscription::where('user_id', $member->id)
            ->with(['tenant', 'package'])
            ->get();

        foreach ($subscriptions as $sub) {
            [$key, $label] = $subStatusMap[$sub->payment_status]
                ?? ['other', ucfirst(str_replace('_', ' ', (string) $sub->payment_status))];

            $period = $sub->start_date
                ? $sub->start_date->format('d M Y').($sub->end_date ? ' – '.$sub->end_date->format('d M Y') : '')
                : optional($sub->created_at)->format('d M Y');

            $payments->push((object) [
                'type' => 'subscription',
                'date' => $sub->created_at,
                'club' => $sub->tenant->club_name ?? 'N/A',
                'club_logo' => $sub->tenant?->logo ? asset('storage/'.$sub->tenant->logo) : null,
                'item' => $sub->package->name ?? 'Subscription',
                'amount' => $sub->amount_due,
                'status_key' => $key,
                'status_label' => $label,
                'receipt_id' => null,
                'has_proof' => (bool) $sub->proof_of_payment,
                'subscription_id' => $sub->id,
                'currency' => $sub->tenant->currency ?? '',
                'period' => $period,
                'settleable' => in_array($sub->payment_status, ['unpaid', 'pending_approval'], true),
            ]);
        }

        return $payments->sortByDesc('date')->values();
    }
}
