{{--
    Set-winners sheet — the organiser's, extracted from the event page so the
    console can own it and the public page can stop carrying it.

    Reads and writes the shared event Alpine root (partials/event-show-script):
    `resultsOpen`, `winners`, `addWinner()`, `removeWinner()`, `saveResults()`.
    Include it inside an element that carries that x-data.
--}}
    {{-- ===== Set-winners modal (managers) — teleported to body so the fixed
             overlay anchors to the viewport, not the transformed shell content. --}}
    @if($canManage ?? false)
        <template x-teleport="body">
        <div x-show="resultsOpen" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
            <div class="absolute inset-0 bg-black/40" @click="resultsOpen=false" x-transition.opacity></div>
            <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-3xl max-h-[85vh] flex flex-col"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #b45309, #d97706b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-trophy-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('personal.event_show_winners_results') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $e['title'] }}</p>
                        </div>
                        <button type="button" @click="resultsOpen=false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- list of participant names for autocomplete --}}
                <datalist id="event-participants">
                    @foreach($e['participants'] as $pp)
                        <option value="{{ $pp['name'] }}"></option>
                    @endforeach
                </datalist>

                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    <p class="text-xs text-muted-foreground">{{ __('personal.event_show_add_podium_help') }}</p>
                    <template x-for="(w, i) in winners" :key="i">
                        <div class="rounded-2xl border border-gray-100 p-3">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full text-white" :style="`background:${medal(w.place)}`"
                                      x-text="w.place===1 ? '{{ __("personal.event_show_place_1st") }}' : (w.place===2 ? '{{ __("personal.event_show_place_2nd") }}' : (w.place===3 ? '{{ __("personal.event_show_place_3rd") }}' : '#' + w.place))"></span>
                                <button type="button" @click="removeWinner(i)" class="m-press text-[11px] text-red-500 font-semibold"><i class="bi bi-trash"></i> {{ __('personal.event_show_remove_btn') }}</button>
                            </div>
                            <input type="text" list="event-participants" x-model="w.name" placeholder="{{ __('personal.event_show_ph_winner_name') }}"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm mb-2 focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            <div class="flex items-center gap-2">
                                <input type="number" min="1" x-model="w.place" placeholder="{{ __('personal.event_show_ph_place') }}"
                                       class="w-20 px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                <input type="text" x-model="w.prize" placeholder="{{ __('personal.event_show_ph_prize') }}"
                                       class="flex-1 px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            </div>
                        </div>
                    </template>
                    <button type="button" @click="addWinner()" class="m-press w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground">
                        <i class="bi bi-plus-lg"></i> {{ __('personal.event_show_add_place') }}
                    </button>
                </div>

                <div class="p-4 border-t border-gray-100">
                    <button type="button" @click="saveResults()" :disabled="busy"
                            class="m-press w-full py-3.5 rounded-2xl text-white font-bold text-sm flex items-center justify-center gap-2 disabled:opacity-60" style="background: {{ $e['color'] }};">
                        <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-check2-circle'"></i>
                        <span x-text="busy ? '{{ __("personal.event_show_saving") }}' : '{{ __("personal.event_show_save_winners") }}'"></span>
                    </button>
                </div>
            </div>
        </div>
        </template>
    @endif
