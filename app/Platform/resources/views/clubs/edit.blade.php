@extends('layouts.admin')

@section('page-title', __('platform.platform_clubs_edit_page_title'))
@section('page-subtitle', __('platform.platform_clubs_edit_page_subtitle'))

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-12">
        <div class="lg:col-span-8 lg:col-start-3">
            <div class="card border-0 shadow-sm">
            <div class="card-header bg-card flex justify-between items-center">
                <h5 class="mb-0"><i class="bi bi-building me-2"></i>{{ $club->club_name }}</h5>
                <span class="badge bg-secondary">{{ $club->slug }}</span>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.platform.clubs.update', $club) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <!-- Owner Selection -->
                    <div class="mb-4">
                        <label for="owner_user_id" class="form-label">{{ __('platform.platform_clubs_edit_owner_label') }} <span class="text-destructive">*</span></label>
                        {{-- Two ways to choose the owner: find a member (the searchable list
                             below), or type their EMAIL and it is looked up. Both set the same
                             owner_user_id select, so the form posts exactly what it did. --}}
                        <div x-data="{
                                way: 'member', email: '', busy: false, msg: '', ok: '',
                                async useEmail() {
                                    const e = this.email.trim();
                                    if (!e || this.busy) return;
                                    this.busy = true; this.msg = ''; this.ok = '';
                                    try {
                                        const r = await fetch(@js(route('admin.api.users.by-email')) + '?email=' + encodeURIComponent(e), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                                        const d = r.ok ? await r.json() : { found: false };
                                        if (!d.found) { this.msg = @js(__('shared.tabs_basic_info_owner_email_not_found')); return; }
                                        this.setOwner(d.user.id, d.user.full_name, d.user.email, d.user.mobile);
                                        this.email = '';
                                    } catch (err) { this.msg = @js(__('shared.tabs_basic_info_owner_email_failed')); }
                                    finally { this.busy = false; }
                                },
                                setOwner(id, name, email, mobile) {
                                    const sel = document.getElementById('owner_user_id');
                                    if (!sel) return;
                                    if (!sel.querySelector('option[value=\'' + Number(id) + '\']')) {
                                        const o = document.createElement('option'); o.value = id; o.textContent = name || email;
                                        o.dataset.name = name || ''; o.dataset.email = email || ''; o.dataset.mobile = mobile || '';
                                        sel.appendChild(o);
                                    }
                                    window.jQuery ? jQuery('#owner_user_id').val(String(id)).trigger('change') : (sel.value = id);
                                    this.msg = ''; this.ok = (name || '') + ' · ' + (email || '');
                                },
                             }" @member-picked="setOwner($event.detail.id, $event.detail.name, $event.detail.email, $event.detail.mobile)" class="mb-2">
                            <div class="inline-flex p-1 rounded-full bg-muted text-xs font-semibold mb-2" role="tablist">
                                <button type="button" role="tab" :aria-selected="way === 'member'" @click="way = 'member'"
                                        class="px-3 py-1.5 rounded-full inline-flex items-center gap-1.5 transition-all"
                                        :class="way === 'member' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-gray-700'">
                                    <i class="bi bi-search"></i>{{ __('shared.person_picker_member') }}
                                </button>
                                <button type="button" role="tab" :aria-selected="way === 'email'" @click="way = 'email'; $nextTick(() => $refs.ownerEmail?.focus())"
                                        class="px-3 py-1.5 rounded-full inline-flex items-center gap-1.5 transition-all"
                                        :class="way === 'email' ? 'bg-white text-primary shadow-sm' : 'text-muted-foreground hover:text-gray-700'">
                                    <i class="bi bi-envelope"></i>{{ __('shared.person_picker_email') }}
                                </button>
                            </div>
                            <div x-show="way === 'member'">
                                {{-- Name, phone, email or TakeOne ID (App\Members\Support\MemberLookup); the list below still works too. --}}
                                <x-fields.member-search :search-url="route('admin.api.users.lookup')" input-class="form-control"
                                                        :placeholder="__('shared.member_search_placeholder')" />
                                <p x-show="ok" class="text-xs text-green-600 mt-1 mb-0"><i class="bi bi-check-circle-fill me-1"></i><span x-text="ok"></span></p>
                            </div>
                            <div x-show="way === 'email'" x-cloak>
                                <div class="flex gap-2">
                                    <input x-ref="ownerEmail" type="email" x-model="email" @keydown.enter.prevent="useEmail()" maxlength="191"
                                           class="form-control" placeholder="owner@club.com" autocomplete="off">
                                    <button type="button" class="btn btn-outline-primary btn-sm flex-shrink-0" @click="useEmail()" :disabled="busy || !email.trim()">
                                        <span x-show="!busy">{{ __('shared.tabs_basic_info_owner_email_use') }}</span>
                                        <i x-show="busy" class="bi bi-arrow-repeat animate-spin"></i>
                                    </button>
                                </div>
                                <p x-show="msg" x-text="msg" class="text-xs text-red-600 mt-1 mb-0"></p>
                                <p x-show="ok" class="text-xs text-green-600 mt-1 mb-0"><i class="bi bi-check-circle-fill me-1"></i><span x-text="ok"></span></p>
                                <p class="text-xs text-muted-foreground mt-1 mb-0">{{ __('shared.tabs_basic_info_owner_email_help') }}</p>
                            </div>
                        </div>
                        <select class="form-select @error('owner_user_id') is-invalid @enderror" id="owner_user_id" name="owner_user_id" required>
                            <option value="">{{ __('platform.platform_clubs_edit_owner_select') }}</option>
                            @foreach($users as $user)
                                <option value="{{ $user['id'] }}"
                                        data-name="{{ $user['full_name'] }}"
                                        data-email="{{ $user['email'] }}"
                                        data-mobile="{{ $user['mobile'] ?? '' }}"
                                        data-picture="{{ $user['profile_picture'] ?? '' }}"
                                        {{ (old('owner_user_id', $club->owner_user_id) == $user['id']) ? 'selected' : '' }}>
                                    {{ $user['full_name'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('owner_user_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted-foreground">{{ __('platform.platform_clubs_edit_owner_help') }}</small>
                    </div>

                    <!-- Basic Information -->
                    <h6 class="border-b pb-2 mb-3">{{ __('platform.platform_clubs_edit_basic_info') }}</h6>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                        <div>
                            <label for="club_name" class="form-label">{{ __('platform.platform_clubs_edit_club_name') }} <span class="text-destructive">*</span></label>
                            <input type="text" class="form-control @error('club_name') is-invalid @enderror" id="club_name" name="club_name" value="{{ old('club_name', $club->club_name) }}" required>
                            @error('club_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label for="slug" class="form-label">{{ __('platform.platform_clubs_edit_club_slug') }} <span class="text-destructive">*</span></label>
                            <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug', $club->slug) }}" required>
                            @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted-foreground">{{ __('platform.platform_clubs_edit_slug_help') }}</small>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <h6 class="border-b pb-2 mb-3 mt-4">{{ __('platform.platform_clubs_edit_contact_info') }}</h6>

                    {{-- No real contact yet? Borrow a TakeOne member's email and phone. --}}
                    <div class="mb-3" x-data="{
                            open: false, used: '',
                            use(p) {
                                const box = $el.nextElementSibling;
                                const e = box.querySelector('[name=email]'); if (e && p.email) e.value = p.email;
                                const n = box.querySelector('[name=phone_number]'); if (n && p.phone_number) n.value = p.phone_number;
                                const code = String(p.phone_code || '').trim();
                                const h = box.querySelector('input[type=hidden][name=phone_code]');
                                const sel = h ? h.closest('[x-data]') : null;
                                if (sel && code && window.Alpine) { const d = Alpine.$data(sel); if ((d.opts || []).some(o => String(o.value) === code)) d.sel = code; }
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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                        <div>
                            <label for="email" class="form-label">{{ __('platform.platform_clubs_edit_club_email') }}</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $club->email) }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label for="phone_number" class="form-label">{{ __('platform.platform_clubs_edit_phone_number') }}</label>
                            <div class="flex gap-2 items-start">
                                <div class="max-w-[130px]">
                                    <x-fields.select name="phone_code" :value="old('phone_code', $club->phone['code'] ?? '+973')"
                                                   :options="[['value' => '+973', 'label' => '+973 (BH)'], ['value' => '+966', 'label' => '+966 (SA)'], ['value' => '+971', 'label' => '+971 (AE)'], ['value' => '+965', 'label' => '+965 (KW)']]" />
                                </div>
                                <input type="text" class="form-control" name="phone_number" value="{{ old('phone_number', $club->phone['number'] ?? '') }}" placeholder="12345678">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                        <div>
                            <label for="currency" class="form-label">{{ __('platform.platform_clubs_edit_currency') }}</label>
                            <x-fields.select name="currency" :value="old('currency', $club->currency ?? 'BHD')"
                                           :options="[
                                               ['value' => 'BHD', 'label' => __('platform.platform_clubs_edit_currency_bhd')],
                                               ['value' => 'SAR', 'label' => __('platform.platform_clubs_edit_currency_sar')],
                                               ['value' => 'AED', 'label' => __('platform.platform_clubs_edit_currency_aed')],
                                               ['value' => 'KWD', 'label' => __('platform.platform_clubs_edit_currency_kwd')],
                                           ]" />
                        </div>
                        <div>
                            <label for="timezone" class="form-label">{{ __('platform.platform_clubs_edit_timezone') }}</label>
                            <x-fields.select name="timezone" :value="old('timezone', $club->timezone ?? 'Asia/Bahrain')"
                                           :options="[
                                               ['value' => 'Asia/Bahrain', 'label' => 'Asia/Bahrain'],
                                               ['value' => 'Asia/Riyadh', 'label' => 'Asia/Riyadh'],
                                               ['value' => 'Asia/Dubai', 'label' => 'Asia/Dubai'],
                                               ['value' => 'Asia/Kuwait', 'label' => 'Asia/Kuwait'],
                                           ]" />
                        </div>
                        <div>
                            <label for="country" class="form-label">{{ __('platform.platform_clubs_edit_country') }}</label>
                            <x-fields.select name="country" :value="old('country', $club->country ?? 'BH')"
                                           :options="[
                                               ['value' => 'BH', 'label' => __('platform.platform_clubs_edit_country_bh')],
                                               ['value' => 'SA', 'label' => __('platform.platform_clubs_edit_country_sa')],
                                               ['value' => 'AE', 'label' => __('platform.platform_clubs_edit_country_ae')],
                                               ['value' => 'KW', 'label' => __('platform.platform_clubs_edit_country_kw')],
                                           ]" />
                        </div>
                    </div>

                    <!-- Location -->
                    <h6 class="border-b pb-2 mb-3 mt-4">{{ __('platform.platform_clubs_edit_location') }}</h6>

                    <div class="mb-3">
                        <label for="address" class="form-label">{{ __('platform.platform_clubs_edit_address') }}</label>
                        <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2">{{ old('address', $club->address) }}</textarea>
                        @error('address')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                        <div>
                            <label for="gps_lat" class="form-label">{{ __('platform.platform_clubs_edit_gps_lat') }}</label>
                            <input type="number" step="0.0000001" class="form-control @error('gps_lat') is-invalid @enderror" id="gps_lat" name="gps_lat" value="{{ old('gps_lat', $club->gps_lat) }}">
                            @error('gps_lat')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label for="gps_long" class="form-label">{{ __('platform.platform_clubs_edit_gps_long') }}</label>
                            <input type="number" step="0.0000001" class="form-control @error('gps_long') is-invalid @enderror" id="gps_long" name="gps_long" value="{{ old('gps_long', $club->gps_long) }}">
                            @error('gps_long')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Branding -->
                    <h6 class="border-b pb-2 mb-3 mt-4">{{ __('platform.platform_clubs_edit_branding') }}</h6>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                        <div class="text-center">
                            <label class="form-label block">{{ __('platform.platform_clubs_edit_club_logo') }}</label>
                            <x-image-upload
                                id="club_logo"
                                name="logo"
                                :width="200"
                                :height="200"
                                shape="square"
                                folder="clubs/{{ $club->id }}/logos"
                                filename="logo_{{ $club->id }}"
                                uploadUrl="{{ route('admin.platform.clubs.upload-logo', $club) }}"
                                :currentImage="$club->logo ? file_url($club->logo) : ''"
                                placeholder="{{ __('platform.platform_clubs_edit_no_logo') }}"
                                placeholderIcon="bi-building"
                                buttonText="{{ __('platform.platform_clubs_edit_change_logo') }}"
                                buttonClass="btn btn-success btn-sm"
                                :rounded="false"
                            />
                            <small class="text-muted-foreground block mt-2">{{ __('platform.platform_clubs_edit_logo_help') }}</small>
                        </div>
                        <div class="text-center">
                            <label class="form-label block">{{ __('platform.platform_clubs_edit_cover_image') }}</label>
                            <x-image-upload
                                id="club_cover"
                                name="cover_image"
                                :width="600"
                                :height="200"
                                shape="square"
                                folder="clubs/{{ $club->id }}/covers"
                                filename="cover_{{ $club->id }}"
                                uploadUrl="{{ route('admin.platform.clubs.upload-cover', $club) }}"
                                :currentImage="$club->cover_image ? file_url($club->cover_image) : ''"
                                placeholder="{{ __('platform.platform_clubs_edit_no_cover') }}"
                                placeholderIcon="bi-image"
                                buttonText="{{ __('platform.platform_clubs_edit_change_cover') }}"
                                buttonClass="btn btn-success btn-sm"
                                :rounded="false"
                            />
                            <small class="text-muted-foreground block mt-2">{{ __('platform.platform_clubs_edit_cover_help') }}</small>
                        </div>
                    </div>

                    <!-- Actions -->
                        <div class="flex justify-between mt-4 pt-3 border-t">
                            <div>
                                <a href="{{ route('admin.platform.clubs') }}" class="btn btn-secondary">
                                    <i class="bi bi-chevron-left me-2"></i>{{ __('shared.cancel') }}
                                </a>
                                <button type="button" class="btn btn-danger ms-2" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="bi bi-trash me-2"></i>{{ __('platform.platform_clubs_edit_delete_club') }}
                                </button>
                                @if(config('features.club_removal_tool'))
                                    <button type="button" class="btn btn-danger ms-2"
                                            onclick="window.dispatchEvent(new CustomEvent('open-club-removal', { detail: { id: {{ (int) $club->id }}, name: {{ \Illuminate\Support\Js::from($club->club_name) }} } }))">
                                        <i class="bi bi-building-x me-2"></i>{{ __('Remove club & athletes') }}
                                    </button>
                                @endif
                            </div>
                            <button type="submit" class="btn text-white" style="background: linear-gradient(135deg, hsl(250 65% 66%) 0%, hsl(262 60% 56%) 100%);">
                                <i class="bi bi-check-circle me-2"></i>{{ __('platform.platform_clubs_edit_update_club') }}
                            </button>
                        </div>
                </form>
            </div>
        </div>
    </div>
</div>
<x-club-removal />
@endsection

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-destructive text-white">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="bi bi-exclamation-triangle me-2"></i>{{ __('platform.platform_clubs_edit_confirm_delete') }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('platform.platform_clubs_edit_close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">{{ __('platform.platform_clubs_edit_delete_confirm_prefix') }} <strong>{{ $club->club_name }}</strong>?</p>
                <p class="text-destructive mb-0 mt-2"><small><i class="bi bi-info-circle me-1"></i>{{ __('platform.platform_clubs_edit_delete_warning') }}</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('shared.cancel') }}</button>
                <form action="{{ route('admin.platform.clubs.destroy', $club) }}" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-2"></i>{{ __('platform.platform_clubs_edit_yes_delete') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Styles moved to app.css (Phase 6) --}}

@push('scripts')
<script>
// Initialize Select2 for owner dropdown
$(document).ready(function() {
    $('#owner_user_id').select2({
        placeholder: '{{ __("platform.platform_clubs_edit_search_placeholder") }}',
        allowClear: true,
        width: '100%',
        templateResult: formatUser,
        templateSelection: formatUserSelection,
        matcher: customMatcher
    });

    // Custom template for dropdown options. The name, email and phone are a
    // member's own text: built with .text() / attributes, never HTML strings,
    // so a name can never run as script here.
    function formatUser(user) {
        if (!user.id) {
            return user.text;
        }

        var $user = $(user.element);
        var name = String($user.data('name') || '');
        var email = String($user.data('email') || '');
        var mobile = String($user.data('mobile') || '');
        var picture = String($user.data('picture') || '');

        var $avatar;
        if (picture && /^(https?:)?\//.test(picture)) {
            $avatar = $('<img class="user-avatar">').attr({ src: picture, alt: name });
        } else {
            $avatar = $('<div class="user-avatar-placeholder"></div>').text(name ? name.charAt(0).toUpperCase() : '?');
        }

        var $details = $('<div class="user-details"></div>');
        if (email) {
            $details.append($('<span class="user-detail-item"></span>').append('<i class="bi bi-envelope"></i> ').append(document.createTextNode(email)));
        }
        if (mobile) {
            $details.append($('<span class="user-detail-item"></span>').append('<i class="bi bi-phone"></i> ').append(document.createTextNode(mobile)));
        }

        var $container = $('<div class="user-option"></div>')
            .append($avatar)
            .append($('<div class="user-info"></div>').append($('<div class="user-name"></div>').text(name)).append($details));

        return $container;
    }

    // Custom template for selected option
    function formatUserSelection(user) {
        if (!user.id) {
            return user.text;
        }

        var $user = $(user.element);
        var name = $user.data('name');
        return name || user.text;
    }

    // Custom matcher for searching by name, email, or phone
    function customMatcher(params, data) {
        // If there are no search terms, return all data
        if ($.trim(params.term) === '') {
            return data;
        }

        // Do not display the item if there is no 'text' property
        if (typeof data.text === 'undefined') {
            return null;
        }

        var $option = $(data.element);
        var name = $option.data('name') || '';
        var email = $option.data('email') || '';
        var mobile = $option.data('mobile') || '';

        var searchTerm = params.term.toLowerCase();

        // Check if the search term matches name, email, or mobile
        if (name.toLowerCase().indexOf(searchTerm) > -1 ||
            email.toLowerCase().indexOf(searchTerm) > -1 ||
            mobile.toLowerCase().indexOf(searchTerm) > -1) {
            return data;
        }

        // Return null if the term should not be displayed
        return null;
    }
});
</script>
@endpush

@if(config('features.club_removal_tool'))
@push('scripts')
<script>
    // The club this page edits is gone: back to the list, which no longer has it.
    if (window.__clubRemovedEdit) window.removeEventListener('club-removed', window.__clubRemovedEdit);
    window.__clubRemovedEdit = function (e) {
        if (e.detail && String(e.detail.id) === @js((string) $club->id)) {
            setTimeout(function () { window.location.assign(@js(route('admin.platform.clubs'))); }, 1600);
        }
    };
    window.addEventListener('club-removed', window.__clubRemovedEdit);
</script>
@endpush
@endif

