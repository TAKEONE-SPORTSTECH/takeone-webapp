{{--
    The clubs this member may compete for, as selection cards.

    Just the choice — no heading, no card around it — so the same control can sit
    in the join sheet (where the choice is MADE, at the moment of entering) and
    on the page afterwards (where it is changed). Selection cards rather than a
    dropdown: the list is short and known, and an absolutely-positioned panel
    inside a scrolling sheet would be clipped by it.

    Reads `representing` / `setRepresenting()` from the event-show Alpine scope.

    Expects: $representing (from PersonalEventController::show).
--}}
<div class="space-y-2">
    @foreach($representing['clubs'] as $club)
        <button type="button" @click="setRepresenting({{ (int) $club['id'] }})"
                :class="representing === {{ (int) $club['id'] }} ? 'border-primary bg-primary/5' : 'border-gray-200'"
                class="m-press w-full rounded-xl border-2 p-3 flex items-center gap-3 text-start transition-colors">
            <span class="w-8 h-8 rounded-lg bg-muted grid place-items-center flex-shrink-0">
                <i class="bi bi-building text-muted-foreground text-sm"></i>
            </span>
            <span class="min-w-0 flex-1 text-[13px] font-bold text-foreground truncate">{{ $club['name'] }}</span>
            <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
                  :class="representing === {{ (int) $club['id'] }} ? 'border-primary bg-primary text-white' : 'border-gray-300'">
                <i class="bi bi-check text-[11px]" x-show="representing === {{ (int) $club['id'] }}" x-cloak></i>
            </span>
        </button>
    @endforeach

    {{-- Competing for nobody is a real answer, not a missing one. --}}
    <button type="button" @click="setRepresenting(null)"
            :class="representing === null ? 'border-primary bg-primary/5' : 'border-gray-200'"
            class="m-press w-full rounded-xl border-2 p-3 flex items-center gap-3 text-start transition-colors">
        <span class="w-8 h-8 rounded-lg bg-muted grid place-items-center flex-shrink-0">
            <i class="bi bi-person text-muted-foreground text-sm"></i>
        </span>
        <span class="min-w-0 flex-1 text-[13px] font-bold text-foreground truncate">{{ __('events.claim_unattached') }}</span>
        <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
              :class="representing === null ? 'border-primary bg-primary text-white' : 'border-gray-300'">
            <i class="bi bi-check text-[11px]" x-show="representing === null" x-cloak></i>
        </span>
    </button>
</div>
