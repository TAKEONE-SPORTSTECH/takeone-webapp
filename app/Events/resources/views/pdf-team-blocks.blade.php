{{--
    The people running an event, one section per group, a card per person —
    face (3:4), job, name, club. Shared by the draw report (events::bracket-pdf)
    and the federation competition report; the data is BracketPdf::cover()['team'].
    Expects \$sections (one page of sections).
--}}
                    @foreach($sections as $sec)
                        <div class="tsec">
                            <div class="h2">{{ $sec['heading'] }}</div>
                            <div class="tgrid">
                                @foreach($sec['cards'] as $m)
                                    <div class="pcard">
                                        <div class="ph">
                                            @if($m['photo'])<img src="{{ $m['photo'] }}" alt="">@else<img class="ph-none" src="{{ $m['placeholder'] }}" alt="">@endif
                                        </div>
                                        <div class="tx">
                                            <div class="job">{{ $m['job'] }}</div>
                                            <div class="nm" dir="auto">{{ $m['name'] }}</div>
                                            @if($m['club'])
                                                <div class="cl">@if($m['logo'])<img src="{{ $m['logo'] }}" alt="">@else<i>{{ __('events::events.draw_print_club') }}</i>@endif<span>{{ $m['club'] }}</span></div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
