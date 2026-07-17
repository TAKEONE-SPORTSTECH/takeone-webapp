@extends('layouts.personal-mobile')

@section('title', __('personal.personal_schedule_page_title'))

{{--
    Schedule — mobile training schedule (DB-backed). Merges the member's own
    PERSONAL sessions (editable, created via the + button) with read-only sessions
    SYNCED from the packages they're enrolled in. Me/Family toggle (real
    dependents), a horizontal week-day strip, and per-day session cards.

    The dynamic regions (week-strip dot rows, family avatars, hero stats, and the
    sessions list) are rendered by the inline scheduleBoard() script so writes
    patch the UI in place — no reload, per project rules. Card markup mirrors the
    original Blade exactly. The create/edit sheet is <x-schedule-session-modal>.
--}}

@section('personal-content')
<div class="-mx-4 -mt-4 pb-4">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-12 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ __('personal.personal_schedule_this_week') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('personal.personal_schedule_heading') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <button type="button"
                        onclick="window.dispatchEvent(new CustomEvent('open-schedule-form'))"
                        class="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform"
                        aria-label="{{ __('personal.personal_schedule_add_session') }}">
                    <i class="bi bi-plus-lg text-xl"></i>
                </button>
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                    <i class="bi bi-calendar2-week-fill text-xl m-float"></i>
                </div>
            </div>
        </div>

        <div class="flex gap-2 mt-5 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none" id="sched-stat-count">0</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.personal_schedule_stat_sessions') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none" id="sched-stat-done">0</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('shared.done') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none" id="sched-stat-vol">0h</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.personal_schedule_stat_volume') }}</p>
            </div>
        </div>
    </header>

    {{-- ===== Me / Family toggle (overlaps hero) ===== --}}
    <div class="px-4 -mt-6 relative z-10">
        <div class="bg-white rounded-2xl shadow-md border border-gray-100 p-1 flex" id="sched-who-toggle">
            <button type="button" data-who="all"
                    class="m-press flex-1 py-2.5 rounded-xl text-sm font-bold transition-colors flex items-center justify-center gap-2 bg-primary text-white">
                <i class="bi bi-people-fill"></i> {{ __('personal.personal_schedule_family') }}
            </button>
            <button type="button" data-who="me"
                    class="m-press flex-1 py-2.5 rounded-xl text-sm font-bold transition-colors flex items-center justify-center gap-2 text-muted-foreground">
                <i class="bi bi-person-fill"></i> {{ __('personal.personal_schedule_just_me') }}
            </button>
        </div>
    </div>

    {{-- ===== Week-day strip (JS-rendered) ===== --}}
    <div class="px-4 mt-4">
        <div class="flex gap-1" id="sched-strip"></div>
    </div>

    {{-- ===== Sessions for the selected day (JS-rendered) ===== --}}
    <div class="px-4 mt-5" id="sched-sessions"></div>

</div>

<x-schedule-session-modal :subjects="$subjectsList" />

@include('partials.schedule-board-script')
@endsection
