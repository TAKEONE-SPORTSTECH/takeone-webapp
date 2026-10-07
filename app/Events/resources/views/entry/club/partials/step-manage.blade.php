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

            {{-- Paying (a host whose clubs send one proof — BOOT.features.proof): the
                 account, what is due, the proofs sent and what became of them. --}}
            <div x-show="feat('proof') && (proofDue() > 0 || proofs().length)" x-cloak class="inv-card p-5">
                <p class="inv-h">{{ __('events::events.club_pay_title') }}</p>
                <template x-if="window.TakeOneFed && TakeOneFed.bank()">
                    <div class="mt-3 grid gap-x-4 gap-y-1.5 text-[12.5px]" style="grid-template-columns:auto 1fr;">
                        <template x-if="TakeOneFed.bank().beneficiary"><span class="text-muted-foreground" x-text="t.beneficiary || 'Beneficiary'"></span></template>
                        <template x-if="TakeOneFed.bank().beneficiary"><span class="font-bold text-foreground" x-text="TakeOneFed.bank().beneficiary"></span></template>
                        <template x-if="TakeOneFed.bank().bank"><span class="text-muted-foreground" x-text="t.bank || 'Bank'"></span></template>
                        <template x-if="TakeOneFed.bank().bank"><span class="font-bold text-foreground" x-text="TakeOneFed.bank().bank"></span></template>
                        <template x-if="TakeOneFed.bank().iban"><span class="text-muted-foreground">IBAN</span></template>
                        <template x-if="TakeOneFed.bank().iban"><span class="font-bold text-foreground break-all" dir="ltr" x-text="TakeOneFed.bank().iban"></span></template>
                        <template x-if="TakeOneFed.bank().benefitpay"><span class="text-muted-foreground">BenefitPay</span></template>
                        <template x-if="TakeOneFed.bank().benefitpay"><span class="font-bold text-foreground" dir="ltr" x-text="TakeOneFed.bank().benefitpay"></span></template>
                    </div>
                </template>
                <div class="mt-3 flex items-center justify-between text-[14px]" x-show="proofDue() > 0">
                    <span class="font-bold text-muted-foreground">{{ __('events::events.club_pay_due') }}</span>
                    <span class="font-black text-foreground" x-text="money(proofDue())"></span>
                </div>

                <div class="mt-3 space-y-2" x-show="proofs().length">
                    <template x-for="(p, i) in proofs()" :key="i">
                        <div class="rounded-xl px-3 py-2.5 text-[12.5px]"
                             :style="p.state === 'approved' ? 'background:#e7f6ec' : (p.state === 'rejected' ? 'background:#fdecea' : 'background:#fdf1e0')">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-black" :style="p.state === 'approved' ? 'color:#15803d' : (p.state === 'rejected' ? 'color:#b4483c' : 'color:#b45309')"
                                      x-text="({ submitted: @js(__('events::events.club_payments_state_submitted')), approved: @js(__('events::events.club_payments_state_approved')), rejected: @js(__('events::events.club_payments_state_rejected')) })[p.state]"></span>
                                <span class="font-bold text-foreground" x-text="money(p.amount)"></span>
                            </div>
                            <p class="m-0 mt-1 text-foreground/80" x-show="p.reason" x-text="p.reason"></p>
                        </div>
                    </template>
                </div>

                <button type="button" x-show="proofDue() > 0" @click="$refs.proofInput.click()" :disabled="proofBusy"
                        class="m-press w-full mt-4 rounded-2xl text-white font-black text-[15px] flex items-center justify-center gap-2 disabled:opacity-60"
                        style="height:52px; background: {{ $ev }}; box-shadow:0 18px 40px -18px {{ $ev }};">
                    <i class="bi" :class="proofBusy ? 'bi-hourglass-split' : 'bi-receipt'"></i>
                    <span x-text="proofBusy ? @js(__('events::events.club_pay_uploading')) : @js(__('events::events.club_pay_upload'))"></span>
                </button>
                <p class="text-[11px] text-muted-foreground text-center mt-2" x-show="proofDue() > 0">{{ __('events::events.club_pay_pick') }}</p>
                <input type="file" x-ref="proofInput" accept="image/*" class="hidden" @change="sendProof($event)">
            </div>

            <template x-if="! feat('proof') && mgInvoice().balance > 0">
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
