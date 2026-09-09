{{--
    "Read this in your own language" — the sheet behind the cover's third
    control.

    The cover offers the two languages the whole INTERFACE speaks as big tiles,
    because that is the common answer and a tile is a one-tap answer. This is
    the long tail: every language the organiser's own words can be written into,
    searchable, because sixty tiles is not a choice, it is a wall.

    ── What happens on a tap ────────────────────────────────────────────────────

    The first person ever to ask for a language waits a few seconds while it is
    written, and then it exists forever. So this sheet has a THIRD state between
    "picked" and "gone": it says what it is doing, and why the wait is a
    one-off, rather than freezing on a tapped row.

    That is also why the whole thing is a foreground wait rather than a page
    that loads half-translated and fills in: a poster whose title is Portuguese
    and whose requirements are still Arabic looks broken in a way that no
    spinner does, and this only ever happens once per language per event.

    If it fails — no provider configured, the key is wrong, the model is down —
    the visitor is NOT trapped. They are told, and they go through to the page
    in the language it was written in, which is exactly the page that existed
    before this feature (RULE #1, and *Unattended Devices Must Always Recover*
    applied to a person: never a state you cannot leave).

    ⚠️ EVERY dimension here is an inline `style`, like the rest of
    entry/public/*. The Tailwind bundle in public/build is COMPILED — a class
    nobody used before has no CSS at all and renders as nothing. See
    cover-actions.blade.php, which learned that the hard way.

    Teleported to <body>: this is `position: fixed` and the page's <main> is a
    transformed ancestor, which would otherwise become its containing block and
    size the sheet to a wrapper instead of the screen.
--}}
@php
    use App\Translation\Translations;

    /* Through the module's front door, never into its Services/ — see
       App\Translation\Translations and ModuleBoundaryTest. */
    $locales = Translations::locales();

    /* The event's own colour, re-checked here rather than trusted from the
       caller — it lands in a `style` attribute, and every other partial that
       paints an organiser-supplied colour re-checks it at the point of use. */
    $langColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($e['color'] ?? ''))
        ? $e['color']
        : '#7c3aed';

    /* Ordered so a reader scanning the list meets their own language early:
       the ones the interface speaks first (they are the complete experience),
       then everything else alphabetically by its ENGLISH name — because the
       search box matches both names, and an alphabet the reader cannot read is
       not an ordering they can use. */
    $interface = [];
    $rest = [];

    foreach ($locales->all() as $code => $meta) {
        $row = [
            'code' => $code,
            'name' => $meta['name'],
            'native' => $meta['native'],
            'dir' => $meta['dir'] ?? 'ltr',
            'flag' => $locales->flag($code),
            'ui' => $locales->isInterfaceLocale($code),
        ];

        $row['ui'] ? $interface[] = $row : $rest[] = $row;
    }

    usort($rest, fn ($a, $b) => strcmp($a['name'], $b['name']));

    $languageRows = array_merge($interface, $rest);
@endphp

<div x-data="eventLanguages(@js($e['key']), @js(app()->getLocale()))"
     x-cloak
     @open-language-sheet.window="open()"
     {{-- A tile on the cover's strip was tapped. Same flow as picking from the
          list — prepare the language, show what is happening, then go — so the
          two entry points cannot behave differently. --}}
     @cover-pick-language.window="pickCode($event.detail?.code, $event.detail?.native)">

    <template x-teleport="body">
        <div x-show="showing" x-cloak
             @keydown.escape.window="close()"
             class="fixed inset-0"
             style="z-index:120;">

            {{-- The ground behind the sheet. Tapping it closes — but never
                 while a translation is being written, because a stray tap
                 would abandon a wait the visitor has already half-spent. --}}
            <div x-show="showing"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 @click="busy || close()"
                 class="absolute inset-0"
                 style="background:rgba(7,11,20,.72); -webkit-backdrop-filter:blur(3px); backdrop-filter:blur(3px);"></div>

            <div x-show="showing"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 flex flex-col"
                 style="max-height:92vh; max-width:520px; margin:0 auto;">

                {{-- ===== The gradient band (Design Rule #8) =====
                     The sheet's own colour is the event's, so the choice feels
                     like part of this competition rather than a browser
                     setting. --}}
                <div class="flex-shrink-0"
                     style="padding:12px 20px 18px; border-radius:24px 24px 0 0; color:#fff; position:relative; overflow:hidden;
                            background: linear-gradient(150deg, {{ $langColor }}, {{ $langColor }}b0);">

                    <div style="position:absolute; right:-32px; top:-40px; width:144px; height:144px; border-radius:9999px; background:rgba(255,255,255,.10);"></div>

                    <div style="margin:0 auto 12px; width:40px; height:4px; border-radius:9999px; background:rgba(255,255,255,.40);"></div>

                    <div style="position:relative; display:flex; align-items:flex-start; gap:12px;">
                        <span style="width:48px; height:48px; border-radius:16px; background:rgba(255,255,255,.20); display:grid; place-items:center; flex:none;">
                            <i class="bi bi-translate" style="font-size:20px;"></i>
                        </span>
                        <div style="min-width:0; flex:1;">
                            <h3 style="margin:0; font-size:18px; font-weight:900; line-height:1.15;">{{ __('translation::messages.read_in') }}</h3>
                            <p style="margin:2px 0 0; font-size:12px; color:rgba(255,255,255,.85);">{{ __('translation::messages.choose_language') }}</p>
                        </div>
                        <button type="button" @click="close()" :disabled="busy"
                                aria-label="{{ __('shared.close') }}"
                                style="width:36px; height:36px; border-radius:9999px; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.25); display:grid; place-items:center; flex:none; color:#fff;">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    {{-- Search. Not a native anything — a plain text input,
                         styled, matching both the English name and the
                         language's own, so "German" and "Deutsch" both find it. --}}
                    <div style="position:relative; margin-top:14px;" x-show="! busy">
                        <i class="bi bi-search" style="position:absolute; inset-inline-start:14px; top:50%; transform:translateY(-50%); font-size:13px; color:rgba(255,255,255,.65);"></i>
                        <input type="text" x-model="q" x-ref="search"
                               placeholder="{{ __('translation::messages.search_languages') }}"
                               autocomplete="off" autocorrect="off" spellcheck="false"
                               style="width:100%; padding:11px 14px 11px 38px; border-radius:14px; font-size:14px; color:#fff;
                                      background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.25); outline:none;">
                    </div>
                </div>

                {{-- ===== The body ===== --}}
                <div style="flex:1; overflow-y:auto; background:#fff; -webkit-overflow-scrolling:touch;">

                    {{-- ── Writing it, right now ─────────────────────────────
                         The one screen that justifies the whole sheet: it says
                         what is happening, that it is a one-off, and it never
                         claims to be finished before it is. --}}
                    <div x-show="busy" style="padding:34px 24px 30px; text-align:center;">
                        <div style="margin:0 auto 18px; width:56px; height:56px; border-radius:9999px; display:grid; place-items:center;
                                    background:{{ $langColor }}1a;">
                            <i class="bi bi-translate ev-lang-spin" style="font-size:22px; color:{{ $langColor }};"></i>
                        </div>

                        <p style="margin:0; font-size:16px; font-weight:800; color:#1e2c4f;" x-text="busyTitle"></p>
                        <p style="margin:8px auto 0; max-width:300px; font-size:12.5px; line-height:1.5; color:#6b7689;" x-text="busyNote"></p>

                        <div style="margin:20px auto 0; max-width:260px; height:5px; border-radius:9999px; background:#eef1f6; overflow:hidden;">
                            <div class="ev-lang-bar" style="height:100%; border-radius:9999px; background:{{ $langColor }};"></div>
                        </div>

                        {{-- Always a way out, from the first second. A wait
                             nobody can abandon is a trap. --}}
                        <button type="button" @click="skip()"
                                style="margin-top:22px; font-size:12.5px; font-weight:700; color:#6b7689; text-decoration:underline;">
                            {{ __('shared.cancel') }}
                        </button>
                    </div>

                    {{-- ── It did not work ───────────────────────────────────
                         Says so plainly and offers the page anyway, in the
                         language the organiser wrote it in. --}}
                    <div x-show="failed" style="padding:28px 24px;">
                        <div style="border-radius:16px; background:#fff7ed; border:1px solid #fed7aa; padding:14px 16px; display:flex; gap:12px;">
                            <i class="bi bi-exclamation-triangle-fill" style="color:#c2410c; font-size:16px; flex:none; margin-top:1px;"></i>
                            <p style="margin:0; font-size:12.5px; line-height:1.55; color:#7c2d12;" x-text="failedNote"></p>
                        </div>
                        <button type="button" @click="skip()"
                                style="margin-top:16px; width:100%; padding:13px; border-radius:14px; font-size:14px; font-weight:800; color:#fff; background:{{ $langColor }};">
                            {{ __('translation::messages.continue_anyway') }}
                        </button>
                    </div>

                    {{-- ── The list ──────────────────────────────────────────── --}}
                    <div x-show="! busy && ! failed" style="padding:8px 0 0;">

                        <template x-for="row in filtered()" :key="row.code">
                            <button type="button" @click="pick(row)"
                                    class="m-press"
                                    style="width:100%; display:flex; align-items:center; gap:14px; padding:13px 20px; text-align:start; border-bottom:1px solid #f3f5f9;">

                                {{-- The flag is recognition only; the NAME is
                                     the identity. A language with no honest
                                     flag gets its own initial instead of a
                                     borrowed country. --}}
                                <template x-if="row.flag">
                                    <span :class="'fi fi-' + row.flag"
                                          style="width:30px; height:22px; border-radius:5px; flex:none; background-size:cover; outline:1px solid rgba(30,44,79,.14); outline-offset:-1px;"></span>
                                </template>
                                <template x-if="! row.flag">
                                    <span style="width:30px; height:22px; border-radius:5px; flex:none; display:grid; place-items:center; background:#eef1f6; font-size:10px; font-weight:800; color:#6b7689;"
                                          x-text="row.code.slice(0, 2).toUpperCase()"></span>
                                </template>

                                <span style="min-width:0; flex:1;">
                                    <span style="display:block; font-size:14px; font-weight:700; color:#1e2c4f;"
                                          :dir="row.dir" x-text="row.native"></span>
                                    <span style="display:block; margin-top:1px; font-size:11px; color:#6b7689;" x-text="row.name"></span>
                                </span>

                                {{-- Already reading it. --}}
                                <template x-if="row.code === current">
                                    <i class="bi bi-check-lg" style="font-size:16px; color:{{ $langColor }}; flex:none;"></i>
                                </template>
                            </button>
                        </template>

                        <p x-show="filtered().length === 0"
                           style="padding:30px 24px; text-align:center; font-size:13px; color:#6b7689;">
                            {{ __('translation::messages.no_language_found') }}
                        </p>

                        {{-- The honest footnote. A reader choosing Vietnamese
                             should know the event will be Vietnamese and the
                             buttons will not — being surprised by that after
                             the fact is what makes a product feel broken. --}}
                        <p style="padding:16px 24px calc(20px + env(safe-area-inset-bottom)); font-size:11px; line-height:1.5; color:#96a0b3; text-align:center;">
                            <i class="bi bi-stars" style="margin-inline-end:4px;"></i>{{ __('translation::messages.translated_by_ai') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- The language is set by the same plain <form> the cover's tiles use —
         one endpoint, one code path, and a browser that has just been through a
         fetch() still changes language the ordinary way. --}}
    <form method="POST" action="{{ route('locale.set') }}" x-ref="form" style="display:none;">
        @csrf
        @method('PUT')
        <input type="hidden" name="locale" x-ref="locale" value="">
        {{-- Scopes it to this event — see the note in cover-actions. --}}
        <input type="hidden" name="event" value="{{ $e['key'] }}">
        {{-- Where to come back to. The event surface is self-contained and a
             language change must never land the reader on the platform — see
             LocaleController, which holds this to a path on our own host. --}}
        <input type="hidden" name="back" value="{{ route('events.public', ['event' => $e['key']], false) }}">
    </form>
</div>

@once
@push('styles')
<style>
    /* Two small animations, defined here for the same reason every dimension
       in this folder is inline: the compiled bundle has no class for them. */
    @keyframes ev-lang-spin { to { transform: rotate(360deg); } }
    .ev-lang-spin { display: inline-block; animation: ev-lang-spin 1.6s linear infinite; }

    /* An indeterminate bar: it says "working", never "37% done", because
       nothing here knows how far along a model is and a progress bar that
       lies is worse than one that only paces. */
    @keyframes ev-lang-bar {
        0%   { transform: translateX(-100%); }
        100% { transform: translateX(400%); }
    }
    .ev-lang-bar { width: 25%; animation: ev-lang-bar 1.4s cubic-bezier(.5,0,.5,1) infinite; }

    @media (prefers-reduced-motion: reduce) {
        .ev-lang-spin, .ev-lang-bar { animation: none; }
        .ev-lang-bar { width: 100%; }
    }
</style>
@endpush

@push('scripts')
<script>
    /*
        The picker's state.

        Declared as a plain function in a pushed script, exactly like
        eventCover() beside it — the file is rendered into the page, not inside
        the teleported <template>, so Alpine finds it. A definition written
        INSIDE the template would be inert, which is the trap this surface has
        hit before.
    */
    function eventLanguages(eventKey, currentLocale) {
        return {
            showing: false,
            busy: false,
            failed: false,
            q: '',
            current: currentLocale,
            busyTitle: '',
            busyNote: '',
            failedNote: '',
            rows: @json($languageRows),

            // The poll, and the deadline it gives up on. Held on the component
            // so close() can always stop it — a timer left running after the
            // sheet is gone keeps hitting the server for a page nobody is on.
            _timer: null,
            _deadline: 0,
            _quickUntil: 0,

            open() {
                this.showing = true;
                this.failed = false;
                this.busy = false;
                this.q = '';
                document.documentElement.style.overflow = 'hidden';

                /* Focus on a DESKTOP pointer only. Focusing a text input on a
                   phone throws the keyboard up over the very list the visitor
                   came to read. */
                if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
                    this.$nextTick(() => this.$refs.search?.focus());
                }
            },

            close() {
                if (this.busy) return;      // never abandon a wait by accident
                this.showing = false;
                this.stop();
                document.documentElement.style.overflow = '';
            },

            stop() {
                if (this._timer) { clearInterval(this._timer); this._timer = null; }
            },

            filtered() {
                const q = this.q.trim().toLowerCase();
                if (! q) return this.rows;

                // Matches the English name, the native name and the code, so
                // "de", "German" and "Deutsch" all find the same row.
                return this.rows.filter(r =>
                    r.name.toLowerCase().includes(q) ||
                    r.native.toLowerCase().includes(q) ||
                    r.code.toLowerCase().startsWith(q)
                );
            },

            /* Pick by code, for the cover's flag strip. The sheet opens
               straight into its "preparing" state rather than showing the list
               the visitor did not ask for; if the language is already written
               this is over before it paints. */
            pickCode(code, native) {
                const row = this.rows.find(r => r.code === code)
                    || { code: code, native: native || code, name: native || code, dir: 'ltr', flag: null };

                this.showing = true;
                document.documentElement.style.overflow = 'hidden';
                this.pick(row);
            },

            pick(row) {
                if (row.code === this.current) { this.close(); return; }

                this.busy = true;
                this.failed = false;
                this.busyTitle = @js(__('translation::messages.preparing', ['language' => ':lang'])).replace(':lang', row.native);
                this.busyNote = @js(__('translation::messages.preparing_note', ['language' => ':lang'])).replace(':lang', row.native);
                this.failedNote = @js(__('translation::messages.preparing_failed', ['language' => ':lang'])).replace(':lang', row.native);

                this.$refs.locale.value = row.code;

                fetch(@js(route('events.public.language.prepare', ['event' => $e['key']])), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({ locale: row.code }),
                })
                .then(r => r.ok ? r.json() : Promise.reject(r))
                .then(d => d.ready ? this.go() : this.watch(row))
                /* Rate-limited, offline, or the endpoint is gone. The visitor
                   still gets the page — in the source language, which is the
                   page that existed before any of this. */
                .catch(() => this.go());
            },

            /* Ask often at first, then settle down.
               
               A translation now lands in about twelve seconds, so a flat
               two-second poll spends up to two of those seconds sitting on a
               finished answer — a sixth of the wait, purely in the polling.
               750ms for the first fifteen seconds catches the common case
               almost the moment it is ready; after that the answer is late for
               a reason and hammering it helps nobody.

               The ninety-second ceiling is not a guess about the model's speed:
               it is the point past which a person standing in a hall has
               decided this is broken and would rather have the page. */
            watch(row) {
                this._deadline = Date.now() + 90000;
                this._quickUntil = Date.now() + 15000;
                this.stop();

                const tick = () => {
                    if (Date.now() > this._deadline) { this.stop(); this.giveUp(); return; }

                    fetch(@js(route('events.public.language.status', ['event' => $e['key'], 'locale' => '__L__'])).replace('__L__', encodeURIComponent(row.code)), {
                        headers: { 'Accept': 'application/json' },
                    })
                    .then(r => r.ok ? r.json() : Promise.reject(r))
                    .then(d => {
                        if (d.ready) { this.stop(); this.go(); return; }
                        if (d.status === 'failed' || d.status === 'unavailable') { this.stop(); this.giveUp(); }
                    })
                    .catch(() => { /* one dropped poll is not a failure; the deadline decides */ });
                };

                const schedule = () => {
                    this.stop();
                    this._timer = setInterval(tick, Date.now() < this._quickUntil ? 750 : 2000);
                };

                schedule();
                // One re-schedule when the quick window closes, rather than a
                // timer that re-arms itself on every tick.
                setTimeout(() => { if (this._timer) schedule(); }, 15000);
            },

            giveUp() {
                this.busy = false;
                this.failed = true;
            },

            /* Take the page in the source language and stop waiting. */
            skip() {
                this.stop();
                this.busy = false;
                this.failed = false;
                this.go();
            },

            /* Set the language and go. The cover is marked seen FIRST — this
               submits a form, the page reloads, and without that the poster
               would paint over the page the visitor just waited for.
               Dispatched rather than written here, so the sessionStorage key
               stays defined in exactly one place (cover-script). */
            go() {
                window.dispatchEvent(new CustomEvent('cover-seen'));
                this.$refs.form.submit();
            },
        };
    }
</script>
@endpush
@endonce
