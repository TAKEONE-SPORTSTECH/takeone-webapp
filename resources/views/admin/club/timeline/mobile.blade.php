@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_timeline'))

@section('club-admin-content')
<div class="-mx-4 -mt-4"
     x-data="{ removePostId: null }"
     @timeline-removed.window="removePostId = null">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70 truncate">{{ $club->club_name ?? __('admin.club') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('admin.nav_timeline') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="$dispatch('open-add-timeline')"
                        class="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('admin.tl_add') }}">
                    <i class="bi bi-plus-lg text-xl"></i>
                </button>
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                    <i class="bi bi-newspaper text-xl m-float"></i>
                </div>
            </div>
        </div>
    </header>

    <div class="px-4 pt-5">
    <div id="timelineList" class="space-y-4 mobile-stagger">
        @foreach($posts as $p)
            <div class="m-card overflow-hidden" id="post-{{ $p->id }}" x-data="{ openMenu: false }">
                @if($p->image_path)<img src="{{ asset('storage/'.$p->image_path) }}" alt="" class="w-full h-40 object-cover">@endif
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">{{ $p->category ?? __('admin.tl_update') }}</span>
                            @if(($p->status ?? '') !== 'published')<span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500">{{ __('admin.tl_draft') }}</span>@endif
                        </div>
                        <div class="relative flex-shrink-0" @click.stop>
                            <button type="button" @click="openMenu = !openMenu" class="m-press w-8 h-8 -mr-1 rounded-full flex items-center justify-center text-muted-foreground" aria-label="{{ __('admin.actions') }}"><i class="bi bi-three-dots-vertical"></i></button>
                            <div x-show="openMenu" x-cloak @click.outside="openMenu = false" class="absolute right-0 mt-1 w-44 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-20">
                                <button type="button" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3" @click="openMenu = false; $dispatch('open-edit-timeline', { id: {{ $p->id }} })"><span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0"><i class="bi bi-pencil text-blue-600 text-xs"></i></span><span class="font-medium">{{ __('admin.tl_edit') }}</span></button>
                                <button type="button" class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3" @click="openMenu = false; removePostId = {{ $p->id }}"><span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center shrink-0"><i class="bi bi-trash text-red-600 text-xs"></i></span><span class="font-medium">{{ __('admin.tl_delete') }}</span></button>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-foreground whitespace-pre-line">{{ $p->body }}</p>
                    <div class="flex items-center gap-4 mt-3 text-xs text-muted-foreground">
                        <span>{{ optional($p->posted_at)->format('d M Y') }}</span>
                        <span><i class="bi bi-heart mr-1"></i>{{ $p->likes_count ?? 0 }}</span>
                        <span><i class="bi bi-chat mr-1"></i>{{ $p->comments_count ?? 0 }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div id="timelineEmpty" class="{{ $posts->isEmpty() ? '' : 'hidden' }}">
        <div class="m-card p-8 text-center">
            <i class="bi bi-newspaper text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">{{ __('admin.tl_no_posts') }}</p>
            <button type="button" @click="$dispatch('open-add-timeline')" class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-white text-sm font-semibold"><i class="bi bi-plus-lg"></i>{{ __('admin.tl_add') }}</button>
        </div>
    </div>

    @if($posts->hasPages())<div class="mt-4">{{ $posts->links() }}</div>@endif

    {{-- Delete confirm --}}
    <template x-teleport="body">
    <div x-show="removePostId !== null" x-cloak class="fixed inset-0 z-[60] overflow-y-auto"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="removePostId = null"></div>
        <div class="relative flex min-h-full items-center justify-center p-4 z-10">
            <div class="bg-white w-full max-w-sm relative rounded-2xl overflow-hidden shadow-xl" @click.stop>
                <div class="flex items-center justify-between border-b border-red-100 px-5 py-4">
                    <h5 class="text-destructive font-semibold flex items-center"><i class="bi bi-trash mr-2"></i>{{ __('admin.tl_delete') }}</h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="removePostId = null"><i class="bi bi-x-lg"></i></button>
                </div>
                <div class="px-5 py-4">
                    <p class="mb-1 text-sm">{{ __('admin.tl_delete_confirm') }}</p>
                </div>
                <div class="border-t px-5 py-4 flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl border border-gray-200 bg-white" @click="removePostId = null">{{ __('admin.cancel') }}</button>
                    <button type="button" class="px-4 py-2 text-sm font-medium rounded-xl bg-destructive text-white flex items-center gap-1" @click="removePost(removePostId)"><i class="bi bi-trash"></i>{{ __('admin.tl_delete') }}</button>
                </div>
            </div>
        </div>
    </div>
    </template>

    @include('admin.club.timeline.mobile-form')

    @php
        $postsData = $posts->mapWithKeys(fn ($p) => [$p->id => [
            'id'             => $p->id,
            'body'           => $p->body,
            'category'       => $p->category,
            'posted_at'      => optional($p->posted_at)->format('Y-m-d\TH:i'),
            'status'         => $p->status,
            'image_path'     => $p->image_path,
            'likes_count'    => $p->likes_count ?? 0,
            'comments_count' => $p->comments_count ?? 0,
        ]]);
    @endphp
    <script>
window.timelineData = @json($postsData);
window.TL_MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

function escTimeline(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function tlFormatDate(iso) {
    if (!iso) return '';
    const d = String(iso).slice(0, 10).split('-');
    if (d.length < 3) return escTimeline(iso);
    return `${parseInt(d[2], 10)} ${window.TL_MONTHS[parseInt(d[1], 10) - 1] || ''} ${d[0]}`;
}

function timelineCardHtml(p) {
    const isDraft = (p.status || '') !== 'published';
    const img = p.image_path
        ? `<img src="/storage/${escTimeline(p.image_path)}" alt="" class="w-full h-40 object-cover">`
        : '';
    return `
    <div class="m-card overflow-hidden" id="post-${p.id}" x-data="{ openMenu: false }">
        ${img}
        <div class="p-4">
            <div class="flex items-start justify-between gap-2 mb-2">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">${escTimeline(p.category || '{{ __('admin.tl_update') }}')}</span>
                    ${isDraft ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500">{{ __('admin.tl_draft') }}</span>' : ''}
                </div>
                <div class="relative flex-shrink-0" @click.stop>
                    <button type="button" @click="openMenu = !openMenu" class="m-press w-8 h-8 -mr-1 rounded-full flex items-center justify-center text-muted-foreground"><i class="bi bi-three-dots-vertical"></i></button>
                    <div x-show="openMenu" x-cloak @click.outside="openMenu = false" class="absolute right-0 mt-1 w-44 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-20">
                        <button type="button" class="w-full text-left px-4 py-3 text-sm text-foreground hover:bg-muted/60 flex items-center gap-3" @click="openMenu = false; $dispatch('open-edit-timeline', { id: ${Number(p.id)} })"><span class="w-7 h-7 rounded-lg bg-blue-100 flex items-center justify-center shrink-0"><i class="bi bi-pencil text-blue-600 text-xs"></i></span><span class="font-medium">{{ __('admin.tl_edit') }}</span></button>
                        <button type="button" class="w-full text-left px-4 py-3 text-sm text-red-600 hover:bg-red-50 flex items-center gap-3" @click="openMenu = false; removePostId = ${Number(p.id)}"><span class="w-7 h-7 rounded-lg bg-red-100 flex items-center justify-center shrink-0"><i class="bi bi-trash text-red-600 text-xs"></i></span><span class="font-medium">{{ __('admin.tl_delete') }}</span></button>
                    </div>
                </div>
            </div>
            <p class="text-sm text-foreground whitespace-pre-line">${escTimeline(p.body)}</p>
            <div class="flex items-center gap-4 mt-3 text-xs text-muted-foreground">
                <span>${tlFormatDate(p.posted_at)}</span>
                <span><i class="bi bi-heart mr-1"></i>${Number(p.likes_count || 0)}</span>
                <span><i class="bi bi-chat mr-1"></i>${Number(p.comments_count || 0)}</span>
            </div>
        </div>
    </div>`;
}

window.__timelineSaved && window.removeEventListener('timeline-saved', window.__timelineSaved);
window.__timelineSaved = function (ev) {
    const raw = ev.detail && ev.detail.post;
    if (!raw || !raw.id) return;
    const existingData = window.timelineData[raw.id] || {};
    const p = {
        id: raw.id,
        body: raw.body,
        category: raw.category,
        posted_at: (raw.posted_at || '').slice(0, 16),
        status: raw.status,
        image_path: raw.image_path || null,
        likes_count: existingData.likes_count ?? 0,
        comments_count: existingData.comments_count ?? 0,
    };
    window.timelineData[p.id] = p;
    const existing = document.getElementById('post-' + p.id);
    if (existing) {
        existing.outerHTML = timelineCardHtml(p);
    } else {
        document.getElementById('timelineEmpty')?.classList.add('hidden');
        document.getElementById('timelineList')?.insertAdjacentHTML('afterbegin', timelineCardHtml(p));
    }
};
window.addEventListener('timeline-saved', window.__timelineSaved);

function removePost(id) {
    if (!id) return;
    fetch(`{{ url('admin/club/' . $club->slug . '/timeline') }}/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    })
    .then(r => r.ok ? r : Promise.reject())
    .then(() => {
        document.getElementById('post-' + id)?.remove();
        delete window.timelineData[id];
        const list = document.getElementById('timelineList');
        if (list && !list.children.length) document.getElementById('timelineEmpty')?.classList.remove('hidden');
        window.dispatchEvent(new CustomEvent('timeline-removed'));
        window.showToast('success', '{{ __('admin.tl_deleted') }}');
    })
    .catch(() => window.showToast('error', '{{ __('admin.club_facilities_add_unexpected_error') }}'));
}
    </script>
    </div>{{-- /content --}}
</div>
@endsection
