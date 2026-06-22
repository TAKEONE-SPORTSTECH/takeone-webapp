@extends('layouts.personal-mobile')

@section('title', 'My Progress')

{{-- Full-bleed (Facebook-style): edge-to-edge white blocks separated by gray gutters. --}}
@section('personal-content')
<div class="-mx-4 -mt-4">
    <div class="bg-white px-4 py-4 mb-2">
        <div class="grid grid-cols-3 gap-3">
            <div class="bg-muted/40 rounded-xl p-4 text-center"><p class="text-2xl font-bold text-green-600">{{ $goalStats['completed'] }}</p><p class="text-[11px] text-muted-foreground mt-0.5">{{ __('personal.achieved') }}</p></div>
            <div class="bg-muted/40 rounded-xl p-4 text-center"><p class="text-2xl font-bold text-primary">{{ $goalStats['in_progress'] }}</p><p class="text-[11px] text-muted-foreground mt-0.5">{{ __('personal.in_progress') }}</p></div>
            <div class="bg-muted/40 rounded-xl p-4 text-center"><p class="text-2xl font-bold text-amber-600">{{ $goalStats['pending'] }}</p><p class="text-[11px] text-muted-foreground mt-0.5">{{ __('personal.pending') }}</p></div>
        </div>
    </div>

    <div class="bg-white px-4 py-4">
        <h3 class="font-semibold text-foreground mb-3">{{ __('personal.goals') }}</h3>
        @forelse($goals as $g)
            <div class="flex items-center gap-3 py-2.5 border-b border-gray-50 last:border-0">
                <i class="bi {{ $g->status === 'completed' ? 'bi-check-circle-fill text-green-600' : 'bi-circle text-muted-foreground' }}"></i>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-foreground truncate">{{ $g->title ?? $g->name ?? __('personal.goal') }}</p>
                    <p class="text-[11px] text-muted-foreground capitalize">{{ str_replace('_',' ', $g->status ?? '') }}</p>
                </div>
            </div>
        @empty
            <p class="text-sm text-muted-foreground">{{ __('personal.no_goals_yet') }}</p>
        @endforelse
    </div>
</div>
@endsection
