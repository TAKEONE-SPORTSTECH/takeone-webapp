@extends('layouts.app')

@section('content')
<div class="tf-container">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h1 class="mb-1 text-2xl font-bold">{{ __('member.family_index_title') }}</h1>
            <p class="text-gray-500 mb-0">{{ __('member.family_index_subtitle') }}</p>
        </div>
        <a href="{{ route('me.family') }}"
           class="inline-flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium">
            <i class="bi bi-diagram-3"></i>{{ __('nav.family_tree') }}
        </a>
    </div>

    <!-- Family Members Card Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">


        <!-- Dependents Cards -->
        @foreach($dependents as $relationship)
            <div class="relative">
                <x-member-card
                    :member="$relationship->dependent"
                    :href="route('member.show', $relationship->dependent->uuid)"
                    :footerLabel="$relationship->relationship_type === 'spouse' ? 'WIFE' : strtoupper($relationship->relationship_type)"
                    :guardian="$user"
                />
                @if(!empty($relationship->edges))
                    <button type="button"
                            onclick="event.preventDefault(); window.dispatchEvent(new CustomEvent('ft:manage', { detail: {{ Illuminate\Support\Js::from(['personId' => $relationship->person_id, 'personName' => $relationship->dependent->full_name, 'edges' => $relationship->edges]) }} }));"
                            class="absolute top-2 right-2 w-8 h-8 rounded-full bg-white shadow-sm border border-gray-100 flex items-center justify-center text-muted-foreground hover:text-red-600 hover:border-red-200 transition-colors z-10"
                            aria-label="{{ __('member.manage_relationships') }}">
                        <i class="bi bi-three-dots text-sm"></i>
                    </button>
                @endif
            </div>
        @endforeach

        <!-- Add New Family Member Card -->
        <div x-data>
            <div class="bg-white rounded-lg h-full shadow-sm border-2 border-dashed border-gray-300 add-card"
                 @click="$dispatch('open-member-create-modal')">
                <div class="text-center flex flex-col justify-center items-center h-full cursor-pointer p-6">
                    <div class="mb-3">
                        <i class="bi bi-plus-circle text-5xl"></i>
                    </div>
                    <h5 class="font-semibold text-gray-500">{{ __('member.family_index_add_member') }}</h5>
                </div>
            </div>
        </div>
    </div>

{{-- Add Family Member — chooser (New / Search Existing), then the matching modal --}}
<x-member-add-chooser-desktop />
<x-profile-modal
    mode="create"
    :eventName="'open-member-manual-sheet'"
    :title="__('member.family_index_modal_title')"
    :subtitle="__('member.family_index_modal_subtitle')"
    :showRelationshipFields="true"
    :showEmailField="false"
    :formAction="route('family.store')"
    formMethod="POST"
/>
<x-member-search-existing-modal-desktop />

{{-- Manage-relationships modal — lets you remove a mistaken/duplicate family
     link directly from this list (same action + component the Family Tree uses). --}}
<div x-data="ftManageData({ removeUrl: '{{ route('me.family.relative.remove') }}', csrf: '{{ csrf_token() }}' })"
     @ft:manage.window="openFor($event.detail)"
     x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.escape.window="close()">
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50" @click="close()"></div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         class="relative w-full max-w-md max-h-[90vh] flex flex-col bg-white rounded-2xl shadow-2xl">

        <div class="flex-shrink-0 px-6 pt-5 pb-4 border-b border-gray-100 flex items-start justify-between">
            <div>
                <h3 class="text-xl font-bold text-gray-900">{{ __('member.manage_relationships') }}</h3>
                <p class="text-sm text-muted-foreground">
                    <span class="font-semibold text-primary" x-text="personName"></span>
                </p>
            </div>
            <button type="button" @click="close()" class="text-gray-400 hover:text-gray-600 text-xl"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-5">
            @include('family.partials.manage-relative-fields')
        </div>

        <div class="flex-shrink-0 px-6 py-4 border-t border-gray-100 flex justify-end">
            <button type="button" @click="close()"
                class="px-4 py-2 rounded-lg border border-gray-200 text-gray-700 font-medium hover:bg-gray-50 transition">
                {{ __('member.close') }}
            </button>
        </div>
    </div>
</div>

@include('family.partials.tree-runtime')

</div>

{{-- Styles moved to app.css (Phase 6) --}}

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Load countries from JSON file
        fetch('/data/countries.json')
            .then(response => response.json())
            .then(countries => {
                // Convert all nationality displays from ISO3 to country name with flag
                document.querySelectorAll('.nationality-display').forEach(element => {
                    const iso3Code = element.getAttribute('data-iso3');
                    if (!iso3Code) return;

                    const country = countries.find(c => c.iso2 === iso3Code || c.iso3 === iso3Code);
                    if (country) {
                        // Get flag emoji from ISO2 code
                        const flagEmoji = country.iso2
                            .toUpperCase()
                            .split('')
                            .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                            .join('');

                        element.textContent = `${flagEmoji} ${country.name}`;
                    }
                });
            })
            .catch(error => console.error('Error loading countries:', error));
    });

    // Auto-suggest: while filling the manual "new member" form, check if the
    // phone typed already belongs to a real account (families share numbers,
    // so this can surface more than one candidate). Attached at page level —
    // the shared profile-modal component is used by many other flows, so it's left untouched.
    (function () {
        let lookupTimer = null;

        function ensureBanner(mobileInput) {
            let banner = document.getElementById('memberCreateSuggestBanner');
            if (banner) return banner;
            banner = document.createElement('div');
            banner.id = 'memberCreateSuggestBanner';
            banner.className = 'mt-2 space-y-2';
            mobileInput.closest('.mb-3, .mb-4, div')?.insertAdjacentElement('afterend', banner);
            return banner;
        }

        function renderMatches(banner, matches) {
            banner.textContent = '';
            matches.forEach((m) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'w-full flex items-center gap-2.5 rounded-xl border border-sky-200 bg-sky-50 p-3 text-start';

                const avatarWrap = document.createElement('span');
                avatarWrap.className = 'w-9 h-9 rounded-full overflow-hidden bg-white grid place-items-center flex-shrink-0 border border-sky-100';
                if (m.avatar) {
                    const img = document.createElement('img');
                    img.src = m.avatar; // property assignment, not markup — can't inject tags
                    img.alt = '';
                    img.className = 'w-9 h-9 object-cover';
                    avatarWrap.appendChild(img);
                } else {
                    const icon = document.createElement('i');
                    icon.className = 'bi bi-person-fill text-sky-400';
                    avatarWrap.appendChild(icon);
                }

                const textWrap = document.createElement('span');
                textWrap.className = 'min-w-0 flex-1';
                const nameEl = document.createElement('span');
                nameEl.className = 'block text-sm font-semibold text-foreground truncate';
                nameEl.textContent = @json(__('member.suggest_existing_prefix')) + ' ' + m.name; // textContent — never parsed as HTML
                const ctaEl = document.createElement('span');
                ctaEl.className = 'block text-[11px] text-sky-700';
                ctaEl.textContent = @json(__('member.suggest_existing_cta'));
                textWrap.append(nameEl, ctaEl);

                const chevron = document.createElement('i');
                chevron.className = 'bi bi-chevron-right text-sky-400 flex-shrink-0';

                btn.append(avatarWrap, textWrap, chevron);
                btn.addEventListener('click', () => {
                    window.dispatchEvent(new CustomEvent('open-member-search-sheet', { detail: { preselect: m } }));
                });
                banner.appendChild(btn);
            });
        }

        document.addEventListener('input', function (e) {
            if (e.target.id !== 'memberCreateForm_mobile_number') return;
            clearTimeout(lookupTimer);
            const mobile = e.target.value.trim();
            const banner = ensureBanner(e.target);
            if (mobile.length < 6) { banner.textContent = ''; return; }
            lookupTimer = setTimeout(async () => {
                const codeEl = document.getElementById('memberCreateForm_country_code');
                try {
                    const res = await fetch('{{ route('family.lookup') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ mobile_code: codeEl ? codeEl.value : '+973', mobile }),
                    });
                    const data = await res.json().catch(() => ({}));
                    renderMatches(banner, (data.success && Array.isArray(data.matches)) ? data.matches : []);
                } catch (err) { /* best-effort — never block typing */ }
            }, 400);
        });
    })();

    window.addEventListener('family-member-linked', function () {
        window.location.reload();
    });
    window.addEventListener('family-relative-removed', function () {
        window.location.reload();
    });
</script>
@endsection
