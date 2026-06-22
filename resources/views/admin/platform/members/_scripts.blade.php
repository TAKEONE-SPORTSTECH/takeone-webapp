{{-- Shared behaviour for the members list (desktop + mobile): debounced server-side
     search swapping #membersResults, AJAX pagination, nationality flags, popup. --}}
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const baseUrl   = @json(route('admin.platform.members'));
        const resultsEl = document.getElementById('membersResults');
        const searchEl  = document.getElementById('memberSearch');
        if (!resultsEl || !searchEl) return;
        let countriesCache = null;
        let searchDebounce = null;
        let currentSearch  = @json($search ?? '');
        let activeFetch    = 0;

        function convertNationalities(root) {
            if (!countriesCache) return;
            root.querySelectorAll('.nationality-display').forEach(element => {
                const iso3Code = element.getAttribute('data-iso3');
                if (!iso3Code) return;
                const country = countriesCache.find(c => c.iso2 === iso3Code || c.iso3 === iso3Code);
                if (country) {
                    const flagEmoji = country.iso2.toUpperCase().split('')
                        .map(char => String.fromCodePoint(127397 + char.charCodeAt(0))).join('');
                    element.textContent = `${flagEmoji} ${country.name}`;
                }
            });
        }

        fetch('/data/countries.json')
            .then(response => response.json())
            .then(countries => { countriesCache = countries; convertNationalities(resultsEl); })
            .catch(error => console.error('Error loading countries:', error));

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
            const url = baseUrl + (term ? ('?search=' + encodeURIComponent(term)) : '');
            history.replaceState(null, '', url);
            loadResults(url);
        }

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
