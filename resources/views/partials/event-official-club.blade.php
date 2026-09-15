{{-- WHOSE referee this is — the club an official came with, or nobody's.

     Included inside the officials panel's Alpine scope, which owns `clubs`,
     `club`, `clubOpen` and `clubChosen()`. It appears on BOTH doors (appointing
     somebody the platform knows, and writing down somebody it does not) because
     the question belongs to the APPOINTMENT, not to the person.

     The panel is ANCHORED (partials/float-panel): fixed, positioned under the
     trigger, so it neither pushes the fields below it down the sheet nor gets
     clipped by the sheet's own scrolling body. <x-select-menu> is still not
     usable here — its panel is plainly absolute, and it could not carry the
     crests anyway.

     `ref` keeps two instances apart: this is included on both doors, inside ONE
     Alpine scope, and two elements sharing an x-ref name means the second one
     silently wins. --}}
@php $ref = $ref ?? 'club'; @endphp
@include('partials.float-panel')
<div>
    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('personal.personal_event_officials_club') }}</label>

    <div @click.outside="clubOpen = false; window.tkFloat(null, $refs['{{ $ref }}Panel'], false)"
         @keydown.escape.stop="clubOpen = false; window.tkFloat(null, $refs['{{ $ref }}Panel'], false)">
        <button type="button" x-ref="{{ $ref }}Trigger"
                @click="clubOpen = ! clubOpen; $nextTick(() => window.tkFloat($refs['{{ $ref }}Trigger'], $refs['{{ $ref }}Panel'], clubOpen))"
                :aria-expanded="clubOpen"
                :class="clubOpen ? 'ring-2 ring-primary border-transparent' : 'border-gray-200'"
                class="w-full flex items-center gap-2.5 px-3 py-2.5 bg-white border rounded-xl text-start transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            {{-- Design Rule #5: a crest is the bare image, never a white tile. --}}
            <template x-if="clubChosen() && clubChosen().logo">
                <span class="w-5 h-5 flex-shrink-0 grid place-items-center">
                    <img :src="clubChosen().logo" alt="" class="w-full h-full object-contain">
                </span>
            </template>
            <template x-if="! clubChosen() || ! clubChosen().logo">
                <i class="bi flex-shrink-0 text-muted-foreground"
                   :class="clubChosen() ? 'bi-buildings' : 'bi-person'"></i>
            </template>

            <span class="text-[13px] font-bold flex-1 truncate"
                  :class="clubChosen() ? 'text-foreground' : 'text-muted-foreground'"
                  x-text="clubChosen() ? clubChosen().name : @js(__('personal.personal_event_officials_club_none'))"></span>

            <i class="bi bi-chevron-down text-xs text-gray-400 transition-transform flex-shrink-0"
               :class="clubOpen && 'rotate-180'"></i>
        </button>

        <div x-show="clubOpen" x-cloak x-ref="{{ $ref }}Panel"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             {{-- z above the sheet (65/70) and below the cropper's editor (80).
                  An inline style, not an arbitrary class: the Tailwind bundle is
                  prebuilt and a class nobody used before has no CSS at all. --}}
             style="z-index:78"
             class="rounded-xl border border-gray-100 bg-white shadow-lg overflow-hidden">

            {{-- Independent, first and default: an official here on their own
                 account is the commonest answer of all. --}}
            <button type="button" @click="club = ''; clubOpen = false"
                    :class="! club ? 'bg-primary/5 text-primary' : 'text-foreground hover:bg-muted/60'"
                    class="w-full flex items-center gap-2.5 px-3 py-2.5 text-start transition-colors">
                <i class="bi bi-person flex-shrink-0 text-muted-foreground"></i>
                <span class="text-[13px] font-bold flex-1">{{ __('personal.personal_event_officials_club_none') }}</span>
                <i class="bi bi-check-lg flex-shrink-0" x-show="! club"></i>
            </button>

            <template x-for="c in clubs" :key="c.slug">
                <button type="button" @click="club = c.slug; clubOpen = false"
                        :class="club === c.slug ? 'bg-primary/5 text-primary' : 'text-foreground hover:bg-muted/60'"
                        class="w-full flex items-center gap-2.5 px-3 py-2.5 text-start transition-colors border-t border-gray-100">
                    <span class="w-5 h-5 flex-shrink-0 grid place-items-center">
                        <template x-if="c.logo"><img :src="c.logo" alt="" class="w-full h-full object-contain"></template>
                        <template x-if="! c.logo"><i class="bi bi-buildings text-muted-foreground"></i></template>
                    </span>
                    <span class="text-[13px] font-bold flex-1 truncate" x-text="c.name"></span>
                    <i class="bi bi-check-lg flex-shrink-0" x-show="club === c.slug"></i>
                </button>
            </template>
        </div>
    </div>

    {{-- One line. A hint under a field explains it at a glance; wrapped onto
         three it becomes a paragraph the reader has to get past. The full
         sentence stays in `title` for a pointer and a screen reader. --}}
    <p class="text-[10.5px] text-muted-foreground mt-1.5 truncate"
       title="{{ __('personal.personal_event_officials_club_hint') }}">{{ __('personal.personal_event_officials_club_hint') }}</p>
</div>
