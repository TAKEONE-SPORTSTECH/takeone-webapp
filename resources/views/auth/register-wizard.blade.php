<!DOCTYPE html>
<html lang="en" id="wiz-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ session('club.context.name', 'Register') }} — TAKEONE</title>
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css">
    <style>
        *, *::before, *::after { -webkit-tap-highlight-color: transparent; box-sizing: border-box; }
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

        .terms-box { height: 260px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; background: #fafafa; font-size: 13px; line-height: 1.6; color: #4b5563; }
        .terms-box::-webkit-scrollbar { width: 4px; }
        .terms-box::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }

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
        .tf-input-group { border-radius: 12px !important; border: 1.5px solid #e5e7eb !important; overflow: hidden; }
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
            background-size: cover; background-position: center top;
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
    <div x-show="step > 0 && step < 9" class="bg-white shadow-sm">
        <div class="wiz-progress-track">
            <div class="wiz-progress-fill" x-bind:style="'width:' + Math.round((step/8)*100) + '%'"></div>
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
                   x-text="t.stepLabel + ' ' + step + ' / 8'"></p>
            </div>
        </div>
    </div>

    {{-- ── STEP 0 : Welcome / Language (Splash) ───────────── --}}
    <div x-show="step === 0" id="wiz-splash">
        {{-- Background image --}}
        <div id="wiz-splash-bg"
             x-bind:style="clubCover ? 'background-image: url(' + clubCover + ')' : ''"></div>
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

    {{-- ── STEP 1 : Prepare documents ──────────────────────── --}}
    <div x-show="step === 1" class="wiz-step flex-1 px-5 py-8">
        <div class="mb-8 text-center">
            <div class="w-16 h-16 rounded-2xl bg-amber-50 flex items-center justify-center mx-auto mb-4">
                <i class="bi bi-file-earmark-text text-amber-500 text-3xl"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-900 mb-2" x-text="t.docTitle"></h2>
            <p class="text-sm text-gray-500" x-text="t.docSub"></p>
        </div>
        <div class="space-y-3 mb-8">
            <div class="flex items-start gap-3 p-4 bg-white rounded-xl border border-gray-100 shadow-sm">
                <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="bi bi-person-badge text-primary text-sm"></i>
                </div>
                <div>
                    <p class="font-semibold text-gray-800 text-sm" x-text="t.docItem1"></p>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="t.docItem1sub"></p>
                </div>
            </div>
            <div class="flex items-start gap-3 p-4 bg-white rounded-xl border border-gray-100 shadow-sm">
                <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="bi bi-camera text-primary text-sm"></i>
                </div>
                <div>
                    <p class="font-semibold text-gray-800 text-sm" x-text="t.docItem2"></p>
                    <p class="text-xs text-gray-400 mt-0.5" x-text="t.docItem2sub"></p>
                </div>
            </div>
        </div>
        <button @click="goTo(2)" class="btn-primary" x-text="t.docReady"></button>
    </div>

    {{-- ── STEP 2 : Who are you? ────────────────────────────── --}}
    <div x-show="step === 2" class="wiz-step flex-1 px-5 py-8">
        <div class="mb-8 text-center">
            <h2 class="text-xl font-bold text-gray-900 mb-2" x-text="t.whoTitle"></h2>
            <p class="text-sm text-gray-500" x-text="t.whoSub"></p>
        </div>
        <div class="space-y-4">
            <button @click="registrationType = 'self'; goTo(3)" type="button"
                    class="who-card w-full" :class="registrationType === 'self' ? 'selected' : ''">
                <div class="who-icon">🧑‍💼</div>
                <p class="font-bold text-gray-900 text-base mb-1" x-text="t.whoSelf"></p>
                <p class="text-xs text-gray-400" x-text="t.whoSelfSub"></p>
            </button>
            <button @click="registrationType = 'kids'; goTo(3)" type="button"
                    class="who-card w-full" :class="registrationType === 'kids' ? 'selected' : ''">
                <div class="who-icon">👨‍👧‍👦</div>
                <p class="font-bold text-gray-900 text-base mb-1" x-text="t.whoKids"></p>
                <p class="text-xs text-gray-400" x-text="t.whoKidsSub"></p>
            </button>
        </div>
    </div>

    {{-- ── STEP 3 : Account details ─────────────────────────── --}}
    <div x-show="step === 3" class="wiz-step flex-1 px-5 py-6">
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

            {{-- Password --}}
            <div>
                <label class="field-label" x-text="t.password"></label>
                <input type="password" x-model="account.password" autocomplete="new-password"
                       :class="errors.password ? 'field-error' : ''"
                       class="field-input" placeholder="Min. 8 characters">
                <p x-show="errors.password" class="err-msg" x-text="t.passwordMin"></p>
            </div>

            {{-- Mobile number — uses country-code-dropdown component --}}
            <div>
                <label class="field-label" x-text="t.mobile"></label>
                <x-country-code-dropdown name="country_code" id="country_code" value="+973">
                    <input type="tel" id="wizard_mobile_number" name="mobile_number"
                           class="w-full px-4 py-2.5 bg-transparent focus:outline-none text-sm text-gray-900"
                           placeholder="e.g. 33001234" autocomplete="tel">
                </x-country-code-dropdown>
                <p x-show="errors.mobile_number" class="err-msg" x-text="t.required"></p>
            </div>

            {{-- Nationality — uses country-dropdown component --}}
            <div class="wiz-no-label">
                <label class="field-label" x-text="t.nationality"></label>
                <x-country-dropdown name="nationality" id="nationality" label="Nationality" value="BH" />
            </div>
        </div>
    </div>

    {{-- ── STEP 4a : Self profile ───────────────────────────── --}}
    <div x-show="step === 4 && registrationType === 'self'" class="wiz-step flex-1 px-5 py-6">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-1" x-text="t.profileTitle"></h2>
            <p class="text-sm text-gray-500" x-text="t.profileSub"></p>
        </div>

        <div class="space-y-1">
            {{-- Gender — uses gender-dropdown component --}}
            <div>
                <p x-show="errors.gender" class="err-msg mb-1" x-text="t.required"></p>
                <x-gender-dropdown name="self_gender" id="self_gender" label="Gender" :required="true" />
            </div>

            {{-- Date of birth — uses birthdate-dropdown component --}}
            <div>
                <p x-show="errors.birthdate" class="err-msg mb-1" x-text="t.required"></p>
                <x-birthdate-dropdown name="self_birthdate" id="self_birthdate"
                    label="Date of Birth"
                    :required="true"
                    :min-age="3"
                    :max-age="120" />
            </div>

            {{-- Health conditions --}}
            <div class="mb-4">
                <label class="tf-label" x-text="t.health"></label>
                <textarea x-model="self.health_conditions" class="field-input" rows="3"
                          placeholder="Optional — e.g. asthma, diabetes" style="resize:none"></textarea>
                <p class="text-xs text-gray-400 mt-1" x-text="t.optional"></p>
            </div>
        </div>
    </div>

    {{-- ── STEP 4b : Add kids ───────────────────────────────── --}}
    <div x-show="step === 4 && registrationType === 'kids'" class="wiz-step flex-1 px-5 py-6">
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
                        <p class="font-semibold text-gray-900 text-sm truncate" x-text="child.full_name"></p>
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

        <p x-show="errors.children" class="err-msg mb-3" x-text="t.addAtLeastOne"></p>

        {{-- Toggle button --}}
        <div x-show="!showChildForm">
            <button @click="showChildForm = true; errors = {}" type="button"
                    class="w-full flex items-center justify-center gap-2 p-3.5 border-2 border-dashed border-primary/40 rounded-xl text-primary font-semibold text-sm hover:bg-primary/5 transition-colors">
                <i class="bi bi-plus-lg"></i>
                <span x-text="t.addChild"></span>
            </button>
        </div>

        {{-- Inline child form — x-if so components re-initialize each time --}}
        <template x-if="showChildForm">
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4">
                <h3 class="font-bold text-gray-800 text-sm mb-4" x-text="t.addChild"></h3>

                <div class="mb-4">
                    <label class="tf-label" x-text="t.childName"></label>
                    <input type="text" x-model="newChild.full_name"
                           :class="errors.newChild ? 'field-error' : ''"
                           class="field-input" placeholder="Child's full name">
                </div>

                {{-- Gender component for child --}}
                <x-gender-dropdown name="new_child_gender" id="new_child_gender" label="Gender" :required="true" />

                {{-- Birthdate component for child --}}
                <x-birthdate-dropdown name="new_child_birthdate" id="new_child_birthdate"
                    label="Date of Birth"
                    :required="true"
                    :max-age="0"
                    :min-age="0"
                    :min-year="(int)date('Y') - 25"
                    :max-year="(int)date('Y')" />

                {{-- Nationality component for child --}}
                <x-country-dropdown name="new_child_nationality" id="new_child_nationality"
                    label="Nationality" value="BH" />

                <div class="mb-4">
                    <label class="tf-label" x-text="t.health"></label>
                    <textarea x-model="newChild.health_conditions" class="field-input" rows="2"
                              placeholder="Optional — e.g. asthma, diabetes" style="resize:none"></textarea>
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

    {{-- ── STEP 5 : Documents & Photos ─────────────────────── --}}
    <div x-show="step === 5" class="wiz-step flex-1 px-5 py-6">
        <div class="mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-1" x-text="t.docsTitle"></h2>
            <p class="text-sm text-gray-500" x-text="t.docsSub"></p>
        </div>

        {{-- Self documents --}}
        <template x-if="registrationType === 'self'">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 space-y-5">
                <p class="font-semibold text-gray-800" x-text="account.full_name || t.yourDocs"></p>

                <div>
                    <label class="field-label" x-text="t.profilePhoto"></label>
                    <div class="flex items-center gap-3 flex-wrap">
                        <template x-if="self.profile_photo_url">
                            <img :src="self.profile_photo_url" class="photo-preview">
                        </template>
                        <label class="upload-btn">
                            <i class="bi bi-image"></i> <span x-text="t.uploadGallery"></span>
                            <input type="file" accept="image/*"
                                   @change="uploadFile($event.target.files[0], 'profile_photo', self, 'profile_photo')">
                        </label>
                        <label class="upload-btn">
                            <i class="bi bi-camera"></i> <span x-text="t.takePhoto"></span>
                            <input type="file" accept="image/*" capture="environment"
                                   @change="uploadFile($event.target.files[0], 'profile_photo', self, 'profile_photo')">
                        </label>
                    </div>
                </div>

                <div>
                    <label class="field-label" x-text="t.cprNumber"></label>
                    <input type="text" x-model="self.cpr_number"
                           :class="errors.self_cpr ? 'field-error' : ''"
                           class="field-input" placeholder="Enter ID / CPR number">
                    <p x-show="errors.self_cpr" class="err-msg" x-text="t.required"></p>
                </div>

                <div>
                    <label class="field-label" x-text="t.cprImage"></label>
                    <div class="flex items-center gap-3 flex-wrap">
                        <template x-if="self.cpr_image_url">
                            <img :src="self.cpr_image_url" class="cpr-preview">
                        </template>
                        <label class="upload-btn">
                            <i class="bi bi-image"></i> <span x-text="t.uploadGallery"></span>
                            <input type="file" accept="image/*,application/pdf"
                                   @change="uploadFile($event.target.files[0], 'cpr_image', self, 'cpr_image')">
                        </label>
                        <label class="upload-btn">
                            <i class="bi bi-camera"></i> <span x-text="t.takePhoto"></span>
                            <input type="file" accept="image/*" capture="environment"
                                   @change="uploadFile($event.target.files[0], 'cpr_image', self, 'cpr_image')">
                        </label>
                    </div>
                </div>
            </div>
        </template>

        {{-- Children documents --}}
        <template x-if="registrationType === 'kids'">
            <div class="space-y-4">
                <template x-for="(child, idx) in children" :key="idx">
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 space-y-4">
                        <div class="flex items-center gap-2">
                            <div class="child-avatar text-sm" x-text="child.full_name.charAt(0).toUpperCase()"></div>
                            <p class="font-semibold text-gray-800" x-text="child.full_name"></p>
                        </div>

                        <div>
                            <label class="field-label" x-text="t.profilePhoto"></label>
                            <div class="flex items-center gap-3 flex-wrap">
                                <template x-if="child.profile_photo_url">
                                    <img :src="child.profile_photo_url" class="photo-preview">
                                </template>
                                <label class="upload-btn">
                                    <i class="bi bi-image"></i> <span x-text="t.uploadGallery"></span>
                                    <input type="file" accept="image/*"
                                           @change="uploadFile($event.target.files[0], 'profile_photo', child, 'profile_photo')">
                                </label>
                                <label class="upload-btn">
                                    <i class="bi bi-camera"></i> <span x-text="t.takePhoto"></span>
                                    <input type="file" accept="image/*" capture="environment"
                                           @change="uploadFile($event.target.files[0], 'profile_photo', child, 'profile_photo')">
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="field-label" x-text="t.cprNumber"></label>
                            <input type="text" x-model="child.cpr_number"
                                   :class="errors['child_cpr_'+idx] ? 'field-error' : ''"
                                   class="field-input" placeholder="Enter ID / CPR number">
                            <p x-show="errors['child_cpr_'+idx]" class="err-msg" x-text="t.required"></p>
                        </div>

                        <div>
                            <label class="field-label" x-text="t.cprImage"></label>
                            <div class="flex items-center gap-3 flex-wrap">
                                <template x-if="child.cpr_image_url">
                                    <img :src="child.cpr_image_url" class="cpr-preview">
                                </template>
                                <label class="upload-btn">
                                    <i class="bi bi-image"></i> <span x-text="t.uploadGallery"></span>
                                    <input type="file" accept="image/*,application/pdf"
                                           @change="uploadFile($event.target.files[0], 'cpr_image', child, 'cpr_image')">
                                </label>
                                <label class="upload-btn">
                                    <i class="bi bi-camera"></i> <span x-text="t.takePhoto"></span>
                                    <input type="file" accept="image/*" capture="environment"
                                           @change="uploadFile($event.target.files[0], 'cpr_image', child, 'cpr_image')">
                                </label>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <div x-show="loading" class="flex items-center gap-2 mt-4 text-primary text-sm font-medium">
            <i class="bi bi-arrow-repeat animate-spin"></i>
            <span x-text="t.uploading"></span>
        </div>
    </div>

    {{-- ── STEP 6 : Package selection ───────────────────────── --}}
    <div x-show="step === 6" class="wiz-step flex-1 px-5 py-6">
        <div class="mb-5">
            <h2 class="text-xl font-bold text-gray-900 mb-1" x-text="t.packagesTitle"></h2>
            <p class="text-sm text-gray-500" x-text="t.packagesSub"></p>
        </div>

        <div x-show="loadingPackages" class="flex items-center gap-2 text-primary text-sm py-4">
            <i class="bi bi-arrow-repeat animate-spin"></i>
            <span x-text="t.loadingPackages"></span>
        </div>

        {{-- Self packages --}}
        <div x-show="registrationType === 'self' && !loadingPackages" class="space-y-3">
            <div x-show="(packagesData['self'] || []).length === 0"
                 class="text-center py-8 text-gray-400">
                <i class="bi bi-box text-2xl block mb-2"></i>
                <span x-text="t.noPackages"></span>
            </div>
            <template x-for="pkg in (packagesData['self'] || [])" :key="pkg.id">
                <div class="pkg-card" :class="self.packages.includes(pkg.id) ? 'selected' : ''"
                     @click="togglePackage(self, pkg.id)">
                    <div class="flex items-start gap-3">
                        <div class="pkg-check mt-0.5"><i class="bi bi-check-lg text-xs"></i></div>
                        <div class="flex-1">
                            <p class="font-semibold text-gray-900 text-sm" x-text="pkg.name"></p>
                            <p class="text-xs text-gray-400 mt-0.5"
                               x-text="(pkg.duration_months ? pkg.duration_months + ' ' + t.months : '') + (pkg.type ? ' · ' + pkg.type : '')"></p>
                            <p x-show="pkg.description" class="text-xs text-gray-500 mt-1 line-clamp-2" x-text="pkg.description"></p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="font-bold text-primary text-base" x-text="parseFloat(pkg.price).toFixed(2)"></p>
                            <p class="text-xs text-gray-400" x-text="t.currency"></p>
                        </div>
                    </div>
                </div>
            </template>
            <p x-show="errors.packages" class="err-msg" x-text="t.selectPackage"></p>
        </div>

        {{-- Kids packages — one section per child --}}
        <div x-show="registrationType === 'kids' && !loadingPackages" class="space-y-6">
            <template x-for="(child, idx) in children" :key="idx">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    {{-- Child header --}}
                    <div class="flex items-center gap-3 px-4 py-3 bg-primary/5 border-b border-primary/10">
                        <div class="child-avatar" x-text="child.full_name.charAt(0).toUpperCase()"></div>
                        <div>
                            <p class="font-semibold text-gray-900 text-sm" x-text="child.full_name"></p>
                            <p class="text-xs text-gray-400"
                               x-text="(child.gender === 'Male' ? t.male : t.female) + (child.birthdate ? ' · Born ' + child.birthdate : '')"></p>
                        </div>
                    </div>
                    {{-- Packages for this child --}}
                    <div class="p-3 space-y-2">
                        <div x-show="(packagesData[idx] || []).length === 0"
                             class="text-center py-4 text-gray-400 text-sm">
                            <i class="bi bi-box block text-xl mb-1"></i>
                            <span x-text="t.noPackages"></span>
                        </div>
                        <template x-for="pkg in (packagesData[idx] || [])" :key="pkg.id">
                            <div class="pkg-card" :class="child.packages.includes(pkg.id) ? 'selected' : ''"
                                 @click="togglePackage(child, pkg.id)">
                                <div class="flex items-start gap-3">
                                    <div class="pkg-check mt-0.5"><i class="bi bi-check-lg text-xs"></i></div>
                                    <div class="flex-1">
                                        <p class="font-semibold text-gray-900 text-sm" x-text="pkg.name"></p>
                                        <p class="text-xs text-gray-400 mt-0.5"
                                           x-text="(pkg.duration_months ? pkg.duration_months + ' ' + t.months : '') + (pkg.type ? ' · ' + pkg.type : '')"></p>
                                        <p x-show="pkg.description" class="text-xs text-gray-500 mt-1 line-clamp-2" x-text="pkg.description"></p>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="font-bold text-primary text-base" x-text="parseFloat(pkg.price).toFixed(2)"></p>
                                        <p class="text-xs text-gray-400" x-text="t.currency"></p>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <p x-show="errors['child_packages_'+idx]" class="err-msg px-1" x-text="t.selectPackage"></p>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="parseFloat(totalAmount) > 0" class="mt-6 p-4 bg-primary/5 rounded-xl border border-primary/20">
            <div class="flex items-center justify-between">
                <span class="font-semibold text-gray-700" x-text="t.totalAmount"></span>
                <span class="font-bold text-primary text-lg" x-text="totalAmount + ' ' + t.currency"></span>
            </div>
        </div>
    </div>

    {{-- ── STEP 7 : Terms & Conditions ─────────────────────── --}}
    <div x-show="step === 7" class="wiz-step flex-1 px-5 py-6">
        <div class="mb-5">
            <h2 class="text-xl font-bold text-gray-900 mb-1" x-text="t.termsTitle"></h2>
        </div>
        <div class="terms-box mb-5">
            <h4 class="font-bold text-gray-800 mb-2">Terms & Conditions / الشروط والأحكام</h4>
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
        <label class="flex items-start gap-3 cursor-pointer">
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

    {{-- ── STEP 8 : Review & Submit ─────────────────────────── --}}
    <div x-show="step === 8" class="wiz-step flex-1 px-5 py-6">
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
                        <span class="font-medium text-gray-900" x-text="account.mobile_code + ' ' + account.mobile_number"></span>
                    </div>
                </div>
            </div>

            <template x-if="registrationType === 'self'">
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
                </div>
            </template>

            <template x-if="registrationType === 'kids'">
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
                        </div>
                    </template>
                </div>
            </template>

            <div class="flex items-center justify-between p-4 bg-primary/5 rounded-xl border border-primary/20">
                <span class="font-bold text-gray-800" x-text="t.totalAmount"></span>
                <span class="font-bold text-primary text-xl" x-text="totalAmount + ' ' + t.currency"></span>
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

    {{-- ── STEP 9 : Success ─────────────────────────────────── --}}
    <div x-show="step === 9" class="wiz-step flex-1 flex flex-col items-center justify-center px-6 py-12 text-center">
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
    <div x-show="step > 1 && step < 9" class="bg-white border-t border-gray-100 px-5 py-4">
        <div class="flex gap-3">
            <button x-show="step > 1" @click="prev()" type="button"
                    class="btn-ghost" style="min-width:80px" x-text="t.back"></button>
            <button x-show="step !== 8" @click="next()" type="button"
                    class="btn-primary" :disabled="loading"
                    x-text="t.next"></button>
        </div>
    </div>

</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function wizard() {
    return {
        lang: 'en',
        step: 0,
        registrationType: '',

        account: {
            full_name: '',
            email: '',
            password: '',
            mobile_code: '+973',
            mobile_number: '',
            nationality: '',
        },

        self: {
            gender: '',
            birthdate: '',
            health_conditions: '',
            profile_photo_path: '', profile_photo_url: '',
            cpr_number: '',
            cpr_image_path: '', cpr_image_url: '',
            packages: [],
        },

        // Top-level cache: key 'self' or child index (number) → array of packages
        // Replaced as a whole object so Alpine always detects the change.
        packagesData: {},

        children: [],
        newChild: {
            full_name: '', health_conditions: '',
        },
        showChildForm: false,

        agreedToTerms: false,
        loading: false,
        loadingPackages: false,
        submitting: false,
        errors: {},

        clubSlug:  @json(session('club.context.slug', '')),
        clubName:  @json(session('club.context.name', '')),
        clubLogo:  @json(session('club.context.logo') ? asset('storage/' . session('club.context.logo')) : ''),
        clubCover: @json(session('club.context.cover_image') ? asset('storage/' . session('club.context.cover_image')) : ''),

        T: {
            en: {
                joining: 'Joining', stepLabel: 'Step',
                docTitle: 'Get your documents ready',
                docSub: 'Please have these available before you start — it makes the process much faster.',
                docItem1: 'ID Card (CPR) for each person', docItem1sub: 'You\'ll need the card number and a photo of it',
                docItem2: 'A clear photo of each person', docItem2sub: 'Profile picture for their membership',
                docReady: "I'm ready, let's go →",
                whoTitle: 'How are you registering?', whoSub: 'Choose one to continue',
                whoSelf: 'Registering myself', whoSelfSub: 'I want to join this club personally',
                whoKids: 'Registering my kids', whoKidsSub: 'I\'m signing up my children',
                detailsTitle: 'Your account details', detailsSub: 'This creates your login account',
                fullName: 'Full Name', email: 'Email Address', password: 'Password',
                mobile: 'Mobile Number', nationality: 'Nationality',
                profileTitle: 'Your profile', profileSub: 'Tell us a bit about yourself',
                gender: 'Gender', male: 'Male', female: 'Female',
                dob: 'Date of Birth', health: 'Chronic Health Conditions',
                optional: 'Optional',
                kidsTitle: 'Add your children', kidsSub: 'Add each child who will be registering',
                addChild: 'Add a child', childName: "Child's name",
                saveChild: 'Save', cancel: 'Cancel',
                noChildren: 'No children added yet',
                fillRequired: 'Please fill in name, gender and date of birth',
                docsTitle: 'Documents & Photos', docsSub: 'Upload a photo and ID card for each person',
                yourDocs: 'Your documents',
                profilePhoto: 'Profile Photo', uploadGallery: 'Gallery', takePhoto: 'Camera',
                cprNumber: 'CPR / ID Number', cprImage: 'CPR / ID Card Photo',
                packagesTitle: 'Select packages', packagesSub: 'Choose one or more packages per person',
                loadingPackages: 'Loading available packages…',
                noPackages: 'No packages available for this profile',
                months: 'months', currency: 'BHD',
                totalAmount: 'Total', selectPackage: 'Please select at least one package',
                termsTitle: 'Terms & Conditions', termsAgree: 'I have read and agree to the Terms & Conditions',
                reviewTitle: 'Review & Submit', reviewSub: 'Check your details before submitting',
                accountInfo: 'Account', packages: 'Packages', noPackagesSelected: 'No packages selected',
                submitBtn: 'Submit Registration', submitting: 'Submitting…',
                successTitle: 'Registration submitted!',
                successSub: 'Please check your email to verify your account.',
                pendingNote: 'Your registration is pending approval by the club. You\'ll be notified once confirmed.',
                redirecting: 'Redirecting you in a moment…',
                next: 'Next', back: 'Back', uploading: 'Uploading…',
                required: 'This field is required',
                invalidEmail: 'Please enter a valid email address',
                passwordMin: 'Password must be at least 8 characters',
                addAtLeastOne: 'Please add at least one child',
                agreeTerms: 'Please accept the terms and conditions',
            },
            ar: {
                joining: 'انضمام إلى', stepLabel: 'خطوة',
                docTitle: 'جهّز وثائقك',
                docSub: 'يُرجى تجهيز المستندات التالية قبل البدء لتسريع العملية.',
                docItem1: 'بطاقة الهوية (CPR) لكل شخص', docItem1sub: 'ستحتاج إلى رقم البطاقة وصورة منها',
                docItem2: 'صورة شخصية واضحة لكل شخص', docItem2sub: 'صورة الملف الشخصي للعضوية',
                docReady: 'جاهز، لنبدأ ←',
                whoTitle: 'كيف تودّ التسجيل؟', whoSub: 'اختر للمتابعة',
                whoSelf: 'أسجّل لنفسي', whoSelfSub: 'أريد الانضمام إلى هذا النادي شخصياً',
                whoKids: 'أسجّل لأطفالي', whoKidsSub: 'أودّ تسجيل أطفالي',
                detailsTitle: 'بيانات حسابك', detailsSub: 'هذا ينشئ حساب الدخول الخاص بك',
                fullName: 'الاسم الكامل', email: 'البريد الإلكتروني', password: 'كلمة المرور',
                mobile: 'رقم الهاتف', nationality: 'الجنسية',
                profileTitle: 'ملفك الشخصي', profileSub: 'أخبرنا بعض الشيء عنك',
                gender: 'الجنس', male: 'ذكر', female: 'أنثى',
                dob: 'تاريخ الميلاد', health: 'الحالات الصحية المزمنة',
                optional: 'اختياري',
                kidsTitle: 'إضافة أطفالك', kidsSub: 'أضف كل طفل سيتم تسجيله',
                addChild: 'إضافة طفل', childName: 'اسم الطفل',
                saveChild: 'حفظ', cancel: 'إلغاء',
                noChildren: 'لم يتم إضافة أطفال بعد',
                fillRequired: 'يرجى إدخال الاسم والجنس وتاريخ الميلاد',
                docsTitle: 'الوثائق والصور', docsSub: 'أرفق صورة شخصية وبطاقة هوية لكل شخص',
                yourDocs: 'وثائقك',
                profilePhoto: 'الصورة الشخصية', uploadGallery: 'المعرض', takePhoto: 'الكاميرا',
                cprNumber: 'رقم الهوية (CPR)', cprImage: 'صورة بطاقة الهوية',
                packagesTitle: 'اختر الباقات', packagesSub: 'اختر باقة أو أكثر لكل شخص',
                loadingPackages: 'جارٍ تحميل الباقات المتاحة…',
                noPackages: 'لا توجد باقات متاحة لهذا الملف',
                months: 'أشهر', currency: 'د.ب',
                totalAmount: 'الإجمالي', selectPackage: 'يرجى اختيار باقة واحدة على الأقل',
                termsTitle: 'الشروط والأحكام', termsAgree: 'لقد قرأت وأوافق على الشروط والأحكام',
                reviewTitle: 'مراجعة وتقديم', reviewSub: 'راجع بياناتك قبل التقديم',
                accountInfo: 'الحساب', packages: 'الباقات', noPackagesSelected: 'لم يتم اختيار أي باقة',
                submitBtn: 'تقديم الطلب', submitting: 'جارٍ التقديم…',
                successTitle: 'تم تقديم طلبك!',
                successSub: 'يرجى التحقق من بريدك الإلكتروني لتأكيد حسابك.',
                pendingNote: 'طلبك قيد المراجعة من قِبل النادي. سيتم إشعارك عند التأكيد.',
                redirecting: 'جارٍ توجيهك…',
                next: 'التالي', back: 'رجوع', uploading: 'جارٍ الرفع…',
                required: 'هذا الحقل مطلوب',
                invalidEmail: 'يرجى إدخال بريد إلكتروني صحيح',
                passwordMin: 'كلمة المرور يجب أن تكون ٨ أحرف على الأقل',
                addAtLeastOne: 'يرجى إضافة طفل واحد على الأقل',
                agreeTerms: 'يرجى قبول الشروط والأحكام',
            },
        },

        get t() { return this.T[this.lang]; },

        get totalAmount() {
            let total = 0;
            if (this.registrationType === 'self') {
                const avail = this.packagesData['self'] || [];
                this.self.packages.forEach(id => {
                    const pkg = avail.find(p => p.id == id);
                    if (pkg) total += parseFloat(pkg.price);
                });
            } else {
                this.children.forEach((child, i) => {
                    const avail = this.packagesData[i] || [];
                    child.packages.forEach(id => {
                        const pkg = avail.find(p => p.id == id);
                        if (pkg) total += parseFloat(pkg.price);
                    });
                });
            }
            return total.toFixed(2);
        },

        init() {},

        // Sync values from Blade component hidden inputs into Alpine state
        syncFromDOM() {
            // Mobile
            const mobileCode   = document.getElementById('country_code');
            const mobileNumber = document.getElementById('wizard_mobile_number');
            if (mobileCode)   this.account.mobile_code   = mobileCode.value   || this.account.mobile_code;
            if (mobileNumber) this.account.mobile_number = mobileNumber.value || this.account.mobile_number;

            // Nationality
            const nationality = document.getElementById('nationality');
            if (nationality) this.account.nationality = nationality.value || this.account.nationality;

            // Self profile
            const selfGender    = document.getElementById('self_gender');
            const selfBirthdate = document.getElementById('self_birthdate');
            if (selfGender)    this.self.gender    = selfGender.value    || '';
            if (selfBirthdate) this.self.birthdate = selfBirthdate.value || '';
        },

        goTo(n) {
            this.step = n;
            this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
        },

        async next() {
            this.syncFromDOM();
            if (!this.validate()) return;
            const nextStep = this.step + 1;
            if (nextStep === 6) {
                this.goTo(nextStep);
                await this.loadPackages();
            } else {
                this.goTo(nextStep);
            }
        },

        prev() {
            this.errors = {};
            this.goTo(this.step - 1);
        },

        validate() {
            this.errors = {};
            if (this.step === 3) {
                if (!this.account.full_name.trim()) this.errors.full_name = true;
                const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRe.test(this.account.email)) this.errors.email = true;
                if (this.account.password.length < 8)  this.errors.password = true;
                if (!this.account.mobile_number.trim()) this.errors.mobile_number = true;
            }
            if (this.step === 4) {
                if (this.registrationType === 'self') {
                    if (!this.self.gender)    this.errors.gender    = true;
                    if (!this.self.birthdate) this.errors.birthdate = true;
                } else {
                    if (this.children.length === 0) this.errors.children = true;
                }
            }
            if (this.step === 5) {
                if (this.registrationType === 'self') {
                    if (!this.self.cpr_number.trim()) this.errors.self_cpr = true;
                } else {
                    this.children.forEach((c, i) => {
                        if (!c.cpr_number.trim()) this.errors['child_cpr_' + i] = true;
                    });
                }
            }
            if (this.step === 6) {
                if (this.registrationType === 'self') {
                    if (this.self.packages.length === 0) this.errors.packages = true;
                } else {
                    this.children.forEach((c, i) => {
                        if (c.packages.length === 0) this.errors['child_packages_' + i] = true;
                    });
                }
            }
            if (this.step === 7) {
                if (!this.agreedToTerms) this.errors.terms = true;
            }
            return Object.keys(this.errors).length === 0;
        },

        addChildToList() {
            this.errors = {};
            // Read from component hidden inputs
            const gender      = document.getElementById('new_child_gender')?.value    || '';
            const birthdate   = document.getElementById('new_child_birthdate')?.value || '';
            const nationality = document.getElementById('new_child_nationality')?.value || '';

            if (!this.newChild.full_name.trim() || !gender || !birthdate) {
                this.errors.newChild = true;
                return;
            }
            this.children.push({
                full_name:         this.newChild.full_name,
                gender:            gender,
                birthdate:         birthdate,
                nationality:       nationality,
                health_conditions: this.newChild.health_conditions,
                profile_photo_path: '', profile_photo_url: '',
                cpr_number: '', cpr_image_path: '', cpr_image_url: '',
                packages: [],
            });
            this.newChild = { full_name: '', health_conditions: '' };
            this.showChildForm = false;
        },

        removeChild(index) {
            this.children.splice(index, 1);
        },

        async uploadFile(file, type, target, field) {
            if (!file) return;
            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', type);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            this.loading = true;
            try {
                const res  = await fetch('/register/wizard/upload-temp', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    target[field + '_path'] = data.path;
                    target[field + '_url']  = data.url;
                }
            } catch (e) { console.error('Upload failed', e); }
            this.loading = false;
        },

        async loadPackages() {
            this.loadingPackages = true;
            const newData = {};

            if (this.registrationType === 'self') {
                try {
                    const url  = `/register/wizard/packages?club_slug=${encodeURIComponent(this.clubSlug)}&birthdate=${encodeURIComponent(this.self.birthdate)}&gender=${this.self.gender}`;
                    const res  = await fetch(url);
                    const data = await res.json();
                    newData['self'] = data.packages || [];
                } catch (e) {
                    console.error('Failed to load packages for self', e);
                    newData['self'] = [];
                }
            } else {
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
            }

            // Replace entire object — Alpine always detects top-level property reassignment
            this.packagesData   = newData;
            this.loadingPackages = false;
        },

        togglePackage(person, packageId) {
            const idx = person.packages.indexOf(packageId);
            if (idx === -1) person.packages.push(packageId);
            else            person.packages.splice(idx, 1);
        },

        async submit() {
            this.syncFromDOM();
            this.submitting = true;
            this.errors     = {};

            const payload = {
                full_name:         this.account.full_name,
                email:             this.account.email,
                password:          this.account.password,
                mobile_code:       this.account.mobile_code,
                mobile_number:     this.account.mobile_number,
                nationality:       this.account.nationality,
                registration_type: this.registrationType,
                club_slug:         this.clubSlug,
            };

            if (this.registrationType === 'self') {
                payload.self_gender            = this.self.gender;
                payload.self_birthdate         = this.self.birthdate;
                payload.self_health_conditions = this.self.health_conditions;
                payload.self_profile_photo     = this.self.profile_photo_path;
                payload.self_cpr_number        = this.self.cpr_number;
                payload.self_cpr_image         = this.self.cpr_image_path;
                payload.self_packages          = this.self.packages;
            } else {
                payload.children = this.children.map(c => ({
                    full_name:         c.full_name,
                    gender:            c.gender,
                    birthdate:         c.birthdate,
                    nationality:       c.nationality,
                    health_conditions: c.health_conditions,
                    profile_photo:     c.profile_photo_path,
                    cpr_number:        c.cpr_number,
                    cpr_image:         c.cpr_image_path,
                    packages:          c.packages,
                }));
            }

            try {
                const res  = await fetch('/register/wizard/submit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (data.success) {
                    this.goTo(9);
                    setTimeout(() => { window.location.href = data.redirect; }, 4000);
                } else {
                    this.errors.submit = data.message || 'Registration failed. Please try again.';
                    if (data.errors) {
                        const first = Object.values(data.errors)[0];
                        this.errors.submit = Array.isArray(first) ? first[0] : first;
                    }
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
