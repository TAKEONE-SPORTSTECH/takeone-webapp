{{-- Invoice statement list — rendered on load and returned for AJAX filtering. --}}
@if($list->count() > 0)
    <div class="space-y-8">
        @foreach($grouped as $month => $monthInvoices)
            @php
                $monthTotal = $monthInvoices->sum('amount');
                $monthCur   = $monthInvoices->first()?->tenant?->currency ?? $currency;
            @endphp
            <section class="bill-month" style="--i: {{ $loop->index }}">
                {{-- Month divider --}}
                <div class="flex items-center gap-3 mb-3">
                    <h3 class="text-sm font-bold text-gray-900 tracking-tight">{{ $month }}</h3>
                    <span class="h-px flex-1 bg-gradient-to-r from-gray-200 to-transparent"></span>
                    <span class="text-xs font-semibold text-muted-foreground tabular-nums">
                        {{ $monthInvoices->count() }} {{ \Illuminate\Support\Str::plural('bill', $monthInvoices->count()) }} ·
                        {{ $monthCur }} {{ number_format($monthTotal, 2) }}
                    </span>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-3">
                    @foreach($monthInvoices as $invoice)
                        @php
                            $cur      = $invoice->tenant?->currency ?? $currency;
                            $isPaid   = $invoice->status === 'paid';
                            $due      = $invoice->due_date;
                            $isOverdue = !$isPaid && $due && $due->lt($today);
                            $daysToDue = $due ? $today->diffInDays($due, false) : null;
                            $dueSoon   = !$isPaid && $daysToDue !== null && $daysToDue >= 0 && $daysToDue <= 7;
                            $club      = $invoice->tenant;
                            $student   = $invoice->student;
                            // Accent per state
                            $accent = $isPaid ? 'emerald' : ($isOverdue ? 'red' : ($dueSoon ? 'amber' : 'primary'));
                        @endphp
                        <article
                            class="bill-card group relative overflow-hidden rounded-2xl border border-gray-100 bg-white p-4 sm:p-5 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-300"
                            style="--d: {{ $loop->parent->index * 0.04 + $loop->index * 0.05 }}s">

                            {{-- State accent spine --}}
                            <span class="absolute inset-y-0 start-0 w-1
                                @class([
                                    'bg-emerald-400'  => $accent === 'emerald',
                                    'bg-red-400'      => $accent === 'red',
                                    'bg-amber-400'    => $accent === 'amber',
                                    'bg-primary'      => $accent === 'primary',
                                ])"></span>

                            <div class="flex items-start gap-3 sm:gap-4">
                                {{-- Club crest --}}
                                <div class="shrink-0">
                                    @if($club?->logo)
                                        <img src="{{ asset('storage/'.$club->logo) }}" alt="{{ $club->club_name }}"
                                             class="w-12 h-12 rounded-xl object-cover border border-gray-100 bg-white">
                                    @else
                                        <div class="w-12 h-12 rounded-xl bg-accent text-primary flex items-center justify-center font-bold text-lg">
                                            {{ mb_strtoupper(mb_substr($club->club_name ?? 'C', 0, 1, 'UTF-8'), 'UTF-8') }}
                                        </div>
                                    @endif
                                </div>

                                {{-- Body --}}
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-gray-900 leading-snug truncate">{{ $club->club_name ?? __('shared.templates_invoices_list_club') }}</p>
                                            <p class="text-xs text-muted-foreground mt-0.5 flex items-center gap-1.5">
                                                <i class="bi bi-person-circle"></i>
                                                <span class="truncate">{{ $student->full_name ?? __('shared.templates_invoices_list_member') }}</span>
                                                <span class="text-gray-300">·</span>
                                                <span class="font-mono text-gray-400">#{{ str_pad($invoice->id, 5, '0', STR_PAD_LEFT) }}</span>
                                            </p>
                                        </div>
                                        {{-- Amount --}}
                                        <div class="text-end shrink-0">
                                            <p class="text-lg font-bold text-gray-900 tabular-nums leading-none">{{ number_format($invoice->amount, 2) }}</p>
                                            <p class="text-[10px] font-semibold text-muted-foreground tracking-wider uppercase mt-1">{{ $cur }}</p>
                                        </div>
                                    </div>

                                    {{-- Status + due meta --}}
                                    <div class="flex items-center flex-wrap gap-2 mt-3">
                                        @if($isPaid)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">
                                                <i class="bi bi-check-circle-fill"></i> {{ __('shared.templates_invoices_list_paid') }}
                                            </span>
                                        @elseif($isOverdue)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-600">
                                                <i class="bi bi-exclamation-octagon-fill"></i>
                                                {{ __('shared.templates_invoices_list_overdue') }} · {{ abs($daysToDue) }}d
                                            </span>
                                        @elseif($dueSoon)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700">
                                                <i class="bi bi-hourglass-split"></i>
                                                {{ $daysToDue === 0 ? __('shared.templates_invoices_list_due_today') : __('shared.templates_invoices_list_due_in', ['days' => $daysToDue]) }}
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                                <i class="bi bi-clock"></i> {{ __('shared.templates_invoices_list_pending') }}
                                            </span>
                                        @endif

                                        @if($due)
                                            <span class="text-xs text-muted-foreground inline-flex items-center gap-1">
                                                <i class="bi bi-calendar3"></i> {{ $due->format('M d, Y') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center gap-2 mt-4 pt-4 border-t border-gray-50">
                                <a href="{{ route('bills.show', $invoice->id) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-gray-600 bg-gray-50 hover:bg-gray-100 transition-colors">
                                    <i class="bi bi-eye"></i> {{ __('shared.templates_invoices_list_details') }}
                                </a>
                                @if($isPaid)
                                    <a href="{{ route('bills.receipt', $invoice->id) }}"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 transition-colors">
                                        <i class="bi bi-download"></i> {{ __('shared.templates_invoices_list_receipt') }}
                                    </a>
                                @else
                                    <a href="{{ route('bills.pay', $invoice->id) }}"
                                       class="ms-auto inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg text-xs font-semibold text-white bg-primary hover:bg-primary/90 shadow-sm transition-colors">
                                        <i class="bi bi-credit-card-2-front"></i> {{ __('shared.templates_invoices_list_pay_now') }}
                                    </a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@else
    <div class="text-center py-16 px-4">
        <div class="w-20 h-20 mx-auto mb-5 rounded-2xl bg-accent/60 flex items-center justify-center">
            <i class="bi bi-receipt-cutoff text-4xl text-primary/70"></i>
        </div>
        <h3 class="text-base font-bold text-gray-900">{{ __('shared.templates_invoices_list_empty_title') }}</h3>
        <p class="text-sm text-muted-foreground mt-1 max-w-sm mx-auto">
            @if($status || request('start_date') || request('end_date'))
                {{ __('shared.templates_invoices_list_empty_filtered') }}
            @else
                {{ __('shared.templates_invoices_list_empty_default') }}
            @endif
        </p>
        @if($status || request('start_date') || request('end_date'))
            <a href="{{ route('bills.index') }}" class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 rounded-lg text-sm font-semibold text-primary border border-primary/30 hover:bg-accent transition-colors">
                <i class="bi bi-arrow-counterclockwise"></i> {{ __('shared.templates_invoices_list_clear_filters') }}
            </a>
        @endif
    </div>
@endif
