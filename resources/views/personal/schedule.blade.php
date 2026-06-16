@extends('layouts.personal-mobile')

@section('title', 'My Schedule')

@section('personal-content')
<div class="space-y-4">
    <h2 class="font-semibold text-foreground">My enrolments</h2>
    @forelse($subscriptions as $sub)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-3">
            <span class="w-11 h-11 rounded-xl bg-accent flex items-center justify-center text-primary flex-shrink-0"><i class="bi bi-calendar-event text-lg"></i></span>
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-foreground truncate">{{ $sub->package->name ?? 'Membership' }}</p>
                <p class="text-xs text-muted-foreground truncate">{{ $sub->tenant->club_name ?? '' }}</p>
            </div>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium {{ $sub->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }} flex-shrink-0 capitalize">{{ $sub->status }}</span>
        </div>
    @empty
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
            <i class="bi bi-calendar-x text-4xl text-gray-300"></i>
            <p class="text-sm text-muted-foreground mt-3">You're not enrolled in any classes yet.</p>
        </div>
    @endforelse
</div>
@endsection
