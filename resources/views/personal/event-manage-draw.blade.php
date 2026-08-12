@extends('layouts.app')

@section('title', __('personal.personal_event_bracket_manage_draw'))

{{--
    Manage draw — the board, full screen.

    One view for both devices: the board component is already responsive, and
    arranging a draw is the same job on a phone as on a desktop. The page covers
    the app chrome entirely (fixed inset-0) so the draw gets the whole viewport —
    on a laptop that is the difference between seeing two rounds and seeing all
    of them.

    The division switcher lives in the top bar rather than inside the component
    (showDivisions=false). That is not only tidier: the board's height is an
    inline style, so keeping a variable-height switcher out of the column is what
    makes `calc(100vh - …)` predictable.

    Read-only viewers never get here — manageBracket() redirects them to the
    ordinary bracket page.
--}}

@section('content')
<div class="fixed inset-0 z-[60] bg-background flex flex-col"
     x-data="{
        divisions: [], division: null, arrange: false, open: false,
        target: @js($division),
        get selected() { return this.divisions.find(d => d.id === this.division) ?? null; },

        // Gender is not a column on a division — it lives in the name, so it is
        // read back out here. This MIRRORS AbstractCombatSport::genderWord(),
        // which writes every division name:
        //
        //     'female' => 'Women', everything else => 'Men'
        //
        // Binary and total, exactly like the producer: a division is always one
        // gender or the other, never mixed. Names are generated (never typed by
        // hand) and genderWord() always emits English, so matching 'Women' is
        // enough — no locale variants to chase. Tested with a word boundary
        // because 'men' is a substring of 'women'.
        genderOf(name) {
            return /\bwomen\b/i.test(name || '') ? 'f' : 'm';
        },
        genderIcon(name) {
            return this.genderOf(name) === 'f' ? 'bi-gender-female' : 'bi-gender-male';
        },
        genderColor(name) {
            return this.genderOf(name) === 'f' ? 'text-pink-500' : 'text-blue-500';
        },

        onLoaded(d) {
            this.divisions = d.divisions; this.division = d.division;
            // Deep-link: open on the division the organiser came from.
            if (this.target && d.divisions.some(x => String(x.id) === String(this.target))) {
                window.BracketBoard.show(this.target);
                this.target = null;
            }
        },
        onState(d) { this.arrange = d.arrange; this.divisions = d.divisions; this.division = d.division; },
     }"
     @bracket:loaded="onLoaded($event.detail)"
     @bracket:state="onState($event.detail)">

    {{-- ===== Top bar: leave, and what you are arranging ===== --}}
    <header class="h-14 shrink-0 flex items-center gap-3 px-3 sm:px-4 border-b border-gray-200 bg-white">
        <a href="{{ route('me.events.bracket', $e['key']) }}"
           class="w-9 h-9 shrink-0 rounded-xl border border-gray-200 flex items-center justify-center
                  text-muted-foreground hover:bg-muted/60 transition-colors"
           title="{{ $e['title'] }}">
            <i class="bi bi-arrow-left rtl:rotate-180"></i>
        </a>

        <div class="min-w-0">
            <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-muted-foreground leading-none">
                {{ __('personal.personal_event_bracket_manage_draw') }}
            </p>
            <h1 class="text-sm font-bold text-foreground truncate leading-tight mt-0.5">{{ $e['title'] }}</h1>
        </div>
    </header>

    {{-- ===== The board — every pixel that is not the top bar =====
         No padding anywhere: the canvas runs to all four edges. `bare` (not
         `bleed`) because this page has no gutters to escape from — bleeding here
         would hang the board off the viewport. Height is the viewport minus the
         3.5rem bar and the 1.5rem legend, which is why both are fixed heights. --}}
    <div class="flex-1 min-h-0 relative">
        <x-tournament-bracket
            id="manage-bracket"
            :data-url="route('me.events.bracket.data', $e['key'])"
            :arrange-url="route('me.events.bracket.arrange', $e['key'])"
            :clear-url="route('me.events.bracket.clear', $e['key'])"
            :event-uuid="$e['key']"
            :can-arrange="true"
            :my-competitor-ids="$myCompetitorIds"
            :show-divisions="false"
            height="calc(100vh - 5rem)"
            bare />

        {{-- Divisions — a menu floating on the canvas, stacked above the zoom
             cluster on the same edge. A menu rather than pills because a
             championship carries dozens of divisions (age × gender × weight),
             and scrolling a strip back and forth to find one is worse than
             opening a list you can read down.

             It sits OUTSIDE the board element (a sibling, not a child) on
             purpose: the board is overflow-hidden, which would clip the open
             menu. It drops to top-16 while arranging so it never sits under the
             component's arrange banner, which spans the full width at top-3.

             `end-3` rather than right-3 so it mirrors to the left in Arabic,
             like the rest of this page — and it keeps the trailing edge clear of
             the component's "Draw final" badge, which pins to the leading one. --}}
        <div x-show="divisions.length > 1" x-cloak
             class="absolute end-3 z-40 transition-all"
             :class="arrange ? 'top-16' : 'top-3'"
             @keydown.escape.window="open = false"
             @click.outside="open = false">

            <button type="button" @click="open = !open"
                    :aria-expanded="open" aria-haspopup="listbox"
                    class="flex items-center gap-2 ps-2.5 pe-2 py-1.5 rounded-xl border border-gray-200 bg-white
                           text-xs font-bold text-foreground shadow-sm hover:shadow-md hover:bg-muted/60
                           transition-all max-w-[16rem]">
                <i class="bi shrink-0" :class="genderIcon(selected?.name) + ' ' + genderColor(selected?.name)"></i>
                <span class="truncate" x-text="selected?.name ?? ''"></span>
                <span class="px-1.5 py-0.5 rounded-full bg-muted text-muted-foreground text-[0.6rem] font-extrabold shrink-0"
                      x-text="selected?.entrants ?? 0"></span>
                <i class="bi bi-chevron-down text-muted-foreground transition-transform shrink-0"
                   :class="open && 'rotate-180'"></i>
            </button>

            <div x-show="open" x-cloak x-transition.opacity.duration.120ms
                 role="listbox"
                 class="absolute end-0 mt-2 w-72 max-h-[60vh] overflow-y-auto rounded-xl border border-gray-200
                        bg-white shadow-xl z-50 py-1">
                <template x-for="d in divisions" :key="d.id">
                    <button type="button" role="option" :aria-selected="d.id === division"
                            @click="window.BracketBoard.show(d.id); open = false"
                            :class="d.id === division ? 'bg-primary/10 text-primary' : 'text-foreground hover:bg-muted/60'"
                            class="w-full flex items-center gap-2.5 px-3 py-2 text-start text-xs font-bold transition-colors">
                        <i class="bi shrink-0" :class="genderIcon(d.name) + ' ' + genderColor(d.name)"></i>
                        <span class="flex-1 truncate" x-text="d.name"></span>
                        <span class="px-1.5 py-0.5 rounded-full bg-muted text-muted-foreground text-[0.6rem] font-extrabold shrink-0"
                              x-text="d.entrants"></span>
                        <i class="bi bi-check-lg shrink-0" x-show="d.id === division"></i>
                    </button>
                </template>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
{{-- The legend used to need an inset here because it sat beneath a board that
     runs flush to the edges. It lives inside the board now and carries its own. --}}
@endpush
