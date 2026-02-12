@props([
    'member',
    'href' => null,
    'footerLabel' => 'PLATFORM MEMBER',
    'footerStyle' => 'solid',
    'guardian' => null,
    'memberSince' => null,
    'cardClass' => 'family-card',
])

@php
    $age = $member->age;
    $ageGroup = 'Adult';
    if ($age !== null) {
        if ($age < 2) {
            $ageGroup = 'Infant';
        } elseif ($age < 4) {
            $ageGroup = 'Toddler';
        } elseif ($age < 6) {
            $ageGroup = 'Preschooler';
        } elseif ($age < 13) {
            $ageGroup = 'Child';
        } elseif ($age < 20) {
            $ageGroup = 'Teenager';
        } elseif ($age < 40) {
            $ageGroup = 'Young Adult';
        } elseif ($age < 60) {
            $ageGroup = 'Adult';
        } else {
            $ageGroup = 'Senior';
        }
    }

    $horoscopeSymbols = [
        'Aries' => '♈', 'Taurus' => '♉', 'Gemini' => '♊', 'Cancer' => '♋',
        'Leo' => '♌', 'Virgo' => '♍', 'Libra' => '♎', 'Scorpio' => '♏',
        'Sagittarius' => '♐', 'Capricorn' => '♑', 'Aquarius' => '♒', 'Pisces' => '♓'
    ];
    $horoscope = $member->horoscope ?? 'N/A';
    $symbol = $horoscopeSymbols[$horoscope] ?? '';

    $isMale = ($member->gender ?? 'm') === 'm';
    $sinceDate = $memberSince ?? $member->created_at;

    // Guardian contact fallback
    $displayMobile = $member->mobile;
    $displayEmail = $member->email;
    $isGuardianContact = false;

    if ((!$displayMobile || (is_array($displayMobile) && empty($displayMobile['number']))) && !$displayEmail && $guardian) {
        $displayMobile = $guardian->mobile;
        $displayEmail = $guardian->email;
        $isGuardianContact = true;
    }
@endphp

<div {{ $attributes->merge(['class' => 'member-card-wrapper']) }}
     data-member-name="{{ $member->full_name ?? '' }}"
     data-member-phone="{{ $member->formatted_mobile ?? '' }}"
     data-member-nationality="{{ $member->nationality ?? '' }}"
     data-member-gender="{{ $member->gender ?? '' }}">

    @if($href)
    <a href="{{ $href }}" class="no-underline">
    @endif

        <div class="card h-full shadow-sm border overflow-hidden flex flex-col {{ $cardClass }}">
            <!-- Header with gradient background -->
            <div class="p-4 pb-3" style="background: linear-gradient(135deg, {{ $isMale ? 'rgba(147, 51, 234, 0.1) 0%, rgba(147, 51, 234, 0.05) 50%' : 'rgba(214, 51, 132, 0.1) 0%, rgba(214, 51, 132, 0.05) 50%' }}, transparent 100%);">
                <div class="flex items-start gap-3">
                    <div class="relative shrink-0">
                        <div class="rounded-full border-4 border-white shadow w-20 h-20 overflow-hidden" style="box-shadow: 0 0 0 2px {{ $isMale ? 'rgba(147, 51, 234, 0.3)' : 'rgba(214, 51, 132, 0.3)' }} !important;">
                            @if($member->profile_picture)
                                <img src="{{ asset('storage/' . $member->profile_picture) }}" alt="{{ $member->full_name }}" class="w-full h-full object-cover" style="image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges;">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-white font-bold text-xl" style="background: linear-gradient(135deg, {{ $isMale ? '#8b5cf6 0%, #7c3aed 100%' : '#d63384 0%, #a61e4d 100%' }});">
                                    {{ strtoupper(substr($member->full_name ?? 'M', 0, 1)) }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="grow min-w-0">
                        <h5 class="font-bold mb-2 truncate">{{ $member->full_name ?? 'Unknown' }}</h5>
                        <div class="flex flex-wrap gap-2">
                            <span class="badge {{ $isMale ? 'bg-primary' : 'bg-danger' }}">{{ $ageGroup }}</span>
                            @if(isset($member->member_clubs_count) && $member->member_clubs_count > 0)
                                <span class="badge bg-success">{{ $member->member_clubs_count }} {{ Str::plural('Club', $member->member_clubs_count) }}</span>
                            @endif
                            {{ $badges ?? '' }}
                        </div>
                        {{ $headerExtra ?? '' }}
                    </div>
                </div>
            </div>

            <!-- Contact Info -->
            <div class="px-4 py-3 bg-muted border-t border-b">
                @if($isGuardianContact && $guardian)
                <div class="flex items-center gap-1 text-xs text-info mb-2">
                    <i class="bi bi-info-circle-fill"></i>
                    <span class="font-medium">Guardian's contact ({{ $guardian->full_name }})</span>
                </div>
                @endif
                @if($displayMobile && is_array($displayMobile) && isset($displayMobile['number']))
                    <div class="flex items-center gap-2 text-sm mb-2">
                        <i class="bi bi-telephone-fill {{ $isMale ? 'text-primary' : 'text-destructive' }}"></i>
                        <span class="font-medium text-muted-foreground">{{ $displayMobile['code'] ?? '' }} {{ $displayMobile['number'] }}</span>
                        @if($isGuardianContact)
                            <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full ml-auto {{ $isMale ? 'bg-sky-100 text-sky-700' : 'bg-pink-500 text-white' }}">Guardian's</span>
                        @endif
                    </div>
                @elseif($displayMobile && is_string($displayMobile))
                    <div class="flex items-center gap-2 text-sm mb-2">
                        <i class="bi bi-telephone-fill {{ $isMale ? 'text-primary' : 'text-destructive' }}"></i>
                        <span class="font-medium text-muted-foreground">{{ $displayMobile }}</span>
                        @if($isGuardianContact)
                            <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full ml-auto {{ $isMale ? 'bg-sky-100 text-sky-700' : 'bg-pink-500 text-white' }}">Guardian's</span>
                        @endif
                    </div>
                @endif
                @if($displayEmail)
                    <div class="flex items-center gap-2 text-sm">
                        <i class="bi bi-envelope-fill {{ $isMale ? 'text-primary' : 'text-destructive' }}"></i>
                        <span class="font-medium text-muted-foreground truncate">{{ $displayEmail }}</span>
                        @if($isGuardianContact)
                            <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full ml-auto {{ $isMale ? 'bg-sky-100 text-sky-700' : 'bg-pink-500 text-white' }}">Guardian's</span>
                        @endif
                    </div>
                @endif
                @if(!$displayMobile && !$displayEmail)
                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                        <i class="bi bi-info-circle"></i>
                        <span class="font-medium">No contact info</span>
                    </div>
                @endif
            </div>

            <!-- Details -->
            <div class="px-4 py-3 grow">
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <div class="text-xs text-muted-foreground uppercase font-medium mb-1 tracking-wide">Gender</div>
                        <div class="font-semibold text-muted-foreground capitalize">
                            <i class="bi {{ $isMale ? 'bi-man text-primary' : 'bi-woman text-destructive' }} mr-1"></i>
                            {{ $isMale ? 'Male' : 'Female' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground uppercase font-medium mb-1 tracking-wide">Age</div>
                        <div class="font-semibold text-muted-foreground">{{ $age ? $age . ' years' : 'N/A' }}</div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <div class="text-xs text-muted-foreground uppercase font-medium mb-1 tracking-wide">Nationality</div>
                        <div class="font-semibold text-muted-foreground text-lg nationality-display" data-iso3="{{ $member->nationality }}">{{ $member->nationality ?? 'N/A' }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-muted-foreground uppercase font-medium mb-1 tracking-wide">Horoscope</div>
                        <div class="font-semibold text-muted-foreground">
                            {{ $symbol }} {{ $horoscope }}
                        </div>
                    </div>
                </div>

                {{ $extraDetails ?? '' }}

                <div class="pt-2 border-t">
                    <div class="flex justify-between items-center text-sm mb-2">
                        <span class="text-muted-foreground font-medium">Next Birthday</span>
                        <span class="font-semibold text-muted-foreground">
                            @if($member->birthdate)
                                {{ $member->birthdate->copy()->year(now()->year)->isFuture()
                                    ? $member->birthdate->copy()->year(now()->year)->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])
                                    : $member->birthdate->copy()->year(now()->year + 1)->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}
                            @else
                                N/A
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-muted-foreground font-medium">Member Since</span>
                        <span class="font-semibold text-muted-foreground">{{ $sinceDate ? $sinceDate->format('d/m/Y') : 'N/A' }}</span>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            @if($footerStyle === 'solid')
            <div class="px-4 py-2 border-t" style="background-color: {{ $isMale ? '#7c3aed' : '#d63384' }};">
                <div class="flex items-center justify-center gap-2 text-sm">
                    <span class="font-medium text-white">
                        {{ $footerLabel }}
                    </span>
                </div>
            </div>
            @else
            <div class="px-4 py-2 {{ $isMale ? 'bg-purple-500/10' : 'bg-pink-500/10' }} border-t {{ $isMale ? 'border-purple-200' : 'border-pink-200' }}">
                <div class="flex items-center justify-center gap-2 text-sm">
                    <span class="font-medium {{ $isMale ? 'text-purple-700' : 'text-pink-700' }}">
                        {{ $footerLabel }}
                    </span>
                </div>
            </div>
            @endif
        </div>

    @if($href)
    </a>
    @endif
</div>
