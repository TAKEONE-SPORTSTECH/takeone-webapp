{{-- Product card (Alpine scope: p — a published club product). --}}
<a class="t1-card" :href="p.url" :data-card="p.key" :class="{ hl: hl === p.key }" @mouseenter="hl = p.key">
    <div class="t1-media" :style="'--c:' + p.color">
        <template x-if="p.image"><img class="t1-cover" :src="p.image" alt="" loading="lazy"></template>
        <template x-if="!p.image"><span><span class="t1-pattern"></span><span class="t1-glyph"><i class="bi bi-bag"></i></span></span></template>
        <div class="t1-shade"></div>
        <span class="t1-tchip" style="--c:#0f766e"><i class="bi bi-bag"></i>{{ __('ui::explore.shop') }}</span>
        <template x-if="p.off"><span class="t1-offbadge" x-text="p.off"></span></template>
    </div>
    <div class="t1-cb">
        <div class="t1-ct"><h4 x-text="p.name"></h4>
            <template x-if="p.rating"><span class="t1-rate"><i class="bi bi-star-fill"></i><span x-text="p.rating.toFixed(1)"></span> <small x-text="'(' + p.reviews + ')'"></small></span></template>
        </div>
        <div class="t1-tags" x-show="p.brand" x-text="p.brand"></div>
        <div class="t1-meta"><i class="bi bi-building"></i><span x-text="p.club"></span></div>
        <div class="t1-commit">
            <span><small x-show="p.from" style="color:var(--t1-soft)">{{ __('ui::explore.from') }}</small> <b x-text="p.price"></b> <s class="t1-was" x-show="p.old" x-text="p.old"></s></span>
        </div>
    </div>
</a>
