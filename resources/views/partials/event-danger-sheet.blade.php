{{--
    The gate in front of Cancel and Delete.

    Reads and writes the shared event Alpine root (partials/event-show-script):
    `danger`, `dangerTyped`, `dangerBusy`, `dangerMatches`, `askDanger()`,
    `runDanger()`, `closeDanger()`. Include it inside an element carrying that
    x-data — both event-manage consoles do.

    WHY IT EXISTS: those two buttons ended a competition, and they were one tap
    and one reflex-dismissed dialog away. A code that has to be READ and TYPED
    cannot be answered by muscle memory. The code is issued and checked by the
    SERVER (App\Events\Support\DestructiveChallenge) — the matching done here is
    only so the button can say when it is ready.

    Design Rule #8: the sheet opens with the gradient band, in the colour of the
    act — amber for calling it off, red for erasing it.
--}}
<template x-teleport="body">
    <div x-show="danger" x-cloak class="fixed inset-0 z-[70]" style="display:none;">
        <div class="absolute inset-0 bg-black/50" @click="closeDanger()" x-transition.opacity></div>

        <div x-show="danger"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col rounded-t-3xl overflow-hidden bg-background">

            {{-- ⚠️ The gradient is hex + the `b0` alpha suffix. An hsl() value
                 with `b0` stuck on it is an INVALID gradient, the whole
                 declaration is dropped, and the band renders as grey with white
                 text on it (Design Rule #8). --}}
            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                 :style="danger && danger.action === 'delete'
                    ? 'background: linear-gradient(150deg, #b91c1c, #dc2626b0);'
                    : 'background: linear-gradient(150deg, #b45309, #d97706b0);'">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi text-xl" :class="danger && danger.action === 'delete' ? 'bi-trash' : 'bi-slash-circle'"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-black leading-tight"
                            x-text="danger && danger.action === 'delete'
                                ? @js(__('personal.event_danger_delete_heading'))
                                : @js(__('personal.event_danger_cancel_heading'))"></h3>
                        <p class="text-[12px] text-white/85 mt-0.5"
                           x-text="danger && danger.action === 'delete'
                                ? @js(__('personal.event_danger_delete_sub'))
                                : @js(__('personal.event_danger_cancel_sub'))"></p>
                    </div>
                    <button type="button" @click="closeDanger()" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-4 pt-4 pb-4 space-y-3">
                <p class="text-[13px] text-muted-foreground leading-relaxed px-1">{{ __('personal.event_danger_code_lead') }}</p>

                {{-- The code itself: big, spaced, and selectable. Nothing else
                     on the sheet competes with it. --}}
                <div class="bg-white rounded-2xl border border-gray-100 p-4 text-center">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('personal.event_danger_code_label') }}</p>

                    <p x-show="danger && danger.code" class="mt-2 font-black text-foreground"
                       style="font-size: 30px; letter-spacing: .34em; text-indent: .34em; font-variant-numeric: tabular-nums;"
                       x-text="danger ? danger.code : ''"></p>

                    <p x-show="danger && ! danger.code" class="mt-3 text-[13px] text-muted-foreground">
                        <i class="bi bi-hourglass-split"></i> {{ __('personal.event_danger_asking') }}
                    </p>

                    {{-- autocapitalize/autocorrect off: a phone keyboard
                         "helpfully" rewriting a six-character code is the one
                         way an organiser reading it correctly still fails. --}}
                    <input type="text" x-model="dangerTyped" maxlength="6"
                           autocomplete="off" autocapitalize="characters" autocorrect="off" spellcheck="false"
                           placeholder="{{ __('personal.event_danger_code_placeholder') }}"
                           class="mt-3 w-full px-3 py-3 text-center text-lg font-black bg-white border-2 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary/30"
                           style="letter-spacing: .2em; text-transform: uppercase;"
                           :class="dangerMatches ? 'border-green-500' : 'border-gray-200'">

                    <button type="button" @click="askDanger(danger.action)" x-show="danger && danger.code"
                            class="mt-2 text-[12px] font-bold text-muted-foreground underline">{{ __('personal.event_danger_code_new') }}</button>
                </div>
            </div>

            <div class="flex-shrink-0 px-4 pt-3 bg-white border-t border-gray-100"
                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                <button type="button" @click="runDanger()" :disabled="! dangerMatches || dangerBusy"
                        class="m-press w-full h-12 rounded-xl text-white text-sm font-black flex items-center justify-center gap-2 disabled:opacity-40"
                        :class="danger && danger.action === 'delete' ? 'bg-red-600' : 'bg-amber-600'">
                    <i class="bi" :class="dangerBusy ? 'bi-hourglass-split' : (danger && danger.action === 'delete' ? 'bi-trash' : 'bi-slash-circle')"></i>
                    <span x-text="danger && danger.action === 'delete'
                        ? @js(__('personal.event_danger_delete_heading'))
                        : @js(__('personal.event_danger_cancel_heading'))"></span>
                </button>
            </div>
        </div>
    </div>
</template>
