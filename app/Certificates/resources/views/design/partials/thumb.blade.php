{{--
    A miniature of one background choice, for the picker. Drawn with CSS (the
    real page is drawn by App's PDF engine — see the live preview beside it);
    this only has to say at a glance what each choice looks like.
    Inline styles on purpose: the Tailwind bundle is prebuilt, and a gradient
    class nobody used before would not exist in it.
--}}
@php
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($color ?? '')) ? $color : '#6d5bd0';
    $paper = $bg === 'blank' ? '#ffffff' : '#fbf8f2';
    $layer = match ($bg) {
        'guilloche' => "repeating-radial-gradient(circle at 0% 50%, transparent 0 5px, {$c}26 5px 6px), repeating-radial-gradient(circle at 100% 50%, transparent 0 5px, #b8892b22 5px 6px)",
        'motif' => 'repeating-linear-gradient(90deg, rgba(31,26,46,.06) 0 1px, transparent 1px 7px)',
        'artwork' => "linear-gradient(90deg, #13224a 0 22%, #d4a62a 22% 24%, transparent 24%)",
        default => 'none',
    };
@endphp
<span class="block relative rounded-md overflow-hidden border border-gray-100" style="aspect-ratio: 297 / 210; background: {{ $paper }}; background-image: {{ $layer }};">
    @if(in_array($bg, ['plain', 'guilloche', 'watermark', 'motif'], true))
        <span class="absolute" style="inset: 6%; border: 1px solid {{ $c }};"></span>
    @endif
    @if($bg === 'watermark')
        <span class="absolute rounded-full" style="left: 50%; top: 50%; width: 44%; aspect-ratio: 1; transform: translate(-50%, -50%); border: 5px solid {{ $c }}1f;"></span>
    @elseif($bg === 'motif')
        <span class="absolute rounded-full" style="right: -8%; top: -14%; width: 36%; aspect-ratio: 1; border: 5px solid rgba(31,26,46,.1); border-left-color: transparent;"></span>
    @elseif($bg === 'artwork')
        <span class="absolute inset-0 grid place-items-center text-[9px] font-bold uppercase tracking-wide" style="color: #13224a; padding-left: 20%;"><i class="bi bi-image text-base"></i></span>
    @elseif($bg === 'blank')
        <span class="absolute" style="inset: 6%; border: 1px dashed #d1d5db;"></span>
    @endif
    <span class="absolute rounded-full" style="left: 30%; right: 30%; top: 46%; height: 5px; background: {{ $bg === 'artwork' ? '#13224a' : $c }}; opacity: .8; margin-left: {{ $bg === 'artwork' ? '8%' : '0' }};"></span>
    <span class="absolute rounded-full" style="left: 36%; right: 36%; top: 58%; height: 3px; background: #b9b0a0;"></span>
</span>
