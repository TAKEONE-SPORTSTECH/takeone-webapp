@props([
    /* Who. Only `name` is required — everything about a person may be blank
       (CLAUDE.md, "Who Fills The Form Decides"), and every line below simply
       does not render when its value is missing. */
    'name',
    'photo' => null,
    'gender' => null,
    'country' => null,          // ISO-2; drawn as a flag
    'age' => null,
    'divisions' => [],          // strings: a category, a weight class, a belt
    /* WHICH ACTIVITY of the event they are in — "Gi", "No-Gi", "Gi + No-Gi".
       One event may run several, each drawn and paid for separately, and an
       athlete may enter more than one; this is the scannable version of that,
       drawn as a filled chip beside the name. Built by
       App\Events\Support\ActivityTag from the organiser's own division names,
       so it is their word in their language. Null for an event with one
       activity — then the chip is simply absent, which is every card that
       existed before this. */
    'activity' => null,
    /* ── What they ENTERED, one badge each ────────────────────────────────
       `[['label' => 'Gi', 'paid' => true, 'verified' => true], …]`.

       An event may run several activities and each is entered, drawn and PAID
       FOR separately, so "what did this person pay for" is a question per
       activity and not per person. Given this list, the card draws a badge for
       each with its own money state; given nothing, it falls back to the single
       `activity` chip and every existing caller is unchanged. */
    'activities' => [],
    /* How wide every activity badge should be, in characters of its longest
       label. Given by a LIST so the badges line up card to card — a column of
       tags that each hug their own word reads as noise going down a roster.
       Null falls back to this card's own longest, which is right for a card
       standing on its own. */
    'activityWidth' => null,
    /* The scale reading, when the caller is allowed to show one, plus where it
       came from: 'self' (the athlete declared it — amber) or 'official' (a
       weigh-in official signed for it — green). Printed bold beside the
       divisions, so the weight line on a card is never blank when a weight
       exists (asked for on 2026-09-04). Both are optional and the line simply
       does not render without them. */
    'weight' => null,
    'weightTone' => 'self',
    /* The RANK, drawn as a chip on the club line — see the belt block below.
       `belt` is a colour from App\Sports\Combat\BeltRank::ladder() ('white',
       'blue', 'black', …); `beltGrade` is the free-text degree an organiser
       typed ('2', '2nd', '3rd degree'), from which the number is read. Both
       optional: no belt simply means no chip, so a caller that has not been
       taught about this is unchanged. */
    'belt' => null,
    'beltGrade' => null,
    'clubName' => null,
    'clubLogo' => null,
    'unattached' => null,       // what to say when they compete for nobody

    /* Is this card a door, and does it say so? */
    'chevron' => false,

    /* ===== Behaviour, as props rather than as attributes on the tag =====

       ⚠️ Blade's component-tag compiler in this version does NOT support
       spreading an attribute bag — `<x-entrant-card {{ $attrs }} />` is left
       as literal text on the page, with no error. (Discovered the hard way
       while sharing this card; a directive inside the tag does the same.) So
       the two shapes a caller needs are named props and composed here:

         · `href` — the card is a link.
         · `pick` — an Alpine expression the card runs when tapped; it becomes a
                    keyboard-operable role=button rather than a <button>, so the
                    shape is identical to a plain card (a <button> brings its own
                    intrinsic sizing, which is what made these two cards differ
                    in the first place).
         · `show` — an Alpine expression for x-show, for a filtered list.

       Anything else a caller wants can still ride on the tag as an ordinary
       attribute (`id`, `::class`, `@click.capture="{{ $expr }}"`) — literal
       attributes and echoed VALUES both compile fine. */
    'href' => null,
    'pick' => null,
    'show' => null,

    /* An Alpine expression for when the chevron should be visible. A card that
       is a door while reading and a checkbox while selecting shows one or the
       other, never both. */
    'chevronShow' => null,
])

{{--
    ===== The entrant card =====

    ONE card for a person entered in an event, wherever that list is read: the
    public participants page a stranger opens from a shared link, and the
    organiser's own "who's joined" screen inside the event. They used to be two
    different cards — a tall portrait card on the public page and a squat
    48px-square one for the organiser — and the organiser's was the worse of the
    two while being the one used to actually run the competition.

    So the shape lives HERE and nowhere else. A caller supplies the person and,
    through the slots, whatever it needs to DO with them; it cannot change how
    the card looks, which is the point (asked for on 2026-09-03: "please unify
    the cards … exactly make them the same").

    The root element is the caller's: `<x-entrant-card>` renders a `<div>`, and
    a caller that needs a link passes `href` (Blade renders the root as an `<a>`
    via the `element` prop below). Every behaviour attribute — `x-show`, a click
    handler, an id, a `:class` ring — goes on the root through the attribute bag,
    so no behaviour needs a shape of its own.

    ⚠️ PORTRAIT 3:4, never square (CLAUDE.md → Profile Pictures Are Portrait
    3:4). The width is STATED at 66px and the height comes from the row
    (`self-stretch`): flex resolves width before the stretched height, so an
    `aspect-ratio` would have nothing to derive from and the box would collapse
    to zero — the pictures would simply vanish. 66px is 3:4 of the ~88px the
    card settles at.

    Slots:
      · `leading`  — before the portrait (the organiser's selection checkbox).
      · `picture`  — replaces the portrait's contents, for a picture that can
                     change without a reload.
      · `portrait` — inside the portrait box, on top of the picture (the
                     organiser's add/remove-photo controls).
      · `leading`  — before the portrait, on the LEADING edge (left in English,
                     right in Arabic).
      · `trailing` — after the text, on the TRAILING edge (right in English,
                     left in Arabic) — where the selection checkbox goes, next
                     to the chevron it replaces.
--}}

@php
    $isMale = ($gender ?? 'Male') === 'Male';

    /* The age group, in words. Same ladder everywhere it is shown. */
    $ageGroup = $age === null ? __('platform.age_adult') : match (true) {
        $age < 2 => __('platform.age_infant'),
        $age < 4 => __('platform.age_toddler'),
        $age < 6 => __('platform.age_preschooler'),
        $age < 13 => __('platform.age_child'),
        $age < 20 => __('platform.age_teenager'),
        $age < 40 => __('platform.age_young_adult'),
        $age < 60 => __('platform.age_adult'),
        default => __('platform.age_senior'),
    };

    /* ⚠️ The flag is a FILE, not an emoji.
       It was built from the ISO-2 as a regional-indicator pair, which draws a
       flag on iOS, Android and macOS and the bare LETTERS "BH" on Windows —
       which has no flag glyphs in its emoji font and is what most scoring
       tables run (reported 2026-09-12). `<x-flag>` serves a vendored SVG that
       renders identically everywhere. */
    $flagCode = trim((string) $country);

    $divisions = array_values(array_filter((array) $divisions));
@endphp

@php
    $element = $href ? 'a' : 'div';
    /* `ps-1.5` reserves exactly the belt rail's width. It was `ps-1` (4px) from
       when the rail was a 4px gender stripe; the belt rail is 6px, so 2px of it
       sat UNDER the portrait — and in Arabic, where the leading edge is the
       right and the photo starts there, almost all of it did. The rank was
       being drawn correctly and was simply invisible. */
    $shell = 'm-card relative bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex items-stretch gap-3 ps-1.5 pe-3';
    $shell .= $href ? ' m-press no-underline' : ($pick ? ' m-press cursor-pointer text-start' : '');
@endphp

<{{ $element }} {{ $attributes->merge(['class' => $shell]) }}
    @if($href) href="{{ $href }}" @endif
    @if($pick)
        role="button" tabindex="0"
        @click="{{ $pick }}"
        @keydown.enter.prevent="{{ $pick }}"
        @keydown.space.prevent="{{ $pick }}"
    @endif
    @if($show) x-show="{{ $show }}" x-cloak @endif>

    @php
        /* ===== The card's edge is the GENDER, the belt is a chip =====

           Asked for on 2026-09-06: down a list of thirty the rail is read as
           "who is this", so it carries the one fact that splits a draw in two —
           blue for the men's side, pink for the women's. It is the same pairing
           <x-fields.gender-toggle> already uses, so the colours mean the same thing
           here as they do on the form that set them.

           The rail was the BELT until now, and the rank is not being dropped to
           make room: it moves into the meta row as a chip, which is where the
           card's other facts already live. It reads as a word rather than a
           band — slower to scan, but it survives, and a rank stated in text is
           the one shape that never needs to be taught.

           ⚠️ `$isMale` treats an UNKNOWN gender as male, and that is load-
           bearing rather than sloppy: most entrants are put in by staff who are
           asked for a name and nothing else (CLAUDE.md, "Who Fills The Form
           Decides"), so a blank gender is the normal case. The rail says which
           side of the draw the organiser has them on, and an unpainted rail on
           two thirds of a list would say nothing at all. */
        $railColour = $isMale ? '#3b82f6' : '#ec4899';
        $railTitle = $isMale ? __('clubs::club.gender_male') : __('clubs::club.gender_female');

    @endphp

    {{-- `start-0` rather than `left-0`: these pages are read in Arabic too. --}}
    <span class="absolute start-0 top-0 bottom-0 w-1.5"
          style="background: {{ $railColour }};"
          title="{{ $railTitle }}" aria-label="{{ $railTitle }}" role="img"></span>

    {{ $leading ?? '' }}

    {{-- The portrait. See the note above on why the width is stated and the
         image is absolutely positioned. --}}
    <span class="relative shrink-0 self-stretch w-[66px] overflow-hidden">
        @if(isset($picture))
            {{-- A caller whose picture can CHANGE without a reload (the
                 organiser can put a face on an entry that has none) supplies it
                 itself. The box is still this component's, so the shape cannot
                 drift. --}}
            {{ $picture }}
        @else
            {{-- The shared portrait placeholder on a tile tinted to match this
                 card's rail — the same artwork as every other avatar fallback
                 on the platform, and never an initial-letter crest: invented
                 detail about a person reads as fact.

                 It is drawn UNDERNEATH the photograph rather than instead of
                 it, so a picture that does not load falls back to a face
                 instead of the browser's broken-image glyph. Having a URL and
                 having a readable file are two different things here: /file/…
                 answers 404 both when the bytes are gone and when this viewer
                 may not see them (App\Support\FileAccess), and neither is
                 something to show a reader a torn icon about. --}}
            <x-gender-avatar :gender="$gender"
                             :bg="$isMale ? 'hsl(250 55% 60%)' : '#ec4899'"
                             sizes="66px"
                             class="absolute inset-0 w-full h-full" />

            @if($photo)
                {{-- ── Small, and only when it is on screen ─────────────────
                     A desk photograph is whatever the phone produced — 1836×3264
                     and half a megabyte is ordinary — and this box is 42×56.
                     Sixty of them made the people page pull about 5.6 MB of
                     camera originals to paint thumbnails (reported 2026-09-12).

                     `thumb_url()` asks the one file door for a rendition; it
                     falls back to the original whenever one cannot be drawn, so
                     there is no missing-thumbnail case to handle here.

                     `loading="lazy"` because a roster is a long list and the
                     reader sees eight cards: the rest fetch as they scroll.
                     `decoding="async"` so decoding a face never blocks the
                     paint of the row it sits in. --}}
                <img data-ec="photo" src="{{ thumb_url($photo, 'card') }}" alt=""
                     loading="lazy" decoding="async" onerror="this.remove()"
                     class="absolute inset-0 w-full h-full object-cover">
            @endif
        @endif

        {{ $portrait ?? '' }}
    </span>

    {{-- Info. Its `py-3` is the card's vertical padding, and what gives the
         portrait its height. --}}
    <span class="flex-1 min-w-0 py-3 self-center">
        <span class="flex items-center gap-1.5">
            {{-- `data-ec` marks the facts that can CHANGE while the page is
                 open, so a card can be patched in place after an edit instead
                 of the reader being asked to reload (No Page Reload Rule).
                 Attributes only — nothing here renders differently. --}}
            <span data-ec="name" class="font-bold text-foreground truncate text-[15px]">{{ $name }}</span>
            @if($flagCode !== '')<x-flag :code="$flagCode" :h="13" data-ec="flag" />@endif

            {{-- The activity, beside the name rather than down in the meta row:
                 on a list where half the field is in one and half in the other,
                 it is read WITH the person, not with their weight class. Filled
                 rather than tinted so it never reads as one more division
                 chip — those say where they fight, this says what in. --}}
        </span>

        <span class="flex items-center gap-1.5 mt-1 flex-wrap">
            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $isMale ? 'bg-accent text-primary' : 'bg-pink-50 text-pink-600' }}">{{ $ageGroup }}</span>
            {{-- The age reads in the CARD'S GENDER COLOUR and bold, as one
                 unit with the icon beside it. In muted grey at 11px it was the
                 quietest thing in a row of bold facts and went unread (reported
                 2026-09-12) — which matters on a roster where the age is what
                 decides the division somebody belongs in.

                 An UNKNOWN age stays muted and light: an em dash shouted in
                 pink would draw the eye to the one thing there is nothing to
                 say about. --}}
            <span class="text-[11px] flex items-center gap-1 {{ $age !== null ? ($isMale ? 'text-primary font-bold' : 'text-pink-500 font-bold') : 'text-muted-foreground' }}"
                  data-ec="age-wrap">
                <i class="bi {{ $isMale ? 'bi-gender-male' : 'bi-gender-female' }}"></i><span data-ec="age">{{ $age !== null ? $age.' '.__('platform.years_short') : '—' }}</span>
            </span>
            {{-- The division reads BOLD (asked for on 2026-09-04, and again once
                 it was seen on the page): on a card carrying five facts it is the
                 one an organiser scans for. It was already `font-bold`, but in the
                 muted grey the rest of the meta row uses — and bold grey at 11px
                 does not read as bold next to it. The weight is what it now takes
                 its emphasis from, so it carries the foreground ink as well. --}}
            @foreach($divisions as $division)
                <span class="text-[11px] font-bold text-foreground flex items-center gap-1">
                    <i class="bi bi-rulers {{ $isMale ? 'text-primary' : 'text-pink-500' }}"></i>{{ $division }}
                </span>
            @endforeach

            {{-- The scale reading. Amber while it is only the athlete's own
                 word, green once an official has signed for it — the same two
                 colours as the weigh-in badge above, so the card says one
                 thing twice rather than two things. --}}
            {{-- Always rendered, hidden when there is no reading yet: a weight
                 arrives at the weigh-in desk while this page is open, and a
                 span that does not exist cannot be filled in. `hidden` renders
                 exactly as the old `@if` did — nothing, taking no space. --}}
            <span data-ec="weight" @if($weight === null || $weight === '') hidden @endif
                  class="text-[11px] font-bold flex items-center gap-1 {{ $weightTone === 'official' ? 'text-green-600' : 'text-amber-500' }}">
                <i class="bi bi-speedometer2"></i><span data-ec="weight-value">{{ $weight }}</span> {{ __('personal.division_kg') }}
            </span>
        </span>

        <span class="flex items-center gap-1.5 mt-1 text-[12px] text-muted-foreground truncate">
            {{-- The rank, in the place the rail used to say it. `flex-shrink-0`
                 because the club name beside it truncates and the belt is the
                 shorter, harder fact — a half-written rank is worse than a
                 half-written club. --}}
            <span data-ec="belt" class="flex-shrink-0 inline-flex">
                <x-belt-chip :belt="$belt" :grade="$beltGrade" class="flex-shrink-0" />
            </span>
            @if($clubLogo)
                {{-- Design Rule #5: the bare mark, never a white tile behind it. --}}
                <span class="w-4 h-4 flex-shrink-0">
                    <img src="{{ $clubLogo }}" alt="" class="w-full h-full object-contain">
                </span>
            @else
                <i class="bi bi-buildings {{ $isMale ? 'text-primary/70' : 'text-pink-500/70' }}"></i>
            @endif
            <span data-ec="club" class="truncate">{{ $clubName ?: ($unattached ?? __('events::events.public_unattached')) }}</span>

            @php
                $acts = array_values(array_filter((array) $activities, fn ($a) => ! empty($a['label'])));

                /* Every badge on a card is the SAME WIDTH — sized to the
                   longest word among them, so "Gi" and "Gi + No-Gi" sit as two
                   equal tags rather than reading as a big thing and a small
                   one. In `ch`, which is the unit that actually tracks text,
                   plus the padding and the icon; and inline, because the
                   Tailwind bundle is prebuilt and an arbitrary `min-w-[…]`
                   would ship with no rule at all. */
                $aWidest = (int) $activityWidth;

                if ($aWidest <= 0) {
                    foreach ($acts as $a) {
                        $aWidest = max($aWidest, mb_strlen((string) $a['label']));
                    }
                }

                $aBox = $aWidest ? 'min-width: calc('.$aWidest.'ch + 2.1rem);' : '';
            @endphp

            @if($acts)
                {{-- One badge per thing they signed up for, on the CARD'S LAST
                     ROW beside the club — where the facts about the entry live,
                     rather than competing with the name for the top line
                     (moved 2026-09-12).

                     BLUE, one colour for all of them: these say WHAT somebody
                     entered, and the card already has two places that say
                     whether it is settled — the readiness badges straddling the
                     top edge, and the fee sheet itself. Colouring these by
                     money as well made three payment signals on one card, and a
                     badge that turns dark for "unpaid" reads as a different
                     activity rather than the same one unpaid.

                     The money is still HERE, said the way a card says a
                     secondary fact: a tick for confirmed, a clock while it is
                     only the athlete's word, and the plain tag when nothing has
                     been paid — with the words on the tooltip. --}}
                <span class="ms-auto flex-shrink-0 inline-flex items-center gap-1">
                    @foreach($acts as $act)
                        @php
                            $aPaid = ! empty($act['paid']);
                            $aOk = ! empty($act['verified']);
                            /* ⚠️ `bg-gray-900`, not `bg-foreground`: the Tailwind
                               bundle is PREBUILT and carries no `bg-foreground`
                               rule at all — only `text-foreground`. The chip
                               this replaced used it, so it has been drawing
                               white text on no background since the day it
                               shipped. Every class here was checked against the
                               built CSS. */
                            // One blue, whatever the money says. `bg-blue-600`
                            // is in the prebuilt bundle; `bg-foreground` — what
                            // the chip this replaced used — is not, and drew
                            // white text on nothing.
                            $aTone = 'bg-blue-600 text-white';
                        @endphp
                        <span style="{{ $aBox }}"
                              class="inline-flex items-center justify-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold whitespace-nowrap {{ $aTone }}"
                              title="{{ $act['label'] }}{{ $aOk ? ' · '.__('events::events.people_paid_verified') : ($aPaid ? ' · '.__('events::events.people_paid_claimed') : ' · '.__('events::events.people_unpaid')) }}">
                            <i class="bi {{ $aOk ? 'bi-check-circle-fill' : ($aPaid ? 'bi-clock-fill' : 'bi-tag-fill') }} text-[9px] {{ $aOk ? '' : 'opacity-70' }}"></i>{{ $act['label'] }}
                        </span>
                    @endforeach
                </span>
            @elseif($activity)
                {{-- The single-chip fallback, for callers that pass a plain
                     `activity` string (the public participants list). Same fix
                     as above: `bg-foreground` has no rule in the built CSS. --}}
                <span class="ms-auto flex-shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full
                             text-[10px] font-bold bg-gray-900 text-white whitespace-nowrap">
                    <i class="bi bi-collection text-[9px]"></i>{{ $activity }}
                </span>
            @endif

        </span>
    </span>

    {{ $trailing ?? '' }}

    @if($chevron)
        <i class="bi bi-chevron-right text-muted-foreground/50 shrink-0 self-center"
           @if($chevronShow) x-show="{{ $chevronShow }}" x-cloak @endif></i>
    @endif
</{{ $element }}>
