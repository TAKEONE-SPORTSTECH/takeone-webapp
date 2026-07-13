@extends('layouts.personal-mobile')

@section('title', $s['title'])

{{--
    Training session detail — mobile. DUMMY content from PersonalMobileController@scheduleShow.
    Gradient cover, quick facts, focus chips, and the full workout breakdown
    (warm-up · main lifts with sets×reps · cool-down) with check-off + a
    "start / complete session" action. Reuses the shared mobile motion vocabulary.
--}}
@php
    $mainCount = count($s['workout']['main']);
@endphp

@section('personal-content')
<div x-data="{
        done: {{ ($s['status'] ?? 'upcoming') === 'done' ? 'true' : 'false' }},
        checked: {},
        get doneCount() { return Object.values(this.checked).filter(Boolean).length; },
        toggle(i) { this.checked[i] = !this.checked[i]; },
        complete() {
            this.done = true;
            for (let i = 0; i < {{ $mainCount }}; i++) this.checked[i] = true;
            window.showToast('success', '{{ __("personal.personal_schedule_show_session_complete") }}');
        }
     }"
     class="-mx-4 -mt-4 pb-4">

    {{-- ===== Cover ===== --}}
    <header class="m-hero px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $s['color'] }}, {{ $s['color'] }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-10">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('me.schedule', ['day' => $s['day'] ?? null]) }}')"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-lg"></i>
            </button>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                <i class="bi {{ $s['icon'] }}"></i> {{ $s['discipline'] }}
            </span>
        </div>

        <div class="relative z-10 mt-6">
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
    </header>

    {{-- ===== Quick facts (overlaps cover) ===== --}}
    <div class="px-4 -mt-10 relative z-10">
        <div class="bg-white rounded-3xl shadow-lg border border-gray-100 p-4">
            <div class="grid grid-cols-3 gap-2 text-center">
                <div>
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-speedometer2"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5">{{ $s['intensity'] }}</p>
                    <p class="text-[10px] text-muted-foreground">{{ __('personal.personal_schedule_show_intensity') }}</p>
                </div>
                {{-- Coach → instructor page (or substitute's profile) when resolvable --}}
                @php $coachTag = !empty($coachLink) ? 'a' : 'div'; @endphp
                <{{ $coachTag }} class="border-x border-gray-100 block {{ !empty($coachLink) ? 'm-press' : '' }}"
                    @if(!empty($coachLink)) href="{{ $coachLink['url'] }}" @endif>
                    @if(!empty($coachAvatar))
                        <img src="{{ $coachAvatar }}" alt="" class="w-10 h-10 mx-auto rounded-xl object-cover">
                    @else
                        <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-person-badge"></i></div>
                    @endif
                    <p class="text-xs font-bold {{ !empty($coachLink) ? 'text-primary' : 'text-foreground' }} mt-1.5 truncate px-1">{{ $s['coach'] ?: '—' }}</p>
                    <p class="text-[10px] text-muted-foreground">{{ ($coachLink['type'] ?? '') === 'profile' ? __('personal.personal_schedule_show_substitute') : __('personal.personal_schedule_show_coach') }}</p>
                </{{ $coachTag }}>
                {{-- Where → Google Maps directions --}}
                @php $whereTag = !empty($location['maps_url']) ? 'a' : 'div'; @endphp
                <{{ $whereTag }} class="block {{ !empty($location['maps_url']) ? 'm-press' : '' }}"
                    @if(!empty($location['maps_url'])) href="{{ $location['maps_url'] }}" target="_blank" rel="noopener" @endif>
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-geo-alt"></i></div>
                    <p class="text-xs font-bold {{ !empty($location['maps_url']) ? 'text-primary' : 'text-foreground' }} mt-1.5 truncate px-1">{{ ($location['label'] ?? null) ?: ($s['location'] ?: '—') }}</p>
                    <p class="text-[10px] text-muted-foreground">{{ __('personal.personal_schedule_show_where') }}</p>
                </{{ $whereTag }}>
            </div>

            {{-- focus chips --}}
            @if(!empty($s['focus']))
                <div class="flex flex-wrap gap-2 mt-4 pt-4 border-t border-gray-100">
                    <span class="text-[11px] font-semibold text-muted-foreground me-1 self-center">{{ __('personal.personal_schedule_show_focus_label') }}</span>
                    @foreach($s['focus'] as $f)
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-medium" style="background: {{ $s['color'] }}1a; color: {{ $s['color'] }};">{{ $f }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- ===== Status banners — combined into one swipeable slider ===== --}}
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
            <div class="px-4 mt-4" x-data="bannerSlider({{ $bannerCount }})">
                <div class="relative" @touchstart.passive="start($event)" @touchend.passive="end($event)">
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
                                class="m-press absolute end-1.5 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-white/60 backdrop-blur grid place-items-center text-foreground/70 shadow-sm">
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
                    i: 0, n: n, sx: 0,
                    next() { this.i = (this.i + 1) % this.n; },
                    prev() { this.i = (this.i - 1 + this.n) % this.n; },
                    start(e) { this.sx = e.changedTouches[0].clientX; },
                    end(e) { var dx = e.changedTouches[0].clientX - this.sx; if (Math.abs(dx) > 40) { dx < 0 ? this.next() : this.prev(); } },
                };
            }
            </script>
        @endif
    @endif

    {{-- ===== Coach note — prominent announcement card ===== --}}
    @if(!empty($s['notes']))
        <div class="px-4 mt-4">
            <div class="m-card rounded-2xl overflow-hidden flex">
                <div class="w-1.5 flex-shrink-0" style="background: {{ $s['color'] }};"></div>
                <div class="flex-1 p-4">
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
        </div>
    @endif

    {{-- ===== Warm-up ===== --}}
    @if(!empty($s['workout']['warmup']))
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-fire text-amber-500"></i> {{ __('personal.personal_schedule_show_warmup') }}</h2>
            <ul class="mt-3 space-y-2">
                @foreach($s['workout']['warmup'] as $w)
                    <li class="flex items-start gap-2.5 text-sm text-muted-foreground">
                        <i class="bi bi-dot text-lg leading-none" style="color: {{ $s['color'] }};"></i><span>{{ $w }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    {{-- ===== Main workout (check-off) ===== --}}
    @if(!empty($s['workout']['main']))
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-clipboard-check text-primary"></i> {{ __('personal.personal_schedule_show_workout') }}</h2>
                <span class="text-[11px] font-semibold text-muted-foreground"><span x-text="doneCount">0</span>/{{ $mainCount }} {{ __('personal.personal_schedule_show_done_count') }}</span>
            </div>
            <div class="mt-3 space-y-2.5">
                @foreach($s['workout']['main'] as $i => $ex)
                    <button type="button" @click="toggle({{ $i }})"
                            class="m-press w-full text-start flex items-center gap-3 rounded-xl p-3 border transition-colors"
                            :class="checked[{{ $i }}] ? 'bg-green-50 border-green-200' : 'bg-white border-gray-100'">
                        <span class="w-7 h-7 rounded-lg grid place-items-center flex-shrink-0 border-2 transition-colors"
                              :class="checked[{{ $i }}] ? 'bg-green-500 border-green-500 text-white' : 'border-gray-200 text-transparent'">
                            <i class="bi bi-check-lg text-sm"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-foreground" :class="checked[{{ $i }}] ? 'line-through opacity-60' : ''">{{ $ex['name'] }}</p>
                            @if(!empty($ex['note']))<p class="text-[11px] text-muted-foreground">{{ $ex['note'] }}</p>@endif
                        </div>
                        <div class="text-end flex-shrink-0">
                            <p class="text-sm font-black" style="color: {{ $s['color'] }};">{{ $ex['sets'] }}×{{ $ex['reps'] }}</p>
                            <p class="text-[10px] text-muted-foreground">{{ __('personal.personal_schedule_show_sets_reps') }}</p>
                        </div>
                    </button>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- ===== Cool-down ===== --}}
    @if(!empty($s['workout']['cooldown']))
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl p-4">
            <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-snow text-sky-500"></i> {{ __('personal.personal_schedule_show_cooldown') }}</h2>
            <ul class="mt-3 space-y-2">
                @foreach($s['workout']['cooldown'] as $c)
                    <li class="flex items-start gap-2.5 text-sm text-muted-foreground">
                        <i class="bi bi-dot text-lg leading-none" style="color: {{ $s['color'] }};"></i><span>{{ $c }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    {{-- ===== Class roster (everyone involved) + attendance — for coach / manager / substitute ===== --}}
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
        <div class="px-4 mt-4"
             x-data="classAttendance({
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
            <div class="m-card rounded-2xl p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-people-fill text-primary"></i> {{ __('personal.personal_schedule_show_people_in_class') }}</h2>
                    <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-accent text-primary">{{ $memberCount }}</span>
                </div>
                @if($memberCount > 0)
                    <p class="text-[11px] text-muted-foreground mt-1">
                        {{ __('personal.personal_schedule_show_attendance_for') }} {{ $occurrenceLabel }} · <span class="font-semibold text-foreground"><span x-text="presentCount">0</span>/{{ $memberCount }}</span> {{ __('personal.personal_schedule_show_present_lower') }}
                    </p>
                    <p x-show="notStarted" x-cloak class="text-[11px] text-amber-600 mt-1 inline-flex items-center gap-1"><i class="bi bi-hourglass-split"></i> {{ __('personal.personal_schedule_show_attendance_opens') }}</p>
                    <p x-show="isOver" x-cloak class="text-[11px] text-amber-600 mt-1 inline-flex items-center gap-1"><i class="bi bi-lock-fill"></i> {{ __('personal.personal_schedule_show_attendance_closed') }}</p>
                @endif

                <div class="mt-3 divide-y divide-gray-50">
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
                                    <span class="block text-[11px] text-muted-foreground">{{ $person['note'] }}</span>
                                @endif
                                @if($isMember)
                                    <button type="button" x-show="rateLabel({{ $person['id'] }})" x-cloak
                                            @click="openBreakdown({{ $person['id'] }})"
                                            class="m-press mt-1 inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-full"
                                            :class="rateClass({{ $person['id'] }})">
                                        <i class="bi bi-calendar-check"></i> <span x-text="rateLabel({{ $person['id'] }})"></span>
                                        <i class="bi bi-chevron-right opacity-60"></i>
                                    </button>
                                @endif
                            </span>
                            @if($isMember && !empty($canMarkAttendance))
                                {{-- Tap to mark attended — only DURING the class (browser-local time) --}}
                                <button type="button" x-show="canMarkNow" x-cloak @click="toggle({{ $person['id'] }})" :disabled="busy[{{ $person['id'] }}]"
                                        class="m-press flex items-center gap-1.5 flex-shrink-0"
                                        :aria-pressed="att[{{ $person['id'] }}] ? 'true' : 'false'">
                                    <span class="text-[10px] font-semibold" :class="att[{{ $person['id'] }}] ? 'text-green-600' : 'text-muted-foreground'"
                                          x-text="att[{{ $person['id'] }}] ? '{{ __("personal.personal_schedule_show_present_cap") }}' : '{{ __("personal.personal_schedule_show_mark") }}'"></span>
                                    <span class="w-7 h-7 rounded-lg border-2 grid place-items-center transition-colors"
                                          :class="att[{{ $person['id'] }}] ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 text-transparent'">
                                        <i class="bi bi-check-lg text-sm"></i>
                                    </span>
                                </button>
                                {{-- Before the class starts → locked (avoids marking by mistake) --}}
                                <span x-show="notStarted" x-cloak class="flex items-center gap-1.5 flex-shrink-0">
                                    <span class="text-[10px] font-semibold text-muted-foreground">{{ __('personal.personal_schedule_show_not_started') }}</span>
                                    <span class="w-7 h-7 rounded-lg grid place-items-center bg-gray-100 text-gray-400"><i class="bi bi-lock text-sm"></i></span>
                                </span>
                                {{-- Once the class is over → read-only state --}}
                                <span x-show="isOver" x-cloak class="flex items-center gap-1.5 flex-shrink-0">
                                    <span class="text-[10px] font-semibold" :class="att[{{ $person['id'] }}] ? 'text-green-600' : 'text-muted-foreground'"
                                          x-text="att[{{ $person['id'] }}] ? '{{ __("personal.personal_schedule_show_present_cap") }}' : '{{ __("personal.personal_schedule_show_absent") }}'"></span>
                                    <span class="w-7 h-7 rounded-lg grid place-items-center"
                                          :class="att[{{ $person['id'] }}] ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400'">
                                        <i class="bi text-sm" :class="att[{{ $person['id'] }}] ? 'bi-check-lg' : 'bi-dash-lg'"></i>
                                    </span>
                                </span>
                            @else
                                {{-- Read-only attendance (cancelled / no permission) --}}
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

            {{-- Attendance breakdown sheet (teleported) --}}
            <template x-teleport="body">
            <div>
                <div x-show="bd" x-transition.opacity x-cloak class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm" @click="bd=null"></div>
                <div x-show="bd" x-cloak
                     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                     class="fixed inset-x-0 bottom-0 z-[60] max-h-[85vh] flex flex-col bg-background rounded-t-3xl shadow-2xl">
                    <div class="flex-shrink-0 px-5 pt-3 pb-3 border-b border-border/70 rounded-t-3xl">
                        <div class="w-10 h-1.5 rounded-full bg-gray-300 mx-auto"></div>
                        <div class="flex items-center justify-between mt-3" x-show="bd">
                            <div class="min-w-0">
                                <h2 class="text-base font-black text-foreground truncate" x-text="bd && bd.name"></h2>
                                <p class="text-xs text-muted-foreground"><span class="font-bold text-foreground" x-text="bd && (bd.attended + '/' + bd.total)"></span> {{ __('personal.personal_schedule_show_classes_attended') }}</p>
                            </div>
                            <button type="button" @click="bd=null" class="m-press w-9 h-9 rounded-full bg-muted grid place-items-center flex-shrink-0"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto px-4 py-3" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                        <template x-for="(o, idx) in (bd ? bd.breakdown : [])" :key="idx">
                            <div class="flex items-center gap-3 py-2.5 border-b border-gray-50">
                                <span class="w-7 h-7 rounded-full grid place-items-center flex-shrink-0"
                                      :class="o.attended ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-500'">
                                    <i class="bi" :class="o.attended ? 'bi-check-lg' : 'bi-x-lg'"></i>
                                </span>
                                <span class="text-sm font-medium text-foreground flex-1" x-text="o.label"></span>
                                <span class="text-[11px] font-bold" :class="o.attended ? 'text-green-600' : 'text-red-500'" x-text="o.attended ? '{{ __("personal.personal_schedule_show_attended") }}' : '{{ __("personal.personal_schedule_show_missed") }}'"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
            </template>
        </div>

        <script>
        // Attendance toggles for the shown occurrence — optimistic, persisted via AJAX.
        function classAttendance(cfg) {
            return {
                att: cfg.attended || {},
                members: cfg.members || {},        // id -> {name, attended, total, breakdown[{date,label,attended}]}
                busy: {},
                bd: null,                          // attendance-breakdown sheet payload (a members[id] ref)
                nowTs: Date.now(),
                // Occurrence start/end as local epochs (browser clock → correct window regardless of server TZ).
                _mk: function (hhmm) {
                    if (!hhmm || !cfg.date) return null;
                    var p = String(hhmm).split(':');
                    var d = new Date(cfg.date + 'T00:00:00');         // local midnight of the occurrence
                    d.setHours(parseInt(p[0], 10) || 0, parseInt(p[1], 10) || 0, 0, 0);
                    return d.getTime();
                },
                get startTs() { return this._mk(cfg.startTime); },
                get endTs() {
                    var end = this._mk(cfg.endTime) || this._mk(cfg.startTime);   // prefer end; fall back to start
                    var s = this._mk(cfg.startTime), e = this._mk(cfg.endTime);
                    if (end != null && e != null && s != null && e <= s) end += 86400000;  // crosses midnight
                    return end;
                },
                // Attendance is markable only DURING the class. The server (club timezone) is
                // authoritative for the initial state; the local clock flips these live while
                // the page is open.
                get hasStarted() { return cfg.startedServer === true || (this.startTs != null && this.nowTs >= this.startTs); },
                get isOver()     { return cfg.endedServer === true   || (this.endTs   != null && this.nowTs >= this.endTs); },
                get notStarted() { return !this.hasStarted; },
                get canMarkNow() { return this.hasStarted && !this.isOver; },
                init() {
                    var self = this;
                    if (window.__attTick) clearInterval(window.__attTick);   // dedup across shell swaps
                    window.__attTick = setInterval(function () { self.nowTs = Date.now(); }, 1000);
                },
                openBreakdown(id) { this.bd = this.members[id] || null; },
                rateLabel(id) { const m = this.members[id]; return (m && m.total > 0) ? (m.attended + '/' + m.total) : ''; },
                rateClass(id) {
                    const m = this.members[id];
                    if (!m || !m.total) return '';
                    const p = m.attended / m.total;
                    return p >= 0.75 ? 'bg-green-50 text-green-600' : (p >= 0.5 ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-500');
                },
                // Reflect a just-toggled attendance into the member's rate + breakdown.
                syncRate(id, attended) {
                    const m = this.members[id];
                    if (!m) return;
                    const row = (m.breakdown || []).find(b => b.key === cfg.curKey);
                    if (row) row.attended = attended;            // this session is in the counted history
                    m.attended = (m.breakdown || []).filter(b => b.attended).length;
                },
                get presentCount() { return Object.values(this.att).filter(Boolean).length; },
                async toggle(id) {
                    if (this.busy[id]) return;
                    if (this.notStarted) { window.showToast('info', '{{ __("personal.personal_schedule_show_not_started_toast") }}'); return; }
                    if (this.isOver) { window.showToast('info', '{{ __("personal.personal_schedule_show_attendance_closed") }}'); return; }
                    const next = !this.att[id];
                    this.att[id] = next;          // optimistic
                    this.busy[id] = true;
                    try {
                        const res = await fetch(cfg.url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ user_id: id, date: cfg.date }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) { this.att[id] = !next; window.showToast('error', data.message || '{{ __("personal.personal_schedule_show_could_not_attendance") }}'); }
                        else { this.att[id] = !!data.attended; this.syncRate(id, !!data.attended); }
                    } catch (e) {
                        this.att[id] = !next;
                        window.showToast('error', '{{ __("personal.personal_schedule_show_network_error") }}');
                    } finally {
                        this.busy[id] = false;
                    }
                },
            };
        }
        </script>
    @endif

    {{-- ===== Location (tap the map → open in Google Maps, zoomed to the spot) — after the people list ===== --}}
    @if(!empty($location))
        @php
            $hasCoords = isset($location['lat'], $location['lng']) && $location['lat'] !== null && $location['lng'] !== null;
            // Prefer a coordinate-based link so Google Maps opens zoomed onto the exact pin.
            $mapsLink = $hasCoords
                ? 'https://www.google.com/maps?q=' . $location['lat'] . ',' . $location['lng'] . '&z=17'
                : ($location['maps_url'] ?? null);
        @endphp
        <div class="px-4 mt-4">
            <div class="m-card rounded-2xl p-4">
                {{-- Name replaces the "Location" heading; icon kept. Becomes a link only when there's no map to tap. --}}
                <h2 class="text-sm font-bold text-foreground flex items-center gap-2">
                    <i class="bi bi-geo-alt-fill text-primary flex-shrink-0"></i>
                    @if(!$hasCoords && $mapsLink)
                        <a href="{{ $mapsLink }}" target="_blank" rel="noopener" class="m-press text-primary inline-flex items-center gap-1">
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
                            :id="'classLocMap'"
                            :lat="$location['lat']"
                            :lng="$location['lng']"
                            :address="$location['address'] ?? ''"
                            :readonly="true"
                            :draggable="false"
                            :show-address="false"
                            :show-coords="false"
                            :show-labels="false"
                            height="11rem" />
                        {{-- Transparent overlay makes the whole (static) map tappable → Google Maps.
                             z-[700] sits above leaflet's tile/marker panes but below the attribution control (z-1000), so it stays clickable. --}}
                        <a href="{{ $mapsLink }}" target="_blank" rel="noopener"
                           class="absolute inset-0 z-[700] block group rounded-lg" aria-label="{{ __('personal.personal_schedule_show_open_in_gmaps', ['label' => $location['label']]) }}">
                            <span class="absolute top-2 end-2 inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/95 shadow-sm text-[11px] font-bold text-primary group-hover:bg-white group-active:scale-95 transition-all">
                                <i class="bi bi-box-arrow-up-right"></i> {{ __('personal.personal_schedule_show_open_in_maps') }}
                            </span>
                        </a>
                    </div>
                    {{-- Init inline (shell swaps content; the component's @push create won't re-run). --}}
                    <script>
                        (function () {
                            if (!window.LocationMap) return;
                            window.LocationMap.create({ id: 'classLocMap', defaultLat: {{ Illuminate\Support\Js::from((float) $location['lat']) }}, defaultLng: {{ Illuminate\Support\Js::from((float) $location['lng']) }}, zoom: 15, draggable: false, readonly: true });
                            window.LocationMap.refresh && window.LocationMap.refresh('classLocMap');
                        })();
                    </script>
                @endif
            </div>
        </div>
    @endif

    {{-- ===== Trainee reviews — stars trainees gave + what they think of the class ===== --}}
    @if(($classRatingCount ?? 0) > 0 || !empty($canEditClub) || !empty($canEngage))
        <div class="px-4 mt-4"
             x-data="classReviewsCard({
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
            <div class="m-card rounded-2xl p-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-bold text-foreground flex items-center gap-2"><i class="bi bi-star-half text-primary"></i> {{ __('personal.personal_schedule_show_trainee_reviews') }}</h2>
                    <span x-show="count>0" x-cloak class="text-[11px] text-muted-foreground"><span x-text="count"></span> <span x-text="count===1 ? '{{ __("personal.personal_schedule_show_rating") }}' : '{{ __("personal.personal_schedule_show_ratings") }}'"></span></span>
                </div>

                {{-- Summary: big average + stars + per-star distribution --}}
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
                                    <div class="h-full rounded-full bg-amber-400 m-bar-fill" :style="`width:${count ? Math.round((dist[n]||0)/count*100) : 0}%`"></div>
                                </div>
                                <span class="text-[10px] text-muted-foreground w-4 text-end" x-text="dist[n]||0"></span>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Empty state (for non-enrolled viewers; enrolled trainees get the "Be the first" button instead) --}}
                @empty($canEngage)
                <p x-show="count===0" x-cloak class="text-xs text-muted-foreground mt-2">
                    {{ !empty($canEditClub) ? __('personal.personal_schedule_show_no_reviews_admin') : __('personal.personal_schedule_show_no_reviews_yet') }}
                </p>
                @endempty

                {{-- Your review — tap to revise, trash to delete (only when you've already reviewed) --}}
                <div x-show="hasReview" x-cloak class="mt-4 pt-4 border-t border-gray-100">
                    <div class="rounded-2xl p-3 border border-primary/30 bg-accent/40 flex items-start gap-3">
                        <span class="w-9 h-9 rounded-full bg-primary text-white grid place-items-center text-xs font-bold flex-shrink-0"><i class="bi bi-person-fill"></i></span>
                        <button type="button" @click="editMine()" class="m-press min-w-0 flex-1 text-start">
                            <span class="flex items-center gap-2">
                                <span class="text-xs font-bold text-primary">{{ __('personal.personal_schedule_show_your_review') }}</span>
                                <span class="text-[11px] text-primary inline-flex items-center gap-1 flex-shrink-0"><i class="bi bi-pencil"></i> {{ __('personal.personal_schedule_show_tap_to_revise') }}</span>
                            </span>
                            <span class="flex items-center gap-0.5 mt-1">
                                <template x-for="n in 5" :key="'my'+n">
                                    <i class="bi text-[12px]" :class="n <= myRating ? 'bi-star-fill text-amber-400' : 'bi-star text-gray-300'"></i>
                                </template>
                            </span>
                            <span class="block text-sm text-foreground mt-1 whitespace-pre-line" x-show="myComment" x-text="myComment"></span>
                            <span class="block text-[11px] text-muted-foreground italic mt-1" x-show="!myComment">{{ __('personal.personal_schedule_show_no_comment_yet') }}</span>
                        </button>
                        <button type="button" @click="deleteMine()" :disabled="deleting"
                                class="m-press w-8 h-8 rounded-lg grid place-items-center text-red-500 hover:bg-red-50 flex-shrink-0 disabled:opacity-50" title="{{ __('personal.personal_schedule_show_delete_review') }}">
                            <i class="bi" :class="deleting ? 'bi-arrow-repeat animate-spin' : 'bi-trash'"></i>
                        </button>
                    </div>
                </div>

                {{-- Be the first / add your review — only trainees who attended & whose class has started --}}
                @if(!empty($canReview))
                <div x-show="!hasReview" x-cloak class="mt-4" :class="count>0 ? 'pt-4 border-t border-gray-100' : ''">
                    <button type="button" @click="startReview()"
                            class="m-press w-full py-3 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2"
                            style="background: {{ $s['color'] }};">
                        <i class="bi bi-star-fill"></i>
                        <span x-text="count===0 ? '{{ __("personal.personal_schedule_show_be_first_review") }}' : '{{ __("personal.personal_schedule_show_add_your_review") }}'"></span>
                    </button>
                </div>
                @elseif(!empty($canEngage))
                {{-- Enrolled, but not eligible to review yet (didn't attend, or class hasn't started) --}}
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

                {{-- Other trainees' written reviews --}}
                <div class="mt-4 pt-4 border-t border-gray-100 space-y-3.5" x-show="othersReviews.length" x-cloak>
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

                {{-- Ratings exist but none (from others) had a written comment --}}
                <p x-show="count>0 && !othersReviews.length && !hasReview" x-cloak class="text-[11px] text-muted-foreground mt-3 pt-3 border-t border-gray-100">
                    {{ __('personal.personal_schedule_show_ratings_no_comment') }}
                </p>
            </div>
        </div>

        <script>
        function classReviewsCard(cfg) {
            return {
                avg: (cfg.avg ?? null),
                count: cfg.count || 0,
                reviews: cfg.reviews || [],
                dist: cfg.dist || {},
                myUserId: cfg.myUserId || null,
                myRating: cfg.myRating || 0,
                myComment: cfg.myComment || '',
                deleting: false,
                // Has the current user already rated this class?
                get hasReview() { return this.myRating > 0; },
                // Written reviews from everyone except the current user (theirs shows in its own block).
                get othersReviews() {
                    const me = String(this.myUserId);
                    return (this.reviews || []).filter(r => String(r.user_id) !== me);
                },
                // No review yet → open the rating form straight away.
                startReview() { window.dispatchEvent(new CustomEvent('open-review-form')); },
                // Already reviewed → confirm first, then open the form pre-filled to edit.
                async editMine() {
                    const ok = await window.confirmAction({
                        title: '{{ __("personal.personal_schedule_show_revise_title") }}',
                        message: '{{ __("personal.personal_schedule_show_revise_message") }}',
                        type: 'info',
                        confirmText: '{{ __("personal.personal_schedule_show_edit_review") }}',
                        cancelText: '{{ __("personal.personal_schedule_show_keep_as_is") }}',
                    });
                    if (ok) window.dispatchEvent(new CustomEvent('open-review-form'));
                },
                // Already reviewed → confirm, then delete the review entirely.
                async deleteMine() {
                    if (this.deleting) return;
                    const ok = await window.confirmAction({
                        title: '{{ __("personal.personal_schedule_show_delete_title") }}',
                        message: '{{ __("personal.personal_schedule_show_delete_message") }}',
                        type: 'danger',
                        confirmText: '{{ __("shared.delete") }}',
                        cancelText: '{{ __("shared.cancel") }}',
                    });
                    if (!ok) return;
                    this.deleting = true;
                    try {
                        const res = await fetch(cfg.deleteUrl, {
                            method: 'DELETE',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) { window.showToast('error', data.message || '{{ __("personal.personal_schedule_show_could_not_delete_review") }}'); return; }
                        this.avg = (data.average ?? null); this.count = data.count || 0;
                        this.reviews = data.comments || []; this.dist = data.distribution || {};
                        this.myRating = 0; this.myComment = '';
                        // Reset the form's state too, so reopening it starts fresh.
                        window.dispatchEvent(new CustomEvent('class-review-deleted', { detail: { average: (data.average ?? null), count: data.count || 0 } }));
                        window.showToast('success', data.message || '{{ __("personal.personal_schedule_show_review_deleted") }}');
                    } catch (e) { window.showToast('error', '{{ __("personal.personal_schedule_show_network_error") }}'); }
                    finally { this.deleting = false; }
                },
                // Live patch when the current user submits/updates their class rating.
                patch(d) {
                    if (!d) return;
                    if (d.average !== undefined) this.avg = d.average;
                    if (d.count !== undefined) this.count = d.count || 0;
                    if (d.comments !== undefined) this.reviews = d.comments || [];
                    if (d.distribution !== undefined) this.dist = d.distribution || {};
                    if (d.mine) { this.myRating = d.mine.rating || 0; this.myComment = d.mine.comment || ''; }
                },
            };
        }
        </script>
    @endif

    {{-- ===== Trainee engagement: rating form sheet (emoji + rate class + rate trainer) ===== --}}
    {{-- Hosted here, opened from the "Trainee reviews" card's buttons via the open-review-form event. --}}
    @if(!empty($canEngage))
        <div class="px-4"
             x-data="classEngage({
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
            @if(!empty($canEngage))
            {{-- Rating sheet — opened from the "Trainee reviews" card via the open-review-form event.
                 Teleported to body so the .mobile-stagger transform can't clip the fixed overlay. --}}
            <template x-teleport="body">
                <div x-show="panel" x-cloak class="fixed inset-0 z-[60]">
                    <div class="absolute inset-0 bg-black/40" @click="panel = false"
                         x-show="panel" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>
                    <div class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl"
                         x-show="panel" x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
                        {{-- Header --}}
                        <div class="flex-shrink-0 px-4 pt-3 pb-3 border-b border-gray-100">
                            <div class="w-10 h-1.5 bg-gray-300 rounded-full mx-auto mb-3"></div>
                            <div class="flex items-center justify-between">
                                <h2 class="text-base font-bold text-foreground flex items-center gap-2"><i class="bi bi-emoji-smile text-primary"></i> {{ __('personal.personal_schedule_show_enjoyed_class') }}</h2>
                                <button type="button" @click="panel = false" class="w-8 h-8 grid place-items-center rounded-full hover:bg-muted text-muted-foreground"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>

                        {{-- Scrollable body --}}
                        <div class="flex-1 overflow-y-auto px-4 py-4">
                            {{-- Emoji reactions (WhatsApp-style: borderless, centered) --}}
                            <div class="flex flex-wrap justify-center items-end gap-1">
                                @foreach($emojiChoices as $e)
                                    <button type="button" @click="react('{{ $e }}')"
                                            class="m-press relative w-11 h-11 rounded-full grid place-items-center transition-all active:scale-90"
                                            :class="mine==='{{ $e }}' ? 'bg-accent ring-2 ring-primary' : 'hover:bg-muted'">
                                        <span class="text-2xl leading-none">{{ $e }}</span>
                                        <span x-show="counts['{{ $e }}']" x-cloak
                                              class="absolute -bottom-1 -end-0.5 min-w-4 h-4 px-1 rounded-full bg-white shadow text-[10px] font-bold text-foreground grid place-items-center"
                                              x-text="counts['{{ $e }}']"></span>
                                    </button>
                                @endforeach
                            </div>

                            {{-- Rate the class itself --}}
                            <div class="mt-4 pt-4 border-t border-gray-100">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-foreground">{{ __('personal.personal_schedule_show_rate_this_class') }}</p>
                                    <span x-show="classAvg !== null" x-cloak class="text-[11px] text-muted-foreground">
                                        <i class="bi bi-star-fill text-amber-400"></i> <span x-text="classAvg"></span> {{ __('personal.personal_schedule_show_avg') }} · <span x-text="classCount"></span>
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5 mt-2">
                                    <template x-for="n in 5" :key="'c'+n">
                                        <button type="button" @click="rateClass(n)" class="m-press text-3xl leading-none"
                                                :class="n <= classRating ? 'text-amber-400' : 'text-gray-300'">
                                            <i class="bi" :class="n <= classRating ? 'bi-star-fill' : 'bi-star'"></i>
                                        </button>
                                    </template>
                                </div>
                                <div class="mt-3" x-show="classRating>0" x-cloak x-transition>
                                    <textarea x-model="classComment" rows="2" maxlength="500"
                                              placeholder="{{ __('personal.personal_schedule_show_class_comment_ph') }}"
                                              class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                                    <p class="text-[11px] text-muted-foreground mt-1">{{ __('personal.personal_schedule_show_shared_everyone') }}</p>
                                </div>
                            </div>

                            {{-- Rate the trainer --}}
                            @if(!empty($rateInstructorId))
                                <div class="mt-4 pt-4 border-t border-gray-100">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-semibold text-foreground">{{ __('personal.personal_schedule_show_rate_name', ['name' => $coachRatingName]) }}</p>
                                        @if(!empty($coachRatingAvg))
                                            <span class="text-[11px] text-muted-foreground"><i class="bi bi-star-fill text-amber-400"></i> {{ number_format($coachRatingAvg, 1) }} {{ __('personal.personal_schedule_show_avg') }}</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-2">
                                        <template x-for="n in 5" :key="n">
                                            <button type="button" @click="rate(n)" class="m-press text-3xl leading-none"
                                                    :class="n <= rating ? 'text-amber-400' : 'text-gray-300'">
                                                <i class="bi" :class="n <= rating ? 'bi-star-fill' : 'bi-star'"></i>
                                            </button>
                                        </template>
                                    </div>
                                    <div class="mt-3" x-show="rating>0" x-cloak x-transition>
                                        <textarea x-model="comment" rows="2" maxlength="500"
                                                  placeholder="{{ __('personal.personal_schedule_show_coach_comment_ph') }}"
                                                  class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                                        <p class="text-[11px] text-muted-foreground mt-1">{{ __('personal.personal_schedule_show_shows_on_profile') }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Sticky footer: one submit closes the sheet --}}
                        <div class="flex-shrink-0 px-4 pt-3 border-t border-gray-100 bg-white" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                            <button type="button" @click="submitAll()" :disabled="savingAll || (classRating<1 && rating<1)"
                                    class="m-press w-full py-3.5 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-50"
                                    style="background: {{ $s['color'] }};">
                                <i class="bi" :class="savingAll ? 'bi-arrow-repeat animate-spin' : 'bi-check2-circle'"></i>
                                <span x-text="savingAll ? '{{ __("personal.personal_schedule_show_saving") }}' : '{{ __("personal.personal_schedule_show_submit_rating") }}'"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
            @endif
        </div>

        <script>
        function classEngage(cfg) {
            return {
                panel: false, savingAll: false,
                counts: cfg.counts || {}, mine: cfg.mine || null, rating: cfg.rating || 0, comment: cfg.comment || '',
                classRating: cfg.classRating || 0, classComment: cfg.classComment || '',
                classAvg: (cfg.classAvg ?? null), classCount: cfg.classCount || 0, comments: cfg.comments || [],
                async react(emoji) {
                    try {
                        const res = await fetch(cfg.reactUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            credentials: 'same-origin', body: JSON.stringify({ emoji: emoji, date: cfg.date }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) { window.showToast('error', data.message || '{{ __("personal.personal_schedule_show_could_not_react") }}'); return; }
                        this.counts = data.counts || {}; this.mine = data.mine || null;
                    } catch (e) { window.showToast('error', '{{ __("personal.personal_schedule_show_network_error") }}'); }
                },
                rate(n) { this.rating = n; },           // select locally; saved on submit
                rateClass(n) { this.classRating = n; }, // select locally; saved on submit
                // One submit: save whichever ratings were given, then close the sheet.
                async submitAll() {
                    if (this.savingAll) return;
                    if (this.classRating < 1 && this.rating < 1) { this.panel = false; return; }
                    this.savingAll = true;
                    try {
                        if (this.classRating > 0) await this._post(cfg.rateClassUrl, { rating: this.classRating, comment: this.classComment || null }, (data) => {
                            this.classAvg = (data.average ?? null); this.classCount = data.count || 0; this.comments = data.comments || [];
                            // Patch the standalone "Trainee reviews" card (lives in its own Alpine scope, beneath the map).
                            window.dispatchEvent(new CustomEvent('class-reviews-updated', { detail: {
                                average: (data.average ?? null), count: data.count || 0,
                                comments: data.comments || [], distribution: data.distribution || {},
                                mine: { rating: this.classRating, comment: this.classComment || '' },
                            }}));
                        });
                        if (this.rating > 0) await this._post(cfg.rateUrl, { rating: this.rating, comment: this.comment || null, date: cfg.date });
                        window.showToast('success', '{{ __("personal.personal_schedule_show_rating_saved") }}');
                        this.panel = false;
                    } catch (e) { window.showToast('error', e.message || '{{ __("personal.personal_schedule_show_could_not_save_rating") }}'); }
                    finally { this.savingAll = false; }
                },
                async _post(url, body, onOk) {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        credentials: 'same-origin', body: JSON.stringify(body),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.success) throw new Error(data.message || '{{ __("personal.personal_schedule_show_could_not_save_rating") }}');
                    if (onOk) onOk(data);
                    return data;
                },
            };
        }
        </script>
    @endif

    {{-- ===== Action ===== --}}
    @if(empty($synced))
    <div class="px-4 mt-4">
        <button type="button" @click="complete()" x-show="!done"
                class="m-press w-full py-3.5 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2"
                style="background: {{ $s['color'] }};">
            <i class="bi bi-play-circle-fill"></i> {{ __('personal.personal_schedule_show_start_complete') }}
        </button>
        <div x-show="done" x-cloak class="m-card rounded-2xl p-4 text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl grid place-items-center text-white m-float" style="background: #10b981;"><i class="bi bi-check2-circle text-2xl"></i></div>
            <p class="text-sm font-bold text-green-600 mt-2">{{ __('personal.personal_schedule_show_session_completed') }}</p>
            <p class="text-xs text-muted-foreground mt-0.5">{{ __('personal.personal_schedule_show_logged_to', ['name' => $member['name']]) }}</p>
        </div>
    </div>
    @endif

    {{-- ===== Manage (owner only — personal sessions are editable) ===== --}}
    @if(!empty($isOwner))
        <div class="px-4 mt-3 flex gap-2">
            <button type="button"
                    onclick="window.dispatchEvent(new CustomEvent('open-schedule-form', { detail: window.__schedSession }))"
                    class="m-press flex-1 py-3 rounded-2xl border border-border bg-white text-foreground font-bold text-sm flex items-center justify-center gap-2">
                <i class="bi bi-pencil"></i> {{ __('shared.edit') }}
            </button>
        </div>
    @endif

    {{-- ===== Manage (coach / club owner / admin — one button opens a combined action menu) ===== --}}
    @if(!empty($synced) && !empty($canEditClub))
        @php
            $isTeaching = ($s['source'] ?? '') === 'teaching';
            $clubName   = $s['club'] ?? 'this club';
        @endphp
        <div class="px-4 mt-4" x-data="{
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
                    if (end != null && e != null && s != null && e <= s) end += 86400000;  // crosses midnight
                    return end;
                },
                get isOver() { return this.endedServer === true || (this.endTs != null && this.nowTs >= this.endTs); },
                init() { setInterval(() => { this.nowTs = Date.now(); }, 30000); }
             }">
            {{-- Single entry point --}}
            <div class="rounded-2xl p-4 bg-white border border-border transition-opacity" :class="isOver ? 'opacity-60' : ''">
                <p class="text-xs font-bold uppercase tracking-wide text-muted-foreground flex items-center gap-1.5">
                    <i class="bi {{ $isTeaching ? 'bi-person-video3' : 'bi-shield-lock' }} text-primary"></i> {{ $isTeaching ? __('personal.personal_schedule_show_coach_tools') : __('personal.personal_schedule_show_club_management') }}
                </p>
                <p class="text-sm text-muted-foreground mt-1">{{ $isTeaching ? __('personal.personal_schedule_show_you_coach') : __('personal.personal_schedule_show_you_manage') }} <span class="font-semibold text-foreground">{{ $clubName }}</span>. {{ __('personal.personal_schedule_show_manage_desc') }}</p>
                <button type="button" @click="if (!isOver) menuOpen = true" :disabled="isOver"
                        class="m-press w-full mt-3 py-3 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2"
                        :class="isOver ? 'cursor-not-allowed' : ''"
                        style="background: {{ $s['color'] }};">
                    <i class="bi bi-sliders2"></i> {{ __('personal.personal_schedule_show_manage_class') }}
                </button>
                <p x-show="isOver" x-cloak class="text-[11px] text-amber-600 mt-2 inline-flex items-center gap-1"><i class="bi bi-lock-fill"></i> {{ __('personal.personal_schedule_show_managing_closed') }}</p>
            </div>

            {{-- Combined action menu (teleported bottom-sheet) --}}
            <template x-teleport="body">
            <div>
                <div x-show="menuOpen" x-transition.opacity x-cloak class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm" @click="menuOpen=false"></div>
                <div x-show="menuOpen" x-cloak
                     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                     class="fixed inset-x-0 bottom-0 z-[60] max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl">
                    <div class="flex-shrink-0 px-5 pt-3 pb-3 border-b border-border/70 rounded-t-3xl text-white" style="background: linear-gradient(160deg, {{ $s['color'] }}, {{ $s['color'] }}cc);">
                        <div class="w-10 h-1.5 rounded-full bg-white/40 mx-auto"></div>
                        <div class="flex items-center justify-between mt-3">
                            <div class="min-w-0">
                                <h2 class="text-base font-black leading-tight truncate">{{ __('personal.personal_schedule_show_manage_class') }}</h2>
                                <p class="text-[11px] text-white/80 truncate">{{ $s['title'] ?? $clubName }}{{ !empty($occurrenceLabel) ? ' · '.$occurrenceLabel : '' }}</p>
                            </div>
                            <button type="button" @click="menuOpen=false" class="m-press w-9 h-9 rounded-full bg-white/20 border border-white/30 grid place-items-center flex-shrink-0"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                        {{-- Edit class --}}
                        <button type="button"
                                @click="menuOpen=false; window.dispatchEvent(new CustomEvent('open-schedule-form', { detail: window.__schedClass }))"
                                class="m-press w-full text-start rounded-2xl p-4 flex items-center gap-3 bg-white border border-border">
                            <span class="w-11 h-11 rounded-xl grid place-items-center text-white flex-shrink-0" style="background: {{ $s['color'] }};"><i class="bi bi-pencil-square text-lg"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground">{{ __('personal.personal_schedule_show_edit_class') }}</span>
                                <span class="block text-[11px] text-muted-foreground">{{ __('personal.personal_schedule_show_edit_class_desc') }}</span>
                            </span>
                            <i class="bi bi-chevron-right text-gray-300"></i>
                        </button>

                        {{-- Assign / change substitute --}}
                        <button type="button"
                                @click="menuOpen=false; window.dispatchEvent(new CustomEvent('open-substitute-sheet'))"
                                class="m-press w-full text-start rounded-2xl p-4 flex items-center gap-3 bg-white border border-border">
                            <span class="w-11 h-11 rounded-xl grid place-items-center text-white flex-shrink-0 bg-amber-500"><i class="bi bi-arrow-left-right text-lg"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground">{{ $substitute ? __('personal.personal_schedule_show_change_substitute') : __('personal.personal_schedule_show_assign_substitute') }}</span>
                                <span class="block text-[11px] text-muted-foreground">
                                    @if($substitute)
                                        {{ __('personal.personal_schedule_show_currently') }} <span class="font-semibold text-foreground">{{ $substitute['name'] ?? __('personal.personal_schedule_show_someone') }}</span> {{ __('personal.personal_schedule_show_is_covering') }}{{ !empty($occurrenceLabel) ? ' '.__('personal.personal_schedule_show_on').' '.$occurrenceLabel : '' }}.
                                    @else
                                        {{ __('personal.personal_schedule_show_assign_substitute_desc') }}
                                    @endif
                                </span>
                            </span>
                            <i class="bi bi-chevron-right text-gray-300"></i>
                        </button>

                        {{-- Remove current substitute --}}
                        @if($substitute)
                        <button type="button"
                                @click="menuOpen=false; window.dispatchEvent(new CustomEvent('remove-substitute'))"
                                class="m-press w-full text-start rounded-2xl p-4 flex items-center gap-3 bg-white border border-red-200">
                            <span class="w-11 h-11 rounded-xl grid place-items-center text-red-500 flex-shrink-0 bg-red-50"><i class="bi bi-person-dash text-lg"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground">{{ __('personal.personal_schedule_show_remove_substitute') }}</span>
                                <span class="block text-[11px] text-muted-foreground">{{ __('personal.personal_schedule_show_remove_substitute_desc') }}</span>
                            </span>
                            <i class="bi bi-chevron-right text-gray-300"></i>
                        </button>
                        @endif

                        {{-- Cancel / restore --}}
                        @if(!empty($cancelled))
                        <button type="button"
                                @click="menuOpen=false; window.dispatchEvent(new CustomEvent('restore-class'))"
                                class="m-press w-full text-start rounded-2xl p-4 flex items-center gap-3 bg-white border border-border">
                            <span class="w-11 h-11 rounded-xl grid place-items-center text-white flex-shrink-0 bg-green-600"><i class="bi bi-arrow-counterclockwise text-lg"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground">{{ __('personal.personal_schedule_show_restore_class') }}</span>
                                <span class="block text-[11px] text-muted-foreground">{{ __('personal.personal_schedule_show_restore_class_desc') }}</span>
                            </span>
                            <i class="bi bi-chevron-right text-gray-300"></i>
                        </button>
                        @else
                        <button type="button"
                                @click="menuOpen=false; window.dispatchEvent(new CustomEvent('open-cancel-sheet'))"
                                class="m-press w-full text-start rounded-2xl p-4 flex items-center gap-3 bg-white border border-red-200">
                            <span class="w-11 h-11 rounded-xl grid place-items-center text-white flex-shrink-0 bg-destructive"><i class="bi bi-calendar-x text-lg"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-destructive">{{ __('personal.personal_schedule_show_cancel_class') }}</span>
                                <span class="block text-[11px] text-muted-foreground">{{ __('personal.personal_schedule_show_cancel_class_desc') }}</span>
                            </span>
                            <i class="bi bi-chevron-right text-gray-300"></i>
                        </button>
                        @endif
                    </div>
                </div>
            </div>
            </template>
        </div>

        {{-- Substitute sheet (trigger-only — opened from the menu via window events) --}}
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

        {{-- Cancel / restore sheet (trigger-only — opened from the menu via window events) --}}
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

            {{-- Cancel sheet (teleported) --}}
            <template x-teleport="body">
            <div>
                <div x-show="open" x-transition.opacity x-cloak class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm" @click="open=false"></div>
                <div x-show="open" x-cloak
                     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                     class="fixed inset-x-0 bottom-0 z-[60] max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl">
                    <div class="flex-shrink-0 px-5 pt-3 pb-3 border-b border-border/70 rounded-t-3xl bg-destructive text-white">
                        <div class="w-10 h-1.5 rounded-full bg-white/40 mx-auto"></div>
                        <div class="flex items-center justify-between mt-3">
                            <div class="min-w-0"><h2 class="text-base font-black leading-tight">{{ __('personal.personal_schedule_show_cancel_class') }}</h2><p class="text-[11px] text-white/80">{{ __('personal.personal_schedule_show_auto_makeup_credit') }}</p></div>
                            <button type="button" @click="open=false" class="m-press w-9 h-9 rounded-full bg-white/20 border border-white/30 grid place-items-center flex-shrink-0"><i class="bi bi-x-lg"></i></button>
                        </div>
                    </div>
                    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
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
                        <p class="text-[11px] text-muted-foreground">{{ __('personal.personal_schedule_show_range_hint') }}</p>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('personal.personal_schedule_show_reason') }} <span class="text-muted-foreground font-normal">{{ __('personal.personal_schedule_show_optional') }}</span></label>
                            <textarea x-model="form.reason" rows="2" maxlength="300" placeholder="{{ __('personal.personal_schedule_show_reason_ph') }}" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                        </div>

                        {{-- Creditable toggle --}}
                        <button type="button" @click="form.credit = !form.credit"
                                class="m-press w-full flex items-center gap-3 rounded-xl border p-3 text-start transition-colors"
                                :class="form.credit ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-white'">
                            <span class="w-10 h-6 rounded-full flex items-center transition-colors flex-shrink-0" :class="form.credit ? 'bg-green-500 justify-end' : 'bg-gray-300 justify-start'">
                                <span class="w-5 h-5 rounded-full bg-white shadow mx-0.5"></span>
                            </span>
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-foreground">{{ __('personal.personal_schedule_show_credit_makeup') }}</span>
                                <span class="block text-[11px] text-muted-foreground" x-text="form.credit ? '{{ __("personal.personal_schedule_show_credit_on") }}' : '{{ __("personal.personal_schedule_show_credit_off") }}'"></span>
                            </span>
                        </button>
                    </div>
                    <div class="flex-shrink-0 px-4 py-3 border-t border-border bg-white" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                        <button type="button" @click="submit()" :disabled="busy"
                                class="m-press w-full h-12 rounded-xl bg-destructive text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-60">
                            <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-calendar-x'"></i> {{ __('personal.personal_schedule_show_cancel_class') }}
                        </button>
                    </div>
                </div>
            </div>
            </template>
        </div>

        <script>
        function classCancelTool(cfg) {
            return {
                open: false, busy: false,
                todayStr: new Date().toISOString().slice(0, 10),
                form: { from: cfg.date, to: '', reason: '', credit: true },
                async submit() {
                    if (!this.form.from) { window.showToast('error', '{{ __("personal.personal_schedule_show_pick_date") }}'); return; }
                    this.busy = true;
                    try {
                        const res = await fetch(cfg.cancelUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ from: this.form.from, to: this.form.to || null, reason: this.form.reason || null, credit: this.form.credit }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) { window.showToast('error', data.message || '{{ __("personal.personal_schedule_show_could_not_cancel") }}'); this.busy = false; return; }
                        window.showToast('success', data.message || '{{ __("personal.personal_schedule_show_class_cancelled") }}');
                        this.open = false; this._back();
                    } catch (e) { window.showToast('error', '{{ __("personal.personal_schedule_show_network_error") }}'); }
                    finally { this.busy = false; }
                },
                async restore() {
                    this.busy = true;
                    try {
                        const res = await fetch(cfg.uncancelUrl, {
                            method: 'DELETE',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ date: cfg.date }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) { window.showToast('error', data.message || '{{ __("personal.personal_schedule_show_could_not_restore") }}'); this.busy = false; return; }
                        window.showToast('success', data.message || '{{ __("personal.personal_schedule_show_class_restored") }}');
                        this._back();
                    } catch (e) { window.showToast('error', '{{ __("personal.personal_schedule_show_network_error") }}'); }
                    finally { this.busy = false; }
                },
                _back() {
                    setTimeout(function () {
                        var a = document.querySelector('a[data-route="me.schedule"]');
                        if (a) a.click(); else window.location.href = cfg.listUrl;
                    }, 400);
                },
            };
        }
        </script>
    @endif

</div>

{{-- Full create/edit sheet — shared by personal sessions (owner) and club classes (coach/manager). --}}
@if(!empty($isOwner) || (!empty($synced) && !empty($canEditClub)))
    <x-schedule-session-modal :subjects="$subjectsList ?? []" :facilities="$clubFacilities ?? []" :instructors="$clubInstructors ?? []" />
    <script>
        @if(!empty($isOwner))
        // Personal session this detail renders — handed to the edit sheet on demand.
        window.__schedSession = {{ Illuminate\Support\Js::from($s) }};
        @endif
        @if(!empty($synced) && !empty($canEditClub))
        // Club class this detail renders + where to PUT edits (full form).
        window.__schedClass = Object.assign({}, {{ Illuminate\Support\Js::from($s) }}, { update_url: '{{ $updateUrl }}' });
        @endif
        // After an edit/delete from the detail page, return to the live schedule list.
        window.addEventListener('schedule-session-saved', function () {
            setTimeout(function () {
                var a = document.querySelector('a[data-route="me.schedule"]');
                if (a) a.click(); else window.location.href = "{{ route('me.schedule') }}";
            }, 400);
        });
        window.addEventListener('schedule-session-deleted', function () {
            window.location.href = "{{ route('me.schedule') }}";
        });
    </script>
@endif
@endsection
