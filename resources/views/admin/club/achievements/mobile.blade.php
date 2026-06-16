@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? 'Club') . ' · Achievements')

@section('club-admin-content')
<div class="space-y-4 mobile-stagger">

    @if($achievements->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-trophy text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">No achievements yet.</p>
        </div>
    @else
        @foreach($achievements as $ach)
            @php $img = $ach->image_path ?? (is_array($ach->images ?? null) ? ($ach->images[0] ?? null) : null); @endphp
            <div class="m-card overflow-hidden">
                @if($img)<img src="{{ asset('storage/'.$img) }}" alt="" class="w-full h-36 object-cover">@endif
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-semibold text-foreground truncate">{{ $ach->type_icon ?? '' }} {{ $ach->title }}</h3>
                        @if($ach->tag)<span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary flex-shrink-0"><i class="bi {{ $ach->tag_icon ?? 'bi-trophy' }} mr-1"></i>{{ $ach->tag }}</span>@endif
                    </div>
                    @if($ach->location || $ach->date_label || $ach->achievement_date)
                        <p class="text-xs text-muted-foreground mt-1">
                            @if($ach->location)<i class="bi bi-geo-alt mr-1"></i>{{ $ach->location }}@endif
                            @if($ach->date_label) · {{ $ach->date_label }}@elseif($ach->achievement_date) · {{ optional($ach->achievement_date)->format('d M Y') }}@endif
                        </p>
                    @endif
                    @if(($ach->medals_gold ?? 0) || ($ach->medals_silver ?? 0) || ($ach->medals_bronze ?? 0))
                        <div class="flex items-center gap-3 mt-2 text-sm font-semibold">
                            <span class="text-amber-500">🥇 {{ $ach->medals_gold ?? 0 }}</span>
                            <span class="text-gray-400">🥈 {{ $ach->medals_silver ?? 0 }}</span>
                            <span class="text-orange-700">🥉 {{ $ach->medals_bronze ?? 0 }}</span>
                        </div>
                    @endif
                    @if($ach->description)<p class="text-xs text-muted-foreground mt-2 line-clamp-2">{{ $ach->description }}</p>@endif
                </div>
            </div>
        @endforeach
    @endif

    <p class="text-xs text-muted-foreground text-center px-4">Add &amp; edit achievements from the desktop view.</p>
</div>
@endsection
