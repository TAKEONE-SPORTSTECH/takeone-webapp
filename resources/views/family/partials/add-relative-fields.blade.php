{{--
    Shared add-relative form body (Alpine state from ftAddRelativeData).
    Custom controls only — no native <select> / date popups (Design Rule #4).
    Used inside the mobile bottom-sheet and the desktop modal.
--}}

{{-- Relation type --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Relationship') }}</label>
    <div class="grid grid-cols-3 gap-2">
        <template x-for="opt in [
            { v: 'parent', label: '{{ __('Parent') }}', icon: 'bi-arrow-up-circle' },
            { v: 'spouse', label: '{{ __('Spouse') }}', icon: 'bi-heart' },
            { v: 'child',  label: '{{ __('Child') }}',  icon: 'bi-arrow-down-circle' }
        ]" :key="opt.v">
            <button type="button" @click="type = opt.v"
                :class="type === opt.v ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-gray-600'"
                class="flex flex-col items-center gap-1 py-3 rounded-xl border-2 font-medium text-sm transition">
                <i class="bi text-lg" :class="opt.icon"></i>
                <span x-text="opt.label"></span>
            </button>
        </template>
    </div>
</div>

{{-- Name --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Full name') }}</label>
    <input type="text" x-model="full_name" maxlength="120"
        placeholder="{{ __('e.g. Sarah Ahmed') }}"
        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
</div>

{{-- Gender toggle --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Gender') }}</label>
    <div class="grid grid-cols-2 gap-2">
        <button type="button" @click="gender = 'm'"
            :class="gender === 'm' ? 'border-blue-400 bg-blue-50 text-blue-600' : 'border-gray-200 text-gray-600'"
            class="flex items-center justify-center gap-2 py-2.5 rounded-xl border-2 font-medium text-sm transition">
            <i class="bi bi-gender-male"></i>{{ __('Male') }}
        </button>
        <button type="button" @click="gender = 'f'"
            :class="gender === 'f' ? 'border-pink-400 bg-pink-50 text-pink-600' : 'border-gray-200 text-gray-600'"
            class="flex items-center justify-center gap-2 py-2.5 rounded-xl border-2 font-medium text-sm transition">
            <i class="bi bi-gender-female"></i>{{ __('Female') }}
        </button>
    </div>
</div>

{{-- Marriage state (spouse only) --}}
<div x-show="type === 'spouse'" x-transition>
    <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Status') }}</label>
    <div class="flex flex-wrap gap-2">
        <template x-for="st in ['married', 'partner', 'engaged', 'divorced', 'widowed']" :key="st">
            <button type="button" @click="state = st"
                :class="state === st ? 'bg-primary text-white' : 'bg-muted text-gray-600'"
                class="px-3 py-1.5 rounded-full text-xs font-medium capitalize transition" x-text="st"></button>
        </template>
    </div>
</div>

{{-- Birth year (plain number — avoids native date popup) --}}
<div>
    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Year of birth') }} <span class="text-muted-foreground font-normal">({{ __('optional') }})</span></label>
    <input type="number" x-model="birth_year" min="1850" max="2100" inputmode="numeric"
        placeholder="{{ __('e.g. 1985') }}"
        class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
</div>

{{-- Deceased --}}
<label class="flex items-center justify-between py-1 cursor-pointer">
    <span class="text-sm font-medium text-gray-700">{{ __('Deceased') }}</span>
    <button type="button" @click="is_deceased = !is_deceased"
        :class="is_deceased ? 'bg-primary' : 'bg-gray-200'"
        class="relative w-11 h-6 rounded-full transition-colors">
        <span :class="is_deceased ? 'translate-x-5' : 'translate-x-0.5'"
              class="absolute top-0.5 left-0 w-5 h-5 bg-white rounded-full shadow transition-transform"></span>
    </button>
</label>
