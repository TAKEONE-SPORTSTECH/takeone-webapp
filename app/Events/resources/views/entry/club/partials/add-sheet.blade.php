{{--
    "Add an athlete" — a bottom sheet (Design Rule #8): look an athlete up, or
    create one. Teleported, because the page's ancestors carry transforms.
--}}
<template x-teleport="body" data-teleport-template="true">
    <div x-show="sheetOpen()" x-cloak class="fixed inset-0 flex items-end justify-center" style="z-index:75; display:none;"
         @keydown.escape.window="sheetOpen() && closeAdd()">
        <div x-show="sheetOpen()" x-transition.opacity class="absolute inset-0" style="background:rgba(7,11,20,.55);" @click="closeAdd()"></div>

        <div x-show="sheetOpen()"
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             class="relative w-full flex flex-col bg-white shadow-2xl overflow-hidden"
             style="max-width:520px; max-height:92vh; border-radius:24px 24px 0 0;">

            {{-- The gradient header band --}}
            <div class="flex-shrink-0 px-5 pt-3 pb-4 text-white relative overflow-hidden"
                 style="border-radius:24px 24px 0 0; background: linear-gradient(150deg, {{ $ev }}, {{ $ev }}b0);">
                <div class="absolute rounded-full bg-white/10" style="right:-32px; top:-40px; width:144px; height:144px;"></div>
                <div class="mx-auto rounded-full bg-white/40 mb-3" style="width:40px; height:4px;"></div>
                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi bi-person-plus-fill text-xl"></i></span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-black leading-tight" x-text="editIdx != null ? t.editAthlete : t.addTitle"></h3>
                        <p class="text-[12px] text-white/85 mt-0.5 truncate" x-text="club.name || @js($e['title'])"></p>
                    </div>
                    <button type="button" @click="closeAdd()" :aria-label="t.close"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                {{-- Look up · Create new --}}
                <div class="relative mt-3.5 flex gap-1 p-1 rounded-xl bg-white/15" x-show="editIdx == null">
                    <button type="button" @click="tabLookup()" class="flex-1 h-9 rounded-lg text-[12.5px] font-black transition-colors"
                            :class="! draft ? 'bg-white' : 'text-white/85'" :style="! draft ? 'color: {{ $ev }}' : ''" x-text="t.tabLookup"></button>
                    <button type="button" @click="tabNew()" class="flex-1 h-9 rounded-lg text-[12.5px] font-black transition-colors"
                            :class="draft ? 'bg-white' : 'text-white/85'" :style="draft ? 'color: {{ $ev }}' : ''" x-text="t.tabNew"></button>
                </div>
            </div>

            {{-- The body --}}
            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3.5">

                <div x-show="error" role="alert" class="rounded-2xl px-4 py-3 text-[12.5px] font-bold" style="background:#fdecea; color:#8f2f25;" x-text="error"></div>

                {{-- ===== Look up ===== --}}
                <template x-if="! draft">
                    <div class="space-y-3">
                        <template x-if="! alPicked()">
                            <div class="space-y-3">
                                <div class="relative">
                                    <i class="bi bi-search absolute top-1/2 -translate-y-1/2 text-muted-foreground" style="inset-inline-start:14px;"></i>
                                    <input type="text" x-model="alQ" @input="error = ''" :placeholder="t.alPh" autocomplete="off" :disabled="! clubObj()"
                                           class="e-field w-full h-12 rounded-2xl text-[15px]" style="padding-inline-start:40px; padding-inline-end:14px;">
                                </div>
                                <p class="text-[12px] text-muted-foreground leading-snug" x-text="alHint()"></p>
                                <div class="space-y-2">
                                    <template x-for="a in alList()" :key="a.id">
                                        <button type="button" @click="alChoose(a)" class="m-press e-pick w-full rounded-2xl px-3 py-2.5 flex items-center gap-3 text-start">
                                            <span class="flex-none overflow-hidden grid place-items-center" style="width:36px; height:48px; border-radius:10px; background: {{ $evSoft }};">
                                                <template x-if="a.photo"><img :src="a.photo" alt="" class="w-full h-full object-cover"></template>
                                                <template x-if="! a.photo"><span class="text-[11px] font-black" style="color: {{ $ev }};" x-text="initials(a.name)"></span></template>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block text-[14px] font-bold text-foreground truncate" x-text="a.name"></span>
                                                <span class="flex items-center gap-1.5 text-[11px] text-muted-foreground mt-0.5">
                                                    <span :style="a.gender === 'Female' ? 'color:#db2777' : 'color:#2563eb'" class="font-bold" x-text="gender(a.gender)"></span>
                                                    <span x-show="a.dob" x-text="'· ' + age(a.dob)"></span>
                                                    <span :class="flagOf(a.nat)" style="width:16px; height:12px; border-radius:2px; background-size:cover; flex:none;"></span>
                                                </span>
                                                <span class="block text-[10.5px] text-muted-foreground truncate" x-text="alView(a).clubLine"></span>
                                            </span>
                                            <span class="flex-none px-2 py-1 rounded-full text-[10px] font-black whitespace-nowrap"
                                                  :style="alView(a).tagTone === 'green' ? 'color:#2f7a4f;background:#e8f5ee' : (alView(a).tagTone === 'gold' ? 'color:#8a6d20;background:rgba(216,178,95,.18)' : 'color:#a8620f;background:#fdf1e0')"
                                                  x-text="alView(a).tag"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- The one picked --}}
                        <template x-if="alPicked()">
                            <div class="space-y-3.5">
                                <div class="rounded-2xl p-3 flex items-center gap-3" style="background: {{ $evSoft }};">
                                    <span class="flex-none overflow-hidden grid place-items-center" style="width:42px; height:56px; border-radius:12px; background:#fff;">
                                        <template x-if="alPicked().photo"><img :src="alPicked().photo" alt="" class="w-full h-full object-cover"></template>
                                        <template x-if="! alPicked().photo"><span class="text-[12px] font-black" style="color: {{ $ev }};" x-text="initials(alPicked().name)"></span></template>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[14px] font-black text-foreground m-0 truncate" x-text="alPicked().name"></p>
                                        <p class="text-[11px] text-muted-foreground m-0 truncate" x-text="alPicked().clubLine"></p>
                                    </div>
                                    <button type="button" @click="alPick = null; error = ''" class="text-[12px] font-black flex-none" style="color: {{ $ev }};" x-text="t.idChange"></button>
                                </div>

                                <template x-if="alPicked().own">
                                    <div class="space-y-3">
                                        <div class="flex items-center gap-2.5 flex-wrap">
                                            <label class="inv-label" style="margin:0;" x-text="t.todayWeight"></label>
                                            <input type="number" inputmode="decimal" min="15" max="200" step="0.1" dir="ltr" x-model="alWeight" @input="error = ''"
                                                   class="e-field h-11 px-3 rounded-xl text-[15px] font-bold" style="width:104px;">
                                            <template x-if="classify({ gender: alPicked().gender, dob: alPicked().dob, weight: alWeight })">
                                                <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold" style="background:#eef1f6; color:#1e2c4f;"
                                                      x-text="classify({ gender: alPicked().gender, dob: alPicked().dob, weight: alWeight })"></span>
                                            </template>
                                        </div>
                                        <div>
                                            <p class="inv-label" x-text="t.enterIn"></p>
                                            <div class="flex flex-wrap gap-1.5">
                                                <template x-for="f in indiv(alPicked().dob)" :key="f.type">
                                                    <button type="button" @click="alToggleType(f.type)" class="inv-chip m-press" :class="alTypesSel.includes(f.type) ? 'is-on' : ''">
                                                        <span x-text="f.type"></span><span style="opacity:.75;" x-text="money(f.price)"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="! alPicked().own">
                                    <p class="rounded-2xl px-4 py-3 text-[12.5px] leading-snug m-0" style="background:#fdf1e0; color:#7a4a0c;" x-text="alOtherMsg()"></p>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- ===== Create new ===== --}}
                <template x-if="draft">
                    <div class="space-y-3.5" x-effect="draft && draft.nat2 && setNat2()">
                        <div class="flex items-start gap-3.5">
                            <button type="button" @click="pickPhoto('draft', false)" class="m-press flex-none grid place-items-center overflow-hidden"
                                    style="width:90px; height:120px; border-radius:16px; background: {{ $evSoft }}; border:1.5px dashed {{ $ev }}66;" :aria-label="t.photoAdd">
                                <template x-if="draft.photo"><img :src="draft.photo" alt="" class="w-full h-full object-cover"></template>
                                <template x-if="! draft.photo"><span class="flex flex-col items-center gap-1" style="color: {{ $ev }};"><i class="bi bi-camera text-xl"></i><span class="text-[10px] font-black" x-text="t.photo"></span></span></template>
                            </button>
                            <div class="min-w-0 flex-1 space-y-3">
                                <div>
                                    <label class="inv-label" x-text="t.fullName"></label>
                                    <input type="text" x-model="draft.name" @input="error = ''" :placeholder="t.fullNamePh" maxlength="150"
                                           class="e-field w-full h-11 px-3.5 rounded-xl text-[14px]">
                                </div>
                                <div x-show="feat('nationalId')">
                                    <label class="inv-label" x-text="t.natId"></label>
                                    <input type="text" x-model="draft.natId" :placeholder="t.natIdPh" maxlength="40" dir="ltr"
                                           class="e-field w-full h-11 px-3.5 rounded-xl text-[14px]">
                                </div>
                                {{-- Optional. Somebody TakeOne already has (this phone + this name) is
                                     entered as themselves instead of being recorded twice — and
                                     nothing about them is shown here. --}}
                                <div x-show="feat('matchPhone')">
                                    <label class="inv-label"><span x-text="t.phoneMatch"></span> <span class="font-medium text-muted-foreground">· <span x-text="t.optional"></span></span></label>
                                    <input type="tel" inputmode="tel" x-model="draft.phone" :placeholder="t.phoneMatchPh" maxlength="20" dir="ltr" autocomplete="off"
                                           class="e-field w-full h-11 px-3.5 rounded-xl text-[14px]">
                                    <p class="text-[11px] text-muted-foreground mt-1 m-0 leading-snug" x-text="t.phoneMatchWhy"></p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <span class="inv-label" x-text="t.gender"></span>
                            <div class="grid grid-cols-2 gap-2">
                                <template x-for="g in ['Male', 'Female']" :key="g">
                                    <button type="button" @click="draft.gender = g; error = ''" class="m-press e-pick h-12 rounded-2xl text-[14px] font-bold text-foreground"
                                            :class="draft.gender === g ? 'is-on' : ''" x-text="gender(g)"></button>
                                </template>
                            </div>
                        </div>

                        <x-fields.country id="invAthNat" name="inv_nat" model="draft.nat2" :inline="true"
                                          :label="$t['nationality'] ?? 'Nationality'" labelClass="inv-label" />

                        <div>
                            <span class="inv-label"><span x-text="t.dob"></span> <span class="font-medium text-muted-foreground">· <span x-text="t.dobWhy"></span></span></span>
                            <div class="flex items-center gap-2" dir="ltr">
                                <input type="text" inputmode="numeric" maxlength="2" x-model="draft.dobD" @input="setDob('dobD', 2)" :placeholder="t.dd" class="e-field h-11 px-2 rounded-xl text-[14px] text-center" style="width:62px;">
                                <input type="text" inputmode="numeric" maxlength="2" x-model="draft.dobM" @input="setDob('dobM', 2)" :placeholder="t.mm" class="e-field h-11 px-2 rounded-xl text-[14px] text-center" style="width:62px;">
                                <input type="text" inputmode="numeric" maxlength="4" x-model="draft.dobY" @input="setDob('dobY', 4)" :placeholder="t.yyyy" class="e-field h-11 px-2 rounded-xl text-[14px] text-center" style="width:84px;">
                                <span x-show="draft.dob" class="px-2.5 py-1 rounded-lg text-[11px] font-black" style="background: {{ $evSoft }}; color: {{ $ev }};" x-text="fill(t.ageIs, { n: age(draft.dob) })"></span>
                            </div>
                        </div>

                        <div>
                            <label class="inv-label" x-text="t.weight"></label>
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <input type="number" inputmode="decimal" min="15" max="200" step="0.1" dir="ltr" x-model="draft.weight" @input="error = ''"
                                       class="e-field h-11 px-3 rounded-xl text-[15px] font-bold" style="width:104px;">
                                <template x-if="classify(draft)"><span class="px-2.5 py-1 rounded-lg text-[11px] font-bold" style="background:#eef1f6; color:#1e2c4f;" x-text="classify(draft)"></span></template>
                            </div>
                        </div>

                        <div>
                            <p class="inv-label" x-text="t.enterIn"></p>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="f in indiv(draft.dob)" :key="f.type">
                                    <button type="button" @click="toggleDraftType(f.type)" class="inv-chip m-press" :class="(draft.types || []).includes(f.type) ? 'is-on' : ''">
                                        <span x-text="f.type"></span><span style="opacity:.75;" x-text="money(f.price)"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- The footer: what this athlete costs, and the one action --}}
            <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-white flex items-center gap-3"
                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-bold text-muted-foreground m-0" x-text="t.feeFor"></p>
                    <p class="text-[16px] font-black text-foreground m-0" x-text="money(sheetState().fee)"></p>
                    <p class="text-[10.5px] text-muted-foreground m-0 leading-snug" x-show="sheetFeeSub()" x-text="sheetFeeSub()"></p>
                </div>
                <button type="button" @click="confirmSheet()" :disabled="! sheetState().ready"
                        class="m-press h-12 px-5 rounded-2xl font-black text-[14px] flex-none"
                        :class="sheetState().ready ? 'text-white' : 'text-muted-foreground'"
                        :style="sheetState().ready ? 'background: {{ $ev }}; box-shadow: 0 14px 30px -14px {{ $ev }}' : 'background: var(--color-muted, #eceef2)'"
                        x-text="sheetState().label"></button>
            </div>
        </div>
    </div>
</template>
