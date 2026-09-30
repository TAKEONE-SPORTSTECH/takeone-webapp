@extends('events::entry.layout')

@php $noindex = true; @endphp

{{--
    Signing in as an ATHLETE, on the event's own page.

    The entry form's "Already have an account? Sign in" used to open the
    organiser's console sign-in ("only the organiser and the officials they
    appointed…"), and returning athletes took that as "not for me" and made a
    second account. This is the same sign-in — it posts to
    PublicEventController@signIn, which hands straight to the platform's one
    login, so the lockout, the verification bounce and two-factor are all the
    platform's own — worded for the athlete.

    Two faces of one card: the password, and the entry desk's code for when the
    password is forgotten (App\Events\Support\DeskSignInCode). Most athletes
    enter with a phone and no email, and there is no SMS, so the desk is the
    way back in — never a second account.

    One file for both breakpoints: a single narrow column at every width, like
    the enrolment flow and the organiser's sign-in it sits beside.
--}}

@section('body')
@php
    use App\Support\Palette;
    $ev = Palette::safe($e['color']);
    $evDeep = Palette::shade($ev, 82);
    $evFade = Palette::shade($ev, 38);
@endphp

<div class="-mx-4 -mt-4" x-data="athleteSignIn()" x-init="face = location.hash === '#code' ? 'code' : 'password'">

    <header class="relative overflow-hidden text-white"
            style="padding: 22px 24px 34px; background: {{ Palette::eventBand($e['color']) }};">
        <div class="absolute rounded-full" style="right:-56px; top:-56px; width:190px; height:190px; background:rgba(255,255,255,.07);"></div>
        <div class="absolute rounded-full" style="right:22px; bottom:18px; width:96px; height:96px; background:rgba(255,255,255,.06);"></div>

        <div class="relative z-10 flex items-center justify-between gap-3">
            <a href="{{ $open ? route('events.public.enter', $e['key']) : route('me.events.show', $e['key']) }}"
               class="m-press inline-flex items-center w-10 h-10 justify-center rounded-full text-white text-sm font-semibold"
               style="border:1px solid rgba(255,255,255,.3); background:rgba(255,255,255,.14);"
               aria-label="{{ __('events::events.athlete_signin_back') }}" title="{{ __('events::events.athlete_signin_back') }}">
                <i class="bi bi-chevron-left"></i>
            </a>
        </div>

        <div class="relative z-10" style="margin-top:22px;">
            <span class="flex items-center" style="gap:10px;">
                <span class="flex-none" style="width:38px; height:3px; border-radius:2px; background:rgba(255,255,255,.85);"></span>
                <span class="uppercase" style="font-size:11px; font-weight:600; letter-spacing:.2em; color:rgba(255,255,255,.85);">
                    {{ __('events::events.athlete_signin_eyebrow') }}
                </span>
            </span>
            <h1 style="margin:12px 0 0; font-size:23px; line-height:1.2; font-weight:700; letter-spacing:-.01em;">{{ $e['title'] }}</h1>
            @if($e['club'])
                <p class="flex items-center" style="margin:9px 0 0; gap:8px; font-size:13px; color:rgba(255,255,255,.82);">
                    <i class="bi bi-building"></i>{{ $e['club'] }}
                </p>
            @endif
        </div>
    </header>

    <div class="mx-auto w-full max-w-md px-4 -mt-4 relative z-10"
         style="padding-bottom: calc(3rem + env(safe-area-inset-bottom));">

        @if($who)
            {{-- Already signed in: a door on, never a redirect (Back must render). --}}
            <div class="rounded-3xl p-5" style="background:#fff; border:1px solid hsl(210 14% 88%); box-shadow: 0 24px 60px -34px rgba(15,23,42,.45);">
                <span class="w-12 h-12 rounded-2xl grid place-items-center text-white" style="background: linear-gradient(150deg, {{ $evDeep }}, {{ $evFade }});">
                    <i class="bi bi-person-check-fill text-xl"></i>
                </span>
                <h2 class="text-[19px] font-black text-foreground" style="margin:14px 0 0;">{{ __('events::events.athlete_signin_already', ['name' => $who]) }}</h2>
                <a href="{{ $open ? route('events.public.enter', $e['key']) : route('me.events.show', $e['key']) }}"
                   class="m-press mt-4 w-full h-12 rounded-2xl font-black text-[14px] text-white flex items-center justify-center gap-2 no-underline"
                   style="background: {{ $e['color'] }}; box-shadow: 0 18px 40px -18px {{ $e['color'] }};">
                    {{ $open ? __('events::events.athlete_signin_continue_enter') : __('events::events.athlete_signin_continue_event') }}<i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <form method="POST" action="{{ route('events.public.sign-out', ['event' => $e['key']]) }}" style="margin-top:16px;">
                @csrf
                <button type="submit" class="m-press w-full h-11 rounded-2xl text-[12.5px] font-bold flex items-center justify-center gap-2"
                        style="background:#fff; border:1px solid hsl(210 14% 88%); color: hsl(220 10% 45%);">
                    <i class="bi bi-box-arrow-right"></i>{{ __('events::events.public_sign_out', ['name' => $who]) }}
                </button>
            </form>
        @else

        {{-- ===== Face 1: the password ===== --}}
        <div x-show="face === 'password'" class="rounded-3xl p-5" style="background:#fff; border:1px solid hsl(210 14% 88%); box-shadow: 0 24px 60px -34px rgba(15,23,42,.45);">
            <span class="w-12 h-12 rounded-2xl grid place-items-center text-white" style="background: linear-gradient(150deg, {{ $evDeep }}, {{ $evFade }});">
                <i class="bi bi-person-fill text-xl"></i>
            </span>
            <h2 class="text-[19px] font-black text-foreground" style="margin:14px 0 0; letter-spacing:-.01em;">{{ __('events::events.athlete_signin_title') }}</h2>
            <p class="text-[12.5px] text-muted-foreground leading-snug mt-1.5">{{ __('events::events.athlete_signin_sub') }}</p>

            <form method="POST" action="{{ route('events.public.manage.signin', $e['key']) }}" class="mt-4 space-y-2.5">
                @csrf
                @if($errors->any())
                    <div class="rounded-2xl px-3.5 py-3 text-[12px] font-semibold flex items-start gap-2" style="background:#fef2f2; border:1px solid #fecaca; color:#b91c1c;">
                        <i class="bi bi-exclamation-circle-fill mt-0.5 flex-shrink-0"></i><span>{{ $errors->first() }}</span>
                    </div>
                @endif
                @foreach (['warning', 'error', 'status'] as $flash)
                    @if(session($flash))
                        <div class="rounded-2xl px-3.5 py-3 text-[12px] font-semibold flex items-start gap-2" style="background:#fffbeb; border:1px solid #fde68a; color:#92400e;">
                            <i class="bi bi-info-circle-fill mt-0.5 flex-shrink-0"></i><span>{{ session($flash) }}</span>
                        </div>
                    @endif
                @endforeach

                <div>
                    <label for="ath-login" class="block text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('events::events.athlete_signin_phone') }}</label>
                    {{-- Not type="tel" alone: the platform's login also takes an email. --}}
                    <input id="ath-login" name="email" type="text" inputmode="tel" autocomplete="username" autocapitalize="none" spellcheck="false" required
                           value="{{ old('email') }}" placeholder="{{ __('events::events.athlete_signin_phone_ph') }}"
                           class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                </div>
                <div x-data="{ show: false }">
                    <label for="ath-password" class="block text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('events::events.public_manage_password') }}</label>
                    <div class="relative">
                        <input id="ath-password" name="password" :type="show ? 'text' : 'password'" autocomplete="current-password" required
                               class="e-field w-full h-12 ps-4 pe-12 rounded-2xl text-[15px]">
                        <button type="button" @click="show = ! show" :aria-label="show ? @js(__('events::events.public_manage_hide')) : @js(__('events::events.public_manage_show'))"
                                class="absolute inset-y-0 end-0 w-12 grid place-items-center text-muted-foreground">
                            <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="m-press w-full h-14 rounded-2xl font-black text-[15px] text-white flex items-center justify-center gap-2"
                        style="margin-top:14px; background: {{ $e['color'] }}; box-shadow: 0 18px 40px -18px {{ $e['color'] }};">
                    {{ $open ? __('events::events.athlete_signin_cta_enter') : __('events::events.public_manage_signin') }}<i class="bi bi-arrow-right"></i>
                </button>
            </form>

            <button type="button" @click="face = 'code'; history.replaceState(null, '', '#code')"
                    class="block w-full text-center text-[12.5px] font-semibold mt-3.5" style="color: {{ $e['color'] }};">
                {{ __('events::events.athlete_signin_forgot') }}
            </button>
        </div>

        {{-- ===== Face 2: the entry desk's code ===== --}}
        <div x-show="face === 'code'" x-cloak class="rounded-3xl p-5" style="background:#fff; border:1px solid hsl(210 14% 88%); box-shadow: 0 24px 60px -34px rgba(15,23,42,.45);">
            <span class="w-12 h-12 rounded-2xl grid place-items-center text-white" style="background: linear-gradient(150deg, {{ $evDeep }}, {{ $evFade }});">
                <i class="bi bi-key-fill text-xl"></i>
            </span>
            <h2 class="text-[19px] font-black text-foreground" style="margin:14px 0 0; letter-spacing:-.01em;">{{ __('events::events.desk_code_title') }}</h2>
            <p class="text-[12.5px] text-muted-foreground leading-snug mt-1.5">{{ __('events::events.desk_code_sub') }}</p>

            <div class="mt-4 space-y-3">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('events::events.athlete_signin_phone_only') }}</label>
                    <x-fields.phone-code id="codeDial" name="mobile_code" :value="$guessDial" model="form.mobile_code"
                                         groupClass="e-field w-full rounded-2xl flex items-stretch overflow-hidden">
                        <input type="tel" inputmode="tel" autocomplete="tel" x-model="form.mobile" maxlength="16"
                               placeholder="{{ __('events::events.public_enrol_phone_placeholder') }}"
                               class="w-full h-12 px-4 bg-transparent text-[15px] focus:outline-none">
                    </x-fields.phone-code>
                </div>
                <div>
                    <label for="desk-code" class="block text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('events::events.desk_code_label') }}</label>
                    <input id="desk-code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" x-model="form.code"
                           placeholder="000000" class="e-field w-full h-14 px-4 rounded-2xl text-center font-black tabular-nums" style="font-size:24px; letter-spacing:.35em;">
                    <p x-show="err.code" x-cloak class="text-[11.5px] font-bold leading-snug mt-1.5" style="color:#b91c1c;" x-text="err.code"></p>
                </div>
                <div>
                    <label for="desk-new" class="block text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('events::events.desk_code_new_password') }}</label>
                    <input id="desk-new" type="password" autocomplete="new-password" x-model="form.password"
                           placeholder="{{ __('events::events.claim_password_hint') }}" class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                    <p x-show="err.password" x-cloak class="text-[11.5px] font-bold leading-snug mt-1.5" style="color:#b91c1c;" x-text="err.password"></p>
                </div>
                <p x-show="err.general" x-cloak class="rounded-2xl px-3.5 py-3 text-[12px] font-semibold" style="background:#fef2f2; border:1px solid #fecaca; color:#b91c1c;" x-text="err.general"></p>
                <button type="button" @click="redeem()" :disabled="!ready || busy"
                        class="m-press w-full h-14 rounded-2xl font-black text-[15px] text-white flex items-center justify-center gap-2 disabled:opacity-40"
                        style="background: {{ $e['color'] }}; box-shadow: 0 18px 40px -18px {{ $e['color'] }};">
                    <span x-text="busy ? '…' : @js(__('events::events.desk_code_cta'))"></span><i class="bi bi-arrow-right" x-show="!busy"></i>
                </button>
            </div>

            <button type="button" @click="face = 'password'; history.replaceState(null, '', location.pathname)"
                    class="block w-full text-center text-[12.5px] font-semibold mt-3.5" style="color: {{ $e['color'] }};">
                {{ __('events::events.desk_code_back') }}
            </button>
        </div>
        @endif
    </div>
</div>
@endsection

@push('styles')
<style>
    .e-field { background:#fff; border:1px solid hsl(210 14% 88%); color:hsl(220 20% 15%); }
    .e-field::placeholder { color:hsl(220 10% 60%); }
    .e-field:focus { outline:none; border-color: {{ $e['color'] }}; box-shadow: 0 0 0 4px {{ $e['color'] }}2e; }
</style>
@endpush

@push('scripts')
<script>
function athleteSignIn() {
    return {
        face: 'password', busy: false, err: {},
        form: { mobile: '', mobile_code: @js($guessDial), code: '', password: '' },
        get ready() {
            return this.form.mobile.replace(/\D/g, '').length >= 4
                && this.form.code.replace(/\D/g, '').length === 6
                && this.form.password.length >= 8;
        },
        async redeem() {
            if (!this.ready || this.busy) return;
            this.busy = true; this.err = {};
            try {
                const r = await fetch(@js(route('events.public.sign-in.code', ['event' => $e['key']])), {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''},
                    body: JSON.stringify(this.form),
                });
                const d = await r.json().catch(() => ({}));
                if (!r.ok || !d.success) {
                    if (d.errors) { for (const [k, v] of Object.entries(d.errors)) this.err[k] = Array.isArray(v) ? v[0] : String(v); }
                    else if (d.field) { this.err[d.field] = d.message; }
                    else { this.err.general = r.status === 429 ? @js(__('events::events.desk_code_slow_down')) : (d.message || @js(__('events::events.desk_code_wrong'))); }
                    this.busy = false;
                    return;
                }
                window.location.href = d.redirect || @js(route('me.events.show', ['event' => $e['key']]));
            } catch (e) {
                this.err.general = @js(__('events::events.desk_code_offline'));
                this.busy = false;
            }
        },
    };
}
</script>
@endpush
