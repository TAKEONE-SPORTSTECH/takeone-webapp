{{-- Join Club Modal - Multi-step registration flow --}}
<div x-show="joinModal.open"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50"
     style="display: none;">

    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="joinModal.close()"></div>

    {{-- Modal Dialog --}}
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div x-show="joinModal.open"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4"
             class="bg-white rounded-2xl shadow-2xl border border-border/50 w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col"
             @click.stop>

            {{-- Modal Header --}}
            <div class="flex items-center justify-between px-6 py-5 border-b border-border/50 shrink-0 bg-gradient-to-r from-primary/5 to-transparent">
                <div>
                    <h5 class="text-xl font-bold text-foreground" x-text="joinModal.clubName ? 'Join ' + joinModal.clubName : 'Join Club'"></h5>
                    <p class="text-sm text-muted-foreground mt-0.5">Select who you want to enroll and choose a package</p>
                </div>
                <button type="button" class="w-8 h-8 rounded-full flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-gray-100 transition-all" @click="joinModal.close()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            {{-- Modal Body (scrollable) --}}
            <div class="flex-1 overflow-y-auto px-6 py-6">

                {{-- Progress Steps --}}
                <div class="flex items-center justify-center mb-8">
                    <div class="flex items-center">
                        {{-- Step 1 --}}
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300 shadow-sm"
                                 :class="joinModal.step === 'select-members' ? 'bg-primary text-white shadow-primary/30' : (joinModal.step !== 'select-members' ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-400')">
                                <template x-if="joinModal.step !== 'select-members' && joinModal.step !== 'select-members'">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </template>
                                <template x-if="joinModal.step === 'select-members'">
                                    <span>1</span>
                                </template>
                            </div>
                            <span class="text-xs font-medium hidden sm:inline" :class="joinModal.step === 'select-members' ? 'text-primary' : 'text-muted-foreground'">Members</span>
                        </div>
                        <div class="w-12 sm:w-20 h-0.5 mx-2 transition-colors duration-300" :class="joinModal.step !== 'select-members' ? 'bg-primary' : 'bg-gray-200'"></div>
                        {{-- Step 2 --}}
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300 shadow-sm"
                                 :class="joinModal.step === 'package-selection' ? 'bg-primary text-white shadow-primary/30' : (joinModal.step === 'payment-review' ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-400')">
                                <template x-if="joinModal.step === 'payment-review'">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                </template>
                                <template x-if="joinModal.step !== 'payment-review'">
                                    <span>2</span>
                                </template>
                            </div>
                            <span class="text-xs font-medium hidden sm:inline" :class="joinModal.step === 'package-selection' ? 'text-primary' : 'text-muted-foreground'">Packages</span>
                        </div>
                        <div class="w-12 sm:w-20 h-0.5 mx-2 transition-colors duration-300" :class="joinModal.step === 'payment-review' ? 'bg-primary' : 'bg-gray-200'"></div>
                        {{-- Step 3 --}}
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold transition-all duration-300 shadow-sm"
                                 :class="joinModal.step === 'payment-review' ? 'bg-primary text-white shadow-primary/30' : 'bg-gray-100 text-gray-400'">3</div>
                            <span class="text-xs font-medium hidden sm:inline" :class="joinModal.step === 'payment-review' ? 'text-primary' : 'text-muted-foreground'">Payment</span>
                        </div>
                    </div>
                </div>

                {{-- ==================== STEP 1: Select Family Members ==================== --}}
                <div x-show="joinModal.step === 'select-members'" x-transition>
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-primary/15 to-primary/5 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <h2 class="tf-section-title">Who would you like to register?</h2>
                        <p class="text-muted-foreground text-sm">Tick the family members you want to enroll in this club</p>
                    </div>

                    {{-- Family Members Checklist --}}
                    <div class="max-w-2xl mx-auto">
                        <div class="flex items-center justify-between mb-3 px-1">
                            <h4 class="text-sm font-semibold text-muted-foreground uppercase tracking-wide">Your Family</h4>
                            <span class="text-xs font-medium px-2.5 py-1 rounded-full transition-colors"
                                  :class="joinModal.selectedMemberIds.length > 0 ? 'bg-primary/10 text-primary' : 'bg-gray-100 text-gray-500'"
                                  x-text="joinModal.selectedMemberIds.length + ' selected'"></span>
                        </div>

                        <div class="space-y-2">
                            <template x-for="member in joinModal.familyMembers" :key="member.id">
                                <div class="group flex items-center gap-4 p-4 rounded-xl border-2 cursor-pointer transition-all duration-200"
                                     :class="joinModal.isMemberSelected(member.id) ? 'border-primary bg-primary/5 shadow-sm shadow-primary/10' : 'border-transparent bg-gray-50 hover:bg-gray-100 hover:border-gray-200'"
                                     @click="joinModal.toggleMember(member)">

                                    {{-- Checkbox --}}
                                    <div class="shrink-0">
                                        <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition-all duration-200"
                                             :class="joinModal.isMemberSelected(member.id) ? 'bg-primary border-primary scale-110' : 'border-gray-300 group-hover:border-gray-400'">
                                            <svg x-show="joinModal.isMemberSelected(member.id)"
                                                 x-transition:enter="transition ease-out duration-150"
                                                 x-transition:enter-start="opacity-0 scale-50"
                                                 x-transition:enter-end="opacity-100 scale-100"
                                                 xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        </div>
                                    </div>

                                    {{-- Avatar --}}
                                    <div class="shrink-0">
                                        <template x-if="member.profile_picture">
                                            <img :src="'/storage/' + member.profile_picture" :alt="member.name"
                                                 class="w-11 h-11 rounded-full object-cover ring-2 transition-all duration-200"
                                                 :class="joinModal.isMemberSelected(member.id) ? 'ring-primary/30' : 'ring-gray-200'">
                                        </template>
                                        <template x-if="!member.profile_picture">
                                            <div class="w-11 h-11 rounded-full flex items-center justify-center text-white font-bold text-sm transition-all duration-200"
                                                 :class="joinModal.isMemberSelected(member.id) ? 'bg-primary' : (member.type === 'guardian' ? 'bg-gray-400' : 'bg-gray-300')"
                                                 x-text="member.name.charAt(0).toUpperCase()">
                                            </div>
                                        </template>
                                    </div>

                                    {{-- Member Info --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="font-semibold text-sm truncate" x-text="member.name"></p>
                                            <template x-if="member.type === 'guardian'">
                                                <span class="inline-block bg-primary/10 text-primary px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide shrink-0">You</span>
                                            </template>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground mt-0.5">
                                            <span x-text="member.relationship" class="capitalize"></span>
                                            <template x-if="member.age !== null">
                                                <span>&middot; <span x-text="member.age + ' yrs'"></span></span>
                                            </template>
                                            <template x-if="member.gender">
                                                <span>&middot; <span x-text="joinModal.genderLabel(member.gender)"></span></span>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Empty state --}}
                        <template x-if="joinModal.familyMembers.length === 0">
                            <div class="text-center py-12 text-muted-foreground">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-40"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                </div>
                                <p class="font-medium">No family members found</p>
                                <p class="text-sm mt-1">Add family members from your profile to register them</p>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- ==================== STEP 2: Package Selection ==================== --}}
                <div x-show="joinModal.step === 'package-selection'" x-transition>
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-primary/15 to-primary/5 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><path d="M14.4 14.4 9.6 9.6"/><path d="M18.657 21.485a2 2 0 1 1-2.829-2.828l-1.767 1.768a2 2 0 1 1-2.829-2.829l6.364-6.364a2 2 0 1 1 2.829 2.829l-1.768 1.767a2 2 0 1 1 2.828 2.829z"/><path d="m21.5 21.5-1.4-1.4"/><path d="M3.9 3.9 2.5 2.5"/><path d="M6.404 12.768a2 2 0 1 1-2.829-2.829l1.768-1.767a2 2 0 1 1-2.828-2.829l2.828-2.828a2 2 0 1 1 2.829 2.828l1.767-1.768a2 2 0 1 1 2.829 2.829z"/></svg>
                        </div>
                        <h2 class="tf-section-title">Select Packages</h2>
                        <p class="text-muted-foreground text-sm">Choose a package for each registrant</p>
                    </div>

                    {{-- Loading --}}
                    <template x-if="joinModal.loadingPackages">
                        <div class="text-center py-16">
                            <div class="animate-spin rounded-full h-10 w-10 border-2 border-primary border-t-transparent mx-auto mb-4"></div>
                            <p class="text-muted-foreground text-sm">Loading packages...</p>
                        </div>
                    </template>

                    {{-- Package cards per registrant --}}
                    <template x-if="!joinModal.loadingPackages">
                        <div class="space-y-8">
                            <template x-for="reg in joinModal.registrants" :key="reg.id">
                                <div class="border rounded-xl overflow-hidden">
                                    {{-- Registrant header --}}
                                    <div class="px-5 py-3.5 bg-gray-50 border-b border-border flex items-center gap-3">
                                        <template x-if="reg.avatarUrl">
                                            <img :src="reg.avatarUrl" :alt="reg.name" class="w-9 h-9 rounded-full object-cover ring-2 ring-white">
                                        </template>
                                        <template x-if="!reg.avatarUrl">
                                            <div class="w-9 h-9 rounded-full bg-primary flex items-center justify-center text-white font-bold text-xs"
                                                 x-text="reg.name.charAt(0).toUpperCase()"></div>
                                        </template>
                                        <div>
                                            <div class="font-semibold text-sm" x-text="reg.name"></div>
                                            <div class="text-xs text-muted-foreground">
                                                <template x-if="reg.relationship">
                                                    <span x-text="reg.relationship"></span>
                                                </template>
                                                <template x-if="reg.dateOfBirth">
                                                    <span> &middot; <span x-text="joinModal.calculateAge(reg.dateOfBirth) + ' yrs'"></span></span>
                                                </template>
                                                <template x-if="reg.gender">
                                                    <span> &middot; <span x-text="joinModal.genderLabel(reg.gender)"></span></span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Packages list --}}
                                    <div class="p-4">
                                        <template x-if="joinModal.packages.length === 0">
                                            <div class="flex items-start gap-2 p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                                <span>No eligible packages found. Please contact the club.</span>
                                            </div>
                                        </template>

                                        <template x-if="joinModal.packages.length > 0">
                                            <div class="space-y-3">
                                                <div class="space-y-3">
                                                    <template x-for="pkg in joinModal.packages" :key="pkg.id">
                                                        <div class="border-2 rounded-xl p-5 cursor-pointer transition-all duration-200 hover:shadow-md space-y-3"
                                                             :class="reg.packageId == pkg.id ? 'ring-2 ring-primary border-primary shadow-sm shadow-primary/10 bg-primary/[0.02]' : 'border-border hover:border-gray-300'"
                                                             @click="joinModal.selectPackage(reg.id, pkg.id)">

                                                            <div class="flex items-start justify-between gap-4">
                                                                <div class="flex-1">
                                                                    <div class="flex items-center gap-2 mb-1">
                                                                        <h4 class="font-semibold" x-text="pkg.name"></h4>
                                                                        <template x-if="reg.packageId == pkg.id">
                                                                            <span class="inline-flex items-center gap-1 bg-primary text-white px-2 py-0.5 rounded-full text-[10px] font-bold">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                                                Selected
                                                                            </span>
                                                                        </template>
                                                                    </div>
                                                                    <p class="text-xs text-muted-foreground">
                                                                        <span x-text="pkg.duration_months"></span> months
                                                                        <template x-if="pkg.activity_type">
                                                                            <span> &middot; <span x-text="pkg.activity_type"></span></span>
                                                                        </template>
                                                                    </p>
                                                                </div>
                                                                <div class="text-right shrink-0">
                                                                    <div class="text-xl font-bold text-primary" x-text="joinModal.formatCurrency(pkg.price)"></div>
                                                                    <div class="text-[10px] text-muted-foreground">per month</div>
                                                                </div>
                                                            </div>

                                                            {{-- Schedule --}}
                                                            <template x-if="pkg.schedules && pkg.schedules.length > 0">
                                                                <div class="pt-3 border-t border-border/50">
                                                                    <div class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground mb-1.5">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                                                        Schedule
                                                                    </div>
                                                                    <div class="space-y-0.5 pl-4">
                                                                        <template x-for="(sched, si) in pkg.schedules" :key="si">
                                                                            <div class="text-xs text-muted-foreground" x-text="sched.days + ': ' + sched.time"></div>
                                                                        </template>
                                                                    </div>
                                                                </div>
                                                            </template>

                                                            {{-- Instructors --}}
                                                            <template x-if="pkg.instructors && pkg.instructors.length > 0">
                                                                <div class="pt-3 border-t border-border/50">
                                                                    <div class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground mb-1.5">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                                                        Instructors
                                                                    </div>
                                                                    <div class="flex flex-wrap gap-2 pl-4">
                                                                        <template x-for="(inst, ii) in pkg.instructors" :key="ii">
                                                                            <div class="flex items-center gap-1.5 bg-gray-50 rounded-full px-2 py-1">
                                                                                <template x-if="inst.image_url">
                                                                                    <img :src="inst.image_url" :alt="inst.name" class="w-5 h-5 rounded-full object-cover">
                                                                                </template>
                                                                                <span class="text-xs font-medium" x-text="inst.name"></span>
                                                                            </div>
                                                                        </template>
                                                                    </div>
                                                                </div>
                                                            </template>

                                                            {{-- Eligibility --}}
                                                            <div class="pt-3 border-t border-border/50">
                                                                <div class="flex flex-wrap gap-1.5">
                                                                    <template x-if="pkg.age_min || pkg.age_max">
                                                                        <span class="inline-block bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-[10px] font-medium">
                                                                            Ages <span x-text="pkg.age_min || '0'"></span>-<span x-text="pkg.age_max || '∞'"></span>
                                                                        </span>
                                                                    </template>
                                                                    <span class="inline-block bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-[10px] font-medium"
                                                                          x-text="(pkg.gender_restriction && pkg.gender_restriction !== 'mixed') ? pkg.gender_restriction.charAt(0).toUpperCase() + pkg.gender_restriction.slice(1) + ' only' : 'Mixed'"></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- ==================== STEP 3: Review & Payment ==================== --}}
                <div x-show="joinModal.step === 'payment-review'" x-transition>
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-primary/15 to-primary/5 mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                        <h2 class="tf-section-title">Review & Payment</h2>
                        <p class="text-muted-foreground text-sm">Review your selection and upload payment proof</p>
                    </div>

                    {{-- Registrants Summary --}}
                    <div class="space-y-3 mb-6">
                        <template x-for="reg in joinModal.registrants" :key="reg.id">
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                <div class="flex items-center gap-3">
                                    <template x-if="reg.avatarUrl">
                                        <img :src="reg.avatarUrl" :alt="reg.name" class="w-9 h-9 rounded-full object-cover">
                                    </template>
                                    <template x-if="!reg.avatarUrl">
                                        <div class="w-9 h-9 rounded-full bg-primary flex items-center justify-center text-white font-bold text-xs"
                                             x-text="reg.name.charAt(0).toUpperCase()"></div>
                                    </template>
                                    <div>
                                        <div class="font-semibold text-sm" x-text="reg.name"></div>
                                        <div class="text-xs text-muted-foreground">
                                            <span x-text="joinModal.getPackageForRegistrant(reg)?.name || 'No package'"></span>
                                            <template x-if="joinModal.getPackageForRegistrant(reg)?.duration_months">
                                                <span> &middot; <span x-text="joinModal.getPackageForRegistrant(reg)?.duration_months"></span> mo</span>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                                <p class="text-lg font-bold text-primary" x-text="joinModal.formatCurrency(joinModal.getPackageForRegistrant(reg)?.price || 0)"></p>
                            </div>
                        </template>
                    </div>

                    {{-- Billing Summary --}}
                    <div class="border rounded-xl overflow-hidden mb-6">
                        <div class="px-5 py-3.5 bg-gray-50 border-b border-border flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            <h4 class="font-semibold text-sm">Billing Summary</h4>
                        </div>
                        <div class="p-5 space-y-3">
                            <template x-for="reg in joinModal.registrants" :key="'bill-' + reg.id">
                                <template x-if="joinModal.getPackageForRegistrant(reg)">
                                    <div class="flex justify-between items-center text-sm">
                                        <div>
                                            <span class="font-medium" x-text="reg.name"></span>
                                            <span class="text-muted-foreground"> - <span x-text="joinModal.getPackageForRegistrant(reg)?.name"></span></span>
                                        </div>
                                        <span class="font-medium" x-text="Number(joinModal.getPackageForRegistrant(reg)?.price || 0).toFixed(2) + ' ' + joinModal.currency"></span>
                                    </div>
                                </template>
                            </template>

                            <template x-if="joinModal.enrollmentFee > 0 && joinModal.firstTimerCount() > 0">
                                <div class="flex justify-between items-center text-sm py-2 px-3 bg-blue-50 rounded-lg -mx-1">
                                    <div>
                                        <span class="font-medium">Enrollment Fee</span>
                                        <span class="text-muted-foreground text-xs"> (<span x-text="joinModal.firstTimerCount()"></span> member<span x-text="joinModal.firstTimerCount() > 1 ? 's' : ''"></span>)</span>
                                    </div>
                                    <span class="font-medium" x-text="(joinModal.enrollmentFee * joinModal.firstTimerCount()).toFixed(2) + ' ' + joinModal.currency"></span>
                                </div>
                            </template>

                            <div class="border-t border-border pt-3 mt-3">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-muted-foreground">Subtotal</span>
                                    <span class="font-medium" x-text="joinModal.calculateSubtotal() + ' ' + joinModal.currency"></span>
                                </div>
                                <template x-if="joinModal.vatRegNumber && joinModal.vatPercentage > 0">
                                    <div class="flex justify-between items-center text-sm mt-1">
                                        <span class="text-muted-foreground">VAT (<span x-text="joinModal.vatPercentage"></span>%)</span>
                                        <span class="font-medium" x-text="joinModal.calculateVat() + ' ' + joinModal.currency"></span>
                                    </div>
                                </template>
                            </div>

                            <div class="border-t-2 border-primary pt-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-lg font-bold">Total</span>
                                    <span class="text-2xl font-bold text-primary" x-text="joinModal.calculateTotal() + ' ' + joinModal.currency"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Payment Proof Upload --}}
                    <div class="border rounded-xl overflow-hidden">
                        <div class="px-5 py-3.5 bg-gray-50 border-b border-border flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            <h4 class="font-semibold text-sm">Payment Proof</h4>
                        </div>
                        <div class="p-5 space-y-4">
                            <label class="flex items-start gap-3 p-3 rounded-lg cursor-pointer transition-colors" :class="joinModal.payLater ? 'bg-primary/5' : 'bg-gray-50 hover:bg-gray-100'">
                                <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-primary focus:ring-primary"
                                       x-model="joinModal.payLater" @change="if(joinModal.payLater) { joinModal.paymentScreenshot = null; joinModal.paymentPreview = null; }">
                                <div>
                                    <span class="font-medium text-sm">I'll pay later</span>
                                    <p class="text-xs text-muted-foreground mt-0.5">The club owner will review your registration and send a payment proposal</p>
                                </div>
                            </label>

                            <template x-if="!joinModal.payLater">
                                <div class="space-y-3">
                                    <div class="flex items-start gap-2 p-3 bg-blue-50 border border-blue-100 rounded-lg text-xs text-blue-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                        <span>Upload a screenshot of your payment. Registration completes after verification.</span>
                                    </div>
                                    <label class="block">
                                        <span class="text-xs font-medium">Upload Screenshot *</span>
                                        <input type="file" accept="image/*" class="mt-1 block w-full text-xs text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20 file:cursor-pointer"
                                               @change="joinModal.handleFileUpload($event)">
                                    </label>
                                    <template x-if="joinModal.paymentPreview">
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-1.5 text-xs text-green-600 font-medium">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                                Uploaded successfully
                                            </div>
                                            <img :src="joinModal.paymentPreview" alt="Payment proof" class="w-full max-w-sm h-auto rounded-lg border">
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Modal Footer --}}
            <div class="flex items-center justify-between px-6 py-4 border-t border-border/50 bg-gray-50/80 shrink-0">
                <button class="px-5 py-2.5 rounded-lg border border-border text-sm font-medium text-muted-foreground hover:text-foreground hover:bg-gray-100 transition-colors" @click="joinModal.goBack()">
                    <span x-text="joinModal.step === 'select-members' ? 'Cancel' : 'Back'"></span>
                </button>
                <button class="btn btn-primary px-6 py-2.5 rounded-lg text-sm font-semibold shadow-sm"
                        @click="joinModal.goNext()"
                        :disabled="(joinModal.step === 'select-members' && joinModal.selectedMemberIds.length === 0) || (joinModal.step === 'payment-review' && !joinModal.payLater && !joinModal.paymentScreenshot)"
                        :class="{ 'opacity-50 cursor-not-allowed': (joinModal.step === 'select-members' && joinModal.selectedMemberIds.length === 0) || (joinModal.step === 'payment-review' && !joinModal.payLater && !joinModal.paymentScreenshot) }">
                    <span x-text="joinModal.step === 'payment-review' ? (joinModal.payLater ? 'Complete Registration' : 'Complete Registration') : 'Continue'"></span>
                    <template x-if="joinModal.step !== 'payment-review'">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-1.5 inline-block"><polyline points="9 18 15 12 9 6"/></svg>
                    </template>
                </button>
            </div>

        </div>
    </div>
</div>
