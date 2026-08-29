{{--
    Club row for the public athlete profile (mobile).

    Kept separate from people/partials/club-row, which the desktop profile still
    renders: this one belongs to the mobile art direction and must be free to
    change without touching what desktop draws today.

    A club only becomes a link when it has a real public page — a manually typed
    affiliation with no tenant behind it stays a plain row rather than a dead one.
--}}
@once
<style>
    /* Self-contained: the row's hover lift lives here rather than borrowing a
       class from the host page. */
    .club-card-row { display: flex; align-items: center; gap: 12px; transition: transform .2s ease, box-shadow .2s ease; }
    .club-card-row:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(28,16,72,.14); }
</style>
@endonce

@php
    $clubUrl = ($a->tenant && $a->tenant->slug && $a->tenant->country)
        ? route('clubs.show', ['country' => strtolower($a->tenant->country), 'slug' => $a->tenant->slug])
        : null;
    $tag = $clubUrl ? 'a' : 'div';
    $logo = $a->logo ? file_url($a->logo) : null;
@endphp

<{{ $tag }} @if($clubUrl) href="{{ $clubUrl }}" @endif
    class="club-card-row" style="background:#fff;border-radius:18px;box-shadow:0 6px 20px rgba(28,16,72,.07);padding:14px;color:#1c1c28">
    <span style="display:grid;place-items:center;flex-shrink:0;width:46px;height:46px;border-radius:13px;background:#f1f2f7;color:#a2a6b8;font-size:18px;overflow:hidden;{{ $active ? '' : 'filter:grayscale(1);' }}">
        @if($logo)
            <img src="{{ $logo }}" alt="" style="width:100%;height:100%;object-fit:cover;display:block">
        @else
            <i class="bi bi-buildings"></i>
        @endif
    </span>

    <div style="flex:1;min-width:0">
        <p style="margin:0;display:flex;align-items:center;gap:6px;font-size:14px;font-weight:700">
            <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $a->tenant?->tr('club_name') ?? $a->club_name }}</span>
            @if($primary ?? false)
                <span style="flex-shrink:0;font-size:9px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;padding:3px 6px;border-radius:6px;background:#f2effe;color:#6d4bd8">{{ __('personal.primary_club') }}</span>
            @endif
        </p>
        <p style="margin:3px 0 0;font-size:11px;color:#8a8fa3">
            @if($active)
                {{ __('member.since') }} {{ optional($a->start_date)->format('M Y') ?: '—' }}
            @else
                {{ optional($a->start_date)->format('M Y') ?: '—' }} – {{ optional($a->end_date)->format('M Y') }}
            @endif
        </p>
    </div>

    @if($active)
        <span style="display:flex;align-items:center;gap:5px;flex-shrink:0;font-size:10px;font-weight:700;padding:4px 9px;border-radius:999px;background:#e7f7ee;color:#15803d">
            <span style="width:6px;height:6px;border-radius:50%;background:#22c55e"></span>{{ __('member.active') }}
        </span>
    @else
        <span style="flex-shrink:0;font-size:10px;font-weight:700;padding:4px 9px;border-radius:999px;background:#f1f2f7;color:#7a7f94">{{ __('member.left') }}</span>
    @endif
</{{ $tag }}>
