{{--
    One programme on the club front page — the same card on desktop and mobile.
    Needs $p (a row of $cpPackages from App\Clubs\Support\ClubFrontPage), $cpCurrency, $cpMoney,
    $club, and the page's x-data="clubPage" scope (select(), shows()).
    Each activity opens its public directory page when the catalog has one; the
    coach opens their trainer page.
--}}
<article class="cp-card cp-lift" style="padding:22px;display:flex;flex-direction:column;{{ $p['applicable'] ? '' : 'opacity:.55;' }}"
         x-show="shows(@js($p['cats']), {{ $p['applicable'] ? 'true' : 'false' }})" x-transition.opacity>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
        <span class="cp-tag cp-red">{{ $p['gender'] ?: __('clubs::page.training_program') }}</span>
        <span class="cp-muted" style="font-size:12px;white-space:nowrap;">{{ $p['ages'] }}</span>
    </div>
    <h3 style="font-size:24px;font-weight:700;margin-top:16px;">{{ $p['name'] }}</h3>
    @if($p['about'])<p class="cp-muted" style="font-size:14px;margin-top:4px;">{{ $p['about'] }}</p>@endif
    @if(count($p['activities']))
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:12px;">
            @foreach($p['activities'] as $act)
                @if($act['url'])
                    <a href="{{ $act['url'] }}" class="cp-badge" style="background:var(--cp-accent-soft);color:var(--cp-accent);">{{ $act['name'] }} <i class="bi bi-arrow-up-right cp-flip" style="font-size:9px;"></i></a>
                @else
                    <span class="cp-badge" style="background:var(--cp-paper);border:1px solid var(--cp-line);">{{ $act['name'] }}</span>
                @endif
            @endforeach
        </div>
    @endif
    @if($p['days'] || $p['coach'])
        <p style="font-size:14px;margin-top:14px;">
            {{ $p['days'] }}@if($p['days'] && $p['coach']) · @endif
            @if($p['coach'])<a href="{{ $p['coach']['url'] }}" style="font-weight:700;text-decoration:underline;text-underline-offset:3px;">{{ __('clubs::page.coach_named', ['name' => $p['coach']['name']]) }}</a>@endif
        </p>
    @endif
    <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:12px;margin-top:auto;padding-top:24px;">
        <div>
            @if($p['price'] !== null)<b class="cp-display" style="font-size:30px;">{{ $p['price'] }}</b><small class="cp-muted"> {{ $cpCurrency }} {{ $p['per'] }}</small>@endif
        </div>
        @if($p['applicable'])
            <button type="button" class="cp-cta" @click="select({{ $p['id'] }})">{{ __('clubs::page.select') }} <i class="bi bi-arrow-up-right cp-flip"></i></button>
        @else
            <span class="cp-pill" style="opacity:.8;"><i class="bi bi-lock"></i>{{ __('clubs::page.not_eligible') }}</span>
        @endif
    </div>
    @if($p['applicable'] && ($club->enrollment_fee ?? 0) > 0)
        <p class="cp-muted" style="font-size:11px;margin-top:8px;">{{ __('clubs::page.enrolment_fee', ['fee' => $cpMoney($club->enrollment_fee), 'currency' => $cpCurrency]) }}</p>
    @endif
</article>
