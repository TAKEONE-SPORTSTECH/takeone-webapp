{{--
    The gallery's PHOTOGRAPHS — a masonry of the day's pictures, a full-screen
    viewer that swipes between them, and, for whoever runs the event, the door
    to add more.

    Lives inside gallery-mobile's photos tab and reads the parent scope's
    `photos` / `photoCount` (eventGallery in that file), so the tab badge and
    the grid can never disagree. Everything a reader sees comes from
    App\Events\Support\EventPhotos — addresses and sizes, never a storage path.
    Delete addresses exist only when the VIEWER may manage (added by the parent
    from $canManage); the endpoint re-checks regardless.

    Expects: $canManage, $uploadUrl (null for anybody who may not add).
--}}

@once
@push('styles')
<style>
    @keyframes gpTileIn{from{opacity:0;transform:translateY(14px) scale(.97)}to{opacity:1;transform:none}}
    @keyframes gpFade{from{opacity:0}to{opacity:1}}
    @keyframes gpShine{0%{background-position:-200% 0}100%{background-position:200% 0}}

    .gp-grid{columns:2;column-gap:10px}
    @media (min-width:640px){.gp-grid{columns:3}}
    @media (min-width:1024px){.gp-grid{columns:4;column-gap:12px}}

    .gp-tile{position:relative;display:block;width:100%;break-inside:avoid;margin:0 0 10px;padding:0;border:none;border-radius:14px;overflow:hidden;cursor:zoom-in;
        background:linear-gradient(100deg,#e6eaf1 30%,#f3f5f9 50%,#e6eaf1 70%);background-size:200% 100%;animation:gpTileIn .45s cubic-bezier(.22,1,.36,1) backwards,gpShine 1.4s linear infinite;
        box-shadow:0 6px 18px rgba(11,19,43,.10);transition:transform .25s cubic-bezier(.22,1,.36,1),box-shadow .25s}
    .gp-tile.gp-loaded{animation:gpTileIn .45s cubic-bezier(.22,1,.36,1) backwards;background:#e6eaf1}
    .gp-tile img{display:block;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity .4s ease,transform .5s cubic-bezier(.22,1,.36,1)}
    .gp-tile.gp-loaded img{opacity:1}
    .gp-tile:active{transform:scale(.97)}
    @media (hover:hover){.gp-tile:hover img{transform:scale(1.04)}.gp-tile:hover{box-shadow:0 12px 28px rgba(11,19,43,.18)}}
    .gp-tile::after{content:"";position:absolute;inset:0;border-radius:inherit;box-shadow:inset 0 0 0 1px rgba(255,255,255,.18);pointer-events:none}
    .gp-tile[data-removing="1"]{opacity:0;transform:scale(.85);transition:all .3s ease}

    .gp-del{position:absolute;top:7px;inset-inline-end:7px;width:32px;height:32px;border-radius:50%;border:1px solid rgba(255,255,255,.35);background:rgba(11,19,43,.45);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);color:#fff;display:grid;place-items:center;font-size:.8rem;cursor:pointer;z-index:2}
    .gp-del:active{transform:scale(.9)}

    .gp-add{position:relative;overflow:hidden;display:flex;align-items:center;gap:14px;width:100%;text-align:start;padding:14px;border-radius:16px;border:1.5px dashed #d0a854;background:linear-gradient(135deg,#fffaf0 0%,#fff 60%);cursor:pointer;font-family:inherit;color:#1e2c4f;transition:transform .2s,box-shadow .2s}
    .gp-add:active{transform:scale(.985)}
    @media (hover:hover){.gp-add:hover{box-shadow:0 10px 24px rgba(208,168,84,.22)}}
    .gp-add-tile{flex:none;width:46px;height:46px;border-radius:14px;display:grid;place-items:center;font-size:1.25rem;color:#1e2c4f;background:linear-gradient(100deg,#edcd85 0%,#d0a854 100%);box-shadow:0 6px 14px rgba(208,168,84,.35)}

    .gp-viewer{position:fixed;inset:0;z-index:80;background:#05060a;color:#fff;display:flex;flex-direction:column;animation:gpFade .2s ease backwards;touch-action:none;user-select:none;-webkit-user-select:none}
    .gp-stage{position:relative;flex:1;overflow:hidden}
    .gp-strip{position:absolute;inset:0;display:flex;will-change:transform}
    .gp-slide{flex:none;width:100%;height:100%;display:grid;place-items:center;overflow:hidden}
    .gp-slide img{max-width:100%;max-height:100%;object-fit:contain;transform-origin:center center;will-change:transform}
    .gp-vbtn{width:42px;height:42px;border-radius:50%;border:1px solid rgba(255,255,255,.22);background:rgba(255,255,255,.10);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);color:#fff;display:grid;place-items:center;cursor:pointer;font-size:1rem}
    .gp-vbtn:active{transform:scale(.92)}
    .gp-nav{position:absolute;top:50%;transform:translateY(-50%);z-index:3}
    @media (hover:none){.gp-nav{display:none}}

    @media (prefers-reduced-motion:reduce){
        .gp-tile,.gp-tile img,.gp-viewer,.gp-strip{animation:none!important;transition:none!important}
    }
</style>
@endpush
@endonce

<div>
    {{-- ═══ The organiser's door ═══════════════════════════════════════════ --}}
    @if($uploadUrl)
        <input type="file" x-ref="photoPicker" class="hidden" style="display:none" multiple
               accept="image/jpeg,image/png,image/webp" @change="pickPhotos($event)">

        <div style="margin-bottom:14px">
            <button type="button" class="gp-add" @click="$refs.photoPicker.click()" :disabled="uploading" x-show="!uploading">
                <span class="gp-add-tile"><i class="bi bi-camera-fill"></i></span>
                <span style="flex:1;min-width:0">
                    <span style="display:block;font-size:.95rem;font-weight:700">{{ __('events::events.gallery_add_photos') }}</span>
                    <span style="display:block;margin-top:2px;font-size:.76rem;color:#6b7689;line-height:1.4">{{ __('events::events.gallery_add_photos_sub') }}</span>
                </span>
                <i class="bi bi-plus-lg" style="font-size:1.1rem;color:#b08a3a"></i>
            </button>

            {{-- While a batch goes up: the count, and a bar that fills. --}}
            <div x-show="uploading" x-cloak
                 style="padding:14px;border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(11,19,43,.08)">
                <div style="display:flex;align-items:center;gap:10px;font-size:.82rem;font-weight:600;color:#1e2c4f">
                    <span class="gp-add-tile" style="width:34px;height:34px;border-radius:10px;font-size:.95rem"><i class="bi bi-cloud-arrow-up-fill"></i></span>
                    <span style="flex:1" x-text="uploadLabel"></span>
                    <span style="font-variant-numeric:tabular-nums;color:#6b7689" x-text="Math.round(uploadPct) + '%'"></span>
                </div>
                <div style="margin-top:10px;height:6px;border-radius:3px;background:#eef1f6;overflow:hidden">
                    <div :style="{ width: uploadPct + '%' }"
                         style="height:100%;border-radius:3px;background:linear-gradient(100deg,#edcd85 0%,#d0a854 100%);transition:width .3s ease"></div>
                </div>
            </div>
        </div>
    @endif

    {{-- ═══ The pictures ═══════════════════════════════════════════════════ --}}
    <div class="gp-grid" x-show="photos.length">
        <template x-for="(p, i) in photos" :key="p.uuid">
            <div class="gp-tile" role="button" tabindex="0"
                 :data-removing="removingPhoto === p.uuid ? '1' : '0'"
                 :style="{ aspectRatio: p.w + ' / ' + p.h, animationDelay: Math.min(i, 12) * 45 + 'ms' }"
                 :aria-label="'{{ __('events::events.gallery_photo_open') }} ' + (i + 1)"
                 @click="openViewer(i)" @keydown.enter="openViewer(i)">
                <img :src="p.thumb" alt="" loading="lazy" decoding="async"
                     @load="$el.parentElement.classList.add('gp-loaded')">
                <template x-if="p.delete_url">
                    <button type="button" class="gp-del" @click.stop="photoConfirm = p.uuid"
                            aria-label="{{ __('events::events.gallery_photo_delete') }}">
                        <i class="bi bi-trash3"></i>
                    </button>
                </template>
            </div>
        </template>
    </div>

    {{-- ═══ Nothing here yet ═══════════════════════════════════════════════ --}}
    <div x-show="!photos.length && !uploading" style="text-align:center;padding:44px 24px 40px;animation:gpTileIn .4s ease backwards">
        <div style="width:72px;height:72px;margin:0 auto 16px;border-radius:22px;background:#fff;border:1px solid #d5dae3;display:grid;place-items:center;transform:rotate(-6deg);box-shadow:0 8px 20px rgba(11,19,43,.06)">
            <i class="bi bi-images" style="font-size:1.7rem;color:#c4cad6;transform:rotate(6deg)"></i>
        </div>
        <div style="font-size:.95rem;font-weight:600;color:#1e2c4f">{{ __('events::events.gallery_photos_empty_title') }}</div>
        <div style="margin-top:4px;font-size:.8rem;color:#6b7689;max-width:280px;margin-inline:auto;line-height:1.5">
            {{ $uploadUrl ? __('events::events.gallery_photos_empty_manage') : __('events::events.gallery_photos_empty_body') }}
        </div>
    </div>

    {{-- ═══ Delete, confirmed ══════════════════════════════════════════════ --}}
    @if($uploadUrl)
    <template x-teleport="body">
        <div x-show="photoConfirm" x-cloak @click="photoConfirm = null" @keydown.escape.window="photoConfirm = null"
             style="position:fixed;inset:0;background:rgba(11,19,43,.5);z-index:85">
            {{-- Anchored to the bottom edge itself: x-show owns `display` on the
                 backdrop, so a grid/flex placement there would be wiped. --}}
            <div @click.stop
                 style="position:absolute;bottom:0;left:50%;transform:translateX(-50%);width:100%;max-width:430px;background:#fff;border-radius:20px 20px 0 0;padding:22px 20px 28px;font-family:'Poppins','Cairo',system-ui,sans-serif;color:#1e2c4f">
                <div style="width:36px;height:4px;border-radius:2px;background:#d5dae3;margin:0 auto 18px"></div>
                <div style="display:flex;gap:14px;align-items:center">
                    <template x-if="photoConfirm">
                        <img :src="(photos.find(x => x.uuid === photoConfirm) || {}).thumb" alt=""
                             style="width:64px;height:64px;border-radius:12px;object-fit:cover;flex:none">
                    </template>
                    <div>
                        <div style="font-size:1.05rem;font-weight:700">{{ __('events::events.gallery_photo_delete_title') }}</div>
                        <div style="margin-top:4px;font-size:.82rem;color:#6b7689;line-height:1.5">{{ __('events::events.gallery_photo_delete_body') }}</div>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:20px;padding-bottom:env(safe-area-inset-bottom)">
                    <button type="button" @click="photoConfirm = null"
                            style="flex:1;padding:13px;border:1px solid #d5dae3;background:#fff;border-radius:12px;font-family:inherit;font-size:.9rem;font-weight:600;color:#1e2c4f;cursor:pointer">{{ __('shared.cancel') }}</button>
                    <button type="button" @click="deletePhoto(photoConfirm)"
                            style="flex:1;padding:13px;border:none;background:#b4483c;border-radius:12px;font-family:inherit;font-size:.9rem;font-weight:600;color:#fff;cursor:pointer">{{ __('events::events.gallery_photo_delete') }}</button>
                </div>
            </div>
        </div>
    </template>
    @endif

    {{-- ═══ The viewer ═════════════════════════════════════════════════════
         Full screen, swipe or arrow between pictures, double-tap (or
         double-click) to look closer and drag around. Teleported: it is
         `position:fixed` and must escape any transformed ancestor. --}}
    <template x-teleport="body">
        <div class="gp-viewer" x-show="viewer !== null" x-cloak
             @keydown.escape.window="closeViewer()" @keydown.arrow-left.window="viewer !== null && step(document.dir === 'rtl' ? 1 : -1)"
             @keydown.arrow-right.window="viewer !== null && step(document.dir === 'rtl' ? -1 : 1)">

            <div style="flex:none;display:flex;align-items:center;gap:10px;padding:calc(12px + env(safe-area-inset-top)) 14px 10px">
                <button type="button" class="gp-vbtn" @click="closeViewer()" aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg"></i></button>
                <div style="flex:1;text-align:center;font-size:.85rem;font-weight:600;letter-spacing:.06em;font-variant-numeric:tabular-nums;color:rgba(255,255,255,.85)"
                     x-text="viewer !== null ? (viewer + 1) + ' / ' + photos.length : ''"></div>
                @if($uploadUrl)
                    <button type="button" class="gp-vbtn" @click="photoConfirm = photos[viewer] && photos[viewer].uuid"
                            aria-label="{{ __('events::events.gallery_photo_delete') }}"><i class="bi bi-trash3"></i></button>
                @else
                    <span style="width:42px"></span>
                @endif
            </div>

            <div class="gp-stage" x-ref="stage" dir="ltr"
                 @pointerdown="vDown($event)" @pointermove="vMove($event)" @pointerup="vUp($event)" @pointercancel="vUp($event)"
                 @dblclick="toggleZoom($event)" @wheel.prevent="vWheel($event)">
                <div class="gp-strip" :style="stripStyle()">
                    <template x-for="(p, i) in photos" :key="'v' + p.uuid">
                        <div class="gp-slide">
                            <template x-if="viewer !== null && Math.abs(i - viewer) <= 1">
                                <img :src="p.full" alt="" draggable="false" :style="i === viewer ? zoomStyle() : ''">
                            </template>
                        </div>
                    </template>
                </div>

                <button type="button" class="gp-vbtn gp-nav" style="left:16px" x-show="viewer > 0" @click.stop="step(-1)" aria-label="‹"><i class="bi bi-chevron-left"></i></button>
                <button type="button" class="gp-vbtn gp-nav" style="right:16px" x-show="viewer !== null && viewer < photos.length - 1" @click.stop="step(1)" aria-label="›"><i class="bi bi-chevron-right"></i></button>
            </div>

            {{-- The strip of thumbnails along the foot, the current one lit. --}}
            <div x-ref="thumbs" style="flex:none;display:flex;gap:6px;overflow-x:auto;padding:10px 14px calc(12px + env(safe-area-inset-bottom));scrollbar-width:none">
                <template x-for="(p, i) in photos" :key="'t' + p.uuid">
                    <button type="button" @click="go(i)"
                            :style="'flex:none;width:44px;height:44px;border-radius:9px;overflow:hidden;padding:0;cursor:pointer;transition:all .2s;border:2px solid ' + (i === viewer ? '#edcd85' : 'transparent') + ';opacity:' + (i === viewer ? 1 : .5)">
                        <img :src="p.thumb" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;display:block">
                    </button>
                </template>
            </div>
        </div>
    </template>
</div>
