@extends('layouts.admin-club')

@section('club-admin-content')
<div class="space-y-6" x-data="{ activeTab: 'basic', showDeleteClubModal: false, showOwnerModal: false, ownerTab: 'existing' }">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ __('admin.club_details_index_title') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('admin.club_details_index_subtitle') }}</p>
        </div>
        <button type="submit" form="clubDetailsForm" class="btn btn-primary shrink-0">
            <i class="bi bi-check-lg me-2"></i>{{ __('admin.club_details_index_save_all_changes') }}
        </button>
    </div>

    <!-- Success/Error Messages -->
    @if($errors->any())
    <div class="alert alert-danger relative" role="alert" x-data="{ show: true }" x-show="show">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="absolute top-3 end-3 text-red-600 hover:text-red-800" @click="show = false">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    @endif

    <!-- Tabs Navigation -->
    <div class="border-b overflow-x-auto">
        <nav class="flex gap-1 min-w-max" role="tablist">
            <button type="button" class="tab-btn" :class="{ 'active': activeTab === 'basic' }" @click="activeTab = 'basic'" role="tab">
                <i class="bi bi-info-circle me-2"></i>{{ __('admin.club_details_index_tab_basic') }}
            </button>
            <button type="button" class="tab-btn" :class="{ 'active': activeTab === 'location' }" @click="activeTab = 'location'; window.LocationMap && window.LocationMap.refresh('clubDetailsLoc')" role="tab">
                <i class="bi bi-geo-alt me-2"></i>{{ __('admin.club_details_index_tab_location') }}
            </button>
            <button type="button" class="tab-btn" :class="{ 'active': activeTab === 'branding' }" @click="activeTab = 'branding'" role="tab">
                <i class="bi bi-palette me-2"></i>{{ __('admin.club_details_index_tab_branding') }}
            </button>
            <button type="button" class="tab-btn" :class="{ 'active': activeTab === 'registration' }" @click="activeTab = 'registration'" role="tab">
                <i class="bi bi-clipboard-check me-2"></i>{{ __('admin.club_details_index_tab_registration') }}
            </button>
            <button type="button" class="tab-btn" :class="{ 'active': activeTab === 'social' }" @click="activeTab = 'social'" role="tab">
                <i class="bi bi-share me-2"></i>{{ __('admin.club_details_index_tab_social_media') }}
            </button>
            <button type="button" class="tab-btn" :class="{ 'active': activeTab === 'settings' }" @click="activeTab = 'settings'" role="tab">
                <i class="bi bi-gear me-2"></i>{{ __('admin.club_details_index_tab_settings') }}
            </button>
        </nav>
    </div>

    <form id="clubDetailsForm" action="{{ route('admin.club.update', $club->slug) }}" method="POST" enctype="multipart/form-data" x-data="{ lang: 'en' }">
        @csrf
        @method('PUT')

        <x-lang-toggle class="mb-4" />

        <!-- Basic Tab -->
        <div class="tab-content" id="tab-basic" x-show="activeTab === 'basic'">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Left Column - Basic Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-building text-primary me-2"></i>{{ __('admin.club_details_index_basic_information') }}
                        </h5>
                    </div>
                    <div class="card-body space-y-4">
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_club_name_label') }} <span class="text-danger">*</span></label>
                            <input type="text" name="club_name" class="form-control" value="{{ old('club_name', $club->club_name) }}" x-show="lang==='en'" required>
                            <input type="text" name="translations[club_name][ar]" dir="rtl" x-show="lang==='ar'" x-cloak class="form-control" placeholder="اسم النادي بالعربية" value="{{ old('translations.club_name.ar', data_get($club ?? null, 'translations.club_name.ar')) }}">
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_slogan_label') }}</label>
                            <input type="text" name="slogan" class="form-control" value="{{ old('slogan', $club->slogan) }}" x-show="lang==='en'" placeholder="{{ __('admin.club_details_index_slogan_placeholder') }}">
                            <input type="text" name="translations[slogan][ar]" dir="rtl" x-show="lang==='ar'" x-cloak class="form-control" placeholder="شعار النادي بالعربية" value="{{ old('translations.slogan.ar', data_get($club ?? null, 'translations.slogan.ar')) }}">
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_description_label') }}</label>
                            <textarea name="description" class="form-control" rows="3" x-show="lang==='en'" placeholder="{{ __('admin.club_details_index_description_placeholder') }}">{{ old('description', $club->description) }}</textarea>
                            <textarea name="translations[description][ar]" dir="rtl" x-show="lang==='ar'" x-cloak class="form-control" rows="3" placeholder="وصف النادي بالعربية">{{ old('translations.description.ar', data_get($club ?? null, 'translations.description.ar')) }}</textarea>
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_registration_fee_label') }} ({{ $club->currency ?? 'USD' }})</label>
                            <input type="number" name="registration_fee" class="form-control" step="0.01" value="{{ old('registration_fee', $club->registration_fee) }}" placeholder="0.00">
                            <small class="text-muted">{{ __('admin.club_details_index_registration_fee_help') }}</small>
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_enrollment_fee_label') }} ({{ $club->currency ?? 'USD' }})</label>
                            <input type="number" name="enrollment_fee" class="form-control" step="0.01" value="{{ old('enrollment_fee', $club->enrollment_fee) }}" placeholder="0.00">
                            <small class="text-muted">{{ __('admin.club_details_index_enrollment_fee_help') }}</small>
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_commercial_reg_label') }}</label>
                            <input type="text" name="commercial_reg_number" class="form-control" value="{{ old('commercial_reg_number', $club->commercial_reg_number) }}" placeholder="{{ __('admin.club_details_index_commercial_reg_placeholder') }}">
                            <small class="text-muted">{{ __('admin.club_details_index_appears_on_receipts') }}</small>
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_vat_reg_label') }}</label>
                            <input type="text" name="vat_reg_number" class="form-control" value="{{ old('vat_reg_number', $club->vat_reg_number) }}" placeholder="{{ __('admin.club_details_index_vat_reg_placeholder') }}">
                            <small class="text-muted">{{ __('admin.club_details_index_appears_on_receipts') }}</small>
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_vat_percentage_label') }}</label>
                            <input type="number" name="vat_percentage" class="form-control" step="0.01" value="{{ old('vat_percentage', $club->vat_percentage) }}" placeholder="0.00">
                            <small class="text-muted">{{ __('admin.club_details_index_vat_percentage_help') }}</small>
                            <div class="alert alert-warning mt-2 py-2 px-3 small">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                {{ __('admin.club_details_index_vat_rate_warning') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Contact Information -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-telephone text-primary me-2"></i>{{ __('admin.club_details_index_contact_information') }}
                        </h5>
                    </div>
                    <div class="card-body space-y-4">
                        <h6 class="text-muted text-uppercase small font-semibold border-bottom pb-2">{{ __('admin.club_details_index_club_contact') }}</h6>

                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_club_email_label') }}</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $club->email) }}" placeholder="{{ __('admin.club_details_index_club_email_placeholder') }}">
                        </div>
                        <x-country-dropdown
                            name="country"
                            id="countrySelect"
                            :value="old('country', $club->country)"
                            :label="__('admin.club_details_index_country_label')" />
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_club_phone_label') }}</label>
                            <x-country-code-dropdown
                                name="phone_code"
                                id="phoneCode"
                                :value="old('phone_code', $club->phone['code'] ?? '+973')"
                                :error="$errors->first('phone_code')">
                                <input type="text"
                                       class="form-control border-0"
                                       name="phone_number"
                                       value="{{ old('phone_number', $club->phone['number'] ?? '') }}"
                                       placeholder="12345678">
                            </x-country-code-dropdown>
                        </div>
                        <x-currency-dropdown
                            name="currency"
                            id="currencySelect"
                            :value="old('currency', $club->currency)"
                            :label="__('admin.club_details_index_currency_label')" />
                        <x-timezone-dropdown
                            name="timezone"
                            id="timezoneSelect"
                            :value="old('timezone', $club->timezone)"
                            :label="__('admin.club_details_index_timezone_label')" />
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_club_slug_label') }}</label>
                            <div class="input-group">
                                <input type="text" name="slug" class="form-control" value="{{ old('slug', $club->slug) }}" placeholder="{{ __('admin.club_details_index_club_slug_placeholder') }}">
                            </div>
                            <small class="text-muted">{{ __('admin.club_details_index_club_slug_help') }}</small>
                            @if($club->slug && $club->country)
                            <div class="mt-2 p-2 bg-light rounded">
                                <small class="text-muted">{{ __('admin.club_details_index_club_url_label') }}</small>
                                <div class="flex items-center gap-2 mt-1">
                                    <code class="flex-1">{{ route('clubs.show', [strtolower($club->country), $club->slug]) }}</code>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyClubUrl()">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                    <a href="{{ route('clubs.show', [strtolower($club->country), $club->slug]) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 mt-3">
                                    <x-qr-code
                                        :url="\App\Http\Controllers\QrController::clubPageUrl($club)"
                                        :title="($club->club_name ?? 'Club') . ' — Club page'"
                                        :caption="__('admin.club_details_index_qr_page_caption')"
                                        :filename="'qr-' . $club->slug . '-page'"
                                        :label="__('admin.club_details_index_qr_page_label')"
                                        icon="bi-qr-code"
                                        :poster-url="route('qr.club.page', $club)" />
                                    <x-qr-code
                                        :url="\App\Http\Controllers\QrController::clubRegisterUrl($club)"
                                        :title="($club->club_name ?? 'Club') . ' — Registration'"
                                        :caption="__('admin.club_details_index_qr_register_caption')"
                                        :filename="'qr-' . $club->slug . '-register'"
                                        :label="__('admin.club_details_index_qr_register_label')"
                                        icon="bi-person-plus"
                                        :poster-url="route('qr.club.register', $club)" />
                                </div>
                            </div>
                            @endif
                        </div>

                        <h6 class="text-muted text-uppercase small font-semibold border-bottom pb-2 pt-4">{{ __('admin.club_details_index_owner_information') }}</h6>

                        <div id="ownerSection">
                        @if($club->owner)
                        <div class="card bg-light">
                            <div class="card-body">
                                <div class="flex justify-between items-start">
                                    <div id="ownerCardInfo">
                                        <h6 class="mb-1">{{ $club->owner->full_name }}</h6>
                                        @if($club->owner->email)
                                        <p class="text-muted small mb-1">
                                            <i class="bi bi-envelope me-1"></i>{{ $club->owner->email }}
                                        </p>
                                        @endif
                                        @if($club->owner->formatted_mobile)
                                        <p class="text-muted small mb-0">
                                            <i class="bi bi-phone me-1"></i>{{ $club->owner->formatted_mobile }}
                                        </p>
                                        @endif
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" @click="showOwnerModal = true">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        @else
                        <div class="text-center py-4 border-2 border-dashed rounded">
                            <i class="bi bi-person-plus text-muted" style="font-size: 2rem;"></i>
                            <p class="text-muted mt-2 mb-3">{{ __('admin.club_details_index_no_owner_assigned') }}</p>
                            <div class="flex gap-2 justify-center">
                                <button type="button" class="btn btn-outline-primary btn-sm" @click="showOwnerModal = true; ownerTab = 'existing'">
                                    <i class="bi bi-link me-1"></i>{{ __('admin.club_details_index_link_existing') }}
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" @click="showOwnerModal = true; ownerTab = 'new'">
                                    <i class="bi bi-person-plus me-1"></i>{{ __('admin.club_details_index_create_new') }}
                                </button>
                            </div>
                        </div>
                        @endif
                        </div>{{-- /#ownerSection --}}

                        <input type="hidden" name="owner_name" value="{{ old('owner_name', $club->owner_name) }}">
                        <input type="hidden" name="owner_email" value="{{ old('owner_email', $club->owner_email) }}">
                    </div>
                </div>
            </div>
        </div>

        <!-- Location Tab -->
        <div class="tab-content" id="tab-location" x-show="activeTab === 'location'" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-geo-alt text-primary me-2"></i>{{ __('admin.club_details_index_location_gps') }}
                    </h5>
                </div>
                <div class="card-body space-y-4">
                    <x-location-map
                        id="clubDetailsLoc"
                        :lat="old('gps_lat', $club->gps_lat)"
                        :lng="old('gps_long', $club->gps_long)"
                        :address="old('address', $club->address ?? '')"
                        :defaultLat="26.2285"
                        :defaultLng="50.5860"
                        height="400px"
                    />
                    <div x-show="lang==='ar'" x-cloak>
                        <label class="form-label">العنوان بالعربية</label>
                        <input type="text" name="translations[address][ar]" dir="rtl" class="form-control" placeholder="عنوان النادي بالعربية" value="{{ old('translations.address.ar', data_get($club ?? null, 'translations.address.ar')) }}">
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-primary btn-sm" id="useMyLocationBtn">
                            <i class="bi bi-crosshair me-1"></i>{{ __('admin.club_details_index_use_my_location') }}
                        </button>
                        @if($club->gps_lat && $club->gps_long)
                        <a href="https://www.google.com/maps?q={{ $club->gps_lat }},{{ $club->gps_long }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('admin.club_details_index_view_on_google_maps') }}
                        </a>
                        @endif
                    </div>

                    <div>
                        <label class="form-label">{{ __('admin.club_details_index_google_maps_url_label') }}</label>
                        <div class="flex gap-2">
                            <input type="url" name="maps_url" class="form-control"
                                   placeholder="https://maps.google.com/..."
                                   value="{{ old('maps_url', $club->maps_url) }}">
                            @if($club->maps_url)
                            <a href="{{ $club->maps_url }}" target="_blank" class="btn btn-outline-secondary flex-shrink-0">
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                            @endif
                        </div>
                        <p class="text-xs text-muted-foreground mt-1">{{ __('admin.club_details_index_google_maps_help') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Branding Tab -->
        <div class="tab-content" id="tab-branding" x-show="activeTab === 'branding'" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-palette text-primary me-2"></i>{{ __('admin.club_details_index_branding_assets') }}
                    </h5>
                </div>
                <div class="card-body space-y-5">
                    <!-- Logo -->
                    <div>
                        <label class="form-label font-medium">{{ __('admin.club_details_index_club_logo_label') }}</label>
                        <small class="text-muted block mb-3">{{ __('admin.club_details_index_logo_recommendation') }}</small>
                        <x-takeone-cropper
                            id="clubDetailLogo"
                            :width="200"
                            :height="200"
                            shape="square"
                            mode="form"
                            inputName="logo"
                            folder="clubs/{{ $club->id }}/branding"
                            :filename="'logo_' . time()"
                            :previewWidth="150"
                            :previewHeight="150"
                            :currentImage="$club->logo ? asset('storage/' . $club->logo) : ''"
                            :buttonText="__('admin.club_details_index_change_logo')"
                            buttonClass="btn btn-outline-secondary"
                        />
                    </div>

                    <hr>

                    <!-- Favicon -->
                    <div>
                        <label class="form-label font-medium">{{ __('admin.club_details_index_favicon_label') }}</label>
                        <small class="text-muted block mb-3">{{ __('admin.club_details_index_favicon_recommendation') }}</small>
                        <x-takeone-cropper
                            id="clubDetailFavicon"
                            :width="64"
                            :height="64"
                            shape="square"
                            mode="form"
                            inputName="favicon"
                            folder="clubs/{{ $club->id }}/branding"
                            :filename="'favicon_' . time()"
                            :previewWidth="64"
                            :previewHeight="64"
                            :currentImage="$club->favicon ? asset('storage/' . $club->favicon) : ''"
                            :buttonText="__('admin.club_details_index_change_favicon')"
                            buttonClass="btn btn-outline-secondary"
                        />
                    </div>

                    <hr>

                    <!-- Cover Image -->
                    <div>
                        <label class="form-label font-medium">{{ __('admin.club_details_index_cover_image_label') }}</label>
                        <small class="text-muted block mb-3">{{ __('admin.club_details_index_cover_recommendation') }}</small>
                        <x-takeone-cropper
                            id="clubDetailCover"
                            :width="600"
                            :height="338"
                            shape="square"
                            mode="form"
                            inputName="cover_image"
                            folder="clubs/{{ $club->id }}/branding"
                            :filename="'cover_' . time()"
                            :previewWidth="400"
                            :previewHeight="225"
                            :currentImage="$club->cover_image ? asset('storage/' . $club->cover_image) : ''"
                            :buttonText="__('admin.club_details_index_change_cover')"
                            buttonClass="btn btn-outline-secondary"
                            :uploadAsIs="true"
                            :uploadAsIsText="__('admin.club_details_index_upload_without_cropping')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Registration Page Tab -->
        <div class="tab-content" id="tab-registration" x-show="activeTab === 'registration'" style="display: none;">
            @php
                $reqAr   = data_get($club->translations, 'registration_requirements.ar', '');
                $termsAr = data_get($club->translations, 'registration_terms.ar', '');
            @endphp
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-clipboard-check text-primary me-2"></i>{{ __('admin.club_details_index_self_registration_page') }}</h5>
                </div>
                <div class="card-body space-y-5">
                    <p class="text-muted small mb-0">{{ __('admin.club_details_index_reg_page_intro_before') }}<code>/register/{{ strtolower($club->country) }}/{{ $club->slug }}</code>{{ __('admin.club_details_index_reg_page_intro_mid') }}<strong>{{ __('admin.club_details_index_both_languages') }}</strong>{{ __('admin.club_details_index_reg_page_intro_after') }}</p>

                    <!-- Registration background image — full-resolution upload + live phone preview -->
                    @once
                    <style>
                        .reg-phone { width: 188px; aspect-ratio: 9 / 19; border-radius: 26px; padding: 7px; background: #111; box-shadow: 0 12px 32px rgba(0,0,0,.28); position: relative; flex-shrink: 0; }
                        .reg-phone-notch { position: absolute; top: 12px; left: 50%; transform: translateX(-50%); width: 52px; height: 5px; background: #000; border-radius: 4px; z-index: 3; }
                        .reg-phone-screen { position: relative; width: 100%; height: 100%; border-radius: 20px; overflow: hidden; background: #0a0a14 center/cover no-repeat; }
                        .reg-phone-overlay { position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(5,5,20,.12) 0%, rgba(5,5,20,.45) 52%, rgba(5,5,20,.96) 100%); }
                        .reg-phone-content { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; padding: 0 14px 18px; text-align: center; }
                        .reg-phone-logo { width: 46px; height: 46px; border-radius: 50%; border: 2px solid rgba(255,255,255,.3); background: rgba(0,0,0,.4) center/contain no-repeat; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; margin-bottom: 9px; }
                        .reg-phone-name { color: #fff; font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; text-shadow: 0 2px 10px rgba(0,0,0,.7); line-height: 1.15; }
                        .reg-phone-tag { color: rgba(255,255,255,.6); font-size: 8px; margin: 4px 0 11px; }
                        .reg-phone-langs { display: grid; grid-template-columns: 1fr 1fr; gap: 7px; width: 100%; }
                        .reg-phone-lang { background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2); border-radius: 10px; padding: 7px 4px; display: flex; flex-direction: column; align-items: center; gap: 3px; color: #fff; font-size: 9px; font-weight: 600; }
                        .reg-phone-lang .fi { font-size: 15px; border-radius: 3px; }
                    </style>
                    <script>
                        function splashFilePreview(opts) {
                            return {
                                splash: opts.current || '', logo: opts.logo || '', name: opts.name || '', fileName: '',
                                init() {
                                    const nameInput = document.getElementById('club_name');
                                    if (nameInput) { this.name = nameInput.value || this.name; nameInput.addEventListener('input', () => { this.name = nameInput.value; }); }
                                },
                                onFile(e) {
                                    const f = e.target.files && e.target.files[0];
                                    if (!f) return;
                                    this.fileName = f.name;
                                    const r = new FileReader();
                                    r.onload = (ev) => { this.splash = ev.target.result; };
                                    r.readAsDataURL(f);
                                },
                            };
                        }
                    </script>
                    @endonce
                    <div x-data="splashFilePreview({ logo: @js($club->logo ? asset('storage/' . $club->logo) : ''), name: @js($club->club_name), current: @js($club->registration_splash_image ? asset('storage/' . $club->registration_splash_image) : '') })" x-init="init()">
                        <label class="form-label font-medium">{{ __('admin.club_details_index_reg_bg_image_label') }}</label>
                        <small class="text-muted block mb-3">{{ __('admin.club_details_index_reg_bg_help_1') }}<strong>{{ __('admin.club_details_index_reg_bg_full_quality') }}</strong>{{ __('admin.club_details_index_reg_bg_help_2') }}<strong>{{ __('admin.club_details_index_reg_bg_not') }}</strong>{{ __('admin.club_details_index_reg_bg_help_3') }}</small>
                        <div class="flex flex-col sm:flex-row gap-6 items-center sm:items-start">
                            <div>
                                <label class="btn btn-outline-secondary btn-sm" style="cursor:pointer">
                                    <i class="bi bi-upload me-2"></i>{{ __('admin.club_details_index_choose_image') }}
                                    <input type="file" name="registration_splash_image" accept="image/jpeg,image/png,image/webp"
                                           style="display:none" @change="onFile($event)">
                                </label>
                                <p class="text-muted small mt-2 mb-0" x-text="fileName || '{{ __('admin.club_details_index_no_file_chosen') }}'"></p>
                                <small class="text-muted block mt-2">{{ __('admin.club_details_index_reg_bg_tip') }}</small>
                            </div>
                            <div class="flex flex-col items-center">
                                <small class="text-muted mb-2"><i class="bi bi-eye me-1"></i>{{ __('admin.club_details_index_live_preview') }}</small>
                                <div class="reg-phone">
                                    <div class="reg-phone-notch"></div>
                                    <div class="reg-phone-screen" :style="splash ? ('background-image:url(' + splash + ')') : ''">
                                        <div class="reg-phone-overlay"></div>
                                        <div class="reg-phone-content">
                                            <div class="reg-phone-logo" :style="logo ? ('background-image:url(' + logo + ')') : ''">
                                                <i class="bi bi-shield-fill-check" x-show="!logo"></i>
                                            </div>
                                            <div class="reg-phone-name" x-text="name || 'TAKEONE'"></div>
                                            <div class="reg-phone-tag">Choose your language / اختر لغتك</div>
                                            <div class="reg-phone-langs">
                                                <div class="reg-phone-lang"><span class="fi fi-gb"></span><span>English</span></div>
                                                <div class="reg-phone-lang"><span class="fi fi-bh"></span><span>العربية</span></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <!-- Registration requirements (both languages shown together) -->
                    <div>
                        <label class="form-label font-medium">{{ __('admin.club_details_index_reg_requirements_label') }}</label>
                        <small class="text-muted block mb-3">{{ __('admin.club_details_index_reg_requirements_help') }}</small>
                        <p class="small font-semibold mb-1"><span class="fi fi-gb me-1"></span> {{ __('admin.club_details_index_english') }}</p>
                        <x-rich-text-editor name="registration_requirements" :value="$club->registration_requirements ?? ''"
                            :placeholder="__('admin.club_details_index_reg_requirements_placeholder')" />
                        <p class="small font-semibold mb-1 mt-3"><span class="fi fi-bh me-1"></span> {{ __('admin.club_details_index_arabic') }}</p>
                        <x-rich-text-editor name="translations[registration_requirements][ar]" :value="$reqAr" dir="rtl"
                            placeholder="ما يحتاجه الأعضاء للتسجيل…" />
                    </div>

                    <!-- Terms & conditions (both languages shown together) -->
                    <div>
                        <label class="form-label font-medium">{{ __('admin.club_details_index_terms_conditions_label') }}</label>
                        <small class="text-muted block mb-3">{{ __('admin.club_details_index_terms_help') }}</small>
                        <p class="small font-semibold mb-1"><span class="fi fi-gb me-1"></span> {{ __('admin.club_details_index_english') }}</p>
                        <x-rich-text-editor name="registration_terms" :value="$club->registration_terms ?? ''" min-height="200px"
                            :placeholder="__('admin.club_details_index_terms_placeholder')" />
                        <p class="small font-semibold mb-1 mt-3"><span class="fi fi-bh me-1"></span> {{ __('admin.club_details_index_arabic') }}</p>
                        <x-rich-text-editor name="translations[registration_terms][ar]" :value="$termsAr" dir="rtl" min-height="200px"
                            placeholder="شروط وأحكام النادي للانضمام…" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Social Media Tab -->
        <div class="tab-content" id="tab-social" x-show="activeTab === 'social'" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-share text-primary me-2"></i>{{ __('admin.club_details_index_social_media_links') }}
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted text-sm mb-4">{{ __('admin.club_details_index_social_media_help') }}</p>
                    <x-social-links-editor :links="$club->socialLinks" containerId="clubSocialLinksContainer" />
                </div>
            </div>
        </div>

        <!-- Settings Tab -->
        <div class="tab-content" id="tab-settings" x-show="activeTab === 'settings'" style="display: none;">
            <!-- Code Prefixes -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-hash text-primary me-2"></i>{{ __('admin.club_details_index_code_prefixes') }}
                    </h5>
                </div>
                <div class="card-body">
                    @php
                        $settings = $club->settings ?? [];
                    @endphp
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_member_code_prefix_label') }}</label>
                            <input type="text" name="settings[member_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.member_code_prefix', $settings['member_code_prefix'] ?? 'MEM') }}" placeholder="MEM">
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_child_code_prefix_label') }}</label>
                            <input type="text" name="settings[child_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.child_code_prefix', $settings['child_code_prefix'] ?? 'CHILD') }}" placeholder="CHILD">
                            <small class="text-muted">{{ __('admin.club_details_index_child_code_help') }}</small>
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_invoice_code_prefix_label') }}</label>
                            <input type="text" name="settings[invoice_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.invoice_code_prefix', $settings['invoice_code_prefix'] ?? 'INV') }}" placeholder="INV">
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_receipt_code_prefix_label') }}</label>
                            <input type="text" name="settings[receipt_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.receipt_code_prefix', $settings['receipt_code_prefix'] ?? 'REC') }}" placeholder="REC">
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_expense_code_prefix_label') }}</label>
                            <input type="text" name="settings[expense_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.expense_code_prefix', $settings['expense_code_prefix'] ?? 'EXP') }}" placeholder="EXP">
                        </div>
                        <div>
                            <label class="form-label">{{ __('admin.club_details_index_specialist_code_prefix_label') }}</label>
                            <input type="text" name="settings[specialist_code_prefix]" class="form-control text-uppercase" value="{{ old('settings.specialist_code_prefix', $settings['specialist_code_prefix'] ?? 'SPEC') }}" placeholder="SPEC">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Member Preferences -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-people text-primary me-2"></i>{{ __('admin.club_details_index_member_prefs') }}
                    </h5>
                </div>
                <div class="card-body">
                    {{-- Hidden 0 so an unchecked box still submits an explicit "off"
                         (a bare unchecked checkbox sends nothing → array_merge would
                         keep the old value). --}}
                    <input type="hidden" name="settings[block_explore]" value="0">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="settings[block_explore]" value="1"
                               class="mt-0.5 w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary"
                               @checked(old('settings.block_explore', ! empty($settings['block_explore']))) >
                        <span>
                            <span class="block text-sm font-medium text-gray-900">{{ __('admin.club_details_index_block_explore_label') }}</span>
                            <span class="block text-xs text-muted-foreground mt-0.5">{{ __('admin.club_details_index_block_explore_help') }}</span>
                        </span>
                    </label>
                </div>
            </div>

            <!-- WhatsApp Integration -->
            <div class="card mb-4" x-data="clubWhatsAppIntegration()">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-whatsapp text-primary me-2"></i>{{ __('admin.club_details_index_whatsapp_title') }}
                    </h5>
                </div>
                <div class="card-body space-y-4">
                    <p class="text-muted small mb-0">{{ __('admin.club_details_index_whatsapp_description') }}</p>

                    @unless($whatsappSettings['gateway_configured'])
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>{{ __('admin.club_details_index_whatsapp_gateway_not_configured') }}
                    </div>
                    @endunless

                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" x-model="form.enabled"
                               class="mt-0.5 w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary">
                        <span>
                            <span class="block text-sm font-medium text-gray-900">{{ __('admin.club_details_index_whatsapp_enable') }}</span>
                            <span class="block text-xs text-muted-foreground mt-0.5">{{ __('admin.club_details_index_whatsapp_enable_hint') }}</span>
                        </span>
                    </label>

                    <div>
                        <label class="form-label">{{ __('admin.club_details_index_whatsapp_session_name') }}</label>
                        <input type="text" x-model="form.session_name" class="form-control" placeholder="my-club-session">
                        <small class="text-muted">{{ __('admin.club_details_index_whatsapp_session_name_hint') }}</small>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                        <button type="button" class="btn btn-outline-primary btn-sm w-full sm:w-auto" @click="test" :disabled="testing">
                            <i class="bi bi-broadcast me-1"></i>
                            <span x-text="testing ? '{{ __('admin.club_details_index_whatsapp_testing') }}' : '{{ __('admin.club_details_index_whatsapp_test') }}'"></span>
                        </button>
                        <button type="button" class="btn btn-primary btn-sm w-full sm:w-auto" @click="save" :disabled="saving">
                            <i class="bi bi-check-lg me-1"></i>
                            <span x-text="saving ? '{{ __('admin.club_details_index_whatsapp_saving') }}' : '{{ __('admin.club_details_index_whatsapp_save') }}'"></span>
                        </button>
                    </div>

                    <div class="border-top pt-3">
                        <label class="form-label">{{ __('admin.club_details_index_whatsapp_send_test_label') }}</label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="text" x-model="testPhone" class="form-control" placeholder="{{ __('admin.club_details_index_whatsapp_send_test_placeholder') }}">
                            <button type="button" class="btn btn-outline-primary btn-sm w-full sm:w-auto flex-shrink-0" @click="sendTest" :disabled="sendingTest || !testPhone">
                                <i class="bi bi-send me-1"></i>
                                <span x-text="sendingTest ? '{{ __('admin.club_details_index_whatsapp_sending_test') }}' : '{{ __('admin.club_details_index_whatsapp_send_test') }}'"></span>
                            </button>
                        </div>
                        <small class="text-muted">{{ __('admin.club_details_index_whatsapp_send_test_hint') }}</small>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="bg-white rounded-xl shadow-sm border border-red-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-red-200 bg-red-50">
                    <h5 class="flex items-center gap-2 text-sm font-semibold text-red-600 m-0">
                        <i class="bi bi-exclamation-triangle"></i>{{ __('admin.club_details_index_danger_zone') }}
                    </h5>
                </div>
                <div class="p-6">
                    <p class="text-sm text-muted-foreground mb-3">{{ __('admin.club_details_index_delete_intro') }}</p>
                    <ul class="text-sm text-muted-foreground space-y-1 mb-4 list-disc list-inside">
                        <li>{{ __('admin.club_details_index_delete_item_info') }}</li>
                        <li>{{ __('admin.club_details_index_delete_item_facilities') }}</li>
                        <li>{{ __('admin.club_details_index_delete_item_packages') }}</li>
                        <li>{{ __('admin.club_details_index_delete_item_files') }}</li>
                        <li>{{ __('admin.club_details_index_delete_item_reviews') }}</li>
                    </ul>
                    <button type="button" class="bg-destructive text-white px-4 py-2 rounded-lg hover:bg-destructive/90 transition-colors font-medium inline-flex items-center gap-2" @click="showDeleteClubModal = true">
                        <i class="bi bi-trash"></i>{{ __('admin.club_details_index_delete_this_club') }}
                    </button>
                </div>
            </div>
        </div>
    </form>

<!-- Transfer Ownership Modal -->
<div x-show="showOwnerModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="showOwnerModal = false"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-lg relative rounded-lg overflow-hidden" @click.stop>
            <div class="modal-header border-b px-6 py-4 flex items-center justify-between">
                <h5 class="modal-title font-semibold"><i class="bi bi-person-gear me-2"></i>{{ __('admin.club_details_index_transfer_ownership') }}</h5>
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="showOwnerModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body px-6 py-4">

                {{-- Tabs --}}
                <div class="flex border-b mb-4">
                    <button type="button"
                            class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                            :class="ownerTab === 'existing' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
                            @click="ownerTab = 'existing'">
                        <i class="bi bi-search me-1"></i>{{ __('admin.club_details_index_link_existing_member') }}
                    </button>
                    <button type="button"
                            class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                            :class="ownerTab === 'new' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'"
                            @click="ownerTab = 'new'">
                        <i class="bi bi-person-plus me-1"></i>{{ __('admin.club_details_index_create_new_member') }}
                    </button>
                </div>

                {{-- Tab: Link Existing --}}
                <div x-show="ownerTab === 'existing'">
                    <p class="text-sm text-muted-foreground mb-3">{{ __('admin.club_details_index_transfer_existing_help') }}</p>
                    <div class="relative mb-3">
                        <input type="text" id="ownerSearchInput" placeholder="{{ __('admin.club_details_index_owner_search_placeholder') }}"
                               autocomplete="new-password"
                               class="form-control" style="padding-inline-start: 2.25rem;">
                        <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm pointer-events-none"></i>
                    </div>
                    <div id="ownerSearchResults" class="space-y-2 max-h-60 overflow-y-auto"></div>
                    <div id="ownerSelectedUser" class="hidden mt-3 p-3 border border-primary/40 bg-primary/5 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full overflow-hidden shrink-0" id="ownerSelectedAvatar">
                                <div class="w-full h-full bg-primary/20 flex items-center justify-center font-bold text-primary" id="ownerSelectedInitial"></div>
                            </div>
                            <div>
                                <div class="font-semibold text-sm" id="ownerSelectedName"></div>
                                <div class="text-xs text-muted-foreground" id="ownerSelectedEmail"></div>
                            </div>
                            <button type="button" class="ms-auto text-muted-foreground hover:text-destructive" onclick="clearOwnerSelection()">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" id="ownerSelectedUserId">
                </div>

                {{-- Tab: Create New --}}
                <div x-show="ownerTab === 'new'">
                    <p class="text-sm text-muted-foreground mb-3">{{ __('admin.club_details_index_transfer_new_help') }}</p>
                    <div class="text-center py-4">
                        <i class="bi bi-person-plus text-primary" style="font-size:2.5rem;"></i>
                        <p class="text-sm text-muted-foreground mt-2 mb-4">{{ __('admin.club_details_index_transfer_new_instruction') }}</p>
                        <button type="button" class="btn btn-primary"
                                @click="showOwnerModal = false; $dispatch('open-create-owner-modal')">
                            <i class="bi bi-person-plus me-2"></i>{{ __('admin.club_details_index_open_registration_form') }}
                        </button>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-t px-6 py-4 flex justify-end gap-3">
                <button type="button" class="btn btn-secondary" @click="showOwnerModal = false">{{ __('shared.cancel') }}</button>
                <button type="button" class="btn btn-primary" id="confirmTransferBtn" onclick="confirmOwnerTransfer()">
                    <i class="bi bi-check-lg me-1"></i>{{ __('admin.club_details_index_confirm_transfer') }}
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Club Modal -->
<div x-show="showDeleteClubModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <div class="fixed inset-0 bg-black/50" @click="showDeleteClubModal = false"></div>

    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-md relative rounded-lg overflow-hidden" @click.stop>
            <div class="modal-header border-b border-destructive/30 px-6 py-4">
                <h5 class="modal-title text-destructive font-semibold">
                    <i class="bi bi-exclamation-triangle me-2"></i>{{ __('admin.club_details_index_delete_club_title') }}
                </h5>
                <button type="button" class="text-muted-foreground hover:text-foreground" @click="showDeleteClubModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <form action="{{ route('admin.club.destroy', $club->slug) }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-body px-6 py-4">
                    <div class="alert alert-danger mb-4">
                        <strong>{{ __('admin.club_details_index_warning_label') }}</strong>{{ __('admin.club_details_index_action_cannot_be_undone') }}
                    </div>
                    <p class="mb-3">{{ __('admin.club_details_index_confirm_delete_prompt') }} <strong>{{ $club->club_name }}</strong></p>
                    <input type="text" class="form-control" id="confirmClubName" placeholder="{{ __('admin.club_details_index_confirm_delete_placeholder') }}" required>
                </div>
                <div class="modal-footer border-t border-border px-6 py-4 flex justify-end gap-3">
                    <button type="button" class="btn btn-secondary" @click="showDeleteClubModal = false">{{ __('shared.cancel') }}</button>
                    <button type="submit" class="btn btn-danger" id="confirmDeleteBtn" disabled>
                        <i class="bi bi-trash me-1"></i>{{ __('admin.club_details_index_delete_permanently') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

{{-- Create Owner Modal --}}
<x-profile-modal
    mode="create"
    :title="__('admin.club_details_index_create_new_owner')"
    :subtitle="__('admin.club_details_index_create_owner_subtitle')"
    :showPasswordFields="true"
    :formAction="route('admin.club.create-owner', $club->slug)"
    formMethod="POST"
    eventName="open-create-owner-modal"
/>

{{-- Styles moved to app.css (Phase 6) --}}

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching is handled by Alpine (activeTab) on the wrapper.

    // Delete club confirmation
    const confirmInput = document.getElementById('confirmClubName');
    const deleteBtn = document.getElementById('confirmDeleteBtn');
    const clubName = '{{ $club->club_name }}';

    if (confirmInput && deleteBtn) {
        confirmInput.addEventListener('input', function() {
            deleteBtn.disabled = this.value !== clubName;
        });
    }

    // The map auto-initialises itself (see the location-map component); we
    // only need its id for the "Use My Location" helper below.
    const DETAILS_MAP_ID = 'clubDetailsLoc';

    // Use my location button
    const useLocationBtn = document.getElementById('useMyLocationBtn');
    if (useLocationBtn) {
        useLocationBtn.addEventListener('click', function() {
            if (!navigator.geolocation) {
                window.showToast('error', '{{ __('admin.club_details_index_js_geolocation_unsupported') }}');
                return;
            }
            const btn = this;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>{{ __('admin.club_details_index_getting_location') }}';

            navigator.geolocation.getCurrentPosition(function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                if (window.LocationMap) {
                    window.LocationMap.setPosition(DETAILS_MAP_ID, lat, lng);
                    const inst = window.LocationMap.get(DETAILS_MAP_ID);
                    if (inst) inst.map.setView([lat, lng], 15);
                }
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }, function(error) {
                window.showToast('error', '{{ __('admin.club_details_index_js_location_error') }}');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
        });
    }

    // (Club QR codes are now rendered offline via the x-qr-code component above.)
});

// ===== Owner Transfer =====
let ownerSearchDebounce = null;
let selectedOwnerId = null;

document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('ownerSearchInput')?.addEventListener('input', function () {
        clearTimeout(ownerSearchDebounce);
        const q = this.value.trim();
        if (q.length < 2) {
            document.getElementById('ownerSearchResults').innerHTML = '';
            return;
        }
        ownerSearchDebounce = setTimeout(() => searchOwnerUsers(q), 300);
    });
});

function searchOwnerUsers(q) {
    fetch(`{{ route('admin.club.members.search', $club->slug) }}?query=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('ownerSearchResults');
            container.innerHTML = '';
            const users = data.users || [];
            if (!users.length) {
                container.innerHTML = '<p class="text-sm text-muted-foreground text-center py-3">{{ __('admin.club_details_index_js_no_members_found') }}</p>';
                return;
            }
            users.forEach(user => {
                const div = document.createElement('div');
                div.className = 'flex items-center gap-3 p-2.5 border rounded-lg cursor-pointer hover:border-primary/50 hover:bg-primary/5 transition-colors';
                const initial = (user.name || '?').charAt(0).toUpperCase();
                const avatar = user.profile_picture
                    ? `<img src="${user.profile_picture}" class="w-9 h-9 rounded-full object-cover shrink-0" onerror="this.outerHTML='<div class=\'w-9 h-9 rounded-full bg-primary/20 flex items-center justify-center font-bold text-primary text-sm shrink-0\'>${initial}</div>'">`
                    : `<div class="w-9 h-9 rounded-full bg-primary/20 flex items-center justify-center font-bold text-primary text-sm shrink-0">${initial}</div>`;
                div.innerHTML = `
                    ${avatar}
                    <div class="min-w-0">
                        <div class="font-medium text-sm truncate">${user.name}</div>
                        <div class="text-xs text-muted-foreground truncate">${user.email || ''}</div>
                    </div>`;
                div.addEventListener('click', () => selectOwnerUser(user));
                container.appendChild(div);
            });
        });
}

function selectOwnerUser(user) {
    selectedOwnerId = user.id;
    document.getElementById('ownerSelectedUserId').value = user.id;
    document.getElementById('ownerSelectedName').textContent = user.name;
    document.getElementById('ownerSelectedEmail').textContent = user.email || '';
    const avatarEl = document.getElementById('ownerSelectedAvatar');
    const initial = (user.name || '?').charAt(0).toUpperCase();
    if (user.profile_picture) {
        avatarEl.innerHTML = `<img src="${user.profile_picture}" class="w-full h-full object-cover" onerror="this.parentElement.innerHTML='<div class=\'w-full h-full bg-primary/20 flex items-center justify-center font-bold text-primary\'>${initial}</div>'">`;
    } else {
        document.getElementById('ownerSelectedInitial').textContent = initial;
    }
    document.getElementById('ownerSelectedUser').classList.remove('hidden');
    document.getElementById('ownerSearchResults').innerHTML = '';
    document.getElementById('ownerSearchInput').value = '';
}

function clearOwnerSelection() {
    selectedOwnerId = null;
    document.getElementById('ownerSelectedUserId').value = '';
    document.getElementById('ownerSelectedUser').classList.add('hidden');
}

function confirmOwnerTransfer() {
    const tab = document.querySelector('[x-data]').__x?.$data?.ownerTab
               || (document.querySelector('.border-primary.text-primary')?.textContent?.includes('Link') ? 'existing' : 'new');

    const btn = document.getElementById('confirmTransferBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>{{ __('admin.club_details_index_js_transferring') }}';

    let body = { _token: '{{ csrf_token() }}' };

    if (tab === 'existing') {
        const userId = document.getElementById('ownerSelectedUserId').value;
        if (!userId) {
            window.showToast('error', '{{ __('admin.club_details_index_js_select_member') }}');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>{{ __('admin.club_details_index_confirm_transfer') }}';
            return;
        }
        body.mode = 'existing';
        body.user_id = userId;
    } else {
        const name     = document.getElementById('newOwnerName').value.trim();
        const email    = document.getElementById('newOwnerEmail').value.trim();
        const password = document.getElementById('newOwnerPassword').value;
        if (!name || !email || !password) {
            window.showToast('error', '{{ __('admin.club_details_index_js_fill_all_fields') }}');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>{{ __('admin.club_details_index_confirm_transfer') }}';
            return;
        }
        body.mode      = 'new';
        body.full_name = name;
        body.email     = email;
        body.password  = password;
    }

    fetch('{{ route('admin.club.transfer-ownership', $club->slug) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(body),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Regenerate the owner section in place — no reload. Handles both the
            // "no owner yet" → assigned transition and owner → owner replacement.
            const section = document.getElementById('ownerSection');
            if (section && data.owner) {
                const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
                let inner = '<h6 class="mb-1">' + esc(data.owner.name) + '</h6>';
                if (data.owner.email) {
                    inner += '<p class="text-muted small mb-1"><i class="bi bi-envelope me-1"></i>' + esc(data.owner.email) + '</p>';
                }
                if (data.owner.mobile) {
                    inner += '<p class="text-muted small mb-0"><i class="bi bi-phone me-1"></i>' + esc(data.owner.mobile) + '</p>';
                }
                section.innerHTML =
                    '<div class="card bg-light"><div class="card-body">' +
                        '<div class="flex justify-between items-start">' +
                            '<div id="ownerCardInfo">' + inner + '</div>' +
                            '<button type="button" class="btn btn-sm btn-outline-secondary" @click="showOwnerModal = true"><i class="bi bi-pencil"></i></button>' +
                        '</div>' +
                    '</div></div>';
            }

            // Close modal (Alpine 3 scope) — no reload.
            try { if (window.Alpine) window.Alpine.$data(btn).showOwnerModal = false; } catch (e) {}

            window.showToast('success', data.message || '{{ __('admin.club_details_index_js_transfer_success') }}');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>{{ __('admin.club_details_index_confirm_transfer') }}';
        } else {
            window.showToast('error', data.message || '{{ __('admin.club_details_index_js_something_wrong') }}');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>{{ __('admin.club_details_index_confirm_transfer') }}';
        }
    })
    .catch(() => {
        window.showToast('error', '{{ __('admin.club_details_index_js_network_error') }}');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>{{ __('admin.club_details_index_confirm_transfer') }}';
    });
}

// Copy club URL
function copyClubUrl() {
    const url = '{{ $club->slug && $club->country ? route("clubs.show", [strtolower($club->country), $club->slug]) : "" }}';
    if (url) {
        navigator.clipboard.writeText(url).then(function() {
            window.showToast('success', '{{ __('admin.club_details_index_js_url_copied') }}');
        });
    }
}

function clubWhatsAppIntegration() {
    return {
        saving: false,
        testing: false,
        sendingTest: false,
        testPhone: '',
        form: {
            enabled:      @json($whatsappSettings['enabled']),
            session_name: @json($whatsappSettings['session_name']),
        },

        async save() {
            this.saving = true;
            try {
                const res = await fetch('{{ route('admin.club.settings.whatsapp.update', $club->slug) }}', {
                    method: 'PUT',
                    headers: this._headers(),
                    body: JSON.stringify(this.form),
                });
                const data = await res.json();
                if (data.success) {
                    window.showToast('success', data.message);
                } else {
                    window.showToast('error', data.message || 'Could not save settings.');
                }
            } catch (e) {
                window.showToast('error', 'Network error while saving.');
            } finally {
                this.saving = false;
            }
        },

        async test() {
            this.testing = true;
            try {
                const res = await fetch('{{ route('admin.club.settings.whatsapp.test', $club->slug) }}', {
                    method: 'POST',
                    headers: this._headers(),
                });
                const data = await res.json();
                window.showToast(data.success ? 'success' : 'error', data.message);
            } catch (e) {
                window.showToast('error', 'Network error during test.');
            } finally {
                this.testing = false;
            }
        },

        async sendTest() {
            if (!this.testPhone) return;
            this.sendingTest = true;
            try {
                const res = await fetch('{{ route('admin.club.settings.whatsapp.send-test', $club->slug) }}', {
                    method: 'POST',
                    headers: this._headers(),
                    body: JSON.stringify({ phone: this.testPhone }),
                });
                const data = await res.json();
                window.showToast(data.success ? 'success' : 'error', data.message);
            } catch (e) {
                window.showToast('error', 'Network error while sending.');
            } finally {
                this.sendingTest = false;
            }
        },

        _headers() {
            return {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            };
        },
    };
}
</script>
@endpush
@endsection
