@extends('layouts.admin')

@section('admin-content')
<div x-data>
    <!-- Page Header -->
    <div class="mb-4">
        <h1 class="text-2xl font-bold mb-2">All Members</h1>
        <p class="text-muted-foreground">Manage all platform members</p>
    </div>

    <!-- Search and Actions Bar -->
    <div class="flex justify-between items-center mb-4">
        <div class="grow mr-3">
            <input type="text" id="memberSearch" class="form-control" placeholder="Search members by name, phone, nationality, or gender..." value="{{ $search ?? '' }}">
        </div>
        <button class="btn btn-primary" @click="$dispatch('open-member-create-modal')">
            <i class="bi bi-plus-circle mr-2"></i>Add Member
        </button>
    </div>

    <!-- Members Grid + Pagination (swapped in place on search) -->
    <div id="membersResults">
        @include('admin.platform.members._results')
    </div>
</div>

{{-- Member Create Modal --}}
<x-profile-modal
    mode="create"
    title="Add Platform Member"
    subtitle="Fill in the details to add a new platform member"
    :showPasswordFields="true"
    :formAction="route('admin.platform.members.store')"
    formMethod="POST"
/>

{{-- Member quick-view popup --}}
@include('admin.club.members.partials.member-popup')

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const baseUrl   = @json(route('admin.platform.members'));
        const resultsEl = document.getElementById('membersResults');
        const searchEl  = document.getElementById('memberSearch');
        let countriesCache = null;
        let searchDebounce = null;
        let currentSearch  = @json($search ?? '');
        let activeFetch    = 0;

        // Convert ISO nationality codes to "🏳 Country" within a given root element.
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

        // Load countries once, then run the initial conversion.
        fetch('/data/countries.json')
            .then(response => response.json())
            .then(countries => {
                countriesCache = countries;
                convertNationalities(resultsEl);
            })
            .catch(error => console.error('Error loading countries:', error));

        // Fetch a results page from the server and swap it in — no reload.
        function loadResults(url) {
            const requestId = ++activeFetch;
            resultsEl.classList.add('opacity-50', 'pointer-events-none');
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } })
                .then(r => r.text())
                .then(html => {
                    if (requestId !== activeFetch) return; // a newer request superseded this one
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

        // Debounced server-side search (matches across ALL members, not just this page).
        searchEl.addEventListener('input', function(e) {
            clearTimeout(searchDebounce);
            const term = e.target.value.trim();
            searchDebounce = setTimeout(() => runSearch(term), 300);
        });

        // Delegated handlers on the stable results container (survives innerHTML swaps).
        resultsEl.addEventListener('click', function(e) {
            // Pagination links → fetch in place, preserving the active search.
            const pageLink = e.target.closest('.pagination a, nav[role="navigation"] a, [aria-label="Pagination Navigation"] a');
            if (pageLink && pageLink.getAttribute('href')) {
                e.preventDefault();
                history.replaceState(null, '', pageLink.href);
                loadResults(pageLink.href);
                resultsEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                return;
            }

            // Member card → quick-view popup.
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
@endsection
