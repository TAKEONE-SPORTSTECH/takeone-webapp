{{-- ===== The draw, for a visitor and a competitor =====

     Two shapes, and they are two different answers rather than one screen with
     a hole in it:

       · DRAWN — the full-screen board (`x-draw-stage`, both permissions off).
         It covers the app chrome entirely, so the section draws nothing else:
         a lead paragraph and a chip strip sitting behind a fixed stage are
         furniture nobody can reach.
       · NOT DRAWN — the waiting room, or one honest line. Those keep the
         section's own frame, because there is something to read around them.
--}}

@if($e['draw']['published'] ?? false)
    {{-- ===== THE DRAW, FULL SCREEN, READ-ONLY =====

         The same stage the organiser arranges on, with both permissions off —
         which is the state that component was built for (owner's instruction,
         2026-09-17). A visitor and a competitor get the whole viewport for the
         board instead of a 72vh slot under a paragraph, and the same division
         menu, the same zoom and the same gestures the organiser has. Nothing
         that writes is drawn, and neither write address is in the page.

         ⚠️ THROUGH THE PUBLIC DOOR. `$e['draw_url']` is the wall sheet —
         read-only by construction, no entrants bench, no faces, nobody's
         payment state — and it answers a reader who is not signed in at all.
         The member feed the stage defaults to would refuse them.

         `?division=` opens it on ONE group: it is how a division chip on a
         participant card gets somewhere. An id that is not in this draw is
         simply not found and the board opens on its first division, so the
         parameter can be wrong without being dangerous.

         Back goes to the poster, which is where this section was opened from —
         the stage covers the app chrome, so its own bar is the only way out,
         and the label names that DESTINATION rather than this page.

         ⚠️ Nothing but plain attributes inside the tag below. Blade's
         component-tag parser is not the Blade compiler: a directive — or even
         a Blade comment — in an attribute list makes it give up and print the
         whole tag as text on the page, with no error to say so. --}}
    <x-draw-stage
        id="public-draw"
        :event="$e['key']"
        :title="$e['title']"
        :data-url="$e['draw_url']"
        :color="$e['color'] ?? null"
        :division="(int) request('division') ?: null">

        {{-- ===== THE EVENT'S OWN CONTROL ROW, OVER THE BOARD =====

             The band's row — back, the console for whoever runs the event, and
             the ⋯ menu with share and sign out — moves INTO the stage's bar
             (owner's instruction, 2026-09-17). It has to: the stage is
             `fixed inset-0` and covers the app chrome, so the row left in the
             band behind it is a row nobody can reach, and its teleported menu
             was the only part of it still on screen.

             The SAME partial the band renders, with the same inputs. Not a copy
             styled to match — the whole point of partials/event-band-controls
             is that the poster, the four section pages and the member door draw
             one row, and a second version of it here is how the drift it was
             written to end would start again.

             The stage colours its bar when it is given controls, because
             `.ev-ctl` is white-on-translucent and would vanish on the white
             working bar the organiser's stage uses.

             `leading = back` because there IS somewhere to go back to: the
             poster. The stage draws no back arrow of its own while this row is
             present — the row has one, and two in a 56px bar is exactly the
             drift this is avoiding. --}}
        <x-slot:controls>
            @include('events::partials.event-band-controls', [
                'mode' => 'public',
                'leading' => 'back',
                'backHref' => route('events.public', ['event' => $e['key']]),
                'backLabel' => __('personal.event_show_event'),
                /* What the stage's own white bar says in its title block, and
                   for the same reason: this row is over the BOARD, with the
                   band's headline no longer on screen, so it has to carry both
                   what the screen is and which event it belongs to. "Draw",
                   not "Manage draw" — this door is read-only for everybody,
                   whoever is signed in. */
                'rowEyebrow' => __('events::events.public_draw'),
                'rowTitle' => $e['title'],
                'e' => $e,
                'console' => $console ?? null,
                'signedIn' => $signedIn ?? auth()->check(),
                'signedInName' => auth()->user()?->full_name ?? auth()->user()?->name,
                'signOutUrl' => route('events.public.sign-out', ['event' => $e['key']]),
            ])
        </x-slot:controls>
    </x-draw-stage>
@else
    @php $drawDivisions = count($e['divisions']); @endphp

    <div class="px-4 pt-3">
        <p class="text-[13px] text-muted-foreground leading-relaxed">{{ __('events::events.public_draw_empty_body') }}</p>

        <div class="flex flex-wrap gap-1.5 mt-3">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold"
                  style="color: {{ $ev }}; background: {{ $evSoft }};">
                <i class="bi bi-diagram-3 bracket-icon"></i>{{ trans_choice('events::events.public_draw_divisions', $drawDivisions, ['n' => $drawDivisions]) }}
            </span>
        </div>
    </div>

    @if(($waitingRoom['at'] ?? null) || ($waitingRoom['entries'] ?? 0))
        {{-- Not drawn yet — so the page answers the question underneath the one
             that was asked. A clock to the weigh-in, the head count, the spread
             across the weight classes, the flags and the clubs. Who fights who
             is still the one thing withheld, and nothing here says it.

             The same component the member draw page uses
             (<x-draw-waiting-room>), so the two surfaces cannot drift. --}}
        <div class="px-4 pt-4 pb-5">
            <x-draw-waiting-room :data="$waitingRoom" :color="$ev" />
        </div>
    @else
        {{-- Nothing to count and nobody entered: one honest line, as before. --}}
        @include('events::entry.public.partials.empty', [
            'ev' => $ev, 'icon' => 'bi-diagram-3 bracket-icon',
            'title' => __('events::events.public_draw_empty_title'),
        ])
    @endif
@endif
