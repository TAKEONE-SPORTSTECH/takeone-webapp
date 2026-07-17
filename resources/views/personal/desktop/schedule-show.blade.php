@extends('layouts.app')

@section('title', $s['title'])

@php
    $mainCount = count($s['workout']['main']);
@endphp

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6"
     x-data="{
        done: {{ ($s['status'] ?? 'upcoming') === 'done' ? 'true' : 'false' }},
        checked: {},
        get doneCount() { return Object.values(this.checked).filter(Boolean).length; },
        toggle(i) { this.checked[i] = !this.checked[i]; },
        complete() {
            this.done = true;
            for (let i = 0; i < {{ $mainCount }}; i++) this.checked[i] = true;
            window.showToast('success', '{{ __("personal.personal_schedule_show_session_complete") }}');
        }
     }">

    @include('partials.personal-desktop-subnav')

    <a href="{{ route('me.schedule', ['day' => $s['day'] ?? null]) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-muted-foreground hover:text-primary transition-colors mb-4">
        <i class="bi bi-arrow-left"></i> {{ __('nav.tab_schedule') }}
    </a>

    {{-- ===== Cover ===== --}}
    <div class="rounded-2xl overflow-hidden shadow-sm mb-6 text-white relative" style="background: linear-gradient(150deg, {{ $s['color'] }}, {{ $s['color'] }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="relative p-6 sm:p-8">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                <i class="bi {{ $s['icon'] }}"></i> {{ $s['discipline'] }}
            </span>
            <div class="mt-5">
                <div class="flex items-center gap-2">
                    @if(!empty($member['avatar']))
                        <span class="w-7 h-7 rounded-full overflow-hidden border-2 border-white/40 block"><img src="{{ $member['avatar'] }}" alt="" class="w-7 h-7 object-cover"></span>
                    @else
                        <span class="w-7 h-7 rounded-full grid place-items-center text-white text-[10px] font-bold border-2 border-white/40" style="background: {{ $member['color'] }};">{{ $member['initials'] }}</span>
                    @endif
                    <span class="text-sm text-white/85 font-medium">{{ $member['name'] }} · {{ $member['relation'] }}</span>
                </div>
                <h1 class="text-2xl font-black mt-2 leading-tight">{{ $s['title'] }}</h1>
                <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                    <i class="bi bi-clock"></i>{{ $s['start'] }} – {{ $s['end'] }} · {{ $s['duration'] }}
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 items-start">
        {{-- ===== Left: content ===== --}}
        <div class="space-y-4 min-w-0">

            {{-- Quick facts --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div>
                        <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-speedometer2"></i></div>
                        <p class="text-xs font-bold text-foreground mt-1.5">{{ $s['intensity'] }}</p>
                        <p class="text-[10px] text-muted-foreground">{{ __('personal.personal_schedule_show_intensity') }}</p>
                    </div>
                    @php $coachTag = !empty($coachLink) ? 'a' : 'div'; @endphp
                    <{{ $coachTag }} class="border-x border-gray-100 block {{ !empty($coachLink) ? 'hover:bg-gray-50 transition-colors rounded-lg' : '' }}"
                        @if(!empty($coachLink)) href="{{ $coachLink['url'] }}" @endif>
                        @if(!empty($coachAvatar))
                            <img src="{{ $coachAvatar }}" alt="" class="w-10 h-10 mx-auto rounded-xl object-cover">
                        @else
                            <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-person-badge"></i></div>
                        @endif
                        <p class="text-xs font-bold {{ !empty($coachLink) ? 'text-primary' : 'text-foreground' }} mt-1.5 truncate px-1">{{ $s['coach'] ?: '—' }}</p>
                        <p class="text-[10px] text-muted-foreground">{{ ($coachLink['type'] ?? '') === 'profile' ? __('personal.personal_schedule_show_substitute') : __('personal.personal_schedule_show_coach') }}</p>
                    </{{ $coachTag }}>
                    @php $whereTag = !empty($location['maps_url']) ? 'a' : 'div'; @endphp
                    <{{ $whereTag }} class="block {{ !empty($location['maps_url']) ? 'hover:bg-gray-50 transition-colors rounded-lg' : '' }}"
                        @if(!empty($location['maps_url'])) href="{{ $location['maps_url'] }}" target="_blank" rel="noopener" @endif>
                        <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-geo-alt"></i></div>
                        <p class="text-xs font-bold {{ !empty($location['maps_url']) ? 'text-primary' : 'text-foreground' }} mt-1.5 truncate px-1">{{ ($location['label'] ?? null) ?: ($s['location'] ?: '—') }}</p>
                        <p class="text-[10px] text-muted-foreground">{{ __('personal.personal_schedule_show_where') }}</p>
                    </{{ $whereTag }}>
                </div>

                @if(!empty($s['focus']))
                    <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-100">
                        <span class="text-xs font-semibold text-muted-foreground me-1 self-center">{{ __('personal.personal_schedule_show_focus_label') }}</span>
                        @foreach($s['focus'] as $f)
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium" style="background: {{ $s['color'] }}1a; color: {{ $s['color'] }};">{{ $f }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Status banners --}}
            @if(!empty($synced))
                @php
                    $banners = [];
                    if (!empty($cancelled)) {
                        $banners[] = ['icon' => 'bi-calendar-x-fill', 'iconBg' => 'bg-destructive', 'bg' => 'bg-red-50', 'border' => 'border-red-100',
                            'title' => __('personal.personal_schedule_show_cancelled_for', ['label' => $occurrenceLabel ?? __('personal.personal_schedule_show_this_session')]),
                            'subtitle' => (!empty($cancelReason) ? $cancelReason . ' · ' : '') . (!empty($cancelCreditable) ? __('personal.personal_schedule_show_members_credited') : __('personal.personal_schedule_show_no_makeup_credit'))];
                    }
                    if (!empty($substitute) && empty($cancelled)) {
                        $banners[] = ['icon' => 'bi-person-check-fill', 'iconBg' => 'bg-amber-500', 'bg' => 'bg-amber-50', 'border' => 'border-amber-100',
                            'title' => __('personal.personal_schedule_show_substitute_name', ['name' => $substitute['name']]), 'subtitle' => __('personal.personal_schedule_show_covering_on', ['label' => $occurrenceLabel ?? ''])];
                    }
                    if (!empty($myCredits)) {
                        $banners[] = ['icon' => 'bi-ticket-detailed-fill', 'iconBg' => 'bg-green-500', 'bg' => 'bg-green-50', 'border' => 'border-green-100',
                            'title' => trans_choice('personal.personal_schedule_show_makeup_credits', $myCredits, ['count' => $myCredits]),
                            'subtitle' => __('personal.personal_schedule_show_drop_in')];
                    }
                    $isTeaching = ($s['source'] ?? '') === 'teaching'; $isCovering = ($s['source'] ?? '') === 'substituting';
                    if ($isCovering) {
                        $banners[] = ['icon' => 'bi-person-check-fill', 'iconBg' => 'bg-green-500', 'bg' => 'bg-green-50', 'border' => 'border-green-100',
                            'title' => __('personal.personal_schedule_show_youre_covering'), 'subtitle' => __('personal.personal_schedule_show_covering_subtitle', ['club' => $s['club'] ?? __('personal.personal_schedule_show_the_club')])];
                    } elseif ($isTeaching) {
                        $banners[] = ['icon' => 'bi-person-video3', 'iconBg' => 'bg-amber-500', 'bg' => 'bg-amber-50', 'border' => 'border-amber-100',
                            'title' => __('personal.personal_schedule_show_you_teach'), 'subtitle' => __('personal.personal_schedule_show_teach_subtitle', ['club' => $s['club'] ?? __('personal.personal_schedule_show_your_club')])];
                    } else {
                        $banners[] = ['icon' => 'bi-arrow-repeat', 'iconBg' => 'bg-sky-500', 'bg' => 'bg-sky-50', 'border' => 'border-sky-100',
                            'title' => __('personal.personal_schedule_show_synced_from', ['club' => $s['club'] ?? __('personal.personal_schedule_show_your_club')]), 'subtitle' => __('personal.personal_schedule_show_synced_subtitle')];
                    }
                    $bannerCount = count($banners);
                @endphp
                @if($bannerCount)
                    <div x-data="bannerSlider({{ $bannerCount }})">
                        <div class="relative">
                            @foreach($banners as $k => $b)
                                <div x-show="i==={{ $k }}" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                     class="rounded-2xl p-4 flex items-center gap-3 {{ $b['bg'] }} border {{ $b['border'] }}">
                                    <div class="w-10 h-10 rounded-xl {{ $b['iconBg'] }} text-white grid place-items-center flex-shrink-0"><i class="bi {{ $b['icon'] }}"></i></div>
                                    <div class="min-w-0 flex-1 {{ $bannerCount > 1 ? 'pe-7' : '' }}">
                                        <p class="text-sm font-bold text-foreground truncate">{{ $b['title'] }}</p>
                                        <p class="text-xs text-muted-foreground truncate">{{ $b['subtitle'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                            @if($bannerCount > 1)
                                <button type="button" @click="next()"
                                        class="absolute end-1.5 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-white/60 backdrop-blur grid place-items-center text-foreground/70 shadow-sm hover:bg-white transition-colors">
                                    <i class="bi bi-chevron-right text-sm"></i>
                                </button>
                            @endif
                        </div>
                        @if($bannerCount > 1)
                            <div class="flex justify-center gap-1.5 mt-1.5">
                                @foreach($banners as $k => $b)
                                    <button type="button" @click="i={{ $k }}" class="rounded-full transition-all" :class="i==={{ $k }} ? 'w-4 h-1.5 bg-primary' : 'w-1.5 h-1.5 bg-gray-300'"></button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <script>
                    function bannerSlider(n) {
                        return {
                            i: 0, n: n,
                            next() { this.i = (this.i + 1) % this.n; },
                            prev() { this.i = (this.i - 1 + this.n) % this.n; },
                        };
                    }
                    </script>
                @endif
            @endif

            {{-- Coach note --}}
            @if(!empty($s['notes']))
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex">
                    <div class="w-1.5 flex-shrink-0" style="background: {{ $s['color'] }};"></div>
                    <div class="flex-1 p-5">
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-xl grid place-items-center flex-shrink-0" style="background: {{ $s['color'] }}1a;">
                                <i class="bi bi-megaphone-fill" style="color: {{ $s['color'] }};"></i>
                            </span>
                            <h2 class="text-sm font-bold text-foreground">{{ __('personal.personal_schedule_show_class_details') }}</h2>
                            <span class="ms-auto text-[9px] font-extrabold uppercase tracking-wide px-2 py-0.5 rounded-full"
                                  style="background: {{ $s['color'] }}1a; color: {{ $s['color'] }};">{{ __('personal.personal_schedule_show_important') }}</span>
                        </div>
                        <p class="text-[15px] leading-relaxed font-semibold text-foreground mt-3">{{ $s['notes'] }}</p>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {{-- Warm-up --}}
                @if(!empty($s['workout']['warmup']))
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-fire text-amber-500"></i> {{ __('personal.personal_schedule_show_warmup') }}</h2>
                    <ul class="mt-3 space-y-2">
                        @foreach($s['workout']['warmup'] as $w)
                            <li class="flex items-start gap-2.5 text-sm text-muted-foreground">
                                <i class="bi bi-dot text-lg leading-none" style="color: {{ $s['color'] }};"></i><span>{{ $w }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                {{-- Main workout --}}
                @if(!empty($s['workout']['main']))
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:col-span-2">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-clipboard-check text-primary"></i> {{ __('personal.personal_schedule_show_workout') }}</h2>
                        <span class="text-xs font-semibold text-muted-foreground"><span x-text="doneCount">0</span>/{{ $mainCount }} {{ __('personal.personal_schedule_show_done_count') }}</span>
                    </div>
                    <div class="mt-3 space-y-2.5">
                        @foreach($s['workout']['main'] as $i => $ex)
                            <button type="button" @click="toggle({{ $i }})"
                                    class="w-full text-start flex items-center gap-3 rounded-xl p-3 border transition-colors"
                                    :class="checked[{{ $i }}] ? 'bg-green-50 border-green-200' : 'bg-white border-gray-100 hover:border-gray-200'">
                                <span class="w-7 h-7 rounded-lg grid place-items-center flex-shrink-0 border-2 transition-colors"
                                      :class="checked[{{ $i }}] ? 'bg-green-500 border-green-500 text-white' : 'border-gray-200 text-transparent'">
                                    <i class="bi bi-check-lg text-sm"></i>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-foreground" :class="checked[{{ $i }}] ? 'line-through opacity-60' : ''">{{ $ex['name'] }}</p>
                                    @if(!empty($ex['note']))<p class="text-xs text-muted-foreground">{{ $ex['note'] }}</p>@endif
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <p class="text-sm font-black" style="color: {{ $s['color'] }};">{{ $ex['sets'] }}×{{ $ex['reps'] }}</p>
                                    <p class="text-[10px] text-muted-foreground">{{ __('personal.personal_schedule_show_sets_reps') }}</p>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            {{-- Cool-down --}}
            @if(!empty($s['workout']['cooldown']))
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-snow text-sky-500"></i> {{ __('personal.personal_schedule_show_cooldown') }}</h2>
                <ul class="mt-3 space-y-2 grid grid-cols-1 sm:grid-cols-2 gap-x-8">
                    @foreach($s['workout']['cooldown'] as $c)
                        <li class="flex items-start gap-2.5 text-sm text-muted-foreground">
                            <i class="bi bi-dot text-lg leading-none" style="color: {{ $s['color'] }};"></i><span>{{ $c }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- Roster + attendance --}}
            @if(!empty($roster))
                @php
                    $roleStyles = [
                        'trainer'    => ['label' => 'Trainer',    'bg' => '#f59e0b', 'chip' => 'bg-amber-50 text-amber-600'],
                        'substitute' => ['label' => 'Substitute', 'bg' => '#10b981', 'chip' => 'bg-green-50 text-green-600'],
                        'member'     => ['label' => 'Member',     'bg' => $s['color'], 'chip' => 'bg-accent text-primary'],
                    ];
                    $members      = collect($roster)->where('role', 'member')->values();
                    $memberCount  = $members->count();
                    $attendedMap  = $members->mapWithKeys(fn ($p) => [$p['id'] => (bool) ($p['attended'] ?? false)]);
                    $rosterMeta   = $members->mapWithKeys(fn ($p) => [$p['id'] => [
                        'name'      => $p['name'],
                        'attended'  => (int) ($p['attended_count'] ?? 0),
                        'total'     => (int) ($p['total_count'] ?? 0),
                        'breakdown' => $p['breakdown'] ?? [],
                    ]]);
                @endphp
                <div x-data="classAttendance({
                        url: '{{ $attendanceUrl }}',
                        date: '{{ $occurrenceDate }}',
                        csrf: '{{ csrf_token() }}',
                        attended: {{ Illuminate\Support\Js::from($attendedMap) }},
                        members: {{ Illuminate\Support\Js::from($rosterMeta) }},
                        curKey: {{ Illuminate\Support\Js::from($curOccKey ?? '') }},
                        total: {{ $memberCount }},
                        startTime: {{ Illuminate\Support\Js::from($slotStart ?? '') }},
                        endTime: {{ Illuminate\Support\Js::from($slotEnd ?? '') }},
                        startedServer: {{ !empty($classStarted) ? 'true' : 'false' }},
                        endedServer: {{ !empty($classEnded) ? 'true' : 'false' }},
                     })">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-people-fill text-primary"></i> {{ __('personal.personal_schedule_show_people_in_class') }}</h2>
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-accent text-primary">{{ $memberCount }}</span>
                        </div>
                        @if($memberCount > 0)
                            <p class="text-xs text-muted-foreground mt-1">
                                {{ __('personal.personal_schedule_show_attendance_for') }} {{ $occurrenceLabel }} · <span class="font-semibold text-foreground"><span x-text="presentCount">0</span>/{{ $memberCount }}</span> {{ __('personal.personal_schedule_show_present_lower') }}
                            </p>
                            <p x-show="notStarted" x-cloak class="text-xs text-amber-600 mt-1 inline-flex items-center gap-1"><i class="bi bi-hourglass-split"></i> {{ __('personal.personal_schedule_show_attendance_opens') }}</p>
                            <p x-show="isOver" x-cloak class="text-xs text-amber-600 mt-1 inline-flex items-center gap-1"><i class="bi bi-lock-fill"></i> {{ __('personal.personal_schedule_show_attendance_closed') }}</p>
                        @endif

                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 divide-gray-50">
                            @foreach($members as $person)
                                @php $rs = $roleStyles[$person['role']] ?? $roleStyles['member']; $isMember = $person['role'] === 'member'; @endphp
                                <div class="flex items-center gap-3 py-2.5">
                                    @if(!empty($person['avatar']))
                                        <span class="w-9 h-9 rounded-full overflow-hidden flex-shrink-0 block"><img src="{{ $person['avatar'] }}" alt="" class="w-9 h-9 object-cover"></span>
                                    @else
                                        <span class="w-9 h-9 rounded-full grid place-items-center text-white text-xs font-bold flex-shrink-0" style="background: {{ $rs['bg'] }};">{{ $person['initials'] }}</span>
                                    @endif
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium text-foreground truncate">{{ $person['name'] }}</span>
                                        @if(!empty($person['note']))
                                            <span class="block text-xs text-muted-foreground">{{ $person['note'] }}</span>
                                        @endif
                                        @if($isMember)
                                            <button type="button" x-show="rateLabel({{ $person['id'] }})" x-cloak
                                                    @click="openBreakdown({{ $person['id'] }})"
                                                    class="mt-1 inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full hover:opacity-80 transition-opacity"
                                                    :class="rateClass({{ $person['id'] }})">
                                                <i class="bi bi-calendar-check"></i> <span x-text="rateLabel({{ $person['id'] }})"></span>
                                                <i class="bi bi-chevron-right opacity-60"></i>
                                            </button>
                                        @endif
                                    </span>
                                    @if($isMember && !empty($canMarkAttendance))
                                        <button type="button" x-show="canMarkNow" x-cloak @click="toggle({{ $person['id'] }})" :disabled="busy[{{ $person['id'] }}]"
                                                class="flex items-center gap-1.5 flex-shrink-0"
                                                :aria-pressed="att[{{ $person['id'] }}] ? 'true' : 'false'">
                                            <span class="text-[10px] font-semibold" :class="att[{{ $person['id'] }}] ? 'text-green-600' : 'text-muted-foreground'"
                                                  x-text="att[{{ $person['id'] }}] ? '{{ __("personal.personal_schedule_show_present_cap") }}' : '{{ __("personal.personal_schedule_show_mark") }}'"></span>
                                            <span class="w-7 h-7 rounded-lg border-2 grid place-items-center transition-colors"
                                                  :class="att[{{ $person['id'] }}] ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 text-transparent'">
                                                <i class="bi bi-check-lg text-sm"></i>
                                            </span>
                                        </button>
                                        <span x-show="notStarted" x-cloak class="flex items-center gap-1.5 flex-shrink-0">
                                            <span class="text-[10px] font-semibold text-muted-foreground">{{ __('personal.personal_schedule_show_not_started') }}</span>
                                            <span class="w-7 h-7 rounded-lg grid place-items-center bg-gray-100 text-gray-400"><i class="bi bi-lock text-sm"></i></span>
                                        </span>
                                        <span x-show="isOver" x-cloak class="flex items-center gap-1.5 flex-shrink-0">
                                            <span class="text-[10px] font-semibold" :class="att[{{ $person['id'] }}] ? 'text-green-600' : 'text-muted-foreground'"
                                                  x-text="att[{{ $person['id'] }}] ? '{{ __("personal.personal_schedule_show_present_cap") }}' : '{{ __("personal.personal_schedule_show_absent") }}'"></span>
                                            <span class="w-7 h-7 rounded-lg grid place-items-center"
                                                  :class="att[{{ $person['id'] }}] ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400'">
                                                <i class="bi text-sm" :class="att[{{ $person['id'] }}] ? 'bi-check-lg' : 'bi-dash-lg'"></i>
                                            </span>
                                        </span>
                                    @else
                                        <span class="flex items-center gap-1.5 flex-shrink-0">
                                            <span class="text-[10px] font-semibold" :class="att[{{ $person['id'] }}] ? 'text-green-600' : 'text-muted-foreground'"
                                                  x-text="att[{{ $person['id'] }}] ? '{{ __("personal.personal_schedule_show_present_cap") }}' : '{{ __("personal.personal_schedule_show_absent") }}'"></span>
                                            <span class="w-7 h-7 rounded-lg grid place-items-center"
                                                  :class="att[{{ $person['id'] }}] ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400'">
                                                <i class="bi text-sm" :class="att[{{ $person['id'] }}] ? 'bi-check-lg' : 'bi-dash-lg'"></i>
                                            </span>
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if($memberCount === 0)
                            <p class="mt-3 pt-3 border-t border-gray-50 text-xs text-muted-foreground text-center">{{ __('personal.personal_schedule_show_no_members_enrolled') }}</p>
                        @endif
                    </div>

                    {{-- Attendance breakdown sheet --}}
                    <div>
                        <div x-show="bd" x-transition.opacity x-cloak class="fixed inset-0 z-[60] bg-black/50" @click="bd=null"></div>
                        <div x-show="bd" x-cloak
                             x-transition:enter="transition ease-out duration-250" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                             class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                            <div class="w-full max-w-md max-h-[85vh] flex flex-col bg-white rounded-2xl shadow-2xl" @click.outside="bd=null">
                                <div class="flex-shrink-0 px-5 py-4 border-b border-gray-100">
                                    <div class="flex items-center justify-between" x-show="bd">
                                        <div class="min-w-0">
                                            <h2 class="text-base font-black text-foreground truncate" x-text="bd && bd.name"></h2>
                                            <p class="text-xs text-muted-foreground"><span class="font-bold text-foreground" x-text="bd && (bd.attended + '/' + bd.total)"></span> {{ __('personal.personal_schedule_show_classes_attended') }}</p>
                                        </div>
                                        <button type="button" @click="bd=null" class="w-9 h-9 rounded-full bg-muted grid place-items-center flex-shrink-0 hover:bg-gray-200 transition-colors"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                </div>
                                <div class="flex-1 overflow-y-auto px-5 py-3">
                                    <template x-for="(o, idx) in (bd ? bd.breakdown : [])" :key="idx">
                                        <div class="flex items-center gap-3 py-2.5 border-b border-gray-50">
                                            <span class="w-7 h-7 rounded-full grid place-items-center flex-shrink-0"
                                                  :class="o.attended ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-500'">
                                                <i class="bi" :class="o.attended ? 'bi-check-lg' : 'bi-x-lg'"></i>
                                            </span>
                                            <span class="text-sm font-medium text-foreground flex-1" x-text="o.label"></span>
                                            <span class="text-xs font-bold" :class="o.attended ? 'text-green-600' : 'text-red-500'" x-text="o.attended ? '{{ __("personal.personal_schedule_show_attended") }}' : '{{ __("personal.personal_schedule_show_missed") }}'"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @include('partials.class-attendance-script')
            @endif

            {{-- Location --}}
            @if(!empty($location))
                @php
                    $hasCoords = isset($location['lat'], $location['lng']) && $location['lat'] !== null && $location['lng'] !== null;
                    $mapsLink = $hasCoords
                        ? 'https://www.google.com/maps?q=' . $location['lat'] . ',' . $location['lng'] . '&z=17'
                        : ($location['maps_url'] ?? null);
                @endphp
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                    <h2 class="text-sm font-bold text-foreground flex items-center gap-2">
                        <i class="bi bi-geo-alt-fill text-primary flex-shrink-0"></i>
                        @if(!$hasCoords && $mapsLink)
                            <a href="{{ $mapsLink }}" target="_blank" rel="noopener" class="text-primary inline-flex items-center gap-1 hover:underline">
                                {{ $location['label'] }} <i class="bi bi-box-arrow-up-right text-[11px]"></i>
                            </a>
                        @else
                            <span>{{ $location['label'] }}</span>
                        @endif
                    </h2>
                    @if(!empty($location['address']) && $location['address'] !== $location['label'])
                        <p class="text-xs text-muted-foreground mt-0.5">{{ $location['address'] }}</p>
                    @endif
                    @if($hasCoords)
                        <div class="mt-3 relative">
                            <x-location-map
                                :id="'classLocMapDesktop'"
                                :lat="$location['lat']"
                                :lng="$location['lng']"
                                :address="$location['address'] ?? ''"
                                :readonly="true"
                                :draggable="false"
                                :show-address="false"
                                :show-coords="false"
                                :show-labels="false"
                                height="16rem" />
                            <a href="{{ $mapsLink }}" target="_blank" rel="noopener"
                               class="absolute inset-0 z-[700] block group rounded-lg" aria-label="{{ __('personal.personal_schedule_show_open_in_gmaps', ['label' => $location['label']]) }}">
                                <span class="absolute top-2 end-2 inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/95 shadow-sm text-xs font-bold text-primary group-hover:bg-white transition-all">
                                    <i class="bi bi-box-arrow-up-right"></i> {{ __('personal.personal_schedule_show_open_in_maps') }}
                                </span>
                            </a>
                        </div>
                        <script>
                            (function () {
                                if (!window.LocationMap) return;
                                window.LocationMap.create({ id: 'classLocMapDesktop', defaultLat: {{ Illuminate\Support\Js::from((float) $location['lat']) }}, defaultLng: {{ Illuminate\Support\Js::from((float) $location['lng']) }}, zoom: 15, draggable: false, readonly: true });
                                window.LocationMap.refresh && window.LocationMap.refresh('classLocMapDesktop');
                            })();
                        </script>
                    @endif
                </div>
            @endif

            {{-- Trainee reviews --}}
            @if(($classRatingCount ?? 0) > 0 || !empty($canEditClub) || !empty($canEngage))
                <div x-data="classReviewsCard({
                        avg: {{ $classRatingAvg !== null ? $classRatingAvg : 'null' }},
                        count: {{ (int) ($classRatingCount ?? 0) }},
                        reviews: {{ Illuminate\Support\Js::from($classComments ?? []) }},
                        dist: {{ Illuminate\Support\Js::from($classDistribution ?? []) }},
                        myUserId: {{ (int) (auth()->id() ?? 0) }},
                        myRating: {{ (int) ($myClassRating ?? 0) }},
                        myComment: {{ Illuminate\Support\Js::from($myClassComment ?? '') }},
                        deleteUrl: '{{ $rateClassDeleteUrl }}',
                        csrf: '{{ csrf_token() }}',
                     })"
                     @class-reviews-updated.window="patch($event.detail)">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-star-half text-primary"></i> {{ __('personal.personal_schedule_show_trainee_reviews') }}</h2>
                            <span x-show="count>0" x-cloak class="text-xs text-muted-foreground"><span x-text="count"></span> <span x-text="count===1 ? '{{ __("personal.personal_schedule_show_rating") }}' : '{{ __("personal.personal_schedule_show_ratings") }}'"></span></span>
                        </div>

                        <div x-show="count>0" x-cloak class="mt-3 flex items-stretch gap-5">
                            <div class="flex flex-col items-center justify-center px-1 flex-shrink-0">
                                <span class="text-3xl font-black text-foreground leading-none" x-text="avg !== null ? Number(avg).toFixed(1) : '—'"></span>
                                <div class="flex items-center gap-0.5 mt-1.5">
                                    <template x-for="n in 5" :key="'avg'+n">
                                        <i class="bi text-xs" :class="n <= Math.round(avg||0) ? 'bi-star-fill text-amber-400' : 'bi-star text-gray-300'"></i>
                                    </template>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0 space-y-1 self-center">
                                <template x-for="n in [5,4,3,2,1]" :key="'bar'+n">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-semibold text-muted-foreground w-2.5 text-end" x-text="n"></span>
                                        <i class="bi bi-star-fill text-amber-400 text-[9px]"></i>
                                        <div class="flex-1 h-1.5 rounded-full bg-muted overflow-hidden">
                                            <div class="h-full rounded-full bg-amber-400" :style="`width:${count ? Math.round((dist[n]||0)/count*100) : 0}%`"></div>
                                        </div>
                                        <span class="text-[10px] text-muted-foreground w-4 text-end" x-text="dist[n]||0"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        @empty($canEngage)
                        <p x-show="count===0" x-cloak class="text-xs text-muted-foreground mt-2">
                            {{ !empty($canEditClub) ? __('personal.personal_schedule_show_no_reviews_admin') : __('personal.personal_schedule_show_no_reviews_yet') }}
                        </p>
                        @endempty

                        <div x-show="hasReview" x-cloak class="mt-4 pt-4 border-t border-gray-100">
                            <div class="rounded-2xl p-3 border border-primary/30 bg-accent/40 flex items-start gap-3">
                                <span class="w-9 h-9 rounded-full bg-primary text-white grid place-items-center text-xs font-bold flex-shrink-0"><i class="bi bi-person-fill"></i></span>
                                <button type="button" @click="editMine()" class="min-w-0 flex-1 text-start">
                                    <span class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-primary">{{ __('personal.personal_schedule_show_your_review') }}</span>
                                        <span class="text-xs text-primary inline-flex items-center gap-1 flex-shrink-0"><i class="bi bi-pencil"></i> {{ __('personal.personal_schedule_show_tap_to_revise') }}</span>
                                    </span>
                                    <span class="flex items-center gap-0.5 mt-1">
                                        <template x-for="n in 5" :key="'my'+n">
                                            <i class="bi text-[12px]" :class="n <= myRating ? 'bi-star-fill text-amber-400' : 'bi-star text-gray-300'"></i>
                                        </template>
                                    </span>
                                    <span class="block text-sm text-foreground mt-1 whitespace-pre-line" x-show="myComment" x-text="myComment"></span>
                                    <span class="block text-xs text-muted-foreground italic mt-1" x-show="!myComment">{{ __('personal.personal_schedule_show_no_comment_yet') }}</span>
                                </button>
                                <button type="button" @click="deleteMine()" :disabled="deleting"
                                        class="w-8 h-8 rounded-lg grid place-items-center text-red-500 hover:bg-red-50 flex-shrink-0 disabled:opacity-50" title="{{ __('personal.personal_schedule_show_delete_review') }}">
                                    <i class="bi" :class="deleting ? 'bi-arrow-repeat animate-spin' : 'bi-trash'"></i>
                                </button>
                            </div>
                        </div>

                        @if(!empty($canReview))
                        <div x-show="!hasReview" x-cloak class="mt-4" :class="count>0 ? 'pt-4 border-t border-gray-100' : ''">
                            <button type="button" @click="startReview()"
                                    class="w-full py-3 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2 hover:opacity-90 transition-opacity"
                                    style="background: {{ $s['color'] }};">
                                <i class="bi bi-star-fill"></i>
                                <span x-text="count===0 ? '{{ __("personal.personal_schedule_show_be_first_review") }}' : '{{ __("personal.personal_schedule_show_add_your_review") }}'"></span>
                            </button>
                        </div>
                        @elseif(!empty($canEngage))
                        <div x-show="!hasReview" x-cloak class="mt-4" :class="count>0 ? 'pt-4 border-t border-gray-100' : ''">
                            <div class="rounded-xl p-3 bg-muted/60 flex items-start gap-2.5">
                                <i class="bi bi-info-circle text-muted-foreground mt-0.5 flex-shrink-0"></i>
                                <p class="text-xs text-muted-foreground">
                                    {{ empty($iAttendedAny)
                                        ? __('personal.personal_schedule_show_review_after_attend')
                                        : __('personal.personal_schedule_show_review_after_start') }}
                                </p>
                            </div>
                        </div>
                        @endif

                        <div class="mt-4 pt-4 border-t border-gray-100 space-y-3.5 grid grid-cols-1 sm:grid-cols-2 gap-x-6" x-show="othersReviews.length" x-cloak>
                            <template x-for="c in othersReviews" :key="c.id">
                                <div class="flex items-start gap-3">
                                    <template x-if="c.avatar">
                                        <img :src="c.avatar" :alt="c.name" class="w-9 h-9 rounded-full object-cover flex-shrink-0">
                                    </template>
                                    <template x-if="!c.avatar">
                                        <div class="w-9 h-9 rounded-full bg-accent text-primary grid place-items-center text-xs font-bold flex-shrink-0" x-text="c.initials"></div>
                                    </template>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-sm font-semibold text-foreground truncate" x-text="c.name"></p>
                                            <span class="text-[10px] text-muted-foreground flex-shrink-0" x-text="c.when"></span>
                                        </div>
                                        <div class="flex items-center gap-0.5 mt-0.5">
                                            <template x-for="n in 5" :key="'r'+c.id+n">
                                                <i class="bi text-[11px]" :class="n <= c.rating ? 'bi-star-fill text-amber-400' : 'bi-star text-gray-300'"></i>
                                            </template>
                                        </div>
                                        <p class="text-sm text-foreground mt-1 whitespace-pre-line" x-text="c.comment"></p>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <p x-show="count>0 && !othersReviews.length && !hasReview" x-cloak class="text-xs text-muted-foreground mt-3 pt-3 border-t border-gray-100">
                            {{ __('personal.personal_schedule_show_ratings_no_comment') }}
                        </p>
                    </div>
                </div>

                @include('partials.class-reviews-script')
            @endif
        </div>

        {{-- ===== Right sidebar: actions ===== --}}
        <aside class="space-y-4 lg:sticky lg:top-20">
            {{-- Complete (personal sessions) --}}
            @if(empty($synced))
            <div>
                <button type="button" @click="complete()" x-show="!done"
                        class="w-full py-3.5 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2 hover:opacity-90 transition-opacity"
                        style="background: {{ $s['color'] }};">
                    <i class="bi bi-play-circle-fill"></i> {{ __('personal.personal_schedule_show_start_complete') }}
                </button>
                <div x-show="done" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 text-center">
                    <div class="w-14 h-14 mx-auto rounded-2xl grid place-items-center text-white" style="background: #10b981;"><i class="bi bi-check2-circle text-2xl"></i></div>
                    <p class="text-sm font-bold text-green-600 mt-2">{{ __('personal.personal_schedule_show_session_completed') }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">{{ __('personal.personal_schedule_show_logged_to', ['name' => $member['name']]) }}</p>
                </div>
            </div>
            @endif

            {{-- Owner edit (personal sessions) --}}
            @if(!empty($isOwner))
                <button type="button"
                        onclick="window.dispatchEvent(new CustomEvent('open-schedule-form', { detail: window.__schedSession }))"
                        class="w-full py-3 rounded-2xl border border-border bg-white text-foreground font-bold text-sm flex items-center justify-center gap-2 hover:bg-gray-50 transition-colors">
                    <i class="bi bi-pencil"></i> {{ __('shared.edit') }}
                </button>
            @endif

            {{-- Manage (coach / club owner / admin) --}}
            @if(!empty($synced) && !empty($canEditClub))
                @php
                    $isTeaching = ($s['source'] ?? '') === 'teaching';
                    $clubName   = $s['club'] ?? 'this club';
                @endphp
                <div x-data="{
                        menuOpen: false,
                        nowTs: Date.now(),
                        endedServer: {{ !empty($classEnded) ? 'true' : 'false' }},
                        date: {{ Illuminate\Support\Js::from($occurrenceDate ?? '') }},
                        startTime: {{ Illuminate\Support\Js::from($slotStart ?? '') }},
                        endTime: {{ Illuminate\Support\Js::from($slotEnd ?? '') }},
                        _mk(hhmm) {
                            if (!hhmm || !this.date) return null;
                            var p = String(hhmm).split(':');
                            var d = new Date(this.date + 'T00:00:00');
                            d.setHours(parseInt(p[0], 10) || 0, parseInt(p[1], 10) || 0, 0, 0);
                            return d.getTime();
                        },
                        get endTs() {
                            var end = this._mk(this.endTime) || this._mk(this.startTime);
                            var s = this._mk(this.startTime), e = this._mk(this.endTime);
                            if (end != null && e != null && s != null && e <= s) end += 86400000;
                            return end;
                        },
                        get isOver() { return this.endedServer === true || (this.endTs != null && this.nowTs >= this.endTs); },
                        init() { setInterval(() => { this.nowTs = Date.now(); }, 30000); }
                     }">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 transition-opacity" :class="isOver ? 'opacity-60' : ''">
                        <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground flex items-center gap-1.5">
                            <i class="bi {{ $isTeaching ? 'bi-person-video3' : 'bi-shield-lock' }} text-primary"></i> {{ $isTeaching ? __('personal.personal_schedule_show_coach_tools') : __('personal.personal_schedule_show_club_management') }}
                        </p>
                        <p class="text-sm text-muted-foreground mt-1">{{ $isTeaching ? __('personal.personal_schedule_show_you_coach') : __('personal.personal_schedule_show_you_manage') }} <span class="font-semibold text-foreground">{{ $clubName }}</span>. {{ __('personal.personal_schedule_show_manage_desc') }}</p>
                        <button type="button" @click="if (!isOver) menuOpen = true" :disabled="isOver"
                                class="w-full mt-3 py-3 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2 hover:opacity-90 transition-opacity"
                                :class="isOver ? 'cursor-not-allowed' : ''"
                                style="background: {{ $s['color'] }};">
                            <i class="bi bi-sliders2"></i> {{ __('personal.personal_schedule_show_manage_class') }}
                        </button>
                        <p x-show="isOver" x-cloak class="text-xs text-amber-600 mt-2 inline-flex items-center gap-1"><i class="bi bi-lock-fill"></i> {{ __('personal.personal_schedule_show_managing_closed') }}</p>
                    </div>

                    {{-- Combined action menu --}}
                    <div>
                        <div x-show="menuOpen" x-transition.opacity x-cloak class="fixed inset-0 z-[60] bg-black/50" @click="menuOpen=false"></div>
                        <div x-show="menuOpen" x-cloak
                             x-transition:enter="transition ease-out duration-250" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                             class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                            <div class="w-full max-w-md max-h-[85vh] flex flex-col bg-white rounded-2xl shadow-2xl" @click.outside="menuOpen=false">
                                <div class="flex-shrink-0 px-5 py-4 border-b border-border/70 rounded-t-2xl text-white" style="background: linear-gradient(160deg, {{ $s['color'] }}, {{ $s['color'] }}cc);">
                                    <div class="flex items-center justify-between">
                                        <div class="min-w-0">
                                            <h2 class="text-base font-black leading-tight truncate">{{ __('personal.personal_schedule_show_manage_class') }}</h2>
                                            <p class="text-xs text-white/80 truncate">{{ $s['title'] ?? $clubName }}{{ !empty($occurrenceLabel) ? ' · '.$occurrenceLabel : '' }}</p>
                                        </div>
                                        <button type="button" @click="menuOpen=false" class="w-9 h-9 rounded-full bg-white/20 border border-white/30 grid place-items-center flex-shrink-0 hover:bg-white/30 transition-colors"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                </div>
                                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                                    <button type="button"
                                            @click="menuOpen=false; window.dispatchEvent(new CustomEvent('open-schedule-form', { detail: window.__schedClass }))"
                                            class="w-full text-start rounded-2xl p-4 flex items-center gap-3 bg-white border border-border hover:bg-gray-50 transition-colors">
                                        <span class="w-11 h-11 rounded-xl grid place-items-center text-white flex-shrink-0" style="background: {{ $s['color'] }};"><i class="bi bi-pencil-square text-lg"></i></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-bold text-foreground">{{ __('personal.personal_schedule_show_edit_class') }}</span>
                                            <span class="block text-xs text-muted-foreground">{{ __('personal.personal_schedule_show_edit_class_desc') }}</span>
                                        </span>
                                        <i class="bi bi-chevron-right text-gray-300"></i>
                                    </button>

                                    @if(empty($programOverridden))
                                    <button type="button"
                                            @click="menuOpen=false; window.dispatchEvent(new CustomEvent('open-schedule-form', { detail: Object.assign({}, window.__schedClass, { once: true, occ_date: {{ Illuminate\Support\Js::from($occurrenceDate) }}, occ_label: {{ Illuminate\Support\Js::from($occurrenceLabel) }} }) }))"
                                            class="w-full text-start rounded-2xl p-4 flex items-center gap-3 bg-white border border-border hover:bg-gray-50 transition-colors">
                                        <span class="w-11 h-11 rounded-xl grid place-items-center text-white flex-shrink-0 bg-sky-500"><i class="bi bi-calendar2-week text-lg"></i></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-bold text-foreground">{{ __('personal.personal_schedule_show_customize_program') }}</span>
                                            <span class="block text-xs text-muted-foreground">{{ __('personal.personal_schedule_show_customize_program_desc') }}</span>
                                        </span>
                                        <i class="bi bi-chevron-right text-gray-300"></i>
                                    </button>
                                    @else
                                    <button type="button"
                                            @click="menuOpen=false; window.dispatchEvent(new CustomEvent('open-schedule-form', { detail: Object.assign({}, window.__schedClass, { once: true, occ_date: {{ Illuminate\Support\Js::from($occurrenceDate) }}, occ_label: {{ Illuminate\Support\Js::from($occurrenceLabel) }} }) }))"
                                            class="w-full text-start rounded-2xl p-4 flex items-center gap-3 bg-white border border-sky-200 hover:bg-sky-50 transition-colors">
                                        <span class="w-11 h-11 rounded-xl grid place-items-center text-white flex-shrink-0 bg-sky-500"><i class="bi bi-calendar2-check text-lg"></i></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-bold text-foreground">{{ __('personal.personal_schedule_show_program_customized') }}</span>
                                            <span class="block text-xs text-muted-foreground">{{ __('personal.personal_schedule_show_program_customized_desc', ['name' => $programOverrideBy ?? __('personal.personal_schedule_show_someone')]) }}</span>
                                        </span>
                                        <i class="bi bi-chevron-right text-gray-300"></i>
                                    </button>
                                    <button type="button"
                                            @click="menuOpen=false; window.dispatchEvent(new CustomEvent('reset-program'))"
                                            class="w-full text-start rounded-2xl p-4 flex items-center gap-3 bg-white border border-red-200 hover:bg-red-50 transition-colors">
                                        <span class="w-11 h-11 rounded-xl grid place-items-center text-red-500 flex-shrink-0 bg-red-50"><i class="bi bi-arrow-counterclockwise text-lg"></i></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-bold text-foreground">{{ __('personal.personal_schedule_show_reset_program') }}</span>
                                            <span class="block text-xs text-muted-foreground">{{ __('personal.personal_schedule_show_reset_program_desc') }}</span>
                                        </span>
                                        <i class="bi bi-chevron-right text-gray-300"></i>
                                    </button>
                                    @endif

                                    <button type="button"
                                            @click="menuOpen=false; window.dispatchEvent(new CustomEvent('open-substitute-sheet'))"
                                            class="w-full text-start rounded-2xl p-4 flex items-center gap-3 bg-white border border-border hover:bg-gray-50 transition-colors">
                                        <span class="w-11 h-11 rounded-xl grid place-items-center text-white flex-shrink-0 bg-amber-500"><i class="bi bi-arrow-left-right text-lg"></i></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-bold text-foreground">{{ $substitute ? __('personal.personal_schedule_show_change_substitute') : __('personal.personal_schedule_show_assign_substitute') }}</span>
                                            <span class="block text-xs text-muted-foreground">
                                                @if($substitute)
                                                    {{ __('personal.personal_schedule_show_currently') }} <span class="font-semibold text-foreground">{{ $substitute['name'] ?? __('personal.personal_schedule_show_someone') }}</span> {{ __('personal.personal_schedule_show_is_covering') }}{{ !empty($occurrenceLabel) ? ' '.__('personal.personal_schedule_show_on').' '.$occurrenceLabel : '' }}.
                                                @else
                                                    {{ __('personal.personal_schedule_show_assign_substitute_desc') }}
                                                @endif
                                            </span>
                                        </span>
                                        <i class="bi bi-chevron-right text-gray-300"></i>
                                    </button>

                                    @if($substitute)
                                    <button type="button"
                                            @click="menuOpen=false; window.dispatchEvent(new CustomEvent('remove-substitute'))"
                                            class="w-full text-start rounded-2xl p-4 flex items-center gap-3 bg-white border border-red-200 hover:bg-red-50 transition-colors">
                                        <span class="w-11 h-11 rounded-xl grid place-items-center text-red-500 flex-shrink-0 bg-red-50"><i class="bi bi-person-dash text-lg"></i></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-bold text-foreground">{{ __('personal.personal_schedule_show_remove_substitute') }}</span>
                                            <span class="block text-xs text-muted-foreground">{{ __('personal.personal_schedule_show_remove_substitute_desc') }}</span>
                                        </span>
                                        <i class="bi bi-chevron-right text-gray-300"></i>
                                    </button>
                                    @endif

                                    @if(!empty($cancelled))
                                    <button type="button"
                                            @click="menuOpen=false; window.dispatchEvent(new CustomEvent('restore-class'))"
                                            class="w-full text-start rounded-2xl p-4 flex items-center gap-3 bg-white border border-border hover:bg-gray-50 transition-colors">
                                        <span class="w-11 h-11 rounded-xl grid place-items-center text-white flex-shrink-0 bg-green-600"><i class="bi bi-arrow-counterclockwise text-lg"></i></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-bold text-foreground">{{ __('personal.personal_schedule_show_restore_class') }}</span>
                                            <span class="block text-xs text-muted-foreground">{{ __('personal.personal_schedule_show_restore_class_desc') }}</span>
                                        </span>
                                        <i class="bi bi-chevron-right text-gray-300"></i>
                                    </button>
                                    @else
                                    <button type="button"
                                            @click="menuOpen=false; window.dispatchEvent(new CustomEvent('open-cancel-sheet'))"
                                            class="w-full text-start rounded-2xl p-4 flex items-center gap-3 bg-white border border-red-200 hover:bg-red-50 transition-colors">
                                        <span class="w-11 h-11 rounded-xl grid place-items-center text-white flex-shrink-0 bg-destructive"><i class="bi bi-calendar-x text-lg"></i></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-bold text-destructive">{{ __('personal.personal_schedule_show_cancel_class') }}</span>
                                            <span class="block text-xs text-muted-foreground">{{ __('personal.personal_schedule_show_cancel_class_desc') }}</span>
                                        </span>
                                        <i class="bi bi-chevron-right text-gray-300"></i>
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if(!empty($programOverridden))
                <div x-data="{
                        resetUrl: '{{ $programResetUrl }}',
                        date: {{ Illuminate\Support\Js::from($occurrenceDate) }},
                        csrf: '{{ csrf_token() }}',
                        async reset() {
                            const ok = await window.confirmAction({
                                title: '{{ __('personal.personal_schedule_show_reset_program') }}',
                                message: '{{ __('personal.personal_schedule_show_reset_program_confirm') }}',
                                type: 'danger',
                                confirmText: '{{ __('personal.personal_schedule_show_reset_program') }}',
                            });
                            if (!ok) return;
                            try {
                                const res = await fetch(this.resetUrl, {
                                    method: 'DELETE',
                                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                                    credentials: 'same-origin',
                                    body: JSON.stringify({ date: this.date }),
                                });
                                const data = await res.json().catch(() => ({}));
                                if (!res.ok || !data.success) { window.showToast('error', data.message || '{{ __('personal.personal_schedule_show_reset_program_failed') }}'); return; }
                                window.showToast('success', data.message || '{{ __('personal.personal_schedule_show_program_reset_done') }}');
                                window.location.reload();
                            } catch (e) {
                                window.showToast('error', '{{ __('personal.personal_schedule_show_reset_program_failed') }}');
                            }
                        }
                     }"
                     @reset-program.window="reset()">
                </div>
                @endif

                <x-substitute-picker
                    :search-url="$substituteSearchUrl"
                    :assign-url="$substituteAssignUrl"
                    :remove-url="$substituteRemoveUrl"
                    :occurrence-date="$occurrenceDate"
                    :occurrence-label="$occurrenceLabel"
                    :substitute="$substitute"
                    :color="$s['color']"
                    :list-url="route('me.schedule')"
                    :trigger-only="true" />

                <div x-data="classCancelTool({
                        cancelUrl: '{{ $cancelUrl }}',
                        uncancelUrl: '{{ $uncancelUrl }}',
                        csrf: '{{ csrf_token() }}',
                        listUrl: '{{ route('me.schedule') }}',
                        date: '{{ $occurrenceDate }}',
                        cancelled: {{ !empty($cancelled) ? 'true' : 'false' }},
                     })"
                     @open-cancel-sheet.window="open=true"
                     @restore-class.window="restore()">

                    <div>
                        <div x-show="open" x-transition.opacity x-cloak class="fixed inset-0 z-[60] bg-black/50" @click="open=false"></div>
                        <div x-show="open" x-cloak
                             x-transition:enter="transition ease-out duration-250" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                             class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                            <div class="w-full max-w-md max-h-[85vh] flex flex-col bg-white rounded-2xl shadow-2xl" @click.outside="open=false">
                                <div class="flex-shrink-0 px-5 py-4 border-b border-border/70 rounded-t-2xl bg-destructive text-white">
                                    <div class="flex items-center justify-between">
                                        <div class="min-w-0"><h2 class="text-base font-black leading-tight">{{ __('personal.personal_schedule_show_cancel_class') }}</h2><p class="text-xs text-white/80">{{ __('personal.personal_schedule_show_auto_makeup_credit') }}</p></div>
                                        <button type="button" @click="open=false" class="w-9 h-9 rounded-full bg-white/20 border border-white/30 grid place-items-center flex-shrink-0 hover:bg-white/30 transition-colors"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                </div>
                                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('personal.personal_schedule_show_from') }}</label>
                                            <input type="date" x-model="form.from" :min="todayStr" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('personal.personal_schedule_show_to') }} <span class="text-muted-foreground font-normal">{{ __('personal.personal_schedule_show_optional') }}</span></label>
                                            <input type="date" x-model="form.to" :min="form.from" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                        </div>
                                    </div>
                                    <p class="text-xs text-muted-foreground">{{ __('personal.personal_schedule_show_range_hint') }}</p>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('personal.personal_schedule_show_reason') }} <span class="text-muted-foreground font-normal">{{ __('personal.personal_schedule_show_optional') }}</span></label>
                                        <textarea x-model="form.reason" rows="2" maxlength="300" placeholder="{{ __('personal.personal_schedule_show_reason_ph') }}" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                                    </div>
                                    <button type="button" @click="form.credit = !form.credit"
                                            class="w-full flex items-center gap-3 rounded-xl border p-3 text-start transition-colors"
                                            :class="form.credit ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-white'">
                                        <span class="w-10 h-6 rounded-full flex items-center transition-colors flex-shrink-0" :class="form.credit ? 'bg-green-500 justify-end' : 'bg-gray-300 justify-start'">
                                            <span class="w-5 h-5 rounded-full bg-white shadow mx-0.5"></span>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block text-sm font-semibold text-foreground">{{ __('personal.personal_schedule_show_credit_makeup') }}</span>
                                            <span class="block text-xs text-muted-foreground" x-text="form.credit ? '{{ __("personal.personal_schedule_show_credit_on") }}' : '{{ __("personal.personal_schedule_show_credit_off") }}'"></span>
                                        </span>
                                    </button>
                                </div>
                                <div class="flex-shrink-0 px-5 py-4 border-t border-border">
                                    <button type="button" @click="submit()" :disabled="busy"
                                            class="w-full h-12 rounded-xl bg-destructive text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-60 hover:opacity-90 transition-opacity">
                                        <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-calendar-x'"></i> {{ __('personal.personal_schedule_show_cancel_class') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @include('partials.class-cancel-script')
            @endif
        </aside>
    </div>

    {{-- Trainee engagement: rating form sheet --}}
    @if(!empty($canEngage))
        <div x-data="classEngage({
                reactUrl: '{{ $reactUrl }}',
                rateUrl: '{{ $rateUrl }}',
                rateClassUrl: '{{ $rateClassUrl }}',
                csrf: '{{ csrf_token() }}',
                canManage: {{ !empty($canEditClub) ? 'true' : 'false' }},
                date: '{{ $occurrenceDate }}',
                counts: {{ Illuminate\Support\Js::from($reactions ?? []) }},
                mine: {{ $myReaction ? "'".e($myReaction)."'" : 'null' }},
                rating: {{ (int) ($myRating ?? 0) }},
                comment: {{ Illuminate\Support\Js::from($myComment ?? '') }},
                classRating: {{ (int) ($myClassRating ?? 0) }},
                classComment: {{ Illuminate\Support\Js::from($myClassComment ?? '') }},
                classAvg: {{ $classRatingAvg !== null ? $classRatingAvg : 'null' }},
                classCount: {{ (int) ($classRatingCount ?? 0) }},
                comments: {{ Illuminate\Support\Js::from($classComments ?? []) }},
             })"
             @open-review-form.window="panel = true"
             @class-review-deleted.window="classRating = 0; classComment = ''; classAvg = ($event.detail.average ?? null); classCount = $event.detail.count || 0; comments = []">
            <div x-show="panel" x-cloak class="fixed inset-0 z-[80]">
                <div class="absolute inset-0 bg-black/40" @click="panel = false"
                     x-show="panel" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
                <div class="absolute inset-y-0 end-0 w-full max-w-md flex flex-col bg-white shadow-2xl"
                     x-show="panel" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full rtl:-translate-x-full" x-transition:enter-end="translate-x-0"
                     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full rtl:-translate-x-full">
                    <div class="flex-shrink-0 px-4 py-4 border-b border-gray-100">
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-bold text-foreground flex items-center gap-2"><i class="bi bi-emoji-smile text-primary"></i> {{ __('personal.personal_schedule_show_enjoyed_class') }}</h2>
                            <button type="button" @click="panel = false" class="w-8 h-8 grid place-items-center rounded-full hover:bg-muted text-muted-foreground"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto px-4 py-4">
                        <div class="flex flex-wrap justify-center items-end gap-1">
                            @foreach($emojiChoices as $e)
                                <button type="button" @click="react('{{ $e }}')"
                                        class="relative w-11 h-11 rounded-full grid place-items-center transition-all hover:scale-110"
                                        :class="mine==='{{ $e }}' ? 'bg-accent ring-2 ring-primary' : 'hover:bg-muted'">
                                    <span class="text-2xl leading-none">{{ $e }}</span>
                                    <span x-show="counts['{{ $e }}']" x-cloak
                                          class="absolute -bottom-1 -end-0.5 min-w-4 h-4 px-1 rounded-full bg-white shadow text-[10px] font-bold text-foreground grid place-items-center"
                                          x-text="counts['{{ $e }}']"></span>
                                </button>
                            @endforeach
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-foreground">{{ __('personal.personal_schedule_show_rate_this_class') }}</p>
                                <span x-show="classAvg !== null" x-cloak class="text-xs text-muted-foreground">
                                    <i class="bi bi-star-fill text-amber-400"></i> <span x-text="classAvg"></span> {{ __('personal.personal_schedule_show_avg') }} · <span x-text="classCount"></span>
                                </span>
                            </div>
                            <div class="flex items-center gap-1.5 mt-2">
                                <template x-for="n in 5" :key="'c'+n">
                                    <button type="button" @click="rateClass(n)" class="text-3xl leading-none hover:scale-110 transition-transform"
                                            :class="n <= classRating ? 'text-amber-400' : 'text-gray-300'">
                                        <i class="bi" :class="n <= classRating ? 'bi-star-fill' : 'bi-star'"></i>
                                    </button>
                                </template>
                            </div>
                            <div class="mt-3" x-show="classRating>0" x-cloak x-transition>
                                <textarea x-model="classComment" rows="2" maxlength="500"
                                          placeholder="{{ __('personal.personal_schedule_show_class_comment_ph') }}"
                                          class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                                <p class="text-xs text-muted-foreground mt-1">{{ __('personal.personal_schedule_show_shared_everyone') }}</p>
                            </div>
                        </div>

                        @if(!empty($rateInstructorId))
                            <div class="mt-4 pt-4 border-t border-gray-100">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-foreground">{{ __('personal.personal_schedule_show_rate_name', ['name' => $coachRatingName]) }}</p>
                                    @if(!empty($coachRatingAvg))
                                        <span class="text-xs text-muted-foreground"><i class="bi bi-star-fill text-amber-400"></i> {{ number_format($coachRatingAvg, 1) }} {{ __('personal.personal_schedule_show_avg') }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5 mt-2">
                                    <template x-for="n in 5" :key="n">
                                        <button type="button" @click="rate(n)" class="text-3xl leading-none hover:scale-110 transition-transform"
                                                :class="n <= rating ? 'text-amber-400' : 'text-gray-300'">
                                            <i class="bi" :class="n <= rating ? 'bi-star-fill' : 'bi-star'"></i>
                                        </button>
                                    </template>
                                </div>
                                <div class="mt-3" x-show="rating>0" x-cloak x-transition>
                                    <textarea x-model="comment" rows="2" maxlength="500"
                                              placeholder="{{ __('personal.personal_schedule_show_coach_comment_ph') }}"
                                              class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                                    <p class="text-xs text-muted-foreground mt-1">{{ __('personal.personal_schedule_show_shows_on_profile') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="flex-shrink-0 px-4 py-4 border-t border-gray-100 bg-white">
                        <button type="button" @click="submitAll()" :disabled="savingAll || (classRating<1 && rating<1)"
                                class="w-full py-3.5 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-50 hover:opacity-90 transition-opacity"
                                style="background: {{ $s['color'] }};">
                            <i class="bi" :class="savingAll ? 'bi-arrow-repeat animate-spin' : 'bi-check2-circle'"></i>
                            <span x-text="savingAll ? '{{ __("personal.personal_schedule_show_saving") }}' : '{{ __("personal.personal_schedule_show_submit_rating") }}'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @include('partials.class-engage-script')
    @endif
</div>

{{-- Full create/edit sheet — shared by personal sessions (owner) and club classes (coach/manager). --}}
@if(!empty($isOwner) || (!empty($synced) && !empty($canEditClub)))
    <x-schedule-session-modal :subjects="$subjectsList ?? []" :facilities="$clubFacilities ?? []" :instructors="$clubInstructors ?? []" />
    <script>
        @if(!empty($isOwner))
        window.__schedSession = {{ Illuminate\Support\Js::from($s) }};
        @endif
        @if(!empty($synced) && !empty($canEditClub))
        window.__schedClass = Object.assign({}, {{ Illuminate\Support\Js::from($s) }}, { update_url: '{{ $updateUrl }}' });
        @endif
        window.addEventListener('schedule-session-saved', function (e) {
            if (e.detail && e.detail.once) {
                setTimeout(function () { window.location.reload(); }, 300);
                return;
            }
            setTimeout(function () {
                window.location.href = "{{ route('me.schedule') }}";
            }, 400);
        });
        window.addEventListener('schedule-session-deleted', function () {
            window.location.href = "{{ route('me.schedule') }}";
        });
    </script>
@endif
@endsection
