@extends('layouts.app')

@section('content')
<div class="px-4 py-4">
    <div class="flex justify-center">
        <div class="w-full md:w-2/3">
            <div class="card shadow-sm">
                <div class="card-header bg-card flex justify-between items-center">
                    <h4 class="mb-0">{{ __('shared.templates_invoices_show_invoice') }} #{{ $invoice->id }}</h4>
                    <a href="{{ route('bills.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> {{ __('shared.templates_invoices_show_back_to_bills') }}
                    </a>
                </div>
                <div class="card-body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <h5>{{ __('shared.templates_invoices_show_billed_to') }}</h5>
                            <p class="mb-1">{{ Auth::user()->full_name }}</p>
                            <p class="mb-1">{{ Auth::user()->email }}</p>
                            @if(Auth::user()->mobile)
                                <p class="mb-0">{{ Auth::user()->mobile }}</p>
                            @endif
                        </div>
                        <div class="md:text-end">
                            <h5>{{ __('shared.templates_invoices_show_invoice_details') }}</h5>
                            <p class="mb-1">{{ __('shared.templates_invoices_show_invoice_no_label') }} {{ $invoice->id }}</p>
                            <p class="mb-1">{{ __('shared.templates_invoices_show_due_date_label') }} {{ $invoice->due_date->format('F j, Y') }}</p>
                            <p class="mb-0">
                                {{ __('shared.templates_invoices_show_status_label') }}
                                @if($invoice->status === 'paid')
                                    <span class="badge bg-success">{{ __('shared.templates_invoices_show_paid') }}</span>
                                @elseif($invoice->status === 'pending')
                                    <span class="badge bg-warning text-foreground">{{ __('shared.templates_invoices_show_pending') }}</span>
                                @else
                                    <span class="badge bg-danger">{{ __('shared.templates_invoices_show_overdue') }}</span>
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <h5>{{ __('shared.templates_invoices_show_club_information') }}</h5>
                            <p class="mb-1">{{ $invoice->tenant->club_name }}</p>
                            <p class="mb-0">{{ $invoice->tenant->owner->full_name }} {{ __('shared.templates_invoices_show_owner') }}</p>
                        </div>
                        <div class="md:text-end">
                            <h5>{{ __('shared.templates_invoices_show_student_information') }}</h5>
                            <p class="mb-1">{{ $invoice->student->full_name }}</p>
                            <p class="mb-0">{{ __('shared.templates_invoices_show_age_label') }} {{ $invoice->student->age }} ({{ $invoice->student->life_stage }})</p>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead class="bg-muted">
                                <tr>
                                    <th>{{ __('shared.templates_invoices_show_description') }}</th>
                                    <th class="text-end">{{ __('shared.templates_invoices_show_amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ __('shared.templates_invoices_show_membership_fee') }} {{ $invoice->tenant->club_name }}</td>
                                    <td class="text-end">${{ number_format($invoice->amount, 2) }}</td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>{{ __('shared.templates_invoices_show_total') }}</th>
                                    <th class="text-end">${{ number_format($invoice->amount, 2) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="flex justify-end">
                        @if($invoice->status !== 'paid')
                            <a href="{{ route('bills.pay', $invoice->id) }}" class="btn btn-success">
                                <i class="bi bi-credit-card"></i> {{ __('shared.templates_invoices_show_pay_now') }}
                            </a>
                        @else
                            <button class="btn btn-outline-success me-2" disabled>
                                <i class="bi bi-check-circle"></i> {{ __('shared.templates_invoices_show_paid') }}
                            </button>
                            <a href="{{ route('bills.receipt', $invoice->id) }}" class="btn btn-outline-primary" target="_blank">
                                <i class="bi bi-receipt"></i> {{ __('shared.templates_invoices_show_view_receipt') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
