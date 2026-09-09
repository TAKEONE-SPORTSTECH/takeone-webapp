@extends('entry.layout')

@php
    use App\Support\Palette;

    $noindex = true;

    /* The layout is the sealed public shell and needs the poster payload. The
       controller does not pass it — and must not be changed for a view — so it
       is built here from the same one class every other page on this surface
       uses. `payload()` is the gate as well as the shape: it refuses to speak
       about an event that is not public. */
    $e = app(\App\Events\Support\PublicEvent::class)->payload($event);

    /* Mixed in PHP, never `color-mix()`: an Android WebView older than Chrome
       111 drops the whole declaration and the band would have no background. */
    $ev     = Palette::safe($e['color']);
    $evDeep = Palette::shade($ev, 82);
    $evFade = Palette::shade($ev, 38);

    /* The clubs this athlete may compete for. The controller hands over ids and
       names; the editor writes a SLUG, and a slug is the only club identifier
       allowed to leave the server (CLAUDE.md → Unpredictable Resource
       Identifiers). Read-only, and only for clubs the service already said are
       theirs — this widens nothing. */
    $myClubs = \App\Clubs\Models\Tenant::whereIn('id', array_column($clubs, 'id'))
        ->get(['id', 'slug', 'club_name', 'logo', 'country'])
        ->map(fn ($t) => [
            'slug'    => $t->slug,
            'name'    => $t->club_name,
            'logo'    => $t->logo ? file_url($t->logo) : null,
            'country' => $t->country,
        ])->values()->all();
@endphp

{{--
    MY ENTRY — the athlete's own control panel for one competition.

    WHY IT EXISTS
    -------------
    A competitor enters ONCE. `club_event_registrations` carries
    `unique(event_id, user_id)` on purpose — thirty-nine places read that table
    as "who is competing" — so whatever somebody typed on a phone at two in the
    morning is what they turn up as. Until this screen existed they could change
    exactly two things about that entry, their proof of payment and which club
    they represent, and they could not withdraw at all. Their weight, their
    belt, their photograph, their date of birth and their gender were frozen:
    the five most error-prone fields on the form, and the ones that decide their
    division and their safety bracket. The only remedy was to telephone the
    organiser.

    So this is where they fix them, and where they can ask to be taken out.

    WHAT IT DOES NOT DECIDE
    -----------------------
    Nothing. Every rule about what may be changed, by whom and until when lives
    in App\Events\Support\EntryEditor, and every rule about leaving lives in
    App\Events\Support\Withdrawal. This page renders `$permissions` — it never
    infers them — so a field the server will refuse is drawn LOCKED with the
    server's own reason beside it, rather than offered and then rejected.

    It sits inside the event's own sealed skin, because an athlete who arrived
    from a WhatsApp link must never be handed back to a platform they have never
    heard of. Its sibling is entry/public/partials/enrol-mine.blade.php and it
    wears that band verbatim.

    Expects $event, $entry, $permissions, $withdrawal, $clubs, $belts.
--}}

@section('body')
{{-- ===== Phase M2: the React island (feature-flagged, default OFF) =====
     When config('features.react_entry') is ON the panel below is rendered
     instead by resources/js/islands/entry.jsx, mounted by the shell-aware
     helper in resources/js/island.js. When the flag is OFF (the default, and
     what every entrant gets) nothing changes and the Blade panel renders
     exactly as before. The two paths are mutually exclusive — never both,
     never neither.

     The PHOTOGRAPH is not in the island. `<x-takeone-cropper>` is a Blade +
     jQuery widget and this project has exactly ONE cropper (CLAUDE.md → One
     Cropper Everywhere), so the photo sheet, the camera and the cropper stay
     here in Blade, outside the island, driven by two events:

        island → Blade   `entry-photo:open`   the pass card's camera button
        Blade  → island  `entry:updated`      the PUT's JSON body

     The island reads the current photograph out of `entry` like any other
     server value and re-reads its state when that event lands. --}}
@if (config('features.react_entry'))

@php
    /* Everything the island needs, resolved SERVER-side. Colours are mixed in
       PHP (never `color-mix()`, which an old Android WebView drops whole), and
       the alpha stops are 8-digit hex for the same reason. */
    $islandProps = [
        'eventUuid'  => $event->uuid,
        'eventTitle' => $e['title'],
        'backUrl'    => route('events.public', ['event' => $e['key']]),
        'urls' => [
            'state'      => route('events.public.my-entry.state', ['event' => $event->uuid]),
            'update'     => route('events.public.my-entry.update', ['event' => $event->uuid]),
            'withdraw'   => route('events.public.my-entry.withdraw', ['event' => $event->uuid]),
            'clubSearch' => route('events.public.enter.clubs', ['event' => $event->uuid]),
        ],
        'entry'       => $entry,
        'permissions' => $permissions,
        'withdrawal'  => $withdrawal,
        'clubs'       => $myClubs,
        'belts'       => $belts,
        /* The country list, handed over the same way the belts and the clubs
           are: the island cannot mount <x-country-dropdown> (Alpine), so it
           renders an equivalent searchable picker from this data. One list,
           read from the same file every country picker in the product uses. */
        'countries'   => \App\Support\Countries::all(),
        'maxBirthdate' => now()->subDay()->toDateString(),
        'theme' => [
            'ev'     => $ev,
            'evDeep' => $evDeep,
            'evFade' => $evFade,
            'evA06'  => Palette::alpha($ev, .06),
            'evA07'  => Palette::alpha($ev, .07),
            'evA10'  => Palette::alpha($ev, .1),
            'evA30'  => Palette::alpha($ev, .3),
        ],
        /* The gendered silhouette, rendered by the ONE component that draws it
           (srcset cuts and all) rather than reimplemented in JS. Both genders
           travel so changing gender repaints without a reload. It is our own
           Blade output — never user input — and the island says so where it
           injects it. */
        'avatars' => [
            'male'   => trim(\Illuminate\Support\Facades\Blade::render(
                '<x-gender-avatar gender="Male" :bg="$bg" sizes="78px" class="w-full h-full" />', ['bg' => $ev]
            )),
            'female' => trim(\Illuminate\Support\Facades\Blade::render(
                '<x-gender-avatar gender="Female" :bg="$bg" sizes="78px" class="w-full h-full" />', ['bg' => $ev]
            )),
        ],
    ];

    /* Copy comes from the server. The island carries no English of its own and
       builds no second translation system — it reads these keys and nothing
       else. */
    $islandI18n = [
        'title'    => __('events.entry_edit_title'),
        'subtitle' => __('events.entry_edit_subtitle'),
        'back'     => __('events.public_enrol_back_event'),
        'close'    => __('shared.close'),
        'clear'    => __('shared.clear'),
        'save'     => __('shared.save'),
        'edit'     => __('shared.edit'),

        'field_name'        => __('events.entry_field_name'),
        'field_nationality' => __('events.entry_field_nationality'),
        'name_hint'         => __('events.entry_name_hint'),
        'name_min'          => __('events.entry_name_min'),
        'country_search'    => __('events.entry_country_search'),
        'country_none'      => __('events.entry_country_none'),
        'photo_recrop'        => __('events.entry_photo_recrop'),
        'photo_recrop_hint'   => __('events.entry_photo_recrop_hint'),
        'photo_recrop_failed' => __('events.entry_photo_recrop_failed'),

        'field_weight'    => __('events.entry_field_weight'),
        'field_belt'      => __('events.entry_field_belt'),
        'field_grade'     => __('events.entry_field_grade'),
        'field_photo'     => __('events.entry_field_photo'),
        'field_club'      => __('events.entry_field_club'),
        'field_birthdate' => __('events.entry_field_birthdate'),
        'field_gender'    => __('events.entry_field_gender'),

        'weighed_in'       => __('events.entry_weighed_in'),
        'self_declared'    => __('events.entry_self_declared'),
        'no_club'          => __('events.entry_no_club'),
        'no_division_yet'  => __('events.entry_no_division_yet'),
        'division_moved'   => __('events.entry_edit_division_moved'),
        'reweigh_warning'  => __('events.entry_edit_saved_reweigh'),
        'not_yours'        => __('events.entry_edit_not_yours'),

        'claim_weight'          => __('events.claim_weight'),
        'claim_weight_blank'    => __('events.claim_weight_blank'),
        'claim_belt'            => __('events.claim_belt'),
        'claim_birthdate_blank' => __('events.claim_birthdate_blank'),

        'photo_add'    => __('events.public_enrol_photo_add'),
        'photo_change' => __('events.public_enrol_photo_change'),
        'photo_add_cta' => __('events.entry_photo_add_cta'),
        'photo_on_file' => __('events.entry_photo_on_file'),
        'photo_remove'  => __('events.entry_photo_remove'),

        /* The finish-up prompt: what the one-screen door no longer collects. */
        'finish_title'     => __('events.entry_finish_title'),
        'finish_hint'      => __('events.entry_finish_hint'),
        'finish_photo_why' => __('events.entry_finish_photo_why'),

        'club_search'     => __('events.public_enrol_club_search'),
        'club_none_found' => __('events.public_enrol_club_none_found'),

        'withdraw_title'          => __('events.withdraw_title'),
        'withdraw_explain'        => __('events.withdraw_explain'),
        'withdraw_reason_label'   => __('events.withdraw_reason_label'),
        'withdraw_action'         => __('events.withdraw_action'),
        'withdraw_pending_banner' => __('events.withdraw_pending_banner'),
        'withdraw_take_back'      => __('events.withdraw_take_back'),
        'withdraw_not_entered'    => __('events.withdraw_not_entered'),
        'withdraw_nothing_to_cancel' => __('events.withdraw_nothing_to_cancel'),

        'gender_male'   => __('Male'),
        'gender_female' => __('Female'),

        'day'            => __('Day'),
        'month'          => __('Month'),
        'year'           => __('Year'),
        'year_to_finish' => __('Enter the year to finish'),
    ];
@endphp

{{-- The mount point. Props travel as JSON attributes (Blade-escaped), so the
     island gets its first paint without a round trip. --}}
<div id="entry-island"
     data-island-props="{{ json_encode($islandProps) }}"
     data-i18n="{{ json_encode($islandI18n) }}"></div>

{{-- The Blade half of the photograph — the same sheet, camera and cropper the
     panel below uses, with only the wiring changed: it opens on
     `entry-photo:open` and reports its save on `entry:updated`. --}}
<div x-data="myEntryPhoto()">
    {{-- ================= The photo sheet =================
         Two ways in, because a phone has two: the camera and what is already on
         it. Both hand the file to the ONE cropper this project has, in its
         inline bottom-sheet mode (CLAUDE.md → One Cropper Everywhere). --}}
    <template x-teleport="body">
        <div x-show="photoSheet" x-cloak class="fixed inset-0 z-[60]">
            <div x-show="photoSheet" x-transition.opacity @click="photoSheet = false" class="absolute inset-0 bg-black/50"></div>

            <div x-show="photoSheet"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col rounded-t-3xl overflow-hidden bg-background">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $ev }}, {{ $ev }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-camera-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events.entry_field_photo') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('events.public_enrol_photo_sheet_hint') }}</p>
                        </div>
                        <button type="button" @click="photoSheet = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- A read-only sheet would have no footer; this one's body IS
                     the last element, so it carries the safe-area padding. --}}
                <div class="flex-1 overflow-y-auto px-5 pt-4"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" x-show="!cameraFallback" @click="openCamera()"
                                class="ph-tile m-press text-white"
                                style="background: linear-gradient(155deg, {{ $ev }}, {{ $ev }}b0); box-shadow: 0 20px 42px -22px {{ $ev }};">
                            <span class="ph-corner tl"></span><span class="ph-corner tr"></span>
                            <span class="ph-corner bl"></span><span class="ph-corner br"></span>
                            <span class="ph-lens" style="background: rgba(255,255,255,.16); box-shadow: 0 0 0 8px rgba(255,255,255,.08);">
                                <i class="bi bi-camera" style="font-size: 26px;"></i>
                            </span>
                            <span class="relative">
                                <span class="ph-title block">{{ __('events.public_enrol_photo_take') }}</span>
                                <span class="ph-hint block" style="color: rgba(255,255,255,.8);">{{ __('events.public_enrol_photo_take_hint') }}</span>
                            </span>
                        </button>

                        {{-- The same tile when there is no camera API or the
                             permission was refused: one door, not a third
                             option appearing underneath. --}}
                        <label x-show="cameraFallback" x-cloak
                               class="ph-tile m-press text-white cursor-pointer"
                               style="background: linear-gradient(155deg, {{ $ev }}, {{ $ev }}b0); box-shadow: 0 20px 42px -22px {{ $ev }};">
                            <span class="ph-corner tl"></span><span class="ph-corner tr"></span>
                            <span class="ph-corner bl"></span><span class="ph-corner br"></span>
                            <span class="ph-lens" style="background: rgba(255,255,255,.16); box-shadow: 0 0 0 8px rgba(255,255,255,.08);">
                                <i class="bi bi-camera-fill" style="font-size: 26px;"></i>
                            </span>
                            <span class="relative">
                                <span class="ph-title block">{{ __('events.public_enrol_photo_take') }}</span>
                                <span class="ph-hint block" style="color: rgba(255,255,255,.8);">{{ __('events.public_enrol_photo_take_native_hint') }}</span>
                            </span>
                            <input type="file" accept="image/*" capture="user" class="hidden" @change="handOff($event)">
                        </label>

                        <label class="ph-tile m-press cursor-pointer"
                               style="background: #fff; border: 1.5px solid hsl(210 14% 88%);">
                            <span class="ph-print" style="width: 46px; height: 58px; top: 30px; inset-inline-start: 26px; transform: rotate(-11deg); background: {{ Palette::alpha($ev, .1) }};"></span>
                            <span class="ph-print" style="width: 46px; height: 58px; top: 26px; inset-inline-start: 40px; transform: rotate(7deg); background: {{ Palette::alpha($ev, .16) }};"></span>
                            <span class="ph-print grid place-items-center" style="width: 50px; height: 62px; top: 32px; inset-inline-start: 33px; background: {{ Palette::alpha($ev, .28) }}; color: #fff;">
                                <i class="bi bi-images" style="font-size: 22px;"></i>
                            </span>
                            <span class="relative">
                                <span class="ph-title block text-foreground">{{ __('events.public_enrol_photo_gallery') }}</span>
                                <span class="ph-hint block text-muted-foreground">{{ __('events.public_enrol_photo_gallery_hint') }}</span>
                            </span>
                            <input type="file" accept="image/*" class="hidden" @change="handOff($event)">
                        </label>
                    </div>

                    <p class="text-[11px] text-muted-foreground leading-snug pt-4">{{ __('events.public_enrol_photo_ratio') }}</p>
                </div>
            </div>
        </div>
    </template>

    {{-- ================= The camera =================
         A capture screen laid out like a camera app: the frame in the middle at
         the crop's true 3:4 with the face oval inside it, and every control in
         the bottom third where a thumb already is. The shot goes to the cropper
         rather than straight to the server — the guide gets the face roughly
         right, the crop makes it exact. --}}
    <template x-teleport="body">
        <div x-show="camera" x-cloak class="fixed inset-0 z-[70] flex flex-col" style="background:#0a0a0f;">
            <div class="cam-stage" style="--cam: {{ $ev }};">
                <video x-ref="video" autoplay playsinline muted class="cam-video"
                       :style="cameraFacing === 'user' ? 'transform: scaleX(-1)' : ''"></video>

                <div class="cam-frame">
                    <span class="cam-b tl"></span><span class="cam-b tr"></span>
                    <span class="cam-b bl"></span><span class="cam-b br"></span>
                    <span class="cam-face"></span>
                </div>

                <div class="cam-top">
                    <button type="button" @click="closeCamera()" aria-label="{{ __('shared.close') }}" class="cam-round m-press">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <span class="cam-label">{{ __('events.entry_field_photo') }}</span>
                    <span class="cam-chip"><i class="bi bi-person-bounding-box"></i>3:4</span>
                </div>

                <p class="cam-hint" x-text="cameraHint"></p>
            </div>

            <div class="cam-bar" style="--cam: {{ $ev }};">
                <span></span>
                <button type="button" @click="shoot()" aria-label="{{ __('events.public_enrol_photo_take') }}" class="cam-shutter">
                    <span></span>
                </button>
                <span class="cam-side">
                    <button type="button" @click="flipCamera()" x-show="cameraCanFlip" x-cloak
                            aria-label="{{ __('events.public_enrol_photo_flip') }}" class="cam-round m-press">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </span>
            </div>
        </div>
    </template>

    {{-- The cropper. ONE per project, inline bottom-sheet mode, at the
         platform's portrait 3:4 — stored 600×800. `mode="form"` keeps the bytes
         in a hidden input instead of uploading them, which is what this page
         needs: the PUT carries the data URI and EntryEditor sniffs the real
         bytes and assigns the extension itself. --}}
    <div class="hidden">
        <x-takeone-cropper
            id="myEntryPhoto" mode="form" :inline="true"
            :width="600" :height="800" shape="rectangle" :canvasHeight="360"
            folder="temp" filename="entrant" inputName="photo"
            sheetMaxWidth="100%" sheetClass="rounded-t-3xl shadow-2xl bg-background"
            :showControls="false" :showCancel="false"
            saveText="{{ __('shared.save') }}" />
    </div>
</div>

@vite(['resources/js/islands/entry.jsx'])

@push('scripts')
<script>
/* The Blade half of the photograph, for the React path only.
   Everything below is the camera/cropper part of `myEntry()` with the panel
   state removed: it opens when the island asks, writes the one field it owns,
   and tells the island what came back. */
function myEntryPhoto() {
    return {
        photoSheet: false,
        camera: false,
        cameraFallback: false,
        cameraCanFlip: false,
        cameraFacing: 'user',
        cameraHint: '',
        saving: false,
        _stream: null,

        /* ---------- The one write this half owns ---------- */

        async savePhoto(base64) {
            if (this.saving) return;
            this.saving = true;

            try {
                const res = await fetch(@js(route('events.public.my-entry.update', ['event' => $event->uuid])), {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ photo: base64 }),
                });
                const d = await res.json().catch(() => ({}));

                if (!res.ok || !d.success) throw new Error(d.message || @js(__('events.entry_edit_not_yours')));

                // The island owns the panel; it decides what the response means.
                window.dispatchEvent(new CustomEvent('entry:updated', { detail: d }));
            } catch (e) {
                window.showToast('error', e.message);
            } finally {
                this.saving = false;
            }
        },

        /* ---------- The photograph ---------- */

        /* Re-frame the picture already on file. Same cropper, same bytes-back
           approach as the Blade panel; the island cannot render the cropper, so
           it hands the URL over on `entry-photo:recrop`. */
        async recrop(src) {
            if (!src) return;

            try {
                const res = await fetch(src, { credentials: 'same-origin' });
                if (!res.ok) throw new Error();

                const blob = await res.blob();
                if (!blob || !String(blob.type).startsWith('image/')) throw new Error();

                this.photoSheet = false;
                this.toCropper(new File([blob], 'photo', { type: blob.type }));
            } catch (e) {
                // The island hides the action once it knows the file is gone.
                window.dispatchEvent(new CustomEvent('entry-photo:broken', { detail: { src } }));
                window.showToast('error', @js(__('events.entry_photo_recrop_failed')));
            }
        },

        handOff(ev) {
            const file = ev.target.files && ev.target.files[0];
            if (!file) return;

            // Let the same door be used twice in a row: without this, picking
            // the identical file again fires no change event at all.
            ev.target.value = '';
            this.photoSheet = false;
            this.toCropper(file);
        },

        /* Hand a file to the ONE cropper. Its own input is what it reads from,
           so the file is moved across with a DataTransfer and a `change`. */
        toCropper(file) {
            const target = document.getElementById('input_myEntryPhoto');
            if (!target) return;

            const dt = new DataTransfer();
            dt.items.add(file);
            target.files = dt.files;
            target.dispatchEvent(new Event('change', { bubbles: true }));
        },

        /* Detect first, request properly, fall back honestly (CLAUDE.md →
           Mobile Device Capabilities). */
        async openCamera() {
            this.cameraHint = @js(__('events.public_enrol_photo_guide'));

            if (!navigator.mediaDevices?.getUserMedia) {
                this.cameraFallback = true;
                window.showToast('error', @js(__('events.public_enrol_photo_no_camera')));

                return;
            }

            try {
                this._stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: this.cameraFacing, width: { ideal: 1080 }, height: { ideal: 1440 } },
                    audio: false,
                });
            } catch (e) {
                this.cameraFallback = true;
                window.showToast('error', @js(__('events.public_enrol_photo_denied')));

                return;
            }

            this.photoSheet = false;
            this.camera = true;

            await this.$nextTick();
            const v = this.$refs.video;
            if (v) { v.srcObject = this._stream; try { await v.play(); } catch (e) {} }

            try {
                const cams = await navigator.mediaDevices.enumerateDevices();
                this.cameraCanFlip = cams.filter(d => d.kind === 'videoinput').length > 1;
            } catch (e) {
                this.cameraCanFlip = false;
            }
        },

        async flipCamera() {
            this.cameraFacing = this.cameraFacing === 'user' ? 'environment' : 'user';
            this.stopStream();
            this.camera = false;
            await this.openCamera();
        },

        /* Take the frame in the guide's own 3:4. Mirrored only where the
           preview was. */
        shoot() {
            const v = this.$refs.video;
            if (!v || !v.videoWidth) { window.showToast('error', @js(__('events.public_enrol_photo_wait'))); return; }

            const srcW = v.videoWidth, srcH = v.videoHeight;
            const want = 3 / 4;
            let sw = srcW, sh = Math.round(srcW / want);
            if (sh > srcH) { sh = srcH; sw = Math.round(srcH * want); }
            const sx = Math.round((srcW - sw) / 2);
            const sy = Math.round((srcH - sh) / 2);

            const canvas = document.createElement('canvas');
            canvas.width = 900;
            canvas.height = 1200;
            const ctx = canvas.getContext('2d');

            if (this.cameraFacing === 'user') {
                ctx.translate(canvas.width, 0);
                ctx.scale(-1, 1);
            }

            ctx.drawImage(v, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);

            canvas.toBlob((blob) => {
                if (!blob) { window.showToast('error', @js(__('events.public_enrol_photo_failed'))); return; }

                const file = new File([blob], 'photo.jpg', { type: 'image/jpeg' });
                this.closeCamera();
                this.toCropper(file);
            }, 'image/jpeg', 0.92);
        },

        closeCamera() { this.stopStream(); this.camera = false; },

        /* Never leave the camera light on. */
        stopStream() {
            if (!this._stream) return;
            this._stream.getTracks().forEach(t => { try { t.stop(); } catch (e) {} });
            this._stream = null;
        },

        init() {
            /* The island's pass card asks for this sheet; it cannot render the
               cropper itself. */
            window.addEventListener('entry-photo:open', () => { this.photoSheet = true; });

            /* …and the same for re-framing what is already there. The island
               owns the button; the cropper lives here. */
            window.addEventListener('entry-photo:recrop', (ev) => {
                this.recrop(ev.detail && ev.detail.src);
            });

            /* The cropper announces its result rather than firing an input
               event, because it writes the hidden field with `.value =`. */
            document.addEventListener('cropperCropped', (ev) => {
                if (ev.detail && ev.detail.id === 'myEntryPhoto' && ev.detail.base64) {
                    this.savePhoto(ev.detail.base64);
                }
            });

            window.addEventListener('pagehide', () => this.stopStream());
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') this.stopStream();
            });
        },
    };
}
</script>
@endpush

@else

<div x-data="myEntry()" class="-mx-4 -mt-4">

    {{-- ===== The band — enrol-mine's, verbatim. Same surface, same header;
         inventing a second one here would be a redesign nobody asked for
         (Design Rule #1). ===== --}}
    <header class="relative overflow-hidden text-white"
            style="padding: 22px 24px 26px; background: {{ \App\Support\Palette::eventBand($e['color']) }};">
        <div class="absolute rounded-full" style="right:-56px; top:-56px; width:190px; height:190px; background:rgba(255,255,255,.07);"></div>

        <div class="flex items-center justify-between gap-3 relative z-10">
            <a href="{{ route('events.public', ['event' => $e['key']]) }}"
               class="m-press ev-ico inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline"
               aria-label="{{ __('events.public_enrol_back_event') }}" title="{{ __('events.public_enrol_back_event') }}">
                <i class="bi bi-chevron-left"></i>
            </a>
        </div>

        <div class="relative z-10" style="margin-top:22px;">
            <span class="flex items-center" style="gap:10px;">
                <span class="flex-none" style="width:38px; height:3px; border-radius:2px; background:rgba(255,255,255,.85);"></span>
                <span class="uppercase truncate" style="font-size:11px; font-weight:600; letter-spacing:.2em; color:rgba(255,255,255,.85);">{{ $e['title'] }}</span>
            </span>

            <h1 style="margin:12px 0 0; font-size:23px; line-height:1.2; font-weight:700; letter-spacing:-.01em;">{{ __('events.entry_edit_title') }}</h1>

            <p style="margin:9px 0 0; font-size:13px; color:rgba(255,255,255,.82);">{{ __('events.entry_edit_subtitle') }}</p>
        </div>
    </header>

    <div class="mx-auto w-full max-w-lg px-4 -mt-3 relative z-10">
        <div class="pb-[max(4rem,calc(3rem+env(safe-area-inset-bottom)))] mobile-stagger space-y-3.5">

            {{-- ===== Finish your entry =====

                 The door only asks for a name, a telephone number and a
                 password now (entry/public/enrol, 2026-09-05) — everything else
                 was making strangers stop before they had entered at all. So
                 this is where the rest is asked for, and it has to ASK: an
                 entrant who lands here and sees six tidy rows has no idea that
                 any of them matter.

                 The photograph comes first because it is the one the draw and
                 the hall screens need. Warm, never a nag: nothing here is
                 required, nothing is blocked, and the card removes itself item
                 by item as they are filled — off the same `entry` state the
                 panel already patches in place, so no reload (No-Reload Rule).

                 Hidden entirely when the editing window is shut: asking for
                 something the server would refuse is worse than not asking. --}}
            <template x-if="missing.length">
                <div class="fin m-card">
                    <div class="flex items-start gap-3">
                        <span class="fin-tile"><i class="bi bi-stars"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="fin-title">{{ __('events.entry_finish_title') }}</p>
                            <p class="fin-hint">{{ __('events.entry_finish_hint') }}</p>
                        </div>
                        <span class="fin-count" x-text="missing.length"></span>
                    </div>

                    <div class="fin-chips">
                        <template x-for="m in missing" :key="m.field">
                            <button type="button" @click="askFor(m.field)"
                                    class="fin-chip m-press" :class="m.field === 'photo' && 'is-key'">
                                <i class="bi" :class="m.icon"></i>
                                <span x-text="m.label"></span>
                                <i class="bi bi-chevron-right" style="font-size:9px; opacity:.6;"></i>
                            </button>
                        </template>
                    </div>

                    {{-- The "why the photo matters" line lives on the pass
                         card, next to the photograph. Printing it here too put
                         the same sentence on screen twice. --}}
                </div>
            </template>


            {{-- ===== The window, when it is shut =====
                 One calm sentence at the top, not an error beside every field.
                 It is not the athlete's mistake that the competition started. --}}
            <template x-if="!perm.window.open">
                <div class="rounded-2xl px-4 py-3.5 flex items-start gap-2.5"
                     style="background:#f1f5f9; border:1px solid hsl(210 14% 88%);">
                    <i class="bi bi-lock-fill mt-0.5 text-muted-foreground"></i>
                    <p class="text-[12.5px] text-muted-foreground leading-snug" x-text="perm.window.reason"></p>
                </div>
            </template>

            {{-- ===== What the last save changed =====
                 A re-weigh and a division move are the two outcomes an athlete
                 must not learn from a toast that has already faded. --}}
            <template x-for="n in notices" :key="n.id">
                <div class="rounded-2xl px-4 py-3.5 flex items-start gap-2.5"
                     :style="n.tone === 'warn'
                        ? 'background:#fef3c7; border:1px solid #fcd34d; color:#92400e'
                        : 'background: {{ Palette::alpha($ev, .1) }}; border:1px solid {{ Palette::alpha($ev, .3) }}; color: {{ $evDeep }}'">
                    <i class="bi mt-0.5" :class="n.tone === 'warn' ? 'bi-exclamation-triangle-fill' : 'bi-diagram-3 bracket-icon'"></i>
                    <p class="text-[12.5px] font-bold leading-snug flex-1" x-text="n.text"></p>
                    <button type="button" @click="dismiss(n.id)" class="m-press flex-shrink-0" aria-label="{{ __('shared.close') }}">
                        <i class="bi bi-x-lg text-xs"></i>
                    </button>
                </div>
            </template>

            {{-- ===== Waiting to be let out =====
                 While a request is pending the withdraw button is gone: there
                 is nothing to ask twice, and the only useful action left is
                 taking it back. --}}
            <template x-if="withdrawal && withdrawal.state === 'pending'">
                <div class="rounded-2xl p-4" style="background:#fef3c7; border:1px solid #fcd34d;">
                    <p class="text-[12.5px] font-bold leading-snug flex items-start gap-2" style="color:#92400e;">
                        <i class="bi bi-hourglass-split mt-0.5"></i>{{ __('events.withdraw_pending_banner') }}
                    </p>
                    <p x-show="withdrawal.reason" x-cloak class="text-[11.5px] mt-1.5 ps-6" style="color:#b45309;"
                       x-text="withdrawal.reason"></p>
                    <button type="button" @click="takeBack()" :disabled="saving"
                            class="m-press mt-3 w-full h-11 rounded-2xl text-[12.5px] font-black text-white"
                            :class="saving ? 'opacity-40' : ''"
                            style="background:#b45309;">
                        {{ __('events.withdraw_take_back') }}
                    </button>
                </div>
            </template>

            {{-- ===== The pass =====

                 The photograph, the name and the division, drawn as the object
                 they actually become: the row on the entry list, the face on
                 the draw, the portrait on the wall board. So the card is built
                 like a pass — the event's own band across the top, the portrait
                 plate riding up over its tail (the same move Design Rule #6's
                 hero band makes), the name beside it and the actions quiet
                 underneath.

                 It replaced a single cramped row (a 3:4 outline, the name, an
                 amber "a photo is needed" pill and three competing text
                 buttons). The amber read as an ERROR, and nothing had gone
                 wrong: the person simply had not added a photograph yet. It
                 invites now.

                 EMPTY: the gendered silhouette shows through a wash of the
                 event's colour with one round shutter on it — a human shape
                 says "your face goes here" far better than an outline and a
                 "3:4" label ever did, and it is drawn for this ratio
                 (<x-gender-avatar>).

                 FILLED: the photograph is the hero. Change is the one filled
                 control; re-crop and remove are quiet round icons beside it.

                 PORTRAIT 3:4 at every state (CLAUDE.md → Profile Pictures Are
                 Portrait 3:4), so nothing jumps when a new picture lands. --}}
            <div class="pass m-card">
                <div class="pass-band">
                    <span class="pass-orb" style="inset-inline-end:-44px; top:-56px; width:150px; height:150px;"></span>
                    <span class="pass-orb" style="inset-inline-end:38px; bottom:-26px; width:70px; height:70px; background:rgba(255,255,255,.06);"></span>

                    <div class="relative flex items-center justify-between gap-2">
                        <span class="pass-eyebrow">{{ __('events.entry_field_photo') }}</span>
                        {{-- The division, said plainly — including when there is
                             not one yet, because "blank" and "not placed" read
                             the same and only one of them is true. --}}
                        <span class="pass-chip">
                            <i class="bi bi-diagram-3 bracket-icon"></i>
                            <span class="truncate" x-text="entry.division || @js(__('events.entry_no_division_yet'))"></span>
                        </span>
                    </div>
                </div>

                <div class="pass-body">
                    <button type="button" @click="editable('photo') && (photoSheet = true)"
                            :disabled="!editable('photo')"
                            class="pass-plate m-press"
                            :aria-label="entry.photo ? @js(__('events.public_enrol_photo_change')) : @js(__('events.entry_photo_add_cta'))">
                        {{-- `brokenPhoto` remembers a URL whose FILE is not on
                             disk. The row still points at it, so `entry.photo`
                             is truthy and this would otherwise paint a
                             broken-image glyph — which is what a spectator sees
                             today on the participants list, where 8 of 30
                             competitor photographs 404. Falls through to the
                             silhouette instead.
                             ⚠️ `x-on:error`, never `@error` — that is a BLADE
                             directive and would compile away. --}}
                        <template x-if="entry.photo && entry.photo !== brokenPhoto">
                            <img :src="entry.photo" alt="" x-on:error="brokenPhoto = entry.photo">
                        </template>

                        {{-- No photograph is not a bug and must be silent. --}}
                        <template x-if="!entry.photo || entry.photo === brokenPhoto">
                            <span class="pass-ghost">
                                <x-gender-avatar :gender="$entry['gender']" class="w-full h-full" :bg="$ev" sizes="100px" />
                            </span>
                        </template>

                        {{-- The invitation, on the plate itself: the whole thing
                             is the tap target while it is empty. --}}
                        <template x-if="(!entry.photo || entry.photo === brokenPhoto) && editable('photo')">
                            <span class="pass-wash">
                                <span class="pass-add"><i class="bi bi-camera-fill"></i></span>
                            </span>
                        </template>

                        {{-- Filled: a small shutter in the corner, so the
                             picture stays the hero. --}}
                        <template x-if="entry.photo && entry.photo !== brokenPhoto && editable('photo')">
                            <span class="pass-fab"><i class="bi bi-camera-fill"></i></span>
                        </template>
                    </button>

                    <div class="min-w-0 flex-1" style="padding-bottom:2px;">
                        <p class="pass-name truncate" x-text="entry.name"></p>

                        <span class="pass-state" x-show="entry.photo && entry.photo !== brokenPhoto" x-cloak
                              style="color: {{ $ev }}; background: {{ Palette::alpha($ev, .12) }};">
                            <i class="bi bi-check-circle-fill"></i>{{ __('events.entry_photo_on_file') }}
                        </span>

                        {{-- No "add a photo" badge here. It read as a third
                             button two inches from the real one — the exact
                             crowding this card was redesigned to remove. The
                             empty plate and the single CTA below already say it. --}}
                    </div>
                </div>

                {{-- One warm line saying WHY, only while it is missing. --}}
                <p class="pass-why" x-show="!entry.photo || entry.photo === brokenPhoto" x-cloak>
                    {{ __('events.entry_finish_photo_why') }}
                </p>

                <div class="pass-acts" x-show="editable('photo')" x-cloak>
                    <button type="button" @click="photoSheet = true" class="pass-cta m-press">
                        <i class="bi" :class="entry.photo && entry.photo !== brokenPhoto ? 'bi-arrow-repeat' : 'bi-camera-fill'"></i>
                        <span x-text="entry.photo && entry.photo !== brokenPhoto
                            ? @js(__('events.public_enrol_photo_change'))
                            : @js(__('events.entry_photo_add_cta'))"></span>
                    </button>

                    {{-- Re-frame what is already there. Hidden when the file
                         behind the URL is missing from disk: there is nothing to
                         re-crop, and offering it would open an empty cropper. --}}
                    <button type="button" x-show="entry.photo && entry.photo !== brokenPhoto" x-cloak
                            @click="recrop()" :disabled="saving"
                            class="pass-ico m-press"
                            :aria-label="@js(__('events.entry_photo_recrop'))"
                            title="{{ __('events.entry_photo_recrop_hint') }}">
                        <i class="bi bi-crop"></i>
                    </button>

                    <button type="button" x-show="entry.photo && entry.photo !== brokenPhoto" x-cloak
                            @click="save({ photo: null })"
                            class="pass-ico m-press"
                            :aria-label="@js(__('events.entry_photo_remove'))"
                            title="{{ __('events.entry_photo_remove') }}">
                        <i class="bi bi-trash3"></i>
                    </button>
                </div>

                <p class="pass-locked" x-show="!editable('photo')" x-cloak>
                    <i class="bi bi-lock-fill" style="margin-top:2px;"></i><span x-text="reason('photo')"></span>
                </p>
            </div>

            {{-- ===== The weigh-in truth =====
                 A figure an official signed for and a figure somebody typed are
                 not the same fact and must never look the same. `weighed_in_by`
                 is what the final draw filters on. --}}
            <div class="m-card rounded-2xl p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] uppercase font-bold text-muted-foreground" style="letter-spacing:.12em;">
                            {{ __('events.entry_field_weight') }}
                        </p>
                        <p class="mt-1">
                            <span class="text-[32px] font-black leading-none tabular-nums text-foreground"
                                  x-text="entry.weight !== null ? Number(entry.weight).toFixed(1) : '—'"></span>
                            <span class="text-[13px] font-bold text-muted-foreground ms-1" x-show="entry.weight !== null">kg</span>
                        </p>
                    </div>

                    <button type="button" x-show="editable('weight')" x-cloak @click="openSheet('weight')"
                            class="m-press flex-shrink-0 inline-flex items-center gap-1.5 h-10 px-4 rounded-2xl text-[12px] font-black text-white"
                            style="background: {{ $ev }};">
                        <i class="bi bi-pencil-fill text-[11px]"></i>{{ __('shared.edit') }}
                    </button>
                </div>

                <p class="mt-3 inline-flex items-start gap-1.5 px-2.5 py-1.5 rounded-xl text-[11.5px] font-bold leading-snug"
                   :style="entry.weighed_in ? 'color:#15803d; background:#dcfce7' : 'color:#b45309; background:#fef3c7'">
                    <i class="bi mt-0.5" :class="entry.weighed_in ? 'bi-check-circle-fill' : 'bi-info-circle-fill'"></i>
                    <span x-text="entry.weighed_in ? @js(__('events.entry_weighed_in')) : @js(__('events.entry_self_declared'))"></span>
                </p>

                <p x-show="!editable('weight')" x-cloak
                   class="text-[11px] text-muted-foreground mt-2 flex items-start gap-1.5">
                    <i class="bi bi-lock-fill mt-0.5"></i><span x-text="reason('weight')"></span>
                </p>
            </div>

            {{-- ===== The rest, as rows =====
                 One screen, one job: a row states the fact and opens a sheet
                 that asks for exactly that one thing. --}}
            <div class="m-card rounded-2xl overflow-hidden">
                <template x-for="(row, i) in rows" :key="row.field">
                    <div>
                        <div class="border-t border-gray-100" x-show="i > 0"></div>
                        <button type="button" @click="editable(row.field) && openSheet(row.field)"
                                :disabled="!editable(row.field)"
                                class="m-press w-full px-4 py-3.5 flex items-center gap-3 text-start">
                            <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                                  style="color: {{ $ev }}; background: {{ Palette::alpha($ev, .1) }};">
                                <i class="bi" :class="row.icon"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[11px] font-bold uppercase text-muted-foreground" style="letter-spacing:.1em;"
                                      x-text="row.label"></span>
                                <span class="block text-[13.5px] font-black text-foreground truncate mt-0.5">
                                    <span x-show="row.field === 'nationality' && entry.nationality" x-cloak
                                          :class="'fi fi-' + (entry.nationality || '').toLowerCase()"
                                          class="inline-block align-middle me-1.5"
                                          style="width:20px; height:15px; border-radius:3px;"></span><span x-text="value(row.field)"></span>
                                </span>
                                <span x-show="!editable(row.field)" x-cloak
                                      class="block text-[11px] text-muted-foreground mt-1" x-text="reason(row.field)"></span>
                            </span>
                            <i class="bi flex-shrink-0 text-muted-foreground"
                               :class="editable(row.field) ? 'bi-chevron-right' : 'bi-lock-fill'"></i>
                        </button>
                    </div>
                </template>
            </div>

            {{-- ===== When you fight =====
                 The question this panel could not answer.

                 It said what the ENTRY was — name, division, fee, receipt — and
                 nothing about competing. A competitor's own draw position, their
                 opponent, the mat and whether the draw had even been made were
                 all in the system and none of it was ever shown to the person it
                 is about.

                 DESIGN, and the order is the argument: the reader wants "am I
                 in" (above), then "when do I fight", then "what do I owe". So
                 this sits between the entry and the fee, in the panel's own row
                 idiom rather than as a board — three compact states, never a
                 blank.

                 The WITHHELD state deliberately does NOT use `<x-draw-veil>`,
                 even though it says the same sentence: the veil is a centred
                 p-6 card built to stand in for a whole bracket, and dropped in
                 here it reads as "this page is blocked" instead of "one fact is
                 not out yet". The SENTENCE is what must be shared, and it is —
                 `EventAccess::drawHiddenMessage()`, the same words the board's
                 veil, the bout redirect and the console row use. --}}
            <div class="mt-5">
                <h2 class="text-[11px] font-bold uppercase text-muted-foreground px-1 mb-2" style="letter-spacing:.1em;">
                    {{ __('events.entry_bouts_title') }}
                </h2>

                <div class="m-card rounded-2xl overflow-hidden">

                    {{-- The division always leads: it is the fact that makes
                         every row beneath it mean something, and it is known
                         long before the draw is. --}}
                    @php
                        $myDivision = ($myBouts['entry']['division'] ?? null) ?: ($myBouts['entry']['category'] ?? null);
                    @endphp
                    <div class="px-4 py-3.5 flex items-center gap-3">
                        <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                              style="color: {{ $ev }}; background: {{ Palette::alpha($ev, .1) }};">
                            <i class="bi bi-people-fill"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[10px] font-bold uppercase text-muted-foreground" style="letter-spacing:.08em;">{{ __('events.entry_bouts_division') }}</span>
                            <span class="block text-sm font-bold text-foreground truncate">
                                {{ $myDivision ?: __('events.entry_bouts_unplaced') }}
                            </span>
                            @unless($myDivision)
                                <span class="block text-[11.5px] text-muted-foreground mt-0.5">{{ __('events.entry_bouts_unplaced_sub') }}</span>
                            @endunless
                        </span>
                    </div>

                    @if(! ($drawOpen ?? false))
                        {{-- Withheld. Says WHEN, never nothing. --}}
                        <div class="px-4 py-3.5 flex items-center gap-3 border-t border-gray-100">
                            <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 text-muted-foreground" style="background: rgba(0,0,0,.05);">
                                <i class="bi bi-lock-fill"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground">{{ $drawNote }}</span>
                                <span class="block text-[11.5px] text-muted-foreground mt-0.5">{{ __('events.draw_hidden_sub') }}</span>
                            </span>
                        </div>
                    @elseif(empty($myBouts['bouts'] ?? []))
                        {{-- Drawn, but not this competitor yet. --}}
                        <div class="px-4 py-3.5 flex items-center gap-3 border-t border-gray-100">
                            <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 text-muted-foreground" style="background: rgba(0,0,0,.05);">
                                <i class="bi bi-hourglass-split"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground">{{ __('events.entry_bouts_none') }}</span>
                                <span class="block text-[11.5px] text-muted-foreground mt-0.5">{{ __('events.entry_bouts_none_sub') }}</span>
                            </span>
                        </div>
                    @else
                        @php
                            /* The NEXT bout is the first one not yet decided —
                               the one row a competitor is actually looking for,
                               so it is the only one that carries the event's
                               colour. Everything above it is history. */
                            $nextKey = null;
                            foreach ($myBouts['bouts'] as $i => $b) {
                                if (! ($b['decided'] ?? false)) { $nextKey = $i; break; }
                            }
                        @endphp
                        @foreach($myBouts['bouts'] as $i => $b)
                            @php
                                $isNext = $i === $nextKey;
                                $done = (bool) ($b['decided'] ?? false);
                                $won = (bool) ($b['won'] ?? false);
                                $bye = (bool) ($b['bye'] ?? false);
                            @endphp
                            <div class="px-4 py-3.5 flex items-center gap-3 border-t border-gray-100"
                                 @if($isNext) style="background: {{ Palette::alpha($ev, .05) }};" @endif>
                                <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                                      @if($bye)
                                          style="color: #6b7689; background: rgba(0,0,0,.05);"
                                      @elseif($done)
                                          style="color: {{ $won ? '#059669' : '#b91c1c' }}; background: {{ $won ? 'rgba(5,150,105,.1)' : 'rgba(185,28,28,.08)' }};"
                                      @else
                                          style="color: {{ $ev }}; background: {{ Palette::alpha($ev, .1) }};"
                                      @endif>
                                    <i class="bi {{ $bye ? 'bi-fast-forward-fill' : ($done ? ($won ? 'bi-trophy-fill' : 'bi-x-lg') : 'bi-hourglass-split') }}"></i>
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-1.5">
                                        <span class="text-[10px] font-bold uppercase text-muted-foreground" style="letter-spacing:.08em;">{{ $b['round'] ?: $b['phase'] }}</span>
                                        @if($isNext && ! $done)
                                            <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded-full"
                                                  style="letter-spacing:.08em; color: {{ $ev }}; background: {{ Palette::alpha($ev, .12) }};">{{ __('events.entry_bouts_next') }}</span>
                                        @endif
                                    </span>

                                    <span class="block text-sm font-bold text-foreground truncate">
                                        {{ $bye ? __('events.entry_bouts_bye') : $b['opponent'] }}
                                    </span>

                                    {{-- Only what is actually known. A draw is
                                         published long before mats and times
                                         exist, and printing an empty "Mat —"
                                         would read as information. --}}
                                    @php
                                        $facts = [];
                                        /* The court VERBATIM. Organisers name
                                           their own mats — "Mat 1", "Tatami A"
                                           — so wrapping it in a "Mat :mat"
                                           label produced "Mat Mat 1". The name
                                           on the wall is the name to print. */
                                        if (! empty($b['mat']))      $facts[] = $b['mat'];
                                        if (! empty($b['at']))       $facts[] = $b['at'];
                                        if (! empty($b['match_no'])) $facts[] = '#'.$b['match_no'];
                                        if ($done && ! $bye)         $facts[] = ($won ? __('events.entry_bouts_won') : __('events.entry_bouts_lost')).' '.$b['my_score'].'–'.$b['their_score'];
                                        if (! $done && ! $bye)       $facts[] = __('events.entry_bouts_upcoming');
                                    @endphp
                                    @if($facts)
                                        <span class="block text-[11.5px] text-muted-foreground mt-0.5">{{ implode(' · ', $facts) }}</span>
                                    @endif
                                </span>

                                @if(! empty($b['video_url']))
                                    <a href="{{ $b['video_url'] }}"
                                       class="text-[11px] font-bold flex-shrink-0 no-underline" style="color: {{ $ev }};">
                                        {{ __('events.entry_bouts_watch') }}
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            {{-- ===== The fee =====
                 What is owed, whether it has landed, and the way to settle it —
                 none of which this screen used to say. The organiser could
                 already APPROVE a receipt; there was no way to send one, so the
                 approval had nothing to act on.

                 There is no gateway by decision, so settling is: transfer,
                 photograph the receipt, send it, and the organiser confirms.
                 The state line is the part that matters — somebody who has paid
                 and cannot tell whether it arrived telephones the organiser. --}}
            @if($payment ?? null)
                <div class="mt-5">
                    <h2 class="text-[11px] font-bold uppercase text-muted-foreground px-1 mb-2" style="letter-spacing:.1em;">
                        {{ __('events.entry_pay_title') }}
                    </h2>

                    <div class="m-card rounded-2xl overflow-hidden">
                        <div class="px-4 py-3.5 flex items-center gap-3">
                            <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                                  :style="pay.state === 'approved'
                                      ? 'color:#059669; background:rgba(5,150,105,.1)'
                                      : (pay.state === 'submitted'
                                          ? 'color:#d97706; background:rgba(217,119,6,.1)'
                                          : '{{ 'color: '.$ev.'; background: '.Palette::alpha($ev, .1) }}')">
                                <i class="bi" :class="pay.state === 'approved' ? 'bi-check-circle-fill'
                                    : (pay.state === 'submitted' ? 'bi-hourglass-split' : 'bi-cash-coin')"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[13.5px] font-black text-foreground" x-text="pay.display"></span>
                                <span class="block text-[11.5px] mt-0.5"
                                      :class="pay.state === 'approved' ? 'text-green-600'
                                          : (pay.state === 'submitted' ? 'text-amber-600' : 'text-muted-foreground')"
                                      x-text="payLabel"></span>
                            </span>
                        </div>

                        <template x-if="pay.state !== 'approved'">
                            <div class="border-t border-gray-100 px-4 py-3.5">
                                <p class="text-[11.5px] text-muted-foreground leading-relaxed">{{ __('events.entry_pay_how') }}</p>

                                {{-- The club's own account, so the transfer can
                                     actually be made from this screen rather
                                     than from a phone call asking for it. --}}
                                <template x-if="pay.bank">
                                    <div class="mt-3 rounded-xl p-3" style="background: {{ Palette::alpha($ev, .06) }};">
                                        <p class="text-[10px] font-bold uppercase text-muted-foreground" style="letter-spacing:.1em;">
                                            {{ __('events.entry_pay_account') }} <span x-text="pay.club"></span>
                                        </p>
                                        <template x-for="(v, k) in pay.bank" :key="k">
                                            <p class="text-[12.5px] font-bold text-foreground mt-1" dir="ltr" x-text="v"></p>
                                        </template>
                                    </div>
                                </template>

                                <label class="m-press mt-3 w-full h-12 rounded-2xl font-black text-[13.5px] text-white
                                              inline-flex items-center justify-center gap-2 cursor-pointer"
                                       style="background: {{ $ev }};" :class="payBusy && 'opacity-60 pointer-events-none'">
                                    <i class="bi bi-receipt"></i>
                                    <span x-text="pay.has_proof
                                        ? @js(__('events.entry_pay_replace_proof'))
                                        : @js(__('events.entry_pay_send_proof'))"></span>
                                    <input type="file" accept="image/*" class="hidden" @change="sendProof($event)">
                                </label>
                            </div>
                        </template>
                    </div>
                </div>
            @endif

            {{-- ===== How you sign in =====
                 A card of its own, not two more rows above, because these are
                 not facts about the ENTRY — they are how this person gets back
                 into the account the entry lives in.

                 It is here because there was nowhere else. The public door
                 makes an email optional and signs people in by phone, and every
                 other screen that could add one sits behind email verification
                 — which an account with no email can never pass. So a mistyped
                 number or a forgotten password was the end of it. This panel is
                 the one screen such a person can always reach.

                 Drawn only when the server actually sent the details: it
                 withholds them from anyone who is neither the person nor
                 somebody holding authority over them, and a card with two empty
                 rows would be worse than no card. --}}
            <template x-if="entry.mobile !== undefined || entry.email !== undefined">
                <div class="mt-5">
                    <h2 class="text-[11px] font-bold uppercase text-muted-foreground px-1 mb-2" style="letter-spacing:.1em;">
                        {{ __('events.entry_contact_title') }}
                    </h2>

                    <div class="m-card rounded-2xl overflow-hidden">
                        <template x-for="(row, i) in contactRows" :key="row.field">
                            <div>
                                <div class="border-t border-gray-100" x-show="i > 0"></div>
                                <button type="button" @click="editable(row.field) && openSheet(row.field)"
                                        :disabled="!editable(row.field)"
                                        class="m-press w-full px-4 py-3.5 flex items-center gap-3 text-start">
                                    <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                                          style="color: {{ $ev }}; background: {{ Palette::alpha($ev, .1) }};">
                                        <i class="bi" :class="row.icon"></i>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[11px] font-bold uppercase text-muted-foreground" style="letter-spacing:.1em;"
                                              x-text="row.label"></span>
                                        <span class="block text-[13.5px] font-black text-foreground truncate mt-0.5"
                                              x-text="value(row.field)"></span>
                                        <span x-show="row.field === 'email' && entry.email && !entry.email_verified" x-cloak
                                              class="block text-[11px] text-amber-600 mt-1">
                                            <i class="bi bi-exclamation-circle-fill me-1"></i>{{ __('events.entry_contact_unverified') }}
                                        </span>
                                        <span x-show="!editable(row.field)" x-cloak
                                              class="block text-[11px] text-muted-foreground mt-1" x-text="reason(row.field)"></span>
                                    </span>
                                    <i class="bi flex-shrink-0 text-muted-foreground"
                                       :class="editable(row.field) ? 'bi-chevron-right' : 'bi-lock-fill'"></i>
                                </button>
                            </div>
                        </template>
                    </div>

                    <p class="text-[11.5px] text-muted-foreground mt-2 px-1 flex items-start gap-1.5">
                        <i class="bi bi-info-circle-fill mt-0.5 flex-shrink-0"></i>
                        <span>{{ __('events.entry_contact_hint') }}</span>
                    </p>
                </div>
            </template>

            {{-- ===== Leaving =====
                 Low emphasis, at the very bottom, and never competing with the
                 controls above it. Withdrawing is a real answer, not a
                 mistake — but it is also not what most people opened this for. --}}
            <template x-if="!(withdrawal && withdrawal.state === 'pending') && perm.window.open">
                <div class="pt-2 text-center">
                    <button type="button" @click="openSheet('withdraw')"
                            class="m-press inline-flex items-center gap-2 text-[12.5px] font-bold text-muted-foreground">
                        <i class="bi bi-box-arrow-left"></i>{{ __('events.withdraw_action') }}
                    </button>
                </div>
            </template>
        </div>
    </div>

    {{-- ================= The one sheet =================
         Every field opens the SAME sheet with a different body. One header
         band, one scroll body, one sticky footer — so a new field is a case in
         a switch rather than a fifth copy of a bottom sheet that will drift
         from the other four (Design Rule #8, Shared Stays Shared).

         Teleported to <body>: the page wrapper carries `mobile-stagger`, whose
         animation leaves a transform on its children, and a transform makes
         that element the containing block for anything `position: fixed`
         inside it — a sheet left in place resolves `bottom-0` against a
         few-hundred-pixel wrapper and is clipped. --}}
    <template x-teleport="body">
        <div x-show="sheet" x-cloak class="fixed inset-0 z-[60]">
            <div x-show="sheet" x-transition.opacity @click="closeSheet()" class="absolute inset-0 bg-black/50"></div>

            <div x-show="sheet"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col rounded-t-3xl overflow-hidden bg-background">

                {{-- The band. The gradient is `#hex → #hex + b0`: the alpha
                     suffix is HEX-ONLY, and an hsl() with it appended is an
                     invalid gradient the browser drops whole. --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $ev }}, {{ $ev }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi text-xl" :class="sheetIcon"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight" x-text="sheetTitle"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="sheetHint"></p>
                        </div>
                        <button type="button" @click="closeSheet()" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 pt-4 pb-3">

                    {{-- ---- Weight ---- --}}
                    <div x-show="sheet === 'weight'">
                        <div class="m-card rounded-2xl p-4">
                            <div class="flex items-center justify-between">
                                <label class="text-[12px] font-bold text-foreground">{{ __('events.claim_weight') }}</label>
                                <button type="button" @click="draft.weight = null" x-show="draft.weight"
                                        class="m-press text-[11px] font-bold text-muted-foreground">{{ __('shared.clear') }}</button>
                            </div>
                            <div class="flex items-center justify-center gap-5 mt-2">
                                <button type="button" @click="bump(-0.5)"
                                        class="m-press w-11 h-11 rounded-full bg-muted border border-gray-200 grid place-items-center text-lg text-foreground">
                                    <i class="bi bi-dash-lg"></i>
                                </button>
                                <div class="text-center min-w-[7rem]">
                                    <span class="text-[40px] font-black leading-none tabular-nums text-foreground"
                                          x-text="draft.weight ? Number(draft.weight).toFixed(1) : '—'"></span>
                                    <span class="text-[13px] font-bold text-muted-foreground ms-1">kg</span>
                                </div>
                                <button type="button" @click="bump(0.5)"
                                        class="m-press w-11 h-11 rounded-full bg-muted border border-gray-200 grid place-items-center text-lg text-foreground">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                            <input type="range" min="20" max="140" step="0.5" class="e-range w-full mt-3"
                                   :value="draft.weight ?? 60" @input="draft.weight = Number($event.target.value)">
                            <p x-show="!draft.weight" class="text-[11.5px] text-muted-foreground mt-2 text-center">
                                {{ __('events.claim_weight_blank') }}
                            </p>
                        </div>

                        {{-- Said BEFORE they save, not after: changing a figure
                             an official signed for sends them back to the desk,
                             and that is worth knowing while the slider is still
                             under a thumb. --}}
                        <p x-show="entry.weighed_in" x-cloak
                           class="mt-3 rounded-2xl px-3.5 py-3 text-[11.5px] font-bold leading-snug flex items-start gap-2"
                           style="background:#fef3c7; color:#92400e;">
                            <i class="bi bi-exclamation-triangle-fill mt-0.5"></i>
                            <span>{{ __('events.entry_edit_saved_reweigh') }}</span>
                        </p>
                    </div>

                    {{-- ---- Name ----
                         The one field that is never optional: a competitor with
                         no name breaks every listing, card and search result on
                         the platform, so the server refuses a blank and so does
                         this. Two characters is the server's own floor. --}}
                    <div x-show="sheet === 'name'">
                        <label class="block text-[12px] font-bold text-foreground mb-1.5">{{ __('events.entry_field_name') }}</label>
                        <input type="text" x-model="draft.name" maxlength="120" autocomplete="name"
                               class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                        <p class="text-[11.5px] text-muted-foreground mt-2 flex items-start gap-1.5">
                            <i class="bi bi-info-circle-fill mt-0.5"></i>
                            <span x-text="(draft.name || '').trim().length < 2
                                ? @js(__('events.entry_name_min'))
                                : @js(__('events.entry_name_hint'))"></span>
                        </p>
                    </div>

                    {{-- ---- Email ----
                         Blank is allowed here and refused by the server only if
                         it would leave the account with no way in at all — the
                         one check that needs both fields at once, so it is made
                         where both are visible rather than guessed at here. --}}
                    <div x-show="sheet === 'email'">
                        <label class="block text-[12px] font-bold text-foreground mb-1.5">{{ __('events.entry_contact_email_label') }}</label>
                        <input type="email" x-model="draft.email" maxlength="190" autocomplete="email"
                               inputmode="email" placeholder="{{ __('events.entry_contact_email_placeholder') }}"
                               class="e-field w-full h-12 px-4 rounded-2xl text-[15px]" dir="ltr">
                        <p class="text-[11.5px] text-muted-foreground mt-2 flex items-start gap-1.5">
                            <i class="bi bi-info-circle-fill mt-0.5 flex-shrink-0"></i>
                            <span>{{ __('events.entry_contact_hint') }}</span>
                        </p>
                    </div>

                    {{-- ---- Phone ----
                         Two inputs because `users.mobile` is stored as
                         `{code, number}` and every other reader on the platform
                         expects both halves. One box would force this screen to
                         guess where a dial code ends, and a wrong guess is a
                         number nobody can sign in with. --}}
                    <div x-show="sheet === 'mobile'">
                        <label class="block text-[12px] font-bold text-foreground mb-1.5">{{ __('events.entry_contact_mobile_label') }}</label>
                        <div class="flex items-center gap-2" dir="ltr">
                            <input type="tel" x-model="draft.mobile_code" maxlength="8" inputmode="tel"
                                   placeholder="+973" aria-label="{{ __('events.entry_contact_mobile_code_label') }}"
                                   class="e-field h-12 px-4 rounded-2xl text-[15px]" style="width: 92px; flex: 0 0 auto;">
                            <input type="tel" x-model="draft.mobile" maxlength="24" autocomplete="tel" inputmode="tel"
                                   class="e-field h-12 px-4 rounded-2xl text-[15px]" style="flex: 1 1 auto; min-width: 0;">
                        </div>
                        <p class="text-[11.5px] text-muted-foreground mt-2 flex items-start gap-1.5">
                            <i class="bi bi-info-circle-fill mt-0.5 flex-shrink-0"></i>
                            <span>{{ __('events.entry_contact_hint') }}</span>
                        </p>
                    </div>

                    {{-- ---- Nationality ----
                         The shared country picker (flag, searchable list of all
                         196), wearing this page's own field styling rather than
                         the platform's purple input group — one component, two
                         skins, no second implementation (Component-First). It
                         posts an ISO-2 code, which is exactly what the server
                         validates.

                         `nationalityPick` lives on the ROOT and not in `draft`:
                         the picker keeps its own selected state, and reseeding
                         a draft underneath it would leave the row saying one
                         country while the panel saved another.

                         The min-height is load-bearing — the picker's panel is
                         absolutely positioned, and a one-field sheet is shorter
                         than the panel, so without it the list would be clipped
                         by this scrolling body. --}}
                    <div x-show="sheet === 'nationality'" style="min-height: 340px;">
                        <x-country-dropdown id="myEntryNationality" name="nationality"
                                            :label="__('events.entry_field_nationality')"
                                            :value="$entry['nationality'] ?? ''"
                                            model="nationalityPick"
                                            wrapper-class=""
                                            label-class="block text-[12px] font-bold text-foreground mb-1.5"
                                            trigger-class="e-field w-full h-12 px-4 rounded-2xl text-[15px] flex items-center justify-between" />
                    </div>

                    {{-- ---- Belt ---- --}}
                    <div x-show="sheet === 'belt'">
                        <label class="block text-[12px] font-bold text-foreground mb-2">{{ __('events.claim_belt') }}</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach ($belts as $b)
                                <button type="button" @click="draft.belt_colour = (draft.belt_colour === '{{ $b['value'] }}' ? null : '{{ $b['value'] }}')"
                                        class="m-press e-pick rounded-2xl py-3 flex flex-col items-center gap-1.5"
                                        :class="draft.belt_colour === '{{ $b['value'] }}' && 'is-on'">
                                    <span class="w-8 h-2.5 rounded-full border border-white/25" style="background: {{ $b['bg'] }}"></span>
                                    <span class="text-[11.5px] font-bold">{{ $b['label'] }}</span>
                                </button>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            <label class="block text-[12px] font-bold text-foreground mb-1.5">{{ __('events.entry_field_grade') }}</label>
                            <input type="text" x-model="draft.belt_grade" maxlength="32"
                                   class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                        </div>
                    </div>

                    {{-- ---- Competing for ----
                         Selection cards, not a dropdown: the answer set is
                         short, known and already scoped by the server to the
                         clubs this athlete is actually an active member of. An
                         organiser editing their OWN entry gets the search as
                         well, because they may name any active club. --}}
                    <div x-show="sheet === 'club'">
                        <div class="space-y-2">
                            <button type="button" @click="draft.club = ''"
                                    class="m-press e-pick w-full rounded-2xl px-3 py-3 flex items-center gap-3 text-start"
                                    :class="!draft.club && 'is-on'">
                                <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                                      style="color: {{ $ev }}; background: {{ Palette::alpha($ev, .1) }};">
                                    <i class="bi bi-person"></i>
                                </span>
                                <span class="min-w-0 flex-1 text-[13.5px] font-bold text-foreground">{{ __('events.entry_no_club') }}</span>
                                <i class="bi bi-check-lg flex-shrink-0" x-show="!draft.club" style="color: {{ $ev }};"></i>
                            </button>

                            <template x-for="c in clubOptions" :key="c.slug">
                                <button type="button" @click="draft.club = c.slug"
                                        class="m-press e-pick w-full rounded-2xl px-3 py-3 flex items-center gap-3 text-start"
                                        :class="draft.club === c.slug && 'is-on'">
                                    <template x-if="c.logo">
                                        <span class="w-9 h-9 flex-shrink-0">
                                            <img :src="c.logo" alt="" class="w-full h-full object-contain">
                                        </span>
                                    </template>
                                    <template x-if="!c.logo">
                                        <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                                              style="color: {{ $ev }}; background: {{ Palette::alpha($ev, .1) }};">
                                            <i class="bi bi-building"></i>
                                        </span>
                                    </template>
                                    <span class="min-w-0 flex-1 text-[13.5px] font-bold text-foreground truncate" x-text="c.name"></span>
                                    <span x-show="c.country" :class="'fi fi-' + (c.country || '').toLowerCase()"
                                          class="flex-shrink-0" style="width:20px; height:15px; border-radius:3px;"></span>
                                    <i class="bi bi-check-lg flex-shrink-0" x-show="draft.club === c.slug" style="color: {{ $ev }};"></i>
                                </button>
                            </template>
                        </div>

                        <div x-show="perm.role === 'organiser'" x-cloak class="mt-4">
                            <div class="relative">
                                <input type="text" x-model="clubQuery" @input.debounce.300ms="searchClubs()"
                                       placeholder="{{ __('events.public_enrol_club_search') }}"
                                       class="e-field w-full h-12 ps-10 pe-4 rounded-2xl text-[15px]">
                                <i class="bi bi-search absolute start-4 top-1/2 -translate-y-1/2 text-muted-foreground text-sm"></i>
                            </div>
                            <div x-show="clubResults.length" x-cloak class="mt-2 space-y-1.5">
                                <template x-for="c in clubResults" :key="'s' + c.slug">
                                    <button type="button" @click="adopt(c)"
                                            class="m-press e-pick w-full rounded-2xl px-3 py-2.5 flex items-center gap-3 text-start">
                                        <span class="min-w-0 flex-1 text-[13px] font-bold text-foreground truncate" x-text="c.name"></span>
                                        <span x-show="c.country" :class="'fi fi-' + (c.country || '').toLowerCase()"
                                              class="flex-shrink-0" style="width:20px; height:15px; border-radius:3px;"></span>
                                    </button>
                                </template>
                            </div>
                            <p x-show="clubQuery.trim().length >= 2 && !clubResults.length && !clubSearching" x-cloak
                               class="text-[11.5px] text-muted-foreground mt-2">{{ __('events.public_enrol_club_none_found') }}</p>
                        </div>
                    </div>

                    {{-- ---- Date of birth ----
                         Never demanded of anyone (CLAUDE.md). The dropdown
                         variant expands IN FLOW, so it cannot be clipped by
                         this scrolling body. --}}
                    <div x-show="sheet === 'birthdate'">
                        <label class="block text-[12px] font-bold text-foreground mb-1.5">{{ __('events.entry_field_birthdate') }}</label>
                        <x-date-picker variant="dropdown" model="draft.birthdate" max="{{ now()->subDay()->toDateString() }}" />
                        <p x-show="!draft.birthdate" x-transition.opacity
                           class="text-[11.5px] text-muted-foreground mt-2 flex items-start gap-1.5">
                            <i class="bi bi-info-circle-fill mt-0.5"></i>
                            <span>{{ __('events.claim_birthdate_blank') }}</span>
                        </p>
                    </div>

                    {{-- ---- Gender ---- --}}
                    <div x-show="sheet === 'gender'">
                        <label class="block text-[12px] font-bold text-foreground mb-1.5">{{ __('events.entry_field_gender') }}</label>
                        <x-gender-toggle model="draft.gender" />
                    </div>

                    {{-- ---- Withdraw ----
                         A sheet, never a native confirm(). It explains who
                         actually decides before it asks for anything. --}}
                    <div x-show="sheet === 'withdraw'">
                        <p class="text-[12.5px] text-muted-foreground leading-snug">{{ __('events.withdraw_explain') }}</p>

                        <div class="mt-4">
                            <label class="block text-[12px] font-bold text-foreground mb-1.5">{{ __('events.withdraw_reason_label') }}</label>
                            <textarea x-model="draft.reason" maxlength="300" rows="3"
                                      class="e-field w-full px-4 py-3 rounded-2xl text-[15px]"></textarea>
                        </div>
                    </div>
                </div>

                {{-- The footer. It belongs to a sheet that has something to
                     SUBMIT — every one of these does. --}}
                <div class="flex-shrink-0 px-5 pt-3 bg-white border-t border-gray-100"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="commit()" :disabled="saving"
                            class="m-press w-full h-14 rounded-2xl font-black text-[15px] flex items-center justify-center gap-2 text-white"
                            :class="saving ? 'opacity-40' : ''"
                            style="background: {{ $ev }}; box-shadow: 0 18px 40px -18px {{ $ev }};">
                        <span x-text="saving ? '…' : (sheet === 'withdraw' ? @js(__('events.withdraw_action')) : @js(__('shared.save')))"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ================= The photo sheet =================
         Two ways in, because a phone has two: the camera and what is already on
         it. Both hand the file to the ONE cropper this project has, in its
         inline bottom-sheet mode (CLAUDE.md → One Cropper Everywhere). --}}
    <template x-teleport="body">
        <div x-show="photoSheet" x-cloak class="fixed inset-0 z-[60]">
            <div x-show="photoSheet" x-transition.opacity @click="photoSheet = false" class="absolute inset-0 bg-black/50"></div>

            <div x-show="photoSheet"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col rounded-t-3xl overflow-hidden bg-background">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $ev }}, {{ $ev }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-camera-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events.entry_field_photo') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('events.public_enrol_photo_sheet_hint') }}</p>
                        </div>
                        <button type="button" @click="photoSheet = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- A read-only sheet would have no footer; this one's body IS
                     the last element, so it carries the safe-area padding. --}}
                <div class="flex-1 overflow-y-auto px-5 pt-4"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <div class="grid grid-cols-2 gap-3">
                        <button type="button" x-show="!cameraFallback" @click="openCamera()"
                                class="ph-tile m-press text-white"
                                style="background: linear-gradient(155deg, {{ $ev }}, {{ $ev }}b0); box-shadow: 0 20px 42px -22px {{ $ev }};">
                            <span class="ph-corner tl"></span><span class="ph-corner tr"></span>
                            <span class="ph-corner bl"></span><span class="ph-corner br"></span>
                            <span class="ph-lens" style="background: rgba(255,255,255,.16); box-shadow: 0 0 0 8px rgba(255,255,255,.08);">
                                <i class="bi bi-camera" style="font-size: 26px;"></i>
                            </span>
                            <span class="relative">
                                <span class="ph-title block">{{ __('events.public_enrol_photo_take') }}</span>
                                <span class="ph-hint block" style="color: rgba(255,255,255,.8);">{{ __('events.public_enrol_photo_take_hint') }}</span>
                            </span>
                        </button>

                        {{-- The same tile when there is no camera API or the
                             permission was refused: one door, not a third
                             option appearing underneath. --}}
                        <label x-show="cameraFallback" x-cloak
                               class="ph-tile m-press text-white cursor-pointer"
                               style="background: linear-gradient(155deg, {{ $ev }}, {{ $ev }}b0); box-shadow: 0 20px 42px -22px {{ $ev }};">
                            <span class="ph-corner tl"></span><span class="ph-corner tr"></span>
                            <span class="ph-corner bl"></span><span class="ph-corner br"></span>
                            <span class="ph-lens" style="background: rgba(255,255,255,.16); box-shadow: 0 0 0 8px rgba(255,255,255,.08);">
                                <i class="bi bi-camera-fill" style="font-size: 26px;"></i>
                            </span>
                            <span class="relative">
                                <span class="ph-title block">{{ __('events.public_enrol_photo_take') }}</span>
                                <span class="ph-hint block" style="color: rgba(255,255,255,.8);">{{ __('events.public_enrol_photo_take_native_hint') }}</span>
                            </span>
                            <input type="file" accept="image/*" capture="user" class="hidden" @change="handOff($event)">
                        </label>

                        <label class="ph-tile m-press cursor-pointer"
                               style="background: #fff; border: 1.5px solid hsl(210 14% 88%);">
                            <span class="ph-print" style="width: 46px; height: 58px; top: 30px; inset-inline-start: 26px; transform: rotate(-11deg); background: {{ Palette::alpha($ev, .1) }};"></span>
                            <span class="ph-print" style="width: 46px; height: 58px; top: 26px; inset-inline-start: 40px; transform: rotate(7deg); background: {{ Palette::alpha($ev, .16) }};"></span>
                            <span class="ph-print grid place-items-center" style="width: 50px; height: 62px; top: 32px; inset-inline-start: 33px; background: {{ Palette::alpha($ev, .28) }}; color: #fff;">
                                <i class="bi bi-images" style="font-size: 22px;"></i>
                            </span>
                            <span class="relative">
                                <span class="ph-title block text-foreground">{{ __('events.public_enrol_photo_gallery') }}</span>
                                <span class="ph-hint block text-muted-foreground">{{ __('events.public_enrol_photo_gallery_hint') }}</span>
                            </span>
                            <input type="file" accept="image/*" class="hidden" @change="handOff($event)">
                        </label>
                    </div>

                    <p class="text-[11px] text-muted-foreground leading-snug pt-4">{{ __('events.public_enrol_photo_ratio') }}</p>
                </div>
            </div>
        </div>
    </template>

    {{-- ================= The camera =================
         A capture screen laid out like a camera app: the frame in the middle at
         the crop's true 3:4 with the face oval inside it, and every control in
         the bottom third where a thumb already is. The shot goes to the cropper
         rather than straight to the server — the guide gets the face roughly
         right, the crop makes it exact. --}}
    <template x-teleport="body">
        <div x-show="camera" x-cloak class="fixed inset-0 z-[70] flex flex-col" style="background:#0a0a0f;">
            <div class="cam-stage" style="--cam: {{ $ev }};">
                <video x-ref="video" autoplay playsinline muted class="cam-video"
                       :style="cameraFacing === 'user' ? 'transform: scaleX(-1)' : ''"></video>

                <div class="cam-frame">
                    <span class="cam-b tl"></span><span class="cam-b tr"></span>
                    <span class="cam-b bl"></span><span class="cam-b br"></span>
                    <span class="cam-face"></span>
                </div>

                <div class="cam-top">
                    <button type="button" @click="closeCamera()" aria-label="{{ __('shared.close') }}" class="cam-round m-press">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <span class="cam-label">{{ __('events.entry_field_photo') }}</span>
                    <span class="cam-chip"><i class="bi bi-person-bounding-box"></i>3:4</span>
                </div>

                <p class="cam-hint" x-text="cameraHint"></p>
            </div>

            <div class="cam-bar" style="--cam: {{ $ev }};">
                <span></span>
                <button type="button" @click="shoot()" aria-label="{{ __('events.public_enrol_photo_take') }}" class="cam-shutter">
                    <span></span>
                </button>
                <span class="cam-side">
                    <button type="button" @click="flipCamera()" x-show="cameraCanFlip" x-cloak
                            aria-label="{{ __('events.public_enrol_photo_flip') }}" class="cam-round m-press">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </span>
            </div>
        </div>
    </template>

    {{-- The cropper. ONE per project, inline bottom-sheet mode, at the
         platform's portrait 3:4 — stored 600×800. `mode="form"` keeps the bytes
         in a hidden input instead of uploading them, which is what this page
         needs: the PUT carries the data URI and EntryEditor sniffs the real
         bytes and assigns the extension itself. --}}
    <div class="hidden">
        <x-takeone-cropper
            id="myEntryPhoto" mode="form" :inline="true"
            :width="600" :height="800" shape="rectangle" :canvasHeight="360"
            folder="temp" filename="entrant" inputName="photo"
            sheetMaxWidth="100%" sheetClass="rounded-t-3xl shadow-2xl bg-background"
            :showControls="false" :showCancel="false"
            saveText="{{ __('shared.save') }}" />
    </div>
</div>
@endif

{{-- Signing out — OUTSIDE the feature-flag branch, so it is there whichever
     panel rendered.

     This is where an entrant actually lives; they would never think to open
     the organiser's sign-in door to find it. Before this there was no way out
     of the sealed app at all: the platform's sign-out sits behind a navigation
     bar this surface deliberately does not render, so a phone handed to the
     next competitor stayed signed in as the last one.

     A POST (CSRF), landing on the poster rather than the platform. Quiet, and
     last: nobody opened their entry in order to leave. --}}
<div class="px-4 pb-[max(2rem,calc(1.5rem+env(safe-area-inset-bottom)))]">
    <form method="POST" action="{{ route('events.public.sign-out', ['event' => $event->uuid]) }}">
        @csrf
        <button type="submit"
                class="m-press w-full h-11 rounded-2xl text-[12.5px] font-bold flex items-center justify-center gap-2"
                style="background:#fff; border:1px solid hsl(210 14% 88%); color: hsl(220 10% 45%);">
            <i class="bi bi-box-arrow-right"></i>{{ __('events.public_sign_out', ['name' => $entry['name'] ?? '']) }}
        </button>
    </form>
</div>
@endsection

@push('styles')
<style>
    /* Real CSS, not arbitrary Tailwind values: the bundle is PREBUILT, so a
       class nobody used before has no rule at all and renders as nothing. */
    .e-field {
        background: #fff;
        border: 1px solid hsl(210 14% 88%);
        color: hsl(220 20% 15%);
    }
    .e-field::placeholder { color: hsl(220 10% 60%); }
    .e-field:focus {
        outline: none;
        border-color: {{ $ev }};
        box-shadow: 0 0 0 4px {{ $ev }}2e;
    }

    .e-pick { border: 1.5px solid hsl(210 14% 88%); background: #fff; }
    .e-pick.is-on {
        border-color: {{ $ev }};
        background: {{ $ev }}14;
        box-shadow: 0 0 0 4px {{ $ev }}1f;
    }

    input[type=range].e-range { accent-color: {{ $ev }}; }

    /* ===== Finish your entry =====
       The prompt that asks for what the door no longer collects. Real CSS —
       the Tailwind bundle is PREBUILT. */
    .fin { position: relative; overflow: hidden; border-radius: 22px; padding: 14px 16px 15px;
           background: {{ Palette::alpha($ev, .07) }}; border: 1.5px solid {{ Palette::alpha($ev, .28) }}; }
    .fin-tile { flex: none; width: 38px; height: 38px; border-radius: 13px; display: grid;
                place-items: center; color: #fff; font-size: 16px;
                background: linear-gradient(150deg, {{ $evDeep }} 0%, {{ $evFade }} 100%);
                box-shadow: 0 10px 22px -12px {{ $ev }}; }
    .fin-title { font-size: 14.5px; font-weight: 900; line-height: 1.2; color: hsl(220 20% 15%); }
    .fin-hint { font-size: 11.5px; line-height: 1.45; margin-top: 3px; color: hsl(220 10% 45%); }
    .fin-count { flex: none; min-width: 24px; height: 24px; padding: 0 7px; border-radius: 999px;
                 display: grid; place-items: center; font-size: 11.5px; font-weight: 900;
                 color: #fff; background: {{ $ev }}; }
    .fin-chips { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 12px; }
    .fin-chip { display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 12px;
                border-radius: 999px; background: #fff; border: 1px solid {{ Palette::alpha($ev, .3) }};
                color: hsl(220 20% 15%); font-size: 12px; font-weight: 800; }
    /* The photograph is the one the draw and the hall screens need, so it is
       the filled one — the rest are equals behind it. */
    .fin-chip.is-key { background: {{ $ev }}; border-color: {{ $ev }}; color: #fff;
                       box-shadow: 0 12px 24px -16px {{ $ev }}; }
    .fin-why { margin: 11px 0 0; font-size: 11px; line-height: 1.45; color: hsl(220 10% 45%); }

    /* ===== The competitor's pass =====
       Real CSS, not arbitrary Tailwind values: the bundle is PREBUILT, so a
       class nobody used before has no rule at all and renders as nothing. */
    .pass { position: relative; overflow: hidden; border-radius: 24px; background: #fff;
            border: 1.5px solid hsl(210 14% 88%); }
    .pass-band { position: relative; overflow: hidden; padding: 13px 16px 54px; color: #fff;
                 background: linear-gradient(150deg, {{ $evDeep }} 0%, {{ $evFade }} 100%); }
    .pass-orb { position: absolute; border-radius: 50%; background: rgba(255,255,255,.09);
                pointer-events: none; }
    .pass-eyebrow { font-size: 9.5px; font-weight: 900; letter-spacing: .18em;
                    text-transform: uppercase; color: rgba(255,255,255,.85); }
    .pass-chip { display: inline-flex; align-items: center; gap: 6px; max-width: 62%;
                 padding: 4px 10px; border-radius: 999px; background: rgba(255,255,255,.18);
                 border: 1px solid rgba(255,255,255,.22);
                 font-size: 10px; font-weight: 800; line-height: 1.5; }

    /* The plate rides UP over the band's tail — the same move the hero band
       makes (Design Rule #6), which is what stops this reading as two boxes. */
    .pass-body { position: relative; display: flex; align-items: flex-end; gap: 14px;
                 padding: 0 16px 12px; margin-top: -46px; }
    /* PORTRAIT 3:4 at every state (CLAUDE.md), so nothing jumps when a picture
       lands. The white ring is what lifts it off the band. */
    .pass-plate { position: relative; flex: none; width: 100px; height: 133px; border-radius: 20px;
                  overflow: hidden; background: {{ Palette::alpha($ev, .08) }};
                  box-shadow: 0 0 0 3px #fff, 0 16px 34px -18px rgba(15,23,42,.55); }
    .pass-plate img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .pass-ghost { position: absolute; inset: 0; opacity: .55; }
    .pass-wash { position: absolute; inset: 0;
                 background: linear-gradient(180deg, rgba(255,255,255,0) 34%, {{ Palette::alpha($ev, .6) }} 100%); }
    .pass-add { position: absolute; inset-inline-start: 50%; bottom: 9px; transform: translateX(-50%);
                width: 34px; height: 34px; border-radius: 50%; display: grid; place-items: center;
                color: #fff; font-size: 14px; background: {{ $ev }};
                box-shadow: 0 6px 16px -6px rgba(15,23,42,.65); }
    .pass-fab { position: absolute; inset-inline-end: 6px; bottom: 6px; width: 28px; height: 28px;
                border-radius: 50%; display: grid; place-items: center; color: #fff; font-size: 12px;
                background: {{ $ev }}; box-shadow: 0 4px 12px -4px rgba(15,23,42,.6); }

    .pass-name { font-size: 16px; font-weight: 900; line-height: 1.15; color: hsl(220 20% 15%); }
    .pass-state { display: inline-flex; align-items: center; gap: 6px; margin-top: 7px;
                  padding: 4px 9px; border-radius: 999px; font-size: 10.5px; font-weight: 800; }
    .pass-why { padding: 0 16px; margin: 0 0 12px; font-size: 11.5px; line-height: 1.45;
                color: hsl(220 10% 45%); }
    .pass-acts { display: flex; align-items: center; gap: 8px; padding: 0 16px 16px; }
    .pass-cta { display: inline-flex; align-items: center; justify-content: center; gap: 7px;
                height: 38px; padding: 0 16px; border-radius: 999px; color: #fff;
                font-size: 12px; font-weight: 900; background: {{ $ev }};
                box-shadow: 0 14px 26px -16px {{ $ev }}; }
    .pass-ico { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 50%;
                background: #fff; border: 1px solid hsl(210 14% 88%);
                color: hsl(220 10% 45%); font-size: 13px; }
    .pass-locked { display: flex; align-items: flex-start; gap: 6px; padding: 0 16px 16px;
                   font-size: 11px; line-height: 1.45; color: hsl(220 10% 45%); }

    /* The photo sheet's two tiles — portrait 4:5, echoing the 3:4 about to be
       cropped, so the sheet reads as being about a photograph. */
    .ph-tile { position: relative; overflow: hidden; border-radius: 22px; aspect-ratio: 4 / 5;
               display: flex; flex-direction: column; justify-content: flex-end;
               padding: 14px; text-align: start; }
    .ph-corner { position: absolute; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.55); }
    .ph-corner.tl { top: 12px; inset-inline-start: 12px; border-right: 0; border-bottom: 0; border-radius: 6px 0 0 0; }
    .ph-corner.tr { top: 12px; inset-inline-end: 12px; border-left: 0; border-bottom: 0; border-radius: 0 6px 0 0; }
    .ph-corner.bl { bottom: 12px; inset-inline-start: 12px; border-right: 0; border-top: 0; border-radius: 0 0 0 6px; }
    .ph-corner.br { bottom: 12px; inset-inline-end: 12px; border-left: 0; border-top: 0; border-radius: 0 0 6px 0; }
    .ph-lens { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -58%);
               width: 62px; height: 62px; border-radius: 50%; display: grid; place-items: center; }
    .ph-print { position: absolute; border-radius: 8px; }
    .ph-title { font-size: 13px; font-weight: 900; line-height: 1.15; }
    .ph-hint { font-size: 10.5px; line-height: 1.3; margin-top: 3px; }

    /* The camera. */
    .cam-stage { position: relative; flex: 1; overflow: hidden; }
    .cam-video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
    .cam-frame { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -54%);
                 width: min(78vw, 360px); aspect-ratio: 3 / 4; border-radius: 20px;
                 box-shadow: 0 0 0 100vmax rgba(6, 8, 16, .62); pointer-events: none; }
    .cam-b { position: absolute; width: 26px; height: 26px; border: 3px solid var(--cam); }
    .cam-b.tl { top: -1px; left: -1px;  border-right: 0; border-bottom: 0; border-radius: 20px 0 0 0; }
    .cam-b.tr { top: -1px; right: -1px; border-left: 0;  border-bottom: 0; border-radius: 0 20px 0 0; }
    .cam-b.bl { bottom: -1px; left: -1px;  border-right: 0; border-top: 0; border-radius: 0 0 0 20px; }
    .cam-b.br { bottom: -1px; right: -1px; border-left: 0; border-top: 0; border-radius: 0 0 20px 0; }
    .cam-face { position: absolute; left: 50%; top: 42%; transform: translate(-50%, -50%);
                width: 62%; height: 66%; border-radius: 50%; border: 2px dashed rgba(255,255,255,.55); }
    .cam-top { position: absolute; inset-inline: 0; top: 0; z-index: 2;
               display: flex; align-items: center; justify-content: space-between;
               gap: 12px; padding: calc(env(safe-area-inset-top) + 14px) 16px 14px;
               background: linear-gradient(rgba(6,8,16,.72), rgba(6,8,16,0)); }
    .cam-round { width: 42px; height: 42px; border-radius: 50%; display: grid; place-items: center;
                 color: #fff; background: rgba(255,255,255,.14);
                 border: 1px solid rgba(255,255,255,.22); backdrop-filter: blur(6px); }
    .cam-label { font-size: 11px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase;
                 color: rgba(255,255,255,.9); }
    .cam-chip { display: inline-flex; align-items: center; gap: 6px; padding: 5px 11px;
                border-radius: 999px; background: rgba(255,255,255,.14);
                border: 1px solid rgba(255,255,255,.2); backdrop-filter: blur(6px);
                font-size: 10px; font-weight: 800; letter-spacing: .1em; color: #fff; }
    .cam-hint { position: absolute; inset-inline: 0; bottom: 18px; z-index: 2; text-align: center;
                padding: 0 28px; font-size: 12.5px; line-height: 1.45; font-weight: 600;
                color: rgba(255,255,255,.86); text-shadow: 0 2px 12px rgba(0,0,0,.6); }
    .cam-bar { flex-shrink: 0; display: grid; grid-template-columns: 1fr auto 1fr;
               align-items: center; padding: 20px 26px calc(24px + env(safe-area-inset-bottom)); }
    .cam-shutter { grid-column: 2; width: 78px; height: 78px; border-radius: 50%;
                   display: grid; place-items: center; background: transparent;
                   border: 3px solid rgba(255,255,255,.9); transition: transform .12s ease; }
    .cam-shutter:active { transform: scale(.94); }
    .cam-shutter > span { width: 62px; height: 62px; border-radius: 50%; background: #fff;
                          box-shadow: 0 0 0 4px var(--cam); }
    .cam-side { display: flex; justify-content: flex-end; }

    /* The toast. On palette, and it respects a reader who asked for less
       motion — the shared m-* classes already do. */
    .ev-toast { position: fixed; left: 1rem; right: 1rem; z-index: 90;
                bottom: calc(1.5rem + env(safe-area-inset-bottom));
                color: #fff; font-size: 12.5px; line-height: 1.4; font-weight: 600;
                padding: .85rem 1rem; border-radius: 1rem;
                box-shadow: 0 20px 40px -20px rgba(0,0,0,.7);
                transition: opacity .3s, transform .3s; transform: translateY(8px); opacity: 0; }

    @media (prefers-reduced-motion: reduce) {
        .cam-shutter, .ev-toast { transition: none; }
    }
</style>
@endpush

@push('scripts')
<script>
/* Outside the app shell, so the product's own toast container does not exist
   here. One small on-palette notice instead — never a native dialog
   (CLAUDE.md → toast-only notifications). Published under the name the rest of
   the platform calls, so the code below reads the same as it would anywhere. */
window.showToast = window.showToast || function (type, msg) {
    if (!msg) return;
    const n = document.createElement('div');
    n.className = 'ev-toast';
    n.textContent = msg;
    n.style.background = type === 'error' ? '#7f1d1d' : '#111827';
    document.body.appendChild(n);
    requestAnimationFrame(() => { n.style.opacity = '1'; n.style.transform = 'none'; });
    setTimeout(() => { n.style.opacity = '0'; n.style.transform = 'translateY(8px)'; setTimeout(() => n.remove(), 320); }, 4200);
};

function myEntry() {
    return {
        /* Server truth, and the only truth. Every one of these is replaced
           wholesale by what a save or a re-fetch hands back — the page never
           patches a value it computed itself. */
        entry: @js($entry),
        perm: @js($permissions),
        withdrawal: @js($withdrawal),

        /* A photo URL whose FILE is missing from disk. Set by the <img>'s
           error handler, so a 404 shows the gendered silhouette rather than a
           broken-image glyph. Not persisted — it is about this render only. */
        brokenPhoto: null,

        /* The clubs this athlete may claim, resolved to slugs server-side. An
           organiser editing their own entry can add any active club to the
           list through the search. */
        clubOptions: @js($myClubs),
        clubQuery: '',
        clubResults: [],
        clubSearching: false,

        belts: @js(collect($belts)->mapWithKeys(fn ($b) => [$b['value'] => $b['label']])->all()),

        /* The country picker owns its own selected state (it is a shared
           Alpine component with a hidden input), so its value lives on the root
           rather than in `draft`, which is reseeded on every sheet open. */
        nationalityPick: @js($entry['nationality'] ?? ''),

        /* The fee, as the server last described it. Re-read on every refresh
           like everything else on this panel, so an approval made at the desk
           lands here without anybody reloading. */
        pay: @js($payment ?? null) || {},
        payBusy: false,

        get payLabel() {
            if (this.pay.state === 'approved')  return @js(__('events.entry_pay_approved'));
            if (this.pay.state === 'submitted') return @js(__('events.entry_pay_submitted'));
            return @js(__('events.entry_pay_owed'));
        },

        /* A photograph of the receipt. Read in the browser, posted as a data
           URI, and validated by its REAL BYTES on the server — the same path
           every other image on the platform takes. Sending it never marks
           anything paid; only an official does that. */
        async sendProof(ev) {
            const file = ev.target.files && ev.target.files[0];
            ev.target.value = '';
            if (!file || this.payBusy) return;

            this.payBusy = true;

            try {
                const proof = await new Promise((resolve, reject) => {
                    const r = new FileReader();
                    r.onload = () => resolve(r.result);
                    r.onerror = reject;
                    r.readAsDataURL(file);
                });

                const res = await fetch(@js(route('events.public.my-entry.payment-proof', ['event' => $event->uuid])), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ proof }),
                });
                const data = await res.json().catch(() => ({}));

                if (!res.ok || !data.success) {
                    window.showToast('error', data.message || @js(__('events.entry_pay_proof_rejected')));
                    return;
                }

                this.pay = data.payment || this.pay;
                window.showToast('success', data.message);
            } catch (e) {
                window.showToast('error', @js(__('events.entry_pay_proof_rejected')));
            } finally {
                this.payBusy = false;
            }
        },

        sheet: '',
        draft: { weight: null, belt_colour: null, belt_grade: '', club: '', name: '', birthdate: '', gender: '', email: '', mobile: '', mobile_code: '', reason: '' },
        saving: false,
        notices: [],
        _n: 0,

        photoSheet: false,
        camera: false,
        cameraFallback: false,
        cameraCanFlip: false,
        cameraFacing: 'user',
        cameraHint: '',
        _stream: null,

        /* ---------- What is still missing ----------

           The door (entry/public/enrol) asks for a name, a number and a
           password and nothing else since 2026-09-05, so almost everything an
           organiser needs arrives here blank. This is the list the prompt at
           the top of the panel asks for, in the order it matters: the
           PHOTOGRAPH first — the draw and the hall screens introduce a
           competitor with their face — then the facts that place them in a
           division.

           Read straight off `entry`, which every save patches in place, so the
           prompt shrinks as they fill it in with no reload. A field the server
           will not let them change is never asked for, and neither is anything
           at all once the window is shut. */
        get missing() {
            if (! this.perm.window?.open) return [];

            const e = this.entry;
            const gone = (f) => f === 'photo'
                ? (! e.photo || e.photo === this.brokenPhoto)
                : false;

            const want = [
                ['photo',     'bi-camera-fill',       @js(__('events.entry_field_photo')),     gone('photo')],
                ['birthdate', 'bi-calendar-event',    @js(__('events.entry_field_birthdate')), ! e.birthdate],
                ['gender',    'bi-gender-ambiguous',  @js(__('events.entry_field_gender')),    ! e.gender],
                ['weight',    'bi-speedometer2',      @js(__('events.entry_field_weight')),    e.weight === null || e.weight === undefined || e.weight === ''],
                ['belt',      'bi-award-fill',        @js(__('events.entry_field_belt')),      ! e.belt_colour],
                ['club',      'bi-building',          @js(__('events.entry_field_club')),      ! e.club],
            ];

            return want
                .filter(([field, , , isMissing]) => isMissing && this.editable(field))
                .map(([field, icon, label]) => ({ field, icon, label }));
        },

        /* One tap from the prompt to the thing it is asking for. The photograph
           has its own sheet (the cropper is Blade + jQuery and there is exactly
           one of it); everything else is the panel's one editing sheet. */
        askFor(field) {
            if (field === 'photo') { this.photoSheet = true; return; }

            this.openSheet(field);
        },

        /* ---------- What each row says ---------- */

        get rows() {
            return [
                { field: 'name',      icon: 'bi-person-vcard',     label: @js(__('events.entry_field_name')) },
                { field: 'belt',      icon: 'bi-award-fill',       label: @js(__('events.entry_field_belt')) },
                { field: 'club',      icon: 'bi-building',         label: @js(__('events.entry_field_club')) },
                { field: 'birthdate', icon: 'bi-calendar-event',   label: @js(__('events.entry_field_birthdate')) },
                { field: 'gender',    icon: 'bi-gender-ambiguous', label: @js(__('events.entry_field_gender')) },
                { field: 'nationality', icon: 'bi-globe2',         label: @js(__('events.entry_field_nationality')) },
            ];
        },

        /* The account rows, kept apart from the entry rows above so the two
           cards stay two answers to two different questions: what am I
           competing as, and how do I get back in. */
        get contactRows() {
            return [
                { field: 'email',  icon: 'bi-envelope-fill',  label: @js(__('events.entry_contact_email_label')) },
                { field: 'mobile', icon: 'bi-telephone-fill', label: @js(__('events.entry_contact_mobile_label')) },
            ];
        },

        /* A row keyed 'belt' asks the server about `belt_colour`; everything
           else is named the same on both sides. */
        realField(f) { return f === 'belt' ? 'belt_colour' : f; },

        editable(f) { return !!(this.perm.fields?.[this.realField(f)]?.editable); },
        reason(f)   { return this.perm.fields?.[this.realField(f)]?.reason || this.perm.window?.reason || ''; },

        value(f) {
            if (f === 'belt') {
                const c = this.entry.belt_colour ? (this.belts[this.entry.belt_colour] || this.entry.belt_colour) : '';
                const g = this.entry.belt_grade || '';
                return [c, g].filter(Boolean).join(' · ') || '—';
            }
            if (f === 'club')      return this.entry.club ? this.entry.club.name : @js(__('events.entry_no_club'));
            if (f === 'birthdate') return this.entry.birthdate || '—';
            if (f === 'gender')    return this.entry.gender || '—';
            if (f === 'name')      return this.entry.name || '—';
            if (f === 'nationality') return this.countryName(this.entry.nationality) || '—';
            if (f === 'email')     return this.entry.email || '—';
            /* The joined string, because this row only has to READ as a
               number; the sheet edits the two halves the column stores. */
            if (f === 'mobile')    return this.entry.mobile_display || '—';
            return '—';
        },

        /* An ISO-2 becomes a country name, from the same list the picker
           reads. Loaded once, lazily; until it lands the code itself is shown,
           which is honest rather than blank. */
        _countries: null,

        countryName(code) {
            if (!code) return '';
            const c = String(code).toUpperCase();

            return (this._countries && this._countries[c]) || c;
        },

        async loadCountries() {
            if (this._countries) return;

            try {
                const res = await fetch('/data/countries.json', { credentials: 'same-origin' });
                const rows = await res.json();
                const map = {};
                (Array.isArray(rows) ? rows : []).forEach(r => { if (r && r.iso2) map[String(r.iso2).toUpperCase()] = r.name; });
                this._countries = map;
            } catch (e) { this._countries = {}; }
        },

        /* ---------- The one sheet ---------- */

        get sheetTitle() {
            return {
                weight:    @js(__('events.entry_field_weight')),
                belt:      @js(__('events.entry_field_belt')),
                club:      @js(__('events.entry_field_club')),
                birthdate: @js(__('events.entry_field_birthdate')),
                gender:    @js(__('events.entry_field_gender')),
                name:      @js(__('events.entry_field_name')),
                nationality: @js(__('events.entry_field_nationality')),
                email:     @js(__('events.entry_contact_email_label')),
                mobile:    @js(__('events.entry_contact_mobile_label')),
                withdraw:  @js(__('events.withdraw_title')),
            }[this.sheet] || '';
        },

        /* The sub-line says WHOSE entry this is, not what the page is for. It
           used to fall back to the page subtitle, so every sheet repeated
           "Everything you compete as…" under a heading like "Belt" — true of
           the page, meaningless on the sheet. */
        get sheetHint() {
            return this.sheet === 'withdraw'
                ? @js(__('events.withdraw_explain'))
                : (this.entry.name || '');
        },

        get sheetIcon() {
            return {
                weight: 'bi-speedometer2', belt: 'bi-award-fill', club: 'bi-building',
                birthdate: 'bi-calendar-event', gender: 'bi-gender-ambiguous',
                name: 'bi-person-vcard', nationality: 'bi-globe2',
                email: 'bi-envelope-fill', mobile: 'bi-telephone-fill',
                withdraw: 'bi-box-arrow-left',
            }[this.sheet] || 'bi-pencil-fill';
        },

        openSheet(which) {
            // Seeded from the server's copy every time, so a sheet closed
            // without saving leaves nothing behind.
            this.draft = {
                weight: this.entry.weight,
                belt_colour: this.entry.belt_colour || null,
                belt_grade: this.entry.belt_grade || '',
                club: this.entry.club ? this.entry.club.slug : '',
                name: this.entry.name || '',
                birthdate: this.entry.birthdate || '',
                gender: this.entry.gender || '',
                email: this.entry.email || '',
                mobile: this.entry.mobile || '',
                mobile_code: this.entry.mobile_code || '',
                reason: '',
            };
            this.clubQuery = '';
            this.clubResults = [];
            this.sheet = which;
        },

        closeSheet() { this.sheet = ''; },

        bump(by) {
            const v = (this.draft.weight ?? 60) + by;
            this.draft.weight = Math.min(140, Math.max(20, Math.round(v * 2) / 2));
        },

        /* Only the field this sheet is about — absent is not blank, and a sheet
           that saves one thing must never wipe the other six. */
        commit() {
            if (this.sheet === 'withdraw') return this.withdraw();
            if (this.sheet === 'weight')   return this.save({ weight: this.draft.weight });
            if (this.sheet === 'belt')     return this.save({ belt_colour: this.draft.belt_colour, belt_grade: this.draft.belt_grade });
            if (this.sheet === 'club')     return this.save({ club: this.draft.club || null });
            if (this.sheet === 'birthdate') return this.save({ birthdate: this.draft.birthdate || null });
            if (this.sheet === 'gender')   return this.save({ gender: this.draft.gender || null });
            if (this.sheet === 'nationality') return this.save({ nationality: this.nationalityPick || null });

            /* Both contact fields go up together whenever either changes. The
               server refuses a change that would leave the account with no way
               to sign in, and it can only judge that if it can see both — an
               absent key means "leave that one alone", which is exactly the
               wrong reading for this one check. */
            if (this.sheet === 'email' || this.sheet === 'mobile') {
                return this.save({
                    email: (this.draft.email || '').trim() || null,
                    mobile: (this.draft.mobile || '').trim() || null,
                    mobile_code: (this.draft.mobile_code || '').trim() || null,
                });
            }

            if (this.sheet === 'name') {
                /* Collapsed the same way the server collapses it, and refused
                   the same way: a blank name is never written, so asking for it
                   and then quietly keeping the old one would be a lie. */
                const v = (this.draft.name || '').trim().replace(/\s+/g, ' ');

                if (v.length < 2) { window.showToast('error', @js(__('events.entry_name_min'))); return; }

                return this.save({ name: v });
            }
        },

        /* ---------- Writing ---------- */

        async save(fields) {
            if (this.saving) return;
            this.saving = true;

            try {
                const res = await fetch(@js(route('events.public.my-entry.update', ['event' => $event->uuid])), {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(fields),
                });
                const d = await res.json().catch(() => ({}));

                if (!res.ok || !d.success) throw new Error(d.message || @js(__('events.entry_edit_not_yours')));

                if (d.entry) this.entry = d.entry;
                this.sheet = '';

                /* A re-weigh and a division move are the two outcomes that must
                   not vanish with a toast: the first sends them back to the
                   desk, the second changes who they fight. Both stay on the
                   page until dismissed. */
                if (d.reweigh) this.push('warn', d.message);
                if (d.division_changed) this.push('brand', @js(__('events.entry_edit_division_moved')));
                if (!d.reweigh) window.showToast('success', d.message);
            } catch (e) {
                window.showToast('error', e.message);
            } finally {
                this.saving = false;
            }
        },

        async withdraw() {
            if (this.saving) return;
            this.saving = true;

            try {
                const res = await fetch(@js(route('events.public.my-entry.withdraw', ['event' => $event->uuid])), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ reason: this.draft.reason || null }),
                });
                const d = await res.json().catch(() => ({}));

                if (!res.ok || !d.success) throw new Error(d.message || @js(__('events.withdraw_not_entered')));

                this.withdrawal = d.withdrawal;
                this.sheet = '';
                window.showToast('success', d.message);
            } catch (e) {
                window.showToast('error', e.message);
            } finally {
                this.saving = false;
            }
        },

        async takeBack() {
            if (this.saving) return;
            this.saving = true;

            try {
                const res = await fetch(@js(route('events.public.my-entry.withdraw.cancel', ['event' => $event->uuid])), {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                });
                const d = await res.json().catch(() => ({}));

                if (!res.ok || !d.success) throw new Error(d.message || @js(__('events.withdraw_nothing_to_cancel')));

                this.withdrawal = null;
                window.showToast('success', d.message);
            } catch (e) {
                window.showToast('error', e.message);
            } finally {
                this.saving = false;
            }
        },

        /* ---------- Reading it back ---------- */

        /* The whole panel, re-fetched. Silent on purpose: this runs because
           somebody ELSE changed something, and a toast for every organiser
           keystroke would be noise. */
        async refresh() {
            try {
                const res = await fetch(@js(route('events.public.my-entry.state', ['event' => $event->uuid])), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;

                const d = await res.json().catch(() => ({}));
                if (!d.success) return;

                this.entry = d.entry;
                this.perm = d.permissions;
                this.withdrawal = d.withdrawal;
                // An approval made at the desk lands here without a reload.
                if (d.payment) this.pay = d.payment;
            } catch (e) { /* the DB is the truth; a dropped poll changes nothing */ }
        },

        push(tone, text) {
            if (!text) return;
            this.notices.push({ id: ++this._n, tone, text });
        },

        dismiss(id) { this.notices = this.notices.filter(n => n.id !== id); },

        /* ---------- The club search (organiser only) ---------- */

        async searchClubs() {
            const q = this.clubQuery.trim();
            if (q.length < 2) { this.clubResults = []; return; }

            this.clubSearching = true;
            try {
                const url = @js(route('events.public.enter.clubs', ['event' => $event->uuid])) + '?q=' + encodeURIComponent(q);
                const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                const d = await res.json().catch(() => ({}));
                this.clubResults = Array.isArray(d.clubs) ? d.clubs : (Array.isArray(d) ? d : []);
            } catch (e) {
                this.clubResults = [];
            } finally {
                this.clubSearching = false;
            }
        },

        /* A searched club joins the selection cards and is chosen, so the
           chosen state is always read from ONE list. */
        adopt(c) {
            if (!this.clubOptions.some(o => o.slug === c.slug)) {
                this.clubOptions = this.clubOptions.concat([{ slug: c.slug, name: c.name, logo: c.logo || '', country: c.country || '' }]);
            }
            this.draft.club = c.slug;
            this.clubQuery = '';
            this.clubResults = [];
        },

        /* ---------- The photograph ---------- */

        /* Re-frame the picture already on file, without asking for another one.
           It is served same-origin through /file/{path}, so it can be read back
           as bytes and handed to the SAME cropper a freshly-picked file goes
           to — which is what keeps the full-resolution crop (and every other
           detail of One Cropper Everywhere) in exactly one place.

           A file missing from disk has nothing to re-crop: it is remembered as
           broken, which is what hides the action. */
        async recrop() {
            const src = this.entry.photo;
            if (!src || src === this.brokenPhoto || !this.editable('photo')) return;

            try {
                const res = await fetch(src, { credentials: 'same-origin' });
                if (!res.ok) throw new Error();

                const blob = await res.blob();
                if (!blob || !String(blob.type).startsWith('image/')) throw new Error();

                this.photoSheet = false;
                this.toCropper(new File([blob], 'photo', { type: blob.type }));
            } catch (e) {
                this.brokenPhoto = src;
                window.showToast('error', @js(__('events.entry_photo_recrop_failed')));
            }
        },

        handOff(ev) {
            const file = ev.target.files && ev.target.files[0];
            if (!file) return;

            // Let the same door be used twice in a row: without this, picking
            // the identical file again fires no change event at all.
            ev.target.value = '';
            this.photoSheet = false;
            this.toCropper(file);
        },

        /* Hand a file to the ONE cropper. Its own input is what it reads from,
           so the file is moved across with a DataTransfer and a `change`, which
           opens its crop sheet on the image. That is what lets this page offer
           three doors — camera, gallery, native picker — while the project
           still has exactly one cropper. */
        toCropper(file) {
            const target = document.getElementById('input_myEntryPhoto');
            if (!target) return;

            const dt = new DataTransfer();
            dt.items.add(file);
            target.files = dt.files;
            target.dispatchEvent(new Event('change', { bubbles: true }));
        },

        /* Detect first, request properly, fall back honestly (CLAUDE.md →
           Mobile Device Capabilities). No mediaDevices at all (an old WebView,
           a page not on https) and a refused permission land in the same
           place: the native picker replaces this tile and says why. */
        async openCamera() {
            this.cameraHint = @js(__('events.public_enrol_photo_guide'));

            if (!navigator.mediaDevices?.getUserMedia) {
                this.cameraFallback = true;
                window.showToast('error', @js(__('events.public_enrol_photo_no_camera')));

                return;
            }

            try {
                this._stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: this.cameraFacing, width: { ideal: 1080 }, height: { ideal: 1440 } },
                    audio: false,
                });
            } catch (e) {
                this.cameraFallback = true;
                window.showToast('error', @js(__('events.public_enrol_photo_denied')));

                return;
            }

            this.photoSheet = false;
            this.camera = true;

            await this.$nextTick();
            const v = this.$refs.video;
            if (v) { v.srcObject = this._stream; try { await v.play(); } catch (e) {} }

            try {
                const cams = await navigator.mediaDevices.enumerateDevices();
                this.cameraCanFlip = cams.filter(d => d.kind === 'videoinput').length > 1;
            } catch (e) {
                this.cameraCanFlip = false;
            }
        },

        async flipCamera() {
            this.cameraFacing = this.cameraFacing === 'user' ? 'environment' : 'user';
            this.stopStream();
            this.camera = false;
            await this.openCamera();
        },

        /* Take the frame in the guide's own 3:4 — cropping the stream to what
           the person was actually looking at rather than handing over a 16:9
           they never saw. Mirrored only where the preview was. */
        shoot() {
            const v = this.$refs.video;
            if (!v || !v.videoWidth) { window.showToast('error', @js(__('events.public_enrol_photo_wait'))); return; }

            const srcW = v.videoWidth, srcH = v.videoHeight;
            const want = 3 / 4;
            let sw = srcW, sh = Math.round(srcW / want);
            if (sh > srcH) { sh = srcH; sw = Math.round(srcH * want); }
            const sx = Math.round((srcW - sw) / 2);
            const sy = Math.round((srcH - sh) / 2);

            const canvas = document.createElement('canvas');
            canvas.width = 900;
            canvas.height = 1200;
            const ctx = canvas.getContext('2d');

            if (this.cameraFacing === 'user') {
                ctx.translate(canvas.width, 0);
                ctx.scale(-1, 1);
            }

            ctx.drawImage(v, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);

            canvas.toBlob((blob) => {
                if (!blob) { window.showToast('error', @js(__('events.public_enrol_photo_failed'))); return; }

                const file = new File([blob], 'photo.jpg', { type: 'image/jpeg' });
                this.closeCamera();
                this.toCropper(file);
            }, 'image/jpeg', 0.92);
        },

        closeCamera() { this.stopStream(); this.camera = false; },

        /* Never leave the camera light on. */
        stopStream() {
            if (!this._stream) return;
            this._stream.getTracks().forEach(t => { try { t.stop(); } catch (e) {} });
            this._stream = null;
        },

        /* ---------- Wiring ---------- */

        init() {
            // So the nationality row says "Bahrain" rather than "BH".
            this.loadCountries();

            /* The cropper announces its result rather than firing an input
               event, because it writes the hidden field with `.value =`. This
               is the only place this page learns a new photograph exists — and
               it saves straight away: a crop sheet that closes and changes
               nothing on the page reads as a failure. */
            document.addEventListener('cropperCropped', (ev) => {
                if (ev.detail && ev.detail.id === 'myEntryPhoto' && ev.detail.base64) {
                    this.save({ photo: ev.detail.base64 });
                }
            });

            /* LIVE. An organiser correcting this entry from the desk must reach
               an athlete standing in the hall with the panel already open. The
               payload is a signal, not the data: what each recipient may see
               differs, so the panel re-fetches its own state rather than
               trusting a broadcast body (CLAUDE.md → Realtime, refresh shape).

               Dedup: the handler is parked on window so a second mount removes
               the first, rather than stacking listeners. */
            if (window.__myEntryRealtime) {
                window.removeEventListener('realtime:events', window.__myEntryRealtime);
            }

            window.__myEntryRealtime = (ev) => {
                const d = ev.detail || {};
                if (d.event !== @js($event->uuid)) return;
                if (d.action !== 'entry-updated' && d.action !== 'withdrawal') return;
                this.refresh();
            };

            window.addEventListener('realtime:events', window.__myEntryRealtime);

            // A camera left running is a light on somebody's phone and a
            // permission they never agreed to keep granting.
            window.addEventListener('pagehide', () => this.stopStream());
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') this.stopStream();
            });
        },
    };
}
</script>
@endpush
