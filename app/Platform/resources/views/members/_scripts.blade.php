{{-- The one nationality/flag runtime (window.paintNationalities) --}}
@include('partials.nationality-runtime')

{{-- Shared behaviour for the members list (desktop + mobile): debounced server-side
     search swapping #membersResults, AJAX pagination, nationality flags, popup. --}}
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const baseUrl   = @json(route('admin.platform.members'));
        const resultsEl = document.getElementById('membersResults');
        const searchEl  = document.getElementById('memberSearch');
        if (!resultsEl || !searchEl) return;
        let searchDebounce = null;
        let currentSearch  = @json($search ?? '');
        let currentSort    = @json($sort ?? 'newest');
        let currentFilters = @json($filterState ?? null) || {};
        let activeFetch    = 0;

        // Array filters travel as repeated params (missing[]=phone&missing[]=email);
        // everything else as a scalar. Empty values are left out entirely so a
        // cleared filter disappears from the URL rather than lingering as noise.
        function appendFilters(params) {
            Object.entries(currentFilters).forEach(([key, value]) => {
                if (Array.isArray(value)) {
                    value.forEach(v => params.append(key + '[]', v));
                } else if (value !== null && value !== '' && value !== undefined && !(key === 'verified' && value === 'any')) {
                    params.set(key, value);
                }
            });
        }

        function buildUrl() {
            const params = new URLSearchParams();
            if (currentSearch) params.set('search', currentSearch);
            if (currentSort)   params.set('sort', currentSort);
            appendFilters(params);
            const qs = params.toString();
            return baseUrl + (qs ? ('?' + qs) : '');
        }

        // Flags come from partials/nationality-runtime — one runtime, our own
        // files. This used to fetch countries.json and build an EMOJI, which
        // draws the letters "BH" on Windows, which is what an admin desk runs
        // (CLAUDE.md -> Flags Come From One Local Source).
        function convertNationalities(root) {
            window.paintNationalities?.(root || document);
        }

        convertNationalities(resultsEl);

        function loadResults(url) {
            const requestId = ++activeFetch;
            resultsEl.classList.add('opacity-50', 'pointer-events-none');
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
                .then(r => r.text())
                .then(html => {
                    if (requestId !== activeFetch) return;
                    resultsEl.innerHTML = html;
                    convertNationalities(resultsEl);
                })
                .catch(() => window.showToast?.('error', 'Could not load members. Please try again.'))
                .finally(() => {
                    if (requestId === activeFetch) resultsEl.classList.remove('opacity-50', 'pointer-events-none');
                });
        }

        function runSearch(term) {
            currentSearch = term;
            const url = buildUrl();
            history.replaceState(null, '', url);
            loadResults(url);
        }

        // Called by the sort dropdown (Alpine) — re-fetch with the new sort, preserving the search.
        window.memberSetSort = function(sort) {
            currentSort = sort;
            const url = buildUrl();
            history.replaceState(null, '', url);
            loadResults(url);
        };

        // The advanced filter component's one output. Stored on window and removed
        // before re-adding, because the mobile shell re-runs this script on every
        // AJAX navigation and the listeners would otherwise stack up.
        if (window.__memberFiltersApply) {
            window.removeEventListener('member-filters:apply', window.__memberFiltersApply);
        }
        window.__memberFiltersApply = function(e) {
            currentFilters = e.detail || {};
            const url = buildUrl();
            history.replaceState(null, '', url);
            loadResults(url);
        };
        window.addEventListener('member-filters:apply', window.__memberFiltersApply);

        searchEl.addEventListener('input', function(e) {
            clearTimeout(searchDebounce);
            const term = e.target.value.trim();
            searchDebounce = setTimeout(() => runSearch(term), 300);
        });

        resultsEl.addEventListener('click', function(e) {
            const pageLink = e.target.closest('.pagination a, nav[role="navigation"] a, [aria-label="Pagination Navigation"] a');
            if (pageLink && pageLink.getAttribute('href')) {
                e.preventDefault();
                history.replaceState(null, '', pageLink.href);
                loadResults(pageLink.href);
                resultsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }
            const wrapper = e.target.closest('.member-card-wrapper[data-member-id]');
            if (!wrapper) return;
            e.preventDefault();
            e.stopPropagation();
            const userId   = wrapper.getAttribute('data-member-id');
            const popupUrl = wrapper.getAttribute('data-popup-url');
            if (userId && popupUrl && window.openMemberPopup) {
                window.openMemberPopup(userId, popupUrl);
            }
        });
    });
</script>
@endpush
