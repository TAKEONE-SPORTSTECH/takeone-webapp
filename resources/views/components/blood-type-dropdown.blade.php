@props(['name' => 'blood_type', 'id' => 'blood_type', 'value' => '', 'required' => false, 'error' => null, 'label' => 'Blood Type'])

<div class="mb-4" x-data="bloodTypeDropdown_{{ $id }}()">
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
                <span x-show="selectedValue" class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold" :class="selectedBg" x-html="selectedIcon"></span>
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
             class="absolute left-0 right-0 z-50 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden max-h-64 overflow-y-auto">
            <template x-for="item in items" :key="item.value">
                <div @click="selectItem(item)"
                     class="px-4 py-2.5 hover:bg-primary hover:text-white cursor-pointer flex items-center gap-3 transition-colors text-sm"
                     :class="selectedValue === item.value ? 'bg-primary/5 font-semibold' : ''">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold" :class="item.bg" x-html="item.icon"></span>
                    <span x-text="item.label"></span>
                    <span class="ml-auto text-xs opacity-60" x-text="item.desc"></span>
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
    function bloodTypeDropdown_{{ $id }}() {
        return {
            open: false,
            dropUp: false,
            selectedValue: '{{ $value }}',
            selectedLabel: '',
            selectedIcon: '',
            selectedBg: '',
            items: [
                { value: 'A+',  label: 'A+',  icon: '<i class="bi bi-droplet-fill"></i>', bg: 'bg-red-100 text-red-600',    desc: 'Universal platelet' },
                { value: 'A-',  label: 'A-',  icon: '<i class="bi bi-droplet-fill"></i>', bg: 'bg-red-50 text-red-400',     desc: '' },
                { value: 'B+',  label: 'B+',  icon: '<i class="bi bi-droplet-fill"></i>', bg: 'bg-orange-100 text-orange-600', desc: '' },
                { value: 'B-',  label: 'B-',  icon: '<i class="bi bi-droplet-fill"></i>', bg: 'bg-orange-50 text-orange-400',  desc: '' },
                { value: 'AB+', label: 'AB+', icon: '<i class="bi bi-droplet-fill"></i>', bg: 'bg-purple-100 text-purple-600', desc: 'Universal recipient' },
                { value: 'AB-', label: 'AB-', icon: '<i class="bi bi-droplet-fill"></i>', bg: 'bg-purple-50 text-purple-400',  desc: '' },
                { value: 'O+',  label: 'O+',  icon: '<i class="bi bi-droplet-fill"></i>', bg: 'bg-green-100 text-green-600',   desc: 'Most common' },
                { value: 'O-',  label: 'O-',  icon: '<i class="bi bi-droplet-fill"></i>', bg: 'bg-emerald-100 text-emerald-600', desc: 'Universal donor' },
                { value: 'Unknown', label: 'Unknown', icon: '<i class="bi bi-question-circle"></i>', bg: 'bg-gray-100 text-gray-500', desc: '' }
            ],

            init() {
                if (this.selectedValue) {
                    const match = this.items.find(i => i.value === this.selectedValue);
                    if (match) {
                        this.selectedLabel = match.label;
                        this.selectedIcon = match.icon;
                        this.selectedBg = match.bg;
                    }
                }
            },

            toggle() {
                if (!this.open) {
                    const rect = this.$refs.trigger.getBoundingClientRect();
                    const spaceBelow = window.innerHeight - rect.bottom;
                    this.dropUp = spaceBelow < 250;
                }
                this.open = !this.open;
            },

            selectItem(item) {
                this.selectedValue = item.value;
                this.selectedLabel = item.label;
                this.selectedIcon = item.icon;
                this.selectedBg = item.bg;
                this.open = false;
            }
        }
    }
</script>
