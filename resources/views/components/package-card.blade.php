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
@endphp

<div class="card border-0 shadow-sm overflow-hidden flex flex-col transition-all hover:shadow-lg {{ ($package->is_popular ?? false) ? 'border-2 border-primary' : '' }}">

    {{-- Cover Image with overlaid name + badges --}}
    <div class="relative w-full aspect-video overflow-hidden {{ $package->cover_image ? 'bg-gray-200' : 'bg-gradient-to-br from-slate-600 to-slate-800' }}">
        @if($package->cover_image)
            <img src="{{ asset('storage/' . $package->cover_image) }}"
                 alt="{{ $package->name }}"
                 class="w-full h-full object-cover object-top">
        @else
            <div class="flex items-center justify-center h-full opacity-10">
                <i class="bi bi-box text-7xl text-white"></i>
            </div>
        @endif

        {{-- Popular badge — top left --}}
        @if($package->is_popular ?? false)
            <div class="absolute top-4 left-4">
                <span class="badge bg-primary text-white px-3 py-1">
                    <i class="bi bi-star-fill mr-1"></i>Popular
                </span>
            </div>
        @endif

        {{-- Dark gradient overlay with name + badges --}}
        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/80 via-black/50 to-transparent px-4 pt-10 pb-3">
            <h3 class="text-base font-bold text-white leading-snug mb-2">{{ $package->name }}</h3>
            <div class="flex flex-wrap gap-1">
                <span class="badge {{ count($groupedActivities) <= 1 ? 'bg-secondary text-secondary-foreground' : 'bg-primary text-white' }} text-xs">
                    {{ count($groupedActivities) <= 1 ? 'Single Activity' : 'Multi-Activity' }}
                </span>
                <span class="badge bg-sky-500 text-white text-xs border border-sky-600">
                    <i class="bi bi-people mr-1"></i>{{ ucfirst($package->gender ?? 'mixed') }}
                </span>
                @if($package->age_min || $package->age_max)
                    <span class="badge bg-green-400 text-white text-xs border border-green-500">
                        {{ $package->age_min ?? 0 }}-{{ $package->age_max ?? '∞' }}y
                    </span>
                @endif
            </div>
        </div>
    </div>

    {{-- Card Body --}}
    <div class="flex-1 p-4">

        {{-- Description --}}
        @if($package->description)
            <p class="text-sm text-muted-foreground mb-3">{{ Str::limit($package->description, 100) }}</p>
        @endif

        {{-- Remaining badges (capacity, discount) --}}
        @if(($package->max_capacity ?? false) || ($package->discount_code ?? false))
        <div class="flex flex-wrap gap-1 mb-3">
            @if($package->max_capacity ?? false)
                <span class="badge bg-blue-50 text-blue-700 text-xs border border-blue-200">
                    <i class="bi bi-people-fill mr-1"></i>{{ $package->max_capacity }} capacity
                </span>
            @endif
            @if($package->discount_code ?? false)
                <span class="badge bg-secondary text-secondary-foreground text-xs">
                    <i class="bi bi-tag mr-1"></i>{{ $package->discount_code }}
                </span>
            @endif
        </div>
        @endif

        {{-- Price & Duration --}}
        <div class="mb-3">
            <div class="flex items-baseline gap-2 mb-1">
                <span class="text-2xl font-bold text-primary">
                    {{ $currency }} {{ number_format($package->price, 2) }}
                </span>
                <span class="text-xs text-muted-foreground flex items-center gap-1">
                    <i class="bi bi-calendar3"></i>{{ $package->duration_months }}mo
                </span>
                @if(($package->discount_percentage ?? 0) > 0)
                    <span class="text-xs text-green-600 font-medium">
                        {{ $package->discount_percentage }}% off
                    </span>
                @endif
            </div>
            @if($package->session_count ?? 0)
            <div class="flex items-center gap-1 text-xs text-muted-foreground">
                <i class="bi bi-clock"></i>
                <span>{{ $package->session_count }} sessions</span>
            </div>
            @endif
        </div>

        {{-- Included Activities --}}
        @if(count($groupedActivities) > 0)
            <div class="mt-3 pt-3 border-t border-gray-200" x-data="{ open: window.innerWidth >= 768 }">
                <div class="flex items-center justify-between w-full gap-2 mb-3" :style="window.innerWidth < 768 ? 'cursor:pointer' : 'cursor:default'" @click="if (window.innerWidth < 768) open = !open">
                    <div class="flex items-center gap-2">
                        <i class="bi bi-box"></i>
                        <h4 class="text-sm font-semibold">Included Activities ({{ count($groupedActivities) }})</h4>
                    </div>
                    <i class="bi bi-chevron-down text-xs text-muted-foreground transition-transform duration-200 md:hidden" :class="{ 'rotate-180': open }"></i>
                </div>
                <div class="space-y-2" x-show="open">
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
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50/30">
                        <div class="flex gap-3">
                            <div class="flex-1 min-w-0 space-y-3">
                                <div class="flex items-start justify-between gap-2">
                                    <h5 class="font-semibold text-base">{{ $activity->title ?? $activity->name }}</h5>
                                    @if($instructor)
                                        <a href="{{ route('trainer.show', $instructor['user_id']) }}"
                                           class="flex items-center gap-1.5 bg-primary/10 rounded-full px-2 py-1 hover:bg-primary/20 transition-colors">
                                            @if($instructor['image'])
                                                <img src="{{ asset('storage/' . $instructor['image']) }}" alt="{{ $instructor['name'] }}" class="w-5 h-5 rounded-full border border-primary/20 object-cover">
                                            @else
                                                <div class="w-5 h-5 rounded-full bg-primary/20 flex items-center justify-center text-[10px] font-medium text-primary border border-primary/20">
                                                    {{ strtoupper(substr($instructor['name'], 0, 1)) }}
                                                </div>
                                            @endif
                                            <span class="text-[10px] font-medium text-primary">{{ $instructor['name'] }}</span>
                                        </a>
                                    @endif
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    @foreach($timeGroups as $tg)
                                    @php
                                        $groupDuration = abs(\Carbon\Carbon::parse($tg['end'])->diffInMinutes(\Carbon\Carbon::parse($tg['start'])));
                                    @endphp
                                    <div class="text-xs rounded-lg border border-gray-200 bg-white px-3 py-2 space-y-1.5">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($tg['days'] as $day)
                                                <span class="px-1.5 py-0.5 rounded bg-primary/10 text-primary font-semibold text-[10px]">{{ $day }}</span>
                                            @endforeach
                                        </div>
                                        <div class="flex items-center gap-2 text-gray-600">
                                            <i class="bi bi-clock text-gray-400 text-[11px]"></i>
                                            <span>{{ \Carbon\Carbon::parse($tg['start'])->format('g:i A') }} – {{ \Carbon\Carbon::parse($tg['end'])->format('g:i A') }}</span>
                                            <span class="text-gray-300">·</span>
                                            <span class="text-gray-500">{{ $groupDuration }} min</span>
                                        </div>
                                        @if(!empty($tg['facility_name']))
                                        <div class="flex items-center gap-1 text-[10px] text-sky-700">
                                            <i class="bi bi-geo-alt text-sky-400"></i>
                                            <span>{{ $tg['facility_name'] }}</span>
                                        </div>
                                        @endif
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>

    {{-- Actions slot (edit/copy/delete buttons) --}}
    @if(isset($actions))
    <div class="flex justify-end gap-1 px-4 pb-4">
        {{ $actions }}
    </div>
    @endif

    {{-- Footer slot (e.g. CTA button) --}}
    @if(isset($footer))
    <div class="px-4 pb-4">
        {{ $footer }}
    </div>
    @endif
</div>
