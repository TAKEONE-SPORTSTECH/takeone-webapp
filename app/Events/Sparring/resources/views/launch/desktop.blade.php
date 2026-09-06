@extends('layouts.admin-club')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('event-sparring::messages.label'))

{{--
    Sparring launcher — desktop.

    The same three questions as the phone, laid across one row because there is
    room for them, and the same single rule: if a session is already running
    today, the questions are replaced by the way back into it.
--}}

@php
    $sportLabel = function (string $s) {
        $key = 'sport-'.$s.'::messages.sport_label';

        return __($key) !== $key ? __($key) : ucfirst($s);
    };
@endphp

@section('club-admin-content')
<div class="space-y-6" x-data="sparringLaunch()">

    <x-admin-hero
        :title="__('event-sparring::messages.launch_title')"
        :eyebrow="__('event-sparring::messages.launch_eyebrow')"
        :subtitle="__('event-sparring::messages.launch_lead')"
        icon="bi-lightning-charge" />

    @if($today && ! $today['closed'])
        <div class="rounded-2xl p-6 text-white relative overflow-hidden flex flex-col sm:flex-row items-start sm:items-center gap-5"
             style="background: linear-gradient(120deg, #0EA5E9, #0EA5E9b0);">
            <div class="absolute -end-10 -top-10 w-40 h-40 rounded-full bg-white/10"></div>
            <div class="relative z-10 flex-1 min-w-0">
                <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider bg-white/20 border border-white/30 rounded-full px-2.5 py-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                    {{ __('event-sparring::messages.launch_running') }}
                </span>
                <p class="text-2xl font-black mt-2.5">{{ $today['title'] }}</p>
                <p class="text-sm text-white/85 mt-1">
                    {{ $sportLabel($today['sport']) }} ·
                    {{ __('event-sparring::messages.console_mat_count', ['n' => $today['mats']]) }}
                    @if($today['started_at'])
                        · {{ __('event-sparring::messages.launch_open_since', ['time' => $today['started_at']]) }}
                    @endif
                </p>
            </div>
            <a href="{{ $today['url'] }}" class="relative z-10 inline-flex items-center gap-2 bg-white text-sky-700 font-bold text-sm rounded-xl px-5 py-3 hover:bg-white/90 transition-colors">
                <i class="bi bi-arrow-right-circle"></i>{{ __('event-sparring::messages.launch_resume') }}
            </a>
        </div>
    @elseif(count($sports) === 0)
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
            <i class="bi bi-emoji-neutral text-4xl text-muted-foreground"></i>
            <p class="text-sm text-muted-foreground mt-3">{{ __('event-sparring::messages.launch_no_sport') }}</p>
        </div>
    @else
        <form method="POST" action="{{ route('admin.club.sparring.store', $club->id) }}"
              class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            @csrf
            <input type="hidden" name="sport" :value="sport">
            <input type="hidden" name="mats" :value="mats">
            <input type="hidden" name="minutes" :value="minutes">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-sparring::messages.launch_sport') }}</p>
                    <div class="mt-3 space-y-2">
                        @foreach($sports as $s)
                            <button type="button" @click="sport = '{{ $s }}'"
                                    class="w-full flex items-center gap-3 rounded-xl border p-3 text-start transition-colors"
                                    :class="sport === '{{ $s }}' ? 'border-primary bg-primary/5' : 'border-border hover:bg-muted/60'">
                                <span class="w-9 h-9 rounded-lg grid place-items-center flex-shrink-0"
                                      :class="sport === '{{ $s }}' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'">
                                    <i class="bi bi-person-arms-up"></i>
                                </span>
                                <span class="flex-1 font-medium text-sm text-foreground">{{ $sportLabel($s) }}</span>
                                <i class="bi bi-check-lg text-primary" x-show="sport === '{{ $s }}'"></i>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-muted-foreground">{{ __('event-sparring::messages.launch_mats') }}</p>
                    <div class="mt-3 grid grid-cols-4 gap-2">
                        @foreach([1,2,3,4] as $n)
                            <button type="button" @click="mats = {{ $n }}"
                                    class="h-12 rounded-xl font-black text-lg border transition-colors"
                                    :class="mats === {{ $n }} ? 'border-primary bg-primary text-white' : 'border-border hover:bg-muted/60'">{{ $n }}</button>
                        @endforeach
                    </div>

                    <p class="text-xs font-bold uppercase tracking-wider text-muted-foreground mt-5">{{ __('event-sparring::messages.launch_minutes') }}</p>
                    <div class="mt-3 grid grid-cols-4 gap-2">
                        @foreach([1,2,3,4] as $n)
                            <button type="button" @click="minutes = {{ $n }}"
                                    class="h-12 rounded-xl font-bold text-sm border transition-colors"
                                    :class="minutes === {{ $n }} ? 'border-primary bg-primary text-white' : 'border-border hover:bg-muted/60'">{{ __('event-sparring::messages.launch_minutes_n', ['n' => $n]) }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col justify-end">
                    <button type="submit" class="w-full rounded-xl py-4 text-white font-black shadow-lg hover:opacity-95 transition-opacity"
                            style="background: linear-gradient(120deg, #0EA5E9, #6366F1);">
                        <i class="bi bi-lightning-charge-fill me-2"></i>{{ __('event-sparring::messages.launch_start') }}
                    </button>
                </div>
            </div>
        </form>
    @endif

    <div>
        <p class="text-sm font-medium text-muted-foreground">{{ __('event-sparring::messages.launch_past') }}</p>
        <div class="mt-3 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($past as $s)
                <a href="{{ $s['url'] }}" class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex items-center gap-3 hover:shadow-md transition-shadow">
                    <span class="w-10 h-10 rounded-lg grid place-items-center flex-shrink-0 {{ $s['closed'] ? 'bg-muted text-muted-foreground' : 'bg-sky-100 text-sky-600' }}">
                        <i class="bi bi-lightning-charge"></i>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block font-bold text-sm text-gray-900 truncate">{{ $s['title'] }}</span>
                        <span class="block text-xs text-muted-foreground">{{ $sportLabel($s['sport']) }} · {{ __('event-sparring::messages.launch_bouts_n', ['n' => $s['bouts']]) }}</span>
                    </span>
                    <i class="bi bi-chevron-right text-muted-foreground"></i>
                </a>
            @empty
                <p class="text-sm text-muted-foreground">{{ __('event-sparring::messages.launch_past_none') }}</p>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script>
function sparringLaunch() {
    return {
        sport: @json($sports[0] ?? 'karate'),
        mats: 1,
        minutes: 3,
    };
}
</script>
@endpush
@endsection
