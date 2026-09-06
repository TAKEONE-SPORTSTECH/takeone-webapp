{{--
    The match card — who fought, in what, on which mat, and who officiated.

    Two layouts of the same facts, exactly as the design draws them: `.mac-card`
    above 991px and `.macm` below it, each hidden by the other's media query. The
    stylesheet they need ships once with the page.

    Included wherever the watch page offers a "Match" tab, so the three tab
    systems (the portrait review page, the fullscreen drawer, the wide pane)
    cannot drift apart.
--}}
<div class="mac-card">
    <div class="mac-shell">
        <div class="mac-head">
            <div class="mac-head-l">
                <a class="mac-event" href="{{ $bout['gallery_url'] }}">{{ $e['title'] }}</a>
                <div class="mac-sub">
                    @if ($e['sport_label'])<span>{{ $e['sport_label'] }}</span><span class="mac-dot">·</span>@endif
                    <a class="mac-gold" href="{{ $bout['bout_url'] }}">{{ $stage }}@if ($bout['division']) · {{ $bout['division'] }}@endif</a>
                    <span class="mac-dot">·</span>
                    <a href="{{ $bout['bout_url'] }}">{{ __('events.bout_card_match') }} #{{ $bout['match_no'] }}@if ($bout['court']) · {{ __('events.bout_card_court') }} {{ $bout['court'] }}@endif</a>
                </div>
            </div>
            @if ($e['date'])
                <div class="mac-head-r"><span class="mac-when">{{ $e['date'] }}</span></div>
            @endif
        </div>

        <div class="mac-body">
            <div class="mac-side mac-side-red">
                <a class="mac-photo mac-photo-red" href="{{ $bout['bout_url'] }}"
                   @if ($red['photo']) style="background-image:url('{{ $red['photo'] }}')" @endif></a>
                <span class="mac-id">
                    <span class="mac-corner mac-corner-red">
                        {{ Str::upper($red['corner_label']) }}
                        @if ($red['country'])<span class="fi fi-{{ Str::lower($red['country']) }} mac-flag"></span>@endif
                        @if ($red['won'])<span class="mac-win">{{ __('events.bout_card_winner') }}</span>@endif
                    </span>
                    <a class="mac-name" href="{{ $bout['bout_url'] }}">{{ $red['name'] }}</a>
                    @if ($red['club'])
                        <span class="mac-club"><span>{{ $red['club'] }}</span></span>
                    @endif
                </span>
            </div>

            <div class="mac-score">
                <span class="mac-score-lbl">{{ __('events.bout_video_final') }}</span>
                <span class="mac-score-row">
                    <span class="mac-pts mac-pts-red">{{ $red['score'] }}</span>
                    <span class="mac-dash">–</span>
                    <span class="mac-pts mac-pts-blue">{{ $blue['score'] }}</span>
                </span>
            </div>

            <div class="mac-side mac-side-blue">
                <span class="mac-id">
                    <span class="mac-corner mac-corner-blue">
                        @if ($blue['country'])<span class="fi fi-{{ Str::lower($blue['country']) }} mac-flag"></span>@endif
                        {{ Str::upper($blue['corner_label']) }}
                        @if ($blue['won'])<span class="mac-win">{{ __('events.bout_card_winner') }}</span>@endif
                    </span>
                    <a class="mac-name" href="{{ $bout['bout_url'] }}">{{ $blue['name'] }}</a>
                    @if ($blue['club'])
                        <span class="mac-club"><span>{{ $blue['club'] }}</span></span>
                    @endif
                </span>
                <a class="mac-photo mac-photo-blue" href="{{ $bout['bout_url'] }}"
                   @if ($blue['photo']) style="background-image:url('{{ $blue['photo'] }}')" @endif></a>
            </div>
        </div>

        @if (count($officials))
            <div class="mac-foot">
                <div class="mac-refs">
                    @foreach ($officials as $o)
                        <span class="mac-ref">
                            <span class="mac-ref-pic"
                                  @if ($o['photo']) style="background-image:url('{{ $o['photo'] }}')" @endif></span>
                            <span class="mac-ref-txt">
                                <span class="mac-ref-lbl">{{ Str::upper($o['role']) }}</span>
                                <span class="mac-ref-name">
                                    @if ($o['country'])<span class="fi fi-{{ Str::lower($o['country']) }} mac-flag"></span>@endif
                                    {{ $o['name'] }}
                                </span>
                            </span>
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>


<div class="macm">
    <div class="macm-head">
        @if ($e['sport_label'])
            <div class="macm-kicker-row">
                <a class="macm-kicker" href="{{ $bout['gallery_url'] }}">{{ $e['sport_label'] }}</a>
            </div>
        @endif

        <a class="macm-event" href="{{ $bout['gallery_url'] }}">{{ $e['title'] }}</a>

        <div class="macm-pills">
            <a class="macm-pill macm-pill-gold" href="{{ $bout['bout_url'] }}">{{ $stage }}@if ($bout['division']) · {{ $bout['division'] }}@endif</a>
            <a class="macm-pill" href="{{ $bout['bout_url'] }}">{{ __('events.bout_card_match') }} #{{ $bout['match_no'] }}@if ($bout['court']) · {{ __('events.bout_card_court') }} {{ $bout['court'] }}@endif</a>
        </div>

        @if ($e['date'])
            <div class="macm-when-row"><span class="macm-when">{{ $e['date'] }}</span></div>
        @endif
    </div>

    <div class="macm-body">
        @foreach ([['c' => $red, 'k' => 'red'], ['c' => $blue, 'k' => 'blue']] as $corner)
            @php $x = $corner['c']; $k = $corner['k']; @endphp
            <div class="macm-fighter">
                <a class="macm-pic-wrap{{ $x['won'] ? ' macm-win' : '' }}" href="{{ $bout['bout_url'] }}">
                    <span class="macm-pic macm-pic-{{ $k }}"
                          @if ($x['photo']) style="background-image:url('{{ $x['photo'] }}')" @endif></span>
                </a>

                <span class="macm-corner macm-corner-{{ $k }}">
                    {{ Str::upper($x['corner_label']) }}
                    @if ($x['country'])<span class="fi fi-{{ Str::lower($x['country']) }} macm-flag"></span>@endif
                </span>

                <a class="macm-name" href="{{ $bout['bout_url'] }}">
                    @foreach (preg_split('/\s+/', trim((string) $x['name'])) as $part)
                        <span class="macm-name-l">{{ $part }}</span>
                    @endforeach
                </a>

                @if ($x['club'])
                    <span class="macm-club macm-club-{{ $k }}"><span>{{ $x['club'] }}</span></span>
                @endif
            </div>
        @endforeach
    </div>

    <a class="macm-score" href="{{ $bout['bout_url'] }}">
        <span class="macm-score-mid">
            <span class="macm-score-lbl">{{ __('events.bout_video_final') }}</span>
            <span class="macm-score-row">
                <span class="macm-pts macm-pts-red">{{ $red['score'] }}</span>
                <span class="macm-dash">–</span>
                <span class="macm-pts macm-pts-blue">{{ $blue['score'] }}</span>
            </span>
        </span>
    </a>

    @if (count($officials))
        <div class="macm-foot">
            <div class="macm-refs">
                @foreach ($officials as $o)
                    <span class="macm-ref">
                        <span class="macm-ref-pic"
                              @if ($o['photo']) style="background-image:url('{{ $o['photo'] }}')" @endif></span>
                        <span class="macm-ref-txt">
                            <span class="macm-ref-lbl">{{ Str::upper($o['role']) }}</span>
                            <span class="macm-ref-name">
                                @if ($o['country'])<span class="fi fi-{{ Str::lower($o['country']) }} macm-flag"></span>@endif
                                {{ $o['name'] }}
                            </span>
                        </span>
                    </span>
                @endforeach
            </div>
        </div>
    @endif
</div>
