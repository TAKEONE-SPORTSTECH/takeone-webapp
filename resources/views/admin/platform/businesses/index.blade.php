@extends('layouts.admin')

@section('title', 'Businesses')

@php
    use App\Models\Business;
@endphp

@section('admin-content')
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Businesses</h1>
            <p class="text-sm text-muted-foreground">Review, edit and manage chains that group multiple clubs.</p>
        </div>
        @if($pendingCount > 0)
            <span id="bizPendingBadge" class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                <i class="bi bi-hourglass-split mr-1"></i>{{ $pendingCount }} pending
            </span>
        @endif
    </div>

    {{-- Search + status filters --}}
    <div class="flex flex-col gap-3">
        <div class="relative">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" id="bizSearch" autocomplete="off"
                   placeholder="Search by business name, owner name or email..."
                   class="w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
        </div>
        <div class="flex flex-wrap gap-2" id="bizFilters">
            @php
                $filterDefs = [
                    'all'      => ['All', 'bg-gray-100 text-gray-700', $counts['all']],
                    'pending'  => ['Pending', 'bg-amber-100 text-amber-700', $counts['pending']],
                    'approved' => ['Approved', 'bg-green-100 text-green-700', $counts['approved']],
                    'rejected' => ['Rejected', 'bg-red-100 text-red-700', $counts['rejected']],
                ];
            @endphp
            @foreach($filterDefs as $key => $def)
                <button type="button" data-status="{{ $key }}"
                        class="biz-filter-btn px-3 py-1.5 rounded-full text-xs font-medium border transition-colors {{ $key === 'all' ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                    {{ $def[0] }}
                    <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] px-1 py-0.5 rounded-full text-[10px] {{ $key === 'all' ? 'bg-white/20' : $def[1] }} biz-filter-count" data-status-count="{{ $key }}">{{ $def[2] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Empty state (no businesses at all) --}}
    <div id="bizEmpty" class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center {{ $businesses->isEmpty() ? '' : 'hidden' }}">
        <i class="bi bi-buildings text-4xl text-gray-300"></i>
        <p class="text-sm text-muted-foreground mt-3">No businesses have been created yet.</p>
    </div>

    {{-- No-results state (filtered out) --}}
    <div id="bizNoResults" class="hidden bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
        <i class="bi bi-search text-4xl text-gray-300"></i>
        <p class="text-sm text-muted-foreground mt-3">No businesses match your search.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 {{ $businesses->isEmpty() ? 'hidden' : '' }}" id="bizGrid">
        @foreach($businesses as $business)
            @include('admin.platform.businesses._card', ['business' => $business])
        @endforeach
    </div>

</div>

@php
    $bizData = $businesses->mapWithKeys(fn($b) => [$b->id => [
        'id'               => $b->id,
        'name'             => $b->name,
        'description'      => $b->description,
        'status'           => $b->status,
        'rejection_reason' => $b->rejection_reason,
        'logo'             => $b->logo,
        'logo_url'         => $b->logo ? asset('storage/' . $b->logo) : null,
        'owner_name'       => $b->owner?->full_name,
        'owner_email'      => $b->owner?->email,
        'clubs_count'      => $b->clubs_count,
    ]]);
@endphp
<script>window.__bizData = @json((object) $bizData->toArray());</script>

<x-business-edit-modal />
<x-user-picker-modal title="Select Business Owner" subtitle="Search and select a user to be the business owner" />

@include('admin.platform.businesses._scripts')
@endsection
