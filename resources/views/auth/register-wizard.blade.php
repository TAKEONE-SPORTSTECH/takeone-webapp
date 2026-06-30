<!DOCTYPE html>
<html lang="en" id="wiz-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ session('club.context.name', 'Register') }} — TAKEONE</title>
    {{-- Use the club's logo as the browser tab favicon --}}
    @if(session('club.context.logo'))
        <link rel="icon" href="{{ asset('storage/' . session('club.context.logo')) }}">
        <link rel="apple-touch-icon" href="{{ asset('storage/' . session('club.context.logo')) }}">
    @endif
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css">
    <style>
        *, *::before, *::after { -webkit-tap-highlight-color: transparent; box-sizing: border-box; }
        [x-cloak] { display: none !important; }
        body { margin: 0; background: #f4f5fb; font-family: 'Inter', system-ui, sans-serif; }
        #wiz-root { min-height: 100svh; display: flex; flex-direction: column; max-width: 540px; margin: 0 auto; }

        .wiz-step { animation: wizFade 0.22s ease; }
        @keyframes wizFade { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .wiz-progress-track { height: 3px; background: #e5e7eb; }
        .wiz-progress-fill  { height: 3px; background: hsl(250 65% 65%); transition: width 0.4s ease; }

        .who-card { border: 2px solid #e5e7eb; border-radius: 16px; padding: 28px 20px; cursor: pointer; transition: all 0.18s; text-align: center; background: #fff; }
        .who-card:hover, .who-card.selected { border-color: hsl(250 65% 65%); background: hsl(250 60% 97%); }
        .who-card .who-icon { font-size: 2.5rem; margin-bottom: 10px; }

        .pkg-card { border: 2px solid #e5e7eb; border-radius: 14px; padding: 16px; cursor: pointer; transition: all 0.15s; background: #fff; }
        .pkg-card.selected { border-color: hsl(250 65% 65%); background: hsl(250 60% 97%); }
        .pkg-card .pkg-check { width: 22px; height: 22px; border-radius: 50%; border: 2px solid #d1d5db; display: flex; align-items: center; justify-content: center; transition: all 0.15s; flex-shrink: 0; }
        .pkg-card.selected .pkg-check { background: hsl(250 65% 65%); border-color: hsl(250 65% 65%); color: #fff; }

        .upload-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 16px; border-radius: 10px; font-size: 13px; font-weight: 500; cursor: pointer; border: 1.5px solid #d1d5db; background: #fff; color: #374151; transition: all 0.15s; white-space: nowrap; }
        .upload-btn:hover { border-color: hsl(250 65% 65%); color: hsl(250 65% 65%); }
        .upload-btn input { display: none; }

        .photo-preview { width: 72px; height: 72px; border-radius: 12px; object-fit: cover; border: 2px solid hsl(250 65% 65%); }
        .cpr-preview { width: 100px; height: 64px; border-radius: 8px; object-fit: cover; border: 2px solid hsl(250 65% 65%); }

        .terms-box { max-height: 28vh; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; background: #fafafa; font-size: 13px; line-height: 1.6; color: #4b5563; }
        /* The main terms box fills the leftover space down to the bottom bar and
           scrolls internally — flex-basis:0 + min-height:0 stop its content from
           dictating the height (which would grow the page past the viewport). */
        .terms-box.terms-grow { flex: 1 1 0; min-height: 0; max-height: none; }
        .terms-box::-webkit-scrollbar { width: 4px; }
        .terms-box::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
        /* Render the rich-text-editor HTML (h1-h3, lists, quotes, links) consistently
           with how it looked in the editor — Tailwind's reset strips browser defaults. */
        .terms-box h1 { font-size: 1.25rem; font-weight: 700; margin: .5em 0 .3em; color: #1f2937; }
        .terms-box h2 { font-size: 1.1rem; font-weight: 700; margin: .5em 0 .3em; color: #1f2937; }
        .terms-box h3 { font-size: 1rem; font-weight: 700; margin: .5em 0 .3em; color: #1f2937; }
        .terms-box p { margin: .35em 0; }
        .terms-box ul { list-style: disc; padding-inline-start: 1.5em; margin: .4em 0; }
        .terms-box ol { list-style: decimal; padding-inline-start: 1.5em; margin: .4em 0; }
        .terms-box li { margin: .15em 0; }
        .terms-box li p { margin: 0; }
        .terms-box a { color: hsl(250 65% 55%); text-decoration: underline; }
        .terms-box strong, .terms-box b { font-weight: 700; }
        .terms-box em, .terms-box i { font-style: italic; }
        .terms-box u { text-decoration: underline; }
        .terms-box blockquote { border-inline-start: 3px solid hsl(250 65% 75%); padding-inline-start: 12px; margin: .5em 0; color: #6b7280; font-style: italic; }
        .terms-box hr { border: none; border-top: 1px solid #e5e7eb; margin: .8em 0; }

        .field-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .field-input { width: 100%; padding: 11px 14px; border: 1.5px solid #e5e7eb; border-radius: 12px; font-size: 15px; color: #111827; background: #fff; outline: none; transition: border-color 0.15s; }
        .field-input:focus { border-color: hsl(250 65% 65%); }
        .field-error { border-color: #f87171 !important; }
        .err-msg { font-size: 12px; color: #ef4444; margin-top: 4px; }

        .child-card { display: flex; align-items: center; gap: 12px; padding: 14px; background: #fff; border: 1.5px solid #e5e7eb; border-radius: 14px; }
        .child-avatar { width: 42px; height: 42px; border-radius: 50%; background: hsl(250 60% 92%); color: hsl(250 65% 65%); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; flex-shrink: 0; }

        .btn-primary { background: hsl(250 65% 65%); color: #fff; border: none; border-radius: 14px; font-size: 15px; font-weight: 600; padding: 14px 28px; cursor: pointer; transition: opacity 0.15s; width: 100%; }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-ghost { background: transparent; color: #6b7280; border: 1.5px solid #e5e7eb; border-radius: 14px; font-size: 15px; font-weight: 500; padding: 14px 20px; cursor: pointer; transition: all 0.15s; }
        .btn-ghost:hover { border-color: #9ca3af; color: #374151; }

        /* Make tf-dropdown-trigger & tf-input-group blend into wizard card */
        .tf-dropdown-trigger { border-radius: 12px !important; }
        /* No overflow:hidden — it would clip the country-code dropdown panel (an
           absolutely-positioned descendant). The button (rounded-l-xl) and the tel
           input (rounded-r-xl) keep the corners clean on their own. */
        .tf-input-group { border-radius: 12px !important; border: 1.5px solid #e5e7eb !important; }
        .tf-input-group input[type="tel"] { padding: 11px 14px; font-size: 15px; }

        [dir="rtl"] .field-input { text-align: right; }
        [dir="rtl"] .upload-btn { flex-direction: row-reverse; }
        /* Ensure component labels don't double up */
        .wiz-no-label .tf-label { display: none; }

        /* ── Step 0: Splash screen ── */
        #wiz-splash {
            flex: 1;
            position: relative;
            display: flex; flex-direction: column;
            align-items: center; justify-content: flex-end;
            overflow: hidden;
            min-height: 100svh;
        }
        #wiz-splash-bg {
            position: absolute; inset: 0;
            /* cover scales the dedicated portrait image proportionally to fill the
               screen — it never distorts/stretches; it only crops overflow. */
            background-size: cover;
            background-position: center center;
            background-repeat: no-repeat;
            background-color: #0a0a14;
        }
        #wiz-splash-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(
                to bottom,
                rgba(5,5,20,0.15) 0%,
                rgba(5,5,20,0.10) 30%,
                rgba(5,5,20,0.55) 58%,
                rgba(5,5,20,0.92) 75%,
                rgba(5,5,20,1.00) 100%
            );
        }
        #wiz-splash-content {
            position: relative; z-index: 2;
            width: 100%; padding: 0 28px 52px;
            text-align: center;
            animation: wizFade 0.6s ease;
        }
        .splash-logo {
            width: 100px; height: 100px;
            border-radius: 50%;
            object-fit: contain;
            border: 3px solid rgba(255,255,255,0.25);
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
            margin: 0 auto 18px;
            display: block;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
        }
        .splash-club-name {
            font-size: clamp(22px, 6vw, 30px);
            font-weight: 900;
            color: #fff;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            line-height: 1.15;
            margin-bottom: 6px;
            text-shadow: 0 2px 16px rgba(0,0,0,0.8);
        }
        .splash-tagline {
            font-size: 13px;
            color: rgba(255,255,255,0.6);
            margin-bottom: 36px;
            letter-spacing: 0.02em;
        }
        .splash-lang-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 14px;
            /* The language picker stays in a fixed order (English | العربية) even
               after Arabic is chosen — otherwise the buttons swap sides on the
               RTL splash when returning via Back. */
            direction: ltr;
        }
        .splash-lang-btn {
            display: flex; flex-direction: column; align-items: center; gap: 8px;
            padding: 18px 12px;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(12px);
            border: 1.5px solid rgba(255,255,255,0.18);
            border-radius: 18px;
            cursor: pointer;
            transition: all 0.2s;
            color: #fff;
        }
        .splash-lang-btn:hover, .splash-lang-btn:active {
            background: rgba(255,255,255,0.16);
            border-color: rgba(255,255,255,0.4);
            transform: translateY(-2px);
        }
        .splash-lang-btn .fi { font-size: 2.2rem; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
        .splash-lang-btn .lang-name { font-size: 15px; font-weight: 700; letter-spacing: 0.02em; }
        .splash-lang-btn .lang-sub  { font-size: 11px; color: rgba(255,255,255,0.55); }
        .splash-lang-btn .lang-arrow {
            width: 28px; height: 28px; border-radius: 50%;
            background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25);
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; margin-top: 2px;
        }
    </style>
</head>
<body>
<div id="wiz-root"
     x-data="wizard()"
     x-init="init()"
     x-bind:dir="lang === 'ar' ? 'rtl' : 'ltr'"
     x-bind:lang="lang">

    {{-- ── Header ─────────────────────────────────────────── --}}
    <div x-show="step > 0 && step < 6" class="bg-white shadow-sm">
        <div class="wiz-progress-track">
            <div class="wiz-progress-fill" x-bind:style="'width:' + Math.round((step/5)*100) + '%'"></div>
        </div>
        <div class="flex items-center gap-3 px-4 py-3">
            <template x-if="clubLogo">
                <img x-bind:src="clubLogo" alt="" class="w-9 h-9 rounded-lg object-contain flex-shrink-0">
            </template>
            <template x-if="!clubLogo">
                <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
                    <i class="bi bi-shield-fill-check text-primary text-lg"></i>
                </div>
            </template>
            <div class="flex-1 min-w-0">
                <p class="text-xs text-gray-400 font-medium" x-text="t.joining + ' ' + clubName"></p>
                <p class="text-sm font-semibold text-gray-800 truncate"
                   x-text="t.stepLabel + ' ' + step + ' / 5'"></p>
            </div>
        </div>
    </div>

    {{-- ── STEP 0 : Welcome / Language (Splash) ───────────── --}}
    <div x-show="step === 0" id="wiz-splash">
        {{-- Background image --}}
        <div id="wiz-splash-bg"
             x-bind:style="(clubSplash || clubCover) ? 'background-image: url(' + (clubSplash || clubCover) + ')' : ''"></div>
        <div id="wiz-splash-overlay"></div>

        <div id="wiz-splash-content">
            {{-- Logo --}}
            <template x-if="clubLogo">
                <img x-bind:src="clubLogo" alt="" class="splash-logo">
            </template>
            <template x-if="!clubLogo">
                <div class="splash-logo flex items-center justify-center">
                    <i class="bi bi-shield-fill-check text-white text-4xl"></i>
                </div>
            </template>

            {{-- Club name --}}
            <div class="splash-club-name" x-text="clubName || 'TAKEONE'"></div>
            <div class="splash-tagline">Choose your language / اختر لغتك</div>

            {{-- Language buttons --}}
            <div class="splash-lang-grid">
                <button class="splash-lang-btn"
                        @click="lang = 'en'; goTo(1)">
                    <span class="fi fi-gb"></span>
                    <span class="lang-name">English</span>
                    <span class="lang-sub">English Language</span>
                    <span class="lang-arrow"><i class="bi bi-chevron-right"></i></span>
                </button>
                <button class="splash-lang-btn"
                        @click="lang = 'ar'; document.getElementById('wiz-html').setAttribute('lang','ar'); goTo(1)">
                    <span class="fi fi-bh"></span>
                    <span class="lang-name">العربية</span>
                    <span class="lang-sub">اللغة العربية</span>
                    <span class="lang-arrow"><i class="bi bi-chevron-left"></i></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── STEP 1 : Terms & Conditions ─────────────────────── --}}
    <div x-show="step === 1" class="wiz-step flex-1 min-h-0 px-5 py-6 flex flex-col">
        <div class="mb-4 flex-shrink-0">
            <h2 class="text-xl font-bold text-gray-900 mb-1" x-text="t.termsTitle"></h2>
            <p class="text-sm text-gray-500" x-text="t.termsSub"></p>
        </div>

        {{-- Club requirements (rich HTML, shown in the chosen language if set) --}}
        <template x-if="reqsHtml">
            <div class="terms-box mb-4 flex-shrink-0">
                <h4 class="font-bold text-gray-800 mb-2"><i class="bi bi-list-check"></i> Requirements / المتطلبات</h4>
                <div x-html="reqsHtml"></div>
            </div>
        </template>

        {{-- Terms & Conditions: club's own rich text (per language), else the default --}}
        <div class="terms-box terms-grow mb-4">
            <template x-if="clubTermsEn || clubTermsAr">
                <div x-html="termsHtml"></div>
            </template>
            <div x-show="!clubTermsEn && !clubTermsAr">
                <p>By registering with {{ session('club.context.name', 'this club') }}, you agree to the following terms:</p>
                <br>
                <p><strong>1. Membership</strong><br>Membership is subject to approval by the club. Registration does not guarantee acceptance. The club reserves the right to decline any application.</p>
                <br>
                <p><strong>2. Payment</strong><br>Fees are due as per the selected package. Payment must be submitted to the club as proof of payment. Subscriptions become active only after payment is confirmed by club administration.</p>
                <br>
                <p><strong>3. Medical Information</strong><br>You are responsible for disclosing any medical conditions that may affect participation in club activities. The club is not liable for undisclosed health conditions.</p>
                <br>
                <p><strong>4. Code of Conduct</strong><br>All members are expected to respect club facilities, staff, and fellow members. The club may revoke membership for misconduct.</p>
                <br>
                <p><strong>5. Privacy</strong><br>Your personal data is collected for the purpose of club management and will not be shared with third parties without your consent, except as required by law.</p>
                <br>
                <p><strong>6. Changes</strong><br>The club reserves the right to amend these terms at any time. Continued membership constitutes acceptance of updated terms.</p>
                <br>
                <p class="font-medium">By proceeding, you confirm you have read and understood these terms.</p>
            </div>
        </div>
        <label class="flex items-start gap-3 cursor-pointer flex-shrink-0">
            <div class="relative flex-shrink-0 mt-0.5">
                <input type="checkbox" x-model="agreedToTerms" class="sr-only">
                <div class="w-5 h-5 rounded-md border-2 flex items-center justify-center transition-all"
                     :class="agreedToTerms ? 'bg-primary border-primary' : 'border-gray-300 bg-white'">
                    <i class="bi bi-check text-white text-xs" x-show="agreedToTerms"></i>
                </div>
            </div>
            <span class="text-sm text-gray-700 leading-relaxed" x-text="t.termsAgree"></span>
        </label>
        <p x-show="errors.terms" class="err-msg mt-2" x-text="t.agreeTerms"></p>
    </div>

    {{-- ── STEP 2 : Your details (account + profile) ───────── --}}
    <div x-show="step === 2" class="wiz-step flex-1 px-5 py-6">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-1" x-text="t.detailsTitle"></h2>
            <p class="text-sm text-gray-500" x-text="t.detailsSub"></p>
        </div>

        <div class="space-y-4">
            {{-- Full name --}}
            <div>
                <label class="field-label" x-text="t.fullName"></label>
                <input type="text" x-model="account.full_name" autocomplete="name"
                       :class="errors.full_name ? 'field-error' : ''"
                       class="field-input" placeholder="Your full name">
                <p x-show="errors.full_name" class="err-msg" x-text="t.required"></p>
            </div>

            {{-- Email --}}
            <div>
                <label class="field-label" x-text="t.email"></label>
                <input type="email" x-model="account.email" autocomplete="email"
                       :class="errors.email ? 'field-error' : ''"
                       class="field-input" placeholder="you@example.com">
                <p x-show="errors.email" class="err-msg" x-text="t.invalidEmail"></p>
            </div>


            {{-- Mobile number --}}
            <div>
                <label class="field-label" x-text="t.mobile"></label>
                <x-country-code-dropdown name="country_code" id="country_code" value="+973">
                    <input type="tel" id="wizard_mobile_number" name="mobile_number"
                           class="w-full px-4 py-2.5 bg-transparent focus:outline-none text-sm text-gray-900"
                           placeholder="e.g. 33001234" autocomplete="tel">
                </x-country-code-dropdown>
                <p x-show="errors.mobile_number" class="err-msg" x-text="t.required"></p>
            </div>

            {{-- Nationality --}}
            <div class="wiz-no-label">
                <label class="field-label" x-text="t.nationality"></label>
                <x-country-dropdown name="nationality" id="nationality" label="Nationality" value="BH" />
            </div>

            {{-- Gender — inline bilingual selector (fully localised) --}}
            <div>
                <label class="field-label" x-text="t.gender"></label>
                <p x-show="errors.gender" class="err-msg mb-1" x-text="t.required"></p>
                <x-gender-toggle model="self.gender" male-label="t.male" female-label="t.female" />
            </div>

            {{-- Date of birth --}}
            <div class="wiz-no-label">
                <label class="field-label" x-text="t.dob"></label>
                <p x-show="errors.birthdate" class="err-msg mb-1" x-text="t.required"></p>
                <x-birthdate-dropdown name="self_birthdate" id="self_birthdate"
                    label="Date of Birth"
                    :required="true"
                    :min-age="3"
                    :max-age="120" />
            </div>

            {{-- Health conditions — checkbox enables the text box --}}
            <div class="mb-4">
                <label class="flex items-start gap-3 cursor-pointer">
                    <div class="relative flex-shrink-0 mt-0.5">
                        <input type="checkbox" x-model="self.hasHealth"
                               @change="if (!self.hasHealth) self.health_conditions = ''" class="sr-only">
                        <div class="w-5 h-5 rounded-md border-2 flex items-center justify-center transition-all"
                             :class="self.hasHealth ? 'bg-primary border-primary' : 'border-gray-300 bg-white'">
                            <i class="bi bi-check text-white text-xs" x-show="self.hasHealth"></i>
                        </div>
                    </div>
                    <span class="text-sm text-gray-700 leading-relaxed" x-text="t.healthToggle"></span>
                </label>
                <div x-show="self.hasHealth" x-transition class="mt-3">
                    <textarea x-model="self.health_conditions" class="field-input" rows="3"
                              placeholder="Optional — e.g. asthma, diabetes" style="resize:none"></textarea>
                </div>
            </div>
        </div>
    </div>

    {{-- ── STEP 3 : Add children (optional) ─────────────────── --}}
    <div x-show="step === 3" class="wiz-step flex-1 px-5 py-6">
        <div class="mb-5">
            <h2 class="text-xl font-bold text-gray-900 mb-1" x-text="t.kidsTitle"></h2>
            <p class="text-sm text-gray-500" x-text="t.kidsSub"></p>
        </div>

        {{-- Child list --}}
        <div class="space-y-3 mb-4">
            <template x-for="(child, idx) in children" :key="idx">
                <div class="child-card">
                    <div class="child-avatar" x-text="child.full_name.charAt(0).toUpperCase()"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-semibold text-gray-900 text-sm truncate" x-text="child.full_name"></p>
                            <span x-show="child.existing_user_id"
                                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-primary/10 text-primary flex-shrink-0">
                                <i class="bi bi-link-45deg"></i><span x-text="t.existingMember"></span>
                            </span>
                        </div>
                        <p class="text-xs text-gray-400"
                           x-text="(child.gender === 'Male' ? t.male : t.female) + ' · ' + (child.birthdate || '')"></p>
                    </div>
                    <button @click="removeChild(idx)" type="button"
                            class="w-8 h-8 rounded-lg bg-red-50 text-red-400 hover:bg-red-100 flex items-center justify-center flex-shrink-0">
                        <i class="bi bi-x-lg text-xs"></i>
                    </button>
                </div>
            </template>
            <div x-show="children.length === 0" class="text-center py-6 text-gray-400 text-sm">
                <i class="bi bi-person-plus text-2xl mb-2 block"></i>
                <span x-text="t.noChildren"></span>
            </div>
        </div>

        {{-- Toggle button --}}
        <div x-show="!showChildForm">
            <button @click="showChildForm = true; errors = {}" type="button"
                    class="w-full flex items-center justify-center gap-2 p-3.5 border-2 border-dashed border-primary/40 rounded-xl text-primary font-semibold text-sm hover:bg-primary/5 transition-colors">
                <i class="bi bi-plus-lg"></i>
                <span x-text="t.addChild"></span>
            </button>
        </div>

        {{-- Inline child form --}}
        <template x-if="showChildForm">
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
                <h3 class="font-bold text-gray-800 text-sm mb-4" x-text="t.addChild"></h3>

                <div class="mb-4">
                    <label class="field-label" x-text="t.relationship"></label>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="rel in relationshipOptions" :key="rel.value">
                            <button type="button" @click="setNewChildRelationship(rel.value)"
                                    class="px-3 py-1.5 rounded-full text-sm font-medium border transition-colors flex items-center gap-1.5"
                                    :class="newChild.relationship === rel.value ? 'border-primary bg-primary/10 text-primary' : 'border-gray-200 text-gray-600'">
                                <i class="bi" :class="rel.icon"></i><span x-text="rel.label"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="tf-label" x-text="t.childName"></label>
                    <input type="text" x-model="newChild.full_name"
                           :class="errors.newChild ? 'field-error' : ''"
                           class="field-input" placeholder="Full name">
                </div>

                <div class="mb-4">
                    <label class="field-label" x-text="t.gender"></label>
                    <x-gender-toggle model="newChild.gender" male-label="t.male" female-label="t.female" />
                </div>

                <div class="wiz-no-label">
                    <label class="field-label" x-text="t.dob"></label>
                    <x-birthdate-dropdown name="new_child_birthdate" id="new_child_birthdate"
                        label="Date of Birth"
                        :required="true"
                        :max-age="0"
                        :min-age="0"
                        :min-year="(int)date('Y') - 25"
                        :max-year="(int)date('Y')" />
                </div>

                <div class="wiz-no-label">
                    <label class="field-label" x-text="t.nationality"></label>
                    <x-country-dropdown name="new_child_nationality" id="new_child_nationality"
                        label="Nationality" value="BH" />
                </div>

                {{-- Health conditions — checkbox enables the text box --}}
                <div class="mb-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <div class="relative flex-shrink-0 mt-0.5">
                            <input type="checkbox" x-model="newChild.hasHealth"
                                   @change="if (!newChild.hasHealth) newChild.health_conditions = ''" class="sr-only">
                            <div class="w-5 h-5 rounded-md border-2 flex items-center justify-center transition-all"
                                 :class="newChild.hasHealth ? 'bg-primary border-primary' : 'border-gray-300 bg-white'">
                                <i class="bi bi-check text-white text-xs" x-show="newChild.hasHealth"></i>
                            </div>
                        </div>
                        <span class="text-sm text-gray-700 leading-relaxed" x-text="t.healthToggle"></span>
                    </label>
                    <div x-show="newChild.hasHealth" x-transition class="mt-3">
                        <textarea x-model="newChild.health_conditions" class="field-input" rows="2"
                                  placeholder="Optional — e.g. asthma, diabetes" style="resize:none"></textarea>
                    </div>
                </div>

                <p x-show="errors.newChild" class="err-msg mb-3" x-text="t.fillRequired"></p>

                <div class="flex gap-3">
                    <button @click="showChildForm = false; errors = {}" type="button"
                            class="btn-ghost flex-1" x-text="t.cancel"></button>
                    <button @click="addChildToList()" type="button"
                            class="btn-primary flex-1" style="border-radius:12px" x-text="t.saveChild"></button>
                </div>
            </div>
        </template>
    </div>

    {{-- ── STEP 4 : Package selection ───────────────────────── --}}
    <div x-show="step === 4" class="wiz-step flex-1 px-5 py-6">
        <div class="mb-5">
            <h2 class="text-xl font-bold text-gray-900 mb-1" x-text="t.packagesTitle"></h2>
            <p class="text-sm text-gray-500" x-text="t.packagesSub"></p>
        </div>

        <div x-show="loadingPackages" class="flex items-center gap-2 text-primary text-sm py-4">
            <i class="bi bi-arrow-repeat animate-spin"></i>
            <span x-text="t.loadingPackages"></span>
        </div>

        <div x-show="!loadingPackages" class="space-y-6">
            {{-- The registrant (you) --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="flex items-center gap-3 px-4 py-3 bg-primary/5 border-b border-primary/10">
                    <div class="child-avatar" x-text="(account.full_name || 'Y').charAt(0).toUpperCase()"></div>
                    <div>
                        <p class="font-semibold text-gray-900 text-sm" x-text="account.full_name || t.you"></p>
                        <p class="text-xs text-gray-400" x-text="t.forYou"></p>
                    </div>
                </div>
                <div class="p-3 space-y-2">
                    <div x-show="(packagesData['self'] || []).length === 0"
                         class="text-center py-4 text-gray-400 text-sm">
                        <i class="bi bi-box block text-xl mb-1"></i>
                        <span x-text="t.noPackages"></span>
                    </div>
                    <template x-for="pkg in (packagesData['self'] || [])" :key="pkg.id">
                        <div>
                            <div class="pkg-card" :class="self.packages.includes(pkg.id) ? 'selected' : ''"
                                 @click="togglePackage(self, pkg.id)">
                                <div class="flex items-start gap-3">
                                    <div class="pkg-check mt-0.5"><i class="bi bi-check-lg text-xs"></i></div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900 text-sm" x-text="pkg.name"></p>
                                        <p class="text-xs text-gray-400 mt-0.5"
                                           x-text="(pkg.duration_months ? pkg.duration_months + ' ' + t.months : '') + (pkg.type ? ' · ' + pkg.type : '')"></p>
                                        <p x-show="pkg.description" class="text-xs text-gray-500 mt-1 line-clamp-2" x-text="pkg.description"></p>
                                        <div x-show="(pkg.schedule || []).length > 0" class="flex flex-wrap gap-1 mt-1.5">
                                            <template x-for="(s, si) in (pkg.schedule || [])" :key="si">
                                                <span class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-md bg-gray-100 text-gray-600">
                                                    <i class="bi bi-clock"></i>
                                                    <span x-text="dayShort(s.day) + ' · ' + fmtTime(s.start_time) + '–' + fmtTime(s.end_time)"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="font-bold text-primary text-base" x-text="parseFloat(pkg.price).toFixed(2)"></p>
                                        <p class="text-xs text-gray-400" x-text="t.currency"></p>
                                    </div>
                                </div>
                            </div>
                            <x-wizard-equipment-chips person="self" />
                        </div>
                    </template>
                    <p x-show="errors.packages" class="err-msg px-1" x-text="t.selectPackage"></p>
                </div>
            </div>

            {{-- One section per child --}}
            <template x-for="(child, idx) in children" :key="idx">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="flex items-center gap-3 px-4 py-3 bg-primary/5 border-b border-primary/10">
                        <div class="child-avatar" x-text="child.full_name.charAt(0).toUpperCase()"></div>
                        <div>
                            <p class="font-semibold text-gray-900 text-sm" x-text="child.full_name"></p>
                            <p class="text-xs text-gray-400"
                               x-text="(child.gender === 'Male' ? t.male : t.female) + (child.birthdate ? ' · ' + child.birthdate : '')"></p>
                        </div>
                    </div>
                    <div class="p-3 space-y-2">
                        <div x-show="(packagesData[idx] || []).length === 0"
                             class="text-center py-4 text-gray-400 text-sm">
                            <i class="bi bi-box block text-xl mb-1"></i>
                            <span x-text="t.noPackages"></span>
                        </div>
                        <template x-for="pkg in (packagesData[idx] || [])" :key="pkg.id">
                            <div>
                                <div class="pkg-card" :class="child.packages.includes(pkg.id) ? 'selected' : ''"
                                     @click="togglePackage(child, pkg.id)">
                                    <div class="flex items-start gap-3">
                                        <div class="pkg-check mt-0.5"><i class="bi bi-check-lg text-xs"></i></div>
                                        <div class="flex-1">
                                            <p class="font-semibold text-gray-900 text-sm" x-text="pkg.name"></p>
                                            <p class="text-xs text-gray-400 mt-0.5"
                                               x-text="(pkg.duration_months ? pkg.duration_months + ' ' + t.months : '') + (pkg.type ? ' · ' + pkg.type : '')"></p>
                                            <p x-show="pkg.description" class="text-xs text-gray-500 mt-1 line-clamp-2" x-text="pkg.description"></p>
                                            <div x-show="(pkg.schedule || []).length > 0" class="flex flex-wrap gap-1 mt-1.5">
                                                <template x-for="(s, si) in (pkg.schedule || [])" :key="si">
                                                    <span class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-md bg-gray-100 text-gray-600">
                                                        <i class="bi bi-clock"></i>
                                                        <span x-text="dayShort(s.day) + ' · ' + fmtTime(s.start_time) + '–' + fmtTime(s.end_time)"></span>
                                                    </span>
                                                </template>
                                            </div>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <p class="font-bold text-primary text-base" x-text="parseFloat(pkg.price).toFixed(2)"></p>
                                            <p class="text-xs text-gray-400" x-text="t.currency"></p>
                                        </div>
                                    </div>
                                </div>
                                <x-wizard-equipment-chips person="child" />
                            </div>
                        </template>
                        <p x-show="errors['child_packages_'+idx]" class="err-msg px-1" x-text="t.selectPackage"></p>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="parseFloat(totalAmount) > 0" class="mt-6 p-4 bg-primary/5 rounded-xl border border-primary/20 space-y-2">
            <div x-show="amountBreakdown.packages > 0" class="flex items-center justify-between text-sm">
                <span class="text-gray-500" x-text="t.subtotal"></span>
                <span class="font-medium text-gray-700" x-text="amountBreakdown.packages.toFixed(2) + ' ' + t.currency"></span>
            </div>
            <div x-show="amountBreakdown.regFees > 0" class="flex items-center justify-between text-sm">
                <span class="text-gray-500" x-text="t.regFee"></span>
                <span class="font-medium text-gray-700" x-text="amountBreakdown.regFees.toFixed(2) + ' ' + t.currency"></span>
            </div>
            <div x-show="amountBreakdown.equipment > 0" class="flex items-center justify-between text-sm">
                <span class="text-gray-500" x-text="t.equipment"></span>
                <span class="font-medium text-gray-700" x-text="amountBreakdown.equipment.toFixed(2) + ' ' + t.currency"></span>
            </div>
            <div class="flex items-center justify-between pt-2 border-t border-primary/15">
                <span class="font-semibold text-gray-700" x-text="t.totalAmount"></span>
                <span class="font-bold text-primary text-lg" x-text="totalAmount + ' ' + t.currency"></span>
            </div>
        </div>
    </div>

    {{-- ── STEP 5 : Review & Submit ─────────────────────────── --}}
    <div x-show="step === 5" class="wiz-step flex-1 px-5 py-6">
        <div class="mb-5">
            <h2 class="text-xl font-bold text-gray-900 mb-1" x-text="t.reviewTitle"></h2>
            <p class="text-sm text-gray-500" x-text="t.reviewSub"></p>
        </div>

        <div class="space-y-4 mb-6">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3" x-text="t.accountInfo"></p>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500" x-text="t.fullName"></span>
                        <span class="font-medium text-gray-900" x-text="account.full_name"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500" x-text="t.email"></span>
                        <span class="font-medium text-gray-900 truncate max-w-[180px]" x-text="account.email"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500" x-text="t.mobile"></span>
                        <span dir="ltr" class="font-medium text-gray-900" x-text="account.mobile_code + ' ' + account.mobile_number"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500" x-text="t.gender"></span>
                        <span class="font-medium text-gray-900" x-text="self.gender === 'Male' ? t.male : (self.gender === 'Female' ? t.female : self.gender)"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500" x-text="t.dob"></span>
                        <span class="font-medium text-gray-900" x-text="self.birthdate"></span>
                    </div>
                </div>
            </div>

            {{-- Your packages --}}
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3" x-text="t.packages"></p>
                <div x-show="self.packages.length === 0">
                    <p class="text-sm text-gray-400 italic" x-text="t.noPackagesSelected"></p>
                </div>
                <template x-for="pkgId in self.packages" :key="pkgId">
                    <div class="flex justify-between text-sm py-1">
                        <span class="text-gray-700"
                              x-text="(packagesData['self']||[]).find(p=>p.id==pkgId)?.name || pkgId"></span>
                        <span class="font-semibold text-gray-900"
                              x-text="parseFloat((packagesData['self']||[]).find(p=>p.id==pkgId)?.price||0).toFixed(2) + ' ' + t.currency"></span>
                    </div>
                </template>
                <div x-show="personRegFee(self, packagesData['self']||[]) > 0" class="flex justify-between text-sm py-1">
                    <span class="text-gray-500" x-text="t.regFee"></span>
                    <span class="font-medium text-gray-700" x-text="personRegFee(self, packagesData['self']||[]).toFixed(2) + ' ' + t.currency"></span>
                </div>
                <template x-for="eq in selectedEquipmentFor(self, packagesData['self']||[])" :key="eq.id">
                    <div class="flex justify-between text-sm py-1">
                        <span class="text-gray-500 flex items-center gap-1"><i class="bi bi-box-seam text-xs"></i><span x-text="eq.name + (eq.variantLabel ? (' — ' + eq.variantLabel) : '')"></span></span>
                        <span class="font-medium text-gray-700" x-text="parseFloat(eq.price).toFixed(2) + ' ' + t.currency"></span>
                    </div>
                </template>
            </div>

            {{-- Children --}}
            <template x-if="children.length > 0">
                <div class="space-y-3">
                    <template x-for="(child, idx) in children" :key="idx">
                        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="child-avatar text-xs" x-text="child.full_name.charAt(0).toUpperCase()"></div>
                                <p class="font-semibold text-gray-800 text-sm" x-text="child.full_name"></p>
                            </div>
                            <div x-show="child.packages.length === 0">
                                <p class="text-sm text-gray-400 italic" x-text="t.noPackagesSelected"></p>
                            </div>
                            <template x-for="pkgId in child.packages" :key="pkgId">
                                <div class="flex justify-between text-sm py-1">
                                    <span class="text-gray-700"
                                          x-text="(packagesData[idx]||[]).find(p=>p.id==pkgId)?.name || pkgId"></span>
                                    <span class="font-semibold text-gray-900"
                                          x-text="parseFloat((packagesData[idx]||[]).find(p=>p.id==pkgId)?.price||0).toFixed(2) + ' ' + t.currency"></span>
                                </div>
                            </template>
                            <div x-show="personRegFee(child, packagesData[idx]||[]) > 0" class="flex justify-between text-sm py-1">
                                <span class="text-gray-500" x-text="t.regFee"></span>
                                <span class="font-medium text-gray-700" x-text="personRegFee(child, packagesData[idx]||[]).toFixed(2) + ' ' + t.currency"></span>
                            </div>
                            <template x-for="eq in selectedEquipmentFor(child, packagesData[idx]||[])" :key="eq.id">
                                <div class="flex justify-between text-sm py-1">
                                    <span class="text-gray-500 flex items-center gap-1"><i class="bi bi-box-seam text-xs"></i><span x-text="eq.name + (eq.variantLabel ? (' — ' + eq.variantLabel) : '')"></span></span>
                                    <span class="font-medium text-gray-700" x-text="parseFloat(eq.price).toFixed(2) + ' ' + t.currency"></span>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            <div class="flex items-center justify-between p-4 bg-primary/5 rounded-xl border border-primary/20">
                <span class="font-bold text-gray-800" x-text="t.totalAmount"></span>
                <span class="font-bold text-primary text-xl" x-text="totalAmount + ' ' + t.currency"></span>
            </div>

            {{-- Payment proof — only relevant when there is an amount to pay --}}
            <div x-show="parseFloat(totalAmount) > 0" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1" x-text="t.paymentTitle"></p>
                <p class="text-sm text-gray-500 mb-3" x-text="t.paymentSub"></p>

                {{-- Upload dropzone (hidden while "pay later" is active) --}}
                <div x-show="!payLater">
                    {{-- Empty state --}}
                    <label x-show="!paymentProof" for="wiz-proof-input"
                           class="flex flex-col items-center justify-center gap-2 w-full py-6 px-4 border-2 border-dashed border-gray-200 rounded-xl cursor-pointer hover:border-primary hover:bg-primary/5 transition-colors text-center">
                        <i class="bi bi-cloud-arrow-up text-2xl text-primary"></i>
                        <span class="text-sm font-medium text-gray-700" x-text="t.uploadProof"></span>
                        <span class="text-xs text-gray-400" x-text="t.proofHint"></span>
                    </label>
                    <input type="file" id="wiz-proof-input" class="hidden"
                           accept="image/jpeg,image/png,image/heic,image/webp,application/pdf"
                           @change="onProofSelected($event)">

                    {{-- Selected state --}}
                    <div x-show="paymentProof" class="flex items-center gap-3 p-3 rounded-xl border border-green-200 bg-green-50">
                        <i class="bi bi-file-earmark-check text-green-600 text-xl"></i>
                        <span class="flex-1 text-sm font-medium text-gray-700 truncate" x-text="paymentProofName"></span>
                        <button type="button" @click="removeProof()" class="text-gray-400 hover:text-red-500 transition-colors">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- Pay later toggle --}}
                <button type="button" @click="togglePayLater()"
                        class="mt-3 flex items-center gap-3 w-full text-left">
                    <span class="w-5 h-5 rounded-md border-2 flex items-center justify-center flex-shrink-0 transition-colors"
                          :class="payLater ? 'border-primary bg-primary text-white' : 'border-gray-300'">
                        <i class="bi bi-check-lg text-xs" x-show="payLater"></i>
                    </span>
                    <span class="text-sm text-gray-600" x-text="t.payLater"></span>
                </button>

                <p x-show="errors.proof" class="err-msg mt-2" x-text="errors.proof"></p>
            </div>
        </div>

        <p x-show="errors.submit" class="err-msg text-center mb-3 bg-red-50 p-3 rounded-xl" x-text="errors.submit"></p>

        <button @click="submit()" class="btn-primary" :disabled="submitting">
            <span x-show="!submitting" x-text="t.submitBtn"></span>
            <span x-show="submitting" class="flex items-center justify-center gap-2">
                <i class="bi bi-arrow-repeat animate-spin"></i>
                <span x-text="t.submitting"></span>
            </span>
        </button>
    </div>

    {{-- ── STEP 6 : Success ─────────────────────────────────── --}}
    <div x-show="step === 6" class="wiz-step flex-1 flex flex-col items-center justify-center px-6 py-12 text-center">
        <div class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-6 shadow-sm">
            <i class="bi bi-check-lg text-green-500 text-3xl"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-900 mb-3" x-text="t.successTitle"></h2>
        <p class="text-gray-500 text-sm mb-4 leading-relaxed" x-text="t.successSub"></p>
        <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 max-w-xs">
            <i class="bi bi-hourglass-split text-amber-500 text-lg mb-2 block"></i>
            <p class="text-sm text-amber-800 leading-relaxed" x-text="t.pendingNote"></p>
        </div>
        <p class="text-xs text-gray-400 mt-6" x-text="t.redirecting"></p>
    </div>

    {{-- ── BOTTOM NAVIGATION ────────────────────────────────── --}}
    <div x-show="step >= 1 && step <= 5" class="bg-white border-t border-gray-100 px-5 py-4">
        <div class="flex gap-3">
            <button x-show="step >= 1" @click="prev()" type="button"
                    class="btn-ghost" style="min-width:80px" x-text="t.back"></button>
            <button x-show="step !== 5" @click="next()" type="button"
                    class="btn-primary" :disabled="loading"
                    x-text="t.next"></button>
        </div>
    </div>

    {{-- ── Email-OTP verification sheet ────────────────────────
         Shown when the email/phone matches an existing account; the
         code proves control before any family data is disclosed. --}}
    <template x-teleport="body">
        <div x-show="otpSheetOpen" x-cloak class="fixed inset-0 z-[70]" style="display:none">
            <div class="absolute inset-0 bg-black/40" x-transition.opacity @click="cancelOtp()"></div>
            <div class="absolute inset-x-0 bottom-0 max-h-[88vh] flex flex-col bg-white rounded-t-3xl shadow-2xl"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 :dir="lang === 'ar' ? 'rtl' : 'ltr'">
                <div class="flex-shrink-0 px-6 pt-5 pb-2 text-center">
                    <div class="w-12 h-1.5 rounded-full bg-gray-200 mx-auto mb-4"></div>
                    <div class="w-14 h-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mx-auto mb-3">
                        <i class="bi bi-shield-lock text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900" x-text="t.otpTitle"></h3>
                    <p class="text-sm text-gray-500 mt-1" x-text="t.otpSub.replace('{email}', otpEmailHint)"></p>
                </div>
                <div class="px-6 py-4">
                    <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                           x-model="otpCode" @keydown.enter.prevent="verifyOtpCode()"
                           @input="otpCode = otpCode.replace(/\D/g,''); otpError=''"
                           class="field-input text-center"
                           style="font-size:1.6rem; letter-spacing:0.5em; font-weight:700;"
                           placeholder="••••••">
                    <p x-show="otpError" class="err-msg mt-2 text-center" x-text="otpError"></p>
                    <div class="text-center mt-3">
                        <button type="button" @click="resendOtp()" :disabled="otpResending"
                                class="text-sm text-primary font-medium hover:underline disabled:opacity-50"
                                x-text="otpResending ? t.otpSending : t.otpResend"></button>
                    </div>
                </div>
                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 flex gap-3"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="cancelOtp()" class="btn-ghost flex-1" x-text="t.cancel"></button>
                    <button type="button" @click="verifyOtpCode()" class="btn-primary flex-1" :disabled="otpVerifying"
                            style="border-radius:12px"
                            x-text="otpVerifying ? t.otpVerifying : t.otpVerify"></button>
                </div>
            </div>
        </div>
    </template>

    {{-- ── Returning-member family sheet ───────────────────────
         Teleported to <body> so the fixed bottom-sheet escapes any
         transformed ancestor and resolves against the viewport. --}}
    <template x-teleport="body">
        <div x-show="familySheetOpen" x-cloak class="fixed inset-0 z-[70]" style="display:none">
            <div class="absolute inset-0 bg-black/40" x-transition.opacity @click="closeFamilySheet()"></div>
            <div class="absolute inset-x-0 bottom-0 max-h-[88vh] flex flex-col bg-white rounded-t-3xl shadow-2xl"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 :dir="lang === 'ar' ? 'rtl' : 'ltr'">
                {{-- Header --}}
                <div class="flex-shrink-0 px-6 pt-5 pb-4 text-center">
                    <div class="w-12 h-1.5 rounded-full bg-gray-200 mx-auto mb-4"></div>
                    <div class="w-14 h-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center mx-auto mb-3">
                        <i class="bi bi-people-fill text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900" x-text="t.familyTitle"></h3>
                    <p class="text-sm text-gray-500 mt-1"
                       x-text="t.familySub.replace('{name}', familyOwnerName)"></p>
                </div>

                {{-- Relative list --}}
                <div class="flex-1 overflow-y-auto px-5 py-2 space-y-2">
                    <template x-for="rel in foundRelatives" :key="rel.id">
                        <button type="button" @click="relativePicked[rel.id] = !relativePicked[rel.id]"
                                class="w-full flex items-center gap-3 p-3 rounded-2xl border-2 transition-all text-start"
                                :class="relativePicked[rel.id] ? 'border-primary bg-primary/5' : 'border-gray-100 bg-white'">
                            <div class="child-avatar flex-shrink-0" x-text="rel.full_name.charAt(0).toUpperCase()"></div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="font-semibold text-gray-900 text-sm truncate" x-text="rel.full_name"></p>
                                    <span x-show="rel.already_member" class="text-[10px] px-1.5 py-0.5 rounded-full bg-green-100 text-green-700 font-medium flex-shrink-0" x-text="t.enrolledBadge"></span>
                                </div>
                                <p class="text-xs text-gray-400"
                                   x-text="(rel.gender === 'Male' ? t.male : t.female) + (rel.birthdate ? ' · ' + rel.birthdate : '')"></p>
                            </div>
                            <span class="w-6 h-6 rounded-lg border-2 flex items-center justify-center flex-shrink-0 transition-colors"
                                  :class="relativePicked[rel.id] ? 'border-primary bg-primary text-white' : 'border-gray-300'">
                                <i class="bi bi-check-lg text-xs" x-show="relativePicked[rel.id]"></i>
                            </span>
                        </button>
                    </template>
                </div>

                {{-- Sticky actions --}}
                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 flex gap-3"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="closeFamilySheet()" class="btn-ghost flex-1" x-text="t.familySkip"></button>
                    <button type="button" @click="includePickedRelatives()" class="btn-primary flex-1"
                            style="border-radius:12px"
                            x-text="t.familyInclude + ' (' + Object.values(relativePicked).filter(Boolean).length + ')'"></button>
                </div>
            </div>
        </div>
    </template>

</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function wizard() {
    return {
        lang: 'en',
        step: 0,

        account: {
            full_name: '',
            email: '',
            mobile_code: '+973',
            mobile_number: '',
            nationality: '',
        },

        // The registrant's own profile (they always register themselves first).
        self: {
            gender: '',
            birthdate: '',
            hasHealth: false,
            health_conditions: '',
            packages: [],
            equipment: [],
            equipmentVariants: {},
            ownedEquipment: [],
        },

        // Club-wide default joining fee (used when a package has no override).
        clubEnrollmentFee: @json((float) session('club.context.enrollment_fee', 0)),

        relationshipOptions: [
            { value: 'son',      label: 'Son',      icon: 'bi-person',       gender: 'Male'   },
            { value: 'daughter', label: 'Daughter', icon: 'bi-person-heart', gender: 'Female' },
            { value: 'spouse',   label: 'Spouse',   icon: 'bi-heart',        gender: ''       },
            { value: 'other',    label: 'Other',    icon: 'bi-people',       gender: ''       },
        ],

        // Top-level cache: key 'self' or child index (number) → array of packages.
        packagesData: {},

        children: [],
        newChild: {
            full_name: '', gender: '', health_conditions: '', hasHealth: false, relationship: 'son',
        },
        showChildForm: false,

        agreedToTerms: false,
        // Returning-member detection (step 2 → 3). When the email/phone matches an
        // existing account we surface their linked relatives so they can be pulled
        // into this registration instead of being re-entered.
        lookupChecked: false,           // guard: only run the lookup once per session
        familySheetOpen: false,
        familyOwnerName: '',
        foundRelatives: [],             // [{id, full_name, gender, birthdate, already_member}]
        relativePicked: {},             // { [id]: true }
        // Email-OTP gate: an existing account must prove control of its email before
        // we disclose any relatives or let it be reused.
        otpSheetOpen: false,
        otpCode: '',
        otpEmailHint: '',
        otpError: '',
        otpVerifying: false,
        otpResending: false,
        // Payment proof collected on the final step. `payLater` lets the member
        // skip the upload and continue; the club collects payment afterwards.
        payLater: false,
        paymentProof: null,        // base64 data-URL of the uploaded proof
        paymentProofName: '',
        loading: false,
        loadingPackages: false,
        submitting: false,
        errors: {},

        clubSlug:  @json(session('club.context.slug', '')),
        clubName:  @json(session('club.context.name', '')),
        clubLogo:  @json(session('club.context.logo') ? asset('storage/' . session('club.context.logo')) : ''),
        clubCover: @json(session('club.context.cover_image') ? asset('storage/' . session('club.context.cover_image')) : ''),
        clubSplash: @json(session('club.context.splash') ? asset('storage/' . session('club.context.splash')) : ''),

        // Bilingual rich-HTML registration content (sanitised server-side).
        clubTermsEn: @js(session('club.context.terms')),
        clubTermsAr: @js(session('club.context.terms_ar')),
        clubReqsEn:  @js(session('club.context.requirements')),
        clubReqsAr:  @js(session('club.context.requirements_ar')),

        T: {
            en: {
                joining: 'Joining', stepLabel: 'Step',
                detailsTitle: 'Your details', detailsSub: 'This creates your account and membership profile',
                fullName: 'Full Name', email: 'Email Address', password: 'Password',
                mobile: 'Mobile Number', nationality: 'Nationality',
                gender: 'Gender', male: 'Male', female: 'Female',
                dob: 'Date of Birth', health: 'Chronic Health Conditions',
                healthToggle: 'Has chronic health conditions',
                optional: 'Optional',
                kidsTitle: 'Add children', kidsSub: 'Optional — add children to register under your account. Skip if you\'re only registering yourself.',
                existingMember: 'Linked',
                otpTitle: 'Verify it\'s you', otpSub: 'We emailed a 6-digit code to {email}. Enter it to continue.',
                otpEnter: 'Enter the code we sent you.', otpWrong: 'Incorrect or expired code. Please try again.',
                otpVerify: 'Verify', otpVerifying: 'Verifying…', otpResend: 'Resend code', otpSending: 'Sending…',
                otpResent: 'A new code has been sent.',
                familyTitle: 'Welcome back!', familySub: 'We found family members linked to {name}. Select who to include in this registration.',
                familySkip: 'Skip', familyInclude: 'Include',
                addChild: 'Add a child', childName: "Child's name",
                saveChild: 'Save', cancel: 'Cancel',
                noChildren: 'No children added — that\'s fine, this step is optional.',
                fillRequired: 'Please fill in name, gender and date of birth',
                packagesTitle: 'Select packages', packagesSub: 'Choose one or more packages per person',
                loadingPackages: 'Loading available packages…',
                noPackages: 'No packages available for this profile',
                you: 'You', forYou: 'Your membership',
                months: 'months', currency: 'BHD',
                equipmentLabel: 'Equipment for this activity', alreadyOwned: 'Already owned', alreadyHaveIt: 'I already have it', requiredBadge: 'Required',
                equipmentOwnHint: 'Already train elsewhere? Tick anything you already own to remove it from the bill.',
                relationship: 'Relationship', regFee: 'Registration fee', enrolledBadge: 'Enrolled',
                subtotal: 'Packages subtotal', equipment: 'Equipment',
                totalAmount: 'Total', selectPackage: 'Please select at least one package',
                termsTitle: 'Terms & Conditions', termsSub: 'Please read and accept to continue',
                termsAgree: 'I have read and agree to the Terms & Conditions',
                reviewTitle: 'Review & Submit', reviewSub: 'Check your details before submitting',
                paymentTitle: 'Payment', paymentSub: 'Upload your proof of payment to speed up approval.',
                uploadProof: 'Upload proof of payment', proofHint: 'JPG, PNG or PDF · up to 10MB',
                payLater: 'I\'ll pay later', proofRequired: 'Please upload proof of payment or choose “I\'ll pay later”.',
                proofTooLarge: 'File is too large. Maximum size is 10MB.',
                accountInfo: 'Account', packages: 'Your packages', noPackagesSelected: 'No packages selected',
                submitBtn: 'Submit Registration', submitting: 'Submitting…',
                successTitle: 'Registration submitted!',
                successSub: 'Please check your email to verify your account.',
                pendingNote: 'Your registration is pending approval by the club. You\'ll be notified once confirmed.',
                redirecting: 'Redirecting you in a moment…',
                next: 'Next', back: 'Back',
                required: 'This field is required',
                invalidEmail: 'Please enter a valid email address',
                passwordMin: 'Password must be at least 8 characters',
                agreeTerms: 'Please accept the terms and conditions',
            },
            ar: {
                joining: 'انضمام إلى', stepLabel: 'خطوة',
                detailsTitle: 'بياناتك', detailsSub: 'هذا ينشئ حسابك وملف العضوية الخاص بك',
                fullName: 'الاسم الكامل', email: 'البريد الإلكتروني', password: 'كلمة المرور',
                mobile: 'رقم الهاتف', nationality: 'الجنسية',
                gender: 'الجنس', male: 'ذكر', female: 'أنثى',
                dob: 'تاريخ الميلاد', health: 'الحالات الصحية المزمنة',
                healthToggle: 'لديه حالات صحية مزمنة',
                optional: 'اختياري',
                kidsTitle: 'إضافة أطفال', kidsSub: 'اختياري — أضف أطفالاً لتسجيلهم ضمن حسابك. تخطَّ هذه الخطوة إذا كنت تسجّل لنفسك فقط.',
                existingMember: 'مرتبط',
                otpTitle: 'تأكيد هويتك', otpSub: 'أرسلنا رمزاً من ٦ أرقام إلى {email}. أدخله للمتابعة.',
                otpEnter: 'أدخل الرمز الذي أرسلناه إليك.', otpWrong: 'رمز غير صحيح أو منتهي. حاول مرة أخرى.',
                otpVerify: 'تأكيد', otpVerifying: 'جارٍ التأكيد…', otpResend: 'إعادة إرسال الرمز', otpSending: 'جارٍ الإرسال…',
                otpResent: 'تم إرسال رمز جديد.',
                familyTitle: 'مرحباً بعودتك!', familySub: 'وجدنا أفراد عائلة مرتبطين بـ {name}. اختر من تريد تضمينه في هذا التسجيل.',
                familySkip: 'تخطّي', familyInclude: 'تضمين',
                addChild: 'إضافة طفل', childName: 'اسم الطفل',
                saveChild: 'حفظ', cancel: 'إلغاء',
                noChildren: 'لم يتم إضافة أطفال — لا بأس، هذه الخطوة اختيارية.',
                fillRequired: 'يرجى إدخال الاسم والجنس وتاريخ الميلاد',
                packagesTitle: 'اختر الباقات', packagesSub: 'اختر باقة أو أكثر لكل شخص',
                loadingPackages: 'جارٍ تحميل الباقات المتاحة…',
                noPackages: 'لا توجد باقات متاحة لهذا الملف',
                you: 'أنت', forYou: 'عضويتك',
                months: 'أشهر', currency: 'د.ب',
                equipmentLabel: 'معدات هذا النشاط', alreadyOwned: 'مملوكة', alreadyHaveIt: 'لدي هذا بالفعل', requiredBadge: 'مطلوب',
                equipmentOwnHint: 'تتدرّب في نادٍ آخر؟ أشِّر على أي معدات تملكها بالفعل لإزالتها من الفاتورة.',
                relationship: 'صلة القرابة', regFee: 'رسوم التسجيل', enrolledBadge: 'مُسجّل',
                subtotal: 'إجمالي الباقات', equipment: 'المعدات',
                totalAmount: 'الإجمالي', selectPackage: 'يرجى اختيار باقة واحدة على الأقل',
                termsTitle: 'الشروط والأحكام', termsSub: 'يرجى القراءة والموافقة للمتابعة',
                termsAgree: 'لقد قرأت وأوافق على الشروط والأحكام',
                reviewTitle: 'مراجعة وتقديم', reviewSub: 'راجع بياناتك قبل التقديم',
                paymentTitle: 'الدفع', paymentSub: 'ارفع إثبات الدفع لتسريع الموافقة.',
                uploadProof: 'رفع إثبات الدفع', proofHint: 'JPG أو PNG أو PDF · حتى ١٠ ميجابايت',
                payLater: 'سأدفع لاحقاً', proofRequired: 'يرجى رفع إثبات الدفع أو اختيار «سأدفع لاحقاً».',
                proofTooLarge: 'الملف كبير جداً. الحد الأقصى ١٠ ميجابايت.',
                accountInfo: 'الحساب', packages: 'باقاتك', noPackagesSelected: 'لم يتم اختيار أي باقة',
                submitBtn: 'تقديم الطلب', submitting: 'جارٍ التقديم…',
                successTitle: 'تم تقديم طلبك!',
                successSub: 'يرجى التحقق من بريدك الإلكتروني لتأكيد حسابك.',
                pendingNote: 'طلبك قيد المراجعة من قِبل النادي. سيتم إشعارك عند التأكيد.',
                redirecting: 'جارٍ توجيهك…',
                next: 'التالي', back: 'رجوع',
                required: 'هذا الحقل مطلوب',
                invalidEmail: 'يرجى إدخال بريد إلكتروني صحيح',
                passwordMin: 'كلمة المرور يجب أن تكون ٨ أحرف على الأقل',
                agreeTerms: 'يرجى قبول الشروط والأحكام',
            },
        },

        get t() { return this.T[this.lang]; },

        // Pick the registration content for the active language, falling back to
        // the other language if only one was provided.
        get termsHtml() {
            const raw = this.lang === 'ar'
                ? (this.clubTermsAr || this.clubTermsEn || '')
                : (this.clubTermsEn || this.clubTermsAr || '');
            return this.stripLeadingTermsHeading(raw);
        },
        // Drop a leading "Terms & Conditions" heading from the saved content — the
        // step title already shows it, so it would otherwise appear twice.
        stripLeadingTermsHeading(html) {
            if (!html) return html;
            return html.replace(
                /^\s*<h[1-3][^>]*>\s*(terms\s*(&amp;|&|and)?\s*conditions|الشروط\s*والأحكام)\s*<\/h[1-3]>\s*/i,
                ''
            );
        },
        get reqsHtml() {
            return this.lang === 'ar'
                ? (this.clubReqsAr || this.clubReqsEn || '')
                : (this.clubReqsEn || this.clubReqsAr || '');
        },

        get totalAmount() {
            const b = this.amountBreakdown;
            return (b.packages + b.regFees + b.equipment).toFixed(2);
        },

        // Itemised cost across self + all children, so the total can be explained
        // line-by-line wherever it's shown (selection step + review step).
        get amountBreakdown() {
            let packages = 0, regFees = 0, equipment = 0;
            const tally = (person, avail) => {
                if ((person.packages || []).length === 0) return;
                person.packages.forEach(id => {
                    const pkg = avail.find(p => p.id == id);
                    if (pkg) packages += parseFloat(pkg.price);
                });
                regFees += this.personRegFee(person, avail);
                this.selectedEquipmentFor(person, avail).forEach(eq => { equipment += parseFloat(eq.price); });
            };
            tally(this.self, this.packagesData['self'] || []);
            this.children.forEach((child, i) => tally(child, this.packagesData[i] || []));
            return { packages, regFees, equipment };
        },

        init() {},

        // Read the chosen proof image/PDF as a base64 data-URL for the JSON submit.
        onProofSelected(e) {
            const file = e.target.files[0];
            if (!file) return;
            if (file.size > 10 * 1024 * 1024) {
                window.showToast && window.showToast('error', this.t.proofTooLarge);
                e.target.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = () => {
                this.paymentProof = reader.result;
                this.paymentProofName = file.name;
                this.payLater = false;            // uploading proof clears "pay later"
                this.errors.proof = '';
            };
            reader.readAsDataURL(file);
        },
        removeProof() {
            this.paymentProof = null;
            this.paymentProofName = '';
            const el = document.getElementById('wiz-proof-input');
            if (el) el.value = '';
        },
        togglePayLater() {
            this.payLater = !this.payLater;
            if (this.payLater) this.removeProof();   // pay later clears any upload
            this.errors.proof = '';
        },

        // Sync values from Blade component hidden inputs into Alpine state
        syncFromDOM() {
            const mobileCode   = document.getElementById('country_code');
            const mobileNumber = document.getElementById('wizard_mobile_number');
            if (mobileCode)   this.account.mobile_code   = mobileCode.value   || this.account.mobile_code;
            if (mobileNumber) this.account.mobile_number = mobileNumber.value || this.account.mobile_number;

            const nationality = document.getElementById('nationality');
            if (nationality) this.account.nationality = nationality.value || this.account.nationality;

            // Gender is bound directly via the inline selector; only birthdate
            // still comes from a Blade component's hidden input.
            const selfBirthdate = document.getElementById('self_birthdate');
            if (selfBirthdate) this.self.birthdate = selfBirthdate.value || '';
        },

        goTo(n) {
            this.step = n;
            this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
        },

        async next() {
            this.syncFromDOM();
            if (!this.validate()) return;

            // Leaving step 2 → run the smart lookup once. If the email/phone matches
            // an existing account with relatives, pop the family sheet and let the
            // user pick who to include before continuing to step 3.
            if (this.step === 2 && !this.lookupChecked) {
                const opened = await this.runFamilyLookup();
                if (opened) return;   // sheet is open; it advances the step on close
            }

            const nextStep = this.step + 1;
            this.goTo(nextStep);
            if (nextStep === 4) {
                await this.loadPackages();
            }
        },

        _lookupHeaders() {
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            };
        },
        _identityBody(extra = {}) {
            return JSON.stringify({
                email:         this.account.email,
                mobile_code:   this.account.mobile_code,
                mobile_number: this.account.mobile_number,
                club_slug:     this.clubSlug,
                ...extra,
            });
        },

        // Returns true if a sheet was opened (caller should stop and let the sheet
        // drive navigation). If the email/phone matches an existing account we email
        // a one-time code and open the OTP sheet — relatives are disclosed only AFTER
        // the code is verified. Best-effort: any failure just proceeds as a new signup.
        async runFamilyLookup() {
            this.lookupChecked = true;
            try {
                const res  = await fetch('/register/wizard/lookup', {
                    method: 'POST', headers: this._lookupHeaders(),
                    credentials: 'same-origin', body: this._identityBody(),
                });
                const data = await res.json();
                if (data.found && data.verification_required) {
                    this.otpEmailHint  = data.email_hint || '';
                    this.otpCode       = '';
                    this.otpError      = '';
                    this.otpSheetOpen  = true;
                    return true;
                }
            } catch (e) { /* best-effort — never block registration on lookup */ }
            return false;
        },

        async verifyOtpCode() {
            const code = (this.otpCode || '').trim();
            if (code.length < 4) { this.otpError = this.t.otpEnter; return; }
            this.otpVerifying = true;
            this.otpError     = '';
            try {
                const res  = await fetch('/register/wizard/verify-otp', {
                    method: 'POST', headers: this._lookupHeaders(),
                    credentials: 'same-origin', body: this._identityBody({ code }),
                });
                const data = await res.json();
                if (res.ok && data.verified) {
                    // Proven — disclose & stage relatives, then hand off to the family sheet.
                    const existingIds = this.children.map(c => c.existing_user_id).filter(Boolean);
                    // Show ALL linked relatives — including ones already enrolled — so the
                    // family is always recognised. Already-enrolled members are flagged and
                    // not pre-selected (they need no new enrolment), but can still be added.
                    const relatives = (data.dependents || []).filter(
                        d => !existingIds.includes(d.id) && d.birthdate && d.gender
                    );
                    this.otpSheetOpen = false;
                    if (relatives.length > 0) {
                        this.familyOwnerName = data.name || '';
                        this.foundRelatives  = relatives;
                        this.relativePicked  = {};
                        relatives.forEach(r => this.relativePicked[r.id] = !r.already_member);
                        this.familySheetOpen = true;
                    } else {
                        // Verified but no relatives to import — just continue.
                        this.goTo(3);
                    }
                } else {
                    this.otpError = data.message || this.t.otpWrong;
                }
            } catch (e) {
                this.otpError = this.t.otpWrong;
            }
            this.otpVerifying = false;
        },

        async resendOtp() {
            this.otpResending = true;
            this.otpError     = '';
            try {
                await fetch('/register/wizard/lookup', {
                    method: 'POST', headers: this._lookupHeaders(),
                    credentials: 'same-origin', body: this._identityBody(),
                });
                window.showToast && window.showToast('success', this.t.otpResent);
            } catch (e) { /* ignore */ }
            this.otpResending = false;
        },

        cancelOtp() {
            // Stay on step 2 so they can edit the email/phone or retry — they cannot
            // proceed under an existing account without verifying.
            this.otpSheetOpen = false;
            this.lookupChecked = false;
        },

        includePickedRelatives() {
            this.foundRelatives.forEach(r => {
                if (!this.relativePicked[r.id]) return;
                this.children.push({
                    full_name:         r.full_name,
                    gender:            r.gender,
                    birthdate:         r.birthdate,
                    nationality:       '',
                    health_conditions: '',
                    packages:          [],
                    equipment:         [],
                    equipmentVariants: {},
                    ownedEquipment:    [],
                    existing_user_id:  r.id,        // marks this as an existing person
                    already_member:    !!r.already_member,
                    relationship_type: r.relationship_type || '',
                });
            });
            this.closeFamilySheet();
        },

        closeFamilySheet() {
            this.familySheetOpen = false;
            // Continue on to step 3 now that the choice has been made.
            this.goTo(3);
        },

        prev() {
            this.errors = {};
            // Returning to the details step lets them edit email/phone — allow the
            // smart lookup to run again on the next forward move.
            if (this.step - 1 === 2) this.lookupChecked = false;
            this.goTo(this.step - 1);
        },

        validate() {
            this.errors = {};
            // Step 1 — terms
            if (this.step === 1) {
                if (!this.agreedToTerms) this.errors.terms = true;
            }
            // Step 2 — your details (account + profile)
            if (this.step === 2) {
                if (!this.account.full_name.trim()) this.errors.full_name = true;
                const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRe.test(this.account.email)) this.errors.email = true;
                if (!this.account.mobile_number.trim()) this.errors.mobile_number = true;
                if (!this.self.gender)    this.errors.gender    = true;
                if (!this.self.birthdate) this.errors.birthdate = true;
            }
            // Step 3 — children are optional, nothing to validate.
            // Step 4 — packages
            if (this.step === 4) {
                // At least one person (registrant or any child) must be enrolling in
                // at least one package — otherwise there is nothing to submit.
                const anyPackages = this.self.packages.length > 0
                    || this.children.some(c => (c.packages || []).length > 0);
                if (!anyPackages) this.errors.packages = true;

                // Every NEW (non-enrolled) child included must have a package — no blank
                // enrolments. Already-enrolled relatives are optional (shown for context,
                // or to add an extra package/equipment).
                this.children.forEach((c, i) => {
                    if (c.already_member) return;
                    if ((c.packages || []).length === 0) this.errors['child_packages_' + i] = true;
                });
            }
            return Object.keys(this.errors).length === 0;
        },

        addChildToList() {
            this.errors = {};
            const birthdate   = document.getElementById('new_child_birthdate')?.value || '';
            const nationality = document.getElementById('new_child_nationality')?.value || '';

            if (!this.newChild.full_name.trim() || !this.newChild.gender || !birthdate) {
                this.errors.newChild = true;
                return;
            }
            this.children.push({
                full_name:         this.newChild.full_name,
                gender:            this.newChild.gender,
                birthdate:         birthdate,
                nationality:       nationality,
                health_conditions: this.newChild.health_conditions,
                relationship:      this.newChild.relationship || 'son',
                packages:          [],
                equipment:         [],
                equipmentVariants: {},
                ownedEquipment:    [],
            });
            this.newChild = { full_name: '', gender: '', health_conditions: '', hasHealth: false, relationship: 'son' };
            this.showChildForm = false;
        },

        removeChild(index) {
            this.children.splice(index, 1);
        },

        async loadPackages() {
            this.loadingPackages = true;
            const newData = {};

            // The registrant always gets a package list.
            try {
                const url  = `/register/wizard/packages?club_slug=${encodeURIComponent(this.clubSlug)}&birthdate=${encodeURIComponent(this.self.birthdate)}&gender=${this.self.gender}`;
                const res  = await fetch(url);
                const data = await res.json();
                newData['self'] = data.packages || [];
                // Authoritative club joining fee (don't depend on a possibly-stale session).
                if (data.enrollment_fee !== undefined) this.clubEnrollmentFee = parseFloat(data.enrollment_fee) || 0;
            } catch (e) {
                console.error('Failed to load packages for self', e);
                newData['self'] = [];
            }

            // Plus one list per child.
            for (let i = 0; i < this.children.length; i++) {
                const child = this.children[i];
                try {
                    const url  = `/register/wizard/packages?club_slug=${encodeURIComponent(this.clubSlug)}&birthdate=${encodeURIComponent(child.birthdate)}&gender=${child.gender}`;
                    const res  = await fetch(url);
                    const data = await res.json();
                    newData[i] = data.packages || [];
                } catch (e) {
                    console.error(`Failed to load packages for child ${i}`, e);
                    newData[i] = [];
                }
            }

            this.packagesData    = newData;
            this.loadingPackages = false;
        },

        togglePackage(person, packageId) {
            const idx = person.packages.indexOf(packageId);
            if (idx === -1) person.packages.push(packageId);
            else            person.packages.splice(idx, 1);
            this.syncEquipmentDefaults(person, this.availForPerson(person));
        },

        // The package list available to a person ('self' key, or child index).
        availForPerson(person) {
            if (person === this.self) return this.packagesData['self'] || [];
            const i = this.children.indexOf(person);
            return this.packagesData[i] || [];
        },

        // Unique gear across a person's selected packages.
        availableEquipmentFor(person, availList) {
            const map = {};
            (person.packages || []).forEach(pid => {
                const pkg = (availList || []).find(p => p.id == pid);
                (pkg && pkg.equipment || []).forEach(eq => { map[eq.id] = eq; });
            });
            return Object.values(map);
        },

        // Cheapest in-stock variant of an equipment item (the sensible default).
        defaultVariantFor(eq) {
            const vs = (eq.variants || []).filter(v => v.in_stock);
            const pool = vs.length ? vs : (eq.variants || []);
            if (!pool.length) return null;
            return pool.reduce((a, b) => (b.price < a.price ? b : a), pool[0]);
        },

        // Pre-tick required, not-owned gear; drop selections no longer offered.
        syncEquipmentDefaults(person, availList) {
            if (!person.equipment) person.equipment = [];
            if (!person.equipmentVariants) person.equipmentVariants = {};
            if (!person.ownedEquipment) person.ownedEquipment = [];
            const available    = this.availableEquipmentFor(person, availList);
            const availableIds  = available.map(e => e.id);
            person.equipment = person.equipment.filter(id => availableIds.includes(id));
            person.ownedEquipment = person.ownedEquipment.filter(id => availableIds.includes(id));
            // Drop variant choices for gear no longer offered.
            Object.keys(person.equipmentVariants).forEach(id => {
                if (!availableIds.includes(parseInt(id))) delete person.equipmentVariants[id];
            });
            available.forEach(eq => {
                // Existing members who already own an item: default it to "I already have".
                if (eq.already_owned && !person.ownedEquipment.includes(eq.id)) {
                    person.ownedEquipment.push(eq.id);
                }
                const owned = person.ownedEquipment.includes(eq.id);
                // Pre-tick required gear, but never anything the person says they own.
                if (eq.is_required && !owned && !person.equipment.includes(eq.id)) {
                    person.equipment.push(eq.id);
                }
                // Owned gear is never billed — make sure it's off the charged list.
                if (owned) {
                    const j = person.equipment.indexOf(eq.id);
                    if (j !== -1) person.equipment.splice(j, 1);
                    delete person.equipmentVariants[eq.id];
                }
                // A required variant item needs a default variant chosen.
                if (eq.has_variants && person.equipment.includes(eq.id) && !person.equipmentVariants[eq.id]) {
                    const dv = this.defaultVariantFor(eq);
                    if (dv) person.equipmentVariants[eq.id] = dv.id;
                }
            });
        },

        // Plain (no-variant) gear: a simple on/off tick (only when not owned).
        toggleEquipment(person, equipmentId) {
            if (!person.equipment) person.equipment = [];
            if ((person.ownedEquipment || []).includes(equipmentId)) return;
            const idx = person.equipment.indexOf(equipmentId);
            if (idx === -1) person.equipment.push(equipmentId);
            else            person.equipment.splice(idx, 1);
        },

        isEquipmentOwned(person, equipmentId) {
            return (person.ownedEquipment || []).includes(equipmentId);
        },

        // "I already have it" — exclude an item from the bill (overrides required),
        // or, when un-checked, fold it back in (re-ticking required / default variant).
        toggleOwned(person, eq) {
            if (!person.ownedEquipment) person.ownedEquipment = [];
            if (!person.equipment) person.equipment = [];
            if (!person.equipmentVariants) person.equipmentVariants = {};
            const oi = person.ownedEquipment.indexOf(eq.id);
            if (oi === -1) {
                // Mark owned → drop from the charged list + clear any variant choice.
                person.ownedEquipment.push(eq.id);
                const j = person.equipment.indexOf(eq.id);
                if (j !== -1) person.equipment.splice(j, 1);
                delete person.equipmentVariants[eq.id];
            } else {
                // Un-own → put it back on the bill if required / pick a default variant.
                person.ownedEquipment.splice(oi, 1);
                if (eq.is_required && !person.equipment.includes(eq.id)) person.equipment.push(eq.id);
                if (eq.has_variants && person.equipment.includes(eq.id) && !person.equipmentVariants[eq.id]) {
                    const dv = this.defaultVariantFor(eq);
                    if (dv) person.equipmentVariants[eq.id] = dv.id;
                }
            }
        },

        equipmentVariantId(person, equipmentId) {
            return (person.equipmentVariants || {})[equipmentId] || null;
        },

        // Variant gear: picking a variant ticks the item; tapping the chosen one
        // again unticks it (unless it's required).
        selectEquipmentVariant(person, eq, variantId) {
            if (!person.equipment) person.equipment = [];
            if (!person.equipmentVariants) person.equipmentVariants = {};
            const current = person.equipmentVariants[eq.id] || null;
            if (current === variantId && !eq.is_required) {
                delete person.equipmentVariants[eq.id];
                const i = person.equipment.indexOf(eq.id);
                if (i !== -1) person.equipment.splice(i, 1);
                return;
            }
            person.equipmentVariants[eq.id] = variantId;
            if (!person.equipment.includes(eq.id)) person.equipment.push(eq.id);
        },

        // Selected gear, each resolved to its effective price + label (the chosen
        // variant's, when it has variants).
        selectedEquipmentFor(person, availList) {
            return this.availableEquipmentFor(person, availList)
                .filter(eq => (person.equipment || []).includes(eq.id))
                .map(eq => {
                    if (!eq.has_variants) return eq;
                    const vid = this.equipmentVariantId(person, eq.id);
                    const v = (eq.variants || []).find(x => x.id === vid);
                    if (!v) return null;   // variant item with no choice → not counted
                    return { ...eq, price: v.price, variantLabel: v.label };
                })
                .filter(Boolean);
        },

        // Per-person first-time registration fee: first selected package's override,
        // else the club default. Existing relatives already enrolled pay nothing.
        personRegFee(person, availList) {
            if (person.already_member) return 0;
            const ids = person.packages || [];
            if (ids.length === 0) return 0;
            const pkg = (availList || []).find(p => p.id == ids[0]);
            if (pkg && pkg.registration_fee !== null && pkg.registration_fee !== undefined && pkg.registration_fee !== '') {
                return parseFloat(pkg.registration_fee);
            }
            return parseFloat(this.clubEnrollmentFee) || 0;
        },

        relationshipLabel(val) {
            const r = this.relationshipOptions.find(o => o.value === val);
            return r ? r.label : 'Member';
        },

        // Schedule formatting for the package cards.
        dayShort(d) {
            if (!d) return '';
            const map = { saturday:'Sat', sunday:'Sun', monday:'Mon', tuesday:'Tue', wednesday:'Wed', thursday:'Thu', friday:'Fri' };
            const key = String(d).toLowerCase();
            return map[key] || (d.charAt(0).toUpperCase() + d.slice(1, 3));
        },
        fmtTime(t) {
            if (!t) return '';
            const parts = String(t).split(':');
            let h = parseInt(parts[0], 10);
            const m = parts[1] || '00';
            if (isNaN(h)) return t;
            const ap = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return `${h}:${m} ${ap}`;
        },
        setNewChildRelationship(val) {
            this.newChild.relationship = val;
            const r = this.relationshipOptions.find(o => o.value === val);
            if (r && r.gender) this.newChild.gender = r.gender;
        },

        async submit() {
            this.syncFromDOM();
            this.errors     = {};

            // When there's an amount due, the member must either attach proof of
            // payment or explicitly choose "I'll pay later" before continuing.
            if (parseFloat(this.totalAmount) > 0 && !this.payLater && !this.paymentProof) {
                this.errors.proof = this.t.proofRequired;
                return;
            }

            this.submitting = true;

            const payload = {
                full_name:              this.account.full_name,
                email:                  this.account.email,
                mobile_code:            this.account.mobile_code,
                mobile_number:          this.account.mobile_number,
                nationality:            this.account.nationality,
                club_slug:              this.clubSlug,
                lang:                   this.lang,
                self_gender:            this.self.gender,
                self_birthdate:         this.self.birthdate,
                self_health_conditions: this.self.health_conditions,
                self_packages:          this.self.packages,
                self_equipment:         this.self.equipment,
                self_equipment_variants: this.self.equipmentVariants || {},
                self_owned_equipment:   this.self.ownedEquipment || [],
                // New children only — existing relatives go in `existing_dependents`.
                children: this.children.filter(c => !c.existing_user_id).map(c => ({
                    full_name:         c.full_name,
                    gender:            c.gender,
                    birthdate:         c.birthdate,
                    nationality:       c.nationality,
                    health_conditions: c.health_conditions,
                    relationship:      c.relationship || 'son',
                    packages:          c.packages,
                    equipment:         c.equipment || [],
                    equipment_variants: c.equipmentVariants || {},
                    owned_equipment:   c.ownedEquipment || [],
                })),
                // Existing relatives pulled in via the smart lookup — enrol only.
                existing_dependents: this.children
                    .filter(c => c.existing_user_id)
                    .map(c => ({ id: c.existing_user_id, packages: c.packages, equipment: c.equipment || [], equipment_variants: c.equipmentVariants || {}, owned_equipment: c.ownedEquipment || [] })),
                pay_later:            this.payLater,
                payment_proof_base64: this.payLater ? null : this.paymentProof,
            };

            try {
                const res  = await fetch('/register/wizard/submit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        // Declare we want JSON so Laravel returns validation (422),
                        // CSRF (419) and server (500) errors AS JSON — otherwise it
                        // replies with an HTML page and JSON.parse fails, which used
                        // to surface as a misleading "Network error".
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                });

                // Parse defensively — the body may not be JSON on a hard error.
                let data = null;
                try { data = await res.json(); } catch (_) { data = null; }

                if (res.ok && data && data.success) {
                    this.goTo(6);
                    setTimeout(() => { window.location.href = data.redirect; }, 4000);
                } else if (res.status === 419) {
                    this.errors.submit = 'Your session expired. Please refresh the page and try again.';
                } else if (data && (data.errors || data.message)) {
                    // Surface the first validation message (e.g. "email already taken").
                    if (data.errors) {
                        const first = Object.values(data.errors)[0];
                        this.errors.submit = Array.isArray(first) ? first[0] : first;
                    } else {
                        this.errors.submit = data.message;
                    }
                } else {
                    this.errors.submit = 'Something went wrong (error ' + res.status + '). Please try again.';
                }
            } catch (e) {
                this.errors.submit = 'Network error. Please check your connection and try again.';
            }
            this.submitting = false;
        },
    };
}
</script>
</body>
</html>
