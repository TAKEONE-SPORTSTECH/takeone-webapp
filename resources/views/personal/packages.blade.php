@extends('layouts.personal-mobile')

@section('title', 'My Packages')

{{-- Full-bleed (Facebook-style): each package is an edge-to-edge white block
     separated by gray gutters; cover images span the full width. --}}
@section('personal-content')
<div class="-mx-4 -mt-4">
    @forelse($subscriptions as $sub)
        @php $cur = $sub->tenant->currency ?? ''; @endphp
        <div class="bg-white mb-2">
            @if($sub->package && $sub->package->cover_image)<img src="{{ asset('storage/'.$sub->package->cover_image) }}" alt="" class="w-full h-40 object-cover">@endif
            <div class="px-4 py-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-foreground truncate">{{ $sub->package?->tr('name') ?? __('personal.membership') }}</h3>
                        <p class="text-xs text-muted-foreground truncate">{{ $sub->tenant?->tr('club_name') ?? '' }}</p>
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
        <div class="bg-white px-4 py-12 text-center">
            <i class="bi bi-box text-4xl text-gray-300"></i>
            <p class="text-sm text-muted-foreground mt-3">{{ __('personal.no_packages_yet') }}</p>
            <a href="{{ route('clubs.explore') }}" class="inline-block mt-3 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('personal.explore_clubs') }}</a>
        </div>
    @endforelse
</div>
@endsection
