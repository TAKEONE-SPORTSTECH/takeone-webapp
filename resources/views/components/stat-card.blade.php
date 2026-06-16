@php
    $sc    = $sizeClasses();
    $click = $clickHandler();

    // Server-side SVG sparkline (visual only — no interactivity in SVG)
    $sparkSvg = (function (array $data, string $color): string {
        $W = 200; $H = 36; $pad = 2;
        $data = array_values($data);
        $n    = count($data);

        if ($n < 2) {
            return '<svg width="100%" height="'.$H.'" viewBox="0 0 '.$W.' '.$H.'"'
                .' preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">'
                .'<line x1="0" y1="'.($H-2).'" x2="'.$W.'" y2="'.($H-2).'"'
                .' stroke="'.$color.'" stroke-width="1.5" opacity="0.3"/>'
                .'</svg>';
        }

        $min   = min($data);
        $max   = max($data);
        $range = ($max - $min) ?: 1;
        $pts   = [];

        foreach ($data as $i => $v) {
            $pts[] = [
                'x' => round($pad + ($i / ($n - 1)) * ($W - $pad * 2), 3),
                'y' => round(($H - $pad) - (($v - $min) / $range) * ($H - $pad * 2 - 4), 3),
            ];
        }

        // Smooth cubic bezier path
        $line = "M {$pts[0]['x']},{$pts[0]['y']}";
        for ($i = 1; $i < $n; $i++) {
            $cpx  = round($pts[$i-1]['x'] + ($pts[$i]['x'] - $pts[$i-1]['x']) * 0.5, 3);
            $line .= " C {$cpx},{$pts[$i-1]['y']} {$cpx},{$pts[$i]['y']} {$pts[$i]['x']},{$pts[$i]['y']}";
        }
        $area = $line . " L {$pts[$n-1]['x']},{$H} L {$pts[0]['x']},{$H} Z";
        $gid  = 'scg' . abs(crc32($color . $n . $min . $max));

        return '<svg width="100%" height="'.$H.'" viewBox="0 0 '.$W.' '.$H.'"'
            .' preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">'
            .'<defs><linearGradient id="'.$gid.'" x1="0" y1="0" x2="0" y2="1">'
            .'<stop offset="0%" stop-color="'.$color.'" stop-opacity="0.25"/>'
            .'<stop offset="100%" stop-color="'.$color.'" stop-opacity="0"/>'
            .'</linearGradient></defs>'
            .'<path d="'.$area.'" fill="url(#'.$gid.')"/>'
            .'<path d="'.$line.'" fill="none" stroke="'.$color.'"'
            .' stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>'
            // Pre-created indicator elements — hidden until JS activates them
            .'<line class="sc-vline" x1="0" y1="0" x2="0" y2="'.$H.'"'
            .' stroke="'.$color.'" stroke-width="1" stroke-dasharray="2,2" opacity="0"/>'
            .'<circle class="sc-dot" cx="0" cy="0" r="3"'
            .' fill="'.$color.'" stroke="white" stroke-width="1.5" opacity="0"/>'
            .'</svg>';
    })($sparkData, $sparkColor);
@endphp

{{-- ── Card ── --}}
<div
    id="{{ $cardId }}"
    class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden transition-shadow duration-150 flex flex-col
           {{ $isClickable() ? 'cursor-pointer hover:shadow-md' : '' }}"
    @if($isClickable())
        onclick="{{ $click }}"
        role="button"
        tabindex="0"
        onkeydown="if(event.key==='Enter'||event.key===' '){ {{ $click }} }"
    @endif
>
    {{-- Header --}}
    <div class="flex items-start justify-between {{ $sc['pad'] }}">
        <div class="min-w-0 flex-1 pr-3">
            <p class="{{ $sc['label'] }} font-bold uppercase tracking-[0.14em] text-gray-400"
               data-sc-label>{{ $label }}</p>

            <p class="{{ $sc['value'] }} font-bold text-slate-900 mt-2 tabular-nums leading-none truncate"
               data-sc-value>{{ $value }}</p>

            @if($trend)
            <span class="inline-flex items-center gap-0.5 mt-1.5 text-[11px] font-semibold
                         {{ $trendUp ? 'text-emerald-600' : 'text-red-500' }}"
                  data-sc-trend>
                <i class="bi {{ $trendUp ? 'bi-arrow-up-short' : 'bi-arrow-down-short' }} text-sm leading-none"></i>
                {{ $trend }}
            </span>
            @endif

            @if($subLabel)
            <p class="{{ $sc['sub'] }} text-gray-400 mt-1.5 truncate" data-sc-sub>{{ $subLabel }}</p>
            @endif
        </div>

        <div class="{{ $sc['icon'] }} rounded-full {{ $iconBg }} flex items-center justify-center flex-shrink-0">
            <i class="bi {{ $icon }} {{ $iconColor }}"></i>
        </div>
    </div>

    {{-- Sparkline --}}
    @if(count($sparkData) >= 2)
    <div class="relative mt-auto" style="height:40px;"
         data-sc-spark
         data-values="{{ json_encode(array_values($sparkData)) }}"
         data-labels="{{ json_encode(array_values($sparkLabels)) }}"
         data-color="{{ $sparkColor }}">
        {!! $sparkSvg !!}
        {{-- Transparent overlay that captures mouse events reliably --}}
        <div style="position:absolute;inset:0;cursor:crosshair;" data-sc-overlay></div>
    </div>
    @else
    <div class="mt-auto" style="height:12px;"></div>
    @endif
</div>

{{-- ══════════════════════════════════════════
     Shared JS — rendered once per page
══════════════════════════════════════════ --}}
@once
@push('scripts')
<script>
(function () {
    // ── Shared floating tooltip ──────────────────────────────────────────────
    var tip = document.createElement('div');
    tip.id  = 'sc-tooltip';
    Object.assign(tip.style, {
        position:      'fixed',
        pointerEvents: 'none',
        zIndex:        '99999',
        display:       'none',
        background:    '#1e293b',
        color:         '#f8fafc',
        fontSize:      '11px',
        fontWeight:    '600',
        fontFamily:    'inherit',
        padding:       '4px 9px',
        borderRadius:  '6px',
        whiteSpace:    'nowrap',
        boxShadow:     '0 4px 12px rgba(0,0,0,.2)',
        lineHeight:    '1.4',
    });
    document.body.appendChild(tip);

    // ── Point computation (mirrors PHP algorithm exactly) ───────────────────
    var W = 200, H = 36, PAD = 2;

    function computePts(values, labels) {
        var n   = values.length;
        var min = Math.min.apply(null, values);
        var max = Math.max.apply(null, values);
        var rng = (max - min) || 1;
        return values.map(function (v, i) {
            return {
                x:     PAD + (i / (n - 1)) * (W - PAD * 2),
                y:     (H - PAD) - ((v - min) / rng) * (H - PAD * 2 - 4),
                value: v,
                label: (labels && labels[i]) ? labels[i] : '',
            };
        });
    }

    // ── Wire interactivity on one spark wrapper ──────────────────────────────
    function initSpark(sparkDiv) {
        var values  = JSON.parse(sparkDiv.dataset.values  || '[]');
        var labels  = JSON.parse(sparkDiv.dataset.labels  || '[]');
        var color   = sparkDiv.dataset.color || '#8b5cf6';
        var overlay = sparkDiv.querySelector('[data-sc-overlay]');
        var svg     = sparkDiv.querySelector('svg');

        if (!overlay || !svg || values.length < 2) return;

        var dot   = svg.querySelector('.sc-dot');
        var vline = svg.querySelector('.sc-vline');
        var pts   = computePts(values, labels);

        overlay.addEventListener('mousemove', function (e) {
            var rect = sparkDiv.getBoundingClientRect();
            var mx   = ((e.clientX - rect.left) / rect.width) * W;

            // Snap to nearest point
            var near = pts[0];
            pts.forEach(function (p) {
                if (Math.abs(p.x - mx) < Math.abs(near.x - mx)) near = p;
            });

            // Move indicator elements inside the SVG
            if (dot) {
                dot.setAttribute('cx', near.x);
                dot.setAttribute('cy', near.y);
                dot.setAttribute('opacity', '1');
            }
            if (vline) {
                vline.setAttribute('x1', near.x);
                vline.setAttribute('x2', near.x);
                vline.setAttribute('opacity', '0.35');
            }

            // Position tooltip above the dot
            var dotScrX = rect.left + (near.x / W) * rect.width;
            var dotScrY = rect.top  + (near.y / H) * rect.height;

            tip.textContent = (near.label ? near.label + '   ' : '') + near.value.toFixed(2);
            tip.style.display = 'block';

            var tw = tip.offsetWidth;
            var th = tip.offsetHeight;
            var topY = dotScrY - th - 8;
            tip.style.top  = (topY > 8 ? topY : dotScrY + 10) + 'px';
            tip.style.left = Math.min(
                Math.max(dotScrX - tw / 2, 6),
                window.innerWidth - tw - 6
            ) + 'px';
        });

        overlay.addEventListener('mouseleave', function () {
            if (dot)   dot.setAttribute('opacity', '0');
            if (vline) vline.setAttribute('opacity', '0');
            tip.style.display = 'none';
        });
    }

    // ── Init all cards on page ready ─────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-sc-spark]').forEach(initSpark);
    });

    // ── Public API ───────────────────────────────────────────────────────────
    window.StatCard = {

        /**
         * Re-render sparkline and re-wire interactivity.
         * Called automatically by update() when sparkData changes.
         */
        redrawSpark: function (sparkDiv, values, labels, color) {
            if (!sparkDiv || !values || values.length < 2) return;

            var c   = color || sparkDiv.dataset.color || '#8b5cf6';
            var n   = values.length;
            var min = Math.min.apply(null, values);
            var max = Math.max.apply(null, values);
            var rng = (max - min) || 1;
            var pts = computePts(values, labels || []);

            // Build SVG paths
            var line = 'M ' + pts[0].x + ',' + pts[0].y;
            for (var i = 1; i < n; i++) {
                var cpx = pts[i-1].x + (pts[i].x - pts[i-1].x) * 0.5;
                line += ' C ' + cpx + ',' + pts[i-1].y + ' ' + cpx + ',' + pts[i].y + ' ' + pts[i].x + ',' + pts[i].y;
            }
            var area = line + ' L ' + pts[n-1].x + ',' + H + ' L ' + pts[0].x + ',' + H + ' Z';
            var gid  = 'scg_live_' + Math.random().toString(36).slice(2, 8);

            sparkDiv.dataset.values = JSON.stringify(values);
            sparkDiv.dataset.labels = JSON.stringify(labels || []);
            sparkDiv.dataset.color  = c;

            var svgEl = sparkDiv.querySelector('svg');
            if (svgEl) {
                svgEl.innerHTML =
                    '<defs><linearGradient id="' + gid + '" x1="0" y1="0" x2="0" y2="1">' +
                    '<stop offset="0%" stop-color="' + c + '" stop-opacity="0.25"/>' +
                    '<stop offset="100%" stop-color="' + c + '" stop-opacity="0"/>' +
                    '</linearGradient></defs>' +
                    '<path d="' + area + '" fill="url(#' + gid + ')"/>' +
                    '<path d="' + line + '" fill="none" stroke="' + c + '" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>' +
                    '<line class="sc-vline" x1="0" y1="0" x2="0" y2="' + H + '" stroke="' + c + '" stroke-width="1" stroke-dasharray="2,2" opacity="0"/>' +
                    '<circle class="sc-dot" cx="0" cy="0" r="3" fill="' + c + '" stroke="white" stroke-width="1.5" opacity="0"/>';
            }

            // Re-wire hover
            var overlay = sparkDiv.querySelector('[data-sc-overlay]');
            if (overlay) {
                var newOverlay = overlay.cloneNode(false);
                overlay.parentNode.replaceChild(newOverlay, overlay);
            }
            initSpark(sparkDiv);
        },

        /**
         * Live-update a stat card in place.
         * detail: { value, subLabel, label, trend, trendUp, sparkData, sparkLabels }
         */
        update: function (cardId, detail) {
            var card = document.getElementById(cardId);
            if (!card) return;

            if (detail.value    != null) { var el = card.querySelector('[data-sc-value]'); if (el) el.textContent = detail.value; }
            if (detail.subLabel != null) { var el = card.querySelector('[data-sc-sub]');   if (el) el.textContent = detail.subLabel; }
            if (detail.label    != null) { var el = card.querySelector('[data-sc-label]'); if (el) el.textContent = detail.label; }

            if (detail.trend != null) {
                var t = card.querySelector('[data-sc-trend]');
                if (t) {
                    var up = detail.trendUp !== false;
                    t.className = t.className.replace(/text-(emerald|red)-\d+/g, '') + ' ' + (up ? 'text-emerald-600' : 'text-red-500');
                    var ico = t.querySelector('i');
                    if (ico) ico.className = 'bi text-sm leading-none ' + (up ? 'bi-arrow-up-short' : 'bi-arrow-down-short');
                    var textNodes = Array.from(t.childNodes).filter(function (n) { return n.nodeType === 3; });
                    if (textNodes.length) textNodes[textNodes.length - 1].textContent = ' ' + detail.trend;
                }
            }

            if (detail.sparkData != null) {
                var spark = card.querySelector('[data-sc-spark]');
                if (spark) window.StatCard.redrawSpark(spark, detail.sparkData, detail.sparkLabels || [], spark.dataset.color);
            }
        },
    };
})();
</script>
@endpush
@endonce

{{-- ── Per-instance live-update listener ── --}}
@if($refreshEvent)
@push('scripts')
<script>
(function () {
    var cardId = {{ json_encode($cardId) }};
    window.addEventListener({{ json_encode($refreshEvent) }}, function (e) {
        window.StatCard.update(cardId, e.detail || {});
    });
})();
</script>
@endpush
@endif
