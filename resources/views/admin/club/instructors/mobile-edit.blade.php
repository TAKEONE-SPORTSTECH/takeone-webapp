{{-- Mobile "Edit Instructor" — full-height bottom sheet. Opens on the `open-edit-instructor`
     window event (detail.id); prefills from window.instructorData. Submits to instructors.update. --}}
<script>
window.instructorEditSheet = function () {
    return {
        open: false,
        id: null,
        lang: 'en',
        name: '',
        role: '',
        roleAr: '',
        experience: '',
        bio: '',
        skills: [],
        skillDraft: '',
        photoPreview: '',
        staffType: 'instructor',
        compType: 'volunteer',
        wageAmount: '',
        wagePeriod: 'monthly',
        slotIds: [],
        base: '{{ url('admin/club/' . $club->slug . '/instructors') }}',

        openWith(id) {
            const d = (window.instructorData || {})[id] || {};
            this.id = id;
            this.name = d.name || '';
            this.role = d.role || '';
            this.roleAr = d.translations?.role?.ar || '';
            this.experience = (d.experience ?? '') === null ? '' : (d.experience ?? '');
            this.bio = d.bio || '';
            this.skills = Array.isArray(d.skills) ? [...d.skills] : [];
            this.photoPreview = d.photo || '';
            this.skillDraft = '';
            this.staffType = d.staff_type || 'instructor';
            this.compType = d.compensation_type === 'paid' ? 'paid' : 'volunteer';
            this.wageAmount = (d.wage_amount ?? '') === null ? '' : (d.wage_amount ?? '');
            this.wagePeriod = d.wage_period || 'monthly';
            this.slotIds = Array.isArray(d.slot_ids) ? [...d.slot_ids] : [];
            this.open = true;
        },
        action() { return `${this.base}/${this.id}`; },

        toggleSlot(sid) {
            const i = this.slotIds.indexOf(sid);
            if (i > -1) this.slotIds.splice(i, 1); else this.slotIds.push(sid);
        },

        addSkill() {
            const s = this.skillDraft.trim();
            if (s && !this.skills.includes(s)) this.skills.push(s);
            this.skillDraft = '';
        },
        removeSkill(i) { this.skills.splice(i, 1); },
        onPhoto(e) {
            const file = e.target.files[0];
            if (!file) return;
            const r = new FileReader();
            r.onload = (ev) => this.photoPreview = ev.target.result;
            r.readAsDataURL(file);
        },
    };
};
</script>

<div class="contents" x-data="instructorEditSheet()"
     @open-edit-instructor.window="openWith($event.detail.id)"
     @keydown.escape.window="open = false">
{{-- Teleport to <body> so the fixed overlay escapes the transformed `.mobile-stagger`
     container (a CSS transform on an ancestor traps position:fixed inside it). --}}
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

            {{-- Header --}}
            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl sm:rounded-t-2xl text-white relative overflow-hidden"
                 style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi bi-pencil-square text-xl"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-black leading-tight truncate"><span x-text="name || '{{ __('admin.ins_edit') }}'"></span></h3>
                        <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $club->name }}</p>
                    </div>
                    <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            {{-- Form --}}
            <form x-ref="form" method="POST" :action="action()" enctype="multipart/form-data"
                  class="flex-1 overflow-y-auto overscroll-contain px-4 py-4 space-y-4">
                @csrf
                @method('PUT')

                {{-- Photo --}}
                <div class="flex items-center gap-3">
                    <span class="w-16 h-16 rounded-2xl bg-muted overflow-hidden flex items-center justify-center flex-shrink-0">
                        <template x-if="photoPreview"><img :src="photoPreview" class="w-16 h-16 object-cover"></template>
                        <template x-if="!photoPreview"><i class="bi bi-person text-muted-foreground text-2xl"></i></template>
                    </span>
                    <input type="file" name="photo" accept="image/*" class="hidden" x-ref="photo" @change="onPhoto($event)">
                    <button type="button" @click="$refs.photo.click()" class="m-press flex-1 rounded-xl border border-gray-200 py-2.5 text-sm font-medium text-foreground bg-white">
                        <i class="bi bi-camera mr-1"></i>{{ __('admin.change_photo') }}
                    </button>
                </div>

                <div>
                    <label class="form-label">{{ __('admin.full_name') }}</label>
                    <input type="text" name="name" x-model="name" placeholder="{{ __('admin.full_name') }}" class="form-control">
                </div>
                <x-lang-toggle class="mb-4" />
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label">{{ __('admin.ins_role') }}</label>
                        <input type="text" name="role" x-model="role" x-show="lang==='en'" placeholder="{{ __('admin.ins_role_ph') }}" class="form-control">
                        <input type="text" name="translations[role][ar]" x-model="roleAr" dir="rtl" x-show="lang==='ar'" x-cloak placeholder="المسمى بالعربية" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">{{ __('admin.ins_experience') }}</label>
                        <input type="number" min="0" name="experience" x-model="experience" placeholder="5" class="form-control">
                    </div>
                </div>
                <div>
                    <label class="form-label">{{ __('admin.ins_skills') }}</label>
                    <div class="flex gap-2">
                        <input type="text" x-model="skillDraft" @keydown.enter.prevent="addSkill()" placeholder="{{ __('admin.ins_skill_ph') }}" class="form-control flex-1">
                        <button type="button" @click="addSkill()" class="m-press px-4 rounded-xl bg-primary text-white"><i class="bi bi-plus-lg"></i></button>
                    </div>
                    <div class="flex flex-wrap gap-1.5 mt-2" x-show="skills.length">
                        <template x-for="(s, i) in skills" :key="i">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-accent text-primary">
                                <span x-text="s"></span>
                                <button type="button" @click="removeSkill(i)" class="hover:text-red-500"><i class="bi bi-x"></i></button>
                            </span>
                        </template>
                    </div>
                    <input type="hidden" name="skills" :value="JSON.stringify(skills)">
                </div>
                <div>
                    <label class="form-label">{{ __('admin.ins_bio') }}</label>
                    <textarea name="bio" x-model="bio" rows="4" placeholder="{{ __('admin.ins_bio_ph') }}" class="form-control resize-none"></textarea>
                </div>

                @include('admin.club.instructors.partials.compensation-fields')

                <div x-show="staffType === 'instructor'" x-transition>
                    @include('admin.club.instructors.partials.package-slots-fields')
                </div>
            </form>

            {{-- Footer --}}
            <div class="px-4 py-3 bg-gray-50 border-t flex-shrink-0 flex items-center gap-2" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                <button type="button" @click="open = false" class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 bg-white text-sm font-medium">{{ __('admin.cancel') }}</button>
                <button type="button" @click="$refs.form.requestSubmit()" class="flex-1 btn btn-primary py-2.5">
                    <i class="bi bi-check-lg mr-1"></i>{{ __('admin.update') }}
                </button>
            </div>
        </div>
    </div>
</div>
</template>
</div>
