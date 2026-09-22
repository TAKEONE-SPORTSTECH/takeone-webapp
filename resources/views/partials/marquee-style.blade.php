{{--
    One line, never wrapped — and when it does not fit, it slides.

    A division can be called "Group G · White Belt · Male · 15-60 years ·
    71-79 kg · No-Gi". Wrapped, that is three lines and the rhythm that makes a
    running order scannable across a noisy hall is gone. So the name stays on
    one line and, when it is wider than its column, slides out far enough to
    show its last character and comes back.

    ⚠️ `min(0px, calc(100cqw - 100%))` is the whole trick and it is why there is
    no JavaScript here: `100%` is the TEXT's own width, `100cqw` its COLUMN's
    (the parent declares `container-type: inline-size`). Text that fits gives a
    positive number, which `min()` clamps to zero — so short names never twitch
    and nothing has to be measured.

    Used by <x-event-run-order> and by both running-order boards. Include it
    anywhere a label must not wrap; it is @once-guarded, so twice is free.

    Markup:  <span class="mq"><span>…</span></span>
--}}
@once
@push('styles')
<style>
    .mq { container-type: inline-size; overflow: hidden; }
    .mq > span { display: inline-block; white-space: nowrap; animation: mq-slide 6s cubic-bezier(.4, 0, .6, 1) infinite; }
    @keyframes mq-slide {
        0%, 10%   { transform: translateX(0); }
        45%, 58%  { transform: translateX(min(0px, calc(100cqw - 100%))); }
        95%, 100% { transform: translateX(0); }
    }
    .ro-now-dot { animation: ro-pulse 1.8s ease-in-out infinite; }
    @keyframes ro-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .45; } }
    @media (prefers-reduced-motion: reduce) {
        .mq > span, .ro-now-dot { animation: none; }
    }
</style>
@endpush
@endonce
