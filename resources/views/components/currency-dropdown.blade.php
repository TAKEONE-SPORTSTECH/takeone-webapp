@props(['name' => 'currency', 'id' => 'currency', 'value' => '', 'required' => false, 'error' => null, 'label' => 'Currency'])

<div class="mb-4" x-data="currencyDropdown_{{ $id }}()" x-init="init()">
    <label class="block text-sm font-medium text-gray-600 mb-1">
        {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
    </label>
    <div class="relative">
        <button type="button"
                @click="toggle()"
                @click.away="open = false"
                x-ref="trigger"
                class="w-full px-4 py-3 text-base border-2 rounded-xl bg-white/80 shadow-inner transition-all duration-300 focus:bg-white focus:ring-4 focus:ring-primary/10 focus:outline-none flex items-center justify-between cursor-pointer {{ $error ? 'border-red-500' : 'border-primary/20 focus:border-primary' }}">
            <span class="flex items-center gap-2">
                <span x-show="selectedFlag" :class="'fi fi-' + selectedFlag"></span>
                <span x-text="selectedLabel || 'Select Currency'" class="text-sm" :class="{ 'text-gray-400': !selectedValue }"></span>
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
            <div class="p-2 border-b border-gray-100">
                <input type="text"
                       x-model="search"
                       @click.stop
                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                       placeholder="Search currency or country...">
            </div>
            <div class="max-h-60 overflow-y-auto">
                <template x-for="item in filteredItems" :key="item.currency">
                    <div @click="selectItem(item)"
                         class="px-4 py-2 hover:bg-primary hover:text-white cursor-pointer flex items-center transition-colors text-sm">
                        <span :class="'fi fi-' + item.flag" class="mr-2"></span>
                        <span x-text="item.name + ' – ' + item.currency"></span>
                    </div>
                </template>
                <div x-show="filteredItems.length === 0" class="px-4 py-2 text-gray-500 text-sm">
                    No currencies found
                </div>
            </div>
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
    function currencyDropdown_{{ $id }}() {
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

                    // Deduplicate by currency code
                    const seen = {};
                    countries.forEach(c => {
                        if (c.currency && !seen[c.currency]) {
                            seen[c.currency] = true;
                            this.items.push({
                                currency: c.currency,
                                currency_symbol: c.currency_symbol || '',
                                flag: c.flag,
                                name: c.name
                            });
                        }
                    });
                } catch (e) {
                    console.error('Error loading currencies:', e);
                    return;
                }

                if (this.selectedValue) {
                    const match = this.items.find(c => c.currency === this.selectedValue);
                    if (match) {
                        this.selectedLabel = match.name + ' – ' + match.currency;
                        this.selectedFlag = match.flag;
                    }
                }

                // Listen for country-changed events
                window.addEventListener('country-changed', (e) => {
                    const currency = e.detail.currency;
                    if (!currency) return;
                    const match = this.items.find(c => c.currency === currency);
                    if (match) {
                        this.selectedValue = match.currency;
                        this.selectedLabel = match.name + ' – ' + match.currency;
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
                    c.name.toLowerCase().includes(term) ||
                    c.currency.toLowerCase().includes(term)
                );
            },

            selectItem(item) {
                this.selectedValue = item.currency;
                this.selectedLabel = item.name + ' – ' + item.currency;
                this.selectedFlag = item.flag;
                this.open = false;
                this.search = '';
            }
        }
    }
</script>
