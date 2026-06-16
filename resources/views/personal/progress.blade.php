@extends('layouts.personal-mobile')

@section('title', 'My Progress')

@section('personal-content')
<div class="space-y-5">
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center"><p class="text-2xl font-bold text-green-600">{{ $goalStats['completed'] }}</p><p class="text-[11px] text-muted-foreground mt-0.5">Achieved</p></div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center"><p class="text-2xl font-bold text-primary">{{ $goalStats['in_progress'] }}</p><p class="text-[11px] text-muted-foreground mt-0.5">In progress</p></div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 text-center"><p class="text-2xl font-bold text-amber-600">{{ $goalStats['pending'] }}</p><p class="text-[11px] text-muted-foreground mt-0.5">Pending</p></div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
        <h3 class="font-semibold text-foreground mb-3">Goals</h3>
        @forelse($goals as $g)
            <div class="flex items-center gap-3 py-2.5 border-b border-gray-50 last:border-0">
                <i class="bi {{ $g->status === 'completed' ? 'bi-check-circle-fill text-green-600' : 'bi-circle text-muted-foreground' }}"></i>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-foreground truncate">{{ $g->title ?? $g->name ?? 'Goal' }}</p>
                    <p class="text-[11px] text-muted-foreground capitalize">{{ str_replace('_',' ', $g->status ?? '') }}</p>
                </div>
            </div>
        @empty
            <p class="text-sm text-muted-foreground">No goals set yet.</p>
        @endforelse
    </div>
</div>
@endsection
