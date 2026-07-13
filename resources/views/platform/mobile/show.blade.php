@extends('layouts.app')

@section('hide-navbar', true)
@section('title', $club->club_name)

@section('content')
@php
    $instructorsMap = $club->instructors->mapWithKeys(function ($instructor) {
        return [$instructor->id => [
            'id' => $instructor->id, 'user_id' => $instructor->user_id,
            'name' => $instructor->user?->full_name ?? $instructor->user?->name ?? 'Unknown',
            'image' => $instructor->user?->profile_picture ?? null,
        ]];
    })->toArray();

    $cover = $club->cover_image ? asset('storage/'.$club->cover_image)
           : ($club->galleryImages->first() ? asset('storage/'.$club->galleryImages->first()->image_path) : null);

    // Weekly class slots from all packages.
    $slots = collect();
    foreach ($club->packages as $pkg) {
        foreach ($pkg->packageActivities as $pa) {
            $sched = is_string($pa->schedule) ? json_decode($pa->schedule, true) : $pa->schedule;
            foreach ((is_array($sched) ? $sched : []) as $s) {
                $slots->push([
                    'day' => ucfirst($s['day'] ?? ''), 'start' => $s['start_time'] ?? null, 'end' => $s['end_time'] ?? null,
                    'facility' => $s['facility_name'] ?? null, 'package' => $pkg->name,
                    'instructor' => $pa->instructor?->user?->full_name ?? null,
                ]);
            }
        }
    }
    $dayOrder = ['Saturday'=>0,'Sunday'=>1,'Monday'=>2,'Tuesday'=>3,'Wednesday'=>4,'Thursday'=>5,'Friday'=>6];
    $slotsByDay = $slots->groupBy('day')->sortBy(fn($v, $k) => $dayOrder[$k] ?? 9);
@endphp
<div x-data="{ tab: window.location.hash === '#packages' ? 'packages' : 'about' }" class="min-h-screen bg-background pb-24">

    {{-- ===== Hero ===== --}}
    <div class="relative h-56">
        @if($cover)
            <img src="{{ $cover }}" alt="" class="absolute inset-0 w-full h-full object-cover">
        @else
            <div class="absolute inset-0 m-hero"></div>
        @endif
        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-black/20"></div>

        {{-- Back --}}
        <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('clubs.explore') }}')"
                class="m-press absolute top-3 left-3 w-10 h-10 rounded-full bg-black/35 backdrop-blur text-white flex items-center justify-center" aria-label="{{ __('shared.back') }}">
            <i class="bi bi-arrow-left text-lg"></i>
        </button>

        {{-- One QR button → modal with a Register / Club page tab switcher --}}
        <div class="absolute top-3 right-3 z-10">
            <x-qr-code
                label=""
                icon="bi-qr-code"
                button-class="w-10 h-10 rounded-full bg-black/35 backdrop-blur text-white flex items-center justify-center"
                :targets="[
                    [
                        'url'     => \App\Http\Controllers\QrController::clubRegisterUrl($club),
                        'tab'     => 'Register',
                        'title'   => $club->tr('club_name') . ' — Registration',
                        'caption' => 'Scan to register & join this club',
                        'filename'=> 'qr-' . $club->slug . '-register',
                        'poster'  => auth()->check() ? route('qr.club.register', $club) : null,
                    ],
                    [
                        'url'     => \App\Http\Controllers\QrController::clubPageUrl($club),
                        'tab'     => 'Club page',
                        'title'   => $club->tr('club_name') . ' — Club page',
                        'caption' => 'Scan to view this club',
                        'filename'=> 'qr-' . $club->slug . '-page',
                        'poster'  => auth()->check() ? route('qr.club.page', $club) : null,
                    ],
                ]" />
        </div>

        {{-- Club identity --}}
        <div class="absolute bottom-0 inset-x-0 p-4 text-white">
            <div class="flex items-end gap-3">
                @if($club->logo)
                    <span class="w-16 h-16 flex-shrink-0">
                        <img src="{{ asset('storage/'.$club->logo) }}" alt="" class="w-full h-full object-contain">
                    </span>
                @endif
                <div class="min-w-0">
                    <h1 class="text-xl font-extrabold uppercase leading-tight truncate">{{ $club->tr('club_name') }}</h1>
                    @if($club->tr('address'))
                        <p class="text-[12px] text-white/80 truncate mt-0.5"><i class="bi bi-geo-alt-fill text-primary"></i> {{ $club->tr('address') }}</p>
                    @endif
                </div>
            </div>
            {{-- stat chips --}}
            <div class="flex gap-2 mt-3 overflow-x-auto scrollbar-hide">
                <span class="flex-shrink-0 px-3 py-1 rounded-full bg-white/15 backdrop-blur text-[12px] font-semibold"><i class="bi bi-star-fill text-amber-400"></i> {{ number_format($averageRating, 1) }}</span>
                <span class="flex-shrink-0 px-3 py-1 rounded-full bg-white/15 backdrop-blur text-[12px] font-semibold"><i class="bi bi-people-fill"></i> {{ $activeMembersCount }}{{ __('club.members_suffix') }}</span>
                <span class="flex-shrink-0 px-3 py-1 rounded-full bg-white/15 backdrop-blur text-[12px] font-semibold"><i class="bi bi-trophy-fill"></i> {{ $distinctClassCount }}{{ __('club.classes_suffix') }}</span>
                @if($club->established_date)
                    <span class="flex-shrink-0 px-3 py-1 rounded-full bg-white/15 backdrop-blur text-[12px] font-semibold"><i class="bi bi-fire"></i> {{ __('club.since') }} {{ \Carbon\Carbon::parse($club->established_date)->format('Y') }}</span>
                @endif
            </div>
        </div>
    </div>

    {{-- ===== Social strip ===== --}}
    @if($club->socialLinks->isNotEmpty() || $club->phone)
        <div class="flex items-center justify-evenly gap-2 px-4 py-3 bg-white border-b border-border">
            @foreach($club->socialLinks as $link)
                @php
                    $p = strtolower($link->platform); $icon = 'bi-link-45deg';
                    foreach (['whatsapp'=>'bi-whatsapp','instagram'=>'bi-instagram','facebook'=>'bi-facebook','youtube'=>'bi-youtube','tiktok'=>'bi-tiktok','linkedin'=>'bi-linkedin','snapchat'=>'bi-snapchat','telegram'=>'bi-telegram'] as $k=>$v) { if (str_contains($p,$k)) { $icon=$v; break; } }
                @endphp
                <a href="{{ $link->url }}" target="_blank" class="m-press w-10 h-10 rounded-full bg-muted text-primary flex items-center justify-center flex-shrink-0"><i class="bi {{ $icon }}"></i></a>
            @endforeach
            @if($club->phone)
                <a href="tel:{{ is_array($club->phone) ? (($club->phone['code'] ?? '').($club->phone['number'] ?? '')) : $club->phone }}" class="m-press w-10 h-10 rounded-full bg-muted text-primary flex items-center justify-center flex-shrink-0"><i class="bi bi-telephone"></i></a>
            @endif
        </div>
    @endif

    {{-- ===== Tabs ===== --}}
    <div class="sticky top-0 z-30 bg-white border-b border-border overflow-x-auto scrollbar-hide">
        <div class="flex gap-1 px-2 w-max">
            @foreach(['about'=>__('club.tab_about'),'packages'=>__('club.tab_packages'),'schedule'=>__('club.tab_schedule'),'events'=>__('club.tab_events'),'reviews'=>__('club.tab_reviews')] as $key => $label)
                <button type="button" @click="tab='{{ $key }}'"
                        class="m-press relative px-3.5 py-3 text-sm font-semibold whitespace-nowrap transition-colors"
                        :class="tab==='{{ $key }}' ? 'text-primary' : 'text-muted-foreground'">
                    {{ $label }}
                    <span x-show="tab==='{{ $key }}'" class="absolute bottom-0 inset-x-2 h-0.5 bg-primary rounded-full"></span>
                </button>
            @endforeach
        </div>
    </div>

    <div class="px-4 py-4">
        {{-- ===== About ===== --}}
        <div x-show="tab==='about'" x-transition.opacity class="space-y-4">
            @if($club->tr('description'))
                <div class="text-center">
                    <p class="text-[14px] leading-relaxed text-foreground/90 whitespace-pre-line">{{ $club->tr('description') }}</p>
                </div>
            @endif

            @if($club->facilities->isNotEmpty())
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2">{{ __('club.facilities') }}</p>
                    <div class="flex gap-3 overflow-x-auto scrollbar-hide pb-1 snap-x snap-mandatory">
                        @foreach($club->facilities as $f)
                            @php
                                $facImages = collect($f->images ?? [])->map(fn($p) => asset('storage/'.$p))->values()->toArray();
                                if (empty($facImages) && $f->photo) $facImages = [asset('storage/'.$f->photo)];
                                $facTag = $f->maps_url ? 'a' : 'div';
                            @endphp
                            <{{ $facTag }} @if($f->maps_url) href="{{ $f->maps_url }}" target="_blank" rel="noopener" @endif
                                x-data="{ i: 0, n: {{ count($facImages) }} }"
                                x-init="n > 1 && setInterval(() => i = (i + 1) % n, 3500)"
                                class="m-press snap-start flex-shrink-0 w-56 aspect-video rounded-2xl overflow-hidden relative shadow-md ring-1 ring-black/5 block">
                                @if(count($facImages))
                                    @foreach($facImages as $idx => $img)
                                        <img src="{{ $img }}" alt="{{ $f->tr('name') }}"
                                             class="absolute inset-0 w-full h-full object-cover transition-opacity duration-700"
                                             :class="i === {{ $idx }} ? 'opacity-100' : 'opacity-0'"
                                             @if($idx === 0) style="opacity:1" @endif>
                                    @endforeach
                                @else
                                    <div class="absolute inset-0 m-hero flex items-center justify-center">
                                        <i class="bi bi-building text-white/90 text-4xl"></i>
                                    </div>
                                @endif

                                {{-- readability gradient --}}
                                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/15 to-transparent"></div>

                                {{-- availability + map affordance --}}
                                <div class="absolute top-2.5 left-2.5 right-2.5 flex items-center justify-between">
                                    @if($f->is_available)
                                        <span class="px-2 py-0.5 rounded-full bg-green-500/90 text-white text-[10px] font-semibold backdrop-blur-sm"><i class="bi bi-check-circle-fill mr-0.5"></i>{{ __('club.available') }}</span>
                                    @else
                                        <span></span>
                                    @endif
                                    @if($f->maps_url)
                                        <span class="w-7 h-7 rounded-full bg-white/25 backdrop-blur text-white flex items-center justify-center"><i class="bi bi-geo-alt-fill text-xs"></i></span>
                                    @endif
                                </div>

                                {{-- image dots --}}
                                @if(count($facImages) > 1)
                                    <div class="absolute top-12 left-2.5 flex gap-1">
                                        @foreach($facImages as $idx => $_)
                                            <span class="h-1 rounded-full bg-white transition-all duration-300"
                                                  :class="i === {{ $idx }} ? 'w-4 opacity-90' : 'w-1 opacity-50'"
                                                  @if($idx === 0) style="width:1rem;opacity:.9" @endif></span>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- title + address --}}
                                <div class="absolute bottom-0 inset-x-0 p-3 text-white">
                                    <p class="text-[15px] font-bold leading-tight drop-shadow-sm">{{ $f->tr('name') }}</p>
                                    @if($f->tr('address'))
                                        <p class="text-[11px] text-white/85 truncate mt-0.5"><i class="bi bi-geo-alt mr-1"></i>{{ $f->tr('address') }}</p>
                                    @endif
                                </div>
                            </{{ $facTag }}>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($club->instructors->isNotEmpty())
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2">{{ __('club.coaches') }}</p>
                    <div class="flex gap-3 overflow-x-auto scrollbar-hide pb-1 snap-x snap-mandatory">
                        @foreach($club->instructors as $ins)
                            <a href="{{ route('trainer.show', $ins->user_id) }}"
                               class="m-press snap-start flex-shrink-0 w-60 flex items-center gap-3 bg-white rounded-2xl shadow-sm border border-gray-100 p-2.5">
                                <span class="w-14 h-14 flex-shrink-0 rounded-xl bg-muted overflow-hidden flex items-center justify-center">
                                    @if($ins->user?->profile_picture)<img src="{{ asset('storage/'.$ins->user->profile_picture) }}" alt="" class="w-14 h-14 object-cover">@else<i class="bi bi-person text-2xl text-muted-foreground"></i>@endif
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-semibold text-foreground truncate">{{ $ins->user?->full_name ?? __('club.coach') }}</span>
                                    @if($ins->tr('role'))<span class="block text-[11px] text-muted-foreground truncate">{{ $ins->tr('role') }}</span>@endif
                                </span>
                                <i class="bi bi-chevron-right text-muted-foreground/60 text-sm flex-shrink-0"></i>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ===== Achievements ===== --}}
            @if($achievementsAll->isNotEmpty())
                @php
                    $achInitial = 5;
                    $achTotal = $achievementsAll->count();
                    // Resolve linked-athlete user ids -> uuids for member-profile links (single query).
                    $athleteIds = $achievementsAll->flatMap(fn ($a) => collect($a->athletes ?? [])->pluck('user_id'))->filter()->unique()->values();
                    $athleteUuidMap = $athleteIds->isNotEmpty()
                        ? \App\Models\User::whereIn('id', $athleteIds)->pluck('uuid', 'id')->toArray()
                        : [];
                    $achievementsJson = $achievementsAll->map(function ($a) use ($athleteUuidMap) {
                        $combined = collect(array_filter(array_merge($a->image_path ? [$a->image_path] : [], $a->images ?? [])))
                            ->map(fn ($p) => asset('storage/' . $p))->values()->toArray();
                        $medals = [];
                        if ($a->medals_gold)   $medals[] = $a->medals_gold . ' Gold';
                        if ($a->medals_silver) $medals[] = $a->medals_silver . ' Silver';
                        if ($a->medals_bronze) $medals[] = $a->medals_bronze . ' Bronze';
                        return [
                            'title'         => $a->tr('title'),
                            'short_title'   => $a->tr('short_title') ?: $a->tr('title'),
                            'type_icon'     => $a->type_icon ?: '🏆',
                            'description'   => $a->tr('description') ?? '',
                            'location'      => $a->tr('location') ?? '',
                            'date_label'    => $a->date_label ?? ($a->achievement_date ? $a->achievement_date->format('M Y') : ''),
                            'category'      => $a->category ?? '',
                            'medal_summary' => implode(' • ', $medals) ?: ($a->tr('tag') ?? ''),
                            'medal_emojis'  => ($a->medals_gold ? '🥇' : '') . ($a->medals_silver ? '🥈' : '') . ($a->medals_bronze ? '🥉' : ''),
                            'bouts_count'   => $a->bouts_count ?? 0,
                            'wins_count'    => $a->wins_count ?? 0,
                            'chips'         => array_values(array_filter((array) ($a->chips ?? []), fn ($c) => is_string($c) && $c !== '')),
                            'athletes'      => collect($a->athletes ?? [])->map(fn ($x) => is_array($x)
                                                    ? ['name' => $x['name'] ?? '', 'role' => $x['role'] ?? '', 'profile_url' => (!empty($x['user_id']) && isset($athleteUuidMap[$x['user_id']])) ? route('member.show', $athleteUuidMap[$x['user_id']]) : null]
                                                    : ['name' => (string) $x, 'role' => '', 'profile_url' => null])
                                                ->filter(fn ($x) => $x['name'] !== '')->values()->toArray(),
                            'image_url'     => $combined[0] ?? null,
                            'images'        => $combined,
                            'bg_from'       => $a->bg_from ?: '#6d5efc',
                            'bg_to'         => $a->bg_to ?: '#9b8cff',
                        ];
                    })->values()->toArray();
                @endphp
                <div x-data="{ showAch: false, ach: null, idx: 0, expanded: false,
                               openAch(a) { this.ach = a; this.idx = 0; this.showAch = true; },
                               medalEmoji(r) { r = (r || '').toLowerCase(); var m = '';
                                   if (r.includes('gold')) m += '🥇';
                                   if (r.includes('silver')) m += '🥈';
                                   if (r.includes('bronze')) m += '🥉';
                                   return m || '🏅'; } }">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('club.achievements') }}</p>
                        <span class="text-[11px] text-muted-foreground/70"><i class="bi bi-arrow-left-right mr-1"></i>{{ __('club.ach_swipe') }}</span>
                    </div>
                    <div class="flex items-stretch gap-3 overflow-x-auto snap-x snap-mandatory scrollbar-hide pb-1">
                        @foreach($achievementsAll as $i => $a)
                            @php
                                $achData = $achievementsJson[$i];
                                $athleteInitials = collect($achData['athletes'])->take(4);
                            @endphp
                            <div class="relative snap-start flex-shrink-0 w-[58%] max-w-[14rem]"
                                 @if($i >= $achInitial) x-show="expanded" x-cloak @endif>
                            @if($canManage)
                                <a href="{{ route('admin.club.achievements', $club) }}?edit={{ $a->id }}"
                                   @click.stop
                                   class="m-press absolute top-1.5 left-1.5 z-10 w-7 h-7 rounded-full bg-black/55 backdrop-blur text-white grid place-items-center active:scale-95 transition-transform"
                                   aria-label="{{ __('shared.edit') }}">
                                    <i class="bi bi-pencil-fill text-[11px]"></i>
                                </a>
                            @endif
                            <button type="button" @click='openAch(@json($achData))'
                                    class="m-press m-card w-full text-left overflow-hidden flex flex-col">
                                <div class="relative h-24 flex-shrink-0">
                                    @if($achData['image_url'])
                                        <img src="{{ $achData['image_url'] }}" alt="{{ $a->tr('title') }}" class="w-full h-24 object-cover">
                                    @else
                                        <div class="w-full h-24 flex items-center justify-center" style="background:linear-gradient(135deg,{{ $achData['bg_from'] }},{{ $achData['bg_to'] }});">
                                            <span class="text-4xl opacity-40">{{ $achData['type_icon'] }}</span>
                                        </div>
                                    @endif
                                    <div class="absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-black/55 to-transparent"></div>
                                    @if($achData['medal_summary'])
                                        <span class="absolute top-1.5 right-1.5 max-w-[calc(100%-0.75rem)] inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wide text-white bg-black/60">
                                            <span class="text-[10px] leading-none flex-shrink-0">{{ $achData['medal_emojis'] ?: '🏅' }}</span><span class="truncate">{{ $achData['medal_summary'] }}</span>
                                        </span>
                                    @endif
                                </div>
                                <div class="p-2.5 flex-1 flex flex-col">
                                    <p class="font-bold text-[12px] text-foreground flex items-center gap-1">
                                        <span class="w-4 h-4 rounded bg-accent text-primary grid place-items-center text-[9px] flex-shrink-0">{{ $achData['type_icon'] }}</span>
                                        <span class="truncate">{{ $achData['short_title'] }}</span>
                                    </p>
                                    @if($achData['location'] || $achData['date_label'])
                                        <p class="mt-0.5 text-[10px] text-muted-foreground flex flex-wrap gap-x-2 gap-y-0.5">
                                            @if($achData['location'])<span class="truncate"><i class="bi bi-geo-alt mr-0.5"></i>{{ $achData['location'] }}</span>@endif
                                            @if($achData['date_label'])<span><i class="bi bi-calendar-event mr-0.5"></i>{{ $achData['date_label'] }}</span>@endif
                                        </p>
                                    @endif
                                    <div class="mt-auto pt-2 flex items-center justify-between">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            @if($athleteInitials->isNotEmpty())
                                                <div class="flex">
                                                    @foreach($athleteInitials as $idx => $ath)
                                                        <span class="w-5 h-5 rounded-full bg-primary text-white text-[9px] font-bold grid place-items-center ring-2 ring-white {{ $idx > 0 ? '-ml-1.5' : '' }}">{{ mb_strtoupper(mb_substr($ath['name'], 0, 1)) }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <span class="text-[10px] font-semibold text-primary flex items-center gap-0.5 flex-shrink-0">{{ __('club.ach_view_story') }}<i class="bi bi-chevron-right text-[9px]"></i></span>
                                    </div>
                                </div>
                            </button>
                            </div>
                        @endforeach
                    </div>

                    @if($achTotal > $achInitial)
                        <button type="button" x-show="!expanded" @click="expanded = true"
                                class="m-press mt-3 w-full flex items-center justify-center gap-1.5 rounded-xl border border-primary/30 text-primary text-sm font-semibold py-2.5">
                            {{ __('club.ach_view_more', ['count' => $achTotal - $achInitial]) }}<i class="bi bi-chevron-down"></i>
                        </button>
                    @endif

                    {{-- Detail bottom sheet (teleported to body) --}}
                    <template x-teleport="body">
                        <div x-show="showAch" x-cloak class="fixed inset-0 z-[60] overflow-y-auto" @keydown.escape.window="showAch = false">
                            <div x-show="showAch" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                 class="fixed inset-0 bg-black/60" @click="showAch = false"></div>
                            <div class="flex min-h-full items-end justify-center sm:items-center sm:p-4">
                                <div x-show="showAch"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
                                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                                     x-transition:leave="transition ease-in duration-200"
                                     x-transition:leave-start="opacity-100 translate-y-0"
                                     x-transition:leave-end="opacity-0 translate-y-full"
                                     class="relative bg-white rounded-t-3xl sm:rounded-2xl shadow-xl w-full sm:max-w-lg flex flex-col"
                                     style="max-height: 92vh;" @click.stop>
                                    <template x-if="ach">
                                        <div class="flex flex-col overflow-hidden">
                                            {{-- Media --}}
                                            <div class="relative flex-shrink-0">
                                                <div class="flex overflow-x-auto snap-x snap-mandatory scrollbar-hide rounded-t-3xl sm:rounded-t-2xl"
                                                     x-ref="strip" @scroll.debounce.50ms="idx = Math.round($refs.strip.scrollLeft / $refs.strip.offsetWidth)">
                                                    <template x-if="ach.images && ach.images.length">
                                                        <template x-for="img in ach.images" :key="img">
                                                            <img :src="img" class="snap-start flex-shrink-0 w-full h-60 object-cover">
                                                        </template>
                                                    </template>
                                                    <template x-if="!ach.images || !ach.images.length">
                                                        <div class="w-full h-60 flex items-center justify-center" :style="'background:linear-gradient(135deg,'+ach.bg_from+','+ach.bg_to+')'">
                                                            <span class="text-6xl opacity-40" x-text="ach.type_icon"></span>
                                                        </div>
                                                    </template>
                                                </div>
                                                <button type="button" @click="showAch = false" class="absolute top-3 right-3 w-9 h-9 rounded-full bg-black/40 backdrop-blur text-white grid place-items-center"><i class="bi bi-x-lg"></i></button>
                                                <div class="absolute inset-x-0 bottom-0 h-20 bg-gradient-to-t from-black/60 to-transparent pointer-events-none"></div>
                                                <div x-show="ach.images && ach.images.length > 1" class="absolute bottom-2.5 left-1/2 -translate-x-1/2 flex gap-1.5">
                                                    <template x-for="(img, i) in ach.images" :key="i">
                                                        <span class="h-1.5 rounded-full transition-all" :class="idx === i ? 'bg-white w-4' : 'bg-white/50 w-1.5'"></span>
                                                    </template>
                                                </div>
                                                <span x-show="ach.medal_summary" class="absolute top-3 left-3 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider text-white bg-black/40 backdrop-blur">
                                                    <span class="text-[12px] leading-none" x-text="ach.medal_emojis || '🏅'"></span><span x-text="ach.medal_summary"></span>
                                                </span>
                                            </div>

                                            {{-- Body --}}
                                            <div class="overflow-y-auto p-4 space-y-3" style="max-height: calc(92vh - 15rem);">
                                                <div>
                                                    <h3 class="text-lg font-extrabold text-foreground leading-tight" x-text="ach.title"></h3>
                                                    <p class="mt-1 text-xs text-muted-foreground flex flex-wrap gap-x-3 gap-y-0.5">
                                                        <span x-show="ach.location"><i class="bi bi-geo-alt mr-0.5"></i><span x-text="ach.location"></span></span>
                                                        <span x-show="ach.date_label"><i class="bi bi-calendar-event mr-0.5"></i><span x-text="ach.date_label"></span></span>
                                                        <span x-show="ach.category"><i class="bi bi-tag mr-0.5"></i><span x-text="ach.category"></span></span>
                                                    </p>
                                                </div>

                                                <div x-show="ach.bouts_count" class="flex gap-2">
                                                    <span class="px-2.5 py-1 rounded-lg bg-muted text-[12px] font-semibold text-foreground"><i class="bi bi-bar-chart-fill text-primary mr-1"></i><span x-text="ach.bouts_count + ' bouts'"></span></span>
                                                    <span x-show="ach.wins_count" class="px-2.5 py-1 rounded-lg bg-green-50 text-[12px] font-semibold text-green-700"><i class="bi bi-trophy mr-1"></i><span x-text="ach.wins_count + ' wins'"></span></span>
                                                </div>

                                                <p x-show="ach.description" class="text-[13px] text-foreground/90 whitespace-pre-line" x-text="ach.description"></p>

                                                <div x-show="ach.chips && ach.chips.length" class="flex flex-wrap gap-1.5">
                                                    <template x-for="chip in ach.chips" :key="chip">
                                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-medium bg-accent text-primary" x-text="chip"></span>
                                                    </template>
                                                </div>

                                                <div x-show="ach.athletes && ach.athletes.length">
                                                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-1.5">{{ __('club.ach_athletes') }}</p>
                                                    <div class="space-y-1.5">
                                                        <template x-for="ath in ach.athletes" :key="ath.name">
                                                            <a :href="ath.profile_url" :class="ath.profile_url ? 'm-press hover:bg-accent cursor-pointer' : 'cursor-default'"
                                                               class="flex items-center gap-2.5 rounded-xl bg-muted/50 px-2.5 py-2 transition-colors">
                                                                <span class="w-7 h-7 rounded-full bg-primary text-white text-[11px] font-bold grid place-items-center flex-shrink-0" x-text="ath.name.charAt(0).toUpperCase()"></span>
                                                                <div class="min-w-0 flex-1">
                                                                    <p class="text-[13px] font-semibold text-foreground truncate" x-text="ath.name"></p>
                                                                    <p x-show="ath.role" class="text-[11px] text-muted-foreground flex items-center gap-1">
                                                                        <span class="text-[13px] leading-none" x-text="medalEmoji(ath.role)"></span><span x-text="ath.role"></span>
                                                                    </p>
                                                                </div>
                                                                <i x-show="ath.profile_url" class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
                                                            </a>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            @endif

            {{-- ===== Member Perks ===== --}}
            @php $activePerks = $club->perks->where('status', 'active')->values(); @endphp
            @if($activePerks->isNotEmpty())
                <div x-data="{
                        open: false, view: 'loading', collectUrl: '',
                        title: '', message: '', value: '', perkType: '', collectedAt: '', members: [], copied: false,
                        auth: {{ Auth::check() ? 'true' : 'false' }},
                        loginUrl: '{{ route('login') }}',
                        csrf: '{{ csrf_token() }}',
                        start(url) {
                            if (!this.auth) { window.location.href = this.loginUrl; return; }
                            this.collectUrl = url; this.copied = false; this.open = true; this.doCollect(null);
                        },
                        doCollect(forId) {
                            this.view = 'loading';
                            fetch(this.collectUrl, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                                body: forId ? JSON.stringify({ for_user_id: forId }) : null,
                            }).then(r => r.json()).then(d => {
                                if (d.members_only)        { this.message = d.message; this.view = 'members_only'; return; }
                                if (d.already_collected)   { this.collectedAt = d.collected_at || ''; this.view = 'already'; return; }
                                if (d.requires_selection)  { this.members = d.members || []; this.view = 'picker'; return; }
                                if (!d.success)            { this.message = d.message || ''; this.view = 'error'; return; }
                                this.title = d.title; this.value = d.perk_value; this.perkType = d.perk_type;
                                if (d.perk_type === 'code') { this.view = 'code'; }
                                else { this.view = 'qr'; this.$nextTick(() => this.renderQr()); }
                            }).catch(() => { this.view = 'error'; });
                        },
                        renderQr() {
                            const el = this.$refs.qr; if (!el || typeof QRCode === 'undefined') return;
                            el.innerHTML = '';
                            new QRCode(el, { text: this.value, width: 180, height: 180, correctLevel: QRCode.CorrectLevel.M });
                        },
                        copy() {
                            navigator.clipboard.writeText(this.value).then(() => {
                                this.copied = true; setTimeout(() => this.copied = false, 2000);
                            });
                        }
                     }">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('club.perks_title') }}</p>
                        <span class="text-[11px] text-muted-foreground/70">{{ __('club.perks_subtitle') }}</span>
                    </div>

                    <div class="flex gap-3 overflow-x-auto scrollbar-hide pb-1 snap-x snap-mandatory">
                        @foreach($activePerks as $perk)
                            <button type="button"
                                    @click="start('{{ route('clubs.perks.collect', [$club->country_code, $club->slug, $perk->id]) }}')"
                                    class="m-press m-card snap-start flex-shrink-0 w-[60%] max-w-[15rem] text-left rounded-2xl overflow-hidden bg-white shadow-sm border border-gray-100 flex flex-col">
                                <div class="relative h-24 flex-shrink-0">
                                    @if($perk->image_path)
                                        <img src="{{ asset('storage/'.$perk->image_path) }}" alt="{{ $perk->tr('title') }}" class="w-full h-24 object-cover">
                                    @else
                                        <div class="w-full h-24 flex items-center justify-center" style="background:linear-gradient(135deg,{{ $perk->bg_from ?: '#6d5efc' }},{{ $perk->bg_to ?: '#9b8cff' }});">
                                            <i class="bi {{ $perk->icon ?: 'bi-gift' }} text-white text-4xl opacity-90"></i>
                                        </div>
                                    @endif
                                    <div class="absolute inset-x-0 bottom-0 h-12 bg-gradient-to-t from-black/45 to-transparent"></div>
                                    @if($perk->tr('badge'))
                                        <span class="absolute top-1.5 left-1.5 max-w-[calc(100%-0.75rem)] truncate px-1.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wide text-primary bg-white shadow-sm">{{ $perk->tr('badge') }}</span>
                                    @endif
                                </div>
                                {{-- ticket notch divider --}}
                                <div class="relative h-0">
                                    <span class="absolute -left-1.5 -top-1.5 w-3 h-3 rounded-full bg-background"></span>
                                    <span class="absolute -right-1.5 -top-1.5 w-3 h-3 rounded-full bg-background"></span>
                                    <span class="absolute inset-x-2 top-0 border-t border-dashed border-gray-200"></span>
                                </div>
                                <div class="p-2.5 flex-1 flex flex-col">
                                    <p class="font-bold text-[12px] text-foreground truncate">{{ $perk->tr('title') }}</p>
                                    @if($perk->tr('description'))
                                        <p class="mt-0.5 text-[10px] text-muted-foreground line-clamp-2 leading-snug">{{ $perk->tr('description') }}</p>
                                    @endif
                                    <span class="mt-2 inline-flex items-center justify-center gap-1 rounded-lg bg-primary/10 text-primary text-[11px] font-bold py-1.5">
                                        <i class="bi bi-ticket-perforated-fill text-[12px]"></i>{{ __('club.perk_collect') }}
                                    </span>
                                </div>
                            </button>
                        @endforeach
                    </div>

                    {{-- Collect bottom sheet (teleported to body) --}}
                    <template x-teleport="body">
                        <div x-show="open" x-cloak class="fixed inset-0 z-[60] overflow-y-auto" @keydown.escape.window="open = false">
                            <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                 class="fixed inset-0 bg-black/60" @click="open = false"></div>
                            <div class="flex min-h-full items-end justify-center sm:items-center sm:p-4">
                                <div x-show="open"
                                     x-transition:enter="transition ease-out duration-300"
                                     x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
                                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                                     x-transition:leave="transition ease-in duration-200"
                                     x-transition:leave-start="opacity-100 translate-y-0"
                                     x-transition:leave-end="opacity-0 translate-y-full"
                                     class="relative bg-white rounded-t-3xl sm:rounded-2xl shadow-xl w-full sm:max-w-sm p-6 pt-7"
                                     style="max-height: 92vh;" @click.stop>
                                    <button type="button" @click="open = false" class="absolute top-3 right-3 w-8 h-8 rounded-full bg-muted text-muted-foreground grid place-items-center"><i class="bi bi-x-lg text-sm"></i></button>

                                    {{-- Loading --}}
                                    <div x-show="view === 'loading'" class="py-8 text-center">
                                        <i class="bi bi-arrow-repeat text-3xl text-primary inline-block animate-spin"></i>
                                        <p class="mt-3 text-sm text-muted-foreground">{{ __('club.perk_loading') }}</p>
                                    </div>

                                    {{-- Members only --}}
                                    <div x-show="view === 'members_only'" x-cloak class="py-6 text-center">
                                        <div class="w-14 h-14 mx-auto rounded-2xl bg-accent grid place-items-center"><i class="bi bi-lock-fill text-2xl text-primary"></i></div>
                                        <h5 class="font-bold text-base mt-3 text-foreground">{{ __('club.perk_members_only') }}</h5>
                                        <p class="text-[13px] text-muted-foreground mt-1" x-text="message"></p>
                                    </div>

                                    {{-- Already collected --}}
                                    <div x-show="view === 'already'" x-cloak class="py-6 text-center">
                                        <div class="w-14 h-14 mx-auto rounded-2xl bg-green-50 grid place-items-center"><i class="bi bi-check-circle-fill text-2xl text-green-500"></i></div>
                                        <h5 class="font-bold text-base mt-3 text-foreground">{{ __('club.perk_already') }}</h5>
                                        <p class="text-[13px] text-muted-foreground mt-1" x-text="collectedAt ? '{{ __('club.perk_collected_on', ['date' => '__D__']) }}'.replace('__D__', collectedAt) : ''"></p>
                                    </div>

                                    {{-- Member picker --}}
                                    <div x-show="view === 'picker'" x-cloak>
                                        <h5 class="font-bold text-base text-foreground">{{ __('club.perk_who') }}</h5>
                                        <p class="text-[13px] text-muted-foreground mb-3">{{ __('club.perk_who_sub') }}</p>
                                        <div class="space-y-2 max-h-[55vh] overflow-y-auto">
                                            <template x-for="m in members" :key="m.id">
                                                <button type="button" :disabled="m.already_collected"
                                                        @click="!m.already_collected && doCollect(m.id)"
                                                        class="m-press w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border text-left transition-all"
                                                        :class="m.already_collected ? 'bg-gray-50 border-gray-100 opacity-60 cursor-not-allowed' : 'border-gray-200 hover:border-primary hover:bg-primary/5'">
                                                    <template x-if="m.profile_picture">
                                                        <img :src="'/storage/' + m.profile_picture" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                                                    </template>
                                                    <template x-if="!m.profile_picture">
                                                        <span class="w-10 h-10 rounded-full bg-primary text-white font-bold text-sm grid place-items-center flex-shrink-0" x-text="m.name.trim().charAt(0).toUpperCase()"></span>
                                                    </template>
                                                    <span class="flex-1 min-w-0">
                                                        <span class="block text-[13px] font-semibold text-foreground truncate" x-text="m.name"></span>
                                                        <span class="block text-[11px] mt-0.5" :class="m.already_collected ? 'text-green-600' : 'text-muted-foreground'"
                                                              x-text="m.already_collected ? '{{ __('club.perk_already') }}' : '{{ __('club.perk_tap_collect') }}'"></span>
                                                    </span>
                                                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs" x-show="!m.already_collected"></i>
                                                </button>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Code reveal --}}
                                    <div x-show="view === 'code'" x-cloak class="py-2 text-center">
                                        <div class="w-14 h-14 mx-auto rounded-2xl bg-accent grid place-items-center"><i class="bi bi-ticket-perforated-fill text-2xl text-primary"></i></div>
                                        <h5 class="font-bold text-base mt-3 text-foreground" x-text="title"></h5>
                                        <p class="text-[13px] text-muted-foreground mb-4">{{ __('club.perk_show_code') }}</p>
                                        <div class="flex items-center gap-2 bg-gray-50 border border-dashed border-gray-300 rounded-xl px-4 py-3">
                                            <span class="font-mono font-extrabold text-lg tracking-widest flex-1 text-center text-primary" x-text="value"></span>
                                            <button type="button" @click="copy()" class="text-muted-foreground hover:text-primary transition-colors"><i class="bi" :class="copied ? 'bi-check-lg text-green-500' : 'bi-copy'"></i></button>
                                        </div>
                                        <p x-show="copied" class="text-green-600 text-[12px] mt-2">{{ __('club.perk_copied') }}</p>
                                    </div>

                                    {{-- QR reveal --}}
                                    <div x-show="view === 'qr'" x-cloak class="py-2 text-center">
                                        <h5 class="font-bold text-base text-foreground" x-text="title"></h5>
                                        <p class="text-[13px] text-muted-foreground mb-4">{{ __('club.perk_show_qr') }}</p>
                                        <div x-ref="qr" class="flex justify-center"></div>
                                    </div>

                                    {{-- Error --}}
                                    <div x-show="view === 'error'" x-cloak class="py-6 text-center">
                                        <div class="w-14 h-14 mx-auto rounded-2xl bg-red-50 grid place-items-center"><i class="bi bi-exclamation-triangle-fill text-2xl text-red-500"></i></div>
                                        <p class="text-[13px] text-muted-foreground mt-3" x-text="message || '{{ __('club.perk_error') }}'"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            @endif
        </div>

        {{-- ===== Packages ===== --}}
        <div x-show="tab==='packages'" x-cloak x-transition.opacity>
            @if($club->packages->count() > 0)
                <div class="space-y-4">
                    @foreach($club->packages as $package)
                        <div class="{{ $package->is_applicable ? '' : 'opacity-60' }}">
                            <x-package-card-mobile :package="$package" :club="$club" :instructors-map="$instructorsMap">
                                <x-slot:footer>
                                    @if($package->is_applicable)
                                        <button onclick="openSelectPackageModal({{ $package->id }})"
                                                class="m-press w-full bg-primary text-white font-bold py-2.5 rounded-xl hover:bg-primary/90 transition-colors">
                                            {{ __('club.select_package') }}
                                        </button>
                                        @if(($club->enrollment_fee ?? 0) > 0)
                                            <p class="text-xs text-center text-muted-foreground mt-1.5">{{ __('club.enrolment_fee_note', ['currency' => $club->currency ?? 'BHD', 'amount' => number_format($club->enrollment_fee, 2)]) }}</p>
                                        @endif
                                    @else
                                        <button disabled
                                                class="w-full bg-gray-100 text-gray-400 font-bold py-2.5 rounded-xl cursor-not-allowed flex items-center justify-center gap-1.5">
                                            <i class="bi bi-lock"></i> {{ __('club.not_eligible') }}
                                        </button>
                                        <p class="text-[11px] text-center text-muted-foreground mt-1.5">{{ __('club.not_eligible_note') }}</p>
                                    @endif
                                </x-slot:footer>
                            </x-package-card-mobile>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-12 text-center">
                    <i class="bi bi-box text-4xl text-gray-300 m-float inline-block"></i>
                    <p class="text-sm font-semibold text-foreground mt-3">{{ __('club.no_packages') }}</p>
                    <p class="text-[12px] text-muted-foreground mt-1">{{ __('club.check_back_later') }}</p>
                </div>
            @endif
        </div>

        {{-- ===== Schedule ===== --}}
        <div x-show="tab==='schedule'" x-cloak x-transition.opacity class="space-y-3">
            @forelse($slotsByDay as $day => $daySlots)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <p class="px-4 py-2.5 bg-muted/50 text-sm font-bold text-foreground border-b border-gray-100">{{ $day }}</p>
                    <div class="divide-y divide-gray-50">
                        @foreach($daySlots->sortBy('start') as $s)
                            <div class="flex items-center gap-3 px-4 py-3">
                                <span class="text-[13px] font-semibold text-primary w-20 flex-shrink-0">{{ $s['start'] }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-foreground truncate">{{ $s['package'] }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">@if($s['facility'])<i class="bi bi-geo-alt"></i> {{ $s['facility'] }}@endif @if($s['instructor']) · {{ $s['instructor'] }}@endif</p>
                                </div>
                                @if($s['end'])<span class="text-[11px] text-muted-foreground flex-shrink-0">{{ __('club.till') }} {{ $s['end'] }}</span>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-12 text-center">
                    <i class="bi bi-calendar-week text-4xl text-gray-300 m-float inline-block"></i>
                    <p class="text-sm font-semibold text-foreground mt-3">{{ __('club.no_schedule') }}</p>
                </div>
            @endforelse
        </div>

        {{-- ===== Events ===== --}}
        <div x-show="tab==='events'" x-cloak x-transition.opacity class="space-y-3">
            @forelse($club->events->where('is_archived', false)->sortBy('date') as $event)
                @php $isJoined = in_array($event->id, $joinedEventIds ?? []); @endphp
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    @if($event->cover_image)<img src="{{ asset('storage/'.$event->cover_image) }}" alt="" class="w-full h-32 object-cover">@endif
                    <div class="p-4">
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-bold text-foreground">{{ $event->title }}</p>
                            @if($isJoined)<span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700"><i class="bi bi-check-circle-fill"></i> {{ __('club.joined') }}</span>@endif
                        </div>
                        <p class="text-[12px] text-muted-foreground mt-1">
                            @if($event->date)<i class="bi bi-calendar-event"></i> {{ \Carbon\Carbon::parse($event->date)->format('d M Y') }}@endif
                            @if($event->location) · <i class="bi bi-geo-alt"></i> {{ $event->location }}@endif
                        </p>
                        @if($event->description)<p class="text-[13px] text-foreground/80 mt-2 line-clamp-3">{{ $event->description }}</p>@endif
                        @auth
                            @if(!$isJoined)
                                <form method="POST" action="{{ route('clubs.events.join', [$club->country_code, $club->slug, $event->id]) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="m-press w-full bg-primary text-white py-2.5 rounded-xl font-semibold text-sm">{{ $event->cta_text ?: __('club.join_event') }}</button>
                                </form>
                            @endif
                        @endauth
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-12 text-center">
                    <i class="bi bi-calendar-heart text-4xl text-gray-300 m-float inline-block"></i>
                    <p class="text-sm font-semibold text-foreground mt-3">{{ __('club.no_events') }}</p>
                </div>
            @endforelse
        </div>

        {{-- ===== Reviews ===== --}}
        <div x-show="tab==='reviews'" x-cloak x-transition.opacity class="space-y-3">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-4">
                <div class="text-center">
                    <p class="text-3xl font-extrabold text-foreground leading-none">{{ number_format($averageRating, 1) }}</p>
                    <div class="flex gap-0.5 mt-1 text-amber-400 text-[12px]">
                        @for($i=1;$i<=5;$i++)<i class="bi {{ $i <= round($averageRating) ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor
                    </div>
                </div>
                <div class="flex-1 border-l border-gray-100 pl-4">
                    <p class="text-sm font-semibold text-foreground">{{ $reviews->count() }} {{ $reviews->count() == 1 ? __('club.review_count') : __('club.reviews_count') }}</p>
                    <p class="text-[12px] text-muted-foreground">{{ __('club.from_club_members') }}</p>
                </div>
            </div>
            @forelse($reviews as $r)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-full bg-muted overflow-hidden flex items-center justify-center flex-shrink-0">
                            @if($r->user?->profile_picture)<img src="{{ asset('storage/'.$r->user->profile_picture) }}" alt="" class="w-9 h-9 object-cover">@else<i class="bi bi-person text-muted-foreground"></i>@endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-foreground truncate">{{ $r->user?->full_name ?? __('club.member') }}</p>
                            <div class="flex gap-0.5 text-amber-400 text-[10px]">@for($i=1;$i<=5;$i++)<i class="bi {{ $i <= $r->rating ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor</div>
                        </div>
                        <span class="text-[11px] text-muted-foreground">{{ optional($r->created_at)->diffForHumans() }}</span>
                    </div>
                    @if($r->comment)<p class="text-[13px] text-foreground/90 mt-2.5">{{ $r->comment }}</p>@endif
                </div>
            @empty
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-12 text-center">
                    <i class="bi bi-chat-square-text text-4xl text-gray-300 m-float inline-block"></i>
                    <p class="text-sm font-semibold text-foreground mt-3">{{ __('club.no_reviews') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ===== Enrol flow (shared) ===== --}}
    @include('platform.partials._join-flow')
</div>
@endsection

@if($club->perks->where('status', 'active')->isNotEmpty())
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
@endpush
@endif
