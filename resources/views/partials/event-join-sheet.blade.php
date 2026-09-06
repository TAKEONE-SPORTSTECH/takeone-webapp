{{--
    Join sheet — what it costs, how to pay, and the choice to settle later.

    Tapping Participate or Spectate on a PAID event opens this instead of
    registering straight away. A confirm dialog could only say "the fee is due at
    the club"; an athlete standing in a car park needs the account number.

    Paying is never a condition of holding your place: "Pay later" registers you
    exactly as "I've paid" does. The difference is only whether the proof sheet
    opens next, and the organiser sees the same amber "claimed" chip either way
    until an official verifies it.

    Free entry never reaches here — startJoin() registers directly.

    Expects: $e, $payment (from PersonalEventController::paymentInstructions).
--}}
<template x-teleport="body" data-teleport-template="true">
    <div x-show="joinOpen" x-cloak class="fixed inset-0 z-[70] flex items-end sm:items-center sm:justify-center sm:p-4"
         @keydown.escape.window="closeJoin()" style="display:none;">
        <div x-show="joinOpen" x-transition.opacity class="absolute inset-0 bg-black/40" @click="closeJoin()"></div>

        {{-- A bottom sheet on a phone, a centred dialog from `sm` up. Desktop
             includes this same partial, and a sheet pinned to the bottom of a
             1440px window reads as a stuck toolbar rather than a decision. Same
             idiom <x-qr-code> already uses, so the two behave alike. --}}
        <div x-show="joinOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0" x-transition:enter-end="translate-y-0 sm:opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 sm:opacity-100" x-transition:leave-end="translate-y-full sm:translate-y-4 sm:opacity-0"
             class="relative w-full sm:max-w-md max-h-[92vh] sm:max-h-[85vh] flex flex-col bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl">

            {{-- Handle + what you are joining --}}
            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl sm:rounded-t-2xl text-white relative overflow-hidden"
                 style="background: linear-gradient(150deg, {{ $e['color'] }}, {{ $e['color'] }}b0);">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi bi-ticket-perforated text-xl"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-black leading-tight"
                            x-text="joinMode === 'settle'
                                ? '{{ __('personal.event_show_join_settle_title') }}'
                                : (joinRole === 'spectator'
                                    ? '{{ __('personal.event_show_spectator_ticket') }}'
                                    : '{{ __('personal.event_show_join_participant') }}')"></h3>
                        <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $e['title'] }}</p>
                    </div>
                    <button type="button" @click="closeJoin()" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">

                {{-- WHO you are entering as. A competitor at an open event
                     represents a club, and that is decided here — at the moment
                     of entering — rather than left to a card further down the
                     page that someone may never scroll to. Pre-selected with the
                     club where they last practised this sport, so the common
                     case is a glance rather than a decision. --}}
                @if(($representing['ask'] ?? false))
                    <div x-show="joinRole === 'participant'" x-cloak>
                        <p class="text-sm font-bold text-foreground mb-1">{{ __('personal.event_show_representing_title') }}</p>
                        <p class="text-[11px] text-muted-foreground leading-snug mb-2">{{ __('personal.event_show_representing_hint') }}</p>
                        @include('partials.event-representing-cards')
                    </div>
                @endif

                {{-- ===== What they are entering =====

                     Selection cards rather than a dropdown, per the Mobile
                     Pattern Language: a short, known set of choices belongs on
                     the surface where a thumb can reach it, and an absolutely
                     positioned panel inside this scrolling body would be clipped
                     by it anyway.

                     Ticking any number is the whole point — Gi AND No-Gi is a
                     competitor entering two divisions, not a mistake — so these
                     are checkboxes, and the amount above adds up as they go. --}}
                <div x-show="feeOptions().length" x-cloak>
                    <p class="text-sm font-bold text-foreground mb-2">{{ __('events.fee_select_options') }}</p>

                    <div class="space-y-2">
                        <template x-for="opt in feeOptions()" :key="opt.key">
                            <button type="button" @click="toggleFeeOption(opt.key)"
                                    :class="chosenOptions.includes(opt.key) ? 'border-primary bg-primary/5' : 'border-gray-200'"
                                    class="m-press w-full rounded-xl border-2 p-3 flex items-center gap-3 text-start transition-colors">
                                <span class="w-6 h-6 rounded-md border-2 grid place-items-center flex-shrink-0 transition-colors"
                                      :class="chosenOptions.includes(opt.key) ? 'bg-primary border-primary text-white' : 'border-gray-300 bg-white'">
                                    <i class="bi bi-check-lg text-[13px] font-black" x-show="chosenOptions.includes(opt.key)" x-cloak></i>
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-[13px] font-bold text-foreground truncate" x-text="opt.label"></span>
                                </span>
                                <span class="text-[13px] font-bold text-primary flex-shrink-0" x-text="opt.display"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- The penalty, said before they commit rather than discovered
                     on the total. It is charged by the server on its own clock,
                     so this is a warning, never the decision. --}}
                <div x-show="fees.late_active && joinRole === 'participant'" x-cloak
                     class="rounded-xl p-3 flex items-start gap-2 text-[12px] font-semibold"
                     style="background: #fef3c7; color: #92400e;">
                    <i class="bi bi-clock-history mt-0.5"></i>
                    <span x-text="lateFeeNote()"></span>
                </div>

                {{-- The amount, stated once and plainly. A free place has no
                     amount and no method: the sheet is then only the club
                     question and a confirm.

                     `joinTotal` rather than the event's headline: with options
                     ticked the headline is the floor, and showing a competitor
                     a number they are not about to be charged is how billing
                     arguments start. --}}
                <div x-show="joinFee" x-cloak class="rounded-2xl bg-muted/40 p-4 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                            {{ __('personal.event_show_join_amount_due') }}
                        </p>
                        <p class="text-2xl font-extrabold text-primary mt-0.5 truncate" x-text="joinTotal()"></p>
                        {{-- The breakdown, so the number can always be taken
                             apart by the person paying it. --}}
                        <p class="text-[11px] text-muted-foreground mt-0.5 truncate" x-show="feeBreakdown()" x-cloak x-text="feeBreakdown()"></p>
                    </div>
                    <div class="w-11 h-11 rounded-xl bg-white grid place-items-center flex-shrink-0 shadow-sm">
                        <i class="bi bi-cash-coin text-primary text-xl"></i>
                    </div>
                </div>

                {{-- Choose a method. No gateway exists, so 'online' means a
                     transfer the club verifies — not a card charge. --}}
                <div x-show="joinFee" x-cloak>
                    <p class="text-sm font-bold text-foreground mb-2">{{ __('personal.event_show_join_how_to_pay') }}</p>

                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <button type="button" @click="payMethod = 'online'"
                                :class="payMethod === 'online' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-foreground'"
                                class="rounded-xl border-2 p-3 text-start transition-colors">
                            <i class="bi bi-bank text-lg"></i>
                            <p class="text-[13px] font-bold mt-1">{{ __('personal.event_show_pay_online') }}</p>
                            <p class="text-[10px] text-muted-foreground leading-snug">{{ __('personal.event_show_pay_online_hint') }}</p>
                        </button>
                        <button type="button" @click="payMethod = 'cash'"
                                :class="payMethod === 'cash' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-foreground'"
                                class="rounded-xl border-2 p-3 text-start transition-colors">
                            <i class="bi bi-cash-stack text-lg"></i>
                            <p class="text-[13px] font-bold mt-1">{{ __('personal.event_show_pay_cash') }}</p>
                            <p class="text-[10px] text-muted-foreground leading-snug">{{ __('personal.event_show_pay_cash_hint') }}</p>
                        </button>
                    </div>

                    {{-- Cash: nothing to copy, just where to hand it over. --}}
                    <div x-show="payMethod === 'cash'" x-cloak
                         class="rounded-xl border border-gray-200 p-3 flex items-start gap-2.5">
                        <i class="bi bi-geo-alt text-primary mt-0.5"></i>
                        <p class="text-[13px] text-muted-foreground leading-snug">
                            {{ __('personal.event_show_join_pay_at_club', ['club' => $payment['club'] ?? '']) }}
                        </p>
                    </div>

                    <div x-show="payMethod === 'online'" x-cloak>

                    @if(!empty($payment['bank']))
                        <ol class="space-y-3">
                            <li class="flex gap-3">
                                <span class="w-6 h-6 rounded-full bg-primary/10 text-primary text-[11px] font-black grid place-items-center flex-shrink-0">1</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[13px] font-semibold text-foreground">{{ __('personal.event_show_join_step_transfer') }}</p>
                                    <div class="mt-2 rounded-xl border border-gray-200 divide-y divide-gray-100">
                                        @foreach($payment['bank'] as $label => $value)
                                            <div class="flex items-center gap-2 px-3 py-2">
                                                <span class="text-[11px] text-muted-foreground w-24 flex-shrink-0">{{ __('personal.event_show_bank_'.$label) }}</span>
                                                <span class="text-[12px] font-bold text-foreground truncate flex-1" id="pay-{{ $label }}">{{ $value }}</span>
                                                {{-- Copy: an IBAN retyped by hand is an IBAN typed wrong. --}}
                                                <button type="button" @click="copyValue('{{ $label }}')"
                                                        class="w-7 h-7 rounded-lg grid place-items-center text-muted-foreground hover:bg-muted flex-shrink-0"
                                                        aria-label="{{ __('personal.event_show_join_copy') }}">
                                                    <i class="bi bi-clipboard text-xs"></i>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </li>
                            <li class="flex gap-3">
                                <span class="w-6 h-6 rounded-full bg-primary/10 text-primary text-[11px] font-black grid place-items-center flex-shrink-0">2</span>
                                <p class="text-[13px] text-muted-foreground leading-snug pt-0.5">{{ __('personal.event_show_join_step_receipt') }}</p>
                            </li>
                            <li class="flex gap-3">
                                <span class="w-6 h-6 rounded-full bg-primary/10 text-primary text-[11px] font-black grid place-items-center flex-shrink-0">3</span>
                                <p class="text-[13px] text-muted-foreground leading-snug pt-0.5">{{ __('personal.event_show_join_step_verify') }}</p>
                            </li>
                        </ol>
                    @else
                        {{-- No bank details on file — most clubs take it at the door. --}}
                        <div class="rounded-xl border border-gray-200 p-3 flex items-start gap-2.5">
                            <i class="bi bi-cash text-primary mt-0.5"></i>
                            <p class="text-[13px] text-muted-foreground leading-snug">
                                {{ __('personal.event_show_join_pay_at_club', ['club' => $payment['club'] ?? '']) }}
                            </p>
                        </div>
                    @endif
                    </div>
                </div>

                <p x-show="joinFee" x-cloak class="text-[11px] text-muted-foreground flex items-start gap-1.5">
                    <i class="bi bi-info-circle mt-0.5"></i>
                    <span>{{ __('personal.event_show_join_place_held') }}</span>
                </p>
            </div>

            {{-- The action depends on the method chosen. Nothing is committed until
                 one of these is pressed. --}}
            <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 space-y-2"
                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">

                {{-- Online: they say they have transferred it, and upload a receipt. --}}
                <button type="button" x-show="payMethod === 'online'" x-cloak
                        @click="finishJoin(true)" :disabled="busy"
                        class="m-press w-full py-3 rounded-xl bg-primary text-white font-bold text-sm
                               flex items-center justify-center gap-2 active:scale-[.98] transition disabled:opacity-60">
                    <i class="bi bi-upload"></i>
                    <span x-text="busy ? '{{ __('personal.event_show_join_working') }}' : '{{ __('personal.event_show_join_paid_upload') }}'"></span>
                </button>

                {{-- Cash: nothing to upload; the fee stays due until the club takes it. --}}
                <button type="button" x-show="payMethod === 'cash'" x-cloak
                        @click="finishJoin(false)" :disabled="busy"
                        class="m-press w-full py-3 rounded-xl bg-primary text-white font-bold text-sm
                               flex items-center justify-center gap-2 active:scale-[.98] transition disabled:opacity-60">
                    <i class="bi bi-check2"></i>
                    <span x-text="busy ? '{{ __('personal.event_show_join_working') }}' : '{{ __('personal.event_show_join_cash_confirm') }}'"></span>
                </button>

                {{-- Free entry: nothing to pay, so the sheet's only job was the
                     club question and this. --}}
                <button type="button" x-show="! joinFee" x-cloak
                        @click="finishJoin(false)" :disabled="busy"
                        class="m-press w-full py-3 rounded-xl bg-primary text-white font-bold text-sm
                               flex items-center justify-center gap-2 active:scale-[.98] transition disabled:opacity-60">
                    <i class="bi bi-check2"></i>
                    <span x-text="busy ? '{{ __('personal.event_show_join_working') }}' : '{{ __('personal.event_show_im_in') }}'"></span>
                </button>

                {{-- Taking a place without deciding how to pay. Not offered when
                     settling: they already have the place, so this would be a
                     button that does nothing. --}}
                <button type="button" x-show="joinFee && joinMode !== 'settle'"
                        @click="finishJoin(false)" :disabled="busy"
                        class="m-press w-full py-3 rounded-xl border border-gray-200 text-foreground font-semibold text-sm
                               active:scale-[.98] transition disabled:opacity-60">
                    {{ __('personal.event_show_join_pay_later') }}
                </button>

                <button type="button" @click="closeJoin()" :disabled="busy"
                        class="w-full py-2 text-[12px] font-semibold text-muted-foreground">
                    {{ __('shared.cancel') }}
                </button>
            </div>
        </div>
    </div>
</template>
