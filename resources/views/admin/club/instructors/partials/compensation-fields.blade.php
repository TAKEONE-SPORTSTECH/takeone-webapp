{{-- Shared compensation UI for the mobile add/edit instructor sheets.
     Expects Alpine state: compType ('volunteer'|'paid'), wageAmount, wagePeriod, staffType. --}}
<div class="pt-1 space-y-3">
    <div class="flex items-center gap-2">
        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('admin.ins_staff_type') }}</span>
        <div class="flex-1 h-px bg-gray-100"></div>
    </div>
    <x-select-menu model="staffType" :options="[
        ['value' => 'instructor', 'label' => __('admin.ins_staff_type_instructor')],
        ['value' => 'secretary',  'label' => __('admin.ins_staff_type_secretary')],
        ['value' => 'operator',   'label' => __('admin.ins_staff_type_operator')],
        ['value' => 'cleaner',    'label' => __('admin.ins_staff_type_cleaner')],
        ['value' => 'other',      'label' => __('admin.ins_staff_type_other')],
    ]" />

    <div class="flex items-center gap-2 pt-1">
        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('admin.ins_compensation') }}</span>
        <div class="flex-1 h-px bg-gray-100"></div>
    </div>

    <div class="grid grid-cols-2 gap-1 p-1 bg-muted rounded-2xl text-sm font-semibold">
        <button type="button" @click="compType = 'volunteer'" :class="compType === 'volunteer' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="m-press rounded-xl py-2 transition-colors">
            <i class="bi bi-heart mr-1"></i>{{ __('admin.ins_volunteer') }}
        </button>
        <button type="button" @click="compType = 'paid'" :class="compType === 'paid' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground'" class="m-press rounded-xl py-2 transition-colors">
            <i class="bi bi-cash-coin mr-1"></i>{{ __('admin.ins_paid') }}
        </button>
    </div>

    <div x-show="compType === 'paid'"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         class="grid grid-cols-2 gap-3">
        <div>
            <label class="form-label">{{ __('admin.ins_wage_amount') }}</label>
            <input type="number" min="0" step="0.01" x-model="wageAmount" placeholder="0.00" class="form-control">
        </div>
        <div>
            <label class="form-label">{{ __('admin.ins_wage_period') }}</label>
            <x-select-menu model="wagePeriod" :options="[
                ['value' => 'monthly', 'label' => __('admin.ins_per_month')],
                ['value' => 'session', 'label' => __('admin.ins_per_session')],
                ['value' => 'hourly',  'label' => __('admin.ins_per_hour')],
            ]" />
        </div>
    </div>

    <input type="hidden" name="staff_type" :value="staffType">
    <input type="hidden" name="compensation_type" :value="compType">
    <input type="hidden" name="wage_amount" :value="compType === 'paid' ? wageAmount : ''">
    <input type="hidden" name="wage_period" :value="compType === 'paid' ? wagePeriod : ''">
</div>
