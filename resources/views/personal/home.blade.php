@extends('layouts.personal-mobile')

@section('title', 'News Feed')

@section('personal-content')
<div class="space-y-4">
    @forelse($posts as $p)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="flex items-center gap-3 p-4 pb-3">
                <span class="w-10 h-10 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                    @if($p->tenant && $p->tenant->logo)<img src="{{ asset('storage/'.$p->tenant->logo) }}" alt="" class="w-10 h-10 object-cover">@else<i class="bi bi-buildings text-muted-foreground"></i>@endif
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-foreground truncate">{{ $p->tenant->club_name ?? 'Club' }}</p>
                    <p class="text-[11px] text-muted-foreground">{{ $p->category ?? 'Update' }} · {{ optional($p->posted_at)->diffForHumans() }}</p>
                </div>
            </div>
            @if($p->image_path)<img src="{{ asset('storage/'.$p->image_path) }}" alt="" class="w-full max-h-72 object-cover">@endif
            <p class="text-sm text-foreground whitespace-pre-line px-4 py-3">{{ $p->body }}</p>
            <div class="flex items-center gap-4 px-4 pb-3 text-xs text-muted-foreground border-t border-gray-50 pt-2">
                <span><i class="bi bi-heart mr-1"></i>Like</span>
                <span><i class="bi bi-chat mr-1"></i>Comment</span>
            </div>
        </div>
    @empty
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
            <i class="bi bi-newspaper text-4xl text-gray-300"></i>
            <p class="text-sm text-muted-foreground mt-3">No posts from your clubs yet.</p>
        </div>
    @endforelse
</div>
@endsection
