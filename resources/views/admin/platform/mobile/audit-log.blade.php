@extends('layouts.app')

@section('hide-navbar', true)
@section('title', 'Audit Log')

@section('content')
@php
    $fieldLabels = [
        'club_name'=>__('platform.field_club_name'),'slug'=>__('platform.field_slug'),'status'=>__('platform.field_status'),'enrollment_fee'=>__('platform.field_enrollment_fee'),'vat_percentage'=>__('platform.field_vat_percentage'),
        'owner_user_id'=>__('platform.field_owner_user_id'),'email'=>__('platform.field_email'),'name'=>__('platform.field_name'),'price'=>__('platform.field_price'),'duration_months'=>__('platform.field_duration_months'),
        'session_count'=>__('platform.field_session_count'),'age_min'=>__('platform.field_age_min'),'age_max'=>__('platform.field_age_max'),'gender'=>__('platform.field_gender'),'role'=>__('platform.field_role'),'rating'=>__('platform.field_rating'),
        'description'=>__('platform.field_description'),'location'=>__('platform.field_location'),'date'=>__('platform.field_date'),'start_time'=>__('platform.field_start_time'),'end_time'=>__('platform.field_end_time'),
        'max_capacity'=>__('platform.field_max_capacity'),'is_archived'=>__('platform.field_is_archived'),'payment_status'=>__('platform.field_payment_status'),'amount_paid'=>__('platform.field_amount_paid'),
        'amount_due'=>__('platform.field_amount_due'),'start_date'=>__('platform.field_start_date'),'end_date'=>__('platform.field_end_date'),'type'=>__('platform.field_type'),'category'=>__('platform.field_category'),
        'amount'=>__('platform.field_amount'),'transaction_date'=>__('platform.field_transaction_date'),'ip'=>__('platform.field_ip'),'user_agent'=>__('platform.field_user_agent'),
        'registrants'=>__('platform.field_registrants'),'pay_later'=>__('platform.field_pay_later'),'guardian_email'=>__('platform.field_guardian_email'),'people_count'=>__('platform.field_people_count'),'club_id'=>__('platform.field_club_id'),
    ];
    $modelLabels = ['Tenant'=>__('platform.model_Tenant'),'ClubPackage'=>__('platform.model_ClubPackage'),'ClubInstructor'=>__('platform.model_ClubInstructor'),'ClubFacility'=>__('platform.model_ClubFacility'),'ClubActivity'=>__('platform.model_ClubActivity'),'ClubEvent'=>__('platform.model_ClubEvent'),'ClubMemberSubscription'=>__('platform.model_ClubMemberSubscription'),'ClubTransaction'=>__('platform.model_ClubTransaction')];
    $moneyFields = ['price','enrollment_fee','amount_paid','amount_due','amount'];
    $formatValue = function($field,$val) use ($moneyFields) {
        if (is_null($val) || $val === '') return __('platform.not_set');
        if (is_bool($val)) return $val ? __('platform.yes') : __('platform.no');
        if ($val === 1 || $val === '1') return __('platform.yes');
        if ($val === 0 || $val === '0') return __('platform.no');
        if (in_array($field,$moneyFields)) return number_format((float)$val,2);
        if (is_array($val)) return implode(', ',$val);
        if (preg_match('/^\d{4}-\d{2}-\d{2}T/',$val)) { try { return \Carbon\Carbon::parse($val)->format('d M Y, H:i'); } catch (\Exception $e) {} }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$val)) { try { return \Carbon\Carbon::parse($val)->format('d M Y'); } catch (\Exception $e) {} }
        return ucfirst(str_replace('_',' ',$val));
    };
    $hasFilters = $search || $logName || $event || $dateFrom || $dateTo;
@endphp
<div x-data="{ filters: {{ $hasFilters ? 'true' : 'false' }} }" class="min-h-screen bg-background pb-20">

    {{-- ===== Header ===== --}}
    <header class="sticky top-0 z-40 bg-white border-b border-border">
        <div class="flex items-center gap-2 px-3 h-14">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('admin.platform.index') }}')"
                    class="m-press w-10 h-10 -ml-1 rounded-xl flex items-center justify-center text-foreground" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-xl"></i>
            </button>
            <p class="flex-1 min-w-0 text-base font-bold text-primary truncate">{{ __('platform.audit_log') }}</p>
            <button type="button" @click="filters = !filters" class="m-press w-10 h-10 rounded-xl flex items-center justify-center {{ $hasFilters ? 'text-primary' : 'text-muted-foreground' }}" aria-label="{{ __('platform.filters') }}">
                <i class="bi bi-funnel{{ $hasFilters ? '-fill' : '' }} text-lg"></i>
            </button>
        </div>
    </header>

    {{-- ===== Filters ===== --}}
    <form method="GET" action="{{ route('admin.platform.audit-log') }}" class="px-4 pt-4">
        <div class="relative">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('platform.search_audit') }}"
                   class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/40 focus:border-transparent text-sm">
        </div>

        <div x-show="filters" x-collapse class="mt-3 bg-white rounded-2xl p-4 shadow-sm border border-gray-100 space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-medium text-muted-foreground mb-1">{{ __('platform.channel') }}</label>
                    <x-select-menu name="log_name" :value="$logName ?? ''" :placeholder="__('platform.filter_all')"
                                   :options="collect($logNames)->map(fn($n) => ['value' => $n, 'label' => ucfirst($n)])->all()" />
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-muted-foreground mb-1">{{ __('platform.event') }}</label>
                    <x-select-menu name="event" :value="$event ?? ''" :placeholder="__('platform.filter_all')"
                                   :options="[['value' => 'created', 'label' => 'Created'], ['value' => 'updated', 'label' => 'Updated'], ['value' => 'deleted', 'label' => 'Deleted']]" />
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-muted-foreground mb-1">{{ __('platform.from') }}</label>
                    <input type="date" name="date_from" value="{{ $dateFrom ?? '' }}" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-muted-foreground mb-1">{{ __('platform.to') }}</label>
                    <input type="date" name="date_to" value="{{ $dateTo ?? '' }}" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm">
                </div>
            </div>
            <div class="flex gap-2 pt-1">
                <button type="submit" class="m-press flex-1 bg-primary text-white py-2.5 rounded-lg font-semibold text-sm"><i class="bi bi-funnel mr-1"></i>{{ __('platform.apply') }}</button>
                @if($hasFilters)
                    <a href="{{ route('admin.platform.audit-log') }}" class="m-press px-4 py-2.5 rounded-lg border border-gray-200 text-gray-600 text-sm font-medium flex items-center"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </div>
    </form>

    {{-- ===== Entries ===== --}}
    <div class="px-4 mt-4 space-y-3 mobile-stagger">
        @forelse($logs as $log)
            @php
                $subjectBase = $log->subject_type ? class_basename($log->subject_type) : null;
                $subjectLabel = $modelLabels[$subjectBase] ?? $subjectBase;
                $old = $log->properties['old'] ?? [];
                $new = $log->properties['attributes'] ?? [];
                $channelColors = ['auth'=>'bg-blue-100 text-blue-700','club'=>'bg-purple-100 text-purple-700','membership'=>'bg-green-100 text-green-700','financial'=>'bg-yellow-100 text-yellow-700','default'=>'bg-gray-100 text-gray-700'];
                $channelColor = $channelColors[$log->log_name] ?? $channelColors['default'];
                $eventColors = ['created'=>'bg-green-100 text-green-700','updated'=>'bg-blue-100 text-blue-700','deleted'=>'bg-red-100 text-red-700'];
                $eventColor = $eventColors[$log->event] ?? 'bg-gray-100 text-gray-600';
            @endphp
            <div class="m-card bg-white rounded-2xl p-4 shadow-sm border border-gray-100">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-2 py-0.5 rounded text-[11px] font-medium {{ $channelColor }}">{{ ucfirst($log->log_name ?? '—') }}</span>
                    @if($log->event)<span class="px-2 py-0.5 rounded text-[11px] font-medium {{ $eventColor }}">{{ ucfirst($log->event) }}</span>@endif
                    <span class="ml-auto text-[11px] text-muted-foreground">{{ $log->created_at->format('d M Y · H:i') }}</span>
                </div>
                <p class="text-sm text-foreground mt-2">{{ $log->description }}</p>
                <div class="flex items-center gap-2 mt-2 text-[12px] text-muted-foreground">
                    <i class="bi bi-person-circle"></i>
                    <span class="truncate">{{ $log->causer?->full_name ?? __('platform.system') }}</span>
                    @if($subjectLabel)<span class="ml-auto">{{ $subjectLabel }}{{ $log->subject_id ? ' #'.$log->subject_id : '' }}</span>@endif
                </div>
                @if(!empty($new))
                    <div class="mt-3 pt-3 border-t border-gray-100 space-y-1.5">
                        @foreach($new as $field => $newVal)
                            @php $oldVal = $old[$field] ?? null; $label = $fieldLabels[$field] ?? ucwords(str_replace('_',' ',$field)); $changed = $oldVal !== null && $oldVal !== $newVal; @endphp
                            <div class="text-[12px]">
                                <span class="font-semibold text-foreground">{{ $label }}</span>
                                @if($changed)
                                    <span class="ml-1 px-1.5 py-0.5 rounded bg-red-50 text-red-600 line-through">{{ $formatValue($field,$oldVal) }}</span>
                                    <i class="bi bi-arrow-right text-muted-foreground mx-0.5"></i>
                                    <span class="px-1.5 py-0.5 rounded bg-green-50 text-green-700 font-medium">{{ $formatValue($field,$newVal) }}</span>
                                @else
                                    <span class="text-muted-foreground ml-1">{{ $formatValue($field,$newVal) }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <div class="bg-white rounded-2xl px-6 py-14 text-center shadow-sm border border-gray-100">
                <i class="bi bi-journal-text text-5xl text-gray-300 m-float inline-block"></i>
                <p class="text-sm font-semibold text-foreground mt-4">{{ __('platform.no_logs_found') }}</p>
                <p class="text-[12px] text-muted-foreground mt-1">{{ $hasFilters ? __('platform.no_logs_match') : __('platform.no_activity') }}</p>
            </div>
        @endforelse

        @if($logs->count() > 0)
            <p class="text-center text-[12px] text-muted-foreground pt-1">{{ __('platform.showing_range', ['first' => $logs->firstItem(), 'last' => $logs->lastItem(), 'total' => number_format($logs->total())]) }}</p>
            <div class="flex justify-center">{{ $logs->withQueryString()->links() }}</div>
        @endif
    </div>
</div>
@endsection
