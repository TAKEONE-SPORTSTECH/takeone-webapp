{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'layouts.app')

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

@section($contentSection ?? 'content')
@php
    // The event's own colour, whitelisted before it reaches a style attribute —
    // it is organiser-supplied and the sheet bands interpolate it directly.
    // Same expression the two event-manage consoles use, and the same fallback.
    $mgColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($e['color'] ?? '')) ? $e['color'] : '#7c3aed';
@endphp
<div class="ev-app-fixed fixed inset-0 z-[60] bg-background flex flex-col"
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
        {{-- ⚠️ Back goes where you CAME FROM: the console. This screen is
             linked from exactly one place — the console's draw tile — and the
             arrow used to open `me.events.bracket`, the read-only board, which
             is neither where you were nor what you were doing.

             ICON ONLY here, and a tail-less chevron (asked for on 2026-09-04).
             Design Rule #6's labelled back pill governs the HERO BAND that
             opens a page; this is a 56px working bar on a full-screen board,
             where the words were competing with the event title beside them.
             The destination still says itself through `title` / `aria-label`.

             Inside the sealed event app the console is /e/{uuid}/admin/manage;
             on the platform it is the member console. --}}
        @php
            $drawBack = isset($shell)
                ? url('/e/'.$e['key'].'/admin/manage')
                : route('testcode.me.events.manage', $e['key']);
        @endphp
        <a href="{{ $drawBack }}"
           class="ev-ico shrink-0 w-9 h-9 rounded-full border border-gray-200 flex items-center justify-center
                  text-foreground no-underline hover:bg-muted/60 transition-colors"
           title="{{ __('personal.event_manage_title') }}"
           aria-label="{{ __('personal.event_manage_title') }}">
            <i class="bi bi-chevron-left text-base"></i>
        </a>

        <div class="min-w-0">
            <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-muted-foreground leading-none">
                {{ __('personal.personal_event_bracket_manage_draw') }}
            </p>
            <h1 class="text-sm font-bold text-foreground truncate leading-tight mt-0.5">{{ $e['title'] }}</h1>
        </div>

        <div class="ms-auto shrink-0 flex items-center gap-2">
            {{-- ===== One control for the whole bar =====

                 The draw's own verbs (build it, clear it — moved off the
                 console on 2026-09-03) and the group tools used to be a menu
                 AND three labelled buttons sitting side by side, which is four
                 controls and five words in a 56px bar (reported 2026-09-04).

                 They are now ONE three-dot menu: <x-eventlab::division-groups> renders it
                 in `as-menu` mode and this page hands its verbs down through
                 the `actions` slot. The component still owns both sheets and
                 every request, and still learns which division is open from the
                 board's bracket:loaded / bracket:state events.

                 The package decides whether each verb may run today
                 (availableActions); this is only where they are offered. --}}
            <x-eventlab::division-groups :event="$e['key']" :can-manage="$canManage"
                               :color="$mgColor" :as-menu="true">
                @if(! empty($drawActions))
                    <x-slot:actions>
                        @foreach($drawActions as $action)
                            <form method="POST" action="{{ route('testcode.me.events.action', [$e['key'], $action['action']]) }}">
                                @csrf
                                <button type="submit" role="menuitem"
                                        class="w-full text-start px-3 py-2.5 flex items-center gap-2.5 text-sm hover:bg-muted/60 transition-colors">
                                    <span class="w-8 h-8 rounded-lg grid place-items-center flex-shrink-0 bg-primary/10 text-primary">
                                        <i class="{{ \App\Support\Icon::bi($action['icon'] ?? null, 'text-sm', 'bi-lightning-charge') }}"></i>
                                    </span>
                                    <span class="font-bold text-foreground">{{ $action['label'] }}</span>
                                </button>
                            </form>
                        @endforeach
                    </x-slot:actions>
                @endif
            </x-eventlab::division-groups>
        </div>
    </header>

    {{-- ===== The board — every pixel that is not the top bar =====
         No padding anywhere: the canvas runs to all four edges. `bare` (not
         `bleed`) because this page has no gutters to escape from — bleeding here
         would hang the board off the viewport. Height is the viewport minus the
         3.5rem bar and the 1.5rem legend, which is why both are fixed heights. --}}
    <div class="flex-1 min-h-0 relative">
        <x-eventlab::tournament-bracket
            id="manage-bracket"
            :data-url="route('testcode.me.events.bracket.data', $e['key'])"
            :arrange-url="route('testcode.me.events.bracket.arrange', $e['key'])"
            :clear-url="route('testcode.me.events.bracket.clear', $e['key'])"
            :event-uuid="$e['key']"
            :can-arrange="$canArrange"
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
