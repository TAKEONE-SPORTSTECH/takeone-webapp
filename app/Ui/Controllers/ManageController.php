<?php

namespace App\Ui\Controllers;

use App\Clubs\Models\ClubMemberSubscription;
use App\Clubs\Support\ChainDashboardService;
use App\Http\Controllers\Controller;
use App\Ui\Manage\ManageScope;
use App\Ui\NewUi;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * /me/manage — the Manage side of Play/Manage (new UI only).
 *
 * Shows the organisation picker and an overview for the chosen business, club
 * or federation. The organisation comes from the person's OWN list
 * (ManageScope); a key outside it is ignored, never trusted. Figures come from
 * ChainDashboardService::forClubs — the same rules as the chain dashboard.
 */
class ManageController extends Controller
{
    public function show(Request $request): View
    {
        abort_unless(NewUi::active(), 404);

        $user = $request->user();
        $orgs = ManageScope::orgsFor($user);
        abort_if(empty($orgs) && ! $user->isSuperAdmin(), 404);

        $current = collect($orgs)->firstWhere('key', (string) $request->query('org')) ?? ($orgs[0] ?? null);
        $overview = $current ? $this->overview($current, $orgs) : null;

        $isMobile = (bool) $request->attributes->get('is_mobile', false);

        return view($isMobile ? 'ui::manage.mobile' : 'ui::manage.desktop', [
            'orgs' => $orgs,
            'current' => $current,
            'overview' => $overview,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function overview(array $org, array $orgs): ?array
    {
        if ($org['kind'] === 'federation') {
            return null;
        }

        /** @var Collection $clubs */
        $clubs = $org['kind'] === 'business' ? $org['clubs'] : collect([$org['club']]);
        $data = app(ChainDashboardService::class)->forClubs($clubs);
        $ids = $clubs->pluck('id')->all();

        // Month buckets summed across clubs (the chart is per club).
        $months = [];
        foreach ($data['revenue_chart']['datasets'] ?? [] as $set) {
            foreach (array_values($set['data'] ?? []) as $i => $v) {
                $months[$i] = ($months[$i] ?? 0) + (float) $v;
            }
        }

        $proofs = $ids ? ClubMemberSubscription::whereIn('tenant_id', $ids)->where('payment_status', 'pending_approval')
            ->selectRaw('tenant_id, COUNT(*) as c')->groupBy('tenant_id')->pluck('c', 'tenant_id') : collect();
        $ending = $ids ? ClubMemberSubscription::whereIn('tenant_id', $ids)->whereIn('status', ['active'])
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->selectRaw('tenant_id, COUNT(*) as c')->groupBy('tenant_id')->pluck('c', 'tenant_id') : collect();

        $byId = collect($orgs)->where('kind', 'club')->keyBy(fn ($o) => $o['club']->id);
        $needs = [];
        foreach ($clubs as $c) {
            $o = $byId[$c->id] ?? null;
            if (($n = (int) ($proofs[$c->id] ?? 0)) > 0) {
                $needs[] = ['org' => $o, 'text' => trans_choice('ui::manage.needs_proofs', $n, ['count' => $n]), 'url' => route('admin.club.financials', $c->slug), 'cta' => __('ui::manage.review')];
            }
            if (($n = (int) ($ending[$c->id] ?? 0)) > 0) {
                $needs[] = ['org' => $o, 'text' => trans_choice('ui::manage.needs_ending', $n, ['count' => $n]), 'url' => route('admin.club.members', $c->slug), 'cta' => __('ui::manage.open')];
            }
        }

        return [
            'totals' => $data['totals'],
            'rows' => $data['clubs'],
            'months' => array_values($months),
            'thisMonth' => (float) (end($months) ?: 0),
            'needs' => $needs,
            'clubOrgs' => $byId,
        ];
    }
}
