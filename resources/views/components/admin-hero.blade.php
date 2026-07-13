@props([
    'eyebrow'    => null,
    'title',
    'subtitle'   => null,
    'icon'       => 'bi-grid-1x2-fill',
    'count'      => null,
    'countLabel' => null,
])

{{-- Platform-admin page hero band — matches the dashboard control-center style.
     Optional `count` chip on the right and an `actions` slot for buttons/badges. --}}
<div class="relative overflow-hidden rounded-2xl text-white shadow-sm mb-5"
     style="background: linear-gradient(135deg, hsl(250 65% 66%) 0%, hsl(262 60% 58%) 60%, hsl(275 52% 52%) 100%);">
    <div class="pointer-events-none absolute -top-12 -right-8 w-56 h-56 rounded-full" style="background:radial-gradient(circle, rgba(255,255,255,.16), transparent 70%);"></div>

    <div class="relative flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 p-5 lg:p-6">
        <div class="min-w-0">
            @if($eyebrow)
                <span class="inline-flex items-center gap-2 text-[11px] font-bold tracking-[0.18em] uppercase text-white/80">
                    <i class="bi {{ $icon }}"></i>{{ $eyebrow }}
                </span>
            @endif
            <h1 class="mt-1 text-2xl lg:text-3xl font-extrabold leading-tight">{{ $title }}</h1>
            @if($subtitle)
                <p class="mt-1 text-sm text-white/85">{{ $subtitle }}</p>
            @endif
        </div>

        @if($count !== null || isset($actions))
            <div class="flex items-center gap-3 shrink-0">
                @if($count !== null)
                    <div class="text-center rounded-xl bg-white/10 border border-white/15 px-5 py-2.5">
                        <div class="text-2xl font-extrabold leading-none">{{ $count }}</div>
                        @if($countLabel)
                            <div class="mt-1 text-[10px] font-semibold uppercase tracking-wide text-white/80">{{ $countLabel }}</div>
                        @endif
                    </div>
                @endif
                {{ $actions ?? '' }}
            </div>
        @endif
    </div>
</div>
