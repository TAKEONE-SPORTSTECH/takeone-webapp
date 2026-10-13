{{--
    The event's numbers — female · male · divisions · bouts — as one slim card
    under the quick facts. Counts only (App\Events\Support\EventStats), so it is
    as public as "how full is it".

    Expects `$e` (the page payload, reading `$e['stats']`) and `$ev` (the
    guarded event colour). Draws nothing when there is nothing to count, and
    leaves the bouts tile out when the draw is withheld or not built yet.

    Inline styles on purpose, like quick-facts beside it: the Tailwind bundle is
    prebuilt and this card's sizes are not in it.
--}}
@php
    $st = $e['stats'] ?? null;
    $tiles = [];

    if ($st && ($st['female'] + $st['male'] + $st['divisions']) > 0) {
        $tiles[] = ['n' => $st['female'], 'label' => __('events::events.stats_female'), 'icon' => 'bi bi-gender-female', 'ink' => '#db2777', 'tint' => '#fdf2f8'];
        $tiles[] = ['n' => $st['male'], 'label' => __('events::events.stats_male'), 'icon' => 'bi bi-gender-male', 'ink' => '#2563eb', 'tint' => '#eff6ff'];
        $tiles[] = ['n' => $st['divisions'], 'label' => __('events::events.stats_divisions'), 'icon' => \App\Support\Icon::bi('bi-diagram-3-fill'), 'ink' => $ev, 'tint' => \App\Support\Palette::alpha($ev, .09)];

        if ($st['bouts'] !== null) {
            $tiles[] = ['n' => $st['bouts'], 'label' => __('events::events.stats_bouts'), 'icon' => 'bi bi-lightning-charge-fill', 'ink' => $ev, 'tint' => \App\Support\Palette::alpha($ev, .09)];
        }
    }
@endphp

@if($tiles)
    <div class="relative" style="padding:0 18px; margin-top:12px;">
        <div class="ev-stats" style="background:#fff; border-radius:18px;
                    box-shadow:0 14px 40px rgba(30,44,79,.09), 0 2px 6px rgba(30,44,79,.05);
                    padding:14px 8px; display:grid; grid-template-columns:repeat({{ count($tiles) }}, minmax(0, 1fr));">
            @foreach($tiles as $i => $t)
                <div class="ev-stat text-center min-w-0"
                     style="padding:0 4px; {{ $i > 0 ? 'border-inline-start:1px solid #eef1f6;' : '' }} animation-delay:{{ $i * 60 }}ms;"
                     @isset($t['hint']) title="{{ $t['hint'] }}" @endisset>
                    <span style="width:30px; height:30px; border-radius:10px; display:inline-grid; place-items:center;
                                 background:{{ $t['tint'] }}; color:{{ $t['ink'] }}; font-size:14px;">
                        <i class="{{ $t['icon'] }}"></i>
                    </span>
                    <p style="margin:6px 0 0; font-size:20px; font-weight:900; line-height:1; color:#1e2c4f; font-variant-numeric:tabular-nums;">{{ number_format($t['n']) }}</p>
                    <p class="truncate" style="margin:4px 0 0; font-size:10.5px; font-weight:600; color:#6b7689; letter-spacing:.02em;">{{ $t['label'] }}</p>
                    @isset($t['hint'])
                        <p class="truncate" style="margin:1px 0 0; font-size:9px; color:#9aa3b2;">{{ $t['hint'] }}</p>
                    @endisset
                </div>
            @endforeach
        </div>
    </div>

    @once
        <style>
            .ev-stat { animation: ev-stat-in .45s cubic-bezier(.2,.8,.2,1) both; }
            @keyframes ev-stat-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
            @media (prefers-reduced-motion: reduce) { .ev-stat { animation: none; } }
        </style>
    @endonce
@endif
