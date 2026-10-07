@extends('events::entry.layout')

{{--
    "Register your club" — the ONE club-entry page, shared by every host
    (owner, 2026-10-04: club and federation events must be identical).

    Drawn by ClubEntryController at the event's own addresses (/rules, /club,
    /club/athletes, /club/review, /club/done, /club/entries — `$nav` set, each
    step its own address) and by a federation's legacy invitation door (`$nav`
    null, in-page steps). The cover, language sheet, poster band, quick facts
    and detail card are the event page's own partials. Everything that differs
    by host — the event payload, the browser-side store the steps talk to, its
    scripts and its words — comes from that host's ClubEntryDesk.

    Moved here from the federation module's invite/show on 2026-10-04.
--}}

@section('theme-color', '#ffffff')

@push('styles')
@include('events::entry.public.partials.tokens')
<style>
    body { --pg: #ffffff; --ink: var(--color-foreground); }
    /* The entry form's own controls (entry/public/enrol), in this event's colour. */
    .e-field { background:#fff; border:1px solid hsl(210 14% 88%); color:hsl(220 20% 15%); }
    .e-field::placeholder { color:hsl(220 10% 60%); }
    .e-field:focus, .e-field:focus-within { outline:none; border-color:{{ $e['color'] }}; box-shadow:0 0 0 4px {{ $e['color'] }}2e; }
    .e-pick { border:1.5px solid hsl(210 14% 88%); background:#fff; }
    .e-pick.is-on { border-color:{{ $e['color'] }}; background:{{ $e['color'] }}14; box-shadow:0 0 0 4px {{ $e['color'] }}1f; }
    .inv-chip { display:inline-flex; align-items:center; gap:6px; height:32px; padding:0 12px; border-radius:999px; font-size:12px; font-weight:700; border:1.5px solid hsl(210 14% 88%); background:#fff; color:#1e2c4f; }
    .inv-chip.is-on { border-color:{{ $e['color'] }}; background:{{ $e['color'] }}; color:#fff; }
    .inv-label { display:block; font-size:12px; font-weight:700; color:#1e2c4f; margin-bottom:6px; }
    .inv-card { background:#fff; border-radius:18px; border:1px solid #eef1f6; box-shadow:0 10px 30px rgba(30,44,79,.06); }
    .inv-h { margin:0; font-size:15px; font-weight:900; color:#1e2c4f; }
    .inv-sub { margin:4px 0 0; font-size:12px; line-height:1.5; color:#6b7689; }
    .inv-seg { height:4px; border-radius:2px; background:rgba(255,255,255,.28); flex:1; }
    .inv-seg.is-done { background:rgba(255,255,255,.9); }
    .inv-seg.is-cur { background:#fff; box-shadow:0 0 0 2px rgba(255,255,255,.25); }
    @keyframes inv-in { from { opacity:0; transform:translate3d(0,10px,0) } to { opacity:1; transform:none } }
    .inv-in { animation:inv-in .35s cubic-bezier(.22,.61,.36,1) both }
    @media (prefers-reduced-motion: reduce) { .inv-in { animation:none } }
</style>
@endpush

@section('body')
@php
    use App\Support\Palette;

    $ev = Palette::safe($e['color']);
    $evSoft = Palette::alpha($ev, .09);
    $eyebrow = \App\Events\Support\EventClassification::line($e['sport_label'] ?? null, $e['type'] ?? null);
    $t = $strings;
@endphp

{{-- ===== The cover and the language picker — the event page's own ===== --}}
@include('events::entry.public.partials.cover', ['coverLanguages' => $coverLanguages, 'coverStartsClosed' => (bool) ($nav ?? null)])
@include('events::entry.public.partials.language-sheet', ['languageUrls' => $languageUrls])

{{-- `$nav` (ClubEntryController): the step addresses. Null on the
     federation's legacy invitation door, which keeps its in-page steps. --}}
<div x-data="{{ ($nav ?? null) ? 'clubInvite('.json_encode($nav, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES).')' : 'clubInvite()' }}" class="-mx-4 -mt-4 pb-4">

    {{-- ================================================================
         THE POSTER — step 0
         ================================================================ --}}
    <div x-show="step === 0">
        @php
            $hchips = [];
            if ($e['fee_is_paid'] ?? false) $hchips[] = ['bi-cash-coin', __('personal.event_show_paid_entry')];
            if ($e['capped'] ?? false) $hchips[] = ['bi-people', trans_choice('events::events.public_spots', max(0, $e['cap'] - $e['going']), ['n' => max(0, $e['cap'] - $e['going'])])];
        @endphp
        <x-event-poster-band :color="$e['color']" :eyebrow="$eyebrow" :title="$e['title']" :owner="$e['club']" :chips="$hchips">
            <x-slot:lead>
                <button type="button" @click="window.dispatchEvent(new CustomEvent('reopen-cover'))"
                        class="m-press ev-ctl flex-none"
                        aria-label="{{ __('events::events.band_home') }}" title="{{ __('events::events.band_home') }}">
                    <i class="bi bi-house-door-fill"></i>
                </button>
            </x-slot:lead>
            <x-slot:controls>
                @include('events::partials.event-band-controls', [
                    'mode' => 'public', 'e' => $e, 'console' => $console,
                    'signedIn' => false, 'signOutUrl' => null, 'withQr' => false,
                ])
            </x-slot:controls>
        </x-event-poster-band>

        @include('events::entry.public.partials.quick-facts')

        @include('events::partials.event-detail-card-mobile', [
            'documents' => $e['documents'] ?? [],
            'divisionLink' => null, 'divisionNote' => null, 'noteTap' => null,
        ])

        {{-- ===== The doors — the same buttons the event's public page shows
             (App\Events\Support\EventDoors), when the event runs on the platform. ===== --}}
        @include('events::partials.event-doors', ['doors' => $doors ?? []])

        {{-- ===== The way in ===== --}}
        <span id="enter" class="block"></span>
        <div class="px-4 mt-4 space-y-3">
            <template x-if="phase() !== 'open'">
                <div class="rounded-2xl px-4 py-3 flex items-start gap-2.5"
                     :style="phase() === 'locked' ? 'background:#fdecea;' : 'background:#fdf1e0;'">
                    <i class="bi mt-0.5" :class="phase() === 'locked' ? 'bi-lock-fill' : 'bi-clock-history'"
                       :style="phase() === 'locked' ? 'color:#b4483c' : 'color:#b45309'"></i>
                    <p class="text-[12px] leading-snug m-0" :style="phase() === 'locked' ? 'color:#8f2f25' : 'color:#7a4a0c'"
                       x-text="phase() === 'locked' ? t.phaseClosedCover : fill(t.phaseLateCover, { f: money(ev().lateFee || 0) })"></p>
                </div>
            </template>

            <template x-if="hasEntry()">
                <button type="button" @click="go(6)"
                        class="m-press w-full text-start rounded-2xl p-4 text-white relative overflow-hidden shadow-lg flex items-center gap-3.5"
                        style="background: {{ Palette::eventBand($e['color']) }};">
                    <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>
                    <div class="relative w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                        <i class="bi bi-person-check-fill text-2xl"></i>
                    </div>
                    <div class="relative min-w-0 flex-1">
                        <h3 class="font-black text-base leading-tight" x-text="t.wayInEntries"></h3>
                        <p class="text-xs text-white/85 mt-0.5" x-text="club.name ? club.name + ' · ' + t.wayInEntriesSub : t.wayInEntriesSub"></p>
                    </div>
                    <i class="bi bi-chevron-right rtl:rotate-180 text-white/80 relative flex-shrink-0"></i>
                </button>
            </template>

            <template x-if="! hasEntry() && ! coverClosed()">
                <button type="button" @click="start()"
                        class="m-press w-full text-start rounded-2xl p-4 text-white relative overflow-hidden shadow-lg flex items-center gap-3.5"
                        style="background: linear-gradient(135deg, #7f1d1d, #1f2937);">
                    <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>
                    <div class="relative w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                        <i class="bi bi-building-add text-2xl"></i>
                    </div>
                    <div class="relative min-w-0 flex-1">
                        <h3 class="font-black text-base leading-tight" x-text="t.wayInTitle"></h3>
                        <p class="text-xs text-white/85 mt-0.5" x-text="t.wayInSub"></p>
                    </div>
                    <i class="bi bi-chevron-right rtl:rotate-180 text-white/80 relative flex-shrink-0"></i>
                </button>
            </template>

            <template x-if="coverClosed()">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 text-center">
                    <i class="bi bi-lock-fill text-xl" style="color: {{ $ev }}"></i>
                    <p class="text-sm font-bold text-foreground mt-2" x-text="t.closedCta"></p>
                    <p class="text-[12px] text-muted-foreground mt-1 leading-relaxed" x-text="t.wayInClosedSub"></p>
                </div>
            </template>
        </div>
    </div>

    {{-- ================================================================
         THE FLOW — steps 1–6, each under the section page's own band
         ================================================================ --}}
    <div x-show="step > 0" x-cloak>
        <header class="m-hero px-5 pt-5 pb-6 text-white relative overflow-hidden"
                style="background: {{ Palette::eventBand($e['color'], '150deg') }};">
            <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
            <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

            <div class="relative z-50 flex items-center" style="gap:12px;">
                <button type="button" @click="back()" class="m-press ev-ctl flex-none"
                        :aria-label="t.back" :title="t.back">
                    <i class="bi bi-chevron-left rtl:rotate-180"></i>
                </button>
                @include('events::partials.event-band-controls', [
                    'mode' => 'public', 'e' => $e, 'console' => $console,
                    'signedIn' => false, 'signOutUrl' => null, 'withQr' => false,
                ])
            </div>

            <div class="relative z-10 mt-6">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                        <i class="bi" :class="step === 5 ? 'bi-check2-circle' : (step === 6 ? 'bi-list-check' : 'bi-envelope-paper')"></i>
                        <span x-text="stepChip()"></span>
                    </span>
                    @if(!empty($e['sport_label']))
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi {{ $e['sport_icon'] ?? 'bi-trophy' }}"></i> {{ $e['sport_label'] }}</span>
                    @endif
                </div>
                <h1 class="text-2xl font-black mt-3 leading-tight" x-text="stepTitle()"></h1>
                <p class="text-sm text-white/85 mt-1.5 flex items-start gap-1.5">
                    <i class="bi bi-trophy mt-0.5"></i><span class="min-w-0">{{ $e['title'] }}</span>
                </p>

                {{-- Where in the four steps this is. --}}
                <div class="flex items-center gap-1.5 mt-4" x-show="step >= 1 && step <= 4">
                    <template x-for="n in [1,2,3,4]" :key="n">
                        <span class="inv-seg" :class="step > n ? 'is-done' : (step === n ? 'is-cur' : '')"></span>
                    </template>
                </div>
            </div>
        </header>

        <div class="px-4 mt-4 relative z-10 space-y-3" style="padding-bottom:1rem;">

            {{-- The one error line, above whatever it is about. --}}
            <div x-show="error" x-cloak role="alert" class="inv-in rounded-2xl px-4 py-3 flex items-start gap-2.5"
                 style="background:#fdecea; border:1px solid #f5c2bd;">
                <i class="bi bi-exclamation-circle-fill mt-0.5" style="color:#b4483c;"></i>
                <p class="text-[12.5px] font-bold leading-snug m-0" style="color:#8f2f25;" x-text="error"></p>
            </div>

            @include('events::entry.club.partials.step-rules')
            @include('events::entry.club.partials.step-club')
            @include('events::entry.club.partials.step-athletes')
            @include('events::entry.club.partials.step-review')
            @include('events::entry.club.partials.step-done')
            @include('events::entry.club.partials.step-manage')

            {{-- Continue — a button IN the page, the same shape as the event
                 page's own ways forward ("Register your club", the doors), not
                 a bar fixed to the screen (owner, 2026-10-04). Back is the
                 header's round back control. Greyed until the step is done; its
                 sub-line says what is missing, or what comes next. --}}
            <button type="button" x-show="step >= 1 && step <= 4" x-cloak
                    @click="next()" :disabled="submitting || checkingClub"
                    class="m-press w-full text-start rounded-2xl p-4 relative overflow-hidden flex items-center gap-3.5 disabled:cursor-wait"
                    :class="nextBlocked() ? 'text-muted-foreground' : 'text-white shadow-lg'"
                    :style="nextBlocked() ? 'background: var(--color-muted, #eceef2);' : 'background: {{ Palette::eventBand($e['color']) }};'">
                <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full" :class="nextBlocked() ? 'bg-white/40' : 'bg-white/10'"></div>
                <div class="relative w-12 h-12 rounded-2xl grid place-items-center flex-shrink-0"
                     :class="nextBlocked() ? 'bg-white/70' : 'bg-white/15 border border-white/25 backdrop-blur'">
                    <i class="bi text-2xl" :class="submitting || checkingClub ? 'bi-hourglass-split' : nextIcon()"></i>
                </div>
                <div class="relative min-w-0 flex-1">
                    <h3 class="font-black text-base leading-tight" x-text="nextLabel()"></h3>
                    <p class="text-xs mt-0.5" :class="nextBlocked() ? '' : 'text-white/85'" x-text="nextHint()"></p>
                </div>
                <i class="bi bi-chevron-right rtl:rotate-180 relative flex-shrink-0" :class="nextBlocked() ? '' : 'text-white/80'"></i>
            </button>
        </div>
    </div>

    @include('events::entry.club.partials.add-sheet')

    {{-- The camera and the file picker, handed to the ONE cropper. --}}
    <input type="file" x-ref="fileInput" accept="image/*" class="hidden" @change="onPhotoFile($event)">
    <input type="file" x-ref="camInput" accept="image/*" capture="user" class="hidden" @change="onPhotoFile($event)">
</div>

{{-- The project's one cropper, inline bottom-sheet mode, never uploading:
     the crop comes back as a data URL (cropperCropped) and rides the submit,
     where the server checks the real bytes. Portrait 3:4 for a person; the
     club logo square or round. --}}
<div class="hidden">
    <x-takeone-cropper id="invPhoto" mode="form" :inline="true"
        :width="600" :height="800" shape="rectangle" :canvasHeight="360"
        folder="temp" filename="invite" inputName="inv_photo"
        sheetMaxWidth="100%" sheetClass="rounded-t-3xl shadow-2xl bg-background"
        :showControls="false" :showCancel="false" saveText="{{ $t['savePhoto'] ?? __('shared.save') }}" />
    <x-takeone-cropper id="invLogo" mode="form" :inline="true"
        :width="640" :height="640" shape="rectangle" :canvasHeight="360" shapes="circle,square"
        folder="temp" filename="invite-logo" inputName="inv_logo"
        sheetMaxWidth="100%" sheetClass="rounded-t-3xl shadow-2xl bg-background"
        :showControls="false" :showCancel="false" saveText="{{ $t['savePhoto'] ?? __('shared.save') }}" />
</div>
@endsection

@push('scripts')
<script>window.__TAKEONE_FED = @json($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);</script>
{{-- The host's store (ClubEntryDesk::page → scripts): the browser half of its back office. --}}
@foreach($scripts as $script)
<script src="{{ $script['src'] }}"@if($script['defer'] ?? false) defer @endif></script>
@endforeach
{!! \App\Support\KarateShotukanWeightclasses::tags() !!}
@include('events::entry.club.partials.script')
@endpush
