{{-- An organisation's tile: its logo bare (Design Rule #5) or its initials. Props: $o, $s (px) --}}
<span class="t1-stile {{ $o['logo'] ? 'has-logo' : '' }}" style="--c:{{ $o['color'] }};width:{{ $s }}px;height:{{ $s }}px;font-size:{{ round($s / 2.7) }}px">
    @if($o['logo'])<img src="{{ $o['logo'] }}" alt="">@else{{ $o['initials'] }}@endif
</span>
