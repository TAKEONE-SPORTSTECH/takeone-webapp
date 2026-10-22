{{--
    One block per club — crest, name, size, medals; then its athletes with
    their divisions and medals. Shared by the draw report (events::bracket-pdf)
    and the federation Results PDF; the data is BracketPdf::clubPages().
    Expects \$clubs (one page of blocks) and \$sheetIds (the divisions that
    have a sheet in this document — only those are links; empty for none).
--}}
                    @foreach($clubs as $club)
                        <div class="club">
                            <div class="club-h">
                                @if($club['rank'])<span class="rk">{{ $club['rank'] }}</span>@endif
                                @if($club['logo'])
                                    <span class="lg"><img src="{{ $club['logo'] }}" alt=""></span>
                                @else
                                    <span class="lg none"><i class="bi bi-shield-fill"></i></span>
                                @endif
                                <div class="nm">{{ $club['name'] }}
                                    <small>{{ trans_choice('events::events.draw_print_club_athletes', $club['count'], ['n' => $club['count']]) }}@if($club['continued']) · {{ __('events::events.draw_print_continued') }}@endif</small>
                                </div>
                                @if(array_sum($club['medals']) > 0 && ! $club['continued'])
                                    <div class="md">
                                        @foreach([1, 2, 3] as $pl)
                                            <span class="{{ $club['medals'][$pl] ? '' : 'zero' }}"><x-medal-icon :place="$pl" :size="15" :numeral="false" />{{ $club['medals'][$pl] }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="club-p">
                                @foreach($club['people'] as $a)
                                    <div class="ath{{ array_key_exists('photo', $a) ? ' with-face' : '' }}">
                                        @if(array_key_exists('photo', $a))
                                            <span class="ph">@if($a['photo'])<img src="{{ $a['photo'] }}" alt="">@else<img class="none" src="{{ $a['placeholder'] }}" alt="">@endif</span>
                                        @endif
                                        <div class="t">
                                            @php $linked = collect($a['divisions'])->filter(fn ($d) => $sheetIds->has($d['id']))->values(); @endphp
                                            {{-- The name opens their first bracket; each division its own. --}}
                                            @if($linked->isNotEmpty())
                                                <a class="n" dir="auto" href="#sheet-{{ $linked[0]['id'] }}">@if(! empty($a['flag']))<span class="fi fi-{{ $a['flag'] }}"></span>@endif{{ $a['name'] }}</a>
                                            @else
                                                <div class="n" dir="auto">@if(! empty($a['flag']))<span class="fi fi-{{ $a['flag'] }}"></span>@endif{{ $a['name'] }}</div>
                                            @endif
                                            @if($a['divisions'])
                                                <div class="d">@foreach($a['divisions'] as $d)@if(! $loop->first) · @endif @if($sheetIds->has($d['id']))<a href="#sheet-{{ $d['id'] }}">{{ $d['name'] }}</a>@else{{ $d['name'] }}@endif @endforeach</div>
                                            @endif
                                        </div>
                                        @if($a['medals'])
                                            <div class="m">@foreach($a['medals'] as $pl)<x-medal-icon :place="$pl" :size="11" :numeral="false" />@endforeach</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
