{{--
    Member Quick-View Popup Modal
    ─────────────────────────────
    Standalone partial — include once on any page that needs it.
    Triggered by calling window.openMemberPopup(userId, fetchUrl).
    Requires: Bootstrap Icons, Tailwind CSS (already in app layout).
--}}

@once
@push('styles')
<style>
/* ── Member Popup ───────────────────────────────────────────────── */
#memberPopupModal .popup-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 0.625rem;
    padding: 8px 14px;
    transition: background 0.15s, border-color 0.15s;
}
#memberPopupModal .popup-info-row:hover {
    background: #f3e8ff;
    border-color: #e9d5ff;
}
#memberPopupModal .popup-info-key {
    font-size: 0.73rem;
    color: #6b7280;
    flex-shrink: 0;
    white-space: nowrap;
}
#memberPopupModal .popup-info-val {
    font-size: 0.8rem;
    font-weight: 600;
    color: #111827;
    text-align: right;
    word-break: break-all;
}
#memberPopupModal .popup-payment-scroll {
    max-height: 230px;
    overflow-y: auto;
    overflow-x: auto;
    border-radius: 0.75rem;
    border: 1px solid #e5e7eb;
    background: #fff;
}
#memberPopupModal .popup-payment-scroll thead th {
    position: sticky;
    top: 0;
    background: #f8f9fa;
    z-index: 2;
    box-shadow: 0 1px 0 #e5e7eb;
}
#memberPopupModal .popup-payment-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
#memberPopupModal .popup-payment-scroll::-webkit-scrollbar-track { background: #f9fafb; border-radius: 99px; }
#memberPopupModal .popup-payment-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 99px; }
#memberPopupModal .popup-payment-scroll::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
/* Bottom-sheet slide-up on phones (centered card on larger screens). */
@media (max-width: 639px) {
    #memberPopupModal .mp-panel { animation: mp-sheet-up 0.28s cubic-bezier(0.22, 0.61, 0.36, 1); }
}
@keyframes mp-sheet-up { from { transform: translateY(28px); opacity: 0.4; } to { transform: none; opacity: 1; } }
</style>
@endpush
@endonce

{{-- Remove Member — name confirmation modal --}}
<div id="mpRemoveModal" class="hidden fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 px-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm">
        <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
            <div class="w-9 h-9 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                <i class="bi bi-person-dash text-red-500 text-base"></i>
            </div>
            <div>
                <h4 class="text-sm font-bold text-gray-900">{{ __('admin.partials_member_popup_remove_from_club_title') }}</h4>
                <p class="text-xs text-gray-400 mt-0.5">{{ __('admin.partials_member_popup_cannot_be_undone_here') }}</p>
            </div>
        </div>
        <div class="px-5 py-4 space-y-3">
            <p class="text-sm text-gray-600">
                {{ __('admin.partials_member_popup_remove_explain') }}
            </p>
            <div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs text-amber-700">
                <i class="bi bi-exclamation-triangle me-1"></i>
                {{ __('admin.partials_member_popup_type_fullname_confirm') }}
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">
                    {{ __('admin.partials_member_popup_type_pre') }} <span id="mpRemoveNameExpected" class="font-bold text-gray-800"></span> {{ __('admin.partials_member_popup_type_post') }}
                </label>
                <input id="mpRemoveNameInput" type="text" autocomplete="off"
                       placeholder="{{ __('admin.partials_member_popup_fullname_placeholder') }}"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-red-400 focus:border-transparent outline-none">
            </div>
        </div>
        <div class="flex gap-2 px-5 py-4 border-t border-gray-100">
            <button onclick="document.getElementById('mpRemoveModal').classList.add('hidden')"
                    class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                {{ __('shared.cancel') }}
            </button>
            <button id="mpRemoveConfirmBtn" disabled
                    class="flex-1 px-4 py-2 text-sm font-semibold text-white bg-red-500 rounded-lg hover:bg-red-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                {{ __('admin.partials_member_popup_remove_member') }}
            </button>
        </div>
    </div>
</div>

<!-- Member Quick-View Popup Modal -->
<div id="memberPopupModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-full items-end justify-center sm:items-center p-0 sm:p-4">

        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-black/50" onclick="closeMemberPopup()"></div>

        {{-- Panel — bottom sheet on mobile, centered card on desktop --}}
        <div class="mp-panel relative bg-white w-full sm:max-w-[700px] max-h-[92vh] overflow-y-auto rounded-t-3xl sm:rounded-3xl shadow-2xl">

            {{-- Grab handle (mobile only) --}}
            <div class="sm:hidden sticky top-0 z-30 bg-white/95 backdrop-blur pt-2.5 pb-1 flex justify-center">
                <span class="w-10 h-1.5 rounded-full bg-gray-300"></span>
            </div>

            {{-- Top bar: back (in sub-views) · title · QR + close --}}
            <div class="flex items-center justify-between gap-2 px-4 pt-3 pb-1">
                <div class="flex items-center gap-1.5 min-w-0">
                    <button type="button" id="mpHeaderBack" onclick="mpBack()" aria-label="{{ __('shared.back') }}"
                            class="hidden w-9 h-9 -ms-1 rounded-full bg-muted text-gray-600 grid place-items-center hover:bg-gray-200 transition-colors flex-shrink-0"><i class="bi bi-chevron-left text-lg"></i></button>
                    <span id="mpHeaderTitle" class="text-sm font-bold text-gray-900 truncate"></span>
                </div>
                <div class="flex items-center gap-1.5 flex-shrink-0">
                    <button type="button" onclick="openMpQr()" aria-label="{{ __('admin.partials_member_popup_member_qr') }}" title="{{ __('admin.partials_member_popup_member_qr') }}"
                            class="m-press w-9 h-9 rounded-full bg-accent text-primary grid place-items-center hover:bg-primary hover:text-white transition-colors"><i class="bi bi-qr-code"></i></button>
                    <button type="button" onclick="closeMemberPopup()" aria-label="{{ __('admin.partials_member_popup_close') }}"
                            class="m-press w-9 h-9 rounded-full bg-muted text-gray-500 grid place-items-center hover:bg-gray-200 transition-colors"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>

            {{-- Identity (profile view only) --}}
            <div id="mpIdentity" class="px-6 pt-1 pb-1">
                <div class="flex items-center gap-4">
                    <div id="mpAvatar"
                         class="w-20 h-20 rounded-2xl grid place-items-center text-white font-bold text-3xl shadow ring-1 ring-gray-100 overflow-hidden flex-shrink-0 bg-muted"></div>
                    <div class="min-w-0">
                        <h4 id="mpName" class="font-extrabold text-gray-900 text-lg leading-tight truncate"></h4>
                        <span id="mpMemberId" class="inline-block mt-1.5 text-[11px] text-primary bg-accent rounded-full px-2.5 py-0.5 font-semibold"></span>
                    </div>
                </div>
            </div>

            {{-- ===== View: profile ===== --}}
            <div id="mpProfileView" class="px-6 pt-5 pb-6 space-y-6">

                {{-- Info tiles --}}
                <div class="grid grid-cols-2 gap-2.5">
                    <div class="rounded-2xl border border-gray-100 bg-gray-50/70 p-3 min-w-0">
                        <p class="text-[11px] text-muted-foreground flex items-center gap-1.5"><i class="bi bi-telephone text-primary"></i> {{ __('admin.partials_member_popup_phone') }}</p>
                        <p id="mpPhone" class="text-sm font-semibold text-gray-900 mt-1 truncate"></p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50/70 p-3 min-w-0">
                        <p class="text-[11px] text-muted-foreground flex items-center gap-1.5"><i class="bi bi-envelope text-primary"></i> {{ __('admin.partials_member_popup_email') }}</p>
                        <p id="mpEmail" class="text-sm font-semibold text-gray-900 mt-1 truncate"></p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50/70 p-3 min-w-0">
                        <p class="text-[11px] text-muted-foreground flex items-center gap-1.5"><i class="bi bi-person text-primary"></i> {{ __('admin.partials_member_popup_age_gender') }}</p>
                        <p id="mpAgeGender" class="text-sm font-semibold text-gray-900 mt-1 truncate"></p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50/70 p-3 min-w-0">
                        <p class="text-[11px] text-muted-foreground flex items-center gap-1.5"><i class="bi bi-calendar3 text-primary"></i> {{ __('admin.partials_member_popup_member_since') }}</p>
                        <p id="mpSince" class="text-sm font-semibold text-gray-900 mt-1 truncate"></p>
                    </div>
                </div>

                {{-- Single Actions menu --}}
                <div class="relative">
                    <button type="button" onclick="mpToggleActions()"
                            class="m-press w-full inline-flex items-center justify-center gap-2 bg-primary text-white rounded-xl py-3 px-4 font-semibold text-sm hover:bg-primary/90 transition-colors">
                        <i class="bi bi-sliders2"></i> {{ __('admin.partials_member_popup_actions') }} <i class="bi bi-chevron-down text-xs opacity-80"></i>
                    </button>
                    <div id="mpActionsMenu" class="hidden mt-2 rounded-2xl border border-gray-100 shadow-lg bg-white overflow-hidden divide-y divide-gray-50">
                        <a id="mpProfileLink" href="#" onclick="mpHideActions()"
                           class="flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-800 hover:bg-muted transition-colors no-underline">
                            <i class="bi bi-person-circle text-primary w-4"></i> {{ __('admin.partials_member_popup_view_full_profile') }}
                        </a>
                        <button type="button" onclick="mpHideActions(); openMpPayments()"
                                class="w-full text-start flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-800 hover:bg-muted transition-colors">
                            <i class="bi bi-receipt text-primary w-4"></i> {{ __('admin.partials_member_popup_payments') }}
                        </button>
                        <button id="mpEnrollBtn" type="button" onclick="mpHideActions(); openMemberEnroll()"
                                class="w-full text-start flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-800 hover:bg-muted transition-colors">
                            <i class="bi bi-plus-circle text-green-600 w-4"></i> {{ __('admin.partials_member_popup_enroll_in_package') }}
                        </button>
                        {{-- Shown only when this member's email isn't verified yet --}}
                        <button id="mpVerifyBtn" type="button" onclick="mpHideActions(); verifyMemberEmail()"
                                class="hidden w-full text-start flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-800 hover:bg-muted transition-colors">
                            <i class="bi bi-patch-check text-green-600 w-4"></i> {{ __('admin.partials_member_popup_verify_email') }}
                        </button>
                        @if(auth()->user()?->isSuperAdmin())
                        @isset($club)
                        <button id="mpPermsBtn" type="button" onclick="mpHideActions(); openMpPermissions()"
                                class="w-full text-start flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-800 hover:bg-muted transition-colors">
                            <i class="bi bi-shield-lock text-primary w-4"></i> {{ __('admin.partials_member_popup_manage_permissions') }}
                        </button>
                        @endisset
                        <button id="mpImpersonateBtn" type="button" onclick="mpHideActions(); impersonateMember()"
                                class="w-full text-start flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-800 hover:bg-muted transition-colors">
                            <i class="bi bi-incognito text-amber-500 w-4"></i> {{ __('admin.partials_member_popup_login_as_member') }}
                        </button>
                        @endif
                        <button id="mpRemoveBtn" type="button" onclick="mpHideActions(); removeMemberFromClub()"
                                class="w-full text-start flex items-center gap-3 px-4 py-3 text-sm font-medium text-red-600 hover:bg-red-50 transition-colors">
                            <i class="bi bi-person-dash w-4"></i> {{ __('admin.partials_member_popup_remove_from_club') }}
                        </button>
                        @if(auth()->user()?->isSuperAdmin())
                        {{-- Delete the whole account — super-admin only, platform members page only --}}
                        <button id="mpDeleteBtn" type="button" onclick="mpHideActions(); deleteMemberAccount()"
                                class="hidden w-full text-start flex items-center gap-3 px-4 py-3 text-sm font-semibold text-red-700 hover:bg-red-50 transition-colors border-t border-red-100">
                            <i class="bi bi-trash w-4"></i> {{ __('admin.partials_member_popup_delete_member') }}
                        </button>
                        @endif
                    </div>
                </div>

            </div>

            {{-- ===== View: payments list ===== --}}
            <div id="mpPaymentsView" class="hidden px-6 pt-5 pb-6">
                <div class="flex gap-1 p-1 rounded-xl bg-muted mb-3">
                    <button type="button" data-tab="all" onclick="setMpPayTab('all')" class="mp-pay-tab flex-1 py-1.5 rounded-lg text-xs font-bold transition-colors bg-white shadow text-foreground">{{ __('admin.partials_member_popup_tab_all') }}</button>
                    <button type="button" data-tab="pending" onclick="setMpPayTab('pending')" class="mp-pay-tab flex-1 py-1.5 rounded-lg text-xs font-bold transition-colors text-muted-foreground">{{ __('admin.partials_member_popup_tab_pending') }}</button>
                    <button type="button" data-tab="paid" onclick="setMpPayTab('paid')" class="mp-pay-tab flex-1 py-1.5 rounded-lg text-xs font-bold transition-colors text-muted-foreground">{{ __('admin.partials_member_popup_tab_paid') }}</button>
                </div>
                <div id="mpPaymentList" class="space-y-2 pe-0.5">
                    <div class="text-center text-gray-400 text-sm py-10">{{ __('admin.partials_member_popup_no_payment_records') }}</div>
                </div>
            </div>

            {{-- ===== View: record detail ===== --}}
            <div id="mpDetailView" class="hidden px-6 pt-5 pb-6 space-y-5">

                {{-- Transaction info --}}
                <div class="grid grid-cols-2 gap-2.5 text-sm">
                    <div class="rounded-2xl border border-gray-100 bg-gray-50/70 p-3 col-span-2">
                        <p class="text-muted-foreground text-[11px] uppercase font-medium mb-0.5">{{ __('admin.partials_member_popup_package') }}</p>
                        <p class="font-semibold text-gray-900" id="mpTxPackage">—</p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50/70 p-3">
                        <p class="text-muted-foreground text-[11px] uppercase font-medium mb-0.5">{{ __('admin.partials_member_popup_amount') }}</p>
                        <p class="font-bold text-green-600" id="mpTxAmount">—</p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50/70 p-3">
                        <p class="text-muted-foreground text-[11px] uppercase font-medium mb-0.5">{{ __('admin.partials_member_popup_status') }}</p>
                        <span id="mpTxStatus"></span>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50/70 p-3">
                        <p class="text-muted-foreground text-[11px] uppercase font-medium mb-0.5">{{ __('admin.partials_member_popup_start_date') }}</p>
                        <p class="font-semibold text-gray-900" id="mpTxStart">—</p>
                    </div>
                    <div class="rounded-2xl border border-gray-100 bg-gray-50/70 p-3">
                        <p class="text-muted-foreground text-[11px] uppercase font-medium mb-0.5">{{ __('admin.partials_member_popup_end_date') }}</p>
                        <p class="font-semibold text-gray-900" id="mpTxEnd">—</p>
                    </div>
                </div>

                {{-- Member's uploaded proof --}}
                <div id="mpTxProofWrap" class="hidden">
                    <p class="text-sm font-semibold mb-2">{{ __('admin.partials_member_popup_payment_proof') }}</p>
                    <img id="mpTxProofImg" src="" alt="{{ __('admin.partials_member_popup_payment_proof_alt') }}"
                         class="w-full rounded-lg border border-gray-200 cursor-pointer"
                         onclick="window.open(this.src,'_blank')">
                    <p class="text-xs text-muted-foreground mt-1">{{ __('admin.partials_member_popup_tap_full_size') }}</p>
                </div>

                {{-- Approve section (unpaid/pending) --}}
                <div id="mpTxApproveSection" class="border-t border-gray-100 pt-4 space-y-4">
                    <p class="text-sm font-semibold text-gray-900">{{ __('admin.partials_member_popup_approve_payment') }}</p>
                    <div>
                        <p class="text-xs text-muted-foreground mb-2">{{ __('admin.partials_member_popup_upload_own_proof') }}</p>
                        <x-takeone-cropper
                            id="mpAdminProofCropper"
                            :width="900"
                            :height="600"
                            :canvasHeight="680"
                            shape="rectangle"
                            mode="form"
                            inputName="mp_admin_proof_base64"
                            folder="payment-proofs"
                            :filename="'admin_proof_' . time()"
                            :previewWidth="320"
                            :previewHeight="200"
                            buttonText="{{ __('admin.partials_member_popup_upload_admin_proof') }}"
                            buttonClass="w-full px-4 py-2 border border-dashed border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors flex items-center justify-center gap-2 bg-white text-gray-600"
                        />
                    </div>
                    <button type="button" id="mpConfirmApproveBtn"
                            class="m-press w-full inline-flex items-center justify-center gap-2 bg-green-500 text-white rounded-xl py-2.5 px-4 font-semibold text-sm hover:bg-green-600 transition-colors"
                            onclick="confirmMpApprove()">
                        <i class="bi bi-check-circle"></i> {{ __('admin.partials_member_popup_confirm_approve') }}
                    </button>
                </div>

                {{-- Already paid --}}
                <div id="mpTxPaidSection" class="hidden rounded-xl bg-green-50 border border-green-200 p-3">
                    <div class="flex items-center gap-2 text-green-700">
                        <i class="bi bi-check-circle-fill"></i>
                        <span class="font-semibold text-sm">{{ __('admin.partials_member_popup_payment_approved') }}</span>
                    </div>
                </div>

                {{-- Back to payments --}}
                <button type="button" onclick="mpPayShowList()"
                        class="m-press w-full px-4 py-2.5 border border-gray-200 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition-colors text-sm inline-flex items-center justify-center gap-2">
                    <i class="bi bi-chevron-left"></i> {{ __('admin.partials_member_popup_back_to_payments') }}
                </button>
            </div>

            {{-- ===== View: member QR ===== --}}
            <div id="mpQrView" class="hidden px-6 pt-5 pb-6">
                <div class="mx-auto bg-white rounded-2xl border border-gray-100 p-4 grid place-items-center" style="width: 100%; max-width: 252px;">
                    <div id="mpQrBox" class="grid place-items-center" style="width: 220px; height: 220px; max-width: 100%;"></div>
                </div>
                <a id="mpQrUrlLink" href="#" target="_blank" rel="noopener" class="block text-center text-[11px] text-primary break-all mt-3 hover:underline"></a>
                <div class="grid grid-cols-2 gap-2 mt-4">
                    <button type="button" onclick="mpCopyQr()" class="m-press inline-flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg border border-gray-200 text-foreground text-sm font-medium hover:bg-muted transition-colors">
                        <i class="bi bi-link-45deg"></i> {{ __('admin.partials_member_popup_copy_link') }}
                    </button>
                    <a id="mpQrPosterLink" href="#" target="_blank" rel="noopener" class="m-press inline-flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary/90 transition-colors no-underline">
                        <i class="bi bi-printer"></i> {{ __('admin.partials_member_popup_poster') }}
                    </a>
                </div>
            </div>

            {{-- ===== View: permissions (super-admin) ===== --}}
            <div id="mpPermissionsView" class="hidden px-6 pt-5 pb-6">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-bold uppercase tracking-wide text-muted-foreground">{{ __('admin.partials_member_popup_permissions_label') }} (<span id="mpPermCount">0</span>/<span id="mpPermTotal">0</span>)</span>
                    <div class="flex gap-2">
                        <button type="button" onclick="mpPermSetAll(true)" class="text-xs px-2.5 py-1 rounded-md bg-accent text-primary hover:bg-primary hover:text-white transition-colors">{{ __('admin.partials_member_popup_select_all') }}</button>
                        <button type="button" onclick="mpPermSetAll(false)" class="text-xs px-2.5 py-1 rounded-md bg-muted text-gray-600 hover:bg-gray-200 transition-colors">{{ __('admin.partials_member_popup_clear_all') }}</button>
                    </div>
                </div>
                <div id="mpPermLoading" class="flex flex-col items-center justify-center py-10 gap-3">
                    <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-sm text-gray-400">{{ __('admin.partials_member_popup_loading_ellipsis') }}</p>
                </div>
                <div id="mpPermList" class="hidden border border-gray-100 rounded-xl p-2 max-h-[46vh] overflow-y-auto"></div>
                <p class="text-xs text-muted-foreground mt-2"><i class="bi bi-info-circle me-1"></i>{{ __('admin.partials_member_popup_perms_replace_note') }}</p>
                <div class="grid grid-cols-2 gap-2 mt-4">
                    <button type="button" onclick="mpShowProfile()" class="m-press inline-flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg border border-gray-200 text-foreground text-sm font-medium hover:bg-muted transition-colors">{{ __('shared.cancel') }}</button>
                    <button type="button" id="mpPermSaveBtn" onclick="saveMpPermissions()" class="m-press inline-flex items-center justify-center gap-2 px-3 py-2.5 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary/90 transition-colors">
                        <i class="bi bi-check-lg"></i> {{ __('admin.partials_member_popup_save_permissions') }}
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Enroll Member Modal -->
<div id="mpEnrollModal" class="fixed inset-0 hidden overflow-y-auto" style="z-index:99999;">
    <div class="fixed inset-0 bg-black/60" onclick="closeMpEnroll()"></div>
    <div class="relative flex min-h-full items-center justify-center p-4" style="z-index:1;">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg" onclick="event.stopPropagation()">

            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <h5 class="font-bold flex items-center gap-2 mb-0 text-gray-900">
                    <i class="bi bi-plus-circle text-green-500"></i>
                    <span id="mpEnrollTitle">{{ __('admin.partials_member_popup_enroll_member') }}</span>
                </h5>
                <button onclick="closeMpEnroll()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="px-6 py-5">

                <div id="mpEnrollLoading" class="flex flex-col items-center justify-center py-10 gap-3">
                    <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-sm text-gray-400">{{ __('admin.partials_member_popup_loading_dots') }}</p>
                </div>

                <div id="mpEnrollClubStep" class="hidden">
                    <p class="text-sm text-gray-500 mb-4">{{ __('admin.partials_member_popup_multiple_clubs') }}</p>
                    <div id="mpEnrollClubList" class="flex flex-col gap-2"></div>
                </div>

                <div id="mpEnrollPackageStep" class="hidden">
                    <p class="text-sm text-gray-500 mb-1" id="mpEnrollPackageSubtitle">{{ __('admin.partials_member_popup_select_package_enroll') }}</p>
                    <div id="mpEnrollPackageList" class="flex flex-col gap-2 max-h-72 overflow-y-auto pe-1 mt-3"></div>
                </div>

                <div id="mpEnrollEmpty" class="hidden flex flex-col items-center justify-center py-10 text-center gap-2">
                    <i class="bi bi-inbox text-3xl text-gray-300"></i>
                    <p class="text-sm text-gray-400">{{ __('admin.partials_member_popup_no_eligible_packages') }}</p>
                </div>

                <div id="mpEnrollFooter" class="hidden pt-4 border-t border-gray-100 mt-4 flex flex-col sm:flex-row gap-2">
                    <button id="mpEnrollConfirmBtn" onclick="confirmMpEnroll()" disabled
                            class="flex-1 flex items-center justify-center gap-2 bg-green-500 text-white rounded-lg py-2.5 px-5 font-semibold text-sm hover:bg-green-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="bi bi-check-circle"></i> {{ __('admin.partials_member_popup_confirm_enrollment') }}
                    </button>
                    <button onclick="closeMpEnroll()"
                            class="flex-1 flex items-center justify-center gap-2 bg-gray-100 text-gray-700 border border-gray-200 rounded-lg py-2.5 px-5 font-semibold text-sm hover:bg-gray-200 transition-colors">
                        {{ __('shared.cancel') }}
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
(function () {
    // ── Member Popup ────────────────────────────────────────────────
    window._mpSubStore = {};

    // Escape user-supplied values before inserting via innerHTML (prevents stored XSS).
    function mpEsc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    window.openMemberPopup = function (userId, fetchUrl) {
        const modal = document.getElementById('memberPopupModal');
        modal.classList.remove('hidden');
        if (window.mpShowProfile) mpShowProfile();   // always open on the profile view

        fetch(fetchUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => {
            if (!r.ok) throw new Error('Failed to load member data');
            return r.json();
        })
        .then(d => {
            _populatePopup(d);
        })
        .catch(() => {
            if (window.showToast) window.showToast('error', '{{ __("admin.partials_member_popup_failed_load_member") }}');
        });
    };

    window.closeMemberPopup = function () {
        document.getElementById('memberPopupModal').classList.add('hidden');
    };

    // The member popup is a single panel with three views: profile · payments · detail.
    const MP_TITLES = { profile: '', payments: '{{ __("admin.partials_member_popup_payment_history") }}', detail: '{{ __("admin.partials_member_popup_transaction_detail") }}', qr: '{{ __("admin.partials_member_popup_member_qr") }}', permissions: '{{ __("admin.partials_member_popup_manage_permissions") }}' };
    function mpSetView(view) {
        window._mpView = view;
        const isProfile = view === 'profile';
        document.getElementById('mpProfileView')?.classList.toggle('hidden', view !== 'profile');
        document.getElementById('mpPaymentsView')?.classList.toggle('hidden', view !== 'payments');
        document.getElementById('mpDetailView')?.classList.toggle('hidden', view !== 'detail');
        document.getElementById('mpQrView')?.classList.toggle('hidden', view !== 'qr');
        document.getElementById('mpPermissionsView')?.classList.toggle('hidden', view !== 'permissions');
        document.getElementById('mpIdentity')?.classList.toggle('hidden', !isProfile);
        document.getElementById('mpHeaderBack')?.classList.toggle('hidden', isProfile);

        const title = document.getElementById('mpHeaderTitle'); if (title) title.textContent = MP_TITLES[view] || '';
        mpHideActions();
        const panel = document.querySelector('#memberPopupModal .mp-panel'); if (panel) panel.scrollTop = 0;
    }

    // Single Actions menu (profile view).
    window.mpToggleActions = function () { document.getElementById('mpActionsMenu')?.classList.toggle('hidden'); };
    window.mpHideActions = function () { document.getElementById('mpActionsMenu')?.classList.add('hidden'); };
    document.addEventListener('click', function (e) {
        const menu = document.getElementById('mpActionsMenu');
        if (!menu || menu.classList.contains('hidden')) return;
        if (e.target.closest('#mpActionsMenu') || e.target.closest('[onclick*="mpToggleActions"]')) return;
        menu.classList.add('hidden');
    });

    window.mpShowProfile  = function () { mpSetView('profile'); };
    window.openMpPayments = function () { mpSetView('payments'); };   // "Payments" button
    window.mpPayShowList  = function () { mpSetView('payments'); };   // back from a record
    function mpShowDetail() { mpSetView('detail'); }
    // Header back arrow: detail → payments, payments/qr → profile.
    window.mpBack = function () { mpSetView(window._mpView === 'detail' ? 'payments' : 'profile'); };

    // Member QR — rendered client-side (offline qrcodejs) inside this same popup.
    window.openMpQr = function () {
        const d = window._mpData || {};
        const url = d.qr_url || d.profile_url || '';
        const link = document.getElementById('mpQrUrlLink');
        if (link) { link.textContent = url; link.href = url || '#'; }
        const poster = document.getElementById('mpQrPosterLink'); if (poster) poster.href = d.qr_poster_url || '#';
        mpSetView('qr');
        const box = document.getElementById('mpQrBox');
        if (!box) return;
        // Offline, server-rendered QR (bacon) served as an SVG image — no external libs.
        box.innerHTML = (d.qr_svg_url && url)
            ? '<img src="' + mpEsc(d.qr_svg_url) + '" alt="{{ __("admin.partials_member_popup_member_qr") }}" class="w-full h-full object-contain">'
            : '<p class="text-xs text-gray-400">{{ __("admin.partials_member_popup_no_link_available") }}</p>';
    };
    window.mpCopyQr = function () {
        const url = (window._mpData || {}).qr_url || '';
        if (!url) return;
        navigator.clipboard.writeText(url)
            .then(() => window.showToast && window.showToast('success', '{{ __("admin.partials_member_popup_link_copied") }}'))
            .catch(() => window.showToast && window.showToast('error', '{{ __("admin.partials_member_popup_could_not_copy") }}'));
    };

    // Start impersonating the member shown in the popup (super-admin only).
    window.impersonateMember = function () {
        const id = window._mpCurrentUserId;
        if (!id) return;
        const token = document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}';
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ url('admin/impersonate') }}/' + id;
        form.innerHTML = '<input type="hidden" name="_token" value="' + token + '">';
        document.body.appendChild(form);
        form.submit();
    };

    // ===== Manage permissions (super-admin) =====
    const MP_PERM_GET  = '{{ isset($club) ? route('admin.club.roles.member.permissions', [$club->slug, '__U__']) : '' }}';
    const MP_PERM_SAVE = '{{ isset($club) ? route('admin.club.roles.member.permissions.store', $club->slug) : '' }}';

    window.openMpPermissions = function () {
        const id = window._mpCurrentUserId;
        if (!id) return;
        mpSetView('permissions');
        document.getElementById('mpPermLoading')?.classList.remove('hidden');
        document.getElementById('mpPermList')?.classList.add('hidden');
        window._mpPerms = [];
        window._mpPermGroups = [];

        fetch(MP_PERM_GET.replace('__U__', id), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(d => {
                if (!d.success) throw new Error();
                window._mpPermGroups = d.groups || [];
                window._mpPerms = (d.permissions || []).slice();
                document.getElementById('mpPermTotal').textContent = d.total || 0;
                renderMpPermList();
                document.getElementById('mpPermLoading')?.classList.add('hidden');
                document.getElementById('mpPermList')?.classList.remove('hidden');
            })
            .catch(() => { if (window.showToast) window.showToast('error', '{{ __("admin.partials_member_popup_failed_load_perms") }}'); });
    };

    function renderMpPermList() {
        const wrap = document.getElementById('mpPermList');
        if (!wrap) return;
        wrap.innerHTML = (window._mpPermGroups || []).map(g => `
            <div class="mb-2">
                <div class="text-[11px] font-bold uppercase tracking-wide text-primary/70 px-2 pt-1.5 pb-1">${mpEsc(g.label)}</div>
                ${(g.perms || []).map(p => `
                    <label class="flex items-start gap-2.5 px-2 py-2 rounded-lg hover:bg-muted/50 cursor-pointer">
                        <input type="checkbox" data-perm="${mpEsc(p.slug)}" ${window._mpPerms.includes(p.slug) ? 'checked' : ''}
                               onchange="mpPermToggle(this.dataset.perm)" class="mt-0.5" style="width:16px;height:16px;accent-color:hsl(250 65% 65%);">
                        <span class="min-w-0">
                            <span class="block text-sm text-foreground">${mpEsc(p.name)}</span>
                            <span class="block text-xs text-muted-foreground">${mpEsc(p.desc || '')}</span>
                        </span>
                    </label>
                `).join('')}
            </div>
        `).join('');
        updateMpPermCount();
    }

    window.mpPermToggle = function (slug) {
        const i = window._mpPerms.indexOf(slug);
        i > -1 ? window._mpPerms.splice(i, 1) : window._mpPerms.push(slug);
        updateMpPermCount();
    };

    window.mpPermSetAll = function (v) {
        window._mpPerms = v ? (window._mpPermGroups || []).flatMap(g => (g.perms || []).map(p => p.slug)) : [];
        document.querySelectorAll('#mpPermList input[data-perm]').forEach(cb => { cb.checked = v; });
        updateMpPermCount();
    };

    function updateMpPermCount() {
        const el = document.getElementById('mpPermCount');
        if (el) el.textContent = (window._mpPerms || []).length;
    }

    window.saveMpPermissions = async function () {
        const id = window._mpCurrentUserId;
        if (!id) return;
        const btn = document.getElementById('mpPermSaveBtn');
        if (btn) btn.disabled = true;
        const token = document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}';
        try {
            const res = await fetch(MP_PERM_SAVE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ user_id: id, permissions: window._mpPerms || [] }),
            });
            const d = await res.json().catch(() => ({}));
            if (!res.ok || !d.success) throw new Error(d.message || '{{ __("admin.partials_member_popup_could_not_save_perms") }}');
            if (window.showToast) window.showToast('success', d.message);
            if (typeof window.reloadMemberCards === 'function') window.reloadMemberCards();
            mpShowProfile();
        } catch (e) {
            if (window.showToast) window.showToast('error', e.message);
        } finally {
            if (btn) btn.disabled = false;
        }
    };

    function _populatePopup(d) {
        window._mpSubStore = {};
        window._mpData     = d;

        // Avatar
        const avatarEl = document.getElementById('mpAvatar');
        if (d.has_picture && d.picture_url) {
            avatarEl.innerHTML = `<img src="${mpEsc(d.picture_url)}" alt="${mpEsc(d.name)}" class="w-full h-full object-cover">`;
            avatarEl.style.background = '';
        } else {
            avatarEl.style.background = d.gender === 'Male'
                ? 'linear-gradient(135deg, hsl(250 65% 65%) 0%, hsl(250 65% 60%) 100%)'
                : 'linear-gradient(135deg, #d63384 0%, #a61e4d 100%)';
            avatarEl.textContent = d.initial;
        }

        // Basic info
        document.getElementById('mpName').textContent      = d.name;
        const year = (d.since || '').split('/')[2] ?? new Date().getFullYear();
        document.getElementById('mpMemberId').textContent  = `#MEM-${year}-${String(d.id).padStart(3, '0')}`;
        document.getElementById('mpPhone').textContent     = d.phone;
        document.getElementById('mpEmail').textContent     = d.email;
        document.getElementById('mpAgeGender').textContent = `${d.age} / ${d.gender === 'Male' ? '{{ __("admin.partials_member_popup_male") }}' : '{{ __("admin.partials_member_popup_female") }}'}`;
        document.getElementById('mpSince').textContent     = d.since;
        document.getElementById('mpProfileLink').href      = d.profile_url;
        document.getElementById('mpRemoveBtn').dataset.removeUrl  = d.remove_url;
        document.getElementById('mpRemoveBtn').dataset.memberName = d.name;
        window._mpCurrentUserId = d.id;

        // Verify-email action: only when the member is unverified and we can verify them.
        const verifyBtn = document.getElementById('mpVerifyBtn');
        if (verifyBtn) {
            if (d.verify_email_url && !d.verified) {
                verifyBtn.dataset.url = d.verify_email_url;
                verifyBtn.classList.remove('hidden');
            } else {
                verifyBtn.classList.add('hidden');
            }
        }

        // Delete-account action (super-admin, platform page only — delete_url present there).
        const deleteBtn = document.getElementById('mpDeleteBtn');
        if (deleteBtn) {
            if (d.delete_url) {
                deleteBtn.dataset.url = d.delete_url;
                deleteBtn.dataset.memberName = d.name;
                deleteBtn.classList.remove('hidden');
            } else {
                deleteBtn.classList.add('hidden');
            }
        }

        // Payment records → stored; shown as a tabbed, tappable list in its own modal.
        (d.subscriptions || []).forEach(sub => { window._mpSubStore[sub.id] = sub; });
        window._mpPayTab = 'all';
        renderMpPayments();
        setMpPayTabActive('all');
    }

    // ── Payments list (tabbed) ───────────────────────────────────────
    function mpPayBadge(sub) {
        return sub.payment_status === 'paid'
            ? `<span class="inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded-full bg-green-50 text-green-700 border border-green-200">{{ __("admin.partials_member_popup_badge_paid") }}</span>`
            : `<span class="inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded-full bg-yellow-50 text-yellow-700 border border-yellow-200">{{ __("admin.partials_member_popup_badge_pending") }}</span>`;
    }

    function setMpPayTabActive(tab) {
        document.querySelectorAll('.mp-pay-tab').forEach(b => {
            const on = b.dataset.tab === tab;
            b.classList.toggle('bg-white', on);
            b.classList.toggle('shadow', on);
            b.classList.toggle('text-foreground', on);
            b.classList.toggle('text-muted-foreground', !on);
        });
    }

    window.setMpPayTab = function (tab) {
        window._mpPayTab = tab;
        setMpPayTabActive(tab);
        renderMpPayments();
    };

    window.renderMpPayments = function () {
        const list = document.getElementById('mpPaymentList');
        if (!list) return;
        const tab  = window._mpPayTab || 'all';
        const subs = Object.values(window._mpSubStore || {});
        const filtered = subs.filter(s => tab === 'all'
            ? true
            : (tab === 'paid' ? s.payment_status === 'paid' : s.payment_status !== 'paid'));

        if (!filtered.length) {
            list.innerHTML = `<div class="text-center text-gray-400 text-sm py-10"><i class="bi bi-inbox text-2xl text-gray-300 block mb-1"></i>No ${tab === 'all' ? '' : mpEsc(tab) + ' '}records</div>`;
            return;
        }

        list.innerHTML = filtered.map(sub => {
            const period = sub.is_active
                ? `<span class="text-green-600"><i class="bi bi-play-circle-fill"></i> {{ __("admin.partials_member_popup_active") }}</span>`
                : `<span class="text-gray-400"><i class="bi bi-clock-history"></i> {{ __("admin.partials_member_popup_ended") }}</span>`;
            return `<button type="button" onclick="openMpTxDetail(${sub.id})" id="mp-sub-row-${sub.id}"
                        class="w-full text-start rounded-2xl border border-gray-100 hover:border-primary hover:bg-accent/40 transition-all p-3 flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 bg-accent text-primary"><i class="bi bi-receipt"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-gray-900 truncate">${mpEsc(sub.package)}</span>
                    <span class="block text-[11px] text-gray-500 mt-0.5">${mpEsc(sub.start_date)} → ${mpEsc(sub.end_date)}</span>
                    <span class="block text-[11px] mt-0.5">${period}</span>
                </span>
                <span class="text-end flex-shrink-0">
                    <span class="block text-sm font-bold text-gray-900 whitespace-nowrap">${mpEsc(sub.currency)} ${mpEsc(sub.amount_due)}</span>
                    <span class="block mt-1">${mpPayBadge(sub)}</span>
                </span>
                <i class="bi bi-chevron-right text-gray-300 flex-shrink-0"></i>
            </button>`;
        }).join('');
    };

    // ── Transaction Detail Modal ─────────────────────────────────────
    window._mpCurrentSubId      = null;
    window._mpCurrentApproveUrl = null;
    window._mpCurrentUserId     = null;

    window.openMpTxDetail = function (subId) {
        const sub = window._mpSubStore[subId];
        if (!sub) return;

        window._mpCurrentSubId      = subId;
        window._mpCurrentApproveUrl = sub.approve_url;

        // Populate fields
        document.getElementById('mpTxPackage').textContent = sub.package;
        document.getElementById('mpTxAmount').textContent  = sub.currency + ' ' + sub.amount_due;
        document.getElementById('mpTxStart').textContent   = sub.start_date;
        document.getElementById('mpTxEnd').textContent     = sub.end_date;

        const isPaid = sub.payment_status === 'paid';
        document.getElementById('mpTxStatus').innerHTML = isPaid
            ? `<span class="badge bg-green-100 text-green-700">{{ __("admin.partials_member_popup_tab_paid") }}</span>`
            : `<span class="badge bg-amber-100 text-amber-700">{{ __("admin.partials_member_popup_tab_pending") }}</span>`;

        // Proof image
        const proofWrap = document.getElementById('mpTxProofWrap');
        if (sub.proof_url) {
            document.getElementById('mpTxProofImg').src = sub.proof_url;
            proofWrap.classList.remove('hidden');
        } else {
            proofWrap.classList.add('hidden');
        }

        // Show/hide sections
        document.getElementById('mpTxApproveSection').classList.toggle('hidden', isPaid);
        document.getElementById('mpTxPaidSection').classList.toggle('hidden', !isPaid);

        // Reset approve button
        const btn = document.getElementById('mpConfirmApproveBtn');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> {{ __("admin.partials_member_popup_confirm_approve_btn") }}';

        // Clear previous cropper value
        const hiddenInput = document.getElementById('hiddenInput_mpAdminProofCropper');
        if (hiddenInput) hiddenInput.value = '';

        mpShowDetail();   // swap the popup to the record-detail view
    };

    // Back from the record detail → the payments list (same popup).
    window.closeMpTxDetail = function () {
        mpPayShowList();
    };

    window.confirmMpApprove = async function () {
        const btn = document.getElementById('mpConfirmApproveBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="inline-block me-2">&#8635;</span> {{ __("admin.partials_member_popup_approving") }}';

        try {
            const formData = new FormData();
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            const adminProof = document.getElementById('hiddenInput_mpAdminProofCropper')?.value;
            if (adminProof) formData.append('admin_proof_base64', adminProof);

            const res  = await fetch(window._mpCurrentApproveUrl, {
                method: 'POST', body: formData, headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();

            if (data.success) {
                closeMpTxDetail();
                // Mark the stored record paid and re-render the list.
                if (window._mpSubStore && window._mpSubStore[window._mpCurrentSubId]) {
                    window._mpSubStore[window._mpCurrentSubId].payment_status = 'paid';
                }
                if (window.renderMpPayments) window.renderMpPayments();
                if (window.showToast) window.showToast('success', '{{ __("admin.partials_member_popup_payment_approved_success") }}');
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> {{ __("admin.partials_member_popup_confirm_approve_btn") }}';
                if (window.showToast) window.showToast('error', data.message || '{{ __("admin.partials_member_popup_error_approving") }}');
            }
        } catch (e) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-1"></i> {{ __("admin.partials_member_popup_confirm_approve_btn") }}';
            if (window.showToast) window.showToast('error', '{{ __("admin.partials_member_popup_error_approving") }}');
        }
    };
    // ── Enroll Modal ─────────────────────────────────────────────────
    window._mpEnrollState = { enrollUrl: null, selectedPackageId: null };

    window.openMemberEnroll = function () {
        const d = window._mpData;
        if (!d) return;

        // Teleport to body so it escapes any CSS stacking context
        const modal = document.getElementById('mpEnrollModal');
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        _mpEnrollReset();
        document.getElementById('mpEnrollModal').classList.remove('hidden');

        if (d.context === 'club') {
            _mpEnrollFetchPackages(d.enroll_packages_url, d.enroll_url, null);
        } else {
            // platform: fetch clubs first
            fetch(d.enroll_data_url, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (!data.clubs || data.clubs.length === 0) {
                    _mpEnrollShowEmpty();
                    return;
                }
                if (data.single_club) {
                    const club = data.clubs[0];
                    _mpEnrollFetchPackages(club.packages_url, club.enroll_url, club.name);
                } else {
                    _mpEnrollShowClubPicker(data.clubs);
                }
            })
            .catch(() => {
                _mpEnrollShowEmpty();
                if (window.showToast) window.showToast('error', '{{ __("admin.partials_member_popup_failed_load_enrollment") }}');
            });
        }
    };

    function _mpEnrollReset() {
        window._mpEnrollState = { enrollUrl: null, selectedPackageId: null };
        document.getElementById('mpEnrollLoading').classList.remove('hidden');
        document.getElementById('mpEnrollClubStep').classList.add('hidden');
        document.getElementById('mpEnrollPackageStep').classList.add('hidden');
        document.getElementById('mpEnrollEmpty').classList.add('hidden');
        document.getElementById('mpEnrollFooter').classList.add('hidden');
        document.getElementById('mpEnrollTitle').textContent = '{{ __("admin.partials_member_popup_enroll_member") }}';
        const btn = document.getElementById('mpEnrollConfirmBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-check-circle"></i> {{ __("admin.partials_member_popup_confirm_enrollment") }}';
    }

    function _mpEnrollShowClubPicker(clubs) {
        document.getElementById('mpEnrollLoading').classList.add('hidden');
        document.getElementById('mpEnrollTitle').textContent = '{{ __("admin.partials_member_popup_select_club") }}';
        const list = document.getElementById('mpEnrollClubList');
        list.innerHTML = '';
        clubs.forEach(c => {
            const btn = document.createElement('button');
            btn.className = 'flex items-center gap-3 w-full text-start px-4 py-3 rounded-xl border border-gray-200 hover:border-primary hover:bg-accent transition-all text-sm font-medium text-gray-800';
            btn.innerHTML = '<i class="bi bi-building text-primary"></i> <span class="mp-club-name"></span> <i class="bi bi-chevron-right ms-auto text-gray-400 text-xs"></i>';
            btn.querySelector('.mp-club-name').textContent = c.name; // safe: never parsed as HTML
            btn.addEventListener('click', () => _mpEnrollPickClub(c.packages_url, c.enroll_url, c.name));
            list.appendChild(btn);
        });
        document.getElementById('mpEnrollClubStep').classList.remove('hidden');
    }

    window._mpEnrollPickClub = function (packagesUrl, enrollUrl, clubName) {
        document.getElementById('mpEnrollClubStep').classList.add('hidden');
        _mpEnrollFetchPackages(packagesUrl, enrollUrl, clubName);
    };

    function _mpEnrollFetchPackages(packagesUrl, enrollUrl, clubName) {
        document.getElementById('mpEnrollLoading').classList.remove('hidden');
        window._mpEnrollState.enrollUrl = enrollUrl;

        fetch(packagesUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('mpEnrollLoading').classList.add('hidden');
            if (!data.packages || data.packages.length === 0) {
                _mpEnrollShowEmpty();
                return;
            }
            _mpEnrollShowPackages(data.packages, clubName);
        })
        .catch(() => {
            _mpEnrollShowEmpty();
            if (window.showToast) window.showToast('error', '{{ __("admin.partials_member_popup_failed_load_packages") }}');
        });
    }

    function _mpEnrollShowPackages(packages, clubName) {
        document.getElementById('mpEnrollTitle').textContent = '{{ __("admin.partials_member_popup_select_package") }}' + (clubName ? ` — ${clubName}` : '');
        const subtitle = document.getElementById('mpEnrollPackageSubtitle');
        subtitle.textContent = '{{ __("admin.partials_member_popup_select_package_enroll") }}';

        const list = document.getElementById('mpEnrollPackageList');
        list.innerHTML = packages.map(pkg => {
            const duration = pkg.duration_months === 1 ? '1 month' : `${pkg.duration_months} months`;
            const descPart = pkg.description ? ' · ' + mpEsc(pkg.description.substring(0, 50)) : '';
            return `<label class="mp-pkg-card flex items-center gap-3 px-4 py-3 rounded-xl border border-gray-200 cursor-pointer hover:border-primary hover:bg-accent transition-all" data-pkg-id="${pkg.id}">
                <input type="radio" name="mpEnrollPkg" value="${pkg.id}" class="accent-primary" onchange="_mpEnrollSelectPkg(${pkg.id})">
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm text-gray-900 truncate">${mpEsc(pkg.name)}</p>
                    <p class="text-xs text-gray-400 mt-0.5">${mpEsc(duration)}${descPart}</p>
                </div>
                <span class="font-bold text-sm text-primary whitespace-nowrap">${mpEsc(pkg.currency)} ${mpEsc(pkg.price)}</span>
            </label>`;
        }).join('');

        document.getElementById('mpEnrollPackageStep').classList.remove('hidden');
        document.getElementById('mpEnrollFooter').classList.remove('hidden');
    }

    window._mpEnrollSelectPkg = function (pkgId) {
        window._mpEnrollState.selectedPackageId = pkgId;
        document.getElementById('mpEnrollConfirmBtn').disabled = false;
        // Highlight selected card
        document.querySelectorAll('.mp-pkg-card').forEach(el => {
            el.classList.toggle('border-primary', el.dataset.pkgId == pkgId);
            el.classList.toggle('bg-accent', el.dataset.pkgId == pkgId);
        });
    };

    function _mpEnrollShowEmpty() {
        document.getElementById('mpEnrollLoading').classList.add('hidden');
        document.getElementById('mpEnrollEmpty').classList.remove('hidden');
        document.getElementById('mpEnrollFooter').classList.remove('hidden');
    }

    window.closeMpEnroll = function () {
        document.getElementById('mpEnrollModal').classList.add('hidden');
    };

    window.confirmMpEnroll = async function () {
        const { enrollUrl, selectedPackageId } = window._mpEnrollState;
        if (!enrollUrl || !selectedPackageId) return;

        const btn = document.getElementById('mpEnrollConfirmBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="inline-block me-2 animate-spin">&#8635;</span> {{ __("admin.partials_member_popup_enrolling") }}';

        try {
            const formData = new FormData();
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            formData.append('package_id', selectedPackageId);

            const res  = await fetch(enrollUrl, { method: 'POST', body: formData, headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            if (data.success) {
                closeMpEnroll();
                if (window.showToast) window.showToast('success', data.message || '{{ __("admin.partials_member_popup_member_enrolled_success") }}');
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle"></i> {{ __("admin.partials_member_popup_confirm_enrollment") }}';
                if (window.showToast) window.showToast('error', data.message || '{{ __("admin.partials_member_popup_enrollment_failed") }}');
            }
        } catch (e) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle"></i> {{ __("admin.partials_member_popup_confirm_enrollment") }}';
            if (window.showToast) window.showToast('error', '{{ __("admin.partials_member_popup_enrollment_failed_retry") }}');
        }
    };
    // ── End Enroll Modal ──────────────────────────────────────────────

    // ── Remove from Club ─────────────────────────────────────────────
    window.removeMemberFromClub = function () {
        const btn  = document.getElementById('mpRemoveBtn');
        const url  = btn.dataset.removeUrl;
        const name = btn.dataset.memberName;

        // Show name-confirmation modal
        document.getElementById('mpRemoveNameExpected').textContent = name;
        document.getElementById('mpRemoveNameInput').value = '';
        document.getElementById('mpRemoveConfirmBtn').disabled = true;
        document.getElementById('mpRemoveModal').classList.remove('hidden');
    };

    document.getElementById('mpRemoveNameInput')?.addEventListener('input', function () {
        const expected = document.getElementById('mpRemoveNameExpected').textContent.trim();
        document.getElementById('mpRemoveConfirmBtn').disabled =
            this.value.trim().toLowerCase() !== expected.toLowerCase();
    });

    document.getElementById('mpRemoveConfirmBtn')?.addEventListener('click', async function () {
        const btn    = document.getElementById('mpRemoveBtn');
        const url    = btn.dataset.removeUrl;

        this.disabled = true;
        this.innerHTML = '<i class="bi bi-hourglass-split"></i> {{ __("admin.partials_member_popup_removing") }}';

        try {
            const resp = await fetch(url, {
                method:  'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept':       'application/json',
                },
            });
            const data = await resp.json();
            if (data.success) {
                document.getElementById('mpRemoveModal').classList.add('hidden');
                window.showToast('success', data.message);
                closeMemberPopup();
                const card = document.querySelector(`[data-member-id="${_mpCurrentUserId}"]`);
                if (card) card.closest('.member-item')?.remove();
            } else {
                window.showToast('error', data.message || '{{ __("admin.partials_member_popup_could_not_remove") }}');
                this.disabled = false;
                this.innerHTML = '{{ __("admin.partials_member_popup_remove_member") }}';
            }
        } catch {
            window.showToast('error', '{{ __("admin.partials_member_popup_request_failed") }}');
            this.disabled = false;
            this.innerHTML = '{{ __("admin.partials_member_popup_remove_member") }}';
        }
    });

    // ── Verify member email (admin marks account as verified → can log in) ──
    window.verifyMemberEmail = async function () {
        const btn = document.getElementById('mpVerifyBtn');
        const url = btn?.dataset.url;
        if (!url) return;

        btn.disabled = true;
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-hourglass-split w-4"></i> {{ __("admin.partials_member_popup_verifying") }}';

        try {
            const resp = await fetch(url, {
                method:  'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept':       'application/json',
                },
            });
            const data = await resp.json();
            if (data.success) {
                window.showToast('success', data.message || '{{ __("admin.partials_member_popup_member_verified") }}');
                btn.classList.add('hidden');           // no longer needed
                // Patch any "unverified" badge on the member's card in place.
                const card = document.querySelector(`[data-member-id="${_mpCurrentUserId}"]`);
                card?.querySelector('[data-unverified-badge]')?.remove();
            } else {
                window.showToast('error', data.message || '{{ __("admin.partials_member_popup_could_not_verify") }}');
            }
        } catch {
            window.showToast('error', '{{ __("admin.partials_member_popup_request_failed") }}');
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    };

    // ── Delete member account (super-admin, platform page) ───────────
    window.deleteMemberAccount = async function () {
        const btn  = document.getElementById('mpDeleteBtn');
        const url  = btn?.dataset.url;
        const name = btn?.dataset.memberName || 'this member';
        if (!url) return;

        const ok = await window.confirmAction({
            title: '{{ __("admin.partials_member_popup_delete_member_q") }}',
            message: "{!! __('admin.partials_member_popup_delete_confirm_msg') !!}".replace(':name', name),
            type: 'danger',
            confirmText: '{{ __("shared.delete") }}',
        });
        if (!ok) return;

        try {
            const resp = await fetch(url, {
                method:  'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept':       'application/json',
                },
            });
            const data = await resp.json();
            if (data.success) {
                window.showToast('success', data.message || '{{ __("admin.partials_member_popup_member_deleted") }}');
                closeMemberPopup();
                const card = document.querySelector(`[data-member-id="${_mpCurrentUserId}"]`);
                if (card) {
                    (card.closest('.member-item') || card.closest('.member-card-wrapper') || card).remove();
                }
            } else {
                window.showToast('error', data.message || '{{ __("admin.partials_member_popup_could_not_delete") }}');
            }
        } catch {
            window.showToast('error', '{{ __("admin.partials_member_popup_request_failed") }}');
        }
    };

    // ── End Member Popup ─────────────────────────────────────────────
})();
</script>
@endpush
@endonce
