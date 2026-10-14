{{-- What the event has REALLY taken and spent — the console's finance numbers,
     beside the plan. Recorded costs are entered in the console's Finance sheet. --}}
<section class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5" aria-label="{{ __('events::events.budget_actual') }}">
    <h2 class="text-sm font-extrabold text-foreground">{{ __('events::events.budget_actual') }}</h2>
    <p class="text-[11px] text-muted-foreground mt-0.5">{{ __('events::events.budget_actual_hint') }}</p>
    <div class="grid grid-cols-3 gap-2 mt-3">
        <div class="rounded-xl bg-muted/60 p-2.5">
            <p class="text-[11px] font-semibold text-muted-foreground">{{ __('events::events.budget_income') }}</p>
            <p class="text-base font-black mt-1" x-text="money(b.actual.revenue)"></p>
            <p class="text-[10.5px] text-muted-foreground" x-text="@js(__('events::events.budget_paid_people', ['n' => ':n'])).replace(':n', b.actual.paid)"></p>
        </div>
        <div class="rounded-xl bg-muted/60 p-2.5">
            <p class="text-[11px] font-semibold text-muted-foreground">{{ __('events::events.budget_spent') }}</p>
            <p class="text-base font-black mt-1" x-text="money(b.actual.expenses)"></p>
        </div>
        <div class="rounded-xl p-2.5" :class="b.actual.profit < 0 ? 'bg-red-50' : 'bg-green-50'">
            <p class="text-[11px] font-semibold" :class="b.actual.profit < 0 ? 'text-red-700' : 'text-green-700'">{{ __('events::events.budget_profit_so_far') }}</p>
            <p class="text-base font-black mt-1" :class="b.actual.profit < 0 ? 'text-red-700' : 'text-green-700'" x-text="signed(b.actual.profit)"></p>
        </div>
    </div>
</section>
