{{--
    The hero band's controls — ONE row, for BOTH event doors.

    ── The shape, identical on both ───────────────────────────────────────────
        [back | —]  ·····································  [role]  [⋯ menu]

    Home is NOT in this row. On the poster it sits at the head of the
    classification line, in the band's `lead` slot, where it replaced the dash.

    TWO controls on the trailing edge, never three (2026-09-08, at the user's
    request): the one control that matters for who is reading, and a menu that
    holds the rest with a name against each. Share went into the menu with
    everything else — a header that grows a glyph per feature is how the old
    row reached five unlabelled icons.

    ── What this replaced ─────────────────────────────────────────────────────
    Each door drew its own version of the same row, which left: two
    implementations of the 40px control (Tailwind `bg-white/15` here, inline
    `rgba(255,255,255,.14)` there), two gaps inside one row, `bi-sliders` on one
    door for the job `bi-gear` did on the other, and five glyphs with no names
    anywhere. Now one file, one `.ev-ctl` token (real CSS in
    entry/partials/skin-style, included by BOTH shells), one gap.

    ── Notes that are load-bearing ────────────────────────────────────────────
    · The ROOT is a <span>. The band's control slot is a <span>
      (components/event-poster-band.blade.php), and a <div> inside phrasing
      content is invalid markup.
    · The menu is TELEPORTED and `position: fixed`, positioned from the
      trigger's own rect. The band is `overflow-hidden` — it has to be, for the
      soft circles bleeding off its corner — so a panel left in the flow would
      be clipped by it. Design Rule #6 anticipates this ("z-50 so a dropdown
      paints above the title block"); escaping the clip is the other half.
    · Language is NOT here. It lives on the poster cover, which the Home button
      opens — one place to change it, on the screen that introduces the event.

    ── Two axes, not one ──────────────────────────────────────────────────────
    `$mode` is which DOOR this is — it decides the share verb, where the gear
    goes, and whether sign-out is offered. `$leading` is which control sits on
    the LEADING edge, and it is separate because the two are not the same
    question: the public event's own SECTION pages (draw · officials · gallery ·
    participants) are the public door in every respect and still need Back
    rather than Home, because there is somewhere to go back TO.

    Conflating them is why those four pages hand-rolled the row instead of
    including this one — and the hand-rolled copy used `justify-between`, which
    spreads THREE children across the whole width and leaves the middle one
    stranded in the centre of the band. One spacer after the leading control is
    the whole trick: back on the leading edge, everything else clustered on the
    trailing one.

    ── Inputs ─────────────────────────────────────────────────────────────────
    $mode      'member' | 'public'
    $leading   'back' | 'none'  — Back where there is somewhere to go back to
               (the four section pages, and the member door), nothing on the
               poster, whose Home lives in the band's `lead` slot instead.
    $e         the event view/payload — needs `key`, `title`, `color`
    back:      $backHref, $backLabel
    member:    $canManage|$canOfficiate, $publicUrl, $shareUrl
    public:    $console (resolved by the caller), $signedIn, $signedInName, $signOutUrl
--}}
@php
    $mode = $mode ?? 'member';
    $isPublic = $mode === 'public';

    /* Which control leads this row: 'back', or 'none' at all.

       There is no 'home' any more. The poster's Home moved to the head of the
       classification line on 2026-09-09 (the band's `lead` slot, where it
       replaced the dash), so the one copy of that button lives there and this
       row never draws it. */
    $leading = in_array($leading ?? null, ['back', 'none'], true)
        ? $leading
        : ($isPublic ? 'none' : 'back');

    // Organiser-supplied, and it lands in a style attribute.
    $bandColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($e['color'] ?? '')) ? $e['color'] : '#7c6bf5';

    $hasQr = ! $isPublic;
    $hasPublic = ! $isPublic && ! empty($publicUrl ?? null);
    $hasSignOut = $isPublic && ($signedIn ?? false) && ! empty($signOutUrl ?? null);

    // Precomputed: Blade's component-tag parser is not the Blade compiler, and
    // a concatenation with __() inside an attribute list is printed as text.
    $qrTitle = ($e['title'] ?? '').' — '.__('personal.event_show_event');
@endphp

<span class="ev-ctl-row"
      x-data="{
          menu: false,
          top: 0, right: 0,
          /* Pinned to the trigger rather than to a guessed offset: this row
             sits at a different height on the two bands, and inside a 520px
             column on a wide screen. Clamped so the panel can never hang off
             the trailing edge. */
          place() {
              const r = $refs.menuBtn.getBoundingClientRect();
              this.top = Math.round(r.bottom + 8);
              this.right = Math.round(Math.max(12, window.innerWidth - r.right));
          },
          toggle() {
              if (this.menu) { this.menu = false; return; }
              this.place();
              this.menu = true;
          },
      }"
      @keydown.escape.window="menu = false"
      {{-- A header dropdown that stays put while the page moves underneath it
           reads as detached. Closing is honest and costs nothing. --}}
      @scroll.window="menu = false"
      @resize.window="menu && place()">

    {{-- ===== The leading edge ===== --}}
    @if($leading === 'back')
        <a href="{{ $backHref }}" class="m-press ev-ctl"
           aria-label="{{ $backLabel }}" title="{{ $backLabel }}">
            {{-- `rtl:rotate-180` is not decoration: a chevron that keeps
                 pointing left in Arabic points AWAY from where it goes. The
                 hand-rolled copy on the section pages was missing it. --}}
            <i class="bi bi-chevron-left rtl:rotate-180"></i>
        </a>
    @endif

    {{-- The ONE spacer. It sits after the leading control and nowhere else, so
         the leading edge holds one control and the trailing edge holds the rest
         as a tight cluster. (A `justify-between` row with three children
         spreads all three and strands the middle one mid-band.) --}}
    <span class="ev-ctl-spacer"></span>

    {{-- ===== 1 of 2: the role control =====
         On the public door the gear is shown to EVERYONE on purpose: rendering
         it only for organisers would tell a stranger who the organisers are. It
         lands on the event's own sign-in, or straight on the console for
         whoever already runs it. --}}
    @if($isPublic)
        <a href="{{ $console ?? route('events.public.manage', $e['key']) }}" class="m-press ev-ctl"
           aria-label="{{ __('events.public_manage_title') }}" title="{{ __('events.public_manage_title') }}">
            <i class="bi bi-gear"></i>
        </a>
    @elseif(($canManage ?? false) || ($canOfficiate ?? false))
        <a href="{{ route('me.events.manage', $e['key']) }}" data-shell-link data-route="me.events.manage"
           class="m-press ev-ctl"
           aria-label="{{ __('personal.event_manage_title') }}" title="{{ __('personal.event_manage_title') }}">
            <i class="bi bi-sliders"></i>
        </a>
    @endif

    {{-- ===== 2 of 2: the menu ===== --}}
    <button type="button" x-ref="menuBtn" @click="toggle()"
            class="m-press ev-ctl" :aria-expanded="menu ? 'true' : 'false'"
            aria-label="{{ __('events.band_more') }}" title="{{ __('events.band_more') }}">
        <i class="bi bi-three-dots"></i>
    </button>

    <template x-teleport="body" data-teleport-template="true">
        <div x-show="menu" x-cloak class="fixed inset-0 z-[70]" style="display:none;">
            {{-- A click anywhere else closes it. Transparent rather than tinted:
                 this is a dropdown, not a sheet, and dimming the page for four
                 rows is too much weight. --}}
            <div class="absolute inset-0" @click="menu = false"></div>

            <div x-show="menu"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                 x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                 :style="'top:' + top + 'px; inset-inline-end:' + right + 'px;'"
                 class="ev-menu-panel" role="menu">

                @if($hasQr)
                    {{-- The shared QR component, rendered AS a row rather than
                         re-implemented: it owns the code, the downloads, the
                         copy-link and the printable poster. --}}
                    <div class="ev-menu-slot">
                        <x-qr-code
                            :url="$shareUrl ?? route('me.events.show', ['event' => $e['key']])"
                            :title="$qrTitle"
                            :caption="__('personal.event_show_qr_caption')"
                            :filename="'qr-event-' . $e['key']"
                            :poster-url="route('qr.event', ['event' => $e['key']])"
                            :label="__('events.band_qr')"
                            button-class="ev-menu-row">
                            <x-slot:trigger>
                                <span class="ev-menu-ico" style="background: hsl(250 60% 92%); color: hsl(250 65% 55%);">
                                    <i class="bi bi-qr-code"></i>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="ev-menu-label">{{ __('events.band_qr') }}</span>
                                    <span class="ev-menu-sub">{{ __('events.band_qr_sub') }}</span>
                                </span>
                            </x-slot:trigger>
                        </x-qr-code>
                    </div>
                @endif

                {{-- Share: every reader of an event wants it, so it is the first
                     row a stranger sees. The two doors raise it differently and
                     that is the only thing left that differs between them. --}}
                <button type="button" role="menuitem" class="ev-menu-row"
                        @click="menu = false; {{ $isPublic ? 'share()' : "\$dispatch('share-event')" }}">
                    <span class="ev-menu-ico" style="background: hsl(145 60% 92%); color: hsl(150 60% 32%);">
                        <i class="bi bi-share-fill"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="ev-menu-label">{{ $isPublic ? __('events.public_share') : __('personal.event_show_share') }}</span>
                        <span class="ev-menu-sub">{{ __('events.band_share_sub') }}</span>
                    </span>
                </button>

                @if($hasPublic)
                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener" role="menuitem" class="ev-menu-row">
                        <span class="ev-menu-ico" style="background: hsl(210 100% 94%); color: hsl(211 100% 43%);">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="ev-menu-label">{{ __('events.band_public') }}</span>
                            <span class="ev-menu-sub">{{ __('events.band_public_sub') }}</span>
                        </span>
                    </a>
                @endif

                @if($hasSignOut)
                    <form method="POST" action="{{ $signOutUrl }}" style="margin:0;">
                        @csrf
                        <button type="submit" role="menuitem" class="ev-menu-row">
                            <span class="ev-menu-ico" style="background: hsl(0 86% 97%); color: hsl(0 72% 45%);">
                                <i class="bi bi-box-arrow-right"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="ev-menu-label" style="color: hsl(0 72% 45%);">{{ __('events.band_sign_out') }}</span>
                                <span class="ev-menu-sub">{{ $signedInName ?? '' }}</span>
                            </span>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </template>
</span>
