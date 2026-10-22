{{-- The report sheet's navy band: event name, the page's title, and optional figures. --}}
<div class="band">
    <span class="stripes"></span>
    @if($hostLogo)<span class="hlogo" role="img" aria-label="{{ $host }}"></span>@endif
    <div class="who">
        <div class="eye">{{ $eyebrow ?? $title }}</div>
        <h1>{{ $heading }}</h1>
        @if($date || $host)
            <div class="sub">{{ collect([$date, $host])->filter()->implode(' · ') }}</div>
        @endif
    </div>
    @if(! empty($figures))
        <div class="totals">
            @foreach($figures as $f)
                <div><b>{{ $f[0] }}</b><span>{{ $f[1] }}</span></div>
            @endforeach
        </div>
    @endif
    <span class="slab"></span>
</div>
