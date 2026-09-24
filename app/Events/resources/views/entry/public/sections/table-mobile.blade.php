{{-- ===== Tables, for anybody with the link =====

     One table, or many. A team league is one table and it IS the event; a
     championship may run several weight classes as pools and each is its own,
     beside the classes that are bracketed — an event with both publishes both
     doors, and this is the one behind "Pool tables".

     The payload is the owning package's own (EventType::publicView), already
     narrowed to names and numbers on the way out: no ids, no crests, no
     weights, nothing that addresses a person. This page renders it and knows
     nothing else about leagues or pools. --}}

@php
    $tables = $e['type_public']['data']['tables'] ?? [];
@endphp

<div class="px-4 pt-3 pb-4 space-y-5">
    @forelse($tables as $block)
        @php
            $rows = $block['rows'] ?? [];
            $fixtures = collect($block['fixtures'] ?? []);
            $days = $fixtures->groupBy(fn ($f) => $f['matchday'] ?? 0)->sortKeys();
        @endphp

        <div class="space-y-3">
            {{-- ── The table ── --}}
            <div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-4 py-3 flex items-center gap-2.5 text-white relative overflow-hidden"
                     style="background: linear-gradient(135deg, {{ $ev }}, {{ $ev }}b0);">
                    <div class="absolute -right-6 -top-8 w-24 h-24 rounded-full bg-white/10"></div>
                    <i class="bi bi-table relative"></i>
                    <p class="relative min-w-0 flex-1 text-[13px] font-black leading-tight truncate">
                        {{ $block['division'] ?? ($e['type_public']['label'] ?? '') }}
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-[12px]">
                        <thead>
                            <tr class="bg-muted/40 text-[9.5px] font-black uppercase tracking-wider text-muted-foreground">
                                <th class="py-2 ps-3 text-center w-8">#</th>
                                <th class="py-2 text-start">{{ __('personal.event_show_team') }}</th>
                                <th class="py-2 text-center w-7">{{ __('personal.event_show_col_played') }}</th>
                                <th class="py-2 text-center w-7">{{ __('personal.event_show_col_won') }}</th>
                                <th class="py-2 text-center w-7">{{ __('personal.event_show_col_drawn') }}</th>
                                <th class="py-2 text-center w-7">{{ __('personal.event_show_col_lost') }}</th>
                                <th class="py-2 text-center w-9">{{ __('personal.event_show_col_gd') }}</th>
                                <th class="py-2 pe-3 text-center w-10">{{ __('personal.event_show_col_pts') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr class="border-t border-gray-50">
                                    <td class="py-2.5 ps-3 text-center">
                                        <span class="w-6 h-6 rounded-lg grid place-items-center text-[11px] font-black mx-auto"
                                              @if($loop->first)
                                                  style="background: {{ $ev }}1f; color: {{ $ev }};"
                                              @else
                                                  style="background: hsl(220 15% 94%); color: hsl(215 15% 45%);"
                                              @endif>{{ $row['position'] }}</span>
                                    </td>
                                    <td class="py-2.5">
                                        <p class="font-bold text-foreground truncate max-w-[150px]">{{ $row['name'] }}</p>
                                        @if(! empty($row['form']))
                                            <span class="flex items-center gap-0.5 mt-1">
                                                @foreach($row['form'] as $f)
                                                    <span class="w-1.5 h-1.5 rounded-full {{ $f === 'W' ? 'bg-green-500' : ($f === 'D' ? 'bg-gray-300' : 'bg-red-400') }}"></span>
                                                @endforeach
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 text-center tabular-nums text-muted-foreground">{{ $row['played'] }}</td>
                                    <td class="py-2.5 text-center tabular-nums text-muted-foreground">{{ $row['won'] }}</td>
                                    <td class="py-2.5 text-center tabular-nums text-muted-foreground">{{ $row['drawn'] }}</td>
                                    <td class="py-2.5 text-center tabular-nums text-muted-foreground">{{ $row['lost'] }}</td>
                                    <td class="py-2.5 text-center tabular-nums font-semibold {{ $row['difference'] > 0 ? 'text-green-600' : ($row['difference'] < 0 ? 'text-red-500' : 'text-muted-foreground') }}">
                                        {{ $row['difference'] > 0 ? '+' : '' }}{{ $row['difference'] }}
                                    </td>
                                    <td class="py-2.5 pe-3 text-center font-black tabular-nums">{{ $row['points'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if(! empty($block['points']))
                    <p class="px-3 py-2 bg-muted/30 text-[10.5px] text-muted-foreground">
                        {{ __('event-league::messages.points_rule', [
                            'win' => $block['points']['win'] ?? 3,
                            'draw' => $block['points']['draw'] ?? 1,
                        ]) }}
                    </p>
                @endif
            </div>

            {{-- ── The matches it was built from ── --}}
            @foreach($days as $no => $group)
                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden">
                    @if($no)
                        <div class="px-4 py-2.5 flex items-center justify-between text-white"
                             style="background: linear-gradient(135deg, {{ $ev }}, {{ $ev }}b0);">
                            <span class="text-[12px] font-black">{{ __('event-league::messages.matchday', ['no' => $no]) }}</span>
                            <span class="text-[10.5px] text-white/85">{{ $group->first()['kickoff'] }}</span>
                        </div>
                    @endif

                    @foreach($group as $f)
                        <div class="flex items-center gap-2 px-3 py-2.5 {{ ! $loop->first || $no ? 'border-t border-gray-50' : '' }}">
                            @if(! $no && $f['kickoff'])
                                <span class="text-[10px] font-black text-muted-foreground tabular-nums w-7 flex-shrink-0">{{ $f['kickoff'] }}</span>
                            @endif
                            <span class="min-w-0 flex-1 text-[13px] text-end truncate {{ $f['played'] && $f['home_score'] > $f['away_score'] ? 'font-black text-foreground' : 'font-semibold text-foreground' }}">{{ $f['home'] }}</span>
                            <span class="flex-shrink-0 px-2.5 py-1 rounded-lg text-[12px] font-black tabular-nums {{ $f['played'] ? 'bg-foreground text-white' : 'bg-muted text-muted-foreground' }}">
                                {{ $f['played'] ? $f['home_score'].' – '.$f['away_score'] : 'v' }}
                            </span>
                            <span class="min-w-0 flex-1 text-[13px] truncate {{ $f['played'] && $f['away_score'] > $f['home_score'] ? 'font-black text-foreground' : 'font-semibold text-foreground' }}">{{ $f['away'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @empty
        @include('events::entry.public.partials.empty', [
            'ev' => $ev, 'icon' => 'bi-table',
            'title' => __('events::events.public_pools_title'),
            'flush' => true,
        ])
    @endforelse
</div>
