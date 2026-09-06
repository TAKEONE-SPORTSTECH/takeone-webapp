@props([
    // The event's public uuid — where sign-out returns to.
    'event',
    // The event's own colour, for the sheet's band.
    'color' => '#1677FF',
])

{{--
    Your account, on the sealed event surface — and the way OUT of it.

    WHY THIS EXISTS
    ---------------
    There was no way to sign out of the standalone event app at all. The
    platform's sign-out lives behind a navigation bar this surface deliberately
    does not render, so a phone handed to the next competitor stayed signed in
    as the last person, and nobody could become themselves.

    It was first put on the event's sign-in door and on the entrant's own panel,
    and an organiser reaches NEITHER: the poster's gear links a manager straight
    to the console, so the sign-in page is never seen, and /my-entry belongs to
    competitors. A sign-out nobody can find is not a sign-out. So it lives in
    the header — on the poster and on every section page, which between them are
    every page a reader actually lands on.

    A SHEET, not a bare button. Signing out by mis-tapping a 40px circle in a
    header should not be possible, and the sheet is also where the answer to
    "who am I signed in as?" belongs — the question people are really asking
    when they go looking for this.

    Renders nothing at all for a signed-out visitor.

    Its own `x-data`, so it can be dropped into any header without touching that
    page's scope (Standalone Self-Contained Components).
--}}

@auth
@php
    $me = auth()->user();
    $accountName = $me->full_name ?: $me->name;
    /* Organiser-supplied, and it lands in a `style` attribute — so it is
       whitelisted here rather than trusted, exactly as the other event
       components do. */
    $accentSafe = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#1677FF';
@endphp

<div x-data="{ acct: false }" class="flex-none">
    <button type="button" @click="acct = true"
            aria-label="{{ __('events.public_account') }}"
            title="{{ __('events.public_account') }}"
            {{ $attributes->merge(['class' => 'm-press grid place-items-center flex-none']) }}>
        <i class="bi bi-person"></i>
    </button>

    {{-- Teleported: the mobile shell leaves a transform on its children, which
         makes any `position: fixed` descendant resolve against that wrapper
         instead of the viewport and clips the sheet. --}}
    <template x-teleport="body">
        <div x-show="acct" x-cloak class="fixed inset-0" style="z-index:80;">
            <div class="absolute inset-0" style="background:rgba(8,10,20,.55);"
                 @click="acct = false" x-transition.opacity></div>

            <div class="absolute inset-x-0 bottom-0 flex flex-col" style="max-height:92vh;"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-y-full"
                 x-transition:enter-end="translate-y-0">

                {{-- Design Rule #8's band. ⚠️ hex → hex+b0: the `b0` alpha
                     suffix is HEX-ONLY, and an hsl() colour with it appended is
                     an invalid gradient that silently renders nothing. --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $accentSafe }}, {{ $accentSafe }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-person-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ $accountName }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('events.public_account_signed_in') }}</p>
                        </div>
                        <button type="button" @click="acct = false"
                                aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- No footer: the band's ✕ closes it, and the one action here
                     IS the body (Design Rule #8). --}}
                <div class="bg-white px-5 pt-4" style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));">
                    {{-- POST, so it keeps CSRF and cannot be fired by a link
                         somebody else plants. Lands on the poster rather than
                         the platform: being signed out of an event is not a
                         reason to be thrown out of the app. --}}
                    <form method="POST" action="{{ route('events.public.sign-out', ['event' => $event]) }}">
                        @csrf
                        <button type="submit"
                                class="m-press w-full h-12 rounded-2xl font-black text-[14px] flex items-center justify-center gap-2"
                                style="background:#fff; border:1px solid hsl(210 14% 88%); color:#b91c1c;">
                            <i class="bi bi-box-arrow-right"></i>{{ __('events.public_account_sign_out') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
@endauth
