@extends('layouts.admin')

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

@section('admin-content')
<div class="space-y-6" x-data="activityDirectory(@js($activitiesJson))">

    <x-admin-hero title="Activities" eyebrow="Global directory" icon="bi-lightning-charge"
        :count="$activities->count()" countLabel="activities"
        subtitle="The shared catalog every club reuses instead of re-typing the same activity. Add disciplines, their styles/federations (e.g. Taekwondo → WTF/ITF), descriptions and icons.">
        <x-slot:actions>
            <button type="button" @click="openCreate()"
                class="bg-white text-primary px-4 py-2 rounded-lg font-medium hover:bg-white/90 transition-colors inline-flex items-center gap-2">
                <i class="bi bi-plus-lg"></i> Add activity
            </button>
        </x-slot:actions>
    </x-admin-hero>

    {{-- Search --}}
    <div class="relative max-w-md">
        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        <input type="text" x-model="search" placeholder="Search activities…"
               class="w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
    </div>

    <template x-if="filtered.length === 0">
        <div class="bg-white rounded-xl border border-dashed border-gray-200 p-8 text-center text-muted-foreground text-sm">
            No activities found.
        </div>
    </template>

    {{-- Directory grid --}}
    <div class="grid gap-5" style="grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));">
        <template x-for="a in filtered" :key="a.uuid">
            <div class="group relative bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col transition-all duration-200 hover:shadow-xl hover:-translate-y-1 hover:border-primary/20"
                 :class="!a.is_active && 'opacity-75'">

                {{-- Cover --}}
                <div class="relative aspect-[16/10] overflow-hidden bg-accent">
                    <template x-if="a.picture_src">
                        <img :src="a.picture_src" alt="" loading="lazy"
                             class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                    </template>
                    <template x-if="!a.picture_src">
                        <div class="w-full h-full grid place-items-center bg-gradient-to-br from-accent via-white to-accent/40">
                            <i class="bi text-5xl text-primary/40" :class="a.icon || 'bi-activity'"></i>
                        </div>
                    </template>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-black/5 to-transparent"></div>

                    {{-- Status pill --}}
                    <span class="absolute top-3 right-3 inline-flex items-center gap-1.5 text-[10px] font-semibold px-2.5 py-1 rounded-full backdrop-blur-md shadow-sm"
                          :class="a.is_active ? 'bg-green-500/90 text-white' : 'bg-gray-900/60 text-white'">
                        <span class="w-1.5 h-1.5 rounded-full" :class="a.is_active ? 'bg-white' : 'bg-gray-300'"></span>
                        <span x-text="a.is_active ? 'Active' : 'Hidden'"></span>
                    </span>

                    {{-- Usage --}}
                    <span class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 text-[11px] font-medium text-white bg-black/35 backdrop-blur-md px-2.5 py-1 rounded-full">
                        <i class="bi bi-people"></i><span x-text="a.usage_count"></span> clubs
                    </span>

                    {{-- Icon chip when a real image is present --}}
                    <template x-if="a.picture_src && a.icon">
                        <span class="absolute bottom-3 right-3 w-8 h-8 rounded-xl bg-white/90 backdrop-blur text-primary grid place-items-center shadow-sm">
                            <i class="bi" :class="a.icon"></i>
                        </span>
                    </template>
                </div>

                {{-- Body --}}
                <div class="p-4 flex flex-col flex-1">
                    <h3 class="font-bold text-[15px] text-gray-900 leading-tight truncate" x-text="a.name"></h3>
                    <p class="text-xs text-muted-foreground truncate mt-0.5" x-show="a.name_ar" dir="rtl" x-text="a.name_ar"></p>

                    {{-- Styles --}}
                    <div class="flex flex-wrap gap-1 mt-2.5" x-show="a.variants.length">
                        <template x-for="(v, i) in a.variants.slice(0, 4)" :key="i">
                            <span class="text-[10px] px-2 py-0.5 rounded-md bg-accent text-primary font-medium" x-text="v.name"></span>
                        </template>
                        <span x-show="a.variants.length > 4" class="text-[10px] px-2 py-0.5 rounded-md bg-muted/60 text-muted-foreground font-medium"
                              x-text="'+' + (a.variants.length - 4)"></span>
                    </div>

                    <p class="text-xs text-muted-foreground mt-2.5 line-clamp-2 leading-relaxed" x-show="a.description"
                       x-text="(a.description || '').replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim()"></p>
                </div>

                {{-- Action bar --}}
                <div class="flex items-stretch border-t border-gray-100 divide-x divide-gray-100 mt-auto">
                    <button type="button" x-show="a.has_prompt" @click="generateImage(a)" :disabled="a._imaging"
                            class="flex-1 py-2.5 inline-flex items-center justify-center gap-1.5 text-[12px] font-medium text-primary hover:bg-accent transition-colors disabled:opacity-50" title="Generate image">
                        <i class="bi" :class="a._imaging ? 'bi-arrow-repeat animate-spin' : 'bi-image'"></i><span>Image</span>
                    </button>
                    <a :href="'{{ url('/activity') }}/' + a.uuid" target="_blank"
                       class="flex-1 py-2.5 inline-flex items-center justify-center gap-1.5 text-[12px] font-medium text-foreground hover:bg-muted/60 transition-colors" title="View">
                        <i class="bi bi-eye"></i><span>View</span>
                    </a>
                    <button type="button" @click="openEdit(a)"
                            class="flex-1 py-2.5 inline-flex items-center justify-center gap-1.5 text-[12px] font-medium text-foreground hover:bg-muted/60 transition-colors" title="Edit">
                        <i class="bi bi-pencil"></i><span>Edit</span>
                    </button>
                    <button type="button" @click="remove(a)"
                            class="flex-1 py-2.5 inline-flex items-center justify-center gap-1.5 text-[12px] font-medium text-red-600 hover:bg-red-50 transition-colors" title="Delete">
                        <i class="bi bi-trash"></i><span>Delete</span>
                    </button>
                </div>
            </div>
        </template>
    </div>

    {{-- ===== Create / edit modal ===== --}}
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" @click="modalOpen=false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-xl max-h-[90vh] flex flex-col" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-border flex-shrink-0">
                <h3 class="text-lg font-bold text-foreground" x-text="editing ? 'Edit activity' : 'Add activity'"></h3>
                <div class="flex items-center gap-2">
                    <button type="button" @click="generateContent()" :disabled="aiBusy || !form.name.trim()"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium bg-accent text-primary hover:bg-primary hover:text-white transition-colors disabled:opacity-50"
                            title="Generate the full bilingual write-up + image prompt from the name">
                        <i class="bi" :class="aiBusy ? 'bi-arrow-repeat animate-spin' : 'bi-magic'"></i>
                        <span x-text="aiBusy ? 'Generating…' : 'Generate with AI'"></span>
                    </button>
                    <button @click="modalOpen=false" class="text-muted-foreground hover:text-foreground"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>

            <div class="px-6 py-5 overflow-y-auto space-y-4">
                {{-- Name EN / AR --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name (English) <span class="text-red-500">*</span></label>
                        <input type="text" x-model="form.name" maxlength="255" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">الاسم (بالعربية)</label>
                        <input type="text" x-model="form.name_ar" dir="rtl" maxlength="255" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                {{-- Icon --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Icon <span class="text-muted-foreground font-normal">(Bootstrap icon name, e.g. bi-person-arms-up)</span></label>
                    <div class="flex items-center gap-2">
                        <span class="w-10 h-10 rounded-lg bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi" :class="form.icon || 'bi-activity'"></i></span>
                        <input type="text" x-model="form.icon" placeholder="bi-activity" maxlength="50" class="flex-1 px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                    </div>
                </div>

                {{-- Description EN / AR --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description (English)</label>
                    <textarea x-model="form.description" rows="3" maxlength="5000" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent resize-none" placeholder="Origins, what a session involves, benefits…"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">الوصف (بالعربية)</label>
                    <textarea x-model="form.description_ar" dir="rtl" rows="3" maxlength="5000" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                </div>

                {{-- AI image prompt + generate --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">AI image prompt <span class="text-muted-foreground font-normal">(for generating the hero poster)</span></label>
                    <textarea x-model="form.image_prompt" rows="3" maxlength="4000" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent resize-none text-xs" placeholder="A cinematic, ultra-detailed hero poster…"></textarea>
                    <div class="mt-2 flex items-center gap-3">
                        <template x-if="editing && current && current.picture_src">
                            <img :src="current.picture_src" alt="" class="w-24 h-16 object-cover rounded-lg border border-gray-200 flex-shrink-0">
                        </template>
                        <div x-show="editing" class="flex items-center gap-2 flex-wrap">
                            <button type="button" @click="generateImageFromForm()" :disabled="imgBusy || !form.image_prompt.trim()"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium border border-primary text-primary hover:bg-primary hover:text-white transition-colors disabled:opacity-50">
                                <i class="bi" :class="imgBusy ? 'bi-arrow-repeat animate-spin' : 'bi-image'"></i>
                                <span x-text="imgBusy ? 'Generating image…' : (current && current.picture_src ? 'Regenerate with AI' : 'Generate with AI')"></span>
                            </button>
                            {{-- The cropper's own button opens its modal (the proven pattern). --}}
                            <x-takeone-cropper
                                id="activityHeroCropper"
                                mode="ajax"
                                :width="1600"
                                :height="900"
                                shape="rectangle"
                                :canvasHeight="520"
                                folder="activity-catalog/uploads"
                                filename="hero"
                                :uploadUrl="route('admin.platform.activities.upload-image')"
                                buttonText="Upload image"
                                buttonClass="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium border border-gray-200 text-foreground hover:bg-accent transition-colors"
                                :uploadAsIs="true"
                                uploadAsIsText="Upload without cropping" />
                        </div>
                        <p x-show="!editing" class="text-xs text-muted-foreground">Save the activity first, then you can generate or upload its image here.</p>
                    </div>
                    {{-- Inline status so the result is never invisible --}}
                    <p x-show="imgError" x-cloak class="mt-2 text-xs text-red-600 flex items-start gap-1.5">
                        <i class="bi bi-exclamation-triangle-fill mt-0.5"></i><span x-text="imgError"></span>
                    </p>
                    <p x-show="imgOk" x-cloak class="mt-2 text-xs text-green-600 flex items-center gap-1.5">
                        <i class="bi bi-check-circle-fill"></i><span>Image saved and attached.</span>
                    </p>
                </div>

                {{-- Styles / federations editor --}}
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-gray-700">Styles / Federations</label>
                        <button type="button" @click="addVariant()" class="text-xs text-primary font-medium hover:underline"><i class="bi bi-plus-lg"></i> Add style</button>
                    </div>
                    <p class="text-xs text-muted-foreground mb-2">e.g. Taekwondo → WTF/Kukkiwon, ITF. Leave empty if the activity has no sub-styles.</p>
                    <div class="space-y-2">
                        <template x-for="(v, i) in form.variants" :key="i">
                            <div class="flex items-center gap-2">
                                <input type="text" x-model="v.name" placeholder="Style (English)" maxlength="100" class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                                <input type="text" x-model="v.name_ar" dir="rtl" placeholder="بالعربية" maxlength="100" class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent">
                                <button type="button" @click="form.variants.splice(i,1)" class="w-8 h-8 rounded-lg grid place-items-center text-red-500 hover:bg-red-50 flex-shrink-0"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </template>
                        <p class="text-xs text-muted-foreground" x-show="!form.variants.length">No styles added.</p>
                    </div>
                </div>

                {{-- Active toggle --}}
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <button type="button" @click="form.is_active = !form.is_active"
                            class="w-11 h-6 rounded-full transition-colors flex-shrink-0 relative"
                            :class="form.is_active ? 'bg-primary' : 'bg-gray-300'">
                        <span class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform" :class="form.is_active && 'translate-x-5'"></span>
                    </button>
                    <span class="text-sm text-gray-700">Active — visible to clubs in the picker</span>
                </label>
            </div>

            <div class="flex justify-end gap-3 px-6 py-4 border-t border-border flex-shrink-0">
                <button type="button" @click="modalOpen=false" class="px-4 py-2 rounded-lg border border-border text-foreground hover:bg-muted/60 transition-colors text-sm font-medium">Cancel</button>
                <button type="button" @click="save()" :disabled="saving" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm inline-flex items-center gap-2 disabled:opacity-60">
                    <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>
                    <span x-text="editing ? 'Save changes' : 'Add activity'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// The takeone cropper widget calls window.Toast.{success,error} — which is only
// defined by the toast-notification component, not on this admin layout. Shim it
// onto the available window.showToast so the cropper's success hook isn't broken.
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
            // The takeone cropper calls this global after a successful upload —
            // route it to attach the image to the activity being edited.
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

        // AI agent — fill the full bilingual write-up + image prompt from the name.
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
