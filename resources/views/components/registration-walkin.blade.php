@props([
    'club',
    'packages' => [],
    'eventName' => 'open-walkin-modal',
])

@php
    $currency = $club->currency ?? 'BHD';
    $enrollmentFee = $club->enrollment_fee ?? 0;
    $vatPercentage = $club->vat_percentage ?? 0;
    $clubId = $club->id;
@endphp

<!-- Walk-In Registration Modal -->
<div x-data="walkInRegistration()" x-init="init()" x-show="open" x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     x-on:{{ $eventName }}.window="openModal($event.detail)"
     @keydown.escape.window="open && closeWalkIn()">

    <!-- Backdrop -->
    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50" @click="closeWalkIn()"></div>

    {{-- Mobile: full-height sheet (items-stretch, p-0). Desktop (sm+): centered card. --}}
    <div class="flex min-h-[100dvh] items-stretch sm:items-center justify-center p-0 sm:p-4">
        <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
             class="relative bg-white shadow-xl w-full max-w-3xl flex flex-col overflow-hidden rounded-none sm:rounded-2xl max-h-[100dvh] sm:max-h-[90vh]" @click.stop>

            <!-- Header -->
            <div class="flex-shrink-0 px-5 sm:px-6 py-4 border-b border-gray-100">
                <h3 class="text-lg sm:text-xl font-bold text-gray-900" x-text="registrantType === 'child' ? 'Register Child' : 'Walk-In Registration'"></h3>
                <p class="text-sm text-gray-500 mt-1">{{ __('shared.components_registration_walkin_step') }} <span x-text="currentStepIndex"></span> {{ __('shared.components_registration_walkin_of') }} <span x-text="visibleSteps.length"></span>: <span x-text="currentStepLabel"></span></p>
            </div>

            <!-- Step Indicator (driven by visibleSteps so the Child flow skips the children step) -->
            <div class="flex-shrink-0 flex items-center justify-center gap-1.5 sm:gap-2 py-3 sm:py-4 px-4 sm:px-6 bg-gray-50">
                <template x-for="(vs, idx) in visibleSteps" :key="vs.step">
                    <div class="flex items-center gap-1.5 sm:gap-2">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full flex items-center justify-center font-semibold transition-colors"
                             :class="vs.step <= step ? 'bg-purple-500 text-white' : 'bg-gray-200 text-gray-500'">
                            <template x-if="vs.step < step">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            </template>
                            <template x-if="vs.step >= step">
                                <span x-text="idx + 1"></span>
                            </template>
                        </div>
                        <div x-show="idx < visibleSteps.length - 1" class="h-0.5 w-6 sm:w-12 transition-colors" :class="vs.step < step ? 'bg-purple-500' : 'bg-gray-200'"></div>
                    </div>
                </template>
            </div>

            {{-- Mobile: body flexes to fill the sheet & scrolls. Desktop: capped at 60vh. --}}
            <div class="flex-1 min-h-0 overflow-y-auto sm:flex-none sm:max-h-[60vh]">
                <!-- Step 1: Personal Information -->
                <div x-show="step === 1" class="p-4 sm:p-6 space-y-5 sm:space-y-6">
                    <div class="bg-gray-50 rounded-xl p-4">
                        <h4 class="flex items-center gap-2 font-semibold text-gray-900 mb-4">
                            <i class="bi text-purple-500 text-lg" :class="registrantType === 'child' ? 'bi-balloon' : 'bi-person'"></i>
                            <span x-text="registrantType === 'child' ? 'Child Details' : 'Personal Information'"></span>
                        </h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.components_registration_walkin_full_name') }} <span class="text-red-500">*</span></label>
                                <input type="text" x-model="data.guardian.name" @input="errors.name = ''"
                                       :class="errors.name ? 'border-red-400 ring-2 ring-red-100' : 'border-gray-200'"
                                       class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="{{ __('shared.components_registration_walkin_ph_john_doe') }}">
                                <span x-show="errors.name" x-text="errors.name" class="text-red-500 text-xs mt-1 block"></span>
                            </div>
                            <div x-show="registrantType === 'guardian'">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.components_registration_walkin_email') }} <span class="text-gray-400">{{ __('shared.components_registration_walkin_optional') }}</span></label>
                                <input type="email" x-model="data.guardian.email" @input="errors.email = ''"
                                       :class="errors.email ? 'border-red-400 ring-2 ring-red-100' : 'border-gray-200'"
                                       class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="{{ __('shared.components_registration_walkin_ph_member_email') }}">
                                <p class="text-xs text-gray-400 mt-1">{{ __('shared.components_registration_walkin_email_claim_hint') }}</p>
                                <span x-show="errors.email" x-text="errors.email" class="text-red-500 text-xs mt-1 block"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.components_registration_walkin_phone_number') }} <span class="text-red-500">*</span></label>
                                <x-country-code-dropdown name="walkIn_countryCode" id="walkIn_countryCode" value="+973" :required="true" :syncWithCountry="false">
                                    <input type="text" id="walkIn_phone" x-model="data.guardian.phone" @input="errors.phone = ''"
                                           :class="errors.phone ? 'ring-2 ring-red-100' : ''"
                                           class="w-full px-4 py-3 text-base bg-transparent focus:outline-none" placeholder="{{ __('shared.components_registration_walkin_ph_phone_number') }}">
                                </x-country-code-dropdown>
                                <span x-show="errors.phone" x-text="errors.phone" class="text-red-500 text-xs mt-1 block"></span>
                            </div>
                            <div class="sm:col-span-2">
                                <x-birthdate-dropdown name="walkIn_dob" id="walkIn_dob" label="{{ __('shared.components_registration_walkin_date_of_birth') }}" :required="true" :minAge="3" :maxAge="100" />
                                <span x-show="errors.dob" x-text="errors.dob" class="text-red-500 text-xs mt-1 block"></span>
                            </div>
                            <div>
                                <x-gender-dropdown name="walkIn_gender" id="walkIn_gender" label="{{ __('shared.components_registration_walkin_gender') }}" :required="true" />
                                <span x-show="errors.gender" x-text="errors.gender" class="text-red-500 text-xs mt-1 block"></span>
                            </div>
                            <div>
                                <x-country-dropdown name="walkIn_nationality" id="walkIn_nationality" label="{{ __('shared.components_registration_walkin_nationality') }}" :required="false" />
                                <span x-show="errors.nationality" x-text="errors.nationality" class="text-red-500 text-xs mt-1 block"></span>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.components_registration_walkin_address_optional') }}</label>
                                <input type="text" x-model="data.guardian.address" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="{{ __('shared.components_registration_walkin_ph_street_address') }}">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Guardian & Children -->
                <div x-show="step === 2" class="p-4 sm:p-6 space-y-5 sm:space-y-6">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">{{ __('shared.components_registration_walkin_registering_family_question') }}</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <button type="button" @click="setIsGuardian(true)"
                                    class="px-4 py-3 border-2 rounded-lg font-medium text-gray-700 hover:border-purple-400 transition-colors flex items-center justify-center gap-2"
                                    :class="data.isGuardian === true ? 'border-purple-500 bg-purple-50' : 'border-gray-200'">
                                <svg x-show="data.isGuardian === true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                {{ __('shared.components_registration_walkin_yes_guardian') }}
                            </button>
                            <button type="button" @click="setIsGuardian(false)"
                                    class="px-4 py-3 border-2 rounded-lg font-medium text-gray-700 hover:border-purple-400 transition-colors flex items-center justify-center gap-2"
                                    :class="data.isGuardian === false ? 'border-purple-500 bg-purple-50' : 'border-gray-200'">
                                <svg x-show="data.isGuardian === false" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                {{ __('shared.components_registration_walkin_no_just_myself') }}
                            </button>
                        </div>
                    </div>

                    <!-- Children Section -->
                    <div x-show="data.isGuardian === true" class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-semibold text-gray-900">{{ __('shared.components_registration_walkin_family_members') }}</h4>
                            <button type="button" @click="addChild()" class="inline-flex items-center px-3 py-1.5 bg-purple-500 text-white text-sm font-medium rounded-lg hover:bg-purple-600 transition-colors">
                                <i class="bi bi-plus me-1"></i> {{ __('shared.components_registration_walkin_add_member') }}
                            </button>
                        </div>
                        <template x-for="(child, index) in data.children" :key="child.id">
                            <div class="bg-white border border-gray-200 rounded-xl p-4">
                                <div class="flex justify-between items-start mb-4">
                                    <h5 class="font-medium text-gray-900" x-text="relationshipLabel(child.relationship) + ' ' + (index + 1)"></h5>
                                    <button type="button" @click="removeChild(child.id)" class="text-gray-400 hover:text-red-500 transition-colors">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('shared.components_registration_walkin_relationship') }} <span class="text-red-500">*</span></label>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="rel in relationshipOptions" :key="rel.value">
                                            <button type="button" @click="setChildRelationship(child, rel.value)"
                                                    class="px-3 py-1.5 rounded-full text-sm font-medium border transition-colors flex items-center gap-1.5"
                                                    :class="child.relationship === rel.value ? 'border-purple-500 bg-purple-50 text-purple-700' : 'border-gray-200 text-gray-600 hover:border-purple-300'">
                                                <i class="bi" :class="rel.icon"></i><span x-text="rel.label"></span>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('shared.components_registration_walkin_name') }} <span class="text-red-500">*</span></label>
                                        <input type="text" x-model="child.name" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500" placeholder="{{ __('shared.components_registration_walkin_ph_family_member_name') }}">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('shared.components_registration_walkin_date_of_birth') }} <span class="text-red-500">*</span></label>
                                        <input type="date" x-model="child.dob" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('shared.components_registration_walkin_gender') }} <span class="text-red-500">*</span></label>
                                        <x-gender-toggle model="child.gender" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('shared.components_registration_walkin_nationality') }} <span class="text-gray-400">{{ __('shared.components_registration_walkin_optional') }}</span></label>
                                        <x-select-menu model="child.nationality"
                                            placeholder="{{ __('shared.components_registration_walkin_select') }}"
                                            :options="[
                                                ['value' => 'Bahrain', 'label' => __('shared.components_registration_walkin_bahrain')],
                                                ['value' => 'Saudi Arabia', 'label' => __('shared.components_registration_walkin_saudi_arabia')],
                                                ['value' => 'UAE', 'label' => __('shared.components_registration_walkin_uae')],
                                                ['value' => 'Kuwait', 'label' => __('shared.components_registration_walkin_kuwait')],
                                                ['value' => 'India', 'label' => __('shared.components_registration_walkin_india')],
                                                ['value' => 'Pakistan', 'label' => __('shared.components_registration_walkin_pakistan')],
                                            ]" />
                                    </div>
                                    {{-- A child needs no email/password — just a phone number for contact. --}}
                                    <div class="col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('shared.components_registration_walkin_phone_number') }} <span class="text-red-500">*</span></label>
                                        <div class="flex gap-2">
                                            <div class="w-28 flex-shrink-0">
                                                <x-select-menu model="child.countryCode"
                                                    :options="[
                                                        ['value' => '+973', 'label' => '🇧🇭 +973'],
                                                        ['value' => '+966', 'label' => '🇸🇦 +966'],
                                                        ['value' => '+971', 'label' => '🇦🇪 +971'],
                                                        ['value' => '+965', 'label' => '🇰🇼 +965'],
                                                        ['value' => '+974', 'label' => '🇶🇦 +974'],
                                                        ['value' => '+968', 'label' => '🇴🇲 +968'],
                                                        ['value' => '+1', 'label' => '+1'],
                                                        ['value' => '+44', 'label' => '+44'],
                                                        ['value' => '+91', 'label' => '+91'],
                                                        ['value' => '+92', 'label' => '+92'],
                                                    ]" />
                                            </div>
                                            <input type="tel" x-model="child.phone" inputmode="tel"
                                                   class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500" placeholder="{{ __('shared.components_registration_walkin_ph_phone_number') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Step 3: Package Selection -->
                <div x-show="step === 3" class="p-4 sm:p-6 space-y-5 sm:space-y-6">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">{{ __('shared.components_registration_walkin_select_packages_each_person') }}</h4>
                        <p class="text-sm text-gray-500">{{ __('shared.components_registration_walkin_package_selection_optional') }}</p>
                    </div>

                    <div class="space-y-4 max-h-80 overflow-y-auto">
                        <template x-for="person in data.people" :key="person.id">
                            <div class="bg-white border border-gray-200 rounded-xl p-4">
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex items-center gap-3">
                                        <input type="checkbox" :checked="person.isJoining" @change="togglePersonJoining(person.id)" class="w-5 h-5 text-purple-500 rounded border-gray-300 focus:ring-purple-500">
                                        <div>
                                            <h5 class="font-semibold text-gray-900" x-text="person.name"></h5>
                                            <p class="text-sm text-gray-500">
                                                <span x-text="person.type === 'guardian' ? 'Guardian' : 'Child'"></span> &bull;
                                                {{ __('shared.components_registration_walkin_age') }} <span x-text="calculateAge(person.dob)"></span> &bull;
                                                <span x-text="person.gender"></span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div x-show="person.isJoining" class="ps-8 space-y-2">
                                    {{-- Waive the one-time joining fee for existing/legacy members who
                                         predate the system (they already paid the club long ago). --}}
                                    <label class="flex items-start gap-2.5 mb-3 p-2.5 rounded-lg bg-gray-50 border border-gray-100 cursor-pointer select-none">
                                        <input type="checkbox" x-model="person.waiveRegFee" class="mt-0.5 w-4 h-4 text-purple-500 rounded border-gray-300 focus:ring-purple-500">
                                        <span class="flex-1">
                                            <span class="block text-sm font-medium text-gray-700">{{ __('shared.components_registration_walkin_waive_reg_fee') }}</span>
                                            <span class="block text-xs text-gray-400">{{ __('shared.components_registration_walkin_waive_reg_fee_hint') }}</span>
                                        </span>
                                    </label>
                                    <p class="text-sm font-medium text-gray-700 mb-2">{{ __('shared.components_registration_walkin_select_packages_optional') }}</p>
                                    <template x-if="getEligiblePackages(person).length === 0">
                                        <p class="text-sm text-gray-500 italic">{{ __('shared.components_registration_walkin_no_packages_age_gender') }}</p>
                                    </template>
                                    <template x-for="pkg in getEligiblePackages(person)" :key="pkg.id">
                                        <div>
                                            <div @click="togglePackageForPerson(person.id, pkg.id)"
                                                 class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer hover:border-purple-400 transition-colors"
                                                 :class="person.selectedPackageIds.includes(pkg.id) ? 'border-purple-500 bg-purple-50' : 'border-gray-200'">
                                                <input type="checkbox" :checked="person.selectedPackageIds.includes(pkg.id)" class="mt-1 w-4 h-4 text-purple-500 rounded" @click.stop>
                                                <div class="flex-1">
                                                    <div class="flex items-center justify-between">
                                                        <span class="font-medium text-gray-900" x-text="pkg.name"></span>
                                                        <span class="text-sm font-semibold text-purple-600" x-text="currencySymbol + ' ' + parseFloat(pkg.price).toFixed(2)"></span>
                                                    </div>
                                                    <p x-show="pkg.description" class="text-sm text-gray-500 mt-1" x-text="pkg.description"></p>
                                                </div>
                                            </div>

                                            {{-- Equipment chips for this package (shown when the package is selected). --}}
                                            <div x-show="person.selectedPackageIds.includes(pkg.id) && (pkg.equipment || []).length > 0"
                                                 class="mt-2 ms-7 p-3 rounded-lg bg-gray-50 border border-gray-100">
                                                <p class="text-xs font-medium text-gray-500 mb-2 flex items-center gap-1.5">
                                                    <i class="bi bi-box-seam text-purple-500"></i> {{ __('shared.components_registration_walkin_equipment_for_activity') }}
                                                </p>
                                                <div class="space-y-2.5">
                                                    <template x-for="eq in (pkg.equipment || [])" :key="eq.id">
                                                        <div :class="isEquipmentOwned(person, eq.id) ? 'opacity-60' : ''">
                                                            {{-- Plain gear: checkbox --}}
                                                            <label x-show="!eq.has_variants" class="flex items-center gap-2.5 cursor-pointer select-none py-1" :class="isEquipmentOwned(person, eq.id) ? 'pointer-events-none' : ''">
                                                                <input type="checkbox" :checked="person.selectedEquipmentIds.includes(eq.id)"
                                                                       :disabled="isEquipmentOwned(person, eq.id)"
                                                                       @change="toggleEquipmentForPerson(person.id, eq.id)"
                                                                       class="w-4 h-4 text-purple-500 rounded border-gray-300 focus:ring-purple-500">
                                                                <span class="flex-1 text-sm text-gray-700" :class="isEquipmentOwned(person, eq.id) ? 'line-through' : ''" x-text="eq.name"></span>
                                                                <span x-show="eq.is_required && !isEquipmentOwned(person, eq.id)" class="text-[10px] px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700 font-medium">{{ __('shared.components_registration_walkin_required') }}</span>
                                                                <span class="text-sm font-medium text-gray-600" x-text="currencySymbol + ' ' + parseFloat(eq.price).toFixed(2)"></span>
                                                            </label>

                                                            {{-- Variant gear: name + selectable chips --}}
                                                            <div x-show="eq.has_variants">
                                                                <div class="flex items-center gap-2 mb-1.5">
                                                                    <span class="flex-1 text-sm font-medium text-gray-700" :class="isEquipmentOwned(person, eq.id) ? 'line-through' : ''" x-text="eq.name"></span>
                                                                    <span x-show="eq.is_required && !isEquipmentOwned(person, eq.id)" class="text-[10px] px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700 font-medium">{{ __('shared.components_registration_walkin_required') }}</span>
                                                                </div>
                                                                <div class="flex flex-wrap gap-1.5" x-show="!isEquipmentOwned(person, eq.id)">
                                                                    <template x-for="v in (eq.variants || [])" :key="v.id">
                                                                        <button type="button" @click="selectVariantForPerson(person.id, eq, v.id)"
                                                                                :disabled="!v.in_stock || v.owned"
                                                                                class="px-2.5 py-1.5 rounded-lg border text-xs font-medium transition-colors inline-flex items-center gap-1.5"
                                                                                :class="equipmentVariantId(person, eq.id) === v.id
                                                                                            ? 'border-purple-500 bg-purple-50 text-purple-700'
                                                                                            : 'border-gray-200 bg-white text-gray-700 hover:border-purple-300'"
                                                                                :style="(!v.in_stock || v.owned) ? 'opacity:.45;cursor:not-allowed;text-decoration:line-through' : ''">
                                                                            <span x-show="v.color_hex" class="w-2.5 h-2.5 rounded-full border border-gray-200" :style="`background:${v.color_hex}`"></span>
                                                                            <span x-text="v.label"></span>
                                                                            <span class="text-gray-400 font-normal" x-text="'· ' + parseFloat(v.price).toFixed(2)"></span>
                                                                            <span x-show="v.owned" class="text-[9px] px-1 py-0.5 rounded-full bg-green-100 text-green-700">{{ __('shared.components_registration_walkin_owned') }}</span>
                                                                        </button>
                                                                    </template>
                                                                </div>
                                                            </div>

                                                            {{-- "I already have it" — removes the item from the bill --}}
                                                            <label class="flex items-center gap-2 cursor-pointer select-none mt-1.5 ps-0.5">
                                                                <input type="checkbox" :checked="isEquipmentOwned(person, eq.id)"
                                                                       @change="toggleOwnedForPerson(person.id, eq)"
                                                                       class="w-3.5 h-3.5 text-green-600 rounded border-gray-300 focus:ring-green-500">
                                                                <span class="text-[11px] font-medium" :class="isEquipmentOwned(person, eq.id) ? 'text-green-700' : 'text-gray-500'">{{ __('shared.components_registration_walkin_i_already_have_it') }}</span>
                                                            </label>
                                                        </div>
                                                    </template>
                                                    <p class="text-[11px] text-gray-500 mt-2.5 flex items-start gap-1.5">
                                                        <i class="bi bi-info-circle text-purple-500 mt-0.5"></i>
                                                        <span>{{ __('shared.components_registration_walkin_trains_elsewhere_hint') }}</span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Cost Summary -->
                    <div class="bg-gray-50 rounded-xl p-4">
                        <h4 class="font-semibold text-gray-900 mb-3">{{ __('shared.components_registration_walkin_cost_summary') }}</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ __('shared.components_registration_walkin_registration_fee') }} (<span x-text="totals.memberCount"></span> {{ __('shared.components_registration_walkin_members') }})</span>
                                <span x-text="currencySymbol + ' ' + totals.enrollmentTotal.toFixed(2)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ __('shared.components_registration_walkin_packages_total') }}</span>
                                <span x-text="currencySymbol + ' ' + totals.packagesTotal.toFixed(2)"></span>
                            </div>
                            <div class="flex justify-between" x-show="totals.equipmentTotal > 0">
                                <span class="text-gray-600">{{ __('shared.components_registration_walkin_equipment_total') }}</span>
                                <span x-text="currencySymbol + ' ' + totals.equipmentTotal.toFixed(2)"></span>
                            </div>
                            <div class="flex justify-between font-medium pt-2 border-t border-gray-200">
                                <span>{{ __('shared.components_registration_walkin_subtotal') }}</span>
                                <span x-text="currencySymbol + ' ' + totals.subtotal.toFixed(2)"></span>
                            </div>
                            <div class="pt-2">
                                <label class="text-xs text-gray-500">{{ __('shared.components_registration_walkin_discount_optional') }}</label>
                                <div class="flex gap-2 mt-1">
                                    <button type="button" @click="data.discountType = 'percentage'"
                                            class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm font-medium transition-colors"
                                            :class="data.discountType === 'percentage' ? 'bg-purple-500 text-white' : 'text-gray-700'">%</button>
                                    <button type="button" @click="data.discountType = 'fixed'"
                                            class="px-3 py-1.5 border border-gray-200 rounded-lg text-sm font-medium transition-colors"
                                            :class="data.discountType === 'fixed' ? 'bg-purple-500 text-white' : 'text-gray-700'">{{ $currency }}</button>
                                    <input type="number" x-model.number="data.discountValue" class="flex-1 px-3 py-1.5 border border-gray-200 rounded-lg text-sm" placeholder="0" min="0">
                                </div>
                            </div>
                            <div x-show="totals.discount > 0" class="flex justify-between text-green-600">
                                <span>{{ __('shared.components_registration_walkin_discount') }}</span>
                                <span x-text="'-' + currencySymbol + ' ' + totals.discount.toFixed(2)"></span>
                            </div>
                            @if($vatPercentage > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ __('shared.components_registration_walkin_vat') }} ({{ $vatPercentage }}%)</span>
                                <span x-text="currencySymbol + ' ' + totals.vat.toFixed(2)"></span>
                            </div>
                            @endif
                            <div class="flex justify-between font-bold text-lg pt-3 border-t border-gray-200">
                                <span>{{ __('shared.components_registration_walkin_total_amount') }}</span>
                                <span class="text-purple-600" x-text="currencySymbol + ' ' + totals.grandTotal.toFixed(2)"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Payment Confirmation -->
                <div x-show="step === 4" class="p-4 sm:p-6 space-y-5 sm:space-y-6">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">{{ __('shared.components_registration_walkin_payment_confirmation') }}</h4>
                        <p class="text-sm text-gray-500">{{ __('shared.components_registration_walkin_collect_payment_confirm') }}</p>
                    </div>

                    <div class="bg-purple-50 rounded-xl p-6 text-center">
                        <p class="text-sm text-gray-600 mb-2">{{ __('shared.components_registration_walkin_total_amount_to_collect') }}</p>
                        <p class="text-4xl font-bold text-purple-600" x-text="currencySymbol + ' ' + totals.grandTotal.toFixed(2)"></p>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl p-4">
                        <h5 class="font-semibold text-gray-900 mb-3">{{ __('shared.components_registration_walkin_registration_summary') }}</h5>
                        <div class="space-y-4">
                            <template x-for="person in joiningPeople" :key="person.id">
                                <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                                    <div class="flex items-center justify-between mb-2">
                                        <div>
                                            <p class="font-medium text-gray-900" x-text="person.name"></p>
                                            <p class="text-xs text-gray-500" x-text="person.type === 'guardian' ? 'Guardian' : 'Child'"></p>
                                        </div>
                                        <p class="font-semibold text-gray-900" x-text="currencySymbol + ' ' + getPersonTotal(person).toFixed(2)"></p>
                                    </div>
                                    <div class="ps-4 space-y-1 text-sm">
                                        <div class="flex justify-between text-gray-500" x-show="getPersonRegFee(person) > 0">
                                            <span>{{ __('shared.components_registration_walkin_registration_fee') }}</span>
                                            <span x-text="currencySymbol + ' ' + getPersonRegFee(person).toFixed(2)"></span>
                                        </div>
                                        <template x-if="getPersonPackages(person).length === 0">
                                            <p class="text-gray-400 italic">{{ __('shared.components_registration_walkin_no_packages_selected') }}</p>
                                        </template>
                                        <template x-for="pkg in getPersonPackages(person)" :key="pkg.id">
                                            <div class="flex justify-between text-gray-500">
                                                <span x-text="pkg.name"></span>
                                                <span x-text="currencySymbol + ' ' + parseFloat(pkg.price).toFixed(2)"></span>
                                            </div>
                                        </template>
                                        <template x-for="eq in getPersonEquipment(person)" :key="eq.id">
                                            <div class="flex justify-between text-gray-500">
                                                <span class="flex items-center gap-1"><i class="bi bi-box-seam text-xs"></i><span x-text="eq.name + (eq.variantLabel ? (' — ' + eq.variantLabel) : '')"></span></span>
                                                <span x-text="currencySymbol + ' ' + parseFloat(eq.price).toFixed(2)"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer with Navigation (sticky, safe-area aware on mobile) -->
            <div class="flex-shrink-0 px-5 sm:px-6 py-4 border-t border-gray-100 flex justify-between gap-3 bg-white"
                 style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                <button type="button" @click="prevStep()" x-show="step > 1" class="px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">
                    <i class="bi bi-arrow-left me-2"></i>{{ __('shared.back') }}
                </button>
                <button type="button" @click="closeWalkIn()" x-show="step === 1" class="px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">{{ __('shared.cancel') }}</button>
                <button type="button" @click="nextStep()" x-show="step < 4" class="px-6 py-2.5 bg-purple-500 text-white font-medium rounded-lg hover:bg-purple-600 transition-colors">
                    {{ __('shared.components_registration_walkin_next') }}<i class="bi bi-arrow-right ms-2"></i>
                </button>
                <button type="button" @click="submitRegistration()" x-show="step === 4" :disabled="isSubmitting" class="px-6 py-2.5 bg-green-500 text-white font-medium rounded-lg hover:bg-green-600 transition-colors disabled:opacity-50">
                    <span x-show="!isSubmitting"><i class="bi bi-check-lg me-2"></i>{{ __('shared.components_registration_walkin_confirm_register') }}</span>
                    <span x-show="isSubmitting"><span class="inline-block animate-spin me-2">&#8635;</span>{{ __('shared.components_registration_walkin_processing') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Inline (not @push) so the factory is re-defined when this component is
     swapped into the mobile shell's #shell-content via in-place navigation.
     On desktop the inline script runs identically on full page load. --}}
<script>
function walkInRegistration() {
    return {
        open: false,
        step: 1,
        registrantType: 'guardian',   // 'guardian' (account) | 'child' (standalone, phone only)
        isSubmitting: false,
        currencySymbol: '{{ $currency }}',
        enrollmentFeeAmount: {{ $enrollmentFee }},
        vatPercentageAmount: {{ $vatPercentage }},
        clubId: '{{ $clubId }}',
        availablePackages: @json($packages),

        relationshipOptions: [
            { value: 'son',      label: '{{ __("shared.components_registration_walkin_son") }}',      icon: 'bi-person',        gender: 'Male'   },
            { value: 'daughter', label: '{{ __("shared.components_registration_walkin_daughter") }}', icon: 'bi-person-heart',  gender: 'Female' },
            { value: 'spouse',   label: '{{ __("shared.components_registration_walkin_spouse") }}',   icon: 'bi-heart',         gender: ''       },
            { value: 'other',    label: '{{ __("shared.components_registration_walkin_other") }}',    icon: 'bi-people',        gender: ''       },
        ],
        relationshipLabel(val) {
            const r = this.relationshipOptions.find(o => o.value === val);
            return r ? r.label : '{{ __("shared.components_registration_walkin_member") }}';
        },
        setChildRelationship(child, val) {
            child.relationship = val;
            const r = this.relationshipOptions.find(o => o.value === val);
            if (r && r.gender) child.gender = r.gender;   // pre-fill gender from relationship
        },

        // The visible step sequence differs by type — Child skips the "children" step.
        // `step` stays tied to the markup numbers (1 details · 2 children · 3 packages · 4 payment).
        get visibleSteps() {
            return this.registrantType === 'child'
                ? [{ step: 1, label: '{{ __("shared.components_registration_walkin_child_details") }}' }, { step: 3, label: '{{ __("shared.components_registration_walkin_package_selection") }}' }, { step: 4, label: '{{ __("shared.components_registration_walkin_payment_confirmation") }}' }]
                : [{ step: 1, label: '{{ __("shared.components_registration_walkin_personal_information") }}' }, { step: 2, label: '{!! __("shared.components_registration_walkin_guardian_and_children") !!}' }, { step: 3, label: '{{ __("shared.components_registration_walkin_package_selection") }}' }, { step: 4, label: '{{ __("shared.components_registration_walkin_payment_confirmation") }}' }];
        },
        get currentStepLabel() {
            const s = this.visibleSteps.find(v => v.step === this.step);
            return s ? s.label : '';
        },
        get currentStepIndex() {
            const i = this.visibleSteps.findIndex(v => v.step === this.step);
            return i < 0 ? 1 : i + 1;
        },

        data: {
            guardian: { name: '', email: '', phone: '', countryCode: '+973', dob: '', gender: '', nationality: '', address: '' },
            isGuardian: null,
            children: [],
            people: [],
            discountType: 'percentage',
            discountValue: 0
        },

        errors: {
            name: '', email: '', phone: '', dob: '', gender: '', nationality: ''
        },

        totals: {
            memberCount: 0,
            enrollmentTotal: 0,
            packagesTotal: 0,
            equipmentTotal: 0,
            subtotal: 0,
            discount: 0,
            vat: 0,
            grandTotal: 0
        },

        init() {
            // Expose global function for backwards compatibility
            window.openWalkInModal = () => this.openModal();

            // Recalculate totals reactively when relevant data changes
            this.$watch('data.people', () => this.calculateTotals(), { deep: true });
            this.$watch('data.discountType', () => this.calculateTotals());
            this.$watch('data.discountValue', () => this.calculateTotals());
        },

        openModal(detail) {
            this.resetForm();
            this.registrantType = (detail && detail.type === 'child') ? 'child' : 'guardian';
            this.open = true;
        },

        closeWalkIn() {
            this.open = false;
        },

        resetForm() {
            this.step = 1;
            this.registrantType = 'guardian';
            this.isSubmitting = false;
            this.errors = { name: '', email: '', phone: '', dob: '', gender: '', nationality: '' };
            this.data = {
                guardian: { name: '', email: '', phone: '', countryCode: '+973', dob: '', gender: '', nationality: '', address: '' },
                isGuardian: null,
                children: [],
                people: [],
                discountType: 'percentage',
                discountValue: 0
            };
            this.totals = { memberCount: 0, enrollmentTotal: 0, packagesTotal: 0, equipmentTotal: 0, subtotal: 0, discount: 0, vat: 0, grandTotal: 0 };

            // Reset the hidden inputs from Blade components
            this.$nextTick(() => {
                const dobEl = document.getElementById('walkIn_dob');
                if (dobEl) dobEl.value = '';
                const dobDay = document.getElementById('walkIn_dob_day');
                if (dobDay) dobDay.value = '';
                const dobMonth = document.getElementById('walkIn_dob_month');
                if (dobMonth) dobMonth.value = '';
                const dobYear = document.getElementById('walkIn_dob_year');
                if (dobYear) dobYear.value = '';
                const genderEl = document.getElementById('walkIn_gender');
                if (genderEl) genderEl.value = '';
                // NOTE: do NOT clear walkIn_nationality here. The country dropdown
                // geo-detects a default and keeps it in its own Alpine state; wiping
                // only the DOM value desyncs them — the field shows a country but
                // submits empty, triggering a false "select a nationality" error.
            });
        },

        // --- Step 2: Guardian & Children ---
        setIsGuardian(val) {
            this.data.isGuardian = val;
            if (!val) {
                this.data.children = [];
            }
        },

        addChild() {
            this.data.children.push({
                id: 'child_' + Date.now(),
                name: '',
                dob: '',
                gender: 'Male',
                relationship: 'son',
                nationality: this.data.guardian.nationality || '',
                phone: '',
                countryCode: this.data.guardian.countryCode || '+973'
            });
        },

        removeChild(childId) {
            this.data.children = this.data.children.filter(c => c.id !== childId);
        },

        // --- Step 3: People & Packages ---
        buildPeopleList() {
            this.data.people = [];

            // Child mode: the single registrant IS the child (standalone, no guardian).
            if (this.registrantType === 'child') {
                const g = this.data.guardian;
                this.data.people.push({
                    id: 'child_primary',
                    name: g.name,
                    dob: g.dob,
                    gender: g.gender,
                    nationality: g.nationality,
                    phone: g.phone,
                    countryCode: g.countryCode || '+973',
                    type: 'child',
                    relationship: 'other',
                    isJoining: true,   // single person — auto-selected for packages/payment
                    waiveRegFee: false,
                    selectedPackageIds: [],
                    selectedEquipmentIds: [],
                    selectedVariants: {},
                    ownedEquipmentIds: []
                });
                return;
            }

            if (this.data.guardian.dob && this.data.guardian.gender) {
                this.data.people.push({
                    id: 'guardian',
                    name: this.data.guardian.name,
                    dob: this.data.guardian.dob,
                    gender: this.data.guardian.gender,
                    nationality: this.data.guardian.nationality,
                    type: 'guardian',
                    isJoining: false,
                    waiveRegFee: false,
                    selectedPackageIds: [],
                    selectedEquipmentIds: [],
                    selectedVariants: {},
                    ownedEquipmentIds: []
                });
            }
            this.data.children.forEach(child => {
                if (child.name && child.dob) {
                    this.data.people.push({
                        id: child.id,
                        name: child.name,
                        dob: child.dob,
                        gender: child.gender,
                        nationality: child.nationality,
                        phone: child.phone,
                        countryCode: child.countryCode || '+973',
                        type: 'child',
                        relationship: child.relationship || 'son',
                        isJoining: false,
                        waiveRegFee: false,
                        selectedPackageIds: [],
                        selectedEquipmentIds: [],
                    selectedVariants: {},
                    ownedEquipmentIds: []
                    });
                }
            });
        },

        calculateAge(dob) {
            const today = new Date();
            const birth = new Date(dob);
            let age = today.getFullYear() - birth.getFullYear();
            const m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
            return age;
        },

        getEligiblePackages(person) {
            if (!person.dob) return [];
            const age = this.calculateAge(person.dob);
            return this.availablePackages.filter(pkg => {
                if (pkg.age_min && age < pkg.age_min) return false;
                if (pkg.age_max && age > pkg.age_max) return false;
                if (pkg.gender_restriction === 'male' && person.gender !== 'Male') return false;
                if (pkg.gender_restriction === 'female' && person.gender !== 'Female') return false;
                return true;
            });
        },

        togglePersonJoining(personId) {
            const person = this.data.people.find(p => p.id === personId);
            if (person) {
                person.isJoining = !person.isJoining;
                if (!person.isJoining) { person.selectedPackageIds = []; person.selectedEquipmentIds = []; }
            }
        },

        togglePackageForPerson(personId, packageId) {
            const person = this.data.people.find(p => p.id === personId);
            if (person) {
                const idx = person.selectedPackageIds.indexOf(packageId);
                if (idx > -1) person.selectedPackageIds.splice(idx, 1);
                else person.selectedPackageIds.push(packageId);
                this.syncEquipmentDefaults(person);
            }
        },

        // Equipment offered to a person = unique gear across their selected packages.
        availableEquipmentFor(person) {
            const map = {};
            person.selectedPackageIds.forEach(pkgId => {
                const pkg = this.availablePackages.find(p => p.id == pkgId);
                (pkg && pkg.equipment || []).forEach(eq => { map[eq.id] = eq; });
            });
            return Object.values(map);
        },

        // Re-apply smart defaults after a package toggle: pre-tick required gear the
        // member doesn't already own; drop selections no longer offered.
        syncEquipmentDefaults(person) {
            if (!person.selectedVariants) person.selectedVariants = {};
            if (!person.ownedEquipmentIds) person.ownedEquipmentIds = [];
            const available = this.availableEquipmentFor(person);
            const availableIds = available.map(e => e.id);
            person.selectedEquipmentIds = person.selectedEquipmentIds.filter(id => availableIds.includes(id));
            person.ownedEquipmentIds = person.ownedEquipmentIds.filter(id => availableIds.includes(id));
            Object.keys(person.selectedVariants).forEach(id => {
                if (!availableIds.includes(parseInt(id))) delete person.selectedVariants[id];
            });
            available.forEach(eq => {
                // Members who already own an item: default it to "I already have".
                if (eq.already_owned && !person.ownedEquipmentIds.includes(eq.id)) {
                    person.ownedEquipmentIds.push(eq.id);
                }
                const owned = person.ownedEquipmentIds.includes(eq.id);
                if (eq.is_required && !owned && !person.selectedEquipmentIds.includes(eq.id)) {
                    person.selectedEquipmentIds.push(eq.id);
                }
                // Owned gear is never billed — keep it off the charged list.
                if (owned) {
                    const j = person.selectedEquipmentIds.indexOf(eq.id);
                    if (j > -1) person.selectedEquipmentIds.splice(j, 1);
                    delete person.selectedVariants[eq.id];
                }
                if (eq.has_variants && person.selectedEquipmentIds.includes(eq.id) && !person.selectedVariants[eq.id]) {
                    const dv = this.defaultVariantFor(eq);
                    if (dv) person.selectedVariants[eq.id] = dv.id;
                }
            });
        },

        defaultVariantFor(eq) {
            const vs = (eq.variants || []).filter(v => v.in_stock);
            const pool = vs.length ? vs : (eq.variants || []);
            if (!pool.length) return null;
            return pool.reduce((a, b) => (b.price < a.price ? b : a), pool[0]);
        },

        equipmentVariantId(person, equipmentId) {
            return (person.selectedVariants || {})[equipmentId] || null;
        },

        toggleEquipmentForPerson(personId, equipmentId) {
            const person = this.data.people.find(p => p.id === personId);
            if (!person) return;
            if ((person.ownedEquipmentIds || []).includes(equipmentId)) return;
            const idx = person.selectedEquipmentIds.indexOf(equipmentId);
            if (idx > -1) person.selectedEquipmentIds.splice(idx, 1);
            else person.selectedEquipmentIds.push(equipmentId);
        },

        isEquipmentOwned(person, equipmentId) {
            return (person.ownedEquipmentIds || []).includes(equipmentId);
        },

        // "I already have it" — exclude an item from the bill (overrides required),
        // or fold it back in when un-checked.
        toggleOwnedForPerson(personId, eq) {
            const person = this.data.people.find(p => p.id === personId);
            if (!person) return;
            if (!person.ownedEquipmentIds) person.ownedEquipmentIds = [];
            if (!person.selectedVariants) person.selectedVariants = {};
            const oi = person.ownedEquipmentIds.indexOf(eq.id);
            if (oi === -1) {
                person.ownedEquipmentIds.push(eq.id);
                const j = person.selectedEquipmentIds.indexOf(eq.id);
                if (j > -1) person.selectedEquipmentIds.splice(j, 1);
                delete person.selectedVariants[eq.id];
            } else {
                person.ownedEquipmentIds.splice(oi, 1);
                if (eq.is_required && !person.selectedEquipmentIds.includes(eq.id)) person.selectedEquipmentIds.push(eq.id);
                if (eq.has_variants && person.selectedEquipmentIds.includes(eq.id) && !person.selectedVariants[eq.id]) {
                    const dv = this.defaultVariantFor(eq);
                    if (dv) person.selectedVariants[eq.id] = dv.id;
                }
            }
        },

        // Variant gear: picking a variant ticks the item; tapping the chosen one
        // again unticks it (unless required).
        selectVariantForPerson(personId, eq, variantId) {
            const person = this.data.people.find(p => p.id === personId);
            if (!person) return;
            if (!person.selectedVariants) person.selectedVariants = {};
            const current = person.selectedVariants[eq.id] || null;
            if (current === variantId && !eq.is_required) {
                delete person.selectedVariants[eq.id];
                const i = person.selectedEquipmentIds.indexOf(eq.id);
                if (i > -1) person.selectedEquipmentIds.splice(i, 1);
                return;
            }
            person.selectedVariants[eq.id] = variantId;
            if (!person.selectedEquipmentIds.includes(eq.id)) person.selectedEquipmentIds.push(eq.id);
        },

        // Effective per-person registration fee: the first selected package's
        // override, else the club default. Mirrors the server-side resolver.
        getPersonRegFee(person) {
            // Existing/legacy members already paid their joining fee — waive it.
            if (person.waiveRegFee) return 0;
            const ids = person.selectedPackageIds || [];
            if (ids.length > 0) {
                const pkg = this.availablePackages.find(p => p.id == ids[0]);
                if (pkg && pkg.registration_fee !== null && pkg.registration_fee !== undefined && pkg.registration_fee !== '') {
                    return parseFloat(pkg.registration_fee);
                }
            }
            return parseFloat(this.enrollmentFeeAmount) || 0;
        },

        getPersonEquipment(person) {
            return this.availableEquipmentFor(person)
                .filter(eq => person.selectedEquipmentIds.includes(eq.id))
                .map(eq => {
                    if (!eq.has_variants) return eq;
                    const vid = this.equipmentVariantId(person, eq.id);
                    const v = (eq.variants || []).find(x => x.id === vid);
                    if (!v) return null;
                    return { ...eq, price: v.price, variantLabel: v.label };
                })
                .filter(Boolean);
        },

        calculateTotals() {
            const joining = this.data.people.filter(p => p.isJoining);
            const memberCount = joining.length;
            let enrollmentTotal = 0;
            let packagesTotal = 0;
            let equipmentTotal = 0;
            joining.forEach(person => {
                enrollmentTotal += this.getPersonRegFee(person);
                person.selectedPackageIds.forEach(pkgId => {
                    const pkg = this.availablePackages.find(p => p.id == pkgId);
                    if (pkg) packagesTotal += parseFloat(pkg.price);
                });
                this.getPersonEquipment(person).forEach(eq => { equipmentTotal += parseFloat(eq.price); });
            });
            const subtotal = enrollmentTotal + packagesTotal + equipmentTotal;
            let discount = 0;
            if (this.data.discountValue > 0) {
                discount = this.data.discountType === 'percentage' ? (subtotal * this.data.discountValue / 100) : this.data.discountValue;
            }
            const afterDiscount = subtotal - discount;
            const vat = afterDiscount * (this.vatPercentageAmount / 100);
            const grandTotal = afterDiscount + vat;

            this.totals = { memberCount, enrollmentTotal, packagesTotal, equipmentTotal, subtotal, discount, vat, grandTotal };
        },

        // --- Step 4: Summary helpers ---
        get joiningPeople() {
            return this.data.people.filter(p => p.isJoining);
        },

        getPersonPackages(person) {
            return person.selectedPackageIds.map(id => this.availablePackages.find(p => p.id == id)).filter(Boolean);
        },

        getPersonTotal(person) {
            const pkgs = this.getPersonPackages(person);
            const equip = this.getPersonEquipment(person);
            return this.getPersonRegFee(person)
                + pkgs.reduce((s, pkg) => s + parseFloat(pkg.price), 0)
                + equip.reduce((s, eq) => s + parseFloat(eq.price), 0);
        },

        // --- Validation ---
        validateStep1() {
            const g = this.data.guardian;
            // Read values from Blade component hidden inputs
            g.countryCode = document.getElementById('walkIn_countryCode')?.value || '+973';
            g.dob         = document.getElementById('walkIn_dob')?.value || '';
            g.gender      = document.getElementById('walkIn_gender')?.value || '';
            g.nationality = document.getElementById('walkIn_nationality')?.value || '';

            // Reset errors
            this.errors = { name: '', email: '', phone: '', dob: '', gender: '', nationality: '' };

            let valid = true;

            if (!g.name.trim()) {
                this.errors.name = '{{ __("shared.components_registration_walkin_full_name_required") }}'; valid = false;
            }

            // Email is optional. If provided (Guardian/Adult only), it must be a valid address —
            // it's the handle the member uses to claim their account later. No password is collected;
            // the member sets one on first login via email link.
            if (this.registrantType === 'guardian' && g.email.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(g.email.trim())) {
                this.errors.email = '{{ __("shared.components_registration_walkin_valid_email") }}'; valid = false;
            }

            if (!g.phone.trim()) {
                this.errors.phone = '{{ __("shared.components_registration_walkin_phone_required") }}'; valid = false;
            }

            if (!g.dob) {
                this.errors.dob = '{{ __("shared.components_registration_walkin_dob_required") }}'; valid = false;
            }

            if (!g.gender) {
                this.errors.gender = '{{ __("shared.components_registration_walkin_select_gender") }}'; valid = false;
            }

            if (!valid) {
                this.toast('{{ __("shared.components_registration_walkin_fill_required_fields") }}', 'warning');
            }

            return valid;
        },

        validateStep2() {
            if (this.data.isGuardian === null) {
                this.toast('{{ __("shared.components_registration_walkin_select_registering_children") }}', 'warning');
                return false;
            }
            if (this.data.isGuardian && this.data.children.length === 0) {
                this.toast('{!! __("shared.components_registration_walkin_add_child_or_myself") !!}', 'warning');
                return false;
            }
            for (let i = 0; i < this.data.children.length; i++) {
                const child = this.data.children[i];
                const n = i + 1;
                if (!child.name.trim())            { this.toast(`{{ __("shared.components_registration_walkin_child_label") }} ${n}: {{ __("shared.components_registration_walkin_name_required") }}`, 'warning'); return false; }
                if (!child.dob)                    { this.toast(`{{ __("shared.components_registration_walkin_child_label") }} ${n}: {{ __("shared.components_registration_walkin_dob_required") }}`, 'warning'); return false; }
                if (!child.gender)                 { this.toast(`{{ __("shared.components_registration_walkin_child_label") }} ${n}: {{ __("shared.components_registration_walkin_select_gender") }}`, 'warning'); return false; }
                if (!child.phone || !child.phone.trim()) { this.toast(`{{ __("shared.components_registration_walkin_child_label") }} ${n}: {{ __("shared.components_registration_walkin_phone_required") }}`, 'warning'); return false; }
            }
            return true;
        },

        validateStep3() {
            if (this.data.people.filter(p => p.isJoining).length === 0) {
                this.toast('{{ __("shared.components_registration_walkin_select_person_to_register") }}', 'warning');
                return false;
            }
            return true;
        },

        // --- Navigation ---
        nextStep() {
            if (this.step === 1 && !this.validateStep1()) return;
            if (this.step === 2 && !this.validateStep2()) return;
            if (this.step === 3 && !this.validateStep3()) return;

            // Child mode skips the "children" step: 1 → 3 (packages).
            if (this.step === 1 && this.registrantType === 'child') {
                this.buildPeopleList();
                this.calculateTotals();
                this.step = 3;
                return;
            }

            if (this.step === 2) this.buildPeopleList();
            if (this.step === 3) this.calculateTotals();

            this.step++;
        },

        prevStep() {
            // Child mode: from packages (3) go straight back to the details step (1).
            if (this.registrantType === 'child' && this.step === 3) { this.step = 1; return; }
            if (this.step > 1) this.step--;
        },

        // --- Submit ---
        async submitRegistration() {
            this.isSubmitting = true;
            try {
                const joiningPeople = this.data.people.filter(p => p.isJoining);
                const formData = {
                    registrant_type: this.registrantType,
                    guardian:        this.data.guardian,
                    people:          joiningPeople,
                    discount_type:   this.data.discountType,
                    discount_value:  this.data.discountValue
                };

                const res = await fetch(`/admin/club/${this.clubId}/members/walk-in`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(formData)
                });

                const data = await res.json();

                if (res.ok && data.success) {
                    this.toast('{{ __("shared.components_registration_walkin_walkin_success") }}', 'success');
                    this.open = false;
                    // Refresh the members grid in place (no page reload) if available on this page.
                    if (typeof window.reloadMemberCards === 'function') {
                        window.reloadMemberCards();
                    } else {
                        setTimeout(() => location.reload(), 1200);
                    }
                } else if (res.status === 422 && data.errors) {
                    // Map backend field errors to inline display
                    const map = {
                        'guardian.name':        'name',
                        'guardian.email':       'email',
                        'guardian.phone':       'phone',
                        'guardian.dob':         'dob',
                        'guardian.gender':      'gender',
                        'guardian.nationality': 'nationality',
                    };
                    let shown = false;
                    Object.keys(data.errors).forEach(field => {
                        const key = map[field];
                        if (key) { this.errors[key] = data.errors[field][0]; shown = true; }
                    });
                    this.step = 1; // navigate back to step 1 for field errors
                    this.toast(shown ? '{{ __("shared.components_registration_walkin_review_highlighted") }}' : (data.message || '{{ __("shared.components_registration_walkin_validation_failed") }}'), 'warning');
                } else {
                    this.toast(data.message || '{{ __("shared.components_registration_walkin_registration_failed") }}', 'error');
                }
            } catch (error) {
                this.toast('{{ __("shared.components_registration_walkin_unexpected_error") }}', 'error');
            } finally {
                this.isSubmitting = false;
            }
        },

        // --- Toast --- always use the global toast; never render an inline alert on the page.
        toast(msg, type = 'info') {
            window.showToast(type, msg);
        }
    };
}
</script>
