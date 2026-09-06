<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div>
        <h5>{{ __('shared.invoices_show_modal_billed_to') }}</h5>
        <p class="mb-1">{{ Auth::user()->full_name }}</p>
        <p class="mb-1">{{ Auth::user()->email }}</p>
        @if(Auth::user()->mobile)
            <p class="mb-0">{{ Auth::user()->mobile }}</p>
        @endif
    </div>
    <div class="md:text-end">
        <h5>{{ __('shared.invoices_show_modal_invoice_details') }}</h5>
        <p class="mb-1">{{ __('shared.invoices_show_modal_invoice_number') }} {{ $invoice->id }}</p>
        <p class="mb-1">{{ __('shared.invoices_show_modal_due_date') }} {{ $invoice->due_date->format('F j, Y') }}</p>
        <p class="mb-0">
            {{ __('shared.invoices_show_modal_status') }}
            @if($invoice->status === 'paid')
                <span class="badge bg-success">{{ __('shared.invoices_show_modal_paid') }}</span>
            @elseif($invoice->status === 'pending')
                <span class="badge bg-warning text-foreground">{{ __('shared.invoices_show_modal_pending') }}</span>
            @else
                <span class="badge bg-danger">{{ __('shared.invoices_show_modal_overdue') }}</span>
            @endif
        </p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div>
        <h5>{{ __('shared.invoices_show_modal_club_information') }}</h5>
        <p class="mb-1">{{ $invoice->tenant->club_name }}</p>
        <p class="mb-0">{{ $invoice->tenant->owner->full_name }} ({{ __('shared.invoices_show_modal_owner') }})</p>
    </div>
    <div class="md:text-end">
        <h5>{{ __('shared.invoices_show_modal_student_information') }}</h5>
        <p class="mb-1">{{ $invoice->student->full_name }}</p>
        <p class="mb-0">{{ __('shared.invoices_show_modal_age') }} {{ $invoice->student->age }} ({{ $invoice->student->life_stage }})</p>
    </div>
</div>

<div class="table-responsive mb-4">
    <table class="table table-bordered">
        <thead class="bg-muted">
            <tr>
                <th>{{ __('shared.invoices_show_modal_description') }}</th>
                <th class="text-end">{{ __('shared.invoices_show_modal_amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('shared.invoices_show_modal_membership_fee') }} - {{ $invoice->tenant->club_name }}</td>
                <td class="text-end">${{ number_format($invoice->amount, 2) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <th>{{ __('shared.invoices_show_modal_total') }}</th>
                <th class="text-end">${{ number_format($invoice->amount, 2) }}</th>
            </tr>
        </tfoot>
    </table>
</div>

<div class="flex justify-end">
    @if($invoice->status !== 'paid')
        <a href="{{ route('bills.pay', $invoice->id) }}" class="btn btn-success">
            <i class="bi bi-credit-card"></i> {{ __('shared.invoices_show_modal_pay_now') }}
        </a>
    @else
        <button class="btn btn-outline-success me-2" disabled>
            <i class="bi bi-check-circle"></i> {{ __('shared.invoices_show_modal_paid') }}
        </button>
        <a href="{{ route('bills.receipt', $invoice->id) }}" class="btn btn-outline-primary" target="_blank">
            <i class="bi bi-receipt"></i> {{ __('shared.invoices_show_modal_view_receipt') }}
        </a>
    @endif
</div>
