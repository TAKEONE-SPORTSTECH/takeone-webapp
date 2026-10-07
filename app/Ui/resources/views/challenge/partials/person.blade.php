{{-- A duel side's face, portrait 3:4. Props: $p (me/opponent array), $size --}}
<span class="t1-pv {{ $size ?? 'm' }}" style="--pc:hsl(250 55% 60%)">
    @if(!empty($p['avatar']))<img src="{{ $p['avatar'] }}" alt="">@else<x-gender-avatar :gender="$p['gender'] ?? null" class="w-full h-full" />@endif
</span>
