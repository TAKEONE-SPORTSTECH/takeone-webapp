@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_perks'))

@section('club-admin-content')
<div class="-mx-4 -mt-4"
     x-data="{ removePerkId: null, removePerkName: '' }"
     @perk-removed.window="removePerkId = null; removePerkName = ''">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70 truncate">{{ $club->club_name ?? __('admin.club') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('admin.nav_perks') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="$dispatch('open-add-perk')"
                        class="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('admin.perk_add') }}">
                    <i class="bi bi-plus-lg text-xl"></i>
                </button>
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                    <i class="bi bi-gift text-xl m-float"></i>
                </div>
            </div>
        </div>
    </header>

    <div class="px-4 pt-5">

    <div id="perksList" class="space-y-4 mobile-stagger">
        @foreach($perks as $perk)
            <div class="m-card p-4 {{ ($perk->status ?? '') === 'active' ? '' : 'opacity-60' }}" id="perk-{{ $perk->id }}" x-data="{ openMenu: false }">
                <div class="flex items-start gap-3">
                    <span class="w-11 h-11 rounded-xl flex items-center justify-center text-white flex-shrink-0"
                          style="background: linear-gradient(135deg, {{ $perk->bg_from ?? 'hsl(250 65% 60%)' }}, {{ $perk->bg_to ?? 'hsl(250 65% 65%)' }});">
                        <i class="bi {{ $perk->icon ?? 'bi-gift' }} text-lg"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-semibold text-foreground truncate">{{ $perk->title }}</h3>
                            <div class="flex items-center gap-1.5 flex-shrink-0" @click.stop>
                                @if(($perk->status ?? '') !== 'active')<span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500">{{ __('admin.perk_inactive') }}</span>@endif
                                <div class="relative">
                                    <button type="button" @click="openMenu = !openMenu" class="m-press w-8 h-8 -mr-1 rounded-full flex items-center justify-center text-muted-foreground" aria-label="{{ __('admin.actions') }}"><i class="bi bi-three-dots-vertical"></i></button>
                                    <div x-show="openMenu" x-cloak @click.outside="openMenu = false" class="absolute right-0 mt-1 w-44 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-20">
                                        <button type="button" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3" @click="openMenu = false; $dispatch('open-edit-perk', { id: {{ $perk->id }} })"><span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0"><i class="bi bi-pencil text-blue-600 text-xs"></i></span><span class="font-medium">{{ __('admin.perk_edit') }}</span></button>
                                        <button type="button" class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3" @click="openMenu = false; removePerkId = {{ $perk->id }}; removePerkName = (window.perksData[{{ $perk->id }}] || {}).title || ''"><span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center shrink-0"><i class="bi bi-trash text-red-600 text-xs"></i></span><span class="font-medium">{{ __('admin.perk_delete') }}</span></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if($perk->badge)<span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">{{ $perk->badge }}</span>@endif
                        @if($perk->description)<p class="text-xs text-muted-foreground mt-1.5 line-clamp-2">{{ $perk->description }}</p>@endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Empty state (shown when no perks; hidden once the list has cards) --}}
    <div id="perksEmpty" class="{{ $perks->isEmpty() ? '' : 'hidden' }}">
        <div class="m-card p-8 text-center">
            <i class="bi bi-gift text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">{{ __('admin.perk_no_perks') }}</p>
            <button type="button" @click="$dispatch('open-add-perk')" class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-white text-sm font-semibold"><i class="bi bi-plus-lg"></i>{{ __('admin.perk_add') }}</button>
        </div>
    </div>

    {{-- Delete confirm (teleported to body to escape the transformed `.mobile-stagger` container) --}}
    <template x-teleport="body">
    <div x-show="removePerkId !== null" x-cloak class="fixed inset-0 z-[60] overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="removePerkId = null"></div>
        <div class="relative flex min-h-full items-center justify-center p-4 z-10">
            <div class="bg-white w-full max-w-sm relative rounded-2xl overflow-hidden shadow-xl" @click.stop>
                <div class="flex items-center justify-between border-b border-red-100 px-5 py-4">
                    <h5 class="text-destructive font-semibold flex items-center"><i class="bi bi-trash mr-2"></i>{{ __('admin.perk_delete') }}</h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="removePerkId = null"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="px-5 py-4">
                    <p class="mb-1 text-sm">{{ __('admin.perk_delete_confirm') }}</p>
                    <p class="font-semibold" x-text="removePerkName"></p>
                </div>
                <div class="border-t px-5 py-4 flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 bg-white" @click="removePerkId = null">{{ __('admin.cancel') }}</button>
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl bg-destructive text-white flex items-center gap-1" @click="removePerk(removePerkId)"><i class="bi bi-trash"></i>{{ __('admin.perk_delete') }}</button>
                </div>
            </div>
        </div>
    </div>
    </template>

    @include('admin.club.perks.mobile-form')

    {{-- Edit payloads + delete handler. Inline (inside #shell-content) so it re-runs
         after an in-shell AJAX swap. --}}
    @php
        $perksData = $perks->mapWithKeys(fn ($p) => [$p->id => [
            'id'             => $p->id,
            'title'          => $p->title,
            'title_ar'       => data_get($p->translations, 'title.ar', ''),
            'description'    => $p->description,
            'description_ar' => data_get($p->translations, 'description.ar', ''),
            'badge'          => $p->badge,
            'badge_ar'       => data_get($p->translations, 'badge.ar', ''),
            'icon'           => $p->icon ?: 'bi-gift',
            'bg_from'        => $p->bg_from ?: '#f59e0b',
            'bg_to'          => $p->bg_to ?: '#f97316',
            'perk_type'      => $p->perk_type ?: 'code',
            'perk_value'     => $p->perk_value,
            'status'         => $p->status ?: 'active',
            'sort_order'     => $p->sort_order ?? 0,
            'image_path'     => $p->image_path,
        ]]);
    @endphp
    <script>
window.perksData = @json($perksData);

function escPerk(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function perkCardHtml(p) {
    const inactive = (p.status || '') !== 'active';
    return `
    <div class="m-card p-4 ${inactive ? 'opacity-60' : ''}" id="perk-${p.id}" x-data="{ openMenu: false }">
        <div class="flex items-start gap-3">
            <span class="w-11 h-11 rounded-xl flex items-center justify-center text-white flex-shrink-0" style="background: linear-gradient(135deg, ${escPerk(p.bg_from || 'hsl(250 65% 60%)')}, ${escPerk(p.bg_to || 'hsl(250 65% 65%)')});">
                <i class="bi ${escPerk(p.icon || 'bi-gift')} text-lg"></i>
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="font-semibold text-foreground truncate">${escPerk(p.title)}</h3>
                    <div class="flex items-center gap-1.5 flex-shrink-0" @click.stop>
                        ${inactive ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500">{{ __('admin.perk_inactive') }}</span>' : ''}
                        <div class="relative">
                            <button type="button" @click="openMenu = !openMenu" class="m-press w-8 h-8 -mr-1 rounded-full flex items-center justify-center text-muted-foreground"><i class="bi bi-three-dots-vertical"></i></button>
                            <div x-show="openMenu" x-cloak @click.outside="openMenu = false" class="absolute right-0 mt-1 w-44 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-20">
                                <button type="button" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3" @click="openMenu = false; $dispatch('open-edit-perk', { id: ${Number(p.id)} })"><span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0"><i class="bi bi-pencil text-blue-600 text-xs"></i></span><span class="font-medium">{{ __('admin.perk_edit') }}</span></button>
                                <button type="button" class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3" @click="openMenu = false; removePerkId = ${Number(p.id)}; removePerkName = (window.perksData[${Number(p.id)}] || {}).title || ''"><span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center shrink-0"><i class="bi bi-trash text-red-600 text-xs"></i></span><span class="font-medium">{{ __('admin.perk_delete') }}</span></button>
                            </div>
                        </div>
                    </div>
                </div>
                ${p.badge ? `<span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">${escPerk(p.badge)}</span>` : ''}
                ${p.description ? `<p class="text-xs text-muted-foreground mt-1.5 line-clamp-2">${escPerk(p.description)}</p>` : ''}
            </div>
        </div>
    </div>`;
}

// In-place add/update after a save (deduped — the mobile shell re-runs inline scripts on nav).
window.__perkSaved && window.removeEventListener('perk-saved', window.__perkSaved);
window.__perkSaved = function (e) {
    const p = e.detail && e.detail.perk;
    if (!p || !p.id) return;
    const tr = p.translations || {};
    window.perksData[p.id] = {
        id: p.id, title: p.title,
        title_ar: (tr.title && tr.title.ar) || '',
        description: p.description,
        description_ar: (tr.description && tr.description.ar) || '',
        badge: p.badge,
        badge_ar: (tr.badge && tr.badge.ar) || '',
        icon: p.icon || 'bi-gift',
        bg_from: p.bg_from || '#f59e0b',
        bg_to: p.bg_to || '#f97316',
        perk_type: p.perk_type || 'code',
        perk_value: p.perk_value,
        status: p.status || 'active',
        sort_order: p.sort_order ?? 0,
        image_path: p.image_path || '',
    };
    const existing = document.getElementById('perk-' + p.id);
    if (existing) {
        existing.outerHTML = perkCardHtml(window.perksData[p.id]);
    } else {
        document.getElementById('perksEmpty')?.classList.add('hidden');
        document.getElementById('perksList')?.insertAdjacentHTML('beforeend', perkCardHtml(window.perksData[p.id]));
    }
};
window.addEventListener('perk-saved', window.__perkSaved);

@if($errors->any())
document.addEventListener('DOMContentLoaded', function () {
    window.showToast && window.showToast('error', @json($errors->first()));
});
@endif

function removePerk(id) {
    if (!id) return;
    fetch(`{{ url('admin/club/' . $club->slug . '/perks') }}/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    })
    .then(r => r.ok ? r : Promise.reject())
    .then(() => {
        document.getElementById('perk-' + id)?.remove();
        delete window.perksData[id];
        const list = document.getElementById('perksList');
        if (list && !list.children.length) document.getElementById('perksEmpty')?.classList.remove('hidden');
        window.dispatchEvent(new CustomEvent('perk-removed'));
        window.showToast('success', '{{ __('admin.perk_deleted') }}');
    })
    .catch(() => window.showToast('error', '{{ __('admin.club_facilities_add_unexpected_error') }}'));
}
    </script>
    </div>{{-- /content --}}
</div>
@endsection
