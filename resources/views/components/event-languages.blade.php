@props([
    'event',              // event uuid
    'color' => '#7c3aed',
    'title' => '',
    'sourceLocale' => null,   // the language it was written in, for the row's sub-line
])

{{--
    Every language this event is being read in — and the organiser's power to
    correct any of it.

    ── Why this screen exists ───────────────────────────────────────────────────

    An automatic translation on a public poster with nobody able to fix it is a
    promise the platform cannot keep. The organiser is the one who gets phoned
    when the Portuguese says the wrong thing, so the organiser is the one who
    must be able to open the Portuguese, read it beside their own words, and put
    it right.

    ── The one invariant ────────────────────────────────────────────────────────

    A sentence corrected here is marked as written by a PERSON, and no machine
    run will ever overwrite it — not a re-translate, not an edit to the source,
    not a change of provider. That is enforced on the server
    (App\Translation\Jobs\TranslateContent::store); this screen is simply the
    only thing that sets the flag.

    ── Shape ────────────────────────────────────────────────────────────────────

    Hub and spoke, per the Mobile Pattern Language: the sheet opens on the list
    of languages, and tapping one drills into that language's fields. Not tabs —
    sixty languages is not a tab strip — and not one endless scroll of every
    field of every language, which is the same information arranged so nobody
    can act on it.

    Standalone per the component contract: it owns its state, its requests, its
    sheet and its own in-place patching, and drops into either console with no
    page glue.
--}}

@php
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';
@endphp

<div x-data="eventLanguagesAdmin(@js($event), @js((string) $sourceLocale))"
     @event-languages-refresh.window="if ($event.detail?.event === @js($event)) load(true)">

    {{-- ===== The row in the console ===== --}}
    <button type="button" @click="openSheet()"
            class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0"
              :class="languages.length ? 'text-white' : 'bg-muted text-muted-foreground'"
              :style="languages.length ? 'background: {{ $c }}' : ''">
            <i class="bi bi-translate text-lg"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ __('translation::messages.languages') }}</span>
            <span class="block text-[11px] mt-0.5 truncate"
                  :class="needsReview > 0 ? 'text-amber-600 font-semibold' : 'text-muted-foreground'"
                  x-text="rowSub()"></span>
        </span>
        {{-- The one number worth surfacing on a closed row: how many sentences
             the organiser has not looked at since their own words changed. --}}
        <template x-if="needsReview > 0">
            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700 flex-shrink-0"
                  x-text="needsReview"></span>
        </template>
        <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
    </button>

    <template x-teleport="body" data-teleport-template="true">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center"
             @keydown.escape.window="back()" style="display:none;">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="close()"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="relative w-full sm:max-w-2xl max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">

                {{-- ===== The gradient band (Design Rule #8) ===== --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>

                    <div class="relative flex items-start gap-3">
                        {{-- Back on the leading edge once you are inside a
                             language, ✕ on the trailing one — the drill-down's
                             own way out, so the sheet never closes when the
                             organiser meant to go up one level. --}}
                        <template x-if="! viewing && ! picking">
                            <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                                <i class="bi bi-translate text-xl"></i>
                            </span>
                        </template>
                        <template x-if="viewing || picking">
                            <button type="button" @click="back()"
                                    aria-label="{{ __('shared.back') }}"
                                    class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                                <i class="bi bi-chevron-left rtl:rotate-180 text-xl"></i>
                            </button>
                        </template>

                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight"
                                x-text="picking ? @js(__('translation::messages.on_the_poster')) : (viewing ? viewing.native : @js(__('translation::messages.languages')))"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate"
                               x-text="picking ? @js(__('translation::messages.on_the_poster_sub')) : (viewing ? viewing.name : @js($title))"></p>
                        </div>

                        <button type="button" @click="close()" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    {{-- Which language the organiser actually wrote in. It is
                         the anchor for everything below — every panel shows
                         these words on the left. --}}
                    <p class="relative mt-3 text-[11.5px] text-white/85 leading-snug flex items-center gap-1.5">
                        <i class="bi bi-pencil-fill flex-shrink-0"></i>
                        <span x-text="@js(__('translation::messages.source_language', ['language' => ':L'])).replace(':L', sourceName || '…')"></span>
                    </p>
                </div>

                {{-- ===== Body ===== --}}
                <div class="flex-1 overflow-y-auto" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">

                    {{-- loading --}}
                    <div x-show="loading" class="px-5 py-12 text-center">
                        <i class="bi bi-translate text-2xl text-muted-foreground/40"></i>
                        <p class="text-xs text-muted-foreground mt-3">…</p>
                    </div>

                    {{-- ── LEVEL 1 — the languages ──────────────────────────── --}}
                    <div x-show="! loading && ! viewing && ! picking" class="px-5 py-4 space-y-2.5">

                        {{-- What the POSTER offers, which is a different
                             question from what has been written. A reader can
                             only pick from this list, and every language not on
                             it keeps its words — including every correction
                             typed by hand — for the moment it goes back on.
                             (Asked for 2026-09-10.) --}}
                        <button type="button" @click="openPicker()"
                                class="m-press w-full text-start rounded-2xl border-2 border-gray-200 bg-white p-3.5 flex items-center gap-3">
                            <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 text-white"
                                  style="background: {{ $c }};">
                                <i class="bi bi-eye text-sm"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground">{{ __('translation::messages.on_the_poster') }}</span>
                                <span class="block text-[11px] text-muted-foreground mt-0.5 truncate" x-text="posterSub()"></span>
                            </span>
                            <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
                        </button>

                        <template x-if="languages.length === 0 && ! adding">
                            <div class="rounded-2xl border-2 border-dashed border-gray-200 px-5 py-8 text-center">
                                <i class="bi bi-translate text-2xl text-muted-foreground/40"></i>
                                <p class="text-sm font-bold text-foreground mt-3">{{ __('translation::messages.nothing_yet') }}</p>
                                <p class="text-[11.5px] text-muted-foreground leading-snug mt-1.5">{{ __('translation::messages.nothing_yet_note') }}</p>
                            </div>
                        </template>

                        {{-- ⚠️ This list is every language that has WORDS, which is
                             deliberately not the same as the languages the poster
                             offers — hiding one keeps its text. Without saying so on
                             the row, an organiser who has just narrowed the poster to
                             two languages opens this and sees twenty-three flags,
                             which reads as the setting having been ignored. It was
                             reported as exactly that (2026-09-10). So an off-poster
                             language is dimmed and says why. --}}
                        <template x-for="lang in languages" :key="lang.locale">
                            <button type="button" @click="viewing = lang"
                                    class="m-press w-full text-start rounded-2xl border-2 border-gray-200 bg-white p-3.5 flex items-center gap-3"
                                    :class="onPoster(lang.locale) ? '' : 'opacity-60'">
                                <template x-if="lang.flag">
                                    <span :class="'fi fi-' + lang.flag"
                                          class="flex-shrink-0"
                                          style="width:32px; height:24px; border-radius:5px; background-size:cover; outline:1px solid rgba(30,44,79,.14); outline-offset:-1px;"></span>
                                </template>
                                <template x-if="! lang.flag">
                                    <span class="flex-shrink-0 grid place-items-center bg-muted text-muted-foreground text-[10px] font-bold"
                                          style="width:32px; height:24px; border-radius:5px;"
                                          x-text="lang.locale.slice(0,2).toUpperCase()"></span>
                                </template>

                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-bold text-foreground truncate" :dir="lang.dir" x-text="lang.native"></span>
                                    <span class="block text-[11px] text-muted-foreground mt-0.5 truncate" x-text="lang.name"></span>
                                </span>

                                {{-- Off the poster: said in words, not by the
                                     dimming alone, and BEFORE the status chip —
                                     "not readable by anybody" outranks "three
                                     sentences need review". Tapping through still
                                     works: the words are all there, and this is
                                     where they are corrected. --}}
                                <template x-if="! onPoster(lang.locale)">
                                    <span class="flex-shrink-0 px-2 py-1 rounded-full text-[10px] font-bold bg-muted text-muted-foreground inline-flex items-center gap-1">
                                        <i class="bi bi-eye-slash"></i>{{ __('translation::messages.not_on_poster') }}
                                    </span>
                                </template>

                                {{-- One chip, saying the thing that most needs
                                     saying about this language right now. --}}
                                <span class="flex-shrink-0 px-2 py-1 rounded-full text-[10px] font-bold"
                                      :class="chipClass(lang)" x-text="chipLabel(lang)"></span>

                                <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
                            </button>
                        </template>

                        {{-- Add a language. The organiser rarely needs this — a
                             language appears by itself the moment a visitor
                             picks it — but an organiser preparing for a squad
                             they know is coming should not have to wait to be
                             asked. --}}
                        <template x-if="! adding">
                            <button type="button" @click="adding = true; q = ''"
                                    class="m-press w-full rounded-2xl border-2 border-dashed border-gray-200 p-3.5 flex items-center justify-center gap-2 text-sm font-bold"
                                    style="color: {{ $c }};">
                                <i class="bi bi-plus-lg"></i>{{ __('translation::messages.add_language') }}
                            </button>
                        </template>

                        <template x-if="adding">
                            <div class="rounded-2xl border-2 border-gray-200 overflow-hidden">
                                <div class="relative border-b border-gray-100">
                                    <i class="bi bi-search absolute inset-inline-start-0 ms-4 top-1/2 -translate-y-1/2 text-xs text-muted-foreground"></i>
                                    <input type="text" x-model="q" x-ref="add"
                                           placeholder="{{ __('translation::messages.search_languages') }}"
                                           autocomplete="off" spellcheck="false"
                                           class="w-full ps-10 pe-10 py-3 text-sm outline-none">
                                    <button type="button" @click="adding = false"
                                            class="absolute inset-inline-end-0 me-3 top-1/2 -translate-y-1/2 text-muted-foreground"
                                            aria-label="{{ __('shared.close') }}">
                                        <i class="bi bi-x-lg text-xs"></i>
                                    </button>
                                </div>
                                <div style="max-height:260px; overflow-y:auto;">
                                    <template x-for="row in addable()" :key="row.code">
                                        <button type="button" @click="add(row)"
                                                class="m-press w-full text-start px-4 py-2.5 flex items-center gap-3 border-b border-gray-50">
                                            <span class="text-sm font-semibold text-foreground" :dir="row.dir" x-text="row.native"></span>
                                            <span class="text-[11px] text-muted-foreground" x-text="row.name"></span>
                                        </button>
                                    </template>
                                    <p x-show="addable().length === 0" class="px-4 py-6 text-center text-xs text-muted-foreground">
                                        {{ __('translation::messages.no_language_found') }}
                                    </p>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- ── LEVEL 2b — which languages the POSTER offers ─────
                         A checklist, not a list of removals. Nothing in here
                         deletes a word: un-ticking a language takes it off the
                         reader's picker and leaves its translation exactly
                         where it is, so a poster can be narrowed and widened
                         again for nothing. Removing a language — which does
                         throw the words away — stays where it was, inside that
                         one language's own panel.

                         The default is the honest one: OFFER EVERYTHING, shown
                         as a switch rather than sixty-eight pre-ticked boxes,
                         because un-ticking one of those would look like a
                         change of sixty-seven. --}}
                    <div x-show="picking" x-cloak class="m-panel-in px-5 py-4 space-y-3">

                        <button type="button" @click="offeredLimited = ! offeredLimited"
                                class="m-press w-full text-start rounded-2xl border-2 p-3.5 flex items-center gap-3"
                                :class="offeredLimited ? 'border-gray-200 bg-white' : 'border-transparent'"
                                :style="offeredLimited ? '' : 'background: {{ $c }}12; border-color: {{ $c }};'">
                            <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                                  :class="offeredLimited ? 'bg-muted text-muted-foreground' : 'text-white'"
                                  :style="offeredLimited ? '' : 'background: {{ $c }}'">
                                <i class="bi bi-globe2 text-sm"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground">{{ __('translation::messages.offer_every_language') }}</span>
                                <span class="block text-[11px] text-muted-foreground leading-snug mt-0.5">{{ __('translation::messages.offer_every_language_note', ['count' => count(\App\Translation\Translations::locales()->all())]) }}</span>
                            </span>
                            <span class="w-6 h-6 rounded-full border-2 grid place-items-center flex-shrink-0"
                                  :class="offeredLimited ? 'border-gray-300' : 'border-transparent text-white'"
                                  :style="offeredLimited ? '' : 'background: {{ $c }}'">
                                <i class="bi bi-check text-xs" x-show="! offeredLimited"></i>
                            </span>
                        </button>

                        <template x-if="offeredLimited">
                            <div class="space-y-3">
                                {{-- Searchable, because sixty-eight rows is not
                                     a choice, it is a wall — the same reasoning
                                     as the reader's own sheet. --}}
                                <div class="rounded-2xl border-2 border-gray-200 overflow-hidden">
                                    <div class="relative border-b border-gray-100">
                                        <i class="bi bi-search absolute inset-inline-start-0 ms-4 top-1/2 -translate-y-1/2 text-xs text-muted-foreground"></i>
                                        <input type="text" x-model="pq"
                                               placeholder="{{ __('translation::messages.search_languages') }}"
                                               autocomplete="off" spellcheck="false"
                                               class="w-full ps-10 pe-4 py-3 text-sm outline-none">
                                    </div>
                                    <div style="max-height:340px; overflow-y:auto;">
                                        <template x-for="row in pickable()" :key="row.code">
                                            <button type="button" @click="togglePick(row.code)"
                                                    class="m-press w-full text-start px-4 py-2.5 flex items-center gap-3 border-b border-gray-50">
                                                <span class="w-5 h-5 rounded border-2 grid place-items-center flex-shrink-0"
                                                      :class="isPicked(row.code) ? 'border-transparent text-white' : 'border-gray-300'"
                                                      :style="isPicked(row.code) ? 'background: {{ $c }}' : ''">
                                                    <i class="bi bi-check text-[10px]" x-show="isPicked(row.code)"></i>
                                                </span>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block text-sm font-semibold text-foreground truncate" :dir="row.dir" x-text="row.native"></span>
                                                    <span class="block text-[11px] text-muted-foreground truncate" x-text="row.name"></span>
                                                </span>
                                                {{-- The language the organiser WROTE in is always offered:
                                                     its words are the event itself, not a translation of
                                                     it, and a poster that cannot be read in its own
                                                     language is not a setting anybody means. --}}
                                                <template x-if="row.code === sourceLocaleCode">
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-muted text-muted-foreground flex-shrink-0">{{ __('translation::messages.always') }}</span>
                                                </template>
                                                {{-- Written already, so un-ticking it hides rather than
                                                     wastes. Worth saying on the row. --}}
                                                <template x-if="row.code !== sourceLocaleCode && written.has(row.code)">
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-green-100 text-green-700 flex-shrink-0">{{ __('translation::messages.written') }}</span>
                                                </template>
                                            </button>
                                        </template>
                                        <p x-show="pickable().length === 0" class="px-4 py-6 text-center text-xs text-muted-foreground">
                                            {{ __('translation::messages.no_language_found') }}
                                        </p>
                                    </div>
                                </div>

                                <p class="text-[11.5px] text-muted-foreground leading-snug px-1">
                                    <i class="bi bi-info-circle me-1"></i>{{ __('translation::messages.hiding_keeps_words') }}
                                </p>
                            </div>
                        </template>

                        <button type="button" @click="savePoster()" :disabled="working"
                                class="m-press w-full rounded-2xl py-3.5 text-sm font-bold text-white disabled:opacity-50"
                                style="background: {{ $c }};">
                            <span x-show="! working">{{ __('translation::messages.save_poster_languages') }}</span>
                            <span x-show="working">…</span>
                        </button>
                    </div>

                    {{-- ── LEVEL 2 — one language, field by field ───────────── --}}
                    <div x-show="viewing && ! picking" x-cloak class="m-panel-in px-5 py-4 space-y-3">

                        {{-- Correcting a language nobody can currently read is a
                             reasonable thing to be doing — getting it right before
                             putting it up — but not something to discover
                             afterwards. --}}
                        <template x-if="viewing && ! onPoster(viewing.locale)">
                            <button type="button" @click="openPicker()"
                                    class="m-press w-full text-start rounded-xl bg-muted/50 border border-gray-200 px-3.5 py-3 flex items-center gap-2.5">
                                <i class="bi bi-eye-slash text-muted-foreground text-sm flex-shrink-0"></i>
                                <span class="text-[11.5px] leading-snug text-muted-foreground flex-1">{{ __('translation::messages.not_on_poster_note') }}</span>
                                <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
                            </button>
                        </template>

                        {{-- Whatever went wrong, said plainly, with the fix. --}}
                        <template x-if="viewing && viewing.status === 'failed'">
                            <div class="rounded-xl bg-amber-50 border border-amber-200 px-3.5 py-3 flex items-start gap-2.5">
                                <i class="bi bi-exclamation-triangle-fill text-amber-600 text-sm flex-shrink-0 mt-0.5"></i>
                                <p class="text-[11.5px] leading-snug text-amber-900"
                                   x-text="viewing.error || @js(__('translation::messages.no_provider'))"></p>
                            </div>
                        </template>

                        <template x-for="f in (viewing?.fields || [])" :key="f.field">
                            <div class="rounded-2xl border-2 border-gray-200 overflow-hidden">

                                {{-- The organiser's own words, on top. The whole
                                     screen is a comparison, so the source is
                                     never more than a glance away from the box
                                     being typed into. --}}
                                <div class="px-3.5 py-2.5 bg-muted/40">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground" x-text="f.label"></span>
                                        <span class="flex items-center gap-1.5 flex-shrink-0">
                                            <template x-if="f.origin === 'human'">
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-green-100 text-green-700">{{ __('translation::messages.edited_by_you') }}</span>
                                            </template>
                                            <template x-if="f.stale">
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-700"
                                                      title="{{ __('translation::messages.needs_review_note') }}">{{ __('translation::messages.needs_review') }}</span>
                                            </template>
                                        </span>
                                    </div>
                                    <p class="text-[12px] text-muted-foreground leading-snug mt-1 whitespace-pre-line" x-text="f.source"></p>
                                </div>

                                {{-- The translation, editable in place. Saved on
                                     blur rather than behind a Save button per
                                     field: forty fields would mean forty
                                     buttons, and an organiser correcting three
                                     of them should not have to find three. --}}
                                <textarea :dir="viewing.dir"
                                          x-model="f.value"
                                          @blur="save(f)"
                                          :placeholder="@js(__('translation::messages.not_translated'))"
                                          rows="2"
                                          class="w-full px-3.5 py-3 text-sm text-foreground outline-none resize-y"
                                          :class="f.saving ? 'opacity-50' : ''"></textarea>
                            </div>
                        </template>

                        {{-- The two acts that are not editing: do it again, or
                             take the language away. Destructive last, and
                             behind a confirmation, because it deletes the
                             organiser's OWN corrections too. --}}
                        <div class="flex items-center gap-2 pt-2">
                            <button type="button" @click="retranslate()" :disabled="working"
                                    class="m-press flex-1 rounded-xl border-2 border-gray-200 py-3 text-xs font-bold text-foreground disabled:opacity-50 flex items-center justify-center gap-1.5">
                                <i class="bi bi-arrow-clockwise"></i>{{ __('translation::messages.retranslate') }}
                            </button>
                            <button type="button" @click="remove()" :disabled="working"
                                    class="m-press rounded-xl border-2 border-red-200 text-red-600 px-4 py-3 text-xs font-bold disabled:opacity-50">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

@once
@push('scripts')
<script>
    /*
        Registered through Alpine.data(), NOT as a bare function.

        This component's markup lives inside <template x-teleport>, and a script
        written inside a teleported template is inert — the scope comes back
        EMPTY and every binding fails on its own, which reads as a dozen
        unrelated bugs. Both registration paths are covered because the shell
        may swap this page in after Alpine has already started.
    */
    (function () {
        const define = (Alpine) => Alpine.data('eventLanguagesAdmin', (eventUuid, sourceLocale) => ({
            open: false,
            loading: false,
            working: false,
            adding: false,
            q: '',
            languages: [],
            available: [],
            sourceName: '',
            viewing: null,
            _loaded: false,

            /* Which languages the POSTER offers. `offeredLimited` false means
               "all of them", which is the default and is NOT the same as a
               fully-ticked list — see the panel's own note. `offered` is only
               meaningful while limited. */
            picking: false,
            pq: '',
            offered: [],
            offeredLimited: false,
            sourceLocaleCode: sourceLocale,
            written: new Set(),

            get needsReview() {
                return this.languages.reduce((n, l) => n + (l.stale || 0), 0);
            },

            rowSub() {
                if (! this._loaded) return @js(__('translation::messages.languages_sub'));
                if (this.languages.length === 0) return @js(__('translation::messages.nothing_yet'));

                const names = this.languages.slice(0, 3).map(l => l.native).join(' · ');

                return this.languages.length > 3
                    ? names + ' +' + (this.languages.length - 3)
                    : names;
            },

            chipLabel(lang) {
                if (lang.status === 'preparing') return '…';
                if (lang.status === 'failed') return '!';
                if (lang.stale > 0) return @js(__('translation::messages.needs_review'));
                if (lang.human > 0) return @js(__('translation::messages.edited_by_you'));

                return @js(__('translation::messages.complete', ['done' => ':d', 'total' => ':t']))
                    .replace(':d', lang.translated).replace(':t', lang.total);
            },

            chipClass(lang) {
                if (lang.status === 'failed') return 'bg-red-100 text-red-700';
                if (lang.stale > 0) return 'bg-amber-100 text-amber-700';
                if (lang.human > 0) return 'bg-green-100 text-green-700';

                return 'bg-muted text-muted-foreground';
            },

            openSheet() {
                this.open = true;
                this.viewing = null;
                this.adding = false;
                if (! this._loaded) this.load();
            },

            /* Escape goes UP one level before it closes the sheet — inside a
               language, the expected exit is back to the list. */
            back() {
                if (this.picking) { this.picking = false; return; }
                this.viewing ? (this.viewing = null) : this.close();
            },

            close() {
                this.open = false;
                this.adding = false;
                this.picking = false;
            },

            async load(silent = false) {
                if (! silent) this.loading = true;
                try {
                    const res = await fetch(@js(route('me.events.translations', ['event' => $event])), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    if (! res.ok) throw new Error();
                    const d = await res.json();
                    this.languages = d.languages || [];
                    this.available = d.available || [];
                    this.sourceName = d.source_name || sourceLocale;
                    this.offered = d.offered || [];
                    this.offeredLimited = !! d.offered_limited;
                    // Which languages already HAVE words, so the checklist can
                    // say that un-ticking one hides rather than wastes.
                    this.written = new Set(this.languages.map(l => l.locale));
                    this._loaded = true;

                    // Keep the open panel pointed at the refreshed copy of the
                    // same language, not at a stale object.
                    if (this.viewing) {
                        this.viewing = this.languages.find(l => l.locale === this.viewing.locale) || null;
                    }
                } catch (e) {
                    window.showToast('error', @js(__('personal.event_show_action_failed')));
                } finally { this.loading = false; }
            },

            /* The closed row's summary. Counted from the EFFECTIVE list the
               server sent, never from the local ticks, so a row read before
               anything is saved says what the poster is actually doing. */
            posterSub() {
                if (! this._loaded) return '…';
                if (! this.offeredLimited) {
                    return @js(__('translation::messages.every_language', ['count' => ':n']))
                        .replace(':n', this.available.length);
                }

                return @js(__('translation::messages.n_languages', ['count' => ':n']))
                    .replace(':n', this.offered.length);
            },

            /* Whether a language is currently readable on the poster. Read off
               the EFFECTIVE list the server sent, so it is right in both modes
               — unrestricted, where everything is offered, and restricted,
               where `offered` is the organiser's own list plus the source. */
            onPoster(code) {
                return ! this._loaded || ! this.offeredLimited || this.offered.includes(code);
            },

            openPicker() {
                this.picking = true;
                this.viewing = null;
                this.adding = false;
                this.pq = '';
            },

            pickable() {
                const q = this.pq.trim().toLowerCase();

                return this.available.filter(r =>
                    ! q || r.name.toLowerCase().includes(q) || r.native.toLowerCase().includes(q)
                );
            },

            /* The source language reads as ticked and cannot be un-ticked. The
               server adds it back regardless (Translations::offered), so a box
               that appeared to turn it off would be a control that lies. */
            isPicked(code) {
                return code === sourceLocale || this.offered.includes(code);
            },

            togglePick(code) {
                if (code === sourceLocale) return;

                this.offered = this.offered.includes(code)
                    ? this.offered.filter(c => c !== code)
                    : [...this.offered, code];
            },

            async savePoster() {
                this.working = true;
                try {
                    const d = await this.request('PUT',
                        @js(route('me.events.translations.offered', ['event' => $event])),
                        { limited: this.offeredLimited, locales: this.offered });

                    this.offered = d.offered || [];
                    this.offeredLimited = !! d.offered_limited;
                    this.picking = false;
                    // request() has already raised the success toast.
                } catch (e) {
                    window.showToast('error', @js(__('personal.event_show_action_failed')));
                } finally { this.working = false; }
            },

            addable() {
                const have = new Set(this.languages.map(l => l.locale));
                const q = this.q.trim().toLowerCase();

                return this.available.filter(r =>
                    ! have.has(r.code) && r.code !== sourceLocale &&
                    (! q || r.name.toLowerCase().includes(q) || r.native.toLowerCase().includes(q))
                ).slice(0, 60);
            },

            async add(row) {
                this.adding = false;
                await this.post(@js(route('me.events.translations.retranslate', ['event' => $event])), { locale: row.code });
                await this.load(true);
            },

            /* Save one corrected field. Optimistic on the badge — the box the
               organiser just typed in must not flicker back to "Automatic"
               while a round trip happens. */
            async save(f) {
                const value = (f.value || '').trim();
                if (value === (f._saved ?? f.value ?? '') && f._saved !== undefined) return;

                f.saving = true;
                try {
                    const d = await this.put(@js(route('me.events.translations.update', ['event' => $event])), {
                        locale: this.viewing.locale,
                        field: f.field,
                        value: value,
                    });
                    f.origin = d.origin;
                    f.stale = false;
                    f._saved = value;
                } catch (e) {
                    window.showToast('error', e.message);
                } finally { f.saving = false; }
            },

            async retranslate() {
                if (this.working) return;
                this.working = true;
                try {
                    await this.post(@js(route('me.events.translations.retranslate', ['event' => $event])), { locale: this.viewing.locale });
                    // Give the worker a moment, then show what it wrote.
                    setTimeout(() => this.load(true), 4000);
                } catch (e) {
                    window.showToast('error', e.message);
                } finally { this.working = false; }
            },

            async remove() {
                const lang = this.viewing;
                if (! lang) return;

                const ok = await window.confirmAction({
                    title: @js(__('translation::messages.remove_language')),
                    message: @js(__('translation::messages.remove_language_confirm', ['language' => ':L'])).replace(':L', lang.native),
                    type: 'danger',
                });
                if (! ok) return;

                this.working = true;
                try {
                    await this.request('DELETE', @js(route('me.events.translations.destroy', ['event' => $event])), { locale: lang.locale });
                    this.languages = this.languages.filter(l => l.locale !== lang.locale);
                    this.viewing = null;
                } catch (e) {
                    window.showToast('error', e.message);
                } finally { this.working = false; }
            },

            post(url, body) { return this.request('POST', url, body); },
            put(url, body) { return this.request('PUT', url, body); },

            async request(method, url, body) {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));
                if (d.message) window.showToast('success', d.message);

                return d;
            },
        }));

        window.Alpine ? define(window.Alpine) : document.addEventListener('alpine:init', () => define(window.Alpine));
    })();
</script>
@endpush
@endonce
