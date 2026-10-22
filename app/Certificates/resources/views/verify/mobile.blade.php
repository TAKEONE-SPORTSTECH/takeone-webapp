<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('locales.'.app()->getLocale().'.dir', 'ltr') }}">
<head>@include('cert::verify.partials.head')</head>
@php
    $color = $found && preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($accent ?? '')) ? $accent : '#6d5bd0';
    $medal = [1 => '#b8892b', 2 => '#8a939c', 3 => '#a0612e'][$place ?? 0] ?? null;
@endphp
<body class="font-sans antialiased bg-background text-foreground min-h-screen">
    <header class="px-5 pb-20 text-white relative overflow-hidden" style="padding-top: calc(1.5rem + env(safe-area-inset-top)); background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <img src="{{ asset('images/fullLogo.png') }}" alt="{{ config('app.name', 'TakeOne') }}" class="relative h-7 brightness-0 invert">
        <div class="relative mt-6">
            @if($found)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi {{ $revoked ? 'bi-x-octagon-fill' : 'bi-patch-check-fill' }}"></i>{{ $revoked ? __('cert::messages.verify_chip_revoked') : ($headings['chip'] ?? __('cert::messages.verify_chip_valid')) }}
                </span>
            @endif
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ ! $found ? ($headings['unknown'] ?? __('cert::messages.verify_unknown_title')) : ($revoked ? __('cert::messages.verify_revoked_title') : ($headings['valid'] ?? __('cert::messages.verify_valid_title'))) }}</h1>
            <p class="text-sm text-white/85 mt-1.5">{{ $found ? __('cert::messages.verify_issued_by', ['issuer' => $issuer]) : __('cert::messages.verify_unknown') }}</p>
        </div>
    </header>

    <main class="-mt-12 relative z-10 px-4 space-y-3" style="padding-bottom: calc(1.5rem + env(safe-area-inset-bottom));">
        @if($found)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="flex items-center gap-3 p-4">
                    @if(! empty($story['photo']))
                        {{-- Only a picture the member made public (CompetitorFace). --}}
                        <a class="cv-photo" href="{{ $story['profile_url'] ?? '#' }}" @if(empty($story['profile_url'])) aria-disabled="true" @endif><img src="{{ $story['photo'] }}" alt="{{ $name }}">
                            <i class="cv-photo-badge {{ $revoked ? 'is-revoked' : '' }} bi {{ $revoked ? 'bi-x-lg' : 'bi-check-lg' }}"></i></a>
                    @else
                    <span class="w-12 h-12 rounded-2xl grid place-items-center flex-shrink-0 {{ $revoked ? 'bg-rose-50 text-rose-600' : 'bg-emerald-50 text-emerald-600' }}">
                        <i class="bi {{ $revoked ? 'bi-x-lg' : 'bi-check-lg' }} text-2xl"></i>
                    </span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-lg font-black text-foreground leading-tight break-words">@if(! empty($story['profile_url']))<a class="cv-link" href="{{ $story['profile_url'] }}">{{ $name }}</a>@else {{ $name }} @endif</p>
                        @php $clubInfo = $story['club'] ?? null; $clubName = $clubInfo['name'] ?? $club; @endphp
                        @if($clubName)<p class="text-[12px] text-muted-foreground mt-0.5 break-words cv-clubline">@if(! empty($clubInfo['url']))<a class="cv-clubline cv-link" href="{{ $clubInfo['url'] }}">@endif @if(! empty($clubInfo['logo']))<img src="{{ $clubInfo['logo'] }}" alt="">@endif<span>{{ $clubName }}</span>@if(! empty($clubInfo['url']))</a>@endif</p>@endif
                        @if(isset($story) && (! empty($story['age']) || ! empty($story['weight']) || ! empty($story['height'])))
                            <div class="cv-chips">
                                @if(! empty($story['age']))<a class="cv-chip" href="{{ $story['profile_url'] ?? ($story['draw_url'] ?? '#') }}"><i class="bi bi-cake2"></i>{{ trans_choice('cert::messages.verify_age', $story['age'], ['age' => $story['age']]) }}</a>@endif
                                @if(! empty($story['weight']))<a class="cv-chip" href="{{ $story['draw_url'] ?? '#' }}"><i class="bi bi-speedometer2"></i>{{ $story['weight'] }} kg @if($story['weighed']) <i class="bi bi-patch-check-fill cv-ok" title="{{ __('cert::messages.verify_weighed') }}"></i>@endif</a>@endif
                                @if(! empty($story['height']))<a class="cv-chip" href="{{ $story['profile_url'] ?? '#' }}"><i class="bi bi-arrows-expand"></i>{{ $story['height'] }} cm</a>@endif
                            </div>
                        @endif
                    </div>
                    @if($medal)<i class="bi bi-award-fill text-3xl flex-shrink-0" style="color: {{ $medal }};"></i>@endif
                </div>
                <dl class="border-t border-gray-100 divide-y divide-gray-50">
                    @php
                        $st = $story ?? [];
                        $rows = array_filter([
                            ['k' => __('cert::messages.verify_certificate'), 'v' => $title, 'href' => $downloadUrl ?? null],
                            ['k' => ($headings['what'] ?? __('cert::messages.verify_for')), 'v' => $what, 'href' => $st['draw_url'] ?? null],
                            ['k' => __('cert::messages.verify_event'), 'v' => $event, 'href' => $st['event']['url'] ?? null],
                            ['k' => __('cert::messages.verify_number'), 'v' => $number, 'copy' => true],
                            ['k' => __('cert::messages.verify_issued'), 'v' => $issuedOn, 'href' => $st['event']['url'] ?? null],
                            ['k' => __('cert::messages.verify_revoked'), 'v' => $revoked ? $revokedOn : null, 'danger' => true],
                        ], fn ($r) => filled($r['v']));
                    @endphp
                    @foreach($rows as $r)
                        <div class="flex gap-3 px-4 py-3">
                            <dt class="w-28 flex-shrink-0 text-[12px] font-semibold text-muted-foreground">{{ $r['k'] }}</dt>
                            <dd class="text-[13px] font-semibold text-foreground break-words {{ ! empty($r['danger']) ? 'text-rose-700' : '' }}">
                                @if(! empty($r['href']))<a class="cv-link" href="{{ $r['href'] }}">{{ $r['v'] }} <i class="bi bi-chevron-right cv-go"></i></a>
                                @elseif(! empty($r['copy']))<button type="button" class="cv-copy" data-copy="{{ $r['v'] }}" data-done="{{ __('cert::messages.verify_copied_number') }}">{{ $r['v'] }} <i class="bi bi-copy cv-go"></i></button>
                                @else {{ $r['v'] }} @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            <div class="cv-stack">@includeWhen(isset($shareUrl), 'cert::verify.partials.story')</div>
            <div class="bg-white rounded-2xl border border-gray-100 p-4 flex gap-3">
                <span class="w-9 h-9 rounded-xl bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-info-circle"></i></span>
                <p class="text-[12px] text-muted-foreground leading-relaxed">{{ $headings['note'] ?? __('cert::messages.verify_note') }}</p>
            </div>
        @else
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 text-center">
                <span class="w-14 h-14 mx-auto rounded-2xl bg-muted text-muted-foreground grid place-items-center"><i class="bi bi-question-lg text-2xl"></i></span>
                <p class="text-sm text-muted-foreground mt-3 leading-relaxed">{{ $headings['unknownHint'] ?? __('cert::messages.verify_unknown_hint') }}</p>
            </div>
        @endif
    </main>
</body>
</html>
