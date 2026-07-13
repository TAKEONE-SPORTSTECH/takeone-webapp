@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_activities'))

@section('club-admin-content')
@php
    $actFacilities = $activities->pluck('facility_id')->filter()->unique()->count();
    $activitiesJson = $activities->map(fn ($a) => [
        'id' => $a->id,
        'name' => $a->name,
        'description' => $a->description,
        'notes' => $a->notes,
        'duration_minutes' => $a->duration_minutes,
        'picture_src' => $a->picture_url ? asset('storage/'.$a->picture_url) : null,
        'facility' => $a->facility ? ['id' => $a->facility->id, 'name' => $a->facility->name] : null,
    ])->values();
@endphp
<div class="-mx-4 -mt-4"
     x-data="activitiesAdmin({{ Illuminate\Support\Js::from($activitiesJson) }}, {
        storeUrl: '{{ route('admin.club.activities.store', $club->slug) }}',
        base: '{{ url('admin/club/'.$club->slug.'/activities') }}',
        shopUrl: '{{ url('admin/club/'.$club->slug.'/shop') }}',
        cur: '{{ $club->currency ?: 'BHD' }}',
        csrf: '{{ csrf_token() }}'
     })">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70 truncate">{{ $club->club_name ?? __('admin.club') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('admin.nav_activities') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="openAdd()"
                        class="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('admin.club_activities_index_add_activity') }}">
                    <i class="bi bi-plus-lg text-xl"></i>
                </button>
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                    <i class="bi bi-activity text-xl m-float"></i>
                </div>
            </div>
        </div>

        <div class="flex gap-2 mt-5 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none tabular-nums" x-text="list.length">{{ $activities->count() }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('admin.nav_activities') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none tabular-nums">{{ $actFacilities }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('admin.nav_facilities') }}</p>
            </div>
        </div>
    </header>

    <div class="px-4 pt-5 space-y-4">

        {{-- Empty state --}}
        <template x-if="list.length === 0">
            <div class="m-card p-8 text-center">
                <i class="bi bi-activity text-3xl text-gray-300 m-float"></i>
                <p class="text-sm text-muted-foreground mt-2">{{ __('admin.act_none_yet') }}</p>
                <button type="button" @click="openAdd()"
                        class="m-press mt-4 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-medium">
                    <i class="bi bi-plus-lg"></i>{{ __('admin.club_activities_index_add_activity') }}
                </button>
            </div>
        </template>

        {{-- Activity cards (two per row) --}}
        <div class="grid grid-cols-2 gap-3 mobile-stagger" x-show="list.length">
            <template x-for="a in list" :key="a.id">
                <div class="m-card overflow-hidden flex flex-col">
                    {{-- Thumbnail (image or icon fallback) --}}
                    <div class="relative aspect-[4/3] shrink-0">
                        <template x-if="a.picture_src">
                            <img :src="a.picture_src" alt="" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!a.picture_src">
                            <div class="w-full h-full grid place-items-center bg-accent/40"><i class="bi bi-activity text-2xl text-primary/50"></i></div>
                        </template>
                    </div>
                    <div class="p-3 flex flex-col flex-1">
                        <h3 class="font-semibold text-foreground text-[13px] leading-tight line-clamp-2" x-text="a.name"></h3>
                        <p class="text-[11px] text-muted-foreground mt-1 flex items-center gap-1 truncate" x-show="a.facility">
                            <i class="bi bi-geo-alt shrink-0"></i><span class="truncate" x-text="a.facility?.name"></span>
                        </p>
                        <p class="text-[11px] text-muted-foreground mt-0.5 flex items-center gap-1" x-show="a.duration_minutes">
                            <i class="bi bi-clock shrink-0"></i><span x-text="a.duration_minutes + ' {{ __('admin.club_activities_index_min') }}'"></span>
                        </p>
                        {{-- Action buttons: below the content so they never cover the image --}}
                        <div class="flex items-center gap-1.5 mt-3 pt-3 border-t border-gray-100">
                            <button type="button" @click="openEquip(a)" class="m-press flex-1 h-8 rounded-lg grid place-items-center bg-muted text-gray-600 hover:text-primary transition-colors" aria-label="{{ __('admin.club_activities_index_equipment') }}"><i class="bi bi-box-seam text-sm"></i></button>
                            <button type="button" @click="openEdit(a)" class="m-press flex-1 h-8 rounded-lg grid place-items-center bg-muted text-gray-600 hover:text-primary transition-colors" aria-label="{{ __('shared.edit') }}"><i class="bi bi-pencil text-sm"></i></button>
                            <button type="button" @click="deleteActivity(a)" class="m-press flex-1 h-8 rounded-lg grid place-items-center bg-muted text-gray-600 hover:text-red-600 transition-colors" aria-label="{{ __('shared.delete') }}"><i class="bi bi-trash text-sm"></i></button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>{{-- /content --}}

    {{-- ===== Add / edit bottom-sheet (teleported so the fixed overlay escapes the shell transform) ===== --}}
    <template x-teleport="body">
        <div x-show="sheetOpen" x-cloak class="fixed inset-0 z-[80] flex items-end" style="display:none;" @keydown.escape.window="sheetOpen = false">
            <div class="absolute inset-0 bg-black/40" @click="sheetOpen = false"></div>
            <div x-show="sheetOpen"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="relative w-full bg-white rounded-t-3xl max-h-[92vh] flex flex-col overflow-hidden">
                {{-- Header --}}
                <div class="flex-shrink-0 px-5 pt-3">
                    <div class="w-10 h-1 rounded-full bg-gray-300 mx-auto mb-3"></div>
                    <div class="flex items-center justify-between pb-3">
                        <h3 class="text-base font-bold text-gray-900" x-text="editingId ? '{{ __('admin.club_activities_index_edit_activity') }}' : '{{ __('admin.club_activities_index_add_activity') }}'"></h3>
                        <button type="button" @click="sheetOpen = false" class="w-8 h-8 rounded-full grid place-items-center text-gray-500 hover:bg-muted"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>

                {{-- Scrollable body --}}
                <div class="flex-1 overflow-y-auto px-5 pb-2 space-y-4">
                    {{-- Picture --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.components_activity_modal_activity_picture') }}</label>
                        <input type="file" x-ref="photo" accept="image/*" class="hidden" @change="pickPhoto($event)">
                        <button type="button" @click="$refs.photo.click()"
                                class="relative w-full h-40 rounded-2xl border-2 border-dashed border-gray-200 grid place-items-center overflow-hidden hover:border-primary transition-colors bg-muted/40">
                            <template x-if="form.pictureSrc">
                                <img :src="form.pictureSrc" alt="" class="absolute inset-0 w-full h-full object-cover">
                            </template>
                            <template x-if="!form.pictureSrc">
                                <span class="flex flex-col items-center gap-1 text-muted-foreground">
                                    <i class="bi bi-image text-2xl"></i>
                                    <span class="text-xs">{{ __('shared.components_activity_modal_upload_picture') }}</span>
                                </span>
                            </template>
                        </button>
                        <button type="button" x-show="form.pictureSrc" @click="form.picture = null; form.pictureSrc = null; form.removePicture = true"
                                class="mt-1.5 text-xs text-red-600 hover:underline">{{ __('admin.club_activities_index_remove') }}</button>
                    </div>

                    {{-- Name --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.components_activity_modal_activity_title') }} <span class="text-red-500">*</span></label>
                        <input type="text" x-model="form.name" maxlength="255" @input="errors.name = ''"
                               class="w-full px-4 py-2.5 border rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                               :class="errors.name ? 'border-red-400 ring-2 ring-red-100' : 'border-gray-200'"
                               placeholder="{{ __('shared.components_activity_modal_name_placeholder') }}">
                        <span x-show="errors.name" x-text="errors.name" class="text-red-500 text-xs mt-1 block"></span>
                    </div>

                    {{-- Duration --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('admin.club_activities_index_duration_minutes') }}</label>
                        <input type="number" inputmode="numeric" min="1" max="1440" x-model="form.duration_minutes"
                               class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                               placeholder="60">
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.components_activity_modal_description') }}</label>
                        <textarea x-model="form.description" rows="3" maxlength="2000"
                                  class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm resize-none"
                                  placeholder="{{ __('shared.components_activity_modal_description_placeholder') }}"></textarea>
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.components_activity_modal_additional_notes') }}</label>
                        <textarea x-model="form.notes" rows="2" maxlength="1000"
                                  class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm resize-none"
                                  placeholder="{{ __('shared.components_activity_modal_notes_placeholder') }}"></textarea>
                    </div>
                </div>

                {{-- Sticky footer --}}
                <div class="flex-shrink-0 border-t border-gray-100 bg-white px-5 pt-3 flex gap-2"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="sheetOpen = false"
                            class="m-press flex-1 py-3 rounded-2xl border border-gray-200 text-sm font-semibold text-muted-foreground">{{ __('shared.cancel') }}</button>
                    <button type="button" @click="save()" :disabled="saving"
                            class="m-press flex-1 py-3 rounded-2xl bg-primary text-white text-sm font-semibold flex items-center justify-center gap-2 disabled:opacity-50">
                        <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>
                        <span x-text="editingId ? '{{ __('shared.save') }}' : '{{ __('admin.club_activities_index_add_activity') }}'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ===== Equipment manager bottom-sheet (gear a member needs for the activity) ===== --}}
    <template x-teleport="body">
        <div x-show="equipOpen" x-cloak class="fixed inset-0 z-[80] flex items-end" style="display:none;" @keydown.escape.window="equipOpen = false">
            <div class="absolute inset-0 bg-black/40" @click="equipOpen = false"></div>
            <div x-show="equipOpen"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="relative w-full bg-white rounded-t-3xl max-h-[92vh] flex flex-col overflow-hidden">
                {{-- Header --}}
                <div class="flex-shrink-0 px-5 pt-3">
                    <div class="w-10 h-1 rounded-full bg-gray-300 mx-auto mb-3"></div>
                    <div class="flex items-center justify-between pb-3">
                        <div class="min-w-0">
                            <h3 class="text-base font-bold text-gray-900">{{ __('shared.activity_equipment_modal_title') }}</h3>
                            <p class="text-xs text-muted-foreground mt-0.5 truncate">{{ __('shared.activity_equipment_modal_subtitle') }} <span class="font-medium text-foreground" x-text="equipActivityName"></span></p>
                        </div>
                        <button type="button" @click="equipOpen = false" class="w-8 h-8 rounded-full grid place-items-center text-gray-500 hover:bg-muted flex-shrink-0"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>

                {{-- Scrollable body --}}
                <div class="flex-1 overflow-y-auto px-5 pb-2 space-y-5">
                    <div x-show="equipLoading" class="text-center py-10 text-muted-foreground">
                        <i class="bi bi-arrow-repeat animate-spin text-2xl"></i>
                    </div>

                    {{-- No shop products at all --}}
                    <div x-show="!equipLoading && equipProducts.length === 0" class="text-center py-10">
                        <i class="bi bi-shop text-muted-foreground text-4xl"></i>
                        <p class="text-sm text-muted-foreground mt-2">{{ __('shared.activity_equipment_modal_no_products') }}</p>
                        <a :href="shopUrl" class="inline-block mt-3 text-primary text-sm font-medium hover:underline">
                            <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('shared.activity_equipment_modal_open_shop') }}
                        </a>
                    </div>

                    {{-- Linked gear --}}
                    <div x-show="!equipLoading && equipItems.length > 0">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">{{ __('shared.activity_equipment_modal_linked_gear') }}</p>
                        <div class="space-y-2">
                            <template x-for="item in equipItems" :key="item.id">
                                <div class="flex items-center gap-3 p-2.5 rounded-xl border border-gray-100 bg-card">
                                    <div class="w-10 h-10 rounded-lg bg-accent flex items-center justify-center flex-shrink-0 overflow-hidden">
                                        <template x-if="item.image"><img :src="item.image" alt="" class="w-full h-full object-cover"></template>
                                        <template x-if="!item.image"><i class="bi bi-box-seam text-primary"></i></template>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-foreground truncate" x-text="item.name || '{{ __('shared.activity_equipment_modal_product_removed') }}'"></span>
                                            <span x-show="!item.is_active" class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-muted text-muted-foreground flex-shrink-0">{{ __('shared.activity_equipment_modal_hidden') }}</span>
                                        </div>
                                        <span class="text-xs text-muted-foreground" x-text="cur + ' ' + item.price.toFixed(2)"></span>
                                    </div>
                                    <button type="button" @click="equipToggleRequired(item)"
                                            class="m-press px-2.5 py-1 rounded-full text-[11px] font-medium border transition-colors flex-shrink-0 flex items-center gap-1"
                                            :class="item.is_required ? 'border-primary bg-primary/10 text-primary' : 'border-gray-200 text-gray-400'">
                                        <i class="bi" :class="item.is_required ? 'bi-check-circle-fill' : 'bi-circle'"></i>
                                        <span x-text="item.is_required ? '{{ __('shared.activity_equipment_modal_required') }}' : '{{ __('shared.activity_equipment_modal_optional') }}'"></span>
                                    </button>
                                    <button type="button" @click="equipRemove(item)" class="m-press w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-red-50 hover:text-red-600 transition-colors flex-shrink-0">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Add from shop --}}
                    <div x-show="!equipLoading && equipAvailableProducts.length > 0">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">{{ __('shared.activity_equipment_modal_add_from_shop') }}</p>
                        <div class="grid grid-cols-2 gap-2.5">
                            <template x-for="p in equipAvailableProducts" :key="p.id">
                                <button type="button" @click="equipSelectedProductId = (equipSelectedProductId === p.id ? null : p.id)"
                                        class="relative text-start rounded-xl border-2 overflow-hidden transition-all"
                                        :class="equipSelectedProductId === p.id ? 'border-primary ring-2 ring-primary/20' : 'border-gray-100'">
                                    <span x-show="equipSelectedProductId === p.id"
                                          class="absolute top-1.5 end-1.5 w-5 h-5 rounded-full bg-primary text-white flex items-center justify-center shadow-sm z-10">
                                        <i class="bi bi-check-lg text-xs"></i>
                                    </span>
                                    <div class="h-20 bg-accent/50 flex items-center justify-center overflow-hidden">
                                        <template x-if="p.image"><img :src="p.image" alt="" class="w-full h-full object-cover"></template>
                                        <template x-if="!p.image"><i class="bi bi-box-seam text-primary text-2xl"></i></template>
                                    </div>
                                    <div class="p-2">
                                        <p class="text-xs font-medium text-foreground truncate" x-text="p.name"></p>
                                        <p class="text-[11px] text-primary font-semibold mt-0.5" x-text="cur + ' ' + p.price.toFixed(2)"></p>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div x-show="!equipLoading && equipProducts.length > 0 && equipAvailableProducts.length === 0 && equipItems.length > 0" class="text-center py-2">
                        <p class="text-xs text-muted-foreground">{{ __('shared.activity_equipment_modal_all_linked') }}</p>
                    </div>
                </div>

                {{-- Sticky footer: required toggle + add selected --}}
                <div class="flex-shrink-0 border-t border-gray-100 bg-white px-5 pt-3 flex items-center justify-between gap-3"
                     x-show="!equipLoading && equipAvailableProducts.length > 0"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" x-model="equipNewRequired" class="rounded border-gray-300 text-primary focus:ring-purple-500">
                        <span class="text-xs text-gray-700">{{ __('shared.activity_equipment_modal_required_at_registration') }}</span>
                    </label>
                    <button type="button" @click="equipAdd()" :disabled="equipSaving || !equipSelectedProductId"
                            class="m-press bg-primary text-white px-5 py-2.5 rounded-2xl hover:bg-primary/90 transition-colors font-semibold text-sm disabled:opacity-40 flex items-center gap-1.5">
                        <i class="bi" :class="equipSaving ? 'bi-arrow-repeat animate-spin' : 'bi-plus-lg'"></i>
                        <span x-text="equipSelectedProductId ? '{{ __('shared.activity_equipment_modal_add_selected') }}' : '{{ __('shared.activity_equipment_modal_select_product') }}'"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

{{-- Inline (inside #shell-content) so the mobile admin navigator re-runs it on
     in-place nav — @push('scripts')/#shell-scripts is NOT re-run by the mobile shell. --}}
@once
<script>
window.activitiesAdmin = function (list, opts) {
    return {
        list: list,
        storeUrl: opts.storeUrl,
        base: opts.base,
        shopUrl: opts.shopUrl,
        cur: opts.cur || 'BHD',
        csrf: opts.csrf,
        sheetOpen: false,
        editingId: null,
        saving: false,
        errors: {},
        form: { name: '', duration_minutes: '', description: '', notes: '', picture: null, pictureSrc: null, removePicture: false },

        // --- Equipment manager state ---
        equipOpen: false,
        equipLoading: false,
        equipSaving: false,
        equipActivityId: null,
        equipActivityName: '',
        equipItems: [],
        equipProducts: [],
        equipSelectedProductId: null,
        equipNewRequired: true,

        blankForm() { return { name: '', duration_minutes: '', description: '', notes: '', picture: null, pictureSrc: null, removePicture: false }; },

        openAdd() {
            this.editingId = null;
            this.errors = {};
            this.form = this.blankForm();
            this.sheetOpen = true;
        },
        openEdit(a) {
            this.editingId = a.id;
            this.errors = {};
            this.form = {
                name: a.name || '',
                duration_minutes: a.duration_minutes || '',
                description: a.description || '',
                notes: a.notes || '',
                picture: null,                 // only set when a NEW file is picked
                pictureSrc: a.picture_src || null,
                removePicture: false,
            };
            this.sheetOpen = true;
        },

        async pickPhoto(e) {
            const f = (e.target.files || [])[0];
            e.target.value = '';
            if (!f || !f.type.startsWith('image/')) return;
            let file = f;
            try {
                if (window.imageCompression && f.size > 400 * 1024) {
                    file = await window.imageCompression(f, { maxSizeMB: 0.7, maxWidthOrHeight: 1600, useWebWorker: true });
                }
            } catch (_) { file = f; }
            const r = new FileReader();
            r.onload = () => { this.form.picture = r.result; this.form.pictureSrc = r.result; this.form.removePicture = false; };
            r.readAsDataURL(file);
        },

        async save() {
            if (this.saving) return;
            if (!this.form.name.trim()) { this.errors = { name: @js(__('validation.required', ['attribute' => 'name'])) }; return; }
            this.saving = true;
            const body = {
                name: this.form.name.trim(),
                duration_minutes: this.form.duration_minutes || null,
                description: this.form.description || '',
                notes: this.form.notes || '',
            };
            // Only send a picture when the user picked a new one.
            if (this.form.picture && this.form.picture.startsWith('data:image')) body.picture = this.form.picture;

            const url = this.editingId ? `${this.base}/${this.editingId}` : this.storeUrl;
            const method = this.editingId ? 'PUT' : 'POST';
            try {
                const res = await fetch(url, {
                    method,
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'same-origin', body: JSON.stringify(body),
                });
                const d = await res.json().catch(() => ({}));
                if (res.status === 422 && d.errors) { this.errors = Object.fromEntries(Object.entries(d.errors).map(([k, v]) => [k, v[0]])); throw new Error(d.message || @js(__('shared.error'))); }
                if (!res.ok || d.success === false) throw new Error(d.message || @js(__('shared.error')));
                if (d.activity) {
                    const i = this.list.findIndex(x => x.id === d.activity.id);
                    if (i === -1) this.list.unshift(d.activity); else this.list[i] = d.activity;
                }
                this.sheetOpen = false;
                window.showToast && window.showToast('success', d.message || @js(__("shared.done")));
            } catch (e) {
                if (!this.errors.name) window.showToast && window.showToast('error', e.message);
            } finally { this.saving = false; }
        },

        async deleteActivity(a) {
            const ok = await window.confirmAction({ title: @js(__('admin.club_activities_index_delete_activity')), message: @js(__('admin.club_activities_index_delete_confirm')), type: 'danger', confirmText: @js(__('shared.delete')) });
            if (!ok) return;
            try {
                const res = await fetch(`${this.base}/${a.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json' }, credentials: 'same-origin' });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || @js(__('shared.error')));
                this.list = this.list.filter(x => x.id !== a.id);
                window.showToast && window.showToast('success', d.message || @js(__('shared.deleted')));
            } catch (e) { window.showToast && window.showToast('error', e.message); }
        },

        // --- Equipment manager (gear required to practice the activity) ---
        equipBase() { return `${this.base}/${this.equipActivityId}/equipment`; },

        get equipAvailableProducts() {
            const linked = this.equipItems.map(i => i.product_id);
            return this.equipProducts.filter(p => !linked.includes(p.id));
        },

        openEquip(a) {
            this.equipActivityId = a.id;
            this.equipActivityName = a.name || '';
            this.equipSelectedProductId = null;
            this.equipNewRequired = true;
            this.equipItems = [];
            this.equipProducts = [];
            this.equipOpen = true;
            this.loadEquip();
        },

        async loadEquip() {
            this.equipLoading = true;
            try {
                const res = await fetch(this.equipBase(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                const d = await res.json().catch(() => ({}));
                if (d.success) { this.equipItems = d.equipment || []; this.equipProducts = d.products || []; }
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('shared.error')));
            } finally { this.equipLoading = false; }
        },

        async equipAdd() {
            if (!this.equipSelectedProductId || this.equipSaving) return;
            this.equipSaving = true;
            try {
                const res = await fetch(this.equipBase(), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    credentials: 'same-origin',
                    body: JSON.stringify({ club_product_id: this.equipSelectedProductId, is_required: this.equipNewRequired ? 1 : 0, is_active: 1 }),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || !d.success) throw new Error(d.message || @js(__('shared.error')));
                const i = this.equipItems.findIndex(x => x.id === d.equipment.id);
                if (i !== -1) this.equipItems.splice(i, 1, d.equipment); else this.equipItems.push(d.equipment);
                this.equipSelectedProductId = null;
                this.equipNewRequired = true;
                window.showToast && window.showToast('success', d.message);
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            } finally { this.equipSaving = false; }
        },

        async equipToggleRequired(item) {
            const next = !item.is_required;
            try {
                const res = await fetch(`${this.equipBase()}/${item.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf },
                    credentials: 'same-origin',
                    body: JSON.stringify({ is_required: next ? 1 : 0, is_active: item.is_active ? 1 : 0 }),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || !d.success) throw new Error(d.message || @js(__('shared.error')));
                const i = this.equipItems.findIndex(x => x.id === item.id);
                if (i !== -1) this.equipItems.splice(i, 1, d.equipment);
            } catch (e) { window.showToast && window.showToast('error', e.message); }
        },

        async equipRemove(item) {
            const ok = await window.confirmAction({
                title: @js(__('shared.activity_equipment_modal_remove_title')),
                message: @js(__('shared.activity_equipment_modal_unlink_confirm')).replace(':name', item.name || ''),
                confirmText: @js(__('shared.activity_equipment_modal_remove')), type: 'danger',
            });
            if (!ok) return;
            try {
                const res = await fetch(`${this.equipBase()}/${item.id}`, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf }, credentials: 'same-origin' });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || !d.success) throw new Error(d.message || @js(__('shared.error')));
                this.equipItems = this.equipItems.filter(x => x.id !== item.id);
                window.showToast && window.showToast('success', d.message);
            } catch (e) { window.showToast && window.showToast('error', e.message); }
        },
    };
};
</script>
@endonce
@endsection
