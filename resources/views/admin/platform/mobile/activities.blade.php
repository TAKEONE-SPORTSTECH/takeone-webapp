@extends('layouts.app')

@section('hide-navbar', true)
@section('title', 'Activities')

@php
    $activitiesJson = $activities->map(fn ($a) => [
        'uuid' => $a->uuid,
        'name' => $a->name,
        'name_ar' => data_get($a->translations, 'name.ar', ''),
        'slug' => $a->slug,
        'description' => $a->description,
        'description_ar' => data_get($a->translations, 'description.ar', ''),
        'image_prompt' => $a->image_prompt,
        'has_prompt' => filled($a->image_prompt),
        'image_url' => route('admin.platform.activities.image', $a),
        'set_image_url' => route('admin.platform.activities.set-image', $a),
        'icon' => $a->icon,
        'is_active' => (bool) $a->is_active,
        'usage_count' => (int) $a->usage_count,
        'variants' => $a->variants ?: [],
        'picture_src' => $a->picture_url ? asset('storage/'.$a->picture_url) : null,
        'update_url' => route('admin.platform.activities.update', $a),
        'destroy_url' => route('admin.platform.activities.destroy', $a),
    ])->values();
@endphp

@section('content')
<div class="min-h-screen bg-background pb-24" x-data="activityDirectory(@js($activitiesJson))">

    {{-- ===== Top bar ===== --}}
    <header class="sticky top-0 z-40 bg-white border-b border-border">
        <div class="flex items-center gap-2 px-3 h-14">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('admin.platform.index') }}')"
                    class="m-press w-10 h-10 -ml-1 rounded-xl flex items-center justify-center text-foreground" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-xl"></i>
            </button>
            <p class="flex-1 min-w-0 text-base font-bold text-primary truncate">Activities</p>
            <button type="button" @click="openCreate()"
                    class="m-press w-10 h-10 rounded-xl flex items-center justify-center text-primary" aria-label="Add activity">
                <i class="bi bi-plus-circle text-xl"></i>
            </button>
        </div>
    </header>

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-6 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-start justify-between gap-3 relative z-10">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">Global directory</p>
                <h1 class="text-2xl font-black mt-0.5 leading-tight">Activities</h1>
                <p class="mt-1.5 text-sm text-white/85">The shared catalog every club reuses — disciplines, their styles/federations, descriptions and icons.</p>
            </div>
            <div class="w-12 h-12 shrink-0 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                <i class="bi bi-lightning-charge text-xl m-float"></i>
            </div>
        </div>
        <div class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-white/15 border border-white/25 px-3 py-1 text-[12px] font-semibold relative z-10">
            <i class="bi bi-collection"></i> <span x-text="list.length"></span> activities
        </div>
    </header>

    <div class="px-4 pt-4">
        {{-- Search --}}
        <div class="relative mb-4">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" x-model="search" autocomplete="off" placeholder="Search activities…"
                   class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/40 focus:border-transparent text-sm">
        </div>

        {{-- Empty state --}}
        <template x-if="filtered.length === 0">
            <div class="bg-white rounded-2xl px-6 py-14 text-center shadow-sm border border-gray-100">
                <i class="bi bi-lightning-charge text-5xl text-gray-300 m-float inline-block"></i>
                <p class="text-sm font-semibold text-foreground mt-4">No activities found</p>
                <p class="text-[12px] text-muted-foreground mt-1" x-text="search ? 'Nothing matches your search.' : 'Add the first activity to the directory.'"></p>
            </div>
        </template>

        {{-- ===== Cards ===== --}}
        <div class="space-y-3 mobile-stagger">
            <template x-for="a in filtered" :key="a.uuid">
                <div class="m-card bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-100" :class="!a.is_active && 'opacity-60'">
                    {{-- Tapping the card body opens the activity in place; the back
                         arrow on the activity page returns here (same tab). --}}
                    <a :href="'{{ url('/activity') }}/' + a.uuid" class="m-press flex gap-3 p-3 no-underline text-left">
                        {{-- Thumb --}}
                        <span class="w-16 h-16 rounded-2xl bg-accent text-primary grid place-items-center flex-shrink-0 overflow-hidden">
                            <template x-if="a.picture_src"><img :src="a.picture_src" alt="" class="w-full h-full object-cover"></template>
                            <template x-if="!a.picture_src"><i class="bi text-2xl" :class="a.icon || 'bi-activity'"></i></template>
                        </span>

                        {{-- Body --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-semibold text-sm text-gray-900 truncate" x-text="a.name"></h3>
                                    <p class="text-[12px] text-muted-foreground truncate" x-show="a.name_ar" dir="rtl" x-text="a.name_ar"></p>
                                </div>
                                <span class="text-[10px] px-2 py-0.5 rounded-full flex-shrink-0 font-medium"
                                      :class="a.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                      x-text="a.is_active ? 'Active' : 'Hidden'"></span>
                            </div>

                            {{-- Styles --}}
                            <div class="flex flex-wrap gap-1 mt-1.5" x-show="a.variants.length">
                                <template x-for="(v, i) in a.variants" :key="i">
                                    <span class="text-[10px] px-2 py-0.5 rounded-md bg-muted/60 text-foreground" x-text="v.name"></span>
                                </template>
                            </div>

                            <p class="text-[12px] text-muted-foreground mt-1.5 line-clamp-2" x-show="a.description"
                               x-text="(a.description || '').replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim()"></p>

                            <p class="text-[11px] text-muted-foreground mt-1.5"><i class="bi bi-people mr-1"></i><span x-text="a.usage_count"></span> clubs</p>
                        </div>

                        <i class="bi bi-chevron-right text-muted-foreground/50 self-center flex-shrink-0"></i>
                    </a>

                    {{-- Actions --}}
                    <div class="flex items-stretch border-t border-gray-100 divide-x divide-gray-100">
                        <button type="button" x-show="a.has_prompt" @click="generateImage(a)" :disabled="a._imaging"
                                class="m-press flex-1 py-2.5 flex items-center justify-center gap-1.5 text-primary text-[12px] font-medium disabled:opacity-50">
                            <i class="bi" :class="a._imaging ? 'bi-arrow-repeat animate-spin' : 'bi-image'"></i> Image
                        </button>
                        <button type="button" @click="openEdit(a)"
                                class="m-press flex-1 py-2.5 flex items-center justify-center gap-1.5 text-foreground text-[12px] font-medium">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                        <button type="button" @click="remove(a)"
                                class="m-press flex-1 py-2.5 flex items-center justify-center gap-1.5 text-red-600 text-[12px] font-medium">
                            <i class="bi bi-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ===== Create / edit bottom-sheet ===== --}}
    {{-- Teleport to <body> so the fixed sheet escapes #shell-content's transformed
         ancestor (.mobile-stagger leaves a transform that would clip a fixed child). --}}
    <template x-teleport="body">
        <div>
            {{-- Backdrop --}}
            <div x-show="modalOpen" x-cloak x-transition.opacity
                 class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm" @click="modalOpen=false"></div>

            {{-- Sheet --}}
            <div x-show="modalOpen" x-cloak
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0"
                 x-transition:leave-end="translate-y-full"
                 class="fixed inset-x-0 bottom-0 z-[60] max-h-[92vh] flex flex-col bg-background rounded-t-3xl shadow-2xl">

                {{-- Header --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-3 border-b border-border rounded-t-3xl bg-white">
                    <div class="w-10 h-1.5 rounded-full bg-gray-300 mx-auto"></div>
                    <div class="flex items-center justify-between mt-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-10 h-10 rounded-2xl bg-accent text-primary grid place-items-center flex-shrink-0">
                                <i class="bi" :class="form.icon || 'bi-lightning-charge'"></i>
                            </div>
                            <div class="min-w-0">
                                <h2 class="text-base font-black leading-tight text-foreground" x-text="editing ? 'Edit activity' : 'Add activity'"></h2>
                                <p class="text-[11px] text-muted-foreground truncate" x-text="form.name || 'Global directory'"></p>
                            </div>
                        </div>
                        <button type="button" @click="modalOpen=false" class="m-press w-9 h-9 rounded-full bg-muted grid place-items-center text-foreground flex-shrink-0">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- Scrollable body --}}
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">

                    {{-- AI generate --}}
                    <button type="button" @click="generateContent()" :disabled="aiBusy || !form.name.trim()"
                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-accent text-primary hover:bg-primary hover:text-white transition-colors disabled:opacity-50">
                        <i class="bi" :class="aiBusy ? 'bi-arrow-repeat animate-spin' : 'bi-magic'"></i>
                        <span x-text="aiBusy ? 'Generating…' : 'Generate full write-up with AI'"></span>
                    </button>
                    <p class="text-[11px] text-muted-foreground -mt-2">Fills the bilingual description + image prompt from the name.</p>

                    {{-- Name EN / AR --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name (English) <span class="text-red-500">*</span></label>
                        <input type="text" x-model="form.name" maxlength="255" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الاسم (بالعربية)</label>
                        <input type="text" x-model="form.name_ar" dir="rtl" maxlength="255" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>

                    {{-- Icon --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Icon <span class="text-muted-foreground font-normal">(e.g. bi-person-arms-up)</span></label>
                        <div class="flex items-center gap-2">
                            <span class="w-11 h-11 rounded-xl bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi" :class="form.icon || 'bi-activity'"></i></span>
                            <input type="text" x-model="form.icon" placeholder="bi-activity" maxlength="50" class="flex-1 px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent">
                        </div>
                    </div>

                    {{-- Description EN / AR --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description (English)</label>
                        <textarea x-model="form.description" rows="4" maxlength="5000" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent resize-none" placeholder="Origins, what a session involves, benefits…"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الوصف (بالعربية)</label>
                        <textarea x-model="form.description_ar" dir="rtl" rows="4" maxlength="5000" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                    </div>

                    {{-- AI image prompt + image --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">AI image prompt <span class="text-muted-foreground font-normal">(hero poster)</span></label>
                        <textarea x-model="form.image_prompt" rows="3" maxlength="4000" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary focus:border-transparent resize-none text-xs" placeholder="A cinematic, ultra-detailed hero poster…"></textarea>

                        <template x-if="editing && current && current.picture_src">
                            <img :src="current.picture_src" alt="" class="mt-2 w-full h-36 object-cover rounded-xl border border-gray-200">
                        </template>

                        <div x-show="editing" class="mt-2 flex flex-col gap-2">
                            <button type="button" @click="generateImageFromForm()" :disabled="imgBusy || !form.image_prompt.trim()"
                                    class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl text-sm font-medium border border-primary text-primary hover:bg-primary hover:text-white transition-colors disabled:opacity-50">
                                <i class="bi" :class="imgBusy ? 'bi-arrow-repeat animate-spin' : 'bi-image'"></i>
                                <span x-text="imgBusy ? 'Generating image…' : (current && current.picture_src ? 'Regenerate image with AI' : 'Generate image with AI')"></span>
                            </button>
                            {{-- Mobile: inline mode → upload/camera tiles render in-flow and the crop
                                 editor opens as a teleported bottom-sheet (mobile-first), instead of the
                                 desktop Bootstrap modal that collapses on a phone. --}}
                            <x-takeone-cropper
                                id="activityHeroCropperMobile"
                                mode="ajax"
                                :inline="true"
                                :width="1600"
                                :height="900"
                                shape="rectangle"
                                :canvasHeight="300"
                                folder="activity-catalog/uploads"
                                filename="hero"
                                :uploadUrl="route('admin.platform.activities.upload-image')"
                                sheetMaxWidth="100%"
                                sheetClass="rounded-t-3xl shadow-2xl bg-background"
                                saveText="Crop"
                                :showCancel="false"
                                :showControls="false"
                                :uploadAsIs="true"
                                uploadAsIsText="Upload" />
                        </div>
                        <p x-show="!editing" class="text-xs text-muted-foreground mt-1">Save the activity first, then generate or upload its image.</p>

                        <p x-show="imgError" x-cloak class="mt-2 text-xs text-red-600 flex items-start gap-1.5">
                            <i class="bi bi-exclamation-triangle-fill mt-0.5"></i><span x-text="imgError"></span>
                        </p>
                        <p x-show="imgOk" x-cloak class="mt-2 text-xs text-green-600 flex items-center gap-1.5">
                            <i class="bi bi-check-circle-fill"></i><span>Image saved and attached.</span>
                        </p>
                    </div>

                    {{-- Styles / federations --}}
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-medium text-gray-700">Styles / Federations</label>
                            <button type="button" @click="addVariant()" class="text-xs text-primary font-medium"><i class="bi bi-plus-lg"></i> Add style</button>
                        </div>
                        <p class="text-xs text-muted-foreground mb-2">e.g. Taekwondo → WTF/Kukkiwon, ITF. Leave empty if none.</p>
                        <div class="space-y-2">
                            <template x-for="(v, i) in form.variants" :key="i">
                                <div class="flex items-center gap-2">
                                    <input type="text" x-model="v.name" placeholder="Style (English)" maxlength="100" class="flex-1 min-w-0 px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <input type="text" x-model="v.name_ar" dir="rtl" placeholder="بالعربية" maxlength="100" class="flex-1 min-w-0 px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <button type="button" @click="form.variants.splice(i,1)" class="m-press w-9 h-9 rounded-xl grid place-items-center text-red-500 bg-red-50 flex-shrink-0"><i class="bi bi-x-lg"></i></button>
                                </div>
                            </template>
                            <p class="text-xs text-muted-foreground" x-show="!form.variants.length">No styles added.</p>
                        </div>
                    </div>

                    {{-- Active toggle --}}
                    <label class="flex items-center gap-3 cursor-pointer select-none bg-white rounded-xl border border-gray-100 px-3 py-3">
                        <button type="button" @click="form.is_active = !form.is_active"
                                class="w-11 h-6 rounded-full transition-colors flex-shrink-0 relative"
                                :class="form.is_active ? 'bg-primary' : 'bg-gray-300'">
                            <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform" :class="form.is_active && 'translate-x-5'"></span>
                        </button>
                        <span class="text-sm text-gray-700">Active — visible to clubs in the picker</span>
                    </label>
                </div>

                {{-- Sticky footer (safe-area aware) --}}
                <div class="flex-shrink-0 flex gap-3 px-4 pt-3 border-t border-border bg-white" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="modalOpen=false" class="flex-1 px-4 py-3 rounded-xl border border-border text-foreground hover:bg-muted/60 transition-colors text-sm font-medium">Cancel</button>
                    <button type="button" @click="save()" :disabled="saving" class="flex-1 bg-primary text-white px-4 py-3 rounded-xl hover:bg-primary/90 transition-colors font-medium text-sm inline-flex items-center justify-center gap-2 disabled:opacity-60">
                        <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>
                        <span x-text="editing ? 'Save changes' : 'Add activity'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

@push('scripts')
<script>
// The takeone cropper widget calls window.Toast.{success,error} — shim it onto
// the available window.showToast so the cropper's success hook isn't broken.
window.Toast = window.Toast || {
    success: (t, m) => window.showToast && window.showToast('success', m || t),
    error: (t, m) => window.showToast && window.showToast('error', m || t),
    info: (t, m) => window.showToast && window.showToast('info', m || t),
    warning: (t, m) => window.showToast && window.showToast('warning', m || t),
};

function activityDirectory(initial) {
    return {
        list: initial || [],
        search: '',
        modalOpen: false,
        editing: false,
        saving: false,
        aiBusy: false,
        imgBusy: false,
        imgError: '',
        imgOk: false,
        current: null,
        form: { name: '', name_ar: '', icon: '', description: '', description_ar: '', image_prompt: '', is_active: true, variants: [] },
        storeUrl: @js(route('admin.platform.activities.store')),
        generateUrl: @js(route('admin.platform.activities.generate')),
        csrf: document.querySelector('meta[name=csrf-token]').content,

        get filtered() {
            const q = this.search.trim().toLowerCase();
            if (!q) return this.list;
            return this.list.filter(a =>
                (a.name || '').toLowerCase().includes(q) ||
                (a.name_ar || '').includes(this.search.trim()) ||
                (a.variants || []).some(v => (v.name || '').toLowerCase().includes(q))
            );
        },
        blank() {
            return { name: '', name_ar: '', icon: '', description: '', description_ar: '', image_prompt: '', is_active: true, variants: [] };
        },
        openCreate() {
            this.editing = false;
            this.current = null;
            this.imgError = ''; this.imgOk = false;
            this.form = this.blank();
            this.modalOpen = true;
        },
        openEdit(a) {
            this.editing = true;
            this.current = a;
            this.imgError = ''; this.imgOk = false;
            const self = this;
            window.imageUploadSuccess = (res) => self.attachUploaded(res);
            this.form = {
                name: a.name || '',
                name_ar: a.name_ar || '',
                icon: a.icon || '',
                description: a.description || '',
                description_ar: a.description_ar || '',
                image_prompt: a.image_prompt || '',
                is_active: !!a.is_active,
                variants: (a.variants || []).map(v => ({ name: v.name || '', name_ar: v.name_ar || '' })),
            };
            this.modalOpen = true;
        },
        addVariant() { this.form.variants.push({ name: '', name_ar: '' }); },

        async generateContent() {
            if (this.aiBusy || !this.form.name.trim()) return;
            this.aiBusy = true;
            try {
                const res = await fetch(this.generateUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ name: this.form.name.trim(), style: (this.form.variants[0]?.name || '') }),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || 'Error');
                if (d.name_ar && !this.form.name_ar) this.form.name_ar = d.name_ar;
                this.form.description = d.description || this.form.description;
                this.form.description_ar = d.description_ar || this.form.description_ar;
                if (d.image_prompt) this.form.image_prompt = d.image_prompt;
                window.showToast && window.showToast('success', d.message || 'Draft generated.');
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            } finally {
                this.aiBusy = false;
            }
        },

        async attachUploaded(res) {
            if (!res || !res.path || !this.current) return;
            this.imgBusy = true;
            try {
                const r = await fetch(this.current.set_image_url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ path: res.path }),
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok || d.success === false) throw new Error(d.message || 'Could not attach the image.');
                if (d.activity) {
                    this.current = d.activity;
                    const i = this.list.findIndex(x => x.uuid === d.activity.uuid);
                    if (i !== -1) this.list[i] = d.activity;
                }
                this.imgOk = true;
                window.showToast && window.showToast('success', d.message || 'Image uploaded.');
            } catch (e) {
                this.imgError = e.message;
                window.showToast && window.showToast('error', e.message);
            } finally {
                this.imgBusy = false;
            }
        },

        async generateImageFromForm() {
            if (this.imgBusy) return;
            this.imgError = '';
            this.imgOk = false;
            if (!this.editing || !this.current) {
                this.imgError = 'Save the activity first, then generate its image.';
                return;
            }
            if (!(this.form.image_prompt || '').trim()) {
                this.imgError = 'Add an image prompt first (or use “Generate with AI”).';
                return;
            }
            this.imgBusy = true;
            try {
                const res = await fetch(this.current.image_url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ prompt: (this.form.image_prompt || '').trim() }),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || 'Image generation failed.');
                if (d.activity) {
                    this.current = d.activity;
                    const i = this.list.findIndex(x => x.uuid === d.activity.uuid);
                    if (i !== -1) this.list[i] = d.activity;
                }
                this.imgOk = true;
                window.showToast && window.showToast('success', d.message || 'Image generated.');
            } catch (e) {
                this.imgError = e.message;
                window.showToast && window.showToast('error', e.message);
            } finally {
                this.imgBusy = false;
            }
        },

        async generateImage(a) {
            if (a._imaging) return;
            a._imaging = true;
            try {
                const res = await fetch(a.image_url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || 'Error');
                if (d.activity) {
                    const i = this.list.findIndex(x => x.uuid === d.activity.uuid);
                    if (i !== -1) this.list[i] = d.activity;
                }
                window.showToast && window.showToast('success', d.message || 'Image generated.');
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            } finally {
                a._imaging = false;
            }
        },

        async save() {
            if (this.saving) return;
            if (!this.form.name.trim()) { window.showToast && window.showToast('error', 'Name is required.'); return; }
            this.saving = true;
            const url = this.editing ? this.current.update_url : this.storeUrl;
            const method = this.editing ? 'PUT' : 'POST';
            try {
                const res = await fetch(url, {
                    method,
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(this.form),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || 'Error');
                if (d.activity) {
                    const i = this.list.findIndex(x => x.uuid === d.activity.uuid);
                    if (i === -1) this.list.push(d.activity); else this.list[i] = d.activity;
                    this.list.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
                }
                this.modalOpen = false;
                window.showToast && window.showToast('success', d.message || 'Saved.');
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            } finally {
                this.saving = false;
            }
        },
        async remove(a) {
            const ok = await window.confirmAction({ title: 'Remove activity', message: `Remove “${a.name}” from the global directory? Clubs already using it keep their copy.`, confirmText: 'Remove', type: 'danger' });
            if (!ok) return;
            try {
                const res = await fetch(a.destroy_url, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || 'Error');
                this.list = this.list.filter(x => x.uuid !== a.uuid);
                window.showToast && window.showToast('success', d.message || 'Removed.');
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            }
        },
    };
}
</script>
@endpush
@endsection
