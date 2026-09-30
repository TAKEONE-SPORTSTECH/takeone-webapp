{{-- The workspace's bottom sheets — one per focused job. Teleported to <body>
     (the shell's transformed ancestor would otherwise clip them) and all
     carrying the Design Rule #8 gradient band. Their state lives in the
     registered `championshipWorkspace` component, so teleporting is safe. --}}
@php $sheetBand = "background: linear-gradient(150deg, {$color}, {$color}b0);"; @endphp

<template x-teleport="body">
    <div x-show="sheet" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="sheet = null">
        <div class="absolute inset-0 bg-black/40" @click="sheet = null"
             x-show="sheet" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"></div>

        <div class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl"
             x-show="sheet" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">

            {{-- ===== Band ===== --}}
            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden" style="{{ $sheetBand }}">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi text-xl" :class="sheetIcon"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-black leading-tight break-words" x-text="sheetTitle"></h3>
                        <p class="text-[12px] text-white/85 mt-0.5" x-text="sheetSub"></p>
                    </div>
                    <button type="button" @click="sheet = null" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">

                {{-- ===== A contest's result ===== --}}
                <template x-if="sheet === 'contest' && contest">
                    <div class="space-y-4">
                        {{-- Sets and games. --}}
                        <template x-if="c.result === 'sets'">
                            <div class="bg-white rounded-2xl border border-gray-100 p-4">
                                <div class="grid gap-2 items-center text-[11px] font-bold text-muted-foreground uppercase tracking-wide" style="grid-template-columns: 1fr 4rem 4rem;">
                                    <span></span>
                                    <span class="text-center break-words normal-case text-[12px] text-foreground" x-text="contest.a"></span>
                                    <span class="text-center break-words normal-case text-[12px] text-foreground" x-text="contest.b"></span>
                                </div>
                                <template x-for="(s, i) in form.sets" :key="i">
                                    <div class="grid gap-2 items-center mt-2" style="grid-template-columns: 1fr 4rem 4rem;">
                                        <span class="text-sm font-semibold text-foreground" x-text="setLabel(i)"></span>
                                        <input type="number" inputmode="numeric" min="0" max="99" x-model="s[0]"
                                               class="w-full px-2 py-2.5 border border-gray-200 rounded-lg text-center font-black tabular-nums focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                        <input type="number" inputmode="numeric" min="0" max="99" x-model="s[1]"
                                               class="w-full px-2 py-2.5 border border-gray-200 rounded-lg text-center font-black tabular-nums focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- A score (goals, points, a chess result). --}}
                        <template x-if="c.result !== 'sets'">
                            <div class="bg-white rounded-2xl border border-gray-100 p-4 space-y-3">
                                <div class="grid gap-3 items-center" style="grid-template-columns: 1fr 5rem;">
                                    <span class="text-sm font-bold text-foreground break-words" x-text="contest.a"></span>
                                    <input type="number" inputmode="decimal" min="0" :step="c.draws && c.contest !== 'match' ? '0.5' : '1'" x-model="form.a"
                                           class="w-full px-2 py-3 border border-gray-200 rounded-lg text-center text-lg font-black tabular-nums focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                </div>
                                <div class="grid gap-3 items-center" style="grid-template-columns: 1fr 5rem;">
                                    <span class="text-sm font-bold text-foreground break-words" x-text="contest.b"></span>
                                    <input type="number" inputmode="decimal" min="0" :step="c.draws && c.contest !== 'match' ? '0.5' : '1'" x-model="form.b"
                                           class="w-full px-2 py-3 border border-gray-200 rounded-lg text-center text-lg font-black tabular-nums focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                </div>

                                {{-- A tie that must be broken: a knockout, or a sport with no draws. --}}
                                <template x-if="needsDecider">
                                    <div class="rounded-xl bg-accent/60 p-3 space-y-2">
                                        <p class="text-xs font-bold text-primary flex items-center gap-1.5"><i class="bi bi-bullseye"></i>{{ __('event-sport_championship::messages.penalties') }}</p>
                                        <div class="grid grid-cols-2 gap-2">
                                            <input type="number" inputmode="numeric" min="0" max="99" x-model="form.pa" :aria-label="contest.a"
                                                   class="w-full px-2 py-2.5 border border-gray-200 rounded-lg text-center font-black tabular-nums bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                            <input type="number" inputmode="numeric" min="0" max="99" x-model="form.pb" :aria-label="contest.b"
                                                   class="w-full px-2 py-2.5 border border-gray-200 rounded-lg text-center font-black tabular-nums bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Walkover: somebody did not turn up. --}}
                        <div class="bg-white rounded-2xl border border-gray-100 p-4">
                            <p class="text-xs font-bold text-muted-foreground uppercase tracking-wide mb-2">{{ __('event-sport_championship::messages.walkover') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="side in ['a', 'b']" :key="side">
                                    <button type="button" @click="form.walkover = form.walkover === side ? null : side"
                                            class="m-press rounded-xl border px-3 py-2.5 text-[13px] font-semibold text-start flex items-center gap-2 transition-colors"
                                            :class="form.walkover === side ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-foreground'">
                                        <i class="bi" :class="form.walkover === side ? 'bi-check-circle-fill' : 'bi-circle'"></i>
                                        <span class="min-w-0 break-words" x-text="contest[side]"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- ===== One entrant's time / distance / marks ===== --}}
                <template x-if="sheet === 'performance' && perf">
                    <div class="space-y-4">
                        <div class="bg-white rounded-2xl border border-gray-100 p-4 space-y-2" x-show="form.status === 'ok'">
                            <template x-for="(a, i) in form.attempts" :key="i">
                                <div class="grid gap-3 items-center" style="grid-template-columns: 1fr 7rem;">
                                    <span class="text-sm font-semibold text-foreground" x-text="attemptLabel(i)"></span>
                                    <input type="number" inputmode="decimal" min="0" step="0.01" x-model="form.attempts[i]"
                                           class="w-full px-2 py-2.5 border border-gray-200 rounded-lg text-center font-black tabular-nums focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                </div>
                            </template>
                            <template x-if="c.scoring.difficulty">
                                <div class="grid gap-3 items-center pt-2 border-t border-gray-100" style="grid-template-columns: 1fr 7rem;">
                                    <span class="text-sm font-semibold text-foreground">{{ __('event-sport_championship::messages.difficulty') }}</span>
                                    <input type="number" inputmode="decimal" min="0" max="10" step="0.1" x-model="form.difficulty"
                                           class="w-full px-2 py-2.5 border border-gray-200 rounded-lg text-center font-black tabular-nums focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                </div>
                            </template>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="st in statuses" :key="st.key">
                                <button type="button" @click="form.status = st.key"
                                        class="m-press rounded-xl border px-3 py-2.5 text-[13px] font-semibold flex items-center gap-2 transition-colors"
                                        :class="form.status === st.key ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 bg-white text-foreground'">
                                    <i class="bi" :class="form.status === st.key ? 'bi-check-circle-fill' : 'bi-circle'"></i>
                                    <span x-text="st.label"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- ===== A new pair / team ===== --}}
                <template x-if="sheet === 'side'">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('event-sport_championship::messages.side_name') }}</label>
                            <input type="text" maxlength="80" x-model="form.name" placeholder="{{ __('event-sport_championship::messages.side_name_hint') }}"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
                        </div>
                        <div class="space-y-2">
                            <p class="text-sm font-medium text-gray-700">{{ __('event-sport_championship::messages.choose_division') }}</p>
                            <template x-for="d in c.divisions" :key="d.id">
                                <button type="button" @click="form.category_id = d.id"
                                        class="m-press w-full rounded-xl border px-4 py-3 text-sm font-semibold text-start flex items-center gap-3 transition-colors bg-white"
                                        :class="form.category_id === d.id ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-foreground'">
                                    <i class="bi" :class="form.category_id === d.id ? 'bi-check-circle-fill' : 'bi-circle'"></i>
                                    <span x-text="d.name"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- ===== Add a person to a side ===== --}}
                <template x-if="sheet === 'member' && pickFor">
                    <div class="space-y-2">
                        <input type="search" x-model="search" placeholder="{{ __('event-sport_championship::messages.find_person') }}"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
                        <template x-if="!candidates.length">
                            <p class="text-sm text-muted-foreground text-center py-6">{{ __('event-sport_championship::messages.nobody_free') }}</p>
                        </template>
                        <template x-for="p in candidates" :key="p.id">
                            <button type="button" @click="addMember(p)" :disabled="busy"
                                    class="m-press w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-start flex items-center gap-3 hover:bg-muted/60">
                                <i class="bi bi-person text-muted-foreground"></i>
                                <span class="min-w-0 flex-1 break-words" x-text="p.name"></span>
                                <i class="bi bi-plus-circle text-primary"></i>
                            </button>
                        </template>
                    </div>
                </template>
            </div>

            {{-- A sheet that submits has a footer; the picker saves on tap. --}}
            <div x-show="sheet && sheet !== 'member'" class="flex-shrink-0 px-5 pt-3 bg-white border-t border-gray-100"
                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                <button type="button" @click="submitSheet()" :disabled="busy"
                        class="m-press w-full rounded-xl py-3 text-sm font-bold text-white flex items-center justify-center gap-2 disabled:opacity-60" style="background: {{ $color }}">
                    <i class="bi" :class="busy ? 'bi-hourglass-split' : 'bi-check-lg'"></i>{{ __('event-sport_championship::messages.save') }}
                </button>
            </div>
            <div x-show="sheet === 'member'" style="padding-bottom: env(safe-area-inset-bottom);"></div>
        </div>
    </div>
</template>
