{{--
    What this event's screens play.

    Six slots, one row each: the introduction music under the VS screen, the
    celebration over the winner's confetti, and a noise for one, two, three
    points and a penalty. Nothing is shipped with the app — every federation and
    club has its own, and the tracks we could ship we would not be licensed to.

    Owns its own uploads, its own removals and its own in-place patching, so it
    can be dropped into any console that manages an event. Files go to a private
    disk; the screens fetch them through their own token-authorised route, which
    is why nothing here ever holds a path.

    Audition is deliberate: an organiser setting a hall up needs to hear what
    they just uploaded, and one <audio> element is reused for all six so a second
    Play stops the first.
--}}
@props([
    'event',            // uuid
    'media' => [],      // slot => ['name' =>, 'bytes' =>, 'uploaded_at' =>]
    'audioUrl' => null, // optional: slot => url for auditioning
    'color' => '#7c5cff',
])

@php
    // Reaches a style attribute (the header band gradient) — whitelisted hex,
    // because `#rrggbb` + the `b0` alpha suffix is the only form that parses.
    $saColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c5cff';

    $slots = [
        'vs_music' => ['icon' => 'bi-music-note-beamed', 'label' => __('events.screen_audio_vs_music')],
        'winner_music' => ['icon' => 'bi-trophy', 'label' => __('events.screen_audio_winner_music')],
        'point_1' => ['icon' => 'bi-1-circle', 'label' => __('events.screen_audio_point_1')],
        'point_2' => ['icon' => 'bi-2-circle', 'label' => __('events.screen_audio_point_2')],
        'point_3' => ['icon' => 'bi-3-circle', 'label' => __('events.screen_audio_point_3')],
        'foul' => ['icon' => 'bi-exclamation-triangle', 'label' => __('events.screen_audio_foul')],
    ];
@endphp

{{-- Rendered as a SHEET, not a card. Sound is a setting you go and change once
     while setting a hall up, not a panel to read past on every visit — so it
     lives behind the gear on the hall-screens panel and opens on the
     `open-screen-audio` window event. Teleported to <body> so the mobile shell's
     transformed wrapper cannot become its containing block and clip it. --}}
<div
    x-data="eventScreenAudio({
        event: @js($event),
        media: @js((object) $media),
        audio: @js((object) ($audioUrl ?? [])),
    })"
    @open-screen-audio.window="open = true"
>
<template x-teleport="body">
<div x-show="open" x-cloak class="fixed inset-0" style="z-index:75" @keydown.escape.window="open = false">
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" @click="close()"></div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
         class="absolute inset-x-0 bottom-0 flex flex-col bg-white rounded-t-3xl shadow-2xl sm:mx-auto sm:max-w-lg"
         style="max-height:92vh">

        {{-- Header — the sheet's own band, with the drag handle and a way out. --}}
        <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
             style="background: linear-gradient(150deg, {{ $saColor }}, {{ $saColor }}b0);">
            <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
            <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

            <div class="relative flex items-start gap-3">
                <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                    <i class="bi bi-volume-up text-xl"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-lg font-black leading-tight">{{ __('events.screen_audio_title') }}</h3>
                    <p class="text-[12px] text-white/85 mt-0.5">{{ __('events.screen_audio_intro') }}</p>
                </div>
                <button type="button" @click="close()" aria-label="{{ __('shared.close') }}"
                        class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

    <div class="flex-1 min-h-0 overflow-y-auto divide-y divide-gray-100"
         style="padding-bottom: calc(0.5rem + env(safe-area-inset-bottom));">
        @foreach ($slots as $slot => $meta)
            <div class="px-5 py-3.5 flex items-center gap-3">
                <i class="bi {{ $meta['icon'] }} text-lg text-muted-foreground w-5 text-center flex-shrink-0"></i>

                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-900">{{ $meta['label'] }}</div>
                    {{-- The uploader's own filename, shown so they recognise what
                         is in the slot. Rendered with x-text, never innerHTML:
                         it is a name somebody typed. --}}
                    <div class="text-xs mt-0.5 truncate"
                         :class="media[@js($slot)] ? 'text-green-600' : 'text-muted-foreground'"
                         x-text="media[@js($slot)]
                            ? media[@js($slot)].name + ' · ' + size(media[@js($slot)].bytes)
                            : @js(__('events.screen_audio_none'))"></div>
                </div>

                <div class="flex items-center gap-1.5 flex-shrink-0">
                    <button type="button" x-show="audio[@js($slot)] && media[@js($slot)]"
                            @click="audition(@js($slot))"
                            class="w-9 h-9 rounded-lg grid place-items-center border border-border text-foreground hover:bg-accent transition-colors"
                            :title="@js(__('events.screen_audio_play'))">
                        <i class="bi" :class="playing === @js($slot) ? 'bi-stop-fill' : 'bi-play-fill'"></i>
                    </button>

                    {{-- h-9 + a minimum width, and centred: this is a 36px control
                         like the two round buttons beside it. Left to padding
                         alone it shrank to a badge the moment its label became
                         '…', and sat a few pixels short of the others always. --}}
                    <label class="h-9 min-w-[104px] px-3 inline-flex items-center justify-center gap-1.5 rounded-lg text-xs font-semibold whitespace-nowrap cursor-pointer transition-colors"
                           :class="busy === @js($slot) ? 'bg-muted text-muted-foreground' : 'bg-primary text-white hover:bg-primary/90'">
                        <i class="bi text-[13px]" :class="busy === @js($slot) ? 'bi-arrow-repeat animate-spin' : 'bi-upload'"></i>
                        <span x-text="busy === @js($slot)
                            ? '…'
                            : (media[@js($slot)] ? @js(__('events.screen_audio_replace')) : @js(__('events.screen_audio_choose')))"></span>
                        <input type="file" class="hidden" accept="audio/*"
                               @change="upload(@js($slot), $event.target)">
                    </label>

                    <button type="button" x-show="media[@js($slot)]" @click="remove(@js($slot))"
                            class="w-9 h-9 rounded-lg grid place-items-center border border-red-200 text-red-600 hover:bg-red-50 transition-colors"
                            :title="@js(__('events.screen_audio_remove'))">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
    </div>
</div>
</template>
</div>

@once
{{-- Deliberately INLINE, not @push('scripts') — pushed scripts land in
     #shell-scripts, OUTSIDE <main id="shell-content">, and the mobile shell
     navigator only re-runs scripts inside the swapped content. Pushed, this
     definition never arrived after an in-shell navigation: x-data then threw
     "eventScreenAudio is not defined", Alpine aborted the tree after clearing
     x-cloak, and the fixed sheet painted itself over the console the moment you
     arrived from the event page. Same note as event-documents / event-checklist. --}}
<script>
/**
 * One <audio> for all six slots, so auditioning a second sound stops the first —
 * an organiser checking their uploads should never end up with two tracks
 * playing over each other in a hall.
 *
 * Defined once per document; instantiated per instance. Guarded because a shell
 * swap re-executes this tag.
 */
window.eventScreenAudio = window.eventScreenAudio || function (config) {
    return {
        open: false,
        media: config.media || {},
        audio: config.audio || {},
        busy: null,
        playing: null,
        player: null,

        // Closing stops whatever is auditioning: a sound still playing from a
        // sheet nobody can see is the kind of thing that happens once, in a hall.
        close() {
            if (this.player) { this.player.pause(); this.player.currentTime = 0; }
            this.playing = null;
            this.open = false;
        },

        size(bytes) {
            if (!bytes) return '';
            return bytes < 1024 * 1024
                ? Math.max(1, Math.round(bytes / 1024)) + ' KB'
                : (bytes / 1024 / 1024).toFixed(1) + ' MB';
        },

        async upload(slot, input) {
            const file = input.files && input.files[0];
            if (!file) return;

            this.busy = slot;
            const body = new FormData();
            body.append('file', file);

            try {
                const res = await fetch(`/me/events/${config.event}/screen-audio/${slot}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                    body,
                });
                const data = await res.json().catch(() => ({}));

                if (!data.success) throw new Error(data.message || 'Upload failed');

                // In place, per the no-reload rule: the row re-renders from the
                // returned record rather than the page being loaded again.
                this.media[slot] = data.media;
                window.showToast && window.showToast('success', data.message);
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            } finally {
                this.busy = null;
                // So choosing the SAME file again still fires a change event.
                input.value = '';
            }
        },

        async remove(slot) {
            if (window.confirmAction && !(await window.confirmAction({
                title: @js(__('events.screen_audio_remove')),
                message: @js(__('events.screen_audio_intro')),
                type: 'danger',
            }))) return;

            this.busy = slot;

            try {
                const res = await fetch(`/me/events/${config.event}/screen-audio/${slot}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));

                if (!data.success) throw new Error(data.message || 'Failed');

                delete this.media[slot];
                if (this.playing === slot) this.stop();
                window.showToast && window.showToast('success', data.message);
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            } finally {
                this.busy = null;
            }
        },

        audition(slot) {
            if (this.playing === slot) { this.stop(); return; }

            const url = this.audio[slot];
            if (!url) return;

            this.stop();
            this.player = new Audio(url);
            this.player.addEventListener('ended', () => { this.playing = null; });
            this.player.play().then(() => { this.playing = slot; }).catch(() => { this.playing = null; });
        },

        stop() {
            if (this.player) { this.player.pause(); this.player = null; }
            this.playing = null;
        },
    };
};
</script>
@endonce
