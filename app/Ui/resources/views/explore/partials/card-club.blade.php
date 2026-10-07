{{-- Club card (Alpine scope: c). --}}
<a class="t1-card" :href="c.url" :data-card="c.key" :class="{ hl: hl === c.key }" @mouseenter="hl = c.key">
    <div class="t1-media" :style="'--c:' + c.color">
        <template x-if="c.cover"><img class="t1-cover" :src="c.cover" alt="" loading="lazy"></template>
        <template x-if="!c.cover"><span><span class="t1-pattern"></span><span class="t1-glyph"><i class="bi bi-building"></i></span></span></template>
        <div class="t1-shade"></div>
        <span class="t1-tchip" style="--c:#7c3aed"><i class="bi bi-building"></i>{{ __('ui::explore.club') }}</span>
        <template x-if="c.logo"><span class="t1-logo-bare"><img :src="c.logo" alt=""></span></template>
        <template x-if="!c.logo"><span class="t1-mono" x-text="c.initials"></span></template>
    </div>
    <div class="t1-cb">
        <div class="t1-ct"><h4 x-text="c.name"></h4>
            <template x-if="c.rating"><span class="t1-rate"><i class="bi bi-star-fill"></i><span x-text="c.rating.toFixed(1)"></span> <small x-text="'(' + c.reviews + ')'"></small></span></template>
        </div>
        <div class="t1-tags" x-text="c.activities.join(' · ')"></div>
        <div class="t1-meta" x-show="place(c)"><i class="bi bi-geo-alt"></i><span x-text="place(c)"></span></div>
        <div class="t1-commit">
            <template x-if="c.price"><span><small style="color:var(--t1-soft)">{{ __('ui::explore.from') }}</small> <b x-text="c.price"></b> <span x-show="c.per" x-text="'/ ' + c.per"></span></span></template>
            <template x-if="!c.price"><span></span></template>
            <span class="t1-pill" x-show="c.members > 0" x-text="c.members === 1 ? t('member_one') : t('members', c.members)"></span>
        </div>
    </div>
</a>
