{{-- Mobile "Add / Edit Package" — full-height bottom sheet. Opens on `open-add-package`
     or `open-edit-package` (detail.id). A self-contained Alpine reimplementation of the
     desktop wizard that posts to the SAME store/update endpoints & field names. --}}
@php
    $weekDays = [];
    foreach (['saturday','sunday','monday','tuesday','wednesday','thursday','friday'] as $d) {
        $weekDays[] = ['value' => $d, 'name' => \Illuminate\Support\Carbon::parse($d)->locale(app()->getLocale())->isoFormat('ddd')];
    }
@endphp
<script>
window.packageFormSheet = function () {
    return {
        open: false,
        mode: 'add',                 // 'add' | 'edit'
        editId: null,
        tab: 'basic',                // basic | schedules | trainers
        lang: 'en',                  // en | ar (translatable field toggle)

        // ── basic fields ──
        name: '', description: '', price: '', registration_fee: '', duration_months: 1,
        name_ar: '', description_ar: '',
        gender: 'mixed', age_min: '', age_max: '',
        imagePreview: '', existingImage: '',

        // ── reference data ──
        activities:  @js($activities->map(fn ($a) => ['id' => (string) $a->id, 'name' => $a->name])->values()),
        facilities:  @js($facilities->map(fn ($f) => ['id' => (string) $f->id, 'name' => $f->name])->values()),
        instructors: @js($instructors->map(fn ($i) => ['id' => (string) $i->id, 'name' => $i->user?->full_name ?? $i->user?->name ?? 'Unknown'])->values()),
        weekDays:    @js($weekDays),

        // ── schedule builder ──
        schedules: [],
        editingIndex: null,
        draft: { activityId: '', dayValues: [], startTime: '', endTime: '', facilityId: '' },
        trainerAssignments: {},      // { activityId: instructorId }

        // ── helpers ──
        dayName(v) { const d = this.weekDays.find(w => w.value === v); return d ? d.name : v; },
        activityName(id) { const a = this.activities.find(x => x.id === String(id)); return a ? a.name : ''; },
        facilityName(id) { const f = this.facilities.find(x => x.id === String(id)); return f ? f.name : ''; },
        time12(t) {
            if (!t) return '';
            const [h, m] = t.split(':').map(Number);
            const p = h >= 12 ? 'PM' : 'AM';
            return `${h % 12 || 12}:${String(m).padStart(2, '0')} ${p}`;
        },

        get uniqueActivities() {
            const seen = {}, out = [];
            this.schedules.forEach(s => { if (s.activityId && !seen[s.activityId]) { seen[s.activityId] = 1; out.push({ id: s.activityId, name: s.activityName }); } });
            return out;
        },
        get schedulesJson() { return JSON.stringify(this.schedules); },
        get trainersJson() {
            const out = {};
            Object.keys(this.trainerAssignments).forEach(k => { if (this.trainerAssignments[k]) out[k] = this.trainerAssignments[k]; });
            return JSON.stringify(out);
        },
        get formAction() {
            return this.mode === 'edit'
                ? `{{ url('admin/club/' . $club->slug . '/packages') }}/${this.editId}`
                : `{{ route('admin.club.packages.store', $club->slug) }}`;
        },

        // ── draft schedule ──
        toggleDay(v) { const i = this.draft.dayValues.indexOf(v); if (i > -1) this.draft.dayValues.splice(i, 1); else this.draft.dayValues.push(v); },
        resetDraft() { this.draft = { activityId: '', dayValues: [], startTime: '', endTime: '', facilityId: '' }; this.editingIndex = null; },

        commitSchedule() {
            const d = this.draft;
            if (!d.activityId) { window.showToast('warning', '{{ __('admin.pkg_err_activity') }}'); return; }
            if (!d.dayValues.length) { window.showToast('warning', '{{ __('admin.pkg_err_days') }}'); return; }
            if (!d.startTime || !d.endTime) { window.showToast('warning', '{{ __('admin.pkg_err_times') }}'); return; }
            if (d.endTime <= d.startTime) { window.showToast('warning', '{{ __('admin.pkg_err_order') }}'); return; }

            const entry = {
                activityId: String(d.activityId),
                activityName: this.activityName(d.activityId),
                days: d.dayValues.map(v => ({ value: v, name: this.dayName(v) })),
                startTime: d.startTime, endTime: d.endTime,
                facilityId: d.facilityId ? String(d.facilityId) : '',
                facilityName: d.facilityId ? this.facilityName(d.facilityId) : '',
            };
            if (this.editingIndex !== null) this.schedules[this.editingIndex] = entry;
            else this.schedules.push(entry);
            this.resetDraft();
            this.syncTrainers();
        },
        editSchedule(i) {
            const s = this.schedules[i];
            this.draft = { activityId: s.activityId, dayValues: s.days.map(d => d.value), startTime: s.startTime, endTime: s.endTime, facilityId: s.facilityId || '' };
            this.editingIndex = i;
        },
        removeSchedule(i) {
            this.schedules.splice(i, 1);
            if (this.editingIndex === i) this.resetDraft();
            this.syncTrainers();
        },
        syncTrainers() {
            const valid = {};
            this.uniqueActivities.forEach(a => { if (this.trainerAssignments[a.id] != null) valid[a.id] = this.trainerAssignments[a.id]; });
            this.trainerAssignments = valid;
        },

        // ── image ──
        onImage(e) {
            const file = e.target.files[0];
            if (!file) return;
            const r = new FileReader();
            r.onload = ev => this.imagePreview = ev.target.result;
            r.readAsDataURL(file);
        },

        // ── open / reset ──
        resetAll() {
            this.tab = 'basic';
            this.lang = 'en';
            this.name = ''; this.description = ''; this.price = ''; this.registration_fee = ''; this.duration_months = 1;
            this.name_ar = ''; this.description_ar = '';
            this.gender = 'mixed'; this.age_min = ''; this.age_max = '';
            this.imagePreview = ''; this.existingImage = '';
            this.schedules = []; this.trainerAssignments = {}; this.resetDraft();
            const f = this.$refs.form; if (f) f.reset();
        },
        openAdd() { this.mode = 'add'; this.editId = null; this.resetAll(); this.open = true; },
        openEdit(id) {
            const p = (window.packagesData || {})[id];
            if (!p) { window.showToast('error', 'Package not found.'); return; }
            this.mode = 'edit'; this.editId = id; this.resetAll();
            this.name = p.name || ''; this.description = p.description || '';
            this.name_ar = (p.translations && p.translations.name && p.translations.name.ar) || '';
            this.description_ar = (p.translations && p.translations.description && p.translations.description.ar) || '';
            this.price = p.price ?? ''; this.registration_fee = p.registration_fee ?? ''; this.duration_months = p.duration_months || 1;
            this.gender = p.gender || 'mixed';
            this.age_min = p.age_min ?? ''; this.age_max = p.age_max ?? '';
            this.existingImage = p.cover_image ? ('/storage/' + p.cover_image) : '';

            // Reconstruct schedules from activity pivots (group by activity + time window).
            (p.activities || []).forEach(act => {
                if (act.instructor_id) this.trainerAssignments[String(act.id)] = String(act.instructor_id);
                const sched = act.schedule || [];
                if (sched.length) {
                    const groups = {};
                    sched.forEach(s => {
                        const start = s.start_time || s.startTime || '';
                        const end   = s.end_time   || s.endTime   || '';
                        const day   = (s.day || s.day_of_week || '').toLowerCase();
                        const key   = start + '-' + end + '-' + (s.facility_id || '');
                        if (!groups[key]) groups[key] = { days: [], startTime: start, endTime: end, facilityId: s.facility_id ? String(s.facility_id) : '', facilityName: s.facility_name || '' };
                        if (day) groups[key].days.push({ value: day, name: this.dayName(day) });
                    });
                    Object.values(groups).forEach(g => this.schedules.push({
                        activityId: String(act.id), activityName: act.name || this.activityName(act.id),
                        days: g.days, startTime: g.startTime, endTime: g.endTime,
                        facilityId: g.facilityId, facilityName: g.facilityName,
                    }));
                }
            });
            this.open = true;
        },

        submit() {
            if (!this.name.trim()) { window.showToast('warning', '{{ __('admin.pkg_err_name') }}'); this.tab = 'basic'; return; }
            if (!(parseFloat(this.price) >= 0) || this.price === '') { window.showToast('warning', '{{ __('admin.pkg_err_price') }}'); this.tab = 'basic'; return; }
            if (!(parseInt(this.duration_months) >= 1)) { window.showToast('warning', '{{ __('admin.pkg_err_duration') }}'); this.tab = 'basic'; return; }
            this.$refs.form.submit();
        },
    };
};
</script>

<div class="contents" x-data="packageFormSheet()"
     @open-add-package.window="openAdd()"
     @open-edit-package.window="openEdit($event.detail.id)"
     @keydown.escape.window="open = false">
<template x-teleport="body">
<div x-show="open" x-cloak class="fixed inset-0 z-50 overflow-y-auto">

    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50" @click="open = false"></div>

    <div class="flex min-h-full items-end justify-center sm:items-center sm:p-4">
        <div x-show="open"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
             class="relative bg-white rounded-t-3xl sm:rounded-2xl shadow-xl w-full sm:max-w-lg flex flex-col"
             style="height: 92vh; max-height: 92vh;" @click.stop>

            <div class="pt-2.5 pb-1 flex justify-center sm:hidden flex-shrink-0"><span class="w-10 h-1.5 rounded-full bg-gray-300"></span></div>

            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 bg-primary text-white rounded-t-3xl sm:rounded-t-2xl flex-shrink-0">
                <h5 class="text-base font-semibold flex items-center">
                    <i class="bi bi-box-seam mr-2"></i><span x-text="mode === 'edit' ? '{{ __('admin.pkg_edit') }}' : '{{ __('admin.pkg_add') }}'"></span>
                </h5>
                <button type="button" @click="open = false" class="text-white/90 hover:text-white text-2xl leading-none w-8 h-8 flex items-center justify-center -mr-1">&times;</button>
            </div>

            {{-- Tabs --}}
            <div class="px-4 pt-3 flex-shrink-0">
                <div class="grid grid-cols-3 gap-1 p-1 bg-muted rounded-2xl text-xs font-semibold">
                    <button type="button" @click="tab = 'basic'" :class="tab === 'basic' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="m-press rounded-xl py-2 transition-colors"><i class="bi bi-info-circle mr-1"></i>{{ __('admin.pkg_section_basic') }}</button>
                    <button type="button" @click="tab = 'schedules'" :class="tab === 'schedules' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="m-press rounded-xl py-2 transition-colors"><i class="bi bi-calendar2-week mr-1"></i>{{ __('admin.pkg_section_schedules') }}<span x-show="schedules.length" class="ml-1" x-text="'(' + schedules.length + ')'"></span></button>
                    <button type="button" @click="tab = 'trainers'" :class="tab === 'trainers' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="m-press rounded-xl py-2 transition-colors"><i class="bi bi-person-check mr-1"></i>{{ __('admin.pkg_section_trainers') }}</button>
                </div>
            </div>

            <form x-ref="form" method="POST" :action="formAction" enctype="multipart/form-data"
                  class="flex-1 overflow-y-auto overscroll-contain px-4 py-4" @submit.prevent="submit()">
                @csrf
                <input type="hidden" name="_method" :value="mode === 'edit' ? 'PUT' : 'POST'">
                <input type="hidden" name="gender_restriction" :value="gender">
                <input type="hidden" name="schedules" :value="schedulesJson">
                <input type="hidden" name="trainer_assignments" :value="trainersJson">

                {{-- ===== BASIC ===== --}}
                <div x-show="tab === 'basic'" class="space-y-4">
                    <x-lang-toggle class="mb-4" />
                    {{-- Cover --}}
                    <div>
                        <label class="form-label">{{ __('admin.pkg_cover') }}</label>
                        <input type="file" name="image" accept="image/*" class="hidden" x-ref="image" @change="onImage($event)">
                        <button type="button" @click="$refs.image.click()"
                                class="m-press w-full h-36 rounded-2xl border-2 border-dashed border-gray-200 bg-muted/40 flex items-center justify-center overflow-hidden relative">
                            <template x-if="imagePreview || existingImage">
                                <img :src="imagePreview || existingImage" class="absolute inset-0 w-full h-full object-cover">
                            </template>
                            <span class="relative flex flex-col items-center text-muted-foreground" :class="(imagePreview || existingImage) ? 'bg-black/40 text-white rounded-xl px-3 py-1.5' : ''">
                                <i class="bi bi-camera text-2xl"></i>
                                <span class="text-xs font-medium mt-1" x-text="(imagePreview || existingImage) ? '{{ __('admin.pkg_change_cover') }}' : '{{ __('admin.pkg_upload_cover') }}'"></span>
                            </span>
                        </button>
                    </div>

                    <div>
                        <label class="form-label">{{ __('admin.pkg_name') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="name" x-model="name" required x-show="lang==='en'" placeholder="{{ __('admin.pkg_name_ph') }}" class="form-control">
                        <input type="text" name="translations[name][ar]" x-model="name_ar" dir="rtl" x-show="lang==='ar'" x-cloak placeholder="الاسم بالعربية" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">{{ __('admin.pkg_description') }}</label>
                        <textarea name="description" x-model="description" rows="3" x-show="lang==='en'" placeholder="{{ __('admin.pkg_description_ph') }}" class="form-control resize-none"></textarea>
                        <textarea name="translations[description][ar]" x-model="description_ar" dir="rtl" x-show="lang==='ar'" x-cloak rows="3" placeholder="الوصف بالعربية" class="form-control resize-none"></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">{{ __('admin.pkg_price') }} ({{ $club->currency ?: '' }}) <span class="text-red-500">*</span></label>
                            <input type="number" name="price" x-model="price" step="0.01" min="0" required placeholder="0.00" class="form-control">
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.pkg_duration') }} <span class="text-red-500">*</span></label>
                            <input type="number" name="duration_months" x-model="duration_months" min="1" required class="form-control">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">{{ __('admin.pkg_reg_fee') }} ({{ $club->currency ?: '' }})</label>
                        <input type="number" name="registration_fee" x-model="registration_fee" step="0.01" min="0" placeholder="{{ __('admin.pkg_reg_fee_ph') }}" class="form-control">
                        <p class="text-xs text-muted-foreground mt-1">{{ __('admin.pkg_reg_fee_hint') }}</p>
                    </div>
                    <div>
                        <label class="form-label">{{ __('admin.pkg_gender') }}</label>
                        <div class="grid grid-cols-3 gap-1 p-1 bg-muted rounded-2xl text-sm font-medium">
                            <button type="button" @click="gender = 'mixed'" :class="gender === 'mixed' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="m-press rounded-xl py-2">{{ __('admin.pkg_mixed') }}</button>
                            <button type="button" @click="gender = 'male'" :class="gender === 'male' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="m-press rounded-xl py-2">{{ __('admin.pkg_male') }}</button>
                            <button type="button" @click="gender = 'female'" :class="gender === 'female' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="m-press rounded-xl py-2">{{ __('admin.pkg_female') }}</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">{{ __('admin.pkg_age_min') }}</label>
                            <input type="number" name="age_min" x-model="age_min" min="0" placeholder="—" class="form-control">
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.pkg_age_max') }}</label>
                            <input type="number" name="age_max" x-model="age_max" min="0" placeholder="—" class="form-control">
                        </div>
                    </div>
                </div>

                {{-- ===== SCHEDULES ===== --}}
                <div x-show="tab === 'schedules'" class="space-y-4">
                    <p class="text-xs text-muted-foreground">{{ __('admin.pkg_schedule_hint') }}</p>

                    {{-- Draft builder --}}
                    <div class="rounded-2xl border border-gray-100 bg-muted/20 p-3 space-y-3">
                        <div>
                            <label class="form-label">{{ __('admin.pkg_activity') }}</label>
                            <select x-model="draft.activityId" class="form-select">
                                <option value="">{{ __('admin.pkg_select_activity') }}</option>
                                <template x-for="a in activities" :key="a.id"><option :value="a.id" x-text="a.name"></option></template>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.pkg_days') }}</label>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="w in weekDays" :key="w.value">
                                    <button type="button" @click="toggleDay(w.value)"
                                            :class="draft.dayValues.includes(w.value) ? 'bg-primary text-white border-primary' : 'bg-white text-muted-foreground border-gray-200'"
                                            class="m-press px-3 py-1.5 rounded-full text-xs font-medium border transition-colors" x-text="w.name"></button>
                                </template>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label">{{ __('admin.pkg_start') }}</label>
                                <input type="time" x-model="draft.startTime" class="form-control">
                            </div>
                            <div>
                                <label class="form-label">{{ __('admin.pkg_end') }}</label>
                                <input type="time" x-model="draft.endTime" class="form-control">
                            </div>
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.pkg_facility') }}</label>
                            <select x-model="draft.facilityId" class="form-select">
                                <option value="">{{ __('admin.pkg_no_facility') }}</option>
                                <template x-for="f in facilities" :key="f.id"><option :value="f.id" x-text="f.name"></option></template>
                            </select>
                        </div>
                        <button type="button" @click="commitSchedule()" class="m-press w-full rounded-xl bg-primary text-white py-2.5 text-sm font-semibold">
                            <i class="bi bi-plus-lg mr-1"></i><span x-text="editingIndex !== null ? '{{ __('admin.pkg_update_schedule') }}' : '{{ __('admin.pkg_add_schedule') }}'"></span>
                        </button>
                    </div>

                    {{-- Committed schedules --}}
                    <div x-show="!schedules.length" class="text-center py-6 text-muted-foreground">
                        <i class="bi bi-calendar-x text-2xl"></i>
                        <p class="text-xs mt-1">{{ __('admin.pkg_no_schedules') }}</p>
                    </div>
                    <div class="space-y-2">
                        <template x-for="(s, i) in schedules" :key="i">
                            <div class="rounded-2xl border border-gray-100 p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-sm text-foreground truncate"><i class="bi bi-activity text-primary mr-1"></i><span x-text="s.activityName"></span></p>
                                        <p class="text-xs text-muted-foreground mt-0.5"><span x-text="time12(s.startTime)"></span> – <span x-text="time12(s.endTime)"></span><template x-if="s.facilityName"><span> · <i class="bi bi-geo-alt"></i> <span x-text="s.facilityName"></span></span></template></p>
                                        <div class="flex flex-wrap gap-1 mt-1.5">
                                            <template x-for="d in s.days" :key="d.value"><span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-accent text-primary" x-text="d.name"></span></template>
                                        </div>
                                    </div>
                                    <div class="flex gap-1 flex-shrink-0">
                                        <button type="button" @click="editSchedule(i)" class="m-press w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="bi bi-pencil text-xs"></i></button>
                                        <button type="button" @click="removeSchedule(i)" class="m-press w-7 h-7 rounded-lg bg-red-50 text-red-600 flex items-center justify-center"><i class="bi bi-trash text-xs"></i></button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- ===== TRAINERS ===== --}}
                <div x-show="tab === 'trainers'" class="space-y-4">
                    <p class="text-xs text-muted-foreground">{{ __('admin.pkg_trainers_hint') }}</p>
                    <div x-show="!uniqueActivities.length" class="text-center py-6 text-muted-foreground">
                        <i class="bi bi-person-x text-2xl"></i>
                        <p class="text-xs mt-1">{{ __('admin.pkg_no_activities') }}</p>
                    </div>
                    <div class="space-y-2.5">
                        <template x-for="a in uniqueActivities" :key="a.id">
                            <div class="rounded-2xl border border-gray-100 p-3">
                                <p class="font-semibold text-sm text-foreground mb-2"><i class="bi bi-activity text-primary mr-1"></i><span x-text="a.name"></span></p>
                                <select x-model="trainerAssignments[a.id]" class="form-select">
                                    <option value="">{{ __('admin.pkg_select_instructor') }}</option>
                                    <template x-for="ins in instructors" :key="ins.id"><option :value="ins.id" x-text="ins.name"></option></template>
                                </select>
                            </div>
                        </template>
                    </div>
                </div>
            </form>

            {{-- Footer --}}
            <div class="px-4 py-3 bg-gray-50 border-t flex-shrink-0 flex items-center gap-2" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                <button type="button" @click="open = false" class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 bg-white text-sm font-medium">{{ __('admin.cancel') }}</button>
                <button type="button" @click="if (!(name || '').trim()) { lang = 'en' } $nextTick(() => $refs.form.requestSubmit())" class="flex-1 btn btn-primary py-2.5">
                    <i class="bi bi-check-lg mr-1"></i><span x-text="mode === 'edit' ? '{{ __('admin.pkg_update') }}' : '{{ __('admin.pkg_create') }}'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
</template>
</div>
