@props(['mode' => 'create', 'club' => null, 'context' => 'admin'])

@php
    $isEdit = $mode === 'edit' && $club;
    $modalId = 'clubModal';
@endphp

{{-- The sheet's height follows the VISIBLE viewport on phones (dvh), so the
     footer is never under the browser's toolbar. Inline because the Tailwind
     bundle is prebuilt and has no max-h-[92dvh]. --}}
@once
<style>
    @supports (height: 100dvh) {
        .club-modal-panel { max-height: 92dvh; }
        @media (min-width: 640px) { .club-modal-panel { max-height: 90dvh; } }
    }
</style>
@endonce

<!-- Club Modal (Alpine.js) -->
<div x-data="clubModalController({{ json_encode(['mode' => $mode, 'clubId' => $club->id ?? null, 'isEdit' => $isEdit, 'context' => $context]) }})"
     x-show="open"
     x-cloak
     @open-club-modal.window="openModal($event.detail)"
     @close-club-modal.window="closeModal()"
     @keydown.escape.window="closeModal()"
     class="fixed inset-0 z-50"
     id="{{ $modalId }}">

    <!-- Backdrop -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50"
         @click="closeModal()">
    </div>

    <!-- Modal Dialog -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
         class="fixed inset-0 flex items-end sm:items-center justify-center sm:p-4">

        <div class="club-modal-panel bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl w-full sm:max-w-5xl max-h-[92vh] sm:max-h-[90vh] flex flex-col" @click.stop>
            <!-- Modal Header band (Design Rule #8) -->
            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl sm:rounded-t-2xl text-white relative overflow-hidden"
                 style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi bi-buildings text-xl"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h4 class="text-lg font-black leading-tight" x-text="mode === 'edit' ? @js(__('shared.club_modal_title_edit')) : @js(__('shared.club_modal_title_create'))"></h4>
                        <p class="text-[12px] text-white/85 mt-0.5">{{ __('shared.components_club_modal_subtitle') }}</p>
                    </div>
                    <!-- Ask Coach — opens the AI assistant (continuously animated) -->
                    <button type="button"
                            @click="window.openCopilot && window.openCopilot()"
                            title="{{ __('copilot.fab_title') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-stars relative"></i>
                    </button>
                    <button @click="closeModal()" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Steps + tabs -->
            <div class="px-4 sm:px-6 pt-4 pb-0 flex-shrink-0">
                <!-- Progress Indicator -->
                <div class="flex items-center gap-2 mb-3">
                    <span class="badge bg-primary text-white" x-text="@js(__('shared.club_modal_step')).replace(':current', currentTab + 1).replace(':total', tabs.length)"></span>
                    <div class="flex-1 h-1.5 bg-muted rounded-full overflow-hidden">
                        <div class="h-full bg-primary transition-all duration-300 rounded-full"
                             :style="'width: ' + ((currentTab + 1) / tabs.length * 100) + '%'"></div>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="flex gap-2 border-b-2 border-border overflow-x-auto scrollbar-hide">
                    <template x-for="(tab, index) in tabs" :key="tab.id">
                        <button @click="goToTab(index)"
                                :class="currentTab === index
                                    ? 'text-primary border-primary'
                                    : 'text-muted-foreground border-transparent hover:text-primary hover:border-primary/30'"
                                class="flex items-center gap-2 px-3 sm:px-4 py-3 border-b-3 font-medium text-sm whitespace-nowrap transition-all -mb-0.5">
                            <i :class="tab.icon"></i>
                            <span x-text="tab.name"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="px-4 sm:px-6 py-4 sm:py-6 overflow-y-auto overscroll-contain flex-1">
                <form id="clubForm" x-ref="form" data-mode="{{ $mode }}" data-club-id="{{ $club->id ?? '' }}">
                    @csrf
                    {{-- 1 = the social-link and bank-account rows below are the club's own
                         (rendered for this club, or rebuilt by populateForm). Only then does
                         an update replace them; otherwise the server leaves them alone. --}}
                    <input type="hidden" name="relations_loaded" id="relationsLoaded" value="{{ $isEdit ? 1 : 0 }}">
                    @if($isEdit)
                        @method('PUT')
                    @endif

                    <!-- Tab 1: Basic Information -->
                    <div x-show="currentTab === 0" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <x-club-modal.tabs.basic-info :club="$club" :mode="$mode" :context="$context" />
                    </div>

                    <!-- Tab 2: Identity & Branding -->
                    <div x-show="currentTab === 1" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <x-club-modal.tabs.identity-branding :club="$club" :mode="$mode" />
                    </div>

                    <!-- Tab 3: Location -->
                    <div x-show="currentTab === 2" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <x-club-modal.tabs.location :club="$club" :mode="$mode" />
                    </div>

                    <!-- Tab 4: Contact -->
                    <div x-show="currentTab === 3" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <x-club-modal.tabs.contact :club="$club" :mode="$mode" :context="$context" />
                    </div>

                    <!-- Tab 5: Finance & Settings -->
                    <div x-show="currentTab === 4" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                        <x-club-modal.tabs.finance-settings :club="$club" :mode="$mode" />
                    </div>
                </form>
            </div>

            <!-- Modal Footer -->
            <div class="px-4 sm:px-6 pt-4 flex items-center justify-end gap-2 sm:gap-3 border-t border-border flex-shrink-0 flex-wrap"
                 style="padding-bottom: max(1rem, calc(0.75rem + env(safe-area-inset-bottom)));">
                <button x-show="currentTab > 0"
                        @click="goToTab(currentTab - 1)"
                        class="btn btn-secondary flex-1 sm:flex-initial justify-center">
                    <i class="bi bi-chevron-left me-2 rtl:rotate-180"></i>{{ __('shared.back') }}
                </button>
                <button @click="closeModal()" class="btn btn-secondary flex-1 sm:flex-initial justify-center">{{ __('shared.cancel') }}</button>
                {{-- Editing: Next steps through, Update saves from any step (no need to walk to the last one). --}}
                <button x-show="currentTab < tabs.length - 1"
                        @click="goToTab(currentTab + 1)"
                        :class="mode === 'edit' ? 'btn-secondary' : 'btn-primary'"
                        class="btn flex-1 sm:flex-initial justify-center">
                    {{ __('shared.components_club_modal_next') }}<i class="bi bi-arrow-right ms-2 rtl:rotate-180"></i>
                </button>
                <button x-show="currentTab === tabs.length - 1 || mode === 'edit'"
                        @click="handleSubmit()"
                        :disabled="isSubmitting"
                        class="btn btn-primary flex-1 sm:flex-initial justify-center">
                    <template x-if="isSubmitting">
                        <span class="flex items-center justify-center">
                            <span class="spinner-border me-2"></span>{{ __('shared.components_club_modal_saving') }}
                        </span>
                    </template>
                    <template x-if="!isSubmitting">
                        <span class="flex items-center justify-center">
                            <i class="bi bi-check-circle me-2"></i><span x-text="mode === 'edit' ? @js(__('shared.club_modal_submit_update')) : @js(__('shared.club_modal_submit_create'))"></span>
                        </span>
                    </template>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Styles moved to app.css (Phase 6) --}}

@once
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
    // Internal User Picker Functions
    let allUsersData = [];

    async function showUserPicker() {
        const overlay = document.getElementById('userPickerOverlay');
        if (overlay) {
            overlay.style.display = 'flex';
            await loadUsersInternal();
            document.getElementById('userSearchInputInternal')?.focus();
        }
    }

    function hideUserPicker() {
        const overlay = document.getElementById('userPickerOverlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }

    async function loadUsersInternal(q = '') {
        const loadingDiv = document.getElementById('userPickerLoadingInternal');
        const resultsDiv = document.getElementById('userPickerResultsInternal');
        const noResultsDiv = document.getElementById('userPickerNoResultsInternal');

        if (loadingDiv) loadingDiv.style.display = 'block';
        if (resultsDiv) resultsDiv.innerHTML = '';
        if (noResultsDiv) noResultsDiv.style.display = 'none';

        try {
            const response = await fetch('/admin/api/users' + (q ? '?q=' + encodeURIComponent(q) : ''), { headers: { 'Accept': 'application/json' } });
            if (response.ok) {
                allUsersData = await response.json();
                displayUsersInternal(allUsersData);
            }
        } catch (error) {
            console.error('Error loading users:', error);
        } finally {
            if (loadingDiv) loadingDiv.style.display = 'none';
        }
    }

    // Every value here is a member's own text (name, email, phone) — built with
    // textContent / attributes, never HTML strings, so a name can never run as
    // script in the super-admin's browser.
    function ownerPickerEl(tag, cls, text) {
        const el = document.createElement(tag);
        if (cls) el.className = cls;
        if (text !== undefined && text !== null) el.textContent = String(text);
        return el;
    }

    // getUsers() sends a full URL; older callers passed a storage path.
    function ownerPickerPhoto(picture) {
        const p = String(picture || '');
        return /^(https?:)?\//.test(p) ? p : '/file/' + p;
    }

    function ownerPickerRow(name, email, mobile, picture) {
        const row = ownerPickerEl('div', 'flex items-center gap-3');
        if (picture) {
            const img = ownerPickerEl('img', 'rounded-full w-12 h-12 object-cover');
            img.src = ownerPickerPhoto(picture);
            img.alt = String(name || '');
            row.appendChild(img);
        } else {
            row.appendChild(ownerPickerEl('div', 'rounded-full bg-primary text-white flex items-center justify-center w-12 h-12 text-xl font-semibold', String(name || '?').charAt(0)));
        }
        const info = ownerPickerEl('div', 'flex-1');
        info.appendChild(ownerPickerEl('div', 'font-semibold', name || ''));
        const meta = ownerPickerEl('div', 'text-sm text-muted-foreground');
        meta.appendChild(ownerPickerEl('i', 'bi bi-envelope me-1'));
        meta.appendChild(document.createTextNode(String(email || '')));
        if (mobile) {
            const ph = ownerPickerEl('span', 'ms-2');
            ph.appendChild(ownerPickerEl('i', 'bi bi-phone me-1'));
            ph.appendChild(document.createTextNode(String(mobile)));
            meta.appendChild(ph);
        }
        info.appendChild(meta);
        row.appendChild(info);
        return row;
    }

    function displayUsersInternal(users) {
        const resultsDiv = document.getElementById('userPickerResultsInternal');
        const noResultsDiv = document.getElementById('userPickerNoResultsInternal');

        if (!resultsDiv) return;

        resultsDiv.replaceChildren();

        if (users.length === 0) {
            if (noResultsDiv) noResultsDiv.style.display = 'block';
            return;
        }

        if (noResultsDiv) noResultsDiv.style.display = 'none';

        users.forEach(user => {
            const mobile = user.mobile_formatted || user.mobile || '';
            const item = ownerPickerEl('div', 'user-picker-item');
            item.appendChild(ownerPickerRow(user.full_name, user.email, mobile, user.profile_picture));
            item.addEventListener('click', () => selectUserInternal(user.id, user.full_name || '', user.email || '', mobile, user.profile_picture || ''));
            resultsDiv.appendChild(item);
        });
    }

    function selectUserInternal(id, name, email, mobile, picture) {
        const ownerInput = document.getElementById('owner_user_id');
        if (ownerInput) {
            ownerInput.value = id;
            ownerInput.dispatchEvent(new Event('change'));
        }

        const ownerDisplay = document.getElementById('ownerDisplay');
        if (ownerDisplay) {
            ownerDisplay.replaceChildren(ownerPickerRow(name, email, mobile, picture));
        }

        hideUserPicker();
    }

    // Search functionality for internal user picker
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('userSearchInputInternal');
        if (searchInput) {
            let searchTimeout;
            if (searchInput.__clubUserSearch) return;
            searchInput.__clubUserSearch = true;
            // The list is bounded on the server, so a search asks the server.
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => loadUsersInternal(this.value.trim()), 300);
            });
        }
    });

    // Club Modal Alpine.js Controller
    function clubModalController(config) {
        return {
            open: false,
            currentTab: 0,
            isSubmitting: false,
            draftLoaded: false,
            toastShown: {},
            mode: config.mode,
            clubId: config.clubId,
            isEdit: config.isEdit,
            context: config.context || 'admin',

            tabs: [
                { id: 'basic', name: @js(__('shared.club_modal_tab_basic')), icon: 'bi bi-info-circle' },
                { id: 'identity', name: @js(__('shared.club_modal_tab_identity')), icon: 'bi bi-palette' },
                { id: 'location', name: @js(__('shared.club_modal_tab_location')), icon: 'bi bi-geo-alt' },
                { id: 'contact', name: @js(__('shared.club_modal_tab_contact')), icon: 'bi bi-telephone' },
                { id: 'finance', name: @js(__('shared.club_modal_tab_finance')), icon: 'bi bi-bank' }
            ],

            async openModal(detail = {}) {
                // Set mode and clubId from event detail
                if (detail.mode) {
                    this.mode = detail.mode;
                    this.isEdit = detail.mode === 'edit';
                }
                if (detail.clubId) {
                    this.clubId = detail.clubId;
                }

                // Update form data attributes
                if (this.$refs.form) {
                    this.$refs.form.dataset.mode = this.mode;
                    this.$refs.form.dataset.clubId = this.clubId || '';
                }

                this.clearFieldErrors();
                this.open = true;
                document.body.classList.add('overflow-hidden');

                // Load club data if in edit mode
                if (this.mode === 'edit' && this.clubId) {
                    await this.loadClubData(this.clubId);
                } else if (this.mode === 'create') {
                    // Reset form for create mode
                    this.resetForm();
                    if (!this.draftLoaded) {
                        this.loadDraft();
                        this.draftLoaded = true;
                    }
                }
            },

            async loadClubData(clubId) {
                try {
                    const response = await fetch(`/admin/api/clubs/${encodeURIComponent(clubId)}`, { headers: { 'Accept': 'application/json' } });
                    if (response.ok) {
                        const club = await response.json();
                        this.populateForm(club);
                    } else {
                        this.showToast('{{ __("shared.components_club_modal_failed_load_club") }}', 'error');
                    }
                } catch (error) {
                    console.error('Error loading club data:', error);
                    this.showToast('{{ __("shared.components_club_modal_error_load_club") }}', 'error');
                }
            },

            populateForm(club) {
                // Basic fields
                this.setFieldValue('club_name', club.club_name);
                this.setFieldValue('slug', club.slug);
                this.setFieldValue('slogan', club.slogan);
                this.setFieldValue('description', club.description ?? '');
                document.getElementById('description')?.dispatchEvent(new Event('input'));
                this.setAlpineField('clubEstablishedDateField', 'sel', club.established_date ? String(club.established_date).slice(0, 10) : '');
                this.setFieldValue('commercial_reg_number', club.commercial_reg_number ?? '');
                this.setFieldValue('vat_reg_number', club.vat_reg_number ?? '');
                this.setFieldValue('vat_percentage', club.vat_percentage ?? '');

                // Registration requirements / terms, English and Arabic.
                this.setRichText('registration_requirements', club.registration_requirements);
                this.setRichText('registration_terms', club.registration_terms);
                this.setRichText('translations[registration_requirements][ar]', club.translations?.registration_requirements?.ar);
                this.setRichText('translations[registration_terms][ar]', club.translations?.registration_terms?.ar);

                // Owner
                this.setFieldValue('owner_user_id', club.owner_user_id);
                if (club.owner) {
                    this.updateOwnerDisplay(club.owner);
                    document.dispatchEvent(new CustomEvent('ownerSelected', { detail: { email: club.owner.email, mobile: club.owner.mobile } }));
                }

                // Location: the pickers are Alpine controls — set their state so the
                // visible label follows, not only the hidden input.
                this.setPicker('country', club.country || '', 'iso2', i => i.name);
                this.setPicker('timezone', club.timezone || '', 'timezone', i => i.timezone + ' (' + i.name + ')');
                this.setPicker('currency', club.currency || '', 'currency', i => i.name + ' – ' + i.currency);
                this.setFieldValue('clubLocAddress', club.address ?? '');
                this.setFieldValue('clubLocLat', club.gps_lat ?? '');
                this.setFieldValue('clubLocLng', club.gps_long ?? '');
                this.setFieldValue('clubLocMapsUrl', club.maps_url ?? '');

                // Sync map marker to saved coordinates
                if (club.gps_lat && club.gps_long) {
                    const lat = parseFloat(club.gps_lat);
                    const lng = parseFloat(club.gps_long);
                    if (!isNaN(lat) && !isNaN(lng) && window.LocationMap) {
                        window.LocationMap.setPosition('clubLoc', lat, lng);
                    }
                }

                // Contact: which contact the club shows, and its own email/phone.
                const src = club.contact_source;
                const phone = club.phone && typeof club.phone === 'object' ? club.phone : null;
                const emailOwner = src === 'owner' || (!src && !club.email);
                const phoneOwner = src === 'owner' || (!src && !(phone && phone.number));
                this.setFieldValue('email', club.email ?? '');
                this.setFieldValue('phone_number', phone?.number ?? '');
                this.setPhoneCode(phone?.code || '+973');
                this.chooseContact('email', emailOwner);
                this.chooseContact('phone', phoneOwner);

                // Social links + bank accounts: rebuild the rows from the club, so a
                // save writes back what is on file (the server only replaces these
                // when relations_loaded says the rows were loaded).
                this.fillRelations(club);

                // Finance fields
                this.setFieldValue('enrollment_fee', club.enrollment_fee ?? '');
                this.setFieldValue('registration_fee', club.registration_fee ?? '');
                this.setAlpineField('clubStatusField', 'sel', club.status || 'active');
                const publicProfileCheckbox = document.getElementById('public_profile_enabled');
                if (publicProfileCheckbox) {
                    publicProfileCheckbox.checked = !!club.public_profile_enabled;
                }
                setTimeout(() => window.updateClubFinanceSummary && window.updateClubFinanceSummary(), 0);

                // Slug: the URL preview and the QR follow it.
                document.getElementById('slug')?.dispatchEvent(new Event('input'));
                const urlPreview = document.getElementById('clubUrlPreview');
                if (urlPreview && club.url) urlPreview.textContent = club.url;

                // Pictures
                this.setPreview('logoPreviewContainer', 'logoPreview', 'logoInput', club.logo, 'width: 150px; height: 150px; border-radius: 8px; border: 2px solid #dee2e6;');
                this.setPreview('coverPreviewContainer', 'coverPreview', 'coverInput', club.cover_image, 'width: 250px; height: 141px; border-radius: 8px; border: 2px solid #dee2e6; object-fit: cover;');
                this.setPreview('splashPreviewContainer', 'splashPreview', 'splashInput', club.registration_splash_image, 'width: 110px; height: 195px; border-radius: 12px; border: 2px solid #dee2e6; object-fit: cover; margin: 0 auto;');
            },

            // A stored picture path (or URL) shown as the preview, built as DOM —
            // and the hidden input re-posts it (the server ignores non-data-URIs).
            setPreview(containerId, previewId, inputId, path, style) {
                const input = document.getElementById(inputId);
                if (input) input.value = path || '';
                const container = document.getElementById(containerId);
                if (!container || !path) return;
                const img = document.createElement('img');
                img.id = previewId;
                img.className = 'cropper-preview-image';
                img.setAttribute('style', style);
                img.src = /^(https?:)?\//.test(path) ? path : '/file/' + String(path).split('/').map(encodeURIComponent).join('/');
                container.replaceChildren(img);
            },

            // The x-data scope a field component owns, found from its wrapper.
            setAlpineField(wrapperId, key, value) {
                const root = document.getElementById(wrapperId)?.querySelector('[x-data]');
                if (!root || !window.Alpine) return;
                try { Alpine.$data(root)[key] = value; } catch (e) { /* not initialised yet */ }
            },

            // Country / timezone / currency pickers: state + visible label.
            // Their item list is fetched on init, so wait for it briefly.
            setPicker(inputId, value, key, labelOf, tries = 0) {
                const input = document.getElementById(inputId);
                if (!input || !window.Alpine) return;
                let d;
                try { d = Alpine.$data(input); } catch (e) { return; }
                if (!d) return;
                const items = Array.isArray(d.items) ? d.items : [];
                if (!items.length && value && tries < 20) {
                    setTimeout(() => this.setPicker(inputId, value, key, labelOf, tries + 1), 150);
                    return;
                }
                const match = items.find(i => i[key] === value);
                d.selectedValue = value;
                d.selectedLabel = match ? labelOf(match) : value;
                d.selectedFlag = match ? match.flag : '';
                input.value = value;
            },

            setPhoneCode(code) {
                const group = document.querySelector('#customPhoneInput [data-phone-group]');
                if (!group || !window.Alpine) return;
                try {
                    const d = Alpine.$data(group);
                    const c = (d.countries || []).find(x => x.code === code);
                    if (c) { d.selectedCode = c.code; d.selectedFlag = c.flag; }
                } catch (e) { /* not initialised yet */ }
            },

            chooseContact(kind, useOwner) {
                const radio = document.getElementById(kind + '_option_' + (useOwner ? 'owner' : 'custom'));
                if (radio) radio.checked = true;
                if (typeof window.showClubContactChoice === 'function') window.showClubContactChoice(kind);
            },

            // A rich-text editor (the fields.rich-text component) by its posted name: the
            // visible editor and the hidden textarea that posts. The HTML comes
            // from the server, which sanitised it when it was stored.
            setRichText(name, html) {
                const area = this.$refs.form?.querySelector(`textarea[name="${CSS.escape(name)}"]`);
                if (!area) return;
                area.value = html || '';
                const editor = area.closest('[x-data]')?.querySelector('[contenteditable]');
                if (editor) editor.innerHTML = html || '';
            },

            fillRelations(club) {
                const social = document.getElementById('socialLinksContainer');
                const banks = document.getElementById('bankAccountsContainer');
                if (!social || !banks || typeof window.addSocialLink !== 'function' || typeof window.addBankAccount !== 'function') return;
                social.querySelectorAll('.social-link-row').forEach(r => r.remove());
                banks.querySelectorAll('.bank-account-block').forEach(r => r.remove());
                const put = (row, key, v) => { const el = row && row.querySelector('[name$="[' + key + ']"]'); if (el) el.value = v ?? ''; };
                (club.social_links || []).forEach(l => window.addSocialLink(l.platform || '', l.url || ''));
                (club.bank_accounts || []).forEach(a => {
                    window.addBankAccount();
                    const row = [...banks.querySelectorAll('.bank-account-block')].pop();
                    ['bank_name', 'account_name', 'account_number', 'iban', 'swift_code', 'benefitpay_account'].forEach(k => put(row, k, a[k]));
                });
                const flag = document.getElementById('relationsLoaded');
                if (flag) flag.value = '1';
                setTimeout(() => window.updateClubFinanceSummary && window.updateClubFinanceSummary(), 0);
            },

            setFieldValue(fieldId, value) {
                const field = document.getElementById(fieldId);
                if (field && value !== null && value !== undefined) {
                    field.value = value;
                }
            },

            // The owner card — built from DOM nodes (ownerPickerRow), never an
            // HTML string, so a name or email can never run as script.
            updateOwnerDisplay(owner) {
                const ownerDisplay = document.getElementById('ownerDisplay');
                if (ownerDisplay && owner) {
                    ownerDisplay.replaceChildren(ownerPickerRow(owner.full_name, owner.email, owner.mobile, owner.profile_picture));
                }
            },

            // Everything back to a blank create form: inputs, the Alpine pickers,
            // the rich-text editors, the rows and the previews.
            resetForm() {
                const form = this.$refs.form;
                if (form) form.reset();
                this.setPicker('country', 'BH', 'iso2', i => i.name);
                this.setPicker('timezone', 'Asia/Bahrain', 'timezone', i => i.timezone + ' (' + i.name + ')');
                this.setPicker('currency', 'BHD', 'currency', i => i.name + ' – ' + i.currency);
                this.setAlpineField('clubEstablishedDateField', 'sel', '');
                this.setAlpineField('clubStatusField', 'sel', 'active');
                ['registration_requirements', 'registration_terms', 'translations[registration_requirements][ar]', 'translations[registration_terms][ar]']
                    .forEach(n => this.setRichText(n, ''));
                ['clubLocAddress', 'clubLocLat', 'clubLocLng', 'clubLocMapsUrl', 'logoInput', 'coverInput', 'splashInput', 'owner_user_id']
                    .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
                if (this.context === 'business') this.setFieldValue('owner_user_id', @js((string) auth()->id()));
                document.querySelectorAll('#socialLinksContainer .social-link-row, #bankAccountsContainer .bank-account-block').forEach(r => r.remove());
                const flag = document.getElementById('relationsLoaded');
                if (flag) flag.value = '0';
                if (typeof window.showClubContactChoice === 'function') { window.showClubContactChoice('email'); window.showClubContactChoice('phone'); }
                document.getElementById('slug')?.dispatchEvent(new Event('input'));
                setTimeout(() => window.updateClubFinanceSummary && window.updateClubFinanceSummary(), 0);
                this.resetPreviews();
            },

            resetPreviews() {
                // Reset logo preview
                const logoContainer = document.getElementById('logoPreviewContainer');
                if (logoContainer) {
                    logoContainer.innerHTML = `<div id="logoPreview" class="cropper-preview-placeholder" style="width: 150px; height: 150px; border-radius: 8px; border: 2px dashed #dee2e6; display: flex; align-items: center; justify-content: center; background-color: #f0f0f0; color: #6c757d;"><i class="bi bi-image text-2xl"></i></div>`;
                }

                // Reset cover preview
                const coverContainer = document.getElementById('coverPreviewContainer');
                if (coverContainer) {
                    coverContainer.innerHTML = `<div id="coverPreview" class="cropper-preview-placeholder" style="width: 250px; height: 141px; border-radius: 8px; border: 2px dashed #dee2e6; display: flex; align-items: center; justify-content: center; background-color: #f0f0f0; color: #6c757d;"><i class="bi bi-image text-2xl"></i></div>`;
                }

                // Reset splash preview
                const splashContainer = document.getElementById('splashPreviewContainer');
                if (splashContainer) {
                    splashContainer.innerHTML = `<div id="splashPreview" class="cropper-preview-placeholder" style="width: 110px; height: 195px; border-radius: 12px; border: 2px dashed #dee2e6; display: flex; align-items: center; justify-content: center; background-color: #f0f0f0; color: #6c757d; margin: 0 auto;"><i class="bi bi-phone text-2xl"></i></div>`;
                }

                // Reset owner display (not in the business flow: the owner is the acting user)
                const ownerDisplay = document.getElementById('ownerDisplay');
                if (ownerDisplay) {
                    ownerDisplay.innerHTML = `<div class="text-center text-muted-foreground py-3" id="noOwnerSelected"><i class="bi bi-person-plus text-3xl mb-2 block"></i><p class="mb-0">{{ __("shared.components_club_modal_no_owner_selected") }}</p></div>`;
                }
            },

            closeModal() {
                if (!this.open) return;
                this.open = false;
                document.body.classList.remove('overflow-hidden');
                this.currentTab = 0;
                this.draftLoaded = false;
                this.toastShown = {};
                // Reset mode to create for next open
                this.mode = 'create';
                this.isEdit = false;
                this.clubId = null;
                if (this.$refs.form) {
                    this.$refs.form.dataset.mode = 'create';
                    this.$refs.form.dataset.clubId = '';
                }
                this.clearFieldErrors();
                this.resetForm();
            },

            goToTab(index) {
                if (index < 0 || index >= this.tabs.length) return;

                // Validate current tab before moving forward
                if (index > this.currentTab && !this.validateCurrentTab()) {
                    return;
                }

                this.currentTab = index;
                this.saveDraft();
            },

            validateCurrentTab() {
                const form = this.$refs.form;
                if (!form) return true;

                let isValid = true;
                let errorCount = 0;

                // --- Tab 0: owner hidden field (business context auto-fills it — nothing to validate) ---
                if (this.currentTab === 0 && this.context !== 'business') {
                    const ownerInput = document.getElementById('owner_user_id');
                    const ownerError = document.getElementById('ownerError');
                    if (ownerInput && !ownerInput.value) {
                        ownerInput.classList.add('is-invalid');
                        if (ownerError) ownerError.style.setProperty('display', 'block', 'important');
                        isValid = false;
                        errorCount++;
                    } else if (ownerInput) {
                        ownerInput.classList.remove('is-invalid');
                        if (ownerError) ownerError.style.setProperty('display', 'none', 'important');
                    }
                }

                // --- Tab 1: slug format ---
                if (this.currentTab === 1) {
                    const slugInput = document.getElementById('slug');
                    if (slugInput) {
                        const slugError = slugInput.closest('.mb-4')?.querySelector('.invalid-feedback');
                        if (!slugInput.value.trim()) {
                            slugInput.classList.add('is-invalid');
                            if (slugError) { slugError.textContent = '{{ __("shared.components_club_modal_slug_required") }}'; slugError.style.display = 'block'; }
                            isValid = false;
                            errorCount++;
                        } else if (!/^[a-z0-9-]+$/.test(slugInput.value)) {
                            slugInput.classList.add('is-invalid');
                            if (slugError) { slugError.textContent = '{{ __("shared.components_club_modal_slug_format") }}'; slugError.style.display = 'block'; }
                            isValid = false;
                            errorCount++;
                        } else {
                            slugInput.classList.remove('is-invalid');
                            if (slugError) slugError.style.display = 'none';
                        }
                    }
                }

                // --- Standard required fields (skip hidden, file) ---
                const allInputs = form.querySelectorAll('input[required], select[required], textarea[required]');
                allInputs.forEach(input => {
                    if (input.type === 'file' || input.type === 'hidden') return;

                    let parent = input.closest('[x-show]');
                    if (!parent) return;

                    const showAttr = parent.getAttribute('x-show');
                    if (!showAttr || !showAttr.includes(`currentTab === ${this.currentTab}`)) return;

                    // Skip slug — handled above
                    if (input.id === 'slug') return;

                    const isEmpty = !input.value.trim();
                    const isInvalidEmail = input.type === 'email' && input.value && !this.isValidEmail(input.value);

                    if (isEmpty || isInvalidEmail) {
                        input.classList.add('is-invalid');
                        isValid = false;
                        errorCount++;

                        // Find or create error div (handle input-group wrapper)
                        let errorDiv = input.nextElementSibling;
                        if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
                            const wrapper = input.closest('.input-group');
                            errorDiv = wrapper ? wrapper.nextElementSibling : null;
                        }
                        if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
                            errorDiv = document.createElement('div');
                            errorDiv.className = 'invalid-feedback';
                            const wrapper = input.closest('.input-group') || input;
                            wrapper.parentNode.insertBefore(errorDiv, wrapper.nextSibling);
                        }
                        errorDiv.textContent = isInvalidEmail
                            ? '{{ __("shared.components_club_modal_valid_email") }}'
                            : (input.dataset.errorMessage || '{{ __("shared.components_club_modal_field_required") }}');
                        errorDiv.style.display = 'block';
                    } else {
                        input.classList.remove('is-invalid');
                        let errorDiv = input.nextElementSibling;
                        if (!errorDiv || !errorDiv.classList.contains('invalid-feedback')) {
                            const wrapper = input.closest('.input-group');
                            errorDiv = wrapper ? wrapper.nextElementSibling : null;
                        }
                        if (errorDiv && errorDiv.classList.contains('invalid-feedback')) {
                            errorDiv.style.display = 'none';
                        }
                    }
                });

                if (!isValid) {
                    this.showToast(@js(__('shared.club_modal_required_missing')).replace(':count', errorCount), 'error');
                }

                return isValid;
            },

            // The tab a server error belongs to — every field the form posts.
            fieldTab(field) {
                const base = String(field).split('.')[0];
                const map = {
                    club_name: 0, owner_user_id: 0, slogan: 0, description: 0, established_date: 0,
                    commercial_reg_number: 0, vat_reg_number: 0, vat_percentage: 0,
                    registration_requirements: 0, registration_terms: 0, translations: 0,
                    slug: 1, logo: 1, cover_image: 1, registration_splash_image: 1, social_links: 1,
                    country: 2, timezone: 2, currency: 2, address: 2, gps_lat: 2, gps_long: 2, maps_url: 2,
                    email: 3, phone_code: 3, phone_number: 3, settings: 3,
                    bank_accounts: 4, enrollment_fee: 4, registration_fee: 4, club_status: 4, public_profile_enabled: 4,
                };
                return map[base];
            },

            // The input a server error names: an id where the field has one,
            // otherwise its posted name (social_links.0.url → social_links[0][url]).
            fieldInput(field) {
                const ids = { address: 'clubLocAddress', gps_lat: 'clubLocLat', gps_long: 'clubLocLng', maps_url: 'clubLocMapsUrl' };
                const byId = document.getElementById(ids[field] || field);
                if (byId && this.$refs.form?.contains(byId)) return byId;
                const parts = String(field).split('.');
                const name = parts[0] + parts.slice(1).map(p => '[' + p + ']').join('');
                return this.$refs.form?.querySelector(`[name="${CSS.escape(name)}"]`) || null;
            },

            clearFieldErrors() {
                const form = this.$refs.form;
                if (!form) return;
                form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                form.querySelectorAll('.invalid-feedback[data-server-error]').forEach(el => el.remove());
                form.querySelectorAll('.invalid-feedback').forEach(el => { if (el.id !== 'ownerError') el.style.display = 'none'; });
                const ownerError = document.getElementById('ownerError');
                if (ownerError) ownerError.style.setProperty('display', 'none', 'important');
            },

            showFieldErrors(errors) {
                this.clearFieldErrors();
                let firstErrorTab = null;

                Object.keys(errors).forEach(field => {
                    const message = Array.isArray(errors[field]) ? errors[field][0] : String(errors[field]);
                    const tab = this.fieldTab(field);
                    if (tab !== undefined && (firstErrorTab === null || tab < firstErrorTab)) {
                        firstErrorTab = tab;
                    }

                    if (field === 'owner_user_id') {
                        const ownerError = document.getElementById('ownerError');
                        if (ownerError) { ownerError.textContent = message; ownerError.style.setProperty('display', 'block', 'important'); }
                        return;
                    }

                    const input = this.fieldInput(field);
                    if (!input) return;
                    // A hidden input (a styled picker) shows its message beside its wrapper.
                    const anchor = input.type === 'hidden' ? (input.closest('.relative') || input.parentElement) : (input.closest('.input-group') || input);
                    if (input.type !== 'hidden') input.classList.add('is-invalid');

                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.dataset.serverError = '';
                    errorDiv.textContent = message;
                    errorDiv.style.display = 'block';
                    anchor.parentNode.insertBefore(errorDiv, anchor.nextSibling);
                });

                if (firstErrorTab !== null) {
                    this.currentTab = firstErrorTab;
                }

                const total = Object.keys(errors).length;
                this.showToast(@js(__('shared.club_modal_validation_errors')).replace(':count', total), 'error');
            },

            isValidEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
            },

            async handleSubmit() {
                // Validate all tabs — a failure stays on the step that failed;
                // a pass returns to the step the user saved from.
                const from = this.currentTab;
                for (let i = 0; i < this.tabs.length; i++) {
                    this.currentTab = i;
                    if (!this.validateCurrentTab()) {
                        return;
                    }
                }
                this.currentTab = from;
                this.clearFieldErrors();
                if (typeof window.syncClubContactSource === 'function') window.syncClubContactSource();

                const form = this.$refs.form;
                const formData = new FormData(form);

                // Add _method for Laravel to handle PUT request
                if (this.mode === 'edit') {
                    formData.append('_method', 'PUT');
                }

                this.isSubmitting = true;

                try {
                    const url = this.mode === 'edit'
                        ? `/admin/clubs/${this.clubId}`
                        : (this.context === 'business' ? '/business/clubs' : '/admin/clubs');

                    const response = await fetch(url, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    const data = await response.json();

                    if (response.ok && data.success) {
                        this.showToast(data.message || '{{ __("shared.components_club_modal_saved_success") }}', 'success');
                        this.clearDraft();
                        // closeModal() resets mode to 'create' — read it first, or an
                        // edit would redirect away instead of patching the page.
                        const savedMode = this.mode;
                        this.closeModal();

                        // Notify any listening page so it can update in place — no reload.
                        window.dispatchEvent(new CustomEvent('club-saved', {
                            detail: { mode: savedMode, club: data.club }
                        }));

                        // Creating a club navigates to the new record (sanctioned by the
                        // no-reload rule); editing patches the list in place via the event above.
                        if (savedMode === 'create' && data.club?.slug) {
                            setTimeout(() => { window.location.href = '/admin/club/' + data.club.slug; }, 800);
                        }
                        this.isSubmitting = false;
                    } else {
                        if (response.status === 422 && data.errors) {
                            this.showFieldErrors(data.errors);
                        } else {
                            this.showToast(data.message || '{{ __("shared.components_club_modal_error_occurred") }}', 'error');
                        }
                        this.isSubmitting = false;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    this.showToast('{{ __("shared.components_club_modal_error_saving") }}', 'error');
                    this.isSubmitting = false;
                }
            },

            // Scoped per context so a draft abandoned in the super-admin "Create Club"
            // wizard can never resurface in the business-dashboard one (or vice versa)
            // — they're unrelated forms that happen to share this component.
            draftKey() {
                return `clubModalDraft:${this.context}`;
            },

            // Never in a draft: pictures (megabytes of base64 would overflow
            // localStorage) and the rich-text fields (their editor is not a plain input).
            draftSkips(key) {
                return ['logo', 'cover_image', 'registration_splash_image', 'registration_requirements', 'registration_terms', '_token', '_method', 'relations_loaded'].includes(key)
                    || key.startsWith('translations[');
            },

            saveDraft() {
                if (this.mode !== 'create') return;
                const form = this.$refs.form;
                if (!form) return;

                const draft = {};
                for (let [key, value] of new FormData(form).entries()) {
                    if (typeof value !== 'string' || this.draftSkips(key)) continue;
                    draft[key] = value;
                }
                try { localStorage.setItem(this.draftKey(), JSON.stringify(draft)); } catch (e) { /* storage full or blocked — a draft is a convenience */ }
            },

            loadDraft() {
                if (this.mode !== 'create') return;
                let raw = null;
                try {
                    // One-time cleanup of the old unscoped key from before contexts existed.
                    localStorage.removeItem('clubModalDraft');
                    raw = localStorage.getItem(this.draftKey());
                } catch (e) { return; }
                if (!raw) return;

                try {
                    const data = JSON.parse(raw);
                    const form = this.$refs.form;
                    if (!form || !data || typeof data !== 'object') return;

                    Object.keys(data).forEach(key => {
                        if (this.draftSkips(key) || typeof data[key] !== 'string') return;
                        const input = form.querySelector(`[name="${CSS.escape(key)}"]`);
                        if (input && input.type !== 'file' && input.type !== 'hidden' && input.type !== 'radio' && input.type !== 'checkbox' && !input.value) {
                            input.value = data[key];
                        }
                    });
                } catch (e) {
                    console.error('Error loading draft:', e);
                }
            },

            clearDraft() {
                try { localStorage.removeItem(this.draftKey()); } catch (e) { /* blocked storage */ }
            },

            showToast(message, type = 'info') {
                if (typeof window.showToast === 'function') {
                    window.showToast(type, message);
                } else {
                    console.warn('Toast:', type, message);
                }
            }
        }
    }
</script>
@endpush
@endonce
