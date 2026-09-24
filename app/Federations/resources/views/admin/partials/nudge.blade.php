{{--
    The nudge: an encouraging card on a federation still missing details.
    Shows right after the quick create (?welcome=1) and while the "ready to
    open" facts are incomplete. Never a gate — each chip only opens its
    section, and ✕ hides the card for this federation on this browser.
    Needs the fedAdminForm scope (stage, arc, nudgeShown, nudgeItems, reveal).
--}}
<div x-show="nudgeShown()" x-cloak x-transition.opacity.duration.300ms
     class="fedx-card-in fedx-nudge relative overflow-hidden rounded-2xl border border-primary/20 bg-white shadow-sm mb-6">
    <div class="absolute -right-10 -top-10 w-40 h-40 rounded-full bg-primary/10"></div>
    <div class="relative p-5 flex items-start gap-4">
        <span class="fedx-seal-icon flex-shrink-0">
            <svg viewBox="0 0 48 48" aria-hidden="true"><circle class="trk" cx="24" cy="24" r="22"/><circle class="arc" cx="24" cy="24" r="22" :style="arc('open')"/></svg>
            <i class="bi" :class="welcome ? 'bi-stars' : 'bi-door-open-fill'"></i>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-base font-black text-gray-900 leading-tight mb-1"
               x-text="welcome ? @js(__('federations::messages.nudge_welcome_title')) : @js(__('federations::messages.nudge_title'))"></p>
            <p class="text-sm text-muted-foreground mb-0" x-show="nudgeItems().length">{{ __('federations::messages.nudge_sub') }}</p>
            <p class="text-sm text-muted-foreground mb-0" x-show="!nudgeItems().length">{{ __('federations::messages.nudge_open_done') }}</p>

            <div class="flex flex-wrap gap-2 mt-3" x-show="nudgeItems().length">
                <template x-for="it in nudgeItems()" :key="it.key">
                    <button type="button" @click="reveal(it.sec)"
                            class="m-press inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium border border-primary/30 bg-primary/5 text-primary hover:bg-primary hover:text-white transition-colors">
                        <i class="bi bi-plus-lg"></i><span x-text="it.label"></span>
                    </button>
                </template>
            </div>
            <p class="text-xs text-muted-foreground mt-3 mb-0"><i class="bi bi-clock-history me-1"></i>{{ __('federations::messages.nudge_no_rush') }}</p>
        </div>
        <button type="button" @click="hideNudge()" aria-label="{{ __('shared.close') }}" title="{{ __('federations::messages.nudge_hide') }}"
                class="w-8 h-8 rounded-full grid place-items-center text-muted-foreground hover:bg-muted flex-shrink-0 transition-colors">
            <i class="bi bi-x-lg text-sm"></i>
        </button>
    </div>
</div>
