{{-- Mobile-native enrol flow — full-screen sheet. Reuses the same joinModal
     Alpine state as the desktop modal; only the layout differs. --}}
<div x-show="joinModal.open" x-cloak class="fixed inset-0 z-[100]" style="display:none;">
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-black/50" @click="joinModal.close()"></div>

    {{-- Sheet --}}
    <div x-show="joinModal.open"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
         class="absolute inset-x-0 bottom-0 top-3 bg-white rounded-t-3xl shadow-2xl flex flex-col overflow-hidden">

        {{-- Header --}}
        <div class="shrink-0 px-4 pt-2.5 pb-3 border-b border-gray-100">
            <div class="flex justify-center mb-2"><span class="w-10 h-1.5 rounded-full bg-gray-300"></span></div>
            <div class="flex items-center gap-3">
                <button type="button" @click="joinModal.close()" class="m-press w-9 h-9 rounded-full bg-muted flex items-center justify-center text-muted-foreground shrink-0"><i class="bi bi-x-lg"></i></button>
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-foreground truncate" x-text="joinModal.clubName ? @js(__('club.join_prefix')) + ' ' + joinModal.clubName : @js(__('club.join_club'))"></p>
                    <p class="text-[11px] text-muted-foreground" x-text="@js(__('club.step')) + ' ' + (joinModal.step === 'select-members' ? 1 : (joinModal.step === 'package-selection' ? 2 : 3)) + ' ' + @js(__('club.of')) + ' 3 · ' + (joinModal.step === 'select-members' ? @js(__('club.step_who')) : (joinModal.step === 'package-selection' ? @js(__('club.step_packages')) : @js(__('club.step_payment'))))"></p>
                </div>
            </div>
            {{-- Progress segments --}}
            <div class="flex gap-1.5 mt-3">
                <span class="h-1.5 flex-1 rounded-full transition-colors bg-primary"></span>
                <span class="h-1.5 flex-1 rounded-full transition-colors" :class="joinModal.step !== 'select-members' ? 'bg-primary' : 'bg-gray-200'"></span>
                <span class="h-1.5 flex-1 rounded-full transition-colors" :class="joinModal.step === 'payment-review' ? 'bg-primary' : 'bg-gray-200'"></span>
            </div>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto px-4 py-4 bg-background">

            {{-- ===== Step 1: Members ===== --}}
            <div x-show="joinModal.step === 'select-members'">
                <div class="text-center mb-4">
                    <span class="inline-flex w-14 h-14 rounded-2xl bg-accent text-primary items-center justify-center mb-2"><i class="bi bi-people-fill text-2xl"></i></span>
                    <h2 class="text-lg font-bold text-foreground">{{ __('club.who_enrolling') }}</h2>
                    <p class="text-[13px] text-muted-foreground">{{ __('club.pick_people') }}</p>
                </div>

                <div class="flex items-center justify-between mb-2 px-1">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">{{ __('club.eligible_people') }}</span>
                    <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-primary/10 text-primary" x-text="joinModal.selectedMemberIds.length + ' ' + @js(__('club.selected_suffix'))"></span>
                </div>

                <div class="space-y-2">
                    <template x-for="member in joinModal.familyMembers" :key="member.id">
                        <button type="button" @click="joinModal.toggleMember(member)"
                                class="m-press w-full flex items-center gap-3 p-3 rounded-2xl border-2 text-left transition-all"
                                :class="joinModal.isMemberSelected(member.id) ? 'border-primary bg-primary/5' : 'border-gray-100 bg-white'">
                            <span class="shrink-0 w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all"
                                  :class="joinModal.isMemberSelected(member.id) ? 'bg-primary border-primary' : 'border-gray-300'">
                                <i x-show="joinModal.isMemberSelected(member.id)" class="bi bi-check text-white text-sm"></i>
                            </span>
                            <span class="shrink-0">
                                <template x-if="member.profile_picture"><img :src="'/storage/' + member.profile_picture" class="w-11 h-11 rounded-full object-cover" alt=""></template>
                                <template x-if="!member.profile_picture"><span class="w-11 h-11 rounded-full bg-gradient-to-br from-primary to-purple-400 text-white flex items-center justify-center font-bold" x-text="member.name.charAt(0).toUpperCase()"></span></template>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-1.5">
                                    <span class="font-semibold text-sm text-foreground truncate" x-text="member.name"></span>
                                    <template x-if="member.type === 'guardian'"><span class="shrink-0 bg-primary/10 text-primary px-1.5 py-0.5 rounded text-[9px] font-bold uppercase">{{ __('club.you') }}</span></template>
                                </span>
                                <span class="block text-[12px] text-muted-foreground mt-0.5">
                                    <span class="capitalize" x-text="member.relationship"></span><template x-if="member.age !== null"><span x-text="' · ' + member.age + ' ' + @js(__('club.yrs'))"></span></template><template x-if="member.gender"><span x-text="' · ' + joinModal.genderLabel(member.gender)"></span></template>
                                </span>
                            </span>
                        </button>
                    </template>
                    <template x-if="joinModal.familyMembers.length === 0">
                        <div class="text-center py-10 text-muted-foreground bg-white rounded-2xl border border-gray-100">
                            <i class="bi bi-people text-3xl opacity-40"></i>
                            <p class="font-medium mt-2 text-sm">{{ __('club.no_eligible_people') }}</p>
                            <p class="text-[12px] mt-0.5">{{ __('club.add_family_note') }}</p>
                        </div>
                    </template>
                </div>
            </div>

            {{-- ===== Step 2: Packages (multi-registrant flow) ===== --}}
            <div x-show="joinModal.step === 'package-selection'" x-cloak>
                <div class="text-center mb-4">
                    <span class="inline-flex w-14 h-14 rounded-2xl bg-accent text-primary items-center justify-center mb-2"><i class="bi bi-box-seam text-2xl"></i></span>
                    <h2 class="text-lg font-bold text-foreground">{{ __('club.choose_package') }}</h2>
                    <p class="text-[13px] text-muted-foreground">{{ __('club.one_per_person') }}</p>
                </div>

                <template x-if="joinModal.loadingPackages">
                    <div class="text-center py-12"><div class="animate-spin rounded-full h-9 w-9 border-2 border-primary border-t-transparent mx-auto mb-3"></div><p class="text-muted-foreground text-sm">{{ __('club.loading_packages') }}</p></div>
                </template>

                <template x-if="!joinModal.loadingPackages">
                    <div class="space-y-5">
                        <template x-for="reg in joinModal.registrants" :key="reg.id">
                            <div>
                                <div class="flex items-center gap-2.5 mb-2 px-1">
                                    <template x-if="reg.avatarUrl"><img :src="reg.avatarUrl" class="w-8 h-8 rounded-full object-cover" alt=""></template>
                                    <template x-if="!reg.avatarUrl"><span class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xs" x-text="reg.name.charAt(0).toUpperCase()"></span></template>
                                    <span class="text-sm font-bold text-foreground truncate" x-text="reg.name"></span>
                                </div>
                                <template x-if="joinModal.getEligiblePackages(reg).length === 0">
                                    <div class="flex items-start gap-2 p-3 bg-red-50 border border-red-200 rounded-xl text-[13px] text-red-700"><i class="bi bi-exclamation-circle mt-0.5"></i><span>{{ __('club.no_eligible_packages') }}</span></div>
                                </template>
                                <div class="space-y-2">
                                    <template x-for="pkg in joinModal.getEligiblePackages(reg)" :key="pkg.id">
                                        <button type="button" @click="joinModal.selectPackage(reg.id, pkg.id)"
                                                class="m-press w-full text-left rounded-2xl border-2 p-3.5 transition-all"
                                                :class="reg.packageId == pkg.id ? 'border-primary bg-primary/5' : 'border-gray-100 bg-white'">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="font-bold text-foreground" x-text="pkg.name"></span>
                                                        <template x-if="reg.packageId == pkg.id"><span class="shrink-0 bg-primary text-white px-1.5 py-0.5 rounded-full text-[9px] font-bold"><i class="bi bi-check"></i></span></template>
                                                    </div>
                                                    <p class="text-[11px] text-muted-foreground mt-0.5"><span x-text="pkg.duration_months"></span> {{ __('club.months_short') }}<template x-if="pkg.activity_type"><span x-text="' · ' + pkg.activity_type"></span></template></p>
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <p class="text-lg font-extrabold text-primary leading-none" x-text="joinModal.formatCurrency(pkg.price)"></p>
                                                    <p class="text-[10px] text-muted-foreground">{{ __('club.per_month') }}</p>
                                                </div>
                                            </div>
                                            <template x-if="pkg.schedules && pkg.schedules.length > 0">
                                                <p class="text-[11px] text-muted-foreground mt-2 pt-2 border-t border-gray-100"><i class="bi bi-calendar-week text-primary"></i> <span x-text="pkg.schedules[0].days + ' · ' + pkg.schedules[0].time"></span></p>
                                            </template>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            {{-- ===== Step 3: Review & Payment ===== --}}
            <div x-show="joinModal.step === 'payment-review'" x-cloak>
                <div class="text-center mb-4">
                    <span class="inline-flex w-14 h-14 rounded-2xl bg-accent text-primary items-center justify-center mb-2"><i class="bi bi-credit-card text-2xl"></i></span>
                    <h2 class="text-lg font-bold text-foreground">{{ __('club.review_pay') }}</h2>
                    <p class="text-[13px] text-muted-foreground">{{ __('club.confirm_upload') }}</p>
                </div>

                {{-- Registrant lines --}}
                <div class="space-y-2 mb-4">
                    <template x-for="reg in joinModal.registrants" :key="reg.id">
                        <div class="flex items-center justify-between p-3 bg-white rounded-2xl border border-gray-100">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <template x-if="reg.avatarUrl"><img :src="reg.avatarUrl" class="w-9 h-9 rounded-full object-cover" alt=""></template>
                                <template x-if="!reg.avatarUrl"><span class="w-9 h-9 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xs" x-text="reg.name.charAt(0).toUpperCase()"></span></template>
                                <div class="min-w-0">
                                    <p class="font-semibold text-sm text-foreground truncate" x-text="reg.name"></p>
                                    <p class="text-[12px] text-muted-foreground truncate" x-text="joinModal.getPackageForRegistrant(reg)?.name || @js(__('club.no_package'))"></p>
                                </div>
                            </div>
                            <p class="font-bold text-primary shrink-0" x-text="joinModal.formatCurrency(joinModal.getPackageForRegistrant(reg)?.price || 0)"></p>
                        </div>
                    </template>
                </div>

                {{-- Billing summary --}}
                <div class="bg-white rounded-2xl border border-gray-100 p-4 mb-4 space-y-2">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-1">{{ __('club.billing') }}</p>
                    <template x-if="joinModal.enrollmentFee > 0 && joinModal.firstTimerCount() > 0">
                        <div class="flex justify-between text-[13px]"><span class="text-muted-foreground">{{ __('club.enrolment_fee') }} (<span x-text="joinModal.firstTimerCount()"></span>)</span><span class="font-medium" x-text="(joinModal.enrollmentFee * joinModal.firstTimerCount()).toFixed(2) + ' ' + joinModal.currency"></span></div>
                    </template>
                    <div class="flex justify-between text-[13px]"><span class="text-muted-foreground">{{ __('club.subtotal') }}</span><span class="font-medium" x-text="joinModal.calculateSubtotal() + ' ' + joinModal.currency"></span></div>
                    <template x-if="joinModal.vatRegNumber && joinModal.vatPercentage > 0">
                        <div class="flex justify-between text-[13px]"><span class="text-muted-foreground">{{ __('club.vat') }} (<span x-text="joinModal.vatPercentage"></span>%)</span><span class="font-medium" x-text="joinModal.calculateVat() + ' ' + joinModal.currency"></span></div>
                    </template>
                    <div class="flex justify-between items-center pt-2 mt-1 border-t-2 border-primary/20">
                        <span class="font-bold text-foreground">{{ __('club.total') }}</span>
                        <span class="text-xl font-extrabold text-primary" x-text="joinModal.calculateTotal() + ' ' + joinModal.currency"></span>
                    </div>
                </div>

                {{-- Payment proof --}}
                <div class="bg-white rounded-2xl border border-gray-100 p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2">{{ __('club.payment') }}</p>
                    <label class="flex items-start gap-3 p-3 rounded-xl cursor-pointer transition-colors" :class="joinModal.payLater ? 'bg-primary/5' : 'bg-muted/50'">
                        <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-primary focus:ring-primary" x-model="joinModal.payLater" @change="if(joinModal.payLater) { joinModal.paymentScreenshot = false; if(typeof removeImage_joinPaymentProofCropper === 'function') removeImage_joinPaymentProofCropper(); }">
                        <span>
                            <span class="font-semibold text-sm text-foreground">{{ __('club.pay_later') }}</span>
                            <p class="text-[12px] text-muted-foreground mt-0.5">{{ __('club.pay_later_note') }}</p>
                        </span>
                    </label>

                    <template x-if="!joinModal.payLater">
                        <div class="space-y-3 mt-3">
                            <div class="flex items-start gap-2 p-3 bg-blue-50 border border-blue-100 rounded-xl text-[12px] text-blue-700"><i class="bi bi-info-circle mt-0.5"></i><span>{{ __('club.upload_note') }}</span></div>
                            <x-takeone-cropper
                                id="joinPaymentProofCropper"
                                :width="900"
                                :height="600"
                                :canvasHeight="680"
                                shape="rectangle"
                                mode="form"
                                inputName="payment_proof_base64"
                                folder="payment-proofs"
                                :filename="'proof_' . time()"
                                :previewWidth="320"
                                :previewHeight="200"
                                :buttonText="__('club.upload_screenshot')"
                                buttonClass="m-press w-full px-4 py-3 border-2 border-dashed border-primary/30 rounded-xl text-sm font-semibold text-primary hover:bg-accent/40 transition-colors flex items-center justify-center gap-2 bg-white"
                            />
                            <div x-show="joinModal.paymentScreenshot" class="flex items-center gap-1.5 text-[12px] text-green-600 font-medium"><i class="bi bi-check-circle-fill"></i> {{ __('club.screenshot_uploaded') }}</div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="shrink-0 flex items-center gap-3 px-4 py-3 border-t border-gray-100 bg-white" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            <button type="button" @click="joinModal.goBack()"
                    class="m-press px-5 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-muted-foreground">
                <span x-text="joinModal.step === 'select-members' ? @js(__('shared.cancel')) : @js(__('shared.back'))"></span>
            </button>
            <button type="button" @click="joinModal.goNext()"
                    class="m-press flex-1 py-3 rounded-xl bg-primary text-white text-sm font-bold flex items-center justify-center gap-1.5 transition-opacity"
                    :disabled="(joinModal.step === 'select-members' && joinModal.selectedMemberIds.length === 0) || (joinModal.step === 'payment-review' && !joinModal.payLater && !joinModal.paymentScreenshot) || joinModal.submitting"
                    :class="((joinModal.step === 'select-members' && joinModal.selectedMemberIds.length === 0) || (joinModal.step === 'payment-review' && !joinModal.payLater && !joinModal.paymentScreenshot) || joinModal.submitting) ? 'opacity-50' : ''">
                <span x-show="joinModal.submitting" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
                <span x-text="joinModal.step === 'payment-review' ? @js(__('club.complete_registration')) : @js(__('club.continue'))"></span>
                <template x-if="joinModal.step !== 'payment-review'"><i class="bi bi-chevron-right text-xs"></i></template>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    $(document).on('hidden.bs.modal', '#cropperModal_joinPaymentProofCropper', function () {
        const val = document.getElementById('hiddenInput_joinPaymentProofCropper')?.value;
        if (val && window._joinModal) window._joinModal.paymentScreenshot = true;
    });
});
</script>
@endpush
