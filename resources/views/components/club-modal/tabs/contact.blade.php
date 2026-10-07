@props(['club' => null, 'mode' => 'create', 'context' => 'admin'])

@php
    $isEdit = $mode === 'edit' && $club;
    // Whose contact "use the owner's" means: the club's owner when editing, the
    // acting user in the business flow (they become the owner).
    $contactOwner = $isEdit ? $club->owner : ($context === 'business' ? auth()->user() : null);
    $emailOwner = $isEdit ? (($club->settings['contact_source'] ?? null) === 'owner' || (! $club->email && ($club->settings['contact_source'] ?? null) === null)) : true;
    $phoneOwner = $isEdit ? (($club->settings['contact_source'] ?? null) === 'owner' || (! $club->phone && ($club->settings['contact_source'] ?? null) === null)) : true;
@endphp

<div class="px-0">
    <h5 class="font-bold mb-3">{{ __('shared.modal_tabs_contact_title') }}</h5>
    <p class="text-muted-foreground mb-4">{{ __('shared.modal_tabs_contact_subtitle') }}</p>

    {{-- No real contact yet? Borrow a TakeOne member's email and phone: switches
         both to "custom" and fills them. They stay editable and post as typed.
         Super-admin only — the member search behind it is. --}}
    @if (auth()->user()?->isSuperAdmin())
    <div class="mb-4" x-data="{
            open: false, used: '',
            use(p) {
                const tab = $el.parentElement;
                const pick = (id) => { const r = tab.querySelector('#' + id); if (r && !r.checked) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); } };
                if (p.email) {
                    pick('email_option_custom');
                    const e = tab.querySelector('[name=email]'); if (e) { e.value = p.email; e.dispatchEvent(new Event('input', { bubbles: true })); }
                }
                if (p.phone_number) {
                    pick('phone_option_custom');
                    const n = tab.querySelector('[name=phone_number]'); if (n) { n.value = p.phone_number; n.dispatchEvent(new Event('input', { bubbles: true })); }
                    const g = tab.querySelector('[data-phone-group]'); const code = String(p.phone_code || '').trim();
                    if (g && code && window.Alpine) { const d = Alpine.$data(g); const c = (d.countries || []).find(c => c.code === code); if (c) { d.selectedCode = c.code; d.selectedFlag = c.flag; } }
                }
                this.used = p.name; this.open = false;
            },
         }" @member-picked="use($event.detail)">
        <button type="button" @click="open = !open" x-show="!open"
                class="px-3 py-1.5 rounded-full text-xs font-medium border border-primary text-primary hover:bg-primary hover:text-white transition-colors inline-flex items-center gap-1.5">
            <i class="bi bi-person-lines-fill"></i>{{ __('shared.contact_use_member') }}
        </button>
        <div x-show="open" x-cloak class="rounded-xl border border-gray-100 bg-muted/30 p-3">
            <div class="flex items-center justify-between gap-2 mb-2">
                <p class="text-xs text-muted-foreground mb-0">{{ __('shared.contact_use_member_help') }}</p>
                <button type="button" @click="open = false" class="w-7 h-7 rounded-full grid place-items-center text-muted-foreground hover:bg-muted flex-shrink-0" aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg text-xs"></i></button>
            </div>
            <x-fields.member-search :search-url="route('admin.api.users.lookup')" input-class="form-control" :placeholder="__('shared.member_search_placeholder')" />
        </div>
        <p x-show="used" x-cloak class="text-xs text-green-600 mt-2 mb-0"><i class="bi bi-check-circle-fill me-1"></i><span x-text="@js(__('shared.contact_used_member')).replace(':name', used)"></span></p>
    </div>
    @endif

    {{-- settings[contact_source]: 'owner' when both choices below use the owner's
         contact, otherwise 'club'. The club's own email/phone post either way,
         so switching back restores them. --}}
    <input type="hidden" name="settings[contact_source]" id="clubContactSource" value="{{ $emailOwner && $phoneOwner ? 'owner' : 'club' }}">

    <!-- Club Email -->
    <div class="mb-4">
        <label class="form-label">{{ __('shared.modal_tabs_contact_club_email') }}</label>

        <!-- Toggle: Use Owner Email or Custom -->
        <div class="form-check mb-3">
            <input class="form-check-input"
                   type="radio"
                   name="email_option"
                   id="email_option_owner"
                   value="owner"
                   {{ $emailOwner ? 'checked' : '' }}>
            <label class="form-check-label" for="email_option_owner">
                {{ __('shared.modal_tabs_contact_use_owner_email') }}
            </label>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input"
                   type="radio"
                   name="email_option"
                   id="email_option_custom"
                   value="custom"
                   {{ $emailOwner ? '' : 'checked' }}>
            <label class="form-check-label" for="email_option_custom">
                {{ __('shared.modal_tabs_contact_use_custom_email') }}
            </label>
        </div>

        <!-- Owner Email Display (Read-only) -->
        <div id="ownerEmailDisplay" class="border border-border rounded-lg p-3 mb-3 bg-muted/20" style="display: {{ $emailOwner ? 'block' : 'none' }};">
            <div class="flex items-center gap-2">
                <i class="bi bi-envelope text-muted-foreground"></i>
                <span id="ownerEmailText" class="text-muted-foreground">
                    @if($contactOwner && $contactOwner->email)
                        {{ $contactOwner->email }}
                    @else
                        {{ __('shared.modal_tabs_contact_select_owner_first') }}
                    @endif
                </span>
            </div>
        </div>

        <!-- Custom Email Input -->
        <div id="customEmailInput" style="display: {{ $emailOwner ? 'none' : 'block' }};">
            <input type="email"
                   class="form-control"
                   id="email"
                   name="email"
                   value="{{ $club->email ?? old('email') }}"
                   placeholder="{{ __('shared.modal_tabs_contact_email_placeholder') }}">
            <small class="text-muted-foreground">{{ __('shared.modal_tabs_contact_email_hint') }}</small>
        </div>
    </div>

    <!-- Club Phone -->
    <div class="mb-4">
        <label class="form-label">{{ __('shared.modal_tabs_contact_club_phone') }}</label>

        <!-- Toggle: Use Owner Phone or Custom -->
        <div class="form-check mb-3">
            <input class="form-check-input"
                   type="radio"
                   name="phone_option"
                   id="phone_option_owner"
                   value="owner"
                   {{ $phoneOwner ? 'checked' : '' }}>
            <label class="form-check-label" for="phone_option_owner">
                {{ __('shared.modal_tabs_contact_use_owner_phone') }}
            </label>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input"
                   type="radio"
                   name="phone_option"
                   id="phone_option_custom"
                   value="custom"
                   {{ $phoneOwner ? '' : 'checked' }}>
            <label class="form-check-label" for="phone_option_custom">
                {{ __('shared.modal_tabs_contact_use_custom_phone') }}
            </label>
        </div>

        <!-- Owner Phone Display (Read-only) -->
        <div id="ownerPhoneDisplay" class="border border-border rounded-lg p-3 mb-3 bg-muted/20" style="display: {{ $phoneOwner ? 'block' : 'none' }};">
            <div class="flex items-center gap-2">
                <i class="bi bi-phone text-muted-foreground"></i>
                <span id="ownerPhoneText" class="text-muted-foreground">
                    @if($contactOwner && $contactOwner->mobile)
                        {{ $contactOwner->mobile_formatted }}
                    @else
                        {{ __('shared.modal_tabs_contact_select_owner_first') }}
                    @endif
                </span>
            </div>
        </div>

        <!-- Custom Phone Input -->
        <div id="customPhoneInput" style="display: {{ $phoneOwner ? 'none' : 'block' }};">
            <x-fields.phone-code
                name="phone_code"
                id="phone_code"
                :value="$isEdit && $club->phone ? ($club->phone['code'] ?? '+973') : old('phone_code', '+973')"
                :required="false"
                :error="null">
                <input type="text"
                       class="form-control"
                       name="phone_number"
                       id="phone_number"
                       value="{{ $isEdit && $club->phone ? ($club->phone['number'] ?? '') : old('phone_number') }}"
                       placeholder="12345678">
            </x-fields.phone-code>
            <small class="text-muted-foreground">{{ __('shared.modal_tabs_contact_phone_hint') }}</small>
        </div>
    </div>

    <!-- Additional Contact Info (Optional) -->
    <div class="alert alert-info" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        <strong>{{ __('shared.modal_tabs_contact_tip_label') }}</strong> {!! __('shared.modal_tabs_contact_tip_text') !!}
    </div>
</div>

@push('scripts')
<script>
    // Which contact the club shows: 'owner' only when BOTH choices use the
    // owner's; the typed email/phone are never erased by a toggle.
    function syncClubContactSource() {
        const src = document.getElementById('clubContactSource');
        if (!src) return;
        const emailOwner = document.getElementById('email_option_owner')?.checked;
        const phoneOwner = document.getElementById('phone_option_owner')?.checked;
        src.value = (emailOwner && phoneOwner) ? 'owner' : 'club';
    }

    function showClubContactChoice(kind) {
        const owner = document.getElementById(kind + '_option_owner')?.checked;
        const display = document.getElementById(kind === 'email' ? 'ownerEmailDisplay' : 'ownerPhoneDisplay');
        const custom = document.getElementById(kind === 'email' ? 'customEmailInput' : 'customPhoneInput');
        if (display) display.style.display = owner ? 'block' : 'none';
        if (custom) custom.style.display = owner ? 'none' : 'block';
        syncClubContactSource();
    }

    document.addEventListener('DOMContentLoaded', function() {
        ['email', 'phone'].forEach(kind => {
            ['owner', 'custom'].forEach(opt => {
                const radio = document.getElementById(kind + '_option_' + opt);
                if (radio && !radio.__clubContactBound) {
                    radio.__clubContactBound = true;
                    radio.addEventListener('change', () => showClubContactChoice(kind));
                }
            });
        });

        // Update owner email/phone display when owner is selected (textContent only).
        if (window.__clubOwnerSelectedHandler) {
            document.removeEventListener('ownerSelected', window.__clubOwnerSelectedHandler);
        }
        window.__clubOwnerSelectedHandler = function(e) {
            const owner = e.detail || {};
            const ownerEmailText = document.getElementById('ownerEmailText');
            if (ownerEmailText && owner.email) ownerEmailText.textContent = owner.email;
            const ownerPhoneText = document.getElementById('ownerPhoneText');
            if (ownerPhoneText && owner.mobile) ownerPhoneText.textContent = owner.mobile;
        };
        document.addEventListener('ownerSelected', window.__clubOwnerSelectedHandler);
    });
</script>
@endpush
