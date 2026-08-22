{{--
    Competing for — the club whose name is printed beside this athlete.

    Asked only at events that reach past a single club, and only of someone who
    actually belongs to one: inside a club's own event there is nothing to
    represent. The choice is a CLAIM — no club approves it — so this is a
    picker, never a request. The club's only say comes afterwards, and if it
    uses it the amber note below is how the athlete finds out.

    Expects: $representing (from PersonalEventController::show), $e.
--}}
@if(($representing['ask'] ?? false))
    {{-- Only once they hold a place. BEFORE that the choice belongs in the join
         sheet, at the moment of entering — two pickers for one answer on the
         same screen is how they end up disagreeing. --}}
    <div class="m-card rounded-2xl p-4" x-show="registered" x-cloak>
        <div class="flex items-start gap-3">
            <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                  style="background: {{ $e['color'] }}1a; color: {{ $e['color'] }};">
                <i class="bi bi-flag-fill"></i>
            </span>
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-bold text-foreground">{{ __('personal.event_show_representing_title') }}</h2>
                <p class="text-[11px] text-muted-foreground leading-snug mt-0.5">
                    {{ __('personal.event_show_representing_hint') }}
                </p>
            </div>
        </div>

        {{-- The club took its name off this entry. The place is untouched —
             saying so plainly matters more than hiding it. --}}
        <div x-show="representingDisowned" x-cloak
             class="mt-3 rounded-xl bg-amber-50 border border-amber-200 p-3 flex items-start gap-2">
            <i class="bi bi-exclamation-triangle text-amber-500 mt-0.5"></i>
            <p class="text-[12px] text-amber-800 leading-snug">{{ __('personal.event_show_representing_disowned') }}</p>
        </div>

        <div class="mt-3">
            @include('partials.event-representing-cards')
        </div>
    </div>
@endif
