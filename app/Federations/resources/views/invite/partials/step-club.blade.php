{{-- Step 2 — find the club and prove it, or register a new one; then its officials. --}}
<div x-show="step === 2" class="space-y-3">

    {{-- ===== A. Find your club ===== --}}
    <div x-show="! verified && clubMode !== 'new'" class="inv-card p-5 inv-in">
        <div class="flex items-start gap-3">
            <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 text-white" style="background: {{ $ev }};"><i class="bi bi-search text-lg"></i></span>
            <div class="min-w-0">
                <h2 class="inv-h" x-text="t.idFind"></h2>
                <p class="inv-sub" x-text="t.idFindSub"></p>
            </div>
        </div>

        {{-- One box: a name lists the federation's clubs; an email or phone goes straight to "send code". --}}
        <template x-if="! findPick">
            <div class="mt-4">
                <div class="relative">
                    <i class="bi bi-building absolute top-1/2 -translate-y-1/2 text-muted-foreground" style="inset-inline-start:14px;"></i>
                    <input type="text" x-model="findQ" @input="onFindQ()" @keydown.enter.prevent="findIsContact() && sendCode()"
                           :placeholder="t.idFindPh" autocomplete="off" maxlength="120"
                           class="e-field w-full h-12 rounded-2xl text-[15px]" style="padding-inline-start:40px; padding-inline-end:14px;">
                </div>

                <div x-show="findRows && findRows.length && ! findIsContact()" class="mt-2 space-y-2">
                    <template x-for="k in (findRows || [])" :key="k.key">
                        <button type="button" @click="pickFound(k)" class="m-press e-pick w-full rounded-2xl px-3.5 py-3 flex items-center gap-3 text-start">
                            <template x-if="k.logo"><span class="flex-none" style="width:38px; height:38px;"><img :src="k.logo" alt="" class="w-full h-full object-contain"></span></template>
                            <template x-if="! k.logo"><span class="flex-none grid place-items-center text-[12px] font-black text-white" style="width:38px; height:38px; border-radius:12px; background: {{ $ev }};" x-text="initials(k.name)"></span></template>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[14px] font-bold text-foreground truncate" x-text="k.name"></span>
                                <span class="block text-[11.5px] text-muted-foreground truncate" x-text="clubSub(k)"></span>
                            </span>
                            <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/60 flex-none"></i>
                        </button>
                    </template>
                </div>

                <p x-show="findNone()" class="text-[12.5px] text-muted-foreground mt-3 leading-snug" x-text="fill(t.findNoneT, { q: (findQ || '').trim() })"></p>

                <button type="button" x-show="findIsContact() && ! codeSent" @click="sendCode()"
                        class="m-press w-full h-12 rounded-2xl text-white font-black text-[14px] mt-3" style="background: {{ $ev }};" x-text="t.idSend"></button>
            </div>
        </template>

        {{-- The club picked from the list. --}}
        <template x-if="findPick">
            <div class="mt-4 rounded-2xl px-3.5 py-3 flex items-center gap-3" style="background: {{ $evSoft }}; border:1.5px solid {{ $ev }}55;">
                <template x-if="findPick.logo"><span class="flex-none" style="width:38px; height:38px;"><img :src="findPick.logo" alt="" class="w-full h-full object-contain"></span></template>
                <template x-if="! findPick.logo"><span class="flex-none grid place-items-center text-[12px] font-black text-white" style="width:38px; height:38px; border-radius:12px; background: {{ $ev }};" x-text="initials(findPick.name)"></span></template>
                <span class="min-w-0 flex-1">
                    <span class="block text-[14px] font-black text-foreground truncate" x-text="findPick.name"></span>
                    <span class="block text-[11.5px] text-muted-foreground truncate" x-text="clubSub(findPick)"></span>
                </span>
                <button type="button" @click="unpick()" class="text-[12px] font-black flex-none" style="color: {{ $ev }};" x-text="t.idChange"></button>
            </div>
        </template>

        <template x-if="findPick && ! findPick.hint">
            <div class="mt-3 rounded-2xl px-4 py-3 text-[12px] leading-snug" style="background:#fdf1e0; color:#7a4a0c;" x-text="t.idNoEmail"></div>
        </template>
        <template x-if="findPick && findPick.hint && ! codeSent">
            <button type="button" @click="sendClubCode()" class="m-press w-full h-12 rounded-2xl text-white font-black text-[14px] mt-3"
                    style="background: {{ $ev }};" x-text="sendLabel()"></button>
        </template>

        {{-- The code that came by email. --}}
        <template x-if="codeSent">
            <div class="mt-4 inv-in">
                <div class="rounded-2xl px-4 py-3 flex items-start gap-2.5" style="background:#e8f5ee;">
                    <i class="bi bi-envelope-check-fill mt-0.5" style="color:#2f7a4f;"></i>
                    <p class="text-[12px] leading-snug m-0" style="color:#14663c;" x-text="codeSentMsg()"></p>
                </div>
                <label class="inv-label mt-4" x-text="t.idCode"></label>
                <div class="flex items-stretch gap-2">
                    <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" dir="ltr"
                           x-model="codeInput" @input="codeInput = codeInput.replace(/\D/g, '').slice(0, 6); error = ''" @keydown.enter.prevent="verifyCode()"
                           class="e-field flex-1 min-w-0 h-12 px-4 rounded-2xl text-[18px] font-black text-center" style="letter-spacing:.35em;">
                    <button type="button" @click="verifyCode()" :disabled="verifying"
                            class="m-press h-12 px-5 rounded-2xl text-white font-black text-[14px] flex-none" style="background: {{ $ev }};" x-text="verifying ? t.idChecking : t.idVerifyBtn"></button>
                </div>
                <button type="button" @click="findPick ? sendClubCode() : sendCode()"
                        class="text-[12px] font-bold mt-2.5 text-muted-foreground underline" x-text="t.idResend"></button>
            </div>
        </template>

        {{-- One code, several clubs (one owner): pick which. --}}
        <template x-if="clubChoices && clubChoices.length">
            <div class="mt-4">
                <p class="text-[13.5px] font-black text-foreground" x-text="t.idChoose"></p>
                <p class="inv-sub" x-text="t.idChooseSub"></p>
                <div class="space-y-2 mt-3">
                    <template x-for="k in clubChoices" :key="k.key">
                        <button type="button" @click="chooseClub(k)" :disabled="!! clubPicking"
                                class="m-press e-pick w-full rounded-2xl px-3.5 py-3 flex items-center gap-3 text-start">
                            <template x-if="k.logo"><span class="flex-none" style="width:38px; height:38px;"><img :src="k.logo" alt="" class="w-full h-full object-contain"></span></template>
                            <template x-if="! k.logo"><span class="flex-none grid place-items-center text-[12px] font-black text-white" style="width:38px; height:38px; border-radius:12px; background: {{ $ev }};" x-text="initials(k.name)"></span></template>
                            <span class="min-w-0 flex-1 text-[14px] font-bold text-foreground truncate" x-text="k.name"></span>
                            <i class="bi flex-none" :class="clubPicking === k.key ? 'bi-arrow-repeat animate-spin' : 'bi-chevron-right rtl:rotate-180'" style="color: {{ $ev }};"></i>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        <div class="mt-5 pt-4 border-t border-gray-100 flex items-center justify-between gap-3 flex-wrap">
            <span class="text-[12px] text-muted-foreground" x-text="t.idNotListed"></span>
            <button type="button" @click="chooseNew()" class="text-[12.5px] font-black" style="color: {{ $ev }};" x-text="t.idNew"></button>
        </div>
    </div>

    {{-- ===== B. The verified club ===== --}}
    <div x-show="clubMode === 'existing' && verified && ! editClub" class="inv-card overflow-hidden inv-in">
        <div class="px-5 py-3 flex items-center gap-2" style="background:#e8f5ee;">
            <i class="bi bi-patch-check-fill" style="color:#2f7a4f;"></i>
            <p class="text-[12.5px] font-bold m-0" style="color:#14663c;" x-text="verifiedMsg()"></p>
        </div>
        <div class="p-5 flex items-start gap-3.5">
            <template x-if="club.logo"><span class="flex-none" style="width:56px; height:56px;"><img :src="club.logo" alt="" class="w-full h-full object-contain"></span></template>
            <template x-if="! club.logo"><span class="flex-none grid place-items-center text-[16px] font-black text-white" style="width:56px; height:56px; border-radius:16px; background: {{ $ev }};" x-text="initials(club.name)"></span></template>
            <div class="min-w-0 flex-1">
                <p class="text-[16px] font-black text-foreground leading-tight" x-text="club.name"></p>
                <p class="text-[12px] text-muted-foreground mt-1 flex items-center gap-1.5" x-show="club.coach"><i class="bi bi-person"></i><span x-text="club.coach"></span></p>
                <p class="text-[12px] text-muted-foreground mt-0.5 flex items-center gap-1.5" x-show="club.phone"><i class="bi bi-telephone"></i><span dir="ltr" x-text="club.dial + ' ' + club.phone"></span></p>
                <p class="text-[12px] text-muted-foreground mt-0.5 flex items-center gap-1.5" x-show="club.email"><i class="bi bi-envelope"></i><span class="truncate" x-text="club.email"></span></p>
                <p class="text-[12px] text-muted-foreground mt-0.5 flex items-center gap-1.5" x-show="club.address"><i class="bi bi-geo-alt"></i><span x-text="club.address"></span></p>
            </div>
        </div>
        <div class="px-5 pb-4">
            <button type="button" @click="editClub = true" class="m-press text-[12.5px] font-black inline-flex items-center gap-1.5" style="color: {{ $ev }};">
                <i class="bi bi-pencil-square"></i><span x-text="t.updateDetails"></span>
            </button>
        </div>
    </div>

    {{-- ===== C. The club's details ===== --}}
    <div x-show="clubMode === 'new' || (clubMode === 'existing' && verified && editClub)" class="space-y-3">

        {{-- A "new" club the federation already has. --}}
        <template x-if="clubMode === 'new' && clubMatch">
            <div class="inv-card p-5 inv-in" style="border-color:#efc98a; background:#fdf6e6;">
                <div class="flex items-start gap-3">
                    <i class="bi bi-exclamation-triangle-fill text-lg" style="color:#b45309;"></i>
                    <div class="min-w-0">
                        <p class="text-[14px] font-black text-foreground" x-text="fill(t.matchTitle, { c: clubMatch.name })"></p>
                        <p class="inv-sub" x-text="fill(clubMatch.reason === 'name' ? t.matchSubName : t.matchSubContact, { c: clubMatch.name })"></p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 mt-3.5">
                    <button type="button" @click="matchItsUs()" class="m-press h-11 px-4 rounded-xl text-white text-[13px] font-black" style="background: {{ $ev }};" x-text="t.matchItsUs"></button>
                    <button type="button" x-show="clubMatch.reason === 'name'" @click="matchDifferent()"
                            class="m-press h-11 px-4 rounded-xl bg-white text-[13px] font-bold text-foreground" style="border:1.5px solid hsl(210 14% 88%);" x-text="t.matchDifferent"></button>
                </div>
            </div>
        </template>

        <div class="inv-card overflow-hidden">
            <x-event-section-band :color="$e['color']" icon="bi-building" :title="$t['tClub'] ?? ''" />
            <div class="p-5 space-y-4">
                {{-- Logo --}}
                <div>
                    <span class="inv-label"><span x-text="t.clubLogo"></span> <span class="font-medium text-muted-foreground">· <span x-text="t.optional"></span></span></span>
                    <div class="flex items-center gap-3.5">
                        <span class="flex-none grid place-items-center overflow-hidden" style="width:72px; height:72px; border:1.5px dashed #c3cad6; background:#f7f8fb;"
                              :style="{ borderRadius: club.logoShape === 'circle' ? '999px' : '16px' }">
                            <template x-if="club.logo"><img :src="club.logo" alt="" class="w-full h-full object-contain"></template>
                            <template x-if="! club.logo"><i class="bi bi-image text-xl text-muted-foreground"></i></template>
                        </span>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" @click="pickPhoto('logo', true)" class="inv-chip m-press"><i class="bi bi-camera"></i><span x-text="t.camera"></span></button>
                            <button type="button" @click="pickPhoto('logo', false)" class="inv-chip m-press"><i class="bi bi-folder2-open"></i><span x-text="t.chooseFile"></span></button>
                            <button type="button" x-show="club.logo" @click="club.logo = null" class="inv-chip m-press" style="color:#b4483c;"><i class="bi bi-trash3"></i><span x-text="t.remove"></span></button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="inv-label" x-text="t.clubName"></label>
                    <input type="text" x-model="club.name" @input="clubEdited()" :placeholder="t.clubNamePh" maxlength="150" autocomplete="organization"
                           class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                </div>
                <div>
                    <label class="inv-label" x-text="t.coach"></label>
                    <input type="text" x-model="club.coach" @input="error = ''" :placeholder="t.coachPh" maxlength="150" autocomplete="name"
                           class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                </div>
                <div data-club-phone>
                    <label class="inv-label" x-text="t.phone"></label>
                    <x-fields.phone-code id="invClubDial" name="club_dial" :value="'+973'" model="club.dial" :syncWithCountry="false"
                                         groupClass="e-field w-full rounded-2xl flex items-stretch overflow-hidden">
                        <input type="tel" inputmode="tel" autocomplete="tel" x-model="club.phone" @input="clubEdited()" maxlength="20"
                               class="w-full h-12 px-4 bg-transparent text-[15px] focus:outline-none" style="border:0; box-shadow:none;">
                    </x-fields.phone-code>
                </div>
                <div>
                    <label class="inv-label" x-text="t.email"></label>
                    <input type="email" x-model="club.email" @input="clubEdited()" maxlength="191" autocomplete="email" dir="ltr"
                           class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                </div>
                <div>
                    <label class="inv-label" x-text="t.address"></label>
                    <input type="text" x-model="club.address" @input="error = ''" :placeholder="t.addressPh" maxlength="250" autocomplete="street-address"
                           class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                </div>

                <button type="button" x-show="clubMode === 'new'" @click="backToChoice()"
                        class="text-[12.5px] font-black" style="color: {{ $ev }};" x-text="t.idBackExisting"></button>
            </div>
        </div>
    </div>

    {{-- ===== D. Team officials — optional until the draw ===== --}}
    <div x-show="clubMode === 'new' || (clubMode === 'existing' && verified)" class="inv-card overflow-hidden">
        <x-event-section-band :color="$e['color']" icon="bi-person-badge" :title="$t['staffTitle'] ?? ''" />
        <div class="p-5">
            <p class="inv-sub" style="margin:0 0 14px;" x-text="t.staffSub"></p>
            <div class="space-y-4">
                <template x-for="k in ['mgr', 'ref1', 'ref2']" :key="k">
                    <div class="flex items-start gap-3.5">
                        {{-- A person's photo is portrait 3:4. --}}
                        <button type="button" @click="pickPhoto(k, false)" class="m-press flex-none grid place-items-center overflow-hidden"
                                style="width:60px; height:80px; border-radius:14px; background: {{ $evSoft }}; border:1.5px dashed {{ $ev }}66;"
                                :aria-label="t.photoAdd">
                            <template x-if="staff[k].photo"><img :src="staff[k].photo" alt="" class="w-full h-full object-cover"></template>
                            <template x-if="! staff[k].photo"><i class="bi bi-camera text-lg" style="color: {{ $ev }};"></i></template>
                        </button>
                        <div class="min-w-0 flex-1 space-y-2">
                            <p class="text-[12px] font-black uppercase tracking-wide text-muted-foreground m-0" x-text="k === 'mgr' ? t.manager : (k === 'ref1' ? t.referee1 : t.referee2)"></p>
                            <input type="text" x-model="staff[k].name" :placeholder="t.staffNamePh" maxlength="150"
                                   class="e-field w-full h-11 px-3.5 rounded-xl text-[14px]">
                            <template x-if="k !== 'mgr'">
                                <input type="text" x-model="staff[k].license" :placeholder="t.refLicensePh" maxlength="60"
                                       class="e-field w-full h-11 px-3.5 rounded-xl text-[14px]">
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
