{{--
    The certificate check, beyond the paper (EventCertificates::verifyStory): the medal,
    the event, every bout the holder fought, the division's draw, and what the reader
    can do with the page. Shared by verify/desktop and verify/mobile; styled by the
    .cv-* rules in verify/partials/head (the shared Tailwind bundle is prebuilt).

    Every value is printed escaped. Pictures are URLs the server already decided may
    be shown (a public face, a club crest); nothing here decides that.
--}}
@php
    $story = $story ?? [];
    $ev = $story['event'] ?? [];
    $bouts = $story['bouts'] ?? [];
    $wins = collect($bouts)->where('bye', false)->where('decided', true)->where('won', true)->count();
    $losses = collect($bouts)->where('bye', false)->where('decided', true)->where('won', false)->count();
    $medalName = [1 => __('cert::messages.medal_short.1'), 2 => __('cert::messages.medal_short.2'), 3 => __('cert::messages.medal_short.3')][$place ?? 0] ?? null;
@endphp

@if($medalName && ! $revoked)
    <a class="cv-medal cv-medal-{{ (int) $place }}" href="{{ $ev['results_url'] ?? '#' }}">
        <x-medal-icon :place="(int) $place" :size="44" />
        <div class="cv-medal-text">
            <b>{{ $medalName }}</b>
            @if(! empty($story['division']))<span>{{ $story['division'] }}</span>@endif
        </div>
        <i class="bi bi-chevron-right cv-go-light"></i>
    </a>
@endif

@if(! empty($ev['title']))
    <section class="cv-card">
        <a class="cv-event" href="{{ $ev['url'] ?? '#' }}">
            @if(! empty($ev['host_logo']))
                <span class="cv-logo"><img src="{{ $ev['host_logo'] }}" alt="" loading="lazy"></span>
            @endif
            <div class="cv-event-text">
                <b>{{ $ev['title'] }}</b>
                @if(! empty($ev['host']))<span>{{ $ev['host'] }}</span>@endif
            </div>
            <i class="bi bi-chevron-right cv-go"></i>
        </a>
        @if(! empty($ev['host_url']) && ! empty($ev['host']))
            <a class="cv-sublink" href="{{ $ev['host_url'] }}"><i class="bi bi-building"></i> {{ __('cert::messages.verify_host_page', ['host' => $ev['host']]) }}</a>
        @endif
        <dl class="cv-facts">
            @if(! empty($ev['dates']))<a href="{{ $ev['url'] ?? '#' }}"><dt><i class="bi bi-calendar3"></i></dt><dd>{{ $ev['dates'] }}</dd><i class="bi bi-chevron-right cv-go"></i></a>@endif
            @if(! empty($ev['venue']))<a href="{{ $ev['map_url'] ?? '#' }}" @if(! empty($ev['map_url'])) target="_blank" rel="noopener" @endif><dt><i class="bi bi-geo-alt"></i></dt><dd>{{ $ev['venue'] }}</dd><i class="bi bi-box-arrow-up-right cv-go"></i></a>@endif
            @if(! empty($story['division']))<a href="{{ $story['draw_url'] ?? ($ev['url'] ?? '#') }}"><dt><i class="bi bi-diagram-3 bracket-icon"></i></dt><dd>{{ $story['division'] }}</dd><i class="bi bi-chevron-right cv-go"></i></a>@endif
            @if(! empty($ev['entrants']))<a href="{{ $ev['entrants_url'] ?? '#' }}"><dt><i class="bi bi-people"></i></dt><dd>{{ trans_choice('cert::messages.verify_entrants', $ev['entrants'], ['count' => $ev['entrants']]) }}</dd><i class="bi bi-chevron-right cv-go"></i></a>@endif
            @if(! empty($story['role']))<a href="{{ $ev['url'] ?? '#' }}"><dt><i class="bi bi-person-badge"></i></dt><dd>{{ $story['role'] }}</dd><i class="bi bi-chevron-right cv-go"></i></a>@endif
        </dl>
    </section>
@endif

@php $team = $story['team'] ?? null; @endphp
@if($team && count($team['athletes']))
    {{-- The team: everyone the club brought (EventCertificates::teamOf). Weight is the event's staff's only. --}}
    <section class="cv-card" aria-labelledby="cv-team-h">
        <div class="cv-head">
            <h2 id="cv-team-h">{{ ! empty($story['team_managed']) ? __('cert::messages.verify_team_managed') : __('cert::messages.verify_team') }}</h2>
            <span class="cv-record">{{ trans_choice('cert::messages.verify_team_count', count($team['athletes']), ['count' => count($team['athletes'])]) }}</span>
        </div>
        @if($team['staff'])
            <p class="cv-team-staff"><i class="bi bi-lock-fill"></i> {{ __('cert::messages.verify_team_staff') }}</p>
        @endif
        @if(array_sum($team['medals']) > 0)
            <div class="cv-tally">
                @foreach([1, 2, 3] as $p)
                    <div class="cv-tally-{{ $p }}"><b>{{ $team['medals'][$p] }}</b><span>{{ __('cert::messages.medal_short.'.$p) }}</span></div>
                @endforeach
            </div>
        @endif
        <ul class="cv-team">
            @foreach($team['athletes'] as $a)
                <li class="cv-athlete {{ $a['place'] ? 'is-medal-'.$a['place'] : '' }}">
                    <span class="cv-athlete-medal">
                        @if($a['place'])<x-medal-icon :place="$a['place']" :size="26" />@else<span class="cv-nomedal" aria-hidden="true"></span>@endif
                    </span>
                    <a class="cv-face cv-athlete-face" href="{{ $a['url'] ?? '#' }}" aria-label="{{ $a['name'] }}">
                        @if($a['photo'])<img src="{{ $a['photo'] }}" alt="" loading="lazy">@else<x-gender-avatar :gender="$a['gender']" class="w-full h-full" />@endif
                    </a>
                    <div class="cv-athlete-text">
                        <a class="cv-link cv-athlete-name" href="{{ $a['url'] ?? '#' }}">
                            @if($a['flag'])<img class="cv-flag" src="{{ $a['flag'] }}" alt="{{ $a['country'] }}" title="{{ $a['country'] }}">@endif
                            <span>{{ $a['name'] }}</span>
                        </a>
                        <span class="cv-athlete-meta">
                            @if($a['age'])<span>{{ trans_choice('cert::messages.verify_age', $a['age'], ['age' => $a['age']]) }}</span>@endif
                            @if($a['weight'])<span class="cv-athlete-kg">{{ $a['weight'] }} kg @if($a['weighed'])<i class="bi bi-patch-check-fill cv-ok" title="{{ __('cert::messages.verify_weighed') }}"></i>@endif</span>@endif
                        </span>
                        @foreach($a['divisions'] as $d)
                            @if($d['url'])
                                <a class="cv-division" href="{{ $d['url'] }}"><i class="bi bi-diagram-3 bracket-icon"></i><span>{{ $d['name'] }}</span><i class="bi bi-chevron-right cv-go-light"></i></a>
                            @else
                                <span class="cv-division"><i class="bi bi-diagram-3 bracket-icon"></i><span>{{ $d['name'] }}</span></span>
                            @endif
                        @endforeach
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="cv-hint">{{ __('cert::messages.verify_team_hint') }}</p>
    </section>
@endif

@if(count($bouts))
    <section class="cv-card">
        <div class="cv-head">
            <h2>{{ __('cert::messages.verify_bouts') }}</h2>
            <span class="cv-record">{{ __('cert::messages.verify_record', ['won' => $wins, 'lost' => $losses]) }}</span>
        </div>
        <ol class="cv-bouts">
            @foreach($bouts as $b)
                @php $open = ! $b['bye'] && empty($b['decided']); @endphp
                <li class="cv-bout {{ $b['bye'] ? 'is-bye' : ($open ? 'is-open' : ($b['won'] ? 'is-won' : 'is-lost')) }}">
                    <a class="cv-result" href="{{ $b['bout_url'] ?? ($story['draw_url'] ?? '#') }}">{{ $b['bye'] ? __('cert::messages.verify_bye') : ($open ? __('cert::messages.verify_open') : ($b['won'] ? __('cert::messages.verify_won') : __('cert::messages.verify_lost'))) }}</a>
                    @if(! $b['bye'])
                        <a class="cv-face" href="{{ $b['opponent_url'] ?? ($b['bout_url'] ?? '#') }}">
                            @if($b['opponent_photo'])<img src="{{ $b['opponent_photo'] }}" alt="" loading="lazy">@else<x-gender-avatar gender="" class="w-full h-full" />@endif
                        </a>
                    @endif
                    <div class="cv-bout-text">
                        @if($b['bye'])
                            <a class="cv-link" href="{{ $story['draw_url'] ?? '#' }}"><b>{{ __('cert::messages.verify_bye_text') }}</b></a>
                        @else
                            <b>{{ __('cert::messages.verify_vs', ['name' => '']) }}<a class="cv-link" href="{{ $b['opponent_url'] ?? ($b['bout_url'] ?? '#') }}">{{ $b['opponent'] }}</a></b>
                        @endif
                        <span>
                            @if(! empty($b['opponent_club']['name']))
                                <a class="cv-link cv-clubline" href="{{ $b['opponent_club']['url'] ?? '#' }}">@if(! empty($b['opponent_club']['logo']))<img class="cv-crest" src="{{ $b['opponent_club']['logo'] }}" alt="" loading="lazy">@endif{{ $b['opponent_club']['name'] }}</a>
                            @endif
                            @if($b['round'])<a class="cv-link" href="{{ $story['draw_url'] ?? '#' }}">{{ ucfirst((string) $b['round']) }}</a>@endif
                        </span>
                    </div>
                    @if($b['score'])<a class="cv-score" href="{{ $b['bout_url'] ?? ($story['draw_url'] ?? '#') }}">{{ $b['score'] }}</a>@endif
                    @if($b['video_url'])
                        <a class="cv-play" href="{{ $b['video_url'] }}" title="{{ __('cert::messages.verify_watch') }}" aria-label="{{ __('cert::messages.verify_watch') }}"><i class="bi bi-play-fill"></i></a>
                    @endif
                </li>
            @endforeach
        </ol>
        @if(collect($bouts)->contains(fn ($b) => $b['video_url']))
            <p class="cv-hint"><i class="bi bi-lock"></i> {{ __('cert::messages.verify_watch_hint') }}</p>
        @endif
    </section>
@endif

<section class="cv-actions">
    @if(! empty($story['draw_url']))
        <a class="cv-btn is-primary" href="{{ $story['draw_url'] }}"><i class="bi bi-diagram-3 bracket-icon"></i> {{ __('cert::messages.verify_draw') }}</a>
    @endif
    @if(! empty($downloadUrl))
        <a class="cv-btn" href="{{ $downloadUrl }}"><i class="bi bi-download"></i> {{ __('cert::messages.verify_download') }}</a>
    @endif
    @if(! empty($profileUrl))
        <a class="cv-btn" href="{{ $profileUrl }}"><i class="bi bi-person-circle"></i> {{ __('cert::messages.verify_profile') }}</a>
    @endif
    <button type="button" class="cv-btn" id="cv-share" data-url="{{ $shareUrl }}" data-title="{{ $name }}" data-done="{{ __('cert::messages.verify_copied') }}">
        <i class="bi bi-share"></i> <span>{{ __('cert::messages.verify_share') }}</span>
    </button>
    <a class="cv-btn is-quiet" href="{{ $shareUrl }}?lang={{ ($lang ?? 'en') === 'ar' ? 'en' : 'ar' }}" hreflang="{{ ($lang ?? 'en') === 'ar' ? 'en' : 'ar' }}">
        <i class="bi bi-translate"></i> {{ ($lang ?? 'en') === 'ar' ? 'English' : 'العربية' }}
    </a>
</section>

<p class="cv-seal {{ $revoked ? 'is-revoked' : '' }}">
    <i class="bi {{ $revoked ? 'bi-x-octagon-fill' : 'bi-shield-check' }}"></i>
    {{ $revoked ? __('cert::messages.verify_seal_revoked', ['at' => $checkedAt]) : __('cert::messages.verify_seal', ['at' => $checkedAt]) }}
</p>

<script>
    // Copy the certificate number.
    document.querySelectorAll('.cv-copy').forEach((b) => b.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(b.dataset.copy); b.firstChild.textContent = b.dataset.done + ' '; } catch (err) { /* no clipboard */ }
    }));
    // A link with nowhere to go does nothing rather than jumping to the top.
    document.querySelectorAll('a[href="#"]').forEach((a) => a.addEventListener('click', (e) => e.preventDefault()));
    // Share: the phone's own share sheet where there is one, else copy the link.
    document.getElementById('cv-share')?.addEventListener('click', async (e) => {
        const b = e.currentTarget;
        const url = b.dataset.url;
        try {
            if (navigator.share) { await navigator.share({ title: b.dataset.title, url }); return; }
            await navigator.clipboard.writeText(url);
            b.querySelector('span').textContent = b.dataset.done;
        } catch (err) { /* cancelled */ }
    });
</script>
