{{-- A sparkline from $values, in $color. Drawn to scale: min→bottom, max→top. --}}
@php
    $vals = array_values(array_map('floatval', $values ?: [0, 0]));
    if (count($vals) < 2) { $vals = [$vals[0] ?? 0, $vals[0] ?? 0]; }
    $mx = max($vals); $mn = min($vals); $n = count($vals) - 1;
    $pts = collect($vals)->map(fn ($v, $i) => round($i / $n * 100, 1).','.round(28 - (($v - $mn) / (($mx - $mn) ?: 1)) * 24 - 2, 1))->implode(' ');
    $last = explode(',', collect(explode(' ', $pts))->last());
@endphp
<svg class="t1-spk" viewBox="0 0 100 28" preserveAspectRatio="none" aria-hidden="true">
    <polyline points="{{ $pts }}" fill="none" stroke="{{ $color }}" stroke-width="2" vector-effect="non-scaling-stroke"/>
    <circle cx="100" cy="{{ $last[1] ?? 14 }}" r="2.5" fill="{{ $color }}"/>
</svg>
