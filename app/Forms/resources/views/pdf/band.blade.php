<header class="band"><span class="c1"></span><span class="c2"></span>
    <div class="bandrow"><div class="num">{{ $num }}</div>
        <div class="t"><h2>{{ $p['title'] ?? '' }}</h2>@if(! empty($p['intro']))<p>{{ $p['intro'] }}</p>@endif</div>
        <div class="t ar" dir="rtl"><h2>{{ $p['title_ar'] ?? '' }}</h2>@if(! empty($p['intro_ar']))<p>{{ $p['intro_ar'] }}</p>@endif</div>
    </div></header>
