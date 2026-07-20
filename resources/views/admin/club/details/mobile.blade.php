@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_details'))

@section('club-admin-content')
@php
    $settings   = $club->settings ?? [];
    $phoneCode  = old('phone_code',   $club->phone['code']   ?? '+973');
    $phoneNum   = old('phone_number', $club->phone['number'] ?? '');
    $reqAr      = data_get($club->translations, 'registration_requirements.ar', '');
    $termsAr    = data_get($club->translations, 'registration_terms.ar', '');
    $logoUrl    = $club->logo        ? asset('storage/'.$club->logo)        : '';
    $coverUrl   = $club->cover_image ? asset('storage/'.$club->cover_image) : '';
    $faviconUrl = $club->favicon     ? asset('storage/'.$club->favicon)     : '';
    $splashUrl  = $club->registration_splash_image ? asset('storage/'.$club->registration_splash_image) : '';
    $publicUrl  = ($club->slug && $club->country) ? route('clubs.show', [strtolower($club->country), $club->slug]) : '';
    $socialCount = $club->socialLinks->count();

    // Profile-strength score — drives the ring in the hero and the per-section dots.
    $checks = [
        'identity'     => filled($club->club_name) && filled($club->description),
        'slogan'       => filled($club->slogan),
        'branding'     => filled($club->logo) && filled($club->cover_image),
        'contact'      => filled($club->email) && filled($phoneNum),
        'region'       => filled($club->country) && filled($club->currency),
        'web'          => filled($club->slug),
        'location'     => filled($club->address) && filled($club->gps_lat) && filled($club->gps_long),
        'social'       => $socialCount > 0,
        'requirements' => filled($club->registration_requirements),
        'terms'        => filled($club->registration_terms),
        'owner'        => (bool) $club->owner,
    ];
    $doneCount = count(array_filter($checks));
    $pct       = (int) round($doneCount / max(count($checks), 1) * 100);
    $ringR     = 26;
    $ringC     = 2 * M_PI * $ringR;

    // Hub rows. `done` drives the status dot; `part` is the panel key.
    $sections = [
        ['part'=>'identity',     'icon'=>'bi-card-heading',     'tint'=>'bg-primary/10 text-primary',      'title'=>__('admin.cs_sec_identity'),     'sub'=>__('admin.cs_sec_identity_sub'),     'done'=>$checks['identity']],
        ['part'=>'branding',     'icon'=>'bi-palette',          'tint'=>'bg-fuchsia-100 text-fuchsia-600', 'title'=>__('admin.cs_sec_branding'),     'sub'=>__('admin.cs_sec_branding_sub'),     'done'=>$checks['branding']],
        ['part'=>'contact',      'icon'=>'bi-telephone',        'tint'=>'bg-sky-100 text-sky-600',         'title'=>__('admin.cs_sec_contact'),      'sub'=>__('admin.cs_sec_contact_sub'),      'done'=>$checks['contact']],
        ['part'=>'location',     'icon'=>'bi-geo-alt',          'tint'=>'bg-rose-100 text-rose-600',       'title'=>__('admin.cs_sec_location'),     'sub'=>__('admin.cs_sec_location_sub'),     'done'=>$checks['location']],
        ['part'=>'money',        'icon'=>'bi-cash-coin',        'tint'=>'bg-emerald-100 text-emerald-600', 'title'=>__('admin.cs_sec_money'),        'sub'=>__('admin.cs_sec_money_sub'),        'done'=>filled($club->registration_fee)],
        ['part'=>'web',          'icon'=>'bi-link-45deg',       'tint'=>'bg-indigo-100 text-indigo-600',   'title'=>__('admin.cs_sec_web'),          'sub'=>__('admin.cs_sec_web_sub'),          'done'=>$checks['web']],
        ['part'=>'registration', 'icon'=>'bi-clipboard-check',  'tint'=>'bg-amber-100 text-amber-600',     'title'=>__('admin.cs_sec_registration'), 'sub'=>__('admin.cs_sec_registration_sub'), 'done'=>$checks['requirements'] && $checks['terms']],
        ['part'=>'social',       'icon'=>'bi-share',            'tint'=>'bg-cyan-100 text-cyan-600',       'title'=>__('admin.cs_sec_social'),       'sub'=>__('admin.cs_sec_social_sub'),       'done'=>$checks['social']],
        ['part'=>'codes',        'icon'=>'bi-hash',             'tint'=>'bg-slate-100 text-slate-600',     'title'=>__('admin.cs_sec_codes'),        'sub'=>__('admin.cs_sec_codes_sub'),        'done'=>true],
    ];
@endphp

{{-- Local styles. Inline (not @push) so they survive mobile-shell AJAX navigation. --}}
<style>
    .cs-lb   { display:block; font-size:.8125rem; font-weight:600; color:hsl(var(--foreground)); margin-bottom:.375rem; }
    .cs-in   { width:100%; padding:.7rem .875rem; font-size:.9375rem; background:#fff; border:1px solid hsl(210 14% 88%); border-radius:.75rem; transition:border-color .15s, box-shadow .15s; }
    .cs-in:focus { outline:none; border-color:hsl(var(--primary)); box-shadow:0 0 0 3px hsl(250 65% 65% / .18); }
    .cs-hint { display:block; font-size:.6875rem; color:hsl(var(--muted-foreground)); margin-top:.375rem; line-height:1.5; }
    .cs-row  { display:flex; align-items:center; gap:.875rem; width:100%; padding:.875rem; text-align:start; }
    /* The shared form components inside the panels render Bootstrap-ish classes;
       give them the same rounded mobile treatment as .cs-in. */
    #clubStudio .form-control, #clubStudio select.form-control {
        width:100%; padding:.7rem .875rem; font-size:.9375rem; background:#fff;
        border:1px solid hsl(210 14% 88%); border-radius:.75rem;
    }
    #clubStudio .form-control:focus { outline:none; border-color:hsl(var(--primary)); box-shadow:0 0 0 3px hsl(250 65% 65% / .18); }
    #clubStudio .form-label { display:block; font-size:.8125rem; font-weight:600; margin-bottom:.375rem; }
</style>

<div id="clubStudio" class="-mx-4 -mt-4"
     x-data="clubStudio()" x-init="init()">

    {{-- ══════════════ Hero — live preview of the club's public identity ══════════════ --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        {{-- Cover image bleeds behind the gradient and updates live when a new one is picked --}}
        <template x-if="cover">
            <div class="absolute inset-0 bg-center bg-cover opacity-30" :style="`background-image:url(${cover})`"></div>
        </template>
        <div class="absolute -end-10 -top-10 w-40 h-40 rounded-full bg-white/10"></div>

        <div class="relative z-10">
            <div class="flex items-start gap-4">
                {{-- Logo (transparent PNG — no white tile, per design rules) --}}
                <span class="w-16 h-16 flex-shrink-0 grid place-items-center">
                    <template x-if="logo">
                        <img :src="logo" alt="" class="w-full h-full object-contain drop-shadow-lg">
                    </template>
                    <template x-if="!logo">
                        <span class="w-16 h-16 rounded-2xl bg-white/20 border border-white/25 backdrop-blur grid place-items-center font-black text-xl">{{ mb_strtoupper(mb_substr($club->club_name ?? 'CL', 0, 2, 'UTF-8'), 'UTF-8') }}</span>
                    </template>
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ __('admin.cs_eyebrow') }}</p>
                    <h1 class="text-xl font-black leading-tight mt-0.5 break-words" x-text="name || @js($club->club_name)"></h1>
                    <p class="text-xs text-white/75 mt-0.5 break-words" x-text="slogan"></p>
                </div>

                {{-- Profile-strength ring --}}
                <div class="relative w-16 h-16 flex-shrink-0 grid place-items-center" role="img"
                     aria-label="{{ $pct }}% {{ __('admin.cs_complete') }}">
                    <svg class="w-16 h-16 -rotate-90" viewBox="0 0 64 64" aria-hidden="true">
                        <circle cx="32" cy="32" r="{{ $ringR }}" fill="none" stroke="rgba(255,255,255,.25)" stroke-width="5"></circle>
                        <circle cx="32" cy="32" r="{{ $ringR }}" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round"
                                stroke-dasharray="{{ round($ringC, 2) }}"
                                stroke-dashoffset="{{ round($ringC * (1 - $pct / 100), 2) }}"
                                style="transition: stroke-dashoffset .9s cubic-bezier(.22,.61,.36,1);"></circle>
                    </svg>
                    <span class="absolute text-[13px] font-black">{{ $pct }}<span class="text-[9px] font-bold">%</span></span>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 mt-4">
                <div class="flex items-center gap-2 text-[11px] text-white/80">
                    <i class="bi bi-star-fill text-amber-300"></i>
                    <span class="font-semibold text-white">{{ number_format($averageRating ?? 0, 1) }}</span>
                    <span>· {{ $reviews->count() }} {{ __('admin.det_reviews') }}</span>
                    <span>· {{ $activeMembersCount ?? 0 }} {{ __('admin.det_active_members') }}</span>
                </div>
                @if($publicUrl)
                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener"
                       class="m-press w-10 h-10 rounded-xl bg-white/20 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0"
                       aria-label="{{ __('admin.preview_club_page') }}">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                @endif
            </div>
        </div>
    </header>

    {{-- ══════════════ Validation errors ══════════════ --}}
    @if($errors->any())
        <div class="mx-4 mt-4 rounded-2xl border border-red-200 bg-red-50 p-4">
            <p class="text-sm font-semibold text-red-700 flex items-center gap-2"><i class="bi bi-exclamation-triangle"></i>{{ __('admin.club_details_index_warning_label') }}</p>
            <ul class="mt-2 space-y-1 text-xs text-red-600 list-disc list-inside">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- ══════════════ HUB — the section index (visible when no panel is open) ══════════════ --}}
    <div x-show="panel === null" class="px-4 pt-5 pb-6 m-panel-in">

        {{-- Save banner: only appears once something actually changed --}}
        <div x-show="dirty" x-cloak
             class="mb-4 rounded-2xl bg-amber-50 border border-amber-200 p-3 flex items-center gap-3">
            <i class="bi bi-exclamation-circle text-amber-600 text-lg"></i>
            <span class="text-xs font-medium text-amber-800 flex-1">{{ __('admin.cs_unsaved') }}</span>
            <button type="submit" form="clubStudioForm"
                    class="m-press px-3.5 py-2 rounded-xl bg-primary text-white text-xs font-semibold">{{ __('admin.cs_save') }}</button>
        </div>

        <div class="space-y-2.5 mobile-stagger">
            @foreach($sections as $s)
                <button type="button" @click="open('{{ $s['part'] }}')"
                        class="m-card m-press cs-row block">
                    <span class="w-11 h-11 rounded-xl grid place-items-center flex-shrink-0 {{ $s['tint'] }}">
                        <i class="bi {{ $s['icon'] }} text-lg"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center gap-2">
                            <span class="font-semibold text-foreground text-sm truncate">{{ $s['title'] }}</span>
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $s['done'] ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                        </span>
                        <span class="block text-[11px] text-muted-foreground truncate mt-0.5">{{ $s['sub'] }}</span>
                    </span>
                    <i class="bi bi-chevron-right text-muted-foreground text-sm flex-shrink-0 rtl:rotate-180"></i>
                </button>
            @endforeach

            {{-- Sections that save on their own (not part of the main form) --}}
            <p class="px-2 pt-4 pb-1 text-[10px] font-bold uppercase tracking-wide text-muted-foreground">{{ __('admin.nav_group_settings') }}</p>

            <button type="button" @click="open('whatsapp')" class="m-card m-press cs-row block">
                <span class="w-11 h-11 rounded-xl grid place-items-center flex-shrink-0 bg-green-100 text-green-600"><i class="bi bi-whatsapp text-lg"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <span class="font-semibold text-foreground text-sm truncate">{{ __('admin.cs_sec_whatsapp') }}</span>
                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ !empty($whatsappSettings['enabled']) ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                    </span>
                    <span class="block text-[11px] text-muted-foreground truncate mt-0.5">{{ __('admin.cs_sec_whatsapp_sub') }}</span>
                </span>
                <i class="bi bi-chevron-right text-muted-foreground text-sm flex-shrink-0 rtl:rotate-180"></i>
            </button>

            <button type="button" @click="open('owner')" class="m-card m-press cs-row block">
                <span class="w-11 h-11 rounded-xl grid place-items-center flex-shrink-0 bg-violet-100 text-violet-600"><i class="bi bi-person-badge text-lg"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <span class="font-semibold text-foreground text-sm truncate">{{ __('admin.cs_sec_owner') }}</span>
                        <span class="w-1.5 h-1.5 rounded-full flex-shrink-0 {{ $club->owner ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                    </span>
                    <span class="block text-[11px] text-muted-foreground truncate mt-0.5">{{ $club->owner->full_name ?? __('admin.cs_no_owner') }}</span>
                </span>
                <i class="bi bi-chevron-right text-muted-foreground text-sm flex-shrink-0 rtl:rotate-180"></i>
            </button>

            <button type="button" @click="open('danger')" class="m-card m-press cs-row block border-red-200">
                <span class="w-11 h-11 rounded-xl grid place-items-center flex-shrink-0 bg-red-100 text-red-600"><i class="bi bi-exclamation-triangle text-lg"></i></span>
                <span class="min-w-0 flex-1">
                    <span class="font-semibold text-red-600 text-sm block truncate">{{ __('admin.cs_sec_danger') }}</span>
                    <span class="block text-[11px] text-muted-foreground truncate mt-0.5">{{ __('admin.cs_sec_danger_sub') }}</span>
                </span>
                <i class="bi bi-chevron-right text-muted-foreground text-sm flex-shrink-0 rtl:rotate-180"></i>
            </button>
        </div>

        {{-- Recent reviews — read-only context, kept from the old mobile page --}}
        @if($reviews->isNotEmpty())
            <div class="m-card p-4 mt-5">
                <h3 class="font-semibold text-foreground mb-3 text-sm">{{ __('admin.det_recent_reviews') }}</h3>
                <div class="space-y-3">
                    @foreach($reviews->take(5) as $r)
                        <div class="border-b border-gray-50 last:border-0 pb-3 last:pb-0">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium text-foreground truncate">{{ $r->user->full_name ?? __('admin.det_member') }}</span>
                                <span class="text-xs text-amber-500 flex-shrink-0">@for($i=0;$i<5;$i++)<i class="bi bi-star{{ $i < ($r->rating ?? 0) ? '-fill' : '' }}"></i>@endfor</span>
                            </div>
                            @if($r->comment)<p class="text-xs text-muted-foreground mt-1">{{ $r->comment }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- ══════════════ PANEL CHROME — sticky header shown whenever a panel is open ══════════════ --}}
    <div x-show="panel !== null" x-cloak
         class="sticky top-0 z-30 bg-background/95 backdrop-blur border-b border-border px-3 py-2.5 flex items-center gap-2">
        <button type="button" @click="close()" class="m-press w-10 h-10 rounded-xl bg-white border border-border grid place-items-center flex-shrink-0" aria-label="{{ __('admin.cs_back') }}">
            <i class="bi bi-chevron-left rtl:rotate-180"></i>
        </button>
        <span class="font-bold text-foreground text-sm truncate flex-1" x-text="title"></span>
        <template x-if="panelUsesForm">
            <button type="submit" form="clubStudioForm"
                    class="m-press px-4 py-2 rounded-xl bg-primary text-white text-xs font-semibold flex items-center gap-1.5 flex-shrink-0">
                <span x-show="dirty" class="w-1.5 h-1.5 rounded-full bg-white/90"></span>
                {{ __('admin.cs_save') }}
            </button>
        </template>
    </div>

    {{-- ══════════════ MAIN FORM — every panel below saves together ══════════════ --}}
    <form id="clubStudioForm" action="{{ route('admin.club.update', $club->slug) }}" method="POST"
          enctype="multipart/form-data" @input="dirty = true" @change="dirty = true">
        @csrf
        @method('PUT')

        {{-- ─────────── Identity ─────────── --}}
        <section x-show="panel === 'identity'" x-cloak class="px-4 py-5 space-y-4 m-panel-in">
            <div class="flex justify-end"><x-lang-toggle /></div>

            <div class="m-card p-4 space-y-4">
                <div>
                    <label class="cs-lb">{{ __('admin.club_details_index_club_name_label') }} <span class="text-destructive">*</span></label>
                    <input type="text" name="club_name" class="cs-in" x-model="name" x-show="lang==='en'" required
                           value="{{ old('club_name', $club->club_name) }}">
                    <input type="text" name="translations[club_name][ar]" dir="rtl" x-show="lang==='ar'" x-cloak class="cs-in"
                           placeholder="اسم النادي بالعربية"
                           value="{{ old('translations.club_name.ar', data_get($club->translations, 'club_name.ar')) }}">
                </div>

                <div>
                    <label class="cs-lb">{{ __('admin.club_details_index_slogan_label') }}</label>
                    <input type="text" name="slogan" class="cs-in" x-model="slogan" x-show="lang==='en'"
                           placeholder="{{ __('admin.club_details_index_slogan_placeholder') }}"
                           value="{{ old('slogan', $club->slogan) }}">
                    <input type="text" name="translations[slogan][ar]" dir="rtl" x-show="lang==='ar'" x-cloak class="cs-in"
                           placeholder="شعار النادي بالعربية"
                           value="{{ old('translations.slogan.ar', data_get($club->translations, 'slogan.ar')) }}">
                </div>

                <div>
                    <label class="cs-lb">{{ __('admin.club_details_index_description_label') }}</label>
                    <textarea name="description" rows="4" class="cs-in" x-show="lang==='en'"
                              placeholder="{{ __('admin.club_details_index_description_placeholder') }}">{{ old('description', $club->description) }}</textarea>
                    <textarea name="translations[description][ar]" dir="rtl" rows="4" x-show="lang==='ar'" x-cloak class="cs-in"
                              placeholder="وصف النادي بالعربية">{{ old('translations.description.ar', data_get($club->translations, 'description.ar')) }}</textarea>
                </div>
            </div>

            <div class="pb-2"><button type="submit" class="m-press w-full py-3.5 rounded-2xl bg-primary text-white font-semibold text-sm">{{ __('admin.cs_save_all') }}</button></div>
        </section>

        {{-- ─────────── Branding ─────────── --}}
        <section x-show="panel === 'branding'" x-cloak class="px-4 py-5 space-y-4 m-panel-in">

            {{-- Cover: big tappable canvas with live preview --}}
            <div class="m-card overflow-hidden">
                <label class="block cursor-pointer">
                    <span class="block relative h-40 bg-muted"
                          :style="cover ? `background-image:url(${cover});background-size:cover;background-position:center` : ''">
                        <span class="absolute inset-0 grid place-items-center bg-black/35 text-white">
                            <span class="text-center">
                                <i class="bi bi-image text-2xl"></i>
                                <span class="block text-xs font-semibold mt-1">{{ __('admin.cs_cover') }}</span>
                                <span class="block text-[10px] text-white/75">{{ __('admin.cs_tap_to_change') }}</span>
                            </span>
                        </span>
                    </span>
                    <input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden"
                           @change="preview($event, 'cover')">
                </label>
                <p class="cs-hint px-4 pb-3">{{ __('admin.club_details_index_cover_recommendation') }}</p>
            </div>

            {{-- Logo + favicon side by side --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="m-card p-4 text-center">
                    <label class="block cursor-pointer">
                        <span class="w-20 h-20 mx-auto grid place-items-center">
                            <template x-if="logo"><img :src="logo" alt="" class="w-full h-full object-contain"></template>
                            <template x-if="!logo"><span class="w-20 h-20 rounded-2xl bg-muted grid place-items-center text-muted-foreground"><i class="bi bi-shield text-2xl"></i></span></template>
                        </span>
                        <span class="block text-xs font-semibold text-foreground mt-2">{{ __('admin.cs_logo') }}</span>
                        <span class="block text-[10px] text-primary font-medium">{{ __('admin.cs_tap_to_change') }}</span>
                        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden"
                               @change="preview($event, 'logo')">
                    </label>
                </div>
                <div class="m-card p-4 text-center">
                    <label class="block cursor-pointer">
                        <span class="w-20 h-20 mx-auto grid place-items-center">
                            <template x-if="favicon"><img :src="favicon" alt="" class="w-12 h-12 object-contain"></template>
                            <template x-if="!favicon"><span class="w-20 h-20 rounded-2xl bg-muted grid place-items-center text-muted-foreground"><i class="bi bi-app text-2xl"></i></span></template>
                        </span>
                        <span class="block text-xs font-semibold text-foreground mt-2">{{ __('admin.cs_favicon') }}</span>
                        <span class="block text-[10px] text-primary font-medium">{{ __('admin.cs_tap_to_change') }}</span>
                        <input type="file" name="favicon" accept="image/jpeg,image/png,image/webp,image/gif" class="hidden"
                               @change="preview($event, 'favicon')">
                    </label>
                </div>
            </div>

            <div class="pb-2"><button type="submit" class="m-press w-full py-3.5 rounded-2xl bg-primary text-white font-semibold text-sm">{{ __('admin.cs_save_all') }}</button></div>
        </section>

        {{-- ─────────── Contact ─────────── --}}
        <section x-show="panel === 'contact'" x-cloak class="px-4 py-5 space-y-4 m-panel-in">
            <div class="m-card p-4 space-y-4">
                <div>
                    <label class="cs-lb">{{ __('admin.club_details_index_club_email_label') }}</label>
                    <input type="email" name="email" class="cs-in" value="{{ old('email', $club->email) }}"
                           placeholder="{{ __('admin.club_details_index_club_email_placeholder') }}">
                </div>

                <div>
                    <label class="cs-lb">{{ __('admin.club_details_index_club_phone_label') }}</label>
                    <x-country-code-dropdown name="phone_code" id="csPhoneCode" :value="$phoneCode" :error="$errors->first('phone_code')">
                        <input type="text" class="form-control border-0" name="phone_number" value="{{ $phoneNum }}" placeholder="12345678">
                    </x-country-code-dropdown>
                </div>

                <x-country-dropdown name="country" id="csCountry" :value="old('country', $club->country)" :label="__('admin.club_details_index_country_label')" />
                <x-currency-dropdown name="currency" id="csCurrency" :value="old('currency', $club->currency)" :label="__('admin.club_details_index_currency_label')" />
                <x-timezone-dropdown name="timezone" id="csTimezone" :value="old('timezone', $club->timezone)" :label="__('admin.club_details_index_timezone_label')" />
            </div>

            <div class="pb-2"><button type="submit" class="m-press w-full py-3.5 rounded-2xl bg-primary text-white font-semibold text-sm">{{ __('admin.cs_save_all') }}</button></div>
        </section>

        {{-- ─────────── Location ─────────── --}}
        <section x-show="panel === 'location'" x-cloak class="px-4 py-5 space-y-4 m-panel-in">
            <div class="flex justify-end"><x-lang-toggle /></div>

            <div class="m-card p-4 space-y-4">
                <div>
                    <label class="cs-lb">{{ __('admin.cs_address_label') }}</label>
                    <p class="cs-hint mb-2 mt-0">{{ __('admin.cs_pin_hint') }}</p>
                </div>

                <x-location-map
                    id="clubDetailsLoc"
                    :lat="old('gps_lat', $club->gps_lat)"
                    :lng="old('gps_long', $club->gps_long)"
                    :address="old('address', $club->address ?? '')"
                    :addressPlaceholder="__('admin.cs_address_ph')"
                    :showLabels="false"
                    :defaultLat="26.2285"
                    :defaultLng="50.5860"
                    height="220px" />

                <p id="csMapFallback" class="hidden text-xs text-muted-foreground">{{ __('admin.cs_map_unavailable') }}</p>

                <div x-show="lang==='ar'" x-cloak>
                    <label class="cs-lb">العنوان بالعربية</label>
                    <input type="text" name="translations[address][ar]" dir="rtl" class="cs-in" placeholder="عنوان النادي بالعربية"
                           value="{{ old('translations.address.ar', data_get($club->translations, 'address.ar')) }}">
                </div>

                <div class="flex gap-2">
                    <button type="button" id="csUseMyLocation"
                            class="m-press flex-1 py-2.5 rounded-xl border border-primary text-primary text-xs font-semibold flex items-center justify-center gap-1.5">
                        <i class="bi bi-crosshair"></i>{{ __('admin.club_details_index_use_my_location') }}
                    </button>
                    @if($club->gps_lat && $club->gps_long)
                        <a href="https://www.google.com/maps?q={{ $club->gps_lat }},{{ $club->gps_long }}" target="_blank" rel="noopener"
                           class="m-press flex-1 py-2.5 rounded-xl border border-border text-foreground text-xs font-semibold flex items-center justify-center gap-1.5">
                            <i class="bi bi-box-arrow-up-right"></i>{{ __('admin.club_details_index_view_on_google_maps') }}
                        </a>
                    @endif
                </div>

                <div>
                    <label class="cs-lb">{{ __('admin.club_details_index_google_maps_url_label') }}</label>
                    <input type="url" name="maps_url" class="cs-in" placeholder="https://maps.google.com/..." value="{{ old('maps_url', $club->maps_url) }}">
                    <span class="cs-hint">{{ __('admin.club_details_index_google_maps_help') }}</span>
                </div>
            </div>

            <div class="pb-2"><button type="submit" class="m-press w-full py-3.5 rounded-2xl bg-primary text-white font-semibold text-sm">{{ __('admin.cs_save_all') }}</button></div>
        </section>

        {{-- ─────────── Fees & tax ─────────── --}}
        <section x-show="panel === 'money'" x-cloak class="px-4 py-5 space-y-4 m-panel-in">
            <div class="m-card p-4 space-y-4">
                <div>
                    <label class="cs-lb">{{ __('admin.club_details_index_registration_fee_label') }} ({{ $club->currency ?? 'USD' }})</label>
                    <input type="number" step="0.01" name="registration_fee" class="cs-in" placeholder="0.00" value="{{ old('registration_fee', $club->registration_fee) }}">
                    <span class="cs-hint">{{ __('admin.club_details_index_registration_fee_help') }}</span>
                </div>
                <div>
                    <label class="cs-lb">{{ __('admin.club_details_index_enrollment_fee_label') }} ({{ $club->currency ?? 'USD' }})</label>
                    <input type="number" step="0.01" name="enrollment_fee" class="cs-in" placeholder="0.00" value="{{ old('enrollment_fee', $club->enrollment_fee) }}">
                    <span class="cs-hint">{{ __('admin.club_details_index_enrollment_fee_help') }}</span>
                </div>
            </div>

            <div class="m-card p-4 space-y-4">
                <div>
                    <label class="cs-lb">{{ __('admin.club_details_index_commercial_reg_label') }}</label>
                    <input type="text" name="commercial_reg_number" class="cs-in" placeholder="{{ __('admin.club_details_index_commercial_reg_placeholder') }}" value="{{ old('commercial_reg_number', $club->commercial_reg_number) }}">
                    <span class="cs-hint">{{ __('admin.club_details_index_appears_on_receipts') }}</span>
                </div>
                <div>
                    <label class="cs-lb">{{ __('admin.club_details_index_vat_reg_label') }}</label>
                    <input type="text" name="vat_reg_number" class="cs-in" placeholder="{{ __('admin.club_details_index_vat_reg_placeholder') }}" value="{{ old('vat_reg_number', $club->vat_reg_number) }}">
                    <span class="cs-hint">{{ __('admin.club_details_index_appears_on_receipts') }}</span>
                </div>
                <div>
                    <label class="cs-lb">{{ __('admin.club_details_index_vat_percentage_label') }}</label>
                    <input type="number" step="0.01" name="vat_percentage" class="cs-in" placeholder="0.00" value="{{ old('vat_percentage', $club->vat_percentage) }}">
                    <span class="cs-hint">{{ __('admin.club_details_index_vat_percentage_help') }}</span>
                    <div class="mt-2 rounded-xl bg-amber-50 border border-amber-200 p-2.5 text-[11px] text-amber-800 flex gap-2">
                        <i class="bi bi-exclamation-triangle flex-shrink-0 mt-0.5"></i><span>{{ __('admin.club_details_index_vat_rate_warning') }}</span>
                    </div>
                </div>
            </div>

            <div class="pb-2"><button type="submit" class="m-press w-full py-3.5 rounded-2xl bg-primary text-white font-semibold text-sm">{{ __('admin.cs_save_all') }}</button></div>
        </section>

        {{-- ─────────── Public link & QR ─────────── --}}
        <section x-show="panel === 'web'" x-cloak class="px-4 py-5 space-y-4 m-panel-in">
            <div class="m-card p-4 space-y-4">
                <div>
                    <label class="cs-lb">{{ __('admin.club_details_index_club_slug_label') }}</label>
                    <input type="text" name="slug" class="cs-in" placeholder="{{ __('admin.club_details_index_club_slug_placeholder') }}" value="{{ old('slug', $club->slug) }}">
                    <span class="cs-hint">{{ __('admin.club_details_index_club_slug_help') }}</span>
                </div>

                @if($publicUrl)
                    <div class="rounded-xl bg-muted/60 p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">{{ __('admin.club_details_index_club_url_label') }}</p>
                        <p class="text-xs text-foreground break-all mt-1 font-mono">{{ $publicUrl }}</p>
                        <div class="flex gap-2 mt-2.5">
                            <button type="button" onclick="csCopyClubUrl()" class="m-press flex-1 py-2 rounded-lg bg-white border border-border text-xs font-semibold flex items-center justify-center gap-1.5">
                                <i class="bi bi-clipboard"></i>{{ __('shared.components_qr_code_copy_link') }}
                            </button>
                            <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="m-press flex-1 py-2 rounded-lg bg-white border border-border text-xs font-semibold flex items-center justify-center gap-1.5">
                                <i class="bi bi-box-arrow-up-right"></i>{{ __('admin.preview_club_page') }}
                            </a>
                        </div>
                    </div>
                @endif
            </div>

            <div class="m-card p-4">
                <h3 class="font-semibold text-foreground text-sm">{{ __('admin.club_details_mobile_qr_codes') }}</h3>
                <p class="text-xs text-muted-foreground mt-0.5 mb-3">{{ __('admin.club_details_mobile_qr_desc') }}</p>
                <div class="flex flex-wrap gap-2">
                    <x-qr-code
                        :url="\App\Http\Controllers\QrController::clubPageUrl($club)"
                        :title="($club->club_name ?? __('admin.club')) . ' — ' . __('admin.club_details_mobile_qr_page_label')"
                        :caption="__('admin.club_details_mobile_qr_page_caption')"
                        :filename="'qr-' . $club->slug . '-page'"
                        :label="__('admin.club_details_mobile_qr_page_label')"
                        icon="bi-qr-code"
                        :poster-url="route('qr.club.page', $club)" />
                    <x-qr-code
                        :url="\App\Http\Controllers\QrController::clubRegisterUrl($club)"
                        :title="($club->club_name ?? __('admin.club')) . ' — ' . __('admin.club_details_mobile_qr_register_label')"
                        :caption="__('admin.club_details_mobile_qr_register_caption')"
                        :filename="'qr-' . $club->slug . '-register'"
                        :label="__('admin.club_details_mobile_qr_register_label')"
                        icon="bi-person-plus"
                        :poster-url="route('qr.club.register', $club)" />
                </div>
            </div>

            <div class="pb-2"><button type="submit" class="m-press w-full py-3.5 rounded-2xl bg-primary text-white font-semibold text-sm">{{ __('admin.cs_save_all') }}</button></div>
        </section>

        {{-- ─────────── Registration page ─────────── --}}
        <section x-show="panel === 'registration'" x-cloak class="px-4 py-5 space-y-4 m-panel-in">

            {{-- Splash image with a live phone preview of the real registration screen --}}
            <div class="m-card p-4">
                <label class="cs-lb">{{ __('admin.club_details_index_reg_bg_image_label') }}</label>
                <div class="flex gap-4 items-start">
                    <div class="w-[104px] flex-shrink-0" style="aspect-ratio:9/19;border-radius:18px;padding:5px;background:#111;box-shadow:0 10px 24px rgba(0,0,0,.25);">
                        <div class="w-full h-full relative overflow-hidden" style="border-radius:14px;background:#0a0a14 center/cover no-repeat;"
                             :style="splash ? `background-image:url(${splash});background-size:cover;background-position:center` : ''">
                            <div class="absolute inset-0" style="background:linear-gradient(to bottom,rgba(5,5,20,.1) 0%,rgba(5,5,20,.45) 52%,rgba(5,5,20,.95) 100%)"></div>
                            <div class="absolute inset-x-0 bottom-0 p-2 text-center text-white">
                                <template x-if="logo"><img :src="logo" class="w-7 h-7 object-contain mx-auto mb-1" alt=""></template>
                                <p class="text-[8px] font-black uppercase leading-tight" x-text="name || @js($club->club_name)"></p>
                                <div class="grid grid-cols-2 gap-1 mt-1.5">
                                    <span class="rounded-md bg-white/10 border border-white/20 py-1 text-[7px] font-semibold">English</span>
                                    <span class="rounded-md bg-white/10 border border-white/20 py-1 text-[7px] font-semibold">العربية</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <label class="m-press block w-full py-2.5 rounded-xl border border-primary text-primary text-xs font-semibold text-center cursor-pointer">
                            <i class="bi bi-upload me-1"></i>{{ __('admin.club_details_index_choose_image') }}
                            <input type="file" name="registration_splash_image" accept="image/jpeg,image/png,image/webp" class="hidden"
                                   @change="preview($event, 'splash')">
                        </label>
                        <p class="text-[11px] text-muted-foreground mt-2 break-words" x-text="splashName || '{{ __('admin.club_details_index_no_file_chosen') }}'"></p>
                        <span class="cs-hint">{{ __('admin.club_details_index_reg_bg_tip') }}</span>
                    </div>
                </div>
            </div>

            {{-- Requirements — EN + AR --}}
            <div class="m-card p-4 space-y-3">
                <div>
                    <label class="cs-lb">{{ __('admin.club_details_index_reg_requirements_label') }}</label>
                    <span class="cs-hint mt-0">{{ __('admin.club_details_index_reg_requirements_help') }}</span>
                </div>
                <p class="text-[11px] font-bold text-muted-foreground uppercase tracking-wide">{{ __('admin.club_details_index_english') }}</p>
                <x-rich-text-editor name="registration_requirements" :value="$club->registration_requirements ?? ''"
                    min-height="150px" :placeholder="__('admin.club_details_index_reg_requirements_placeholder')" />
                <p class="text-[11px] font-bold text-muted-foreground uppercase tracking-wide pt-1">{{ __('admin.club_details_index_arabic') }}</p>
                <x-rich-text-editor name="translations[registration_requirements][ar]" :value="$reqAr" dir="rtl"
                    min-height="150px" placeholder="ما يحتاجه الأعضاء للتسجيل…" />
            </div>

            {{-- Terms — EN + AR --}}
            <div class="m-card p-4 space-y-3">
                <div>
                    <label class="cs-lb">{{ __('admin.club_details_index_terms_conditions_label') }}</label>
                    <span class="cs-hint mt-0">{{ __('admin.club_details_index_terms_help') }}</span>
                </div>
                <p class="text-[11px] font-bold text-muted-foreground uppercase tracking-wide">{{ __('admin.club_details_index_english') }}</p>
                <x-rich-text-editor name="registration_terms" :value="$club->registration_terms ?? ''"
                    min-height="150px" :placeholder="__('admin.club_details_index_terms_placeholder')" />
                <p class="text-[11px] font-bold text-muted-foreground uppercase tracking-wide pt-1">{{ __('admin.club_details_index_arabic') }}</p>
                <x-rich-text-editor name="translations[registration_terms][ar]" :value="$termsAr" dir="rtl"
                    min-height="150px" placeholder="شروط وأحكام النادي للانضمام…" />
            </div>

            <div class="pb-2"><button type="submit" class="m-press w-full py-3.5 rounded-2xl bg-primary text-white font-semibold text-sm">{{ __('admin.cs_save_all') }}</button></div>
        </section>

        {{-- ─────────── Social links ─────────── --}}
        <section x-show="panel === 'social'" x-cloak class="px-4 py-5 space-y-4 m-panel-in">
            <div class="m-card p-4">
                <p class="text-xs text-muted-foreground mb-3">{{ __('admin.club_details_index_social_media_help') }}</p>
                <x-social-links-editor :links="$club->socialLinks" containerId="csSocialLinks" />
            </div>
            <div class="pb-2"><button type="submit" class="m-press w-full py-3.5 rounded-2xl bg-primary text-white font-semibold text-sm">{{ __('admin.cs_save_all') }}</button></div>
        </section>

        {{-- ─────────── Codes & preferences ─────────── --}}
        <section x-show="panel === 'codes'" x-cloak class="px-4 py-5 space-y-4 m-panel-in">
            <div class="m-card p-4">
                <h3 class="font-semibold text-foreground text-sm mb-3">{{ __('admin.club_details_index_code_prefixes') }}</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="cs-lb">{{ __('admin.club_details_index_member_code_prefix_label') }}</label>
                        <input type="text" name="settings[member_code_prefix]" class="cs-in uppercase" placeholder="MEM" value="{{ old('settings.member_code_prefix', $settings['member_code_prefix'] ?? 'MEM') }}">
                    </div>
                    <div>
                        <label class="cs-lb">{{ __('admin.club_details_index_child_code_prefix_label') }}</label>
                        <input type="text" name="settings[child_code_prefix]" class="cs-in uppercase" placeholder="CHILD" value="{{ old('settings.child_code_prefix', $settings['child_code_prefix'] ?? 'CHILD') }}">
                    </div>
                    <div>
                        <label class="cs-lb">{{ __('admin.club_details_index_invoice_code_prefix_label') }}</label>
                        <input type="text" name="settings[invoice_code_prefix]" class="cs-in uppercase" placeholder="INV" value="{{ old('settings.invoice_code_prefix', $settings['invoice_code_prefix'] ?? 'INV') }}">
                    </div>
                    <div>
                        <label class="cs-lb">{{ __('admin.club_details_index_receipt_code_prefix_label') }}</label>
                        <input type="text" name="settings[receipt_code_prefix]" class="cs-in uppercase" placeholder="REC" value="{{ old('settings.receipt_code_prefix', $settings['receipt_code_prefix'] ?? 'REC') }}">
                    </div>
                    <div>
                        <label class="cs-lb">{{ __('admin.club_details_index_expense_code_prefix_label') }}</label>
                        <input type="text" name="settings[expense_code_prefix]" class="cs-in uppercase" placeholder="EXP" value="{{ old('settings.expense_code_prefix', $settings['expense_code_prefix'] ?? 'EXP') }}">
                    </div>
                    <div>
                        <label class="cs-lb">{{ __('admin.club_details_index_specialist_code_prefix_label') }}</label>
                        <input type="text" name="settings[specialist_code_prefix]" class="cs-in uppercase" placeholder="SPEC" value="{{ old('settings.specialist_code_prefix', $settings['specialist_code_prefix'] ?? 'SPEC') }}">
                    </div>
                </div>
            </div>

            <div class="m-card p-4">
                <h3 class="font-semibold text-foreground text-sm mb-3">{{ __('admin.club_details_index_member_prefs') }}</h3>
                {{-- Hidden 0 so an unchecked box still submits an explicit "off". --}}
                <input type="hidden" name="settings[block_explore]" value="0">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" name="settings[block_explore]" value="1"
                           class="mt-0.5 w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary flex-shrink-0"
                           @checked(old('settings.block_explore', ! empty($settings['block_explore'])))>
                    <span>
                        <span class="block text-sm font-medium text-foreground">{{ __('admin.club_details_index_block_explore_label') }}</span>
                        <span class="block text-xs text-muted-foreground mt-0.5">{{ __('admin.club_details_index_block_explore_help') }}</span>
                    </span>
                </label>
            </div>

            <div class="pb-2"><button type="submit" class="m-press w-full py-3.5 rounded-2xl bg-primary text-white font-semibold text-sm">{{ __('admin.cs_save_all') }}</button></div>
        </section>
    </form>

    {{-- ══════════════ Panels that save on their own (outside the main form) ══════════════ --}}

    {{-- ─────────── WhatsApp ─────────── --}}
    <section x-show="panel === 'whatsapp'" x-cloak class="px-4 py-5 space-y-4 m-panel-in" x-data="csWhatsApp()">
        @unless($whatsappSettings['gateway_configured'])
            <div class="rounded-2xl bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800 flex gap-2">
                <i class="bi bi-exclamation-triangle flex-shrink-0 mt-0.5"></i><span>{{ __('admin.club_details_index_whatsapp_gateway_not_configured') }}</span>
            </div>
        @endunless

        <div class="m-card p-4 space-y-4">
            <p class="text-xs text-muted-foreground">{{ __('admin.club_details_index_whatsapp_description') }}</p>

            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" x-model="form.enabled" class="mt-0.5 w-5 h-5 rounded border-gray-300 text-primary focus:ring-primary flex-shrink-0">
                <span>
                    <span class="block text-sm font-medium text-foreground">{{ __('admin.club_details_index_whatsapp_enable') }}</span>
                    <span class="block text-xs text-muted-foreground mt-0.5">{{ __('admin.club_details_index_whatsapp_enable_hint') }}</span>
                </span>
            </label>

            <div>
                <label class="cs-lb">{{ __('admin.club_details_index_whatsapp_session_name') }}</label>
                <input type="text" x-model="form.session_name" class="cs-in" placeholder="my-club-session">
                <span class="cs-hint">{{ __('admin.club_details_index_whatsapp_session_name_hint') }}</span>
            </div>

            <div class="flex gap-2">
                <button type="button" @click="test" :disabled="testing" class="m-press flex-1 py-2.5 rounded-xl border border-primary text-primary text-xs font-semibold disabled:opacity-50">
                    <i class="bi bi-broadcast me-1"></i><span x-text="testing ? '{{ __('admin.club_details_index_whatsapp_testing') }}' : '{{ __('admin.club_details_index_whatsapp_test') }}'"></span>
                </button>
                <button type="button" @click="save" :disabled="saving" class="m-press flex-1 py-2.5 rounded-xl bg-primary text-white text-xs font-semibold disabled:opacity-50">
                    <i class="bi bi-check-lg me-1"></i><span x-text="saving ? '{{ __('admin.club_details_index_whatsapp_saving') }}' : '{{ __('admin.club_details_index_whatsapp_save') }}'"></span>
                </button>
            </div>
        </div>

        <div class="m-card p-4">
            <label class="cs-lb">{{ __('admin.club_details_index_whatsapp_send_test_label') }}</label>
            <input type="text" x-model="testPhone" class="cs-in" placeholder="{{ __('admin.club_details_index_whatsapp_send_test_placeholder') }}">
            <button type="button" @click="sendTest" :disabled="sendingTest || !testPhone"
                    class="m-press w-full mt-2.5 py-2.5 rounded-xl border border-primary text-primary text-xs font-semibold disabled:opacity-50">
                <i class="bi bi-send me-1"></i><span x-text="sendingTest ? '{{ __('admin.club_details_index_whatsapp_sending_test') }}' : '{{ __('admin.club_details_index_whatsapp_send_test') }}'"></span>
            </button>
            <span class="cs-hint">{{ __('admin.club_details_index_whatsapp_send_test_hint') }}</span>
        </div>
    </section>

    {{-- ─────────── Owner ─────────── --}}
    <section x-show="panel === 'owner'" x-cloak class="px-4 py-5 space-y-4 m-panel-in" x-data="csOwner()">
        <div class="m-card p-4" id="csOwnerCard">
            @if($club->owner)
                <div class="flex items-center gap-3">
                    <span class="w-12 h-12 rounded-full bg-muted grid place-items-center overflow-hidden flex-shrink-0">
                        @if($club->owner->profile_picture)
                            <img src="{{ asset('storage/'.$club->owner->profile_picture) }}" alt="" class="w-full h-full object-cover">
                        @else
                            <i class="bi bi-person text-muted-foreground text-lg"></i>
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="font-semibold text-foreground text-sm truncate">{{ $club->owner->full_name }}</p>
                        @if($club->owner->email)<p class="text-xs text-muted-foreground truncate">{{ $club->owner->email }}</p>@endif
                        @if($club->owner->formatted_mobile)<p class="text-xs text-muted-foreground truncate">{{ $club->owner->formatted_mobile }}</p>@endif
                    </div>
                </div>
            @else
                <div class="text-center py-4">
                    <i class="bi bi-person-plus text-2xl text-muted-foreground m-float"></i>
                    <p class="text-sm text-muted-foreground mt-2">{{ __('admin.cs_no_owner') }}</p>
                </div>
            @endif
        </div>

        <div class="m-card p-4 space-y-3">
            <h3 class="font-semibold text-foreground text-sm">{{ __('admin.cs_transfer_owner') }}</h3>
            <p class="text-xs text-muted-foreground">{{ __('admin.club_details_index_transfer_existing_help') }}</p>

            <div class="relative">
                <input type="text" x-model="query" @input.debounce.300ms="search()" class="cs-in ps-9"
                       placeholder="{{ __('admin.cs_owner_search_ph') }}" autocomplete="new-password">
                <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm pointer-events-none"></i>
            </div>

            <div class="space-y-2 max-h-64 overflow-y-auto" x-show="results.length" x-cloak>
                <template x-for="u in results" :key="u.id">
                    <button type="button" @click="select(u)" class="w-full flex items-center gap-3 p-2.5 rounded-xl border border-border text-start hover:bg-muted/60">
                        <span class="w-9 h-9 rounded-full bg-primary/15 grid place-items-center font-bold text-primary text-sm flex-shrink-0 overflow-hidden">
                            <template x-if="u.profile_picture"><img :src="u.profile_picture" class="w-full h-full object-cover" alt=""></template>
                            <template x-if="!u.profile_picture"><span x-text="(u.name || '?').charAt(0).toUpperCase()"></span></template>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-foreground truncate" x-text="u.name"></span>
                            <span class="block text-xs text-muted-foreground truncate" x-text="u.email || ''"></span>
                        </span>
                    </button>
                </template>
            </div>

            <p x-show="searched && !results.length && !selected" x-cloak class="text-xs text-muted-foreground text-center py-2">{{ __('admin.cs_owner_no_results') }}</p>

            <div x-show="selected" x-cloak class="rounded-xl border border-primary/40 bg-primary/5 p-3 flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-foreground truncate" x-text="selected?.name"></p>
                    <p class="text-xs text-muted-foreground truncate" x-text="selected?.email || ''"></p>
                </div>
                <button type="button" @click="selected = null" class="text-muted-foreground" aria-label="{{ __('shared.cancel') }}"><i class="bi bi-x-lg"></i></button>
            </div>

            <button type="button" @click="transfer()" :disabled="!selected || busy"
                    class="m-press w-full py-3 rounded-xl bg-primary text-white text-sm font-semibold disabled:opacity-50">
                <i class="bi bi-check-lg me-1"></i>{{ __('admin.cs_owner_confirm') }}
            </button>
        </div>
    </section>

    {{-- ─────────── Danger zone ─────────── --}}
    <section x-show="panel === 'danger'" x-cloak class="px-4 py-5 space-y-4 m-panel-in" x-data="{ confirmName: '' }">
        <div class="m-card p-4 border-red-200">
            <h3 class="font-semibold text-red-600 text-sm flex items-center gap-2"><i class="bi bi-exclamation-triangle"></i>{{ __('admin.club_details_index_danger_zone') }}</h3>
            <p class="text-xs text-muted-foreground mt-2">{{ __('admin.club_details_index_delete_intro') }}</p>
            <ul class="text-xs text-muted-foreground space-y-1 mt-2 list-disc list-inside">
                <li>{{ __('admin.club_details_index_delete_item_info') }}</li>
                <li>{{ __('admin.club_details_index_delete_item_facilities') }}</li>
                <li>{{ __('admin.club_details_index_delete_item_packages') }}</li>
                <li>{{ __('admin.club_details_index_delete_item_files') }}</li>
                <li>{{ __('admin.club_details_index_delete_item_reviews') }}</li>
            </ul>

            <form action="{{ route('admin.club.destroy', $club->slug) }}" method="POST" class="mt-4">
                @csrf
                @method('DELETE')
                <label class="cs-lb">{{ __('admin.club_details_index_confirm_delete_prompt') }} <strong>{{ $club->club_name }}</strong></label>
                <input type="text" x-model="confirmName" class="cs-in" placeholder="{{ __('admin.cs_delete_confirm_ph') }}" required>
                <button type="submit" :disabled="confirmName !== @js($club->club_name)"
                        class="m-press w-full mt-3 py-3 rounded-xl bg-destructive text-white text-sm font-semibold disabled:opacity-40">
                    <i class="bi bi-trash me-1"></i>{{ __('admin.club_details_index_delete_permanently') }}
                </button>
            </form>
        </div>
    </section>
</div>

{{-- Inline (not @push): the mobile shell only re-executes scripts inside the swapped content. --}}
<script>
function csCopyClubUrl() {
    var url = @json($publicUrl);
    if (!url || !navigator.clipboard) return;
    navigator.clipboard.writeText(url).then(function () {
        window.showToast('success', @json(__('admin.club_details_index_js_url_copied')));
    });
}

function clubStudio() {
    return {
        panel: null,
        title: '',
        panelUsesForm: true,
        dirty: false,
        lang: 'en',
        // Live hero/preview state
        name:    @json(old('club_name', $club->club_name)),
        slogan:  @json(old('slogan', $club->slogan ?? '')),
        logo:    @json($logoUrl),
        cover:   @json($coverUrl),
        favicon: @json($faviconUrl),
        splash:  @json($splashUrl),
        splashName: '',

        titles: {
            identity:     @json(__('admin.cs_sec_identity')),
            branding:     @json(__('admin.cs_sec_branding')),
            contact:      @json(__('admin.cs_sec_contact')),
            location:     @json(__('admin.cs_sec_location')),
            money:        @json(__('admin.cs_sec_money')),
            web:          @json(__('admin.cs_sec_web')),
            registration: @json(__('admin.cs_sec_registration')),
            social:       @json(__('admin.cs_sec_social')),
            codes:        @json(__('admin.cs_sec_codes')),
            whatsapp:     @json(__('admin.cs_sec_whatsapp')),
            owner:        @json(__('admin.cs_sec_owner')),
            danger:       @json(__('admin.cs_sec_danger')),
        },
        // Panels that belong to the shared form (so the header Save button applies).
        formPanels: ['identity','branding','contact','location','money','web','registration','social','codes'],

        init() {
            // Deep-link support: /details#branding opens that panel directly.
            var hash = (window.location.hash || '').replace('#', '');
            if (hash && this.titles[hash]) this.open(hash);
            // A validation failure should land the admin back on a form panel.
            @if($errors->any()) if (!this.panel) this.open('identity'); @endif
        },

        open(part) {
            this.panel = part;
            this.title = this.titles[part] || '';
            this.panelUsesForm = this.formPanels.indexOf(part) !== -1;
            window.scrollTo({ top: 0, behavior: 'smooth' });
            if (part === 'location') this.mountMap();
        },

        close() {
            this.panel = null;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        /** Local file → data URL preview. The file itself still posts with the form. */
        preview(event, key) {
            var file = event.target.files && event.target.files[0];
            if (!file) return;
            if (key === 'splash') this.splashName = file.name;
            var self = this, reader = new FileReader();
            reader.onload = function (e) { self[key] = e.target.result; };
            reader.readAsDataURL(file);
            this.dirty = true;
        },

        /** Leaflet lives behind window.LocationMap; degrade gracefully if absent. */
        mountMap() {
            var el = document.getElementById('clubDetailsLocMap');
            if (!window.LocationMap) {
                if (el) el.classList.add('hidden');
                var fb = document.getElementById('csMapFallback');
                if (fb) fb.classList.remove('hidden');
                return;
            }
            window.LocationMap.create({ id: 'clubDetailsLoc', defaultLat: 26.2285, defaultLng: 50.5860, zoom: 13 });
            window.LocationMap.refresh('clubDetailsLoc');
        },
    };
}

function csWhatsApp() {
    return {
        saving: false, testing: false, sendingTest: false, testPhone: '',
        form: { enabled: @json($whatsappSettings['enabled']), session_name: @json($whatsappSettings['session_name']) },
        _headers() {
            return {
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            };
        },
        async save() {
            this.saving = true;
            try {
                const res = await fetch(@json(route('admin.club.settings.whatsapp.update', $club->slug)), {
                    method: 'PUT', headers: this._headers(), body: JSON.stringify(this.form),
                });
                const data = await res.json();
                window.showToast(data.success ? 'success' : 'error', data.message);
            } catch (e) {
                window.showToast('error', @json(__('admin.club_details_index_js_network_error')));
            } finally { this.saving = false; }
        },
        async test() {
            this.testing = true;
            try {
                const res = await fetch(@json(route('admin.club.settings.whatsapp.test', $club->slug)), { method: 'POST', headers: this._headers() });
                const data = await res.json();
                window.showToast(data.success ? 'success' : 'error', data.message);
            } catch (e) {
                window.showToast('error', @json(__('admin.club_details_index_js_network_error')));
            } finally { this.testing = false; }
        },
        async sendTest() {
            if (!this.testPhone) return;
            this.sendingTest = true;
            try {
                const res = await fetch(@json(route('admin.club.settings.whatsapp.send-test', $club->slug)), {
                    method: 'POST', headers: this._headers(), body: JSON.stringify({ phone: this.testPhone }),
                });
                const data = await res.json();
                window.showToast(data.success ? 'success' : 'error', data.message);
            } catch (e) {
                window.showToast('error', @json(__('admin.club_details_index_js_network_error')));
            } finally { this.sendingTest = false; }
        },
    };
}

function csOwner() {
    return {
        query: '', results: [], selected: null, busy: false, searched: false,

        async search() {
            var q = this.query.trim();
            if (q.length < 2) { this.results = []; this.searched = false; return; }
            try {
                const res = await fetch(@json(route('admin.club.members.search', $club->slug)) + '?query=' + encodeURIComponent(q), {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                this.results = data.users || [];
                this.searched = true;
            } catch (e) { this.results = []; }
        },

        select(u) { this.selected = u; this.results = []; this.query = ''; this.searched = false; },

        async transfer() {
            if (!this.selected || this.busy) return;
            var ok = await window.confirmAction({
                title: @json(__('admin.cs_transfer_owner')),
                message: this.selected.name,
                type: 'warning',
                confirmText: @json(__('admin.cs_owner_confirm')),
            });
            if (!ok) return;

            this.busy = true;
            try {
                const res = await fetch(@json(route('admin.club.transfer-ownership', $club->slug)), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ mode: 'existing', user_id: this.selected.id }),
                });
                const data = await res.json();
                if (data.success && data.owner) {
                    // Patch the owner card in place — no reload (No Page Reload rule).
                    var esc = function (s) {
                        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
                        });
                    };
                    var card = document.getElementById('csOwnerCard');
                    if (card) {
                        card.innerHTML =
                            '<div class="flex items-center gap-3">' +
                                '<span class="w-12 h-12 rounded-full bg-muted grid place-items-center overflow-hidden flex-shrink-0"><i class="bi bi-person text-muted-foreground text-lg"></i></span>' +
                                '<div class="min-w-0">' +
                                    '<p class="font-semibold text-foreground text-sm truncate">' + esc(data.owner.name) + '</p>' +
                                    (data.owner.email ? '<p class="text-xs text-muted-foreground truncate">' + esc(data.owner.email) + '</p>' : '') +
                                    (data.owner.mobile ? '<p class="text-xs text-muted-foreground truncate">' + esc(data.owner.mobile) + '</p>' : '') +
                                '</div>' +
                            '</div>';
                    }
                    this.selected = null;
                    window.showToast('success', data.message || @json(__('admin.club_details_index_js_transfer_success')));
                } else {
                    window.showToast('error', data.message || @json(__('admin.club_details_index_js_something_wrong')));
                }
            } catch (e) {
                window.showToast('error', @json(__('admin.club_details_index_js_network_error')));
            } finally { this.busy = false; }
        },
    };
}

// "Use my location" — writes straight into the map's lat/lng inputs, so it works
// even when Leaflet itself is unavailable.
(function () {
    if (window.__csGeoBound) document.removeEventListener('click', window.__csGeoBound);
    window.__csGeoBound = function (e) {
        var btn = e.target.closest ? e.target.closest('#csUseMyLocation') : null;
        if (!btn) return;
        if (!navigator.geolocation) {
            window.showToast('error', @json(__('admin.club_details_index_js_geolocation_unsupported')));
            return;
        }
        btn.disabled = true;
        navigator.geolocation.getCurrentPosition(function (pos) {
            var lat = pos.coords.latitude, lng = pos.coords.longitude;
            var latEl = document.getElementById('clubDetailsLocLat');
            var lngEl = document.getElementById('clubDetailsLocLng');
            if (latEl) latEl.value = lat.toFixed(6);
            if (lngEl) lngEl.value = lng.toFixed(6);
            if (window.LocationMap) {
                window.LocationMap.setPosition('clubDetailsLoc', lat, lng);
                var inst = window.LocationMap.get('clubDetailsLoc');
                if (inst) inst.map.setView([lat, lng], 15);
            }
            btn.disabled = false;
        }, function () {
            window.showToast('error', @json(__('admin.club_details_index_js_location_error')));
            btn.disabled = false;
        });
    };
    document.addEventListener('click', window.__csGeoBound);
})();
</script>
@endsection
