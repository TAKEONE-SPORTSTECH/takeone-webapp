@php
    $u = Auth::user();
    $hasBusiness = $u->hasApprovedBusiness();
    $businessName = $hasBusiness ? $u->ownedBusiness->name : null;
    // $current = 'personal' | 'business'
    $current = $current ?? 'personal';
@endphp

{{-- Trigger (small label above the page title) --}}
<button @click="switcher = !switcher" type="button" class="flex items-center gap-1.5 max-w-full" {{ $hasBusiness ? '' : 'disabled' }}>
    <span class="text-[10px] text-muted-foreground font-medium flex items-center gap-1">
        <i class="bi {{ $current === 'business' ? 'bi-building' : 'bi-person' }}"></i>
        {{ $current === 'business' ? 'Club Management' : 'Personal View' }}
        @if($hasBusiness)<i class="bi bi-chevron-down text-[9px]" :class="switcher && 'rotate-180'" style="transition:transform .2s"></i>@endif
    </span>
</button>

{{-- Dropdown — identical structure & order in both views; only the check/form swaps --}}
@if($hasBusiness)
<div x-show="switcher" @click.outside="switcher=false" x-cloak x-transition.opacity
     class="absolute left-3 mt-1 w-64 bg-white rounded-xl shadow-lg border border-border z-50 p-1">
    <p class="px-3 py-2 text-[10px] font-semibold text-muted-foreground uppercase tracking-wide">Switch view</p>

    {{-- Personal (always first) --}}
    @if($current === 'personal')
        <div class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg bg-accent/60">
            <span class="w-8 h-8 rounded-full bg-muted flex items-center justify-center"><i class="bi bi-person text-foreground"></i></span>
            <span class="flex-1 text-left"><span class="block text-sm font-medium text-foreground">Personal View</span><span class="block text-[11px] text-muted-foreground">My profile, bookings, payments</span></span>
            <i class="bi bi-check-lg text-primary"></i>
        </div>
    @else
        <form action="{{ route('view.switch') }}" method="POST">@csrf<input type="hidden" name="mode" value="personal">
            <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-accent transition-colors text-left">
                <span class="w-8 h-8 rounded-full bg-muted flex items-center justify-center"><i class="bi bi-person text-foreground"></i></span>
                <span class="flex-1"><span class="block text-sm font-medium text-foreground">Personal View</span><span class="block text-[11px] text-muted-foreground">My profile, bookings, payments</span></span>
            </button>
        </form>
    @endif

    {{-- Club Management (always second) --}}
    @if($current === 'business')
        <div class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg bg-accent/60">
            <span class="w-8 h-8 rounded-full bg-accent flex items-center justify-center"><i class="bi bi-building text-primary"></i></span>
            <span class="flex-1 text-left"><span class="block text-sm font-medium text-foreground">Club Management</span><span class="block text-[11px] text-muted-foreground truncate">{{ $businessName }}</span></span>
            <i class="bi bi-check-lg text-primary"></i>
        </div>
    @else
        <form action="{{ route('view.switch') }}" method="POST">@csrf<input type="hidden" name="mode" value="business">
            <button type="submit" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-accent transition-colors text-left">
                <span class="w-8 h-8 rounded-full bg-accent flex items-center justify-center"><i class="bi bi-building text-primary"></i></span>
                <span class="flex-1"><span class="block text-sm font-medium text-foreground">Club Management</span><span class="block text-[11px] text-muted-foreground truncate">{{ $businessName }}</span></span>
            </button>
        </form>
    @endif
</div>
@endif
