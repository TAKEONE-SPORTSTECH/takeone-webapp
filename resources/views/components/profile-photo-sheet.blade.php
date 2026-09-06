{{-- Profile pictures — a profile holds several; one of them is the avatar.
     Opening: dispatch the window event named by $eventName (default `open-profile-photo-sheet`).
     Emitting: `profile-photo-updated` ({ url }) whenever the avatar changes, `profile-photo-removed`
     when the last picture goes, so the host page patches its avatar in place (No-Reload rule).

     Two teleported layers, so a transformed ancestor (mobile shell) can't clip them:
       1. the manager — semi-transparent backdrop, the selected picture large, a filmstrip, a "+" tile
       2. the upload sheet — the shared cropper in its mobile bottom-sheet mode --}}
@props([
    'user' => null,
    'eventName' => 'open-profile-photo-sheet',
    'id' => 'profilePhotoSheet',
])

@php
    $cropperId = $id.'Cropper';

    // Every picture on the profile, avatar first — it is the one the sheet opens on.
    $sheetPhotos = $user
        ? $user->photos->map(fn ($p) => $p->toSheetArray($user->profile_picture))
            ->sortByDesc('is_avatar')->values()->all()
        : [];

    // Per-photo endpoints are built from a placeholder the JS swaps for the uuid.
    $uuidToken = '__PHOTO__';
    $photoStoreUrl = $user ? route('member.photos.store', $user->id) : '';
    $photoAvatarUrl = $user ? route('member.photos.avatar', [$user->id, $uuidToken]) : '';
    $photoDeleteUrl = $user ? route('member.photos.destroy', [$user->id, $uuidToken]) : '';
@endphp

<div x-data="{
        open: false,
        uploadOpen: false,
        busy: false,
        i: 0,
        photos: @js($sheetPhotos),

        get current() { return this.photos[this.i] || null },
        url(tpl, uuid) { return tpl.replace(@js($uuidToken), encodeURIComponent(uuid)) },
        headers() {
            return {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            };
        },

        openSheet() { this.i = Math.max(0, this.photos.findIndex(p => p.is_avatar)); this.open = true },
        close() { this.uploadOpen = false; this.open = false },

        {{-- Two signals per change: the avatar for anything that renders one, and the
             whole list for anything that renders the set (the hero's picture viewer). --}}
        announce(url) {
            window.dispatchEvent(new CustomEvent(url ? 'profile-photo-updated' : 'profile-photo-removed', { detail: { url } }));
            window.dispatchEvent(new CustomEvent('profile-photos-changed', {
                detail: { photos: this.photos.map(p => ({ uuid: p.uuid, url: p.url, is_avatar: !! p.is_avatar })) },
            }));
        },

        {{-- The shared cropper announces every successful upload on `document`. --}}
        onUploaded(detail) {
            const photo = detail?.photo;
            if (! photo) return;
            this.photos.forEach(p => p.is_avatar = false);
            this.photos.unshift(photo);          {{-- a new picture becomes the avatar, server-side too --}}
            this.i = 0;
            this.uploadOpen = false;
            this.announce(photo.url);
        },

        async makeAvatar() {
            const photo = this.current;
            if (! photo || photo.is_avatar || this.busy) return;
            this.busy = true;
            try {
                const res = await fetch(this.url(@js($photoAvatarUrl), photo.uuid), { method: 'PUT', headers: this.headers() });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    this.photos.forEach(p => p.is_avatar = false);
                    photo.is_avatar = true;
                    this.announce(data.url || photo.url);
                    window.showToast && window.showToast('success', data.message);
                } else {
                    window.showToast && window.showToast('error', data.message || @js(__('Something went wrong.')));
                }
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('Something went wrong.')));
            } finally { this.busy = false }
        },

        async remove() {
            const photo = this.current;
            if (! photo || this.busy) return;
            if (window.confirmAction && ! await window.confirmAction({
                title: @js(__('shared.profile_modal_fields_remove')),
                message: @js(__('member.remove_photo_confirm')),
                type: 'danger',
                confirmText: @js(__('shared.profile_modal_fields_remove')),
            })) return;

            this.busy = true;
            try {
                const res = await fetch(this.url(@js($photoDeleteUrl), photo.uuid), { method: 'DELETE', headers: this.headers() });
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    this.photos.splice(this.i, 1);
                    this.i = Math.min(this.i, Math.max(0, this.photos.length - 1));
                    if (data.was_avatar) {
                        {{-- The server promoted the next picture, or the profile has none left. --}}
                        const next = this.photos.find(p => p.url === data.avatar_url) || this.photos[0];
                        if (data.avatar_url && next) next.is_avatar = true;
                        this.announce(data.avatar_url || '');
                    } else {
                        this.announce(this.photos.find(p => p.is_avatar)?.url || '');
                    }
                    if (! this.photos.length) this.open = false;
                    window.showToast && window.showToast('success', data.message);
                } else {
                    window.showToast && window.showToast('error', data.message || @js(__('Something went wrong.')));
                }
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('Something went wrong.')));
            } finally { this.busy = false }
        },
     }"
     x-on:{{ $eventName }}.window="openSheet()"
     {{-- HTML lowercases attribute names, so the camelCase `imageUploaded` event needs .camel --}}
     @image-uploaded.camel.document="onUploaded($event.detail)"
     @keydown.escape.window="uploadOpen ? uploadOpen = false : close()">

    {{-- ===== 1. Manager ===== --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[75] flex flex-col" @click="close()">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/60 backdrop-blur-md"></div>

            {{-- Header --}}
            <div class="relative flex items-center justify-between px-4 pt-4 pb-2 text-white" @click.stop>
                <h2 class="text-base font-bold">
                    {{ __('member.your_pictures') }}
                    <span class="text-white/50 font-medium text-sm" x-show="photos.length">
                        <span x-text="i + 1"></span>/<span x-text="photos.length"></span>
                    </span>
                </h2>
                <button type="button" @click="close()" aria-label="{{ __('shared.close') }}"
                        class="w-10 h-10 rounded-full bg-white/15 grid place-items-center active:scale-90 transition-transform">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>

            {{-- The selected picture, as large as the screen allows --}}
            <div class="relative flex-1 flex items-center justify-center px-6 py-2 min-h-0" @click.stop>
                <template x-if="current">
                    <div class="relative max-w-full max-h-full">
                        <img :src="current.url" alt="{{ $user->full_name ?? '' }}"
                             class="max-w-full max-h-full object-contain rounded-2xl shadow-2xl">
                        <span x-show="current.is_avatar"
                              class="absolute top-3 left-3 rtl:left-auto rtl:right-3 px-2.5 py-1 rounded-full bg-primary text-white text-[11px] font-bold shadow-lg">
                            <i class="bi bi-person-badge me-1"></i>{{ __('shared.components_profile_modal_tab_photo') }}
                        </span>
                    </div>
                </template>
                <template x-if="! current">
                    <div class="w-48 aspect-[3/4] rounded-2xl bg-white/10 border border-white/20 grid place-items-center text-white/70">
                        <div class="text-center px-4">
                            <i class="bi bi-person-bounding-box text-4xl"></i>
                            <p class="text-xs mt-2">{{ __('shared.profile_modal_fields_no_profile_picture') }}</p>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Per-picture actions --}}
            <div class="relative flex-shrink-0 px-6 flex items-center justify-center gap-5 text-sm font-semibold" @click.stop>
                <button type="button" x-show="current && ! current.is_avatar" @click="makeAvatar()" :disabled="busy"
                        class="text-white/85 hover:text-white disabled:opacity-50 transition-colors">
                    <i class="bi bi-person-badge me-1"></i>{{ __('member.set_as_avatar') }}
                </button>
                <button type="button" x-show="current" @click="remove()" :disabled="busy"
                        class="text-white/70 hover:text-red-300 disabled:opacity-50 transition-colors">
                    <i class="bi bi-trash me-1"></i>{{ __('shared.profile_modal_fields_remove') }}
                </button>
            </div>

            {{-- Filmstrip: every picture, plus the tile that adds one --}}
            <div class="relative flex-shrink-0 px-4 pt-3 overflow-x-auto mp-rail"
                 style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));" @click.stop>
                <div class="flex items-center gap-2.5 w-max mx-auto">
                    <template x-for="(p, n) in photos" :key="p.uuid">
                        <button type="button" @click="i = n"
                                class="relative w-14 aspect-[3/4] rounded-xl overflow-hidden flex-shrink-0 transition-all"
                                :class="i === n ? 'ring-2 ring-white scale-105' : 'ring-1 ring-white/25 opacity-70'">
                            <img :src="p.url" alt="" class="w-full h-full object-cover">
                            <span x-show="p.is_avatar" class="absolute bottom-0 inset-x-0 bg-primary/90 text-white text-[9px] font-bold py-0.5 text-center">
                                <i class="bi bi-person-badge"></i>
                            </span>
                        </button>
                    </template>

                    <button type="button" @click="uploadOpen = true"
                            class="m-press w-14 aspect-[3/4] rounded-xl border-2 border-dashed border-white/40 text-white grid place-items-center flex-shrink-0 active:scale-95 transition-transform"
                            aria-label="{{ __('member.add_new_picture') }}">
                        <i class="bi bi-plus-lg text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ===== 2. Upload sheet — the shared cropper, mobile bottom-sheet mode ===== --}}
    <template x-teleport="body">
        <div x-show="uploadOpen" x-cloak class="fixed inset-0 z-[78] flex flex-col justify-end">
            <div x-show="uploadOpen" x-transition.opacity class="absolute inset-0 bg-black/50" @click="uploadOpen = false"></div>

            <div x-show="uploadOpen"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="relative max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-camera-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('member.add_new_picture') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('member.your_pictures') }}</p>
                        </div>
                        <button type="button" @click="uploadOpen = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                    @if($user)
                        {{-- One cropper everywhere: inline mode is the mobile crop editor.
                             300x400 matches the profile picture the rest of the platform stores.
                             folder/filename are ignored by the endpoint — it generates both. --}}
                        <x-takeone-cropper
                            :id="$cropperId"
                            mode="ajax"
                            :inline="true"
                            :width="600"
                            :height="800"
                            shape="rectangle"
                            :canvasHeight="300"
                            folder="photos"
                            filename="photo"
                            :uploadUrl="$photoStoreUrl"
                            sheetMaxWidth="100%"
                            sheetClass="rounded-t-3xl shadow-2xl bg-background"
                            :showControls="false"
                            :showCancel="false"
                            saveText="{{ __('member.save') }}"
                            :uploadAsIs="true"
                            uploadAsIsText="{{ __('shared.profile_modal_fields_change_photo') }}" />
                    @endif
                </div>
            </div>
        </div>
    </template>
</div>
