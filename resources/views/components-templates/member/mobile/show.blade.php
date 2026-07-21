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
        padding: 3px; border-radius: 25px;
        box-shadow: 0 12px 30px rgba(60,20,120,.45);
    }

    /* progress ring */
    .mp-ring { position: relative; width: 60px; height: 60px; border-radius: 50%;
        background: conic-gradient(hsl(250 65% 65%) calc(var(--p) * 1%), hsl(220 14% 88%) 0);
        display: grid; place-items: center; }
    .mp-ring::before { content:""; position:absolute; width:46px; height:46px; border-radius:50%; background:#fff; }
    .mp-ring b { position: relative; font-size: 13px; font-weight: 800; color: #1f2937; }

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
<div class="{{ $inShell ? '-mx-4 -mt-4' : 'bg-background min-h-screen pb-10' }}" x-data="{ tab: ['#affiliations','#clubs'].includes(window.location.hash) ? 'clubs' : 'overview', goTab(t){ this.tab = t; this.$nextTick(() => document.getElementById('mpTabs')?.scrollIntoView({behavior:'smooth', block:'start'})); } }">

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
            <div class="relative inline-block flex-shrink-0" x-data="{ zoom: false }">
                <div class="mp-avatar-ring inline-block">
                    @if($user->profile_picture)
                        <img id="mpAvatarImg" src="{{ asset('storage/'.$user->profile_picture) }}?v={{ optional($user->updated_at)->timestamp }}"
                             alt="{{ $user->full_name }}" class="w-28 aspect-[3/4] rounded-[22px] object-cover block cursor-pointer" @click="zoom=true">
                    @else
                        <div id="mpAvatarFallback" class="w-28 aspect-[3/4] rounded-[22px] bg-white/20 grid place-items-center text-4xl font-black">{{ $initials }}</div>
                    @endif
                </div>
                <span class="absolute bottom-1 right-1 w-5 h-5 rounded-full bg-green-400 border-[3px] border-white"></span>

                @if($user->profile_picture)
                    {{-- Full, uncropped view of the profile picture — tap the avatar to open, tap away to close --}}
                    <template x-teleport="body">
                        <div x-show="zoom" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center p-4" @click="zoom=false" @keydown.escape.window="zoom=false">
                            <div x-show="zoom" x-transition.opacity class="absolute inset-0 bg-black/90"></div>
                            <button type="button" class="absolute top-4 right-4 rtl:right-auto rtl:left-4 w-10 h-10 rounded-full bg-white/10 text-white grid place-items-center z-10" @click.stop="zoom=false"><i class="bi bi-x-lg text-lg"></i></button>
                            <img x-show="zoom" x-transition src="{{ asset('storage/'.$user->profile_picture) }}?v={{ optional($user->updated_at)->timestamp }}"
                                 alt="{{ $user->full_name }}" class="relative rounded-2xl object-contain" style="max-width:80vw; max-height:85vh;" @click.stop>
                        </div>
                    </template>
                @endif
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
        {{-- Each stat card jumps to its matching profile section/tab. Tournaments &
             attendance have no tab button, so the cards are the only way to reach them. --}}
        <div class="mp-rail flex gap-3 overflow-x-auto pb-1">
            {{-- attendance ring --}}
            <div role="button" tabindex="0" @click="goTab('attendance')" @keydown.enter.space.prevent="goTab('attendance')"
                 class="mp-card m-press cursor-pointer bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center mp-reveal" style="animation-delay:.24s">
                <div class="mp-ring" style="--p:{{ (int) $attendanceRate }}"><b>{{ (int) $attendanceRate }}%</b></div>
                <p class="text-[11px] text-muted-foreground mt-2 font-medium">{{ __('member.attendance') }}</p>
            </div>
            {{-- goals ring --}}
            <div role="button" tabindex="0" @click="goTab('goals')" @keydown.enter.space.prevent="goTab('goals')"
                 class="mp-card m-press cursor-pointer bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center mp-reveal" style="animation-delay:.28s">
                <div class="mp-ring" style="--p:{{ (int) $successRate }}"><b>{{ (int) $successRate }}%</b></div>
                <p class="text-[11px] text-muted-foreground mt-2 font-medium">{{ __('member.goal_success') }}</p>
            </div>
            {{-- challenge win rate — opens this member's Challenges list in-page --}}
            <div role="button" tabindex="0" @click="goTab('challenges')" @keydown.enter.space.prevent="goTab('challenges')"
                 class="mp-card m-press cursor-pointer bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center mp-reveal" style="animation-delay:.3s">
                <div class="mp-ring" style="--p:{{ (int) $challengeWinRate }}"><b>{{ (int) $challengeWinRate }}%</b></div>
                <p class="text-[11px] text-muted-foreground mt-2 font-medium">{{ __('member.challenge') }}</p>
            </div>
        </div>
    </div>

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

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-foreground flex items-center gap-2"><i class="bi bi-person-vcard text-primary"></i> {{ __('member.personal') }}</h3>
                    @if($canEditBasic ?? false)
                        <button type="button" @click="$dispatch('open-profile-modal')"
                                class="m-press inline-flex items-center gap-1.5 text-primary text-sm font-semibold">
                            <i class="bi bi-pencil-square"></i> {{ __('member.edit') }}
                        </button>
                    @endif
                </div>
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
                        @if(($allSkills ?? collect())->count())
                            <div class="flex flex-wrap gap-1.5 mt-1">
                                @foreach($allSkills as $skillName)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">{{ $skillName }}</span>
                                @endforeach
                            </div>
                        @else
                            <p class="font-semibold">—</p>
                        @endif
                    </div>
                </div>
            </div>
            @if($user->email || $phone)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 space-y-2.5">
                <h3 class="font-bold text-foreground mb-1 flex items-center gap-2"><i class="bi bi-telephone text-primary"></i> {{ __('member.contact') }}</h3>
                @if($user->email)<a href="mailto:{{ $user->email }}" class="flex items-center gap-3 text-sm"><span class="w-8 h-8 rounded-lg bg-accent grid place-items-center text-primary"><i class="bi bi-envelope"></i></span><span class="truncate">{{ $user->email }}</span></a>@endif
                @if($phone)<a href="tel:{{ $phone }}" class="flex items-center gap-3 text-sm"><span class="w-8 h-8 rounded-lg bg-accent grid place-items-center text-primary"><i class="bi bi-phone"></i></span><span dir="ltr">{{ $phone }}</span></a>@endif
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

            {{-- ===== Weight tracking ===== --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-bold text-foreground flex items-center gap-2"><i class="bi bi-graph-up-arrow text-primary"></i> {{ __('member.weight_history') }}</h3>
                    @if($canEditBasic)
                        <button type="button" @click="openAdd()" class="m-press inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary text-white text-xs font-bold active:bg-primary/90">
                            <i class="bi bi-plus-lg"></i>{{ __('member.add_weight') }}
                        </button>
                    @endif
                </div>

                {{-- Taekwondo weight-class card — reflects the latest weight, updates live --}}
                <template x-if="classify(latest.weight)">
                    <div class="mb-3 rounded-2xl border border-primary/20 bg-gradient-to-br from-primary/5 to-accent/40 p-3.5 overflow-hidden relative">
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

                <template x-if="rows.length">
                    <div class="space-y-1.5">
                        <template x-for="(row,i) in rows" :key="row.date + '-' + i">
                            <div class="flex items-center gap-3 rounded-xl border border-gray-100 bg-white px-3 py-2.5">
                                <span class="w-10 h-10 rounded-xl bg-accent grid place-items-center text-primary flex-shrink-0"><i class="bi bi-speedometer2"></i></span>
                                <div class="min-w-0 flex-1">
                                    <p class="flex items-center gap-1.5 flex-wrap leading-none">
                                        <span class="text-base font-black text-foreground tabular-nums" x-text="Number(row.weight).toFixed(1)"></span>
                                        <span class="text-[10px] font-semibold text-muted-foreground">kg</span>
                                        {{-- Taekwondo division at that weight --}}
                                        <template x-if="classify(row.weight)">
                                            <span class="inline-flex items-center text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-primary/10 text-primary" x-text="classify(row.weight).label"></span>
                                        </template>
                                    </p>
                                    <p class="text-[10px] text-muted-foreground mt-1"><span x-text="row.label"></span><span x-show="ago(row.date)" class="text-muted-foreground/70"> · <span x-text="ago(row.date)"></span></span></p>
                                </div>
                                {{-- Δ vs the previous (older) reading --}}
                                <template x-if="delta(i) !== null">
                                    <span class="inline-flex items-center gap-0.5 text-[11px] font-bold px-2 py-0.5 rounded-full"
                                          :class="delta(i) === 0 ? 'bg-gray-100 text-gray-500' : (delta(i) < 0 ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-500')">
                                        <i class="bi" :class="delta(i) === 0 ? 'bi-dash' : (delta(i) < 0 ? 'bi-arrow-down-short' : 'bi-arrow-up-short')"></i><span x-text="Math.abs(delta(i)).toFixed(1) + ' kg'"></span>
                                    </span>
                                </template>
                                <template x-if="delta(i) === null">
                                    <span class="text-[9px] font-semibold text-muted-foreground/70 px-2">{{ __('member.first_reading') }}</span>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="!rows.length">
                    <p class="text-sm text-muted-foreground text-center py-4">{{ __('member.no_weight_records') }}</p>
                </template>
            </div>

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
             })">
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
            @if($isSelf && $hasTournamentContent)
                {{-- Records exist → a small circular "add more" tucked in the top corner. --}}
                <div class="flex justify-end -mb-1">
                    <button type="button" @click="openAdd()" aria-label="{{ __('Add achievement') }}"
                            class="m-press w-9 h-9 rounded-full bg-primary text-white grid place-items-center shadow-md shadow-primary/25 hover:bg-primary/90 transition-colors flex-shrink-0">
                        <i class="bi bi-plus-lg"></i>
                    </button>
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
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex flex-col items-center justify-center w-12 flex-shrink-0">
                            <span class="text-lg font-black text-primary leading-none">{{ optional($t->date)->format('d') }}</span>
                            <span class="text-[10px] uppercase text-muted-foreground">{{ optional($t->date)->format('M') }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-foreground truncate">{{ $t->title }}</p>
                            <p class="text-xs text-muted-foreground truncate">{{ $t->sport }}@if($t->location) · {{ $t->location }}@endif</p>
                            @if($t->performanceResults->count())
                                <div class="flex flex-wrap gap-1 mt-2">
                                    @foreach($t->performanceResults as $r)
                                        @php $mc = ['1st'=>'bg-amber-100 text-amber-700','2nd'=>'bg-slate-100 text-slate-600','3rd'=>'bg-orange-100 text-orange-700','special'=>'bg-accent text-primary']; @endphp
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $mc[$r->medal_type] ?? 'bg-gray-100 text-gray-600' }}"><i class="bi bi-award-fill mr-0.5"></i>{{ ucfirst($r->medal_type) }}</span>
                                    @endforeach
                                </div>
                            @endif
                            {{-- Provenance: honest state + evidence + request action --}}
                            <div class="mt-2 flex items-center gap-2 flex-wrap" data-verify-row="{{ $t->uuid }}">
                                <x-verification-badge data-verify-badge :status="$t->verification_status" :club="$t->verifiedByTenant?->tr('club_name') ?? $t->verifiedByTenant?->club_name" />
                                @if($t->evidence_path)
                                    <a href="{{ route('member.tournament.evidence', [$t->user_id, $t->uuid]) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[11px] text-muted-foreground hover:text-primary"><i class="bi bi-paperclip"></i>{{ __('Evidence') }}</a>
                                @endif
                                @if($isSelf && $t->clubAffiliation?->tenant_id && ! in_array($t->verification_status, ['verified','pending']))
                                    <button type="button" data-verify-btn @click="requestVerify($el, '{{ route('member.tournament.request-verification', [$t->user_id, $t->uuid]) }}')" class="inline-flex items-center gap-1 text-[11px] font-medium text-primary"><i class="bi bi-patch-check"></i>{{ __('Request verification') }}</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                @if(($awardedAchievements ?? collect())->isEmpty())
                <div id="mobileTournamentsEmpty" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-bold text-foreground flex items-center gap-2"><i class="bi bi-trophy text-primary"></i> {{ __('member.tab_tournaments') }}</h3>
                        @if($isSelf)
                            <button type="button" @click="openAdd()" class="m-press inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary text-white text-xs font-bold active:bg-primary/90">
                                <i class="bi bi-plus-lg"></i>{{ __('Add achievement') }}
                            </button>
                        @endif
                    </div>
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
                                <h3 class="font-bold text-foreground">{{ __('Add achievement') }}</h3>
                                <button type="button" @click="addOpen=false" class="w-8 h-8 rounded-full grid place-items-center text-muted-foreground hover:bg-muted"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                        {{-- Body --}}
                        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
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
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('Club') }} <span class="text-xs font-normal text-gray-400">({{ __('for verification') }})</span></label>
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
                                <span x-show="!saving">{{ __('Save achievement') }}</span>
                                <span x-show="saving"><i class="bi bi-arrow-repeat animate-spin"></i></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- ===== Clubs / affiliations ===== --}}
        <div x-show="tab==='clubs'" x-transition.opacity x-cloak class="space-y-4">
            @php
                $activeAffil = $clubAffiliations->whereNull('end_date')->sortByDesc('start_date')->values();
                $leftAffil   = $clubAffiliations->whereNotNull('end_date')->sortByDesc('end_date')->values();
            @endphp

            {{-- Active --}}
            <div>
                @forelse($activeAffil as $a)
                    @php $clubUrl = ($a->tenant && $a->tenant->slug && $a->tenant->country) ? route('clubs.show', ['country' => strtolower($a->tenant->country), 'slug' => $a->tenant->slug]) : null; $tag = $clubUrl ? 'a' : 'div'; @endphp
                    <{{ $tag }} @if($clubUrl) href="{{ $clubUrl }}" @endif class="group relative block bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-2.5 overflow-hidden {{ $clubUrl ? 'm-press' : '' }}">
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
                                <div class="mt-1.5 flex flex-col gap-1 text-[11px] text-muted-foreground">
                                    <span class="inline-flex items-center gap-1.5"><i class="bi bi-calendar3 text-muted-foreground/70"></i>{{ __('member.since') }} {{ optional($a->start_date)->format('M Y') ?: '—' }}</span>
                                    @if($a->location)
                                        <span class="inline-flex items-center gap-1.5 min-w-0"><i class="bi bi-geo-alt text-muted-foreground/70 flex-shrink-0"></i><span class="truncate">{{ $a->location }}</span></span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @if($a->skillAcquisitions->count())
                            <div class="flex flex-wrap gap-1.5 mt-3 pt-3 border-t border-gray-50">
                                @foreach($a->skillAcquisitions->take(6) as $s)
                                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">{{ $s->skill_name }}</span>
                                @endforeach
                            </div>
                        @endif
                    </{{ $tag }}>
                @empty
                    @if($leftAffil->isEmpty())
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-bold text-foreground flex items-center gap-2"><i class="bi bi-diagram-3 text-primary"></i> {{ __('member.active_clubs') }}</h3>
                            </div>
                            <p class="text-sm text-muted-foreground text-center py-4">{{ __('member.not_active_in_club') }}</p>
                        </div>
                    @endif
                @endforelse
            </div>

            {{-- Previous clubs --}}
            @if($leftAffil->isNotEmpty())
            <div>
                <div class="flex items-center gap-2.5 mb-2">
                    <span class="w-9 h-9 rounded-xl bg-muted grid place-items-center flex-shrink-0"><i class="bi bi-clock-history text-muted-foreground"></i></span>
                    <span class="text-sm font-semibold text-foreground truncate">{{ __('member.previous_clubs') }}</span>
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 flex-shrink-0">{{ $leftAffil->count() }}</span>
                </div>

                <div class="space-y-2.5">
                    @foreach($leftAffil as $a)
                        @php
                            $span = ($a->start_date && $a->end_date) ? $a->start_date->diffInMonths($a->end_date) : null;
                            $clubUrl = ($a->tenant && $a->tenant->slug && $a->tenant->country) ? route('clubs.show', ['country' => strtolower($a->tenant->country), 'slug' => $a->tenant->slug]) : null;
                            $tag = $clubUrl ? 'a' : 'div';
                        @endphp
                        <{{ $tag }} @if($clubUrl) href="{{ $clubUrl }}" @endif class="group relative block bg-white rounded-2xl shadow-sm border border-gray-100 p-4 overflow-hidden {{ $clubUrl ? 'm-press' : '' }}">
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
                                    <div class="mt-1.5 flex flex-col gap-1 text-[11px] text-muted-foreground">
                                        <span class="inline-flex items-center gap-1.5"><i class="bi bi-calendar-range text-muted-foreground/70"></i>{{ optional($a->start_date)->format('M Y') ?: '—' }} – {{ optional($a->end_date)->format('M Y') }}</span>
                                        @if($span !== null)
                                            <span class="inline-flex items-center gap-1.5"><i class="bi bi-hourglass-split text-muted-foreground/70"></i>{{ $span }} {{ \Illuminate\Support\Str::plural('month', max(1,$span)) }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </{{ $tag }}>
                    @endforeach
                </div>
            </div>
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

            @if($canEditBasic ?? false)
                <div class="flex justify-end -mb-1" x-show="items.length">
                    <button type="button" @click="openAdd()" aria-label="{{ __('member.add_certification') }}"
                            class="m-press w-9 h-9 rounded-full bg-primary text-white grid place-items-center shadow-md shadow-primary/25 hover:bg-primary/90 transition-colors flex-shrink-0">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            @endif

            {{-- Empty state --}}
            <template x-if="!items.length">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-bold text-foreground flex items-center gap-2"><i class="bi bi-patch-check text-primary"></i> {{ __('member.certifications') }}</h3>
                        @if($canEditBasic ?? false)
                            <button type="button" @click="openAdd()" class="m-press inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary text-white text-xs font-bold active:bg-primary/90">
                                <i class="bi bi-plus-lg"></i>{{ __('member.add_certification') }}
                            </button>
                        @endif
                    </div>
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
            $workJs = $workHistory->map(fn ($w) => [
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
            ])->values();
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

            @if($canEditBasic ?? false)
                <div class="flex justify-end -mb-1" x-show="items.length">
                    <button type="button" @click="openAdd()" aria-label="{{ __('member.add_work') }}"
                            class="m-press w-9 h-9 rounded-full bg-primary text-white grid place-items-center shadow-md shadow-primary/25 hover:bg-primary/90 transition-colors flex-shrink-0">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
            @endif

            {{-- Empty state --}}
            <template x-if="!items.length">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-bold text-foreground flex items-center gap-2"><i class="bi bi-briefcase text-primary"></i> {{ __('member.work_history') }}</h3>
                        @if($canEditBasic ?? false)
                            <button type="button" @click="openAdd()" class="m-press inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary text-white text-xs font-bold active:bg-primary/90">
                                <i class="bi bi-plus-lg"></i>{{ __('member.add_work') }}
                            </button>
                        @endif
                    </div>
                    <p class="text-sm text-muted-foreground text-center py-4">{{ __('member.no_work') }}</p>
                </div>
            </template>

            {{-- Timeline list --}}
            <template x-for="w in items" :key="w.id">
                <div class="group relative bg-white rounded-2xl shadow-sm border border-gray-100 p-4 overflow-hidden">
                    <span class="absolute inset-y-0 left-0 rtl:left-auto rtl:right-0 w-1" :class="w.current ? 'bg-green-400/80' : 'bg-gray-300'"></span>
                    <div class="flex items-start gap-3">
                        <span class="w-11 h-11 rounded-xl bg-accent grid place-items-center text-primary flex-shrink-0 ring-1 ring-primary/10"><i class="bi bi-briefcase-fill"></i></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-bold text-foreground text-[15px] leading-snug" x-text="w.title"></p>
                                <template x-if="w.current">
                                    <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700"><span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>{{ __('member.work_current') }}</span>
                                </template>
                            </div>
                            <p class="text-[12px] font-medium text-foreground/70 mt-0.5" x-text="w.organization"></p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                                <span class="inline-flex items-center gap-1.5"><i class="bi bi-calendar-range text-muted-foreground/70"></i><span x-text="w.start_label + ' – ' + (w.end_label || i18n.present)"></span></span>
                                <span class="inline-flex items-center gap-1.5" x-show="w.employment_type"><i class="bi bi-person-badge text-muted-foreground/70"></i><span x-text="w.employment_type"></span></span>
                                <span class="inline-flex items-center gap-1.5 min-w-0" x-show="w.location"><i class="bi bi-geo-alt text-muted-foreground/70 flex-shrink-0"></i><span class="truncate" x-text="w.location"></span></span>
                            </div>
                            <p class="text-[11px] text-foreground/70 mt-2 whitespace-pre-line" x-show="w.description" x-text="w.description"></p>
                            <template x-if="canEdit">
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
    <x-profile-modal
        :user="$relationship->dependent"
        :formAction="$relationship->relationship_type === 'admin_view' ? route('admin.platform.members.update', $relationship->dependent->id) : route('member.update', $relationship->dependent->id)"
        formMethod="PUT"
        :cancelUrl="null"
        :uploadUrl="$relationship->relationship_type === 'admin_view' ? route('admin.platform.members.upload-picture', $relationship->dependent->id) : route('member.upload-picture', $relationship->dependent->id)"
        :showRelationshipFields="$relationship->relationship_type !== 'admin_view' && $relationship->relationship_type !== 'self'"
        :relationship="$relationship"
    />
@endif

{{-- Scripts live INSIDE the content section (not @push) so they ship with #shell-content --}}
{{-- and re-run on the mobile shell's AJAX swaps — @push('scripts') would be dropped there. --}}
<script>
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
        blankForm() {
            return { title: '', sport: '', date: '', type: 'tournament', location: '', club_affiliation_id: null, medal_type: '' };
        },
        openAdd() {
            this.form = this.blankForm();
            this.evidence = null; this.evidenceName = ''; this.evidencePreview = '';
            this.addOpen = true;
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
            try {
                var res = await fetch(cfg.storeUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
                var data = await res.json();
                if (data.success) {
                    this.prependCard(data.tournament);
                    this.addOpen = false;
                    window.showToast && window.showToast('success', @js(__('Achievement added.')));
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
                return '<span class="px-2 py-0.5 rounded-full text-[10px] font-semibold ' + (mc[r.medal_type] || 'bg-gray-100 text-gray-600') + '"><i class="bi bi-award-fill mr-0.5"></i>' + esc(r.medal_type ? r.medal_type.charAt(0).toUpperCase() + r.medal_type.slice(1) : '') + '</span>';
            }).join('');
            var v = t.verification || {};
            var verifyExtra = '';
            if (v.evidence_url) verifyExtra += '<a href="' + esc(v.evidence_url) + '" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[11px] text-muted-foreground hover:text-primary"><i class="bi bi-paperclip"></i>' + @js(__('Evidence')) + '</a>';
            if (v.can_request && v.request_url) verifyExtra += '<button type="button" data-verify-btn onclick="window.requestAchievementVerification(this)" data-verify-url="' + esc(v.request_url) + '" class="inline-flex items-center gap-1 text-[11px] font-medium text-primary"><i class="bi bi-patch-check"></i>' + @js(__('Request verification')) + '</button>';
            var d = t.date ? new Date(t.date) : null;
            var day = d ? String(d.getDate()).padStart(2, '0') : '—';
            var mon = d ? d.toLocaleString('en', { month: 'short' }) : '';
            var html =
                '<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4"><div class="flex items-start gap-3">' +
                '<div class="flex flex-col items-center justify-center w-12 flex-shrink-0"><span class="text-lg font-black text-primary leading-none">' + day + '</span><span class="text-[10px] uppercase text-muted-foreground">' + mon + '</span></div>' +
                '<div class="min-w-0 flex-1"><p class="font-semibold text-foreground truncate">' + esc(t.title) + '</p>' +
                '<p class="text-xs text-muted-foreground truncate">' + esc(t.sport) + (t.location ? ' · ' + esc(t.location) : '') + '</p>' +
                (medals ? '<div class="flex flex-wrap gap-1 mt-2">' + medals + '</div>' : '') +
                '<div class="mt-2 flex items-center gap-2 flex-wrap" data-verify-row="' + esc(t.uuid || '') + '">' + window.verifyBadgeHtml(v.status || 'self_reported', v.verified_club) + verifyExtra + '</div>' +
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
