{{--
    Member-facing proof-of-payment for a PAID participant event.
    Standalone: relies only on the shared event-show Alpine scope
    (see partials/event-show-script.blade.php) — no page-local glue.
    Renders nothing unless the event has a real participant fee ($pPaid).
    Include once inside the event-show Alpine root, near the register CTA.
--}}
@if($pPaid ?? false)
    {{-- Inline hint / trigger — only once the member has a participant spot. --}}
    <div x-show="going" x-cloak class="px-4 sm:px-0 mt-2">
        {{-- Awaiting the club's approval --}}
        <div x-show="paymentPending"
             class="bg-white rounded-2xl shadow-sm border border-amber-200 p-3.5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 grid place-items-center flex-shrink-0">
                <i class="bi bi-hourglass-split text-amber-600 text-lg"></i>
            </div>
            <div class="min-w-0 flex-1 leading-tight">
                <p class="text-sm font-bold text-foreground">{{ __('personal.event_show_payment_pending') }}</p>
                <p class="text-[11px] text-muted-foreground truncate">{{ __('personal.event_show_payment_pending_hint') }}</p>
            </div>
            <button type="button" @click="openProof()"
                    class="m-press text-[11px] font-semibold px-2.5 py-1.5 rounded-full border border-gray-200 text-foreground hover:bg-muted flex-shrink-0">
                <i class="bi bi-arrow-repeat mr-1"></i>{{ __('personal.event_show_payment_replace') }}
            </button>
        </div>

        {{-- Not yet uploaded — invite the member to attach proof (optional) --}}
        <div x-show="!paymentPending"
             class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3.5 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 grid place-items-center flex-shrink-0">
                <i class="bi bi-receipt text-amber-600 text-lg"></i>
            </div>
            <div class="min-w-0 flex-1 leading-tight">
                <p class="text-sm font-bold text-foreground">{{ __('personal.event_show_fee_due', ['fee' => $e['participant_fee']]) }}</p>
                <p class="text-[11px] text-muted-foreground truncate">{{ __('personal.event_show_upload_or_pay_club') }}</p>
            </div>
            <button type="button" @click="openProof()"
                    class="m-press text-[11px] font-semibold px-3 py-1.5 rounded-full text-white flex-shrink-0"
                    style="background: {{ $e['color'] }}">
                <i class="bi bi-upload mr-1"></i>{{ __('personal.event_show_upload_proof') }}
            </button>
        </div>
    </div>

    {{-- Bottom-sheet — teleported to <body> so the fixed overlay anchors to the
         viewport, not the transformed shell content (mobile-forms rule). --}}
    <template x-teleport="body">
        <div x-show="proofOpen" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="closeProof()" style="display:none;">
            <div x-show="proofOpen" x-transition.opacity class="absolute inset-0 bg-black/40" @click="closeProof()"></div>

            <div x-show="proofOpen"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">

                {{-- Header --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 border-b border-gray-100">
                    <div class="w-10 h-1.5 bg-gray-200 rounded-full mx-auto mb-3"></div>
                    <h3 class="text-lg font-bold text-gray-900">{{ __('personal.event_show_proof_title') }}</h3>
                    <p class="text-sm text-muted-foreground truncate">{{ $e['title'] }}</p>
                </div>

                {{-- Scrollable body --}}
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                    <div class="rounded-2xl bg-muted/40 p-4 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{{ __('personal.event_show_entry_fee') }}</p>
                            <p class="text-2xl font-extrabold text-primary mt-0.5 truncate">{{ $e['participant_fee'] }}</p>
                        </div>
                        <div class="w-11 h-11 rounded-xl bg-white grid place-items-center flex-shrink-0 shadow-sm">
                            <i class="bi bi-receipt text-primary text-xl"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('personal.event_show_payment_proof') }}</label>
                        <label class="relative flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-2xl p-6 cursor-pointer hover:border-primary/50 transition-colors overflow-hidden">
                            <template x-if="!proofPreview">
                                <div class="text-center">
                                    <i class="bi bi-camera text-3xl text-gray-300"></i>
                                    <p class="text-sm text-muted-foreground mt-2">{{ __('personal.event_show_proof_tap_add') }}</p>
                                </div>
                            </template>
                            <template x-if="proofPreview">
                                <img :src="proofPreview" class="max-h-56 rounded-xl object-contain" alt="">
                            </template>
                            <input type="file" accept="image/*" class="hidden" @change="pickProof($event)">
                        </label>
                        <p class="text-[11px] text-muted-foreground mt-2">
                            <i class="bi bi-info-circle mr-1"></i>{{ __('personal.event_show_proof_review_note') }}
                        </p>
                    </div>
                </div>

                {{-- Sticky footer (safe-area aware) --}}
                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 flex gap-3"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="closeProof()"
                        class="flex-1 py-3 rounded-xl border border-gray-200 text-gray-700 font-medium active:scale-[.98] transition">
                        {{ __('shared.cancel') }}
                    </button>
                    <button type="button" @click="submitProof()" :disabled="proofSubmitting || !proofData"
                        class="flex-1 py-3 rounded-xl bg-primary text-white font-semibold active:scale-[.98] transition disabled:opacity-60 flex items-center justify-center gap-2">
                        <span x-show="!proofSubmitting"><i class="bi bi-send mr-1"></i>{{ __('personal.event_show_send_review') }}</span>
                        <span x-show="proofSubmitting" class="flex items-center gap-2"><i class="bi bi-arrow-repeat animate-spin"></i>{{ __('personal.event_show_sending') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </template>
@endif
