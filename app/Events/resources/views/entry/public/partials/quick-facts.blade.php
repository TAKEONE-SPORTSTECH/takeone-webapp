{{--
    The quick-facts card — when, how much, where — riding up over the band.

    Moved VERBATIM out of entry/public/mobile on 2026-09-28 so the federation's
    club invitation draws the same card rather than a copy of it (CLAUDE.md →
    Shared Stays Shared). Expects `$e` and `$ev` (the guarded event colour), and
    a `jump(id)` on the enclosing Alpine scope.
--}}
    {{-- ===== The three facts, riding up over the band =====
         The design file's card, kept verbatim: a white panel with a hairline of
         the event's colour along its top edge, three columns divided by rules,
         and — when the organiser set a capacity — how full it is. No icon
         tiles: the design puts the bare glyph in the event's colour, which
         keeps the row about the VALUES.

         Each fact is still a door to its fuller answer further down: when → the
         run-of-show, how much → the way in, where → the map. --}}
    <div class="relative" style="padding:0 18px; margin-top:-46px;">
        {{-- ⚠️ NO `m-in` here — removed 2026-09-08, the same reason
             `mobile-stagger` came off entry/layout on the same day.

             `m-in` starts the card at opacity 0 and rises it in over half a
             second, so the band and everything under it paint first and this
             card arrives separately a beat later. On a first arrival that runs
             behind the cover and nobody sees it; on the RELOAD that follows
             choosing a language on the cover it plays in full view, and the
             page reads as loading a second time. Reported exactly that way.

             The member page's copy of this card has never animated. The card
             simply arrives; the entrance the visitor sees is the cover lifting. --}}
        <div style="background:#fff; border-radius:18px; border-top:3px solid {{ $ev }};
                    box-shadow:0 22px 60px rgba(30,44,79,.13), 0 2px 6px rgba(30,44,79,.06);
                    padding:18px 16px 16px;">

            <div class="grid text-center" style="grid-template-columns:1fr 1fr 1fr;">
                {{-- ⚠️ The two dividing rules belong to the MIDDLE column, on
                     both of its sides — never one physical `border-right` per
                     column.

                     `border-right` is a PHYSICAL side, and this page is read in
                     Arabic as often as in English. In RTL the columns flow
                     right-to-left, so a right border on the first column landed
                     on the card's outer edge, the second column's landed in the
                     right-hand gap, and the left-hand gap had no rule at all —
                     three columns with the separators in two wrong places.
                     Hanging both rules off the middle cell is direction-proof:
                     the middle column is the middle column either way, and its
                     two sides ARE the two internal gaps. (The member card next
                     door has always done it this way with `border-x`.) --}}
                <button type="button" @click="jump(['run-start','how-it-runs'])"
                        class="m-press" style="padding:2px 6px;"
                        aria-label="{{ __('personal.event_show_how_it_runs') }}">
                    <i class="bi bi-calendar3" style="font-size:17px; color:{{ $ev }};"></i>
                    <p style="margin:7px 0 0; font-size:12.5px; font-weight:700; color:#1e2c4f;">{{ $e['wday'] }} {{ $e['day'] }} {{ $e['mon'] }}</p>
                    <p style="margin:2px 0 0; font-size:10.5px; color:#6b7689;">{{ $e['time'] }}</p>
                </button>

                <button type="button" @click="jump(['fees','enter'])"
                        class="m-press"
                        style="border-left:1px solid #eef1f6; border-right:1px solid #eef1f6; padding:2px 6px;"
                        aria-label="{{ __('personal.event_show_to_join') }}">
                    <i class="bi bi-cash-coin" style="font-size:17px; color:{{ $ev }};"></i>
                    <p style="margin:7px 0 0; font-size:12.5px; font-weight:700; color:#1e2c4f;">{{ $e['participant_fee'] }}</p>
                    <p style="margin:2px 0 0; font-size:10.5px; color:#6b7689;">{{ __('personal.event_show_to_join') }}</p>
                </button>

                <button type="button" @click="jump('where')"
                        class="m-press min-w-0" style="padding:2px 6px;"
                        aria-label="{{ __('personal.event_show_location') }}">
                    <i class="bi bi-geo-alt" style="font-size:17px; color:{{ $ev }};"></i>
                    <p class="truncate" style="margin:7px 0 0; font-size:12.5px; font-weight:700; color:#1e2c4f;" title="{{ $e['location'] }}">{{ $e['location'] }}</p>
                    <p style="margin:2px 0 0; font-size:10.5px; color:#6b7689;">{{ __('personal.event_show_venue') }}</p>
                </button>
            </div>

            @if($e['capped'] ?? false)
                <div style="margin-top:16px;">
                    <div class="flex justify-between" style="font-size:11px; margin-bottom:6px;">
                        <span style="font-weight:600; color:#1e2c4f;">{{ $e['going'] }} {{ __('personal.event_show_going') }}</span>
                        <span style="color:#6b7689;">{{ max(0, $e['cap'] - $e['going']) }} {{ __('personal.event_show_spots_left') }}</span>
                    </div>
                    <div style="height:6px; border-radius:999px; background:#eef1f6; overflow:hidden;">
                        <div class="m-bar-fill" style="height:100%; border-radius:999px; background:{{ $ev }}; width:{{ min(100, round($e['going'] / max(1, $e['cap']) * 100)) }}%;"></div>
                    </div>
                </div>
            @endif
        </div>
    </div>
