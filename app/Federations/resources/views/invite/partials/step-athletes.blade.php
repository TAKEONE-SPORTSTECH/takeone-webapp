{{-- Step 3 — who is entering: the club's own roster, new athletes, transfers, teams. --}}
<div x-show="step === 3" class="space-y-3">

    {{-- ===== The club's own athletes (a proved club only) ===== --}}
    <template x-if="clubObj()">
        <div class="inv-card overflow-hidden inv-in">
            <x-event-section-band :color="$e['color']" icon="bi-people-fill" :title="$t['rosterTitle'] ?? ''" />
            <div class="p-5">
                <div class="flex items-center justify-between gap-3">
                    <p class="inv-sub" style="margin:0;" x-text="t.rosterSub"></p>
                    <span class="flex-none px-2.5 py-1 rounded-full text-[11px] font-black" style="background: {{ $evSoft }}; color: {{ $ev }};"
                          x-text="fill(t.picked, { n: pickedCount() })"></span>
                </div>
                <div class="relative mt-3.5">
                    <i class="bi bi-search absolute top-1/2 -translate-y-1/2 text-muted-foreground" style="inset-inline-start:14px;"></i>
                    <input type="text" x-model="rosterQ" :placeholder="t.rosterSearch" autocomplete="off"
                           class="e-field w-full h-11 rounded-2xl text-[14px]" style="padding-inline-start:40px; padding-inline-end:14px;">
                </div>

                <div class="space-y-2 mt-3">
                    <template x-for="a in rosterRows()" :key="a.id">
                        <div class="rounded-2xl transition-colors" :class="isPicked(a) ? 'e-pick is-on' : 'e-pick'">
                            <button type="button" @click="togglePick(a)" class="m-press w-full px-3 py-2.5 flex items-center gap-3 text-start">
                                <span class="w-6 h-6 rounded-lg border-2 grid place-items-center flex-shrink-0"
                                      :class="isPicked(a) ? 'text-white' : 'border-gray-300'"
                                      :style="isPicked(a) ? 'background: {{ $ev }}; border-color: {{ $ev }}' : ''">
                                    <i class="bi bi-check-lg text-[13px]" x-show="isPicked(a)"></i>
                                </span>
                                <span class="flex-none overflow-hidden grid place-items-center" style="width:36px; height:48px; border-radius:10px; background: {{ $evSoft }};">
                                    <template x-if="a.photo"><img :src="a.photo" alt="" class="w-full h-full object-cover"></template>
                                    <template x-if="! a.photo"><span class="text-[11px] font-black" style="color: {{ $ev }};" x-text="initials(a.name)"></span></template>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[14px] font-bold text-foreground truncate" x-text="a.name"></span>
                                    <span class="flex items-center gap-1.5 text-[11.5px] text-muted-foreground mt-0.5">
                                        <span :class="flagOf(a.nat)" style="width:16px; height:12px; border-radius:2px; background-size:cover; flex:none;"></span>
                                        <span class="truncate" x-text="gender(a.gender) + (a.dob ? ' · ' + age(a.dob) : '') + ' · ' + a.id"></span>
                                    </span>
                                </span>
                            </button>
                            <template x-if="isPicked(a)">
                                <div class="px-3 pb-3 space-y-2.5 inv-in">
                                    <div class="flex items-center gap-2.5 flex-wrap">
                                        <label class="text-[11.5px] font-bold text-muted-foreground" x-text="t.todayWeight"></label>
                                        <input type="number" inputmode="decimal" min="15" max="200" step="0.1" dir="ltr"
                                               :value="picks[a.id]" @input="picks = { ...picks, [a.id]: $event.target.value }; error = ''"
                                               class="e-field h-10 px-3 rounded-xl text-[14px] font-bold" style="width:96px;">
                                        <template x-if="classify({ gender: a.gender, dob: a.dob, weight: picks[a.id] })">
                                            <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold" style="background:#eef1f6; color:#1e2c4f;"
                                                  x-text="classify({ gender: a.gender, dob: a.dob, weight: picks[a.id] })"></span>
                                        </template>
                                    </div>
                                    <div>
                                        <p class="text-[11.5px] font-bold text-muted-foreground mb-1.5" x-text="t.enterIn"></p>
                                        <div class="flex flex-wrap gap-1.5">
                                            <template x-for="f in indiv(a.dob)" :key="f.type">
                                                <button type="button" @click="togglePickType(a, f.type)" class="inv-chip m-press"
                                                        :class="(pickTypes[a.id] || []).includes(f.type) ? 'is-on' : ''">
                                                    <span x-text="f.type"></span><span style="opacity:.75;" x-text="money(f.price)"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                    <p class="text-[12px] font-black text-foreground m-0" x-show="(pickTypes[a.id] || []).length">
                                        <span x-text="t.feeFor"></span>: <span x-text="money(feeOf(a.dob, pickTypes[a.id]))"></span>
                                    </p>
                                </div>
                            </template>
                        </div>
                    </template>
                    <p x-show="! rosterRows().length" class="text-[12.5px] text-muted-foreground text-center py-4" x-text="t.rosterNone"></p>
                </div>
            </div>
        </div>
    </template>

    {{-- ===== New athletes on this list ===== --}}
    <div x-show="athletes.length" class="inv-card overflow-hidden">
        <x-event-section-band :color="$e['color']" icon="bi-person-plus-fill" :title="$t['newAthletesTitle'] ?? ''" />
        <div class="p-4 space-y-2.5">
            <template x-for="(a, i) in athletes" :key="i">
                <div class="rounded-2xl p-3 flex items-start gap-3" style="background:#f7f8fb; border:1px solid #eef1f6;">
                    <span class="flex-none overflow-hidden grid place-items-center" style="width:48px; height:64px; border-radius:12px; background: {{ $evSoft }};">
                        <template x-if="a.photo"><img :src="a.photo" alt="" class="w-full h-full object-cover"></template>
                        <template x-if="! a.photo"><span class="text-[12px] font-black" style="color: {{ $ev }};" x-text="initials(a.name)"></span></template>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[14px] font-black text-foreground leading-tight" x-text="a.name"></p>
                        <p class="flex items-center gap-1.5 text-[11.5px] text-muted-foreground mt-1">
                            <span :class="flagOf(a.nat)" style="width:16px; height:12px; border-radius:2px; background-size:cover; flex:none;"></span>
                            <span x-text="gender(a.gender) + ' · ' + a.weight + ' kg'"></span>
                        </p>
                        <p class="text-[11px] font-bold mt-1" style="color:#1e2c4f;" x-show="classify(a)" x-text="classify(a)"></p>
                        <p class="text-[11.5px] text-muted-foreground mt-0.5" x-text="(a.types || []).join(' · ')"></p>
                        <div class="flex items-center gap-3 mt-2">
                            <span class="text-[12px] font-black text-foreground" x-text="money(feeOf(a.dob, a.types))"></span>
                            <span class="flex-1"></span>
                            <button type="button" @click="editAthlete(i)" class="text-[12px] font-black" style="color: {{ $ev }};" x-text="t.edit"></button>
                            <button type="button" @click="removeAthlete(i)" class="text-[12px] font-black" style="color:#b4483c;" x-text="t.remove"></button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ===== Transfer requests ===== --}}
    <div x-show="transferReq.length" class="inv-card p-5">
        <p class="inv-h" x-text="t.transfersTitle"></p>
        <div class="space-y-2 mt-3">
            <template x-for="r in transferRows()" :key="r.id">
                <div class="flex items-center gap-3 rounded-xl px-3 py-2.5" style="background:#fdf1e0;">
                    <i class="bi bi-arrow-left-right" style="color:#a8620f;"></i>
                    <span class="min-w-0 flex-1 text-[13px] font-bold text-foreground truncate" x-text="r.name + (r.from ? ' · ' + r.from + ' → ' + club.name : '')"></span>
                    <span class="flex-none px-2 py-0.5 rounded-full text-[10px] font-black" style="background:#fff; color:#a8620f;" x-text="t.transferPending"></span>
                    <button type="button" @click="removeTransfer(r.id)" class="flex-none text-muted-foreground" :aria-label="t.remove"><i class="bi bi-x-lg"></i></button>
                </div>
            </template>
        </div>
        <p class="inv-sub" x-text="t.transferNote"></p>
    </div>

    {{-- ===== Add an athlete ===== --}}
    <button type="button" @click="openAdd()"
            class="m-press w-full rounded-2xl py-4 flex items-center justify-center gap-2 text-[14px] font-black"
            style="border:2px dashed {{ $ev }}66; background: {{ $evSoft }}; color: {{ $ev }};">
        <i class="bi bi-plus-circle-fill"></i><span x-text="t.addAthlete"></span>
    </button>

    {{-- ===== Team events ===== --}}
    <template x-if="teamFees().length">
        <div class="inv-card overflow-hidden">
            <x-event-section-band :color="$e['color']" icon="bi-people" :title="$t['teamsTitle'] ?? ''" />
            <div class="p-5">
                <p class="inv-sub" style="margin:0 0 12px;" x-text="t.teamsSub"></p>
                <div class="space-y-2.5">
                    <template x-for="f in teamFees()" :key="f.type">
                        <div class="flex items-center gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-[14px] font-bold text-foreground m-0" x-text="f.type"></p>
                                <p class="text-[11.5px] text-muted-foreground m-0" x-text="fill(t.perTeam, { p: money(f.price), n: f.teamSize })"></p>
                            </div>
                            <div class="flex items-center gap-2 flex-none" dir="ltr">
                                <button type="button" @click="teamStep(f.type, -1)" class="m-press w-10 h-10 rounded-xl grid place-items-center text-foreground" style="border:1.5px solid hsl(210 14% 88%);"><i class="bi bi-dash-lg"></i></button>
                                <span class="w-8 text-center text-[16px] font-black text-foreground" x-text="teamN(f.type)"></span>
                                <button type="button" @click="teamStep(f.type, 1)" class="m-press w-10 h-10 rounded-xl grid place-items-center text-white" style="background: {{ $ev }};"><i class="bi bi-plus-lg"></i></button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
