@extends('entry.layout')

@php $noindex = true; @endphp

{{--
    Entering a competition from the public link — Door C.

    Documentation/EVENTS-PUBLIC-ENTRY.md, Phase C. Opened by somebody who has
    no account and, five minutes ago, had never heard of TAKEONE.

    ONE SCREEN (2026-09-05).
    ------------------------
    It used to be a four-screen wizard: a "have you been here before?" gate, a
    who-are-you screen, a division screen (weight and belt), and the account.
    The gate cost everybody a tap to serve a minority, and the division was
    being guessed by strangers who had not yet decided to compete.

    Worse, the photograph was REQUIRED: a stranger on a phone had to take or
    pick a picture before getting past screen one, and that is where people
    stopped.

    So the door asks for a NAME, a TELEPHONE NUMBER and a PASSWORD, and nothing
    else. Everything else the wizard used to collect — the photograph first of
    all, then date of birth, gender, weight, belt and club — is asked for the
    moment they are IN, on their own entry panel (entry/public/my-entry), which
    can edit all of it at leisure. The endpoint signs them in and hands back the
    address to send them to.

    It wears the same skin as the rest of the standalone event app — the app's
    tokens, the app's hero band, the app's cards — because tapping Enter on the
    poster and landing somewhere that looks like a different product is exactly
    the seam this whole surface exists to remove.

    One file for both breakpoints on purpose. This is a single narrow column at
    every width — the desktop version is the same column with a ceiling on it.

    Nothing typed here is trusted to mean anything. The fee comes from EventFee,
    the division from the event's own package, and the entry itself is worth
    nothing until the organiser accepts it. Every rule is in
    App\Events\Support\PublicEntry.
--}}

@section('body')
@php
    use App\Support\Palette;

    /* The band takes the event's colour DOWN towards navy rather than
       lightening it, so white type sits on it at full contrast. Mixed in PHP by
       Palette, because the design's `color-mix(in oklab, …)` is dropped whole
       by an Android WebView older than Chrome 111 — which would leave this
       header with no background at all. */
    $ev = Palette::safe($e['color']);
    $evDeep = Palette::shade($ev, 82);
    $evFade = Palette::shade($ev, 38);

    /*
        What this event sells beyond the entry itself.

        PublicEvent::payload() carries the fee LINE ('BHD 20') but not the price
        LIST, and the controller is deliberately untouched, so the options are
        read here from the event's own rows through App\Events\Support\EventFee
        — the same class that will re-price the entry when it lands. Nothing
        about the money is ever taken from the browser: the form posts option
        UUIDs and the totals drawn below are for the reader.

        `$feeLateActive` is the SERVER's answer to "is this a late entry" — a
        phone with a wrong clock must not be able to talk itself out of the
        penalty, or agree to a total it will not be charged.

        Every one of these is empty/false for an event with no options and no
        late fee, and each block that uses them is guarded, so that form is the
        form it was before multi-pricing.
    */
    $feeEvent = \App\Models\ClubEvent::where('uuid', $e['uuid'])->first();
    $feeOptions = $feeEvent ? \App\Events\Support\EventFee::options($feeEvent, 'participant') : collect();
    $feeCurrency = $feeEvent ? \App\Events\Support\EventFee::currency($feeEvent) : '';
    $feeCurrencyLabel = $feeCurrency ? \App\Events\Support\EventFee::currencyLabel($feeCurrency) : '';
    $feeBase = $feeEvent ? (float) (\App\Events\Support\EventFee::amount($feeEvent, 'participant') ?? 0) : 0.0;
    $feeLateActive = $feeEvent ? \App\Events\Support\EventFee::lateFeeApplies($feeEvent, 'participant') : false;
    $feeLateAmount = $feeLateActive ? (float) ($feeEvent->late_fee_amount ?? 0) : 0.0;
    $feePrices = $feeOptions->map(fn ($o) => ['key' => $o->uuid, 'amount' => (float) $o->amount])->values()->all();
    $feeHasOptions = $feeOptions->isNotEmpty();
@endphp
{{-- Two mutually exclusive branches, chosen on the SERVER.

     A signed-in member gets one card: who they are, what they compete at, and
     Confirm. The account-building form is for a guest, and showing it to
     somebody who already has a profile is how you get two of the same person on
     one entry list.

     `$me`, `$alreadyIn` and `$alreadyAsked` come from
     PublicEntryController@show — never from the client. --}}
@if($me)
    @include('entry.public.partials.enrol-mine')
@else
<div x-data="enrolFlow()" class="-mx-4 -mt-4">

    {{-- ===== The band =====
         The design file's header (drafts/Enrolment page.png): the event's
         colour taken down towards navy, the back pill, a dash and the screen's
         name in tracked caps, the title, and whose it is.

         No step count and no segments any more: there is one screen, and a
         progress meter that can only ever read "1 / 1" is noise.

         Same departure from Design Rule #6's hero band as the poster it opens
         from, and for the same reason: this is that poster's own flow, not a
         screen inside the app. --}}
    <header class="relative overflow-hidden text-white"
            style="padding: 22px 24px 20px; background: {{ \App\Support\Palette::eventBand($e['color']) }};">
        <div class="absolute rounded-full" style="right:-56px; top:-56px; width:190px; height:190px; background:rgba(255,255,255,.07);"></div>

        {{-- Back is an ADDRESS, not history: a link opened from WhatsApp has
             nothing behind it, so this names the event page itself
             (reference_back_is_an_address). --}}
        <div class="flex items-center justify-between gap-3 relative z-10">
            <a href="{{ route('events.public', ['event' => $e['uuid']]) }}"
               class="m-press inline-flex items-center gap-2 h-10 ps-3 pe-4 rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline"
               aria-label="{{ __('events.public_enrol_back_event') }}" title="{{ __('events.public_enrol_back_event') }}">
                <i class="bi bi-chevron-left"></i>
                <span>{{ __('events.public_enrol_back_event') }}</span>
            </a>
        </div>

        <div class="relative z-10" style="margin-top:22px;">
            <span class="flex items-center" style="gap:10px;">
                <span class="flex-none" style="width:38px; height:3px; border-radius:2px; background:rgba(255,255,255,.85);"></span>
                <span class="uppercase" style="font-size:11px; font-weight:600; letter-spacing:.2em; color:rgba(255,255,255,.85);">{{ __('events.public_enrol_step_entry') }}</span>
            </span>

            <h1 style="margin:12px 0 0; font-size:23px; line-height:1.2; font-weight:700; letter-spacing:-.01em;">{{ $e['title'] }}</h1>

            @if($e['club'])
                <p class="flex items-center" style="margin:9px 0 0; gap:8px; font-size:13px; color:rgba(255,255,255,.82);">
                    <i class="bi bi-building"></i>{{ $e['club'] }}
                </p>
            @endif
        </div>
    </header>

    <div class="mx-auto w-full max-w-lg px-4 -mt-3 relative z-10">

        <div class="pb-[max(7rem,calc(6rem+env(safe-area-inset-bottom)))]">

        {{-- ===== The whole form, on one screen =====
             Three things are asked for and nothing else is demanded: a name,
             because a competitor without one breaks every listing and every
             draw; a telephone number, because it is the account identifier and
             the way the organiser reaches them; and a password, because
             something has to hold the place. --}}
        <div class="e-inR">
            {{-- The design file's card: white, a hairline of the event's colour
                 along the top edge, the question inside it. --}}
            <div style="background:#fff; border-radius:18px; border-top:3px solid {{ $ev }};
                        box-shadow:0 22px 60px rgba(30,44,79,.13), 0 2px 6px rgba(30,44,79,.06);
                        padding:26px 22px;">

                {{-- The minority who already have an account, served in one
                     quiet line instead of a screen everybody had to tap
                     through. It goes to the EVENT's own sign-in, never the
                     platform's — an athlete who arrived from a WhatsApp link
                     must not be handed to a product they have never heard of. --}}
                <p class="text-[12px] text-muted-foreground">
                    {{ __('events.public_enrol_have_account') }}
                    <a href="{{ route('events.public.manage', ['event' => $e['uuid']]) }}"
                       class="font-black no-underline" style="color: {{ $ev }};">{{ __('events.public_enrol_sign_in_link') }}</a>
                </p>

                <h2 class="text-[22px] font-black leading-tight text-foreground mt-3">{{ __('events.public_enrol_one_title') }}</h2>
                <p class="text-[13px] text-muted-foreground mt-1">{{ __('events.public_enrol_one_hint') }}</p>

                <div class="mobile-stagger space-y-3.5 mt-5">
                    {{-- The name, as it goes on the draw. --}}
                    <div>
                        <label class="block text-[12px] font-bold text-foreground mb-1.5" for="entrantName">{{ __('events.public_enrol_name') }}</label>
                        <input id="entrantName" type="text" autocomplete="name" x-model="form.full_name" maxlength="120"
                               placeholder="{{ __('events.public_enrol_name_placeholder') }}"
                               class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                        <template x-if="fieldErrors.full_name">
                            <p class="text-[11.5px] font-bold leading-snug mt-1.5" style="color:#b91c1c;" x-text="fieldErrors.full_name"></p>
                        </template>
                    </div>

                    {{-- The telephone number, and it is REQUIRED (2026-09-04):
                         it is the account identifier now, not the email address
                         — PublicEntryController validates `mobile` and
                         `mobile_code` as required and sign-in matches on the
                         normalised `users.phone_key`. `canContinue` refuses an
                         empty one: a browser that lets somebody past a field the
                         server will reject is the worse bug (CLAUDE.md — the
                         client must agree with the server).

                         The shared dial-code picker (flag, searchable country
                         list) with the number beside it — Component-First, and
                         the same control the rest of the platform uses. `model`
                         mirrors the code into this page's own Alpine state,
                         because the enrolment posts JSON rather than submitting
                         the form; `groupClass` keeps the page's own `.e-field`
                         styling instead of the platform's purple input group.
                         The two still post as the separate `mobile` /
                         `mobile_code` pair the server expects. --}}
                    <div>
                        <label class="block text-[12px] font-bold text-foreground mb-1.5">
                            {{ __('events.public_enrol_phone') }}
                        </label>
                        <x-country-code-dropdown
                            id="entrantDial" name="mobile_code" :value="$guessDial ?: '+973'"
                            model="form.mobile_code"
                            groupClass="e-field w-full rounded-2xl flex items-stretch overflow-hidden">
                            <input type="tel" inputmode="tel" autocomplete="tel"
                                   x-model="form.mobile" maxlength="16"
                                   placeholder="{{ __('events.public_enrol_phone_placeholder') }}"
                                   class="w-full h-12 px-4 bg-transparent text-[15px] focus:outline-none">
                        </x-country-code-dropdown>
                        <p class="text-[11.5px] text-muted-foreground leading-snug mt-1.5">{{ __('events.public_enrol_phone_hint') }}</p>
                        {{-- The server's own words, against the field they
                             belong to. --}}
                        <template x-if="fieldErrors.mobile || fieldErrors.mobile_code">
                            <p class="text-[11.5px] font-bold leading-snug mt-1.5" style="color:#b91c1c;"
                               x-text="fieldErrors.mobile || fieldErrors.mobile_code"></p>
                        </template>
                    </div>

                    {{-- The password. --}}
                    <div>
                        <label class="block text-[12px] font-bold text-foreground mb-1.5" for="entrantPassword">{{ __('events.claim_password') }}</label>
                        <div class="relative">
                            <input id="entrantPassword" :type="reveal ? 'text' : 'password'" autocomplete="new-password" x-model="form.password"
                                   placeholder="{{ __('events.claim_password_hint') }}"
                                   class="e-field w-full h-12 ps-4 pe-12 rounded-2xl text-[15px]">
                            <button type="button" @click="reveal = !reveal" :aria-label="@js(__('events.claim_password'))"
                                    class="absolute end-3 top-1/2 -translate-y-1/2 text-muted-foreground">
                                <i class="bi" :class="reveal ? 'bi-eye-slash' : 'bi-eye'"></i>
                            </button>
                        </div>
                        <template x-if="fieldErrors.password">
                            <p class="text-[11.5px] font-bold leading-snug mt-1.5" style="color:#b91c1c;" x-text="fieldErrors.password"></p>
                        </template>
                    </div>

@if($feeHasOptions || $feeLateActive)
                    {{-- ===== What they are entering =====
                         Selection cards, never a dropdown: a short known list
                         inside a scrolling column, where an absolutely
                         positioned panel is clipped by its own container
                         (Mobile Pattern Language). They wear this page's own
                         .e-pick / .is-on, the same control the signed-in branch
                         uses for its belts and clubs.

                         The running total is a courtesy, not an authority:
                         PublicEntry prices the entry from the event's rows when
                         it arrives, and only UUIDs are posted. --}}
                    <div>
                        @if($feeHasOptions)
                            <label class="block text-[12px] font-bold text-foreground mb-1.5">{{ __('events.fee_select_options') }}</label>

                            <div class="space-y-2">
                                @foreach($feeOptions as $opt)
                                    <button type="button" @click="toggleFee('{{ $opt->uuid }}')"
                                            :class="feeChosen.includes('{{ $opt->uuid }}') ? 'is-on' : ''"
                                            class="m-press e-pick w-full rounded-2xl px-3 py-3 flex items-center gap-3 text-start">
                                        <span class="w-5 h-5 rounded-md border-2 grid place-items-center flex-shrink-0"
                                              :class="feeChosen.includes('{{ $opt->uuid }}') ? 'text-white' : 'border-gray-300'"
                                              :style="feeChosen.includes('{{ $opt->uuid }}') ? 'background: {{ $ev }}; border-color: {{ $ev }}' : ''">
                                            <i class="bi bi-check text-[11px]" x-show="feeChosen.includes('{{ $opt->uuid }}')"></i>
                                        </span>
                                        <span class="min-w-0 flex-1 text-[13px] font-bold text-foreground">{{ $opt->label }}</span>
                                        <span class="text-[12px] font-black flex-shrink-0" style="color: {{ $ev }};">
                                            {{ \App\Events\Support\EventFee::display((float) $opt->amount, $feeCurrency) }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        @if($feeLateActive)
                            {{-- Said plainly, and said BEFORE the total, because
                                 a charge somebody discovers afterwards is the
                                 one that gets disputed at the desk. --}}
                            <div class="m-card rounded-2xl px-4 py-3 flex items-start gap-2.5 mt-3">
                                <i class="bi bi-clock-history mt-0.5" style="color:#b45309;"></i>
                                <p class="text-[12px] text-muted-foreground leading-snug">
                                    {{ __('events.fee_late_applies', ['amount' => \App\Events\Support\EventFee::display($feeLateAmount, $feeCurrency)]) }}
                                </p>
                            </div>
                        @endif

                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                            <span class="text-[12px] font-bold text-muted-foreground">{{ __('events.fee_total') }}</span>
                            <span class="text-[15px] font-black text-foreground" x-text="feeTotalText"></span>
                        </div>
                    </div>
@endif

                    {{-- what happens next, said before they press the button --}}
                    <div class="m-card rounded-2xl px-4 py-3 flex items-start gap-2.5">
                        <i class="bi bi-info-circle-fill mt-0.5" style="color: {{ $e['color'] }}"></i>
                        <p class="text-[12px] text-muted-foreground leading-snug">
                            {{ __('events.public_enrol_review_note') }}
                            @if($e['fee_is_paid']) {{ __('events.public_entry') }}: <span class="font-bold text-foreground">{{ $e['fee'] }}</span>@endif
                        </p>
                    </div>
                </div>
            </div>
        </div>

        </div>
    </div>

    {{-- ===== the action bar — reachable, safe-area padded ===== --}}
    <div class="ev-app-fixed fixed inset-x-0 bottom-0 z-30 px-5 pt-3 bg-white border-t border-gray-100"
         style="padding-bottom: calc(0.9rem + env(safe-area-inset-bottom));">
        <div class="mx-auto w-full max-w-lg">
            <button type="button" @click="submit()" :disabled="!canContinue || saving"
                    class="m-press w-full h-14 rounded-2xl font-black text-[15px] flex items-center justify-center gap-2"
                    {{-- ONE `:class`. There were two, and HTML keeps only the
                         first duplicate attribute — so the second was silently
                         dropped and the enabled button never got its white
                         text, leaving dark type on the event's own fill. --}}
                    :class="[(canContinue && !saving) ? '' : 'opacity-40', canContinue ? 'text-white' : 'text-muted-foreground']"
                    :style="canContinue ? `background: {{ $e['color'] }}; box-shadow: 0 18px 40px -18px {{ $e['color'] }}` : 'background: var(--color-muted, #eceef2)'">
                <span x-text="saving ? '…' : @js(__('events.public_enrol_cta'))"></span>
                <i class="bi bi-arrow-right" x-show="!saving"></i>
            </button>
        </div>
    </div>
</div>
@endif
@endsection

@push('styles')
<style>
    /* Fields on the app's own ground, focused in the EVENT's colour — the one
       place this flow is allowed to differ from a form inside the product, and
       only because the whole page is that event's. */
    .e-field {
        background: #fff;
        border: 1px solid hsl(210 14% 88%);
        color: hsl(220 20% 15%);
    }
    .e-field::placeholder { color: hsl(220 10% 60%); }
    .e-field:focus {
        outline: none;
        border-color: {{ $e['color'] }};
        box-shadow: 0 0 0 4px {{ $e['color'] }}2e;
    }

    /* Selection cards — never a dropdown inside a scrolling column
       (Mobile Pattern Language). Used by the signed-in branch,
       partials/enrol-mine, which shares this stylesheet. */
    .e-pick { border: 1.5px solid hsl(210 14% 88%); background: #fff; }
    .e-pick.is-on {
        border-color: {{ $e['color'] }};
        background: {{ $e['color'] }}14;
        box-shadow: 0 0 0 4px {{ $e['color'] }}1f;
    }

    /* The weight slider on the signed-in branch. */
    input[type=range].e-range { accent-color: {{ $e['color'] }}; }

    /* The card arrives rather than appearing. */
    @keyframes e-inR { from { opacity:0; transform: translate3d(26px,0,0) } to { opacity:1; transform:none } }
    .e-inR { animation: e-inR .42s cubic-bezier(.22,.61,.36,1) both }

    @media (prefers-reduced-motion: reduce) {
        .e-inR { animation: none }
    }
</style>
@endpush

@push('scripts')
<script>
function enrolFlow() {
    return {
        reveal: false,
        saving: false,
        /* Set the moment the entry is accepted, so the unload warning below
           does not fire on our own redirect. */
        sent: false,

        /* Three fields, and that is the whole door. Everything the wizard used
           to ask for — the photograph, the date of birth, the gender, the
           weight, the belt and the club — is asked for on the entry panel they
           land on, which can edit all of it. */
        form: {
            full_name: '',
            mobile: '',
            mobile_code: @js($guessDial ?: '+973'),
            password: '',
        },

@if($feeHasOptions || $feeLateActive)
        /* The extras they ticked, as UUIDs. Never an amount — what an option
           costs is the event's business and is read server-side by
           EventFee::quote(). The price list below is the one this page was
           rendered with, and exists only so the total can be drawn. */
        feeChosen: [],
        feePrices: @js($feePrices),
        feeBase: @js($feeBase),
        feeLate: @js($feeLateAmount),

        toggleFee(key) {
            const i = this.feeChosen.indexOf(key);
            if (i === -1) this.feeChosen.push(key); else this.feeChosen.splice(i, 1);
        },

        /* Mirrors EventFee::quote() deliberately: base + what is ticked + the
           penalty when the server says it is live. Somebody agreeing to one
           number and being charged another is the bug this guards against. */
        get feeTotalText() {
            let total = this.feeBase + this.feeLate;
            for (const o of this.feePrices) {
                if (this.feeChosen.includes(o.key)) total += o.amount;
            }
            return @js($feeCurrencyLabel) + ' ' + total.toFixed(3).replace(/\.?0+$/, '');
        },

@endif
        /* What the SERVER said about a single field, keyed by its name, so a
           422 lands against the box it belongs to instead of only in the
           notice at the bottom of the screen. Cleared on every submit. */
        fieldErrors: {},

        /* Exactly the server's own rules, no stricter: a name of two
           characters, a non-empty number with a well-formed dial code, and
           eight characters of password. Nothing else may block the button — a
           browser that refuses what the endpoint would accept is the worse bug
           (CLAUDE.md — the client must agree with the server). */
        get canContinue() {
            if (this.form.full_name.trim().length < 2) return false;

            const mobile = (this.form.mobile || '').trim();
            const code = (this.form.mobile_code || '').trim();

            if (mobile.replace(/\D/g, '').length === 0) return false;
            if (! /^\+[0-9]{1,6}$/.test(code)) return false;

            return this.form.password.length >= 8;
        },

        init() {
            /* Half a form is worth a warning. Browsers show their own wording;
               all that matters is that the tab does not close silently on
               somebody part-way through. Never on our own redirect. */
            window.addEventListener('beforeunload', (ev) => {
                if (this.sent || this.saving) return;
                if (! this.form.full_name.trim() && ! this.form.mobile.trim() && ! this.form.password) return;
                ev.preventDefault();
                ev.returnValue = '';
            });
        },

        async submit() {
            if (! this.canContinue || this.saving) return;

            this.saving = true;
            this.fieldErrors = {};
            try {
                const res = await fetch(@js(route('events.public.enter.store', ['event' => $e['uuid']])), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        full_name: this.form.full_name,
                        password: this.form.password,
                        mobile: this.form.mobile || null,
                        mobile_code: this.form.mobile_code || null,
@if($feeHasOptions)
                        fee_options: this.feeChosen,
@endif
                    }),
                });
                const d = await res.json().catch(() => ({}));

                /* A 422 comes back in one of two shapes: Laravel's validation
                   bag (`errors: {field: [msg]}`) or this endpoint's own
                   `{message, field}`. Both are put against the field they name
                   so the person can see WHICH box to fix, and the notice still
                   says it out loud for anything that names no field at all. */
                if (!res.ok || !d.success) {
                    if (d.errors && typeof d.errors === 'object') {
                        for (const [key, list] of Object.entries(d.errors)) {
                            this.fieldErrors[key] = Array.isArray(list) ? list[0] : String(list);
                        }
                    }
                    if (d.field && d.message) this.fieldErrors[d.field] = d.message;

                    throw new Error(d.message || @js(__('events.public_enrol_closed')));
                }

                /* The endpoint signed them in and said where to go: their own
                   entry panel, which is where the photograph and everything
                   else this door no longer asks for is collected. The address
                   comes from the SERVER, never from anything typed here. */
                this.sent = true;
                window.location.href = d.redirect || @js(route('events.public', ['event' => $e['uuid']]));
            } catch (e) {
                this.saving = false;
                notice(e.message);
            }
        },
    };
}

/* Outside the app shell, so window.showToast does not exist here. One small
   on-palette notice instead of a native dialog. */
function notice(msg) {
    const n = document.createElement('div');
    n.textContent = msg;
    n.style.cssText = 'position:fixed;left:1rem;right:1rem;bottom:calc(6rem + env(safe-area-inset-bottom));z-index:60;'
        + 'background:#111827;color:#fff;font-size:12.5px;line-height:1.4;padding:.85rem 1rem;border-radius:1rem;'
        + 'box-shadow:0 20px 40px -20px rgba(0,0,0,.7);transition:opacity .3s,transform .3s;transform:translateY(8px);opacity:0';
    document.body.appendChild(n);
    requestAnimationFrame(() => { n.style.opacity = '1'; n.style.transform = 'none'; });
    setTimeout(() => { n.style.opacity = '0'; n.style.transform = 'translateY(8px)'; setTimeout(() => n.remove(), 320); }, 3600);
}
</script>
@endpush
