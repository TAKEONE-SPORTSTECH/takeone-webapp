@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? 'Club') . ' · Roles')

@section('club-admin-content')
<div class="space-y-4">

    {{-- Available roles legend --}}
    <div class="m-card p-4">
        <h3 class="font-semibold text-foreground mb-2">Roles</h3>
        <div class="space-y-2 mobile-stagger">
            @foreach($availableRoles as $role)
                <div>
                    <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium bg-accent text-primary">{{ $role->name }}</span>
                    @if(!empty($role->description))<p class="text-xs text-muted-foreground mt-1">{{ $role->description }}</p>@endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- Members with roles --}}
    <div class="m-card p-4">
        <h3 class="font-semibold text-foreground mb-3">Team members</h3>
        @if($members->isEmpty())
            <p class="text-sm text-muted-foreground">No members with assigned roles.</p>
        @else
            <div class="space-y-3 mobile-stagger">
                @foreach($members as $m)
                    @php $u = $m->user; if(!$u) continue; $userRoles = $u->getRolesForTenant($club->id); @endphp
                    <div class="flex items-center gap-3 border-b border-gray-50 last:border-0 pb-3 last:pb-0">
                        <span class="w-9 h-9 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                            @if($u->profile_picture)<img src="{{ asset('storage/'.$u->profile_picture) }}" alt="" class="w-9 h-9 object-cover">@else<i class="bi bi-person text-muted-foreground"></i>@endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-foreground truncate">{{ $u->full_name }}</p>
                            <div class="flex flex-wrap gap-1 mt-0.5">
                                @forelse($userRoles as $r)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-accent text-primary">{{ $r->name }}</span>
                                @empty
                                    <span class="text-xs text-muted-foreground">No role</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <p class="text-xs text-muted-foreground text-center px-4">Assign &amp; remove roles from the desktop view.</p>
</div>
@endsection
