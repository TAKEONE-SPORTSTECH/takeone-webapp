{{-- Federation card (Alpine scope: f). No public federation page exists yet,
     so it opens the federation's own events on this page — never a dead link. --}}
<button type="button" class="t1-card" :data-card="f.key" :class="{ hl: hl === f.key }" @mouseenter="hl = f.key" @click="fedEvents(f)">
    <div class="t1-media" :style="'--c:' + f.color">
        <span class="t1-pattern"></span><span class="t1-glyph"><i class="bi bi-bank"></i></span>
        <div class="t1-shade"></div>
        <span class="t1-tchip" style="--c:#1e2c4f"><i class="bi bi-bank"></i>{{ __('ui::explore.federation') }}</span>
        <template x-if="f.logo"><span class="t1-logo-bare"><img :src="f.logo" alt=""></span></template>
        <template x-if="!f.logo"><span class="t1-mono" x-text="f.initials"></span></template>
    </div>
    <div class="t1-cb">
        <div class="t1-ct"><h4 x-text="f.name"></h4></div>
        <div class="t1-tags" x-show="f.sports.length" x-text="f.sports.join(' · ')"></div>
        <div class="t1-meta" x-show="f.country"><i class="bi bi-flag"></i><span x-text="f.country"></span></div>
        <div class="t1-commit">
            <span class="t1-pill" x-text="f.clubs_label"></span>
            <span class="t1-fedgo"><span x-text="fedEventCount(f) ? t('fed_events') + ' · ' + fedEventCount(f) : t('fed_events')"></span><i class="bi bi-chevron-right rtl:rotate-180"></i></span>
        </div>
    </div>
</button>
