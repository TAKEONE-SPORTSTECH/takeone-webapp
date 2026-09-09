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
     * Styling seams, for a surface with its own field language (the public
     * event pages' `.e-field`) rather than the platform's purple input group.
     * Defaults reproduce exactly what this component rendered before.
     */
    'wrapperClass' => 'mb-4',
    'labelClass' => 'tf-label',
    'triggerClass' => null,
])

@php
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

<div class="{{ $wrapperClass }}" x-data="{{ $countryState }}"
     @unless($model && $inline) x-init="init()" @endunless
     @if($model && ! $inline) x-effect="{{ $model }} = selectedValue" @endif>
    <label class="{{ $labelClass }}">
        {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
    </label>
    <div class="relative" x-ref="wrapper">
        <button type="button"
                @click="toggle()"
                @click.away="open = false"
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
             :class="dropUp ? 'bottom-full mb-1' : 'top-full mt-1'"
             class="tf-dropdown-panel">
            <div class="p-2 border-b border-gray-100">
                <input type="text"
                       x-model="search"
                       @click.stop
                       x-ref="searchInput"
                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                       placeholder="{{ __('events.entry_country_search') }}">
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
                    {{ __('events.entry_country_none') }}
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
