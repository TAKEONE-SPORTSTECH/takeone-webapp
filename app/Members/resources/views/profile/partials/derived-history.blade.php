{{--
    ══════════════════════════════════════════════════════════════════════════
    FROM THE CLUB RECORDS — history the platform already knows.

    The Affiliations and Tournaments tabs read the member's own SELF-REPORTED
    log. The authoritative facts live elsewhere: `memberships` records who
    actually joined a club, `club_event_registrations` who actually entered an
    event. Without this block a member can be enrolled and have competed, and
    still see two empty tabs.

    Rendered ALONGSIDE the self-reported list, never merged into it — those cards
    are keyed on a real row id for their edit / skills / media / instructor
    controls, and a derived entry has no such row to act on.

    Only what is NOT already written up by hand appears here (App\Support\ProfileHistory),
    so nothing shows twice, and once the sync has written real rows this block
    empties itself.

    Expects: $rows (Collection), $kind ('affiliations'|'tournaments')
--}}
@php
    $rows = $rows ?? collect();
@endphp

@if ($rows->isNotEmpty())
    <div class="mb-6 rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
        <div class="flex items-start gap-3 px-4 py-3 bg-muted/60 border-b border-gray-100">
            <i class="bi bi-patch-check-fill text-primary text-lg mt-0.5"></i>
            <div class="min-w-0">
                <h4 class="text-sm font-bold text-gray-900 mb-0.5">
                    {{ $kind === 'tournaments'
                        ? __('member.derived_tournaments_title')
                        : __('member.derived_affiliations_title') }}
                </h4>
                <p class="text-xs text-muted-foreground mb-0">
                    {{ $kind === 'tournaments'
                        ? __('member.derived_tournaments_note')
                        : __('member.derived_affiliations_note') }}
                </p>
            </div>
        </div>

        <ul class="divide-y divide-gray-100">
            @foreach ($rows as $row)
                <li class="flex items-center gap-3 px-4 py-3">
                    @if ($kind === 'tournaments')
                        <span class="w-10 h-10 flex-shrink-0 rounded-lg bg-accent grid place-items-center">
                            <i class="bi bi-trophy-fill text-primary"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900 mb-0 truncate">{{ $row->title }}</p>
                            <p class="text-xs text-muted-foreground mb-0 truncate">
                                @if ($row->date){{ $row->date->format('M d, Y') }}@endif
                                @if ($row->club_name) · {{ $row->club_name }}@endif
                                @if ($row->location) · {{ $row->location }}@endif
                            </p>
                        </div>
                        @if ($row->sport)
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-muted text-muted-foreground flex-shrink-0">
                                {{ ucfirst($row->sport) }}
                            </span>
                        @endif
                    @else
                        <span class="w-10 h-10 flex-shrink-0">
                            @if ($row->logo)
                                <img src="{{ file_url($row->logo) }}" alt=""
                                     class="w-full h-full object-contain">
                            @else
                                <span class="w-full h-full rounded-lg bg-accent grid place-items-center">
                                    <i class="bi bi-building text-primary"></i>
                                </span>
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900 mb-0 truncate">
                                @if ($row->club_url)
                                    <a href="{{ $row->club_url }}" class="hover:text-primary transition-colors">{{ $row->club_name }}</a>
                                @else
                                    {{ $row->club_name }}
                                @endif
                            </p>
                            <p class="text-xs text-muted-foreground mb-0">
                                @if ($row->started_at){{ __('member.derived_since', ['date' => $row->started_at->format('M Y')]) }}@endif
                            </p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-medium flex-shrink-0
                            {{ $row->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-muted text-muted-foreground' }}">
                            {{ ucfirst($row->status ?? '') }}
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
