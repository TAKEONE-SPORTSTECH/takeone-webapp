<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('locales.'.app()->getLocale().'.dir', 'ltr') }}">
<head>@include('cert::verify.partials.head')</head>
@php
    $color = $found && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($accent ?? '')) ? $accent : '#6d5bd0';
    $medal = [1 => '#b8892b', 2 => '#8a939c', 3 => '#a0612e'][$place ?? 0] ?? null;
@endphp
<body class="font-sans antialiased bg-background text-foreground min-h-screen">
    <div class="overflow-hidden shadow-sm text-white relative" style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8 pb-24">
            <img src="{{ asset('images/fullLogo.png') }}" alt="{{ config('app.name', 'TakeOne') }}" class="h-8 brightness-0 invert mb-6">
            @if($found)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi {{ $revoked ? 'bi-x-octagon-fill' : 'bi-patch-check-fill' }}"></i>{{ $revoked ? __('cert::messages.verify_chip_revoked') : ($headings['chip'] ?? __('cert::messages.verify_chip_valid')) }}
                </span>
            @endif
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ ! $found ? ($headings['unknown'] ?? __('cert::messages.verify_unknown_title')) : ($revoked ? __('cert::messages.verify_revoked_title') : ($headings['valid'] ?? __('cert::messages.verify_valid_title'))) }}</h1>
            <p class="text-sm text-white/85 mt-1.5">{{ $found ? __('cert::messages.verify_issued_by', ['issuer' => $issuer]) : __('cert::messages.verify_unknown') }}</p>
        </div>
    </div>

    <main class="px-4 sm:px-6 lg:px-8 -mt-12 relative z-10 pb-10">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        @if($found)
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="flex items-center gap-4 p-6">
                    <span class="w-14 h-14 rounded-2xl grid place-items-center flex-shrink-0 {{ $revoked ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600' }}">
                        <i class="bi {{ $revoked ? 'bi-x-lg' : 'bi-check-lg' }} text-2xl"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xl font-black text-foreground break-words">{{ $name }}</p>
                        @if($club)<p class="text-sm text-muted-foreground mt-0.5 break-words">{{ $club }}</p>@endif
                    </div>
                    @if($medal)<i class="bi bi-award-fill text-4xl flex-shrink-0" style="color: {{ $medal }};"></i>@endif
                </div>
                <dl class="border-t border-gray-100 divide-y divide-gray-50">
                    @foreach(array_filter([
                        __('cert::messages.verify_certificate') => $title,
                        ($headings['what'] ?? __('cert::messages.verify_for')) => $what,
                        __('cert::messages.verify_event') => $event,
                        __('cert::messages.verify_number') => $number,
                        __('cert::messages.verify_issued') => $issuedOn,
                        __('cert::messages.verify_revoked') => $revoked ? $revokedOn : null,
                    ]) as $k => $v)
                        <div class="flex gap-4 px-6 py-3.5">
                            <dt class="w-40 flex-shrink-0 text-sm font-medium text-muted-foreground">{{ $k }}</dt>
                            <dd class="text-sm font-semibold text-foreground break-words {{ $k === __('cert::messages.verify_revoked') ? 'text-rose-700' : '' }}">{{ $v }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex gap-3">
                <span class="w-9 h-9 rounded-xl bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-info-circle"></i></span>
                <p class="text-sm text-muted-foreground leading-relaxed">{{ $headings['note'] ?? __('cert::messages.verify_note') }}</p>
            </div>
        @else
            <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
                <span class="w-14 h-14 mx-auto rounded-2xl bg-muted text-muted-foreground grid place-items-center"><i class="bi bi-question-lg text-2xl"></i></span>
                <p class="text-sm text-muted-foreground mt-3">{{ $headings['unknownHint'] ?? __('cert::messages.verify_unknown_hint') }}</p>
            </div>
        @endif
        </div>
    </main>
</body>
</html>
