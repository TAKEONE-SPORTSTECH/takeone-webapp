{{-- Mobile members list — compact, scannable cards that tap to open the full
     member popup. Rendered on load and returned standalone for AJAX search.
     Keeps the .member-card-wrapper / data-member-id / data-popup-url hooks the
     shared members JS relies on. --}}
@php
    // Resolve ISO2/ISO3 nationality → flag emoji once per request.
    static $natMap = null;
    if ($natMap === null) {
        $natMap = [];
        if ($json = @file_get_contents(public_path('data/countries.json'))) {
            foreach (json_decode($json, true) ?? [] as $c) {
                $natMap[strtoupper($c['iso2'])] = $c;
                $natMap[strtoupper($c['iso3'])] = $c;
            }
        }
    }
@endphp

@if($members->count() > 0)
    <div class="space-y-2.5 mobile-stagger" id="membersGrid">
        @foreach($members as $member)
            @php
                $age = $member->age;
                $ageGroup = $age === null ? __('platform.age_adult') : match(true) {
                    $age < 2  => __('platform.age_infant'),  $age < 4  => __('platform.age_toddler'), $age < 6  => __('platform.age_preschooler'),
                    $age < 13 => __('platform.age_child'),   $age < 20 => __('platform.age_teenager'), $age < 40 => __('platform.age_young_adult'),
                    $age < 60 => __('platform.age_adult'),   default   => __('platform.age_senior'),
                };
                $isMale = ($member->gender ?? 'Male') === 'Male';
                $accent = $isMale ? 'primary' : 'pink';

                $natCode = strtoupper($member->nationality ?? '');
                $country = $natMap[$natCode] ?? null;
                $flag = $country
                    ? implode('', array_map(fn($ch) => mb_chr(ord($ch) + 127397), str_split(strtoupper($country['iso2']))))
                    : '';

                $guardian = $member->guardians->first()?->guardian ?? null;
                $mob = $member->mobile;
                $email = $member->email;
                $isGuardian = false;
                if ((!$mob || (is_array($mob) && empty($mob['number']))) && !$email && $guardian) {
                    $mob = $guardian->mobile; $email = $guardian->email; $isGuardian = true;
                }
                $phone = is_array($mob) ? trim(($mob['code'] ?? '') . ' ' . ($mob['number'] ?? '')) : $mob;
                $contact = $phone ?: $email;
                $clubs = $member->member_clubs_count ?? 0;
            @endphp
            <div class="member-card-wrapper m-card m-press relative bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden cursor-pointer"
                 data-member-id="{{ $member->id }}"
                 data-popup-url="{{ route('admin.platform.members.popup', $member->id) }}">
                {{-- gender accent rail --}}
                <span class="absolute left-0 top-0 bottom-0 w-1 {{ $isMale ? 'bg-primary' : 'bg-pink-500' }}"></span>
                <a href="{{ route('member.show', $member->uuid) }}" class="flex items-center gap-3 p-3 pl-4 no-underline">
                    {{-- avatar --}}
                    <span class="relative shrink-0">
                        <span class="block w-14 h-14 rounded-2xl overflow-hidden ring-2 {{ $isMale ? 'ring-primary/30' : 'ring-pink-400/30' }}">
                            @if($member->profile_picture)
                                <img src="{{ asset('storage/'.$member->profile_picture) }}?v={{ optional($member->updated_at)->timestamp }}" alt="" class="w-14 h-14 object-cover">
                            @else
                                <span class="w-14 h-14 flex items-center justify-center text-white font-bold text-xl {{ $isMale ? 'bg-gradient-to-br from-purple-500 to-primary' : 'bg-gradient-to-br from-pink-500 to-pink-700' }}">{{ strtoupper(substr($member->full_name ?? 'M', 0, 1)) }}</span>
                            @endif
                        </span>
                        @if($clubs > 0)
                            <span class="absolute -bottom-1 -right-1 min-w-[20px] h-5 px-1 rounded-full bg-green-500 text-white text-[10px] font-bold flex items-center justify-center border-2 border-white">{{ $clubs }}</span>
                        @endif
                    </span>

                    {{-- info --}}
                    <span class="flex-1 min-w-0">
                        <span class="flex items-center gap-1.5">
                            <span class="font-bold text-foreground truncate text-[15px]">{{ $member->full_name ?? __('platform.unknown') }}</span>
                            @if($flag)<span class="text-sm leading-none">{{ $flag }}</span>@endif
                        </span>
                        <span class="flex items-center gap-1.5 mt-1 flex-wrap">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $isMale ? 'bg-accent text-primary' : 'bg-pink-50 text-pink-600' }}">{{ $ageGroup }}</span>
                            <span class="text-[11px] text-muted-foreground flex items-center gap-1">
                                <i class="bi {{ $isMale ? 'bi-gender-male text-primary' : 'bi-gender-female text-pink-500' }}"></i>{{ $age !== null ? $age.' '.__('platform.years_short') : '—' }}
                            </span>
                        </span>
                        @if($contact)
                            <span class="flex items-center gap-1.5 mt-1 text-[12px] text-muted-foreground truncate">
                                <i class="bi {{ $phone ? 'bi-telephone-fill' : 'bi-envelope-fill' }} {{ $isMale ? 'text-primary/70' : 'text-pink-500/70' }}"></i>
                                <span class="truncate">{{ $contact }}</span>
                                @if($isGuardian)<span class="shrink-0 px-1.5 py-0.5 rounded-full text-[9px] font-semibold bg-sky-100 text-sky-700">{{ __('platform.guardian') }}</span>@endif
                            </span>
                        @endif
                    </span>

                    <i class="bi bi-chevron-right text-muted-foreground/50 shrink-0"></i>
                </a>
            </div>
        @endforeach
    </div>

    <div class="flex justify-center mt-4">
        {{ $members->withQueryString()->links() }}
    </div>
@else
    <div class="bg-white rounded-2xl px-6 py-14 text-center shadow-sm border border-gray-100">
        <i class="bi bi-people text-5xl text-gray-300 m-float inline-block"></i>
        <p class="text-sm font-semibold text-foreground mt-4">{{ __('platform.no_members_found') }}</p>
        <p class="text-[12px] text-muted-foreground mt-1">{{ $search ? __('platform.no_members_match') : __('platform.no_members_yet') }}</p>
    </div>
@endif
