{{-- New UI desktop top bar (replaces <nav class="to-bar"> while
     App\Ui\NewUi::active()). Logo · Explore + the five personal tabs ·
     chats · notifications · Play/Manage. --}}
@php
    $tu = Auth::user();
    $tabs = [
        ['clubs.explore', ['clubs.explore', 'explore.*', 'activity.*'], 'bi-compass',       __('ui::messages.explore'), null],
        ['me.home',       ['me.home', 'me.feed*'],                     'bi-newspaper',      __('nav.tab_feed'),        'feed'],
        ['me.schedule',   ['me.schedule*'],                            'bi-calendar-week',  __('nav.tab_schedule'),    null],
        ['me.challenge',  ['me.challenge*'],                           'bi-trophy',         __('nav.tab_challenge'),   'challenge'],
        ['me.events',     ['me.events*'],                              'bi-calendar-heart', __('nav.tab_events'),      'events'],
        ['me.market',     ['me.market*'],                              'bi-shop',           __('nav.tab_market'),      'market'],
    ];
    if ($tu && $tu->isExploreLocked()) { array_shift($tabs); }
    $dots = $tu ? app(\App\Members\Support\SectionActivity::class)->navDots($tu) : [];
    $chatUnread = $tu ? \Illuminate\Support\Facades\DB::table('messages as m')
        ->join('conversation_user as cu', 'cu.conversation_id', '=', 'm.conversation_id')
        ->where('cu.user_id', $tu->id)->where('m.sender_id', '!=', $tu->id)
        ->whereRaw('m.created_at > COALESCE(cu.last_read_at, ?)', ['1970-01-01 00:00:00'])->count() : 0;
@endphp
<header class="t1 t1-top">
    <a class="t1-logo" href="{{ route('clubs.explore') }}" aria-label="{{ __('nav.layouts_app_brand_home_aria') }}">
        <img src="{{ asset('images/fullLogo.png') }}" alt="TAKEONE" draggable="false">
    </a>
    @auth
    @php $inManage = request()->routeIs('me.manage'); @endphp
    <nav class="t1-nav" @if($inManage) style="visibility:hidden" @endif aria-label="{{ __('ui::messages.explore') }}">
        @foreach($tabs as [$r, $match, $icon, $label, $dotKey])
            @php $on = request()->routeIs(...$match); @endphp
            <a href="{{ route($r) }}" class="{{ $on ? 'on' : '' }} {{ $r === 'me.challenge' ? 'hot' : '' }}" @if($on) aria-current="page" @endif title="{{ $label }}">
                <i class="bi {{ $icon }}"></i><span class="t1-lbl">{{ $label }}</span>
                @if($dotKey && ! $on && ($dots[$dotKey] ?? false))<span class="t1-dot"></span>@endif
            </a>
        @endforeach
    </nav>
    <div class="t1-right">
        <a href="{{ route('me.ask') }}" class="t1-hbtn t1-ai {{ request()->routeIs('me.ask') ? 'on' : '' }}" title="{{ __('ui::ask.title') }}" aria-label="{{ __('ui::ask.title') }}">@include('ui::shell.robot', ['uid' => 'd'])</a>
        <a href="{{ route('messages.index') }}" class="t1-hbtn chat-link" title="{{ __('nav.layouts_app_messages') }}" aria-label="{{ __('nav.layouts_app_messages') }}">
            <i class="bi bi-chat-dots"></i>
            @if($chatUnread > 0)<span class="notification-badge chat-badge">{{ $chatUnread > 99 ? '99+' : $chatUnread }}</span>@endif
        </a>
        <div class="t1-bell">@include('partials.notification-bell')</div>
        <div style="position:relative" x-data="{ acct:false }">
            @include('ui::shell.mode-switch', ['onFace' => 'acct = !acct', 'manage' => $inManage])
            @include('ui::shell.account-menu')
        </div>
    </div>
    @else
    <div class="t1-right">
        <a class="t1-hbtn" style="width:auto;padding:0 14px;font-weight:800;font-size:13px" href="{{ route('login') }}">{{ __('nav.login') }}</a>
    </div>
    @endauth
</header>
