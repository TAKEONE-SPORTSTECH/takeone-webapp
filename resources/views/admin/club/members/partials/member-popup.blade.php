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
                <h4 class="text-sm font-bold text-gray-900">Remove from Club</h4>
                <p class="text-xs text-gray-400 mt-0.5">This action cannot be undone from here</p>
            </div>
        </div>
        <div class="px-5 py-4 space-y-3">
            <p class="text-sm text-gray-600">
                The member's affiliation with this club will be ended. Their profile, health records, and payment history are fully preserved.
            </p>
            <div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs text-amber-700">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Type the member's full name to confirm removal.
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">
                    Type <span id="mpRemoveNameExpected" class="font-bold text-gray-800"></span> to confirm
                </label>
                <input id="mpRemoveNameInput" type="text" autocomplete="off"
                       placeholder="Full name…"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-red-400 focus:border-transparent outline-none">
            </div>
        </div>
        <div class="flex gap-2 px-5 py-4 border-t border-gray-100">
            <button onclick="document.getElementById('mpRemoveModal').classList.add('hidden')"
                    class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                Cancel
            </button>
            <button id="mpRemoveConfirmBtn" disabled
                    class="flex-1 px-4 py-2 text-sm font-semibold text-white bg-red-500 rounded-lg hover:bg-red-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                Remove Member
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
            <div class="sm:hidden sticky top-0 z-20 bg-white/95 backdrop-blur pt-2.5 pb-1 flex justify-center">
                <span class="w-10 h-1.5 rounded-full bg-gray-300"></span>
            </div>

            <div class="p-5 pt-3 sm:p-6 sm:pt-8">

                {{-- Avatar + Info labels --}}
                <div class="flex flex-col sm:flex-row gap-6 mb-6">

                    {{-- Avatar column --}}
                    <div class="flex flex-col items-center text-center sm:min-w-[160px]">
                        <div id="mpAvatar"
                             class="w-20 h-20 rounded-full flex items-center justify-center text-white font-bold text-3xl shadow mb-3 overflow-hidden flex-shrink-0">
                        </div>
                        <h4 id="mpName" class="font-bold text-gray-900 mb-2 text-base leading-snug"></h4>
                        <span id="mpMemberId"
                              class="text-xs text-gray-500 bg-gray-100 border border-gray-200 rounded-full px-3 py-1 font-medium"></span>
                    </div>

                    {{-- Stacked info --}}
                    <div class="flex-1 flex flex-col gap-2">
                        <div class="popup-info-row">
                            <span class="popup-info-key"><i class="bi bi-telephone mr-1"></i>Phone</span>
                            <span id="mpPhone" class="popup-info-val"></span>
                        </div>
                        <div class="popup-info-row">
                            <span class="popup-info-key"><i class="bi bi-envelope mr-1"></i>Email</span>
                            <span id="mpEmail" class="popup-info-val"></span>
                        </div>
                        <div class="popup-info-row">
                            <span class="popup-info-key"><i class="bi bi-person mr-1"></i>Age / Gender</span>
                            <span id="mpAgeGender" class="popup-info-val"></span>
                        </div>
                        <div class="popup-info-row">
                            <span class="popup-info-key"><i class="bi bi-calendar3 mr-1"></i>Member Since</span>
                            <span id="mpSince" class="popup-info-val"></span>
                        </div>
                    </div>
                </div>

                {{-- Payment History --}}
                <div class="mb-5">
                    <h6 class="font-bold text-gray-900 mb-3 flex items-center gap-2 text-sm">
                        <i class="bi bi-receipt text-primary text-base"></i>
                        Payment History
                    </h6>
                    <div class="popup-payment-scroll">
                        <table class="w-full min-w-[520px] text-sm">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Period</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Package</th>
                                    <th class="px-2 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Amount</th>
                                    <th class="px-2 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">End Date</th>
                                    <th class="px-2 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Status</th>
                                    <th class="px-3 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wide whitespace-nowrap">Action</th>
                                </tr>
                            </thead>
                            <tbody id="mpPaymentTbody">
                                <tr>
                                    <td colspan="6" class="px-3 py-8 text-center text-gray-400 text-sm">No payment records</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Actions — compact icon buttons in one row on mobile, full labelled pills on desktop --}}
                <div class="flex items-center justify-center gap-2">
                    <a id="mpProfileLink" href="#" aria-label="Profile" title="Profile"
                       class="shrink-0 w-12 h-12 sm:w-auto sm:h-auto sm:flex-1 flex items-center justify-center gap-2 bg-primary text-white rounded-full sm:py-2.5 sm:px-5 font-semibold text-sm hover:bg-primary/90 transition-colors no-underline">
                        <i class="bi bi-person-circle text-lg sm:text-base"></i><span class="hidden sm:inline">Profile</span>
                    </a>
                    @if(auth()->user()?->isSuperAdmin())
                    <button id="mpImpersonateBtn" type="button" onclick="impersonateMember()" aria-label="Login as" title="Login as"
                            class="shrink-0 w-12 h-12 sm:w-auto sm:h-auto sm:flex-1 flex items-center justify-center gap-2 bg-amber-500 text-white rounded-full sm:py-2.5 sm:px-5 font-semibold text-sm hover:bg-amber-600 transition-colors">
                        <i class="bi bi-incognito text-lg sm:text-base"></i><span class="hidden sm:inline">Login as</span>
                    </button>
                    @endif
                    <button id="mpEnrollBtn" onclick="openMemberEnroll()" aria-label="Enroll" title="Enroll"
                            class="shrink-0 w-12 h-12 sm:w-auto sm:h-auto sm:flex-1 flex items-center justify-center gap-2 bg-green-500 text-white rounded-full sm:py-2.5 sm:px-5 font-semibold text-sm hover:bg-green-600 transition-colors">
                        <i class="bi bi-plus-circle text-lg sm:text-base"></i><span class="hidden sm:inline">Enroll</span>
                    </button>
                    <button onclick="closeMemberPopup()" aria-label="Close" title="Close"
                            class="shrink-0 w-12 h-12 sm:w-auto sm:h-auto sm:flex-1 flex items-center justify-center gap-2 bg-gray-100 text-gray-700 border border-gray-200 rounded-full sm:py-2.5 sm:px-5 font-semibold text-sm hover:bg-gray-200 transition-colors">
                        <i class="bi bi-x-lg text-lg sm:text-base"></i><span class="hidden sm:inline">Close</span>
                    </button>
                    <button id="mpRemoveBtn" onclick="removeMemberFromClub()" aria-label="Remove" title="Remove"
                            class="shrink-0 w-12 h-12 sm:w-auto sm:h-auto sm:flex-1 flex items-center justify-center gap-2 bg-red-500 text-white rounded-full sm:py-2.5 sm:px-5 font-semibold text-sm hover:bg-red-600 transition-colors">
                        <i class="bi bi-person-dash text-lg sm:text-base"></i><span class="hidden sm:inline">Remove</span>
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Transaction Detail / Approve Payment Modal -->
<div id="mpTxDetailModal" class="fixed inset-0 z-[60] hidden overflow-y-auto">
    <div class="fixed inset-0 bg-black/60" onclick="closeMpTxDetail()"></div>
    <div class="relative flex min-h-full items-center justify-center p-4 z-10">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-lg" onclick="event.stopPropagation()">

            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                <h5 class="font-bold flex items-center gap-2 mb-0">
                    <i class="bi bi-receipt text-primary"></i> Transaction Detail
                </h5>
                <button onclick="closeMpTxDetail()" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5 space-y-5">

                {{-- Transaction info --}}
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-muted-foreground text-xs uppercase font-medium mb-0.5">Package</p>
                        <p class="font-semibold" id="mpTxPackage">—</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase font-medium mb-0.5">Amount</p>
                        <p class="font-bold text-green-600" id="mpTxAmount">—</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase font-medium mb-0.5">Start Date</p>
                        <p class="font-semibold" id="mpTxStart">—</p>
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs uppercase font-medium mb-0.5">End Date</p>
                        <p class="font-semibold" id="mpTxEnd">—</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-muted-foreground text-xs uppercase font-medium mb-0.5">Payment Status</p>
                        <span id="mpTxStatus"></span>
                    </div>
                </div>

                {{-- Member's uploaded proof --}}
                <div id="mpTxProofWrap" class="hidden">
                    <p class="text-sm font-semibold mb-2">Member's Payment Proof</p>
                    <img id="mpTxProofImg" src="" alt="Payment proof"
                         class="w-full rounded-lg border border-gray-200 cursor-pointer"
                         onclick="window.open(this.src,'_blank')">
                    <p class="text-xs text-muted-foreground mt-1">Click image to view full size</p>
                </div>

                {{-- Approve section (unpaid/pending) --}}
                <div id="mpTxApproveSection" class="border-t pt-4 space-y-4">
                    <p class="text-sm font-semibold">Approve Payment</p>
                    <div>
                        <p class="text-xs text-muted-foreground mb-2">Optionally upload your own proof of receipt (admin copy)</p>
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
                            buttonText="Upload Admin Proof (Optional)"
                            buttonClass="w-full px-4 py-2 border border-dashed border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors flex items-center justify-center gap-2 bg-white text-gray-600"
                        />
                    </div>
                    <button type="button" id="mpConfirmApproveBtn"
                            class="w-full btn btn-success flex items-center justify-center gap-2"
                            onclick="confirmMpApprove()">
                        <i class="bi bi-check-circle mr-1"></i> Confirm & Approve Payment
                    </button>
                </div>

                {{-- Already paid --}}
                <div id="mpTxPaidSection" class="hidden border-t pt-4">
                    <div class="flex items-center gap-2 text-green-600">
                        <i class="bi bi-check-circle-fill"></i>
                        <span class="font-semibold text-sm">Payment has been approved</span>
                    </div>
                </div>

                {{-- Cancel --}}
                <button onclick="closeMpTxDetail()"
                        class="w-full px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors text-sm">
                    Cancel
                </button>

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
                    <span id="mpEnrollTitle">Enroll Member</span>
                </h5>
                <button onclick="closeMpEnroll()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="px-6 py-5">

                <div id="mpEnrollLoading" class="flex flex-col items-center justify-center py-10 gap-3">
                    <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-sm text-gray-400">Loading...</p>
                </div>

                <div id="mpEnrollClubStep" class="hidden">
                    <p class="text-sm text-gray-500 mb-4">This member belongs to multiple clubs. Select a club to enroll them in:</p>
                    <div id="mpEnrollClubList" class="flex flex-col gap-2"></div>
                </div>

                <div id="mpEnrollPackageStep" class="hidden">
                    <p class="text-sm text-gray-500 mb-1" id="mpEnrollPackageSubtitle">Select a package to enroll this member in:</p>
                    <div id="mpEnrollPackageList" class="flex flex-col gap-2 max-h-72 overflow-y-auto pr-1 mt-3"></div>
                </div>

                <div id="mpEnrollEmpty" class="hidden flex flex-col items-center justify-center py-10 text-center gap-2">
                    <i class="bi bi-inbox text-3xl text-gray-300"></i>
                    <p class="text-sm text-gray-400">No eligible packages available for this member.</p>
                </div>

                <div id="mpEnrollFooter" class="hidden pt-4 border-t border-gray-100 mt-4 flex flex-col sm:flex-row gap-2">
                    <button id="mpEnrollConfirmBtn" onclick="confirmMpEnroll()" disabled
                            class="flex-1 flex items-center justify-center gap-2 bg-green-500 text-white rounded-lg py-2.5 px-5 font-semibold text-sm hover:bg-green-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="bi bi-check-circle"></i> Confirm Enrollment
                    </button>
                    <button onclick="closeMpEnroll()"
                            class="flex-1 flex items-center justify-center gap-2 bg-gray-100 text-gray-700 border border-gray-200 rounded-lg py-2.5 px-5 font-semibold text-sm hover:bg-gray-200 transition-colors">
                        Cancel
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
            if (window.showToast) window.showToast('error', 'Failed to load member data.');
        });
    };

    window.closeMemberPopup = function () {
        document.getElementById('memberPopupModal').classList.add('hidden');
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
                ? 'linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%)'
                : 'linear-gradient(135deg, #d63384 0%, #a61e4d 100%)';
            avatarEl.textContent = d.initial;
        }

        // Basic info
        document.getElementById('mpName').textContent      = d.name;
        const year = (d.since || '').split('/')[2] ?? new Date().getFullYear();
        document.getElementById('mpMemberId').textContent  = `#MEM-${year}-${String(d.id).padStart(3, '0')}`;
        document.getElementById('mpPhone').textContent     = d.phone;
        document.getElementById('mpEmail').textContent     = d.email;
        document.getElementById('mpAgeGender').textContent = `${d.age} / ${d.gender === 'Male' ? 'Male' : 'Female'}`;
        document.getElementById('mpSince').textContent     = d.since;
        document.getElementById('mpProfileLink').href      = d.profile_url;
        document.getElementById('mpRemoveBtn').dataset.removeUrl  = d.remove_url;
        document.getElementById('mpRemoveBtn').dataset.memberName = d.name;
        window._mpCurrentUserId = d.id;

        // Payment table
        const tbody = document.getElementById('mpPaymentTbody');
        if (!d.subscriptions || !d.subscriptions.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="px-3 py-8 text-center text-gray-400 text-sm">No payment records</td></tr>`;
            return;
        }

        tbody.innerHTML = d.subscriptions.map(sub => {
            window._mpSubStore[sub.id] = sub;

            const isPaid = sub.payment_status === 'paid';

            const statusBadge = isPaid
                ? `<span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-green-50 text-green-700 border border-green-200">PAID</span>`
                : `<span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-50 text-yellow-700 border border-yellow-200">PENDING</span>`;

            let actionHtml = `<span class="text-xs text-gray-300">—</span>`;
            if (!isPaid && sub.approve_url) {
                actionHtml = `<button onclick="openMpTxDetail(${sub.id})"
                                       class="text-xs font-semibold text-primary hover:underline cursor-pointer bg-transparent border-0 p-0">
                                Upload
                              </button>`;
            } else if (sub.has_proof && sub.proof_url) {
                actionHtml = `<a href="${sub.proof_url}" target="_blank"
                                 class="text-xs font-semibold text-gray-400 hover:text-gray-600 no-underline">
                                View Proof
                              </a>`;
            }

            const periodNote = sub.is_active
                ? `<span class="block text-xs text-green-600"><i class="bi bi-play-circle-fill"></i> Active</span>`
                : `<span class="block text-xs text-gray-400"><i class="bi bi-clock-history"></i> Ended</span>`;

            return `<tr class="border-t border-gray-100 hover:bg-gray-50 transition-colors" id="mp-sub-row-${sub.id}">
                <td class="px-3 py-2.5">
                    <span class="text-xs font-medium text-gray-800 block">${mpEsc(sub.start_date)}</span>
                    ${periodNote}
                </td>
                <td class="px-3 py-2.5 text-xs text-gray-600 max-w-[110px]">
                    <span class="block truncate" title="${mpEsc(sub.package)}">${mpEsc(sub.package)}</span>
                </td>
                <td class="px-2 py-2.5 font-bold text-xs text-gray-800 whitespace-nowrap">${mpEsc(sub.currency)} ${mpEsc(sub.amount_due)}</td>
                <td class="px-2 py-2.5 text-xs text-gray-500 whitespace-nowrap">${mpEsc(sub.end_date)}</td>
                <td class="px-2 py-2.5 text-center">${statusBadge}</td>
                <td class="px-3 py-2.5 text-right">${actionHtml}</td>
            </tr>`;
        }).join('');
    }

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
            ? `<span class="badge bg-green-100 text-green-700">Paid</span>`
            : `<span class="badge bg-amber-100 text-amber-700">Pending</span>`;

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
        btn.innerHTML = '<i class="bi bi-check-circle mr-1"></i> Confirm & Approve Payment';

        // Clear previous cropper value
        const hiddenInput = document.getElementById('hiddenInput_mpAdminProofCropper');
        if (hiddenInput) hiddenInput.value = '';

        document.getElementById('mpTxDetailModal').classList.remove('hidden');
    };

    window.closeMpTxDetail = function () {
        document.getElementById('mpTxDetailModal').classList.add('hidden');
    };

    window.confirmMpApprove = async function () {
        const btn = document.getElementById('mpConfirmApproveBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="inline-block mr-2">&#8635;</span> Approving...';

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
                // Update row in popup table
                const row = document.getElementById(`mp-sub-row-${window._mpCurrentSubId}`);
                if (row) {
                    row.querySelector('td:nth-child(5)').innerHTML =
                        `<span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full bg-green-50 text-green-700 border border-green-200">PAID</span>`;
                    row.querySelector('td:nth-child(6)').innerHTML =
                        `<span class="text-xs text-gray-300">—</span>`;
                }
                if (window.showToast) window.showToast('success', 'Payment approved successfully.');
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle mr-1"></i> Confirm & Approve Payment';
                if (window.showToast) window.showToast('error', data.message || 'Error approving payment.');
            }
        } catch (e) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle mr-1"></i> Confirm & Approve Payment';
            if (window.showToast) window.showToast('error', 'Error approving payment.');
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
                if (window.showToast) window.showToast('error', 'Failed to load enrollment data.');
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
        document.getElementById('mpEnrollTitle').textContent = 'Enroll Member';
        const btn = document.getElementById('mpEnrollConfirmBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-check-circle"></i> Confirm Enrollment';
    }

    function _mpEnrollShowClubPicker(clubs) {
        document.getElementById('mpEnrollLoading').classList.add('hidden');
        document.getElementById('mpEnrollTitle').textContent = 'Select Club';
        const list = document.getElementById('mpEnrollClubList');
        list.innerHTML = '';
        clubs.forEach(c => {
            const btn = document.createElement('button');
            btn.className = 'flex items-center gap-3 w-full text-left px-4 py-3 rounded-xl border border-gray-200 hover:border-primary hover:bg-accent transition-all text-sm font-medium text-gray-800';
            btn.innerHTML = '<i class="bi bi-building text-primary"></i> <span class="mp-club-name"></span> <i class="bi bi-chevron-right ml-auto text-gray-400 text-xs"></i>';
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
            if (window.showToast) window.showToast('error', 'Failed to load packages.');
        });
    }

    function _mpEnrollShowPackages(packages, clubName) {
        document.getElementById('mpEnrollTitle').textContent = 'Select Package' + (clubName ? ` — ${clubName}` : '');
        const subtitle = document.getElementById('mpEnrollPackageSubtitle');
        subtitle.textContent = 'Select a package to enroll this member in:';

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
        btn.innerHTML = '<span class="inline-block mr-2 animate-spin">&#8635;</span> Enrolling...';

        try {
            const formData = new FormData();
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            formData.append('package_id', selectedPackageId);

            const res  = await fetch(enrollUrl, { method: 'POST', body: formData, headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            if (data.success) {
                closeMpEnroll();
                if (window.showToast) window.showToast('success', data.message || 'Member enrolled successfully.');
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle"></i> Confirm Enrollment';
                if (window.showToast) window.showToast('error', data.message || 'Enrollment failed.');
            }
        } catch (e) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle"></i> Confirm Enrollment';
            if (window.showToast) window.showToast('error', 'Enrollment failed. Please try again.');
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
        this.innerHTML = '<i class="bi bi-hourglass-split"></i> Removing…';

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
                window.showToast('error', data.message || 'Could not remove member.');
                this.disabled = false;
                this.innerHTML = 'Remove Member';
            }
        } catch {
            window.showToast('error', 'Request failed. Please try again.');
            this.disabled = false;
            this.innerHTML = 'Remove Member';
        }
    });

    // ── End Member Popup ─────────────────────────────────────────────
})();
</script>
@endpush
@endonce
