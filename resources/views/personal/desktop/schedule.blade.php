@extends('layouts.app')

@section('title', __('personal.personal_schedule_page_title'))

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6">

    @include('partials.personal-desktop-subnav')

    <div class="flex flex-col lg:flex-row gap-6 items-start">
        {{-- ===== Left: week strip + sessions ===== --}}
        <div class="flex-1 min-w-0 space-y-4">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">{{ __('personal.personal_schedule_this_week') }}</p>
                        <h1 class="text-2xl font-bold text-gray-900 mt-0.5">{{ __('personal.personal_schedule_heading') }}</h1>
                    </div>
                    <button type="button"
                            onclick="window.dispatchEvent(new CustomEvent('open-schedule-form'))"
                            class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium flex items-center gap-2">
                        <i class="bi bi-plus-lg"></i>{{ __('personal.personal_schedule_add_session') }}
                    </button>
                </div>

                {{-- Week-day strip (JS-rendered) --}}
                <div class="flex gap-1.5">
                    <div class="flex gap-1.5 flex-1" id="sched-strip"></div>
                </div>
            </div>

            {{-- Sessions for the selected day (JS-rendered, grid on desktop) --}}
            <div id="sched-sessions" data-layout="grid"></div>
        </div>

        {{-- ===== Right sidebar: stats + who toggle ===== --}}
        <aside class="w-full lg:w-72 flex-shrink-0 space-y-4 lg:sticky lg:top-20">
            <div class="rounded-2xl shadow-sm p-5 text-white relative overflow-hidden" style="background: linear-gradient(135deg, hsl(250 65% 65%), hsl(250 65% 52%));">
                <div class="absolute -end-8 -top-8 w-32 h-32 rounded-full bg-white/10"></div>
                <div class="relative grid grid-cols-3 gap-2 text-center">
                    <div>
                        <p class="text-xl font-black leading-none" id="sched-stat-count">0</p>
                        <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.personal_schedule_stat_sessions') }}</p>
                    </div>
                    <div>
                        <p class="text-xl font-black leading-none" id="sched-stat-done">0</p>
                        <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('shared.done') }}</p>
                    </div>
                    <div>
                        <p class="text-xl font-black leading-none" id="sched-stat-vol">0h</p>
                        <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.personal_schedule_stat_volume') }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1.5 flex" id="sched-who-toggle">
                <button type="button" data-who="all"
                        class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-colors flex items-center justify-center gap-2 bg-primary text-white">
                    <i class="bi bi-people-fill"></i> {{ __('personal.personal_schedule_family') }}
                </button>
                <button type="button" data-who="me"
                        class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-colors flex items-center justify-center gap-2 text-muted-foreground">
                    <i class="bi bi-person-fill"></i> {{ __('personal.personal_schedule_just_me') }}
                </button>
            </div>
        </aside>
    </div>
</div>

<x-schedule-session-modal :subjects="$subjectsList" />

@include('partials.schedule-board-script')
@endsection
