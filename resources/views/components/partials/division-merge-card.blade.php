{{-- One merge, as <x-division-merge> draws it. `$j` names the Alpine variable
     holding the judgement; the enclosing scope provides `k` (its key). Every
     server value is rendered with x-text — never x-html. --}}
<div class="flex items-center gap-2 flex-wrap">
    <span class="inline-flex items-center h-6 px-2.5 rounded-full text-[10px] font-black uppercase tracking-wider"
          :style="'color:' + tone({{ $j }}.grade).ink + ';background:' + tone({{ $j }}.grade).bg"
          x-text="tone({{ $j }}.grade).label"></span>
    <span class="text-[11px] text-muted-foreground" x-text="{{ $j }}.moving + ' ' + words.moving + ' · ' + {{ $j }}.after + ' ' + words.into"></span>
</div>
<p class="text-sm text-foreground leading-snug">
    <b x-text="{{ $j }}.from_names"></b>
    <span class="text-muted-foreground">→</span>
    <b x-text="{{ $j }}.into_name"></b>
</p>
<ul class="space-y-1">
    <template x-for="(r, n) in ({{ $j }}.reasons || [])" :key="n">
        <li class="flex gap-2 text-[12px] text-muted-foreground leading-snug">
            <span class="w-2 h-2 rounded-full flex-shrink-0 mt-1" :style="'background:' + dot(r.level)"></span>
            <span x-text="r.text"></span>
        </li>
    </template>
</ul>
<button type="button" x-show="{{ $j }}.grade === 'amber'" @click="consent = { ...consent, [k]: ! consent[k] }"
        role="checkbox" :aria-checked="!! consent[k]"
        class="w-full flex items-start gap-2.5 p-2.5 rounded-xl text-start border" style="background:#fff6e6;border-color:#f1d9a8">
    <span class="w-5 h-5 rounded-md flex-shrink-0 grid place-items-center text-[11px] font-black"
          :style="consent[k] ? 'background:#b45309;color:#fff' : 'border:1.5px solid #d9b77a;color:transparent'">✓</span>
    <span class="text-[12px] leading-snug" style="color:#7a4a0c">{{ __('events::events.merge_ui_consent_tick') }}</span>
</button>
<div class="flex justify-end" x-show="{{ $j }}.grade !== 'red'">
    <button type="button" @click="merge({{ $j }}, k)" :disabled="! canMerge({{ $j }}, k)"
            class="m-press bg-primary text-white px-4 py-2.5 rounded-xl text-sm font-bold disabled:opacity-50"
            x-text="busy === k ? words.merging : words.merge_into + ' ' + {{ $j }}.into_name"></button>
</div>
