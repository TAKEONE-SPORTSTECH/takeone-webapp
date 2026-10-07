<?php

namespace App\Ui\Explore;

use App\Clubs\Models\ClubActivity;
use App\Clubs\Models\ClubPackage;
use App\Clubs\Models\Tenant;
use App\Federations\Models\Federation;
use App\Members\Models\User;
use App\Platform\Models\ActivityCatalog;
use App\Shop\Models\ClubProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The new Explore page (/explore while App\Ui\NewUi::active()).
 *
 * The public entry point the Clubs module calls — it renders a view and owns
 * no data. Clubs, activities and trainers are read from their own modules'
 * models with the SAME scope the classic page used (every club to any
 * signed-in member; trainers who opted in); events are fetched by the page
 * from the existing explore.events endpoint, which already decides who may see
 * which event.
 *
 * Only the fields a card shows leave the server: never an owner's email or
 * phone (the classic nearby endpoint still sends those — NEW-UI.md).
 */
class ExplorePage
{
    public static function render(Request $request): View
    {
        $isMobile = (bool) $request->attributes->get('is_mobile', false);
        $data = self::data();

        return view($isMobile ? 'ui::explore.mobile' : 'ui::explore.desktop', ['explore' => $data])
            ->with('shellTitle', __('ui::messages.explore'));
    }

    /** @return array<string, mixed> */
    public static function data(): array
    {
        $catalog = ActivityCatalog::where('is_active', true)->orderByDesc('usage_count')->orderBy('name')->get();
        $bySlug = $catalog->keyBy('slug');
        $byName = $catalog->keyBy(fn ($a) => mb_strtolower(trim($a->getRawOriginal('name') ?? $a->name)));
        $match = fn (string $n) => $byName->get(mb_strtolower(trim($n))) ?? $bySlug->get(ActivityCatalog::slugFor($n));

        // club → catalog entries it offers (club activities carry no FK; matched by name, as everywhere else)
        $offered = [];
        foreach (ClubActivity::query()->get(['tenant_id', 'name']) as $ca) {
            if ($entry = $match((string) $ca->name)) {
                $offered[$ca->tenant_id][$entry->uuid] = $entry->uuid;
            }
        }
        $clubsPerActivity = [];
        foreach ($offered as $uuids) {
            foreach ($uuids as $u) {
                $clubsPerActivity[$u] = ($clubsPerActivity[$u] ?? 0) + 1;
            }
        }

        $activities = $catalog->map(function (ActivityCatalog $a) use ($clubsPerActivity) {
            $name = (string) $a->name;
            $group = ActivityGroups::groupFor($name);

            return [
                'uuid' => $a->uuid,
                'name' => $name,
                'group' => $group,
                'color' => ActivityGroups::colourFor($name),
                'initials' => self::initials($name),
                'description' => self::plain((string) $a->description),
                'styles' => collect($a->variants ?? [])->pluck('name')->filter()->values()->all(),
                'picture' => $a->picture_url ? file_url($a->picture_url) : null,
                'clubs' => $clubsPerActivity[$a->uuid] ?? 0,
                'url' => route('activity.show', $a->uuid),
            ];
        })->values();

        $names = $activities->keyBy('uuid');
        $clubs = self::clubs($offered, $names);

        return [
            'activities' => $activities->all(),
            'groups' => collect(ActivityGroups::GROUPS)->map(fn ($g, $k) => ['key' => $k, 'label' => __($g[0]), 'color' => $g[1]])->values()->all(),
            'clubs' => $clubs->all(),
            'trainers' => self::trainers()->all(),
            'products' => self::products()->all(),
            'federations' => self::federations()->all(),
            'eventsUrl' => route('explore.events', ['scope' => 'public']),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private static function clubs(array $offered, Collection $activities): Collection
    {
        $prices = ClubPackage::where('is_active', true)->where('price', '>', 0)
            ->get(['tenant_id', 'price', 'duration_months'])
            ->groupBy('tenant_id')
            ->map(fn ($ps) => $ps->sortBy('price')->first());

        return Tenant::query()
            ->withCount(['members', 'approvedReviews as reviews_count'])
            ->withAvg('approvedReviews as rating', 'rating')
            ->get()
            ->map(function (Tenant $c) use ($offered, $activities, $prices) {
                $acts = collect($offered[$c->id] ?? [])->map(fn ($u) => $activities[$u] ?? null)->filter()->values();
                $p = $prices[$c->id] ?? null;

                return [
                    'key' => $c->slug,
                    'name' => (string) $c->club_name,
                    'url' => $c->url,
                    'logo' => $c->logo ? file_url($c->logo) : null,
                    'cover' => $c->cover_image ? file_url($c->cover_image) : null,
                    'initials' => self::initials((string) $c->club_name),
                    'color' => $acts->first()['color'] ?? '#7c3aed',
                    'lat' => $c->gps_lat ? (float) $c->gps_lat : null,
                    'lng' => $c->gps_long ? (float) $c->gps_long : null,
                    'area' => self::area($c->address),
                    'activities' => $acts->pluck('name')->all(),
                    'activityIds' => $acts->pluck('uuid')->all(),
                    'members' => (int) $c->members_count,
                    'rating' => $c->rating ? round((float) $c->rating, 1) : null,
                    'reviews' => (int) $c->reviews_count,
                    'price' => $p ? self::money((float) $p->price, (string) ($c->currency ?: 'BHD')) : null,
                    'per' => $p ? ((int) $p->duration_months === 1 ? 'month' : ((int) $p->duration_months > 1 ? (int) $p->duration_months.' months' : null)) : null,
                ];
            })->values();
    }

    /** Same opt-in as the classic page: users.is_personal_trainer. */
    private static function trainers(): Collection
    {
        return User::where('is_personal_trainer', true)
            ->with(['clubInstructors.tenant', 'clubInstructors.reviews'])
            ->get()
            ->map(function (User $u) {
                $reviews = $u->clubInstructors->flatMap->reviews;
                $club = $u->clubInstructors->first()?->tenant;

                return [
                    'key' => $u->uuid,
                    'name' => (string) ($u->full_name ?? $u->name),
                    'gender' => $u->gender,
                    'photo' => ($u->profile_picture && $u->profile_picture_is_public) ? file_url($u->profile_picture) : null,
                    'role' => (string) ($u->clubInstructors->first()?->role ?? ''),
                    'years' => (int) ($u->experience_years ?? 0),
                    'rating' => round((float) ($reviews->avg('rating') ?? 0), 1),
                    'reviews' => $reviews->count(),
                    'club' => $u->clubInstructors->first()?->tenant?->club_name,
                    // Where they coach — their club's pin, for the map.
                    'lat' => $club?->gps_lat ? (float) $club->gps_lat : null,
                    'lng' => $club?->gps_long ? (float) $club->gps_long : null,
                    'url' => route('trainer.show', $u->id),
                ];
            })->values();
    }

    /**
     * Shop: every published product, opening the existing product page
     * (me.market.show — it already shows any published product to any member).
     * A card's fields only: never cost, margin, supplier or stock counts.
     */
    private static function products(): Collection
    {
        return ClubProduct::where('status', 'published')
            ->whereHas('tenant')
            ->with(['tenant:id,slug,club_name,currency,gps_lat,gps_long', 'variants:id,club_product_id,price,is_active'])
            ->orderByDesc('featured')->latest()
            ->limit(60)->get()
            ->map(function (ClubProduct $p) {
                $active = $p->variants->where('is_active', true);
                $price = $active->isNotEmpty() ? (float) $active->min('price') : (float) $p->price;
                $old = $p->old_price !== null && (float) $p->old_price > $price ? (float) $p->old_price : null;
                $cur = (string) ($p->tenant->currency ?: 'BHD');

                return [
                    'key' => 'p'.$p->id,
                    'name' => (string) $p->name,
                    'brand' => $p->brand ? (string) $p->brand : null,
                    'image' => $p->image_path ? file_url($p->image_path) : null,
                    'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $p->color) ? $p->color : '#0f766e',
                    'club' => (string) $p->tenant->club_name,
                    'price' => self::money($price, $cur),
                    'from' => $active->count() > 1,
                    'old' => $old ? self::money($old, $cur) : null,
                    'off' => $old ? '−'.(int) round(100 - $price / $old * 100).'%' : null,
                    'rating' => $p->rating_count ? round($p->rating_sum / $p->rating_count, 1) : null,
                    'reviews' => (int) $p->rating_count,
                    'lat' => $p->tenant->gps_lat ? (float) $p->tenant->gps_lat : null,
                    'lng' => $p->tenant->gps_long ? (float) $p->tenant->gps_long : null,
                    'url' => route('me.market.show', $p->id),
                ];
            })->values();
    }

    /**
     * Federations: approved and not suspended. There is no public federation
     * page yet, so a card opens the federation's events on this page instead.
     */
    private static function federations(): Collection
    {
        if (! config('federations.enabled') || ! Route::has('federations.logo')) {
            return collect();
        }

        return Federation::approved()->whereNull('suspended_at')
            ->withCount('clubs')
            ->orderBy('name')->get()
            ->map(fn (Federation $f) => [
                'key' => $f->uuid,
                'name' => (string) $f->name,
                'short' => $f->short_name ? (string) $f->short_name : null,
                'country' => $f->country ? strtoupper((string) $f->country) : null,
                'sports' => collect($f->sports ?: [$f->sport])->filter()->map(fn ($s) => Str::headline((string) $s))->values()->all(),
                'color' => $f->bandColor(),
                'logo' => $f->logo ? route('federations.logo', $f->uuid).'?v='.substr(md5((string) $f->logo), 0, 8) : null,
                'initials' => self::initials((string) ($f->short_name ?: $f->name)),
                'clubs' => (int) $f->clubs_count,
                'clubs_label' => trans_choice('ui::explore.n_fed_clubs', (int) $f->clubs_count),
            ])->values();
    }

    /** Catalogue descriptions are HTML with section headings: keep the prose only. */
    private static function plain(string $html): string
    {
        $text = preg_replace('#<h[1-6][^>]*>.*?</h[1-6]>#is', ' ', $html) ?? '';
        $text = html_entity_decode(strip_tags(preg_replace('#<(br|/p|/li|/div)[^>]*>#i', ' ', $text) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 160);
    }

    private static function initials(string $name): string
    {
        return mb_strtoupper(collect(preg_split('/[\s\-]+/u', trim($name)) ?: [])->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
    }

    private static function area(?string $address): ?string
    {
        if (! $address) {
            return null;
        }
        $parts = array_values(array_filter(array_map('trim', explode(',', $address))));

        return $parts ? Str::limit($parts[count($parts) > 1 ? 1 : 0], 28) : null;
    }

    private static function money(float $amount, string $currency): string
    {
        $label = strtoupper($currency) === 'BHD' ? 'BD' : strtoupper($currency);
        $n = fmod($amount, 1.0) == 0.0 ? number_format($amount, 0) : number_format($amount, 3);

        return $label.' '.$n;
    }
}
