{{-- Activity tile (Alpine scope: a). Opens the activity's directory page. --}}
<a class="t1-act" :href="a.url">
    <div class="t1-media" :style="'--c:' + a.color">
        <template x-if="a.picture"><img class="t1-cover" :src="a.picture" alt="" loading="lazy"></template>
        <template x-if="!a.picture"><span><span class="t1-pattern"></span><span class="t1-glyph"><i class="bi bi-grid"></i></span></span></template>
        <div class="t1-shade"></div>
        <span class="t1-mono" x-text="a.initials"></span>
    </div>
    <div class="t1-ab">
        <span class="t1-grp" x-text="groupLabel(a.group)"></span>
        <h4 x-text="a.name"></h4>
        <p x-show="a.description" x-text="a.description"></p>
        <div class="t1-styles" x-show="a.styles.length">
            <template x-for="s in a.styles.slice(0, 3)" :key="s"><span x-text="s"></span></template>
            <template x-if="a.styles.length > 3"><span x-text="'+' + (a.styles.length - 3)"></span></template>
        </div>
        <span class="t1-near" x-show="a.clubs > 0"><span class="t1-g"></span><span x-text="a.clubs === 1 ? @js(trans_choice('ui::explore.near_n_clubs', 1)) : @js(trans_choice('ui::explore.near_n_clubs', 2, ['count' => '#'])).replace('#', a.clubs)"></span></span>
        <span class="t1-near none" x-show="a.clubs === 0">{{ __('ui::explore.not_offered') }}</span>
    </div>
</a>
