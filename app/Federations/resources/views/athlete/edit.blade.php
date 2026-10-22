{{--
    The federation dashboard's athlete editor (Participants tab), built from the
    platform's own form controls: the gender toggle, the date-of-birth picker,
    the country picker, the styled select and the one shared cropper.

    The dashboard is a prototype page (not Blade), so this lives in a same-origin
    frame, like the Draw board (federations::draw.board), and the two talk by
    postMessage:

      frame → dashboard   {type: 'fed-athlete:close'}
                          {type: 'fed-athlete:saved', message, regs}

    The frame holds no athlete data in its source. It reads and saves through
    federations.api.regs.athlete, where FederationAthleteEditor scopes the entry
    to the viewer's federation and lets only a manager write.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('federations::partials.frame-head', ['title' => __('Edit athlete')])
    <script>
    /* The editor's state. Registered before Alpine starts (Alpine is deferred). */
    window.fedAthleteEdit = function (cfg) {
        return {
            cfg, loading: true, failed: '', busy: false, msg: '', confirmDel: false, wdReason: '', wdTyped: '', canEdit: !! cfg.canEdit,
            a: {}, clubs: [], photo: '', photoUrl: '',
            f: { name: '', gender: '', ageMode: 'dob', dob: '', age: '', nat: '', natId: '', club: '', weight: '' },

            get clubOptions() {
                return this.clubs.map(c => ({ value: c.value, label: c.label + (c.pending ? ' (pending)' : ''), logo: c.logo || null }));
            },
            get initials() {
                return (this.f.name || '').trim().split(/\s+/).slice(0, 2).map(w => w[0] || '').join('').toUpperCase() || '?';
            },
            get clubName() { const c = this.clubs.find(x => x.value === this.f.club); return c ? c.label : ''; },

            post(type, extra) {
                try { if (window.parent !== window) window.parent.postMessage(Object.assign({ type }, extra || {}), window.location.origin); } catch (e) {}
            },
            close() { this.post('fed-athlete:close'); },

            async init() {
                const key = '__aeCrop';
                if (window[key]) document.removeEventListener('cropperCropped', window[key]);
                window[key] = (e) => { const d = e.detail || {}; if (d.id === 'aePhoto' && d.base64) { this.photo = d.base64; this.photoUrl = d.base64; } };
                document.addEventListener('cropperCropped', window[key]);
                // Escape closes the window — but first whatever is open inside
                // it: the crop editor, or a list (which closes itself on Escape;
                // here we only step focus out of the form).
                document.addEventListener('keydown', (e) => {
                    if (e.key !== 'Escape') return;
                    const ed = document.getElementById('editor_aePhoto');
                    if (ed && ed.style.display === 'flex') return;
                    const at = document.activeElement;
                    if (at && at !== document.body && at.closest('main')) { at.blur(); return; }
                    this.close();
                });

                try {
                    const u = cfg.urls.show + '?entry=' + encodeURIComponent(cfg.entry) + '&athlete=' + encodeURIComponent(cfg.athlete);
                    const res = await fetch(u, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                    if (! res.ok) { this.failed = 'That athlete could not be opened.'; this.loading = false; return; }
                    const j = await res.json();
                    this.fill(j.athlete || {});
                    this.canEdit = !! j.canEdit;
                } catch (e) {
                    this.failed = 'Could not reach the server. Try again.';
                }
                this.loading = false;
            },

            fill(a) {
                this.a = a;
                this.clubs = a.clubs || this.clubs;
                this.photo = ''; this.photoUrl = a.photo || '';
                this.f = {
                    name: a.name || '', gender: a.gender || '', ageMode: a.ageMode === 'age' ? 'age' : 'dob',
                    dob: a.dob || '', age: a.age != null ? String(a.age) : '',
                    nat: a.natIso2 || '', natId: a.natId || '', club: a.club || '',
                    weight: a.weight != null ? String(a.weight) : '',
                };
            },

            bumpAge(d) {
                const n = parseInt(this.f.age, 10);
                this.f.age = String(Math.min(99, Math.max(3, (isNaN(n) ? 18 : n) + d)));
            },

            async save() {
                if (this.busy || ! this.canEdit) return;
                const f = this.f;
                this.msg = '';
                if (f.name.trim().length < 2) { this.msg = 'Enter the athlete’s full name.'; return; }
                const w = f.weight.trim();
                if (w !== '' && ! (Number(w) >= 10 && Number(w) <= 250)) { this.msg = 'Weight must be between 10 and 250 kg.'; return; }
                const data = {
                    entry: cfg.entry, athlete: cfg.athlete,
                    name: f.name.trim(), gender: f.gender || null, ageMode: f.ageMode,
                    nat: f.nat || null, natId: f.natId.trim(), weight: w === '' ? null : w,
                };
                if (f.ageMode === 'age') {
                    const n = parseInt(f.age, 10);
                    if (isNaN(n) || n < 3 || n > 99) { this.msg = 'Enter the athlete’s age, from 3 to 99.'; return; }
                    data.age = n;
                } else {
                    data.dob = f.dob || null;
                }
                if (f.club) data.club = f.club;
                if (this.photo) data.photo = this.photo;

                this.busy = true;
                try {
                    const res = await fetch(cfg.urls.update, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify(data),
                    });
                    const j = await res.json().catch(() => ({}));
                    if (res.status === 401 || res.status === 419) { window.top.location.reload(); return; }
                    if (! res.ok || ! j.success) {
                        let m = j.message || 'That did not save. Try again.';
                        if (j.errors) { const k = Object.keys(j.errors)[0]; if (k) m = j.errors[k][0]; }
                        this.msg = m; this.busy = false; return;
                    }
                    this.post('fed-athlete:saved', { message: j.message || 'Athlete updated.', regs: j.regs || null });
                } catch (e) {
                    this.msg = 'Could not reach the server. Try again.';
                }
                this.busy = false;
            },

            /* The photo opens the cropper on the picture already there, to adjust
               it — or to swap it ("New photo" in the sheet). No picture yet: the
               file picker, straight away. */
            /* Take the athlete off the event — two taps. The club's entry keeps
               them as Withdrawn (its fee follows the federation's own rules) and
               the platform entry, its division place and draw slot go with it. */
            async remove() {
                if (this.busy || ! this.canEdit) return;
                if (! this.confirmDel) { this.confirmDel = true; this.wdReason = ''; this.wdTyped = ''; return; }
                // A reason and the word DELETE, as the server demands — it records who removed them and why.
                if (this.wdReason.trim().length < 5) { this.msg = @js(__('Say why they are being removed (at least 5 characters).')); return; }
                if (this.wdTyped.trim() !== 'DELETE') { this.msg = @js(__('Type DELETE to confirm.')); return; }
                this.busy = true; this.msg = '';
                try {
                    const res = await fetch(cfg.urls.withdraw, {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ athletes: [{ entry: cfg.entry, athlete: cfg.athlete }], reason: this.wdReason.trim(), confirm: 'DELETE' }),
                    });
                    if (res.status === 401 || res.status === 419) { window.top.location.reload(); return; }
                    const j = await res.json().catch(() => ({}));
                    if (! res.ok || ! j.success) { let m = j.message || 'They could not be removed. Try again.'; if (j.errors) { const k = Object.keys(j.errors)[0]; if (k) m = j.errors[k][0]; } this.msg = m; this.busy = false; return; }
                    this.post('fed-athlete:saved', { message: (this.f.name || 'The athlete') + ' was removed from the event.', regs: j.regs || null });
                } catch (e) {
                    this.msg = 'Could not reach the server. Try again.'; this.busy = false; this.confirmDel = false;
                }
            },

            pickPhoto() {
                if (! this.canEdit) return;
                if (this.photoUrl) {
                    document.dispatchEvent(new CustomEvent('cropper:open', { detail: { id: 'aePhoto', src: this.photoUrl } }));
                    return;
                }
                if (this.$refs.file) this.$refs.file.click();
            },
            picked(ev) {
                const file = ev.target.files && ev.target.files[0];
                ev.target.value = '';
                const input = document.getElementById('input_aePhoto');
                if (! file || ! input) return;
                const dt = new DataTransfer(); dt.items.add(file);
                input.files = dt.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            },
        };
    };
    </script>
    <script defer src="{{ asset('vendor/alpinejs/3.17.4/alpine.min.js') }}"></script>
</head>
<body class="antialiased">
<div x-data="fedAthleteEdit(@js(['entry' => $entry, 'athlete' => $athlete, 'canEdit' => $canEdit, 'urls' => $urls]))"
     class="ae-shell flex flex-col">

    {{-- Header band (Design Rule #8): the athlete is the headline. --}}
    <header class="flex-shrink-0 z-40 px-5 pt-4 pb-4 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, #1e2c4f, #1e2c4fb0), #fff;">
        <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="relative flex items-start gap-3">
            <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                <i class="bi bi-person-badge-fill text-xl" style="color:#edcd85"></i>
            </span>
            <div class="min-w-0 flex-1">
                <h1 class="text-lg font-black leading-tight" style="overflow-wrap:anywhere"
                    x-text="f.name || @js(__('Athlete'))">{{ __('Athlete') }}</h1>
                <p class="text-[12px] text-white/85 mt-0.5">
                    <span x-text="a.takeoneId || @js($athlete)"></span><template x-if="clubName"><span> &middot; <span x-text="clubName"></span></span></template>
                </p>
            </div>
            <button type="button" @click="close()" aria-label="{{ __('Close') }}"
                    class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform hover:bg-white/25">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="relative mt-3 flex flex-wrap gap-1.5" x-show="! loading && ! failed" x-cloak>
            <template x-if="a.registered">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold"><i class="bi bi-check-circle-fill" style="color:#86efac"></i> {{ __('Registered in the event') }}</span>
            </template>
            <template x-if="a.platform && ! a.registered">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold" style="background:#fbbf2433;color:#fde68a"><i class="bi bi-exclamation-triangle-fill"></i> {{ __('Not on the event’s entry list yet') }}</span>
            </template>
            <template x-for="d in (a.divisions || [])" :key="d">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold"><i class="bi bi-diagram-3 bracket-icon"></i> <span x-text="d"></span></span>
            </template>
            <template x-if="a.registered && ! (a.divisions || []).length">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold"><i class="bi bi-dash-circle"></i> {{ __('Not in a division yet') }}</span>
            </template>
        </div>
    </header>

    <main class="ae-scroll flex-1 min-h-0 px-5 py-4 space-y-4">
        {{-- Loading --}}
        <template x-if="loading">
            <div class="space-y-3" aria-busy="true">
                <div class="flex gap-4"><div class="ae-skel" style="width:108px;height:144px;border-radius:16px"></div>
                    <div class="flex-1 space-y-3"><div class="ae-skel h-11"></div><div class="ae-skel h-11"></div></div></div>
                <div class="ae-skel h-24"></div><div class="ae-skel h-24"></div>
            </div>
        </template>

        <template x-if="failed">
            <div role="alert" class="rounded-2xl px-4 py-3 text-sm font-semibold" style="background:#fdecea;color:#8f2f25" x-text="failed"></div>
        </template>

        <fieldset x-show="! loading && ! failed" x-cloak :disabled="! canEdit" class="space-y-4">
            <template x-if="! canEdit">
                <div class="rounded-2xl px-4 py-3 text-[13px]" style="background:#f4f6fa;color:#5d6880">{{ __('Only a federation manager can change an athlete.') }}</div>
            </template>
            <template x-if="canEdit && a.claimed">
                <div class="rounded-2xl px-4 py-3 text-[13px] leading-relaxed flex gap-2" style="background:#fdf6e6;color:#7a5d17">
                    <i class="bi bi-info-circle-fill mt-0.5"></i>
                    <span>{{ __('This athlete has their own TakeOne account. What you change here changes their profile.') }}</span>
                </div>
            </template>

            {{-- Who --}}
            <section class="ae-sec">
                <h2 class="ae-h"><i class="bi bi-person-fill"></i>{{ __('Athlete') }}</h2>
                <div class="flex gap-4 items-start flex-wrap">
                    <div class="flex flex-col items-center gap-1.5">
                        <button type="button" class="ae-photo" @click="pickPhoto()" aria-label="{{ __('Change photo') }}">
                            <template x-if="photoUrl"><img :src="photoUrl" alt="" x-on:error="photoUrl = ''"></template>
                            <template x-if="! photoUrl && f.gender === 'Female'">
                                <x-gender-avatar gender="female" sizes="108px" class="w-full h-full" />
                            </template>
                            <template x-if="! photoUrl && f.gender !== 'Female'">
                                <x-gender-avatar gender="male" sizes="108px" class="w-full h-full" />
                            </template>
                            <span class="ae-cam" x-show="canEdit"><i class="bi bi-camera-fill"></i></span>
                        </button>
                        <template x-if="photo">
                            <button type="button" class="text-[11px] font-semibold text-gray-500 hover:text-red-600" @click="photo = ''; photoUrl = a.photo || ''">{{ __('Undo new photo') }}</button>
                        </template>
                    </div>
                    <div class="flex-1 space-y-3" style="min-width:220px">
                        <div>
                            <label class="ae-lbl" for="aeName">{{ __('Full name') }} <span class="text-red-500">*</span></label>
                            <input id="aeName" type="text" class="ae-in" x-model="f.name" maxlength="150" autocomplete="off" spellcheck="false">
                        </div>
                        <div>
                            <span class="ae-lbl">{{ __('Gender') }}</span>
                            <x-fields.gender-toggle model="f.gender" />
                        </div>
                    </div>
                </div>
            </section>

            {{-- Age: date of birth OR a stated age, never both --}}
            <section class="ae-sec">
                <div class="flex items-center justify-between gap-2 flex-wrap" style="margin-bottom:12px">
                    <h2 class="ae-h" style="margin:0"><i class="bi bi-calendar-heart"></i>{{ __('Age') }}</h2>
                    <div class="ae-seg" role="group" aria-label="{{ __('How the age is given') }}">
                        <button type="button" :class="f.ageMode === 'dob' && 'is-on'" :aria-pressed="f.ageMode === 'dob'" @click="f.ageMode = 'dob'">{{ __('Date of birth') }}</button>
                        <button type="button" :class="f.ageMode === 'age' && 'is-on'" :aria-pressed="f.ageMode === 'age'" @click="f.ageMode = 'age'; if (! f.age && a.ageOnDay != null) f.age = String(a.ageOnDay)">{{ __('Age') }}</button>
                    </div>
                </div>
                <div x-show="f.ageMode === 'dob'">
                    <x-fields.date-of-birth model="f.dob" :inline="true" id="aeDob" name="ae_dob" label="" :min-age="3" :max-age="99" />
                    <p class="text-[12px] text-gray-500 mt-1.5">{{ __('Leave it empty if it is not known — a guessed date puts an athlete in the wrong age group.') }}</p>
                </div>
                <div x-show="f.ageMode === 'age'" x-cloak>
                    <div class="flex items-center gap-2">
                        <button type="button" class="ae-step" @click="bumpAge(-1)" aria-label="{{ __('One year younger') }}"><i class="bi bi-dash-lg"></i></button>
                        <input type="text" class="ae-in text-center font-bold" style="max-width:96px" x-model="f.age" inputmode="numeric" maxlength="2"
                               aria-label="{{ __('Age in years') }}" @input="f.age = f.age.replace(/\D/g, '')">
                        <button type="button" class="ae-step" @click="bumpAge(1)" aria-label="{{ __('One year older') }}"><i class="bi bi-plus-lg"></i></button>
                        <span class="text-sm text-gray-500">{{ __('years on the event day') }}</span>
                    </div>
                    <p class="text-[12px] text-gray-500 mt-1.5">{{ __('Used when the date of birth is not known. Saving an age clears the date of birth.') }}</p>
                </div>
            </section>

            {{-- Identity --}}
            <section class="ae-sec">
                <h2 class="ae-h"><i class="bi bi-person-vcard-fill"></i>{{ __('Identity') }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <span class="ae-lbl">{{ __('Nationality') }}</span>
                        <x-fields.country model="f.nat" :inline="true" id="aeNat" name="ae_nat" label="" wrapper-class="" />
                    </div>
                    <div>
                        <label class="ae-lbl" for="aeNatId">{{ __('CPR / National ID') }}</label>
                        <input id="aeNatId" type="text" class="ae-in" x-model="f.natId" maxlength="40" autocomplete="off" inputmode="numeric">
                    </div>
                </div>
            </section>

            {{-- Competing --}}
            <section class="ae-sec">
                <h2 class="ae-h"><i class="bi bi-shield-fill"></i>{{ __('Competing') }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <span class="ae-lbl">{{ __('Club') }}</span>
                        <x-fields.select model="f.club" options-expr="clubOptions" placeholder="{{ __('Choose a club') }}"
                            :searchable="true" :logos="true" search-placeholder="{{ __('Search clubs…') }}" panel-class="bottom-full mb-1" />
                        <p class="text-[12px] text-gray-500 mt-1.5" x-show="a.club && f.club !== a.club" x-cloak>{{ __('The athlete moves to this club’s entry, and their fee with them.') }}</p>
                    </div>
                    <div>
                        <label class="ae-lbl" for="aeWeight">{{ __('Weight') }}</label>
                        <div class="relative">
                            <input id="aeWeight" type="text" class="ae-in" style="padding-right:2.6rem" x-model="f.weight" inputmode="decimal" maxlength="6" autocomplete="off">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400 pointer-events-none">kg</span>
                        </div>
                        <p class="text-[12px] text-gray-500 mt-1.5">{{ __('Stops following this form once the athlete is weighed in.') }}</p>
                    </div>
                </div>
            </section>
        </fieldset>
    </main>

    {{-- Footer: the one action --}}
    <footer class="flex-shrink-0 z-40 px-5 py-3 bg-white border-t border-gray-100 flex items-center gap-3 flex-wrap"
            style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));" x-show="! loading && ! failed && canEdit" x-cloak>
        <div class="w-full space-y-2" x-show="confirmDel" x-cloak>
            <label class="ae-lbl" for="aeWdReason">{{ __('Why are they being removed?') }} <span style="color:#b4483c">*</span></label>
            <textarea id="aeWdReason" class="ae-in" rows="2" maxlength="500" x-model="wdReason" style="height:auto;padding-top:.6rem;padding-bottom:.6rem"></textarea>
            <label class="ae-lbl" for="aeWdTyped">{!! __('Type :word to confirm', ['word' => '<b style="color:#b4483c;letter-spacing:.06em">DELETE</b>']) !!}</label>
            <div class="flex gap-2">
                <input id="aeWdTyped" type="text" class="ae-in font-bold" style="letter-spacing:.08em" x-model="wdTyped" placeholder="DELETE" autocomplete="off" spellcheck="false">
                <button type="button" class="ae-ghost" @click="confirmDel = false; msg = ''">{{ __('Keep them') }}</button>
            </div>
        </div>
        <button type="button" class="ae-danger" :class="confirmDel && 'is-armed'" @click="remove()" :disabled="busy || (confirmDel && (wdReason.trim().length < 5 || wdTyped.trim() !== 'DELETE'))"
                x-text="confirmDel ? @js(__('Withdraw from event')) : @js(__('Remove from event'))"></button>
        <p class="flex-1 text-[13px] font-semibold" style="color:#b4483c;min-width:120px" role="alert" x-text="msg"></p>
        <button type="button" class="ae-ghost" @click="close()">{{ __('Cancel') }}</button>
        <button type="button" class="ae-save" :disabled="busy" @click="save()"
                x-text="busy ? @js(__('Saving…')) : @js(__('Save'))">{{ __('Save') }}</button>
    </footer>

    {{-- The project's one cropper, never uploading on its own: the crop comes
         back as a data URL (cropperCropped) and rides the save, where the
         server checks the real bytes. Portrait 3:4, stored at 600×800. --}}
    <input type="file" x-ref="file" accept="image/*" class="hidden" style="display:none" @change="picked($event)">
    <div class="hidden" style="display:none">
        <x-takeone-cropper id="aePhoto" mode="form" :inline="true" :replaceable="true"
            :width="600" :height="800" shape="rectangle" :canvasHeight="400"
            folder="temp" filename="athlete" inputName="ae_photo"
            sheetMaxWidth="min(600px, calc(100vw - 32px))" sheetClass="ae-crop rounded-2xl shadow-2xl bg-white" editorAlign="items-center"
            :showControls="true" :showCancel="true" saveText="{{ __('Use photo') }}" />
    </div>
</div>

@include('partials.toast-host')
@stack('modals')
@stack('scripts')
</body>
</html>
