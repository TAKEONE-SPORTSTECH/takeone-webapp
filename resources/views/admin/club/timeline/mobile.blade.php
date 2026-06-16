@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? 'Club') . ' · Timeline')

@section('club-admin-content')
<div class="space-y-4">

    @if($posts->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-newspaper text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">No posts yet.</p>
        </div>
    @else
        <div class="space-y-4 mobile-stagger">
        @foreach($posts as $p)
            <div class="m-card overflow-hidden">
                @if($p->image_path)<img src="{{ asset('storage/'.$p->image_path) }}" alt="" class="w-full h-40 object-cover">@endif
                <div class="p-4">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">{{ $p->category ?? 'Update' }}</span>
                        @if(($p->status ?? '') !== 'published')<span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-gray-100 text-gray-500">Draft</span>@endif
                    </div>
                    <p class="text-sm text-foreground whitespace-pre-line">{{ $p->body }}</p>
                    <div class="flex items-center gap-4 mt-3 text-xs text-muted-foreground">
                        <span>{{ optional($p->posted_at)->format('d M Y') }}</span>
                        <span><i class="bi bi-heart mr-1"></i>{{ $p->likes_count ?? 0 }}</span>
                        <span><i class="bi bi-chat mr-1"></i>{{ $p->comments_count ?? 0 }}</span>
                    </div>
                </div>
            </div>
        @endforeach
        </div>
        <div>{{ $posts->links() }}</div>
    @endif

    <p class="text-xs text-muted-foreground text-center px-4">Create posts from the desktop view.</p>
</div>
@endsection
