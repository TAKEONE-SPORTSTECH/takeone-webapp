@extends('layouts.app')

@section('title', $name)

@php $rtl = $locale === 'ar'; @endphp

@section('content')
<div class="min-h-screen bg-background pb-16" @if($rtl) dir="rtl" @endif>

    {{-- Hero --}}
    <div class="relative h-64 sm:h-80 w-full overflow-hidden">
        @if($activity->picture_url)
            <img src="{{ asset('storage/'.$activity->picture_url) }}" alt="{{ $name }}" class="absolute inset-0 w-full h-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/10"></div>
        @else
            <div class="absolute inset-0" style="background: linear-gradient(135deg, hsl(250 65% 55%), hsl(250 65% 35%));"></div>
            <div class="absolute inset-0 flex items-center justify-center opacity-25">
                <i class="bi {{ $activity->icon ?: 'bi-activity' }}" style="font-size: 9rem; color:#fff;"></i>
            </div>
        @endif

        <div class="absolute inset-x-0 bottom-0 p-5 sm:p-8">
            <div class="max-w-3xl mx-auto">
                <a href="{{ url()->previous() }}" class="inline-flex items-center gap-1.5 text-white/80 hover:text-white text-sm mb-3">
                    <i class="bi bi-arrow-left"></i> {{ __('shared.back') ?? 'Back' }}
                </a>
                <h1 class="text-3xl sm:text-4xl font-bold text-white drop-shadow">{{ $name }}</h1>
                @if(!empty($activity->variants) && count($activity->variants))
                    <div class="flex flex-wrap gap-2 mt-3">
                        @foreach($activity->variants as $v)
                            <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-white/20 text-white backdrop-blur">{{ $rtl ? ($v['name_ar'] ?? $v['name']) : $v['name'] }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Body --}}
    <div class="max-w-3xl mx-auto px-5 sm:px-6 -mt-6 relative">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-9">
            @if($description)
                <div class="activity-prose" @if($rtl) dir="rtl" @endif>{!! $description !!}</div>
            @else
                <p class="text-muted-foreground text-sm text-center py-8">No description available yet.</p>
            @endif
        </div>

        <p class="text-center text-xs text-muted-foreground mt-6">
            <i class="bi bi-collection mr-1"></i> From the TAKEONE activity directory
        </p>
    </div>
</div>

@push('styles')
<style>
    .activity-prose { color: hsl(220 15% 25%); font-size: 1rem; line-height: 1.85; }
    .activity-prose > div { display: contents; }
    .activity-prose h3 {
        font-size: 1.15rem; font-weight: 700; color: hsl(220 20% 15%);
        margin: 1.9rem 0 0.7rem; padding-bottom: .4rem; border-bottom: 1px solid hsl(210 14% 90%);
    }
    .activity-prose h3:first-child { margin-top: 0; }
    .activity-prose p { margin: 0 0 1rem; }
    .activity-prose ul, .activity-prose ol { margin: 0 0 1.15rem; padding-inline-start: 1.4rem; }
    .activity-prose li { margin: 0 0 .55rem; padding-inline-start: .2rem; }
    .activity-prose ul { list-style: none; padding-inline-start: 0; }
    .activity-prose ul > li { position: relative; padding-inline-start: 1.65rem; }
    .activity-prose ul > li::before {
        content: ""; position: absolute; inset-inline-start: .35rem; top: .72em;
        width: .4rem; height: .4rem; border-radius: 999px; background: hsl(250 65% 60%);
    }
    .activity-prose ol { list-style: decimal; }
    .activity-prose a { color: hsl(250 65% 55%); text-decoration: underline; text-underline-offset: 2px; word-break: break-word; }
    .activity-prose a:hover { color: hsl(250 65% 42%); }
    .activity-prose strong { color: hsl(220 20% 15%); font-weight: 700; }
    .activity-prose blockquote {
        border-inline-start: 3px solid hsl(250 65% 70%); padding-inline-start: 1rem;
        color: hsl(220 12% 40%); font-style: italic; margin: 0 0 1rem;
    }
    /* The last section is Trusted Resources — give the links a subtle card feel */
    .activity-prose h3:last-of-type + ul > li { background: hsl(220 15% 97%); border-radius: .6rem; padding: .6rem .8rem .6rem 1.65rem; }
    .activity-prose h3:last-of-type + ul > li::before { top: 1.05em; }
</style>
@endpush
@endsection
