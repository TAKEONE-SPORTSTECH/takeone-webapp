@extends('clubs::layouts.admin-club')

@section('title', ($club->club_name ?? __('admin.club')).' · '.__('ranks::messages.title'))

@section('club-admin-content')
<x-belt-icon-defs />
<div x-data="ranksHub(@js($ranksPayload))" x-init="init()" class="space-y-6">

    <x-admin-hero :eyebrow="$club->club_name" :title="__('ranks::messages.title')" :subtitle="__('ranks::messages.sub')" icon="bi-award" />

    {{-- Nothing ranked yet: say how it arrives, rather than show an empty table. --}}
    <template x-if="! ranked.length">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
            <span class="w-14 h-14 mx-auto rounded-2xl bg-accent text-primary grid place-items-center"><i class="bi bi-award text-2xl"></i></span>
            <p class="font-bold text-gray-900 mt-4">{{ __('ranks::messages.no_ranked') }}</p>
            <p class="text-sm text-muted-foreground mt-1 max-w-md mx-auto">{{ __('ranks::messages.no_ranked_sub') }}</p>
        </div>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6" x-show="ranked.length || others.length">
        {{-- Activities --}}
        <aside class="lg:col-span-4 space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ __('ranks::messages.activities') }}</p>
            <template x-for="a in ranked" :key="a.uuid">
                <button type="button" @click="sel = a.uuid; q = ''"
                        class="w-full text-start bg-white rounded-xl border p-4 flex items-center gap-3 transition-all hover:shadow-sm"
                        :class="sel === a.uuid ? 'border-primary ring-2 ring-primary/20' : 'border-gray-100'">
                    <span class="w-11 h-11 rounded-xl bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-award-fill text-lg"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-gray-900" x-text="a.name"></span>
                        <span class="block text-xs text-muted-foreground mt-0.5" x-text="a.package"></span>
                        <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-bold" x-show="countStatus(a, 'eligible')">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span><span x-text="eligibleText(a)"></span>
                        </span>
                    </span>
                    <span class="text-end flex-shrink-0">
                        <span class="block text-sm font-bold text-gray-900 tabular-nums" dir="ltr" x-text="countRanked(a) + ' / ' + a.members.length"></span>
                        <span class="block text-[10px] uppercase tracking-wide text-muted-foreground">{{ __('ranks::messages.ranked') }}</span>
                    </span>
                </button>
            </template>

            <template x-if="canChoose && others.length">
                <div class="pt-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground mb-2">{{ __('ranks::messages.other_activities') }}</p>
                    <div class="bg-white rounded-xl border border-gray-100 divide-y divide-gray-100">
                        <template x-for="a in others" :key="a.uuid">
                            <div class="px-4 py-3 flex items-center gap-3">
                                <span class="min-w-0 flex-1 text-sm text-gray-700" x-text="a.name"></span>
                                <button type="button" @click="openPkg(a)" class="text-xs font-semibold text-primary hover:underline flex-shrink-0">{{ __('ranks::messages.change_sport') }}</button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </aside>

        {{-- Roster of the selected activity --}}
        <section class="lg:col-span-8" x-show="current">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                <div class="p-5 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
                    <div class="min-w-0">
                        <h2 class="text-lg font-bold text-gray-900" x-text="current ? current.name : ''"></h2>
                        <p class="text-xs text-muted-foreground mt-0.5 flex items-center gap-2">
                            <span x-text="current ? current.package : ''"></span>
                            <span>·</span>
                            <span x-text="current && current.matched_by === 'club' ? @js(__('ranks::messages.sport_club')) : @js(__('ranks::messages.sport_auto'))"></span>
                            <template x-if="canChoose">
                                <button type="button" @click="openPkg(current)" class="text-primary font-semibold hover:underline">{{ __('ranks::messages.change_sport') }}</button>
                            </template>
                        </p>
                    </div>
                    <button type="button" @click="openReq(current)" class="border border-primary text-primary bg-transparent px-3 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors flex-shrink-0">
                        <i class="bi bi-list-check me-1"></i>{{ __('ranks::messages.requirements') }}
                    </button>
                    <div class="relative sm:w-64">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="search" x-model="q" id="ranksSearch" placeholder="{{ __('ranks::messages.search') }}"
                               class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                    </div>
                </div>

                {{-- Gradings for this activity --}}
                <div class="px-5 pt-4" x-show="current">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ __('ranks::messages.gradings') }}</p>
                        <button type="button" @click="openNewGrading(current)" x-show="current && current.members.length"
                                class="bg-primary text-white px-3 py-1.5 rounded-lg hover:bg-primary/90 transition-colors text-xs font-medium"><i class="bi bi-plus-lg me-1"></i>{{ __('ranks::messages.new_grading') }}</button>
                    </div>
                    <p class="text-xs text-muted-foreground" x-show="current && ! current.gradings.length">{{ __('ranks::messages.no_gradings') }}</p>
                    <div class="flex gap-2 overflow-x-auto pb-1" x-show="current && current.gradings.length">
                        <template x-for="gd in (current ? current.gradings : [])" :key="gd.uuid">
                            <button type="button" @click="openGrading(current, gd)" class="flex-shrink-0 text-start rounded-xl border border-gray-100 bg-white hover:shadow-sm transition-all px-3 py-2">
                                <span class="block text-sm font-semibold text-gray-900" x-text="gd.title"></span>
                                <span class="block text-[11px] text-muted-foreground" x-text="gd.held_on + ' · ' + (gd.status === 'done' ? @js(__('ranks::messages.done')) : @js(__('ranks::messages.planned'))) + ' · ' + gd.passed + '/' + gd.candidates"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Filter by where people stand --}}
                <div class="px-5 pt-3 flex flex-wrap gap-2" x-show="current && current.members.length">
                    <template x-for="f in [['all', @js(__('ranks::messages.filter_all'))], ['eligible', @js(__('ranks::messages.filter_eligible'))], ['nearly', @js(__('ranks::messages.filter_nearly'))], ['unranked', @js(__('ranks::messages.filter_unranked'))]]" :key="f[0]">
                        <button type="button" @click="filter = f[0]" class="px-3 py-1.5 rounded-full text-xs font-medium border transition-colors"
                                :class="filter === f[0] ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 border-gray-200 hover:bg-muted/60'">
                            <span x-text="f[1]"></span>
                            <span class="ms-1 opacity-75" x-show="f[0] !== 'all'" x-text="countStatus(current, f[0])"></span>
                        </button>
                    </template>
                </div>

                <p class="p-8 text-center text-sm text-muted-foreground" x-show="current && ! current.members.length">{{ __('ranks::messages.no_members') }}</p>

                <ul class="divide-y divide-gray-100">
                    <template x-for="m in filtered(current)" :key="m.uuid">
                        <li class="px-5 py-3 flex items-center gap-4">
                            <span class="w-9 h-12 rounded-lg overflow-hidden bg-muted flex-shrink-0"><img :src="m.photo" alt="" class="w-full h-full object-cover"></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2">
                                    <span class="block font-semibold text-gray-900" x-text="m.name"></span>
                                    <span class="w-2 h-2 rounded-full flex-shrink-0" :class="dot(m)" :title="statusLabels[m.status]"></span>
                                </span>
                                <span class="block text-xs text-muted-foreground" x-text="m.age !== null ? @js(__('ranks::messages.age', ['n' => '__N__'])).replace('__N__', m.age) : ''"></span>
                                {{-- Where they stand for the next belt --}}
                                <template x-if="m.next">
                                    <span class="flex items-center gap-1.5 text-[11px] mt-0.5">
                                        <span class="text-muted-foreground" x-text="words.next"></span>
                                        <svg class="w-4 h-4 flex-shrink-0" :style="m.next.icon" aria-hidden="true"><use href="#belt-icon"></use></svg>
                                        <span class="font-semibold text-gray-800" x-text="m.next.label"></span>
                                        <span :class="m.status === 'eligible' ? 'text-emerald-600 font-semibold' : 'text-muted-foreground'"
                                              x-text="'· ' + (m.progress ? (m.status === 'eligible' ? statusLabels.eligible : progressText(m)) : statusLabels[m.status])"></span>
                                    </span>
                                </template>
                                <span class="block h-1 w-40 max-w-full rounded-full bg-gray-100 mt-1 overflow-hidden" x-show="m.progress">
                                    <span class="block h-full rounded-full" :class="m.status === 'eligible' ? 'bg-emerald-500' : (m.status === 'nearly' ? 'bg-amber-400' : 'bg-primary/60')" :style="'width:' + progressPct(m) + '%'"></span>
                                </span>
                            </span>
                            <span class="flex items-center gap-2 min-w-0 w-44">
                                <template x-if="m.rank">
                                    <span class="flex items-center gap-2 min-w-0">
                                        <svg class="w-7 h-7 flex-shrink-0" :style="m.rank.icon" aria-hidden="true"><use href="#belt-icon"></use></svg>
                                        <span class="min-w-0">
                                            <span class="block text-sm font-semibold text-gray-900" x-text="m.rank.label"></span>
                                            <span class="block text-[11px] text-muted-foreground" x-text="'{{ __('ranks::messages.since') }} ' + m.rank.since"></span>
                                        </span>
                                    </span>
                                </template>
                                <template x-if="! m.rank">
                                    <span class="text-xs text-muted-foreground">{{ __('ranks::messages.unranked') }}</span>
                                </template>
                            </span>
                            <span class="flex items-center gap-2 flex-shrink-0">
                                <button type="button" @click="openHistory(current, m)" title="{{ __('ranks::messages.history') }}"
                                        class="w-9 h-9 rounded-lg flex items-center justify-center bg-card text-foreground hover:bg-accent hover:shadow-sm transition-all border border-border">
                                    <i class="bi bi-clock-history"></i>
                                </button>
                                <button type="button" @click="openSet(current, m)"
                                        class="border border-primary text-primary bg-transparent px-3 py-1.5 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors"
                                        x-text="m.rank ? @js(__('ranks::messages.promote')) : @js(__('ranks::messages.set_rank'))"></button>
                            </span>
                        </li>
                    </template>
                </ul>
            </div>
        </section>
    </div>

    @include('ranks::partials.sheets')
</div>
@endsection

@push('scripts')
@include('ranks::partials.hub-script')
@endpush
