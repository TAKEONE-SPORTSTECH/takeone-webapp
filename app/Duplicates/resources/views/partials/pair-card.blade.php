{{--
    One finding as a PAIR: the two records face each other across a connector
    disc — "=" when the watch is certain, "≈" when a person must decide, ✓ once
    resolved. Used by the desktop and the mobile page alike (inside their
    `x-for="f in shown"`), so a finding reads the same everywhere. ONE root
    element, as an x-for template requires — its styles are pair-styles.
--}}
<article class="dup-card bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden" :class="f.status !== 'open' ? 'is-done' : ''">
    <div class="flex items-center justify-between gap-3 px-4 pt-3.5">
        <div class="flex items-center gap-1.5 flex-wrap min-w-0">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-muted text-foreground">
                <i class="bi" :class="icons[f.kind]"></i><span x-text="T['kind_' + f.kind]"></span>
            </span>
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold"
                  :style="f.status !== 'open' ? 'background:hsl(220 15% 94%);color:hsl(220 10% 40%)' : (f.strength === 'certain' ? 'background:#e3f6e9;color:#15803d' : 'background:#fdf0dc;color:#b45309')"
                  x-text="statusLabel(f)"></span>
        </div>
        <span class="text-[11px] text-muted-foreground whitespace-nowrap" x-text="when(f.status === 'open' ? f.last_seen : f.resolved)"></span>
    </div>

    <div class="dup-pair px-4 py-3">
        <template x-for="slot in [0, 'link', 1]" :key="slot">
            <div style="display: contents;">
                <template x-if="slot === 'link'">
                    <span class="dup-link" :aria-label="statusLabel(f)"
                          :style="f.status !== 'open' ? 'background:hsl(220 15% 94%);color:hsl(220 10% 45%)' : (f.strength === 'certain' ? 'background:#15803d;color:#fff' : 'background:#f59e0b;color:#fff')"
                          x-text="f.status !== 'open' ? '✓' : (f.strength === 'certain' ? '=' : '≈')"></span>
                </template>
                <template x-if="slot !== 'link' && sides(f)[slot]">
                    <a class="dup-side" :href="sides(f)[slot].url || null" :class="sides(f)[slot].url ? '' : 'pointer-events-none'"
                       :target="sides(f)[slot].url ? '_blank' : null" rel="noopener">
                        <span class="w-10 h-10 flex-shrink-0 grid place-items-center rounded-xl bg-white border border-gray-100 overflow-hidden">
                            <template x-if="sides(f)[slot].logo"><img :src="sides(f)[slot].logo" alt="" class="w-full h-full object-contain"></template>
                            <template x-if="! sides(f)[slot].logo"><i class="bi text-lg text-primary" :class="sides(f)[slot].icon"></i></template>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-gray-900 leading-snug" x-text="sides(f)[slot].title"></span>
                            <span class="block text-xs text-muted-foreground leading-snug mt-0.5" x-show="sides(f)[slot].sub" x-text="sides(f)[slot].sub"></span>
                        </span>
                    </a>
                </template>
            </div>
        </template>
    </div>

    <div class="px-4 pb-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex flex-wrap gap-1.5 min-w-0">
            <template x-for="r in f.reasons" :key="r">
                <span class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-accent text-primary" x-text="r"></span>
            </template>
            <template x-if="f.status === 'open' && f.fix && f.fix.refused">
                <span class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-muted text-muted-foreground"
                      x-text="fill(T.held_back, { why: f.fix.refused })"></span>
            </template>
        </div>

        <div class="flex flex-wrap items-center gap-2 flex-shrink-0" x-show="f.status === 'open'">
            <template x-if="f.merge_on_members">
                <a href="{{ route('admin.platform.members') }}"
                   class="m-press inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-primary text-primary hover:bg-primary hover:text-white transition-colors">
                    <i class="bi bi-intersect"></i><span x-text="T.act_merge"></span>
                </a>
            </template>
            <template x-if="f.can_fix">
                <button type="button" @click="act(f, 'fix')" :disabled="busy"
                        class="m-press inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-primary text-white hover:bg-primary/90 transition-colors disabled:opacity-50">
                    <i class="bi" :class="busy === f.id + 'fix' ? 'bi-arrow-repeat animate-spin' : (f.kind === 'event_club' ? 'bi-link-45deg' : 'bi-magic')"></i>
                    <span x-text="f.kind === 'event_club' && f.strength !== 'certain' ? T.act_link : T.act_fix"></span>
                </button>
            </template>
            <button type="button" @click="act(f, 'dismiss')" :disabled="busy"
                    class="m-press inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border border-gray-200 text-gray-600 hover:bg-muted transition-colors disabled:opacity-50">
                <i class="bi" :class="busy === f.id + 'dismiss' ? 'bi-arrow-repeat animate-spin' : 'bi-x-lg'"></i><span x-text="T.act_dismiss"></span>
            </button>
        </div>
    </div>
</article>
