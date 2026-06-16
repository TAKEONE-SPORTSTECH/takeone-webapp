@extends('layouts.app')

@section('hide-navbar', true)
@section('title', $user->full_name)

@php
    use Illuminate\Support\Carbon;
    $age = $user->birthdate ? Carbon::parse($user->birthdate)->age : null;
    $initials = strtoupper(mb_substr($user->full_name ?? 'M', 0, 1));
    $medalsTotal = array_sum($awardCounts);
    $latest = $latestHealthRecord;
    $prev = $comparisonRecords->count() > 1 ? $comparisonRecords[1] : null;
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

@section('content')
<div class="bg-background min-h-screen pb-10" x-data="{ tab: 'overview' }">

    {{-- ===== Sticky glass top bar ===== --}}
    <div class="fixed top-0 inset-x-0 z-50 flex items-center justify-between px-4 h-14 backdrop-blur-md bg-white/10">
        <button type="button" onclick="history.length>1 ? history.back() : window.location.href='{{ url('/') }}'"
                class="w-10 h-10 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-white border border-white/30">
            <i class="bi bi-arrow-left text-lg"></i>
        </button>
        <div class="flex items-center gap-2">
            @if(Auth::user()->isSuperAdmin())
                <a href="{{ route('admin.platform.index') }}" title="Admin Panel"
                   class="w-10 h-10 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-white border border-white/30">
                    <i class="bi bi-shield-check text-base"></i>
                </a>
            @endif
            <button type="button" onclick="navigator.share ? navigator.share({title:'{{ addslashes($user->full_name) }}', url:location.href}) : (window.showToast && window.showToast('info','Link: '+location.href))"
                    class="w-10 h-10 rounded-full bg-white/20 backdrop-blur flex items-center justify-center text-white border border-white/30">
                <i class="bi bi-share text-base"></i>
            </button>
        </div>
    </div>

    {{-- ===== Hero ===== --}}
    <header class="mp-hero pt-20 pb-8 px-5 text-white text-center">
        <div class="mp-hero-glow" style="background:#fff; top:-60px; left:-40px;"></div>
        <div class="mp-hero-glow" style="background:hsl(280 80% 70%); bottom:-80px; right:-40px;"></div>

        <div class="relative inline-block mp-reveal" style="animation-delay:.05s">
            <div class="mp-avatar-ring inline-block">
                @if($user->profile_picture)
                    <img src="{{ asset('storage/'.$user->profile_picture) }}?v={{ optional($user->updated_at)->timestamp }}"
                         alt="{{ $user->full_name }}" class="w-28 h-28 rounded-[25px] object-cover block">
                @else
                    <div class="w-28 h-28 rounded-[25px] bg-white/20 grid place-items-center text-4xl font-black">{{ $initials }}</div>
                @endif
            </div>
            <span class="absolute bottom-1 right-1 w-5 h-5 rounded-full bg-green-400 border-[3px] border-white"></span>
        </div>

        <h1 class="text-2xl font-black mt-4 mp-reveal" style="animation-delay:.12s">{{ $user->full_name }}</h1>
        @if($user->motto || $user->bio)
            <p class="text-sm text-white/85 mt-1 max-w-xs mx-auto mp-reveal" style="animation-delay:.16s">“{{ \Illuminate\Support\Str::limit($user->motto ?: $user->bio, 80) }}”</p>
        @endif

        {{-- chips --}}
        <div class="flex flex-wrap items-center justify-center gap-2 mt-4 mp-reveal" style="animation-delay:.2s">
            @if($age)<span class="px-3 py-1 rounded-full bg-white/15 border border-white/25 text-xs font-semibold backdrop-blur">{{ $age }} yrs</span>@endif
            @if($user->gender)<span class="px-3 py-1 rounded-full bg-white/15 border border-white/25 text-xs font-semibold capitalize backdrop-blur">{{ $user->gender }}</span>@endif
            <span class="px-3 py-1 rounded-full bg-white/15 border border-white/25 text-xs font-semibold backdrop-blur">🥇 {{ $awardCounts['1st'] ?? 0 }}</span>
            <span class="px-3 py-1 rounded-full bg-white/15 border border-white/25 text-xs font-semibold backdrop-blur">🥈 {{ $awardCounts['2nd'] ?? 0 }}</span>
            <span class="px-3 py-1 rounded-full bg-white/15 border border-white/25 text-xs font-semibold backdrop-blur">🥉 {{ $awardCounts['3rd'] ?? 0 }}</span>
            <span class="px-3 py-1 rounded-full bg-white/15 border border-white/25 text-xs font-semibold backdrop-blur">🏆 {{ $awardCounts['special'] ?? 0 }}</span>
        </div>
    </header>

    {{-- ===== Metric rail (overlaps hero) ===== --}}
    <div class="px-4 -mt-6 relative z-10">
        <div class="mp-rail flex gap-3 overflow-x-auto pb-1">
            {{-- attendance ring --}}
            <div class="flex-shrink-0 w-32 bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center mp-reveal" style="animation-delay:.24s">
                <div class="mp-ring" style="--p:{{ (int) $attendanceRate }}"><b>{{ (int) $attendanceRate }}%</b></div>
                <p class="text-[11px] text-muted-foreground mt-2 font-medium">Attendance</p>
            </div>
            {{-- goals ring --}}
            <div class="flex-shrink-0 w-32 bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center mp-reveal" style="animation-delay:.28s">
                <div class="mp-ring" style="--p:{{ (int) $successRate }}"><b>{{ (int) $successRate }}%</b></div>
                <p class="text-[11px] text-muted-foreground mt-2 font-medium">Goal success</p>
            </div>
            {{-- sessions --}}
            <div class="flex-shrink-0 w-28 bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center justify-center mp-reveal" style="animation-delay:.32s">
                <p class="text-2xl font-black text-primary" data-count="{{ $sessionsCompleted }}">0</p>
                <p class="text-[11px] text-muted-foreground mt-1 font-medium">Sessions</p>
            </div>
            {{-- medals --}}
            <div class="flex-shrink-0 w-28 bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center justify-center mp-reveal" style="animation-delay:.36s">
                <p class="text-2xl font-black text-amber-500" data-count="{{ $medalsTotal }}">0</p>
                <p class="text-[11px] text-muted-foreground mt-1 font-medium">Medals</p>
            </div>
            {{-- clubs --}}
            <div class="flex-shrink-0 w-28 bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex flex-col items-center justify-center mp-reveal" style="animation-delay:.4s">
                <p class="text-2xl font-black text-primary" data-count="{{ $totalAffiliations }}">0</p>
                <p class="text-[11px] text-muted-foreground mt-1 font-medium">Clubs</p>
            </div>
        </div>
    </div>

    {{-- ===== Medal showcase ===== --}}
    @if($medalsTotal > 0)
    <div class="px-4 mt-4">
        <div class="grid grid-cols-4 gap-2">
            @php
                $medals = [
                    ['Special', $awardCounts['special'] ?? 0, 'bi-gem', 'hsl(250 70% 70%)', 'hsl(280 70% 60%)'],
                    ['Gold', $awardCounts['1st'] ?? 0, 'bi-trophy-fill', '#fbbf24', '#f59e0b'],
                    ['Silver', $awardCounts['2nd'] ?? 0, 'bi-award-fill', '#cbd5e1', '#94a3b8'],
                    ['Bronze', $awardCounts['3rd'] ?? 0, 'bi-award', '#d6a06a', '#b45309'],
                ];
            @endphp
            @foreach($medals as [$label,$cnt,$icon,$c1,$c2])
                <div class="mp-medal rounded-2xl p-3 text-center text-white shadow-sm" style="--c1:{{ $c1 }};--c2:{{ $c2 }}">
                    <i class="bi {{ $icon }} text-lg"></i>
                    <p class="text-lg font-black leading-none mt-1">{{ $cnt }}</p>
                    <p class="text-[10px] opacity-90">{{ $label }}</p>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- ===== Sticky tabs ===== --}}
    <div class="sticky top-14 z-30 bg-background/95 backdrop-blur mt-5 py-2">
        <div class="mp-tabbar flex gap-2 overflow-x-auto px-4">
            @foreach([
                'overview'=>'Overview','health'=>'Health','goals'=>'Goals',
                'tournaments'=>'Tournaments','clubs'=>'Clubs','attendance'=>'Attendance','billing'=>'Billing'
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
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                <h3 class="font-bold text-foreground mb-3 flex items-center gap-2"><i class="bi bi-person-vcard text-primary"></i> Personal</h3>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div><p class="text-[11px] text-muted-foreground">Blood type</p><p class="font-semibold">{{ $user->blood_type ?: '—' }}</p></div>
                    <div><p class="text-[11px] text-muted-foreground">Nationality</p><p class="font-semibold">{{ $natDisplay ?: '—' }}</p></div>
                    <div><p class="text-[11px] text-muted-foreground">Gender</p><p class="font-semibold capitalize">{{ $user->gender ?: '—' }}</p></div>
                    <div><p class="text-[11px] text-muted-foreground">Marital status</p><p class="font-semibold capitalize">{{ $user->marital_status ?: '—' }}</p></div>
                    @if($user->horoscope)
                        @php $zodiac = ['Aries'=>'♈','Taurus'=>'♉','Gemini'=>'♊','Cancer'=>'♋','Leo'=>'♌','Virgo'=>'♍','Libra'=>'♎','Scorpio'=>'♏','Sagittarius'=>'♐','Capricorn'=>'♑','Aquarius'=>'♒','Pisces'=>'♓']; @endphp
                        <div><p class="text-[11px] text-muted-foreground">Horoscope</p><p class="font-semibold">{{ $zodiac[$user->horoscope] ?? '' }} {{ $user->horoscope }}</p></div>
                    @endif
                    @if($memberSince)<div><p class="text-[11px] text-muted-foreground">Member since</p><p class="font-semibold">{{ Carbon::parse($memberSince)->format('M Y') }}</p></div>@endif
                    <div><p class="text-[11px] text-muted-foreground">Skills learned</p><p class="font-semibold">{{ $distinctSkills }}</p></div>
                </div>
            </div>
            @if($user->email || $phone)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 space-y-2.5">
                <h3 class="font-bold text-foreground mb-1 flex items-center gap-2"><i class="bi bi-telephone text-primary"></i> Contact</h3>
                @if($user->email)<a href="mailto:{{ $user->email }}" class="flex items-center gap-3 text-sm"><span class="w-8 h-8 rounded-lg bg-accent grid place-items-center text-primary"><i class="bi bi-envelope"></i></span><span class="truncate">{{ $user->email }}</span></a>@endif
                @if($phone)<a href="tel:{{ $phone }}" class="flex items-center gap-3 text-sm"><span class="w-8 h-8 rounded-lg bg-accent grid place-items-center text-primary"><i class="bi bi-phone"></i></span><span>{{ $phone }}</span></a>@endif
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
                <h3 class="font-bold text-foreground mb-3 flex items-center gap-2"><i class="bi bi-share text-primary"></i> Social</h3>
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
                <h3 class="font-bold text-foreground flex items-center gap-2"><i class="bi bi-telephone-plus text-primary"></i> Emergency contacts</h3>
                @foreach($user->emergency_contacts as $contact)
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-lg bg-accent grid place-items-center text-primary flex-shrink-0"><i class="bi bi-person"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm truncate">{{ $contact['name'] ?? '—' }}</p>
                            <p class="text-[11px] text-muted-foreground capitalize">{{ $contact['relationship'] ?? '' }}</p>
                        </div>
                        @php $cp = trim(($contact['phone_code'] ?? '').' '.($contact['phone'] ?? '')); @endphp
                        @if($cp)<a href="tel:{{ str_replace(' ', '', $cp) }}" class="text-primary text-sm font-semibold whitespace-nowrap">{{ $cp }}</a>@endif
                    </div>
                @endforeach
            </div>
            @endif

            {{-- Identity documents --}}
            @if(!empty($user->documents))
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 space-y-2">
                <h3 class="font-bold text-foreground mb-1 flex items-center gap-2"><i class="bi bi-file-earmark-text text-primary"></i> Documents</h3>
                @foreach($user->documents as $doc)
                    <a href="{{ !empty($doc['file_path']) ? asset('storage/'.$doc['file_path']) : '#' }}" target="_blank" rel="noopener" class="flex items-center gap-3 text-sm">
                        <span class="w-9 h-9 rounded-lg bg-accent grid place-items-center text-primary flex-shrink-0"><i class="bi bi-file-earmark-arrow-down"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold truncate">{{ $doc['type'] ?? 'Document' }}</p>
                            @if(!empty($doc['number']))<p class="text-[11px] text-muted-foreground truncate">{{ $doc['number'] }}</p>@endif
                        </div>
                    </a>
                @endforeach
            </div>
            @endif
        </div>

        {{-- ===== Health ===== --}}
        <div x-show="tab==='health'" x-transition.opacity x-cloak class="space-y-3">
            @if(!empty($user->health_conditions))
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 space-y-2.5">
                <h3 class="font-bold text-foreground flex items-center gap-2"><i class="bi bi-clipboard2-pulse text-primary"></i> Chronic conditions</h3>
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
                    $metrics = [
                        ['Weight', $latest->weight, 'kg', 'bi-speedometer', $prev->weight ?? null],
                        ['Height', $latest->height, 'cm', 'bi-rulers', $prev->height ?? null],
                        ['BMI', $latest->bmi, '', 'bi-heart-pulse', $prev->bmi ?? null],
                        ['Body fat', $latest->body_fat_percentage, '%', 'bi-droplet-half', $prev->body_fat_percentage ?? null],
                        ['Muscle', $latest->muscle_mass, 'kg', 'bi-activity', $prev->muscle_mass ?? null],
                        ['Body age', $latest->body_age, 'yrs', 'bi-hourglass', $prev->body_age ?? null],
                    ];
                @endphp
                <div class="grid grid-cols-2 gap-3">
                    @foreach($metrics as [$label,$val,$unit,$icon,$old])
                        @if(!is_null($val))
                            @php $delta = (!is_null($old) && $old != 0) ? round($val - $old, 1) : null; @endphp
                            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                                <div class="flex items-center justify-between"><span class="text-[11px] text-muted-foreground font-medium">{{ $label }}</span><i class="bi {{ $icon }} text-primary"></i></div>
                                <p class="text-2xl font-black mt-1">{{ $val }}<span class="text-xs font-medium text-muted-foreground ml-0.5">{{ $unit }}</span></p>
                                @if(!is_null($delta) && $delta != 0)
                                    <p class="text-[11px] font-semibold mt-0.5 {{ $delta > 0 ? 'text-green-600' : 'text-red-500' }}"><i class="bi bi-arrow-{{ $delta>0?'up':'down' }}-short"></i>{{ abs($delta) }} {{ $unit }}</p>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
                <p class="text-[11px] text-muted-foreground text-center">Last recorded {{ optional($latest->recorded_at)->format('d M Y') }}</p>
            @else
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center"><i class="bi bi-heart-pulse text-3xl text-gray-300"></i><p class="text-sm text-muted-foreground mt-2">No health records yet.</p></div>
            @endif
        </div>

        {{-- ===== Goals ===== --}}
        <div x-show="tab==='goals'" x-transition.opacity x-cloak class="space-y-3">
            <div class="grid grid-cols-3 gap-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3 text-center"><p class="text-xl font-black text-primary">{{ $activeGoalsCount }}</p><p class="text-[10px] text-muted-foreground">Active</p></div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3 text-center"><p class="text-xl font-black text-green-600">{{ $completedGoalsCount }}</p><p class="text-[10px] text-muted-foreground">Done</p></div>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3 text-center"><p class="text-xl font-black text-amber-500">{{ $successRate }}%</p><p class="text-[10px] text-muted-foreground">Success</p></div>
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
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center"><i class="bi bi-bullseye text-3xl text-gray-300"></i><p class="text-sm text-muted-foreground mt-2">No goals set.</p></div>
            @endforelse
        </div>

        {{-- ===== Tournaments ===== --}}
        <div x-show="tab==='tournaments'" x-transition.opacity x-cloak class="space-y-3">
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
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center"><i class="bi bi-trophy text-3xl text-gray-300"></i><p class="text-sm text-muted-foreground mt-2">No tournaments yet.</p></div>
            @endforelse
        </div>

        {{-- ===== Clubs / affiliations ===== --}}
        <div x-show="tab==='clubs'" x-transition.opacity x-cloak class="space-y-3">
            @forelse($clubAffiliations as $a)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center gap-3">
                        <span class="w-11 h-11 rounded-xl bg-muted grid place-items-center overflow-hidden flex-shrink-0">
                            @if($a->logo)<img src="{{ asset('storage/'.$a->logo) }}" alt="" class="w-11 h-11 object-cover">@else<i class="bi bi-buildings text-muted-foreground"></i>@endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-foreground truncate">{{ $a->club_name }}</p>
                            <p class="text-xs text-muted-foreground">{{ optional($a->start_date)->format('M Y') }} — {{ $a->end_date ? optional($a->end_date)->format('M Y') : 'Present' }}</p>
                        </div>
                    </div>
                    @if($a->skillAcquisitions->count())
                        <div class="flex flex-wrap gap-1.5 mt-3">
                            @foreach($a->skillAcquisitions->take(6) as $s)
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">{{ $s->skill_name }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center"><i class="bi bi-diagram-3 text-3xl text-gray-300"></i><p class="text-sm text-muted-foreground mt-2">No club affiliations.</p></div>
            @endforelse
        </div>

        {{-- ===== Attendance ===== --}}
        <div x-show="tab==='attendance'" x-transition.opacity x-cloak class="space-y-3">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex items-center gap-5">
                <div class="mp-ring" style="--p:{{ (int) $attendanceRate }}; width:84px; height:84px;"><b style="font-size:18px">{{ (int) $attendanceRate }}%</b></div>
                <div class="flex-1 space-y-2">
                    <div class="flex justify-between text-sm"><span class="text-muted-foreground">Completed</span><span class="font-bold text-green-600">{{ $sessionsCompleted }}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-muted-foreground">No-shows</span><span class="font-bold text-red-500">{{ $noShows }}</span></div>
                    <div class="flex justify-between text-sm"><span class="text-muted-foreground">Total sessions</span><span class="font-bold">{{ $attendanceRecords->count() }}</span></div>
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
                            <p class="font-semibold text-foreground truncate">{{ $invoice->tenant->club_name ?? 'Invoice' }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ optional($invoice->created_at)->format('d M Y') }}</p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="font-black text-foreground leading-none">{{ $invoice->amount }}</p>
                            <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $invoice->status==='paid'?'bg-green-100 text-green-700':($invoice->status==='due'?'bg-amber-100 text-amber-700':'bg-gray-100 text-gray-600') }}">{{ ucfirst($invoice->status) }}</span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center"><i class="bi bi-receipt text-3xl text-gray-300"></i><p class="text-sm text-muted-foreground mt-2">No invoices yet.</p></div>
            @endforelse
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
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
</script>
@endpush
