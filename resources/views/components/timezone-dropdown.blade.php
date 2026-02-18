@props(['name' => 'timezone', 'id' => 'timezone', 'value' => '', 'required' => false, 'error' => null, 'label' => 'Timezone'])

<div class="mb-4" x-data="timezoneDropdown_{{ $id }}()" x-init="init()">
    <label class="tf-label">
        {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
    </label>
    <div class="relative">
        <button type="button"
                @click="toggle()"
                @click.away="open = false"
                x-ref="trigger"
                class="tf-dropdown-trigger {{ $error ? 'border-red-500' : 'border-primary/20 focus:border-primary' }}">
            <span class="flex items-center gap-2">
                <span x-show="selectedFlag" :class="'fi fi-' + selectedFlag"></span>
                <span x-text="selectedLabel || 'Select Timezone'" class="text-sm" :class="{ 'text-gray-400': !selectedValue }"></span>
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
            <div class="p-2 border-b border-gray-100">
                <input type="text"
                       x-model="search"
                       @click.stop
                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                       placeholder="Search timezone or country...">
            </div>
            <div class="max-h-60 overflow-y-auto">
                <template x-for="item in filteredItems" :key="item.timezone">
                    <div @click="selectItem(item)"
                         class="tf-dropdown-item-sm">
                        <span :class="'fi fi-' + item.flag" class="mr-2"></span>
                        <span x-text="item.timezone + ' (' + item.name + ')'"></span>
                    </div>
                </template>
                <div x-show="filteredItems.length === 0" class="px-4 py-2 text-gray-500 text-sm">
                    No timezones found
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

<script>
    function timezoneDropdown_{{ $id }}() {
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
                    const countries = await res.json();

                    // Deduplicate by timezone
                    const seen = {};
                    countries.forEach(c => {
                        if (c.timezone && !seen[c.timezone]) {
                            seen[c.timezone] = true;
                            this.items.push({
                                timezone: c.timezone,
                                flag: c.flag,
                                name: c.name
                            });
                        }
                    });
                } catch (e) {
                    console.error('Error loading timezones:', e);
                    return;
                }

                if (this.selectedValue) {
                    const match = this.items.find(c => c.timezone === this.selectedValue);
                    if (match) {
                        this.selectedLabel = match.timezone + ' (' + match.name + ')';
                        this.selectedFlag = match.flag;
                    }
                }

                // Listen for country-changed events
                window.addEventListener('country-changed', (e) => {
                    const tz = e.detail.timezone;
                    if (!tz) return;
                    const match = this.items.find(c => c.timezone === tz);
                    if (match) {
                        this.selectedValue = match.timezone;
                        this.selectedLabel = match.timezone + ' (' + match.name + ')';
                        this.selectedFlag = match.flag;
                    }
                });
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
                    c.timezone.toLowerCase().includes(term) ||
                    c.name.toLowerCase().includes(term)
                );
            },

            selectItem(item) {
                this.selectedValue = item.timezone;
                this.selectedLabel = item.timezone + ' (' + item.name + ')';
                this.selectedFlag = item.flag;
                this.open = false;
                this.search = '';
            }
        }
    }
</script>
