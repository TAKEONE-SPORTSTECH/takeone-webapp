@extends('layouts.app')

@section('hide-navbar')
@endsection

{{--
    "Who is signing in?" — the second half of a telephone sign-in.

    A telephone belongs to a household, not to a person. At a junior
    competition a parent enters two children on one number, and this platform
    models guardians and dependents precisely because that is the normal case.
    So when one number and one password answer for more than one person, the
    password is already proven and only the identity is open.

    Nothing on this page is a credential. The shortlist lives in the SESSION,
    chosen by the server, and the controller refuses any id that is not on it —
    so this cannot be reached as a password-less way in, and a stranger who
    lands here directly is sent back to the sign-in form having learned nothing.

    Deliberately spare: faces and names, nothing else. No email address, no
    telephone number, no club — a shared phone means somebody else's family may
    be looking at this screen.

    Expects $people (id, full_name, name, profile_picture, gender, updated_at).
--}}

@push('styles')
<style>
    /* Same aurora shell as the sign-in form it continues, so the second step
       does not look like a different product. Scoped; independent of the
       prebuilt Tailwind bundle. */
    .lc-screen {
        position: fixed; inset: 0; display: flex; flex-direction: column;
        background: #0e0a1f; overflow: hidden;
        font-family: 'Inter', system-ui, sans-serif;
    }
    .lc-aurora { position: absolute; inset: -30%; z-index: 0; filter: blur(64px); opacity: .85; }
    .lc-aurora span { position: absolute; border-radius: 50%; mix-blend-mode: screen; animation: lc-drift 16s ease-in-out infinite; }
    .lc-aurora .a1 { width: 58vw; height: 58vw; top: -10%; left: -12%; background: radial-gradient(circle, hsl(250 70% 60%), transparent 70%); }
    .lc-aurora .a2 { width: 52vw; height: 52vw; top: 2%; right: -16%; background: radial-gradient(circle, hsl(168 65% 52%), transparent 70%); animation-delay: -4s; }
    .lc-aurora .a3 { width: 46vw; height: 46vw; top: 24%; left: 22%; background: radial-gradient(circle, hsl(285 72% 60%), transparent 70%); animation-delay: -8s; }
    @keyframes lc-drift { 0%,100% { transform: translate3d(0,0,0) scale(1); } 50% { transform: translate3d(3%,-4%,0) scale(1.08); } }

    .lc-body { position: relative; z-index: 1; flex: 1; overflow-y: auto;
        padding: 28px 22px calc(28px + env(safe-area-inset-bottom)); display: flex; flex-direction: column; }

    .lc-title { color: #fff; font-size: 26px; font-weight: 800; letter-spacing: -.02em; margin: 0; }
    .lc-sub { color: rgba(255,255,255,.62); font-size: 14px; margin: 8px 0 0; line-height: 1.5; }

    /* One tappable row per person. 3:4 portrait, because every face on this
       platform is (CLAUDE.md, Profile Pictures Are Portrait 3:4). */
    .lc-person {
        display: flex; align-items: center; gap: 14px; width: 100%;
        padding: 12px 14px; margin-top: 12px;
        background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.14);
        border-radius: 18px; color: #fff; text-align: start;
        transition: transform .12s ease, background .12s ease;
    }
    .lc-person:active { transform: scale(.98); background: rgba(255,255,255,.12); }
    .lc-face { width: 48px; height: 64px; border-radius: 12px; overflow: hidden; flex: none; background: rgba(255,255,255,.08); }
    .lc-face img, .lc-face svg { width: 100%; height: 100%; object-fit: cover; display: block; }
    .lc-name { font-size: 16px; font-weight: 700; line-height: 1.25; }
    .lc-go { margin-inline-start: auto; color: rgba(255,255,255,.55); font-size: 18px; }

    .lc-back { margin-top: 22px; text-align: center; }
    .lc-back a { color: rgba(255,255,255,.6); font-size: 14px; text-decoration: none; }

    @media (prefers-reduced-motion: reduce) {
        .lc-aurora span { animation: none; }
        .lc-person { transition: none; }
    }
</style>
@endpush

@section('content')
<div class="lc-screen">
    <div class="lc-aurora" aria-hidden="true"><span class="a1"></span><span class="a2"></span><span class="a3"></span></div>

    <div class="lc-body">
        <h1 class="lc-title">{{ __('auth.choose_title') }}</h1>
        <p class="lc-sub">{{ __('auth.choose_sub') }}</p>

        <form method="POST" action="{{ route('login.chose') }}" style="margin-top:18px;">
            @csrf
            @foreach($people as $person)
                {{-- The VALUE is the only thing submitted, and the controller
                     accepts it only if the server put it on the shortlist. --}}
                <button type="submit" name="user" value="{{ $person->id }}" class="lc-person">
                    <span class="lc-face">
                        @if($person->profile_picture)
                            <img src="{{ file_url($person->profile_picture) }}?v={{ optional($person->updated_at)->timestamp }}"
                                 alt=""
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <span style="display:none; width:100%; height:100%;">
                                <x-gender-avatar :gender="$person->gender" class="w-full h-full" bg="hsl(250 55% 60%)" sizes="48px" />
                            </span>
                        @else
                            <x-gender-avatar :gender="$person->gender" class="w-full h-full" bg="hsl(250 55% 60%)" sizes="48px" />
                        @endif
                    </span>

                    <span class="lc-name">{{ $person->full_name ?: $person->name }}</span>
                    <i class="bi bi-chevron-right lc-go"></i>
                </button>
            @endforeach
        </form>

        <div class="lc-back">
            <a href="{{ route('login') }}">{{ __('auth.choose_not_you') }}</a>
        </div>
    </div>
</div>
@endsection
