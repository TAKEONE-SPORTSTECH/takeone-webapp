{{-- Clubs results region — rendered on full page load and returned standalone for AJAX search/sort/pagination. --}}
@if($clubs->count() > 0)
    @php
        $countryNames = collect(json_decode(@file_get_contents(public_path('data/countries.json')) ?: '[]', true))
            ->pluck('name', 'iso2');
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-4" id="clubsGrid">
        @foreach($clubs as $club)
            @php
                $cc = strtoupper($club->country ?? '');
                $flag = (strlen($cc) === 2 && ctype_alpha($cc))
                    ? mb_chr(127397 + ord($cc[0])) . mb_chr(127397 + ord($cc[1]))
                    : '';
                $countryName = $countryNames[$cc] ?? $cc;
            @endphp
            <div class="club-card-wrapper w-full"
                 data-club-country="{{ $cc }}"
                 data-club-id="{{ $club->id }}"
                 data-club-name="{{ $club->club_name }}"
                 data-club-address="{{ $club->address ?? '' }}"
                 data-club-owner="{{ $club->owner->full_name ?? '' }}">
                <a href="{{ route('admin.club.dashboard', $club->slug) }}" class="no-underline">
                    <div class="card border shadow-sm overflow-hidden club-card cursor-pointer transition-all duration-300">
                        <!-- Cover Image -->
                        <div class="relative overflow-hidden h-48">
                            @if($club->cover_image)
                                <img src="{{ asset('storage/' . $club->cover_image) }}" alt="{{ $club->club_name }}" loading="lazy" class="w-full h-full object-cover club-cover-img transition-transform duration-300">
                            @else
                                <div class="w-full h-full flex items-center justify-center" style="background: linear-gradient(135deg, hsl(250 65% 66%) 0%, hsl(262 60% 56%) 100%);">
                                    <i class="bi bi-image text-white text-5xl opacity-30"></i>
                                </div>
                            @endif

                            <!-- Club Logo - Bottom Left -->
                            <div class="absolute bottom-2 start-2">
                                <div class="bg-white shadow border p-0.5 w-20 h-20 rounded-full" style="border-color: rgba(0,0,0,0.1) !important;">
                                    @if($club->logo)
                                        <img src="{{ asset('storage/' . $club->logo) }}" alt="{{ $club->club_name }} logo" loading="lazy" class="w-full h-full rounded-full object-contain">
                                    @else
                                        <div class="w-full h-full rounded-full bg-primary flex items-center justify-center">
                                            <span class="text-white font-bold text-xl">{{ substr($club->club_name, 0, 1) }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                        <!-- Admin Badge - Top Left -->
                        <div class="absolute top-2 start-2">
                            <span class="badge text-white px-3 py-1 rounded-full text-xs font-semibold" style="background-color: rgba(147, 51, 234, 0.9);">{{ __('platform.platform_clubs_results_admin_badge') }}</span>
                        </div>

                        <!-- Country Flag - Bottom Right -->
                        @if($flag)
                            <div class="absolute bottom-2 end-2">
                                <span class="club-flag inline-flex items-center gap-1 bg-white/90 shadow-sm rounded-full ps-2 pe-2.5 py-1 text-xs font-semibold text-foreground whitespace-nowrap">
                                    <span class="club-flag-emoji text-base leading-none">{{ $flag }}</span><span class="club-flag-code">{{ $countryName }}</span>
                                </span>
                            </div>
                        @endif

                        <!-- Edit Button - Top Right -->
                        <div class="absolute top-2 end-2">
                            <button type="button"
                                    class="btn btn-sm btn-light shadow-sm"
                                    onclick="event.preventDefault(); event.stopPropagation(); window.dispatchEvent(new CustomEvent('open-club-modal', { detail: { mode: 'edit', clubId: {{ $club->id }} } }));"
                                    title="{{ __('platform.platform_clubs_results_edit_club') }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </div>
                    </div>

                        <!-- Card Body -->
                        <div class="p-4 bg-white">
                            <div class="mb-3">
                                <!-- Club Name -->
                                <h3 class="font-semibold mb-2 club-title text-lg text-foreground transition-colors duration-300">{{ $club->club_name }}</h3>

                                <!-- Address -->
                                @if($club->address)
                                    <div class="flex items-center text-muted-foreground text-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 shrink-0">
                                            <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                        <span class="truncate">{{ $club->address }}</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Stats Grid -->
                            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                                <div class="p-2 rounded bg-primary/5">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1 text-primary">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                    <p class="font-semibold mb-0 text-foreground">{{ $club->members_count }}</p>
                                    <p class="text-muted-foreground mb-0">{{ __('platform.platform_clubs_results_members') }}</p>
                                </div>
                                <div class="p-2 rounded bg-primary/5">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1 text-primary">
                                        <path d="M14.4 14.4 9.6 9.6"></path>
                                        <path d="M18.657 21.485a2 2 0 1 1-2.829-2.828l-1.767 1.768a2 2 0 1 1-2.829-2.829l6.364-6.364a2 2 0 1 1 2.829 2.829l-1.768 1.767a2 2 0 1 1 2.828 2.829z"></path>
                                        <path d="m21.5 21.5-1.4-1.4"></path>
                                        <path d="M3.9 3.9 2.5 2.5"></path>
                                        <path d="M6.404 12.768a2 2 0 1 1-2.829-2.829l1.768-1.767a2 2 0 1 1-2.828-2.829l2.828-2.828a2 2 0 1 1 2.829 2.828l1.767-1.768a2 2 0 1 1 2.829 2.829z"></path>
                                    </svg>
                                    <p class="font-semibold mb-0 text-foreground">{{ $club->packages_count }}</p>
                                    <p class="text-muted-foreground mb-0">{{ __('platform.platform_clubs_results_packages') }}</p>
                                </div>
                                <div class="p-2 rounded bg-primary/5">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1 text-primary">
                                        <path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"></path>
                                    </svg>
                                    <p class="font-semibold mb-0 text-foreground">{{ $club->instructors_count }}</p>
                                    <p class="text-muted-foreground mb-0">{{ __('platform.platform_clubs_results_trainers') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <!-- Pagination -->
    <div class="flex justify-center mb-4">
        {{ $clubs->links() }}
    </div>
@else
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body text-center py-12">
            <i class="bi bi-building text-muted-foreground text-6xl"></i>
            <h5 class="mt-3 mb-2">{{ __('platform.platform_clubs_results_no_clubs_found') }}</h5>
            <p class="text-muted-foreground mb-4">
                @if($search)
                    {{ __('platform.platform_clubs_results_no_clubs_match') }}
                @else
                    {{ __('platform.platform_clubs_results_get_started') }}
                @endif
            </p>
            @if(!$search)
                <button type="button" class="btn btn-primary" onclick="window.dispatchEvent(new CustomEvent('open-club-modal', { detail: { mode: 'create' } }));">
                    <i class="bi bi-plus-circle me-2"></i>{{ __('platform.platform_clubs_results_add_new_club') }}
                </button>
            @endif
        </div>
    </div>
@endif
