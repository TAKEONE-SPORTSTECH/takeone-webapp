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
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-black text-foreground flex items-center gap-2"><i class="bi bi-trophy-fill text-amber-500"></i> {{ __('personal.event_show_winners_results') }}</h3>
                    <button type="button" @click="resultsOpen=false" class="m-press w-8 h-8 rounded-full bg-muted grid place-items-center"><i class="bi bi-x-lg text-xs"></i></button>
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
