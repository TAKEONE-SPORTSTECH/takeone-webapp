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
    $current = app()->getLocale();

    /* The row never mirrors. English sits on the LEFT and Arabic on the RIGHT
       at all times — in an Arabic page as much as an English one — because the
       language buttons are the one control on this page whose POSITION is how
       people find them. A visitor who learned "mine is the right-hand one"
       must not have that swap under them the moment they use it, and somebody
       handed a phone already in the wrong language is looking for a fixed
       landmark, not reading the layout.

       Ordered by the language's own script rather than by a hardcoded 'en'
       first: left-to-right languages first, right-to-left last. config/locales
       stays the single source of truth, and a third language lands on the
       correct side with no edit here. */
    $locales = collect(config('locales', []))
        ->sortBy(fn ($meta) => ($meta['dir'] ?? 'ltr') === 'rtl' ? 1 : 0)
        ->all();
@endphp

@if(count($locales) > 1)
    <div class="m-in-fade" style="margin-top:26px;">

        <p style="font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.18em;
                  color:rgba(255,255,255,.55); margin:0 0 12px; text-align:center;">
            {{ __('shared.language') }}
        </p>

        {{-- Two small tiles side by side and CENTRED, rather than two half-width
             columns: this is a choice between two things, not a menu, and a
             pair of buttons stretched across the frame read as the poster's
             main event when they are its footnote.

             `direction: ltr` pins the ORDER — a flex row lays its items out
             along the writing direction, so without it the pair reverses under
             <html dir="rtl"> and English and Arabic trade places. Each button
             carries its own `dir` from config, so the text inside still reads
             in its own script. --}}
        {{-- 28px apart. It went 12 → 18 → 28: the pair is two separate
             answers, and until there is real air between them they read as one
             two-part control. Wide enough to be obvious, still narrow enough
             that both stay in the middle of the frame rather than drifting to
             its edges. --}}
        <div style="display:flex; justify-content:center; gap:28px; direction:ltr;">
            @foreach($locales as $code => $meta)
                @php
                    $isOn = $code === $current;
                    /* From config, never from a request — but stripped anyway,
                       because it lands in a class name. */
                    $flag = preg_replace('/[^a-z]/', '', strtolower($meta['flag'] ?? ''));
                @endphp
                <form method="POST" action="{{ route('locale.set') }}" style="display:block; margin:0;">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="locale" value="{{ $code }}">
                    {{-- A square: the flag big enough to be read across a room,
                         the language under it in its own script, and nothing
                         else. The English gloss under the native name was the
                         redundancy — "العربية / Arabic" tells an Arabic reader
                         nothing they did not already know from the first word,
                         and the arrow said "this is a button" to a thing that
                         is plainly a button. --}}
                    <button type="submit" @click="markSeen()"
                            @if($isOn) aria-current="true" @endif
                            class="cover-lang{{ $isOn ? ' is-on' : '' }}"
                            @if($meta['dir'] ?? null) dir="{{ $meta['dir'] }}" @endif>
                        <span class="cover-lang-flag fi fi-{{ $flag }}"></span>
                        <span class="cover-lang-name">{{ $meta['native'] ?? strtoupper($code) }}</span>
                    </button>
                </form>
            @endforeach
        </div>
    </div>
@endif

@once
@push('styles')
<style>
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
