@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? 'Club') . ' · Facilities')

@section('club-admin-content')
<div class="space-y-4">

    @if($facilities->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-geo-alt text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">No facilities yet.</p>
        </div>
    @else
        <div class="space-y-4 mobile-stagger">
        @foreach($facilities as $f)
            @php $img = is_array($f->images ?? null) ? ($f->images[0] ?? null) : ($f->photo ?? null); @endphp
            <div class="m-card overflow-hidden">
                @if($img)<img src="{{ asset('storage/'.$img) }}" alt="" class="w-full h-32 object-cover">@endif
                <div class="p-4">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="font-semibold text-foreground truncate">{{ $f->name }}</h3>
                        @if(isset($f->is_available))
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium flex-shrink-0 {{ $f->is_available ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $f->is_available ? 'Available' : 'Unavailable' }}</span>
                        @endif
                    </div>
                    @if($f->description)<p class="text-xs text-muted-foreground mt-1 line-clamp-2">{{ $f->description }}</p>@endif
                    @if($f->address)<p class="text-xs text-muted-foreground mt-2"><i class="bi bi-geo-alt mr-1"></i>{{ $f->address }}</p>@endif
                </div>
            </div>
        @endforeach
        </div>
    @endif

    <p class="text-xs text-muted-foreground text-center px-4">Add &amp; edit facilities from the desktop view.</p>
</div>
@endsection
