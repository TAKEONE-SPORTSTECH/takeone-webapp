@props([
    /* The player this panel drives — its DOM id. */
    'playerId' => 'video-player',
    /* Rounds and grouped moments from App\Media\BoutTimeline. */
    'rounds' => [],
    'moments' => [],
    /* Whether the bout's recording had an anchor at all. */
    'anchored' => true,
    /* Coach notes, already presented. */
    'notes' => [],
    /* May this viewer write notes? */
    'canAnnotate' => false,
    'storeUrl' => null,
    'color' => '#7c6bf5',
])

@php
    $accent = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $color) ? $color : '#7c6bf5';
@endphp

{{--
    The highlights bar.

    Every row here was DERIVED — the mat console logged it and the recording's
    anchor placed it. Nobody typed a timestamp, which is the whole point: the
    officiating sheet and the video can never disagree, because there is only one
    of them.

    Clicking a moment does not seek to it. It replays it, the way a broadcast
    would: in from a second and a half before, then again at half speed. See
    `replay()` on the player for why.

    Notes are the other half — the things a coach says that no console knows.
--}}
<div x-data="takeoneBoutHighlights({
        playerId: @js($playerId),
        moments: @js(array_values($moments)),
        rounds: @js(array_values($rounds)),
        notes: @js(array_values($notes)),
        canAnnotate: @js((bool) $canAnnotate),
        storeUrl: @js($storeUrl),
     })"
     x-init="boot()"
     class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col"
     {{ $attributes }}>

    {{-- Tabs --}}
    <div class="flex-shrink-0 flex items-center gap-1 p-1.5 bg-muted/50 border-b border-gray-100">
        <button type="button" @click="tab = 'points'"
                class="flex-1 h-9 rounded-xl text-[12px] font-black uppercase tracking-wide transition-colors flex items-center justify-center gap-1.5"
                :class="tab === 'points' ? 'bg-white shadow-sm text-foreground' : 'text-muted-foreground'">
            <i class="bi bi-lightning-charge-fill"></i>
            {{ __('events.bout_video_tab_points') }}
            <span class="px-1.5 rounded-full bg-primary/12 text-primary text-[10px]" x-text="moments.length"></span>
        </button>
        <button type="button" @click="tab = 'notes'"
                class="flex-1 h-9 rounded-xl text-[12px] font-black uppercase tracking-wide transition-colors flex items-center justify-center gap-1.5"
                :class="tab === 'notes' ? 'bg-white shadow-sm text-foreground' : 'text-muted-foreground'">
            <i class="bi bi-chat-square-quote-fill"></i>
            {{ __('events.bout_video_tab_notes') }}
            <span class="px-1.5 rounded-full bg-amber-500/15 text-amber-600 text-[10px]" x-text="notes.length"></span>
        </button>
    </div>

    {{-- ── Points ──────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'points'" class="flex-1 overflow-y-auto min-h-0">
        <template x-if="!moments.length">
            <div class="px-5 py-10 text-center">
                <span class="w-14 h-14 mx-auto rounded-2xl bg-muted grid place-items-center text-muted-foreground">
                    <i class="bi bi-lightning-charge text-2xl"></i>
                </span>
                <p class="text-sm font-bold text-foreground mt-3" x-text="anchored
                    ? @js(__('events.bout_video_no_points'))
                    : @js(__('events.bout_video_no_anchor'))"></p>
                <p class="text-xs text-muted-foreground mt-1" x-text="anchored
                    ? @js(__('events.bout_video_no_points_sub'))
                    : @js(__('events.bout_video_no_anchor_sub'))"></p>
            </div>
        </template>

        <template x-for="round in rounds" :key="round.number">
            <div>
                {{-- A round header is a jump, plainly: no replay, just go there. --}}
                <button type="button" @click="jump(round.start)"
                        class="w-full sticky top-0 z-10 flex items-center gap-2 px-4 py-2 bg-white/95 backdrop-blur border-y border-gray-100 text-start">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wide text-white"
                          :style="`background:{{ $accent }}`" x-text="round.name"></span>
                    <span class="text-[11px] text-muted-foreground" x-text="round.moments + ' ' + @js(__('events.bout_video_moments'))"></span>
                    <span class="flex-1"></span>
                    <span class="text-[11px] font-bold tabular-nums text-muted-foreground" x-text="clock(round.start)"></span>
                </button>

                <template x-for="(m, i) in momentsOf(round.number)" :key="round.number + '-' + i">
                    <button type="button" @click="playMoment(m)"
                            class="w-full grid items-center gap-2 px-4 py-2.5 text-start transition-colors hover:bg-muted/60 active:bg-muted"
                            :class="isNow(m) && 'bg-primary/5'"
                            style="grid-template-columns: 44px 4px 1fr auto;">
                        <span class="text-[11px] font-bold tabular-nums text-muted-foreground" x-text="m.clock"></span>

                        <span class="h-7 rounded-full"
                              :class="m.side === 'red' ? 'bg-red-500'
                                    : (m.side === 'blue' ? 'bg-sky-500'
                                    : 'bg-gradient-to-b from-red-500 from-50% to-sky-500 to-50%')"></span>

                        <span class="min-w-0">
                            <span class="block text-[13px] font-bold text-foreground truncate" x-text="m.label"></span>
                            <span class="block text-[11px] text-muted-foreground truncate" x-text="m.who"></span>
                        </span>

                        <span class="text-[13px] font-black tabular-nums whitespace-nowrap">
                            <span class="text-red-500" x-text="m.score_red"></span>
                            <span class="text-muted-foreground/50 mx-0.5">–</span>
                            <span class="text-sky-500" x-text="m.score_blue"></span>
                        </span>
                    </button>
                </template>
            </div>
        </template>
    </div>

    {{-- ── Coach notes ─────────────────────────────────────────────────── --}}
    <div x-show="tab === 'notes'" x-cloak class="flex-1 overflow-y-auto min-h-0">
        <template x-if="!notes.length">
            <div class="px-5 py-10 text-center">
                <span class="w-14 h-14 mx-auto rounded-2xl bg-muted grid place-items-center text-muted-foreground">
                    <i class="bi bi-chat-square-quote text-2xl"></i>
                </span>
                <p class="text-sm font-bold text-foreground mt-3">{{ __('events.bout_video_no_notes') }}</p>
                <p class="text-xs text-muted-foreground mt-1">{{ __('events.bout_video_no_notes_sub') }}</p>
            </div>
        </template>

        <template x-for="n in notes" :key="n.uuid">
            <div class="group/note flex items-start gap-3 px-4 py-3 border-b border-gray-50 last:border-0 transition-colors hover:bg-muted/40">
                <button type="button" @click="playNote(n)" class="flex items-start gap-3 flex-1 min-w-0 text-start">
                    <span class="w-10 h-10 rounded-2xl bg-amber-500/12 grid place-items-center flex-shrink-0 text-lg" x-text="n.emoji"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[11px] font-bold tabular-nums text-muted-foreground">
                            <span x-text="clock(n.start)"></span>
                            <template x-if="n.end !== null"><span> — <span x-text="clock(n.end)"></span></span></template>
                        </span>
                        <span class="block text-[13px] text-foreground leading-snug mt-0.5" x-text="n.note"></span>
                        <span class="block text-[11px] text-muted-foreground mt-1" x-text="n.coach"></span>
                    </span>
                </button>

                <template x-if="canAnnotate">
                    <div class="flex items-center gap-1 flex-shrink-0 opacity-0 group-hover/note:opacity-100 focus-within:opacity-100 transition-opacity">
                        <button type="button" @click="edit(n)" class="w-8 h-8 rounded-lg grid place-items-center text-muted-foreground hover:bg-muted"
                                :aria-label="@js(__('shared.edit'))"><i class="bi bi-pencil text-xs"></i></button>
                        <button type="button" @click="remove(n)" class="w-8 h-8 rounded-lg grid place-items-center text-red-500 hover:bg-red-50"
                                :aria-label="@js(__('shared.delete'))"><i class="bi bi-trash text-xs"></i></button>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="canAnnotate">
            <div class="p-3">
                <button type="button" @click="compose()"
                        class="m-press w-full h-11 rounded-xl border-2 border-dashed border-border text-sm font-bold text-muted-foreground hover:border-primary hover:text-primary transition-colors flex items-center justify-center gap-2">
                    <i class="bi bi-plus-lg"></i>{{ __('events.bout_video_add_note') }}
                </button>
            </div>
        </template>
    </div>

    {{-- ── Note editor: a bottom sheet, per the sheet rules ────────────── --}}
    <template x-teleport="body">
        <div x-show="sheet" x-cloak class="fixed inset-0 z-[70]" role="dialog" aria-modal="true">
            <div x-show="sheet" x-transition.opacity class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="sheet = false"></div>

            <div x-show="sheet"
                 x-transition:enter="transition ease-out duration-250"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl sm:max-w-lg sm:mx-auto">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #b45309, #d97706b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-chat-square-quote-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight" x-text="form.uuid
                                ? @js(__('events.bout_video_edit_note'))
                                : @js(__('events.bout_video_add_note'))"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('events.bout_video_note_sheet_sub') }}</p>
                        </div>
                        <button type="button" @click="sheet = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-5 space-y-4">
                    {{-- The moment. Taken from the playhead, so a coach marks by
                         watching rather than by typing a number. --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('events.bout_video_note_when') }}</label>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="form.start = playerTime(); if (form.end !== null && form.end < form.start) form.end = null"
                                    class="flex-1 h-11 rounded-xl border border-gray-200 bg-white px-3 flex items-center gap-2 text-sm font-bold hover:border-primary transition-colors">
                                <i class="bi bi-crosshair text-primary"></i>
                                <span x-text="clock(form.start)"></span>
                                <span class="flex-1 text-end text-[11px] font-normal text-muted-foreground">{{ __('events.bout_video_note_use_playhead') }}</span>
                            </button>
                        </div>
                        <div class="flex items-center gap-2 mt-2">
                            <button type="button" @click="form.end = Math.max(form.start, playerTime())"
                                    class="flex-1 h-10 rounded-xl border border-gray-200 bg-white px-3 flex items-center gap-2 text-[13px] font-semibold hover:border-primary transition-colors">
                                <i class="bi bi-arrow-bar-right text-muted-foreground"></i>
                                <span x-text="form.end === null ? @js(__('events.bout_video_note_no_end')) : clock(form.end)"></span>
                            </button>
                            <button type="button" x-show="form.end !== null" @click="form.end = null"
                                    class="h-10 px-3 rounded-xl border border-gray-200 text-[13px] font-semibold text-muted-foreground hover:bg-muted">
                                {{ __('shared.clear') }}
                            </button>
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-1.5">{{ __('events.bout_video_note_range_hint') }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('events.bout_video_note_mood') }}</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach (\App\Models\BoutCoachNote::EMOJI as $e)
                                <button type="button" @click="form.emoji = @js($e)"
                                        class="w-11 h-11 rounded-2xl border-2 grid place-items-center text-xl transition-colors"
                                        :class="form.emoji === @js($e) ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'">{{ $e }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('events.bout_video_note_text') }}</label>
                        <textarea x-model="form.note" rows="3" maxlength="1000"
                                  class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                                  placeholder="{{ __('events.bout_video_note_placeholder') }}"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('events.bout_video_note_coach') }}</label>
                        <input type="text" x-model="form.coach" maxlength="100"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                               placeholder="{{ __('events.bout_video_note_coach_placeholder') }}">
                        <p class="text-[11px] text-muted-foreground mt-1.5">{{ __('events.bout_video_note_coach_hint') }}</p>
                    </div>
                </div>

                <div class="flex-shrink-0 border-t border-gray-100 bg-white px-5 pt-3 flex gap-2"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="save()" :disabled="saving"
                            class="flex-1 h-12 rounded-xl bg-primary text-white font-bold text-sm disabled:opacity-60 transition-opacity">
                        <span x-show="!saving">{{ __('shared.save') }}</span>
                        <span x-show="saving" x-cloak>{{ __('shared.saving') }}…</span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

@once
    @push('scripts')
    <script>
    window.takeoneBoutHighlights = function (cfg) {
        return {
            tab: 'points',
            moments: cfg.moments || [],
            rounds: cfg.rounds || [],
            notes: cfg.notes || [],
            anchored: true,
            canAnnotate: !!cfg.canAnnotate,
            storeUrl: cfg.storeUrl,

            sheet: false, saving: false,
            form: { uuid: null, start: 0, end: null, emoji: '🔥', note: '', coach: '' },
            now: 0,

            boot() {
                // Paint the scrubber's ticks as soon as there IS a player. It may
                // mount after us, so we wait for its own ready event rather than
                // assuming an order.
                const wire = () => {
                    const p = this.player();
                    if (!p) return false;
                    p.setMarks(this.moments.map((m) => ({ t: m.t, side: m.side })));
                    return true;
                };

                if (!wire()) {
                    document.addEventListener('player:ready', wire, { once: true });
                }

                // Follow the playhead so the list can highlight where we are.
                // Stored on the instance and removed on teardown — the mobile
                // shell re-runs inline scripts on every navigation and these
                // would otherwise stack up.
                this._onTime = (e) => { this.now = e.detail.t; };
                document.addEventListener('player:time', this._onTime);
            },

            player() {
                const el = document.getElementById(cfg.playerId);
                return el && el._player ? el._player : null;
            },

            playerTime() {
                return Math.max(0, Math.round((this.player()?.now ?? 0) * 100) / 100);
            },

            momentsOf(round) { return this.moments.filter((m) => m.round === round); },

            isNow(m) { return this.now >= m.t - 1.5 && this.now <= m.t + 2.5; },

            playMoment(m) { this.player()?.replay(m.t); },

            jump(t) { this.player()?.playFrom(t || 0); },

            /*
             * A note plays as the coach meant it: a range replays at full speed
             * then half, a point-in-time note simply plays with the caption up
             * for six seconds. Either way the caption sits where it was placed.
             */
            playNote(n) {
                const p = this.player();
                if (!p) return;
                p.showCaption((n.emoji ? n.emoji + '  ' : '') + n.note, n.x, n.y);

                if (n.end !== null && n.end > n.start) {
                    p.playRange(n.start, n.end, { slow: true });
                } else {
                    p.playFrom(n.start);
                    const until = n.start + 6;
                    const off = (e) => {
                        if (e.detail.t >= n.start - 0.75 && e.detail.t < until) return;
                        p.hideCaption();
                        document.removeEventListener('player:time', off);
                    };
                    document.addEventListener('player:time', off);
                }
            },

            compose() {
                this.form = {
                    uuid: null,
                    start: this.playerTime(),
                    end: null,
                    emoji: '🔥',
                    note: '',
                    coach: this.form.coach || '',
                };
                this.player()?.$refs?.video?.pause();
                this.sheet = true;
            },

            edit(n) {
                this.form = { uuid: n.uuid, start: n.start, end: n.end, emoji: n.emoji, note: n.note, coach: n.coach };
                this.sheet = true;
            },

            async save() {
                if (!this.form.note.trim()) { window.showToast('error', @js(__('events.bout_video_note_needs_text'))); return; }
                if (!this.form.coach.trim()) { window.showToast('error', @js(__('events.bout_video_note_needs_coach'))); return; }
                if (this.form.end !== null && this.form.end < this.form.start) {
                    window.showToast('error', @js(__('events.bout_video_note_bad_range'))); return;
                }

                this.saving = true;
                const url = this.form.uuid ? `${this.storeUrl}/${this.form.uuid}` : this.storeUrl;

                try {
                    const res = await fetch(url, {
                        method: this.form.uuid ? 'PUT' : 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify({
                            start_seconds: this.form.start,
                            end_seconds: this.form.end,
                            emoji: this.form.emoji,
                            note: this.form.note,
                            coach_name: this.form.coach,
                            // Where the caption sits. Centred low by default,
                            // which is where a subtitle belongs.
                            position_x: 0.5,
                            position_y: 0.82,
                        }),
                    });
                    const data = await res.json();

                    if (!data.success) { window.showToast('error', data.message || 'Error'); return; }

                    // Patch in place — no reload, per the platform rule.
                    const i = this.notes.findIndex((x) => x.uuid === data.note.uuid);
                    if (i >= 0) this.notes.splice(i, 1, data.note);
                    else this.notes.push(data.note);
                    this.notes.sort((a, b) => a.start - b.start);

                    this.sheet = false;
                    window.showToast('success', data.message);
                } catch (e) {
                    window.showToast('error', @js(__('shared.something_went_wrong')));
                } finally {
                    this.saving = false;
                }
            },

            async remove(n) {
                const ok = await window.confirmAction({
                    title: @js(__('events.bout_video_delete_note')),
                    message: @js(__('events.bout_video_delete_note_sure')),
                    type: 'danger',
                    confirmText: @js(__('shared.delete')),
                });
                if (!ok) return;

                try {
                    const res = await fetch(`${this.storeUrl}/${n.uuid}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                    });
                    const data = await res.json();
                    if (!data.success) { window.showToast('error', data.message || 'Error'); return; }

                    this.notes = this.notes.filter((x) => x.uuid !== data.uuid);
                    window.showToast('success', data.message);
                } catch (e) {
                    window.showToast('error', @js(__('shared.something_went_wrong')));
                }
            },

            clock(s) {
                if (s === null || s === undefined || !isFinite(s)) return '—';
                const w = Math.max(0, Math.floor(s));
                return String(Math.floor(w / 60)).padStart(2, '0') + ':' + String(w % 60).padStart(2, '0');
            },
        };
    };
    </script>
    @endpush
@endonce
