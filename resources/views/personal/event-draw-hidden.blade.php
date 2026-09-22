{{--
    ===== The draw, withheld =====

    The one thing the old bracket page still had to say once the board moved to
    `brackets/manage`: this reader may see the EVENT but not its draw, because
    the organiser is holding it back (`event.draw_reveal`, decided in
    App\Events\Support\EventAccess::drawVisible).

    ⚠️ WHY IT IS A PAGE AND NOT A REDIRECT. `manageBracket` used to answer this
    case by sending the reader to `me.events.bracket`, which is now itself a
    redirect TO `manageBracket` — two pages each pointing at the other is an
    endless bounce, the exact failure the Unattended Devices rule is about, and
    it would be worst on a screen nobody can stop. So the withheld draw is
    drawn here, and the chain ends.

    It says WHEN rather than nothing on purpose: a reader who cannot tell "not
    published yet" from "nobody entered" phones the organiser.

    Expects `$e` (the event view), `$color`, and `$drawHidden` (the sentence).
--}}
@extends($shell ?? 'layouts.app')

@section('title', __('events.public_draw'))

@section($contentSection ?? 'content')
@php $color = $color ?: ($e['color'] ?? '#7c6bf5'); @endphp
<div class="-mx-4 -mt-4 pb-6">

    {{-- The standard event hero band (Design Rule #6). --}}
    <header class="m-hero px-5 pt-5 pb-14 text-white relative overflow-hidden"
            style="background: {{ \App\Support\Palette::pageBand($color, isset($shell)) }};">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between gap-2 relative z-50">
            <a href="{{ ($sealed ?? false) ? url('/e/'.$e['key'].'/admin/manage') : route('me.events.show', $e['key']) }}"
               class="m-press inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline"
               aria-label="{{ ($sealed ?? false) ? __('personal.event_manage_title') : __('personal.event_show_event') }}"
               title="{{ ($sealed ?? false) ? __('personal.event_manage_title') : __('personal.event_show_event') }}">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-diagram-3 bracket-icon"></i> {{ __('events.public_draw') }}
                </span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            @if($e['host'] ?? null)
                <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                    <i class="bi bi-building"></i>{{ $e['host'] }}
                </p>
            @endif
        </div>
    </header>

    <div class="px-4 -mt-10 relative z-10">
        <x-draw-veil :message="$drawHidden ?? __('events.public_draw_empty_title')" :color="$color" />
    </div>
</div>
@endsection
