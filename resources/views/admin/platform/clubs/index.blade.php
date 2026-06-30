@extends('layouts.admin')

@section('admin-content')
<div>
    <!-- Page Header -->
    <div class="mb-4">
        <h1 class="text-2xl font-bold mb-2">All Clubs</h1>
        <p class="text-muted-foreground">Manage all clubs on the platform</p>
    </div>

    <!-- Search and Actions Bar -->
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="grow min-w-[16rem] relative" x-data="{ q: @js($search ?? '') }">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 flex items-center justify-center text-gray-400 pointer-events-none"></i>
            <input type="text" id="clubSearch" x-ref="clubSearch" x-model="q"
                   class="w-full pl-10 pr-10 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                   placeholder="Search clubs by name, location, owner, or description..."
                   value="{{ $search ?? '' }}">
            <button type="button" x-show="q.length" x-cloak
                    @click="q=''; $nextTick(() => $refs.clubSearch.dispatchEvent(new Event('input')))"
                    title="Clear search"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                <i class="bi bi-x-circle-fill"></i>
            </button>
        </div>

        {{-- Sort dropdown (custom Alpine — no native select) --}}
        <div class="relative shrink-0"
             x-data="{
                open: false,
                sort: @js($sort ?? 'newest'),
                labels: {
                    newest: 'Newest first',
                    oldest: 'Oldest first',
                    name_asc: 'Name (A–Z)',
                    name_desc: 'Name (Z–A)',
                    members: 'Most members',
                    packages: 'Most packages'
                },
                pick(key) { this.sort = key; this.open = false; window.clubSetSort?.(key); }
             }"
             @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" @click="open = !open"
                    class="flex items-center gap-2 px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors whitespace-nowrap">
                <i class="bi bi-sort-down text-gray-400"></i>
                <span class="hidden sm:inline text-muted-foreground">Sort:</span>
                <span x-text="labels[sort]"></span>
                <i class="bi bi-chevron-down text-xs text-gray-400 transition-transform" :class="open && 'rotate-180'"></i>
            </button>
            <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="absolute right-0 mt-2 w-56 max-w-[calc(100vw-2rem)] bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden z-50 py-1">
                <template x-for="(label, key) in labels" :key="key">
                    <button type="button" @click="pick(key)"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-sm hover:bg-muted/60 transition-colors"
                            :class="sort === key ? 'text-primary font-semibold' : 'text-gray-700'">
                        <span x-text="label"></span>
                        <i class="bi bi-check-lg" x-show="sort === key"></i>
                    </button>
                </template>
            </div>
        </div>

        <button type="button" class="btn btn-primary shrink-0" onclick="window.dispatchEvent(new CustomEvent('open-club-modal', { detail: { mode: 'create' } }));">
            <i class="bi bi-plus-circle mr-2"></i>Add New Club
        </button>
    </div>

    <!-- Clubs Grid + Pagination (swapped in place on search/sort) -->
    <div id="clubsResults">
        @include('admin.platform.clubs._results')
    </div>
</div>

<!-- Include Club Modal -->
<x-club-modal mode="create" />

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const baseUrl   = @json(route('admin.platform.clubs'));
        const resultsEl = document.getElementById('clubsResults');
        const searchEl  = document.getElementById('clubSearch');
        if (!resultsEl || !searchEl) return;

        let searchDebounce = null;
        let currentSearch  = @json($search ?? '');
        let currentSort    = @json($sort ?? 'newest');
        let activeFetch    = 0;

        function buildUrl() {
            const params = new URLSearchParams();
            if (currentSearch) params.set('search', currentSearch);
            if (currentSort)   params.set('sort', currentSort);
            const qs = params.toString();
            return baseUrl + (qs ? ('?' + qs) : '');
        }

        function loadResults(url) {
            const requestId = ++activeFetch;
            resultsEl.classList.add('opacity-50', 'pointer-events-none');
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
                .then(r => r.text())
                .then(html => {
                    if (requestId !== activeFetch) return;
                    resultsEl.innerHTML = html;
                })
                .catch(() => window.showToast?.('error', 'Could not load clubs. Please try again.'))
                .finally(() => {
                    if (requestId === activeFetch) resultsEl.classList.remove('opacity-50', 'pointer-events-none');
                });
        }

        function reload() {
            const url = buildUrl();
            history.replaceState(null, '', url);
            loadResults(url);
        }

        searchEl.addEventListener('input', function (e) {
            clearTimeout(searchDebounce);
            currentSearch = e.target.value.trim();
            searchDebounce = setTimeout(reload, 300);
        });

        // Called by the sort dropdown (Alpine) — re-fetch with the new sort, preserving the search.
        window.clubSetSort = function (sort) {
            currentSort = sort;
            reload();
        };

        // AJAX pagination (delegated — links are re-rendered on each swap).
        resultsEl.addEventListener('click', function (e) {
            const pageLink = e.target.closest('.pagination a, nav[role="navigation"] a, [aria-label="Pagination Navigation"] a');
            if (pageLink && pageLink.getAttribute('href')) {
                e.preventDefault();
                history.replaceState(null, '', pageLink.href);
                loadResults(pageLink.href);
                resultsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // Patch a club card in place after an edit — no reload.
    window.addEventListener('club-saved', function (e) {
        const detail = e.detail || {};
        if (detail.mode !== 'edit' || !detail.club) return;
        const c = detail.club;
        const wrapper = document.querySelector('.club-card-wrapper[data-club-id="' + c.id + '"]');
        if (!wrapper) return;

        // Keep search filter attributes in sync.
        if (c.club_name != null)  wrapper.setAttribute('data-club-name', c.club_name);
        wrapper.setAttribute('data-club-address', c.address || '');

        const title = wrapper.querySelector('.club-title');
        if (title && c.club_name != null) title.textContent = c.club_name;

        const addrText = wrapper.querySelector('.flex.items-center.text-muted-foreground .truncate');
        if (addrText && c.address != null) addrText.textContent = c.address;

        // Cover + logo images (cache-bust so a freshly uploaded image shows).
        const cover = wrapper.querySelector('.club-cover-img');
        if (cover && c.cover_image) cover.src = '/storage/' + c.cover_image + '?t=' + Date.now();
        const logo = wrapper.querySelector('.absolute.bottom-2.left-2 img');
        if (logo && c.logo) logo.src = '/storage/' + c.logo + '?t=' + Date.now();
    });
</script>
@endpush
@endsection
