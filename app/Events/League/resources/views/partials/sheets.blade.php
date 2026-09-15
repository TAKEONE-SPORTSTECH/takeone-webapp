{{-- The league's sheets — add a team, cut the season, enter a result.

     One file, included by both breakpoints: a sheet is the same object at any
     width on this platform, and two copies of it would drift the first time one
     of the three gained a field. Teleported to <body> so `fixed` resolves
     against the viewport rather than against the mobile shell's transformed
     wrapper. Expects $color. --}}
    {{-- ═══════════════ SHEETS ═══════════════
         Teleported to <body> so `fixed` resolves against the viewport and not
         against the mobile shell's transformed wrapper. --}}
    <template x-teleport="body">
        <div x-show="sheet" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="sheet = null" x-transition.opacity></div>

            <div class="absolute bottom-0 inset-x-0 bg-background rounded-t-3xl max-h-[92vh] flex flex-col"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">

                {{-- Design Rule #8: every sheet opens with a gradient band. --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi text-xl" :class="sheetIcon"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight" x-text="sheetTitle"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="sheetSub"></p>
                        </div>
                        <button type="button" @click="sheet = null" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    {{-- Add a team --}}
                    <template x-if="sheet === 'team'">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('event-league::messages.team_name') }}</label>
                                <input x-model="form.name" type="text" @keydown.enter="saveTeam()"
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('event-league::messages.team_short') }}</label>
                                <input x-model="form.short_name" type="text" maxlength="12"
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                        </div>
                    </template>

                    {{-- Cut the season --}}
                    <template x-if="sheet === 'season'">
                        <div class="space-y-2">
                            <p class="text-xs font-medium text-gray-700">{{ __('event-league::messages.rounds') }}</p>
                            <template x-for="opt in [
                                {v: 1, label: @js(__('event-league::messages.rounds_one')), hint: @js(__('event-league::messages.rounds_one_hint')), icon: 'bi-1-circle'},
                                {v: 2, label: @js(__('event-league::messages.rounds_two')), hint: @js(__('event-league::messages.rounds_two_hint')), icon: 'bi-arrow-left-right'}
                            ]" :key="opt.v">
                                <button type="button" @click="form.rounds = opt.v"
                                        :class="form.rounds === opt.v ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'"
                                        class="m-press w-full flex items-start gap-3 p-3 rounded-xl border text-start transition-colors">
                                    <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                                          :class="form.rounds === opt.v ? 'bg-primary/15 text-primary' : 'bg-muted text-muted-foreground'">
                                        <i class="bi" :class="opt.icon"></i>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-bold text-foreground" x-text="opt.label"></span>
                                        <span class="block text-[11px] text-muted-foreground mt-0.5 leading-snug" x-text="opt.hint"></span>
                                    </span>
                                    <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0 mt-0.5"
                                          :class="form.rounds === opt.v ? 'border-primary bg-primary text-white' : 'border-gray-300'">
                                        <i class="bi bi-check text-[11px]" x-show="form.rounds === opt.v"></i>
                                    </span>
                                </button>
                            </template>
                            <p class="text-[11px] text-amber-700 bg-amber-50 border border-amber-100 rounded-xl p-2.5"
                               x-show="fixtures.length" x-cloak>
                                {{ __('event-league::messages.regenerate_warning') }}
                            </p>
                        </div>
                    </template>

                    {{-- A result --}}
                    <template x-if="sheet === 'result'">
                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <div class="min-w-0 flex-1 text-end text-[13px] font-bold text-foreground truncate" x-text="form.fixture?.home.name"></div>
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <input x-model="form.home_score" type="number" min="0" max="1000" inputmode="numeric"
                                           class="w-14 px-2 py-2.5 text-center text-lg font-black border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                    <span class="text-muted-foreground font-black">–</span>
                                    <input x-model="form.away_score" type="number" min="0" max="1000" inputmode="numeric"
                                           class="w-14 px-2 py-2.5 text-center text-lg font-black border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                </div>
                                <div class="min-w-0 flex-1 text-[13px] font-bold text-foreground truncate" x-text="form.fixture?.away.name"></div>
                            </div>

                            <button type="button" @click="clearResult()" x-show="form.fixture?.played" x-cloak
                                    class="m-press w-full py-2.5 rounded-xl border border-red-200 text-red-600 text-sm font-semibold">
                                {{ __('event-league::messages.clear_result') }}
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Sticky footer, safe-area padded. --}}
                <div class="flex-shrink-0 border-t border-gray-100 bg-white px-4 pt-3"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="submit()" :disabled="busy"
                            class="m-press w-full py-3 rounded-xl text-white text-sm font-bold disabled:opacity-50"
                            :style="'background: {{ $color }};'">
                        <span x-show="!busy" x-text="sheetCta"></span>
                        <span x-show="busy" x-cloak><i class="bi bi-arrow-repeat animate-spin"></i></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
