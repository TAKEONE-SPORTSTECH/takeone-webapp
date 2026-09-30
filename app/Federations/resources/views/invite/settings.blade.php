@extends('events::entry.layout')

{{--
    The invitation's GEAR page — the event page's own "door" for whoever runs
    it (entry/public/manage), for a federation's club invitation.

    A manager of the federation chooses the languages clubs may read the
    invitation in, and the one it was written in. Anybody else is offered the
    sign-in, in the invitation's own skin. Saving is decided by the endpoint
    (InvitePageController::updateSettings), never by this page.
--}}

@section('theme-color', '#ffffff')

@push('styles')
@include('events::entry.public.partials.tokens')
<style>
    .e-field { background:#fff; border:1px solid hsl(210 14% 88%); color:hsl(220 20% 15%); }
    .e-field:focus { outline:none; border-color:{{ $e['color'] }}; box-shadow:0 0 0 4px {{ $e['color'] }}2e; }
    .e-pick { border:1.5px solid hsl(210 14% 88%); background:#fff; }
    .e-pick.is-on { border-color:{{ $e['color'] }}; background:{{ $e['color'] }}14; }
    .inv-card { background:#fff; border-radius:18px; border:1px solid #eef1f6; box-shadow:0 10px 30px rgba(30,44,79,.06); }
</style>
@endpush

@section('body')
@php
    use App\Support\Palette;
    $ev = Palette::safe($e['color']);
    $evSoft = Palette::alpha($ev, .09);
@endphp

<div class="-mx-4 -mt-4 pb-4"
     x-data="{
        rows: @js($languageRows),
        offered: @js($offered),
        source: @js($source),
        q: '',
        saving: false,
        filtered() {
            const q = this.q.trim().toLowerCase();
            return q ? this.rows.filter(r => r.name.toLowerCase().includes(q) || r.native.toLowerCase().includes(q) || r.code.startsWith(q)) : this.rows;
        },
        on(code) { return this.offered.includes(code); },
        toggle(code) {
            if (code === this.source) return;
            this.offered = this.on(code) ? this.offered.filter(c => c !== code) : [...this.offered, code];
        },
        offeredRows() { return this.rows.filter(r => this.on(r.code)); },
        async save() {
            if (this.saving) return;
            this.saving = true;
            try {
                const res = await fetch(@js(route('federations.invite.settings.update', $event->uuid)), {
                    method: 'PUT', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    body: JSON.stringify({ offered: this.offered, source: this.source }),
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));
                this.offered = d.offered; this.source = d.source;
                window.showToast('success', d.message);
            } catch (e) { window.showToast('error', e.message); }
            finally { this.saving = false; }
        },
     }">

    <header class="m-hero px-5 pt-5 pb-14 text-white relative overflow-hidden"
            style="background: {{ Palette::eventBand($e['color'], '150deg') }};">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="relative z-50 flex items-center" style="gap:12px;">
            <a href="{{ $backUrl }}" class="m-press ev-ctl flex-none" aria-label="{{ __('federations::invite.ui.backToEvent') }}" title="{{ __('federations::invite.ui.backToEvent') }}">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-gear"></i> {{ __('federations::invite.settings_chip') }}
                </span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('federations::invite.settings_title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5"><i class="bi bi-trophy"></i><span class="truncate">{{ $e['title'] }}</span></p>
        </div>
    </header>

    <div class="px-4 -mt-8 relative z-10 space-y-3" style="{{ $manager ? 'padding-bottom:7rem;' : '' }}">

        @if($manager)
            {{-- ===== Written in ===== --}}
            <div class="inv-card overflow-hidden">
                <x-event-section-band :color="$e['color']" icon="bi-pencil-square" :title="__('federations::invite.settings_source')" />
                <div class="p-5">
                    <p class="text-[12px] text-muted-foreground leading-snug m-0">{{ __('federations::invite.settings_source_sub') }}</p>
                    <div class="flex flex-wrap gap-1.5 mt-3">
                        <template x-for="r in offeredRows()" :key="r.code">
                            <button type="button" @click="source = r.code" class="m-press e-pick h-9 px-3 rounded-full inline-flex items-center gap-2 text-[12.5px] font-bold text-foreground"
                                    :class="source === r.code ? 'is-on' : ''">
                                <template x-if="r.flag"><span :class="'fi fi-' + r.flag" style="width:18px; height:13px; border-radius:3px; background-size:cover;"></span></template>
                                <span :dir="r.dir" x-text="r.native"></span>
                                <i class="bi bi-check-lg" x-show="source === r.code" style="color: {{ $ev }};"></i>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            {{-- ===== Languages ===== --}}
            <div class="inv-card overflow-hidden">
                <x-event-section-band :color="$e['color']" icon="bi-translate" :title="__('federations::invite.settings_languages')" />
                <div class="p-5">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-[12px] text-muted-foreground leading-snug m-0">{{ __('federations::invite.settings_languages_sub') }}</p>
                        <span class="flex-none px-2.5 py-1 rounded-full text-[11px] font-black" style="background: {{ $evSoft }}; color: {{ $ev }};"
                              x-text="@js(__('federations::invite.settings_selected', ['n' => '__N__'])).replace('__N__', offered.length)"></span>
                    </div>
                    <div class="relative mt-3.5">
                        <i class="bi bi-search absolute top-1/2 -translate-y-1/2 text-muted-foreground" style="inset-inline-start:14px;"></i>
                        <input type="text" x-model="q" placeholder="{{ __('federations::invite.settings_search') }}" autocomplete="off"
                               class="e-field w-full h-11 rounded-2xl text-[14px]" style="padding-inline-start:40px; padding-inline-end:14px;">
                    </div>
                    <div class="mt-3 rounded-2xl overflow-hidden" style="border:1px solid #eef1f6;">
                        <template x-for="r in filtered()" :key="r.code">
                            <button type="button" @click="toggle(r.code)" class="m-press w-full flex items-center gap-3 px-4 py-3 text-start" style="border-bottom:1px solid #f3f5f9;">
                                <template x-if="r.flag"><span :class="'fi fi-' + r.flag" style="width:26px; height:19px; border-radius:4px; background-size:cover; flex:none; outline:1px solid rgba(30,44,79,.12); outline-offset:-1px;"></span></template>
                                <template x-if="! r.flag"><span class="grid place-items-center text-[9px] font-black text-muted-foreground" style="width:26px; height:19px; border-radius:4px; background:#eef1f6; flex:none;" x-text="r.code.slice(0,2).toUpperCase()"></span></template>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[14px] font-bold text-foreground" :dir="r.dir" x-text="r.native"></span>
                                    <span class="block text-[11px] text-muted-foreground" x-text="r.name + (r.code === source ? ' · ' + @js(__('federations::invite.settings_source')) : '')"></span>
                                </span>
                                <span class="w-6 h-6 rounded-lg border-2 grid place-items-center flex-shrink-0"
                                      :class="on(r.code) ? 'text-white' : 'border-gray-300'"
                                      :style="on(r.code) ? 'background: {{ $ev }}; border-color: {{ $ev }};' + (r.code === source ? 'opacity:.55' : '') : ''">
                                    <i class="bi bi-check-lg text-[13px]" x-show="on(r.code)"></i>
                                </span>
                            </button>
                        </template>
                    </div>
                    <p class="text-[11px] text-muted-foreground leading-snug mt-3 mb-0"><i class="bi bi-stars"></i> {{ __('federations::invite.settings_interface_note') }}</p>
                </div>
            </div>

            {{-- ===== The rest of the job lives on the dashboard ===== --}}
            <a href="{{ route('federations.home') }}"
               class="m-press rounded-2xl p-4 text-white relative overflow-hidden shadow-lg flex items-center gap-3.5 no-underline"
               style="background: {{ Palette::eventBand($e['color']) }};">
                <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10"></div>
                <div class="relative w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0"><i class="bi bi-speedometer2 text-2xl"></i></div>
                <div class="relative min-w-0 flex-1">
                    <h3 class="font-black text-base leading-tight">{{ __('federations::invite.settings_dashboard') }}</h3>
                    <p class="text-xs text-white/85 mt-0.5">{{ __('federations::invite.settings_dashboard_sub') }}</p>
                </div>
                <i class="bi bi-chevron-right rtl:rotate-180 text-white/80 relative flex-shrink-0"></i>
            </a>
        @elseif($signedIn)
            <div class="inv-card p-5">
                <div class="flex items-start gap-3">
                    <i class="bi bi-shield-lock-fill text-xl" style="color: {{ $ev }};"></i>
                    <p class="text-[13px] text-foreground leading-relaxed m-0">{{ __('federations::invite.settings_not_manager') }}</p>
                </div>
                <form method="POST" action="{{ route('federations.logout') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="m-press h-11 px-4 rounded-xl text-[13px] font-black" style="border:1.5px solid hsl(210 14% 88%); color:#b4483c;">
                        <i class="bi bi-box-arrow-right"></i> {{ __('federations::invite.settings_sign_out') }}
                    </button>
                </form>
            </div>
        @else
            <div class="inv-card overflow-hidden" x-data="{ reveal: false }">
                <x-event-section-band :color="$e['color']" icon="bi-person-lock" :title="__('federations::invite.settings_signin_title')" />
                <form method="POST" action="{{ route('federations.invite.settings.sign-in', $event->uuid) }}" class="p-5 space-y-3.5">
                    @csrf
                    <p class="text-[12px] text-muted-foreground leading-snug m-0">{{ __('federations::invite.settings_signin_sub') }}</p>
                    @if($errors->any())
                        <div class="rounded-2xl px-4 py-3 text-[12.5px] font-bold" style="background:#fdecea; color:#8f2f25;">{{ $errors->first() }}</div>
                    @endif
                    <div>
                        <label class="block text-[12px] font-bold text-foreground mb-1.5" for="invSignEmail">{{ __('federations::invite.settings_email') }}</label>
                        <input id="invSignEmail" type="text" name="email" value="{{ old('email') }}" autocomplete="username" required dir="ltr"
                               class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                    </div>
                    <div>
                        <label class="block text-[12px] font-bold text-foreground mb-1.5" for="invSignPass">{{ __('federations::invite.settings_password') }}</label>
                        <div class="relative">
                            <input id="invSignPass" :type="reveal ? 'text' : 'password'" name="password" autocomplete="current-password" required
                                   class="e-field w-full h-12 ps-4 pe-12 rounded-2xl text-[15px]">
                            <button type="button" @click="reveal = ! reveal" class="absolute end-3 top-1/2 -translate-y-1/2 text-muted-foreground"
                                    aria-label="{{ __('federations::invite.settings_password') }}"><i class="bi" :class="reveal ? 'bi-eye-slash' : 'bi-eye'"></i></button>
                        </div>
                    </div>
                    <button type="submit" class="m-press w-full h-12 rounded-2xl text-white font-black text-[15px]"
                            style="background: {{ $ev }}; box-shadow:0 18px 40px -18px {{ $ev }};">{{ __('federations::invite.settings_signin') }}</button>
                    <a href="{{ route('federations.password.request') }}" class="block text-center text-[12px] font-bold text-muted-foreground no-underline">{{ __('federations::invite.settings_forgot') }}</a>
                </form>
            </div>
        @endif
    </div>

    @if($manager)
        <div class="ev-app-fixed fixed inset-x-0 bottom-0 z-30 px-5 pt-3 bg-white border-t border-gray-100"
             style="padding-bottom: calc(0.9rem + env(safe-area-inset-bottom));">
            <div class="mx-auto w-full max-w-lg">
                <button type="button" @click="save()" :disabled="saving"
                        class="m-press w-full h-14 rounded-2xl font-black text-[15px] text-white flex items-center justify-center gap-2"
                        style="background: {{ $ev }}; box-shadow: 0 18px 40px -18px {{ $ev }};">
                    <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>
                    <span>{{ __('federations::invite.settings_save') }}</span>
                </button>
            </div>
        </div>
    @endif
</div>
@endsection
