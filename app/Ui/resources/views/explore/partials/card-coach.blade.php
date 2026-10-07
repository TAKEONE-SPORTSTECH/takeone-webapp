{{-- Coach card (Alpine scope: c — a personal trainer). Portrait 3:4. --}}
<a class="t1-card" :href="c.url" :data-card="c.key" :class="{ hl: hl === c.key }" @mouseenter="hl = c.key">
    <div class="t1-media portrait" style="--c:hsl(250 55% 60%)">
        <template x-if="c.photo"><img class="t1-cover" :src="c.photo" alt="" loading="lazy"></template>
        <template x-if="!c.photo"><span class="t1-avatar"><i class="bi bi-person-fill"></i></span></template>
        <div class="t1-shade"></div>
        <span class="t1-tchip" style="--c:#7c3aed"><i class="bi bi-person-badge"></i>{{ __('ui::explore.coach') }}</span>
    </div>
    <div class="t1-cb">
        <div class="t1-ct"><h4 x-text="c.name"></h4>
            <template x-if="c.rating > 0"><span class="t1-rate"><i class="bi bi-star-fill"></i><span x-text="c.rating.toFixed(1)"></span> <small x-text="'(' + c.reviews + ')'"></small></span></template>
        </div>
        <div class="t1-tags" x-text="c.role || c.club"></div>
        <div class="t1-meta" x-show="c.years > 0"><i class="bi bi-award"></i><span x-text="t('years', c.years)"></span></div>
    </div>
</a>
