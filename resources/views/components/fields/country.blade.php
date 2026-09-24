@props([
    'name' => 'country',
    'id' => 'country',
    'value' => '',
    'required' => false,
    'error' => null,
    'label' => 'Country',

    /*
     * Optional Alpine state path in the PARENT scope, e.g. "form.nationality".
     * When given, the chosen ISO-2 is mirrored into it — for a page that posts
     * JSON from its own Alpine root instead of submitting the form. The hidden
     * input stays either way, so every existing caller is untouched.
     *
     * `inline` makes that binding TWO-WAY and moves the behaviour out of the
     * script tag below into the x-data attribute itself. Both are needed
     * wherever this picker is used inside a `<template x-if>` or a teleported
     * sheet: a script tag there NEVER RUNS, so the named function is undefined
     * and the control renders dead; and the value usually arrives after mount
     * (fetched with the record), so a one-way mirror would show nothing.
     */
    'model' => null,
    'inline' => false,

    /*
     * Announce the INITIAL value on `country-changed` as well as every later
     * pick. Off by default because a seeded value silently moving a sibling
     * dial-code picker would be a surprise; on for a page that seeds the
     * country from the visitor's connection and wants the phone code to match.
     */
    'announce' => false,

    /*
     * Seed the field from WHERE THE DEVICE IS, when nothing is chosen yet.
     *
     * A country is the one fact a phone already knows, and hunting for it in a
     * list of 196 is work software exists to remove. Opt-in — off everywhere it
     * is not asked for, so no existing form starts quietly filling itself in —
     * and it only ever fills a BLANK: a value already on file, or one the
     * operator has touched, is never overwritten by a guess.
     */
    'geo' => false,

    /*
     * Anchor the panel instead of letting it be absolute inside whatever is
     * hosting it. An absolute panel is clipped by a scrolling sheet body; an
     * in-flow one shoves the fields below it down the sheet. Fixed and
     * positioned under the trigger (partials/float-panel) is neither.
     * Opt-in — every existing caller renders exactly as it did.
     */
    'float' => false,

    /*
     * Styling seams, for a surface with its own field language (the public
     * event pages' `.e-field`) rather than the platform's purple input group.
     * Defaults reproduce exactly what this component rendered before.
     */
    'wrapperClass' => 'mb-4',
    'labelClass' => 'tf-label',
    'triggerClass' => null,
])

@php
    /*
     * Seeding from the device's own position, when `geo` is on and the field is
     * still blank. `window.takeoneGeoCountry()` (published once, below) owns the
     * whole flow — permission, the fix, the lookup and the cache — and answers
     * null whenever it cannot, which leaves the field exactly as it was.
     */
    $geoSeed = $geo && $model
        ? "if (! this.selectedValue) { window.takeoneGeoCountry?.().then(iso => {
                if (iso && ! this.selectedValue) { this.adopt(iso); {$model} = iso; }
            }); }"
        : '';

    /* Null-safe read of the bound path: the sheet that holds this control sets
       its whole state object to null when it closes, and a getter that reads
       `fix.form.x` straight would throw on the way out. */
    $modelSafe = $model ? str_replace('.', '?.', $model) : null;

    $countryState = ($model && $inline)
        ? "{
            open: false, dropUp: false, search: '', items: [],
            selectedValue: '', selectedLabel: '', selectedFlag: '',
            async init() {
                try { this.items = await (await fetch('/data/countries.json')).json() }
                catch (e) { return }
                this.adopt({$modelSafe});
                /* The record is fetched after this mounts, so follow the bound
                   value until the operator touches the control. */
                this.\$watch('{$modelSafe}', v => { if (v !== this.selectedValue) this.adopt(v) });
                {$geoSeed}
            },
            adopt(iso) {
                const match = this.items.find(c => c.iso2 === (iso || ''));
                this.selectedValue = match ? match.iso2 : '';
                this.selectedLabel = match ? match.name : '';
                this.selectedFlag = match ? match.flag : '';
            },
            toggle() {
                if (! this.open) {
                    const rect = this.\$refs.trigger.getBoundingClientRect();
                    this.dropUp = (window.innerHeight - rect.bottom) < 300;
                }
                this.open = ! this.open;
            },
            get filteredItems() {
                if (! this.search) return this.items;
                const term = this.search.toLowerCase();
                return this.items.filter(c => c.name.toLowerCase().includes(term) || c.iso2.toLowerCase().includes(term));
            },
            selectItem(item) {
                this.selectedValue = item.iso2;
                this.selectedLabel = item.name;
                this.selectedFlag = item.flag;
                this.open = false; this.search = '';
                {$model} = item.iso2;
            },
        }"
        : "countryDropdown_{$id}()";
@endphp

@if($float)
    {{-- The anchoring runtime, published once per page. --}}
    @include('components.fields.support.float-panel')
@endif

<div class="{{ $wrapperClass }}" x-data="{{ $countryState }}"
     @unless($model && $inline) x-init="init()" @endunless
     @if($model && ! $inline) x-effect="{{ $model }} = selectedValue" @endif>
    <label class="{{ $labelClass }}">
        {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
    </label>
    <div class="relative" x-ref="wrapper">
        <button type="button"
                @click="toggle()@if($float); $nextTick(() => window.tkFloat($refs.trigger, $refs.panel, open))@endif"
                @click.away="open = false @if($float); window.tkFloat(null, $refs.panel, false)@endif"
                x-ref="trigger"
                class="{{ $triggerClass ?? 'tf-dropdown-trigger '.($error ? 'border-red-500' : 'border-primary/20 focus:border-primary') }}">
            <span class="flex items-center gap-2">
                <span x-show="selectedFlag" :class="'fi fi-' + selectedFlag"></span>
                <span x-text="selectedLabel || 'Select {{ $label }}'" class="text-sm" :class="{ 'text-gray-400': !selectedValue }"></span>
            </span>
            <i class="bi bi-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
        </button>

        <div x-show="open" x-cloak
             x-ref="dropdown"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @if(! $float) :class="dropUp ? 'bottom-full mb-1' : 'top-full mt-1'" @endif
             @if($float) x-ref="panel" style="z-index:78" @endif
             class="tf-dropdown-panel">
            <div class="p-2 border-b border-gray-100">
                <input type="text"
                       x-model="search"
                       @click.stop
                       x-ref="searchInput"
                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                       placeholder="{{ __('events::events.entry_country_search') }}">
            </div>
            <div class="max-h-60 overflow-y-auto">
                <template x-for="item in filteredItems" :key="item.iso2">
                    <div @click="selectItem(item)"
                         class="tf-dropdown-item-sm">
                        <span :class="'fi fi-' + item.flag" class="mr-2"></span>
                        <span x-text="item.name"></span>
                    </div>
                </template>
                <div x-show="filteredItems.length === 0" class="px-4 py-2 text-gray-500 text-sm">
                    {{ __('events::events.entry_country_none') }}
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="{{ $id }}" name="{{ $name }}" x-model="selectedValue" {{ $required ? 'required' : '' }}>

    @if($error)
        <span class="tf-error" role="alert">
            <strong>{{ $error }}</strong>
        </span>
    @endif
</div>

@unless($model && $inline)
<script>
    function countryDropdown_{{ $id }}() {
        return {
            open: false,
            dropUp: false,
            search: '',
            items: [],
            selectedValue: '{{ $value }}',
            selectedLabel: '',
            selectedFlag: '',

            async init() {
                try {
                    const res = await fetch('/data/countries.json');
                    this.items = await res.json();
                } catch (e) {
                    console.error('Error loading countries:', e);
                    return;
                }

                if (this.selectedValue) {
                    const match = this.items.find(c => c.iso2 === this.selectedValue);
                    if (match) {
                        this.selectedLabel = match.name;
                        this.selectedFlag = match.flag;

                        // Tell the rest of the form what it opened on, so a
                        // dial-code picker beside it starts on the same country.
                        // Opt-in (`:announce="true"`) — a seeded value moving a
                        // sibling field on every page that uses this component
                        // would be a surprise nobody asked for.
                        @if($announce)
                            window.dispatchEvent(new CustomEvent('country-changed', {
                                detail: {
                                    iso2: match.iso2,
                                    call_code: match.call_code,
                                    currency: match.currency,
                                    timezone: match.timezone,
                                    flag: match.flag,
                                    name: match.name,
                                },
                            }));
                        @endif
                    }
                }
            },

            toggle() {
                if (!this.open) {
                    const rect = this.$refs.trigger.getBoundingClientRect();
                    const spaceBelow = window.innerHeight - rect.bottom;
                    this.dropUp = spaceBelow < 300;
                }
                this.open = !this.open;
            },

            get filteredItems() {
                if (!this.search) return this.items;
                const term = this.search.toLowerCase();
                return this.items.filter(c =>
                    c.name.toLowerCase().includes(term) ||
                    c.iso2.toLowerCase().includes(term)
                );
            },

            selectItem(item) {
                this.selectedValue = item.iso2;
                this.selectedLabel = item.name;
                this.selectedFlag = item.flag;
                this.open = false;
                this.search = '';

                // Notify other dropdowns to sync
                window.dispatchEvent(new CustomEvent('country-changed', {
                    detail: {
                        iso2: item.iso2,
                        call_code: item.call_code,
                        currency: item.currency,
                        timezone: item.timezone,
                        flag: item.flag,
                        name: item.name
                    }
                }));
            }
        }
    }
</script>
@endunless

@once
@push('scripts')
{{-- WHERE THE DEVICE IS, as a country code — resolved once per browser and
     shared by every picker that asks for it (`<x-fields.country geo>`).

     Pushed to the page's script stack rather than written inline: this picker is
     regularly rendered inside a teleported sheet, and a <script> there never
     runs (CLAUDE.md → Alpine state must be REGISTERED, not inline).

     The contract is one promise that NEVER rejects and answers null whenever it
     cannot tell — a form seeded by this must be no worse off than a form that
     was not. It:
       · reuses the fix the shell already took (localStorage `userLocation`),
         so no second permission prompt appears for the common case,
       · asks the device only when permission is already granted,
       · sends the coordinates to OUR OWN endpoint, never to a third party from
         the member's browser, and gets back a country code and nothing else,
       · remembers the answer for a week, so opening a form is not a request. --}}
<script>
(function () {
    if (window.takeoneGeoCountry) return;

    var KEY = 'takeoneCountry';
    var WEEK = 7 * 24 * 60 * 60 * 1000;
    var pending = null;

    function cached() {
        try {
            var raw = JSON.parse(localStorage.getItem(KEY) || 'null');
            if (raw && raw.code && (Date.now() - raw.at) < WEEK) return raw.code;
        } catch (e) { /* private window, or storage blocked — just ask again */ }
        return null;
    }

    function remember(code) {
        try { localStorage.setItem(KEY, JSON.stringify({ code: code, at: Date.now() })); }
        catch (e) { /* not worth failing over */ }
    }

    /* The shell stores a fix on login; prefer it over prompting again. */
    function storedFix() {
        try {
            var raw = JSON.parse(localStorage.getItem('userLocation') || 'null');
            if (raw && typeof raw.lat === 'number' && typeof raw.lng === 'number') return raw;
        } catch (e) { /* ignore */ }
        return null;
    }

    function freshFix() {
        return new Promise(function (resolve) {
            if (! navigator.geolocation) return resolve(null);

            var done = false;
            var finish = function (v) { if (! done) { done = true; resolve(v); } };

            /* Only when the member has ALREADY said yes. A country field
               quietly raising a permission dialog is not a convenience. */
            var ask = function () {
                navigator.geolocation.getCurrentPosition(
                    function (p) { finish({ lat: p.coords.latitude, lng: p.coords.longitude }); },
                    function () { finish(null); },
                    { enableHighAccuracy: false, timeout: 6000, maximumAge: 600000 }
                );
            };

            if (navigator.permissions && navigator.permissions.query) {
                navigator.permissions.query({ name: 'geolocation' })
                    .then(function (s) { s.state === 'granted' ? ask() : finish(null); })
                    .catch(function () { finish(null); });
            } else {
                finish(null);
            }
        });
    }

    window.takeoneGeoCountry = function () {
        var hit = cached();
        if (hit) return Promise.resolve(hit);
        if (pending) return pending;

        pending = (async function () {
            var fix = storedFix() || await freshFix();
            if (! fix) return null;

            try {
                var res = await fetch('{{ route('me.geo.country') }}?lat=' + encodeURIComponent(fix.lat) + '&lng=' + encodeURIComponent(fix.lng), {
                    headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
                });
                if (! res.ok) return null;

                var d = await res.json();
                if (d && d.country) { remember(d.country); return d.country; }
            } catch (e) { /* offline, or the geocoder is down — the field stays blank */ }

            return null;
        })().finally(function () { pending = null; });

        return pending;
    };
})();
</script>
@endpush
@endonce
