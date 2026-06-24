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
                <p class="text-sm text-gray-500 mt-1">Step <span x-text="currentStepIndex"></span> of <span x-text="visibleSteps.length"></span>: <span x-text="currentStepLabel"></span></p>
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
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                                <input type="text" x-model="data.guardian.name" @input="errors.name = ''"
                                       :class="errors.name ? 'border-red-400 ring-2 ring-red-100' : 'border-gray-200'"
                                       class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="John Doe">
                                <span x-show="errors.name" x-text="errors.name" class="text-red-500 text-xs mt-1 block"></span>
                            </div>
                            <div x-show="registrantType === 'guardian'">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Email <span class="text-red-500">*</span></label>
                                <input type="email" x-model="data.guardian.email" @input="errors.email = ''"
                                       :class="errors.email ? 'border-red-400 ring-2 ring-red-100' : 'border-gray-200'"
                                       class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="member@example.com">
                                <span x-show="errors.email" x-text="errors.email" class="text-red-500 text-xs mt-1 block"></span>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone Number <span class="text-red-500">*</span></label>
                                <x-country-code-dropdown name="walkIn_countryCode" id="walkIn_countryCode" value="+973" :required="true">
                                    <input type="text" id="walkIn_phone" x-model="data.guardian.phone" @input="errors.phone = ''"
                                           :class="errors.phone ? 'ring-2 ring-red-100' : ''"
                                           class="w-full px-4 py-3 text-base bg-transparent focus:outline-none" placeholder="Phone number">
                                </x-country-code-dropdown>
                                <span x-show="errors.phone" x-text="errors.phone" class="text-red-500 text-xs mt-1 block"></span>
                            </div>
                            <div x-show="registrantType === 'guardian'">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input :type="showPassword ? 'text' : 'password'" x-model="data.guardian.password" @input="errors.password = ''"
                                           :class="errors.password ? 'border-red-400 ring-2 ring-red-100' : 'border-gray-200'"
                                           class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent pr-10" placeholder="Minimum 8 characters">
                                    <button type="button" @click="showPassword = !showPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                        <i class="bi" :class="showPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
                                    </button>
                                </div>
                                <span x-show="errors.password" x-text="errors.password" class="text-red-500 text-xs mt-1 block"></span>
                            </div>
                            <div x-show="registrantType === 'guardian'">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <input :type="showConfirmPassword ? 'text' : 'password'" x-model="passwordConfirmation" @input="errors.confirmPassword = ''"
                                           :class="errors.confirmPassword ? 'border-red-400 ring-2 ring-red-100' : 'border-gray-200'"
                                           class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent pr-10" placeholder="Re-enter password">
                                    <button type="button" @click="showConfirmPassword = !showConfirmPassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                        <i class="bi" :class="showConfirmPassword ? 'bi-eye-slash' : 'bi-eye'"></i>
                                    </button>
                                </div>
                                <span x-show="errors.confirmPassword" x-text="errors.confirmPassword" class="text-red-500 text-xs mt-1 block"></span>
                            </div>
                            <div class="sm:col-span-2">
                                <x-birthdate-dropdown name="walkIn_dob" id="walkIn_dob" label="Date of Birth" :required="true" :minAge="3" :maxAge="100" />
                                <span x-show="errors.dob" x-text="errors.dob" class="text-red-500 text-xs mt-1 block"></span>
                            </div>
                            <div>
                                <x-gender-dropdown name="walkIn_gender" id="walkIn_gender" label="Gender" :required="true" />
                                <span x-show="errors.gender" x-text="errors.gender" class="text-red-500 text-xs mt-1 block"></span>
                            </div>
                            <div>
                                <x-country-dropdown name="walkIn_nationality" id="walkIn_nationality" label="Nationality" :required="true" />
                                <span x-show="errors.nationality" x-text="errors.nationality" class="text-red-500 text-xs mt-1 block"></span>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Address (Optional)</label>
                                <input type="text" x-model="data.guardian.address" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Street address">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Guardian & Children -->
                <div x-show="step === 2" class="p-4 sm:p-6 space-y-5 sm:space-y-6">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Are you registering children?</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <button type="button" @click="setIsGuardian(true)"
                                    class="px-4 py-3 border-2 rounded-lg font-medium text-gray-700 hover:border-purple-400 transition-colors flex items-center justify-center gap-2"
                                    :class="data.isGuardian === true ? 'border-purple-500 bg-purple-50' : 'border-gray-200'">
                                <svg x-show="data.isGuardian === true" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Yes, I'm a guardian
                            </button>
                            <button type="button" @click="setIsGuardian(false)"
                                    class="px-4 py-3 border-2 rounded-lg font-medium text-gray-700 hover:border-purple-400 transition-colors flex items-center justify-center gap-2"
                                    :class="data.isGuardian === false ? 'border-purple-500 bg-purple-50' : 'border-gray-200'">
                                <svg x-show="data.isGuardian === false" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                No, just myself
                            </button>
                        </div>
                    </div>

                    <!-- Children Section -->
                    <div x-show="data.isGuardian === true" class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-semibold text-gray-900">Children</h4>
                            <button type="button" @click="addChild()" class="inline-flex items-center px-3 py-1.5 bg-purple-500 text-white text-sm font-medium rounded-lg hover:bg-purple-600 transition-colors">
                                <i class="bi bi-plus mr-1"></i> Add Child
                            </button>
                        </div>
                        <template x-for="(child, index) in data.children" :key="child.id">
                            <div class="bg-white border border-gray-200 rounded-xl p-4">
                                <div class="flex justify-between items-start mb-4">
                                    <h5 class="font-medium text-gray-900">Child <span x-text="index + 1"></span></h5>
                                    <button type="button" @click="removeChild(child.id)" class="text-gray-400 hover:text-red-500 transition-colors">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                                        <input type="text" x-model="child.name" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500" placeholder="Child's name">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth <span class="text-red-500">*</span></label>
                                        <input type="date" x-model="child.dob" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Gender <span class="text-red-500">*</span></label>
                                        <div class="grid grid-cols-2 gap-2">
                                            <button type="button" @click="child.gender = 'Male'"
                                                    class="px-3 py-2 border-2 rounded-lg text-sm font-medium transition-colors"
                                                    :class="child.gender === 'Male' ? 'border-purple-500 bg-purple-50' : 'border-gray-200'">Male</button>
                                            <button type="button" @click="child.gender = 'Female'"
                                                    class="px-3 py-2 border-2 rounded-lg text-sm font-medium transition-colors"
                                                    :class="child.gender === 'Female' ? 'border-purple-500 bg-purple-50' : 'border-gray-200'">Female</button>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Nationality <span class="text-red-500">*</span></label>
                                        <select x-model="child.nationality" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
                                            <option value="">Select</option>
                                            <option value="Bahrain">Bahrain</option>
                                            <option value="Saudi Arabia">Saudi Arabia</option>
                                            <option value="UAE">UAE</option>
                                            <option value="Kuwait">Kuwait</option>
                                            <option value="India">India</option>
                                            <option value="Pakistan">Pakistan</option>
                                        </select>
                                    </div>
                                    {{-- A child needs no email/password — just a phone number for contact. --}}
                                    <div class="col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number <span class="text-red-500">*</span></label>
                                        <div class="flex">
                                            <select x-model="child.countryCode" class="px-2 py-2 border border-r-0 border-gray-200 rounded-l-lg text-sm bg-gray-50 focus:ring-2 focus:ring-purple-500">
                                                <option value="+973">🇧🇭 +973</option>
                                                <option value="+966">🇸🇦 +966</option>
                                                <option value="+971">🇦🇪 +971</option>
                                                <option value="+965">🇰🇼 +965</option>
                                                <option value="+974">🇶🇦 +974</option>
                                                <option value="+968">🇴🇲 +968</option>
                                                <option value="+1">+1</option>
                                                <option value="+44">+44</option>
                                                <option value="+91">+91</option>
                                                <option value="+92">+92</option>
                                            </select>
                                            <input type="tel" x-model="child.phone" inputmode="tel"
                                                   class="flex-1 px-3 py-2 border border-gray-200 rounded-r-lg text-sm focus:ring-2 focus:ring-purple-500" placeholder="Phone number">
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
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">Select Packages for Each Person</h4>
                        <p class="text-sm text-gray-500">Package selection is optional. You can register members without selecting packages.</p>
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
                                                Age <span x-text="calculateAge(person.dob)"></span> &bull;
                                                <span x-text="person.gender"></span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div x-show="person.isJoining" class="pl-8 space-y-2">
                                    <p class="text-sm font-medium text-gray-700 mb-2">Select Packages (optional):</p>
                                    <template x-if="getEligiblePackages(person).length === 0">
                                        <p class="text-sm text-gray-500 italic">No packages available for this age/gender</p>
                                    </template>
                                    <template x-for="pkg in getEligiblePackages(person)" :key="pkg.id">
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
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Cost Summary -->
                    <div class="bg-gray-50 rounded-xl p-4">
                        <h4 class="font-semibold text-gray-900 mb-3">Cost Summary</h4>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Enrollment Fee (<span x-text="totals.memberCount"></span> members)</span>
                                <span x-text="currencySymbol + ' ' + totals.enrollmentTotal.toFixed(2)"></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Packages Total</span>
                                <span x-text="currencySymbol + ' ' + totals.packagesTotal.toFixed(2)"></span>
                            </div>
                            <div class="flex justify-between font-medium pt-2 border-t border-gray-200">
                                <span>Subtotal</span>
                                <span x-text="currencySymbol + ' ' + totals.subtotal.toFixed(2)"></span>
                            </div>
                            <div class="pt-2">
                                <label class="text-xs text-gray-500">Discount (Optional)</label>
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
                                <span>Discount</span>
                                <span x-text="'-' + currencySymbol + ' ' + totals.discount.toFixed(2)"></span>
                            </div>
                            @if($vatPercentage > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-600">VAT ({{ $vatPercentage }}%)</span>
                                <span x-text="currencySymbol + ' ' + totals.vat.toFixed(2)"></span>
                            </div>
                            @endif
                            <div class="flex justify-between font-bold text-lg pt-3 border-t border-gray-200">
                                <span>Total Amount</span>
                                <span class="text-purple-600" x-text="currencySymbol + ' ' + totals.grandTotal.toFixed(2)"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Payment Confirmation -->
                <div x-show="step === 4" class="p-4 sm:p-6 space-y-5 sm:space-y-6">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-2">Payment Confirmation</h4>
                        <p class="text-sm text-gray-500">Please collect the payment and confirm to complete registration.</p>
                    </div>

                    <div class="bg-purple-50 rounded-xl p-6 text-center">
                        <p class="text-sm text-gray-600 mb-2">Total Amount to Collect</p>
                        <p class="text-4xl font-bold text-purple-600" x-text="currencySymbol + ' ' + totals.grandTotal.toFixed(2)"></p>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl p-4">
                        <h5 class="font-semibold text-gray-900 mb-3">Registration Summary</h5>
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
                                    <div class="pl-4 space-y-1 text-sm">
                                        <div class="flex justify-between text-gray-500">
                                            <span>Enrollment Fee</span>
                                            <span x-text="currencySymbol + ' ' + enrollmentFeeAmount.toFixed(2)"></span>
                                        </div>
                                        <template x-if="getPersonPackages(person).length === 0">
                                            <p class="text-gray-400 italic">No packages selected</p>
                                        </template>
                                        <template x-for="pkg in getPersonPackages(person)" :key="pkg.id">
                                            <div class="flex justify-between text-gray-500">
                                                <span x-text="pkg.name"></span>
                                                <span x-text="currencySymbol + ' ' + parseFloat(pkg.price).toFixed(2)"></span>
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
                    <i class="bi bi-arrow-left mr-2"></i>Back
                </button>
                <button type="button" @click="closeWalkIn()" x-show="step === 1" class="px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                <button type="button" @click="nextStep()" x-show="step < 4" class="px-6 py-2.5 bg-purple-500 text-white font-medium rounded-lg hover:bg-purple-600 transition-colors">
                    Next<i class="bi bi-arrow-right ml-2"></i>
                </button>
                <button type="button" @click="submitRegistration()" x-show="step === 4" :disabled="isSubmitting" class="px-6 py-2.5 bg-green-500 text-white font-medium rounded-lg hover:bg-green-600 transition-colors disabled:opacity-50">
                    <span x-show="!isSubmitting"><i class="bi bi-check-lg mr-2"></i>Confirm & Register</span>
                    <span x-show="isSubmitting"><span class="inline-block animate-spin mr-2">&#8635;</span>Processing...</span>
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
        showPassword: false,
        showConfirmPassword: false,
        passwordConfirmation: '',
        currencySymbol: '{{ $currency }}',
        enrollmentFeeAmount: {{ $enrollmentFee }},
        vatPercentageAmount: {{ $vatPercentage }},
        clubId: '{{ $clubId }}',
        availablePackages: @json($packages),

        // The visible step sequence differs by type — Child skips the "children" step.
        // `step` stays tied to the markup numbers (1 details · 2 children · 3 packages · 4 payment).
        get visibleSteps() {
            return this.registrantType === 'child'
                ? [{ step: 1, label: 'Child Details' }, { step: 3, label: 'Package Selection' }, { step: 4, label: 'Payment Confirmation' }]
                : [{ step: 1, label: 'Personal Information' }, { step: 2, label: 'Guardian & Children' }, { step: 3, label: 'Package Selection' }, { step: 4, label: 'Payment Confirmation' }];
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
            guardian: { name: '', email: '', password: '', phone: '', countryCode: '+973', dob: '', gender: '', nationality: '', address: '' },
            isGuardian: null,
            children: [],
            people: [],
            discountType: 'percentage',
            discountValue: 0
        },

        errors: {
            name: '', email: '', phone: '', password: '', confirmPassword: '', dob: '', gender: '', nationality: ''
        },

        totals: {
            memberCount: 0,
            enrollmentTotal: 0,
            packagesTotal: 0,
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
            this.showPassword = false;
            this.showConfirmPassword = false;
            this.passwordConfirmation = '';
            this.errors = { name: '', email: '', phone: '', password: '', confirmPassword: '', dob: '', gender: '', nationality: '' };
            this.data = {
                guardian: { name: '', email: '', password: '', phone: '', countryCode: '+973', dob: '', gender: '', nationality: '', address: '' },
                isGuardian: null,
                children: [],
                people: [],
                discountType: 'percentage',
                discountValue: 0
            };
            this.totals = { memberCount: 0, enrollmentTotal: 0, packagesTotal: 0, subtotal: 0, discount: 0, vat: 0, grandTotal: 0 };

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
                    isJoining: true,   // single person — auto-selected for packages/payment
                    selectedPackageIds: []
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
                    selectedPackageIds: []
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
                        isJoining: false,
                        selectedPackageIds: []
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
                if (!person.isJoining) person.selectedPackageIds = [];
            }
        },

        togglePackageForPerson(personId, packageId) {
            const person = this.data.people.find(p => p.id === personId);
            if (person) {
                const idx = person.selectedPackageIds.indexOf(packageId);
                if (idx > -1) person.selectedPackageIds.splice(idx, 1);
                else person.selectedPackageIds.push(packageId);
            }
        },

        calculateTotals() {
            const joining = this.data.people.filter(p => p.isJoining);
            const memberCount = joining.length;
            const enrollmentTotal = this.enrollmentFeeAmount * memberCount;
            let packagesTotal = 0;
            joining.forEach(person => {
                person.selectedPackageIds.forEach(pkgId => {
                    const pkg = this.availablePackages.find(p => p.id == pkgId);
                    if (pkg) packagesTotal += parseFloat(pkg.price);
                });
            });
            const subtotal = enrollmentTotal + packagesTotal;
            let discount = 0;
            if (this.data.discountValue > 0) {
                discount = this.data.discountType === 'percentage' ? (subtotal * this.data.discountValue / 100) : this.data.discountValue;
            }
            const afterDiscount = subtotal - discount;
            const vat = afterDiscount * (this.vatPercentageAmount / 100);
            const grandTotal = afterDiscount + vat;

            this.totals = { memberCount, enrollmentTotal, packagesTotal, subtotal, discount, vat, grandTotal };
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
            return this.enrollmentFeeAmount + pkgs.reduce((s, pkg) => s + parseFloat(pkg.price), 0);
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
            this.errors = { name: '', email: '', phone: '', password: '', confirmPassword: '', dob: '', gender: '', nationality: '' };

            let valid = true;

            if (!g.name.trim()) {
                this.errors.name = 'Full name is required.'; valid = false;
            }

            // Email + password only apply to a Guardian/Adult account. A Child needs just a phone.
            if (this.registrantType === 'guardian') {
                if (!g.email.trim()) {
                    this.errors.email = 'Email address is required.'; valid = false;
                } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(g.email.trim())) {
                    this.errors.email = 'Please enter a valid email address.'; valid = false;
                }

                if (!g.password) {
                    this.errors.password = 'Password is required.'; valid = false;
                } else if (g.password.length < 8) {
                    this.errors.password = 'Password must be at least 8 characters.'; valid = false;
                }

                if (!this.passwordConfirmation) {
                    this.errors.confirmPassword = 'Please confirm your password.'; valid = false;
                } else if (g.password !== this.passwordConfirmation) {
                    this.errors.confirmPassword = 'Passwords do not match.'; valid = false;
                }
            }

            if (!g.phone.trim()) {
                this.errors.phone = 'Phone number is required.'; valid = false;
            }

            if (!g.dob) {
                this.errors.dob = 'Date of birth is required.'; valid = false;
            }

            if (!g.gender) {
                this.errors.gender = 'Please select a gender.'; valid = false;
            }

            if (!g.nationality) {
                this.errors.nationality = 'Please select a nationality.'; valid = false;
            }

            if (!valid) {
                this.toast('Please fill in all required fields before continuing.', 'warning');
            }

            return valid;
        },

        validateStep2() {
            if (this.data.isGuardian === null) {
                this.toast('Please select whether you are registering children.', 'warning');
                return false;
            }
            if (this.data.isGuardian && this.data.children.length === 0) {
                this.toast('Please add at least one child, or select "No, just myself".', 'warning');
                return false;
            }
            for (let i = 0; i < this.data.children.length; i++) {
                const child = this.data.children[i];
                const n = i + 1;
                if (!child.name.trim())            { this.toast(`Child ${n}: Name is required.`, 'warning'); return false; }
                if (!child.dob)                    { this.toast(`Child ${n}: Date of birth is required.`, 'warning'); return false; }
                if (!child.gender)                 { this.toast(`Child ${n}: Please select a gender.`, 'warning'); return false; }
                if (!child.nationality)            { this.toast(`Child ${n}: Please select a nationality.`, 'warning'); return false; }
                if (!child.phone || !child.phone.trim()) { this.toast(`Child ${n}: Phone number is required.`, 'warning'); return false; }
            }
            return true;
        },

        validateStep3() {
            if (this.data.people.filter(p => p.isJoining).length === 0) {
                this.toast('Please select at least one person to register.', 'warning');
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
                    this.toast('Walk-in registration completed successfully!', 'success');
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
                        'guardian.password':    'password',
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
                    this.toast(shown ? 'Please review the highlighted fields.' : (data.message || 'Validation failed.'), 'warning');
                } else {
                    this.toast(data.message || 'Registration failed. Please try again.', 'error');
                }
            } catch (error) {
                this.toast('An unexpected error occurred. Please try again.', 'error');
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
