@extends($shell ?? 'members::layouts.personal-mobile')

@section('title', __('events::events.budget_title').' · '.$event->title)

{{--
    Budget & profit — mobile. The organiser's plan for what the event costs and
    earns, beside what it has really taken. One column: the result, the knobs,
    medals, costs, scenarios, the real numbers, then the targets behind the
    suggested fee. Behaviour lives in budget/partials/script (shared with
    desktop); every card is a partial both pages use.
--}}
@section('personal-content')
@include('events::budget.partials.script')

<div x-data="eventBudget(@js(['budget' => $payload, 'base' => url('/me/events/'.$event->uuid.'/budget')]))" x-cloak>

    <header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('me.events.manage', $event->uuid) }}"
               aria-label="{{ __('personal.event_manage_title') }}" title="{{ __('personal.event_manage_title') }}"
               class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white m-press">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-graph-up-arrow"></i> {{ __('events::events.budget_title') }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"
                      x-text="b.approved_at ? @js(__('events::events.budget_chip_approved')) : (b.turnout.entered ? @js(__('events::events.budget_chip_live')) : @js(__('events::events.budget_chip_estimate')))"></span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $event->title }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5"><i class="bi bi-building"></i>{{ $owner }}</p>
        </div>
    </header>

    <div class="-mt-10 relative z-10 space-y-4 pb-24 mobile-stagger">
        @include('events::budget.partials.overview')
        @include('events::budget.partials.tune')
        @include('events::budget.partials.medals')
        @include('events::budget.partials.divisions')
        @include('events::budget.partials.costs')
        @include('events::budget.partials.scenarios')
        @include('events::budget.partials.actual')
        @include('events::budget.partials.targets')

        <button type="button" @click="openStart()" x-show="(b.items || []).length"
                class="m-press w-full h-11 rounded-xl border border-dashed border-gray-300 text-[13px] font-bold text-muted-foreground flex items-center justify-center gap-2">
            <i class="bi bi-copy"></i>{{ __('events::events.budget_add_from') }}
        </button>
    </div>

    @include('events::budget.partials.sheets')

    {{-- Ask the advisor — teleported, because the content column carries a
         transform (mobile-stagger) that would trap a fixed button. --}}
    @if (\App\Events\Support\Budget\BudgetAdvisor::enabled())
        <template x-teleport="body">
            <button type="button" x-show="! sheet" x-transition.opacity @click="openAdvisor()"
                    class="m-press fixed end-4 z-[55] h-14 ps-4 rounded-full text-white text-sm font-extrabold flex items-center gap-2 shadow-xl"
                    style="padding-inline-end:1.25rem; bottom: calc(1.25rem + env(safe-area-inset-bottom)); background: linear-gradient(135deg, #7c6bf5, #5b4bd6); box-shadow: 0 12px 28px rgba(91,75,214,.4);">
                <i class="bi bi-stars text-lg"></i>{{ __('events::events.budget_advisor_ask') }}
            </button>
        </template>
    @endif
</div>
@endsection
