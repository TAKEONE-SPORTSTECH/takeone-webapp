<?php

namespace App\Federations\Services;

use App\Federations\Models\Federation;
use App\Federations\Models\FederationInvoice;
use App\Federations\Models\FederationSubscription;
use App\Members\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The TakeOne subscription, per sport and per billing account, in the shape
 * the designed dashboard reads (`fed-billing:<account id>`).
 *
 * The page computes a price to SHOW; this class computes the price to CHARGE,
 * with the same rules (PLATFORM_SPEC §3):
 *  - the billing account is the nearest body, itself or above, that pays for
 *    itself; a top-level body always does;
 *  - each sport is charged once per account, its athletes summed across every
 *    body in that account to pick the tier;
 *  - up to 300 athletes BHD 45 · up to 1,500 BHD 95 · beyond BHD 180, per sport
 *    per month; annual = 10 × monthly;
 *  - 14-day free trial per account.
 *
 * No card is ever taken (no gateway): "I've sent the transfer" creates a
 * Pending invoice, and only a platform administrator marks it Paid.
 */
class FederationBilling
{
    public const TRIAL_DAYS = 14;

    public function __construct(private FederationDirectory $directory) {}

    /** The paying body for this one. */
    public function account(Federation $f): Federation
    {
        $cur = $f;
        $guard = 0;
        while ($cur->parent_id && $cur->payer !== Federation::PAYER_SELF && $guard++ < 10) {
            $up = Federation::find($cur->parent_id);
            if (! $up) {
                break;
            }
            $cur = $up;
        }

        return $cur;
    }

    /** @return array{0: string, 1: int} tier label, monthly price */
    public function tier(int $athletes): array
    {
        return $athletes <= 300 ? ['Up to 300', 45] : ($athletes <= 1500 ? ['Up to 1,500', 95] : ['Unlimited', 180]);
    }

    /** @return array<int, array{sport: string, athletes: int, bodies: array, tier: string, month: int, amount: int}> */
    public function lines(Federation $account, string $cycle): array
    {
        $members = collect($this->directory->subtree($account))
            ->filter(fn (Federation $f) => ! $f->isSuspended() && $this->account($f)->is($account));

        $bySport = [];
        foreach ($members as $f) {
            foreach ((array) $f->sports as $sport) {
                $bySport[$sport] ??= ['athletes' => 0, 'bodies' => []];
                $bySport[$sport]['athletes'] += $this->directory->athleteCount($f, $sport);
                $bySport[$sport]['bodies'][] = $f->name;
            }
        }
        ksort($bySport);

        $out = [];
        foreach ($bySport as $sport => $row) {
            [$label, $month] = $this->tier($row['athletes']);
            $out[] = [
                'sport' => $sport, 'athletes' => $row['athletes'], 'bodies' => $row['bodies'],
                'tier' => $label, 'month' => $month, 'amount' => $cycle === 'annual' ? $month * 10 : $month,
            ];
        }

        return $out;
    }

    /** The bank reference for this account's n-th invoice — the page draws the same one. */
    public function invoiceNumber(Federation $account, int $n): string
    {
        return 'INV-'.now()->year.'-'.strtoupper(substr(str_replace('-', '', $account->uuid), 0, 4)).'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /**
     * `fed-billing:<id>` for every account the viewer's tree touches. An
     * account ABOVE the viewer's own body shows its status only — never its
     * invoices.
     *
     * @return array<string, array>
     */
    public function payloads(Federation $root): array
    {
        $bodies = $this->directory->subtree($root);
        $inside = collect($bodies)->pluck('id')->all();
        $out = [];

        foreach ($bodies as $f) {
            $acc = $this->account($f);
            if (! isset($out['fed-billing:'.$acc->uuid])) {
                $out['fed-billing:'.$acc->uuid] = $this->payload($acc, in_array($acc->id, $inside, true));
            }
        }

        return $out;
    }

    public function payload(Federation $account, bool $full = true): array
    {
        $sub = FederationSubscription::where('federation_id', $account->id)->first();
        $trialEnds = $sub?->trial_ends_at ?? CarbonImmutable::parse($account->created_at)->addDays(self::TRIAL_DAYS);
        $invoices = FederationInvoice::where('federation_id', $account->id)
            ->where('status', '!=', FederationInvoice::STATUS_VOID)->latest('id')->get();

        $paidUntil = $sub?->paid_until;
        $status = match (true) {
            $paidUntil && $paidUntil->isFuture() => 'active',
            $invoices->contains('status', FederationInvoice::STATUS_PENDING) => 'pending',
            $trialEnds->isFuture() => 'trial',
            default => 'due',
        };

        return [
            'plan' => $sub?->plan ?: 'sport',
            'cycle' => $sub?->cycle ?: 'monthly',
            'status' => $status,
            'trialEnds' => $trialEnds->toIso8601String(),
            'card' => null,
            'nextDue' => $paidUntil?->toIso8601String(),
            'invoices' => $full ? $invoices->map(fn (FederationInvoice $i) => [
                'no' => $i->number,
                'date' => $i->created_at->toIso8601String(),
                'desc' => $i->description,
                'amount' => (float) $i->amount,
                'status' => $i->status,
                'method' => $i->method,
            ])->values()->all() : [],
        ];
    }

    /**
     * The page saved `fed-billing:<id>`. The one thing it may ask for is a new
     * bank-transfer invoice; the amount, number and status are ours.
     */
    public function sync(User $user, array $access, string $accountUuid, array $posted): array
    {
        $root = $access['root'];
        $account = collect($this->directory->subtree($root))->first(fn (Federation $f) => $f->uuid === $accountUuid);

        // Only a manager, only an account inside their own tree, and only a
        // body that really pays for itself.
        if (! $account || ! $access['manager'] || ! $this->account($account)->is($account)) {
            $shown = Federation::where('uuid', $accountUuid)->first();

            return $shown && collect($this->directory->subtree($root))->contains(fn ($f) => $this->account($f)->is($shown))
                ? $this->payload($shown, false) : [];
        }

        $known = FederationInvoice::where('federation_id', $account->id)->pluck('number')->all();
        $asked = collect($posted['invoices'] ?? [])->first(fn ($i) => is_array($i)
            && ($i['status'] ?? null) === FederationInvoice::STATUS_PENDING
            && ! in_array($i['no'] ?? null, $known, true));

        if ($asked) {
            $cycle = ($posted['cycle'] ?? '') === 'annual' ? 'annual' : 'monthly';

            DB::transaction(function () use ($account, $cycle, $user) {
                // One open transfer at a time: a second click adds nothing.
                if (FederationInvoice::where('federation_id', $account->id)->where('status', FederationInvoice::STATUS_PENDING)->lockForUpdate()->exists()) {
                    return;
                }
                $lines = $this->lines($account, $cycle);
                if ($lines === []) {
                    return;
                }
                $n = FederationInvoice::where('federation_id', $account->id)->count() + 1;

                FederationInvoice::create([
                    'federation_id' => $account->id,
                    'number' => $this->invoiceNumber($account, $n),
                    'description' => count($lines).' sport'.(count($lines) === 1 ? '' : 's').' · '.($cycle === 'annual' ? 'Annual' : 'Monthly'),
                    'cycle' => $cycle,
                    'amount' => array_sum(array_column($lines, 'amount')),
                    'currency' => 'BHD',
                    'lines' => $lines,
                    'method' => 'Bank transfer',
                    'status' => FederationInvoice::STATUS_PENDING,
                    'requested_by' => $user->id,
                ]);

                $sub = FederationSubscription::firstOrNew(['federation_id' => $account->id]);
                $sub->plan = 'sport';
                $sub->cycle = $cycle;
                $sub->trial_ends_at ??= CarbonImmutable::parse($account->created_at)->addDays(self::TRIAL_DAYS);
                $sub->save();
            });
        }

        return $this->payload($account);
    }

    /** A platform administrator confirms the transfer arrived. */
    public function markPaid(FederationInvoice $invoice, ?User $by): void
    {
        if ($invoice->status !== FederationInvoice::STATUS_PENDING) {
            return;
        }

        DB::transaction(function () use ($invoice, $by) {
            $invoice->update(['status' => FederationInvoice::STATUS_PAID, 'confirmed_at' => now(), 'confirmed_by' => $by?->id]);

            $sub = FederationSubscription::firstOrNew(['federation_id' => $invoice->federation_id]);
            $from = $sub->paid_until && $sub->paid_until->isFuture() ? CarbonImmutable::parse($sub->paid_until) : CarbonImmutable::now();
            $sub->cycle = $invoice->cycle;
            $sub->paid_until = $invoice->cycle === 'annual' ? $from->addYear() : $from->addMonth();
            $sub->save();
        });
    }
}
