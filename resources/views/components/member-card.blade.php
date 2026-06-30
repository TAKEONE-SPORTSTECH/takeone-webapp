@props([
    'member',
    'href' => null,
    'footerLabel' => 'PLATFORM MEMBER',
    'footerStyle' => 'solid',
    'guardian' => null,
    'memberSince' => null,
    'cardClass' => 'family-card',
    'variant' => 'member',   // 'member' | 'instructor' — instructor drops athlete-only fields
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

    $isMale = ($member->gender ?? 'Male') === 'Male';
    $sinceDate = $memberSince ?? $member->created_at;

    // Nationality: resolve ISO2/ISO3 code → flag emoji + full name
    static $countriesMap = null;
    if ($countriesMap === null) {
        $jsonData = @file_get_contents(public_path('data/countries.json'));
        $countriesMap = [];
        if ($jsonData) {
            foreach (json_decode($jsonData, true) ?? [] as $c) {
                $countriesMap[strtoupper($c['iso2'])] = $c;
                $countriesMap[strtoupper($c['iso3'])] = $c;
            }
        }
    }
    $natCode    = strtoupper($member->nationality ?? '');
    $countryInfo = $countriesMap[$natCode] ?? null;
    if ($countryInfo) {
        $flagEmoji       = implode('', array_map(fn($ch) => mb_chr(ord($ch) + 127397), str_split(strtoupper($countryInfo['iso2']))));
        $nationalityDisplay = $flagEmoji . ' ' . $countryInfo['name'];
    } else {
        $nationalityDisplay = $member->nationality ?? 'N/A';
    }

    // Taekwondo classification
    $latestRecord = $member->relationLoaded('latestHealthRecord')
        ? $member->latestHealthRecord
        : null;
    $weight = $latestRecord ? (float) $latestRecord->weight : null;

    // Category derives from age alone; weight class requires both age + weight
    $tkdFull = ($age !== null && $weight > 0)
        ? classifyTaekwondo(strtolower($member->gender ?? 'male'), $age, $weight)
        : null;
    $weightClass = $tkdFull ? $tkdFull['category'] . ' kg' : null;

    $tkdCategory = $tkdFull
        ? $tkdFull['age_group']
        : ($age !== null ? match(true) {
            $age < 6  => null,
            $age < 12 => 'Kids',
            $age < 15 => 'Cadet',
            $age < 18 => 'Junior',
            $age < 31 => 'Senior',
            default   => 'Masters',
        } : null);

    // Guardian contact fallback
    $displayMobile = $member->mobile;
    $displayEmail = $member->email;
    $isGuardianContact = false;

    if ((!$displayMobile || (is_array($displayMobile) && empty($displayMobile['number']))) && !$displayEmail && $guardian) {
        $displayMobile = $guardian->mobile;
        $displayEmail = $guardian->email;
        $isGuardianContact = true;
    }

    // Gender-driven accent (kept from the original identity: purple = male, pink = female)
    $accent     = $isMale ? 'hsl(250 65% 60%)' : '#d63384';
    $accentSoft = $isMale ? 'hsl(250 65% 65% / 0.10)' : 'rgba(214, 51, 132, 0.09)';
    $accentText = $isMale ? 'text-primary' : 'text-pink-600';
@endphp

<div {{ $attributes->merge(['class' => 'member-card-wrapper']) }}
     data-member-name="{{ $member->full_name ?? '' }}"
     data-member-phone="{{ $member->formatted_mobile ?? '' }}"
     data-member-nationality="{{ $member->nationality ?? '' }}"
     data-member-gender="{{ $member->gender ?? '' }}"
     data-age="{{ $age ?? '' }}"
     data-gender="{{ strtolower($member->gender ?? '') }}"
     data-tkd-category="{{ $tkdCategory ?? '' }}"
     data-weight-class="{{ $weightClass ?? '' }}">

    @if($href)
    <a href="{{ $href }}" class="no-underline h-full flex flex-col">
    @endif

        <div class="card h-full shadow-sm border overflow-hidden flex flex-col {{ $cardClass }} relative">

            {{-- Gender accent rail --}}
            <span class="absolute left-0 top-0 bottom-0 w-1.5 z-10" style="background: {{ $accent }};"></span>

            {{-- Header --}}
            <div class="pl-5 pr-4 pt-4 pb-3" style="background: linear-gradient(135deg, {{ $accentSoft }} 0%, transparent 65%);">
                <div class="flex items-start gap-3">
                    <div class="relative shrink-0">
                        <div class="rounded-full border-4 border-white shadow w-16 h-16 overflow-hidden" style="box-shadow: 0 0 0 2px {{ $isMale ? 'hsl(250 65% 65% / 0.35)' : 'rgba(214, 51, 132, 0.3)' }} !important;">
                            @if($member->profile_picture)
                                <img src="{{ asset('storage/' . $member->profile_picture) }}?v={{ $member->updated_at->timestamp }}" alt="{{ $member->full_name }}" class="w-full h-full object-cover" style="image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges;">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-white font-bold text-lg" style="background: linear-gradient(135deg, {{ $isMale ? 'hsl(250 65% 70%) 0%, hsl(250 60% 58%) 100%' : '#d63384 0%, #a61e4d 100%' }});">
                                    {{ mb_strtoupper(mb_substr($member->full_name ?? 'M', 0, 1, 'UTF-8'), 'UTF-8') }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="grow min-w-0">
                        <h5 class="font-bold mb-1.5 truncate leading-tight">{{ $member->full_name ?? 'Unknown' }}</h5>
                        <div class="flex flex-wrap gap-1.5">
                            <span class="badge {{ $isMale ? 'bg-primary' : 'bg-pink-600' }}">{{ $ageGroup }}</span>
                            @if(isset($member->member_clubs_count) && $member->member_clubs_count > 0)
                                <span class="badge bg-success">{{ $member->member_clubs_count }} {{ Str::plural('Club', $member->member_clubs_count) }}</span>
                            @endif
                            {{ $badges ?? '' }}
                        </div>
                        {{-- Identity meta: nationality · horoscope --}}
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground mt-2 min-w-0">
                            <span class="inline-flex items-center font-medium truncate max-w-[55%]">{{ $nationalityDisplay }}</span>
                            <span class="text-gray-300">·</span>
                            <span class="inline-flex items-center gap-1 font-medium whitespace-nowrap">{{ $symbol }} {{ $horoscope }}</span>
                        </div>
                        {{ $headerExtra ?? '' }}
                    </div>
                </div>
            </div>

            {{-- Contact --}}
            <div class="pl-5 pr-4 py-3 border-t border-gray-100 space-y-1.5">
                @if($isGuardianContact && $guardian)
                <div class="flex items-center gap-1 text-xs text-info">
                    <i class="bi bi-info-circle-fill"></i>
                    <span class="font-medium">Guardian's contact ({{ $guardian->full_name }})</span>
                </div>
                @endif
                @if($displayMobile && is_array($displayMobile) && isset($displayMobile['number']))
                    <div class="flex items-center gap-2 text-sm">
                        <i class="bi bi-telephone-fill {{ $accentText }}"></i>
                        <span class="font-medium text-muted-foreground">{{ $displayMobile['code'] ?? '' }} {{ $displayMobile['number'] }}</span>
                        @if($isGuardianContact)
                            <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full ml-auto {{ $isMale ? 'bg-accent text-primary' : 'bg-pink-500 text-white' }}">Guardian's</span>
                        @endif
                    </div>
                @elseif($displayMobile && is_string($displayMobile))
                    <div class="flex items-center gap-2 text-sm">
                        <i class="bi bi-telephone-fill {{ $accentText }}"></i>
                        <span class="font-medium text-muted-foreground">{{ $displayMobile }}</span>
                        @if($isGuardianContact)
                            <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full ml-auto {{ $isMale ? 'bg-accent text-primary' : 'bg-pink-500 text-white' }}">Guardian's</span>
                        @endif
                    </div>
                @endif
                @if($displayEmail)
                    <div class="flex items-center gap-2 text-sm">
                        <i class="bi bi-envelope-fill {{ $accentText }}"></i>
                        <span class="font-medium text-muted-foreground truncate">{{ $displayEmail }}</span>
                        @if($isGuardianContact)
                            <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full ml-auto {{ $isMale ? 'bg-accent text-primary' : 'bg-pink-500 text-white' }}">Guardian's</span>
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

            {{-- Stats + details --}}
            <div class="pl-5 pr-4 py-3 grow">
                {{-- Headline stat strip: Age · Category · Gender --}}
                <div class="grid grid-cols-3 divide-x divide-gray-100 text-center">
                    <div class="px-2">
                        <div class="text-lg font-bold text-foreground leading-none">{{ $age ?? '—' }}</div>
                        <div class="text-[10px] uppercase tracking-wide text-muted-foreground mt-1">Years</div>
                    </div>
                    <div class="px-2 min-w-0">
                        <div class="text-sm font-bold {{ $accentText }} leading-none truncate">
                            <i class="bi bi-trophy-fill text-[11px] mr-0.5"></i>{{ $tkdCategory ?? '—' }}
                        </div>
                        <div class="text-[10px] uppercase tracking-wide text-muted-foreground mt-1">Category</div>
                    </div>
                    <div class="px-2">
                        <div class="text-base font-bold leading-none">
                            <i class="bi {{ $isMale ? 'bi-man text-primary' : 'bi-woman text-pink-600' }}"></i>
                        </div>
                        <div class="text-[10px] uppercase tracking-wide text-muted-foreground mt-1">{{ $isMale ? 'Male' : 'Female' }}</div>
                    </div>
                </div>

                {{ $extraDetails ?? '' }}

                {{-- Key facts --}}
                <div class="mt-3 pt-3 border-t border-gray-100 space-y-1.5 text-sm">
                    <div class="flex justify-between items-center gap-2">
                        <span class="text-muted-foreground">Weight class</span>
                        @if($weightClass)
                            <span class="font-semibold {{ $accentText }}"><span class="mr-0.5">⚖️</span>{{ $weightClass }}</span>
                        @else
                            <span class="font-normal text-muted-foreground">No weight data</span>
                        @endif
                    </div>
                    <div class="flex justify-between items-center gap-2">
                        <span class="text-muted-foreground">Next birthday</span>
                        <span class="font-semibold text-foreground">
                            @if($member->birthdate)
                                {{ $member->birthdate->copy()->year(now()->year)->isFuture()
                                    ? $member->birthdate->copy()->year(now()->year)->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])
                                    : $member->birthdate->copy()->year(now()->year + 1)->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }}
                            @else
                                N/A
                            @endif
                        </span>
                    </div>
                    <div class="flex justify-between items-center gap-2">
                        <span class="text-muted-foreground">Member since</span>
                        <span class="font-semibold text-foreground">{{ $sinceDate ? $sinceDate->format('d/m/Y') : 'N/A' }}</span>
                    </div>
                </div>
            </div>

            {{-- Ghost footer — quiet status chip, not a loud bar --}}
            <div class="pl-5 pr-4 py-2.5 border-t border-gray-100 flex items-center justify-between gap-2" style="background: {{ $accentSoft }};">
                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider {{ $accentText }}">
                    <span class="w-1.5 h-1.5 rounded-full" style="background: {{ $accent }};"></span>
                    {{ $footerLabel }}
                </span>
                @if($href)
                    <i class="bi bi-arrow-right text-xs text-gray-400"></i>
                @endif
            </div>
        </div>

    @if($href)
    </a>
    @endif
</div>
