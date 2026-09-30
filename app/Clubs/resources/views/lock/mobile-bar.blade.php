{{-- Club mode, mobile — the header that REPLACES the personal shell's header,
     drawer and tab bar while App\Clubs\Support\ClubLock is active (a member
     profile or an event page opened from the club panel). Back goes to the
     club; the only other action is sign out. --}}
@php $__lockClub = \App\Clubs\Support\ClubLock::current(); @endphp
<header class="sticky top-0 z-40 bg-white border-b border-border">
    <div class="flex items-center gap-2 px-3 h-14">
        <a href="{{ \App\Clubs\Support\ClubLock::homeUrl() }}"
           aria-label="{{ __('clubs::club.lock_back') }}" title="{{ __('clubs::club.lock_back') }}"
           class="m-press w-10 h-10 rounded-full bg-muted grid place-items-center text-foreground flex-shrink-0">
            <i class="bi bi-chevron-left rtl:rotate-180"></i>
        </a>
        @if($__lockClub['logo'] ?? null)
            <span class="w-9 h-9 flex-shrink-0"><img src="{{ file_url($__lockClub['logo']) }}" alt="" class="w-full h-full object-contain"></span>
        @endif
        <div class="flex-1 min-w-0">
            <p class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground leading-none">{{ __('clubs::club.lock_mode') }}</p>
            <p class="text-base font-bold text-primary leading-tight truncate">{{ $__lockClub['name'] ?? '' }}</p>
        </div>
        <form action="{{ route('logout') }}" method="POST" class="flex-shrink-0">
            @csrf
            <button type="submit" aria-label="{{ __('nav.sign_out') }}" title="{{ __('nav.sign_out') }}"
                    class="m-press w-9 h-9 rounded-xl bg-muted grid place-items-center text-destructive">
                <i class="bi bi-box-arrow-right"></i>
            </button>
        </form>
    </div>
</header>
