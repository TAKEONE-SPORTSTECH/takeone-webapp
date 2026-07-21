@extends('layouts.app')

@section('hide-navbar', true)
@section('title', 'Businesses')

@php use App\Models\Business; @endphp

@section('content')
<div class="min-h-screen bg-background pb-20">

    {{-- ===== Header ===== --}}
    <header class="sticky top-0 z-40 bg-white border-b border-border">
        <div class="flex items-center gap-2 px-3 h-14">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('admin.platform.index') }}')"
                    class="m-press w-10 h-10 -ml-1 rounded-xl flex items-center justify-center text-foreground" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-xl"></i>
            </button>
            <p class="flex-1 min-w-0 text-base font-bold text-primary truncate">{{ __('platform.businesses') }}</p>
            <span id="bizPendingBadge" class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-100 text-amber-700 {{ $pendingCount > 0 ? '' : 'hidden' }}">
                <i class="bi bi-hourglass-split mr-1"></i>{{ $pendingCount }}
            </span>
        </div>
    </header>

    <div class="px-4 pt-4 space-y-4">
        {{-- Search --}}
        <div class="relative">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" id="bizSearch" autocomplete="off" placeholder="{{ __('platform.search_businesses') }}"
                   class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/40 focus:border-transparent text-sm">
        </div>

        {{-- Status filters — split the full width evenly so all four fit without scrolling --}}
        <div class="flex gap-1.5" id="bizFilters">
            @php
                $filterDefs = [
                    'all'      => [__('platform.filter_all'), 'bg-gray-100 text-gray-700', $counts['all']],
                    'pending'  => [__('platform.filter_pending'), 'bg-amber-100 text-amber-700', $counts['pending']],
                    'approved' => [__('platform.filter_approved'), 'bg-green-100 text-green-700', $counts['approved']],
                    'rejected' => [__('platform.filter_rejected'), 'bg-red-100 text-red-700', $counts['rejected']],
                ];
            @endphp
            @foreach($filterDefs as $key => $def)
                <button type="button" data-status="{{ $key }}"
                        class="biz-filter-btn relative flex-1 min-w-0 inline-flex items-center justify-center px-2 py-1.5 rounded-full text-[11px] font-medium border transition-colors {{ $key === 'all' ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 border-gray-200' }}">
                    <span class="truncate">{{ $def[0] }}</span>
                    <span class="biz-filter-count absolute -top-1.5 -end-1 min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-red-500 text-white text-[9px] font-black inline-flex items-center justify-center shadow-sm ring-2 ring-white"
                          data-status-count="{{ $key }}">{{ $def[2] }}</span>
                </button>
            @endforeach
        </div>

        {{-- Empty / no-results --}}
        <div id="bizEmpty" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center {{ $businesses->isEmpty() ? '' : 'hidden' }}">
            <i class="bi bi-buildings text-4xl text-gray-300 m-float inline-block"></i>
            <p class="text-sm text-muted-foreground mt-3">{{ __('platform.no_businesses_yet') }}</p>
        </div>
        <div id="bizNoResults" class="hidden bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
            <i class="bi bi-search text-4xl text-gray-300"></i>
            <p class="text-sm text-muted-foreground mt-3">{{ __('platform.no_businesses_match') }}</p>
        </div>

        {{-- Grid (reuses the desktop card partial) --}}
        <div class="grid grid-cols-1 gap-3 mobile-stagger {{ $businesses->isEmpty() ? 'hidden' : '' }}" id="bizGrid">
            @foreach($businesses as $business)
                @include('admin.platform.businesses._card', ['business' => $business])
            @endforeach
        </div>
    </div>
</div>

@php
    $bizData = $businesses->mapWithKeys(fn($b) => [$b->id => [
        'id' => $b->id, 'name' => $b->name, 'description' => $b->description, 'status' => $b->status,
        'rejection_reason' => $b->rejection_reason, 'logo' => $b->logo,
        'logo_url' => $b->logo ? asset('storage/' . $b->logo) : null,
        'owner_name' => $b->owner?->full_name, 'owner_email' => $b->owner?->email, 'clubs_count' => $b->clubs_count,
    ]]);
@endphp
<script>window.__bizData = @json((object) $bizData->toArray());</script>

<x-business-edit-modal />
<x-user-picker-modal :title="__('platform.select_business_owner')" :subtitle="__('platform.select_business_owner_desc')" />

@include('admin.platform.businesses._scripts')
@endsection
