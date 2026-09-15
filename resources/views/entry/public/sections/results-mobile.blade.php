{{-- ===== Who won =====
     The result sheet, as it would be pinned to the wall of the hall: one card
     per division, the places in order, the champion first.

     A medal is a fact about the competition and this page is the competition's
     public face, so the names are here in plain text — the same restraint as
     the entry list, and nothing else: no entry ids, no photographs, no scores.

     Fourth place is shown because a result sheet has four names on it: the
     athlete who lost the bronze bout came fourth, and that is a placing they
     carry. It wears its numeral rather than a fourth colour of disc. --}}

@php
    $medals = [
        1 => ['🥇', '#f59e0b', __('personal.event_show_champion')],
        2 => ['🥈', '#9ca3af', __('personal.event_show_runner_up')],
        3 => ['🥉', '#b45309', __('personal.event_show_third_place')],
        4 => ['',   '#64748b', __('personal.event_show_fourth_place')],
    ];
@endphp

<div class="px-4 pt-3 pb-4 space-y-3">
    @forelse($e['results']['divisions'] ?? [] as $division)
        <div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-4 py-3 flex items-center gap-2.5 text-white relative overflow-hidden"
                 style="background: linear-gradient(135deg, {{ $ev }}, {{ $ev }}b0);">
                <div class="absolute -right-6 -top-8 w-24 h-24 rounded-full bg-white/10"></div>
                <i class="bi bi-trophy-fill relative"></i>
                <p class="relative min-w-0 flex-1 text-[13px] font-black leading-tight truncate">{{ $division['division'] }}</p>
            </div>

            <div class="p-3 space-y-1.5">
                @foreach($division['places'] as $place)
                    @php $medal = $medals[$place['place']] ?? ['', '#64748b', '#'.$place['place']]; @endphp
                    <div class="flex items-center gap-3 rounded-xl p-2" style="background: {{ $medal[1] }}12;">
                        @if($medal[0] !== '')
                            <span class="text-2xl flex-shrink-0 leading-none">{{ $medal[0] }}</span>
                        @else
                            <span class="w-8 h-8 rounded-full grid place-items-center flex-shrink-0 text-xs font-black"
                                  style="background: {{ $medal[1] }}20; color: {{ $medal[1] }};">{{ $place['place'] }}</span>
                        @endif
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-bold text-foreground truncate">{{ $place['name'] }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ $medal[2] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        @include('entry.public.partials.empty', [
            'ev' => $ev, 'icon' => 'bi-trophy',
            'title' => __('events.public_results_empty_title'),
            'flush' => true,
        ])
    @endforelse
</div>
