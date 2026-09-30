{{-- Step 4 — the club, the athletes, the invoice, how it is paid, the confirmation. --}}
<div x-show="step === 4" class="space-y-3">

    <div class="inv-card p-5 inv-in">
        <div class="flex items-start gap-3.5">
            <template x-if="club.logo"><span class="flex-none" style="width:48px; height:48px;"><img :src="club.logo" alt="" class="w-full h-full object-contain"></span></template>
            <template x-if="! club.logo"><span class="flex-none grid place-items-center text-[14px] font-black text-white" style="width:48px; height:48px; border-radius:14px; background: {{ $ev }};" x-text="initials(club.name)"></span></template>
            <div class="min-w-0 flex-1">
                <p class="text-[15px] font-black text-foreground leading-tight" x-text="club.name"></p>
                <p class="text-[12px] text-muted-foreground mt-1" x-text="[club.coach, club.email].filter(Boolean).join(' · ')"></p>
                <p class="text-[12px] text-muted-foreground" dir="ltr" x-show="club.phone" x-text="club.dial + ' ' + club.phone"></p>
            </div>
            <button type="button" @click="go(2)" x-show="! amend" class="flex-none text-[12px] font-black" style="color: {{ $ev }};" x-text="t.edit"></button>
        </div>
    </div>

    <div class="inv-card p-5">
        <div class="flex items-center justify-between gap-3">
            <p class="inv-h" x-text="countLabel(entries().length)"></p>
            <button type="button" @click="go(3)" class="flex-none text-[12px] font-black" style="color: {{ $ev }};" x-text="t.edit"></button>
        </div>
        <div class="space-y-2 mt-3">
            <template x-for="g in catGroups()" :key="g.label">
                <div class="rounded-xl px-3.5 py-2.5" style="background:#f7f8fb;">
                    <p class="text-[11.5px] font-black text-foreground m-0 flex items-center gap-2">
                        <span class="truncate" x-text="g.label"></span>
                        <span class="flex-none px-1.5 rounded-md text-[10px]" style="background: {{ $evSoft }}; color: {{ $ev }};" x-text="g.count"></span>
                    </p>
                    <p class="text-[12px] text-muted-foreground mt-0.5 m-0" x-text="g.names"></p>
                </div>
            </template>
        </div>
    </div>

    {{-- ===== The invoice ===== --}}
    <div class="inv-card overflow-hidden">
        <x-event-section-band :color="$e['color']" icon="bi-receipt" :title="$t['invoiceTitle'] ?? ''" />
        <div class="p-5">
            <div class="flex items-center justify-between gap-3 text-[12px]">
                <span class="text-muted-foreground" x-text="t.reference"></span>
                <span class="font-black text-foreground" dir="ltr" style="white-space:nowrap;" x-text="invRef()"></span>
            </div>
            <div class="mt-3">
                <template x-for="(l, i) in invoice().lines" :key="i">
                    <div class="flex items-start gap-3 py-2.5" :class="i ? 'border-t border-gray-100' : ''">
                        <div class="min-w-0 flex-1">
                            <p class="text-[13.5px] font-bold text-foreground m-0" x-text="l.label"></p>
                            <p class="text-[11.5px] text-muted-foreground m-0" x-text="l.detail"></p>
                        </div>
                        <span class="text-[13.5px] font-black text-foreground flex-none" x-text="money(l.amount)"></span>
                    </div>
                </template>
            </div>
            <div class="mt-2 pt-2" style="border-top:2px solid #1e2c4f;">
                <div class="flex justify-between py-1 text-[13px] text-foreground"><span x-text="t.subtotal"></span><span class="font-bold" x-text="money(invoice().total)"></span></div>
                <div x-show="invoice().paid" class="flex justify-between py-1 text-[13px]" style="color:#2f7a4f;"><span x-text="t.alreadyPaid"></span><span class="font-bold" x-text="money(-invoice().paid)"></span></div>
                <div x-show="invoice().pending" class="flex justify-between py-1 text-[13px]" style="color:#a8620f;"><span x-text="t.awaiting"></span><span class="font-bold" x-text="money(-invoice().pending)"></span></div>
                <div class="flex justify-between pt-2 text-[16px] font-black" :style="invoice().balance < 0 ? 'color:#2f7a4f' : 'color:#1e2c4f'">
                    <span x-text="invoice().balance < 0 ? t.credit : t.dueNow"></span>
                    <span x-text="money(invoice().balance < 0 ? -invoice().balance : invoice().balance)"></span>
                </div>
            </div>

            {{-- What to collect from each athlete. --}}
            <div class="mt-4 rounded-2xl" style="background:#f7f8fb;" x-show="invoice().perAthlete.filter(p => ! p.withdrawn).length">
                <button type="button" @click="breakdownOpen = ! breakdownOpen" class="w-full px-4 py-3 flex items-center gap-2 text-start">
                    <span class="min-w-0 flex-1">
                        <span class="block text-[13px] font-black text-foreground" x-text="t.collectTitle"></span>
                        <span class="block text-[11.5px] text-muted-foreground" x-text="t.collectSub"></span>
                    </span>
                    <i class="bi bi-chevron-down transition-transform" :class="breakdownOpen ? 'rotate-180' : ''"></i>
                </button>
                <div x-show="breakdownOpen" class="px-4 pb-3.5">
                    <template x-for="(p, i) in invoice().perAthlete.filter(p => ! p.withdrawn)" :key="i">
                        <div class="flex items-start gap-3 py-2 border-t border-gray-200">
                            <div class="min-w-0 flex-1">
                                <p class="text-[12.5px] font-bold text-foreground m-0" x-text="p.name"></p>
                                <p class="text-[11px] text-muted-foreground m-0" x-text="p.items.map(i => i.label + ' ' + money(i.amount)).join(' + ')"></p>
                            </div>
                            <span class="text-[12.5px] font-black text-foreground flex-none" x-text="money(p.total)"></span>
                        </div>
                    </template>
                    <p class="text-[11px] text-muted-foreground mt-1" x-show="invoice().teams.length"
                       x-text="fill(t.teamsNote, { x: invoice().teams.map(x => x.label + ' × ' + x.n + ' = ' + money(x.amount)).join(', ') })"></p>
                    <button type="button" @click="copyBreakdown()" class="inv-chip m-press mt-2"><i class="bi" :class="copied ? 'bi-check2' : 'bi-clipboard'"></i><span x-text="copied ? t.copied : t.copyBd"></span></button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Payment: settled with the organiser, who records it ===== --}}
    <template x-if="invoice().balance > 0">
        <div class="inv-card p-5">
            <p class="inv-h" x-text="t.payTitle"></p>
            <div class="mt-3 rounded-2xl p-3.5 flex items-start gap-3" style="background: {{ $evSoft }}; border:1px solid {{ $ev }}33;">
                <span class="w-9 h-9 rounded-xl grid place-items-center text-white flex-none" style="background: {{ $ev }};"><i class="bi bi-check2"></i></span>
                <div class="min-w-0">
                    <p class="text-[13.5px] font-black text-foreground m-0" x-text="t.payBankL"></p>
                    <p class="text-[12px] text-muted-foreground mt-0.5 m-0 leading-snug" x-text="t.bankNote"></p>
                </div>
            </div>
            <div class="mt-3 grid gap-x-4 gap-y-1.5 text-[12.5px]" style="grid-template-columns:auto 1fr;">
                <span class="text-muted-foreground" x-text="t.reference"></span><span class="font-black text-foreground" dir="ltr" x-text="invRef()"></span>
                <span class="text-muted-foreground" x-text="t.dueNow"></span><span class="font-black text-foreground" x-text="money(invoice().balance)"></span>
            </div>
        </div>
    </template>

    <template x-if="transferReq.length">
        <div class="rounded-2xl px-4 py-3 text-[12px] leading-snug" style="background:#fdf1e0; color:#7a4a0c;">
            <span class="font-black" x-text="fill(t.transfersN, { n: transferReq.length })"></span> · <span x-text="t.transferReview"></span>
        </div>
    </template>

    <button type="button" @click="confirm = ! confirm; error = ''" :class="confirm ? 'is-on' : ''" :aria-pressed="confirm ? 'true' : 'false'"
            class="m-press e-pick w-full rounded-2xl px-4 py-3.5 flex items-start gap-3 text-start">
        <span class="w-6 h-6 rounded-lg border-2 grid place-items-center flex-shrink-0"
              :class="confirm ? 'text-white' : 'border-gray-300'" :style="confirm ? 'background: {{ $ev }}; border-color: {{ $ev }}' : ''">
            <i class="bi bi-check-lg text-[13px]" x-show="confirm"></i>
        </span>
        <span class="min-w-0 flex-1 text-[13.5px] font-bold text-foreground leading-snug" x-text="t.confirm"></span>
    </button>
</div>
