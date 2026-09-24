@extends('layouts.app')

@section('hide-navbar')
@endsection

{{--
    "Who is signing in?" — desktop. The mobile twin is
    resources/views/auth/mobile/choose.blade.php; the reasoning lives there and
    applies identically here. Kept as two files per the mobile/desktop split:
    this one is a centred card, that one is a full-height sheet.

    Nothing on this page is a credential — the shortlist is server-held and the
    controller refuses any id it did not choose.

    Expects $people.
--}}

@push('styles')
<style>
    .lcd-screen {
        min-height: 100vh; display: grid; place-items: center;
        background: #0e0a1f; position: relative; overflow: hidden;
        font-family: 'Inter', system-ui, sans-serif; padding: 32px 16px;
    }
    .lcd-aurora { position: absolute; inset: -30%; filter: blur(70px); opacity: .8; }
    .lcd-aurora span { position: absolute; border-radius: 50%; mix-blend-mode: screen; }
    .lcd-aurora .a1 { width: 42vw; height: 42vw; top: -6%; left: -8%; background: radial-gradient(circle, hsl(250 70% 60%), transparent 70%); }
    .lcd-aurora .a2 { width: 38vw; height: 38vw; bottom: -10%; right: -6%; background: radial-gradient(circle, hsl(285 72% 60%), transparent 70%); }

    .lcd-card {
        position: relative; z-index: 1; width: 100%; max-width: 460px;
        background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.14);
        border-radius: 24px; padding: 34px 30px; backdrop-filter: blur(14px);
    }
    .lcd-title { color: #fff; font-size: 24px; font-weight: 800; letter-spacing: -.02em; margin: 0; }
    .lcd-sub { color: rgba(255,255,255,.62); font-size: 14px; margin: 8px 0 0; line-height: 1.5; }

    .lcd-person {
        display: flex; align-items: center; gap: 14px; width: 100%;
        padding: 12px 14px; margin-top: 12px;
        background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.14);
        border-radius: 16px; color: #fff; text-align: start;
        transition: background .15s ease, border-color .15s ease;
    }
    .lcd-person:hover { background: rgba(255,255,255,.13); border-color: rgba(255,255,255,.26); }
    .lcd-face { width: 45px; height: 60px; border-radius: 11px; overflow: hidden; flex: none; background: rgba(255,255,255,.08); }
    .lcd-face img, .lcd-face svg { width: 100%; height: 100%; object-fit: cover; display: block; }
    .lcd-name { font-size: 15px; font-weight: 700; }
    .lcd-go { margin-inline-start: auto; color: rgba(255,255,255,.55); }

    .lcd-back { margin-top: 22px; text-align: center; }
    .lcd-back a { color: rgba(255,255,255,.6); font-size: 14px; text-decoration: none; }
    .lcd-back a:hover { color: rgba(255,255,255,.85); }

    @media (prefers-reduced-motion: reduce) { .lcd-person { transition: none; } }
</style>
@endpush

@section('content')
<div class="lcd-screen">
    <div class="lcd-aurora" aria-hidden="true"><span class="a1"></span><span class="a2"></span></div>

    <div class="lcd-card">
        <h1 class="lcd-title">{{ __('auth.choose_title') }}</h1>
        <p class="lcd-sub">{{ __('auth.choose_sub') }}</p>

        <form method="POST" action="{{ route('login.chose') }}" style="margin-top:18px;">
            @csrf
            @foreach($people as $person)
                <button type="submit" name="user" value="{{ $person->id }}" class="lcd-person">
                    <span class="lcd-face">
                        @if($person->profile_picture)
                            <img src="{{ file_url($person->profile_picture) }}?v={{ optional($person->updated_at)->timestamp }}"
                                 alt=""
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                            <span style="display:none; width:100%; height:100%;">
                                <x-gender-avatar :gender="$person->gender" class="w-full h-full" bg="hsl(250 55% 60%)" sizes="45px" />
                            </span>
                        @else
                            <x-gender-avatar :gender="$person->gender" class="w-full h-full" bg="hsl(250 55% 60%)" sizes="45px" />
                        @endif
                    </span>

                    <span class="lcd-name">{{ $person->full_name ?: $person->name }}</span>
                    <i class="bi bi-chevron-right lcd-go"></i>
                </button>
            @endforeach
        </form>

        <div class="lcd-back">
            <a href="{{ route('login') }}">{{ __('auth.choose_not_you') }}</a>
        </div>
    </div>
</div>
@endsection
