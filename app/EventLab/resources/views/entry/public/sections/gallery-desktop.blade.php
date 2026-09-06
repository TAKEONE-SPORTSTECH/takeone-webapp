
<div class="px-5 pt-2.5">
    <p class="text-[13px] text-muted-foreground leading-relaxed">{{ ($e['gallery']['count'] ?? 0) > 0 ? __('events.public_gallery_lead') : __('events.public_gallery_empty_body') }}</p>
    @if(($e['gallery']['count'] ?? 0) > 0)
        <span class="inline-flex items-center gap-1.5 mt-3 px-2.5 py-1 rounded-lg text-[11px] font-bold"
              style="color: {{ $ev }}; background: {{ $evSoft }};">
            <i class="bi bi-camera-reels-fill"></i>{{ trans_choice('events.bout_gallery_count', $e['gallery']['count'], ['count' => $e['gallery']['count']]) }}
        </span>
    @endif
</div>

@forelse($e['gallery']['divisions'] as $div)
    <p class="px-5 mt-5 text-[9.5px] font-bold uppercase tracking-[0.16em] text-muted-foreground/70">
        {{ $div['title'] }}@if($div['subtitle'] ?? null) <span class="normal-case tracking-normal font-semibold">· {{ $div['subtitle'] }}</span>@endif
    </p>

    {{-- A grid rather than the phone's side-scrolling shelf:
         a wide screen has room to show a division at once. --}}
    <div class="px-5 mt-2.5 grid grid-cols-2 xl:grid-cols-3 gap-3">
        @foreach($div['bouts'] as $b)
            <button type="button" class="text-start group"
                    data-media-lightbox data-kind="video"
                    data-src="{{ $b['preview'] }}"
                    data-label="{{ trim(($b['a'] ?? '') . ' — ' . ($b['b'] ?? '')) }}">
                <span class="block relative overflow-hidden rounded-xl aspect-video" style="background:#0b132b;">
                    @if($b['poster'] ?? null)
                        <img src="{{ $b['poster'] }}" alt="" loading="lazy"
                             class="w-full h-full object-cover opacity-90 group-hover:opacity-100 transition-opacity">
                    @endif
                    <span class="absolute inset-0 grid place-items-center">
                        <span class="w-10 h-10 rounded-full grid place-items-center text-white text-sm
                                     bg-black/45 border border-white/35 group-hover:scale-105 transition-transform">
                            <i class="bi bi-play-fill"></i>
                        </span>
                    </span>
                    @if($b['duration'] ?? null)
                        <span class="absolute right-1.5 bottom-1.5 px-1.5 py-0.5 rounded-md bg-black/60 text-white text-[10px] font-semibold">{{ $clock($b['duration']) }}</span>
                    @endif
                </span>
                <span class="block truncate mt-1.5 text-xs font-bold text-foreground">{{ $b['a'] ?: __('events.bracket_tbd') }}</span>
                <span class="block truncate text-xs font-bold text-foreground">{{ $b['b'] ?: __('events.bracket_tbd') }}</span>
                <span class="block truncate mt-0.5 text-[10.5px] text-muted-foreground">
                    {{ $b['round'] }}@if($b['score'] ?? null) · {{ $b['score'] }}@endif
                </span>
            </button>
        @endforeach
    </div>
@empty
    @include('eventlab::entry.public.partials.empty', [
        'ev' => $ev, 'icon' => 'bi-camera-reels',
        'title' => __('events.public_gallery_empty_title'),
        'flush' => true,
    ])
@endforelse

<div class="h-5"></div>
