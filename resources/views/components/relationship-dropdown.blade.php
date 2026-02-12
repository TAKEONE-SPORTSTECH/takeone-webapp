@props(['name' => 'relationship_type', 'id' => 'relationship_type', 'value' => '', 'required' => false, 'error' => null, 'label' => 'Relationship'])

<div class="mb-4" x-data="relationshipDropdown_{{ $id }}()">
    <label class="block text-sm font-medium text-gray-600 mb-1">
        {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
    </label>
    <div class="relative" x-ref="wrapper">
        <button type="button"
                @click="toggle()"
                @click.away="open = false"
                x-ref="trigger"
                class="w-full px-4 py-3 text-base border-2 rounded-xl bg-white/80 shadow-inner transition-all duration-300 focus:bg-white focus:ring-4 focus:ring-primary/10 focus:outline-none flex items-center justify-between cursor-pointer {{ $error ? 'border-red-500' : 'border-primary/20 focus:border-primary' }}">
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
             class="absolute left-0 right-0 z-50 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden">
            <template x-for="item in items" :key="item.value">
                <div @click="selectItem(item)"
                     class="px-4 py-3 hover:bg-primary hover:text-white cursor-pointer flex items-center gap-3 transition-colors text-sm"
                     :class="selectedValue === item.value ? 'bg-primary/5 font-semibold' : ''">
                    <i :class="item.icon + ' ' + (selectedValue === item.value ? item.color : 'text-gray-400')" class="text-lg"></i>
                    <span x-text="item.label"></span>
                </div>
            </template>
        </div>
    </div>

    <input type="hidden" id="{{ $id }}" name="{{ $name }}" x-model="selectedValue" {{ $required ? 'required' : '' }}>

    @if($error)
        <span class="text-red-500 text-sm mt-1 block" role="alert">
            <strong>{{ $error }}</strong>
        </span>
    @endif
</div>

<script>
    function relationshipDropdown_{{ $id }}() {
        return {
            open: false,
            dropUp: false,
            selectedValue: '{{ $value }}',
            selectedLabel: '',
            selectedIcon: '',
            selectedColor: '',
            items: [
                { value: 'son',      label: 'Son',      icon: 'bi bi-person-fill',       color: 'text-blue-500' },
                { value: 'daughter', label: 'Daughter', icon: 'bi bi-person-fill',       color: 'text-pink-500' },
                { value: 'spouse',   label: 'Wife',     icon: 'bi bi-heart-fill',        color: 'text-red-500' },
                { value: 'sponsor',  label: 'Sponsor',  icon: 'bi bi-person-badge-fill', color: 'text-amber-500' },
                { value: 'other',    label: 'Other',    icon: 'bi bi-people-fill',       color: 'text-gray-500' }
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
                    this.dropUp = spaceBelow < 200;
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
