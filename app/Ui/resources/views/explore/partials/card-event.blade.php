{{-- Event card (Alpine scope: e — the explore.events payload). --}}
{{-- A federation event opens on its own front door (artwork + language picker); any other on its page. --}}
<a class="t1-card" :href="(e.cover_url || e.url) + '#cover'" :data-card="e.key" :class="{ hl: hl === e.key }" @mouseenter="hl = e.key">
    <div class="t1-media" :style="'--c:' + (e.color || '#7c3aed')">
        <template x-if="e.image"><img class="t1-cover" :src="fileUrl(e.image)" alt="" loading="lazy"></template>
        <template x-if="!e.image"><span><span class="t1-pattern"></span><span class="t1-glyph"><i class="bi bi-calendar-event"></i></span></span></template>
        <div class="t1-shade"></div>
        <span class="t1-tchip" :style="'--c:' + (e.color || '#7c3aed')"><i class="bi" :class="e.type === 'federation' ? 'bi-bank' : 'bi-calendar-event'"></i>{{ __('ui::explore.event') }}</span>
        <span class="t1-dbadge" :style="'--c:' + (e.color || '#7c3aed')"><b x-text="e.mon"></b><span x-text="e.day"></span></span>
        <span class="t1-state" :class="{ live: e.state === 'live', ended: e.state === 'ended' }" x-text="evState(e)"></span>
    </div>
    <div class="t1-cb">
        <div class="t1-ct"><h4 x-text="e.title"></h4></div>
        <div class="t1-tags" x-text="[e.sport, e.club].filter(Boolean).join(' · ')"></div>
        <div class="t1-meta" x-show="e.location || e.date_label"><i class="bi bi-geo-alt"></i><span x-text="[e.date_label, e.location].filter(Boolean).join(' · ')"></span></div>
        <div class="t1-commit"><span><b x-text="evPrice(e)"></b></span><span class="t1-pill" x-show="e.featured"><i class="bi bi-stars"></i></span></div>
    </div>
</a>
