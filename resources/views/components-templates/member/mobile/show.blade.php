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
    $latestMetrics = $healthMetrics($latest) + ['label' => $latest ? optional($latest->recorded_at)->format('d M Y') : null];
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
        padding: 3px; border-radius: 28px;
        box-shadow: 0 12px 30px rgba(60,20,120,.45);
    }

    /* progress ring */
    .mp-ring { position: relative; width: 60px; height: 60px; border-radius: 50%;
        background: conic-gradient(hsl(250 65% 65%) calc(var(--p) * 1%), hsl(220 14% 88%) 0);
        display: grid; place-items: center; }
    .mp-ring::before { content:""; position:absolute; width:46px; height:46px; border-radius:50%; background:#fff; }
    .mp-ring b { position: relative; font-size: 13px; font-weight: 800; color: #1f2937; }

    .mp-rail { scrollbar-width: none; }
    .mp-rail::-webkit-scrollbar { display: none; }

    .mp-reveal { opacity: 0; transform: translateY(14px); animation: mpUp .6s cubic-bezier(.2,.8,.2,1) forwards; }
    @keyframes mpUp { to { opacity: 1; transform: none; } }

    .mp-tabbar { scrollbar-width: none; }
    .mp-tabbar::-webkit-scrollbar { display: none; }
    .mp-tab.is-on { color: #fff; background: hsl(250 65% 65%); box-shadow: 0 4px 12px hsla(250,65%,55%,.4); }

    .mp-medal { background: linear-gradient(145deg, var(--c1), var(--c2)); }
</style>
@endpush

@section($inShell ? 'personal-content' : 'content')
<div class="{{ $inShell ? '-mx-4 -mt-4' : 'bg-background min-h-screen pb-10' }}" x-data="{ tab: ['#affiliations','#clubs'].includes(window.location.hash) ? 'clubs' : 'overview' }">

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
        @endphp
        <div x-data="memberFollow({{ $isFollowing ? 'true' : 'false' }}, @js(route('wall.follow', $user)), @js($user->full_name))"
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
            <div class="relative inline-block flex-shrink-0">
                <div class="mp-avatar-ring inline-block">
                    @if($user->profile_picture)
                        <img id="mpAvatarImg" src="{{ asset('storage/'.$user->profile_picture) }}?v={{ optional($user->updated_at)->timestamp }}"
                             alt="{{ $user->full_name }}" class="w-28 h-28 rounded-[25px] object-cover block">
                    @else
                        <div id="mpAvatarFallback" class="w-28 h-28 rounded-[25px] bg-white/20 grid place-items-center text-4xl font-black">{{ $initials }}</div>
                    @endif
                </div>
                <span class="absolute bottom-1 right-1 w-5 h-5 rounded-full bg-green-400 border-[3px] border-white"></span>
            </div>

            {{-- Share profile (right of the profile picture) --}}
            <button type="button" @click="shareProfile()"
                    class="m-press w-12 h-12 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-white border border-white/30"
                    aria-label="{{ __('member.share_profile') }}">
                <i class="bi bi-share text-xl"></i>
            </button>
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
        <div class="mp-rail flex gap-3 overflow-x-auto pb-1">
            {{-- attendance ring --}}
            <div class="flex-shrink-0 w-32 bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center mp-reveal" style="animation-delay:.24s">
                <div class="mp-ring" style="--p:{{ (int) $attendanceRate }}"><b>{{ (int) $attendanceRate }}%</b></div>
                <p class="text-[11px] text-muted-foreground mt-2 font-medium">{{ __('member.attendance') }}</p>
            </div>
            {{-- goals ring --}}
            <div class="flex-shrink-0 w-32 bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center mp-reveal" style="animation-delay:.28s">
                <div class="mp-ring" style="--p:{{ (int) $successRate }}"><b>{{ (int) $successRate }}%</b></div>
                <p class="text-[11px] text-muted-foreground mt-2 font-medium">{{ __('member.goal_success') }}</p>
            </div>
            {{-- sessions --}}
            <div class="flex-shrink-0 w-28 bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center justify-center mp-reveal" style="animation-delay:.32s">
                <p class="text-2xl font-black text-primary" data-count="{{ $sessionsCompleted }}">0</p>
                <p class="text-[11px] text-muted-foreground mt-1 font-medium">{{ __('member.sessions') }}</p>
            </div>
            {{-- medals --}}
            <div class="flex-shrink-0 w-28 bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center justify-center mp-reveal" style="animation-delay:.36s">
                <p class="text-2xl font-black text-amber-500" data-count="{{ $medalsTotal }}">0</p>
                <p class="text-[11px] text-muted-foreground mt-1 font-medium">{{ __('member.medals') }}</p>
            </div>
            {{-- clubs --}}
            <div class="flex-shrink-0 w-28 bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center justify-center mp-reveal" style="animation-delay:.4s">
                <p class="text-2xl font-black text-primary" data-count="{{ $totalAffiliations }}">0</p>
                <p class="text-[11px] text-muted-foreground mt-1 font-medium">{{ __('member.clubs') }}</p>
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

    {{-- ===== Medal showcase ===== --}}
    @if($medalsTotal > 0)
    <div class="px-4 mt-4"
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
                                <button type="button" @click="openAch(item)" class="m-card m-press p-3 flex items-start gap-3 w-full text-start">
                                    <span class="w-12 h-12 rounded-full bg-amber-50 grid place-items-center text-2xl flex-shrink-0" x-text="item.emoji"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold text-foreground text-sm leading-tight" x-text="item.member_award"></p>
                                        <p class="text-[11px] text-muted-foreground truncate mt-0.5"><i class="bi bi-trophy text-amber-400 mr-0.5"></i><span x-text="item.short_title"></span></p>
                                        <p x-show="item.location || item.date_label" class="text-[10px] text-muted-foreground/80 truncate mt-0.5">
                                            <i class="bi bi-geo-alt mr-0.5" x-show="item.location"></i><span x-text="[item.location, item.date_label].filter(Boolean).join(' · ')"></span>
                                        </p>
                                        <p x-show="item.club" class="text-[10px] text-muted-foreground/80 truncate" x-text="@js(__('member.award_via', ['club' => '%CLUB%'])).replace('%CLUB%', item.club || '')"></p>
                                    </div>
                                    <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/40 text-sm flex-shrink-0 self-center"></i>
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
    </div>
    @endif

    {{-- ===== Sticky tabs ===== --}}
    <div id="mpTabs" class="sticky top-14 z-30 bg-background/95 backdrop-blur mt-5 py-2">
        <div class="mp-tabbar flex gap-2 overflow-x-auto px-4">
            @foreach([
                'overview'=>__('member.tab_overview'),'health'=>__('member.tab_health'),'goals'=>__('member.tab_goals'),
                'clubs'=>__('member.tab_clubs'),'attendance'=>__('member.tab_attendance'),'billing'=>__('member.tab_billing')
            ] as $key=>$label)
                <button @click="tab='{{ $key }}'"
                        class="mp-tab flex-shrink-0 px-4 py-2 rounded-full text-sm font-semibold text-muted-foreground bg-white border border-gray-100 transition-all"
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
                        <i class="bi bi-key"></i> {{ __('member.set_password') }}
                    </button>
                    <button type="button" @click="generate()" :disabled="busy"
                            class="m-press flex items-center justify-center gap-2 py-2.5 rounded-xl bg-primary text-white text-sm font-semibold active:bg-primary/90 transition-colors disabled:opacity-60">
                        <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-magic'"></i> {{ __('member.generate_password') }}
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

            {{-- Emergency contacts --}}
            @if(!empty($user->emergency_contacts))
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
             x-data="weightLogger({ url: '{{ route('member.store-health', $user->id) }}', csrf: '{{ csrf_token() }}', today: '{{ now()->format('Y-m-d') }}', rows: @js($weightRows), latest: @js($latestMetrics), prev: @js($prevMetrics), gender: @js(strtolower($user->gender ?? '')), age: {{ (int) ($age ?? 0) }}, divisions: @js(config('taekwondo_divisions', [])), i18n: { upTo: @js(__('member.wc_up_to')), over: @js(__('member.wc_over')), range: @js(__('member.wc_range')), headroom: @js(__('member.wc_headroom')) } })">
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
                <p class="text-[11px] text-muted-foreground text-center">{{ __('member.last_recorded') }} <span x-text="latest.label || @js(optional($latest->recorded_at)->format('d M Y'))">{{ optional($latest->recorded_at)->format('d M Y') }}</span></p>
            @else
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center"><i class="bi bi-heart-pulse text-3xl text-gray-300"></i><p class="text-sm text-muted-foreground mt-2">{{ __('member.no_health_records') }}</p></div>
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
                                    <span x-text="classify(latest.weight).label + ' kg'"></span>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/15 text-primary" x-text="classify(latest.weight).age_group"></span>
                                </p>
                                <p class="text-[11px] text-muted-foreground mt-0.5">
                                    <span x-text="classRange(classify(latest.weight))"></span><span x-show="gender"> · </span><span class="capitalize" x-text="gender"></span>
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
                                    <p class="text-[10px] text-muted-foreground mt-1" x-text="row.label"></p>
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
        <div x-show="tab==='goals'" x-transition.opacity x-cloak class="space-y-3">
            <div class="grid grid-cols-3 gap-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3 text-center"><p class="text-xl font-black text-primary">{{ $activeGoalsCount }}</p><p class="text-[10px] text-muted-foreground">{{ __('member.goals_active') }}</p></div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3 text-center"><p class="text-xl font-black text-green-600">{{ $completedGoalsCount }}</p><p class="text-[10px] text-muted-foreground">{{ __('member.goals_done') }}</p></div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3 text-center"><p class="text-xl font-black text-amber-500">{{ $successRate }}%</p><p class="text-[10px] text-muted-foreground">{{ __('member.goals_success') }}</p></div>
            </div>
            @forelse($goals as $g)
                @php
                    $tv = (float) ($g->target_value ?: 0); $cv = (float) ($g->current_progress_value ?: 0);
                    $pct = $tv > 0 ? min(100, round($cv / $tv * 100)) : ($g->status === 'completed' ? 100 : 0);
                @endphp
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold text-foreground">{{ $g->title }}</p>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium capitalize flex-shrink-0 {{ $g->status==='completed'?'bg-green-100 text-green-700':($g->status==='active'?'bg-accent text-primary':'bg-gray-100 text-gray-500') }}">{{ str_replace('_',' ',$g->status) }}</span>
                    </div>
                    <div class="mt-2 h-2 rounded-full bg-muted overflow-hidden"><div class="h-full rounded-full bg-primary" style="width: {{ $pct }}%"></div></div>
                    <p class="text-[11px] text-muted-foreground mt-1">{{ $cv }} / {{ $tv ?: '—' }} {{ $g->unit }} · {{ $pct }}%</p>
                </div>
            @empty
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center"><i class="bi bi-bullseye text-3xl text-gray-300"></i><p class="text-sm text-muted-foreground mt-2">{{ __('member.no_goals') }}</p></div>
            @endforelse
        </div>

        {{-- ===== Tournaments ===== --}}
        <div x-show="tab==='tournaments'" x-transition.opacity x-cloak class="space-y-3">
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
                        </div>
                    </div>
                </div>
            @empty
                @if(($awardedAchievements ?? collect())->isEmpty())
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center"><i class="bi bi-trophy text-3xl text-gray-300"></i><p class="text-sm text-muted-foreground mt-2">{{ __('member.no_tournaments') }}</p></div>
                @endif
            @endforelse
        </div>

        {{-- ===== Clubs / affiliations ===== --}}
        <div x-show="tab==='clubs'" x-transition.opacity x-cloak class="space-y-4">
            @php
                $activeAffil = $clubAffiliations->whereNull('end_date')->sortByDesc('start_date')->values();
                $leftAffil   = $clubAffiliations->whereNotNull('end_date')->sortByDesc('end_date')->values();
            @endphp

            {{-- Active --}}
            <div>
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">{{ __('member.active_clubs') }}</p>
                    @if($activeAffil->count())<span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700">{{ $activeAffil->count() }}</span>@endif
                </div>
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
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                        <i class="bi bi-diagram-3 text-2xl text-gray-300"></i>
                        <p class="text-sm text-muted-foreground mt-2">{{ __('member.not_active_in_club') }}</p>
                    </div>
                @endforelse
            </div>

            {{-- History — clubs left --}}
            @if($leftAffil->count())
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">{{ __('member.clubs_you_left') }}</p>
                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">{{ $leftAffil->count() }}</span>
                    </div>
                    @foreach($leftAffil as $a)
                        @php
                            $span = ($a->start_date && $a->end_date) ? $a->start_date->diffInMonths($a->end_date) : null;
                            $clubUrl = ($a->tenant && $a->tenant->slug && $a->tenant->country) ? route('clubs.show', ['country' => strtolower($a->tenant->country), 'slug' => $a->tenant->slug]) : null;
                            $tag = $clubUrl ? 'a' : 'div';
                        @endphp
                        <{{ $tag }} @if($clubUrl) href="{{ $clubUrl }}" @endif class="group relative block bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-2.5 overflow-hidden {{ $clubUrl ? 'm-press' : '' }}">
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
            @endif

            @if($clubAffiliations->isEmpty())
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center"><i class="bi bi-diagram-3 text-3xl text-gray-300"></i><p class="text-sm text-muted-foreground mt-2">{{ __('member.no_affiliations') }}</p></div>
            @endif
        </div>

        {{-- ===== Attendance ===== --}}
        <div x-show="tab==='attendance'" x-transition.opacity x-cloak class="space-y-3">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-5">
                <div class="mp-ring" style="--p:{{ (int) $attendanceRate }}; width:84px; height:84px;"><b style="font-size:18px">{{ (int) $attendanceRate }}%</b></div>
                <div class="flex-1 space-y-2">
                    <div class="flex justify-between text-sm"><span class="text-muted-foreground">{{ __('member.completed') }}</span><span class="font-bold text-green-600">{{ $sessionsCompleted }}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-muted-foreground">{{ __('member.no_shows') }}</span><span class="font-bold text-red-500">{{ $noShows }}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-muted-foreground">{{ __('member.total_sessions') }}</span><span class="font-bold">{{ $attendanceRecords->count() }}</span></div>
                </div>
            </div>
            @foreach($attendanceRecords->take(8) as $rec)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 flex items-center gap-3">
                    <span class="w-2 h-2 rounded-full {{ $rec->status==='completed'?'bg-green-500':($rec->status==='no_show'?'bg-red-500':'bg-amber-400') }}"></span>
                    <span class="text-sm text-foreground flex-1 truncate capitalize">{{ str_replace('_',' ',$rec->status) }}</span>
                    <span class="text-xs text-muted-foreground">{{ optional($rec->session_datetime)->format('d M, H:i') }}</span>
                </div>
            @endforeach
        </div>

        {{-- ===== Billing ===== --}}
        <div x-show="tab==='billing'" x-transition.opacity x-cloak class="space-y-3">
            @forelse($invoices as $invoice)
                <a href="{{ route('bills.receipt', $invoice->id) }}" class="block bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold text-foreground truncate">{{ $invoice->tenant->club_name ?? __('member.invoice') }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ optional($invoice->created_at)->format('d M Y') }}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="font-black text-foreground leading-none">{{ $invoice->amount }}</p>
                            <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $invoice->status==='paid'?'bg-green-100 text-green-700':($invoice->status==='due'?'bg-amber-100 text-amber-700':'bg-gray-100 text-gray-600') }}">{{ ucfirst($invoice->status) }}</span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center"><i class="bi bi-receipt text-3xl text-gray-300"></i><p class="text-sm text-muted-foreground mt-2">{{ __('member.no_invoices') }}</p></div>
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
                    return { age_group: g, label: list[i].label, min: list[i].min, max: list[i].max };
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
                  self.latest = { weight: rec.weight, height: rec.height, bmi: rec.bmi, label: rec.recorded_label };
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

// Follow / share controls flanking the profile picture in the hero.
window.memberFollow = function (initial, followUrl, name) {
    return {
        following: initial,
        busy: false,

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
</script>
@endsection
