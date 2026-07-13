@extends('layouts.admin-club')

@section('club-admin-content')

@php
$perksJson = $perks->map(function($p) {
    return [
        'id'          => $p->id,
        'title'       => $p->title,
        'description' => $p->description ?? '',
        'badge'       => $p->badge,
        'image_path'  => $p->image_path ?? '',
        'icon'        => $p->icon,
        'bg_from'     => $p->bg_from,
        'bg_to'       => $p->bg_to,
        'perk_type'   => $p->perk_type,
        'perk_value'  => $p->perk_value ?? '',
        'status'      => $p->status,
        'sort_order'  => $p->sort_order,
        'translations_title_ar'       => data_get($p->translations, 'title.ar', ''),
        'translations_description_ar' => data_get($p->translations, 'description.ar', ''),
        'translations_badge_ar'       => data_get($p->translations, 'badge.ar', ''),
    ];
});
@endphp

<div x-data="perksAdmin()">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-foreground">{{ __('admin.club_perks_index_title') }}</h2>
            <p class="text-sm text-muted-foreground mt-0.5">{{ __('admin.club_perks_index_subtitle') }}</p>
        </div>
        <button @click="openAdd()" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i>{{ __('admin.club_perks_index_add_perk') }}
        </button>
    </div>

    {{-- Perks list --}}
    @if($perks->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-16">
                <i class="bi bi-gift text-muted-foreground" style="font-size:2.5rem;opacity:.3;"></i>
                <p class="mt-3 text-muted-foreground">{{ __('admin.club_perks_index_empty') }}</p>
                <button @click="openAdd()" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-lg me-2"></i>{{ __('admin.club_perks_index_add_perk') }}
                </button>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($perks as $perk)
            @php $isInactive = $perk->status === 'inactive'; @endphp
            <div class="card border-0 shadow-sm overflow-hidden {{ $isInactive ? 'opacity-60' : '' }}"
                 id="perk-{{ $perk->id }}">
                {{-- Card visual --}}
                <div class="relative" style="height:120px;">
                    @if($perk->image_path)
                        <img src="{{ asset('storage/' . $perk->image_path) }}"
                             class="w-full h-full object-cover" alt="{{ $perk->title }}">
                    @else
                        <div class="w-full h-full flex items-center justify-center"
                             style="background: linear-gradient(135deg, {{ $perk->bg_from }}, {{ $perk->bg_to }});">
                            <i class="bi {{ $perk->icon }} text-white" style="font-size:2.5rem;"></i>
                        </div>
                    @endif
                    {{-- Badge --}}
                    <span class="absolute top-2 start-2 text-xs font-extrabold px-2 py-1 rounded-full bg-white/90 text-gray-800">
                        {{ $perk->badge }}
                    </span>
                    {{-- Type badge --}}
                    <span class="absolute top-2 end-2 text-xs font-semibold px-2 py-1 rounded-full
                        {{ $perk->perk_type === 'qr' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                        {{ $perk->perk_type === 'qr' ? __('admin.club_perks_index_qr_code') : __('admin.club_perks_index_promo_code') }}
                    </span>
                </div>
                {{-- Card body --}}
                <div class="card-body p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-semibold text-foreground truncate">{{ $perk->title }}</div>
                            @if($perk->description)
                            <div class="text-xs text-muted-foreground mt-0.5 truncate">{{ $perk->description }}</div>
                            @endif
                            <div class="text-xs text-muted-foreground mt-1">
                                <span class="font-mono bg-muted/40 px-1.5 py-0.5 rounded">
                                    {{ $perk->perk_type === 'qr' ? Str::limit($perk->perk_value, 30) : $perk->perk_value }}
                                </span>
                            </div>
                        </div>
                        <div class="flex gap-1.5 flex-shrink-0">
                            <button @click="openEdit({{ $perk->id }})"
                                    class="btn btn-sm btn-outline-secondary" title="{{ __('shared.edit') }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button @click="deletePerk({{ $perk->id }})"
                                    class="btn btn-sm btn-outline-danger" title="{{ __('shared.delete') }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    @if($isInactive)
                    <span class="badge bg-gray-100 text-gray-500 text-xs mt-2">{{ __('admin.club_perks_index_inactive') }}</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    @endif

    {{-- ===== SINGLE MODAL (Add & Edit) ===== --}}
    <div x-show="showModal" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="modal-content border-0 shadow-lg w-full max-w-2xl relative" @click.stop>
                <div class="modal-header border-b border-border px-6 py-4">
                    <h5 class="modal-title text-lg font-semibold" x-text="isEdit ? '{{ __('admin.club_perks_index_edit_perk') }}' : '{{ __('admin.club_perks_index_add_perk') }}'"></h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="showModal = false">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form :action="formAction" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="_method" :value="isEdit ? 'PUT' : 'POST'">
                    <div class="modal-body px-6 py-4 max-h-[70vh] overflow-y-auto">
                        @include('admin.club.perks.partials.form-fields')
                    </div>
                    <div class="modal-footer border-t border-border px-6 py-4 flex justify-end gap-3">
                        <button type="button" class="btn btn-outline-secondary" @click="showModal = false">{{ __('shared.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>
                            <span x-text="isEdit ? '{{ __('admin.club_perks_index_update_perk') }}' : '{{ __('admin.club_perks_index_save_perk') }}'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
const perksData   = @json($perksJson);
const storeUrl    = '{{ route('admin.club.perks.store', $club->slug) }}';
const baseEditUrl = '{{ url('admin/club/' . $club->slug . '/perks') }}';

const emptyForm = {
    title: '', description: '', badge: '',
    image_path: '', remove_image: false,
    icon: 'bi-gift', bg_from: '#f59e0b', bg_to: '#f97316',
    perk_type: 'code', perk_value: '',
    status: 'active', sort_order: 0,
    translations_title_ar: '', translations_description_ar: '', translations_badge_ar: '',
};

function perksAdmin() {
    return {
        lang:       'en',
        showModal:  false,
        isEdit:     false,
        formAction: storeUrl,
        formData:   { ...emptyForm },

        openAdd() {
            this.isEdit     = false;
            this.formAction = storeUrl;
            this.formData   = { ...emptyForm };
            this.showModal  = true;
        },

        openEdit(id) {
            const p = perksData.find(p => p.id === id);
            if (!p) return;
            this.isEdit     = true;
            this.formAction = baseEditUrl + '/' + id;
            this.formData   = { ...emptyForm, ...p, remove_image: false };
            this.showModal  = true;
        },

        deletePerk(id) {
            confirmAction({
                title:       '{{ __('admin.club_perks_index_delete_perk_title') }}',
                message:     '{{ __('admin.club_perks_index_delete_perk_confirm') }}',
                confirmText: '{{ __('shared.delete') }}',
                type:        'danger',
            }).then(confirmed => {
                if (!confirmed) return;
                fetch(baseEditUrl + '/' + id, {
                    method:  'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept':       'application/json',
                    },
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('perk-' + id)?.remove();
                        window.showToast('success', data.message || '{{ __('admin.club_perks_index_deleted') }}');
                    } else {
                        window.showToast('error', data.message || '{{ __('admin.club_perks_index_delete_failed') }}');
                    }
                })
                .catch(() => window.showToast('error', '{{ __('admin.club_perks_index_delete_failed') }}'));
            });
        },
    };
}
</script>
@endpush
@endsection
