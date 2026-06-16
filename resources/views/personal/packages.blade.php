@extends('layouts.personal-mobile')

@section('title', 'My Packages')

@section('personal-content')
<div class="space-y-4">
    @forelse($subscriptions as $sub)
        @php $cur = $sub->tenant->currency ?? ''; @endphp
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            @if($sub->package && $sub->package->cover_image)<img src="{{ asset('storage/'.$sub->package->cover_image) }}" alt="" class="w-full h-32 object-cover">@endif
            <div class="p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-foreground truncate">{{ $sub->package->name ?? 'Membership' }}</h3>
                        <p class="text-xs text-muted-foreground truncate">{{ $sub->tenant->club_name ?? '' }}</p>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium flex-shrink-0 capitalize {{ $sub->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">{{ $sub->status }}</span>
                </div>
                <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-50 text-xs">
                    <span class="font-semibold text-primary">{{ $cur }} {{ number_format((float)($sub->package->price ?? 0), 0) }}</span>
                    <span class="text-muted-foreground capitalize">{{ str_replace('_',' ', $sub->payment_status ?? '') }}</span>
                </div>
            </div>
        </div>
    @empty
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
            <i class="bi bi-box text-4xl text-gray-300"></i>
            <p class="text-sm text-muted-foreground mt-3">No packages yet.</p>
            <a href="{{ route('clubs.explore') }}" class="inline-block mt-3 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium">Explore clubs</a>
        </div>
    @endforelse
</div>
@endsection
