{{-- Step 6 — the club's own entries: where it stands, who is in, what is owed, the history. --}}
<div x-show="step === 6" class="space-y-3">
    <template x-if="baseEntry()">
        <div class="space-y-3 inv-in">
            <div class="inv-card p-5">
                <div class="flex items-center gap-3">
                    <template x-if="club.logo"><span class="flex-none" style="width:44px; height:44px;"><img :src="club.logo" alt="" class="w-full h-full object-contain"></span></template>
                    <template x-if="! club.logo"><span class="flex-none grid place-items-center text-[13px] font-black text-white" style="width:44px; height:44px; border-radius:14px; background: {{ $ev }};" x-text="initials(club.name)"></span></template>
                    <div class="min-w-0 flex-1">
                        <p class="text-[15px] font-black text-foreground leading-tight truncate" x-text="club.name"></p>
                        <p class="text-[11.5px] text-muted-foreground mt-0.5"><span x-text="t.reference"></span> · <span class="font-black" dir="ltr" style="white-space:nowrap;" x-text="baseEntry().ref"></span></p>
                    </div>
                </div>
                <div class="mt-4 rounded-2xl px-4 py-3 text-[12.5px] leading-relaxed"
                     :style="phase() === 'open' ? 'background:#e8f5ee;color:#14663c' : (phase() === 'late' ? 'background:#fdf1e0;color:#7a4a0c' : 'background:#fdecea;color:#8f2f25')"
                     x-text="mgPhaseMsg()"></div>

                {{-- The four figures. --}}
                <div class="grid grid-cols-2 gap-2.5 mt-4">
                    <template x-for="(s, i) in mgStats()" :key="i">
                        <div class="rounded-2xl px-3.5 py-3" style="background:#f7f8fb;">
                            <p class="text-[10.5px] font-bold uppercase tracking-wide text-muted-foreground m-0" x-text="s[0]"></p>
                            <p class="text-[17px] font-black mt-1 m-0" :style="s[2] === 'green' ? 'color:#2f7a4f' : (s[2] === 'amber' ? 'color:#a8620f' : 'color:#1e2c4f')" x-text="s[1]"></p>
                        </div>
                    </template>
                </div>
            </div>

            <div class="inv-card overflow-hidden">
                <x-event-section-band :color="$e['color']" icon="bi-people-fill" :title="$t['yourEntries'] ?? ''" />
                <div class="p-5">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[13px] font-black text-foreground m-0" x-text="mgCountLabel()"></p>
                        <button type="button" x-show="phase() !== 'locked'" @click="startAdd()" class="inv-chip m-press is-on"><i class="bi bi-plus-lg"></i><span x-text="t.addAthletes"></span></button>
                    </div>
                    <div class="mt-2">
                        <template x-for="(a, i) in baseEntry().athletes" :key="i">
                            <div class="flex items-center gap-3 py-3 border-t border-gray-100" :style="a.status === 'Withdrawn' ? 'opacity:.6' : ''">
                                <div class="min-w-0 flex-1">
                                    <p class="text-[14px] font-bold text-foreground m-0">
                                        <span x-text="a.name"></span>
                                        <span class="text-[11px] font-medium text-muted-foreground" dir="ltr" style="white-space:nowrap;" x-text="a.athleteId || ''"></span>
                                    </p>
                                    <p class="text-[11.5px] text-muted-foreground m-0" x-text="mgMeta(a)"></p>
                                    <p class="text-[11.5px] text-muted-foreground m-0" x-text="(a.types || []).join(' · ') + (a.late ? ' · Late' : '')"></p>
                                </div>
                                <span class="flex-none px-2.5 py-1 rounded-full text-[10.5px] font-black"
                                      :style="a.status === 'Withdrawn' ? 'color:#6b7689;background:#eef1f6' : 'color:#2f7a4f;background:#e8f5ee'"
                                      x-text="a.status === 'Withdrawn' ? t.withdrawn : t.active"></span>
                                <button type="button" x-show="a.status !== 'Withdrawn' && phase() !== 'locked'" @click="withdraw(i)"
                                        class="flex-none text-[12px] font-black" style="color:#b4483c;" x-text="t.withdraw"></button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <template x-if="mgInvoice().balance > 0">
                <button type="button" @click="startPay()" class="m-press w-full rounded-2xl text-white font-black text-[15px] flex items-center justify-center gap-2"
                        style="height:52px; background: {{ $ev }}; box-shadow:0 18px 40px -18px {{ $ev }};">
                    <i class="bi bi-cash-coin"></i><span x-text="fill(t.payBalance, { a: money(mgInvoice().balance) })"></span>
                </button>
            </template>

            <div class="inv-card p-5" x-show="mgLog().length">
                <p class="inv-h" x-text="t.historyTitle"></p>
                <div class="mt-3 space-y-2.5">
                    <template x-for="(g, i) in mgLog()" :key="i">
                        <div class="flex items-start gap-3">
                            <span class="w-2 h-2 rounded-full mt-1.5 flex-none" style="background: {{ $ev }};"></span>
                            <div class="min-w-0">
                                <p class="text-[12.5px] font-bold text-foreground m-0" x-text="g.text"></p>
                                <p class="text-[11px] text-muted-foreground m-0" x-text="g.when"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
