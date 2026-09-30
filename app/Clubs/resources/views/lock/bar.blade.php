{{-- Club mode — the top bar that REPLACES the platform navigation (layouts.app)
     while App\Clubs\Support\ClubLock is active. Same frosted .to-bar, same 64px,
     so the club sidebar's calc(100vh - 64px) still fits. Nothing here leads out
     of the club: its name goes back to it, the rest is switch-club and sign out. --}}
@php
    $__lockClub = \App\Clubs\Support\ClubLock::current();
    $__lockCount = count(\App\Clubs\Support\ClubLock::clubs());
@endphp
<nav class="to-bar">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between gap-3 h-16">
            <a href="{{ \App\Clubs\Support\ClubLock::homeUrl() }}" class="flex items-center gap-3 min-w-0 no-underline">
                @if($__lockClub['logo'] ?? null)
                    <span class="w-10 h-10 flex-shrink-0"><img src="{{ file_url($__lockClub['logo']) }}" alt="" class="w-full h-full object-contain"></span>
                @else
                    <span class="w-10 h-10 rounded-xl bg-accent grid place-items-center flex-shrink-0"><i class="bi bi-building text-primary"></i></span>
                @endif
                <span class="min-w-0">
                    <span class="block text-[10px] font-bold uppercase tracking-wide text-primary">{{ __('clubs::club.lock_mode') }}</span>
                    <span class="block font-bold text-foreground leading-tight truncate">{{ $__lockClub['name'] ?? '' }}</span>
                </span>
            </a>

            <div class="flex items-center gap-2 flex-shrink-0">
                @if($__lockCount > 1)
                    <a href="{{ route('club.entry') }}"
                       class="inline-flex items-center gap-2 border border-primary text-primary bg-transparent px-3 py-1.5 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors no-underline">
                        <i class="bi bi-arrow-left-right"></i><span class="hidden sm:inline">{{ __('clubs::club.lock_switch') }}</span>
                    </a>
                @endif
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 border border-red-300 text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-md text-sm font-medium transition-colors">
                        <i class="bi bi-box-arrow-right"></i><span class="hidden sm:inline">{{ __('nav.sign_out') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
