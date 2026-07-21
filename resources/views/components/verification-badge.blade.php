@props([
    'status' => 'self_reported',   // self_reported | pending | verified | rejected
    'club' => null,                // attesting club name (verified only)
    'size' => 'sm',                // sm | xs
])

@php
    // Honest, on-palette provenance labelling. A self-reported claim must NEVER
    // borrow the visual authority of a verified one (see the authenticity plan).
    $map = [
        'verified' => ['bi-patch-check-fill', 'text-green-700', 'bg-green-50', 'border-green-200', __('Verified')],
        'pending' => ['bi-hourglass-split', 'text-amber-700', 'bg-amber-50', 'border-amber-200', __('Pending review')],
        'rejected' => ['bi-patch-exclamation', 'text-red-700', 'bg-red-50', 'border-red-200', __('Not verified')],
        'self_reported' => ['bi-person-badge', 'text-gray-500', 'bg-gray-50', 'border-gray-200', __('Self-reported')],
    ];
    [$icon, $text, $bg, $border, $label] = $map[$status] ?? $map['self_reported'];
    $pad = $size === 'xs' ? 'px-1.5 py-0.5 text-[10px]' : 'px-2 py-0.5 text-xs';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full border font-medium $pad $text $bg $border"]) }}
      title="{{ $status === 'verified' && $club ? __('Verified by :club', ['club' => $club]) : $label }}">
    <i class="bi {{ $icon }}"></i>
    <span>{{ $label }}</span>
    @if($status === 'verified' && $club)
        <span class="opacity-70 font-normal">· {{ $club }}</span>
    @endif
</span>
