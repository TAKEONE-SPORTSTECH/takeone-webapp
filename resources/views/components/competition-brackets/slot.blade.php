{{--
    One corner of a bout — shared by first-round/later bouts, play-in bouts and
    the third-place bout, so the three can never drift apart. Expects an Alpine
    scope variable `s` (a slot from `compute()`); every value is drawn with
    x-text or a sanitised style string, never innerHTML.
--}}
<div class="cb-slot" :style="s.style" :draggable="s.drag ? 'true' : 'false'"
     @click="slotClick(s)"
     @mouseenter="hoverId = s.f ? s.f.id : null" @mouseleave="hoverId = null"
     @dragstart="dragStartSlot($event, s)" @dragend="dragEnd()"
     @dragover="if (canDrop(s)) $event.preventDefault()" @drop.prevent="dropAt(s.r, s.i, s.side)"
     @touchstart="pressStart($event, {type: 'slot', s: s})" @touchmove="pressMove($event)" @touchend="pressEnd()"
     @contextmenu="if (!locked) $event.preventDefault()">
    <template x-if="s.show">
        <div class="cb-x">
            <div class="cb-photo" :style="'border-color:' + s.ring + ';background-image:' + s.f.imgCss">
                <template x-if="s.win"><span class="cb-tick" :title="t.winner">✓</span></template>
            </div>
            <div class="cb-who">
                <div class="cb-line">
                    <span class="cb-name" x-text="s.f.name"></span>
                    <template x-if="s.f.flagCss"><span class="cb-flag" :style="'background-image:' + s.f.flagCss"></span></template>
                </div>
                <template x-if="cfg.showClubs && s.f.club">
                    <div class="cb-clubline">
                        <span class="cb-ab" x-text="s.f.ab"></span>
                        <span class="cb-club" x-text="s.f.club"></span>
                    </div>
                </template>
            </div>
            <template x-if="locked || cfg.recordOnly"><span class="cb-score" :style="'color:' + s.edge" x-text="s.score"></span></template>
            <template x-if="!locked && !cfg.recordOnly">
                <input class="cb-score-in" inputmode="numeric" maxlength="12" :value="s.score"
                       :style="'color:' + s.edge + ';border-color:' + s.edge"
                       @change="setScore(s.k, s.side, $event.target.value)" @click.stop @mousedown.stop>
            </template>
        </div>
    </template>
    <template x-if="s.editing">
        <div class="cb-x">
            <div class="cb-photo" :style="'border-color:#caa14f;background-image:' + s.f.imgCss"></div>
            <input class="cb-name-in" maxlength="120" :value="s.f.name"
                   @change="rename(s.f.id, $event.target.value)" @click.stop @mousedown.stop>
            <template x-if="s.canRemove">
                <button type="button" class="cb-remove" :title="t.removeTitle" :aria-label="t.removeTitle"
                        @click.stop="removeF(s.removeIdx)">✕</button>
            </template>
        </div>
    </template>
    <template x-if="s.empty">
        <div class="cb-x">
            <div class="cb-empty-photo" :style="'border-color:' + s.emptyRing"></div>
            <span class="cb-empty" :style="'color:' + s.emptyColor" x-text="s.emptyLabel"></span>
        </div>
    </template>
</div>
