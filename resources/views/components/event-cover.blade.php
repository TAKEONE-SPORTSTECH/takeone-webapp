@props([
    'event',                 // event uuid
    'photo' => null,         // current cover URL, or null
    'color' => '#7c3aed',
    'title' => '',
    'inline' => false,       // true on mobile: the cropper opens as a bottom sheet
])

{{--
    The picture the public cover opens onto — one row of the event console.

    The event's public page opens on a full-bleed poster (see
    `entry/public/partials/cover`), and that poster is `images[0]`. This is
    where the organiser sets it, sitting beside the switch that publishes the
    page — the two decisions belong together: there is no point choosing a face
    for a page nobody may open.

    Standalone per the component contract: it owns its Alpine state, its
    requests, its sheet and its own in-place patching, and it can be dropped
    into either console with no page glue. It reuses the SHARED cropper
    (`<x-takeone-cropper>`) rather than carrying an uploader of its own —
    self-contained means owning the decision, not duplicating the design system.

    The preview is the real thing at a small size: the picture, the same scrim,
    the same type block. A thumbnail of a photograph would not answer the only
    question the organiser has, which is "what will somebody see when they open
    my link".
--}}

@php
    /* Organiser-supplied and heading for a style attribute, so it is
       whitelisted here rather than trusted. */
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';
    $cropperId = 'eventCover'.Str::of($event)->replace('-', '')->limit(12, '');
@endphp

<div x-data="{
        open: false,
        photo: @js($photo),
        busy: false,

        /* The shared cropper posts to our endpoint and announces the result on
           `document`. We listen for it rather than reaching into the widget. */
        onUploaded(detail) {
            if (! detail || ! detail.url) return;
            this.photo = detail.url;
            this.announce();
        },

        async remove() {
            if (this.busy) return;
            if (! await window.confirmAction({
                title: @js(__('events.cover_remove_title')),
                message: @js(__('events.cover_remove_body')),
                type: 'danger',
                confirmText: @js(__('events.cover_remove_confirm')),
            })) return;

            this.busy = true;
            try {
                const res = await fetch(@js(route('me.events.cover.destroy', $event)), {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (! res.ok || ! data.success) throw new Error(data.message || '');
                this.photo = null;
                this.announce();
                window.showToast('success', data.message);
            } catch (e) {
                window.showToast('error', e.message || @js(__('events.cover_failed')));
            } finally {
                this.busy = false;
            }
        },

        /* Anything else on the page showing this event's face can follow along
           (No-Reload rule §2). */
        announce() {
            window.dispatchEvent(new CustomEvent('event-cover-changed', {
                detail: { event: @js($event), photo: this.photo },
            }));
        },
     }"
     x-init="(() => {
        /* The shared cropper announces `imageUploaded` — camelCase, which an
           HTML attribute cannot express (the browser lowercases it), so this is
           registered in JS rather than as `@imageuploaded.document`.

           It also self-cleans: the console is a shell-swapped page, so a
           listener left on `document` would outlive its component and hold
           stale state. The first event after this element leaves the document
           removes it (CLAUDE.md → dedup persistent listeners).

           ⚠️ WRAPPED IN AN IIFE, and it has to be. Alpine evaluates x-init
           through the same evaluator as any expression — `__self.result = <the
           attribute>` — so a bare `const` here is a SyntaxError. Alpine papers
           over that with a regex, but only when the attribute STARTS with
           `let`/`const`; this one starts with a comment, so the regex missed
           it, the whole x-init threw `Unexpected token 'const'` and the cover
           uploader silently never heard back from the cropper (found in the
           browser console 2026-09-06). An IIFE is an expression, so it is
           valid whatever is inside it. */
        const onCoverUploaded = (e) => {
            if (! document.body.contains($el)) {
                document.removeEventListener('imageUploaded', onCoverUploaded);
                return;
            }
            onUploaded(e.detail);
        };
        document.addEventListener('imageUploaded', onCoverUploaded);
     })()">

    {{-- ===== The row ===== --}}
    <button type="button" @click="open = true"
            class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
        {{-- The tile IS the current picture when there is one, so the row
             answers its own question without being opened. --}}
        <span class="w-11 h-11 rounded-2xl overflow-hidden grid place-items-center flex-shrink-0"
              :class="photo ? '' : 'bg-accent text-primary'"
              :style="photo ? '' : ''">
            {{-- See the note in personal/event-people: a file that has gone must
                 fall back to the empty state, never to a broken-image glyph. --}}
            <template x-if="photo">
                <img :src="photo" alt="" x-on:error="photo = null" class="w-full h-full object-cover">
            </template>
            <template x-if="! photo">
                <i class="bi bi-image-fill text-lg"></i>
            </template>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ __('events.cover_title') }}</span>
            <span class="block text-[11px] text-muted-foreground mt-0.5"
                  x-text="photo ? @js(__('events.cover_sub_set')) : @js(__('events.cover_sub_none'))"></span>
        </span>
        <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
    </button>

    {{-- ===== The sheet ===== --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[60]" @keydown.escape.window="open = false">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50 backdrop-blur-sm"
                 @click="open = false"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-background rounded-t-3xl overflow-hidden shadow-2xl">

                {{-- Gradient header band (Design Rule #8). The alpha suffix is
                     hex-only — `b0` on a #hex, never on an hsl(). --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-image-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events.cover_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('events.cover_sheet_sub') }}</p>
                        </div>
                        <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- Body --}}
                <div class="flex-1 overflow-y-auto px-5 py-5"
                     style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));">

                    {{-- ===== What a visitor will actually see =====
                         The real composition at a small size: picture, scrim,
                         and the same type block the cover draws. --}}
                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2">{{ __('events.cover_preview') }}</p>

                    <div class="relative mx-auto rounded-2xl overflow-hidden border border-gray-200 shadow-sm"
                         style="width: 172px; aspect-ratio: 390 / 844; background: #070b14;">
                        {{-- The same composition the cover draws: the poster
                             blurred to fill the ground, then the poster whole
                             and centred with gutters. A preview that cropped
                             differently would misreport the result. --}}
                        <template x-if="photo">
                            <img :src="photo" alt="" aria-hidden="true" x-on:error="photo = null"
                                 class="absolute inset-0 w-full h-full object-cover scale-[1.35]"
                                 style="filter: blur(11px) saturate(1.5);">
                        </template>
                        <template x-if="photo">
                            <div class="absolute inset-0 px-1.5 pt-2 pb-14 grid place-items-center">
                                <img :src="photo" alt="" x-on:error="photo = null"
                                     class="max-w-full max-h-full w-auto h-auto object-contain rounded-md">
                            </div>
                        </template>
                        <template x-if="! photo">
                            <div class="absolute inset-0"
                                 style="background:
                                    radial-gradient(120% 80% at 82% -10%, {{ $c }}cc 0%, {{ $c }}44 42%, transparent 72%),
                                    radial-gradient(90% 70% at -15% 42%, {{ $c }}66 0%, transparent 68%);"></div>
                        </template>

                        <div class="absolute inset-0"
                             style="background: linear-gradient(180deg, rgba(7,11,20,0) 0%, rgba(7,11,20,.10) 34%, rgba(7,11,20,.72) 64%, rgba(7,11,20,.96) 88%, rgba(7,11,20,.99) 100%);"></div>

                        <div class="absolute inset-x-0 bottom-0 p-2.5 text-white">
                            <span class="block h-[3px] w-4 rounded-full mb-1.5" style="background: {{ $c }};"></span>
                            <p class="text-[7.5px] font-bold leading-tight">{{ Str::limit($title, 42) }}</p>
                            <span class="mt-1.5 block h-3.5 rounded-[3px]" style="background: {{ $c }};"></span>
                        </div>
                    </div>

                    <p class="text-[11.5px] text-muted-foreground text-center mt-3 leading-relaxed">
                        {{ __('events.cover_hint') }}
                    </p>

                    {{-- ===== Choose the picture — the SHARED cropper =====
                         3:4 at 1200×1600: the shape a competition poster comes
                         in, and the shape the cover crops from. --}}
                    <div class="mt-5">
                        <x-takeone-cropper
                            :id="$cropperId"
                            mode="ajax"
                            :inline="$inline"
                            :width="1200" :height="1600" shape="rectangle" :canvasHeight="300"
                            folder="event-cover" :filename="'cover'"
                            :uploadUrl="route('me.events.cover.store', $event)"
                            :buttonText="__('events.cover_choose')"
                            button-class="w-full rounded-xl px-4 py-3 text-white text-sm font-bold flex items-center justify-center gap-2"
                            :showControls="false"
                            :showCancel="false"
                            sheetMaxWidth="100%"
                            sheetClass="rounded-t-3xl shadow-2xl bg-background"
                            :saveText="__('events.cover_save')"
                            :uploadAsIs="true"
                            :uploadAsIsText="__('events.cover_upload_as_is')" />
                    </div>

                    {{-- ===== Take it off ===== --}}
                    <template x-if="photo">
                        <button type="button" @click="remove()" :disabled="busy"
                                class="m-press w-full mt-2.5 rounded-xl px-4 py-3 border border-red-200 text-red-600 text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-50">
                            <i class="bi bi-trash3"></i>{{ __('events.cover_remove') }}
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
