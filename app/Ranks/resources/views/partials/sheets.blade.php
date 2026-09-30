{{-- The three sheets of the ranks hub: set a rank, a member's history, and
     which sport an activity is. Teleported to <body> (Mobile Forms rule), each
     opening with the gradient header band (Design Rule #8). Rises from the
     bottom on a phone, centres on a desktop. Inside the ranksHub() scope. --}}
<template x-teleport="body">
<div>
    {{-- ── Set rank ─────────────────────────────────────────────────── --}}
    <div x-show="sheet.open" x-cloak class="fixed inset-0" style="z-index:70" @keydown.escape.window="sheet.open = false">
        <div class="absolute inset-0 bg-black/40" @click="sheet.open = false" x-show="sheet.open" x-transition.opacity></div>
        <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-6 pointer-events-none">
            <div class="pointer-events-auto w-full sm:max-w-lg flex flex-col bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden" style="max-height:92vh"
                 x-show="sheet.open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="flex-shrink-0 px-5 pt-3 pb-4 text-white relative overflow-hidden" style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi bi-award-fill text-xl"></i></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight" x-text="sheet.m ? sheet.m.name : ''"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="sheet.act ? sheet.act.name + (sheet.act.package ? ' · ' + sheet.act.package : '') : ''"></p>
                        </div>
                        <button type="button" @click="sheet.open = false" aria-label="{{ __('ranks::messages.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('ranks::messages.belt') }}</p>
                        <template x-for="grp in groups(sheet.act)" :key="grp.group">
                        <div class="mb-3">
                        <p class="text-[11px] font-semibold text-muted-foreground mb-1.5" x-show="groups(sheet.act).length > 1" x-text="grp.label"></p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <template x-for="b in grp.belts" :key="b.value">
                                <button type="button" @click="pickBelt(b.value)"
                                        class="m-press text-start rounded-xl border-2 px-3 py-2.5 flex items-center gap-3 transition-colors"
                                        :class="sheet.belt === b.value ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white hover:bg-muted/60'">
                                    <svg class="w-8 h-8 flex-shrink-0" :style="b.icon" aria-hidden="true"><use href="#belt-icon"></use></svg>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-bold text-foreground" x-text="b.label"></span>
                                                                            </span>
                                    <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
                                          :class="sheet.belt === b.value ? 'border-primary bg-primary text-white' : 'border-gray-300'">
                                        <i class="bi bi-check text-[11px]" x-show="sheet.belt === b.value"></i>
                                    </span>
                                </button>
                            </template>
                        </div>
                        </div>
                        </template>
                    </div>

                    <div x-show="sheet.act && step(sheet.act, sheet.belt) && step(sheet.act, sheet.belt).grades.length">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('ranks::messages.grade') }}</p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="g in (sheet.act && step(sheet.act, sheet.belt) ? step(sheet.act, sheet.belt).grades : [])" :key="g">
                                <button type="button" @click="sheet.grade = g"
                                        class="m-press px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors"
                                        :class="sheet.grade === g ? 'bg-primary text-white border-primary' : 'bg-white text-foreground border-gray-200 hover:bg-muted/60'"
                                        x-text="g"></button>
                            </template>
                        </div>
                    </div>

                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('ranks::messages.since') }}</p>
                        <x-fields.date model="sheet.since" :max="$today" />
                    </div>

                    <div>
                        <label for="rankNote" class="block text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('ranks::messages.note') }}</label>
                        <textarea id="rankNote" x-model="sheet.note" rows="2" maxlength="500"
                                  class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"></textarea>
                    </div>
                </div>

                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-white" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="save()" :disabled="sheet.saving || ! sheet.belt"
                            class="w-full h-12 rounded-xl bg-primary text-white font-bold flex items-center justify-center gap-2 disabled:opacity-60">
                        <i class="bi" :class="sheet.saving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>{{ __('ranks::messages.save') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── History ──────────────────────────────────────────────────── --}}
    <div x-show="hist.open" x-cloak class="fixed inset-0" style="z-index:70" @keydown.escape.window="hist.open = false">
        <div class="absolute inset-0 bg-black/40" @click="hist.open = false" x-show="hist.open" x-transition.opacity></div>
        <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-6 pointer-events-none">
            <div class="pointer-events-auto w-full sm:max-w-lg flex flex-col bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden" style="max-height:85vh"
                 x-show="hist.open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="flex-shrink-0 px-5 pt-3 pb-4 text-white relative overflow-hidden" style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi bi-clock-history text-xl"></i></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight" x-text="hist.m ? hist.m.name : ''"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('ranks::messages.history') }}</p>
                        </div>
                        <button type="button" @click="hist.open = false" aria-label="{{ __('ranks::messages.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto px-5 py-4" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                    <p class="text-sm text-muted-foreground" x-show="hist.loading"><i class="bi bi-arrow-repeat animate-spin me-1"></i></p>
                    <p class="text-sm text-muted-foreground" x-show="! hist.loading && ! hist.rows.length">{{ __('ranks::messages.no_history') }}</p>
                    <ol class="relative border-s-2 border-gray-100 ms-3 space-y-4" x-show="hist.rows.length">
                        <template x-for="(r, i) in hist.rows" :key="i">
                            <li class="ps-5 relative">
                                <span style="inset-inline-start:-13px" class="absolute top-0 w-6 h-6 rounded-full bg-white border-2 border-gray-100 grid place-items-center">
                                    <svg class="w-4 h-4" :style="r.icon" aria-hidden="true"><use href="#belt-icon"></use></svg>
                                </span>
                                <p class="text-sm font-bold text-foreground" x-text="r.to"></p>
                                <p class="text-[11px] text-muted-foreground" x-text="r.on + (r.from ? ' · ' + r.from + ' →' : '') + (r.by ? ' · {{ __('ranks::messages.by') }} ' + r.by : '')"></p>
                                <p class="text-xs text-foreground/80 mt-0.5" x-show="r.note" x-text="r.note"></p>
                            </li>
                        </template>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Which sport is this activity ─────────────────────────────── --}}
    <div x-show="pkg.open" x-cloak class="fixed inset-0" style="z-index:70" @keydown.escape.window="pkg.open = false">
        <div class="absolute inset-0 bg-black/40" @click="pkg.open = false" x-show="pkg.open" x-transition.opacity></div>
        <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-6 pointer-events-none">
            <div class="pointer-events-auto w-full sm:max-w-md flex flex-col bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden" style="max-height:85vh"
                 x-show="pkg.open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="flex-shrink-0 px-5 pt-3 pb-4 text-white relative overflow-hidden" style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi bi-journal-bookmark-fill text-xl"></i></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('ranks::messages.choose_sport') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="pkg.act ? pkg.act.name : ''"></p>
                        </div>
                        <button type="button" @click="pkg.open = false" aria-label="{{ __('ranks::messages.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-2" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                    <p class="text-xs text-muted-foreground mb-2">{{ __('ranks::messages.choose_sport_sub') }}</p>
                    <template x-for="c in choices" :key="c.sport + c.style">
                        <button type="button" @click="savePkg(c)" :disabled="pkg.saving"
                                class="m-press w-full text-start rounded-xl border-2 px-3 py-3 flex items-center gap-3 transition-colors"
                                :class="pkg.act && pkg.act.sport === c.sport && pkg.act.style === c.style ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white hover:bg-muted/60'">
                            <span class="w-9 h-9 rounded-lg bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-award"></i></span>
                            <span class="flex-1 text-sm font-bold text-foreground" x-text="c.label"></span>
                            <i class="bi bi-check-circle-fill text-primary" x-show="pkg.act && pkg.act.sport === c.sport && pkg.act.style === c.style"></i>
                        </button>
                    </template>
                    <button type="button" @click="savePkg(null)" :disabled="pkg.saving"
                            class="m-press w-full text-start rounded-xl border-2 px-3 py-3 flex items-center gap-3 transition-colors"
                            :class="pkg.act && ! pkg.act.sport ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white hover:bg-muted/60'">
                        <span class="w-9 h-9 rounded-lg bg-muted text-muted-foreground grid place-items-center flex-shrink-0"><i class="bi bi-slash-circle"></i></span>
                        <span class="flex-1 text-sm font-bold text-foreground">{{ __('ranks::messages.sport_none') }}</span>
                        <i class="bi bi-check-circle-fill text-primary" x-show="pkg.act && ! pkg.act.sport"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Grading requirements for an activity ─────────────────────── --}}
    <div x-show="req.open" x-cloak class="fixed inset-0" style="z-index:70" @keydown.escape.window="req.open = false">
        <div class="absolute inset-0 bg-black/40" @click="req.open = false" x-show="req.open" x-transition.opacity></div>
        <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-6 pointer-events-none">
            <div class="pointer-events-auto w-full sm:max-w-2xl flex flex-col bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden" style="max-height:92vh"
                 x-show="req.open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="flex-shrink-0 px-5 pt-3 pb-4 text-white relative overflow-hidden" style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi bi-list-check text-xl"></i></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('ranks::messages.requirements') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="req.act ? req.act.name + (req.act.package ? ' · ' + req.act.package : '') : ''"></p>
                        </div>
                        <button type="button" @click="req.open = false" aria-label="{{ __('ranks::messages.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto px-5 py-4">
                    <p class="text-xs text-muted-foreground mb-3">{{ __('ranks::messages.requirements_sub') }}</p>
                    <div class="grid grid-cols-12 gap-2 px-1 pb-1 text-[10px] font-bold uppercase tracking-wide text-muted-foreground">
                        <span class="col-span-5">{{ __('ranks::messages.belt') }}</span>
                        <span class="col-span-2 text-center">{{ __('ranks::messages.min_classes') }}</span>
                        <span class="col-span-3 text-center">{{ __('ranks::messages.min_months') }}</span>
                        <span class="col-span-2 text-center">{{ __('ranks::messages.min_age') }}</span>
                    </div>
                    <template x-for="(b, i) in (req.act ? req.act.ladder : [])" :key="b.value">
                        <div class="grid grid-cols-12 gap-2 items-center py-1.5 border-t border-gray-100" x-show="i > 0">
                            <span class="col-span-5 flex items-center gap-2 min-w-0">
                                <svg class="w-6 h-6 flex-shrink-0" :style="b.icon" aria-hidden="true"><use href="#belt-icon"></use></svg>
                                <span class="text-sm font-semibold text-foreground" x-text="b.label"></span>
                            </span>
                            <input type="number" min="0" max="999" inputmode="numeric" :aria-label="b.label + ' · {{ __('ranks::messages.min_classes') }}'"
                                   x-model="req.rows[b.value].min_classes" class="col-span-2 w-full px-2 py-2 border border-gray-200 rounded-lg text-sm text-center focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <input type="number" min="0" max="240" inputmode="numeric" :aria-label="b.label + ' · {{ __('ranks::messages.min_months') }}'"
                                   x-model="req.rows[b.value].min_months" class="col-span-3 w-full px-2 py-2 border border-gray-200 rounded-lg text-sm text-center focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <input type="number" min="0" max="99" inputmode="numeric" :aria-label="b.label + ' · {{ __('ranks::messages.min_age') }}'"
                                   x-model="req.rows[b.value].min_age" class="col-span-2 w-full px-2 py-2 border border-gray-200 rounded-lg text-sm text-center focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    </template>
                </div>
                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-white" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="saveReq()" :disabled="req.saving"
                            class="w-full h-12 rounded-xl bg-primary text-white font-bold flex items-center justify-center gap-2 disabled:opacity-60">
                        <i class="bi" :class="req.saving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>{{ __('ranks::messages.save') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── A grading: plan it, or record its results ────────────────── --}}
    <div x-show="gr.open" x-cloak class="fixed inset-0" style="z-index:70" @keydown.escape.window="gr.open = false">
        <div class="absolute inset-0 bg-black/40" @click="gr.open = false" x-show="gr.open" x-transition.opacity></div>
        <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-6 pointer-events-none">
            <div class="pointer-events-auto w-full sm:max-w-2xl flex flex-col bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden" style="max-height:92vh"
                 x-show="gr.open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6" x-transition:enter-end="opacity-100 translate-y-0">
                <div class="flex-shrink-0 px-5 pt-3 pb-4 text-white relative overflow-hidden" style="background: linear-gradient(150deg, #b45309, #d97706b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi bi-trophy-fill text-xl"></i></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight" x-text="gr.mode === 'new' ? @js(__('ranks::messages.new_grading')) : gr.title"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="(gr.act ? gr.act.name : '') + (gr.mode === 'view' ? ' · ' + gr.held_on : '')"></p>
                        </div>
                        <button type="button" @click="gr.open = false" aria-label="{{ __('ranks::messages.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="relative mt-3 flex flex-wrap gap-1.5" x-show="gr.mode === 'view' && gr.detail">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold"
                              x-text="gr.detail && gr.detail.status === 'done' ? @js(__('ranks::messages.done')) : @js(__('ranks::messages.planned'))"></span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold"
                              x-text="@js(__('ranks::messages.passed_of', ['n' => '__N__', 'total' => '__T__'])).replace('__N__', passedCount()).replace('__T__', gr.detail ? gr.detail.candidates.length : 0)"></span>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                    <p class="text-sm text-muted-foreground" x-show="gr.loading"><i class="bi bi-arrow-repeat animate-spin"></i></p>

                    {{-- Plan --}}
                    <template x-if="gr.mode === 'new'">
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label for="grTitle" class="block text-sm font-medium text-gray-700 mb-1">{{ __('ranks::messages.grading_title') }}</label>
                                    <input id="grTitle" type="text" maxlength="120" x-model="gr.title" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                                </div>
                                <div>
                                    <p class="block text-sm font-medium text-gray-700 mb-1">{{ __('ranks::messages.grading_date') }}</p>
                                    <x-fields.date model="gr.held_on" />
                                </div>
                            </div>
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('ranks::messages.pick_candidates') }} · <span x-text="pickCount()"></span></p>
                                <div class="space-y-2">
                                    <template x-for="m in (gr.act ? gr.act.members : [])" :key="m.uuid">
                                        <div class="rounded-xl border-2 px-3 py-2.5 flex items-center gap-3 transition-colors"
                                             :class="gr.picks[m.uuid] && gr.picks[m.uuid].on ? 'border-primary bg-primary/5' : 'border-gray-200'">
                                            <button type="button" @click="gr.picks[m.uuid].on = ! gr.picks[m.uuid].on" class="flex items-center gap-3 min-w-0 flex-1 text-start">
                                                <span class="w-5 h-5 rounded-md border-2 grid place-items-center flex-shrink-0"
                                                      :class="gr.picks[m.uuid] && gr.picks[m.uuid].on ? 'border-primary bg-primary text-white' : 'border-gray-300'">
                                                    <i class="bi bi-check text-[11px]" x-show="gr.picks[m.uuid] && gr.picks[m.uuid].on"></i>
                                                </span>
                                                <span class="w-2 h-2 rounded-full flex-shrink-0" :class="dot(m)"></span>
                                                <span class="text-sm font-semibold text-foreground" x-text="m.name"></span>
                                            </button>
                                            <span class="flex items-center gap-1.5 flex-shrink-0 text-xs text-muted-foreground" x-show="gr.picks[m.uuid] && gr.picks[m.uuid].on">
                                                <span>{{ __('ranks::messages.for_belt') }}</span>
                                                <svg class="w-5 h-5" :style="step(gr.act, gr.picks[m.uuid].belt) && step(gr.act, gr.picks[m.uuid].belt).icon" aria-hidden="true"><use href="#belt-icon"></use></svg>
                                                <span class="font-semibold text-foreground" x-text="step(gr.act, gr.picks[m.uuid].belt) ? step(gr.act, gr.picks[m.uuid].belt).label : ''"></span>
                                            </span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- Results --}}
                    <template x-if="gr.mode === 'view' && gr.detail">
                        <div class="space-y-2">
                            <template x-for="c in gr.detail.candidates" :key="c.uuid">
                                <div class="rounded-xl border border-gray-100 px-3 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                                    <span class="flex items-center gap-3 min-w-0 flex-1">
                                        <span class="w-8 h-10 rounded-md overflow-hidden bg-muted flex-shrink-0"><img :src="c.photo" alt="" class="w-full h-full object-cover"></span>
                                        <span class="min-w-0">
                                            <span class="block text-sm font-semibold text-foreground" x-text="c.name"></span>
                                            <span class="flex items-center gap-1 text-[11px] text-muted-foreground">
                                                {{ __('ranks::messages.for_belt') }}
                                                <svg class="w-4 h-4" :style="c.target.icon" aria-hidden="true"><use href="#belt-icon"></use></svg>
                                                <span x-text="c.target.label"></span>
                                            </span>
                                        </span>
                                    </span>
                                    <span class="flex gap-1.5 flex-shrink-0" role="radiogroup" :aria-label="c.name">
                                        <template x-for="r in [['passed', @js(__('ranks::messages.passed')), 'bg-emerald-600 border-transparent'], ['failed', @js(__('ranks::messages.failed')), 'bg-gray-900 border-transparent'], ['absent', @js(__('ranks::messages.absent')), 'bg-amber-500 border-transparent']]" :key="r[0]">
                                            <button type="button" role="radio" :aria-checked="gr.results[c.uuid] === r[0]" :disabled="gr.detail.status === 'done'"
                                                    @click="gr.results[c.uuid] = gr.results[c.uuid] === r[0] ? 'pending' : r[0]"
                                                    class="px-2.5 py-1.5 rounded-lg border text-xs font-semibold transition-colors disabled:opacity-70"
                                                    :class="gr.results[c.uuid] === r[0] ? 'text-white ' + r[2] : 'bg-white text-gray-600 border-gray-200 hover:bg-muted/60'"
                                                    x-text="r[1]"></button>
                                        </template>
                                    </span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-white flex gap-2" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));"
                     x-show="gr.mode === 'new' || (gr.detail && gr.detail.status !== 'done')">
                    <template x-if="gr.mode === 'new'">
                        <button type="button" @click="saveNewGrading()" :disabled="gr.saving || ! pickCount()"
                                class="flex-1 h-12 rounded-xl bg-primary text-white font-bold flex items-center justify-center gap-2 disabled:opacity-60">
                            <i class="bi" :class="gr.saving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>{{ __('ranks::messages.save') }}
                        </button>
                    </template>
                    <template x-if="gr.mode === 'view'">
                        <div class="flex-1 flex flex-col sm:flex-row gap-2">
                            <button type="button" @click="deleteGrading()" :disabled="gr.saving" title="{{ __('ranks::messages.delete_grading') }}"
                                    class="h-12 px-4 rounded-xl border border-red-300 text-red-600 hover:bg-red-50 font-semibold text-sm"><i class="bi bi-trash"></i></button>
                            <button type="button" @click="saveResults(false)" :disabled="gr.saving"
                                    class="flex-1 h-12 rounded-xl border border-primary text-primary font-bold text-sm">{{ __('ranks::messages.save_results') }}</button>
                            <button type="button" @click="saveResults(true)" :disabled="gr.saving || ! passedCount()"
                                    class="flex-1 h-12 rounded-xl bg-primary text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-60">
                                <i class="bi" :class="gr.saving ? 'bi-arrow-repeat animate-spin' : 'bi-award'"></i>{{ __('ranks::messages.finish_grading') }}
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</div>
</template>
