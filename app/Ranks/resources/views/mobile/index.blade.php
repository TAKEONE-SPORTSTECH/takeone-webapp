@extends('clubs::layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')).' · '.__('ranks::messages.title'))

@section('club-admin-content')
<x-belt-icon-defs />
<div class="-mx-4 -mt-4" x-data="ranksHub(@js($ranksPayload))" x-init="init()">

    {{-- ══════════════ Hero ══════════════ --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden" x-show="panel === null">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="relative z-10 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70 truncate">{{ $club->club_name ?? __('admin.club') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('ranks::messages.title') }}</h1>
                <p class="text-[12px] text-white/80 mt-1">{{ __('ranks::messages.sub') }}</p>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                <i class="bi bi-award text-xl m-float"></i>
            </div>
        </div>
    </header>

    {{-- ══════════════ HUB ══════════════ --}}
    <div x-show="panel === null" class="px-4 pt-5 pb-6 space-y-2.5 mobile-stagger">
        <template x-if="! ranked.length">
            <div class="m-card p-6 text-center">
                <span class="w-12 h-12 mx-auto rounded-2xl bg-accent text-primary grid place-items-center"><i class="bi bi-award text-xl"></i></span>
                <p class="font-bold text-foreground mt-3">{{ __('ranks::messages.no_ranked') }}</p>
                <p class="text-xs text-muted-foreground mt-1">{{ __('ranks::messages.no_ranked_sub') }}</p>
            </div>
        </template>

        <template x-for="a in ranked" :key="a.uuid">
            <button type="button" @click="panel = a.uuid; q = ''; window.scrollTo(0, 0)" class="m-card m-press w-full flex items-center gap-3 p-4 text-start">
                <span class="w-11 h-11 rounded-xl bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-award-fill text-lg"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-foreground" x-text="a.name"></span>
                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :class="countRanked(a) === a.members.length && a.members.length ? 'bg-emerald-500' : 'bg-amber-400'"></span>
                    </span>
                    <span class="block text-[11px] text-muted-foreground mt-0.5" x-text="a.package + ' · ' + @js(__('ranks::messages.ranked_of', ['n' => '__N__', 'total' => '__T__'])).replace('__N__', countRanked(a)).replace('__T__', a.members.length)"></span>
                    <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-bold" x-show="countStatus(a, 'eligible')">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span><span x-text="eligibleText(a)"></span>
                    </span>
                </span>
                <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground text-sm flex-shrink-0"></i>
            </button>
        </template>

        <template x-if="canChoose && others.length">
            <div class="pt-3">
                <p class="px-1 text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('ranks::messages.other_activities') }}</p>
                <div class="m-card divide-y divide-gray-100">
                    <template x-for="a in others" :key="a.uuid">
                        <div class="px-4 py-3 flex items-center gap-3">
                            <span class="min-w-0 flex-1 text-sm text-foreground" x-text="a.name"></span>
                            <button type="button" @click="openPkg(a)" class="m-press text-xs font-semibold text-primary px-2 py-1">{{ __('ranks::messages.change_sport') }}</button>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- ══════════════ ACTIVITY PANEL ══════════════ --}}
    <div x-show="panel !== null" x-cloak class="m-panel-in">
        <div class="sticky top-0 z-20 bg-background/95 backdrop-blur border-b border-gray-100 px-4 py-3 flex items-center gap-3">
            <button type="button" @click="panel = null" aria-label="{{ __('ranks::messages.activities') }}" title="{{ __('ranks::messages.activities') }}"
                    class="w-10 h-10 rounded-full bg-white border border-gray-200 grid place-items-center flex-shrink-0 m-press">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </button>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-foreground" x-text="current ? current.name : ''"></p>
                <p class="text-[11px] text-muted-foreground" x-text="current ? current.package : ''"></p>
            </div>
            <template x-if="canChoose && current">
                <button type="button" @click="openPkg(current)" class="m-press text-xs font-semibold text-primary px-2 py-1">{{ __('ranks::messages.change_sport') }}</button>
            </template>
        </div>

        <div class="px-4 pt-4 pb-8 space-y-3">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="search" x-model="q" id="ranksSearchM" placeholder="{{ __('ranks::messages.search') }}"
                       class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm bg-white">
            </div>

            {{-- Gradings --}}
            <div class="m-card p-3" x-show="current">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">{{ __('ranks::messages.gradings') }}</p>
                    <button type="button" @click="openNewGrading(current)" x-show="current && current.members.length"
                            class="m-press px-3 py-1.5 rounded-full bg-primary text-white text-xs font-bold"><i class="bi bi-plus-lg me-1"></i>{{ __('ranks::messages.new_grading') }}</button>
                </div>
                <p class="text-xs text-muted-foreground" x-show="current && ! current.gradings.length">{{ __('ranks::messages.no_gradings') }}</p>
                <div class="space-y-1.5" x-show="current && current.gradings.length">
                    <template x-for="gd in (current ? current.gradings : [])" :key="gd.uuid">
                        <button type="button" @click="openGrading(current, gd)" class="m-press w-full text-start rounded-xl bg-muted/60 px-3 py-2 flex items-center gap-2">
                            <i class="bi bi-trophy text-amber-600"></i>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-foreground" x-text="gd.title"></span>
                                <span class="block text-[11px] text-muted-foreground" x-text="gd.held_on + ' · ' + (gd.status === 'done' ? @js(__('ranks::messages.done')) : @js(__('ranks::messages.planned'))) + ' · ' + gd.passed + '/' + gd.candidates"></span>
                            </span>
                            <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground text-xs"></i>
                        </button>
                    </template>
                </div>
            </div>

            <div class="flex items-center gap-2 overflow-x-auto -mx-1 px-1 pb-1" x-show="current && current.members.length">
                <template x-for="f in [['all', @js(__('ranks::messages.filter_all'))], ['eligible', @js(__('ranks::messages.filter_eligible'))], ['nearly', @js(__('ranks::messages.filter_nearly'))], ['unranked', @js(__('ranks::messages.filter_unranked'))]]" :key="f[0]">
                    <button type="button" @click="filter = f[0]" class="m-press flex-shrink-0 px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors"
                            :class="filter === f[0] ? 'bg-primary text-white border-primary' : 'bg-white text-foreground border-gray-200'">
                        <span x-text="f[1]"></span><span class="ms-1 opacity-75" x-show="f[0] !== 'all'" x-text="countStatus(current, f[0])"></span>
                    </button>
                </template>
                <button type="button" @click="openReq(current)" class="m-press flex-shrink-0 ms-auto px-3 py-1.5 rounded-full text-xs font-semibold border border-primary text-primary bg-white">
                    <i class="bi bi-list-check me-1"></i>{{ __('ranks::messages.requirements') }}
                </button>
            </div>

            <p class="text-center text-sm text-muted-foreground py-6" x-show="current && ! current.members.length">{{ __('ranks::messages.no_members') }}</p>

            <template x-for="m in filtered(current)" :key="m.uuid">
                <div class="m-card p-3 flex items-center gap-3">
                    <span class="w-9 h-12 rounded-lg overflow-hidden bg-muted flex-shrink-0"><img :src="m.photo" alt="" class="w-full h-full object-cover"></span>
                    <button type="button" @click="openHistory(current, m)" class="min-w-0 flex-1 text-start">
                        <span class="flex items-center gap-1.5">
                            <span class="text-sm font-semibold text-foreground" x-text="m.name"></span>
                            <span class="w-2 h-2 rounded-full flex-shrink-0" :class="dot(m)"></span>
                        </span>
                        <span class="flex items-center gap-1.5 mt-0.5" x-show="m.rank">
                            <svg class="w-5 h-5 flex-shrink-0" :style="m.rank && m.rank.icon" aria-hidden="true"><use href="#belt-icon"></use></svg>
                            <span class="text-[11px] text-muted-foreground" x-text="m.rank ? m.rank.label : ''"></span>
                        </span>
                        <span class="block text-[11px] text-muted-foreground mt-0.5" x-show="! m.rank">{{ __('ranks::messages.unranked') }}</span>
                        <span class="block text-[11px] mt-0.5" x-show="m.next" :class="m.status === 'eligible' ? 'text-emerald-600 font-semibold' : 'text-muted-foreground'"
                              x-text="m.next ? words.next + ' ' + m.next.label + ' · ' + (m.status === 'eligible' ? statusLabels.eligible : (m.progress ? progressText(m) : statusLabels[m.status])) : ''"></span>
                        <span class="block h-1 rounded-full bg-gray-100 mt-1 overflow-hidden" x-show="m.progress">
                            <span class="block h-full rounded-full" :class="m.status === 'eligible' ? 'bg-emerald-500' : (m.status === 'nearly' ? 'bg-amber-400' : 'bg-primary/60')" :style="'width:' + progressPct(m) + '%'"></span>
                        </span>
                    </button>
                    <button type="button" @click="openSet(current, m)"
                            class="m-press flex-shrink-0 px-3 h-10 rounded-xl bg-primary text-white text-xs font-bold"
                            x-text="m.rank ? @js(__('ranks::messages.promote')) : @js(__('ranks::messages.set_rank'))"></button>
                </div>
            </template>
        </div>
    </div>

    @include('ranks::partials.sheets')
</div>
@include('ranks::partials.hub-script')
@endsection
