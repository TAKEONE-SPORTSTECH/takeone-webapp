@props([
    'package',
    'club' => null,
    'instructorsMap' => [],
])

@php
    $currency = $club?->currency ?? 'BHD';

    $dayOrder = ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
    $dayAbbr  = [
        'saturday'  => 'Sat', 'sunday'    => 'Sun', 'monday'  => 'Mon',
        'tuesday'   => 'Tue', 'wednesday' => 'Wed', 'thursday' => 'Thu', 'friday' => 'Fri',
    ];

    // Group schedules per activity (same shape the desktop card uses).
    $groupedActivities = [];
    foreach ($package->activities ?? [] as $activity) {
        $aid = $activity->id;
        if (!isset($groupedActivities[$aid])) {
            $groupedActivities[$aid] = [
                'activity'      => $activity,
                'instructor_id' => $activity->pivot->instructor_id ?? null,
                'allSchedules'  => [],
            ];
        }
        $pivotSchedule = $activity->pivot->schedule ?? null;
        $scheduleData  = is_string($pivotSchedule)
            ? json_decode($pivotSchedule, true)
            : (is_array($pivotSchedule) ? $pivotSchedule : null);
        if ($scheduleData && is_array($scheduleData)) {
            foreach ($scheduleData as $s) {
                $groupedActivities[$aid]['allSchedules'][] = $s;
            }
        }
        if (!$groupedActivities[$aid]['instructor_id'] && ($activity->pivot->instructor_id ?? null)) {
            $groupedActivities[$aid]['instructor_id'] = $activity->pivot->instructor_id;
        }
    }

    $activityCount = count($groupedActivities);
    $isPopular     = $package->is_popular ?? false;

    $ageLabel = ($package->age_min || $package->age_max)
        ? ($package->age_min ?? 0) . '–' . ($package->age_max ?? '∞')
        : null;
@endphp

<article class="m-card relative rounded-2xl overflow-hidden bg-white border border-gray-100 shadow-sm">

    {{-- ===== Slim cover ===== --}}
    <div class="relative h-28 {{ $package->cover_image ? 'bg-gray-200' : 'bg-gradient-to-br from-primary to-[hsl(265_55%_50%)]' }}">
        @if($package->cover_image)
            <img src="{{ asset('storage/' . $package->cover_image) }}" alt="{{ $package->tr('name') }}" class="w-full h-full object-cover object-top">
        @else
            <i class="bi bi-box absolute bottom-1 right-2 text-white/20 text-5xl"></i>
        @endif

        @if($isPopular)
            <span class="absolute top-2 left-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wide text-white bg-primary shadow-sm">
                <i class="bi bi-star-fill text-amber-300 text-[8px]"></i>{{ __('club.popular') }}
            </span>
        @endif
    </div>

    {{-- ===== Body ===== --}}
    <div class="p-3">
        <div class="flex items-center gap-3">
            {{-- price block (leading side) --}}
            <div class="flex-shrink-0 flex flex-col items-center justify-center text-center rounded-xl bg-primary/5 ring-1 ring-primary/10 px-3.5 py-2.5 min-w-[5rem]">
                <span class="text-[9px] font-bold uppercase tracking-wide text-muted-foreground leading-none mb-0.5">{{ $currency }}</span>
                <span class="text-2xl font-extrabold text-primary leading-none tracking-tight">{{ number_format($package->price, 2) }}</span>
                <span class="text-[9px] text-muted-foreground leading-none mt-1">/ {{ $package->duration_months }} {{ trans_choice('club.month_short', (int) $package->duration_months) }}</span>
                @if(($package->discount_percentage ?? 0) > 0)
                    <span class="mt-1 px-1.5 rounded-full bg-green-100 text-green-700 text-[9px] font-bold">−{{ $package->discount_percentage }}%</span>
                @endif
            </div>

            {{-- name + chips --}}
            <div class="flex-1 min-w-0">
                <h3 class="text-sm font-bold text-foreground leading-snug line-clamp-2">{{ $package->tr('name') }}</h3>
                <div class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-[11px] text-muted-foreground">
                    <span class="inline-flex items-center gap-1"><i class="bi bi-people-fill text-primary/70"></i>{{ __('club.gender_' . strtolower($package->gender ?? 'mixed')) }}</span>
                    @if($ageLabel)
                        <span class="inline-flex items-center gap-1"><i class="bi bi-person-arms-up text-primary/70"></i>{{ $ageLabel }}{{ __('club.years_short') }}</span>
                    @endif
                    @if($package->session_count ?? 0)
                        <span class="inline-flex items-center gap-1"><i class="bi bi-lightning-charge-fill text-primary/70"></i>{{ $package->session_count }}</span>
                    @endif
                    @if($package->max_capacity ?? false)
                        <span class="inline-flex items-center gap-1"><i class="bi bi-door-open-fill text-primary/70"></i>{{ $package->max_capacity }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- ===== Expandable schedule ===== --}}
        @if($activityCount > 0)
            <div x-data="{ open: false }" class="mt-2.5 pt-2.5 border-t border-gray-100">
                <button type="button" @click="open = !open" class="m-press w-full flex items-center justify-between gap-2 text-left">
                    <span class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-foreground">
                        <i class="bi bi-calendar2-week text-primary/70 text-[11px]"></i>{{ __('club.whats_included') }}
                        <span class="px-1.5 rounded-full bg-primary/10 text-primary text-[10px] font-bold">{{ $activityCount }}</span>
                    </span>
                    <i class="bi bi-chevron-down text-xs text-muted-foreground transition-transform duration-300" :class="open && 'rotate-180'"></i>
                </button>

                <div x-show="open" x-cloak
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="mt-2 space-y-2">
                    @foreach($groupedActivities as $grouped)
                        @php
                            $activity     = $grouped['activity'];
                            $instructorId = $grouped['instructor_id'];
                            $instructor   = $instructorId ? ($instructorsMap[$instructorId] ?? null) : null;

                            $timeGroups = [];
                            foreach ($grouped['allSchedules'] as $schedule) {
                                $startTime = $schedule['start_time'] ?? ($schedule['startTime'] ?? '');
                                $endTime   = $schedule['end_time']   ?? ($schedule['endTime']   ?? '');
                                $day       = $schedule['day']        ?? ($schedule['day_of_week'] ?? '');
                                if ($startTime && $endTime && $day) {
                                    $timeKey = \Carbon\Carbon::parse($startTime)->format('H:i') . '-' . \Carbon\Carbon::parse($endTime)->format('H:i');
                                    if (!isset($timeGroups[$timeKey])) {
                                        $timeGroups[$timeKey] = [
                                            'days'          => [],
                                            'start'         => $startTime,
                                            'end'           => $endTime,
                                            'facility_name' => $schedule['facility_name'] ?? null,
                                        ];
                                    }
                                    $dayShort = $dayAbbr[strtolower($day)] ?? ucfirst(substr($day, 0, 3));
                                    if (!in_array($dayShort, $timeGroups[$timeKey]['days'])) {
                                        $timeGroups[$timeKey]['days'][] = $dayShort;
                                    }
                                }
                            }
                            foreach ($timeGroups as &$tg) {
                                usort($tg['days'], fn($a, $b) => array_search($a, $dayOrder) - array_search($b, $dayOrder));
                            }
                            unset($tg);
                        @endphp

                        <div class="rounded-xl bg-gray-50/70 border border-gray-100 p-2.5">
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <h5 class="text-[12px] font-bold text-foreground truncate">{{ $activity->tr('name') ?: ($activity->title ?? $activity->name) }}</h5>
                                @if($instructor)
                                    <a href="{{ route('trainer.show', $instructor['user_id']) }}" class="m-press flex items-center gap-1 flex-shrink-0">
                                        @if($instructor['image'])
                                            <img src="{{ asset('storage/' . $instructor['image']) }}" alt="" class="w-4 h-4 rounded-full object-cover">
                                        @else
                                            <span class="w-4 h-4 rounded-full bg-primary/15 grid place-items-center text-[8px] font-bold text-primary">{{ mb_strtoupper(mb_substr($instructor['name'], 0, 1, 'UTF-8'), 'UTF-8') }}</span>
                                        @endif
                                        <span class="text-[10px] text-primary font-medium max-w-[5rem] truncate">{{ $instructor['name'] }}</span>
                                    </a>
                                @endif
                            </div>
                            @forelse($timeGroups as $tg)
                                @php $groupDuration = abs(\Carbon\Carbon::parse($tg['end'])->diffInMinutes(\Carbon\Carbon::parse($tg['start']))); @endphp
                                <div class="flex items-center flex-wrap gap-x-2 gap-y-0.5 text-[11px] text-gray-600 {{ !$loop->first ? 'mt-1' : '' }}">
                                    <span class="flex gap-0.5">
                                        @foreach($tg['days'] as $day)
                                            <span class="px-1 py-0.5 rounded bg-primary/10 text-primary font-semibold text-[9px]">{{ $day }}</span>
                                        @endforeach
                                    </span>
                                    <span class="inline-flex items-center gap-1"><i class="bi bi-clock text-gray-400 text-[10px]"></i>{{ \Carbon\Carbon::parse($tg['start'])->format('g:i A') }}–{{ \Carbon\Carbon::parse($tg['end'])->format('g:i A') }}</span>
                                    <span class="text-gray-400">· {{ $groupDuration }}{{ __('club.minutes_short') }}</span>
                                    @if(!empty($tg['facility_name']))
                                        <span class="inline-flex items-center gap-1 text-sky-700 w-full"><i class="bi bi-geo-alt text-sky-400 text-[10px]"></i>{{ $tg['facility_name'] }}</span>
                                    @endif
                                </div>
                            @empty
                                <p class="text-[10px] text-muted-foreground italic">{{ __('club.flexible_schedule') }}</p>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Actions slot (admin) --}}
        @if(isset($actions))
            <div class="flex justify-end gap-1 mt-2">{{ $actions }}</div>
        @endif

        {{-- Footer slot (CTA) --}}
        @if(isset($footer))
            <div class="mt-2.5">{{ $footer }}</div>
        @endif
    </div>
</article>
