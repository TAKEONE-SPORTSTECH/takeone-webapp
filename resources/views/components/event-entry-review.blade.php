@props([
    'event',              // event uuid
    'entries' => [],      // App\Events\Support\PublicEntry::pending()
    'color' => '#7c3aed',
    'title' => '',
])

{{--
    The organiser's queue: strangers who followed the public link and asked to
    compete.

    Documentation/EVENTS-PUBLIC-ENTRY.md, Phase C. Accepting one is the moment a
    request becomes an entry — until then it counts toward no entrant total, no
    capacity and no money. So this is the GATE, and the card says so rather than
    reading like an inbox.

    Standalone per the component contract: it owns its Alpine state, its own
    requests, its own sheet, and patches its own list in place (No-Reload). It
    listens on `realtime:events` for a refresh signal so a second organiser's
    decision does not leave a stale row here, and re-fetches rather than
    trusting a pushed payload — what each viewer may see differs.

    Drop it into either console with no page glue. Renders nothing at all when
    the event has no public page and nobody is waiting.
--}}

@php
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';
@endphp

<div x-data="{
        open: false,
        rows: @js(array_values($entries)),
        busy: null,
        loading: false,

        get count() { return this.rows.length },

        async refresh() {
            if (this.loading) return;
            this.loading = true;
            try {
                const res = await fetch(@js(route('me.events.public-entries', $event)), {
                    headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
                });
                const d = await res.json().catch(() => ({}));
                if (res.ok && d.success) this.rows = d.pending || [];
            } catch (e) { /* a failed refresh leaves the list as it was */ }
            finally { this.loading = false }
        },

        async decide(uuid, verdict) {
            if (this.busy) return;

            if (verdict === 'decline') {
                const ok = await window.confirmAction({
                    title: @js(__('events.public_review_decline_confirm')),
                    message: @js(__('events.public_review_decline_confirm_body')),
                    type: 'danger',
                    confirmText: @js(__('events.public_review_decline')),
                });
                if (! ok) return;
            }

            this.busy = uuid;
            try {
                const url = verdict === 'accept'
                    ? @js(route('me.events.public-entries.accept', ['event' => $event, 'entry' => '__U__']))
                    : @js(route('me.events.public-entries.decline', ['event' => $event, 'entry' => '__U__']));

                const res = await fetch(url.replace('__U__', uuid), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));

                // Out of the queue, in place.
                this.rows = this.rows.filter(r => r.uuid !== uuid);
                window.showToast('success', d.message);

                // The entrant count moved on every other panel of this console.
                if (typeof d.going === 'number') {
                    window.dispatchEvent(new CustomEvent('event-entrants-changed', { detail: { going: d.going } }));
                }
            } catch (e) {
                window.showToast('error', e.message);
            } finally { this.busy = null }
        },
     }"
     x-init="
        // Deduped: the mobile shell re-runs inline scripts on every AJAX nav,
        // so an undeduped listener stacks up one copy per visit.
        window.removeEventListener('realtime:events', window.__eventEntryReview);
        window.__eventEntryReview = (ev) => { if (ev.detail?.event === @js($event)) refresh(); };
        window.addEventListener('realtime:events', window.__eventEntryReview);
     "
     x-show="count > 0" x-cloak>

    {{-- the row in the console --}}
    <button type="button" @click="open = true"
            class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 text-white relative"
              style="background: {{ $c }}">
            <i class="bi bi-person-plus-fill text-lg"></i>
            <span class="absolute -top-1 -end-1 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-black grid place-items-center"
                  x-text="count"></span>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ __('events.public_review') }}</span>
            <span class="block text-[11px] mt-0.5 text-amber-600 font-semibold"
                  x-text="@js(__('events.public_review_waiting', ['n' => ':n'])).replace(':n', count)"></span>
        </span>
        <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
    </button>

    {{-- Teleported: the mobile shell leaves a transform on its children, which
         would make a fixed sheet resolve against a wrapper and clip it. --}}
    <template x-teleport="body" data-teleport-template="true">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end sm:items-center sm:justify-center sm:p-4"
             @keydown.escape.window="open = false" style="display:none;">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="open = false"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0" x-transition:enter-end="translate-y-0 sm:opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 sm:opacity-100" x-transition:leave-end="translate-y-full sm:translate-y-4 sm:opacity-0"
                 class="relative w-full sm:max-w-lg max-h-[92vh] flex flex-col bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl">

                {{-- gradient header band (Design Rule #8) --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl sm:rounded-t-2xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-person-plus-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events.public_review') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $title }}</p>
                        </div>
                        <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="relative mt-3 flex flex-wrap gap-1.5">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold"
                              x-text="@js(__('events.public_review_waiting', ['n' => ':n'])).replace(':n', count)"></span>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-2.5"
                     style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">

                    <template x-if="count === 0">
                        <div class="text-center py-10">
                            <i class="bi bi-inbox text-3xl text-muted-foreground/40"></i>
                            <p class="text-sm font-bold text-foreground mt-3">{{ __('events.public_review_none') }}</p>
                            <p class="text-[12px] text-muted-foreground mt-1">{{ __('events.public_review_none_hint') }}</p>
                        </div>
                    </template>

                    <template x-for="r in rows" :key="r.uuid">
                        <div class="rounded-2xl border border-gray-200 p-3.5">
                            <div class="flex items-start gap-3">
                                <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 text-sm font-black"
                                      style="background: {{ $c }}1f; color: {{ $c }}"
                                      x-text="(r.name || '?').charAt(0)"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-black text-foreground truncate" x-text="r.name"></p>
                                    {{-- The address decides nothing, but whether
                                         it is verified is the one signal that
                                         separates a person from a script. --}}
                                    <p class="text-[11px] text-muted-foreground truncate" x-text="r.email"></p>
                                    <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold"
                                              :class="r.verified ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">
                                            <i class="bi" :class="r.verified ? 'bi-patch-check-fill' : 'bi-exclamation-circle-fill'"></i>
                                            <span x-text="r.verified ? @js(__('events.public_review_verified')) : @js(__('events.public_review_unverified'))"></span>
                                        </span>
                                        <template x-if="r.age !== null">
                                            <span class="px-2 py-0.5 rounded-full bg-muted text-[10px] font-bold text-muted-foreground"
                                                  x-text="r.age + ' · ' + (r.gender || '')"></span>
                                        </template>
                                        <span class="px-2 py-0.5 rounded-full bg-muted text-[10px] font-bold text-muted-foreground"
                                              x-text="r.weight ? (r.weight + ' kg') : @js(__('events.public_review_no_weight'))"></span>
                                        <template x-if="r.belt">
                                            <span class="px-2 py-0.5 rounded-full bg-muted text-[10px] font-bold text-muted-foreground capitalize"
                                                  x-text="r.belt"></span>
                                        </template>
                                    </div>
                                    <p class="text-[10.5px] text-muted-foreground/70 mt-1.5"
                                       x-text="@js(__('events.public_review_asked', ['when' => ':w'])).replace(':w', r.asked || '')"></p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 mt-3">
                                <button type="button" @click="decide(r.uuid, 'decline')" :disabled="busy === r.uuid"
                                        class="m-press py-2.5 rounded-xl border border-red-200 text-red-600 text-[12px] font-bold disabled:opacity-50">
                                    <i class="bi bi-x-lg me-1"></i>{{ __('events.public_review_decline') }}
                                </button>
                                <button type="button" @click="decide(r.uuid, 'accept')" :disabled="busy === r.uuid"
                                        class="m-press py-2.5 rounded-xl text-white text-[12px] font-bold disabled:opacity-50"
                                        style="background: {{ $c }}">
                                    <i class="bi bi-check-lg me-1"></i>{{ __('events.public_review_accept') }}
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
