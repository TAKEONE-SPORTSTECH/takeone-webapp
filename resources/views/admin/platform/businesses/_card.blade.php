@php
    use App\Models\Business;
    $badge = match($business->status) {
        Business::STATUS_APPROVED => 'bg-green-100 text-green-700',
        Business::STATUS_REJECTED => 'bg-red-100 text-red-700',
        default => 'bg-amber-100 text-amber-700',
    };
@endphp
<div class="biz-card bg-white rounded-xl shadow-sm border border-gray-100 p-5"
     data-id="{{ $business->id }}"
     data-name="{{ $business->name }}"
     data-owner="{{ $business->owner?->full_name }}"
     data-email="{{ $business->owner?->email }}"
     data-status="{{ $business->status }}">
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-start gap-3 min-w-0">
            <div class="biz-logo w-11 h-11 rounded-lg bg-accent flex items-center justify-center flex-shrink-0">
                @if($business->logo)
                    <img src="{{ asset('storage/' . $business->logo) }}" alt="" class="w-11 h-11 rounded-lg object-cover">
                @else
                    <i class="bi bi-buildings text-primary text-lg"></i>
                @endif
            </div>
            <div class="min-w-0">
                <h3 class="biz-name font-semibold text-foreground truncate">{{ $business->name }}</h3>
                <p class="biz-owner text-xs text-muted-foreground truncate">{{ $business->owner?->full_name ?? 'Unknown owner' }}{{ $business->owner?->email ? ' · ' . $business->owner->email : '' }}</p>
                <p class="biz-clubs text-xs text-muted-foreground mt-0.5">
                    <i class="bi bi-diagram-3 mr-1"></i>{{ $business->clubs_count }} {{ Str::plural('club', $business->clubs_count) }}
                </p>
            </div>
        </div>
        <span class="biz-status-badge inline-block px-2.5 py-0.5 rounded-full text-xs font-medium flex-shrink-0 capitalize {{ $badge }}">{{ $business->status }}</span>
    </div>

    <p class="biz-desc text-sm text-muted-foreground mt-3 {{ $business->description ? '' : 'hidden' }}">{{ $business->description }}</p>

    <p class="biz-reject-reason text-xs text-red-600 mt-3 {{ ($business->status === \App\Models\Business::STATUS_REJECTED && $business->rejection_reason) ? '' : 'hidden' }}">
        @if($business->status === \App\Models\Business::STATUS_REJECTED && $business->rejection_reason)
            <span class="font-medium">Rejection reason:</span> {{ $business->rejection_reason }}
        @endif
    </p>

    <div class="biz-actions mt-4 pt-4 border-t border-gray-100 flex flex-wrap items-center gap-2">
        @if($business->status === \App\Models\Business::STATUS_PENDING)
            <button type="button" data-biz-action="approve" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm"><i class="bi bi-check-lg mr-1"></i>Approve</button>
            <button type="button" data-biz-action="reject" class="border border-red-300 text-red-600 hover:bg-red-50 px-4 py-2 rounded-lg transition-colors font-medium text-sm"><i class="bi bi-x-lg mr-1"></i>Reject</button>
        @elseif($business->status === \App\Models\Business::STATUS_REJECTED)
            <button type="button" data-biz-action="approve" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm"><i class="bi bi-check-lg mr-1"></i>Approve anyway</button>
        @endif
        <button type="button" data-biz-action="edit" class="border border-gray-200 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg transition-colors font-medium text-sm"><i class="bi bi-pencil mr-1"></i>Edit</button>
        <button type="button" data-biz-action="delete" class="border border-red-200 text-red-600 hover:bg-red-50 px-4 py-2 rounded-lg transition-colors font-medium text-sm"><i class="bi bi-trash mr-1"></i>Delete</button>
    </div>
</div>
