@extends('layouts.admin-club')

@section('club-admin-content')
<div x-data="{ showAddPackageModal: false, showEditPackageModal: false }">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="tf-section-title">Packages Management</h2>
            <p class="text-muted-foreground mb-0">Create and manage membership packages</p>
        </div>
        <button class="btn btn-primary" @click="showAddPackageModal = true">
            <i class="bi bi-plus-lg mr-2"></i>Add Package
        </button>
    </div>

    @if(isset($packages) && count($packages) > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($packages as $package)
        <div class="card border-0 shadow-sm overflow-hidden flex flex-col transition-all hover:shadow-lg {{ $package->is_popular ?? false ? 'border-2 border-primary' : '' }}">
            <!-- Image Section -->
            @if($package->cover_image)
            <div class="relative">
                <div class="w-full aspect-video overflow-hidden bg-gray-100">
                    <img src="{{ asset('storage/' . $package->cover_image) }}"
                         alt="{{ $package->name }}"
                         class="w-full h-full object-cover object-top">
                </div>
                @if($package->is_popular ?? false)
                <div class="absolute top-4 left-4">
                    <span class="badge bg-primary text-white px-3 py-1">
                        <i class="bi bi-star-fill mr-1"></i>Popular
                    </span>
                </div>
                @endif
            </div>
            @else
            <div class="w-full aspect-video bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto mb-2 rounded-full bg-gray-300/50 flex items-center justify-center">
                        <i class="bi bi-image text-3xl text-gray-400"></i>
                    </div>
                    <p class="text-sm font-medium text-gray-500">{{ $package->name }}</p>
                </div>
                @if($package->is_popular ?? false)
                <div class="absolute top-4 left-4">
                    <span class="badge bg-primary text-white px-3 py-1">
                        <i class="bi bi-star-fill mr-1"></i>Popular
                    </span>
                </div>
                @endif
            </div>
            @endif

            <!-- Package Details -->
            <div class="flex-1 p-4">
                <!-- Action Buttons -->
                <div class="flex justify-end gap-1 mb-2">
                    <button class="btn btn-sm btn-outline-primary" title="Edit"
                            @click="showEditPackageModal = true; $nextTick(() => window.populateEditPackageForm && window.populateEditPackageForm(packagesData.find(p => p.id === {{ $package->id }})))">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-secondary" title="Duplicate">
                        <i class="bi bi-copy"></i>
                    </button>
                    <form action="{{ route('admin.club.packages.destroy', [$club->id, $package->id]) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this package?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>

                <!-- Package Name & Description -->
                <h3 class="text-lg font-bold mb-2">{{ $package->name }}</h3>
                @if($package->description)
                <p class="text-sm text-muted-foreground mb-3">{{ Str::limit($package->description, 100) }}</p>
                @endif

                <!-- Badges -->
                <div class="flex flex-wrap gap-1 mb-3">
                    <span class="badge {{ ($package->type ?? 'single') === 'single' ? 'bg-secondary text-secondary-foreground' : 'bg-primary text-white' }} text-xs">
                        {{ ($package->type ?? 'single') === 'single' ? 'Single Activity' : 'Multi-Activity' }}
                    </span>
                    <span class="badge bg-gray-100 text-gray-700 text-xs border border-gray-200">
                        <i class="bi bi-people mr-1"></i>
                        {{ ucfirst($package->gender ?? 'mixed') }}
                    </span>
                    @if($package->age_min || $package->age_max)
                    <span class="badge bg-gray-100 text-gray-700 text-xs border border-gray-200">
                        {{ $package->age_min ?? 0 }}-{{ $package->age_max ?? '∞' }}y
                    </span>
                    @endif
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

                <!-- Price & Duration -->
                <div class="mb-3">
                    <div class="flex items-baseline gap-2 mb-1">
                        <span class="text-2xl font-bold text-primary">
                            {{ $club->currency ?? 'BHD' }} {{ number_format($package->price, 2) }}
                        </span>
                        @if(($package->discount_percentage ?? 0) > 0)
                        <span class="text-xs text-green-600 font-medium">
                            {{ $package->discount_percentage }}% off
                        </span>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                        <div class="flex items-center gap-1">
                            <i class="bi bi-calendar3"></i>
                            <span>{{ $package->duration_months }}mo</span>
                        </div>
                        @if($package->session_count ?? 0)
                        <div class="flex items-center gap-1">
                            <i class="bi bi-clock"></i>
                            <span>{{ $package->session_count }} sessions</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Included Activities -->
                @if($package->activities && count($package->activities) > 0)
                <div class="mt-3 pt-3 border-t border-gray-200">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="bi bi-box"></i>
                        <h4 class="text-sm font-semibold">Included Activities ({{ count($package->activities) }})</h4>
                    </div>
                    <div class="space-y-2">
                        @foreach($package->activities as $activity)
                        <div class="border border-gray-200 rounded-lg p-4 bg-gray-50/30">
                            <div class="flex gap-3">
                                <div class="flex-1 min-w-0 space-y-3">
                                    <!-- Activity Title and Instructor -->
                                    <div class="flex items-start justify-between gap-2">
                                        <h5 class="font-semibold text-base">{{ $activity->title ?? $activity->name }}</h5>

                                        <!-- Instructor Badge -->
                                        @if($activity->pivot->instructor_id ?? false)
                                            @php
                                                $instructor = $instructorsMap[$activity->pivot->instructor_id] ?? null;
                                            @endphp
                                            @if($instructor)
                                            <div class="flex items-center gap-1.5 bg-primary/10 rounded-full px-2 py-1">
                                                @if($instructor['image'])
                                                <img src="{{ asset('storage/' . $instructor['image']) }}" alt="{{ $instructor['name'] }}" class="w-5 h-5 rounded-full border border-primary/20 object-cover">
                                                @else
                                                <div class="w-5 h-5 rounded-full bg-primary/20 flex items-center justify-center text-[10px] font-medium text-primary border border-primary/20">
                                                    {{ strtoupper(substr($instructor['name'], 0, 1)) }}
                                                </div>
                                                @endif
                                                <span class="text-[10px] font-medium text-primary">{{ $instructor['name'] }}</span>
                                            </div>
                                            @endif
                                        @endif
                                    </div>

                                    <!-- Duration and Schedule Badges -->
                                    <div class="flex flex-wrap items-center gap-2">
                                        <!-- Duration Badge -->
                                        @if($activity->duration_minutes)
                                        <span class="inline-flex items-center gap-1.5 text-xs py-1 px-3 rounded-full border border-gray-200 bg-white">
                                            <i class="bi bi-clock text-gray-500"></i>
                                            {{ $activity->duration_minutes }} min
                                        </span>
                                        @endif

                                        <!-- Schedule Badges (from pivot) -->
                                        @php
                                            $pivotSchedule = $activity->pivot->schedule ?? null;
                                            $scheduleData = is_string($pivotSchedule) ? json_decode($pivotSchedule, true) : (is_array($pivotSchedule) ? $pivotSchedule : null);
                                        @endphp
                                        @if($scheduleData && is_array($scheduleData))
                                            @php
                                                // Group schedules by time
                                                $timeGroups = [];
                                                $dayOrder = ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
                                                $dayAbbr = [
                                                    'saturday' => 'Sat', 'sunday' => 'Sun', 'monday' => 'Mon',
                                                    'tuesday' => 'Tue', 'wednesday' => 'Wed', 'thursday' => 'Thu', 'friday' => 'Fri'
                                                ];

                                                foreach($scheduleData as $schedule) {
                                                    $startTime = $schedule['start_time'] ?? ($schedule['startTime'] ?? '');
                                                    $endTime = $schedule['end_time'] ?? ($schedule['endTime'] ?? '');
                                                    $day = $schedule['day'] ?? ($schedule['day_of_week'] ?? '');

                                                    if ($startTime && $endTime && $day) {
                                                        $timeKey = $startTime . '-' . $endTime;
                                                        if (!isset($timeGroups[$timeKey])) {
                                                            $timeGroups[$timeKey] = ['days' => [], 'start' => $startTime, 'end' => $endTime];
                                                        }
                                                        $dayShort = $dayAbbr[strtolower($day)] ?? ucfirst(substr($day, 0, 3));
                                                        if (!in_array($dayShort, $timeGroups[$timeKey]['days'])) {
                                                            $timeGroups[$timeKey]['days'][] = $dayShort;
                                                        }
                                                    }
                                                }

                                                // Sort days within each group
                                                foreach ($timeGroups as &$group) {
                                                    usort($group['days'], function($a, $b) use ($dayOrder) {
                                                        return array_search($a, $dayOrder) - array_search($b, $dayOrder);
                                                    });
                                                }
                                            @endphp

                                            @foreach($timeGroups as $group)
                                            <span class="inline-flex items-center gap-1.5 text-xs py-1 px-3 rounded-full border border-gray-200 bg-white">
                                                <i class="bi bi-calendar3 text-gray-500"></i>
                                                {{ implode(', ', $group['days']) }}:
                                                {{ \Carbon\Carbon::parse($group['start'])->format('g:i A') }} - {{ \Carbon\Carbon::parse($group['end'])->format('g:i A') }}
                                            </span>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-16">
            <div class="tf-empty-icon">
                <i class="bi bi-box text-gray-400 text-4xl"></i>
            </div>
            <h5 class="text-xl font-semibold mb-2">No packages yet</h5>
            <p class="text-muted-foreground mb-4">Create membership packages for your club to get started</p>
            <button class="btn btn-primary" @click="showAddPackageModal = true">
                <i class="bi bi-plus-lg mr-2"></i>Add Package
            </button>
        </div>
    </div>
    @endif

    @include('admin.club.packages.add')
    @include('admin.club.packages.edit')
</div>

@php
    $packagesJson = $packages->map(function($p) {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'price' => $p->price,
            'duration_months' => $p->duration_months,
            'gender' => $p->gender ?? 'mixed',
            'age_min' => $p->age_min,
            'age_max' => $p->age_max,
            'cover_image' => $p->cover_image,
            'is_popular' => $p->is_popular ?? false,
            'activities' => $p->activities->map(function($a) {
                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'title' => $a->name,
                    'duration_minutes' => $a->duration_minutes,
                    'schedule' => is_string($a->pivot->schedule) ? json_decode($a->pivot->schedule, true) : ($a->pivot->schedule ?? $a->schedule),
                    'instructor_id' => $a->pivot->instructor_id,
                ];
            }),
        ];
    });
@endphp

@push('scripts')
<script>
    const packagesData = @json($packagesJson);
</script>
@endpush
@endsection
