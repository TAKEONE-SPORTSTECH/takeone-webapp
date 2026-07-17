@extends('layouts.app')

@section('title', __('personal.personal_get_app_page_title'))

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6" x-data="getAppHub()" x-init="init()">
    <div class="max-w-2xl space-y-5">

        {{-- ===== App identity ===== --}}
        <div class="relative overflow-hidden rounded-2xl border border-gray-100 shadow-sm bg-white p-8 text-center">
            <div class="absolute -top-10 -end-10 w-32 h-32 rounded-full bg-primary/10 blur-2xl"></div>
            <div class="absolute -bottom-12 -start-8 w-32 h-32 rounded-full bg-accent/50 blur-2xl"></div>
            <div class="relative">
                <div class="w-20 h-20 mx-auto rounded-2xl bg-primary/10 grid place-items-center mb-3 overflow-hidden ring-1 ring-primary/10">
                    <img src="{{ asset('images/logo.png') }}" alt="TAKEONE" class="w-14 h-14 object-contain">
                </div>
                <h1 class="text-xl font-bold text-gray-900">TAKEONE</h1>
                <p class="text-sm text-muted-foreground mt-1">{{ __('personal.personal_get_app_tagline') }}</p>
            </div>
        </div>

        {{-- ===== Loading ===== --}}
        <div x-show="mode==='loading'" x-cloak class="rounded-2xl border border-gray-100 shadow-sm bg-white p-8 flex flex-col items-center gap-3">
            <span class="w-8 h-8 rounded-full border-[3px] border-primary/30 border-t-primary animate-spin"></span>
            <p class="text-sm text-muted-foreground">{{ __('personal.personal_get_app_checking_updates') }}</p>
        </div>

        {{-- ===== Browser: download ===== --}}
        <div x-show="mode==='browser'" x-cloak class="space-y-4">
            <div class="rounded-2xl border border-gray-100 shadow-sm bg-white p-6">
                <div class="flex items-center gap-2 text-xs font-semibold text-primary mb-2">
                    <i class="bi bi-android2"></i><span>{{ __('personal.personal_get_app_android_app') }}</span>
                </div>
                <h2 class="text-lg font-bold text-gray-900">{{ __('personal.personal_get_app_install_heading') }}</h2>
                <p class="text-sm text-muted-foreground mt-1">{{ __('personal.personal_get_app_install_subtitle') }}</p>

                @if($apkExists)
                    <a :href="apkUrl" download
                       class="mt-4 inline-flex items-center justify-center gap-2 bg-primary text-white px-4 py-3 rounded-lg font-semibold hover:bg-primary/90 transition-colors shadow-sm">
                        <i class="bi bi-download text-lg"></i> {{ __('personal.personal_get_app_download_android') }}
                    </a>
                    <p class="text-[11px] text-muted-foreground mt-2">{{ __('personal.personal_get_app_version_line', ['version' => $versionName]) }}</p>
                @else
                    <div class="mt-4 inline-flex items-center justify-center gap-2 bg-muted text-muted-foreground px-4 py-3 rounded-lg font-semibold cursor-not-allowed">
                        <i class="bi bi-hourglass-split"></i> {{ __('personal.personal_get_app_coming_soon') }}
                    </div>
                @endif
            </div>

            {{-- Install steps --}}
            <div class="rounded-2xl border border-gray-100 shadow-sm bg-white p-5">
                <h3 class="text-sm font-bold text-gray-900 mb-3">{{ __('personal.personal_get_app_how_to_install') }}</h3>
                <ol class="space-y-3">
                    @foreach([
                        ['1','bi-download', __('personal.personal_get_app_step_1')],
                        ['2','bi-file-earmark-arrow-down', __('personal.personal_get_app_step_2')],
                        ['3','bi-shield-check', __('personal.personal_get_app_step_3')],
                        ['4','bi-check-circle', __('personal.personal_get_app_step_4')],
                    ] as $step)
                        <li class="flex items-start gap-3">
                            <span class="w-7 h-7 rounded-full bg-accent text-primary grid place-items-center text-xs font-bold flex-shrink-0">{{ $step[0] }}</span>
                            <p class="text-sm text-foreground pt-1">{{ $step[2] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>

            <p class="text-[11px] text-muted-foreground">
                {{ __('personal.personal_get_app_already_installed') }}
            </p>
        </div>

        {{-- ===== Installed & up to date ===== --}}
        <div x-show="mode==='uptodate'" x-cloak class="rounded-2xl border border-emerald-100 shadow-sm bg-gradient-to-br from-emerald-50 to-white p-6 text-center">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-emerald-100 grid place-items-center mb-3">
                <i class="bi bi-check2-circle text-3xl text-emerald-600"></i>
            </div>
            <h2 class="text-lg font-bold text-gray-900">{{ __('personal.personal_get_app_up_to_date') }}</h2>
            <p class="text-sm text-muted-foreground mt-1">{{ __('personal.personal_get_app_running_latest') }}</p>
            <span class="inline-flex items-center gap-1 mt-3 px-3 py-1 rounded-full bg-white border border-emerald-100 text-xs font-semibold text-emerald-700">
                <i class="bi bi-phone"></i> {{ __('personal.personal_get_app_version_label') }} <span x-text="current"></span>
            </span>
            <button @click="recheck()" class="mt-4 block mx-auto text-xs font-semibold text-primary hover:underline">
                <i class="bi bi-arrow-clockwise"></i> {{ __('personal.personal_get_app_check_again') }}
            </button>
        </div>

        {{-- ===== Installed & update available ===== --}}
        <div x-show="mode==='update'" x-cloak class="space-y-4">
            <div class="rounded-2xl border border-amber-200 shadow-sm bg-gradient-to-br from-amber-50 to-white p-6 text-center">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-100 grid place-items-center mb-3">
                    <i class="bi bi-arrow-up-circle text-3xl text-amber-600"></i>
                </div>
                <h2 class="text-lg font-bold text-gray-900">{{ __('personal.personal_get_app_update_available') }}</h2>
                <p class="text-sm text-muted-foreground mt-1">
                    <span class="line-through opacity-60">v<span x-text="current"></span></span>
                    <i class="bi bi-arrow-right mx-1 text-amber-600"></i>
                    <span class="font-semibold text-amber-700">v<span x-text="latest"></span></span>
                </p>

                <button @click="doUpdate()"
                        class="mt-4 inline-flex items-center justify-center gap-2 bg-primary text-white px-4 py-3 rounded-lg font-semibold hover:bg-primary/90 transition-colors shadow-sm">
                    <i class="bi bi-download text-lg"></i> {{ __('personal.personal_get_app_update_now') }}
                </button>
                <p class="text-[11px] text-muted-foreground mt-2">{{ __('personal.personal_get_app_update_hint') }}</p>
            </div>

            {{-- What's new --}}
            <div x-show="notes" x-cloak class="rounded-2xl border border-gray-100 shadow-sm bg-white p-5">
                <h3 class="text-sm font-bold text-gray-900 mb-2"><i class="bi bi-stars text-primary me-1"></i> {{ __('personal.personal_get_app_whats_new') }}</h3>
                <p class="text-sm text-foreground whitespace-pre-line" x-text="notes"></p>
            </div>
        </div>

    </div>
</div>

<script>
window.getAppHub = function () {
    return {
        mode: 'loading',
        current: '',
        latest: @json($versionName),
        notes: @json($notes),
        apkUrl: @json($apkUrl),
        apkExists: @json($apkExists),
        _res: null,

        init() {
            if (!window.TakeoneApp || !window.TakeoneApp.isNative()) { this.mode = 'browser'; return; }
            this.recheck();
        },
        recheck() {
            this.mode = 'loading';
            var self = this;
            window.TakeoneApp.check().then(function (res) {
                self._res = res;
                self.current = res.current || '';
                self.latest = res.latest || self.latest;
                self.notes = res.notes || self.notes;
                self.mode = res.state === 'update' ? 'update' : (res.state === 'browser' ? 'browser' : 'uptodate');
            });
        },
        doUpdate() {
            window.TakeoneApp.startUpdate(this._res || { url: this.apkUrl });
        },
    };
};
</script>
@endsection
