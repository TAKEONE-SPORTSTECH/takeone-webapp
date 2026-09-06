@props([
    'name' => 'gender', 'id' => 'gender', 'value' => '',
    'required' => false, 'error' => null, 'label' => 'Gender',
    /* ── Alpine mode ───────────────────────────────────────────────────────
       An Alpine state path in the PARENT scope (e.g. "fix.form.gender").
       Given one, this control reads and writes that property instead of
       posting a hidden input, and its behaviour is written INLINE rather than
       as a named function in a <script>.

       That is not a style choice: a script tag inside a `<template x-if>` is
       INERT (the browser never runs it), so a component that defines its
       x-data in one silently dies wherever it is used inside a template or a
       teleported sheet — which is exactly where this now lives, on the event
       roster's edit sheet. The date picker is inline for the same reason.

       Never write a component TAG in these comments: Blade compiles one even
       inside a PHP comment and the view dies with `Undefined variable
       $component`.

       Omit `model` and everything below behaves exactly as it always has. */
    'model' => null,
])

@php
    /* Null-safe READ of the bound path — see the birthdate picker's note. */
    $modelSafe = $model ? str_replace('.', '?.', $model) : null;

    /* One markup body, two state objects. The property NAMES are identical in
       both modes, so nothing below this block knows which mode it is in. */
    $state = $model
        ? "{
            open: false,
            dropUp: false,
            items: [
                { value: 'Male', label: '".addslashes(__('member.templates_member_show_gender_male'))."', icon: 'bi bi-gender-male', color: 'text-blue-500' },
                { value: 'Female', label: '".addslashes(__('member.templates_member_show_gender_female'))."', icon: 'bi bi-gender-female', color: 'text-pink-500' },
            ],
            /* Derived from the bound value, so a model filled in later (this
               sheet loads its entry over the network) shows up with no watcher. */
            get selectedValue() { return {$modelSafe} || '' },
            get selectedItem() { return this.items.find(i => i.value === this.selectedValue) || null },
            get selectedLabel() { return this.selectedItem ? this.selectedItem.label : '' },
            get selectedIcon() { return this.selectedItem ? this.selectedItem.icon : '' },
            get selectedColor() { return this.selectedItem ? this.selectedItem.color : '' },
            toggle() {
                if (! this.open) {
                    const rect = this.\$refs.trigger.getBoundingClientRect();
                    this.dropUp = (window.innerHeight - rect.bottom) < 150;
                }
                this.open = ! this.open;
            },
            selectItem(item) { {$model} = item.value; this.open = false },
        }"
        : "genderDropdown_{$id}()";
@endphp

<div class="mb-4" x-data="{{ $state }}">
    <label class="tf-label">
        {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
    </label>
    <div class="relative" x-ref="wrapper">
        <button type="button"
                @click="toggle()"
                @click.away="open = false"
                x-ref="trigger"
                class="tf-dropdown-trigger {{ $error ? 'border-red-500' : 'border-primary/20 focus:border-primary' }}">
            <span class="flex items-center gap-2">
                <i x-show="selectedIcon" :class="selectedIcon + ' ' + selectedColor" class="text-lg"></i>
                <span x-text="selectedLabel || 'Select {{ $label }}'" class="text-sm" :class="{ 'text-gray-400': !selectedValue }"></span>
            </span>
            <i class="bi bi-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
        </button>

        <div x-show="open" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             :class="dropUp ? 'bottom-full mb-1' : 'top-full mt-1'"
             class="tf-dropdown-panel">
            <template x-for="item in items" :key="item.value">
                <div @click="selectItem(item)"
                     class="tf-dropdown-item gap-3"
                     :class="selectedValue === item.value ? 'bg-primary/5 font-semibold' : ''">
                    <i :class="item.icon + ' ' + (selectedValue === item.value ? item.color : 'text-gray-400')" class="text-lg"></i>
                    <span x-text="item.label"></span>
                </div>
            </template>
        </div>
    </div>

    {{-- Form mode only: in Alpine mode the bound property IS the value, and a
         hidden input bound to a getter would throw on write. --}}
    @unless($model)
        <input type="hidden" id="{{ $id }}" name="{{ $name }}" x-model="selectedValue" {{ $required ? 'required' : '' }}>
    @endunless

    @if($error)
        <span class="tf-error" role="alert">
            <strong>{{ $error }}</strong>
        </span>
    @endif
</div>

@unless($model)
<script>
    function genderDropdown_{{ $id }}() {
        return {
            open: false,
            dropUp: false,
            selectedValue: '{{ $value }}',
            selectedLabel: '',
            selectedIcon: '',
            selectedColor: '',
            items: [
                { value: 'Male', label: 'Male', icon: 'bi bi-gender-male', color: 'text-blue-500' },
                { value: 'Female', label: 'Female', icon: 'bi bi-gender-female', color: 'text-pink-500' }
            ],

            init() {
                if (this.selectedValue) {
                    const match = this.items.find(i => i.value === this.selectedValue);
                    if (match) {
                        this.selectedLabel = match.label;
                        this.selectedIcon = match.icon;
                        this.selectedColor = match.color;
                    }
                }
            },

            toggle() {
                if (!this.open) {
                    const rect = this.$refs.trigger.getBoundingClientRect();
                    const spaceBelow = window.innerHeight - rect.bottom;
                    this.dropUp = spaceBelow < 150;
                }
                this.open = !this.open;
            },

            selectItem(item) {
                this.selectedValue = item.value;
                this.selectedLabel = item.label;
                this.selectedIcon = item.icon;
                this.selectedColor = item.color;
                this.open = false;
            }
        }
    }
</script>
@endunless
