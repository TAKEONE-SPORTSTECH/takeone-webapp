@props(['user'])

{{--
    A member's belts — one card per ranked activity they train in, with the
    belt they hold and how close they are to the next (App\Ranks). Belongs to
    the ACTIVITY, not the club: a member who trains only in sports without
    belts sees nothing here, and nothing renders at all while ranks are off.

    Standalone: server-rendered, no script. The progress ring is an SVG circle.
--}}
@php
    $rankRows = config('ranks.enabled') && $user instanceof \App\Members\Models\User
        ? app(\App\Ranks\Support\MemberRanks::class)->forUser($user)
        : [];
    $statusLabel = fn ($s) => __('ranks::messages.status_'.$s);
@endphp

@if ($rankRows !== [])
<section {{ $attributes->merge(['class' => 'bg-white rounded-xl shadow-sm border border-gray-100 p-4']) }}>
    <div class="flex items-center gap-2 mb-3">
        <span class="w-8 h-8 rounded-lg bg-accent text-primary grid place-items-center"><i class="bi bi-award-fill"></i></span>
        <h3 class="text-sm font-bold text-gray-900">{{ __('ranks::messages.my_belts') }}</h3>
    </div>
    <div class="space-y-3">
        @foreach ($rankRows as $r)
            @php
                $pct = 0;
                if (! empty($r['progress'])) {
                    $ratios = array_map(fn ($p) => $p[1] ? min(1, ($p[0] ?? 0) / $p[1]) : 1, $r['progress']);
                    $pct = (int) round(min($ratios) * 100);
                } elseif (($r['status'] ?? null) === 'eligible') {
                    $pct = 100;
                }
                $ring = ['eligible' => '#10b981', 'nearly' => '#f59e0b'][$r['status'] ?? ''] ?? 'hsl(250 65% 65%)';
                $c = 2 * M_PI * 20;
            @endphp
            <div class="flex items-center gap-3">
                <span class="relative w-14 h-14 flex-shrink-0">
                    <svg viewBox="0 0 48 48" class="w-14 h-14 -rotate-90" aria-hidden="true">
                        <circle cx="24" cy="24" r="20" fill="none" stroke="#eef0f4" stroke-width="4"></circle>
                        @if ($r['next'] && ! empty($r['progress']))
                            <circle cx="24" cy="24" r="20" fill="none" stroke="{{ $ring }}" stroke-width="4" stroke-linecap="round"
                                    stroke-dasharray="{{ round($c, 2) }}" stroke-dashoffset="{{ round($c * (1 - $pct / 100), 2) }}"></circle>
                        @endif
                    </svg>
                    <span class="absolute inset-0 grid place-items-center">
                        @if ($r['rank'])
                            <x-belt-icon :belt="$r['rank']['belt']" :grade="$r['rank']['grade']" class="w-7 h-7" :title="false" />
                        @else
                            <i class="bi bi-dash-lg text-muted-foreground"></i>
                        @endif
                    </span>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-gray-900">{{ $r['rank']['label'] ?? __('ranks::messages.unranked') }}</p>
                    <p class="text-xs text-muted-foreground">{{ $r['activity'] }}@if ($r['club']) · {{ $r['club'] }}@endif</p>
                    @if ($r['next'])
                        <p class="text-[11px] mt-0.5 {{ ($r['status'] ?? '') === 'eligible' ? 'text-emerald-600 font-semibold' : 'text-muted-foreground' }}">
                            {{ __('ranks::messages.next') }}: {{ $r['next']['label'] }} ·
                            @if (($r['status'] ?? '') === 'eligible' || empty($r['progress']))
                                {{ $statusLabel($r['status']) }}
                            @else
                                {{ collect($r['progress'])->map(fn ($p, $k) => ($p[0] ?? '–').'/'.$p[1].' '.__('ranks::messages.'.($k === 'age' ? 'years' : $k)))->implode(' · ') }}
                            @endif
                        </p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif
