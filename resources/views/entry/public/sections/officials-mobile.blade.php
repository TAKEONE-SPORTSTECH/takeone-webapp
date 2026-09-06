
<div class="px-4 pt-3 pb-4">
    <p class="text-[13px] text-muted-foreground leading-relaxed">{{ ($e['officials']['count'] ?? 0) > 0 ? __('events.public_officials_lead') : __('events.public_officials_empty_body') }}</p>

    @forelse($e['officials']['groups'] as $g)
        <p class="mt-4 text-[9.5px] font-bold uppercase tracking-[0.16em] text-muted-foreground/70">{{ $g['label'] }}</p>
        <div class="mt-2 space-y-1.5">
            @foreach($g['people'] as $person)
                <div class="flex items-center gap-2.5 px-3 py-2 rounded-xl bg-muted/40">
                    <span class="w-8 h-8 rounded-lg grid place-items-center flex-shrink-0 text-[11px] font-bold"
                          style="color: {{ $ev }}; background: {{ $evSoft }};">
                        {{ Str::of($person['name'])->explode(' ')->take(2)->map(fn ($w) => Str::upper(Str::substr($w, 0, 1)))->implode('') }}
                    </span>
                    <span class="min-w-0 flex-1 truncate text-[13px] font-semibold text-foreground">{{ $person['name'] }}</span>
                    @if($flagClass($person['country']))
                        <span class="{{ $flagClass($person['country']) }} flex-shrink-0"
                              style="width:20px; height:15px; border-radius:3px;" title="{{ $person['country'] }}"></span>
                    @endif
                </div>
            @endforeach
        </div>
    @empty
        @include('entry.public.partials.empty', [
            'ev' => $ev, 'icon' => 'bi-person-badge',
            'title' => __('events.public_officials_empty_title'),
            'flush' => true,
        ])
    @endforelse
</div>
