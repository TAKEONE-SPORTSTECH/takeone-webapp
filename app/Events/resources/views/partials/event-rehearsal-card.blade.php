{{--
    THE DRESS REHEARSAL — run the whole competition before the day, then take it
    all back.

    Reads the shared event Alpine root (partials/event-show-script): `rehearsing`,
    `rehearsalBusy`, `startRehearsal()`, `stopRehearsal()`. Include it inside an
    element carrying that x-data; both event-manage consoles do.

    Two states, one card. NOT running: an invitation, in the platform's own
    purple, because starting one is safe and reversible and the organiser should
    feel free to. Running: the card goes amber and says so loudly — the whole
    risk of this feature is somebody forgetting a rehearsal is on and reading
    practice results as real ones.

    The card is rendered only where `$canRehearse` is true (organiser, event not
    started); the service refuses both again on the way in.
--}}
@if($canRehearse ?? false)
<div>
    <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80 mb-2 px-1">{{ __('events::events.rehearsal_heading') }}</p>

    {{-- RUNNING --}}
    <div x-show="rehearsing" x-cloak
         class="rounded-2xl overflow-hidden border border-amber-200 bg-white">
        <div class="px-4 py-3 text-white relative overflow-hidden"
             style="background: linear-gradient(150deg, #b45309, #d97706b0);">
            <div class="absolute -right-8 -top-10 w-32 h-32 rounded-full bg-white/10"></div>
            <p class="relative text-[11px] font-black tracking-[0.16em] flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>{{ __('events::events.rehearsal_running') }}
            </p>
        </div>
        <div class="p-3.5">
            <p class="text-[12px] text-muted-foreground leading-relaxed">{{ __('events::events.rehearsal_running_note') }}</p>
            <button type="button" @click="stopRehearsal()" :disabled="rehearsalBusy"
                    class="m-press mt-3 w-full h-11 rounded-xl bg-amber-600 text-white text-sm font-black flex items-center justify-center gap-2 disabled:opacity-50">
                <i class="bi" :class="rehearsalBusy ? 'bi-hourglass-split' : 'bi-arrow-counterclockwise'"></i>
                {{ __('events::events.rehearsal_end') }}
            </button>
            <p class="text-[11px] text-muted-foreground/80 mt-1.5 text-center">{{ __('events::events.rehearsal_end_sub') }}</p>
        </div>
    </div>

    {{-- NOT RUNNING --}}
    <button type="button" x-show="! rehearsing" @click="startRehearsal()" :disabled="rehearsalBusy"
            class="m-press w-full text-start bg-white rounded-2xl border border-gray-100 p-3.5 flex items-center gap-3 disabled:opacity-50">
        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-accent text-primary">
            <i class="bi bi-play-btn text-lg"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ __('events::events.rehearsal_start') }}</span>
            <span class="block text-[11px] text-muted-foreground mt-0.5">{{ __('events::events.rehearsal_start_sub') }}</span>
        </span>
        <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/60"></i>
    </button>
</div>
@endif
