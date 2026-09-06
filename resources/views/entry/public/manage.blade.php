@extends('entry.layout')

@php $noindex = true; @endphp

{{--
    Signing in to RUN this event, without leaving it.

    The gear on the poster lands here. Everything a stranger sees on this
    surface is the event and only the event — its name, its mark, its colour,
    installable on a home screen with no platform anywhere on it — and the
    person running the competition was the one exception, sent out to a login
    page belonging to a product they never mentioned to anybody. On an event
    morning, on a phone, that is where organisers get lost.

    So this is the platform's login wearing the event's skin. ⚠️ The SKIN is all
    that differs: the form posts to PublicEventController@signIn, which hands
    the credentials straight to AuthenticatedSessionController@store. The
    lockout counter, the throttle, the unverified-address bounce, the
    two-factor challenge and the activity log are the same ones /login uses,
    because they are literally the same code.

    One file for both breakpoints, like the enrolment flow: this is a single
    narrow column at every width — the desktop reading is the same column with
    a ceiling on it, not a different layout.

    It names nobody. Who runs this event is never on the page, a wrong password
    says exactly what a wrong password says at /login, and the gear is offered
    to every reader because offering it only to organisers would tell a stranger
    who the organisers are.
--}}

@section('body')
@php
    use App\Support\Palette;

    /* The event's colour taken DOWN towards navy rather than lightened, so white
       type sits on it at full contrast — and mixed in PHP, because an Android
       WebView older than Chrome 111 drops a `color-mix()` declaration whole and
       would leave this band with no background at all. */
    $ev = Palette::safe($e['color']);
    $evDeep = Palette::shade($ev, 82);
    $evFade = Palette::shade($ev, 38);
@endphp

<div class="-mx-4 -mt-4">

    {{-- ===== The band =====
         The poster's own header, shortened: the same departure from Design
         Rule #6 as the page this opened from, because it belongs to that
         poster rather than to a screen inside the app. --}}
    <header class="relative overflow-hidden text-white"
            style="padding: 22px 24px 34px; background: {{ \App\Support\Palette::eventBand($e['color']) }};">
        <div class="absolute rounded-full" style="right:-56px; top:-56px; width:190px; height:190px; background:rgba(255,255,255,.07);"></div>
        <div class="absolute rounded-full" style="right:22px; bottom:18px; width:96px; height:96px; background:rgba(255,255,255,.06);"></div>

        {{-- Back is the round 40px control holding a TAIL-LESS chevron and no
             words (Design Rule #6, 2026-09-04). Where it goes is in its
             aria-label / title — here, the event. --}}
        <div class="relative z-10 flex items-center justify-between gap-3">
            <a href="{{ route('events.public', $e['key']) }}"
               class="m-press inline-flex items-center w-10 h-10 justify-center rounded-full text-white text-sm font-semibold"
               style="border:1px solid rgba(255,255,255,.3); background:rgba(255,255,255,.14);"
           aria-label="{{ __('events.public_manage_back') }}" title="{{ __('events.public_manage_back') }}">
                <i class="bi bi-chevron-left"></i>
            </a>
        </div>

        <div class="relative z-10" style="margin-top:22px;">
            <span class="flex items-center" style="gap:10px;">
                <span class="flex-none" style="width:38px; height:3px; border-radius:2px; background:rgba(255,255,255,.85);"></span>
                <span class="uppercase" style="font-size:11px; font-weight:600; letter-spacing:.2em; color:rgba(255,255,255,.85);">
                    {{ __('events.public_manage_eyebrow') }}
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

        {{-- Signed in, but not as anybody who runs this. Said before the form
             rather than after a failed attempt: the credentials are not the
             problem, the account is, and typing the same password again is the
             thing this note exists to prevent. --}}
        @if($wrongAccount)
            <div class="rounded-2xl p-4 mb-3" style="background:#fff; border:1px solid hsl(210 14% 88%);">
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                          style="background: {{ $e['color'] }}1f; color: {{ $e['color'] }};">
                        <i class="bi bi-person-exclamation text-lg"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-black text-foreground">{{ __('events.public_manage_wrong_account') }}</p>
                        <p class="text-[11.5px] text-muted-foreground leading-snug mt-0.5">
                            {{ __('events.public_manage_wrong_account_hint', ['name' => $who ?: __('shared.unknown')]) }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- ===== Already signed in as somebody who runs this =====

             A DOOR, not a redirect. This page used to 302 a manager straight to
             the console, which left a forward-redirect in the history stack: the
             Back button landed here and was thrown forward again, so the
             organiser could never step back to the poster. A page a person can
             reach by pressing Back has to render.

             The normal path does not come through here at all — the poster's
             gear links a manager straight to the console, and signing in lands
             there directly. This is what Back, a bookmark or a typed address
             finds. --}}
        @if($console ?? null)
            <div class="rounded-3xl p-5" style="background:#fff; border:1px solid hsl(210 14% 88%); box-shadow: 0 24px 60px -34px rgba(15,23,42,.45);">
                <span class="w-12 h-12 rounded-2xl grid place-items-center text-white"
                      style="background: linear-gradient(150deg, {{ $evDeep }}, {{ $evFade }});">
                    <i class="bi bi-sliders text-xl"></i>
                </span>

                <h2 class="text-[19px] font-black text-foreground" style="margin:14px 0 0; letter-spacing:-.01em;">
                    {{ __('events.public_manage_console_title') }}
                </h2>
                <p class="text-[12.5px] text-muted-foreground leading-snug mt-1.5">
                    {{ __('events.public_manage_console_hint', ['name' => $who ?: __('shared.unknown')]) }}
                </p>

                <a href="{{ $console }}"
                   class="m-press mt-4 w-full h-12 rounded-2xl font-black text-[14px] text-white flex items-center justify-center gap-2 no-underline"
                   style="background: {{ $e['color'] }}; box-shadow: 0 18px 40px -18px {{ $e['color'] }};">
                    {{ __('events.public_manage_console_open') }}<i class="bi bi-arrow-right"></i>
                </a>
            </div>
        @else
        <div class="rounded-3xl p-5" style="background:#fff; border:1px solid hsl(210 14% 88%); box-shadow: 0 24px 60px -34px rgba(15,23,42,.45);">

            <span class="w-12 h-12 rounded-2xl grid place-items-center text-white"
                  style="background: linear-gradient(150deg, {{ $evDeep }}, {{ $evFade }});">
                <i class="bi bi-sliders text-xl"></i>
            </span>

            <h2 class="text-[19px] font-black text-foreground" style="margin:14px 0 0; letter-spacing:-.01em;">
                {{ __('events.public_manage_title') }}
            </h2>
            <p class="text-[12.5px] text-muted-foreground leading-snug mt-1.5">
                {{ __('events.public_manage_sub') }}
            </p>

            {{-- The platform's own login, posted from here. CSRF, the lockout
                 counter and the throttle all come with it. --}}
            <form method="POST" action="{{ route('events.public.manage.signin', $e['key']) }}"
                  class="mt-4 space-y-2.5">
                @csrf

                {{-- One message for every kind of failure, exactly as /login
                     phrases it — never "no such account", which would turn this
                     page into an address checker. --}}
                @if($errors->any())
                    <div class="rounded-2xl px-3.5 py-3 text-[12px] font-semibold flex items-start gap-2"
                         style="background:#fef2f2; border:1px solid #fecaca; color:#b91c1c;">
                        <i class="bi bi-exclamation-circle-fill mt-0.5 flex-shrink-0"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                @foreach (['warning', 'error', 'status'] as $flash)
                    @if(session($flash))
                        <div class="rounded-2xl px-3.5 py-3 text-[12px] font-semibold flex items-start gap-2"
                             style="background:#fffbeb; border:1px solid #fde68a; color:#92400e;">
                            <i class="bi bi-info-circle-fill mt-0.5 flex-shrink-0"></i>
                            <span>{{ session($flash) }}</span>
                        </div>
                    @endif
                @endforeach

                <div>
                    <label for="mgr-email" class="block text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5">
                        {{ __('events.public_manage_email') }}
                    </label>
                    {{-- Not type="email": the platform's login also takes a
                         mobile number, and a browser that refuses to submit
                         what the server would accept is the worse bug. --}}
                    <input id="mgr-email" name="email" type="text" inputmode="text"
                           autocomplete="username" autocapitalize="none" spellcheck="false" required
                           value="{{ old('email', session('unverified_email')) }}"
                           placeholder="{{ __('events.public_manage_email_ph') }}"
                           class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                </div>

                <div x-data="{ show: false }">
                    <label for="mgr-password" class="block text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5">
                        {{ __('events.public_manage_password') }}
                    </label>
                    <div class="relative">
                        <input id="mgr-password" name="password" :type="show ? 'text' : 'password'"
                               autocomplete="current-password" required
                               class="e-field w-full h-12 ps-4 pe-12 rounded-2xl text-[15px]">
                        <button type="button" @click="show = ! show"
                                :aria-label="show ? @js(__('events.public_manage_hide')) : @js(__('events.public_manage_show'))"
                                class="absolute inset-y-0 end-0 w-12 grid place-items-center text-muted-foreground">
                            <i class="bi" :class="show ? 'bi-eye-slash' : 'bi-eye'"></i>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="m-press w-full h-14 rounded-2xl font-black text-[15px] text-white flex items-center justify-center gap-2"
                        style="margin-top:14px; background: {{ $e['color'] }}; box-shadow: 0 18px 40px -18px {{ $e['color'] }};">
                    {{ __('events.public_manage_signin') }}
                    <i class="bi bi-arrow-right"></i>
                </button>
            </form>

            {{-- The one link out, and it opens in a TAB of its own. A password
                 nobody can remember is the reason an organiser is standing at a
                 mat unable to start, so it is on the page rather than one they
                 have to go looking for — but resetting one is a platform
                 errand (an email, a signed link, a form), and this page must
                 still be here when they come back. `rel=noopener` because the
                 opened document is never allowed a handle on this one. --}}
            <a href="{{ route('password.request') }}" target="_blank" rel="noopener"
               class="block text-center text-[12px] font-semibold mt-3.5"
               style="color: {{ $e['color'] }};">
                {{ __('events.public_manage_forgot') }}
            </a>
        </div>
        @endif

        {{-- Signing OUT, for anybody who is signed in — whichever card they
             were shown above.

             It exists here because there was NO way out of the sealed app at
             all: the platform's own sign-out lives behind a navigation bar this
             surface deliberately does not render, and somebody handed a phone
             that is still signed in as the last person had no way to become
             themselves. A POST, so it keeps CSRF and cannot be fired by a link
             somebody else plants; it lands on the poster rather than on the
             platform, because being signed out of an event is not a reason to
             be thrown out of the app.

             Low emphasis on purpose. It is the least likely thing anybody came
             here to do, and it sits under the note rather than beside the
             console door it must not be mistaken for. --}}
        @if($who)
            <form method="POST" action="{{ route('events.public.sign-out', ['event' => $e['key']]) }}"
                  style="margin-top:16px;">
                @csrf
                <button type="submit"
                        class="m-press w-full h-11 rounded-2xl text-[12.5px] font-bold flex items-center justify-center gap-2"
                        style="background:#fff; border:1px solid hsl(210 14% 88%); color: hsl(220 10% 45%);">
                    <i class="bi bi-box-arrow-right"></i>{{ __('events.public_sign_out', ['name' => $who]) }}
                </button>
            </form>
        @endif

        <p class="text-center text-[11px] text-muted-foreground leading-snug" style="margin-top:14px;">
            {{ __('events.public_manage_note') }}
        </p>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* The same field the enrolment flow uses: the app's ground, focused in the
       EVENT's colour, because the whole page is that event's. */
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
</style>
@endpush
