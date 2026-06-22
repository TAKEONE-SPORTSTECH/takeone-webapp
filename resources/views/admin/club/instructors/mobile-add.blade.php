{{-- Mobile "Add Instructor" — full-height bottom sheet. Opens on the `open-add-instructor`
     window event. Submits to the same endpoint/fields as the desktop wizard. --}}
<script>
window.instructorAddSheet = function () {
    return {
        open: false,
        mode: 'new',                 // 'new' | 'existing'
        lang: 'en',
        skills: [],
        skillDraft: '',
        photoPreview: '',
        compType: 'volunteer',       // 'volunteer' | 'paid'
        wageAmount: '',
        wagePeriod: 'monthly',
        slotIds: [],
        // existing-member search
        search: '',
        results: [],
        searching: false,
        selectedId: '',
        selectedName: '',
        _t: null,

        roleName()  { return this.mode === 'new' ? 'specialty'   : 'specialty_existing'; },
        expName()   { return this.mode === 'new' ? 'experience'  : 'experience_existing'; },
        skillsName(){ return this.mode === 'new' ? 'skills'      : 'skills_existing'; },
        bioName()   { return this.mode === 'new' ? 'bio'         : 'bio_existing'; },

        reset() {
            this.mode = 'new'; this.skills = []; this.skillDraft = ''; this.photoPreview = '';
            this.search = ''; this.results = []; this.selectedId = ''; this.selectedName = '';
            this.compType = 'volunteer'; this.wageAmount = ''; this.wagePeriod = 'monthly'; this.slotIds = [];
            const f = this.$refs.form; if (f) f.reset();
        },
        close() { this.open = false; },

        toggleSlot(id) {
            const i = this.slotIds.indexOf(id);
            if (i > -1) this.slotIds.splice(i, 1); else this.slotIds.push(id);
        },

        addSkill() {
            const s = this.skillDraft.trim();
            if (s && !this.skills.includes(s)) this.skills.push(s);
            this.skillDraft = '';
        },
        removeSkill(i) { this.skills.splice(i, 1); },

        onPhoto(e) {
            const file = e.target.files[0];
            if (!file) { this.photoPreview = ''; return; }
            const r = new FileReader();
            r.onload = (ev) => this.photoPreview = ev.target.result;
            r.readAsDataURL(file);
        },

        doSearch() {
            clearTimeout(this._t);
            const q = this.search.trim();
            if (q.length < 2) { this.results = []; return; }
            this._t = setTimeout(async () => {
                this.searching = true;
                try {
                    const res = await fetch(`{{ route('admin.club.members.search', $club->slug) }}?query=${encodeURIComponent(q)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await res.json();
                    this.results = data.users ?? data ?? [];
                } catch (e) { this.results = []; }
                this.searching = false;
            }, 300);
        },
        pick(m) {
            this.selectedId = m.id;
            this.selectedName = m.name || m.full_name || '';
            this.results = []; this.search = '';
        },

        submit(e) {
            // Hidden dropdowns (gender/nationality/birthdate) aren't natively required to avoid
            // the "hidden required field" focus bug, so validate the new-member path here.
            if (this.mode === 'new') {
                const fd = new FormData(e.target);
                const need = { email:'Email', password:'Password', name:'Full name', phone:'Phone number', gender:'Gender', birthdate:'Date of birth', nationality:'Nationality' };
                for (const [k, label] of Object.entries(need)) {
                    if (!String(fd.get(k) || '').trim()) { window.showToast('warning', `${label} is required.`); return; }
                }
            } else if (!this.selectedId) {
                window.showToast('warning', 'Please select a member first.'); return;
            }
            if (this.compType === 'paid' && !(parseFloat(this.wageAmount) > 0)) {
                window.showToast('warning', 'Enter a wage amount, or set the instructor as a volunteer.'); return;
            }
            e.target.submit();
        },
    };
};
</script>

<div class="contents" x-data="instructorAddSheet()"
     @open-add-instructor.window="open = true; reset()"
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

            <div class="pt-2.5 pb-1 flex justify-center sm:hidden flex-shrink-0"><span class="w-10 h-1.5 rounded-full bg-gray-300"></span></div>

            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 bg-primary text-white rounded-t-3xl sm:rounded-t-2xl flex-shrink-0">
                <h5 class="text-base font-semibold flex items-center"><i class="bi bi-person-plus mr-2"></i>{{ __('admin.ins_add') }}</h5>
                <button type="button" @click="open = false" class="text-white/90 hover:text-white text-2xl leading-none w-8 h-8 flex items-center justify-center -mr-1">&times;</button>
            </div>

            {{-- Mode toggle --}}
            <div class="px-4 pt-3 flex-shrink-0">
                <div class="grid grid-cols-2 gap-1 p-1 bg-muted rounded-2xl text-sm font-semibold">
                    <button type="button" @click="mode = 'new'" :class="mode === 'new' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="m-press rounded-xl py-2 transition-colors">
                        <i class="bi bi-person-plus mr-1"></i>{{ __('admin.ins_new_member') }}
                    </button>
                    <button type="button" @click="mode = 'existing'" :class="mode === 'existing' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="m-press rounded-xl py-2 transition-colors">
                        <i class="bi bi-search mr-1"></i>{{ __('admin.ins_existing_member') }}
                    </button>
                </div>
            </div>

            {{-- Form --}}
            <form x-ref="form" method="POST" action="{{ route('admin.club.instructors.store', $club->slug) }}"
                  enctype="multipart/form-data" class="flex-1 overflow-y-auto overscroll-contain px-4 py-4 space-y-4"
                  @submit.prevent="submit($event)">
                @csrf
                <input type="hidden" name="creation_type" :value="mode">
                <input type="hidden" name="selected_member_id" :value="selectedId">

                {{-- ===== NEW member ===== --}}
                <div x-show="mode === 'new'" class="space-y-4">
                    <div>
                        <label class="form-label">{{ __('admin.full_name') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="name" :required="mode === 'new'" placeholder="{{ __('admin.full_name') }}" class="form-control">
                    </div>
                    <div class="grid grid-cols-1 gap-3">
                        <div>
                            <label class="form-label">{{ __('admin.email') }} <span class="text-red-500">*</span></label>
                            <input type="email" name="email" :required="mode === 'new'" placeholder="member@example.com" class="form-control">
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.password') }} <span class="text-red-500">*</span></label>
                            <input type="password" name="password" :required="mode === 'new'" placeholder="{{ __('admin.min_6_chars') }}" class="form-control">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">{{ __('admin.phone') }} <span class="text-red-500">*</span></label>
                        <x-country-code-dropdown name="country_code" id="m_instructor_country_code" value="+973" :required="false">
                            <input type="tel" name="phone" placeholder="{{ __('admin.phone') }}" class="w-full px-3 py-3 text-base bg-transparent focus:outline-none">
                        </x-country-code-dropdown>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-gender-dropdown name="gender" id="m_instructor_gender" label="{{ __('admin.gender') }}" :required="false" />
                        <x-birthdate-dropdown name="birthdate" id="m_instructor_birthdate" label="{{ __('admin.dob') }}" :required="false" :minAge="16" :maxAge="80" />
                    </div>
                    <x-country-dropdown name="nationality" id="m_instructor_nationality" label="{{ __('admin.nationality') }}" :required="false" />

                    <div>
                        <label class="form-label">{{ __('admin.photo') }}</label>
                        <input type="file" name="photo" accept="image/*" class="hidden" x-ref="photo" @change="onPhoto($event)">
                        <div class="flex items-center gap-3">
                            <span class="w-16 h-16 rounded-2xl bg-muted overflow-hidden flex items-center justify-center flex-shrink-0">
                                <template x-if="photoPreview"><img :src="photoPreview" class="w-16 h-16 object-cover"></template>
                                <template x-if="!photoPreview"><i class="bi bi-person text-muted-foreground text-2xl"></i></template>
                            </span>
                            <button type="button" @click="$refs.photo.click()" class="m-press flex-1 rounded-xl border border-gray-200 py-2.5 text-sm font-medium text-foreground bg-white">
                                <i class="bi bi-camera mr-1"></i><span x-text="photoPreview ? '{{ __('admin.change_photo') }}' : '{{ __('admin.upload_photo') }}'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- ===== EXISTING member ===== --}}
                <div x-show="mode === 'existing'" class="space-y-3">
                    <div class="relative">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none"></i>
                        <input type="text" x-model="search" @input="doSearch()" placeholder="{{ __('admin.search_members') }}" class="w-full pl-10 pr-3 py-2.5 bg-muted rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40">
                    </div>
                    <div x-show="results.length" class="rounded-2xl border border-gray-100 divide-y divide-gray-100 overflow-hidden max-h-64 overflow-y-auto">
                        <template x-for="m in results" :key="m.id">
                            <button type="button" @click="pick(m)" class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-muted/60 text-left">
                                <span class="w-9 h-9 rounded-full bg-accent text-primary flex items-center justify-center overflow-hidden flex-shrink-0">
                                    <template x-if="m.profile_picture"><img :src="m.profile_picture" class="w-9 h-9 object-cover"></template>
                                    <template x-if="!m.profile_picture"><i class="bi bi-person"></i></template>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-foreground truncate" x-text="m.name || m.full_name"></span>
                                    <span class="block text-xs text-muted-foreground truncate" x-text="m.email || ''"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                    <div x-show="selectedId" class="flex items-center gap-3 rounded-2xl bg-accent border border-primary/20 px-3 py-3">
                        <i class="bi bi-check-circle-fill text-primary text-lg"></i>
                        <div class="min-w-0">
                            <p class="text-[11px] text-muted-foreground">{{ __('admin.ins_selected_member') }}</p>
                            <p class="text-sm font-semibold text-foreground truncate" x-text="selectedName"></p>
                        </div>
                        <button type="button" @click="selectedId=''; selectedName=''" class="ml-auto text-muted-foreground"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>

                {{-- ===== Shared: role / experience / skills / bio ===== --}}
                <div class="pt-1 space-y-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('admin.ins_professional') }}</span>
                        <div class="flex-1 h-px bg-gray-100"></div>
                    </div>
                    <x-lang-toggle class="mb-4" />
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">{{ __('admin.ins_role') }}</label>
                            <input type="text" :name="roleName()" x-show="lang==='en'" placeholder="{{ __('admin.ins_role_ph') }}" class="form-control">
                            <input type="text" name="translations[role][ar]" dir="rtl" x-show="lang==='ar'" x-cloak placeholder="المسمى بالعربية" class="form-control">
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.ins_experience') }}</label>
                            <input type="number" min="0" :name="expName()" placeholder="5" class="form-control">
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
                        <input type="hidden" :name="skillsName()" :value="JSON.stringify(skills)">
                    </div>
                    <div>
                        <label class="form-label">{{ __('admin.ins_bio') }}</label>
                        <textarea :name="bioName()" rows="3" placeholder="{{ __('admin.ins_bio_ph') }}" class="form-control resize-none"></textarea>
                    </div>
                </div>

                @include('admin.club.instructors.partials.compensation-fields')

                @include('admin.club.instructors.partials.package-slots-fields')
            </form>

            {{-- Footer --}}
            <div class="px-4 py-3 bg-gray-50 border-t flex-shrink-0 flex items-center gap-2" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                <button type="button" @click="open = false" class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 bg-white text-sm font-medium">{{ __('admin.cancel') }}</button>
                <button type="button" @click="$refs.form.requestSubmit()" class="flex-1 btn btn-primary py-2.5">
                    <i class="bi bi-check-lg mr-1"></i>{{ __('admin.ins_create') }}
                </button>
            </div>
        </div>
    </div>
</div>
</template>
</div>
