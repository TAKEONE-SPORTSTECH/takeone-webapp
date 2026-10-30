<?php

namespace App\Clubs\Support;

/**
 * Everything the club front page draws, prepared ONCE for both the desktop and
 * the mobile view (clubs::public.show / clubs::public.mobile.show). Markup lives
 * in those two files; the reading of the models lives here, so the two pages can
 * never disagree about what a club has.
 *
 * Only real data. A section with nothing behind it comes back empty and is
 * simply not drawn — the page never shows a sample, a stock photo or an invented
 * number.
 *
 * Takes what PublicClubController::show() hands its view; returns `cp*` variables
 * the views extract().
 */
class ClubFrontPage
{
    public static function data(array $view): array
    {
        [
            'club' => $club, 'achievementsAll' => $achievementsAll, 'reviews' => $reviews,
            'likedPostIds' => $likedPostIds, 'joinedEventIds' => $joinedEventIds,
        ] = $view;

        $cpLocale = app()->getLocale();
        $cpCurrency = $club->currency ?: 'BHD';

        // The club's own colour where one is recorded (settings.brand_color, a #hex),
        // otherwise the design's red. Whitelisted: it lands inside a <style> block.
        $cpBrand = data_get($club->settings, 'brand_color');
        $cpAccent = is_string($cpBrand) && preg_match('/^#[0-9a-fA-F]{6}$/', $cpBrand) ? $cpBrand : '#C8102E';

        $cpMoney = function ($v) {
            $v = (float) $v;
            return rtrim(rtrim(number_format($v, 3, '.', ','), '0'), '.');
        };

        // ---- identity -------------------------------------------------------
        $cpName = $club->tr('club_name') ?: $club->club_name;
        $cpNameWords = preg_split('/\s+/u', trim($cpName));
        $cpNameTail = count($cpNameWords) > 1 ? array_pop($cpNameWords) : null;
        $cpNameHead = implode(' ', $cpNameWords);

        $cpLogo = $club->logo ? file_url($club->logo).'?v='.($club->updated_at?->timestamp ?? 1) : null;
        $cpCover = $club->cover_image ? file_url($club->cover_image)
            : ($club->galleryImages->first() ? file_url($club->galleryImages->first()->image_path) : null);

        $cpSports = $club->activities->pluck('name')
            ->merge($club->packages->flatMap(fn ($p) => $p->activities ?? collect())->pluck('name'))
            ->filter()->map(fn ($n) => trim($n))->unique(fn ($n) => mb_strtolower($n))->values();

        $cpCountryName = $club->country ? \App\Support\Countries::name($club->country) : null;
        $cpAddress = $club->tr('address');
        $cpPlace = $cpCountryName ?: $cpAddress;

        // Hero headline: the club's own slogan, broken across up to three lines with
        // the middle one drawn in outline. No slogan → the club's name, plainly.
        $cpSlogan = trim((string) $club->tr('slogan'));
        $cpHeadline = [];
        if ($cpSlogan !== '') {
            $w = preg_split('/\s+/u', rtrim($cpSlogan, '.!'));
            $n = count($w);
            $cpHeadline = $n >= 3
                ? [implode(' ', array_slice($w, 0, (int) ceil($n / 3))), implode(' ', array_slice($w, (int) ceil($n / 3), (int) ceil($n / 3))), implode(' ', array_slice($w, 2 * (int) ceil($n / 3)))]
                : $w;
            $cpHeadline = array_values(array_filter($cpHeadline, fn ($l) => $l !== ''));
        }

        $cpPrices = $club->packages->pluck('price')->filter(fn ($p) => $p !== null && (float) $p > 0);
        $cpFrom = $cpPrices->isNotEmpty() ? $cpMoney($cpPrices->min()) : null;

        $cpMedalTotals = [
            1 => (int) $achievementsAll->sum('medals_gold'),
            2 => (int) $achievementsAll->sum('medals_silver'),
            3 => (int) $achievementsAll->sum('medals_bronze'),
        ];
        $cpMedals = array_sum($cpMedalTotals);

        $cpCoachesCount = $club->instructors->count();

        // ---- contact --------------------------------------------------------
        $cpSocialIcon = function (string $platform): string {
            $p = strtolower($platform);
            foreach (['whatsapp' => 'bi-whatsapp', 'instagram' => 'bi-instagram', 'facebook' => 'bi-facebook', 'youtube' => 'bi-youtube',
                      'tiktok' => 'bi-tiktok', 'linkedin' => 'bi-linkedin', 'snapchat' => 'bi-snapchat', 'telegram' => 'bi-telegram',
                      'pinterest' => 'bi-pinterest', 'threads' => 'bi-threads', 'twitter' => 'bi-twitter-x'] as $k => $icon) {
                if (str_contains($p, $k)) return $icon;
            }
            return $p === 'x' ? 'bi-twitter-x' : 'bi-link-45deg';
        };
        $cpSafeUrl = fn ($u) => is_string($u) && preg_match('#^https?://#i', $u) ? $u : null;
        $cpSocial = $club->socialLinks
            ->map(fn ($l) => ['url' => $cpSafeUrl($l->url), 'icon' => $cpSocialIcon((string) $l->platform), 'label' => ucfirst((string) $l->platform)])
            ->filter(fn ($l) => $l['url'])->values();
        $cpWhatsapp = optional($cpSocial->firstWhere('icon', 'bi-whatsapp'))['url'] ?? null;
        $cpPhone = $club->contactPhone();
        $cpTel = $cpPhone ? 'tel:'.preg_replace('/[^0-9+]/', '', $cpPhone['code'].$cpPhone['number']) : null;
        if (! $cpWhatsapp && $cpPhone) {
            $cpWhatsapp = 'https://wa.me/'.preg_replace('/\D/', '', $cpPhone['code'].$cpPhone['number']);
        }

        // ---- where ----------------------------------------------------------
        $cpFacilities = $club->facilities->filter(fn ($f) => $f->is_available)->values();
        $cpHome = $cpFacilities->first();
        $cpHomeImage = null;
        if ($cpHome) {
            $imgs = collect($cpHome->images ?? [])->filter();
            $cpHomeImage = $imgs->isNotEmpty() ? file_url($imgs->first()) : ($cpHome->photo ? file_url($cpHome->photo) : null);
        }
        $cpMapsUrl = $cpSafeUrl($cpHome?->maps_url) ?: $cpSafeUrl($club->maps_url)
            ?: ($club->gps_lat && $club->gps_long ? 'https://www.google.com/maps?q='.((float) $club->gps_lat).','.((float) $club->gps_long) : null);

        // ---- people ---------------------------------------------------------
        $cpCoaches = $club->instructors->filter(fn ($i) => $i->user)->map(function ($i) {
            $u = $i->user;
            $showFace = $u->profile_picture && ($u->profile_picture_is_public ?? true);
            return [
                'name' => $u->full_name ?? $u->name,
                'role' => $i->tr('role') ?: __('clubs::page.coach'),
                'photo' => $showFace ? file_url($u->profile_picture).'?v='.($u->updated_at?->timestamp ?? 1) : null,
                'gender' => $u->gender,
                'bio' => $u->bio ? \Illuminate\Support\Str::limit(strip_tags($u->bio), 140) : null,
                'url' => route('trainer.show', $i->user_id),
                'rating' => round((float) ($i->reviews->avg('rating') ?? 0), 1),
                'reviews' => $i->reviews->count(),
            ];
        })->values();

        // ---- programmes -----------------------------------------------------
        $cpDayOrder = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $cpDayShort = fn ($d) => \Carbon\Carbon::parse($d)->locale($cpLocale)->translatedFormat('D');

        // Each activity opens its public directory page when the catalog knows it.
        $cpActivityLinks = \App\Platform\Models\ActivityCatalog::linksForNames(
            $club->packages->flatMap(fn ($p) => $p->activities ?? collect())->pluck('name')
        );

        $cpPackages = $club->packages->map(function ($p) use ($cpMoney, $cpDayOrder, $cpDayShort, $cpActivityLinks) {
            $min = $p->age_min; $max = $p->age_max;
            $kids = $max !== null && $max <= 12;
            $teens = ($min === null || $min <= 17) && ($max === null || $max >= 12) && ! ($min === null && $max === null);
            $adults = $max === null || $max >= 18;
            $ladies = $p->gender === 'female';

            $days = []; $coaches = [];
            foreach ($p->packageActivities as $pa) {
                $slots = is_string($pa->schedule) ? json_decode($pa->schedule, true) : $pa->schedule;
                foreach ((is_array($slots) ? $slots : []) as $s) {
                    $d = strtolower($s['day'] ?? '');
                    if (in_array($d, $cpDayOrder, true)) $days[$d] = true;
                }
                if ($pa->instructor?->user) $coaches[] = ['name' => $pa->instructor->user->full_name ?? $pa->instructor->user->name, 'url' => route('trainer.show', $pa->instructor->user_id)];
            }
            uksort($days, fn ($a, $b) => array_search($a, $cpDayOrder) <=> array_search($b, $cpDayOrder));

            if ($min !== null && $max !== null) $ages = __('clubs::page.ages_range', ['min' => $min, 'max' => $max]);
            elseif ($min !== null) $ages = __('clubs::page.ages_plus', ['min' => $min]);
            elseif ($max !== null) $ages = __('clubs::page.ages_upto', ['max' => $max]);
            else $ages = __('clubs::page.all_ages');

            $months = (int) ($p->duration_months ?: 1);

            return [
                'id' => $p->id,
                'name' => $p->name,
                'about' => $p->description ? \Illuminate\Support\Str::limit(strip_tags($p->description), 110) : null,
                'ages' => $ages,
                'gender' => $p->gender && $p->gender !== 'mixed' ? __('clubs::page.gender_'.$p->gender) : null,
                'days' => collect(array_keys($days))->map($cpDayShort)->implode(' · '),
                'coach' => collect($coaches)->unique('name')->first(),
                'activities' => $p->activities->filter(fn ($a) => trim((string) $a->name) !== '')->unique('name')
                    ->map(fn ($a) => ['name' => $a->name, 'url' => $cpActivityLinks[mb_strtolower(trim($a->name))] ?? null])->values()->all(),
                'price' => $p->price !== null ? $cpMoney($p->price) : null,
                'per' => $months === 1 ? __('clubs::page.per_month') : __('clubs::page.per_months', ['n' => $months]),
                'cover' => $p->cover_image ? file_url($p->cover_image) : null,
                'applicable' => (bool) $p->is_applicable,
                'cats' => array_keys(array_filter(['kids' => $kids, 'teens' => $teens, 'adults' => $adults, 'ladies' => $ladies])),
            ];
        })->values();

        $cpCats = collect(['kids', 'teens', 'adults', 'ladies'])
            ->filter(fn ($c) => $cpPackages->contains(fn ($p) => in_array($c, $p['cats'], true)))->values();

        // "Who is training?" — only the doors this club actually has a programme behind.
        $cpWho = collect([
            ['cat' => 'kids',   'icon' => 'bi-balloon-heart', 'label' => __('clubs::page.who_child'),  'sub' => __('clubs::page.who_child_sub')],
            ['cat' => 'teens',  'icon' => 'bi-lightning-charge', 'label' => __('clubs::page.who_teen'), 'sub' => __('clubs::page.who_teen_sub')],
            ['cat' => 'adults', 'icon' => 'bi-person-arms-up', 'label' => __('clubs::page.who_adult'), 'sub' => __('clubs::page.who_adult_sub')],
        ])->filter(fn ($w) => $cpCats->contains($w['cat']))->map(function ($w) use ($club) {
            if ($w['cat'] !== 'kids') return $w; // open-ended adult programmes make teen/adult ranges meaningless
            // The ages this door really covers, read off the club's own programmes.
            $pk = $club->packages->filter(fn ($p) => match ($w['cat']) {
                'kids' => $p->age_max !== null && $p->age_max <= 12,
                'teens' => ($p->age_min === null || $p->age_min <= 17) && ($p->age_max === null || $p->age_max >= 12) && ! ($p->age_min === null && $p->age_max === null),
                default => $p->age_max === null || $p->age_max >= 18,
            });
            $min = $pk->pluck('age_min')->filter(fn ($v) => $v !== null)->min();
            $max = $pk->contains(fn ($p) => $p->age_max === null) ? null : $pk->pluck('age_max')->max();
            if ($min !== null && $max !== null) $w['sub'] = __('clubs::page.ages_range', ['min' => $min, 'max' => $max]);
            elseif ($min !== null) $w['sub'] = __('clubs::page.ages_plus', ['min' => $min]);
            return $w;
        })->values();

        // ---- the week -------------------------------------------------------
        $cpSlots = [];
        foreach ($club->packages as $pkg) {
            foreach ($pkg->packageActivities as $pa) {
                $sched = is_string($pa->schedule) ? json_decode($pa->schedule, true) : $pa->schedule;
                foreach ((is_array($sched) ? $sched : []) as $s) {
                    $d = strtolower($s['day'] ?? '');
                    // Canonical slots carry start_time/end_time; older ones a single `time`.
                    $s['start_time'] = $s['start_time'] ?? $s['time'] ?? null;
                    if (! in_array($d, $cpDayOrder, true) || empty($s['start_time'])) continue;
                    $cpSlots[$d][] = [
                        'start' => \Carbon\Carbon::parse($s['start_time'])->format('g:i A'),
                        'sort' => $s['start_time'],
                        'end' => ! empty($s['end_time']) ? \Carbon\Carbon::parse($s['end_time'])->format('g:i A') : null,
                        'title' => $pa->activity?->name ?: $pkg->name,
                        'package' => $pkg->name,
                        'coach' => $pa->instructor?->user?->full_name,
                        'place' => $s['facility_name'] ?? null,
                    ];
                }
            }
        }
        foreach ($cpSlots as $d => $list) {
            usort($list, fn ($a, $b) => strcmp($a['sort'], $b['sort']));
            $cpSlots[$d] = array_values(array_unique($list, SORT_REGULAR));
        }
        $cpWeek = collect($cpDayOrder)->map(fn ($d) => ['key' => $d, 'label' => $cpDayShort($d), 'slots' => $cpSlots[$d] ?? []])->values();
        $cpToday = strtolower(now()->format('l'));
        $cpTrainingDays = $cpWeek->filter(fn ($d) => count($d['slots']))->pluck('label');

        // ---- achievements ---------------------------------------------------
        $cpAthleteIds = $achievementsAll->flatMap(fn ($a) => collect($a->athletes ?? [])->pluck('user_id'))->filter()->unique()->values();
        $cpAthleteUuid = $cpAthleteIds->isNotEmpty()
            ? \App\Members\Models\User::whereIn('id', $cpAthleteIds)->pluck('uuid', 'id')->toArray() : [];
        $cpAchievements = $achievementsAll->map(function ($a) use ($cpAthleteUuid) {
            $imgs = collect(array_filter(array_merge($a->image_path ? [$a->image_path] : [], $a->images ?? [])))->map(fn ($p) => file_url($p))->values()->all();
            return [
                'id' => $a->id,
                'title' => $a->tr('title'),
                'short' => $a->tr('short_title') ?: $a->tr('title'),
                'description' => $a->tr('description') ?? '',
                'location' => $a->tr('location') ?? '',
                'date' => $a->date_label ?: ($a->achievement_date ? $a->achievement_date->format('M Y') : ''),
                'category' => $a->category ?? '',
                'tag' => $a->tr('tag') ?? '',
                'gold' => (int) $a->medals_gold, 'silver' => (int) $a->medals_silver, 'bronze' => (int) $a->medals_bronze,
                'bouts' => (int) $a->bouts_count, 'wins' => (int) $a->wins_count,
                'chips' => array_values(array_filter((array) ($a->chips ?? []), fn ($c) => is_string($c) && $c !== '')),
                'athletes' => collect($a->athletes ?? [])->map(fn ($x) => is_array($x)
                        ? ['name' => $x['name'] ?? '', 'role' => $x['role'] ?? '', 'url' => (! empty($x['user_id']) && isset($cpAthleteUuid[$x['user_id']])) ? route('people.show', $cpAthleteUuid[$x['user_id']]) : null]
                        : ['name' => (string) $x, 'role' => '', 'url' => null])
                    ->filter(fn ($x) => $x['name'] !== '')->values()->all(),
                'image' => $imgs[0] ?? null,
                'images' => $imgs,
            ];
        })->values();

        // ---- news (the club's published posts) -------------------------------
        $cpPosts = $club->timelinePosts->where('status', 'published')->sortByDesc('posted_at')->values()->map(function ($post) use ($club, $likedPostIds) {
            return [
                'id' => $post->id,
                'category' => $post->category,
                'body' => $post->body,
                'title' => \Illuminate\Support\Str::limit(strtok(trim((string) $post->body), "\n") ?: '', 70),
                'image' => $post->image_path ? file_url($post->image_path) : null,
                'date' => $post->posted_at?->format('j M Y'),
                'ago' => $post->posted_at?->diffForHumans(),
                'liked' => in_array($post->id, $likedPostIds ?? []),
                'likes' => $post->likes->count(),
                'like_url' => auth()->check() ? route('clubs.timeline.like', [$club->country_code, $club->slug, $post->id]) : null,
                'comment_url' => auth()->check() ? route('clubs.timeline.comment', [$club->country_code, $club->slug, $post->id]) : null,
                'comments' => $post->comments->filter(fn ($c) => $c->user)->map(fn ($c) => [
                    'id' => $c->id,
                    'user_name' => $c->user->full_name ?? $c->user->name,
                    'avatar' => $c->user->profile_picture ? file_url($c->user->profile_picture) : null,
                    'body' => $c->body,
                    'time_ago' => $c->created_at->diffForHumans(),
                    'is_owner' => $c->user_id === auth()->id(),
                    'delete_url' => $c->user_id === auth()->id() ? route('clubs.timeline.comment.delete', [$club->country_code, $club->slug, $post->id, $c->id]) : null,
                ])->values()->all(),
            ];
        });

        // ---- gallery ---------------------------------------------------------
        $cpGallery = $club->galleryImages->map(fn ($g) => ['src' => file_url($g->image_path), 'caption' => $g->caption])->values();
        $cpYoutube = null;
        foreach (array_merge([$club->youtube_url], $club->socialLinks->pluck('url')->all()) as $u) {
            if ($u && preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/', $u, $m)) { $cpYoutube = $m[1]; break; }
        }

        // ---- events ----------------------------------------------------------
        $cpEvents = $club->events->filter(fn ($e) => ! $e->is_archived && ! $e->hasEnded() && $e->date)->sortBy('date')->values()->map(function ($e) use ($joinedEventIds) {
            $img = collect($e->images ?? [])->first();
            return [
                'title' => $e->title,
                'day' => $e->date->format('j'),
                'month' => $e->date->locale(app()->getLocale())->translatedFormat('M Y'),
                'date' => $e->date->format('j M Y').($e->end_date && ! $e->end_date->isSameDay($e->date) ? ' – '.$e->end_date->format('j M Y') : ''),
                'location' => $e->location,
                'level' => $e->level,
                'image' => $img ? (str_starts_with($img, 'http') ? $img : file_url($img)) : null,
                'url' => $e->uuid ? route('me.events.show', $e->uuid) : null,
                'live' => $e->isOngoing(),
                'joined' => in_array($e->id, $joinedEventIds ?? []),
                'cta' => $e->cta_text,
            ];
        });

        // ---- perks -------------------------------------------------------------
        $cpPerks = $club->perks->where('status', 'active')->values()->map(fn ($p) => [
            'title' => $p->tr('title'),
            'description' => $p->tr('description'),
            'badge' => $p->tr('badge'),
            'icon' => preg_match('/^bi-[a-z0-9-]+$/', (string) $p->icon) ? $p->icon : 'bi-gift',
            'image' => $p->image_path ? file_url($p->image_path) : null,
            'url' => route('clubs.perks.collect', [$club->country_code, $club->slug, $p->id]),
        ]);

        // ---- shop ----------------------------------------------------------------
        $cpProducts = \App\Shop\Models\ClubProduct::where('tenant_id', $club->id)->where('status', 'published')
            ->orderByDesc('featured')->orderBy('sort')->latest()->limit(12)->get()
            ->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'cat' => $p->category, 'brand' => $p->brand,
                'price' => $cpMoney($p->price), 'old' => $p->old_price !== null && (float) $p->old_price > (float) $p->price ? $cpMoney($p->old_price) : null,
                'badge' => $p->badge, 'availability' => $p->availability,
                'image' => $p->image_path ? file_url($p->image_path) : null,
                'icon' => preg_match('/^bi-[a-z0-9-]+$/', (string) $p->icon) ? $p->icon : 'bi-bag',
                'url' => route('me.market.show', $p->id),
            ]);
        $cpShopCats = $cpProducts->isEmpty() ? collect() : \App\Shop\Models\ClubProductCategory::where('tenant_id', $club->id)
            ->whereIn('key', $cpProducts->pluck('cat')->unique())->orderBy('sort')->get()->unique('key')
            ->map(fn ($c) => ['key' => $c->key, 'label' => $c->label])->values();

        // ---- reviews -------------------------------------------------------------
        $cpReviews = $reviews->filter(fn ($r) => $r->comment)->take(6)->map(fn ($r) => [
            'name' => $r->user?->full_name ?? __('clubs::page.member'),
            'rating' => (int) $r->rating,
            'comment' => $r->comment,
            'ago' => optional($r->created_at)->diffForHumans(),
        ])->values();

        // ---- good to know: answered only from what is on file --------------------
        $cpFaq = collect();
        if ($cpFrom) {
            $fee = ($club->enrollment_fee ?? 0) > 0 ? ' '.__('clubs::page.faq_price_fee', ['fee' => $cpMoney($club->enrollment_fee), 'currency' => $cpCurrency]) : '';
            $cpFaq->push(['q' => __('clubs::page.faq_price_q'), 'a' => __('clubs::page.faq_price_a', ['from' => $cpFrom, 'currency' => $cpCurrency]).$fee]);
        }
        if ($cpAddress) $cpFaq->push(['q' => __('clubs::page.faq_where_q', ['club' => $cpName]), 'a' => $cpAddress]);
        if ($cpTrainingDays->isNotEmpty()) $cpFaq->push(['q' => __('clubs::page.faq_when_q'), 'a' => __('clubs::page.faq_when_a', ['days' => $cpTrainingDays->implode(', ')])]);
        if ($cpPackages->isNotEmpty()) $cpFaq->push(['q' => __('clubs::page.faq_join_q'), 'a' => __('clubs::page.faq_join_a')]);
        if ($cpWho->isNotEmpty()) $cpFaq->push(['q' => __('clubs::page.faq_who_q'), 'a' => $cpPackages->pluck('ages')->unique()->implode(' · ')]);

        $cpQrTargets = [
            ['url' => \App\Http\Controllers\QrController::clubRegisterUrl($club), 'tab' => __('clubs::page.qr_register'), 'title' => $cpName.' — '.__('clubs::page.qr_register'),
             'caption' => __('clubs::page.qr_register_caption'), 'filename' => 'qr-'.$club->slug.'-register', 'poster' => auth()->check() ? route('qr.club.register', $club) : null],
            ['url' => \App\Http\Controllers\QrController::clubPageUrl($club), 'tab' => __('clubs::page.qr_page'), 'title' => $cpName.' — '.__('clubs::page.qr_page'),
             'caption' => __('clubs::page.qr_page_caption'), 'filename' => 'qr-'.$club->slug.'-page', 'poster' => auth()->check() ? route('qr.club.page', $club) : null],
        ];

        $cpNextLocale = $cpLocale === 'ar' ? 'en' : 'ar';

        // The day the week opens on: today, or the first day that has a class.
        $cpToday = $cpWeek->firstWhere('key', $cpToday) && count($cpWeek->firstWhere('key', $cpToday)['slots'])
            ? $cpToday : (optional($cpWeek->first(fn ($d) => count($d['slots'])))['key'] ?? $cpToday);

        // What the page's Alpine runtime (partials/page-runtime) reads.
        $cpJson = [
            'auth' => auth()->check(),
            'csrf' => csrf_token(),
            'loginUrl' => route('login'),
            'registerUrl' => \App\Http\Controllers\QrController::clubRegisterUrl($club),
            'pageUrl' => \App\Http\Controllers\QrController::clubPageUrl($club),
            'name' => $cpName,
            'today' => $cpToday,
            'achievements' => $cpAchievements,
            'posts' => $cpPosts,
            'gallery' => $cpGallery,
            'i18n' => ['copied' => __('clubs::page.link_copied'), 'error' => __('clubs::page.try_again')],
        ];

        return array_filter(get_defined_vars(), fn ($k) => str_starts_with($k, 'cp'), ARRAY_FILTER_USE_KEY);
    }
}
