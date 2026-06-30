{{--
    Duel (1v1) card — stylish VS layout. Props:
      $d       : duel data (see PersonalMobileController@demoDuels)
      $variant : 'incoming' (accept/decline) | 'active' (live score) | 'sent' (pending)
    Reuses mobile motion vocabulary; links into the duel detail page.
--}}
@php
    $typeLabel = $d['type'] === 'fight' ? 'Fight' : 'Athletic';
    $typeIcon  = $d['type'] === 'fight' ? 'bi-trophy' : 'bi-lightning-charge-fill';
@endphp
<div class="m-card rounded-2xl overflow-hidden" x-data="{
        resolved: '', busy: false,
        async act(kind) {
            if (this.busy) return;
            this.busy = true;
            const urls = {
                accept:  '{{ route('me.challenge.duel.accept',  $d['id']) }}',
                decline: '{{ route('me.challenge.duel.decline', $d['id']) }}',
            };
            try {
                const res = await fetch(urls[kind], {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || 'Action failed');
                this.resolved = kind === 'accept' ? 'accepted' : 'declined';
                window.showToast(kind === 'accept' ? 'success' : 'info', data.message);
            } catch (e) {
                window.showToast('error', e.message);
            } finally {
                this.busy = false;
            }
        }
     }">
    <a href="{{ route('me.challenge.duel', $d['id']) }}" data-shell-link data-route="me.challenge" class="block m-press">
        {{-- header strip --}}
        <div class="px-4 py-2.5 flex items-center justify-between text-white text-[11px] font-bold"
             style="background: linear-gradient(135deg, {{ $d['color'] }}, {{ $d['color'] }}cc);">
            <span class="inline-flex items-center gap-1.5"><i class="bi {{ $typeIcon }}"></i> {{ $typeLabel }} · {{ $d['discipline'] }}</span>
            <span class="opacity-90">{{ $d['when'] ?? '' }}</span>
        </div>

        {{-- VS row --}}
        <div class="px-4 py-4">
            <div class="flex items-center justify-between">
                {{-- me --}}
                <div class="flex flex-col items-center w-1/3">
                    @if(!empty($d['me']['avatar']))
                        <img src="{{ $d['me']['avatar'] }}" alt="You" class="w-14 h-14 rounded-full object-cover border-2 border-white shadow">
                    @else
                        <x-gender-avatar :gender="$d['me']['gender'] ?? null" bg="hsl(250 55% 60%)" class="w-14 h-14 rounded-full border-2 border-white shadow" />
                    @endif
                    <p class="text-xs font-bold text-foreground mt-1.5">You</p>
                    @if(isset($d['me']['score']))
                        <p class="text-sm font-black" style="color: {{ $d['color'] }};">{{ $d['me']['score'] }}</p>
                    @else
                        <p class="text-[10px] text-muted-foreground">{{ $d['me']['record'] }}</p>
                    @endif
                </div>

                {{-- VS badge --}}
                <div class="flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full grid place-items-center text-white font-black text-xs shadow-md m-float" style="background: linear-gradient(135deg, {{ $d['color'] }}, #1f2937);">VS</div>
                    @if($variant === 'active' && isset($d['leading']))
                        <span class="mt-1 px-2 py-0.5 rounded-full text-[9px] font-bold bg-green-50 text-green-600">{{ $d['leading']==='me' ? 'Leading' : 'Behind' }}</span>
                    @endif
                </div>

                {{-- opponent --}}
                <div class="flex flex-col items-center w-1/3">
                    @if(!empty($d['opponent']['avatar']))
                        <img src="{{ $d['opponent']['avatar'] }}" alt="{{ $d['opponent']['name'] }}" class="w-14 h-14 rounded-full object-cover border-2 border-white shadow">
                    @else
                        <x-gender-avatar :gender="$d['opponent']['gender'] ?? null" bg="hsl(8 60% 58%)" class="w-14 h-14 rounded-full border-2 border-white shadow" />
                    @endif
                    <p class="text-xs font-bold text-foreground mt-1.5 truncate max-w-full">{{ $d['opponent']['name'] }}</p>
                    @if(isset($d['opponent']['score']))
                        <p class="text-sm font-black text-muted-foreground">{{ $d['opponent']['score'] }}</p>
                    @else
                        <p class="text-[10px] text-muted-foreground">{{ $d['opponent']['record'] }}</p>
                    @endif
                </div>
            </div>

            {{-- meta --}}
            <div class="flex items-center justify-center gap-3 mt-3 text-[11px] text-muted-foreground">
                <span class="inline-flex items-center gap-1"><i class="bi bi-flag"></i>{{ $d['metric'] }}</span>
                <span class="inline-flex items-center gap-1"><i class="bi bi-star-fill text-amber-400"></i>{{ $d['stake'] }}</span>
            </div>
        </div>
    </a>

    {{-- variant-specific footer --}}
    @if($variant === 'incoming')
        <div class="px-4 pb-4 -mt-1">
            <p class="text-xs text-muted-foreground italic text-center mb-3">“{{ $d['message'] }}”</p>
            <div class="flex items-center gap-2" x-show="resolved===''" x-transition>
                <button type="button" @click="act('decline')" :disabled="busy"
                        class="m-press flex-1 py-2.5 rounded-xl border border-gray-200 text-muted-foreground text-sm font-bold disabled:opacity-50">Decline</button>
                <button type="button" @click="act('accept')" :disabled="busy"
                        class="m-press flex-1 py-2.5 rounded-xl text-white text-sm font-bold disabled:opacity-50" style="background: {{ $d['color'] }};">
                    <i class="bi bi-check2-circle"></i> Accept
                </button>
            </div>
            <div x-show="resolved==='accepted'" x-cloak x-transition class="text-center py-2 text-sm font-bold text-green-600"><i class="bi bi-check2-circle"></i> Accepted</div>
            <div x-show="resolved==='declined'" x-cloak x-transition class="text-center py-2 text-sm font-bold text-muted-foreground"><i class="bi bi-x-circle"></i> Declined</div>
        </div>
    @elseif($variant === 'active')
        <div class="px-4 pb-4">
            <div class="flex items-center justify-between text-[11px] mb-1.5">
                <span class="font-semibold" style="color: {{ $d['color'] }};">You {{ $d['me']['pct'] ?? 0 }}%</span>
                <span class="text-muted-foreground">{{ $d['deadline'] }}</span>
                <span class="font-semibold text-muted-foreground">{{ $d['opponent']['name'] }} {{ $d['opponent']['pct'] ?? 0 }}%</span>
            </div>
            <div class="h-2 rounded-full bg-muted overflow-hidden flex">
                <div class="m-bar-fill h-full" style="width: {{ ($d['me']['pct'] ?? 0) / max(($d['me']['pct'] ?? 0) + ($d['opponent']['pct'] ?? 1), 1) * 100 }}%; background: {{ $d['color'] }};"></div>
                <div class="h-full bg-gray-300 flex-1"></div>
            </div>
            <a href="{{ route('me.challenge.duel', $d['id']) }}" data-shell-link data-route="me.challenge"
               class="m-press mt-3 w-full py-2 rounded-xl bg-accent text-primary text-xs font-bold flex items-center justify-center gap-1.5">
                <i class="bi bi-clipboard-data"></i> Log my result
            </a>
        </div>
    @elseif($variant === 'sent')
        <div class="px-4 pb-4 flex items-center justify-between">
            <span class="text-[11px] text-muted-foreground inline-flex items-center gap-1.5"><i class="bi bi-hourglass-split"></i>{{ $d['deadline'] }}</span>
            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-600">Waiting for reply</span>
        </div>
    @endif
</div>
