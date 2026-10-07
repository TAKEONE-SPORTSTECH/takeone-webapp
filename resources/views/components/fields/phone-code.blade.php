@props([
    'name' => 'country_code',
    'id' => 'country_code',
    'value' => '+1',
    'required' => false,
    'error' => null,
    'syncWithCountry' => true,

    /*
     * Optional Alpine state path in the PARENT scope, e.g. "form.mobile_code".
     * When given, the chosen dial code is mirrored into it — so a page that
     * posts JSON from its own Alpine root gets the value without reading the
     * hidden input. The hidden input stays for ordinary form posts, so every
     * existing caller is untouched.
     */
    'model' => null,

    /*
     * `inline` moves the whole state into the x-data ATTRIBUTE instead of the
     * named function the <script> below defines.
     *
     * Needed wherever this picker is used inside a teleported sheet or a
     * `<template x-if>`: the markup is cloned into the document and Alpine
     * initialises it from the attribute — but the script that defines
     * `countryCodeDropdown_<id>()` may not have run by then, and a missing
     * x-data function leaves the control DEAD with no error a user can see.
     * The same seam the country-dropdown component already has, for the same
     * reason. (Written without its angle brackets on purpose: Blade compiles a
     * component TAG even inside a comment, and one here rendered the whole
     * picker as an "Undefined variable $component" 500.)
     */
    'inline' => false,

    /*
     * Anchor the panel under the trigger (partials/float-panel) instead of
     * leaving it absolute: absolute is clipped by a scrolling sheet body, and
     * in-flow would shove the fields below it down the sheet. Opt-in, so no
     * existing caller changes. The panel keeps its own fixed width — a dial
     * code trigger is narrower than the list it opens — so only its position
     * is taken over.
     */
    'float' => false,

    /*
     * The wrapper's classes. Defaults to the platform's own input group; a
     * surface with its own field styling (the public event pages' `.e-field`)
     * passes its own instead of fighting `tf-input-group`'s purple borders.
     */
    'groupClass' => 'tf-input-group',
])

{{-- Keep the mobile-number group left-to-right even on RTL pages: the flag/code
     button stays on the LEFT and the phone field on the right (phone numbers read
     LTR anyway), so the physical divider, rounding and dropdown anchor all stay
     correct. @once keeps it to a single <style> no matter how many times this renders. --}}
@once
<style>
    /* Keyed on the data attribute, not the class, so it still holds when a
       caller supplies its own `groupClass`. */
    [dir="rtl"] [data-phone-group] { direction: ltr; }
</style>
@endonce

@php
    /* The inline form of the same object. Only what a picker needs to RUN —
       the country list is rendered from the shared `window.__dialCodes` the
       script publishes, so the list itself is not duplicated into an
       attribute on every phone field on the page.

       ⚠️ `$value` goes in through Js::from, never concatenated raw. The
       attribute itself is escaped, but Alpine HTML-decodes it before it
       evaluates the expression, so an apostrophe in a stored dial code would
       close the JS string and the rest of the value would run as Alpine. */
    $dialState = $inline
        ? "{
            open: false, dropUp: false, search: '',
            selectedCode: ".\Illuminate\Support\Js::from($value).", selectedFlag: 'us',
            get countries() { return window.__dialCodes || []; },
            init() {
                const m = this.countries.find(c => c.code === this.selectedCode);
                if (m) this.selectedFlag = m.flag;
            },
            toggle() {
                if (! this.open) {
                    const r = this.\$refs.trigger.getBoundingClientRect();
                    this.dropUp = (window.innerHeight - r.bottom) < 300;
                }
                this.open = ! this.open;
            },
            get filteredCountries() {
                if (! this.search) return this.countries;
                const t = this.search.toLowerCase();
                return this.countries.filter(c => c.name.toLowerCase().includes(t) || c.code.includes(t));
            },
            selectCountry(c) { this.selectedFlag = c.flag; this.selectedCode = c.code; this.open = false; this.search = ''; },
        }"
        : 'countryCodeDropdown_'.$id.'()';
@endphp


@if($float)
    {{-- The anchoring runtime, published once per page. --}}
    @include('components.fields.support.float-panel')
@endif
<div class="{{ $groupClass }}" data-phone-group
     x-data="{{ $dialState }}"
     x-init="init()"
     @if($model)
         {{-- Mirrored with x-effect rather than on click, so the parent also
              gets the initial value and any change made by the shared
              `country-changed` event. --}}
         x-effect="{{ $model }} = selectedCode"
     @endif>
    <!-- Country Code Button -->
    <div class="relative">
        <button type="button"
                @click="toggle()@if($float); $nextTick(() => window.tkFloat($refs.trigger, $refs.panel, open, false))@endif"
                x-ref="trigger"
                class="h-full px-3 py-3 flex items-center gap-2 border-r border-primary/20 bg-transparent hover:bg-gray-50 transition-colors cursor-pointer rounded-l-xl"
                id="{{ $id }}Dropdown">
            {{-- Flags are FILES, not an icon font. The `flag-icons` stylesheet
                 this used to rely on is not loaded anywhere in the project, so
                 `fi fi-bh` painted an EMPTY span on every caller — the code
                 showed, the flag never did. The same vendored SVGs
                 <x-flag> uses, addressed by the very same lowercase ISO-2 the
                 list below already stores. Sized inline because the Tailwind
                 bundle is prebuilt. --}}
            <img :src="'/images/flags/' + selectedFlag + '.svg'" alt=""
                 style="height:13px; width:17.3px; object-fit:cover; border-radius:2px; flex:none;"
                 onerror="this.style.visibility='hidden'">
            <span x-text="selectedCode" class="text-sm font-medium text-gray-700">{{ $value }}</span>
            <i class="bi bi-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
        </button>

        <!-- Dropdown Menu -->
        <div x-show="open" x-cloak
             @if($float) x-ref="panel" style="z-index:78" @endif
             @click.outside="open = false @if($float); window.tkFloat(null, $refs.panel, false)@endif"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @if(! $float) :class="dropUp ? 'bottom-full mb-1' : 'top-full mt-1'" @endif
             class="absolute left-0 z-50 w-64 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden">
            <!-- Search Input -->
            <div class="p-2 border-b border-gray-100">
                <input type="text"
                       x-model="search"
                       @click.stop
                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                       placeholder="{{ __('events::events.entry_country_search') }}">
            </div>
            <!-- Country List -->
            <div class="max-h-60 overflow-y-auto">
                <template x-for="country in filteredCountries" :key="country.name">
                    <div @click="selectCountry(country)"
                         class="tf-dropdown-item-sm">
                        <img :src="'/images/flags/' + country.flag + '.svg'" alt="" class="mr-2"
                             style="height:13px; width:17.3px; object-fit:cover; border-radius:2px; flex:none;"
                             onerror="this.style.visibility='hidden'">
                        <span x-text="country.name + ' (' + country.code + ')'" class="text-sm"></span>
                    </div>
                </template>
                <div x-show="filteredCountries.length === 0" class="px-4 py-2 text-gray-500 text-sm">
                    {{ __('events::events.entry_country_none') }}
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="{{ $id }}" name="{{ $name }}" x-model="selectedCode" {{ $required ? 'required' : '' }}>

    <!-- Phone Number Input Slot -->
    <div class="flex-1">
        {{ $slot }}
    </div>
</div>

@if($error)
    <span class="tf-error" role="alert">
        <strong>{{ $error }}</strong>
    </span>
@endif

<script>
    function countryCodeDropdown_{{ $id }}() {
        return {
            open: false,
            dropUp: false,
            search: '',
            /* ⚠️ From App\Support\Countries — the SAME list every other country
               control reads. This was a hand-written array of sixty countries,
               which is how the platform came to have no Syria, no Yemen, no
               Sudan and no Afghanistan: a member from any of them could not
               enter their own phone number, and nothing said so. */
            countries: @json(\App\Support\Countries::dialCodes()),
            selectedCode: '{{ $value }}',
            selectedFlag: 'us',

            init() {
                // Find initial country by code
                const initialCountry = this.countries.find(c => c.code === this.selectedCode);
                if (initialCountry) {
                    this.selectedFlag = initialCountry.flag;
                }

@if($syncWithCountry)
                // Listen for country-changed events (e.g. a country/nationality picker on the
                // same form syncing the dial code). Opt out with :syncWithCountry="false" when
                // the phone code must stay independent (e.g. nationality ≠ phone country).
                // The listener lives on window, which outlives this picker: once the
                // mobile shell swaps the page away it removes itself, so in-place
                // navigation does not stack one dead listener per visit.
                const el = this.$el;
                const onCountry = (e) => {
                    if (el && ! el.isConnected) { window.removeEventListener('country-changed', onCountry); return; }
                    const code = e.detail && e.detail.call_code;
                    if (!code) return;
                    const match = this.countries.find(c => c.code === code);
                    if (match) {
                        this.selectedCode = match.code;
                        this.selectedFlag = match.flag;
                    }
                };
                window.addEventListener('country-changed', onCountry);
@endif
            },

            toggle() {
                if (!this.open) {
                    const rect = this.$refs.trigger.getBoundingClientRect();
                    const spaceBelow = window.innerHeight - rect.bottom;
                    this.dropUp = spaceBelow < 300;
                }
                this.open = !this.open;
            },

            get filteredCountries() {
                if (!this.search) return this.countries;
                const term = this.search.toLowerCase();
                return this.countries.filter(c =>
                    c.name.toLowerCase().includes(term) ||
                    c.code.includes(term)
                );
            },

            selectCountry(country) {
                this.selectedFlag = country.flag;
                this.selectedCode = country.code;
                this.open = false;
                this.search = '';
            }
        }
    }

    /* The dial codes, published once per page from the one copy above — the
       INLINE variant of this picker reads them from here rather than carrying
       the whole list in an x-data attribute on every phone field. Read when the
       list is opened, by which time this has run. */
    window.__dialCodes = window.__dialCodes || countryCodeDropdown_{{ $id }}().countries;
</script>
