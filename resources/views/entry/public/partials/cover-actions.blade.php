{{--
    The cover's only control: the language.

    ONE copy, included by both covers (CLAUDE.md → Shared Stays Shared).

    The two square tiles that stood here — "Enter this competition" and "View
    competition details" — were REMOVED at the user's instruction (2026-09-02).
    The cover asks one question, and picking a language is the answer AND the way
    in: the page behind it is unreadable until that is settled, and the entry CTA
    already lives on the page itself. So there is nothing else on this screen to
    decide.

    Which means each button does two things: set the locale, and mark the cover
    as seen so it does not paint again on the reload that follows. `markSeen()`
    (partials/cover-script) is that second half — without it the form would
    bounce the visitor straight back onto the cover.

    ⚠️ EVERY dimension here is an inline `style`, not a Tailwind arbitrary
    utility. The bundle in public/build is COMPILED, so a class nobody had used
    before — `bg-white/[.08]`, `text-[#0b1220]`, `h-[86px]` — has no CSS at all
    and renders as nothing. That is what broke this block once already. The rest
    of entry/public/* is written the same way for the same reason: inline styles,
    or classes that already exist in the bundle. Do not reintroduce a novel
    arbitrary class here without rebuilding the bundle.

    It is a plain <form> per language — no JS at all — so a visitor with nothing
    but a browser can still choose. Flags and native names come from
    config/locales.php, the single source of truth: add a locale there and it
    appears here with no edit to this file.
--}}
@php
    use App\Translation\Translations;

    $current = app()->getLocale();
    $contentLocales = Translations::locales();

    /* THE ORDER IS FIXED AND DOES NOT MIRROR.
     *
     * English first, Arabic second, then every other language the organiser's
     * words can be read in, alphabetically by its English name.
     *
     * English and Arabic are pinned to the front rather than sorted with the
     * rest because they are the two the whole INTERFACE speaks — picking one of
     * them gives a completely translated product, and picking any other gives a
     * translated EVENT inside an English app. That is a real difference and the
     * first two positions are how it is signalled without a paragraph.
     *
     * The strip never reverses under <html dir="rtl">: a visitor who learned
     * "mine is the second one" must not have it move the moment they use it,
     * and somebody handed a phone in a language they cannot read is looking for
     * a fixed landmark, not reading the layout. `direction: ltr` on the
     * scroller pins it; each tile carries its own `dir` so its own name still
     * reads correctly.
     */
    $pinned = ['en', 'ar'];
    $rows = [];

    foreach ($pinned as $code) {
        if ($contentLocales->has($code)) {
            $rows[$code] = $contentLocales->meta($code);
        }
    }

    $others = collect($contentLocales->all())
        ->except($pinned)
        ->sortBy(fn ($meta) => $meta['name'])
        ->all();

    $rows = $rows + $others;

    /* Re-checked here rather than taken from the including cover: it lands in a
       `style` attribute, it is organiser-supplied, and the two covers name it
       differently ($coverColor / $dColor). Every other partial that paints an
       organiser's colour re-checks it at the point of use. */
    $coverCtaColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($e['color'] ?? ''))
        ? $e['color']
        : '#7c3aed';
@endphp

@if(count($rows) > 1)
    {{-- `@click.stop` — using a control here never means "take me in".

         Belt and braces since the artwork's own tap-to-dismiss was removed
         (see partials/cover.blade.php). It stays because this block is
         included by both covers and must be safe wherever it is dropped: if
         anything above it ever becomes a tap target again, choosing a language
         still cannot double as leaving the cover — which is exactly the fault
         reported on 2026-09-09.

         `.stop` ends the CLICK's journey; a form's `submit` is a separate
         event, so the language forms still post normally. --}}
    <div class="m-in-fade" style="margin-top:26px;" @click.stop="">

        <p style="font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.18em;
                  color:rgba(255,255,255,.55); margin:0 0 12px; text-align:center;">
            {{ __('shared.language') }}
        </p>

        {{-- The strip.

             A scroller rather than a grid because there are sixty of these and
             a poster has room for three: the first two are the answer for
             almost everybody, and the rest are a flick away for the visitor
             they are not the answer for. Scroll-snap so a flick lands on a
             tile rather than between two.

             `cover-strip` hides the scrollbar and fades both edges, which is
             what says "there is more this way" without a caption. --}}
        <div style="position:relative;">

            {{-- Desktop nudges. Hidden on touch, where the flick IS the
                 control and an arrow is clutter. --}}
            <button type="button" class="cover-strip-nudge cover-strip-prev" aria-label="Scroll left"
                    onclick="this.parentNode.querySelector('.cover-strip').scrollBy({left:-260,behavior:'smooth'})">
                <i class="bi bi-chevron-left"></i>
            </button>
            <button type="button" class="cover-strip-nudge cover-strip-next" aria-label="Scroll right"
                    onclick="this.parentNode.querySelector('.cover-strip').scrollBy({left:260,behavior:'smooth'})">
                <i class="bi bi-chevron-right"></i>
            </button>

            <div class="cover-strip">
                @foreach($rows as $code => $meta)
                    @php
                        $isOn = $code === $current;
                        /* From config, never from a request — but stripped
                           anyway, because it lands in a class name. */
                        $flag = preg_replace('/[^a-z]/', '', strtolower($meta['flag'] ?? ''));
                        $native = $meta['native'] ?? strtoupper($code);
                    @endphp

                    {{-- Still a plain <form> per language, so a visitor with no
                         JavaScript at all can still choose one. When Alpine IS
                         running it intercepts and runs the "preparing your
                         language" flow first (partials/language-sheet); when it
                         is not, this posts and the page comes back in that
                         language, translated or not. Degrades, never breaks. --}}
                    <form method="POST" action="{{ route('locale.set') }}" style="display:block; margin:0; flex:none;"
                          @submit.prevent="window.dispatchEvent(new CustomEvent('cover-pick-language', {
                              detail: { code: @js($code), native: @js($native) }
                          }))">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="locale" value="{{ $code }}">
                        {{-- ⚠️ Scopes the choice to THIS EVENT. Without it the
                             pick becomes the reader's platform-wide language —
                             and for a signed-in member, their saved preference
                             on every device. A poster is a public door, not a
                             settings screen. See App\Translation\EventLocale. --}}
                        <input type="hidden" name="event" value="{{ $e['key'] }}">
                        <input type="hidden" name="back" value="{{ route('events.public', ['event' => $e['key']], false) }}">

                        <button type="submit" @click="markSeen()"
                                @if($isOn) aria-current="true" @endif
                                class="cover-lang{{ $isOn ? ' is-on' : '' }}"
                                title="{{ $meta['name'] ?? $code }}"
                                @if($meta['dir'] ?? null) dir="{{ $meta['dir'] }}" @endif>
                            @if($flag)
                                <span class="cover-lang-flag fi fi-{{ $flag }}"></span>
                            @else
                                {{-- A language with no honest flag gets its own
                                     code on a plate, never a borrowed country. --}}
                                <span class="cover-lang-flag cover-lang-code">{{ strtoupper(substr($code, 0, 2)) }}</span>
                            @endif
                            <span class="cover-lang-name">{{ $native }}</span>
                        </button>
                    </form>
                @endforeach
            </div>
        </div>

        {{-- Sixty tiles is a flick too far when you know what you want. The
             search sheet is the same list with a box on top. --}}
        <div style="text-align:center; margin-top:16px;">
            <button type="button"
                    @click="window.dispatchEvent(new CustomEvent('open-language-sheet'))"
                    class="m-press"
                    style="display:inline-flex; align-items:center; gap:7px; padding:8px 15px; border-radius:9999px;
                           background:rgba(255,255,255,.10); border:1px solid rgba(255,255,255,.18);
                           font-size:12px; font-weight:700; color:rgba(255,255,255,.85);">
                <i class="bi bi-search" style="font-size:11px;"></i>{{ __('translation::messages.search_languages') }}
            </button>
        </div>
    </div>
@endif

{{-- ===== The way in =====

     ⚠️ OUTSIDE the language block's `@if` on purpose, and that is the whole
     safety of this change.

     Tapping the artwork used to enter the event. It was removed on 2026-09-09
     at the user's instruction — "when I click anywhere away from the language
     selection it takes me in, this is wrong" — because the cover asks one
     question and an accidental tap answered it for you, mid-flick through sixty
     languages.

     Which leaves this as the ONLY deliberate way past the cover for somebody
     happy with the language they already have. It must therefore render even
     when the language strip does not: that block is behind `count($rows) > 1`,
     and an event served in a single language would otherwise have a cover with
     no exit at all — a poster nobody can get past, on a public link. --}}
<div style="text-align:center; margin-top:22px;" class="m-in-fade">
    <button type="button" @click="dismiss()" class="cover-cta m-press"
            style="background: {{ $coverCtaColor }};">
        {{ __('events.public_cover_cta') }}
        <i class="bi bi-arrow-right rtl:rotate-180" style="font-size:12px;"></i>
    </button>
</div>

@once
@push('styles')
<style>
    /* The cover's way in. A real rule, not Tailwind utilities: the compiled
       bundle carries no CSS for a class nobody has used before, so a utility
       invented here renders as nothing at all. */
    .cover-cta {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-width: 210px;
        padding: 13px 26px;
        border: 0;
        border-radius: 9999px;
        font-size: 13.5px;
        font-weight: 800;
        color: #fff;
        cursor: pointer;
        box-shadow: 0 10px 26px rgba(0, 0, 0, .35);
    }

    /* The cover's language buttons. Defined here rather than as utilities
       because the compiled bundle has no CSS for a class nobody used before. */
    .cover-lang {
        /* A SMALL SQUARE, sized in px and not in fractions of the frame: the
           pair is a footnote on a poster, and anything that grows with the
           screen ends up dominating it on the one device the poster is for. */
        width: 104px;
        aspect-ratio: 1 / 1;
        flex: none;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 9px;
        padding: 10px;
        border-radius: 18px;
        color: #fff;
        text-align: center;
        background: rgba(255, 255, 255, .09);
        border: 1px solid rgba(255, 255, 255, .20);
        -webkit-backdrop-filter: blur(12px);
        backdrop-filter: blur(12px);
        transition: transform .2s cubic-bezier(.2,.7,.3,1), background-color .2s ease, border-color .2s ease;
    }
    .cover-lang.is-on {
        background: #fff;
        color: #0b1220;
        border-color: #fff;
        box-shadow: 0 14px 30px -14px rgba(0, 0, 0, .6);
    }
    @media (hover: hover) {
        .cover-lang:hover { background: rgba(255, 255, 255, .17); transform: translateY(-2px); }
        .cover-lang.is-on:hover { background: #fff; }
    }
    .cover-lang:active { transform: scale(.975); }
    @media (prefers-reduced-motion: reduce) {
        .cover-lang, .cover-lang:hover, .cover-lang:active { transition: none; transform: none; }
    }

    /* The flag is the button. Sized off the tile so it grows with it, capped so
       it cannot outrun the square on a wide phone, and given a hairline because
       a flag with white in it (both of these) otherwise bleeds into the tile. */
    .cover-lang-flag {
        width: 46px;
        height: auto;
        aspect-ratio: 4 / 3;
        border-radius: 9px;
        flex: none;
        background-size: cover;
        box-shadow: 0 6px 18px rgba(0, 0, 0, .45);
        outline: 1px solid rgba(255, 255, 255, .35);
        outline-offset: -1px;
    }
    .cover-lang.is-on .cover-lang-flag { outline-color: rgba(11, 18, 32, .18); }

    .cover-lang-name {
        display: block;
        font-size: 13px;
        font-weight: 800;
        line-height: 1.15;
        letter-spacing: -.01em;
    }

    /* ===== The scroller =====

       Horizontal, snapping, scrollbar hidden, both edges faded so the strip
       says "there is more this way" without a caption. `direction: ltr` pins
       the ORDER — a flex row lays out along the writing direction, and without
       it the whole strip reverses under <html dir="rtl">. */
    .cover-strip {
        display: flex;
        gap: 12px;
        direction: ltr;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        /* Room for the tile's own lift on hover, and for the edge fade. */
        padding: 4px 40px;
        justify-content: safe center;
        -webkit-mask-image: linear-gradient(to right, transparent, #000 34px, #000 calc(100% - 34px), transparent);
        mask-image: linear-gradient(to right, transparent, #000 34px, #000 calc(100% - 34px), transparent);
    }
    .cover-strip::-webkit-scrollbar { display: none; }
    .cover-strip > form { scroll-snap-align: center; }

    /* The nudges. Pointer devices only: on a phone the flick is the control
       and an arrow sitting on top of the tiles is in the way. */
    .cover-strip-nudge {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        z-index: 2;
        width: 30px;
        height: 30px;
        border-radius: 9999px;
        display: none;
        place-items: center;
        color: #fff;
        background: rgba(255, 255, 255, .14);
        border: 1px solid rgba(255, 255, 255, .22);
        -webkit-backdrop-filter: blur(8px);
        backdrop-filter: blur(8px);
        transition: background-color .2s ease;
    }
    .cover-strip-nudge:hover { background: rgba(255, 255, 255, .26); }
    .cover-strip-prev { left: 0; }
    .cover-strip-next { right: 0; }
    @media (hover: hover) and (pointer: fine) {
        .cover-strip-nudge { display: grid; }
    }

    /* A language with no flag of its own. Same box as a flag so the row of
       tiles keeps one rhythm. */
    .cover-lang-code {
        display: grid;
        place-items: center;
        background: rgba(255, 255, 255, .16);
        font-size: 12px;
        font-weight: 800;
        letter-spacing: .04em;
        color: #fff;
    }
    .cover-lang.is-on .cover-lang-code { background: rgba(11, 18, 32, .08); color: #0b1220; }

    /* Tight windows — a small phone in landscape, the 520px desktop column at
       its narrowest — where a true square would push the pair off the fold. */
    /* Tight windows — a small phone in landscape — where even this much has to
       give. */
    @media (max-height: 620px) {
        .cover-lang { width: 88px; aspect-ratio: 5 / 4; gap: 7px; }
        .cover-lang-flag { width: 38px; }
        .cover-lang-name { font-size: 12px; }
    }
</style>
@endpush
@endonce
