@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? 'Club') . ' · Instructors')

@section('club-admin-content')
<div class="space-y-4 mobile-stagger">

    @if($instructors->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-people text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">No instructors yet.</p>
        </div>
    @else
        @foreach($instructors as $ins)
            @php $u = $ins->user; @endphp
            <div class="m-card p-4">
                <div class="flex items-center gap-3">
                    <span class="w-12 h-12 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                        @if($u && $u->profile_picture)<img src="{{ asset('storage/'.$u->profile_picture) }}" alt="" class="w-12 h-12 object-cover">@else<i class="bi bi-person text-muted-foreground text-lg"></i>@endif
                    </span>
                    @php $expSuffix = ($u && $u->experience_years) ? ' · '.$u->experience_years.' yrs' : ''; @endphp
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-foreground truncate">{{ $u->full_name ?? 'Instructor' }}</p>
                        <p class="text-xs text-muted-foreground truncate">{{ ($ins->role ?? 'Instructor') . $expSuffix }}</p>
                    </div>
                </div>
                @if($u && !empty($u->skills) && is_array($u->skills))
                    <div class="flex flex-wrap gap-1.5 mt-3">
                        @foreach(array_slice($u->skills, 0, 5) as $skill)
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">{{ $skill }}</span>
                        @endforeach
                    </div>
                @endif
                @if($u && $u->bio)<p class="text-xs text-muted-foreground mt-2 line-clamp-2">{{ $u->bio }}</p>@endif
            </div>
        @endforeach
    @endif

    <p class="text-xs text-muted-foreground text-center px-4">Add &amp; edit instructors from the desktop view.</p>
</div>
@endsection
