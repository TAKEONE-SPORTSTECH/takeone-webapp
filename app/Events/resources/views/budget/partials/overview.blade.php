{{-- Where the event stands at the numbers being tuned: result, income vs costs,
     and the three answers an organiser asks for. Reads the live preview. --}}
<section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5" aria-label="{{ __('events::events.budget_result') }}">
    <div class="flex items-center justify-between gap-3">
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold" :class="statusClass(live.status)">
            <span class="w-2 h-2 rounded-full bg-current"></span><span x-text="statusLabel(live.status)"></span>
        </span>
        <span class="text-xs text-muted-foreground" x-text="money(live.fee) + ' ' + b.currency + ' × ' + live.n"></span>
    </div>

    <p class="text-xs font-semibold text-muted-foreground mt-4">{{ __('events::events.budget_net_result') }}</p>
    <p class="text-4xl font-black tracking-tight mt-0.5"
       :class="live.net < 0 ? 'text-red-700' : (live.status === 'thin' ? 'text-amber-700' : 'text-green-700')">
        <span x-text="signed(live.net)"></span> <span class="text-base font-bold" x-text="b.currency"></span>
    </p>

    <div class="mt-4 space-y-2.5">
        <div>
            <div class="flex justify-between text-xs mb-1"><span class="font-semibold text-gray-700">{{ __('events::events.budget_income') }}</span><span class="font-extrabold" x-text="money(live.revenue)"></span></div>
            <div class="h-2 rounded-full bg-muted overflow-hidden"><div class="h-full rounded-full bg-green-600 transition-all" :style="{ width: live.revW + '%' }"></div></div>
        </div>
        <div>
            <div class="flex justify-between text-xs mb-1"><span class="font-semibold text-gray-700">{{ __('events::events.budget_costs') }}</span><span class="font-extrabold" x-text="money(live.expenses)"></span></div>
            <div class="h-2 rounded-full bg-muted overflow-hidden"><div class="h-full rounded-full transition-all" style="background:#dc6b2f" :style="{ width: live.expW + '%' }"></div></div>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-2 mt-4">
        <div class="rounded-xl bg-muted/60 p-2.5">
            <p class="text-[11px] font-semibold text-muted-foreground leading-tight">{{ __('events::events.budget_break_even_fee') }}</p>
            <p class="text-base font-black mt-1" x-text="money(live.breakEven)"></p>
        </div>
        <div class="rounded-xl bg-muted/60 p-2.5">
            <p class="text-[11px] font-semibold text-muted-foreground leading-tight">{{ __('events::events.budget_athletes_needed') }}</p>
            <p class="text-base font-black mt-1" x-text="live.needed === null ? '—' : live.needed"></p>
        </div>
        <div class="rounded-xl p-2.5" style="background:#efedfe">
            <p class="text-[11px] font-semibold leading-tight" style="color:#5b4bd6">{{ __('events::events.budget_suggested_fee') }}</p>
            <p class="text-base font-black mt-1" style="color:#4c3dc4" x-text="money(live.suggested)"></p>
        </div>
    </div>
    <p class="text-[11px] text-muted-foreground mt-2.5 leading-snug"
       x-text="@js(__('events::events.budget_suggested_hint', ['target' => ':t', 'reserve' => ':r', 'step' => ':s'])).replace(':t', money(b.settings.target_profit)).replace(':r', b.settings.reserve_percent).replace(':s', b.settings.round_to)"></p>
</section>
