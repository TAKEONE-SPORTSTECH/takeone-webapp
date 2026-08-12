@php $inShell = $inShell ?? false; @endphp
@extends($inShell ? 'layouts.personal-mobile' : 'layouts.app')

@section('hide-navbar', true)
@section('title', $user->full_name)

@php
    use Illuminate\Support\Carbon;
    $age = $user->birthdate ? Carbon::parse($user->birthdate)->age : null;
    $initials = strtoupper(mb_substr($user->full_name ?? 'M', 0, 1));
    $medalsTotal = array_sum($awardCounts);
    $latest = $latestHealthRecord;
    $prev = $comparisonRecords->count() > 1 ? $comparisonRecords[1] : null;
    // Reactive snapshots for the live-updating health summary (weight/height/BMI).
    $healthMetrics = fn ($r) => [
        'weight' => $r && !is_null($r->weight) ? (float) $r->weight : null,
        'height' => $r && !is_null($r->height) ? (float) $r->height : null,
        'bmi'    => $r && !is_null($r->bmi) ? (float) $r->bmi : null,
    ];
    $latestMetrics = $healthMetrics($latest) + [
        'label' => $latest ? optional($latest->recorded_at)->format('d M Y') : null,
        'date'  => $latest ? optional($latest->recorded_at)->format('Y-m-d') : null,
    ];
    $prevMetrics = $healthMetrics($prev);
    $weightRows = ($weightHistory ?? collect())->map(fn ($w) => [
        'weight' => (float) $w->weight,
        'label'  => optional($w->recorded_at)->format('d M Y'),
        'date'   => optional($w->recorded_at)->format('Y-m-d'),
    ])->values();
    $memberSince = $clubAffiliations->min('start_date');
    $phone = $user->mobile_formatted ?? null;

    // Nationality: resolve ISO2/ISO3 code → flag emoji + full country name.
    $natDisplay = $user->nationality ?: null;
    if ($natDisplay) {
        $countries = collect(json_decode(@file_get_contents(public_path('data/countries.json')) ?: '[]', true));
        $natCode = strtoupper($user->nationality);
        $info = $countries->first(fn($c) => strtoupper($c['iso2'] ?? '') === $natCode || strtoupper($c['iso3'] ?? '') === $natCode);
        if ($info) {
            $flag = implode('', array_map(fn($ch) => mb_chr(ord($ch) + 127397), str_split(strtoupper($info['iso2']))));
            $natDisplay = $flag . ' ' . $info['name'];
        }
    }
@endphp

@push('styles')
<style>
    /* ====== Member profile (mobile) — "Athlete Card" ====== */
    .mp-hero {
        position: relative;
        background:
            radial-gradient(120% 90% at 15% 0%, hsl(250 70% 72%) 0%, transparent 55%),
            radial-gradient(120% 90% at 95% 10%, hsl(280 65% 66%) 0%, transparent 50%),
            linear-gradient(160deg, hsl(250 65% 60%), hsl(255 60% 50%));
        overflow: hidden;
    }
    .mp-hero::after { /* subtle grain */
        content: ""; position: absolute; inset: 0; opacity: .12; pointer-events: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    }
    .mp-hero-glow { position:absolute; width:240px; height:240px; border-radius:50%; filter:blur(60px); opacity:.4; }

    .mp-avatar-ring {
        background: conic-gradient(from 210deg, #fff, hsl(250 80% 85%), #fff, hsl(280 80% 85%), #fff);
        padding: 3px; border-radius: 16px;
        box-shadow: 0 12px 30px rgba(60,20,120,.45);
    }

    /* progress ring */
    .mp-ring { position: relative; width: 60px; height: 60px; border-radius: 50%;
        background: conic-gradient(hsl(250 65% 65%) calc(var(--p) * 1%), hsl(220 14% 88%) 0);
        display: grid; place-items: center; }
    .mp-ring::before { content:""; position:absolute; width:46px; height:46px; border-radius:50%; background:#fff; }
    .mp-ring b { position: relative; font-size: 13px; font-weight: 800; color: #1f2937; }

    /* Same ring, sheet-sized — the hole scales with it or it reads as a thick donut. */
    .mp-ring.is-lg { width: 104px; height: 104px; }
    .mp-ring.is-lg::before { width: 84px; height: 84px; }
    .mp-ring.is-lg b { font-size: 22px; }

    .mp-rail { scrollbar-width: none; scroll-snap-type: x mandatory; }
    .mp-rail::-webkit-scrollbar { display: none; }
    /* Exactly 3 cards per screen (gap-3 = .75rem → two gaps = 1.5rem); swipe-snap to the next set. */
    .mp-card { flex: 0 0 calc((100% - 1.5rem) / 3); scroll-snap-align: start; }

    .mp-reveal { opacity: 0; transform: translateY(14px); animation: mpUp .6s cubic-bezier(.2,.8,.2,1) forwards; }
    @keyframes mpUp { to { opacity: 1; transform: none; } }

    .mp-tabbar { scrollbar-width: none; }
    .mp-tabbar::-webkit-scrollbar { display: none; }
    .mp-tab.is-on { color: #fff; background: hsl(250 65% 65%); box-shadow: 0 4px 12px hsla(250,65%,55%,.4); }

    .mp-medal { background: linear-gradient(145deg, var(--c1), var(--c2)); }
</style>
@endpush

@section($inShell ? 'personal-content' : 'content')
<div class="{{ $inShell ? '-mx-4 -mt-4' : 'bg-background min-h-screen pb-10' }}"
     x-data="{
        tab: (function(){
            const valid = ['overview','health','goals','tournaments','clubs','certifications','worked','attendance','challenges'];
            let h = (window.location.hash || '').replace('#','');
            if (h === 'affiliations') h = 'clubs';   // legacy deep-link alias
            return valid.includes(h) ? h : 'overview';
        })(),
        goTab(t){ this.tab = t; this.$nextTick(() => document.getElementById('mpTabs')?.scrollIntoView({behavior:'smooth', block:'start'})); },
        {{-- The metric rings answer in a sheet rather than throwing the reader down
             the page: null | 'attendance' | 'goals' | 'challenges'. --}}
        metric: null,
        metricQuery: '',
        openMetric(m){ this.metricQuery = ''; this.metric = m },
        closeMetric(){ this.metric = null },
        {{-- In-sheet search: each row carries its own lowercased haystack. --}}
        metricMatches(hay){
            const q = this.metricQuery.trim().toLowerCase();
            return ! q || (hay || '').includes(q);
        }
     }"
     x-init="$watch('tab', v => { try { history.replaceState(history.state, '', '#' + v); } catch(e) {} })">

    @unless($inShell)
    {{-- ===== Sticky glass top bar (standalone only; the shell provides its own) ===== --}}
    <div class="fixed top-0 inset-x-0 z-50 flex items-center justify-between px-4 h-14 backdrop-blur-md bg-white/10">
        <button type="button" onclick="history.length>1 ? history.back() : window.location.href='{{ url('/') }}'"
                class="w-10 h-10 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-white border border-white/30">
            <i class="bi bi-arrow-left text-lg"></i>
        </button>
        <div class="flex items-center gap-2">
            @if(Auth::user()->isSuperAdmin())
                <a href="{{ route('admin.platform.index') }}" title="{{ __('member.admin_panel') }}"
                   class="w-10 h-10 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-white border border-white/30">
                    <i class="bi bi-shield-check text-base"></i>
                </a>
            @endif
            <button type="button" onclick="navigator.share ? navigator.share({title:'{{ addslashes($user->full_name) }}', url:location.href}) : (window.showToast && window.showToast('info',@js(__('member.share_link'))+location.href))"
                    class="w-10 h-10 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-white border border-white/30">
                <i class="bi bi-share text-base"></i>
            </button>
        </div>
    </div>
    @endunless

    {{-- ===== Hero ===== --}}
    <header class="mp-hero {{ $inShell ? 'pt-6' : 'pt-20' }} pb-8 px-5 text-white text-center">
        <div class="mp-hero-glow" style="background:#fff; top:-60px; left:-40px;"></div>
        <div class="mp-hero-glow" style="background:hsl(280 80% 70%); bottom:-80px; right:-40px;"></div>

        @php
            $viewer = auth()->user();
            $isSelf = $viewer && (int) $viewer->id === (int) $user->id;
            $isFollowing = (!$isSelf && $viewer) ? $viewer->isFollowing($user->id) : false;
            // Can the viewer DM this member? (club-mates / connections / existing thread)
            $canChat = !$isSelf && $viewer && $viewer->canMessage($user);

            // ── Avatar gallery ───────────────────────────────────────────────
            // Every picture this profile already shows, gathered behind the avatar:
            // profile photo first, then awards, certificates, goal proofs, club photos.
            // Nothing new is exposed — each source is already rendered in a tab below,
            // so the audience is identical to the page itself.
            $galleryCap = 30;
            $galleryPush = function (?string $path, ?string $label, bool $absolute = false) use (&$avatarGallery, $galleryCap) {
                $path = trim((string) $path);
                if ($path === '' || count($avatarGallery) >= $galleryCap) {
                    return;
                }
                if ($absolute) {
                    // Affiliation media may hold an external URL — allow only http(s).
                    $scheme = strtolower((string) parse_url($path, PHP_URL_SCHEME));
                    $src = in_array($scheme, ['http', 'https'], true) ? $path : null;
                } else {
                    $src = asset('storage/'.$path);
                }
                if ($src) {
                    $avatarGallery[] = ['src' => $src, 'label' => $label ?: ''];
                }
            };

            $avatarGallery = [];

            // The profile's own pictures lead the viewer and are kept separate: the photo
            // sheet can add or delete them, and this list re-syncs from its event.
            $profileGallery = $user->photos
                ->sortByDesc(fn ($p) => $p->path === $user->profile_picture)
                ->map(fn ($p) => ['src' => $p->url(), 'label' => $user->full_name])
                ->values()->all();
            $currentAvatar = $profileGallery[0]['src'] ?? '';

            foreach (($awardedAchievements ?? collect()) as $ga) {
                $gaLabel = $ga->tr('short_title') ?: $ga->tr('title');
                foreach (array_merge($ga->image_path ? [$ga->image_path] : [], $ga->images ?? []) as $gaImg) {
                    $galleryPush($gaImg, $gaLabel);
                }
            }
            foreach (($certifications ?? collect()) as $gc) {
                $galleryPush($gc->image_path, $gc->title);
            }
            foreach (($goals ?? collect()) as $gg) {
                $galleryPush($gg->before_proof, $gg->title ? $gg->title.' · '.__('member.before') : null);
                $galleryPush($gg->after_proof, $gg->title ? $gg->title.' · '.__('member.after') : null);
            }
            foreach (($clubAffiliations ?? collect()) as $gaf) {
                foreach ($gaf->affiliationMedia->where('media_type', 'photo') as $gm) {
                    $galleryPush($gm->media_url, $gm->title ?: $gaf->club_name, true);
                }
            }
        @endphp
        <div x-data="memberFollow({{ $isFollowing ? 'true' : 'false' }}, @js(route('wall.follow', $user)), @js($user->full_name), @js($canChat ? route('messages.start', $user) : null))"
             class="flex items-center justify-center gap-4 mp-reveal" style="animation-delay:.05s">

            {{-- Follow (left of the profile picture) --}}
            @if(!$isSelf)
                <button type="button" @click="toggleFollow()" :disabled="busy"
                        class="m-press w-12 h-12 rounded-full backdrop-blur flex items-center justify-center text-white border border-white/30 transition-colors disabled:opacity-60"
                        :class="following ? 'bg-white/35' : 'bg-white/20'"
                        :aria-label="following ? @js(__('member.unfollow')) : @js(__('member.follow'))">
                    <i class="bi text-xl" :class="busy ? 'bi-arrow-repeat animate-spin' : (following ? 'bi-person-check-fill' : 'bi-person-plus')"></i>
                </button>
            @else
                <span class="w-12 h-12 flex-shrink-0" aria-hidden="true"></span>
            @endif

            {{-- Profile picture --}}
            <div class="relative inline-block flex-shrink-0"
                 x-data="{
                    open: false,
                    i: 0,
                    {{-- The profile's own pictures, then everything else the page shows. --}}
                    profilePhotos: @js($profileGallery),
                    otherPhotos: @js($avatarGallery),
                    avatar: @js($currentAvatar),
                    get photos() { return [...this.profilePhotos, ...this.otherPhotos] },

                    {{-- The photo sheet owns the pictures; the avatar and the viewer follow it. --}}
                    syncPhotos(list) {
                        this.profilePhotos = (list || []).map(p => ({ src: p.url, label: @js($user->full_name) }));
                        this.avatar = (list || []).find(p => p.is_avatar)?.url || '';
                        this.i = Math.min(this.i, Math.max(0, this.photos.length - 1));
                        if (! this.photos.length) this.open = false;
                    },
                    {{-- Tapping the avatar always opens on the avatar, wherever it sits in
                         the list — promoting an older picture moves it off index 0. --}}
                    showAvatar() {
                        const n = this.photos.findIndex(p => p.src === this.avatar);
                        this.show(n < 0 ? 0 : n);
                    },
                    show(n) {
                        if (! this.photos.length) return;
                        this.i = n; this.open = true;
                        {{-- The track only has a width once it is shown. --}}
                        this.$nextTick(() => this.jump(n, 'auto'));
                    },
                    jump(n, behavior) {
                        const t = this.$refs.track;
                        if (t) t.scrollTo({ left: n * t.clientWidth, behavior: behavior || 'smooth' });
                    },
                    {{-- Swipe drives the index; rounding survives RTL's negative scrollLeft. --}}
                    sync() {
                        const t = this.$refs.track;
                        if (t && t.clientWidth) this.i = Math.round(Math.abs(t.scrollLeft) / t.clientWidth);
                    },
                    step(d) {
                        const n = Math.min(this.photos.length - 1, Math.max(0, this.i + d));
                        this.i = n; this.jump(n);
                    },
                 }"
                 @keydown.escape.window="open = false"
                 @keydown.arrow-right.window="open && step(1)"
                 @keydown.arrow-left.window="open && step(-1)"
                 @profile-photos-changed.window="syncPhotos($event.detail?.photos)">
                <div class="mp-avatar-ring inline-block relative">
                    <template x-if="avatar">
                        <img id="mpAvatarImg" :src="avatar" alt="{{ $user->full_name }}"
                             class="w-28 aspect-[3/4] rounded-[13px] object-cover block cursor-pointer" @click="showAvatar()">
                    </template>
                    <template x-if="! avatar">
                        <div id="mpAvatarFallback" class="w-28 aspect-[3/4] rounded-[13px] bg-white/20 grid place-items-center text-4xl font-black"
                             :class="photos.length && 'cursor-pointer'" @click="showAvatar()">{{ $initials }}</div>
                    </template>
                    @if($canEditBasic ?? false)
                        {{-- Bare pencil inside the picture, top-right — opens the photo sheet
                             (the picture large, plus a tile to add a new one) --}}
                        <button type="button" @click.stop="$dispatch('open-profile-photo-sheet')" aria-label="{{ __('member.edit') }}"
                                class="m-press absolute top-2 right-2 rtl:right-auto rtl:left-2 p-1 text-white active:scale-90 transition-transform">
                            <i class="bi bi-pencil-fill text-sm drop-shadow-[0_1px_3px_rgba(0,0,0,.7)]"></i>
                        </button>
                    @endif
                </div>
                <span class="absolute bottom-1 right-1 w-5 h-5 rounded-full bg-green-400 border-[3px] border-white"></span>

                {{-- Picture viewer — tap the avatar to open, swipe for the rest, tap away to close.
                     Teleported to <body> so the hero's transform can't clip a fixed overlay.
                     Always rendered: an upload can fill an empty gallery without a reload. --}}
                <template x-teleport="body">
                        <div x-show="open" x-cloak class="fixed inset-0 z-[80] flex flex-col" @click="open=false">
                            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/60 backdrop-blur-md"></div>

                            {{-- Close --}}
                            <button type="button" @click.stop="open=false" aria-label="{{ __('shared.close') }}"
                                    class="absolute top-4 right-4 rtl:right-auto rtl:left-4 w-10 h-10 rounded-full bg-white/15 text-white grid place-items-center z-10 active:scale-90 transition-transform">
                                <i class="bi bi-x-lg text-lg"></i>
                            </button>

                            {{-- Counter, for galleries too long to dot --}}
                            <div x-show="photos.length > 10" class="absolute top-5 left-4 rtl:left-auto rtl:right-4 z-10 px-2.5 py-1 rounded-full bg-white/15 text-white text-xs font-semibold tabular-nums">
                                <span x-text="i + 1"></span> / <span x-text="photos.length"></span>
                            </div>

                            {{-- Swipeable track — one full-width snap slide per picture --}}
                            <div x-ref="track" @scroll.passive.debounce.60ms="sync()" @click.stop
                                 class="relative flex-1 flex overflow-x-auto overflow-y-hidden snap-x snap-mandatory mp-rail overscroll-x-contain">
                                <template x-for="(p, n) in photos" :key="n">
                                    <div class="w-full h-full flex-shrink-0 snap-center flex items-center justify-center p-6" @click="open=false">
                                        <img :src="p.src" :alt="p.label" loading="lazy" @click.stop
                                             class="max-w-full max-h-full object-contain rounded-2xl shadow-2xl">
                                    </div>
                                </template>
                            </div>

                            {{-- Caption + dots --}}
                            <div class="relative flex-shrink-0 px-6 pt-2 text-center" style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));" @click.stop>
                                <p class="text-white/90 text-sm font-medium truncate" x-text="photos[i]?.label"></p>
                                <div x-show="photos.length > 1 && photos.length <= 10" class="flex items-center justify-center gap-2 mt-3">
                                    <template x-for="(p, n) in photos" :key="n">
                                        <button type="button" @click="i = n; jump(n)" :aria-label="p.label"
                                                class="rounded-full transition-all duration-300"
                                                :class="i === n ? 'w-5 h-1.5 bg-white' : 'w-1.5 h-1.5 bg-white/40'"></button>
                                    </template>
                                </div>
                            </div>
                        </div>
                </template>
            </div>

            {{-- Right controls: share, with the chat button stacked underneath --}}
            <div class="flex flex-col items-center gap-2">
                <button type="button" @click="shareProfile()"
                        class="m-press w-12 h-12 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-white border border-white/30"
                        aria-label="{{ __('member.share_profile') }}">
                    <i class="bi bi-share text-xl"></i>
                </button>

                {{-- Direct message — opens (or starts) a 1:1 chat with this member --}}
                @if($canChat)
                    <button type="button" @click="openChat()" :disabled="chatBusy"
                            class="m-press w-12 h-12 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-white border border-white/30 disabled:opacity-60"
                            aria-label="{{ __('member.message') }}">
                        <i class="bi text-xl" :class="chatBusy ? 'bi-arrow-repeat animate-spin' : 'bi-chat-dots-fill'"></i>
                    </button>
                @endif
            </div>
        </div>

        <h1 id="mpName" class="text-2xl font-black mt-4 mp-reveal" style="animation-delay:.12s">{{ $user->full_name }}</h1>
        <p id="mpMotto" class="text-sm text-white/85 mt-1 max-w-xs mx-auto mp-reveal {{ ($user->motto || $user->bio) ? '' : 'hidden' }}" style="animation-delay:.16s">{{ ($user->motto || $user->bio) ? '“'.\Illuminate\Support\Str::limit($user->motto ?: $user->bio, 80).'”' : '' }}</p>

        {{-- Identity meta — subtle inline line that blends into the gradient
             (medals live in the showcase grid below, not here). --}}
        @php
            $meta = [];
            if ($age) {
                $meta[] = ['icon' => 'bi-calendar3', 'text' => $age . ' ' . __('member.years')];
            }
            if ($user->gender) {
                $g = strtolower($user->gender);
                $gIcon = $g === 'male' ? 'bi-gender-male' : ($g === 'female' ? 'bi-gender-female' : 'bi-gender-ambiguous');
                $meta[] = ['icon' => $gIcon, 'text' => ucfirst($user->gender)];
            }
            if ($natDisplay) {
                $meta[] = ['icon' => null, 'text' => $natDisplay];
            }
        @endphp
        @if(count($meta))
            <div class="flex flex-wrap items-center justify-center gap-x-2.5 gap-y-1 mt-1.5 mb-3 text-[13px] font-medium text-white/85 mp-reveal" style="animation-delay:.2s">
                @foreach($meta as $i => $m)
                    @if($i > 0)<span class="w-1 h-1 rounded-full bg-white/40"></span>@endif
                    <span class="inline-flex items-center gap-1.5">
                        @if($m['icon'])<i class="bi {{ $m['icon'] }} text-white/55 text-xs"></i>@endif{{ $m['text'] }}
                    </span>
                @endforeach
            </div>
        @endif
    </header>

    {{-- ===== Metric rail (overlaps hero) ===== --}}
    <div class="px-4 -mt-6 relative z-10">
        {{-- Each ring opens its own sheet with the numbers behind it — the reader stays
             where they are instead of being thrown down to a tab. --}}
        <div class="mp-rail flex gap-3 overflow-x-auto pb-1">
            {{-- attendance ring --}}
            <div role="button" tabindex="0" @click="openMetric('attendance')" @keydown.enter.space.prevent="openMetric('attendance')"
                 class="mp-card m-press cursor-pointer bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center mp-reveal" style="animation-delay:.24s">
                <div class="mp-ring" style="--p:{{ (int) $attendanceRate }}"><b>{{ (int) $attendanceRate }}%</b></div>
                <p class="text-[11px] text-muted-foreground mt-2 font-medium">{{ __('member.attendance') }}</p>
            </div>
            {{-- goals ring --}}
            <div role="button" tabindex="0" @click="openMetric('goals')" @keydown.enter.space.prevent="openMetric('goals')"
                 class="mp-card m-press cursor-pointer bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center mp-reveal" style="animation-delay:.28s">
                <div class="mp-ring" style="--p:{{ (int) $successRate }}"><b>{{ (int) $successRate }}%</b></div>
                <p class="text-[11px] text-muted-foreground mt-2 font-medium">{{ __('member.goal_success') }}</p>
            </div>
            {{-- challenge win rate --}}
            <div role="button" tabindex="0" @click="openMetric('challenges')" @keydown.enter.space.prevent="openMetric('challenges')"
                 class="mp-card m-press cursor-pointer bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center mp-reveal" style="animation-delay:.3s">
                <div class="mp-ring" style="--p:{{ (int) $challengeWinRate }}"><b>{{ (int) $challengeWinRate }}%</b></div>
                <p class="text-[11px] text-muted-foreground mt-2 font-medium">{{ __('member.challenge') }}</p>
            </div>
        </div>
    </div>

    {{-- ===== Metric sheet — one sheet, three readings of it =====
         The ring, the numbers behind it, and the actual list, scrollable, with a
         search box once the list is long enough to need one. Teleported to <body>
         so the shell's transform can't clip a fixed overlay. --}}
    @php
        // Row view-models per metric. `hay` is the lowercased haystack the in-sheet
        // search filters on — built here so the markup stays declarative.
        $mAttendanceRows = collect($scheduleSessions ?? [])->map(fn ($s) => [
            'hay' => mb_strtolower(trim(($s->title ?? '').' '.($s->coach ?? '').' '.optional($s->date)->format('d M Y'))),
            'session' => $s,
        ])->all();

        $mGoalRows = collect($goals ?? [])->map(fn ($g) => [
            'hay' => mb_strtolower(trim(($g->title ?? '').' '.($g->status ?? '').' '.($g->unit ?? ''))),
            'goal' => $g,
        ])->all();

        $mChallengeRows = collect($memberChallenges ?? [])->map(fn ($c) => [
            'hay' => mb_strtolower(trim(($c->discipline ?? '').' '.($c->rival_name ?? '').' '.($c->status ?? '').' '.($c->result ?? ''))),
            'challenge' => $c,
        ])->all();

        $metricSheets = [
            'attendance' => [
                'icon' => 'bi-calendar2-check',
                'title' => __('member.attendance'),
                'percent' => (int) $attendanceRate,
                'caption' => __('member.attendance_sheet_caption'),
                'figures' => [
                    [__('member.attended'), (int) $sessionsCompleted, 'text-green-600'],
                    [__('member.no_shows'), (int) $noShows, 'text-red-500'],
                    [__('member.total_sessions'), (int) $totalSessions, 'text-foreground'],
                ],
                'rows' => $mAttendanceRows,
                'empty' => __('member.no_schedule_sessions'),
                'emptyIcon' => 'bi-calendar-x',
                // Attendance is derived from the club's class schedule and the trainer's
                // marks — there is nothing here for the member to add by hand.
                'add' => null,
            ],
            'goals' => [
                'icon' => 'bi-flag',
                'title' => __('member.goal_success'),
                'percent' => (int) $successRate,
                'caption' => __('member.goals_sheet_caption'),
                'figures' => [
                    [__('member.completed'), (int) $completedGoalsCount, 'text-green-600'],
                    [__('member.active'), (int) $activeGoalsCount, 'text-primary'],
                    [__('member.total'), (int) ($goals?->count() ?? 0), 'text-foreground'],
                ],
                'rows' => $mGoalRows,
                'empty' => __('member.no_goals'),
                'emptyIcon' => 'bi-flag',
                'add' => ($canEditBasic ?? false)
                    ? ['label' => __('member.add_goal'), 'event' => 'open-goal-sheet']
                    : null,
            ],
            'challenges' => [
                'icon' => 'bi-lightning-charge',
                'title' => __('member.challenge'),
                'percent' => (int) $challengeWinRate,
                'caption' => __('member.challenges_sheet_caption'),
                'figures' => [
                    [__('member.won'), (int) $challengeWins, 'text-green-600'],
                    [__('member.lost'), max(0, (int) $challengesTotal - (int) $challengeWins), 'text-red-500'],
                    [__('member.total'), (int) $challengesTotal, 'text-foreground'],
                ],
                'rows' => $mChallengeRows,
                'empty' => __('member.ch_empty'),
                'emptyIcon' => 'bi-lightning-charge',
                'add' => $isSelf
                    ? ['label' => __('member.new_challenge'), 'url' => route('me.challenge.create')]
                    : null,
            ],
        ];

        // A search box is clutter on a short list — it appears once there is enough to sift.
        $metricSearchFrom = 8;
    @endphp
    <template x-teleport="body">
        <div x-show="metric" x-cloak class="fixed inset-0 z-[70] flex flex-col justify-end"
             @keydown.escape.window="closeMetric()">
            <div x-show="metric" x-transition.opacity class="absolute inset-0 bg-black/50" @click="closeMetric()"></div>

            <div x-show="metric"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="relative h-[88vh] flex flex-col bg-background rounded-t-3xl shadow-2xl overflow-hidden">

                @foreach($metricSheets as $key => $m)
                    @php $rowCount = count($m['rows']); @endphp
                    <div x-show="metric === '{{ $key }}'" class="flex flex-col min-h-0 flex-1">
                        {{-- Header --}}
                        <div class="flex-shrink-0 px-5 pt-3 pb-3 border-b border-gray-100">
                            <div class="w-10 h-1 rounded-full bg-gray-300 mx-auto mb-3"></div>
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-foreground flex items-center gap-2">
                                    <i class="bi {{ $m['icon'] }} text-primary"></i>{{ $m['title'] }}
                                </h3>
                                <button type="button" @click="closeMetric()" aria-label="{{ __('shared.close') }}"
                                        class="w-8 h-8 rounded-full grid place-items-center text-muted-foreground hover:bg-muted"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>

                        {{-- Everything below the header scrolls together: the ring, the
                             figures, then the list — so a long list has the whole sheet. --}}
                        <div class="flex-1 overflow-y-auto min-h-0 px-5 py-4 space-y-4">
                            <div class="flex flex-col items-center">
                                <div class="mp-ring is-lg" style="--p:{{ $m['percent'] }}"><b>{{ $m['percent'] }}%</b></div>
                                <p class="text-[13px] text-muted-foreground text-center mt-3 max-w-xs">{{ $m['caption'] }}</p>
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                @foreach($m['figures'] as [$label, $value, $tone])
                                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3 text-center">
                                        <p class="text-xl font-black tabular-nums leading-none {{ $tone }}">{{ $value }}</p>
                                        <p class="text-[11px] text-muted-foreground mt-1.5 leading-tight">{{ $label }}</p>
                                    </div>
                                @endforeach
                            </div>

                            @if($rowCount >= $metricSearchFrom)
                                {{-- Tiny search — only once the list is long enough to sift --}}
                                <div class="relative sticky top-0 z-10 -mx-1 px-1 py-1 bg-background">
                                    <i class="bi bi-search absolute left-4 rtl:left-auto rtl:right-4 top-1/2 -translate-y-1/2 text-muted-foreground text-xs"></i>
                                    <input type="search" x-model="metricQuery" placeholder="{{ __('member.search_in_list') }}"
                                           class="w-full ps-9 pe-9 py-2 text-sm bg-white border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <button type="button" x-show="metricQuery" @click="metricQuery = ''" aria-label="{{ __('member.clear') }}"
                                            class="absolute right-4 rtl:right-auto rtl:left-4 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground">
                                        <i class="bi bi-x-circle-fill text-xs"></i>
                                    </button>
                                </div>
                            @endif

                            @if($rowCount === 0)
                                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
                                    <i class="bi {{ $m['emptyIcon'] }} text-3xl text-gray-300"></i>
                                    <p class="text-sm text-muted-foreground mt-2">{{ $m['empty'] }}</p>
                                </div>
                            @else
                                <div class="space-y-2">
                                    @if($key === 'attendance')
                                        @php
                                            $mStatusStyles = [
                                                'attended' => ['bg-green-50 text-green-600', 'bi-check-lg', __('member.attended')],
                                                'missed' => ['bg-red-50 text-red-500', 'bi-x-lg', __('member.missed')],
                                                'upcoming' => ['bg-gray-100 text-gray-500', 'bi-clock', __('member.upcoming')],
                                            ];
                                        @endphp
                                        @foreach($m['rows'] as $row)
                                            @php
                                                $s = $row['session'];
                                                [$badgeClass, $icon, $label] = $mStatusStyles[$s->status] ?? $mStatusStyles['upcoming'];
                                            @endphp
                                            <a href="{{ $s->url }}" x-show="metricMatches(@js($row['hay']))"
                                               class="m-press bg-white rounded-xl shadow-sm border border-gray-100 p-3 flex items-center gap-3">
                                                <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 {{ $badgeClass }}"><i class="bi {{ $icon }}"></i></span>
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm font-semibold text-foreground truncate">{{ $s->title }}</p>
                                                    <p class="text-[11px] text-muted-foreground mt-0.5">{{ $s->date->format('d M Y') }} · {{ $s->start_time }}@if($s->coach) · {{ $s->coach }}@endif</p>
                                                </div>
                                                <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $badgeClass }}">{{ $label }}</span>
                                            </a>
                                        @endforeach
                                    @elseif($key === 'goals')
                                        @foreach($m['rows'] as $row)
                                            @php
                                                $g = $row['goal'];
                                                $done = $g->status === 'completed';
                                                $target = (float) ($g->target_value ?: 0);
                                                $pct = $target > 0 ? min(100, round(((float) $g->current_progress_value / $target) * 100)) : ($done ? 100 : 0);
                                            @endphp
                                            <div x-show="metricMatches(@js($row['hay']))" class="bg-white rounded-xl shadow-sm border border-gray-100 p-3">
                                                <div class="flex items-center gap-3">
                                                    <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 {{ $done ? 'bg-green-50 text-green-600' : 'bg-accent text-primary' }}">
                                                        <i class="bi {{ $done ? 'bi-check-lg' : 'bi-flag' }}"></i>
                                                    </span>
                                                    <div class="min-w-0 flex-1">
                                                        <p class="text-sm font-semibold text-foreground truncate">{{ $g->title }}</p>
                                                        <p class="text-[11px] text-muted-foreground mt-0.5">
                                                            {{ number_format((float) $g->current_progress_value, 1) }}/{{ number_format($target, 1) }}{{ $g->unit ? ' '.$g->unit : '' }}
                                                            @if($g->target_date) · {{ optional($g->target_date)->format('d M Y') }}@endif
                                                        </p>
                                                    </div>
                                                    <span class="shrink-0 text-[11px] font-bold tabular-nums {{ $done ? 'text-green-600' : 'text-primary' }}">{{ $pct }}%</span>
                                                </div>
                                                <div class="mt-2 h-1.5 rounded-full bg-muted overflow-hidden">
                                                    <span class="block h-full rounded-full {{ $done ? 'bg-green-500' : 'bg-primary' }}" style="width: {{ $pct }}%"></span>
                                                </div>
                                            </div>
                                        @endforeach
                                    @else
                                        @foreach($m['rows'] as $row)
                                            @php
                                                $ch = $row['challenge'];
                                                if ($ch->result === 'won')          [$bTone, $bText] = ['bg-green-100 text-green-700', __('member.ch_won')];
                                                elseif ($ch->result === 'lost')     [$bTone, $bText] = ['bg-red-100 text-red-600', __('member.ch_lost')];
                                                elseif ($ch->result === 'draw')     [$bTone, $bText] = ['bg-gray-100 text-gray-600', __('member.ch_draw')];
                                                elseif ($ch->status === 'active')   [$bTone, $bText] = ['bg-blue-100 text-blue-700', __('member.ch_active')];
                                                elseif ($ch->status === 'pending')  [$bTone, $bText] = ['bg-amber-100 text-amber-700', __('member.ch_pending')];
                                                elseif ($ch->status === 'reported') [$bTone, $bText] = ['bg-purple-100 text-purple-700', __('member.ch_reported')];
                                                else [$bTone, $bText] = ['bg-gray-100 text-gray-500', ucfirst($ch->status)];
                                            @endphp
                                            <div x-show="metricMatches(@js($row['hay']))" class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 flex items-center gap-3">
                                                @if($ch->rival_avatar)
                                                    <img src="{{ $ch->rival_avatar }}" alt="" class="w-9 h-9 rounded-full object-cover border border-gray-100 flex-shrink-0">
                                                @else
                                                    <x-gender-avatar :gender="$ch->rival_gender" class="w-9 h-9 rounded-full border border-gray-100 flex-shrink-0" />
                                                @endif
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm font-semibold text-foreground truncate">
                                                        <i class="bi {{ $ch->type === 'fight' ? 'bi-shield-shaded' : 'bi-lightning-charge-fill' }} text-primary mr-0.5"></i>{{ $ch->discipline }}
                                                    </p>
                                                    <p class="text-[11px] text-muted-foreground truncate">{{ __('member.ch_vs') }} {{ $ch->rival_name }} · {{ optional($ch->date)->format('d M Y') }}</p>
                                                </div>
                                                <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $bTone }}">{{ $bText }}</span>
                                            </div>
                                        @endforeach
                                    @endif

                                    {{-- Nothing left after filtering --}}
                                    @if($rowCount >= $metricSearchFrom)
                                        <p x-show="metricQuery && ! @js(collect($m['rows'])->pluck('hay')->all()).some(h => h.includes(metricQuery.trim().toLowerCase()))"
                                           class="text-sm text-muted-foreground text-center py-6">{{ __('member.no_matches') }}</p>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if($m['add'])
                            <div class="flex-shrink-0 border-t border-gray-100 bg-background px-5 pt-3"
                                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                                @if(isset($m['add']['url']))
                                    <a href="{{ $m['add']['url'] }}"
                                       class="m-press w-full bg-primary text-white py-3 rounded-xl font-semibold flex items-center justify-center gap-2 active:bg-primary/90">
                                        <i class="bi bi-plus-lg"></i>{{ $m['add']['label'] }}
                                    </a>
                                @else
                                    <button type="button" @click="closeMetric(); $dispatch('{{ $m['add']['event'] }}')"
                                            class="m-press w-full bg-primary text-white py-3 rounded-xl font-semibold flex items-center justify-center gap-2 active:bg-primary/90">
                                        <i class="bi bi-plus-lg"></i>{{ $m['add']['label'] }}
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </template>

    @php
        // Pre-build the awarded-achievement view models ONCE. Reused by the medal
        // showcase filter sheet (here) and the Tournaments tab list below, so the two
        // never drift. Each entry carries the rich detail payload + the medal "buckets"
        // (gold/silver/bronze/special) it belongs to for client-side filtering.
        $medalBuckets = function ($award) {
            $r = mb_strtolower($award ?? '');
            $b = [];
            if (str_contains($r, 'gold'))   $b[] = 'gold';
            if (str_contains($r, 'silver')) $b[] = 'silver';
            if (str_contains($r, 'bronze')) $b[] = 'bronze';
            if (str_contains($r, 'special') || empty($b)) $b[] = 'special';
            return $b;
        };
        $achList = ($awardedAchievements ?? collect())->map(function ($a) use ($medalBuckets) {
            $r = mb_strtolower($a->member_award ?? '');
            $emoji = (str_contains($r, 'gold') ? '🥇' : '') . (str_contains($r, 'silver') ? '🥈' : '') . (str_contains($r, 'bronze') ? '🥉' : '');
            $emoji = $emoji ?: '🏅';
            $dateLabel = $a->date_label ?: ($a->achievement_date ? $a->achievement_date->format('M Y') : '');
            $achLocation = $a->tr('location');
            $achImages = collect(array_filter(array_merge($a->image_path ? [$a->image_path] : [], $a->images ?? [])))
                ->map(fn ($p) => asset('storage/' . $p))->values()->toArray();
            return [
                'a'        => $a,
                'emoji'    => $emoji,
                'location' => $achLocation,
                'metaLine' => implode(' · ', array_filter([$achLocation, $dateLabel])),
                'buckets'  => $medalBuckets($a->member_award),
                'data'     => [
                    'member_award' => $a->member_award ?: __('member.award_default'),
                    'emoji'        => $emoji,
                    'title'        => $a->tr('title'),
                    'short_title'  => $a->tr('short_title') ?: $a->tr('title'),
                    'location'     => $achLocation,
                    'date_label'   => $dateLabel,
                    // Raw event date (not the record's created_at) for relative "X ago".
                    'event_date'   => $a->achievement_date ? $a->achievement_date->format('Y-m-d') : null,
                    'description'  => $a->tr('description'),
                    'club'         => $a->tenant?->tr('club_name'),
                    'type_icon'    => $a->type_icon ?: '🏆',
                    'bg_from'      => $a->bg_from ?: '#f59e0b',
                    'bg_to'        => $a->bg_to ?: '#f97316',
                    'images'       => $achImages,
                    'athletes'     => collect($a->athletes ?? [])->map(fn ($x) => is_array($x)
                                        ? ['name' => $x['name'] ?? '', 'role' => $x['role'] ?? '']
                                        : ['name' => (string) $x, 'role' => ''])
                                      ->filter(fn ($x) => $x['name'] !== '')->values()->toArray(),
                ],
            ];
        })->values();
        // Flat payload for the Alpine filter sheet: detail data + buckets.
        $medalSheetItems = $achList->map(fn ($x) => $x['data'] + ['buckets' => $x['buckets']])->all();
    @endphp

    {{-- ===== Medal showcase — always present, even at zero (counts VERIFIED medals only) ===== --}}
    {{-- mt-3 here matches the tabs' mt-3 below, so the section has equal (and tight) space above and below. --}}
    <div class="px-4 mt-3"
         x-data="{
            items: @js($medalSheetItems),
            sheetOpen: false, filterType: '', filterLabel: '', filterEmoji: '',
            showAch: false, ach: null, idx: 0,
            openMedal(type, label, emoji) { this.filterType = type; this.filterLabel = label; this.filterEmoji = emoji; this.sheetOpen = true; },
            get filtered() { return this.items.filter(i => (i.buckets || []).includes(this.filterType)); },
            openAch(a) { this.ach = a; this.idx = 0; this.showAch = true; },
            medalEmoji(r) { r = (r||'').toLowerCase(); var m=''; if(r.includes('gold'))m+='🥇'; if(r.includes('silver'))m+='🥈'; if(r.includes('bronze'))m+='🥉'; return m||'🏅'; }
         }">
        <div class="grid grid-cols-4 gap-2">
            @php
                $medals = [
                    [__('member.medal_special'), $awardCounts['special'] ?? 0, 'bi-trophy-fill', 'hsl(250 70% 70%)', 'hsl(280 70% 60%)', 'special', '🏅'],
                    [__('member.medal_gold'), $awardCounts['1st'] ?? 0, 'bi-award-fill', '#fbbf24', '#f59e0b', 'gold', '🥇'],
                    [__('member.medal_silver'), $awardCounts['2nd'] ?? 0, 'bi-award-fill', '#cbd5e1', '#94a3b8', 'silver', '🥈'],
                    [__('member.medal_bronze'), $awardCounts['3rd'] ?? 0, 'bi-award-fill', '#d6a06a', '#b45309', 'bronze', '🥉'],
                ];
            @endphp
            @foreach($medals as [$label,$cnt,$icon,$c1,$c2,$bucket,$bemoji])
                <button type="button"
                        @click="openMedal('{{ $bucket }}', @js($label), '{{ $bemoji }}')"
                        class="mp-medal m-press rounded-2xl p-3 text-center text-white shadow-sm w-full"
                        style="--c1:{{ $c1 }};--c2:{{ $c2 }}"
                        aria-label="{{ $label }} — {{ __('member.medals_awards') }}">
                    <i class="bi {{ $icon }} text-lg"></i>
                    <p class="text-lg font-black leading-none mt-1">{{ $cnt }}</p>
                    <p class="text-[10px] opacity-90">{{ $label }}</p>
                </button>
            @endforeach
        </div>

        {{-- Filtered medal list — opens as a mobile bottom-sheet when a medal is tapped --}}
        <template x-teleport="body">
            <div x-show="sheetOpen" x-cloak class="fixed inset-0 z-[65] overflow-y-auto" @keydown.escape.window="sheetOpen=false">
                <div x-show="sheetOpen" x-transition.opacity class="fixed inset-0 bg-black/60" @click="sheetOpen=false"></div>
                <div class="flex min-h-full items-end justify-center sm:items-center sm:p-4">
                    <div x-show="sheetOpen"
                         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
                         class="relative bg-white rounded-t-3xl sm:rounded-2xl shadow-xl w-full sm:max-w-lg flex flex-col" style="max-height:88vh" @click.stop>
                        {{-- Header --}}
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 flex-shrink-0">
                            <h5 class="text-base font-bold text-foreground flex items-center gap-2 min-w-0">
                                <span class="text-xl leading-none" x-text="filterEmoji"></span>
                                <span class="truncate" x-text="filterLabel"></span>
                                <span class="text-[11px] font-semibold text-muted-foreground bg-muted rounded-full px-2 py-0.5 flex-shrink-0" x-text="filtered.length"></span>
                            </h5>
                            <button type="button" @click="sheetOpen=false" class="w-9 h-9 rounded-full bg-muted text-gray-500 grid place-items-center flex-shrink-0"><i class="bi bi-x-lg"></i></button>
                        </div>
                        {{-- List --}}
                        <div class="overflow-y-auto p-3 space-y-2.5" style="max-height:calc(88vh - 4rem)">
                            <template x-for="(item, i) in filtered" :key="i">
                                <button type="button" @click="openAch(item)" class="m-card m-press p-2.5 flex items-center gap-3 w-full text-start">
                                    <span class="w-11 h-11 rounded-full bg-amber-50 grid place-items-center text-xl flex-shrink-0" x-text="item.emoji"></span>
                                    <div class="min-w-0 flex-1">
                                        {{-- Award + relative time (since the event date) on one row --}}
                                        <div class="flex items-center gap-2">
                                            <p class="font-bold text-foreground text-sm leading-tight truncate flex-1" x-text="item.member_award"></p>
                                            <span x-show="window.memberTimeAgo(item.event_date)" class="flex-shrink-0 inline-flex items-center gap-0.5 text-[9px] font-semibold text-primary/80 bg-primary/10 rounded-full px-1.5 py-0.5 whitespace-nowrap"><i class="bi bi-clock-history"></i><span x-text="window.memberTimeAgo(item.event_date)"></span></span>
                                        </div>
                                        <p class="text-[11px] text-muted-foreground truncate mt-0.5"><i class="bi bi-trophy text-amber-400 mr-0.5"></i><span x-text="item.short_title"></span></p>
                                        {{-- Location · date · club condensed into one line --}}
                                        <p x-show="item.location || item.date_label || item.club" class="text-[10px] text-muted-foreground/70 truncate mt-0.5" x-text="[item.location, item.date_label, item.club].filter(Boolean).join(' · ')"></p>
                                    </div>
                                    <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/40 text-sm flex-shrink-0"></i>
                                </button>
                            </template>
                            <div x-show="!filtered.length" class="p-10 text-center">
                                <i class="bi bi-award text-3xl text-gray-300"></i>
                                <p class="text-sm text-muted-foreground mt-2">{{ __('member.medal_none') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- Shared achievement detail sheet (opened from a list row) --}}
        @include('components-templates.member.mobile.partials.achievement-detail-sheet')

        {{-- Self-reported medals are visible on the profile but not counted as verified above --}}
        @php $mSelfReported = array_sum($selfReportedCounts ?? []); @endphp
        @if($mSelfReported > 0)
            <p class="text-[11px] text-muted-foreground/70 flex items-center gap-1 mt-2"><i class="bi bi-person-badge"></i>{{ __('+:count self-reported awaiting verification', ['count' => $mSelfReported]) }}</p>
        @endif
    </div>

    {{-- ===== Sticky tabs ===== --}}
    {{-- mt-1 (4px) + py-2 (8px) ≈ 12px, so the gap below the medal boxes matches the mt-3 above them. --}}
    <div id="mpTabs" class="sticky top-14 z-30 bg-background/95 backdrop-blur mt-1 py-2">
        <div class="mp-tabbar flex gap-1.5 px-4 overflow-x-auto">
            @php
                // 'goals' is intentionally omitted from the tab bar — the overview
                // "Goal success" stat card opens it via goTab('goals').
                $mpTabs = [
                    'overview'=>__('member.tab_overview'),'health'=>__('member.tab_health'),
                    'tournaments'=>__('member.tab_tournaments'),'clubs'=>__('member.tab_clubs'),
                    'certifications'=>__('member.tab_certifications'),'worked'=>__('member.tab_worked'),
                ];
            @endphp
            @foreach($mpTabs as $key=>$label)
                <button @click="tab='{{ $key }}'"
                        class="mp-tab flex-shrink-0 whitespace-nowrap px-4 py-2 rounded-full text-[13px] font-semibold text-center text-muted-foreground bg-white border border-gray-100 transition-all"
                        :class="tab==='{{ $key }}' && 'is-on'">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <div class="px-4 mt-3 space-y-3">

        {{-- ===== Overview ===== --}}
        <div x-show="tab==='overview'" x-transition.opacity class="space-y-3">

            @if($canRegeneratePassword ?? false)
            {{-- ===== Super-admin password controls — reset (any account) or
                 auto-generate a new one (shown + emailed to the member). ===== --}}
            <div x-data="memberPwdAdmin('{{ route('member.reset-password', $user->id) }}', '{{ route('member.regenerate-password', $user->id) }}', @js($user->full_name))"
                 class="bg-white rounded-2xl shadow-sm border border-amber-200 p-4 mp-reveal" style="animation-delay:.2s">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-9 h-9 rounded-xl bg-amber-50 text-amber-500 grid place-items-center flex-shrink-0">
                        <i class="bi bi-shield-lock-fill text-lg"></i>
                    </span>
                    <div class="min-w-0">
                        <h3 class="font-bold text-foreground leading-tight">{{ __('member.account_security') }}</h3>
                        <p class="text-[11px] text-muted-foreground">{{ __('member.super_admin_only') }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2 mt-3">
                    <button type="button" @click="openSet()"
                            class="m-press flex items-center justify-center gap-2 py-2.5 rounded-xl bg-muted text-foreground text-sm font-semibold active:bg-muted/70 transition-colors">
                        <i class="bi bi-key"></i> {{ __('member.set') }}
                    </button>
                    <button type="button" @click="generate()" :disabled="busy"
                            class="m-press flex items-center justify-center gap-2 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold active:bg-primary/90 transition-colors disabled:opacity-60">
                        <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-magic'"></i> {{ __('member.generate') }}
                    </button>
                </div>

                {{-- Manual "set password" bottom sheet — teleported to body so it isn't trapped by the card's transform/animation --}}
                <template x-teleport="body">
                <div x-show="setOpen" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center" @keydown.escape.window="setOpen=false">
                    <div class="absolute inset-0 bg-black/50" @click="setOpen=false"
                         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>
                    <div class="relative w-full max-w-lg bg-white rounded-t-3xl p-5 pb-8"
                         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
                        <div class="w-10 h-1 rounded-full bg-gray-200 mx-auto mb-4"></div>
                        <h3 class="font-bold text-lg text-foreground flex items-center gap-2"><i class="bi bi-key-fill text-amber-500"></i> {{ __('member.set_password') }}</h3>
                        <p class="text-sm text-muted-foreground mt-1 mb-4" x-text="@js(__('member.set_password_for')).replace(':name', name)"></p>
                        <div class="space-y-3">
                            <input type="password" x-model="pw1" placeholder="{{ __('member.new_password') }}" minlength="8"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40">
                            <input type="password" x-model="pw2" placeholder="{{ __('member.confirm_password') }}" minlength="8"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40">
                        </div>
                        <div class="flex gap-2 mt-5">
                            <button type="button" @click="setOpen=false" class="m-press flex-1 py-3 rounded-xl bg-muted text-foreground text-sm font-semibold">{{ __('shared.cancel') }}</button>
                            <button type="button" @click="submitSet()" :disabled="busy" class="m-press flex-1 py-3 rounded-xl bg-primary text-white text-sm font-semibold active:bg-primary/90 disabled:opacity-60">
                                <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i> {{ __('member.set_password') }}
                            </button>
                        </div>
                    </div>
                </div>
                </template>

                {{-- Generated-password result sheet (shows the new password once) — teleported to body --}}
                <template x-teleport="body">
                <div x-show="resultOpen" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center" @keydown.escape.window="resultOpen=false">
                    <div class="absolute inset-0 bg-black/50" @click="resultOpen=false"
                         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>
                    <div class="relative w-full max-w-lg bg-white rounded-t-3xl p-5 pb-8 text-center"
                         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                        <div class="w-10 h-1 rounded-full bg-gray-200 mx-auto mb-4"></div>
                        <div class="w-14 h-14 rounded-2xl bg-green-50 text-green-600 grid place-items-center mx-auto"><i class="bi bi-check-circle-fill text-2xl"></i></div>
                        <h3 class="font-bold text-lg text-foreground mt-3">{{ __('member.password_generated') }}</h3>
                        <p class="text-sm text-muted-foreground mt-1" x-show="emailed">{{ __('member.password_emailed') }}</p>
                        <p class="text-sm text-amber-600 mt-1" x-show="!emailed">{{ __('member.password_not_emailed') }}</p>
                        <button type="button" @click="copy()"
                                class="m-press w-full mt-4 flex items-center justify-between gap-2 px-4 py-3 rounded-xl bg-muted border border-dashed border-primary/40">
                            <span class="font-mono font-bold text-base text-foreground tracking-wider select-all" x-text="newPw"></span>
                            <i class="bi" :class="copied ? 'bi-clipboard-check text-green-600' : 'bi-clipboard text-primary'"></i>
                        </button>
                        <button type="button" @click="resultOpen=false" class="m-press w-full mt-4 py-3 rounded-xl bg-primary text-white text-sm font-semibold active:bg-primary/90">{{ __('shared.done') }}</button>
                    </div>
                </div>
                </template>
            </div>
            @endif

            <div>
                {{-- Header always outside the card (matches Work history / Active clubs) --}}
                <div class="flex items-center justify-between gap-2 mb-2">
                    <h3 class="font-bold text-foreground flex items-center gap-2 text-[15px]"><i class="bi bi-person-vcard text-primary"></i>{{ __('member.personal') }}</h3>
                    @if($canEditBasic ?? false)
                        <button type="button" @click="$dispatch('open-profile-modal')"
                                class="m-press inline-flex items-center gap-1.5 text-primary text-sm font-semibold flex-shrink-0">
                            <i class="bi bi-pencil-square"></i> {{ __('member.edit') }}
                        </button>
                    @endif
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                {{-- Age, gender & nationality live in the hero meta — not repeated here. --}}
                @php
                    // Marital status → icon + colour (matches the profile modal's dropdown).
                    $maritalIcons = [
                        'single'   => ['bi-person',     'text-blue-500'],
                        'married'  => ['bi-heart-fill', 'text-pink-500'],
                        'divorced' => ['bi-heart-half', 'text-orange-500'],
                        'widowed'  => ['bi-flower1',    'text-purple-500'],
                    ];
                    $mi = $maritalIcons[strtolower($user->marital_status ?? '')] ?? null;
                @endphp
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div><p class="text-[11px] text-muted-foreground">{{ __('member.blood_type') }}</p><p class="font-semibold flex items-center gap-1.5">@if($user->blood_type)<i class="bi bi-droplet-fill text-red-500 text-xs"></i>@endif{{ $user->blood_type ?: '—' }}</p></div>
                    <div><p class="text-[11px] text-muted-foreground">{{ __('member.marital_status') }}</p><p class="font-semibold capitalize flex items-center gap-1.5">@if($mi)<i class="bi {{ $mi[0] }} {{ $mi[1] }} text-xs"></i>@endif{{ $user->marital_status ?: '—' }}</p></div>
                    @if($user->horoscope)
                        @php $zodiac = ['Aries'=>'♈','Taurus'=>'♉','Gemini'=>'♊','Cancer'=>'♋','Leo'=>'♌','Virgo'=>'♍','Libra'=>'♎','Scorpio'=>'♏','Sagittarius'=>'♐','Capricorn'=>'♑','Aquarius'=>'♒','Pisces'=>'♓']; @endphp
                        <div><p class="text-[11px] text-muted-foreground">{{ __('member.horoscope') }}</p><p class="font-semibold">{{ $zodiac[$user->horoscope] ?? '' }} {{ $user->horoscope }}</p></div>
                    @endif
                    @if($memberSince)<div><p class="text-[11px] text-muted-foreground">{{ __('member.member_since') }}</p><p class="font-semibold flex items-center gap-1.5"><i class="bi bi-calendar3 text-primary text-xs"></i>{{ Carbon::parse($memberSince)->format('M Y') }}</p></div>@endif
                    <div class="col-span-2">
                        <p class="text-[11px] text-muted-foreground">{{ __('member.skills_learned') }}</p>
                        @if(($skillSummary ?? collect())->count())
                            <div class="flex flex-wrap gap-1.5 mt-1">
                                @foreach($skillSummary as $sk)
                                    @if($sk['uuid'])
                                        <a href="{{ route('activity.show', $sk['uuid']) }}"
                                           class="inline-flex items-center gap-1.5 ps-2.5 pe-2 py-1 rounded-full text-[11px] font-medium bg-accent text-primary no-underline m-press hover:bg-primary/15 transition-colors">
                                            <i class="bi bi-book-half text-[10px]"></i>
                                            <span>{{ $sk['name'] }}</span>
                                            @if($sk['years'])<span class="inline-flex items-center gap-0.5 text-[10px] font-semibold text-primary/70"><i class="bi bi-hourglass-split"></i>{{ $sk['years'] }}</span>@endif
                                            <i class="bi bi-chevron-right text-[9px] text-primary/50"></i>
                                        </a>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium bg-accent text-primary">
                                            <span>{{ $sk['name'] }}</span>
                                            @if($sk['years'])<span class="inline-flex items-center gap-0.5 text-[10px] font-semibold text-primary/70"><i class="bi bi-hourglass-split"></i>{{ $sk['years'] }}</span>@endif
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <p class="font-semibold">—</p>
                        @endif
                    </div>
                </div>
                </div>
            </div>
            @if($user->email || $phone)
            <div>
                {{-- Header always outside the card --}}
                <div class="flex items-center justify-between gap-2 mb-2">
                    <h3 class="font-bold text-foreground flex items-center gap-2 text-[15px]"><i class="bi bi-telephone text-primary"></i>{{ __('member.contact') }}</h3>
                </div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 space-y-2.5">
                @if($user->email)<a href="mailto:{{ $user->email }}" class="flex items-center gap-3 text-sm"><span class="w-8 h-8 rounded-lg bg-accent grid place-items-center text-primary"><i class="bi bi-envelope"></i></span><span class="truncate">{{ $user->email }}</span></a>@endif
                @if($phone)<a href="tel:{{ $phone }}" class="flex items-center gap-3 text-sm"><span class="w-8 h-8 rounded-lg bg-accent grid place-items-center text-primary"><i class="bi bi-phone"></i></span><span dir="ltr">{{ $phone }}</span></a>@endif
                </div>
            </div>
            @endif

            {{-- Social links --}}
            @php
                $socialIcons = ['facebook'=>'bi-facebook','instagram'=>'bi-instagram','linkedin'=>'bi-linkedin','youtube'=>'bi-youtube','tiktok'=>'bi-tiktok','twitter'=>'bi-twitter-x','x'=>'bi-twitter-x','snapchat'=>'bi-snapchat','whatsapp'=>'bi-whatsapp','telegram'=>'bi-telegram','website'=>'bi-globe'];
                // Allowlist URL schemes so a stored javascript:/data: URI can't execute on click.
                $safeUrl = function ($u) {
                    $u = trim((string) $u);
                    $scheme = strtolower((string) parse_url($u, PHP_URL_SCHEME));
                    return in_array($scheme, ['http', 'https'], true) ? $u : '#';
                };
                $socials = collect($user->social_links ?? [])->filter(fn($u) => !empty($u));
            @endphp
            @if($socials->count())
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <h3 class="font-bold text-foreground mb-3 flex items-center gap-2"><i class="bi bi-share text-primary"></i> {{ __('member.social') }}</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($socials as $platform => $url)
                        <a href="{{ $safeUrl($url) }}" target="_blank" rel="noopener noreferrer" class="w-10 h-10 rounded-xl bg-accent grid place-items-center text-primary text-lg"><i class="bi {{ $socialIcons[strtolower($platform)] ?? 'bi-link-45deg' }}"></i></a>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Emergency contacts — private; hidden from viewers without an active tie --}}
            @if(($canViewSensitive ?? false) && !empty($user->emergency_contacts))
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 space-y-3">
                <h3 class="font-bold text-foreground flex items-center gap-2"><i class="bi bi-telephone-plus text-primary"></i> {{ __('member.emergency_contacts') }}</h3>
                @foreach($user->emergency_contacts as $contact)
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-lg bg-accent grid place-items-center text-primary flex-shrink-0"><i class="bi bi-person"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm truncate">{{ $contact['name'] ?? '—' }}</p>
                            <p class="text-[11px] text-muted-foreground capitalize">{{ $contact['relationship'] ?? '' }}</p>
                        </div>
                        @php $cp = trim(($contact['phone_code'] ?? '').' '.($contact['phone'] ?? '')); @endphp
                        @if($cp)<a href="tel:{{ str_replace(' ', '', $cp) }}" dir="ltr" class="text-primary text-sm font-semibold whitespace-nowrap inline-block">{{ $cp }}</a>@endif
                    </div>
                @endforeach
            </div>
            @endif

            {{-- Identity documents --}}
            @if(!empty($user->documents))
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 space-y-2">
                <h3 class="font-bold text-foreground mb-1 flex items-center gap-2"><i class="bi bi-file-earmark-text text-primary"></i> {{ __('member.documents') }}</h3>
                @foreach($user->documents as $doc)
                    <a href="{{ !empty($doc['file_path']) ? asset('storage/'.$doc['file_path']) : '#' }}" target="_blank" rel="noopener" class="flex items-center gap-3 text-sm">
                        <span class="w-9 h-9 rounded-lg bg-accent grid place-items-center text-primary flex-shrink-0"><i class="bi bi-file-earmark-arrow-down"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold truncate">{{ $doc['type'] ?? __('member.document') }}</p>
                            @if(!empty($doc['number']))<p class="text-[11px] text-muted-foreground truncate">{{ $doc['number'] }}</p>@endif
                        </div>
                    </a>
                @endforeach
            </div>
            @endif
        </div>

        {{-- ===== Health ===== --}}
        <div x-show="tab==='health'" x-transition.opacity x-cloak class="space-y-3"
             x-data="weightLogger({ url: '{{ route('member.store-health', $user->id) }}', csrf: '{{ csrf_token() }}', today: '{{ now()->format('Y-m-d') }}', rows: @js($weightRows), latest: @js($latestMetrics), prev: @js($prevMetrics), gender: @js(strtolower($user->gender ?? '')), age: {{ (int) ($age ?? 0) }}, divisions: @js(config('taekwondo_divisions', [])), i18n: { upTo: @js(__('member.wc_up_to')), over: @js(__('member.wc_over')), range: @js(__('member.wc_range')), headroom: @js(__('member.wc_headroom')) }, timeAgo: { tpl: @js(__('member.time_ago_tpl')), today: @js(__('member.time_ago_today')), yr: @js(__('member.unit_yr')), yrs: @js(__('member.unit_yrs')), mo: @js(__('member.unit_mo')), mos: @js(__('member.unit_mos')), day: @js(__('member.unit_day')), days: @js(__('member.unit_days')) } })">
            @if(!empty($user->health_conditions))
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 space-y-2.5">
                <h3 class="font-bold text-foreground flex items-center gap-2"><i class="bi bi-clipboard2-pulse text-primary"></i> {{ __('member.chronic_conditions') }}</h3>
                @foreach($user->health_conditions as $cond)
                    <div class="flex items-start gap-3">
                        <span class="w-2 h-2 rounded-full bg-red-400 mt-1.5 flex-shrink-0"></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm">{{ $cond['condition'] ?? '' }}</p>
                            @if(!empty($cond['notes']))<p class="text-[11px] text-muted-foreground">{{ $cond['notes'] }}</p>@endif
                        </div>
                    </div>
                @endforeach
            </div>
            @endif
            {{-- Section header — above the metric cards it summarises (shown once there are readings) --}}
            <div class="flex items-center justify-between gap-2" x-show="rows.length" x-cloak>
                <h3 class="font-bold text-foreground flex items-center gap-2 text-[15px]"><i class="bi bi-graph-up-arrow text-primary"></i>{{ __('member.weight_history') }}</h3>
                @if($canEditBasic)
                    <button type="button" @click="openAdd()" class="m-press inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary text-white text-xs font-bold shadow-sm shadow-primary/25 hover:bg-primary/90 transition-colors flex-shrink-0">
                        <i class="bi bi-plus-lg"></i>{{ __('member.add_weight') }}
                    </button>
                @endif
            </div>

            @if($latest)
                @php
                    // Primary trio shown in one row, then any extra metrics below.
                    $primary = [
                        // All three are "live": they re-read from the reactive `latest` snapshot
                        // so a freshly logged reading updates them in place without a reload.
                        [__('member.metric_weight'), $latest->weight, 'kg', 'bi-speedometer', $prev->weight ?? null, 'weight'],
                        [__('member.metric_height'), $latest->height, 'cm', 'bi-rulers', $prev->height ?? null, 'height'],
                        [__('member.metric_bmi'), $latest->bmi, '', 'bi-heart-pulse', $prev->bmi ?? null, 'bmi'],
                    ];
                    $secondary = [
                        [__('member.metric_body_fat'), $latest->body_fat_percentage, '%', 'bi-droplet-half', $prev->body_fat_percentage ?? null],
                        [__('member.metric_muscle'), $latest->muscle_mass, 'kg', 'bi-activity', $prev->muscle_mass ?? null],
                        [__('member.metric_body_age'), $latest->body_age, 'yrs', 'bi-hourglass', $prev->body_age ?? null],
                    ];
                @endphp

                {{-- Primary trio — one tidy row of three --}}
                <div class="grid grid-cols-3 gap-2">
                    @foreach($primary as [$label,$val,$unit,$icon,$old,$key])
                        <div class="relative bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col overflow-hidden">
                            <span class="absolute -top-4 -right-4 rtl:right-auto rtl:-left-4 w-16 h-16 rounded-full bg-accent/40 pointer-events-none"></span>
                            <div class="relative flex items-start justify-between">
                                <span class="w-9 h-9 rounded-xl bg-accent grid place-items-center text-primary flex-shrink-0"><i class="bi {{ $icon }} text-base"></i></span>
                                {{-- Live trend vs the previous reading --}}
                                <template x-if="trendOf('{{ $key }}') !== null && trendOf('{{ $key }}') !== 0">
                                    <span class="inline-flex items-center gap-0.5 text-[10px] font-bold px-1.5 py-0.5 rounded-full" :class="trendOf('{{ $key }}') > 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-500'">
                                        <i class="bi text-xs leading-none" :class="trendOf('{{ $key }}') > 0 ? 'bi-arrow-up-short' : 'bi-arrow-down-short'"></i><span x-text="Math.abs(trendOf('{{ $key }}')).toFixed(1)"></span>
                                    </span>
                                </template>
                            </div>
                            <p class="relative mt-2.5 flex items-baseline gap-0.5 min-w-0">
                                <span class="text-xl font-black text-foreground leading-none tabular-nums truncate" x-text="fmt(latest.{{ $key }})">{{ !is_null($val) ? number_format((float) $val, 1) : '—' }}</span>
                                @if($unit)<span class="text-[11px] font-semibold text-muted-foreground flex-shrink-0">{{ $unit }}</span>@endif
                            </p>
                            <p class="relative text-[11px] text-muted-foreground mt-1 font-medium leading-tight">{{ $label }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- Extra metrics (only the ones that exist) --}}
                @php $secondaryShown = collect($secondary)->filter(fn($m) => !is_null($m[1])); @endphp
                @if($secondaryShown->isNotEmpty())
                    <div class="grid grid-cols-3 gap-2">
                        @foreach($secondaryShown as [$label,$val,$unit,$icon,$old])
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3 text-center">
                                <i class="bi {{ $icon }} text-primary"></i>
                                <p class="text-base font-black mt-0.5 leading-none truncate max-w-full">{{ number_format((float) $val, 1) }}</p>
                                @if($unit)<p class="text-[9px] font-semibold text-muted-foreground leading-none mt-0.5">{{ $unit }}</p>@endif
                                <p class="text-[10px] text-muted-foreground mt-1">{{ $label }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
                <p class="text-[11px] text-muted-foreground text-center">{{ __('member.last_recorded') }} <span x-text="latest.label || @js(optional($latest->recorded_at)->format('d M Y'))">{{ optional($latest->recorded_at)->format('d M Y') }}</span><span x-show="ago()" class="text-muted-foreground/70"> · <span x-text="ago()"></span></span></p>
            @endif

            {{-- ===== Weight tracking (Work-History-style layout: bare header + standalone cards) ===== --}}

            {{-- Taekwondo weight-class card — reflects the latest weight, updates live --}}
            <template x-if="classify(latest.weight)">
                <div class="rounded-2xl border border-primary/20 bg-gradient-to-br from-primary/5 to-accent/40 p-3.5 overflow-hidden relative">
                    <span class="absolute -top-5 -right-5 rtl:right-auto rtl:-left-5 w-20 h-20 rounded-full bg-primary/10 pointer-events-none"></span>
                    <div class="relative flex items-center gap-3">
                        <span class="w-11 h-11 rounded-xl bg-primary/15 text-primary grid place-items-center flex-shrink-0"><i class="bi bi-trophy-fill text-lg"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-primary/70">{{ __('member.weight_class_title') }}</p>
                            <p class="font-black text-foreground text-lg leading-tight flex items-center gap-1.5">
                                <span x-text="classify(latest.weight).name || (classify(latest.weight).label + ' kg')"></span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/15 text-primary" x-text="classify(latest.weight).age_group"></span>
                            </p>
                            <p class="text-[11px] text-muted-foreground mt-0.5">
                                <span x-show="classify(latest.weight).name" class="font-semibold text-foreground/70"><span x-text="classify(latest.weight).label + ' kg'"></span> · </span><span x-text="classRange(classify(latest.weight))"></span><span x-show="gender"> · </span><span class="capitalize" x-text="gender"></span>
                            </p>
                        </div>
                    </div>
                    <template x-if="classHeadroom(latest.weight, classify(latest.weight)) !== null && classHeadroom(latest.weight, classify(latest.weight)) >= 0">
                        <p class="relative mt-2.5 pt-2.5 border-t border-primary/10 text-[11px] text-foreground/80 flex items-center gap-1.5">
                            <i class="bi bi-rulers text-primary/60"></i>
                            <span x-text="i18n.headroom.replace(':kg', classHeadroom(latest.weight, classify(latest.weight)).toFixed(1)).replace(':label', classify(latest.weight).label)"></span>
                        </p>
                    </template>
                </div>
            </template>

            {{-- Each reading as its own card (matches Work History) --}}
            <template x-for="(row,i) in rows" :key="row.date + '-' + i">
                <div class="group relative bg-white rounded-2xl shadow-sm border border-gray-100 p-4 overflow-hidden">
                    <span class="absolute inset-y-0 left-0 rtl:left-auto rtl:right-0 w-1"
                          :class="delta(i) === null ? 'bg-primary/40' : (delta(i) < 0 ? 'bg-green-400/80' : (delta(i) > 0 ? 'bg-red-300' : 'bg-gray-300'))"></span>
                    <div class="flex items-center gap-3">
                        <span class="w-12 h-12 rounded-xl bg-accent grid place-items-center text-primary flex-shrink-0 ring-1 ring-primary/10"><i class="bi bi-speedometer2 text-lg"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 flex-wrap leading-none">
                                <span class="text-lg font-black text-foreground tabular-nums" x-text="Number(row.weight).toFixed(1)"></span>
                                <span class="text-[11px] font-semibold text-muted-foreground">kg</span>
                                {{-- Taekwondo division at that weight --}}
                                <template x-if="classify(row.weight)">
                                    <span class="inline-flex items-center text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-primary/10 text-primary" x-text="classify(row.weight).label"></span>
                                </template>
                            </p>
                            <p class="text-[11px] text-muted-foreground mt-1"><span x-text="row.label"></span><span x-show="ago(row.date)" class="text-muted-foreground/70"> · <span x-text="ago(row.date)"></span></span></p>
                        </div>
                        {{-- Δ vs the previous (older) reading --}}
                        <template x-if="delta(i) !== null">
                            <span class="inline-flex items-center gap-0.5 text-[11px] font-bold px-2 py-0.5 rounded-full flex-shrink-0"
                                  :class="delta(i) === 0 ? 'bg-gray-100 text-gray-500' : (delta(i) < 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-500')">
                                <i class="bi" :class="delta(i) === 0 ? 'bi-dash' : (delta(i) < 0 ? 'bi-arrow-down-short' : 'bi-arrow-up-short')"></i><span x-text="Math.abs(delta(i)).toFixed(1) + ' kg'"></span>
                            </span>
                        </template>
                        <template x-if="delta(i) === null">
                            <span class="text-[9px] font-semibold text-muted-foreground/70 px-2 flex-shrink-0">{{ __('member.first_reading') }}</span>
                        </template>
                    </div>
                </div>
            </template>

            {{-- Empty state --}}
            <template x-if="!rows.length">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-bold text-foreground flex items-center gap-2"><i class="bi bi-graph-up-arrow text-primary"></i> {{ __('member.weight_history') }}</h3>
                        @if($canEditBasic)
                            <button type="button" @click="openAdd()" class="m-press inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary text-white text-xs font-bold active:bg-primary/90">
                                <i class="bi bi-plus-lg"></i>{{ __('member.add_weight') }}
                            </button>
                        @endif
                    </div>
                    <p class="text-sm text-muted-foreground text-center py-4">{{ __('member.no_weight_records') }}</p>
                </div>
            </template>

            {{-- Add-weight bottom sheet (teleported to body) --}}
            <template x-teleport="body">
                <div x-show="addOpen" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center" @keydown.escape.window="addOpen=false">
                    <div class="absolute inset-0 bg-black/50" @click="addOpen=false" x-transition.opacity></div>
                    <div class="relative w-full max-w-lg bg-white rounded-t-3xl p-5 pb-8"
                         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
                        <div class="w-10 h-1 rounded-full bg-gray-200 mx-auto mb-4"></div>
                        <h3 class="font-bold text-lg text-foreground flex items-center gap-2"><i class="bi bi-speedometer text-primary"></i> {{ __('member.log_weight') }}</h3>
                        <div class="space-y-3 mt-4">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.metric_weight') }}</label>
                                    <div class="relative">
                                        <input type="number" x-model="weight" step="0.1" min="0" max="999.9" inputmode="decimal" dir="ltr"
                                               class="w-full px-3 py-2.5 pr-10 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40" placeholder="70.5">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-muted-foreground pointer-events-none">kg</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.metric_height') }}</label>
                                    <div class="relative">
                                        <input type="number" x-model="height" step="0.1" min="50" max="250" inputmode="decimal" dir="ltr"
                                               class="w-full px-3 py-2.5 pr-10 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40" placeholder="175">
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-muted-foreground pointer-events-none">cm</span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-[11px] text-muted-foreground flex items-center gap-1"><i class="bi bi-info-circle"></i> {{ __('member.bmi_auto_note') }}</p>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.reading_date') }}</label>
                                <input type="date" x-model="date" :max="today" dir="ltr"
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40">
                            </div>
                        </div>
                        <div class="flex gap-2 mt-5">
                            <button type="button" @click="addOpen=false" class="m-press flex-1 py-3 rounded-xl bg-muted text-foreground text-sm font-semibold">{{ __('shared.cancel') }}</button>
                            <button type="button" @click="save()" :disabled="busy || !weight" class="m-press flex-1 py-3 rounded-xl bg-primary text-white text-sm font-semibold active:bg-primary/90 disabled:opacity-60">
                                <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i> {{ __('member.save') }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- ===== Goals ===== --}}
        @php
            $goalsJs = $goals->map(fn ($g) => [
                'id' => $g->id,
                'title' => $g->title,
                'description' => $g->description,
                'unit' => $g->unit,
                'target_value' => (float) $g->target_value,
                'current_progress_value' => (float) $g->current_progress_value,
                'status' => $g->status,
                'target_date' => optional($g->target_date)->format('M j, Y'),
                'before_proof' => $g->before_proof ? asset('storage/'.$g->before_proof) : null,
                'after_proof' => $g->after_proof ? asset('storage/'.$g->after_proof) : null,
                'completed_at' => optional($g->completed_at)->format('M j, Y'),
                'days_taken' => $g->days_taken,
            ])->values();
        @endphp
        <div x-show="tab==='goals'" x-transition.opacity x-cloak class="space-y-3"
             x-data="goalsManager({
                storeUrl: '{{ route('member.store-goal', $user->id) }}',
                updateUrlBase: '{{ url('/member/goal') }}',
                csrf: '{{ csrf_token() }}',
                today: '{{ now()->format('Y-m-d') }}',
                goals: @js($goalsJs),
                canEdit: @js((bool) ($canEditBasic ?? false)),
                i18n: {
                    pickDate: @js(__('member.pick_a_date')), clear: @js(__('member.clear')), today: @js(__('member.today')),
                    months: @js([__('challenge.personal_challenge_create_month_january'),__('challenge.personal_challenge_create_month_february'),__('challenge.personal_challenge_create_month_march'),__('challenge.personal_challenge_create_month_april'),__('challenge.personal_challenge_create_month_may'),__('challenge.personal_challenge_create_month_june'),__('challenge.personal_challenge_create_month_july'),__('challenge.personal_challenge_create_month_august'),__('challenge.personal_challenge_create_month_september'),__('challenge.personal_challenge_create_month_october'),__('challenge.personal_challenge_create_month_november'),__('challenge.personal_challenge_create_month_december')]),
                    dows: @js([__('challenge.personal_challenge_create_dow_su'),__('challenge.personal_challenge_create_dow_mo'),__('challenge.personal_challenge_create_dow_tu'),__('challenge.personal_challenge_create_dow_we'),__('challenge.personal_challenge_create_dow_th'),__('challenge.personal_challenge_create_dow_fr'),__('challenge.personal_challenge_create_dow_sa')]),
                    pleaseChooseImage: @js(__('Please fill in all required fields and add a photo.')),
                    invalidImage: @js(__('Please choose an image file.')),
                    networkError: @js(__('Something went wrong. Please try again.')),
                    goalCreated: @js(__('member.goal_created')),
                    goalUpdated: @js(__('member.goal_updated')),
                }
             })"
             {{-- The Goal-success metric sheet opens the add form from outside this tab.
                  The form is teleported to <body>, so it shows even while the tab is hidden. --}}
             @open-goal-sheet.window="openAdd()">
            {{-- Goals summary — ring (success rate) + counts, mirrors the attendance card. --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-5" x-show="goals.length">
                <div class="mp-ring" :style="'--p:'+successRate+'; width:84px; height:84px;'"><b style="font-size:18px" x-text="successRate+'%'"></b></div>
                <div class="flex-1 space-y-2">
                    <div class="flex justify-between text-sm"><span class="text-muted-foreground">{{ __('member.goals_done') }}</span><span class="font-bold text-green-600" x-text="doneCount"></span></div>
                    <div class="flex justify-between text-sm"><span class="text-muted-foreground">{{ __('member.goals_active') }}</span><span class="font-bold text-amber-500" x-text="activeCount"></span></div>
                    <div class="flex justify-between text-sm"><span class="text-muted-foreground">{{ __('member.goals_total') }}</span><span class="font-bold" x-text="activeCount + doneCount"></span></div>
                </div>
            </div>

            @if($canEditBasic ?? false)
                {{-- Goals exist → a small circular "add more" tucked in the top corner. --}}
                <div class="flex justify-end -mb-1" x-show="goals.length">
                    <button type="button" @click="openAdd()" aria-label="{{ __('member.add_goal') }}"
                            class="m-press w-9 h-9 rounded-full bg-primary text-white grid place-items-center shadow-md shadow-primary/25 hover:bg-primary/90 transition-colors flex-shrink-0">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            @endif

            <template x-if="!goals.length">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-bold text-foreground flex items-center gap-2"><i class="bi bi-bullseye text-primary"></i> {{ __('member.tab_goals') }}</h3>
                        @if($canEditBasic ?? false)
                            <button type="button" @click="openAdd()" class="m-press inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary text-white text-xs font-bold active:bg-primary/90">
                                <i class="bi bi-plus-lg"></i>{{ __('member.add_goal') }}
                            </button>
                        @endif
                    </div>
                    <p class="text-sm text-muted-foreground text-center py-4">{{ __('member.no_goals') }}</p>
                </div>
            </template>

            <template x-for="g in goals" :key="g.id">
                <div class="m-card m-press cursor-pointer bg-white rounded-2xl shadow-sm border border-gray-100 p-4" @click="openDetail(g)">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold text-foreground truncate" x-text="g.title"></p>
                            <p class="text-[11px] text-muted-foreground truncate mt-0.5" x-show="g.description" x-text="g.description"></p>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium flex-shrink-0" :class="g.status==='completed' ? 'bg-green-100 text-green-700' : 'bg-accent text-primary'" x-text="g.status==='completed' ? @js(__('member.goal_achieved')) : @js(__('member.goals_active'))"></span>
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-muted overflow-hidden"><div class="h-full rounded-full bg-primary transition-all" :style="'width:' + pct(g) + '%'"></div></div>
                    <p class="text-[11px] text-muted-foreground mt-1"><span x-text="g.current_progress_value"></span> / <span x-text="g.target_value || '—'"></span> <span x-text="g.unit"></span> · <span x-text="pct(g)"></span>% · <span x-text="g.target_date"></span></p>

                    <template x-if="g.before_proof || g.after_proof">
                        <div class="flex items-center gap-2 mt-3">
                            <template x-if="g.before_proof">
                                <div class="flex-1 min-w-0">
                                    <img :src="g.before_proof" class="w-full h-20 object-cover rounded-xl" alt="">
                                    <p class="text-[10px] text-muted-foreground text-center mt-1">{{ __('member.before') }}</p>
                                </div>
                            </template>
                            <template x-if="g.after_proof">
                                <div class="flex-1 min-w-0">
                                    <img :src="g.after_proof" class="w-full h-20 object-cover rounded-xl" alt="">
                                    <p class="text-[10px] text-muted-foreground text-center mt-1">{{ __('member.after') }}</p>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="g.status==='completed' && g.days_taken !== null">
                        <p class="text-[11px] font-semibold text-green-600 mt-2 flex items-center gap-1"><i class="bi bi-trophy-fill"></i><span x-text="g.days_taken"></span> {{ __('member.days_to_achieve') }}</p>
                    </template>
                </div>
            </template>

            {{-- Add-goal bottom sheet (teleported to body) --}}
            <template x-teleport="body">
                <div x-show="addOpen" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="addOpen=false">
                    <div x-show="addOpen" x-transition.opacity class="absolute inset-0 bg-black/50" @click="addOpen=false"></div>
                    <div x-show="addOpen"
                         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                         class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">
                        <div class="flex-shrink-0 px-5 pt-3 pb-4 border-b border-gray-100">
                            <div class="w-10 h-1.5 bg-gray-200 rounded-full mx-auto mb-3"></div>
                            <h3 class="text-lg font-bold text-gray-900">{{ __('member.add_goal') }}</h3>
                        </div>
                        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.goal_title') }}</label>
                                <input type="text" x-model="addForm.title" maxlength="150" placeholder="{{ __('member.goal_title_placeholder') }}" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.goal_description') }} <span class="text-muted-foreground font-normal">({{ __('challenge.personal_challenge_create_optional') }})</span></label>
                                <textarea x-model="addForm.description" rows="2" maxlength="1000" placeholder="{{ __('member.goal_description_placeholder') }}" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40"></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.goal_target_value') }}</label>
                                    <input type="number" step="0.1" min="0" x-model="addForm.target_value" inputmode="decimal" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.goal_unit') }}</label>
                                    <input type="text" x-model="addForm.unit" maxlength="30" placeholder="{{ __('member.goal_unit_placeholder') }}" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40">
                                </div>
                            </div>

                            {{-- Custom target-date calendar popover (Design Rule #4 — no native <input type=date>) --}}
                            <div class="relative" :style="dateOpen ? 'z-index:1100' : ''" x-data="{ view: goalDateView(addForm.target_date) }" x-init="$watch('addOpen', v => { if (v) view = goalDateView(addForm.target_date) })" @click.outside="dateOpen=false" @keydown.escape="dateOpen=false">
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.goal_target_date') }}</label>
                                <button type="button" @click="dateOpen=!dateOpen" class="w-full px-3 py-2.5 border rounded-xl text-sm bg-white text-start flex items-center gap-2 outline-none transition-colors" :class="dateOpen ? 'ring-2 ring-purple-500 border-transparent' : 'border-gray-200'">
                                    <i class="bi bi-calendar-event text-gray-400 flex-shrink-0"></i>
                                    <span class="flex-1 truncate" :class="addForm.target_date ? 'text-foreground' : 'text-gray-400'" x-text="addForm.target_date ? fmtDate(addForm.target_date) : i18n.pickDate"></span>
                                    <i class="bi bi-chevron-down text-gray-400 text-xs transition-transform flex-shrink-0" :class="dateOpen ? 'rotate-180' : ''"></i>
                                </button>
                                <div x-show="dateOpen" x-cloak x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="absolute mt-1.5 w-full bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden p-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <button type="button" @click="view = view.m===0 ? {y:view.y-1,m:11} : {y:view.y,m:view.m-1}" class="m-press w-8 h-8 rounded-lg grid place-items-center text-muted-foreground hover:bg-muted/60"><i class="bi bi-chevron-left text-sm"></i></button>
                                        <p class="text-sm font-bold text-foreground" x-text="i18n.months[view.m] + ' ' + view.y"></p>
                                        <button type="button" @click="view = view.m===11 ? {y:view.y+1,m:0} : {y:view.y,m:view.m+1}" class="m-press w-8 h-8 rounded-lg grid place-items-center text-muted-foreground hover:bg-muted/60"><i class="bi bi-chevron-right text-sm"></i></button>
                                    </div>
                                    <div class="grid grid-cols-7 gap-1 mb-1">
                                        <template x-for="dw in i18n.dows" :key="dw"><span class="text-[10px] font-bold text-muted-foreground text-center py-1" x-text="dw"></span></template>
                                    </div>
                                    <div class="grid grid-cols-7 gap-1">
                                        <template x-for="(d, i) in goalCalGrid(view)" :key="i">
                                            <button type="button" :disabled="!d || goalIsPast(view, d)" @click="if (d && !goalIsPast(view, d)) { addForm.target_date = goalIso(view, d); dateOpen=false }"
                                                class="h-9 rounded-lg text-sm grid place-items-center transition-colors"
                                                :class="!d ? 'invisible' : (goalIso(view,d)===addForm.target_date ? 'bg-primary text-white font-bold' : (goalIsPast(view,d) ? 'text-gray-300 cursor-not-allowed' : 'text-foreground hover:bg-muted/60'))"
                                                x-text="d"></button>
                                        </template>
                                    </div>
                                    <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                                        <button type="button" @click="addForm.target_date=''; dateOpen=false" class="text-[11px] font-semibold text-muted-foreground hover:text-foreground">{{ __('member.clear') }}</button>
                                        <button type="button" @click="addForm.target_date = today; dateOpen=false" class="text-[11px] font-semibold text-primary">{{ __('member.today') }}</button>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('member.goal_before_photo') }}</label>
                                <label class="relative flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-2xl p-6 cursor-pointer hover:border-primary/50 transition-colors overflow-hidden">
                                    <template x-if="!addForm.beforePreview">
                                        <div class="text-center">
                                            <i class="bi bi-camera text-3xl text-gray-300"></i>
                                            <p class="text-sm text-muted-foreground mt-2">{{ __('member.goal_before_photo_hint') }}</p>
                                        </div>
                                    </template>
                                    <template x-if="addForm.beforePreview">
                                        <img :src="addForm.beforePreview" class="max-h-56 rounded-xl object-contain" alt="">
                                    </template>
                                    <input type="file" accept="image/*" class="hidden" @change="pickBeforePhoto($event)">
                                </label>
                            </div>
                        </div>
                        <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 flex gap-3" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                            <button type="button" @click="addOpen=false" class="flex-1 py-3 rounded-xl border border-gray-200 text-gray-700 font-medium active:scale-[.98] transition">{{ __('shared.cancel') }}</button>
                            <button type="button" @click="submitAdd()" :disabled="addSubmitting" class="flex-1 py-3 rounded-xl bg-primary text-white font-semibold active:scale-[.98] transition disabled:opacity-60 flex items-center justify-center gap-2">
                                <span x-show="!addSubmitting"><i class="bi bi-check-lg mr-1"></i>{{ __('member.create_goal') }}</span>
                                <span x-show="addSubmitting" class="flex items-center gap-2"><i class="bi bi-arrow-repeat animate-spin"></i>…</span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Goal detail / update / achieve bottom sheet (teleported to body) --}}
            <template x-teleport="body">
                <div x-show="detailOpen" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="detailOpen=false">
                    <div x-show="detailOpen" x-transition.opacity class="absolute inset-0 bg-black/50" @click="detailOpen=false"></div>
                    <div x-show="detailOpen"
                         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                         class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">
                        <div class="flex-shrink-0 px-5 pt-3 pb-4 border-b border-gray-100">
                            <div class="w-10 h-1.5 bg-gray-200 rounded-full mx-auto mb-3"></div>
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-lg font-bold text-gray-900 min-w-0 truncate" x-text="activeGoal && activeGoal.title"></h3>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-medium flex-shrink-0" :class="activeGoal && activeGoal.status==='completed' ? 'bg-green-100 text-green-700' : 'bg-accent text-primary'" x-text="activeGoal && (activeGoal.status==='completed' ? @js(__('member.goal_achieved')) : @js(__('member.goals_active')))"></span>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                            <p class="text-sm text-muted-foreground" x-show="activeGoal && activeGoal.description" x-text="activeGoal && activeGoal.description"></p>

                            <div class="rounded-xl bg-muted/40 p-3">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-semibold text-foreground"><span x-text="activeGoal && activeGoal.current_progress_value"></span> / <span x-text="activeGoal && activeGoal.target_value"></span> <span x-text="activeGoal && activeGoal.unit"></span></span>
                                    <span class="text-sm font-bold text-primary" x-text="activeGoal && pct(activeGoal) + '%'"></span>
                                </div>
                                <div class="h-2 rounded-full bg-white overflow-hidden"><div class="h-full rounded-full bg-primary transition-all" :style="'width:' + (activeGoal ? pct(activeGoal) : 0) + '%'"></div></div>
                                <p class="text-[11px] text-muted-foreground mt-2 flex items-center gap-1"><i class="bi bi-calendar-event"></i>{{ __('member.goal_target_date') }}: <span x-text="activeGoal && activeGoal.target_date"></span></p>
                                <template x-if="activeGoal && activeGoal.status==='completed' && activeGoal.days_taken !== null">
                                    <p class="text-[11px] font-semibold text-green-600 mt-1 flex items-center gap-1"><i class="bi bi-trophy-fill"></i><span x-text="activeGoal.days_taken"></span> {{ __('member.days_to_achieve') }}</p>
                                </template>
                            </div>

                            {{-- Full, uncropped photos — tap either one for a clear, full-screen view --}}
                            <template x-if="activeGoal && (activeGoal.before_proof || activeGoal.after_proof)">
                                <div class="grid gap-2" :class="(activeGoal.before_proof && activeGoal.after_proof) ? 'grid-cols-2' : 'grid-cols-1'">
                                    <template x-if="activeGoal.before_proof">
                                        <button type="button" class="m-press block" @click="lightboxImage = activeGoal.before_proof">
                                            <img :src="activeGoal.before_proof" class="w-full max-h-72 object-contain rounded-xl bg-muted border border-gray-100" alt="">
                                            <p class="text-[11px] text-muted-foreground text-center mt-1">{{ __('member.before') }}</p>
                                        </button>
                                    </template>
                                    <template x-if="activeGoal.after_proof">
                                        <button type="button" class="m-press block" @click="lightboxImage = activeGoal.after_proof">
                                            <img :src="activeGoal.after_proof" class="w-full max-h-72 object-contain rounded-xl bg-muted border border-gray-100" alt="">
                                            <p class="text-[11px] text-muted-foreground text-center mt-1">{{ __('member.after') }}</p>
                                        </button>
                                    </template>
                                </div>
                            </template>

                            <template x-if="editable">
                                <div class="space-y-4 pt-1 border-t border-gray-100">
                                    <div class="pt-3">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.update_progress') }} (<span x-text="activeGoal && activeGoal.unit"></span>)</label>
                                        <input type="number" step="0.1" min="0" x-model="progressValue" inputmode="decimal" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40">
                                    </div>

                                    <label class="flex items-center gap-2.5 bg-muted/40 rounded-xl p-3 cursor-pointer">
                                        <input type="checkbox" x-model="achieving" class="w-4 h-4 rounded accent-primary flex-shrink-0">
                                        <span class="text-sm font-semibold text-foreground">{{ __('member.mark_as_achieved') }}</span>
                                    </label>

                                    <template x-if="achieving">
                                        <div>
                                            <p class="text-[11px] text-muted-foreground mb-2">{{ __('member.mark_as_achieved_hint') }}</p>
                                            <label class="relative flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-2xl p-6 cursor-pointer hover:border-primary/50 transition-colors overflow-hidden">
                                                <template x-if="!afterPreview">
                                                    <div class="text-center">
                                                        <i class="bi bi-camera text-3xl text-gray-300"></i>
                                                        <p class="text-sm text-muted-foreground mt-2">{{ __('member.goal_after_photo_hint') }}</p>
                                                    </div>
                                                </template>
                                                <template x-if="afterPreview">
                                                    <img :src="afterPreview" class="max-h-56 rounded-xl object-contain" alt="">
                                                </template>
                                                <input type="file" accept="image/*" class="hidden" @change="pickAfterPhoto($event)">
                                            </label>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 flex gap-3" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                            <template x-if="editable">
                                <button type="button" @click="detailOpen=false" class="flex-1 py-3 rounded-xl border border-gray-200 text-gray-700 font-medium active:scale-[.98] transition">{{ __('shared.cancel') }}</button>
                            </template>
                            <button type="button" x-show="!editable" @click="detailOpen=false" class="flex-1 py-3 rounded-xl border border-gray-200 text-gray-700 font-medium active:scale-[.98] transition">{{ __('shared.close') }}</button>
                            <template x-if="editable">
                                <button type="button" @click="submitUpdate()" :disabled="updateSubmitting || (achieving && !afterProof)" class="flex-1 py-3 rounded-xl bg-primary text-white font-semibold active:scale-[.98] transition disabled:opacity-60 flex items-center justify-center gap-2">
                                    <span x-show="!updateSubmitting"><i class="bi bi-check-lg mr-1"></i>{{ __('member.save') }}</span>
                                    <span x-show="updateSubmitting" class="flex items-center gap-2"><i class="bi bi-arrow-repeat animate-spin"></i>…</span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Photo lightbox — full, uncropped view of a before/after proof photo --}}
            <template x-teleport="body">
                <div x-show="lightboxImage" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center p-4" @click="lightboxImage=null" @keydown.escape.window="lightboxImage=null">
                    <div x-show="lightboxImage" x-transition.opacity class="absolute inset-0 bg-black/90"></div>
                    <button type="button" class="absolute top-4 right-4 rtl:right-auto rtl:left-4 w-10 h-10 rounded-full bg-white/10 text-white grid place-items-center z-10" @click.stop="lightboxImage=null"><i class="bi bi-x-lg text-lg"></i></button>
                    <img x-show="lightboxImage" x-transition :src="lightboxImage" class="relative max-w-full max-h-full object-contain rounded-lg" @click.stop alt="">
                </div>
            </template>
        </div>

        {{-- ===== Tournaments ===== --}}
        @php
            $tvStoreUrl = $relationship->relationship_type === 'admin_view'
                ? route('admin.platform.members.store-tournament', $relationship->dependent->id)
                : route('member.store-tournament', $relationship->dependent->id);
            $tvAffiliations = ($clubAffiliations ?? collect())->map(fn ($a) => [
                'id' => $a->id, 'name' => $a->club_name, 'linked' => (bool) $a->tenant_id,
            ])->values();
        @endphp
        <div x-show="tab==='tournaments'" x-transition.opacity x-cloak class="space-y-3"
             x-data="tournamentSheet({ storeUrl: '{{ $tvStoreUrl }}', csrf: '{{ csrf_token() }}', memberId: {{ (int) $relationship->dependent->id }}, canAdd: {{ $isSelf ? 'true' : 'false' }}, affiliations: @js($tvAffiliations) })"
             @open-achievement-sheet.window="openAdd()">
            @php $hasTournamentContent = ($awardedAchievements ?? collect())->isNotEmpty() || $tournamentEvents->isNotEmpty(); @endphp
            @if($isSelf || ! $hasTournamentContent)
                {{-- Bare section header + labeled add button (matches Work History / Active clubs).
                     Always outside the card, so the empty state keeps the same header as the filled one. --}}
                <div class="flex items-center justify-between gap-2">
                    <h3 class="font-bold text-foreground flex items-center gap-2 text-[15px]"><i class="bi bi-trophy text-primary"></i>{{ __('member.tab_tournaments') }}</h3>
                    @if($isSelf)
                        <button type="button" @click="openAdd()" aria-label="{{ __('Add achievement') }}"
                                class="m-press inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary text-white text-xs font-bold shadow-sm shadow-primary/25 hover:bg-primary/90 transition-colors flex-shrink-0">
                            <i class="bi bi-plus-lg"></i>{{ __('Add achievement') }}
                        </button>
                    @endif
                </div>
            @endif
            @if(($awardedAchievements ?? collect())->isNotEmpty())
                <div x-data="{ showAch:false, ach:null, idx:0,
                               openAch(a){ this.ach=a; this.idx=0; this.showAch=true; },
                               medalEmoji(r){ r=(r||'').toLowerCase(); var m='';
                                   if(r.includes('gold'))m+='🥇'; if(r.includes('silver'))m+='🥈'; if(r.includes('bronze'))m+='🥉';
                                   return m||'🏅'; } }">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2 flex items-center gap-1"><i class="bi bi-award-fill text-amber-400"></i>{{ __('member.medals_awards') }}</p>
                    <div class="space-y-2.5">
                        @foreach($achList as $item)
                            @php $a = $item['a']; $emoji = $item['emoji']; $achLocation = $item['location']; $metaLine = $item['metaLine']; @endphp
                            <button type="button" @click='openAch(@json($item['data']))' class="m-card m-press p-3 flex items-start gap-3 w-full text-start">
                                <span class="w-12 h-12 rounded-full bg-amber-50 grid place-items-center text-2xl flex-shrink-0">{{ $emoji }}</span>
                                <div class="min-w-0 flex-1">
                                    {{-- Member-first: the medal they won is the headline --}}
                                    <p class="font-bold text-foreground text-sm leading-tight">{{ $a->member_award ?: __('member.award_default') }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate mt-0.5"><i class="bi bi-trophy text-amber-400 mr-0.5"></i>{{ $a->tr('short_title') ?: $a->tr('title') }}</p>
                                    @if($metaLine)
                                        <p class="text-[10px] text-muted-foreground/80 truncate mt-0.5">@if($achLocation)<i class="bi bi-geo-alt mr-0.5"></i>@endif{{ $metaLine }}</p>
                                    @endif
                                    <p class="text-[10px] text-muted-foreground/80 truncate">{{ __('member.award_via', ['club' => $a->tenant?->tr('club_name') ?? '']) }}</p>
                                </div>
                                <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/40 text-sm flex-shrink-0 self-center"></i>
                            </button>
                        @endforeach
                    </div>

                    {{-- Shared achievement detail sheet (teleported to body) --}}
                    @include('components-templates.member.mobile.partials.achievement-detail-sheet')
                </div>
                @if($tournamentEvents->isNotEmpty())
                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-1 mt-2">{{ __('member.tab_tournaments') }}</p>
                @endif
            @endif

            <div id="mobileTournamentsList" class="space-y-3">
            @forelse($tournamentEvents as $t)
                @php
                    // A club decision is final — never offer a button that would no-op.
                    $clubDecided = $t->verification_method === 'club_confirm'
                        && in_array($t->verification_status, ['verified', 'rejected'], true);
                    $canRequestVerify = $t->clubAffiliation?->tenant_id && ! $clubDecided && $t->verification_status !== 'verified';
                    $editPayload = [
                        'uuid' => $t->uuid,
                        'title' => $t->title,
                        'type' => $t->type,
                        'sport' => $t->sport,
                        'date' => optional($t->date)->toDateString(),
                        'location' => $t->location,
                        'club_affiliation_id' => $t->club_affiliation_id,
                        'medal_type' => optional($t->performanceResults->first())->medal_type,
                        'verified' => $t->verification_status === 'verified',
                        'update_url' => route('member.tournament.update', [$t->user_id, $t->uuid]),
                        'delete_url' => route('member.tournament.destroy', [$t->user_id, $t->uuid]),
                    ];
                @endphp
                {{-- Payload goes through {{ }} so quotes/apostrophes in a title are HTML-escaped;
                     inlining raw JSON into an @click attribute would break on an apostrophe. --}}
                <div data-tournament-card data-uuid="{{ $t->uuid }}" @if($isSelf) data-edit="{{ json_encode($editPayload) }}" @endif class="group relative bg-white rounded-2xl shadow-sm border border-gray-100 p-4 overflow-hidden">
                    <span class="absolute inset-y-0 left-0 rtl:left-auto rtl:right-0 w-1 bg-amber-400/80"></span>
                    <div class="flex items-start gap-3">
                        <span class="w-12 h-12 rounded-xl bg-amber-50 grid place-items-center text-amber-600 flex-shrink-0 ring-1 ring-amber-100"><i class="bi bi-trophy-fill text-lg"></i></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start gap-2">
                                <p class="font-bold text-foreground text-[15px] leading-snug truncate flex-1">{{ $t->title }}</p>
                                @if($isSelf)
                                    <div class="flex items-center gap-1 flex-shrink-0 -mt-1">
                                        <button type="button" @click="openEdit($el)" aria-label="{{ __('shared.edit') }}"
                                                class="m-press w-8 h-8 rounded-lg grid place-items-center text-muted-foreground active:bg-muted"><i class="bi bi-pencil text-[13px]"></i></button>
                                        <button type="button" @click="remove($el)" aria-label="{{ __('shared.delete') }}"
                                                class="m-press w-8 h-8 rounded-lg grid place-items-center text-red-500 active:bg-red-50"><i class="bi bi-trash text-[13px]"></i></button>
                                    </div>
                                @endif
                            </div>
                            <p class="text-[12px] font-medium text-foreground/60 truncate mt-0.5">{{ $t->sport }}@if($t->location) · {{ $t->location }}@endif</p>
                            {{-- Inline meta: date + medals + verification (all one row, matches Work history) --}}
                            @php $mc = ['1st'=>'bg-amber-100 text-amber-700','2nd'=>'bg-slate-100 text-slate-600','3rd'=>'bg-orange-100 text-orange-700','special'=>'bg-accent text-primary']; @endphp
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-muted-foreground">
                                <span class="inline-flex items-center gap-1"><i class="bi bi-calendar-range text-primary/50"></i>{{ optional($t->date)->format('d M Y') }}</span>
                                @foreach($t->performanceResults as $r)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $mc[$r->medal_type] ?? 'bg-gray-100 text-gray-600' }}"><i class="bi bi-award-fill"></i>{{ ucfirst($r->medal_type) }}</span>
                                @endforeach
                                {{-- Provenance: honest state + evidence + request action, inline --}}
                                <span class="inline-flex items-center gap-2 flex-wrap" data-verify-row="{{ $t->uuid }}">
                                    <x-verification-badge data-verify-badge :status="$t->verification_status" :club="$t->verifiedByTenant?->tr('club_name') ?? $t->verifiedByTenant?->club_name" />
                                    @if($t->evidence_path)
                                        <a href="{{ route('member.tournament.evidence', [$t->user_id, $t->uuid]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[11px] text-muted-foreground hover:text-primary"><i class="bi bi-paperclip"></i>{{ __('Evidence') }}</a>
                                    @endif
                                    @if($isSelf && $canRequestVerify)
                                        {{-- While pending this is a rate-limited nudge, not a new request. --}}
                                        <button type="button" data-verify-btn @click="requestVerify($el, '{{ route('member.tournament.request-verification', [$t->user_id, $t->uuid]) }}')" class="inline-flex items-center gap-1 text-[11px] font-medium text-primary"><i class="bi bi-patch-check"></i>{{ $t->verification_status === 'pending' ? __('member.tournament_verify_resend') : __('Request verification') }}</button>
                                    @elseif($isSelf && ! $t->clubAffiliation?->tenant_id && $t->verification_status !== 'verified')
                                        {{-- No platform club to confirm → peers/coaches vouch on the public profile. --}}
                                        <button type="button" @click="shareForVouch('{{ route('people.show', $user->uuid) }}')" class="inline-flex items-center gap-1 text-[11px] font-medium text-primary" title="{{ __('member.get_vouched_hint') }}"><i class="bi bi-people"></i>{{ __('member.get_vouched') }}</button>
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                @if(($awardedAchievements ?? collect())->isEmpty())
                <div id="mobileTournamentsEmpty" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <p class="text-sm text-muted-foreground text-center py-4">{{ __('member.no_tournaments') }}</p>
                </div>
                @endif
            @endforelse
            </div>

            {{-- Self-claim bottom-sheet (teleported to body to escape transformed ancestors) --}}
            <template x-teleport="body">
                <div x-show="addOpen" x-cloak @keydown.escape.window="addOpen=false" class="fixed inset-0 z-[70]">
                    <div x-show="addOpen" x-transition.opacity class="absolute inset-0 bg-black/50" @click="addOpen=false"></div>
                    <div x-show="addOpen"
                         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                         class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl">
                        {{-- Header --}}
                        <div class="flex-shrink-0 px-5 pt-3 pb-3 border-b border-gray-100">
                            <div class="w-10 h-1 rounded-full bg-gray-300 mx-auto mb-3"></div>
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-foreground" x-text="editing ? '{{ __('member.tournament_edit_title') }}' : '{{ __('Add achievement') }}'"></h3>
                                <button type="button" @click="addOpen=false" class="w-8 h-8 rounded-full grid place-items-center text-muted-foreground hover:bg-muted"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                        {{-- Body --}}
                        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                            <div x-show="editing && wasVerified" x-cloak class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                <i class="bi bi-exclamation-triangle-fill mt-0.5"></i>
                                <span>{{ __('member.tournament_edit_resets_badge') }}</span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Title') }}</label>
                                <input type="text" x-model="form.title" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="{{ __('e.g. National Championship 2019') }}">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Sport') }}</label>
                                    <input type="text" x-model="form.sport" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="{{ __('e.g. Taekwondo') }}">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Date') }}</label>
                                    <x-date-picker model="form.date" placeholder="{{ __('Pick a date') }}" />
                                </div>
                            </div>
                            {{-- Type — selection cards --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('Type') }}</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <template x-for="opt in typeOptions" :key="opt.v">
                                        <button type="button" @click="form.type=opt.v"
                                                class="flex items-center gap-2 px-3 py-2.5 rounded-xl border text-sm text-start transition-colors"
                                                :class="form.type===opt.v ? 'border-primary bg-primary/5 text-primary font-medium' : 'border-gray-200 text-gray-600'">
                                            <i class="bi" :class="opt.icon"></i><span x-text="opt.l"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            {{-- Club — selection cards (drives verification) --}}
                            <div x-show="affiliations.length">
                                {{-- member.club_singular, NOT __('Club'): a bare
                                     __('Club') resolves to the club.php translation
                                     GROUP on a case-insensitive filesystem, handing
                                     an array to htmlspecialchars(). --}}
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('member.club_singular') }} <span class="text-xs font-normal text-gray-400">({{ __('for verification') }})</span></label>
                                <div class="space-y-2">
                                    <button type="button" @click="form.club_affiliation_id=null"
                                            class="w-full flex items-center gap-2 px-3 py-2.5 rounded-xl border text-sm text-start transition-colors"
                                            :class="!form.club_affiliation_id ? 'border-primary bg-primary/5' : 'border-gray-200'">
                                        <i class="bi bi-person"></i>{{ __('Individual / no club') }}
                                    </button>
                                    <template x-for="a in affiliations" :key="a.id">
                                        <button type="button" @click="form.club_affiliation_id=a.id"
                                                class="w-full flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl border text-sm text-start transition-colors"
                                                :class="form.club_affiliation_id===a.id ? 'border-primary bg-primary/5' : 'border-gray-200'">
                                            <span class="flex items-center gap-2 min-w-0"><i class="bi bi-trophy flex-shrink-0"></i><span class="truncate" x-text="a.name"></span></span>
                                            <span x-show="a.linked" class="text-[10px] text-green-600 flex-shrink-0"><i class="bi bi-patch-check"></i> {{ __('verifiable') }}</span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            {{-- Medal — selection cards --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('Result') }}</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <template x-for="opt in medalOptions" :key="opt.v">
                                        <button type="button" @click="form.medal_type=opt.v"
                                                class="flex flex-col items-center gap-1 px-2 py-2.5 rounded-xl border text-xs transition-colors"
                                                :class="form.medal_type===opt.v ? 'border-primary bg-primary/5 text-primary font-medium' : 'border-gray-200 text-gray-600'">
                                            <span class="text-lg" x-text="opt.e"></span><span x-text="opt.l"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Location') }} <span class="text-xs font-normal text-gray-400">({{ __('optional') }})</span></label>
                                <input type="text" x-model="form.location" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            {{-- Evidence --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Supporting evidence') }} <span class="text-xs font-normal text-gray-400">({{ __('optional') }})</span></label>
                                <p class="text-xs text-gray-500 mb-2">{{ __('Helps a club verify — it does not verify automatically.') }}</p>
                                <label class="flex items-center gap-3 border border-dashed border-gray-300 rounded-xl px-4 py-3 cursor-pointer">
                                    <i class="bi bi-cloud-arrow-up text-xl text-gray-400"></i>
                                    <span class="text-sm text-gray-600 truncate" x-text="evidenceName || '{{ __('Choose an image') }}'"></span>
                                    <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden" @change="readEvidence($event)">
                                </label>
                                <img x-show="evidencePreview" :src="evidencePreview" class="mt-2 h-20 rounded-lg border border-gray-200 object-cover" alt="">
                            </div>
                        </div>
                        {{-- Sticky footer --}}
                        <div class="flex-shrink-0 border-t border-gray-100 bg-background px-5 pt-3" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                            <button type="button" @click="submit()" :disabled="saving"
                                    class="w-full bg-primary text-white py-3 rounded-xl font-semibold disabled:opacity-60 flex items-center justify-center gap-2">
                                <span x-show="!saving" x-text="editing ? '{{ __('shared.save') }}' : '{{ __('Save achievement') }}'"></span>
                                <span x-show="saving"><i class="bi bi-arrow-repeat animate-spin"></i></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- ===== Clubs / affiliations ===== --}}
        @php
            $activeAffil = $clubAffiliations->whereNull('end_date')->sortByDesc('start_date')->values();
            $leftAffil   = $clubAffiliations->whereNotNull('end_date')->sortByDesc('end_date')->values();

            // Detail payload for the tap-through sheet — one entry per affiliation.
            $instrUserIds = $clubAffiliations->flatMap(fn ($a) => collect($a->instructorList())->pluck('user_id'))->filter()->unique();
            $instrUsers = $instrUserIds->isNotEmpty()
                ? \App\Models\User::whereIn('id', $instrUserIds)->get(['id','uuid','full_name','name','profile_picture','updated_at'])->keyBy('id')
                : collect();

            // Free-text instructors: try to match a system member by exact name so the
            // badge can still link to their PUBLIC profile. Only a UNIQUE match links —
            // an ambiguous name (shared by two members) is left as plain text.
            $instrNames = $clubAffiliations
                ->flatMap(fn ($a) => collect($a->instructorList())->filter(fn ($i) => empty($i['user_id']))->pluck('name'))
                ->map(fn ($n) => mb_strtolower(trim((string) $n)))->filter()->unique()->values();
            $instrByName = collect();
            if ($instrNames->isNotEmpty()) {
                $rows = \App\Models\User::where(function ($q) use ($instrNames) {
                    $q->whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(full_name)'), $instrNames->all())
                      ->orWhereIn(\Illuminate\Support\Facades\DB::raw('LOWER(name)'), $instrNames->all());
                })->get(['id','uuid','full_name','name','profile_picture','updated_at']);
                $grouped = [];
                foreach ($rows as $u) {
                    foreach (array_unique(array_filter([mb_strtolower(trim((string) $u->full_name)), mb_strtolower(trim((string) $u->name))])) as $key) {
                        $grouped[$key][] = $u;
                    }
                }
                $instrByName = collect($grouped)->filter(fn ($l) => count($l) === 1)->map(fn ($l) => $l[0]);
            }

            // "X years Y months" from a whole-month count.
            $fmtMonths = function (int $m) {
                $m = max(0, $m);
                if ($m < 1) {
                    return '< 1 '.__('month');
                }
                $y = intdiv($m, 12);
                $r = $m % 12;
                $parts = [];
                if ($y) {
                    $parts[] = $y.' '.($y > 1 ? __('years') : __('year'));
                }
                if ($r) {
                    $parts[] = $r.' '.($r > 1 ? __('months') : __('month'));
                }
                return implode(' ', $parts);
            };

            // Accumulated ENROLLED months in a club — sum of the (merged) subscription
            // periods, so gaps between enrolments are not counted. Off-platform / manual
            // records (no subscriptions) fall back to each skill's own recorded span.
            $enrolledMonths = function ($a) {
                $ivals = $a->subscriptions
                    ->filter(fn ($s) => $s->start_date)
                    ->map(fn ($s) => [$s->start_date->copy(), ($s->end_date ?? now())->copy()])
                    ->sortBy(fn ($i) => $i[0]->timestamp)->values()->all();
                $merged = [];
                foreach ($ivals as [$s, $e]) {
                    if ($e->lte($s)) {
                        continue;
                    }
                    if ($merged && $s->lte($merged[count($merged) - 1][1])) {
                        if ($e->gt($merged[count($merged) - 1][1])) {
                            $merged[count($merged) - 1][1] = $e;
                        }
                    } else {
                        $merged[] = [$s, $e];
                    }
                }
                $total = 0;
                foreach ($merged as [$s, $e]) {
                    $total += (int) floor($s->floatDiffInMonths($e));
                }
                return $total;
            };

            $memberBirthdate = $user->birthdate ? \Illuminate\Support\Carbon::parse($user->birthdate) : null;
            $ageAt = fn ($date) => ($memberBirthdate && $date) ? (int) $memberBirthdate->diffInYears(\Illuminate\Support\Carbon::parse($date)) : null;

            $affiliationDetails = $clubAffiliations->mapWithKeys(function ($a) use ($skillEncyclopedia, $instrUsers, $instrByName, $fmtMonths, $enrolledMonths, $ageAt) {
                // System-tracked club (has package subscriptions) → accumulated enrolled
                // time; otherwise each skill keeps its own manually-recorded duration.
                $hasSubs = $a->subscriptions->isNotEmpty();
                $enrolledLabel = $hasSubs ? $fmtMonths($enrolledMonths($a)) : null;

                // Age the member was over this affiliation (start → end, or → today if ongoing).
                $ageStart = $ageAt($a->start_date);
                $ageEnd = $ageAt($a->end_date ?? now());
                $ageLabel = null;
                if ($ageStart !== null && $ageEnd !== null) {
                    $ageLabel = ($ageStart === $ageEnd)
                        ? $ageStart.' '.__('member.years_old')
                        : $ageStart.' - '.$ageEnd.' '.__('member.years_old');
                }

                return [$a->id => [
                    'id' => $a->id,
                    'club_name' => $a->club_name,
                    'logo' => $a->logo ? asset('storage/'.$a->logo) : null,
                    'ongoing' => ! $a->end_date,
                    // Verification (club-confirm if on-platform; else peer vouch).
                    'verification' => $a->verification_status,
                    'can_request' => (bool) $a->tenant_id,
                    'request_url' => route('member.affiliation.request-verification', [$a->member_id, $a->uuid]),
                    'dates' => (optional($a->start_date)->format('M Y') ?: '—').($a->end_date ? ' — '.$a->end_date->format('M Y') : ' — '.__('member.present')),
                    'duration' => $a->formatted_duration ?? null,
                    // Time actually SPENT: accumulated enrolled time for a system club
                    // (gaps excluded); the full recorded span for a manual/pre-system record.
                    'spent' => $enrolledLabel ?? ($a->formatted_duration ?? null),
                    'age' => $ageLabel,
                    'left_ago' => $a->end_date ? $a->end_date->diffForHumans() : null,
                    'location' => $a->location,
                    'note' => $a->description ?: null,
                    'skills' => $a->skillAcquisitions->map(fn ($s) => [
                        'name' => $s->skill_name,
                        'level' => ucfirst($s->proficiency_level),
                        // Enrolled-time for a real club; full recorded span for a manual record.
                        'duration' => $enrolledLabel ?? $s->formatted_duration,
                        'url' => ($skillEncyclopedia[$s->id] ?? null) ? route('activity.show', $skillEncyclopedia[$s->id]) : null,
                    ])->values(),
                    'instructors' => collect($a->instructorList())->map(function ($ins) use ($instrUsers, $instrByName) {
                        // Linked member first, then a unique name match; else plain free text.
                        $u = $ins['user_id']
                            ? $instrUsers->get($ins['user_id'])
                            : $instrByName->get(mb_strtolower(trim((string) $ins['name'])));
                        return [
                            'name' => $u ? ($u->full_name ?: $u->name) : $ins['name'],
                            'avatar' => $u && $u->profile_picture ? asset('storage/'.$u->profile_picture).'?v='.optional($u->updated_at)->timestamp : null,
                            // ?public=1 → always the minimal public profile, even for self.
                            'url' => $u ? route('people.show', ['uuid' => $u->uuid, 'public' => 1]) : null,
                        ];
                    })->values(),
                    'media' => $a->affiliationMedia->map(fn ($m) => [
                        'title' => $m->title, 'url' => $m->full_url, 'icon' => $m->icon_class,
                    ])->values(),
                ]];
            });
        @endphp
        <div x-show="tab==='clubs'" x-transition.opacity x-cloak class="space-y-4"
             x-data="affiliationSheet(@js($affiliationDetails), { storeUrl: '{{ route('member.store-affiliation', $user->id) }}', csrf: '{{ csrf_token() }}', memberId: {{ (int) $user->id }}, canManage: {{ ($isSelf ?? false) ? 'true' : 'false' }} })">
            @php
                // (kept for the markup below)
            @endphp

            {{-- Active --}}
            <div>
                @if($activeAffil->isNotEmpty() || $leftAffil->isEmpty())
                    {{-- Header always outside the card (matches Work history) --}}
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <h3 class="font-bold text-foreground flex items-center gap-2 text-[15px]"><i class="bi bi-diagram-3 text-primary"></i>{{ __('member.active_clubs') }}</h3>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if($activeAffil->isNotEmpty())
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700">{{ $activeAffil->count() }}</span>
                            @endif
                            @if($canEditBasic ?? false)
                                <button type="button" @click="openAdd()" aria-label="{{ __('member.add_club') }}"
                                        class="m-press inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary text-white text-xs font-bold shadow-sm shadow-primary/25 hover:bg-primary/90 transition-colors">
                                    <i class="bi bi-plus-lg"></i>{{ __('member.add_club') }}
                                </button>
                            @endif
                        </div>
                    </div>
                @endif
                @forelse($activeAffil as $a)
                    <button type="button" @click="openSheet({{ $a->id }})" class="group relative block w-full text-start bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-2.5 overflow-hidden m-press">
                        {{-- subtle accent rail --}}
                        <span class="absolute inset-y-0 left-0 rtl:left-auto rtl:right-0 w-1 bg-green-400/80"></span>
                        <div class="flex items-start gap-3">
                            <span class="w-12 h-12 rounded-xl bg-muted grid place-items-center overflow-hidden flex-shrink-0 ring-1 ring-gray-100">
                                @if($a->logo)<img src="{{ asset('storage/'.$a->logo) }}" alt="" class="w-12 h-12 object-cover">@else<i class="bi bi-buildings text-lg text-muted-foreground"></i>@endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="font-bold text-foreground text-[15px] leading-snug truncate">{{ $a->club_name }}</p>
                                    <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>{{ __('member.active') }}
                                    </span>
                                </div>
                                <div class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-muted-foreground">
                                    <span class="inline-flex items-center gap-1"><i class="bi bi-calendar3 text-primary/50"></i>{{ __('member.since') }} {{ optional($a->start_date)->format('M Y') ?: '—' }}</span>
                                    @if($a->location)
                                        <span class="inline-flex items-center gap-1 min-w-0"><i class="bi bi-geo-alt text-primary/50 flex-shrink-0"></i><span class="truncate">{{ $a->location }}</span></span>
                                    @endif
                                    @foreach($a->skillAcquisitions->take(6) as $s)
                                        <span class="inline-flex items-center gap-1 text-primary font-semibold"><i class="bi bi-mortarboard-fill text-primary/60"></i>{{ $s->skill_name }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </button>
                @empty
                    @if($leftAffil->isEmpty())
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                            <p class="text-sm text-muted-foreground text-center py-4">{{ __('member.not_active_in_club') }}</p>
                        </div>
                    @endif
                @endforelse
            </div>

            {{-- Previous clubs --}}
            @if($leftAffil->isNotEmpty())
            <div>
                <div class="flex items-center justify-between gap-2 mb-2">
                    <h3 class="font-bold text-foreground flex items-center gap-2 text-[15px]"><i class="bi bi-clock-history text-primary"></i>{{ __('member.previous_clubs') }}</h3>
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 flex-shrink-0">{{ $leftAffil->count() }}</span>
                </div>

                <div class="space-y-2.5">
                    @foreach($leftAffil as $a)
                        @php $span = ($a->start_date && $a->end_date) ? (int) $a->start_date->diffInMonths($a->end_date) : null; @endphp
                        <button type="button" @click="openSheet({{ $a->id }})" class="group relative block w-full text-start bg-white rounded-2xl shadow-sm border border-gray-100 p-4 overflow-hidden m-press">
                            {{-- subtle muted rail --}}
                            <span class="absolute inset-y-0 left-0 rtl:left-auto rtl:right-0 w-1 bg-gray-300"></span>
                            <div class="flex items-start gap-3">
                                <span class="w-12 h-12 rounded-xl bg-muted grid place-items-center overflow-hidden flex-shrink-0 ring-1 ring-gray-100 grayscale">
                                    @if($a->logo)<img src="{{ asset('storage/'.$a->logo) }}" alt="" class="w-12 h-12 object-cover">@else<i class="bi bi-buildings text-lg text-muted-foreground"></i>@endif
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="font-bold text-foreground/80 text-[15px] leading-snug truncate">{{ $a->club_name }}</p>
                                        <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-500">{{ __('member.left') }}</span>
                                    </div>
                                    <div class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-muted-foreground">
                                        <span class="inline-flex items-center gap-1"><i class="bi bi-calendar-range text-primary/50"></i>{{ optional($a->start_date)->format('M Y') ?: '—' }} – {{ optional($a->end_date)->format('M Y') }}</span>
                                        @if($span !== null)
                                            <span class="inline-flex items-center gap-1"><i class="bi bi-hourglass-split text-primary/50"></i>{{ $span }} {{ \Illuminate\Support\Str::plural('month', max(1,$span)) }}</span>
                                        @endif
                                        @foreach($a->skillAcquisitions->take(6) as $s)
                                            <span class="inline-flex items-center gap-1 text-primary font-semibold"><i class="bi bi-mortarboard-fill text-primary/60"></i>{{ $s->skill_name }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Add-affiliation bottom sheet (teleported to body) --}}
            @if($canEditBasic ?? false)
            <template x-teleport="body">
                <div x-show="addOpen" x-cloak @keydown.escape.window="addOpen=false" class="fixed inset-0 z-[70]">
                    <div x-show="addOpen" x-transition.opacity class="absolute inset-0 bg-black/50" @click="addOpen=false"></div>
                    <div x-show="addOpen"
                         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                         class="absolute inset-x-0 bottom-0 bg-background rounded-t-3xl shadow-2xl max-h-[92vh] flex flex-col">
                        <div class="flex-shrink-0 px-5 pt-3 pb-2 border-b border-gray-100">
                            <div class="w-10 h-1.5 bg-gray-200 rounded-full mx-auto mb-3"></div>
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-foreground">{{ __('member.add_club') }}</h3>
                                <button type="button" @click="addOpen=false" class="w-8 h-8 rounded-full grid place-items-center text-muted-foreground hover:bg-muted"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                            {{-- Source toggle: platform club vs manual entry --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('member.club_source') }}</label>
                                <div class="grid grid-cols-2 gap-1 p-1 bg-muted rounded-2xl text-sm font-semibold">
                                    <button type="button" @click="form.source='platform'" :class="form.source==='platform' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="m-press rounded-xl py-2 transition-colors">
                                        <i class="bi bi-building mr-1"></i>{{ __('member.from_platform') }}
                                    </button>
                                    <button type="button" @click="form.source='manual'" :class="form.source==='manual' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="m-press rounded-xl py-2 transition-colors">
                                        <i class="bi bi-pencil mr-1"></i>{{ __('member.enter_manually') }}
                                    </button>
                                </div>
                            </div>

                            {{-- Platform club picker --}}
                            <div x-show="form.source==='platform'" x-cloak>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.club_name') }} <span class="text-red-500">*</span></label>
                                <x-select-menu model="form.tenant_id"
                                    :options="($allClubs ?? collect())->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->club_name])->values()->all()"
                                    placeholder="{{ __('member.select_platform_club') }}" />
                            </div>

                            {{-- Manual club name --}}
                            <div x-show="form.source==='manual'" x-cloak>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.club_name') }} <span class="text-red-500">*</span></label>
                                <input type="text" x-model="form.club_name" maxlength="255" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="{{ __('member.club_name_manual_ph') }}">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.start_date') }} <span class="text-red-500">*</span></label>
                                    <x-date-picker model="form.start_date" maxExpr="form.end_date || null" placeholder="{{ __('member.start_date') }}" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.end_date') }}</label>
                                    <x-date-picker model="form.end_date" minExpr="form.start_date || null" placeholder="{{ __('member.present') }}" />
                                </div>
                            </div>
                            <label class="flex items-center gap-2.5 cursor-pointer select-none">
                                <input type="checkbox" x-model="form.current" @change="if(form.current) form.end_date=''" class="w-[18px] h-[18px] rounded text-primary border-gray-300 focus:ring-primary">
                                <span class="text-sm text-gray-700">{{ __('member.work_current') }}</span>
                            </label>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.location') }}</label>
                                <input type="text" x-model="form.location" maxlength="255" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="{{ __('member.location') }}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.note') ?? 'Note' }}</label>
                                <textarea x-model="form.description" rows="3" maxlength="1000" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>
                        </div>
                        <div class="flex-shrink-0 border-t border-gray-100 px-5 pt-3 flex gap-2" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                            <button type="button" @click="submitAdd()" :disabled="saving || !form.club_name.trim() || !form.start_date" class="m-press flex-1 py-3 rounded-xl bg-primary text-white text-sm font-semibold active:bg-primary/90 disabled:opacity-60 flex items-center justify-center gap-2">
                                <i class="bi bi-arrow-repeat animate-spin" x-show="saving"></i>
                                <span>{{ __('member.add_club') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
            @endif

            {{-- Tap-through detail sheet (teleported to <body> so the shell transform
                 can't clip it). Read-only view of the affiliation's data. --}}
            <template x-teleport="body">
                <div x-show="show" x-cloak class="fixed inset-0 z-[70]" style="display:none;" @keydown.escape.window="close()">
                    <div x-show="show" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                         class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="close()"></div>

                    <div x-show="show" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                         class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl overflow-hidden">

                        {{-- Grab handle --}}
                        <div class="flex-shrink-0 pt-2.5 pb-1 flex justify-center"><span class="w-10 h-1.5 rounded-full bg-gray-300"></span></div>

                        {{-- Hero header — the club's own logo becomes a blurred identity wash --}}
                        <div class="flex-shrink-0 relative overflow-hidden bg-primary/[0.04]">
                            {{-- club-identity backdrop (real content → per-club colour + depth) --}}
                            <template x-if="cur?.logo">
                                <img :src="cur.logo" alt="" aria-hidden="true" class="absolute inset-0 w-full h-full object-cover scale-[1.6] blur-2xl opacity-30 pointer-events-none select-none"
                                     :class="cur && !cur.ongoing && 'grayscale'">
                            </template>
                            {{-- legibility + blend into the body --}}
                            <div class="absolute inset-0 bg-gradient-to-b from-white/55 via-background/80 to-background"></div>
                            <div class="relative px-5 pt-3 pb-5 flex items-start gap-4">
                                <span class="w-16 h-16 rounded-[1.15rem] bg-white grid place-items-center overflow-hidden flex-shrink-0 ring-1 ring-black/[0.06] shadow-[0_6px_20px_-6px_rgba(0,0,0,0.18)]"
                                      :class="cur && !cur.ongoing && 'grayscale'">
                                    <template x-if="cur && cur.logo"><img :src="cur.logo" alt="" class="w-16 h-16 object-cover"></template>
                                    <template x-if="cur && !cur.logo"><i class="bi bi-buildings text-2xl text-primary/40"></i></template>
                                </span>
                                <div class="min-w-0 flex-1 pt-1">
                                    <h3 class="font-extrabold text-foreground text-[18px] leading-tight tracking-tight line-clamp-2" x-text="cur?.club_name"></h3>
                                    {{-- brand accent underline --}}
                                    <div class="mt-1.5 h-[3px] w-10 rounded-full bg-gradient-to-r from-primary to-primary/20"></div>
                                    <div class="mt-2 flex items-center gap-2 flex-wrap">
                                        {{-- Status — "Left" folds its "how long ago" inside the same pill --}}
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold shadow-sm ring-1 ring-inset backdrop-blur"
                                              :class="cur?.ongoing ? 'bg-green-100/90 text-green-700 ring-green-200' : 'bg-white/90 text-gray-500 ring-gray-200'">
                                            <span x-show="cur?.ongoing" class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                            <span x-show="!cur?.ongoing" class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                            <span x-text="cur?.ongoing ? '{{ __('member.active') }}' : '{{ __('member.left') }}'"></span>
                                            <span x-show="!cur?.ongoing && cur?.left_ago" x-cloak class="font-medium text-gray-400 border-s border-gray-200 ps-1.5" x-text="cur?.left_ago"></span>
                                        </span>
                                        {{-- Time actually spent training at this club --}}
                                        <span x-show="cur?.spent" x-cloak class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold shadow-sm ring-1 ring-inset backdrop-blur bg-white/90 text-gray-500 ring-gray-200">
                                            <i class="bi bi-hourglass-split text-primary/60"></i>
                                            <span x-text="cur?.spent"></span>
                                            <span class="font-medium text-gray-400">{{ __('member.time_spent') }}</span>
                                        </span>
                                        {{-- Verification badge / action --}}
                                        <span x-show="cur?.verification === 'verified'" x-cloak class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold shadow-sm ring-1 ring-inset bg-green-100/90 text-green-700 ring-green-200"><i class="bi bi-patch-check-fill"></i>{{ __('member.verified') }}</span>
                                        <span x-show="cur?.verification === 'pending'" x-cloak class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold shadow-sm ring-1 ring-inset bg-white/90 text-gray-500 ring-gray-200"><i class="bi bi-hourglass-split text-primary/60"></i>{{ __('member.pending') }}</span>
                                        @if($isSelf ?? false)
                                            <button type="button" x-show="(cur?.verification === 'self_reported' || cur?.verification === 'rejected') && cur?.can_request" x-cloak @click="requestVerify()" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold shadow-sm ring-1 ring-inset bg-primary/10 text-primary ring-primary/15"><i class="bi bi-patch-check"></i>{{ __('Request verification') }}</button>
                                            <button type="button" x-show="(cur?.verification === 'self_reported' || cur?.verification === 'rejected') && !cur?.can_request" x-cloak @click="shareForVouch('{{ route('people.show', $user->uuid) }}')" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold shadow-sm ring-1 ring-inset bg-primary/10 text-primary ring-primary/15"><i class="bi bi-people"></i>{{ __('member.get_vouched') }}</button>
                                        @endif
                                    </div>
                                </div>
                                <button type="button" @click="close()" class="m-press w-9 h-9 -mt-1 -me-1 rounded-full grid place-items-center bg-white/80 backdrop-blur text-muted-foreground hover:bg-white hover:text-foreground shadow-sm ring-1 ring-black/5 flex-shrink-0"><i class="bi bi-x-lg text-[13px]"></i></button>
                            </div>
                        </div>

                        {{-- Scrollable body --}}
                        <div class="flex-1 overflow-y-auto px-5 pt-1 space-y-4" style="padding-bottom: calc(2.5rem + env(safe-area-inset-bottom));">
                            {{-- Journey card — dates headline + a divided stat strip --}}
                            <div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden">
                                <div class="px-4 pt-3.5 pb-3 flex items-center gap-3">
                                    <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary grid place-items-center flex-shrink-0"><i class="bi bi-calendar3 text-[15px]"></i></span>
                                    <div class="min-w-0">
                                        <div class="text-[9px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('member.period') }}</div>
                                        <div class="text-[14px] font-bold text-foreground leading-snug" x-text="cur?.dates"></div>
                                    </div>
                                </div>
                                {{-- stat strip (only the metrics that exist) --}}
                                <div x-show="cur?.duration || cur?.age || cur?.location" class="grid border-t border-gray-100 divide-x divide-gray-100"
                                     :class="{'grid-cols-3': [cur?.duration, cur?.age, cur?.location].filter(Boolean).length===3, 'grid-cols-2': [cur?.duration, cur?.age, cur?.location].filter(Boolean).length===2, 'grid-cols-1': [cur?.duration, cur?.age, cur?.location].filter(Boolean).length===1}">
                                    <template x-if="cur?.duration">
                                        <div class="px-3 py-2.5 min-w-0">
                                            <div class="flex items-center gap-1 text-[9px] font-bold uppercase tracking-wider text-muted-foreground mb-0.5"><i class="bi bi-hourglass-split text-primary/60"></i>{{ __('member.duration') }}</div>
                                            <div class="text-[12.5px] font-bold text-foreground truncate" x-text="cur.duration"></div>
                                        </div>
                                    </template>
                                    <template x-if="cur?.age">
                                        <div class="px-3 py-2.5 min-w-0">
                                            <div class="flex items-center gap-1 text-[9px] font-bold uppercase tracking-wider text-muted-foreground mb-0.5"><i class="bi bi-person text-primary/60"></i>{{ __('member.age') }}</div>
                                            <div class="text-[12.5px] font-bold text-foreground truncate" x-text="cur.age"></div>
                                        </div>
                                    </template>
                                    <template x-if="cur?.location">
                                        <div class="px-3 py-2.5 min-w-0">
                                            <div class="flex items-center gap-1 text-[9px] font-bold uppercase tracking-wider text-muted-foreground mb-0.5"><i class="bi bi-geo-alt text-primary/60"></i>{{ __('member.location') }}</div>
                                            <div class="text-[12.5px] font-bold text-foreground truncate" x-text="cur.location"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Note --}}
                            <div x-show="cur?.note" x-cloak class="relative overflow-hidden rounded-2xl bg-primary/[0.05] border border-primary/10 px-4 py-3.5">
                                <span class="absolute inset-y-0 start-0 w-1 bg-primary/40"></span>
                                <div class="flex items-start gap-2.5 ps-1.5">
                                    <i class="bi bi-quote text-primary/60 text-[16px] leading-none mt-0.5 flex-shrink-0"></i>
                                    <p class="text-[12.5px] leading-relaxed text-foreground whitespace-pre-line" x-text="cur?.note"></p>
                                </div>
                            </div>

                            {{-- Skills --}}
                            <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-3.5">
                                <div class="flex items-center gap-2 mb-2.5">
                                    <span class="w-7 h-7 rounded-xl bg-amber-100 text-amber-600 grid place-items-center shadow-sm"><i class="bi bi-star-fill text-[12px]"></i></span>
                                    <span class="text-[12px] font-bold uppercase tracking-wide text-foreground">{{ __('member.partials_affiliations_enhanced_skills_acquired') }}</span>
                                    <span class="ms-auto text-[11px] font-bold text-amber-600 bg-amber-100 min-w-[20px] text-center px-1.5 rounded-full" x-text="cur?.skills?.length || 0"></span>
                                    @if($isSelf ?? false)
                                        <button type="button" @click="openAddSkill()" class="m-press inline-flex items-center gap-1 text-[11px] font-bold text-amber-700 bg-amber-100 hover:bg-amber-200 rounded-full ps-1.5 pe-2 py-1 transition-colors"><i class="bi bi-plus-lg"></i>{{ __('Add') }}</button>
                                    @endif
                                </div>
                                <div x-show="cur?.skills?.length" class="flex flex-wrap gap-1.5">
                                    <template x-for="(s,i) in (cur?.skills||[])" :key="i">
                                        <a :href="s.url || null" :rel="s.url ? 'noopener' : null"
                                           class="inline-flex items-center gap-1.5 text-[12px] font-semibold px-2.5 py-1.5 rounded-xl bg-amber-50 text-amber-700 border border-amber-200/70 no-underline transition-colors"
                                           :class="s.url && 'm-press hover:bg-amber-100'">
                                            <i class="bi text-[11px]" :class="s.url ? 'bi-book-half' : 'bi-star-fill'"></i><span x-text="s.name"></span>
                                            <span class="text-[10px] font-bold bg-white/90 text-amber-800 px-1.5 py-px rounded-full" x-text="s.level"></span>
                                            <span x-show="s.duration" class="inline-flex items-center gap-1 text-[10px] font-medium text-amber-600/90 ps-0.5"><i class="bi bi-hourglass-split"></i><span x-text="s.duration"></span></span>
                                            <i x-show="s.url" class="bi bi-chevron-right text-[9px] text-amber-500/70"></i>
                                        </a>
                                    </template>
                                </div>
                                <p x-show="!cur?.skills?.length" class="text-[12px] text-muted-foreground">{{ __('member.no_data') }}</p>
                            </div>

                            {{-- Instructors --}}
                            <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-3.5">
                                <div class="flex items-center gap-2 mb-2.5">
                                    <span class="w-7 h-7 rounded-xl bg-green-100 text-green-600 grid place-items-center shadow-sm"><i class="bi bi-people-fill text-[12px]"></i></span>
                                    <span class="text-[12px] font-bold uppercase tracking-wide text-foreground">{{ __('member.partials_affiliations_enhanced_instructors') }}</span>
                                    <span class="ms-auto text-[11px] font-bold text-green-600 bg-green-100 min-w-[20px] text-center px-1.5 rounded-full" x-text="cur?.instructors?.length || 0"></span>
                                    @if($isSelf ?? false)
                                        <button type="button" @click="openAddInstructor()" class="m-press inline-flex items-center gap-1 text-[11px] font-bold text-green-700 bg-green-100 hover:bg-green-200 rounded-full ps-1.5 pe-2 py-1 transition-colors"><i class="bi bi-plus-lg"></i>{{ __('Add') }}</button>
                                    @endif
                                </div>
                                <div x-show="cur?.instructors?.length" class="flex flex-wrap gap-1.5">
                                    <template x-for="(ins,i) in (cur?.instructors||[])" :key="i">
                                        <a :href="ins.url || null"
                                           class="inline-flex items-center gap-1.5 text-[12px] font-semibold ps-1 pe-2.5 py-1 rounded-full bg-green-50 text-green-700 border border-green-200/70 no-underline transition-colors"
                                           :class="ins.url && 'm-press hover:bg-green-100'">
                                            <span class="rounded-full bg-white grid place-items-center overflow-hidden ring-1 ring-green-200/60" style="width:1.35rem;height:1.35rem;">
                                                <template x-if="ins.avatar"><img :src="ins.avatar" alt="" class="w-full h-full object-cover"></template>
                                                <template x-if="!ins.avatar"><i class="bi bi-person-fill text-[11px] text-gray-400"></i></template>
                                            </span>
                                            <span x-text="ins.name"></span>
                                            <i x-show="ins.url" class="bi bi-chevron-right text-[9px] text-green-500/70"></i>
                                        </a>
                                    </template>
                                </div>
                                <p x-show="!cur?.instructors?.length" class="text-[12px] text-muted-foreground">{{ __('member.no_data') }}</p>
                            </div>

                            {{-- Media --}}
                            <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-3.5">
                                <div class="flex items-center gap-2 mb-2.5">
                                    <span class="w-7 h-7 rounded-xl bg-sky-100 text-sky-600 grid place-items-center shadow-sm"><i class="bi bi-paperclip text-[12px]"></i></span>
                                    <span class="text-[12px] font-bold uppercase tracking-wide text-foreground">{{ __('member.partials_affiliations_enhanced_media_certificates') }}</span>
                                    <span class="ms-auto text-[11px] font-bold text-sky-600 bg-sky-100 min-w-[20px] text-center px-1.5 rounded-full" x-text="cur?.media?.length || 0"></span>
                                    @if($isSelf ?? false)
                                        <button type="button" @click="openAddMedia()" class="m-press inline-flex items-center gap-1 text-[11px] font-bold text-sky-700 bg-sky-100 hover:bg-sky-200 rounded-full ps-1.5 pe-2 py-1 transition-colors"><i class="bi bi-plus-lg"></i>{{ __('Add') }}</button>
                                    @endif
                                </div>
                                <div x-show="cur?.media?.length" class="flex flex-col gap-1.5">
                                    <template x-for="(m,i) in (cur?.media||[])" :key="i">
                                        <a :href="m.url" target="_blank" rel="noopener"
                                           class="inline-flex items-center gap-2 text-[12.5px] font-medium text-foreground bg-muted/40 border border-gray-100 rounded-xl px-3 py-2.5 no-underline m-press hover:bg-muted/70 transition-colors">
                                            <span class="w-7 h-7 rounded-lg bg-sky-100 text-sky-600 grid place-items-center flex-shrink-0"><i class="bi" :class="m.icon"></i></span>
                                            <span class="truncate" x-text="m.title"></span>
                                            <i class="bi bi-box-arrow-up-right text-muted-foreground text-[11px] ms-auto flex-shrink-0"></i>
                                        </a>
                                    </template>
                                </div>
                                <p x-show="!cur?.media?.length" class="text-[12px] text-muted-foreground">{{ __('member.no_data') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            @if($isSelf ?? false)
            {{-- ══ Add Skill sheet (mirrors the desktop Add Skill modal) ══ --}}
            <template x-teleport="body">
                <div x-show="skillOpen" x-cloak class="fixed inset-0 z-[80]" style="display:none;" @keydown.escape.window="skillOpen=false">
                    <div x-show="skillOpen" x-transition.opacity class="absolute inset-0 bg-black/50" @click="skillOpen=false"></div>
                    <div x-show="skillOpen"
                         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                         class="absolute inset-x-0 bottom-0 bg-background rounded-t-3xl shadow-2xl max-h-[92vh] flex flex-col">
                        <div class="flex-shrink-0 px-5 pt-3 pb-2 border-b border-gray-100">
                            <div class="w-10 h-1.5 bg-gray-200 rounded-full mx-auto mb-3"></div>
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-xl bg-amber-100 text-amber-600 grid place-items-center flex-shrink-0"><i class="bi bi-star-fill"></i></span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-bold text-foreground leading-tight">{{ __('member.partials_affiliations_enhanced_add_skill') }}</h3>
                                    <p class="text-[11px] text-muted-foreground truncate" x-text="cur?.club_name"></p>
                                </div>
                                <button type="button" @click="skillOpen=false" class="w-8 h-8 rounded-full grid place-items-center text-muted-foreground hover:bg-muted flex-shrink-0"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                            {{-- Activity combobox: club activities + directory + free text --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.partials_affiliations_enhanced_skill_name') }} <span class="text-red-500">*</span></label>
                                <div class="relative" @click.outside="acOpen=false" @keydown.escape.stop="acOpen=false">
                                    <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                                    <input type="text" x-model="skillForm.activityQuery" @focus="acOpen=true" @input="acOpen=true; skillForm.activityId=''"
                                           placeholder="{{ __('The discipline/class you trained') }}"
                                           class="w-full ps-9 pe-9 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <button type="button" @click="acOpen=!acOpen" class="absolute end-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-md grid place-items-center text-gray-400"><i class="bi bi-chevron-down text-xs transition-transform" :class="acOpen && 'rotate-180'"></i></button>
                                    <p x-show="skillForm.activityId" x-cloak class="mt-1.5 text-[11px] text-primary font-medium flex items-center gap-1"><i class="bi bi-patch-check-fill"></i>{{ __('Linked to this club\'s activity') }}</p>
                                    <div x-show="acOpen" x-cloak x-transition.opacity class="mt-1.5 max-h-56 overflow-y-auto bg-white border border-gray-100 rounded-xl shadow-sm divide-y divide-gray-50">
                                        <template x-for="grp in skillGroupedOptions" :key="grp.key">
                                            <div x-show="grp.items.length">
                                                <p class="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground" x-text="grp.label"></p>
                                                <template x-for="opt in grp.items" :key="grp.key+':'+opt.name">
                                                    <button type="button" @click="chooseActivity(opt)" class="w-full text-start px-3 py-2 text-sm flex items-center gap-2.5 hover:bg-muted/50 transition-colors">
                                                        <span class="w-6 h-6 rounded-md grid place-items-center flex-shrink-0" :class="opt.id ? 'bg-accent text-primary' : 'bg-muted text-muted-foreground'"><i class="bi text-[11px]" :class="opt.id ? 'bi-building' : 'bi-grid'"></i></span>
                                                        <span class="flex-1 truncate text-foreground" x-text="opt.name"></span>
                                                        <i class="bi bi-check2 text-primary" x-show="skillForm.activityQuery===opt.name"></i>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>
                                        <button type="button" x-show="skillForm.activityQuery.trim() && !skillExactMatch" @click="useTypedActivity()" class="w-full text-start px-3 py-2.5 text-sm flex items-center gap-2.5 bg-accent/20 hover:bg-accent/40 transition-colors">
                                            <span class="w-6 h-6 rounded-md bg-primary text-white grid place-items-center flex-shrink-0"><i class="bi bi-plus-lg text-[11px]"></i></span>
                                            <span class="min-w-0 flex-1"><span class="block truncate text-foreground font-medium" x-text="skillForm.activityQuery.trim()"></span><span class="block text-[10px] text-muted-foreground">{{ __('Add as a custom activity') }}</span></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            {{-- Proficiency --}}
                            <div>
                                <span class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('member.partials_affiliations_enhanced_proficiency_level') }} <span class="text-red-500">*</span></span>
                                <div class="grid grid-cols-4 gap-2">
                                    <template x-for="lvl in skillLevels" :key="lvl.value">
                                        <button type="button" @click="skillForm.level=lvl.value" class="px-1 py-2.5 rounded-xl border text-center transition-all" :class="skillForm.level===lvl.value ? 'border-primary bg-primary/5 shadow-sm' : 'border-gray-200 hover:bg-muted/40'">
                                            <span class="flex items-center justify-center gap-0.5 mb-1"><template x-for="n in 4" :key="n"><i class="bi text-[8px]" :class="n<=lvl.pips ? (skillForm.level===lvl.value ? 'bi-circle-fill text-primary' : 'bi-circle-fill text-gray-300') : 'bi-circle text-gray-200'"></i></template></span>
                                            <span class="block text-[10.5px] font-semibold leading-tight" :class="skillForm.level===lvl.value ? 'text-primary' : 'text-gray-600'" x-text="lvl.label"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            {{-- Instructor — selection cards (only when the club has any) --}}
                            <div x-show="skillInstructors.length" x-cloak>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('Instructor') }} <span class="text-xs font-normal text-muted-foreground">({{ __('optional') }})</span></label>
                                <div class="space-y-1.5 max-h-44 overflow-y-auto">
                                    <button type="button" @click="skillForm.instructorId=''" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl border text-sm transition-colors" :class="!skillForm.instructorId ? 'border-primary bg-primary/5' : 'border-gray-200 hover:bg-muted/40'">
                                        <span class="w-6 h-6 rounded-full bg-muted grid place-items-center flex-shrink-0"><i class="bi bi-slash-circle text-xs text-muted-foreground"></i></span>
                                        <span class="flex-1 text-start text-muted-foreground">{{ __('No instructor') }}</span>
                                        <span class="w-4 h-4 rounded-full border flex-shrink-0 grid place-items-center" :class="!skillForm.instructorId ? 'border-primary bg-primary' : 'border-gray-300'"><i class="bi bi-check text-[10px] text-white" x-show="!skillForm.instructorId"></i></span>
                                    </button>
                                    <template x-for="ins in skillInstructors" :key="ins.id">
                                        <button type="button" @click="skillForm.instructorId=ins.id" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl border text-sm transition-colors" :class="skillForm.instructorId===ins.id ? 'border-primary bg-primary/5' : 'border-gray-200 hover:bg-muted/40'">
                                            <span class="w-6 h-6 rounded-full bg-accent text-primary grid place-items-center flex-shrink-0 text-[11px] font-bold" x-text="ins.name.charAt(0).toUpperCase()"></span>
                                            <span class="flex-1 text-start truncate text-foreground" x-text="ins.name"></span>
                                            <span class="w-4 h-4 rounded-full border flex-shrink-0 grid place-items-center" :class="skillForm.instructorId===ins.id ? 'border-primary bg-primary' : 'border-gray-300'"><i class="bi bi-check text-[10px] text-white" x-show="skillForm.instructorId===ins.id"></i></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            {{-- When --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.partials_affiliations_enhanced_start_date') }} <span class="text-xs font-normal text-muted-foreground">({{ __('optional') }})</span></label>
                                <x-date-picker model="skillForm.startDate" min-expr="skillBounds.start_date || null" max-expr="skillBounds.max_start || null" placeholder="{{ __('member.partials_affiliations_enhanced_start_date') }}" />
                            </div>
                            <label class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl border cursor-pointer select-none transition-colors" :class="skillForm.present ? 'border-primary bg-primary/5' : 'border-gray-200'">
                                <input type="checkbox" x-model="skillForm.present" @change="if(skillForm.present) skillForm.endDate=''" class="w-[18px] h-[18px] rounded text-primary border-gray-300 focus:ring-primary">
                                <span class="min-w-0"><span class="block text-sm font-medium text-foreground">{{ __('I still practice this skill') }}</span><span class="block text-[11px] text-muted-foreground">{{ __('Ongoing — no end date') }}</span></span>
                            </label>
                            <div x-show="!skillForm.present" x-cloak>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.partials_affiliations_enhanced_end_date') }} <span class="text-xs font-normal text-muted-foreground">({{ __('optional') }})</span></label>
                                <x-date-picker model="skillForm.endDate" name-expr="skillForm.present ? '' : 'end_date'" min-expr="skillForm.startDate || skillBounds.start_date || null" max-expr="skillBounds.end_date || null" placeholder="{{ __('member.partials_affiliations_enhanced_end_date') }}" />
                            </div>
                            {{-- Notes --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.partials_affiliations_enhanced_notes') }}</label>
                                <textarea x-model="skillForm.notes" rows="2" maxlength="500" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent resize-none" placeholder="{{ __('member.partials_affiliations_enhanced_notes_placeholder') }}"></textarea>
                            </div>
                        </div>
                        <div class="flex-shrink-0 border-t border-gray-100 px-5 pt-3 flex gap-2" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                            <button type="button" @click="submitSkill()" :disabled="skillSaving || !skillForm.activityQuery.trim() || !skillForm.level" class="m-press flex-1 py-3 rounded-xl bg-primary text-white text-sm font-semibold active:bg-primary/90 disabled:opacity-60 flex items-center justify-center gap-2">
                                <i class="bi bi-arrow-repeat animate-spin" x-show="skillSaving"></i><span>{{ __('member.partials_affiliations_enhanced_add_skill') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            {{-- ══ Add Instructor sheet ══ --}}
            <template x-teleport="body">
                <div x-show="insOpen" x-cloak class="fixed inset-0 z-[80]" style="display:none;" @keydown.escape.window="insOpen=false">
                    <div x-show="insOpen" x-transition.opacity class="absolute inset-0 bg-black/50" @click="insOpen=false"></div>
                    <div x-show="insOpen"
                         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                         class="absolute inset-x-0 bottom-0 bg-background rounded-t-3xl shadow-2xl max-h-[92vh] flex flex-col">
                        <div class="flex-shrink-0 px-5 pt-3 pb-2 border-b border-gray-100">
                            <div class="w-10 h-1.5 bg-gray-200 rounded-full mx-auto mb-3"></div>
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-xl bg-green-100 text-green-600 grid place-items-center flex-shrink-0"><i class="bi bi-person-plus"></i></span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-bold text-foreground leading-tight">{{ __('Add Instructor') }}</h3>
                                    <p class="text-[11px] text-muted-foreground truncate" x-text="cur?.club_name"></p>
                                </div>
                                <button type="button" @click="insOpen=false" class="w-8 h-8 rounded-full grid place-items-center text-muted-foreground hover:bg-muted flex-shrink-0"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                            <div class="grid grid-cols-2 gap-1 p-1 bg-muted rounded-2xl text-sm font-semibold">
                                <button type="button" @click="insMode='member'" :class="insMode==='member' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="m-press rounded-xl py-2 transition-colors"><i class="bi bi-search mr-1"></i>{{ __('Search member') }}</button>
                                <button type="button" @click="insMode='name'" :class="insMode==='name' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="m-press rounded-xl py-2 transition-colors"><i class="bi bi-pencil mr-1"></i>{{ __('Enter name') }}</button>
                            </div>
                            {{-- Member search --}}
                            <div x-show="insMode==='member'" x-cloak>
                                <div class="relative">
                                    <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                                    <input type="text" x-model="insQuery" @input.debounce.300ms="searchInstructors()" placeholder="{{ __('Search by name…') }}" class="w-full ps-9 pe-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div class="mt-2 space-y-1.5" x-show="insQuery.length>=2" x-cloak>
                                    <p x-show="insSearching" class="px-3 py-4 text-xs text-muted-foreground text-center"><i class="bi bi-arrow-repeat animate-spin me-1"></i>{{ __('shared.loading') }}</p>
                                    <template x-for="r in insResults" :key="r.uuid">
                                        <button type="button" @click="addInstructorMember(r)" :disabled="insSaving" class="w-full text-start px-3 py-2 flex items-center gap-2.5 rounded-xl border border-gray-100 hover:bg-muted/50 transition-colors disabled:opacity-50">
                                            <span class="w-8 h-8 rounded-full bg-green-600 text-white grid place-items-center overflow-hidden flex-shrink-0 text-xs font-bold">
                                                <template x-if="r.avatar"><img :src="r.avatar" alt="" class="w-full h-full object-cover"></template>
                                                <template x-if="!r.avatar"><span x-text="r.name.charAt(0).toUpperCase()"></span></template>
                                            </span>
                                            <span class="flex-1 truncate text-sm text-foreground" x-text="r.name"></span>
                                            <i class="bi bi-plus-circle text-green-600"></i>
                                        </button>
                                    </template>
                                    <p x-show="!insSearching && !insResults.length" class="px-3 py-4 text-xs text-muted-foreground text-center">{{ __('No matching members') }}</p>
                                </div>
                            </div>
                            {{-- Free-text name --}}
                            <div x-show="insMode==='name'" x-cloak>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Instructor name') }}</label>
                                <input type="text" x-model="insName" maxlength="120" @keydown.enter.prevent="addInstructorName()" placeholder="{{ __('Instructor name') }}" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                        <div class="flex-shrink-0 border-t border-gray-100 px-5 pt-3 flex gap-2" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));" x-show="insMode==='name'">
                            <button type="button" @click="addInstructorName()" :disabled="insSaving || !insName.trim()" class="m-press flex-1 py-3 rounded-xl bg-green-600 text-white text-sm font-semibold active:bg-green-700 disabled:opacity-60 flex items-center justify-center gap-2">
                                <i class="bi bi-arrow-repeat animate-spin" x-show="insSaving"></i><span>{{ __('Add') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            {{-- ══ Add Media / Certificate sheet ══ --}}
            <template x-teleport="body">
                <div x-show="mediaOpen" x-cloak class="fixed inset-0 z-[80]" style="display:none;" @keydown.escape.window="mediaOpen=false">
                    <div x-show="mediaOpen" x-transition.opacity class="absolute inset-0 bg-black/50" @click="mediaOpen=false"></div>
                    <div x-show="mediaOpen"
                         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                         class="absolute inset-x-0 bottom-0 bg-background rounded-t-3xl shadow-2xl max-h-[92vh] flex flex-col">
                        <div class="flex-shrink-0 px-5 pt-3 pb-2 border-b border-gray-100">
                            <div class="w-10 h-1.5 bg-gray-200 rounded-full mx-auto mb-3"></div>
                            <div class="flex items-center gap-3">
                                <span class="w-9 h-9 rounded-xl bg-sky-100 text-sky-600 grid place-items-center flex-shrink-0"><i class="bi bi-paperclip"></i></span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-bold text-foreground leading-tight">{{ __('member.partials_affiliations_enhanced_add_media_certificate') }}</h3>
                                    <p class="text-[11px] text-muted-foreground truncate" x-text="cur?.club_name"></p>
                                </div>
                                <button type="button" @click="mediaOpen=false" class="w-8 h-8 rounded-full grid place-items-center text-muted-foreground hover:bg-muted flex-shrink-0"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                            {{-- Type --}}
                            <div>
                                <span class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('member.partials_affiliations_enhanced_type') }} <span class="text-red-500">*</span></span>
                                <div class="grid grid-cols-4 gap-2">
                                    <template x-for="t in mediaTypes" :key="t.value">
                                        <button type="button" @click="mediaType=t.value" class="px-1 py-2.5 rounded-xl border text-center transition-all" :class="mediaType===t.value ? 'border-primary bg-primary/5 shadow-sm' : 'border-gray-200 hover:bg-muted/40'">
                                            <i class="bi text-lg block mb-0.5" :class="[t.icon, mediaType===t.value ? 'text-primary' : 'text-gray-400']"></i>
                                            <span class="block text-[10.5px] font-semibold leading-tight" :class="mediaType===t.value ? 'text-primary' : 'text-gray-600'" x-text="t.label"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            {{-- Title --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.partials_affiliations_enhanced_title') }} <span class="text-red-500">*</span></label>
                                <input type="text" x-model="mediaTitle" maxlength="255" placeholder="{{ __('member.partials_affiliations_enhanced_title_placeholder') }}" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                            {{-- Image types → upload + crop --}}
                            <div x-show="mediaIsImage" x-cloak>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('Image') }} <span class="text-red-500">*</span></label>
                                <button type="button" x-show="!mediaHasImage" @click="pickMediaImage()" class="group w-full rounded-2xl border-2 border-dashed border-gray-200 hover:border-primary hover:bg-primary/5 transition-all px-4 py-7 flex flex-col items-center justify-center gap-2 text-center">
                                    <span class="w-12 h-12 rounded-full bg-accent/70 grid place-items-center"><i class="bi bi-cloud-arrow-up text-2xl text-primary"></i></span>
                                    <span class="text-sm font-semibold text-foreground">{{ __('Click to upload & crop') }}</span>
                                    <span class="text-[11px] text-muted-foreground">{{ __('JPG, PNG, GIF or WebP — auto-optimized on save') }}</span>
                                </button>
                                <div x-show="mediaHasImage" x-cloak class="relative rounded-2xl overflow-hidden border border-gray-100 bg-muted/40">
                                    <img :src="mediaImgSrc" alt="" class="w-full h-40 object-cover">
                                    <div class="absolute inset-x-0 bottom-0 p-2 flex items-center justify-center gap-2 bg-gradient-to-t from-black/50 to-transparent">
                                        <button type="button" @click="pickMediaImage()" class="px-3 py-1.5 rounded-lg bg-white/95 text-foreground text-xs font-semibold shadow-sm flex items-center gap-1.5"><i class="bi bi-crop"></i>{{ __('Change') }}</button>
                                        <button type="button" @click="removeMediaImage()" class="px-3 py-1.5 rounded-lg bg-white/95 text-red-600 text-xs font-semibold shadow-sm flex items-center gap-1.5"><i class="bi bi-trash"></i>{{ __('Remove') }}</button>
                                    </div>
                                </div>
                                <div class="tk-affmedia-cropper-host">
                                    <x-takeone-cropper
                                        id="affMediaCropperM" mode="form" :inline="true"
                                        inputName="cropped_media"
                                        :width="1400" :height="1000" shape="rectangle" :canvasHeight="300"
                                        folder="media" filename="media"
                                        :showControls="false" :showCancel="false"
                                        saveText="{{ __('Crop') }}"
                                        sheetMaxWidth="100%"
                                        sheetClass="rounded-t-3xl shadow-2xl bg-background" />
                                </div>
                                <style>.tk-affmedia-cropper-host #cropperInline_affMediaCropperM { display: none !important; }</style>
                            </div>
                            {{-- Video / document → external link --}}
                            <div x-show="!mediaIsImage" x-cloak>
                                <label class="block text-sm font-medium text-gray-700 mb-1" x-text="mediaType==='video' ? '{{ __('Video link') }}' : '{{ __('Document link') }}'"></label>
                                <div class="relative">
                                    <i class="bi bi-link-45deg absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                                    <input type="url" x-model="mediaUrl" maxlength="500" placeholder="https://…" class="w-full ps-9 pe-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                            </div>
                            {{-- Description --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.partials_affiliations_enhanced_description') }}</label>
                                <textarea x-model="mediaDesc" rows="2" maxlength="500" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent resize-none" placeholder="{{ __('member.partials_affiliations_enhanced_description_placeholder2') }}"></textarea>
                            </div>
                        </div>
                        <div class="flex-shrink-0 border-t border-gray-100 px-5 pt-3 flex gap-2" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                            <button type="button" @click="submitMedia()" :disabled="mediaSaving || !mediaTitle.trim()" class="m-press flex-1 py-3 rounded-xl bg-primary text-white text-sm font-semibold active:bg-primary/90 disabled:opacity-60 flex items-center justify-center gap-2">
                                <i class="bi bi-arrow-repeat animate-spin" x-show="mediaSaving"></i><span>{{ __('member.partials_affiliations_enhanced_add_media') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
            @endif
        </div>

        {{-- ===== Certifications — member-owned, self-managed ===== --}}
        @php
            $certsJs = $certifications->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'issuer' => $c->issuer,
                'issue_date' => optional($c->issue_date)->format('Y-m-d'),
                'issue_label' => optional($c->issue_date)->format('M Y'),
                'expiry_date' => optional($c->expiry_date)->format('Y-m-d'),
                'expiry_label' => optional($c->expiry_date)->format('M Y'),
                'expired' => $c->isExpired(),
                'credential_id' => $c->credential_id,
                'credential_url' => $c->credential_url,
                'image' => $c->image_path ? asset('storage/'.$c->image_path) : null,
                'notes' => $c->notes,
            ])->values();
        @endphp
        <div x-show="tab==='certifications'" x-transition.opacity x-cloak class="space-y-3"
             x-data="certManager({
                storeUrl: '{{ route('member.store-certification', $user->id) }}',
                updateBase: '{{ url('/member/certification') }}',
                csrf: '{{ csrf_token() }}',
                canEdit: @js((bool) ($canEditBasic ?? false)),
                items: @js($certsJs),
                i18n: {
                    saved: @js(__('member.cert_name')),
                    deleteConfirm: @js(__('member.cert_delete_confirm')),
                    networkError: @js(__('Something went wrong. Please try again.')),
                    invalidImage: @js(__('Please choose an image file.')),
                }
             })">

            {{-- Section header — always outside the card (matches Work history) --}}
            <div class="flex items-center justify-between gap-2">
                <h3 class="font-bold text-foreground flex items-center gap-2 text-[15px]"><i class="bi bi-patch-check text-primary"></i>{{ __('member.certifications') }}</h3>
                @if($canEditBasic ?? false)
                    <button type="button" @click="openAdd()" aria-label="{{ __('member.add_certification') }}"
                            class="m-press inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary text-white text-xs font-bold shadow-sm shadow-primary/25 hover:bg-primary/90 transition-colors flex-shrink-0">
                        <i class="bi bi-plus-lg"></i>{{ __('member.add_certification') }}
                    </button>
                @endif
            </div>

            {{-- Empty state --}}
            <template x-if="!items.length">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <p class="text-sm text-muted-foreground text-center py-4">{{ __('member.no_certifications') }}</p>
                </div>
            </template>

            {{-- List --}}
            <template x-for="c in items" :key="c.id">
                <div class="group relative bg-white rounded-2xl shadow-sm border border-gray-100 p-4 overflow-hidden">
                    <span class="absolute inset-y-0 left-0 rtl:left-auto rtl:right-0 w-1 bg-primary/70"></span>
                    <div class="flex items-start gap-3">
                        <span class="w-12 h-12 rounded-xl bg-accent grid place-items-center overflow-hidden flex-shrink-0 ring-1 ring-primary/10">
                            <template x-if="c.image"><img :src="c.image" alt="" class="w-12 h-12 object-cover"></template>
                            <template x-if="!c.image"><i class="bi bi-patch-check-fill text-lg text-primary"></i></template>
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-bold text-foreground text-[15px] leading-snug" x-text="c.title"></p>
                                <template x-if="c.expired">
                                    <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-100 text-red-600">{{ __('member.cert_expired') }}</span>
                                </template>
                            </div>
                            <p class="text-[12px] text-muted-foreground mt-0.5" x-show="c.issuer" x-text="c.issuer"></p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                                <span class="inline-flex items-center gap-1.5" x-show="c.issue_label"><i class="bi bi-calendar3 text-muted-foreground/70"></i><span x-text="c.issue_label"></span></span>
                                <span class="inline-flex items-center gap-1.5" x-show="c.expiry_label"><i class="bi bi-hourglass-split text-muted-foreground/70"></i><span x-text="'{{ __('member.cert_expiry_date') }}: ' + c.expiry_label"></span></span>
                                <span class="inline-flex items-center gap-1.5" x-show="c.credential_id"><i class="bi bi-hash text-muted-foreground/70"></i><span x-text="c.credential_id"></span></span>
                            </div>
                            <p class="text-[11px] text-foreground/70 mt-2" x-show="c.notes" x-text="c.notes"></p>
                            <div class="mt-2 flex items-center gap-3">
                                <template x-if="c.credential_url">
                                    <a :href="c.credential_url" target="_blank" rel="noopener nofollow" class="inline-flex items-center gap-1 text-[11px] font-medium text-primary"><i class="bi bi-box-arrow-up-right"></i>{{ __('member.verify_credential') }}</a>
                                </template>
                                <template x-if="canEdit">
                                    <div class="flex items-center gap-3 ms-auto">
                                        <button type="button" @click="openEdit(c)" class="text-[11px] font-medium text-muted-foreground hover:text-primary inline-flex items-center gap-1"><i class="bi bi-pencil"></i>{{ __('Edit') }}</button>
                                        <button type="button" @click="remove(c)" class="text-[11px] font-medium text-muted-foreground hover:text-red-600 inline-flex items-center gap-1"><i class="bi bi-trash"></i>{{ __('Delete') }}</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Add / edit bottom sheet (teleported so nothing clips it) --}}
            <template x-teleport="body">
                <div x-show="open" x-cloak @keydown.escape.window="close()" class="fixed inset-0 z-[70]">
                    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" @click="close()"></div>
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                         class="absolute inset-x-0 bottom-0 bg-background rounded-t-3xl shadow-2xl max-h-[92vh] flex flex-col">
                        <div class="flex-shrink-0 px-5 pt-3 pb-2 border-b border-gray-100">
                            <div class="w-10 h-1.5 bg-gray-200 rounded-full mx-auto mb-3"></div>
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-foreground" x-text="editing ? '{{ __('member.edit_certification') }}' : '{{ __('member.add_certification') }}'"></h3>
                                <button type="button" @click="close()" class="w-8 h-8 rounded-full grid place-items-center text-muted-foreground hover:bg-muted"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.cert_name') }} <span class="text-red-500">*</span></label>
                                <input type="text" x-model="form.title" maxlength="150" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="{{ __('member.cert_name') }}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.cert_issuer') }}</label>
                                <input type="text" x-model="form.issuer" maxlength="150" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="{{ __('member.cert_issuer') }}">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.cert_issue_date') }}</label>
                                    <x-date-picker model="form.issue_date" placeholder="{{ __('member.cert_issue_date') }}" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.cert_expiry_date') }}</label>
                                    <x-date-picker model="form.expiry_date" min-expr="form.issue_date || null" placeholder="{{ __('member.cert_no_expiry') }}" />
                                </div>
                            </div>
                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.cert_credential_id') }}</label>
                                    <input type="text" x-model="form.credential_id" maxlength="120" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="{{ __('member.cert_credential_id') }}">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.cert_credential_url') }}</label>
                                    <input type="url" inputmode="url" x-model="form.credential_url" maxlength="300" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="https://">
                                </div>
                            </div>
                            {{-- Certificate photo (optional) --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.cert_photo') }}</label>
                                <div class="flex items-center gap-3">
                                    <span class="w-16 h-16 rounded-xl bg-muted grid place-items-center overflow-hidden flex-shrink-0 ring-1 ring-gray-100">
                                        <template x-if="form.imagePreview"><img :src="form.imagePreview" alt="" class="w-16 h-16 object-cover"></template>
                                        <template x-if="!form.imagePreview"><i class="bi bi-image text-muted-foreground"></i></template>
                                    </span>
                                    <label class="m-press inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-primary text-primary text-sm font-medium cursor-pointer hover:bg-primary/5">
                                        <i class="bi bi-camera"></i><span x-text="form.imagePreview ? '{{ __('Change') }}' : '{{ __('Add photo') }}'"></span>
                                        <input type="file" accept="image/*" class="hidden" @change="pickImage($event)">
                                    </label>
                                    <button type="button" x-show="form.imagePreview" @click="form.image=null; form.imagePreview=null" class="text-xs text-red-500 hover:underline">{{ __('Remove') }}</button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.work_description') }}</label>
                                <textarea x-model="form.notes" rows="3" maxlength="1000" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>
                        </div>
                        <div class="flex-shrink-0 border-t border-gray-100 px-5 pt-3 flex gap-2" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                            <button type="button" @click="submit()" :disabled="submitting || !form.title.trim()" class="m-press flex-1 py-3 rounded-xl bg-primary text-white text-sm font-semibold active:bg-primary/90 disabled:opacity-60 flex items-center justify-center gap-2">
                                <i class="bi bi-arrow-repeat animate-spin" x-show="submitting"></i>
                                <span x-text="editing ? '{{ __('Save') }}' : '{{ __('member.add_certification') }}'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- ===== Worked — member-owned work / coaching history ===== --}}
        @php
            $realWork = $workHistory->map(fn ($w) => [
                'id' => $w->id,
                'title' => $w->title,
                'organization' => $w->organization,
                'employment_type' => $w->employment_type,
                'location' => $w->location,
                'start_date' => optional($w->start_date)->format('Y-m-d'),
                'end_date' => optional($w->end_date)->format('Y-m-d'),
                'start_label' => optional($w->start_date)->format('M Y'),
                'end_label' => $w->end_date ? $w->end_date->format('M Y') : null,
                'current' => $w->isCurrent(),
                'description' => $w->description,
                'derived' => false,
                'logo' => null,
                'skills' => [],
                'club_url' => null,
                // Verification (self-entered rows only; derived platform roles are inherently real)
                'verification' => $w->verification_status,
                'can_request' => (bool) $w->attestingTenant(),
                'request_url' => route('member.work.request-verification', [$user->id, $w->uuid]),
                '_sort' => optional($w->start_date)->timestamp ?? 0,
            ]);

            // Skill name → encyclopedia (activity directory) url, for deep-linking each taught skill.
            $workCatalogByName = \App\Models\ActivityCatalog::where('is_active', true)
                ->get(['uuid', 'name'])
                ->keyBy(fn ($a) => mb_strtolower(trim($a->name)));

            // Platform trainer/staff roles are real work history — surface them here,
            // live and read-only (managed from the club, not this list).
            $derivedWork = \App\Models\ClubInstructor::where('user_id', $user->id)
                ->with(['tenant:id,club_name,logo,slug,country', 'activities:id,name'])
                ->get()
                ->map(function ($ci) use ($workCatalogByName) {
                    $start = $ci->created_at;
                    $active = (bool) $ci->is_active;
                    $end = $active ? null : $ci->updated_at;
                    $club = $ci->tenant;
                    $clubUrl = ($club && $club->slug && $club->country)
                        ? route('clubs.show', ['country' => strtolower($club->country), 'slug' => $club->slug])
                        : null;

                    return [
                        'id' => 'trainer-'.$ci->id,
                        'title' => $ci->role ?: ucfirst($ci->staff_type ?? 'instructor'),
                        'organization' => optional($club)->club_name,
                        'employment_type' => $ci->compensation_type ? ucfirst($ci->compensation_type) : null,
                        'location' => null,
                        'start_date' => optional($start)->format('Y-m-d'),
                        'end_date' => optional($end)->format('Y-m-d'),
                        'start_label' => optional($start)->format('M Y'),
                        'end_label' => $end ? $end->format('M Y') : null,
                        'current' => $active,
                        'description' => null,
                        'derived' => true,
                        'logo' => optional($club)->logo ? asset('storage/'.$club->logo) : null,
                        'skills' => $ci->activities->map(fn ($a) => [
                            'name' => $a->name,
                            'url' => ($u = optional($workCatalogByName->get(mb_strtolower(trim((string) $a->name))))->uuid)
                                ? route('activity.show', $u) : null,
                        ])->values()->all(),
                        'club_url' => $clubUrl,
                        'verification' => 'verified',   // platform roles are inherently real
                        'can_request' => false,
                        'request_url' => null,
                        '_sort' => optional($start)->timestamp ?? 0,
                    ];
                });

            $workJs = $derivedWork->concat($realWork)
                ->sortBy([['current', 'desc'], ['_sort', 'desc']])
                ->map(fn ($w) => collect($w)->except('_sort')->all())
                ->values();
            $employmentTypes = ['Full-time','Part-time','Contract','Freelance','Volunteer','Internship'];
        @endphp
        <div x-show="tab==='worked'" x-transition.opacity x-cloak class="space-y-3"
             x-data="workManager({
                storeUrl: '{{ route('member.store-work', $user->id) }}',
                updateBase: '{{ url('/member/work-history') }}',
                csrf: '{{ csrf_token() }}',
                canEdit: @js((bool) ($canEditBasic ?? false)),
                items: @js($workJs),
                i18n: {
                    deleteConfirm: @js(__('member.work_delete_confirm')),
                    networkError: @js(__('Something went wrong. Please try again.')),
                    present: @js(__('member.work_present')),
                }
             })">

            {{-- Section header — always outside the card, so the empty state keeps the same header as the filled one --}}
            <div class="flex items-center justify-between gap-2">
                <h3 class="font-bold text-foreground flex items-center gap-2 text-[15px]"><i class="bi bi-briefcase text-primary"></i>{{ __('member.work_history') }}</h3>
                @if($canEditBasic ?? false)
                    <button type="button" @click="openAdd()" aria-label="{{ __('member.add_work') }}"
                            class="m-press inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary text-white text-xs font-bold shadow-sm shadow-primary/25 hover:bg-primary/90 transition-colors flex-shrink-0">
                        <i class="bi bi-plus-lg"></i>{{ __('member.add_work') }}
                    </button>
                @endif
            </div>

            {{-- Empty state --}}
            <template x-if="!items.length">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <p class="text-sm text-muted-foreground text-center py-4">{{ __('member.no_work') }}</p>
                </div>
            </template>

            {{-- Timeline list --}}
            <template x-for="w in items" :key="w.id">
                <div class="group relative bg-white rounded-2xl shadow-sm border border-gray-100 p-4 overflow-hidden transition-shadow"
                     :class="w.club_url && 'cursor-pointer hover:shadow-md m-press'"
                     @click="w.club_url && (window.location.href = w.club_url)">
                    <span class="absolute inset-y-0 left-0 rtl:left-auto rtl:right-0 w-1" :class="w.current ? 'bg-green-400/80' : 'bg-gray-300'"></span>
                    <div class="flex items-start gap-3">
                        <span class="w-12 h-12 rounded-xl bg-accent grid place-items-center text-primary flex-shrink-0 ring-1 ring-primary/10 overflow-hidden">
                            <template x-if="w.logo"><img :src="w.logo" alt="" class="w-full h-full object-cover"></template>
                            <template x-if="!w.logo"><i class="bi bi-briefcase-fill text-lg"></i></template>
                        </span>
                        <div class="min-w-0 flex-1">
                            {{-- Title + organization, status badge to the right --}}
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-bold text-foreground text-[15px] leading-snug truncate" x-text="w.title"></p>
                                    <p class="text-[12px] font-medium text-foreground/60 truncate mt-0.5" x-text="w.organization"></p>
                                </div>
                                <template x-if="w.current">
                                    <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700"><span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>{{ __('member.work_current') }}</span>
                                </template>
                            </div>
                            {{-- Compact inline facts — one line, icon + text (no pills, so the card stays short) --}}
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-muted-foreground">
                                <span class="inline-flex items-center gap-1"><i class="bi bi-calendar-range text-primary/50"></i><span x-text="w.start_label + ' – ' + (w.end_label || i18n.present)"></span></span>
                                <span x-show="w.employment_type" class="inline-flex items-center gap-1"><i class="bi bi-cash-coin text-primary/50"></i><span x-text="w.employment_type"></span></span>
                                <span x-show="w.location" class="inline-flex items-center gap-1 min-w-0"><i class="bi bi-geo-alt text-primary/50 flex-shrink-0"></i><span class="truncate" x-text="w.location"></span></span>
                                <template x-for="(sk, si) in (w.skills || [])" :key="si">
                                    <span class="inline-flex items-center gap-1 text-primary font-semibold" :class="sk.url && 'cursor-pointer hover:underline'"
                                          @click.stop="sk.url && (window.location.href = sk.url)"><i class="bi bi-mortarboard-fill text-primary/60"></i><span x-text="sk.name"></span></span>
                                </template>
                                <template x-if="w.derived">
                                    <i class="bi bi-shield-fill-check text-primary/70" title="{{ __('member.work_platform_role_note') }}"></i>
                                </template>
                                {{-- Verification state / action for self-entered rows --}}
                                <template x-if="!w.derived && w.verification === 'verified'">
                                    <span class="inline-flex items-center gap-1 font-semibold text-green-600"><i class="bi bi-patch-check-fill"></i>{{ __('member.verified') }}</span>
                                </template>
                                <template x-if="!w.derived && w.verification === 'pending'">
                                    <span class="inline-flex items-center gap-1 font-medium"><i class="bi bi-hourglass-split text-primary/60"></i>{{ __('member.pending') }}</span>
                                </template>
                                <template x-if="!w.derived && (w.verification === 'self_reported' || w.verification === 'rejected') && w.can_request">
                                    <button type="button" @click="requestVerify(w)" class="inline-flex items-center gap-1 font-bold text-primary"><i class="bi bi-patch-check"></i>{{ __('Request verification') }}</button>
                                </template>
                                <template x-if="!w.derived && (w.verification === 'self_reported' || w.verification === 'rejected') && !w.can_request">
                                    <button type="button" @click="shareForVouch('{{ route('people.show', $user->uuid) }}')" class="inline-flex items-center gap-1 font-bold text-primary" title="{{ __('member.get_vouched_hint') }}"><i class="bi bi-people"></i>{{ __('member.get_vouched') }}</button>
                                </template>
                            </div>
                            <p class="text-[11px] text-foreground/70 mt-2 whitespace-pre-line" x-show="w.description" x-text="w.description"></p>
                            <template x-if="canEdit && !w.derived">
                                <div class="mt-2 flex items-center gap-3">
                                    <button type="button" @click="openEdit(w)" class="text-[11px] font-medium text-muted-foreground hover:text-primary inline-flex items-center gap-1"><i class="bi bi-pencil"></i>{{ __('Edit') }}</button>
                                    <button type="button" @click="remove(w)" class="text-[11px] font-medium text-muted-foreground hover:text-red-600 inline-flex items-center gap-1"><i class="bi bi-trash"></i>{{ __('Delete') }}</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Add / edit bottom sheet --}}
            <template x-teleport="body">
                <div x-show="open" x-cloak @keydown.escape.window="close()" class="fixed inset-0 z-[70]">
                    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" @click="close()"></div>
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                         class="absolute inset-x-0 bottom-0 bg-background rounded-t-3xl shadow-2xl max-h-[92vh] flex flex-col">
                        <div class="flex-shrink-0 px-5 pt-3 pb-2 border-b border-gray-100">
                            <div class="w-10 h-1.5 bg-gray-200 rounded-full mx-auto mb-3"></div>
                            <div class="flex items-center justify-between">
                                <h3 class="font-bold text-foreground" x-text="editing ? '{{ __('member.edit_work') }}' : '{{ __('member.add_work') }}'"></h3>
                                <button type="button" @click="close()" class="w-8 h-8 rounded-full grid place-items-center text-muted-foreground hover:bg-muted"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.work_role') }} <span class="text-red-500">*</span></label>
                                <input type="text" x-model="form.title" maxlength="150" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="{{ __('member.work_role') }}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.work_organization') }} <span class="text-red-500">*</span></label>
                                <input type="text" x-model="form.organization" maxlength="150" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="{{ __('member.work_organization') }}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('member.work_employment_type') }}</label>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($employmentTypes as $et)
                                        <button type="button" @click="form.employment_type = (form.employment_type === '{{ $et }}' ? '' : '{{ $et }}')"
                                                class="m-press px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors"
                                                :class="form.employment_type === '{{ $et }}' ? 'bg-primary text-white border-primary' : 'bg-white text-muted-foreground border-gray-200 hover:border-primary/40'">{{ $et }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.work_start_date') }} <span class="text-red-500">*</span></label>
                                    <x-date-picker model="form.start_date" max-expr="form.end_date || null" placeholder="{{ __('member.work_start_date') }}" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.work_end_date') }}</label>
                                    <x-date-picker model="form.end_date" min-expr="form.start_date || null" placeholder="{{ __('member.work_present') }}" />
                                </div>
                            </div>
                            <label class="flex items-center gap-2.5 cursor-pointer select-none">
                                <input type="checkbox" x-model="form.current" @change="if(form.current) form.end_date=''" class="w-[18px] h-[18px] rounded text-primary border-gray-300 focus:ring-primary">
                                <span class="text-sm text-gray-700">{{ __('member.work_current') }}</span>
                            </label>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.work_location') }}</label>
                                <input type="text" x-model="form.location" maxlength="150" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="{{ __('member.work_location') }}">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('member.work_description') }}</label>
                                <textarea x-model="form.description" rows="3" maxlength="2000" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>
                        </div>
                        <div class="flex-shrink-0 border-t border-gray-100 px-5 pt-3 flex gap-2" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                            <button type="button" @click="submit()" :disabled="submitting || !form.title.trim() || !form.organization.trim() || !form.start_date" class="m-press flex-1 py-3 rounded-xl bg-primary text-white text-sm font-semibold active:bg-primary/90 disabled:opacity-60 flex items-center justify-center gap-2">
                                <i class="bi bi-arrow-repeat animate-spin" x-show="submitting"></i>
                                <span x-text="editing ? '{{ __('Save') }}' : '{{ __('member.add_work') }}'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- ===== Attendance ===== --}}
        <div x-show="tab==='attendance'" x-transition.opacity x-cloak class="space-y-3">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-5">
                <div class="mp-ring" style="--p:{{ (int) $attendanceRate }}; width:84px; height:84px;"><b style="font-size:18px">{{ (int) $attendanceRate }}%</b></div>
                <div class="flex-1 space-y-2">
                    <div class="flex justify-between text-sm"><span class="text-muted-foreground">{{ __('member.completed') }}</span><span class="font-bold text-green-600">{{ $sessionsCompleted }}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-muted-foreground">{{ __('member.no_shows') }}</span><span class="font-bold text-red-500">{{ $noShows }}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-muted-foreground">{{ __('member.total_sessions') }}</span><span class="font-bold">{{ $totalSessions }}</span></div>
                </div>
            </div>

            {{-- Class schedule — every dated occurrence of the subscribed package's classes
                 (attended, missed, or still upcoming), tap to open that class's schedule detail. --}}
            @if($scheduleSessions->isNotEmpty())
                @php
                    $statusStyles = [
                        'attended' => ['bg-green-50 text-green-600', 'bi-check-lg', __('member.attended')],
                        'missed' => ['bg-red-50 text-red-500', 'bi-x-lg', __('member.missed')],
                        'upcoming' => ['bg-gray-100 text-gray-500', 'bi-clock', __('member.upcoming')],
                    ];
                @endphp
                <p class="text-xs font-bold text-muted-foreground uppercase tracking-wider px-1">{{ __('member.class_schedule') }}</p>
                @foreach($scheduleSessions as $session)
                    @php [$badgeClass, $icon, $label] = $statusStyles[$session->status]; @endphp
                    <a href="{{ $session->url }}" class="m-press block bg-white rounded-xl shadow-sm border border-gray-100 p-3 flex items-center gap-3">
                        <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 {{ $badgeClass }}">
                            <i class="bi {{ $icon }}"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-foreground truncate">{{ $session->title }}</p>
                            <p class="text-[11px] text-muted-foreground mt-0.5">{{ $session->date->format('d M Y') }} · {{ $session->start_time }}@if($session->coach) · {{ $session->coach }}@endif</p>
                        </div>
                        <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $badgeClass }}">{{ $label }}</span>
                        <i class="bi bi-chevron-left rtl:rotate-180 text-muted-foreground/60 text-xs flex-shrink-0"></i>
                    </a>
                @endforeach
            @else
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
                    <i class="bi bi-calendar-x text-2xl text-gray-300"></i>
                    <p class="text-sm text-muted-foreground mt-2">{{ __('member.no_schedule_sessions') }}</p>
                </div>
            @endif
        </div>

        {{-- ===== Challenges — this member's head-to-head history (reached via the Challenge stat card) ===== --}}
        <div x-show="tab==='challenges'" x-transition.opacity x-cloak class="space-y-3">
            {{-- Win-rate summary --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-5">
                <div class="mp-ring" style="--p:{{ (int) $challengeWinRate }}; width:84px; height:84px;"><b style="font-size:18px">{{ (int) $challengeWinRate }}%</b></div>
                <div class="flex-1 space-y-2">
                    <div class="flex justify-between text-sm"><span class="text-muted-foreground">{{ __('member.ch_won') }}</span><span class="font-bold text-green-600">{{ $challengeWins }}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-muted-foreground">{{ __('member.ch_completed') }}</span><span class="font-bold">{{ $challengesTotal }}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-muted-foreground">{{ __('member.ch_total') }}</span><span class="font-bold">{{ $memberChallenges->count() }}</span></div>
                </div>
            </div>

            @forelse($memberChallenges as $ch)
                @php
                    // Status / result badge tokens.
                    if ($ch->result === 'won')       [$bTone,$bText] = ['bg-green-100 text-green-700', __('member.ch_won')];
                    elseif ($ch->result === 'lost')  [$bTone,$bText] = ['bg-red-100 text-red-600',      __('member.ch_lost')];
                    elseif ($ch->result === 'draw')  [$bTone,$bText] = ['bg-gray-100 text-gray-600',     __('member.ch_draw')];
                    elseif ($ch->status === 'active')   [$bTone,$bText] = ['bg-blue-100 text-blue-700',  __('member.ch_active')];
                    elseif ($ch->status === 'pending')  [$bTone,$bText] = ['bg-amber-100 text-amber-700',__('member.ch_pending')];
                    elseif ($ch->status === 'reported') [$bTone,$bText] = ['bg-purple-100 text-purple-700', __('member.ch_reported')];
                    else [$bTone,$bText] = ['bg-gray-100 text-gray-500', ucfirst($ch->status)];
                    $hasScore = filled($ch->my_score) || filled($ch->rival_score);
                @endphp
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3.5 flex items-center gap-3">
                    {{-- rival avatar --}}
                    @if($ch->rival_uuid)
                        <a href="{{ route('member.show', $ch->rival_uuid) }}" class="flex-shrink-0">
                    @else
                        <span class="flex-shrink-0">
                    @endif
                        @if($ch->rival_avatar)
                            <img src="{{ $ch->rival_avatar }}" alt="" class="w-11 h-11 rounded-full object-cover border border-gray-100">
                        @else
                            <x-gender-avatar :gender="$ch->rival_gender" class="w-11 h-11 rounded-full border border-gray-100" />
                        @endif
                    @if($ch->rival_uuid)</a>@else</span>@endif

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-foreground truncate">
                            <i class="bi {{ $ch->type === 'fight' ? 'bi-shield-shaded' : 'bi-lightning-charge-fill' }} text-primary mr-0.5"></i>
                            {{ $ch->discipline }}
                        </p>
                        <p class="text-[11px] text-muted-foreground truncate">{{ __('member.ch_vs') }} {{ $ch->rival_name }} · {{ optional($ch->date)->format('d M Y') }}</p>
                        @if($hasScore)
                            <p class="text-[11px] text-muted-foreground mt-0.5">{{ __('member.ch_score') }}: <span class="font-semibold text-foreground">{{ $ch->my_score ?? '—' }}</span> – <span class="font-semibold text-foreground">{{ $ch->rival_score ?? '—' }}</span></p>
                        @endif
                    </div>

                    <div class="text-right flex-shrink-0">
                        <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $bTone }}">{{ $bText }}</span>
                        <p class="text-[10px] text-muted-foreground mt-1"><i class="bi bi-star-fill text-amber-400"></i> {{ $ch->stake }}</p>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
                    <i class="bi bi-lightning-charge text-3xl text-gray-300"></i>
                    <p class="text-sm text-muted-foreground mt-2">{{ __('member.ch_empty') }}</p>
                </div>
            @endforelse
        </div>


    </div>
</div>

{{-- Basic-info edit — self / guardian / super-admin only. Reuses the shared
     profile modal; on success it dispatches `member-profile-updated`. --}}
@if($canEditBasic ?? false)
    @php
        $isAdminView = $relationship->relationship_type === 'admin_view';
        $photoUploadUrl = $isAdminView
            ? route('admin.platform.members.upload-picture', $relationship->dependent->id)
            : route('member.upload-picture', $relationship->dependent->id);
    @endphp
    {{-- The photo is NOT a tab here — the avatar's pencil opens the photo sheet instead,
         which manages the profile's several pictures rather than one. --}}
    <x-profile-modal
        :user="$relationship->dependent"
        :formAction="$isAdminView ? route('admin.platform.members.update', $relationship->dependent->id) : route('member.update', $relationship->dependent->id)"
        formMethod="PUT"
        :cancelUrl="null"
        :uploadUrl="$photoUploadUrl"
        :showPhotoTab="false"
        :showRelationshipFields="!$isAdminView && $relationship->relationship_type !== 'self'"
        :relationship="$relationship"
    />

    <x-profile-photo-sheet :user="$relationship->dependent" />
@endif

{{-- Scripts live INSIDE the content section (not @push) so they ship with #shell-content --}}
{{-- and re-run on the mobile shell's AJAX swaps — @push('scripts') would be dropped there. --}}
<script>
// Affiliation detail sheet — a tap on a club card opens its data in a bottom sheet.
function affiliationSheet(items, cfg) {
    return {
        items: items || {},
        cfg: cfg || {},
        show: false,
        cur: null,
        // Add-affiliation bottom sheet
        addOpen: false,
        saving: false,
        blankAffForm() {
            return { source: 'platform', tenant_id: '', club_name: '', start_date: '', end_date: '', current: false, location: '', description: '' };
        },
        form: { source: 'platform', tenant_id: '', club_name: '', start_date: '', end_date: '', current: false, location: '', description: '' },
        openAdd() {
            this.form = this.blankAffForm();
            this.addOpen = true;
        },
        async submitAdd() {
            if (this.saving) return;
            const usePlatform = this.form.source === 'platform';
            if (usePlatform ? ! this.form.tenant_id : ! this.form.club_name.trim()) {
                window.showToast('warning', @js(__('member.club_name_start_required')));
                return;
            }
            if (! this.form.start_date) {
                window.showToast('warning', @js(__('member.club_name_start_required')));
                return;
            }
            this.saving = true;
            try {
                const res = await fetch(this.cfg.storeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        tenant_id: usePlatform ? this.form.tenant_id : null,
                        club_name: usePlatform ? null : this.form.club_name,
                        start_date: this.form.start_date,
                        end_date: this.form.current ? null : (this.form.end_date || null),
                        location: this.form.location || null,
                        description: this.form.description || null,
                    }),
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'error');
                window.showToast('success', data.message || 'Added.');
                // Server-rendered list → reload (the #clubs hash returns to this tab).
                window.location.reload();
            } catch (e) {
                window.showToast('error', @js(__('Something went wrong. Please try again.')));
                this.saving = false;
            }
        },
        // Ask the platform club to confirm this affiliation (from the detail sheet).
        async requestVerify() {
            if (! this.cur || ! this.cur.request_url) return;
            try {
                const res = await fetch(this.cur.request_url, { method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.cfg.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (data.success) { this.cur.verification = 'pending'; window.showToast && window.showToast('success', data.message); }
                else { window.showToast && window.showToast('error', data.message || @js(__('Something went wrong. Please try again.'))); }
            } catch (e) { window.showToast && window.showToast('error', @js(__('Something went wrong. Please try again.'))); }
        },
        async shareForVouch(url) {
            const abs = new URL(url, window.location.origin).href;
            const text = @js(__('member.vouch_share_text'));
            if (navigator.share) { try { await navigator.share({ url: abs, text }); return; } catch (e) { if (e && e.name === 'AbortError') return; } }
            try { await navigator.clipboard.writeText(abs); window.showToast && window.showToast('success', @js(__('member.link_copied'))); }
            catch (e) { window.showToast && window.showToast('info', abs); }
        },

        // ═══ Add Skill / Instructor / Media — self-managed, mirrors the desktop modals.
        //     Each hits the same MemberController endpoint and patches `cur` in place. ═══
        // The affiliation these sheets act on is always the open detail sheet's `cur`.
        get affBase() { return `/member/${this.cfg.memberId}/affiliations/${this.cur?.id}`; },
        jsonHeaders() {
            return { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' };
        },

        // ── Add Skill ───────────────────────────────────────────────────────
        skillOpen: false, skillSaving: false, acOpen: false,
        skillForm: { activityQuery: '', activityId: '', level: '', startDate: '', endDate: '', present: false, notes: '', instructorId: '' },
        skillActivities: [], skillCatalog: [], skillInstructors: [],
        skillBounds: { start_date: null, end_date: null, max_start: null },
        skillLevels: [
            { value: 'beginner',     label: @js(__('member.partials_affiliations_enhanced_beginner')),     pips: 1 },
            { value: 'intermediate', label: @js(__('member.partials_affiliations_enhanced_intermediate')), pips: 2 },
            { value: 'advanced',     label: @js(__('member.partials_affiliations_enhanced_advanced')),     pips: 3 },
            { value: 'expert',       label: @js(__('member.partials_affiliations_enhanced_expert')),       pips: 4 },
        ],
        get skillGroupedOptions() {
            const q = this.skillForm.activityQuery.trim().toLowerCase();
            const match = (a) => !q || a.name.toLowerCase().includes(q);
            return [
                { key: 'club', label: @js(__('This club')),      items: this.skillActivities.filter(match) },
                { key: 'all',  label: @js(__('All activities')), items: this.skillCatalog.filter(match) },
            ];
        },
        get skillExactMatch() {
            const q = this.skillForm.activityQuery.trim().toLowerCase();
            return !!q && [...this.skillActivities, ...this.skillCatalog].some(a => a.name.toLowerCase() === q);
        },
        chooseActivity(opt) { this.skillForm.activityQuery = opt.name; this.skillForm.activityId = opt.id || ''; this.acOpen = false; },
        useTypedActivity() { this.skillForm.activityQuery = this.skillForm.activityQuery.trim(); this.skillForm.activityId = ''; this.acOpen = false; },
        async openAddSkill() {
            if (!this.cur) return;
            this.skillForm = { activityQuery: '', activityId: '', level: '', startDate: '', endDate: '', present: false, notes: '', instructorId: '' };
            this.skillActivities = []; this.skillCatalog = []; this.skillInstructors = [];
            this.skillBounds = { start_date: null, end_date: null, max_start: null };
            this.acOpen = false; this.skillOpen = true;
            try {
                const res = await fetch(`${this.affBase}/activities`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                this.skillActivities = data.activities || [];
                this.skillCatalog = data.suggestions || [];
                this.skillInstructors = data.instructors || [];
                this.skillBounds = data.affiliation || this.skillBounds;
            } catch (e) { /* free text still works */ }
        },
        async submitSkill() {
            if (this.skillSaving) return;
            const f = this.skillForm;
            if (!f.activityQuery.trim() || !f.level) { window.showToast('warning', @js(__('member.partials_affiliations_enhanced_add_skill'))); return; }
            this.skillSaving = true;
            try {
                const res = await fetch(`${this.affBase}/skills`, {
                    method: 'POST', headers: this.jsonHeaders(),
                    body: JSON.stringify({
                        skill_name: f.activityQuery.trim(),
                        activity_name: f.activityQuery.trim(),
                        activity_id: f.activityId || null,
                        proficiency_level: f.level,
                        instructor_id: f.instructorId || null,
                        start_date: f.startDate || null,
                        is_present: f.present ? 1 : 0,
                        end_date: f.present ? null : (f.endDate || null),
                        notes: f.notes || null,
                    }),
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'error');
                const s = data.skill || {};
                const lvl = (s.proficiency_level || f.level);
                this.cur.skills.push({
                    name: s.skill_name || f.activityQuery.trim(),
                    level: lvl ? lvl.charAt(0).toUpperCase() + lvl.slice(1) : '',
                    duration: s.formatted_duration || null,
                    url: s.encyclopedia_url || null,
                });
                window.showToast('success', data.message || @js(__('member.partials_affiliations_enhanced_add_skill')));
                this.skillOpen = false;
            } catch (e) {
                window.showToast('error', @js(__('Something went wrong. Please try again.')));
            }
            this.skillSaving = false;
        },

        // ── Add Instructor ──────────────────────────────────────────────────
        insOpen: false, insSaving: false, insMode: 'member',
        insQuery: '', insName: '', insResults: [], insSearching: false,
        openAddInstructor() {
            if (!this.cur) return;
            this.insMode = 'member'; this.insQuery = ''; this.insName = '';
            this.insResults = []; this.insSearching = false; this.insOpen = true;
        },
        async searchInstructors() {
            const q = this.insQuery.trim();
            if (q.length < 2) { this.insResults = []; return; }
            this.insSearching = true;
            try {
                const res = await fetch(`/member/${this.cfg.memberId}/instructor-search?q=${encodeURIComponent(q)}`, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                this.insResults = data.results || [];
            } catch (e) { this.insResults = []; }
            this.insSearching = false;
        },
        addInstructorMember(r) { this.postInstructor({ user_uuid: r.uuid }); },
        addInstructorName() { if (this.insName.trim()) this.postInstructor({ name: this.insName.trim() }); },
        async postInstructor(payload) {
            if (this.insSaving) return;
            this.insSaving = true;
            try {
                const res = await fetch(`${this.affBase}/instructors`, { method: 'POST', headers: this.jsonHeaders(), body: JSON.stringify(payload) });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'error');
                // The endpoint returns the FULL instructor list — remap to the sheet's shape.
                this.cur.instructors = (data.instructors || []).map(i => ({ name: i.name, avatar: i.avatar, url: i.profile_url || null }));
                window.showToast('success', data.message || @js(__('Add')));
                this.insOpen = false;
            } catch (e) {
                window.showToast('error', @js(__('Something went wrong. Please try again.')));
            }
            this.insSaving = false;
        },

        // ── Add Media / Certificate ─────────────────────────────────────────
        mediaOpen: false, mediaSaving: false, mediaType: 'certificate',
        mediaTitle: '', mediaUrl: '', mediaDesc: '', mediaHasImage: false, mediaImgSrc: '', _mediaObserver: null,
        mediaTypes: [
            { value: 'certificate', label: @js(__('member.partials_affiliations_enhanced_certificate')), icon: 'bi-patch-check' },
            { value: 'photo',       label: @js(__('member.partials_affiliations_enhanced_photo')),       icon: 'bi-image' },
            { value: 'video',       label: @js(__('member.partials_affiliations_enhanced_video')),       icon: 'bi-play-circle' },
            { value: 'document',    label: @js(__('member.partials_affiliations_enhanced_document')),    icon: 'bi-file-text' },
        ],
        get mediaIsImage() { return this.mediaType === 'certificate' || this.mediaType === 'photo'; },
        openAddMedia() {
            if (!this.cur) return;
            this.mediaType = 'certificate'; this.mediaTitle = ''; this.mediaUrl = ''; this.mediaDesc = '';
            this.mediaHasImage = false; this.mediaImgSrc = ''; this.mediaSaving = false;
            const h = document.getElementById('hiddenInput_affMediaCropperM'); if (h) h.value = '';
            this.mediaOpen = true;
        },
        pickMediaImage() {
            // Watch the shared cropper's preview for a completed crop, then trigger its picker.
            if (!this._mediaObserver) {
                const box = document.getElementById('previewContainer_affMediaCropperM');
                if (box) {
                    this._mediaObserver = new MutationObserver(() => {
                        const b64 = document.getElementById('hiddenInput_affMediaCropperM')?.value || '';
                        if (b64) { this.mediaImgSrc = b64; this.mediaHasImage = true; }
                    });
                    this._mediaObserver.observe(box, { childList: true, subtree: true, attributes: true });
                }
            }
            document.getElementById('input_affMediaCropperM')?.click();
        },
        removeMediaImage() {
            this.mediaHasImage = false; this.mediaImgSrc = '';
            const h = document.getElementById('hiddenInput_affMediaCropperM'); if (h) h.value = '';
            try { window['removeImage_affMediaCropperM']?.(); } catch (e) {}
        },
        async submitMedia() {
            if (this.mediaSaving || !this.mediaTitle.trim()) return;
            let mediaUrl = '';
            if (this.mediaIsImage) {
                const b64 = document.getElementById('hiddenInput_affMediaCropperM')?.value || '';
                if (!b64) { window.showToast('error', @js(__('Please choose and crop an image first.'))); return; }
                this.mediaSaving = true;
                let up;
                try {
                    up = await fetch(`${this.affBase}/media/upload-image`, { method: 'POST', headers: this.jsonHeaders(), body: JSON.stringify({ image: b64 }) }).then(r => r.json());
                } catch (e) { up = null; }
                if (!up || !up.success) { this.mediaSaving = false; window.showToast('error', (up && up.message) || @js(__('Image upload failed.'))); return; }
                mediaUrl = up.path;
            } else {
                if (!/^https?:\/\/.+/i.test(this.mediaUrl.trim())) { window.showToast('error', @js(__('Please enter a valid link (http/https).'))); return; }
                mediaUrl = this.mediaUrl.trim();
                this.mediaSaving = true;
            }
            try {
                const res = await fetch(`${this.affBase}/media`, { method: 'POST', headers: this.jsonHeaders(), body: JSON.stringify({ media_type: this.mediaType, title: this.mediaTitle.trim(), media_url: mediaUrl, description: this.mediaDesc.trim() }) });
                const data = await res.json();
                if (!res.ok || !data.success || !data.media) throw new Error(data.message || 'error');
                const m = data.media;
                this.cur.media.push({ title: m.title, url: m.full_url, icon: m.icon_class });
                window.showToast('success', data.message || @js(__('member.partials_affiliations_enhanced_add_media')));
                this.mediaOpen = false;
            } catch (e) {
                window.showToast('error', @js(__('member.partials_affiliations_enhanced_js_error_adding_media')));
            }
            this.mediaSaving = false;
        },

        init() {
            // Restore an open sheet from the URL (?aff=<id>) — e.g. after tapping a
            // skill through to the encyclopedia and pressing back, the sheet reopens
            // exactly as it was left.
            try {
                const id = new URL(window.location.href).searchParams.get('aff');
                if (id && this.items[id]) {
                    this.cur = this.items[id];
                    this.show = true;
                    document.body.style.overflow = 'hidden';
                }
            } catch (e) {}
        },
        openSheet(id) {
            this.cur = this.items[id] || null;
            if (!this.cur) return;
            this.show = true;
            document.body.style.overflow = 'hidden';
            this.syncUrl(id);
        },
        close() {
            this.show = false;
            document.body.style.overflow = '';
            this.syncUrl(null);
        },
        // Reflect the open sheet in the URL without adding a history entry, keeping
        // the tab hash (#clubs) and everything else intact.
        syncUrl(id) {
            try {
                const url = new URL(window.location.href);
                if (id) url.searchParams.set('aff', id);
                else url.searchParams.delete('aff');
                history.replaceState(history.state, '', url.pathname + url.search + url.hash);
            } catch (e) {}
        },
    };
}

// Shared "X years/months/days ago" helper (calendar-accurate, i18n-aware).
// Used by the weight history and the medal sheet (which passes the EVENT date).
window.memberTimeAgo = (function () {
    var t = {
        tpl: @js(__('member.time_ago_tpl')), today: @js(__('member.time_ago_today')),
        yr: @js(__('member.unit_yr')), yrs: @js(__('member.unit_yrs')),
        mo: @js(__('member.unit_mo')), mos: @js(__('member.unit_mos')),
        day: @js(__('member.unit_day')), days: @js(__('member.unit_days')),
    };
    return function (ds) {
        if (!ds) return '';
        var d = new Date(ds + 'T00:00:00');
        if (isNaN(d.getTime())) return '';
        var now = new Date();
        var y = now.getFullYear() - d.getFullYear();
        var m = now.getMonth() - d.getMonth();
        var days = now.getDate() - d.getDate();
        if (days < 0) { m -= 1; days += new Date(now.getFullYear(), now.getMonth(), 0).getDate(); }
        if (m < 0) { y -= 1; m += 12; }
        if (y < 0) return '';
        var p = [];
        if (y > 0) p.push(y + ' ' + (y === 1 ? t.yr : t.yrs));
        if (m > 0) p.push(m + ' ' + (m === 1 ? t.mo : t.mos));
        if (days > 0) p.push(days + ' ' + (days === 1 ? t.day : t.days));
        if (!p.length) return t.today;
        return t.tpl.replace(':time', p.join(' '));
    };
})();

// Weight-tracking: reactive history list + AJAX add (no page reload).
window.weightLogger = function (opts) {
    return {
        rows: opts.rows || [],
        // Live summary the primary cards bind to. `latest` is the newest reading,
        // `prev` the one before it (so trends recompute after each save).
        latest: opts.latest || { weight: null, height: null, bmi: null, label: null },
        prev: opts.prev || { weight: null, height: null, bmi: null },
        addOpen: false, busy: false,
        weight: '', height: '', date: opts.today, today: opts.today,
        // Pre-fill height with the last known value — it rarely changes, and keeping it
        // present lets the server derive BMI for the new reading.
        openAdd() {
            this.weight = '';
            this.height = (this.latest && this.latest.height != null) ? String(this.latest.height) : '';
            this.date = this.today;
            this.addOpen = true;
        },
        fmt(v) { return (v === null || v === undefined || v === '') ? '—' : Number(v).toFixed(1); },
        // Trend of a metric vs the previous reading (null when not comparable).
        trendOf(key) {
            var a = this.latest ? this.latest[key] : null;
            var b = this.prev ? this.prev[key] : null;
            if (a === null || a === undefined || b === null || b === undefined || b == 0) return null;
            return Math.round((a - b) * 10) / 10;
        },
        // Difference vs the previous (chronologically older) reading in the history list.
        delta(i) {
            var older = this.rows[i + 1];
            if (!older) return null;
            return Math.round((this.rows[i].weight - older.weight) * 10) / 10;
        },

        // ── Taekwondo weight-class classification (mirrors app/Helpers/classifyTaekwondo) ──
        gender: opts.gender || '',
        age: opts.age || 0,
        divisions: opts.divisions || {},
        i18n: opts.i18n || { upTo: 'Up to :max kg', over: 'Over :min kg', range: ':min–:max kg', headroom: ':kg kg below the :label limit' },
        timeAgo: opts.timeAgo || { tpl: ':time ago', today: 'today', yr: 'yr', yrs: 'yrs', mo: 'mo', mos: 'mos', day: 'day', days: 'days' },
        // Calendar distance from a reading date to today (defaults to latest reading).
        ago(dateStr) {
            return window.memberTimeAgo(dateStr || (this.latest && this.latest.date));
        },
        ageGroup() {
            var a = this.age;
            if (a >= 6 && a <= 11) return 'Kids';
            if (a >= 12 && a <= 14) return 'Cadet';
            if (a >= 15 && a <= 17) return 'Junior';
            if (a >= 18 && a <= 30) return 'Senior';
            if (a >= 31) return 'Masters';
            return null;
        },
        // → { age_group, label, min, max } | null  (the lightest division the weight fits).
        classify(weight) {
            var w = Number(weight);
            if (!w || !this.gender) return null;
            var g = this.ageGroup();
            if (!g) return null;
            var list = (this.divisions[g] || {})[this.gender];
            if (!list) return null;
            for (var i = 0; i < list.length; i++) {
                if (w >= list[i].min && w <= list[i].max) {
                    return { age_group: g, label: list[i].label, name: list[i].name || null, min: list[i].min, max: list[i].max };
                }
            }
            return null;
        },
        // Human-readable range for a division (e.g. "Up to 58 kg" / "Over 80 kg" / "58–68 kg").
        classRange(c) {
            if (!c) return '';
            if (String(c.label).charAt(0) === '-') return this.i18n.upTo.replace(':max', c.max);
            if (String(c.label).charAt(0) === '+') return this.i18n.over.replace(':min', c.min);
            return this.i18n.range.replace(':min', c.min).replace(':max', c.max);
        },
        // kg the member is from the upper bound of their current division (null for +X / open class).
        classHeadroom(weight, c) {
            if (!c || String(c.label).charAt(0) === '+') return null;
            return Math.round((c.max - Number(weight)) * 10) / 10;
        },
        save() {
            if (!this.weight || this.busy) return;
            this.busy = true;
            var self = this;
            fetch(opts.url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': opts.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ weight: this.weight, height: this.height || null, recorded_at: this.date })
            }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
              .then(function (res) {
                  self.busy = false;
                  if (!res.d || !res.d.success) {
                      window.showToast && window.showToast('error', (res.d && res.d.message) || 'Error');
                      return;
                  }
                  var rec = res.d.record;
                  // History list tracks weight readings.
                  if (rec.weight !== null && rec.weight !== undefined) {
                      self.rows.push({ weight: rec.weight, label: rec.recorded_label, date: rec.recorded_at });
                      self.rows.sort(function (a, b) { return b.date.localeCompare(a.date); }); // newest first
                  }
                  // Shift the live summary: the old latest becomes prev, this reading is latest.
                  self.prev = { weight: self.latest.weight, height: self.latest.height, bmi: self.latest.bmi };
                  self.latest = { weight: rec.weight, height: rec.height, bmi: rec.bmi, label: rec.recorded_label, date: rec.recorded_at };
                  self.addOpen = false;
                  window.showToast && window.showToast('success', res.d.message || 'Added');
              }).catch(function () {
                  self.busy = false;
                  window.showToast && window.showToast('error', 'Something went wrong.');
              });
        }
    };
};

(function () {
    // Count-up for the metric rail numbers.
    var els = document.querySelectorAll('[data-count]');
    els.forEach(function (el) {
        var target = parseInt(el.getAttribute('data-count'), 10) || 0;
        if (target === 0) { el.textContent = '0'; return; }
        var start = null, dur = 900;
        function step(ts) {
            if (!start) start = ts;
            var p = Math.min((ts - start) / dur, 1);
            el.textContent = Math.round(p * target * (2 - p)); // easeOut
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    });
})();

// Super-admin password controls (reset / regenerate). Defined globally so
// Alpine resolves it whether the view loads standalone or in the mobile shell.
window.memberPwdAdmin = function (resetUrl, regenerateUrl, name) {
    return {
        name: name,
        busy: false,
        setOpen: false, resultOpen: false,
        pw1: '', pw2: '',
        newPw: '', emailed: false, copied: false,
        _csrf() { return document.querySelector('meta[name=csrf-token]')?.content || ''; },
        async _post(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this._csrf() },
                credentials: 'same-origin',
                body: body ? JSON.stringify(body) : null,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data.success === false) {
                throw new Error(data.message || (data.errors?.password?.[0]) || @js(__('shared.error')));
            }
            return data;
        },
        openSet() { this.pw1 = ''; this.pw2 = ''; this.setOpen = true; },
        async submitSet() {
            if (this.busy) return;
            if (this.pw1.length < 8) { window.showToast && window.showToast('error', @js(__('member.password_min'))); return; }
            if (this.pw1 !== this.pw2) { window.showToast && window.showToast('error', @js(__('member.passwords_no_match'))); return; }
            this.busy = true;
            try {
                const data = await this._post(resetUrl, { password: this.pw1, password_confirmation: this.pw2 });
                this.setOpen = false;
                window.showToast && window.showToast('success', data.message || @js(__('member.password_reset_ok')));
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            } finally { this.busy = false; }
        },
        async generate() {
            if (this.busy) return;
            const ok = await window.confirmAction({
                title: @js(__('member.generate_password')),
                message: @js(__('member.generate_confirm')).replace(':name', this.name),
                type: 'warning', confirmText: @js(__('member.generate_password')),
            });
            if (!ok) return;
            this.busy = true;
            try {
                const data = await this._post(regenerateUrl, {});
                this.newPw = data.password;
                this.emailed = !!data.emailed;
                this.copied = false;
                this.resultOpen = true;
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            } finally { this.busy = false; }
        },
        copy() {
            const done = () => { this.copied = true; window.showToast && window.showToast('success', @js(__('member.password_copied'))); };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(this.newPw).then(done).catch(() => {});
            } else { done(); }
        },
    };
};

// Follow / share / chat controls flanking the profile picture in the hero.
window.memberFollow = function (initial, followUrl, name, chatUrl) {
    return {
        following: initial,
        busy: false,
        chatBusy: false,

        // Open (or start) a 1:1 conversation with this member, then go to the thread.
        async openChat() {
            if (!chatUrl || this.chatBusy) return;
            this.chatBusy = true;
            try {
                const res = await fetch(chatUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false || !data.conversation_id) {
                    throw new Error(data.message || '');
                }
                window.location.href = '/messages/' + data.conversation_id;
            } catch (e) {
                this.chatBusy = false;
                window.showToast && window.showToast('error', e.message || @js(__('member.chat_error')));
            }
        },

        async toggleFollow() {
            if (this.busy) return;
            const turningOn = !this.following;
            this.busy = true;
            this.following = turningOn; // optimistic
            try {
                const res = await fetch(followUrl, {
                    method: turningOn ? 'POST' : 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.success === false) throw new Error();
                if (data.relationship && typeof data.relationship.following === 'boolean') {
                    this.following = data.relationship.following;
                }
            } catch (e) {
                this.following = !turningOn; // revert
                window.showToast && window.showToast('error', @js(__('member.follow_error')));
            } finally {
                this.busy = false;
            }
        },

        shareProfile() {
            const url = window.location.href;
            if (navigator.share) {
                navigator.share({ title: name, url }).catch(() => {});
            } else if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url)
                    .then(() => window.showToast && window.showToast('success', @js(__('member.profile_link_copied'))))
                    .catch(() => {});
            } else {
                window.showToast && window.showToast('info', @js(__('member.share_unsupported')));
            }
        },
    };
};

// Patch the hero in place after a basic-info edit (no reload). The shared
// profile modal dispatches `member-profile-updated` with the saved member.
// Dedup across shell swaps: drop the previous handler before re-binding so the
// listener doesn't stack each time this content is re-injected.
window.__mpProfileUpdated && window.removeEventListener('member-profile-updated', window.__mpProfileUpdated);
window.__mpProfileUpdated = (e) => {
    const m = e.detail || {};
    const nameEl = document.getElementById('mpName');
    if (nameEl && m.full_name) nameEl.textContent = m.full_name;

    const mottoEl = document.getElementById('mpMotto');
    if (mottoEl) {
        const motto = m.motto || m.bio || '';
        if (motto) { mottoEl.textContent = '“' + motto + '”'; mottoEl.classList.remove('hidden'); }
        else { mottoEl.textContent = ''; mottoEl.classList.add('hidden'); }
    }

    const img = document.getElementById('mpAvatarImg');
    if (img && m.profile_picture) {
        img.src = '/storage/' + m.profile_picture + '?v=' + Date.now();
    }
};
window.addEventListener('member-profile-updated', window.__mpProfileUpdated);

// Goals tab: reactive list + AJAX add/update (no page reload). Pure calendar
// helpers take their args explicitly (no `this`) so they're safe to call bare
// from the nested date-picker's own x-data scope.
function goalDateView(dateStr) {
    var base = dateStr ? new Date(dateStr + 'T00:00:00') : new Date();
    return { y: base.getFullYear(), m: base.getMonth() };
}
function goalCalGrid(view) {
    var start = new Date(view.y, view.m, 1).getDay();
    var days = new Date(view.y, view.m + 1, 0).getDate();
    var cells = [];
    for (var i = 0; i < start; i++) cells.push(null);
    for (var d = 1; d <= days; d++) cells.push(d);
    return cells;
}
function goalIso(view, d) {
    return view.y + '-' + String(view.m + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
}
function goalIsPast(view, d) {
    if (!d) return false;
    var t = new Date(); t.setHours(0, 0, 0, 0);
    return new Date(view.y, view.m, d) < t;
}
function fmtDate(val) {
    if (!val) return '';
    var d = new Date(val + 'T00:00:00');
    return d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
}

// A phone camera photo can be several MB — base64-encoded raw, that easily blows
// past the server's post_max_size and the upload silently fails. Downscale onto a
// canvas (max 1600px) and re-encode as JPEG before it ever becomes a data URI.
function resizeImageToDataUrl(file, maxDim, quality) {
    return new Promise(function (resolve, reject) {
        var reader = new FileReader();
        reader.onload = function () {
            var img = new Image();
            img.onload = function () {
                var width = img.width, height = img.height;
                if (width > maxDim || height > maxDim) {
                    if (width > height) { height = Math.round(height * (maxDim / width)); width = maxDim; }
                    else { width = Math.round(width * (maxDim / height)); height = maxDim; }
                }
                var canvas = document.createElement('canvas');
                canvas.width = width; canvas.height = height;
                canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                resolve(canvas.toDataURL('image/jpeg', quality || 0.85));
            };
            img.onerror = function () { reject(new Error('image_decode_failed')); };
            img.src = reader.result;
        };
        reader.onerror = function () { reject(new Error('file_read_failed')); };
        reader.readAsDataURL(file);
    });
}

// ---- Achievement provenance helpers (shared; safe to redefine on shell re-run) ----
window.verifyBadgeHtml = function (status, club) {
    var map = {
        verified:      ['bi-patch-check-fill','text-green-700','bg-green-50','border-green-200', @js(__('Verified'))],
        pending:       ['bi-hourglass-split','text-amber-700','bg-amber-50','border-amber-200', @js(__('Pending review'))],
        rejected:      ['bi-patch-exclamation','text-red-700','bg-red-50','border-red-200', @js(__('Not verified'))],
        self_reported: ['bi-person-badge','text-gray-500','bg-gray-50','border-gray-200', @js(__('Self-reported'))],
    };
    var m = map[status] || map.self_reported;
    var esc = window.__esc || (s => String(s ?? ''));
    var suffix = (status === 'verified' && club) ? '<span class="opacity-70 font-normal">· ' + esc(club) + '</span>' : '';
    return '<span data-verify-badge class="inline-flex items-center gap-1 rounded-full border font-medium px-2 py-0.5 text-xs ' + m[1] + ' ' + m[2] + ' ' + m[3] + '"><i class="bi ' + m[0] + '"></i><span>' + m[4] + '</span>' + suffix + '</span>';
};
window.patchVerifyRow = function (row, v) {
    var badge = row.querySelector('[data-verify-badge]');
    if (badge) badge.outerHTML = window.verifyBadgeHtml(v.status, v.verified_club);
    if (['verified','pending'].includes(v.status)) { var b = row.querySelector('[data-verify-btn]'); if (b) b.remove(); }
};
// Live updates from a club admin verifying/rejecting elsewhere (dedup across shell swaps).
if (window.__mobileVerifyHandler) window.removeEventListener('realtime:verification', window.__mobileVerifyHandler);
window.__mobileVerifyHandler = function (e) {
    var d = e.detail || {};
    if (d.action !== 'status' || !d.event_uuid) return;
    var row = document.querySelector('[data-verify-row="' + d.event_uuid + '"]');
    if (row) window.patchVerifyRow(row, { status: d.status, verified_club: d.verified_club });
};
window.addEventListener('realtime:verification', window.__mobileVerifyHandler);

window.tournamentSheet = function (cfg) {
    var esc = window.__esc || (s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])));
    window.__esc = esc;
    return {
        addOpen: false, saving: false,
        affiliations: cfg.affiliations || [],
        evidence: null, evidenceName: '', evidencePreview: '',
        typeOptions: [
            { v: 'championship', l: @js(__('Championship')), icon: 'bi-trophy' },
            { v: 'tournament', l: @js(__('Tournament')), icon: 'bi-flag' },
            { v: 'competition', l: @js(__('Competition')), icon: 'bi-people' },
            { v: 'exhibition', l: @js(__('Exhibition')), icon: 'bi-stars' },
        ],
        medalOptions: [
            { v: '', e: '—', l: @js(__('None')) },
            { v: '1st', e: '🥇', l: @js(__('Gold')) },
            { v: '2nd', e: '🥈', l: @js(__('Silver')) },
            { v: '3rd', e: '🥉', l: @js(__('Bronze')) },
            { v: 'special', e: '🏆', l: @js(__('Special')) },
        ],
        form: {},
        editing: false,
        wasVerified: false,
        updateUrl: '',
        blankForm() {
            return { title: '', sport: '', date: '', type: 'tournament', location: '', club_affiliation_id: null, medal_type: '' };
        },
        openAdd() {
            this.form = this.blankForm();
            this.editing = false; this.wasVerified = false; this.updateUrl = '';
            this.evidence = null; this.evidenceName = ''; this.evidencePreview = '';
            this.addOpen = true;
        },
        /** Read the card's escaped payload; both actions are driven from the DOM. */
        cardPayload(el) {
            const card = el.closest('[data-tournament-card]');
            if (!card || !card.dataset.edit) return null;
            try { return JSON.parse(card.dataset.edit); } catch (e) { return null; }
        },
        /** Same sheet, edit mode — prefilled from the card's payload. */
        openEdit(el) {
            const t = this.cardPayload(el);
            if (!t) return;
            this.form = {
                title: t.title || '', sport: t.sport || '', date: t.date || '',
                type: t.type || 'tournament', location: t.location || '',
                club_affiliation_id: t.club_affiliation_id || null, medal_type: t.medal_type || '',
            };
            this.editing = true;
            this.wasVerified = !! t.verified;
            this.updateUrl = t.update_url;
            this.evidence = null; this.evidenceName = ''; this.evidencePreview = '';
            this.addOpen = true;
        },
        /** Delete a record after an in-app confirmation (never a native dialog). */
        async remove(el) {
            const t = this.cardPayload(el);
            if (!t) return;
            const ok = await window.confirmAction({
                title: @js(__('member.tournament_delete_confirm_title')),
                message: @js(__('member.tournament_delete_confirm_body')),
                type: 'danger',
                confirmText: @js(__('shared.delete')),
            });
            if (! ok) return;
            try {
                const res = await fetch(t.delete_url, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                if (data.success) {
                    const card = document.querySelector('[data-tournament-card][data-uuid="' + CSS.escape(t.uuid) + '"]');
                    if (card) card.remove();
                    window.showToast && window.showToast('success', data.message);
                    // Medal tiles are server-computed, so refresh them.
                    if (! window.location.hash) { try { history.replaceState(history.state, '', '#tournaments'); } catch (e) {} }
                    window.location.reload();
                } else {
                    window.showToast && window.showToast('error', data.message || @js(__('shared.error')));
                }
            } catch (e) { window.showToast && window.showToast('error', @js(__('Something went wrong.'))); }
        },
        readEvidence(ev) {
            var file = ev.target.files && ev.target.files[0];
            if (!file) return;
            if (!/^image\/(jpeg|png|webp|gif)$/.test(file.type)) { window.showToast && window.showToast('error', @js(__('Unsupported image type.'))); ev.target.value=''; return; }
            if (file.size > 5 * 1024 * 1024) { window.showToast && window.showToast('error', @js(__('Image is too large (max 5MB).'))); ev.target.value=''; return; }
            var r = new FileReader();
            r.onload = e => { this.evidence = e.target.result; this.evidencePreview = e.target.result; this.evidenceName = file.name; };
            r.readAsDataURL(file);
        },
        // Unlisted club → share the public profile so teammates/coaches can vouch.
        async shareForVouch(url) {
            var abs = new URL(url, window.location.origin).href;
            var text = @js(__('member.vouch_share_text'));
            if (navigator.share) {
                try { await navigator.share({ url: abs, text: text }); return; }
                catch (e) { if (e && e.name === 'AbortError') return; }
            }
            try { await navigator.clipboard.writeText(abs); window.showToast && window.showToast('success', @js(__('member.link_copied'))); }
            catch (e) { window.showToast && window.showToast('info', abs); }
        },
        async submit() {
            if (!this.form.title || !this.form.sport || !this.form.date) {
                window.showToast && window.showToast('error', @js(__('Please fill in the title, sport and date.'))); return;
            }
            this.saving = true;
            var fd = new FormData();
            fd.append('title', this.form.title);
            fd.append('sport', this.form.sport);
            fd.append('date', this.form.date);
            fd.append('type', this.form.type);
            if (this.form.location) fd.append('location', this.form.location);
            if (this.form.club_affiliation_id) fd.append('club_affiliation_id', this.form.club_affiliation_id);
            if (this.form.medal_type) fd.append('performance_results[0][medal_type]', this.form.medal_type);
            if (this.evidence) fd.append('evidence', this.evidence);
            // Laravel method spoofing: the same multipart POST, routed to PUT on edit.
            if (this.editing) fd.append('_method', 'PUT');
            try {
                var res = await fetch(this.editing ? this.updateUrl : cfg.storeUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
                var data = await res.json();
                if (data.success) {
                    this.addOpen = false;
                    window.showToast && window.showToast('success', this.editing ? @js(__('member.tournament_updated')) : @js(__('Achievement added.')));
                    // Reload so the server-computed medal tiles + "awaiting verification"
                    // note reflect the new record (self-reported medals stay uncounted in
                    // the verified tiles by design). The #tournaments hash returns here.
                    if (! window.location.hash) { try { history.replaceState(history.state, '', '#tournaments'); } catch (e) {} }
                    window.location.reload();
                    return;
                } else {
                    window.showToast && window.showToast('error', data.message || @js(__('Could not save.')));
                }
            } catch (e) { window.showToast && window.showToast('error', @js(__('Something went wrong.'))); }
            this.saving = false;
        },
        async requestVerify(el, url) {
            el.disabled = true;
            try {
                var res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                var data = await res.json();
                if (data.success) {
                    var row = el.closest('[data-verify-row]');
                    if (row && data.verification) window.patchVerifyRow(row, data.verification);
                    window.showToast && window.showToast('success', data.message);
                } else { el.disabled = false; window.showToast && window.showToast('error', data.message || @js(__('Could not request verification.'))); }
            } catch (e) { el.disabled = false; window.showToast && window.showToast('error', @js(__('Something went wrong.'))); }
        },
        prependCard(t) {
            var list = document.getElementById('mobileTournamentsList');
            if (!list) return;
            var empty = document.getElementById('mobileTournamentsEmpty');
            if (empty) empty.remove();
            var mc = { '1st': 'bg-amber-100 text-amber-700', '2nd': 'bg-slate-100 text-slate-600', '3rd': 'bg-orange-100 text-orange-700', special: 'bg-accent text-primary' };
            var medals = (t.performance_results || []).map(function (r) {
                return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold ' + (mc[r.medal_type] || 'bg-gray-100 text-gray-600') + '"><i class="bi bi-award-fill"></i>' + esc(r.medal_type ? r.medal_type.charAt(0).toUpperCase() + r.medal_type.slice(1) : '') + '</span>';
            }).join('');
            var v = t.verification || {};
            var verifyExtra = '';
            if (v.evidence_url) verifyExtra += '<a href="' + esc(v.evidence_url) + '" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[11px] text-muted-foreground hover:text-primary"><i class="bi bi-paperclip"></i>' + @js(__('Evidence')) + '</a>';
            if (v.can_request && v.request_url) verifyExtra += '<button type="button" data-verify-btn onclick="window.requestAchievementVerification(this)" data-verify-url="' + esc(v.request_url) + '" class="inline-flex items-center gap-1 text-[11px] font-medium text-primary"><i class="bi bi-patch-check"></i>' + @js(__('Request verification')) + '</button>';
            var d = t.date ? new Date(t.date) : null;
            var dateLabel = d ? (String(d.getDate()).padStart(2, '0') + ' ' + d.toLocaleString('en', { month: 'short' }) + ' ' + d.getFullYear()) : '—';
            var html =
                '<div class="group relative bg-white rounded-2xl shadow-sm border border-gray-100 p-4 overflow-hidden">' +
                '<span class="absolute inset-y-0 left-0 rtl:left-auto rtl:right-0 w-1 bg-amber-400/80"></span>' +
                '<div class="flex items-start gap-3">' +
                '<span class="w-12 h-12 rounded-xl bg-amber-50 grid place-items-center text-amber-600 flex-shrink-0 ring-1 ring-amber-100"><i class="bi bi-trophy-fill text-lg"></i></span>' +
                '<div class="min-w-0 flex-1">' +
                '<p class="font-bold text-foreground text-[15px] leading-snug truncate">' + esc(t.title) + '</p>' +
                '<p class="text-[12px] font-medium text-foreground/60 truncate mt-0.5">' + esc(t.sport) + (t.location ? ' · ' + esc(t.location) : '') + '</p>' +
                '<div class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-muted-foreground">' +
                '<span class="inline-flex items-center gap-1"><i class="bi bi-calendar-range text-primary/50"></i>' + dateLabel + '</span>' + medals +
                '<span class="inline-flex items-center gap-2 flex-wrap" data-verify-row="' + esc(t.uuid || '') + '">' + window.verifyBadgeHtml(v.status || 'self_reported', v.verified_club) + verifyExtra + '</span>' +
                '</div>' +
                '</div></div></div>';
            var wrap = document.createElement('div');
            wrap.innerHTML = html;
            list.insertBefore(wrap.firstElementChild, list.firstChild);
        },
    };
};

// Global request-verification handler (used by JS-inserted mobile cards).
window.requestAchievementVerification = async function (btn) {
    var url = btn.getAttribute('data-verify-url');
    if (!url) return;
    btn.disabled = true;
    try {
        var res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': @js(csrf_token()), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        var data = await res.json();
        if (data.success) {
            var row = btn.closest('[data-verify-row]');
            if (row && data.verification) window.patchVerifyRow(row, data.verification);
            window.showToast && window.showToast('success', data.message);
        } else { btn.disabled = false; window.showToast && window.showToast('error', data.message || @js(__('Could not request verification.'))); }
    } catch (e) { btn.disabled = false; window.showToast && window.showToast('error', @js(__('Something went wrong.'))); }
};

window.goalsManager = function (cfg) {
    return {
        goals: cfg.goals || [],
        canEdit: !!cfg.canEdit,
        i18n: cfg.i18n || {},
        today: cfg.today,

        addOpen: false, addSubmitting: false, dateOpen: false,
        addForm: { title: '', description: '', target_value: '', unit: '', target_date: '', beforeProof: null, beforePreview: null },

        detailOpen: false, updateSubmitting: false, editable: false, lightboxImage: null,
        activeGoal: null, progressValue: 0, achieving: false, afterProof: null, afterPreview: null,

        pct(g) {
            var tv = parseFloat(g.target_value) || 0;
            var cv = parseFloat(g.current_progress_value) || 0;
            if (tv > 0) return Math.min(100, Math.round((cv / tv) * 100));
            return g.status === 'completed' ? 100 : 0;
        },
        get activeCount() { return this.goals.filter(function (g) { return g.status === 'active'; }).length; },
        get doneCount() { return this.goals.filter(function (g) { return g.status === 'completed'; }).length; },
        get successRate() {
            if (!this.goals.length) return 0;
            return Math.round((this.doneCount / this.goals.length) * 100);
        },
        fmtDate: fmtDate,

        openAdd() {
            this.addForm = { title: '', description: '', target_value: '', unit: '', target_date: '', beforeProof: null, beforePreview: null };
            this.dateOpen = false;
            this.addOpen = true;
        },
        async pickBeforePhoto(e) {
            var f = e.target.files && e.target.files[0];
            if (!f) return;
            if (!f.type.startsWith('image/')) { window.showToast && window.showToast('error', this.i18n.invalidImage); return; }
            try {
                var dataUrl = await resizeImageToDataUrl(f, 1600, 0.85);
                this.addForm.beforeProof = dataUrl;
                this.addForm.beforePreview = dataUrl;
            } catch (err) {
                window.showToast && window.showToast('error', this.i18n.invalidImage);
            }
        },
        async submitAdd() {
            var f = this.addForm;
            if (!f.title || !f.target_value || !f.unit || !f.target_date || !f.beforeProof) {
                window.showToast && window.showToast('error', this.i18n.pleaseChooseImage);
                return;
            }
            this.addSubmitting = true;
            try {
                var res = await fetch(cfg.storeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify({
                        title: f.title, description: f.description || null,
                        target_value: f.target_value, unit: f.unit, target_date: f.target_date,
                        before_proof: f.beforeProof,
                    }),
                });
                var data = await res.json().catch(function () { return {}; });
                if (res.ok && data.success) {
                    this.goals.unshift(Object.assign({
                        current_progress_value: 0, status: 'active', after_proof: null, completed_at: null, days_taken: null, description: f.description || null,
                    }, data.goal));
                    this.addOpen = false;
                    window.showToast && window.showToast('success', data.message || this.i18n.goalCreated);
                } else if (data.errors) {
                    var first = Object.values(data.errors)[0];
                    window.showToast && window.showToast('error', Array.isArray(first) ? first[0] : first);
                } else {
                    window.showToast && window.showToast('error', data.message || this.i18n.networkError);
                }
            } catch (e) {
                window.showToast && window.showToast('error', this.i18n.networkError);
            } finally {
                this.addSubmitting = false;
            }
        },

        openDetail(g) {
            this.activeGoal = g;
            this.editable = this.canEdit && g.status === 'active';
            this.progressValue = g.current_progress_value;
            this.achieving = false;
            this.afterProof = null;
            this.afterPreview = null;
            this.lightboxImage = null;
            this.detailOpen = true;
        },
        async pickAfterPhoto(e) {
            var f = e.target.files && e.target.files[0];
            if (!f) return;
            if (!f.type.startsWith('image/')) { window.showToast && window.showToast('error', this.i18n.invalidImage); return; }
            try {
                var dataUrl = await resizeImageToDataUrl(f, 1600, 0.85);
                this.afterProof = dataUrl;
                this.afterPreview = dataUrl;
            } catch (err) {
                window.showToast && window.showToast('error', this.i18n.invalidImage);
            }
        },
        async submitUpdate() {
            if (!this.activeGoal) return;
            if (this.achieving && !this.afterProof) return;
            this.updateSubmitting = true;
            try {
                var body = { current_progress_value: this.progressValue, status: this.achieving ? 'completed' : 'active' };
                if (this.achieving) body.after_proof = this.afterProof;

                var res = await fetch(cfg.updateUrlBase + '/' + this.activeGoal.id, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify(body),
                });
                var data = await res.json().catch(function () { return {}; });
                if (res.ok && data.success) {
                    var idx = this.goals.findIndex(function (g) { return g.id === data.goal.id; });
                    if (idx !== -1) this.goals[idx] = Object.assign({}, this.goals[idx], data.goal);
                    this.detailOpen = false;
                    window.showToast && window.showToast('success', data.message || this.i18n.goalUpdated);
                } else {
                    window.showToast && window.showToast('error', data.message || this.i18n.networkError);
                }
            } catch (e) {
                window.showToast && window.showToast('error', this.i18n.networkError);
            } finally {
                this.updateSubmitting = false;
            }
        },
    };
};

// ===== Certifications tab: reactive list + AJAX add/edit/delete (no reload) =====
window.certManager = function (cfg) {
    const blank = () => ({ id: null, title: '', issuer: '', issue_date: '', expiry_date: '', credential_id: '', credential_url: '', notes: '', image: null, imagePreview: null });
    return {
        items: (cfg.items || []).map(c => ({ ...c })),
        canEdit: !!cfg.canEdit,
        i18n: cfg.i18n || {},
        open: false,
        editing: false,
        submitting: false,
        form: blank(),

        openAdd() { if (!this.canEdit) return; this.form = blank(); this.editing = false; this.open = true; },
        openEdit(c) {
            if (!this.canEdit) return;
            this.form = {
                id: c.id, title: c.title || '', issuer: c.issuer || '',
                issue_date: c.issue_date || '', expiry_date: c.expiry_date || '',
                credential_id: c.credential_id || '', credential_url: c.credential_url || '',
                notes: c.notes || '', image: null, imagePreview: c.image || null,
            };
            this.editing = true; this.open = true;
        },
        close() { this.open = false; },

        pickImage(e) {
            const file = e.target.files && e.target.files[0];
            if (!file) return;
            if (!file.type.startsWith('image/')) { window.showToast && window.showToast('error', this.i18n.invalidImage); e.target.value = ''; return; }
            const reader = new FileReader();
            reader.onload = (ev) => { this.form.image = ev.target.result; this.form.imagePreview = ev.target.result; };
            reader.readAsDataURL(file);
            e.target.value = '';
        },

        async submit() {
            if (this.submitting || !this.form.title.trim()) return;
            this.submitting = true;
            const isEdit = this.editing && this.form.id;
            const url = isEdit ? (cfg.updateBase + '/' + this.form.id) : cfg.storeUrl;
            const payload = {
                _token: cfg.csrf,
                title: this.form.title, issuer: this.form.issuer,
                issue_date: this.form.issue_date || null, expiry_date: this.form.expiry_date || null,
                credential_id: this.form.credential_id, credential_url: this.form.credential_url || null,
                notes: this.form.notes,
            };
            if (this.form.image) payload.image = this.form.image;
            if (isEdit) payload._method = 'PUT';
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!res.ok || !data.success) { window.showToast && window.showToast('error', (data && data.message) || this.i18n.networkError); return; }
                const c = data.certification;
                if (isEdit) {
                    const i = this.items.findIndex(x => x.id === c.id);
                    if (i !== -1) this.items.splice(i, 1, c);
                } else {
                    this.items.unshift(c);
                }
                window.showToast && window.showToast('success', data.message);
                this.close();
            } catch (err) {
                window.showToast && window.showToast('error', this.i18n.networkError);
            } finally {
                this.submitting = false;
            }
        },

        async remove(c) {
            if (!this.canEdit) return;
            const ok = await window.confirmAction({ title: c.title, message: this.i18n.deleteConfirm, type: 'danger', confirmText: '{{ __('Delete') }}' });
            if (!ok) return;
            try {
                const res = await fetch(cfg.updateBase + '/' + c.id, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (!res.ok || !data.success) { window.showToast && window.showToast('error', (data && data.message) || this.i18n.networkError); return; }
                this.items = this.items.filter(x => x.id !== c.id);
                window.showToast && window.showToast('success', data.message);
            } catch (err) {
                window.showToast && window.showToast('error', this.i18n.networkError);
            }
        },
    };
};

// ===== Worked (work history) tab: reactive list + AJAX add/edit/delete =====
window.workManager = function (cfg) {
    const blank = () => ({ id: null, title: '', organization: '', employment_type: '', location: '', start_date: '', end_date: '', current: false, description: '' });
    return {
        items: (cfg.items || []).map(w => ({ ...w })),
        canEdit: !!cfg.canEdit,
        i18n: cfg.i18n || {},
        open: false,
        editing: false,
        submitting: false,
        form: blank(),

        openAdd() { if (!this.canEdit) return; this.form = blank(); this.editing = false; this.open = true; },

        // Ask the matched platform club to confirm this role.
        async requestVerify(w) {
            try {
                const res = await fetch(w.request_url, { method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.i18n.csrf || '{{ csrf_token() }}', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (data.success) { w.verification = 'pending'; window.showToast && window.showToast('success', data.message); }
                else { window.showToast && window.showToast('error', data.message || @js(__('Something went wrong. Please try again.'))); }
            } catch (e) { window.showToast && window.showToast('error', @js(__('Something went wrong. Please try again.'))); }
        },
        // No platform club → share the public profile so colleagues can vouch.
        async shareForVouch(url) {
            const abs = new URL(url, window.location.origin).href;
            const text = @js(__('member.vouch_share_text'));
            if (navigator.share) { try { await navigator.share({ url: abs, text }); return; } catch (e) { if (e && e.name === 'AbortError') return; } }
            try { await navigator.clipboard.writeText(abs); window.showToast && window.showToast('success', @js(__('member.link_copied'))); }
            catch (e) { window.showToast && window.showToast('info', abs); }
        },
        openEdit(w) {
            if (!this.canEdit) return;
            this.form = {
                id: w.id, title: w.title || '', organization: w.organization || '',
                employment_type: w.employment_type || '', location: w.location || '',
                start_date: w.start_date || '', end_date: w.end_date || '',
                current: !!w.current, description: w.description || '',
            };
            this.editing = true; this.open = true;
        },
        close() { this.open = false; },

        async submit() {
            if (this.submitting || !this.form.title.trim() || !this.form.organization.trim() || !this.form.start_date) return;
            this.submitting = true;
            const isEdit = this.editing && this.form.id;
            const url = isEdit ? (cfg.updateBase + '/' + this.form.id) : cfg.storeUrl;
            const payload = {
                _token: cfg.csrf,
                title: this.form.title, organization: this.form.organization,
                employment_type: this.form.employment_type || null, location: this.form.location,
                start_date: this.form.start_date,
                end_date: this.form.current ? null : (this.form.end_date || null),
                description: this.form.description,
            };
            if (isEdit) payload._method = 'PUT';
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!res.ok || !data.success) { window.showToast && window.showToast('error', (data && data.message) || this.i18n.networkError); return; }
                const w = data.work;
                if (isEdit) {
                    const i = this.items.findIndex(x => x.id === w.id);
                    if (i !== -1) this.items.splice(i, 1, w);
                } else {
                    this.items.unshift(w);
                }
                window.showToast && window.showToast('success', data.message);
                this.close();
            } catch (err) {
                window.showToast && window.showToast('error', this.i18n.networkError);
            } finally {
                this.submitting = false;
            }
        },

        async remove(w) {
            if (!this.canEdit) return;
            const ok = await window.confirmAction({ title: w.title, message: this.i18n.deleteConfirm, type: 'danger', confirmText: '{{ __('Delete') }}' });
            if (!ok) return;
            try {
                const res = await fetch(cfg.updateBase + '/' + w.id, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                const data = await res.json();
                if (!res.ok || !data.success) { window.showToast && window.showToast('error', (data && data.message) || this.i18n.networkError); return; }
                this.items = this.items.filter(x => x.id !== w.id);
                window.showToast && window.showToast('success', data.message);
            } catch (err) {
                window.showToast && window.showToast('error', this.i18n.networkError);
            }
        },
    };
};
</script>
@endsection
