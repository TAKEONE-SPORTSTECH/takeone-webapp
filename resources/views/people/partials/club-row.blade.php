@php
    $clubUrl = ($a->tenant && $a->tenant->slug && $a->tenant->country)
        ? route('clubs.show', ['country' => strtolower($a->tenant->country), 'slug' => $a->tenant->slug])
        : null;
    $tag = $clubUrl ? 'a' : 'div';
@endphp
<{{ $tag }} @if($clubUrl) href="{{ $clubUrl }}" @endif
    class="group relative flex items-center gap-3 rounded-xl border border-gray-100 p-2.5 mb-2 last:mb-0 {{ $clubUrl ? 'm-press' : '' }}">
    <span class="w-11 h-11 rounded-xl bg-muted grid place-items-center overflow-hidden flex-shrink-0 ring-1 ring-gray-100 {{ $active ? '' : 'grayscale' }}">
        @if($a->logo)<img src="{{ asset('storage/'.$a->logo) }}" alt="" class="w-11 h-11 object-cover">@else<i class="bi bi-buildings text-muted-foreground"></i>@endif
    </span>
    <div class="min-w-0 flex-1">
        <p class="font-semibold text-foreground {{ $active ? '' : 'text-foreground/80' }} text-sm leading-snug truncate">{{ $a->club_name }}</p>
        <p class="text-[11px] text-muted-foreground mt-0.5">
            @if($active)
                {{ __('member.since') }} {{ optional($a->start_date)->format('M Y') ?: '—' }}
            @else
                {{ optional($a->start_date)->format('M Y') ?: '—' }} – {{ optional($a->end_date)->format('M Y') }}
            @endif
        </p>
    </div>
    @if($active)
        <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>{{ __('member.active') }}</span>
    @else
        <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-500">{{ __('member.left') }}</span>
    @endif
</{{ $tag }}>
