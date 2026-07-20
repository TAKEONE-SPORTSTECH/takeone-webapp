@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_events'))

@section('club-admin-content')
<div class="-mx-4 -mt-4"
     x-data="{ removeEventId: null, removeEventName: '' }"
     @event-removed.window="removeEventId = null; removeEventName = ''">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70 truncate">{{ $club->club_name ?? __('admin.club') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('admin.nav_events') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="$dispatch('open-add-event')"
                        class="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('admin.evt_add') }}">
                    <i class="bi bi-plus-lg text-xl"></i>
                </button>
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                    <i class="bi bi-calendar-event text-xl m-float"></i>
                </div>
            </div>
        </div>
    </header>

    <div class="px-4 pt-5">
    <div id="eventsList" class="space-y-4 mobile-stagger">
        @foreach($events as $e)
            @php $eImg = is_array($e->images ?? null) ? ($e->images[0] ?? null) : null; @endphp
            <div class="m-card p-4 {{ $e->is_archived ? 'opacity-60' : '' }}" id="event-{{ $e->id }}" x-data="{ openMenu: false }">
                <div class="flex items-start gap-3">
                    <div class="flex flex-col items-center justify-center w-14 h-14 rounded-xl text-white flex-shrink-0" style="background: {{ $e->color ?? 'hsl(250 65% 60%)' }};">
                        <span class="text-lg font-bold leading-none">{{ optional($e->date)->format('d') }}</span>
                        <span class="text-[10px] uppercase">{{ optional($e->date)->format('M') }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-semibold text-foreground truncate">{{ $e->title }}</h3>
                            <div class="flex items-center gap-1.5 flex-shrink-0" @click.stop>
                                @if($e->is_archived)<span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500">{{ __('admin.evt_archived') }}</span>@endif
                                <div class="relative">
                                    <button type="button" @click="openMenu = !openMenu" class="m-press w-8 h-8 -mr-1 rounded-full flex items-center justify-center text-muted-foreground" aria-label="{{ __('admin.actions') }}"><i class="bi bi-three-dots-vertical"></i></button>
                                    <div x-show="openMenu" x-cloak @click.outside="openMenu = false" class="absolute right-0 mt-1 w-44 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-20">
                                        <a href="{{ route('admin.club.events.participants', [$club->slug, $e->id]) }}" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3"><span class="w-7 h-7 rounded-lg bg-primary/10 flex items-center justify-center shrink-0"><i class="bi bi-people text-primary text-xs"></i></span><span class="font-medium">{{ __('admin.evt_participants') }}</span></a>
                                        <button type="button" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3" @click="openMenu = false; $dispatch('open-edit-event', { id: {{ $e->id }} })"><span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0"><i class="bi bi-pencil text-blue-600 text-xs"></i></span><span class="font-medium">{{ __('admin.evt_edit') }}</span></button>
                                        <button type="button" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3" @click="openMenu = false; toggleArchiveEvent({{ $e->id }})"><span class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center shrink-0"><i class="bi bi-archive text-amber-600 text-xs"></i></span><span class="font-medium">{{ __('admin.evt_archived') }}</span></button>
                                        <button type="button" class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3" @click="openMenu = false; removeEventId = {{ $e->id }}; removeEventName = (window.eventsData[{{ $e->id }}] || {}).title || ''"><span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center shrink-0"><i class="bi bi-trash text-red-600 text-xs"></i></span><span class="font-medium">{{ __('admin.evt_delete') }}</span></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            @if($e->start_time){{ \Illuminate\Support\Str::of($e->start_time)->before(':00') }}@endif
                            @if($e->location) · <i class="bi bi-geo-alt"></i> {{ $e->location }}@endif
                        </p>
                        @if($e->level)<span class="inline-block mt-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">{{ $e->level }}</span>@endif
                    </div>
                </div>
                @if($e->description)<p class="text-xs text-muted-foreground mt-3 line-clamp-2">{{ $e->description }}</p>@endif
                @if($e->max_capacity)<p class="text-xs text-muted-foreground mt-2"><i class="bi bi-people mr-1"></i>{{ __('admin.evt_capacity') }} {{ $e->max_capacity }}</p>@endif
            </div>
        @endforeach
    </div>

    <div id="eventsEmpty" class="{{ $events->isEmpty() ? '' : 'hidden' }}">
        <div class="m-card p-8 text-center">
            <i class="bi bi-calendar-event text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">{{ __('admin.evt_none_yet') }}</p>
            <button type="button" @click="$dispatch('open-add-event')" class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-white text-sm font-semibold"><i class="bi bi-plus-lg"></i>{{ __('admin.evt_add') }}</button>
        </div>
    </div>

    {{-- Delete confirm --}}
    <template x-teleport="body">
    <div x-show="removeEventId !== null" x-cloak class="fixed inset-0 z-[60] overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="removeEventId = null"></div>
        <div class="relative flex min-h-full items-center justify-center p-4 z-10">
            <div class="bg-white w-full max-w-sm relative rounded-2xl overflow-hidden shadow-xl" @click.stop>
                <div class="flex items-center justify-between border-b border-red-100 px-5 py-4">
                    <h5 class="text-destructive font-semibold flex items-center"><i class="bi bi-trash mr-2"></i>{{ __('admin.evt_delete') }}</h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="removeEventId = null"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="px-5 py-4">
                    <p class="mb-1 text-sm">{{ __('admin.evt_delete_confirm') }}</p>
                    <p class="font-semibold" x-text="removeEventName"></p>
                </div>
                <div class="border-t px-5 py-4 flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 bg-white" @click="removeEventId = null">{{ __('admin.cancel') }}</button>
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl bg-destructive text-white flex items-center gap-1" @click="removeEvent(removeEventId)"><i class="bi bi-trash"></i>{{ __('admin.evt_delete') }}</button>
                </div>
            </div>
        </div>
    </div>
    </template>

    @include('admin.club.events.mobile-form')

    @php
        $eventsData = $events->mapWithKeys(fn ($e) => [$e->id => [
            'id'          => $e->id,
            'title'       => $e->title,
            'date'        => optional($e->date)->format('Y-m-d'),
            'end_date'    => optional($e->end_date)->format('Y-m-d'),
            'start_time'  => $e->start_time ? substr($e->start_time, 0, 5) : '',
            'end_time'    => $e->end_time ? substr($e->end_time, 0, 5) : '',
            'color'       => $e->color ?: '#7c3aed',
            'location'    => $e->location,
            'level'       => $e->level,
            'max_capacity' => $e->max_capacity,
            'cancel_within_days' => $e->cancel_within_days,
            'tags'        => is_array($e->tags) ? $e->tags : [],
            'description' => $e->description,
            'participant_fee' => $e->participant_fee ?? '',
            'images'      => is_array($e->images) ? $e->images : [],
            'is_archived' => (bool) $e->is_archived,
        ]]);
    @endphp
    <script>
window.eventsData = @json($eventsData);
window.EVT_MONTHS = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC'];

function escEvent(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function eventCardHtml(e) {
    const parts = (e.date || '').split('-');
    const day = parts[2] || '';
    const mon = parts[1] ? (window.EVT_MONTHS[parseInt(parts[1], 10) - 1] || '') : '';
    const time = e.start_time ? escEvent(String(e.start_time).replace(/:00$/, '')) : '';
    return `
    <div class="m-card p-4 ${e.is_archived ? 'opacity-60' : ''}" id="event-${e.id}" x-data="{ openMenu: false }">
        <div class="flex items-start gap-3">
            <div class="flex flex-col items-center justify-center w-14 h-14 rounded-xl text-white flex-shrink-0" style="background: ${escEvent(e.color || 'hsl(250 65% 60%)')};">
                <span class="text-lg font-bold leading-none">${escEvent(day)}</span>
                <span class="text-[10px] uppercase">${escEvent(mon)}</span>
            </div>
            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="font-semibold text-foreground truncate">${escEvent(e.title)}</h3>
                    <div class="flex items-center gap-1.5 flex-shrink-0" @click.stop>
                        ${e.is_archived ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500">{{ __('admin.evt_archived') }}</span>' : ''}
                        <div class="relative">
                            <button type="button" @click="openMenu = !openMenu" class="m-press w-8 h-8 -mr-1 rounded-full flex items-center justify-center text-muted-foreground"><i class="bi bi-three-dots-vertical"></i></button>
                            <div x-show="openMenu" x-cloak @click.outside="openMenu = false" class="absolute right-0 mt-1 w-44 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-20">
                                <a href="{{ url('admin/club/' . $club->slug . '/events') }}/${Number(e.id)}/participants" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3"><span class="w-7 h-7 rounded-lg bg-primary/10 flex items-center justify-center shrink-0"><i class="bi bi-people text-primary text-xs"></i></span><span class="font-medium">{{ __('admin.evt_participants') }}</span></a>
                                <button type="button" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3" @click="openMenu = false; $dispatch('open-edit-event', { id: ${Number(e.id)} })"><span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0"><i class="bi bi-pencil text-blue-600 text-xs"></i></span><span class="font-medium">{{ __('admin.evt_edit') }}</span></button>
                                <button type="button" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3" @click="openMenu = false; toggleArchiveEvent(${Number(e.id)})"><span class="w-7 h-7 rounded-lg bg-amber-100 flex items-center justify-center shrink-0"><i class="bi bi-archive text-amber-600 text-xs"></i></span><span class="font-medium">{{ __('admin.evt_archived') }}</span></button>
                                <button type="button" class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3" @click="openMenu = false; removeEventId = ${Number(e.id)}; removeEventName = (window.eventsData[${Number(e.id)}] || {}).title || ''"><span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center shrink-0"><i class="bi bi-trash text-red-600 text-xs"></i></span><span class="font-medium">{{ __('admin.evt_delete') }}</span></button>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-muted-foreground mt-0.5">${time}${e.location ? ` · <i class="bi bi-geo-alt"></i> ${escEvent(e.location)}` : ''}</p>
                ${e.level ? `<span class="inline-block mt-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">${escEvent(e.level)}</span>` : ''}
            </div>
        </div>
        ${e.description ? `<p class="text-xs text-muted-foreground mt-3 line-clamp-2">${escEvent(e.description)}</p>` : ''}
        ${e.max_capacity ? `<p class="text-xs text-muted-foreground mt-2"><i class="bi bi-people mr-1"></i>{{ __('admin.evt_capacity') }} ${escEvent(e.max_capacity)}</p>` : ''}
    </div>`;
}

window.__eventSaved && window.removeEventListener('event-saved', window.__eventSaved);
window.__eventSaved = function (ev) {
    const raw = ev.detail && ev.detail.event;
    if (!raw || !raw.id) return;
    const e = {
        id: raw.id, title: raw.title,
        date: (raw.date || '').slice(0, 10),
        end_date: (raw.end_date || '').slice(0, 10),
        start_time: (raw.start_time || '').slice(0, 5),
        end_time: (raw.end_time || '').slice(0, 5),
        color: raw.color || '#7c3aed', location: raw.location, level: raw.level,
        max_capacity: raw.max_capacity, cancel_within_days: raw.cancel_within_days,
        tags: Array.isArray(raw.tags) ? raw.tags : [],
        description: raw.description,
        participant_fee: raw.participant_fee || '',
        images: Array.isArray(raw.images) ? raw.images : [],
        is_archived: !!raw.is_archived,
    };
    window.eventsData[e.id] = e;
    const existing = document.getElementById('event-' + e.id);
    if (existing) {
        existing.outerHTML = eventCardHtml(e);
    } else {
        document.getElementById('eventsEmpty')?.classList.add('hidden');
        document.getElementById('eventsList')?.insertAdjacentHTML('beforeend', eventCardHtml(e));
    }
};
window.addEventListener('event-saved', window.__eventSaved);

function toggleArchiveEvent(id) {
    fetch(`{{ url('admin/club/' . $club->slug . '/events') }}/${id}/archive`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    })
    .then(r => r.ok ? r.json() : Promise.reject())
    .then(d => {
        const data = window.eventsData[id]; if (data) data.is_archived = !!d.is_archived;
        const card = document.getElementById('event-' + id);
        if (card && data) card.outerHTML = eventCardHtml(data);
        window.showToast('success', d.message || 'Saved.');
    })
    .catch(() => window.showToast('error', '{{ __('admin.club_facilities_add_unexpected_error') }}'));
}

function removeEvent(id) {
    if (!id) return;
    fetch(`{{ url('admin/club/' . $club->slug . '/events') }}/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    })
    .then(r => r.ok ? r : Promise.reject())
    .then(() => {
        document.getElementById('event-' + id)?.remove();
        delete window.eventsData[id];
        const list = document.getElementById('eventsList');
        if (list && !list.children.length) document.getElementById('eventsEmpty')?.classList.remove('hidden');
        window.dispatchEvent(new CustomEvent('event-removed'));
        window.showToast('success', '{{ __('admin.evt_deleted') }}');
    })
    .catch(() => window.showToast('error', '{{ __('admin.club_facilities_add_unexpected_error') }}'));
}
    </script>
    </div>{{-- /content --}}
</div>
@endsection
