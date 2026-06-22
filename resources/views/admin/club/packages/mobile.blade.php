@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_packages'))

@section('club-admin-content')
@php $cur = $club->currency ?: ''; @endphp
<div class="space-y-4 mobile-stagger"
     x-data="{ removePackageId: null, removePackageName: '' }"
     @package-removed.window="removePackageId = null; removePackageName = ''">

    {{-- Add package --}}
    <button type="button" @click="$dispatch('open-add-package')"
            class="m-press w-full flex items-center justify-center gap-2 rounded-2xl bg-primary text-white py-3.5 font-semibold shadow-sm">
        <i class="bi bi-plus-lg text-lg"></i>{{ __('admin.pkg_add') }}
    </button>

    @if($packages->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-box text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">{{ __('admin.pkg_none_yet') }}</p>
        </div>
    @else
        @foreach($packages as $pkg)
            <div class="m-card overflow-hidden" id="package-{{ $pkg->id }}" x-data="{ openMenu: false }">
                <div class="relative">
                    @if($pkg->cover_image)
                        <img src="{{ asset('storage/'.$pkg->cover_image) }}" alt="" class="w-full h-32 object-cover">
                    @endif

                    {{-- Actions menu --}}
                    <div class="absolute top-2 right-2 z-10" @click.stop>
                        <button type="button" @click="openMenu = !openMenu"
                                class="m-press w-8 h-8 rounded-full bg-white/90 backdrop-blur flex items-center justify-center text-foreground shadow-sm"
                                aria-label="{{ __('admin.actions') }}">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <div x-show="openMenu" x-cloak @click.outside="openMenu = false"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95"
                             class="absolute right-0 mt-2 w-44 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-20">
                            <button type="button"
                                    class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3"
                                    @click="openMenu = false; $dispatch('open-edit-package', { id: {{ $pkg->id }} })">
                                <span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0"><i class="bi bi-pencil text-blue-600 text-xs"></i></span>
                                <span class="font-medium">{{ __('admin.pkg_edit') }}</span>
                            </button>
                            <button type="button"
                                    class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3"
                                    @click="openMenu = false; removePackageId = {{ $pkg->id }}; removePackageName = @js($pkg->name)">
                                <span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center shrink-0"><i class="bi bi-trash text-red-600 text-xs"></i></span>
                                <span class="font-medium">{{ __('admin.pkg_delete') }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                    <div class="flex items-start justify-between gap-2 pr-9">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-foreground truncate">{{ $pkg->name }}</h3>
                            @if($pkg->description)<p class="text-xs text-muted-foreground line-clamp-2 mt-0.5">{{ $pkg->description }}</p>@endif
                        </div>
                        @if(!$pkg->is_active)<span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500 flex-shrink-0">{{ __('admin.pkg_inactive') }}</span>@endif
                    </div>
                    <div class="flex items-center flex-wrap gap-2 mt-3">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-accent text-primary">{{ $cur }} {{ number_format((float)($pkg->price ?? 0), 0) }}</span>
                        @if($pkg->duration_months)<span class="px-2.5 py-1 rounded-full text-xs font-medium bg-muted text-muted-foreground">{{ $pkg->duration_months }} {{ __('admin.pkg_mo') }}</span>@endif
                        @if($pkg->gender && $pkg->gender !== 'mixed')<span class="px-2.5 py-1 rounded-full text-xs font-medium bg-muted text-muted-foreground capitalize">{{ $pkg->gender }}</span>@endif
                        @if($pkg->age_min || $pkg->age_max)<span class="px-2.5 py-1 rounded-full text-xs font-medium bg-muted text-muted-foreground">{{ $pkg->age_min ?? 0 }}–{{ $pkg->age_max ?? '∞' }} {{ __('admin.yrs') }}</span>@endif
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-muted text-muted-foreground"><i class="bi bi-activity mr-1"></i>{{ $pkg->activities->count() }}</span>
                    </div>

                    {{-- Schedule & coach per activity --}}
                    @if($pkg->activities->count())
                        <div class="mt-3 pt-3 border-t border-gray-100 space-y-2">
                            @foreach($pkg->activities as $act)
                                @php
                                    $sched = is_string($act->pivot->schedule) ? json_decode($act->pivot->schedule, true) : ($act->pivot->schedule ?? []);
                                    $sched = is_array($sched) ? $sched : [];
                                    $ins   = $instructorsMap[$act->pivot->instructor_id] ?? null;
                                    $groups = [];
                                    foreach ($sched as $s) {
                                        $st  = $s['start_time'] ?? $s['startTime'] ?? '';
                                        $et  = $s['end_time']   ?? $s['endTime']   ?? '';
                                        $day = strtolower($s['day'] ?? $s['day_of_week'] ?? '');
                                        $key = $st.'-'.$et;
                                        if (!isset($groups[$key])) $groups[$key] = ['start' => $st, 'end' => $et, 'days' => []];
                                        if ($day) $groups[$key]['days'][] = \Illuminate\Support\Carbon::parse($day)->locale(app()->getLocale())->isoFormat('ddd');
                                    }
                                @endphp
                                <div class="rounded-xl bg-muted/30 p-2.5">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-xs font-semibold text-foreground truncate min-w-0"><i class="bi bi-activity text-primary mr-1"></i>{{ $act->name }}</p>
                                        @if($ins)
                                            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-muted-foreground flex-shrink-0">
                                                <span class="w-5 h-5 rounded-full bg-accent overflow-hidden flex items-center justify-center">
                                                    @if(!empty($ins['image']))<img src="{{ asset('storage/'.$ins['image']) }}" alt="" class="w-5 h-5 object-cover">@else<i class="bi bi-person text-primary text-[10px]"></i>@endif
                                                </span>
                                                <span class="truncate max-w-[7rem]">{{ $ins['name'] }}</span>
                                            </span>
                                        @endif
                                    </div>
                                    @forelse($groups as $g)
                                        <div class="flex items-center flex-wrap gap-1 mt-1.5">
                                            @foreach($g['days'] as $d)
                                                <span class="px-1.5 py-0.5 rounded-md text-[10px] font-medium bg-white text-muted-foreground border border-gray-100">{{ $d }}</span>
                                            @endforeach
                                            @if($g['start'])
                                                <span class="text-[10px] text-muted-foreground ml-0.5">
                                                    <i class="bi bi-clock mr-0.5"></i>{{ \Illuminate\Support\Carbon::parse($g['start'])->locale(app()->getLocale())->isoFormat('h:mm A') }} – {{ \Illuminate\Support\Carbon::parse($g['end'])->locale(app()->getLocale())->isoFormat('h:mm A') }}
                                                </span>
                                            @endif
                                        </div>
                                    @empty
                                        <p class="text-[10px] text-muted-foreground mt-1">{{ __('admin.pkg_no_schedules') }}</p>
                                    @endforelse
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    @endif

    {{-- Delete confirm (teleported to body to escape the transformed `.mobile-stagger` container) --}}
    <template x-teleport="body">
    <div x-show="removePackageId !== null" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="removePackageId = null"></div>
        <div class="relative flex min-h-full items-center justify-center p-4 z-10">
            <div class="bg-white w-full max-w-sm relative rounded-2xl overflow-hidden shadow-xl" @click.stop>
                <div class="flex items-center justify-between border-b border-red-100 px-5 py-4">
                    <h5 class="text-destructive font-semibold flex items-center"><i class="bi bi-trash mr-2"></i>{{ __('admin.pkg_delete_title') }}</h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="removePackageId = null"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="px-5 py-4">
                    <p class="mb-1 text-sm">{{ __('admin.pkg_delete_msg') }}</p>
                    <p class="font-semibold" x-text="removePackageName"></p>
                </div>
                <div class="border-t px-5 py-4 flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 bg-white" @click="removePackageId = null">{{ __('admin.cancel') }}</button>
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl bg-destructive text-white flex items-center gap-1" @click="removePackage(removePackageId)">
                        <i class="bi bi-trash"></i>{{ __('admin.pkg_delete') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
    </template>

    @include('admin.club.packages.mobile-form')

    {{-- Precompute the edit payload in PHP — a multi-line closure inside @json() breaks
         Blade's directive parser, so build the array first then emit it once. --}}
    @php
        $packagesData = $packages->mapWithKeys(function ($pkg) {
            return [$pkg->id => [
                'id'              => $pkg->id,
                'name'            => $pkg->name,
                'description'     => $pkg->description,
                'price'           => $pkg->price,
                'duration_months' => $pkg->duration_months,
                'gender'          => $pkg->gender ?? 'mixed',
                'age_min'         => $pkg->age_min,
                'age_max'         => $pkg->age_max,
                'cover_image'     => $pkg->cover_image,
                'activities'      => $pkg->activities->map(function ($a) {
                    return [
                        'id'            => $a->id,
                        'name'          => $a->name,
                        'schedule'      => is_string($a->pivot->schedule) ? json_decode($a->pivot->schedule, true) : ($a->pivot->schedule ?? []),
                        'instructor_id' => $a->pivot->instructor_id,
                    ];
                })->values(),
            ]];
        });
    @endphp

    {{-- Inline (inside #shell-content) so it also runs after an in-shell AJAX swap,
         which only re-executes scripts within the swapped content. --}}
    <script>
window.packagesData = @json($packagesData);

@if($errors->any())
// A failed add/edit submit returns a full-page reload; surface the reason as a toast.
document.addEventListener('DOMContentLoaded', function () {
    window.showToast && window.showToast('error', @json($errors->first()));
});
@endif

function removePackage(id) {
    if (!id) return;
    fetch(`{{ url('admin/club/' . $club->slug . '/packages') }}/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    })
    .then(r => r.ok ? r : Promise.reject())
    .then(() => {
        document.getElementById('package-' + id)?.remove();
        delete window.packagesData[id];
        window.dispatchEvent(new CustomEvent('package-removed'));
        window.showToast('success', '{{ __('admin.pkg_deleted') }}');
    })
    .catch(() => window.showToast('error', 'An error occurred. Please try again.'));
}
    </script>
</div>
@endsection
