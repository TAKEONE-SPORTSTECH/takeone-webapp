{{--
    The club's weekly schedule — ONE copy, drawn by the public club page on
    desktop AND mobile (asked for 2026-09-26: "must be same in mobile"). Day
    chips from today onwards; on today, each class is marked live / upcoming /
    finished by the reader's own clock and ordered live first; the pulsing dot,
    status chips, instructor, facility, duration, package and day tags — all of
    it. Styles are the shared .class-card / .status-chip / .live-dot rules in
    resources/css/app.css.

    Needs `$club` with packages.activities (pivot schedule) and instructors.user.
--}}
            @php
                $dayOrder    = ['saturday','sunday','monday','tuesday','wednesday','thursday','friday'];
                $dayAbbr     = ['saturday'=>__('clubs::club.platform_show_day_sat'),'sunday'=>__('clubs::club.platform_show_day_sun'),'monday'=>__('clubs::club.platform_show_day_mon'),'tuesday'=>__('clubs::club.platform_show_day_tue'),'wednesday'=>__('clubs::club.platform_show_day_wed'),'thursday'=>__('clubs::club.platform_show_day_thu'),'friday'=>__('clubs::club.platform_show_day_fri')];
                $todayKey    = strtolower(now()->format('l')); // e.g. 'monday'
                $todayIndex  = array_search($todayKey, $dayOrder);
                $visibleDays = array_slice($dayOrder, $todayIndex !== false ? $todayIndex : 0);

                // Build flat list of schedule slots from all package activities
                $instructorNameMap = $club->instructors->keyBy('id')->map(fn($i) => [
                    'name'    => $i->user?->full_name ?? $i->user?->name,
                    'picture' => $i->user?->profile_picture,
                ]);
                $scheduleSlots = [];
                foreach ($club->packages as $pkg) {
                    foreach ($pkg->activities as $activity) {
                        $pivotSchedule = $activity->pivot->schedule ?? null;
                        $scheduleData  = is_string($pivotSchedule)
                            ? json_decode($pivotSchedule, true)
                            : (is_array($pivotSchedule) ? $pivotSchedule : null);
                        if (!$scheduleData) continue;

                        // Group by time key so Mon+Wed same time = one slot
                        $timeGroups = [];
                        foreach ($scheduleData as $s) {
                            $day   = strtolower($s['day'] ?? '');
                            $start = $s['start_time'] ?? '';
                            $end   = $s['end_time']   ?? '';
                            if (!$day || !$start || !$end) continue;
                            $key = $start . '-' . $end;
                            if (!isset($timeGroups[$key])) {
                                $timeGroups[$key] = [
                                    'days'          => [],
                                    'start'         => $start,
                                    'end'           => $end,
                                    'facility_name' => $s['facility_name'] ?? null,
                                ];
                            }
                            if (!in_array($day, $timeGroups[$key]['days'])) {
                                $timeGroups[$key]['days'][] = $day;
                            }
                        }

                        $instructorId      = $activity->pivot->instructor_id ?? null;
                        $instructorData    = $instructorId ? ($instructorNameMap[$instructorId] ?? null) : null;
                        $instructorName    = $instructorData['name'] ?? null;
                        $instructorPicture = $instructorData['picture'] ?? null;
                        foreach ($timeGroups as $slot) {
                            $duration = abs(\Carbon\Carbon::parse($slot['end'])->diffInMinutes(\Carbon\Carbon::parse($slot['start'])));
                            $scheduleSlots[] = [
                                'activity_name'   => $activity->title ?? $activity->name,
                                'picture_url'     => $pkg->cover_image ?? null,
                                'package_name'    => $pkg->name,
                                'instructor_name'    => $instructorName,
                                'instructor_picture' => $instructorPicture,
                                'days'            => $slot['days'],       // array of lowercase day names
                                'start'           => $slot['start'],
                                'end'             => $slot['end'],
                                'duration'        => $duration,
                                'facility_name'   => $slot['facility_name'],
                            ];
                        }
                    }
                }

                // Sort slots by start time
                usort($scheduleSlots, fn($a, $b) => strcmp($a['start'], $b['start']));

                // Class status is computed client-side in JavaScript to use browser local time
            @endphp

            <div x-data="{
                     activeDay: '{{ $todayKey }}',
                     getStatus(start, end) {
                         const now = new Date();
                         const pad = n => String(n).padStart(2, '0');
                         const t = pad(now.getHours()) + ':' + pad(now.getMinutes());
                         if (t < start) return 'upcoming';
                         if (t <= end) return 'live';
                         return 'finished';
                     }
                 }">

                {{-- Day filter chips (today onwards in the week) --}}
                <div class="flex flex-wrap justify-center gap-2 mb-5">
                    @foreach($visibleDays as $dayKey)
                    <button type="button"
                            @click="activeDay = '{{ $dayKey }}'"
                            :class="activeDay === '{{ $dayKey }}' ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 border-gray-200 hover:border-primary hover:text-primary'"
                            class="px-5 py-2 rounded-full border text-sm font-semibold transition-colors {{ $dayKey === $todayKey ? 'ring-2 ring-primary/30' : '' }}">
                        {{ $dayAbbr[$dayKey] }}
                    </button>
                    @endforeach
                </div>

                @if(count($scheduleSlots) > 0)
                <div class="max-w-3xl mx-auto flex flex-col gap-3">
                    @foreach($scheduleSlots as $slot)
                    <div class="class-card"
                         x-show="@js(array_map('strval', $slot['days'])).includes(activeDay)"
                         :class="activeDay === '{{ $todayKey }}' ? getStatus('{{ $slot['start'] }}', '{{ $slot['end'] }}') + '-card' : ''"
                         :style="{ order: activeDay === '{{ $todayKey }}' ? ({'live':0,'upcoming':1,'finished':2}[getStatus('{{ $slot['start'] }}', '{{ $slot['end'] }}')] ?? 0) : null }"
                         x-cloak>
                        <div class="class-thumb">
                            @if($slot['picture_url'])
                            <img src="{{ file_url($slot['picture_url']) }}" alt="{{ $slot['activity_name'] }}" class="w-full h-full object-cover">
                            @else
                            <div class="w-full h-full bg-gradient-to-br from-primary to-primary/70 flex items-center justify-center min-h-[80px]">
                                <i class="bi bi-activity text-white text-xl"></i>
                            </div>
                            @endif
                        </div>
                        <div class="flex-grow flex flex-col">
                            <div class="flex justify-between items-start mb-1">
                                <div>
                                    <h6 class="text-base font-bold mb-0">{{ $slot['activity_name'] }}</h6>
                                    <div class="class-meta text-muted-foreground flex items-center gap-x-4 mt-0.5 text-sm">
                                        <span><i class="bi bi-clock me-1"></i>{{ \Carbon\Carbon::parse($slot['start'])->format('g:i A') }} – {{ \Carbon\Carbon::parse($slot['end'])->format('g:i A') }}</span>
                                        <span class="flex items-center gap-1 ms-2"><i class="bi bi-stopwatch"></i>{{ $slot['duration'] }} {{ __('clubs::club.platform_show_min') }}</span>
                                    </div>
                                    @if($slot['facility_name'])
                                    <div class="text-sm text-muted-foreground mt-0.5">
                                        <i class="bi bi-geo-alt me-1"></i>{{ $slot['facility_name'] }}
                                    </div>
                                    @endif
                                </div>
                                {{-- Status badge + instructor — right column --}}
                                <div class="flex flex-col items-end gap-2 shrink-0">
                                    <div x-show="activeDay === '{{ $todayKey }}'">
                                        <span x-show="getStatus('{{ $slot['start'] }}', '{{ $slot['end'] }}') === 'live'" class="status-chip status-ongoing">
                                            <span class="live-dot"></span> {{ __('clubs::club.platform_show_ongoing') }}
                                        </span>
                                        <span x-show="getStatus('{{ $slot['start'] }}', '{{ $slot['end'] }}') === 'upcoming'" class="status-chip status-bookable">
                                            <i class="bi bi-clock-fill"></i> {{ __('clubs::club.platform_show_upcoming') }}
                                        </span>
                                        <span x-show="getStatus('{{ $slot['start'] }}', '{{ $slot['end'] }}') === 'finished'" class="status-chip status-finished">
                                            <i class="bi bi-check-circle-fill"></i> {{ __('clubs::club.platform_show_finished') }}
                                        </span>
                                    </div>
                                    @if($slot['instructor_name'])
                                    <div class="flex items-center gap-1.5">
                                        @if($slot['instructor_picture'])
                                        <img src="{{ file_url($slot['instructor_picture']) }}"
                                             class="w-7 h-7 rounded-full object-cover border border-gray-200"
                                             alt="{{ $slot['instructor_name'] }}">
                                        @else
                                        <div class="w-7 h-7 rounded-full bg-primary/20 flex items-center justify-center">
                                            <i class="bi bi-person-fill text-primary" style="font-size:13px"></i>
                                        </div>
                                        @endif
                                        <span class="text-xs font-medium text-gray-700">{{ $slot['instructor_name'] }}</span>
                                    </div>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-1 mt-2">
                                <span class="pill-tag">{{ $slot['package_name'] }}</span>
                                @foreach($slot['days'] as $d)
                                <span class="pill-tag">{{ $dayAbbr[$d] ?? ucfirst(substr($d,0,3)) }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endforeach

                    {{-- Empty state for filtered day --}}
                    <div x-show="!@js(collect($scheduleSlots)->map(fn($s) => $s['days'])->flatten()->unique()->values()->toArray()).includes(activeDay)"
                         class="text-center py-16">
                        <i class="bi bi-calendar-x text-muted-foreground text-5xl"></i>
                        <p class="text-lg font-medium mt-4">{{ __('clubs::club.platform_show_no_classes_day') }}</p>
                        <p class="text-sm text-muted-foreground mt-2">{{ __('clubs::club.platform_show_try_another_day') }}</p>
                    </div>
                </div>
                @else
                <div class="text-center py-16">
                    <i class="bi bi-calendar-x text-muted-foreground text-5xl"></i>
                    <p class="text-lg font-medium mt-4">{{ __('clubs::club.platform_show_no_classes') }}</p>
                    <p class="text-sm text-muted-foreground mt-2">{{ __('clubs::club.platform_show_no_classes_sub') }}</p>
                </div>
                @endif
            </div>
