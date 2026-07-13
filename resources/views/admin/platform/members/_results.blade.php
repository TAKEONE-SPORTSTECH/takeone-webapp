{{-- Members results region — rendered on full page load and returned standalone for AJAX search.
     Renders BOTH the cards grid and a table; visibility is toggled by the `.show-table` class on
     the persistent #membersResults container (set by the Cards/Table toggle in index.blade). --}}
@if($members->count() > 0)
    {{-- ===== Cards view ===== --}}
    <div class="members-cards-view grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-4" id="membersGrid">
        @foreach($members as $member)
            <x-member-card :member="$member" :href="route('member.show', $member->uuid)" :guardian="$member->guardians->first()?->guardian ?? null"
                class="w-full"
                data-member-id="{{ $member->id }}"
                data-popup-url="{{ route('admin.platform.members.popup', $member->id) }}" />
        @endforeach
    </div>

    {{-- ===== Table view ===== --}}
    <div class="members-table-view bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-4">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-start text-xs uppercase tracking-wide text-muted-foreground bg-muted/50 border-b border-gray-100">
                        <th class="px-4 py-3 font-medium">{{ __('platform.members_results_member') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('platform.members_results_gender') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('platform.members_results_age') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('platform.members_results_nationality') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('platform.members_results_category') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('platform.members_results_clubs') }}</th>
                        <th class="px-4 py-3 font-medium whitespace-nowrap">{{ __('platform.members_results_member_since') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($members as $member)
                        @php
                            $tAge   = $member->age;
                            $tMale  = ($member->gender ?? 'Male') === 'Male';
                            $tCat   = $tAge !== null ? match(true) {
                                $tAge < 6  => null,
                                $tAge < 12 => 'Kids',
                                $tAge < 15 => 'Cadet',
                                $tAge < 18 => 'Junior',
                                $tAge < 31 => 'Senior',
                                default    => 'Masters',
                            } : null;
                            $tClubs = $member->member_clubs_count ?? 0;
                        @endphp
                        <tr class="member-card-wrapper hover:bg-muted/40 transition-colors cursor-pointer"
                            data-member-id="{{ $member->id }}"
                            data-popup-url="{{ route('admin.platform.members.popup', $member->id) }}"
                            data-member-name="{{ $member->full_name ?? '' }}"
                            data-member-gender="{{ $member->gender ?? '' }}"
                            data-age="{{ $tAge ?? '' }}"
                            data-tkd-category="{{ $tCat ?? '' }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="w-9 h-9 rounded-full overflow-hidden shrink-0 grid place-items-center text-white font-bold text-xs"
                                          style="background: linear-gradient(135deg, {{ $tMale ? 'hsl(250 65% 70%) 0%, hsl(250 60% 58%) 100%' : '#d63384 0%, #a61e4d 100%' }});">
                                        @if($member->profile_picture)
                                            <img src="{{ asset('storage/' . $member->profile_picture) }}?v={{ $member->updated_at->timestamp }}" alt="{{ $member->full_name }}" class="w-full h-full object-cover">
                                        @else
                                            {{ mb_strtoupper(mb_substr($member->full_name ?? 'M', 0, 1, 'UTF-8'), 'UTF-8') }}
                                        @endif
                                    </span>
                                    <span class="font-semibold text-foreground truncate">{{ $member->full_name ?? __('platform.members_results_unknown') }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-1 {{ $tMale ? 'text-primary' : 'text-pink-600' }}">
                                    <i class="bi {{ $tMale ? 'bi-man' : 'bi-woman' }}"></i>{{ $tMale ? __('platform.members_results_male') : __('platform.members_results_female') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground whitespace-nowrap">{{ $tAge !== null ? $tAge . ' ' . __('platform.members_results_yrs') : '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <span class="nationality-display" data-iso3="{{ $member->nationality }}">{{ $member->nationality ?? '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if($tCat)
                                    <span class="inline-flex items-center gap-1 font-medium {{ $tMale ? 'text-primary' : 'text-pink-600' }}">
                                        <i class="bi bi-trophy-fill text-xs"></i>{{ $tCat }}
                                    </span>
                                @else
                                    <span class="text-muted-foreground">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($tClubs > 0)
                                    <span class="badge bg-success">{{ $tClubs }}</span>
                                @else
                                    <span class="text-muted-foreground">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-muted-foreground whitespace-nowrap">{{ $member->created_at?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination (shared by both views) -->
    <div class="flex justify-center mb-4">
        {{ $members->withQueryString()->links() }}
    </div>
@else
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body text-center py-12">
            <i class="bi bi-people text-muted-foreground text-6xl"></i>
            <h5 class="mt-3 mb-2">{{ __('platform.members_results_none_title') }}</h5>
            <p class="text-muted-foreground mb-0">
                @if($search)
                    {{ __('platform.members_results_none_search') }}
                @else
                    {{ __('platform.members_results_none_empty') }}
                @endif
            </p>
        </div>
    </div>
@endif
