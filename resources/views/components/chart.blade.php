@props([
    'id'          => null,            // canvas id (auto-generated if omitted)
    'type'        => 'line',          // 'line' | 'bar'
    'labels'      => [],              // x-axis labels
    'datasets'    => [],              // [{ label, data:[], color:'#hex', fill:bool, dashed:bool, hidden:bool, borderWidth:int }]
    'height'      => 320,             // chart height in px (parent is fixed-height to avoid infinite growth)
    'valuePrefix' => '',             // e.g. 'BHD '
    'valueSuffix' => '',
    'legend'      => true,            // show the interactive HTML toggle legend
    'clickEvent'  => null,            // window event name dispatched on point click → detail { id, index, label }
    'card'        => true,            // wrap in the styled card shell
    'title'       => null,
    'subtitle'    => null,
    'icon'        => 'bi-graph-up-arrow',
    'badge'       => null,            // small pill in the header (e.g. '12M')
    'hint'        => null,            // helper line under the chart
    'containerClass' => '',
])

@php
    $chartId   = $id ?: 'chart_' . uniqid();
    $legendId  = $chartId . '_legend';
    $config = [
        'type'        => $type,
        'labels'      => $labels,
        'datasets'    => $datasets,
        'valuePrefix' => $valuePrefix,
        'valueSuffix' => $valueSuffix,
        'legendId'    => $legend ? $legendId : null,
        'clickEvent'  => $clickEvent,
    ];
@endphp

@if($card)
<div class="relative overflow-hidden rounded-2xl bg-white border border-gray-100 shadow-sm">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-28" style="background-image: linear-gradient(180deg, hsl(250 65% 97%), transparent);"></div>
    <div class="relative p-5 sm:p-6">
        @if($title || $badge)
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-accent text-primary flex items-center justify-center shrink-0">
                    <i class="bi {{ $icon }} text-lg"></i>
                </span>
                <div>
                    @if($title)<h5 class="font-bold text-gray-900 leading-tight">{{ $title }}</h5>@endif
                    @if($subtitle)<p class="text-xs text-muted-foreground">{{ $subtitle }}</p>@endif
                </div>
            </div>
            @if($badge)
            <span class="self-start sm:self-auto inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-muted/70 text-xs font-semibold text-muted-foreground">
                <i class="bi bi-calendar3"></i> {{ $badge }}
            </span>
            @endif
        </div>
        @endif

        @if($legend)
        <div id="{{ $legendId }}" class="flex flex-wrap items-center gap-2 mb-4"></div>
        @endif

        <div class="relative w-full {{ $containerClass }}" style="height: {{ (int) $height }}px;">
            <canvas id="{{ $chartId }}"></canvas>
        </div>

        @if($hint)
        <p class="text-[11px] text-muted-foreground mt-3 flex items-center gap-1.5">
            <i class="bi bi-info-circle"></i> {{ $hint }}
        </p>
        @endif
    </div>
</div>
@else
    @if($legend)
    <div id="{{ $legendId }}" class="flex flex-wrap items-center gap-2 mb-4"></div>
    @endif
    <div class="relative w-full {{ $containerClass }}" style="height: {{ (int) $height }}px;">
        <canvas id="{{ $chartId }}"></canvas>
    </div>
    @if($hint)
    <p class="text-[11px] text-muted-foreground mt-3 flex items-center gap-1.5">
        <i class="bi bi-info-circle"></i> {{ $hint }}
    </p>
    @endif
@endif

{{-- Shared renderer: loaded once per page. Chart.js itself is bundled via Vite
     (resources/js/app.js exposes window.Chart) — no external CDN needed. --}}
@once
@push('scripts')
<script>
window.TakeoneChart = {
    _queue: [],
    render(cfg) {
        // Defer until Chart.js + DOM are ready.
        if (typeof Chart === 'undefined' || document.readyState === 'loading') {
            this._queue.push(cfg);
            if (!this._wired) {
                this._wired = true;
                const flush = () => { const q = this._queue; this._queue = []; q.forEach(c => this.render(c)); };
                document.addEventListener('DOMContentLoaded', flush);
                window.addEventListener('load', flush);
            }
            return;
        }

        const el = document.getElementById(cfg.canvasId);
        if (!el || el._takeoneChart) return;          // missing or already rendered
        const ctx = el.getContext('2d');

        const area = (hex) => {
            const g = ctx.createLinearGradient(0, 0, 0, 340);
            g.addColorStop(0, hex + '40');
            g.addColorStop(0.85, hex + '08');
            g.addColorStop(1, hex + '00');
            return g;
        };

        const fmt = (v) => cfg.valuePrefix + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + cfg.valueSuffix;

        const datasets = (cfg.datasets || []).map(d => {
            const color = d.color || '#8b5cf6';
            return {
                label: d.label || '',
                data: d.data || [],
                borderColor: color,
                backgroundColor: d.fill ? area(color) : (cfg.type === 'bar' ? color : 'transparent'),
                borderWidth: d.borderWidth || (cfg.type === 'bar' ? 0 : 2.5),
                borderDash: d.dashed ? [4, 4] : [],
                hidden: !!d.hidden,
                fill: !!d.fill,
                tension: 0.4,
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBorderWidth: 2,
                pointHoverBorderColor: '#fff',
                pointHoverBackgroundColor: color,
                borderRadius: cfg.type === 'bar' ? 6 : undefined,
                maxBarThickness: cfg.type === 'bar' ? 40 : undefined,
            };
        });

        const chart = new Chart(el, {
            type: cfg.type || 'line',
            data: { labels: cfg.labels || [], datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                onHover: (e, els) => { e.native.target.style.cursor = els.length ? 'pointer' : 'default'; },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(17,24,39,0.92)', padding: 12, cornerRadius: 12, boxPadding: 6,
                        usePointStyle: true, titleFont: { weight: '600', size: 13 }, bodyFont: { size: 12 },
                        callbacks: { label: (c) => '  ' + c.dataset.label + ': ' + fmt(c.parsed.y) }
                    }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { color: '#9ca3af', font: { size: 11 } } },
                    y: {
                        beginAtZero: true, border: { display: false },
                        grid: { color: 'rgba(0,0,0,0.05)', drawTicks: false },
                        ticks: { color: '#9ca3af', font: { size: 11 }, padding: 8, maxTicksLimit: 6,
                                 callback: (v) => cfg.valuePrefix + v.toLocaleString() + cfg.valueSuffix }
                    }
                },
                onClick: (event, elements, c) => {
                    if (!cfg.clickEvent) return;
                    const pts = c.getElementsAtEventForMode(event.native, 'index', { intersect: false }, true);
                    if (!pts.length) return;
                    const index = pts[0].index;
                    window.dispatchEvent(new CustomEvent(cfg.clickEvent, {
                        detail: { id: cfg.canvasId, index, label: (cfg.labels || [])[index] }
                    }));
                }
            }
        });
        el._takeoneChart = chart;

        // Interactive HTML legend.
        const legendEl = cfg.legendId && document.getElementById(cfg.legendId);
        if (legendEl) {
            legendEl.innerHTML = '';
            chart.data.datasets.forEach((ds, i) => {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all select-none';
                const paint = () => {
                    const on = chart.isDatasetVisible(i);
                    chip.style.borderColor = on ? ds.borderColor : '#e5e7eb';
                    chip.style.color = on ? '#374151' : '#9ca3af';
                    chip.style.background = on ? (ds.borderColor + '14') : 'transparent';
                    chip.classList.toggle('line-through', !on);
                };
                chip.innerHTML = '<span class="w-2.5 h-2.5 rounded-full" style="background:' + ds.borderColor + '"></span><span>' + ds.label + '</span>';
                chip.addEventListener('click', () => { chart.setDatasetVisibility(i, !chart.isDatasetVisible(i)); chart.update(); paint(); });
                paint();
                legendEl.appendChild(chip);
            });
        }
        return chart;
    }
};
</script>
@endpush
@endonce

@push('scripts')
<script>
    window.TakeoneChart.render(Object.assign(@json($config), { canvasId: @json($chartId) }));
</script>
@endpush
