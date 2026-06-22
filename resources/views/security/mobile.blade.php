@extends('layouts.personal-mobile')

@section('title', __('security.title'))

{{-- Mobile Security — full-bleed, animated. Mirrors the desktop security page
     (2FA, recovery codes, password) but with a creative mobile-first layout. --}}
@section('personal-content')
@php $twoFa = $user->hasTwoFactorEnabled(); @endphp

<div class="-mx-4 -mt-4"
     x-data="securityMobile({ codeError: @js($errors->has('code')) })"
     x-init="init()">

    {{-- ===== Hero status ===== --}}
    <div class="m-hero relative overflow-hidden px-5 pt-7 pb-8 text-white
                {{ $twoFa ? 'bg-gradient-to-br from-green-600 via-green-500 to-emerald-400'
                          : 'bg-gradient-to-br from-primary via-primary to-violet-500' }}">
        <div class="absolute -right-6 -top-8 w-32 h-32 rounded-full bg-white/10 m-float"></div>
        <div class="absolute -left-8 bottom-0 w-28 h-28 rounded-full bg-white/5"></div>

        <div class="relative flex items-center gap-4">
            <div class="w-16 h-16 rounded-2xl bg-white/15 backdrop-blur flex items-center justify-center shrink-0 m-press">
                <i class="bi bi-shield-{{ $twoFa ? 'check' : 'lock' }}" style="font-size:2rem;"></i>
            </div>
            <div class="min-w-0">
                <h1 class="text-xl font-bold leading-tight">{{ __('security.security') }}</h1>
                <p class="text-sm text-white/80 mt-0.5">
                    {{ $twoFa ? __('security.protected_message') : __('security.lockdown_message') }}
                </p>
            </div>
        </div>

        <div class="relative mt-5 inline-flex items-center gap-1.5 text-xs font-semibold rounded-full px-3 py-1.5 bg-white/15 backdrop-blur">
            <i class="bi bi-{{ $twoFa ? 'check-circle-fill' : 'exclamation-circle-fill' }}"></i>
            {{ $twoFa ? __('security.two_factor_on') : __('security.two_factor_off') }}
        </div>
    </div>

    {{-- ===== Two-Factor card ===== --}}
    <div class="m-card bg-white px-4 py-5 mb-2">
        <div class="flex items-start gap-3">
            <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0
                        {{ $twoFa ? 'bg-green-100 text-green-600' : 'bg-muted text-muted-foreground' }}">
                <i class="bi bi-phone" style="font-size:1.2rem;"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="font-semibold text-foreground">{{ __('security.two_factor_auth') }}</h2>
                <p class="text-sm text-muted-foreground mt-0.5">
                    @if($twoFa)
                        {{ __('security.protected_with_app') }}
                    @else
                        {{ __('security.add_otp_desc') }}
                    @endif
                </p>
            </div>
        </div>

        <div class="mt-4">
            @if($twoFa)
                <button type="button" @click="open('disable')"
                        class="m-press w-full flex items-center justify-center gap-2 rounded-xl border border-red-200 text-red-600 bg-red-50/60 py-3 text-sm font-semibold active:bg-red-100 transition-colors">
                    <i class="bi bi-shield-x"></i> {{ __('security.disable_2fa') }}
                </button>
            @else
                <form action="{{ route('security.2fa.setup') }}" method="POST">
                    @csrf
                    <button type="submit"
                            class="m-press w-full flex items-center justify-center gap-2 rounded-xl bg-primary text-white py-3 text-sm font-semibold active:bg-primary/90 transition-colors shadow-sm">
                        <i class="bi bi-shield-plus"></i> {{ __('security.enable_2fa') }}
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- ===== Recovery codes (only when 2FA on) ===== --}}
    @if($twoFa)
    <div class="m-card bg-white px-4 py-5 mb-2">
        <div class="flex items-start gap-3">
            <div class="w-11 h-11 rounded-xl bg-accent text-primary flex items-center justify-center shrink-0">
                <i class="bi bi-key" style="font-size:1.2rem;"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="font-semibold text-foreground">{{ __('security.recovery_codes') }}</h2>
                <p class="text-sm text-muted-foreground mt-0.5">{{ __('security.recovery_codes_desc') }}</p>
            </div>
        </div>
        <button type="button" @click="open('regen')"
                class="m-press mt-4 w-full flex items-center justify-center gap-2 rounded-xl border border-border text-foreground bg-white py-3 text-sm font-semibold active:bg-muted transition-colors">
            <i class="bi bi-arrow-repeat"></i> {{ __('security.regenerate_codes') }}
        </button>
    </div>
    @endif

    {{-- ===== Change password ===== --}}
    <div class="m-card bg-white px-4 py-5">
        <div class="flex items-start gap-3 mb-4">
            <div class="w-11 h-11 rounded-xl bg-muted text-muted-foreground flex items-center justify-center shrink-0">
                <i class="bi bi-lock" style="font-size:1.2rem;"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h2 class="font-semibold text-foreground">{{ __('security.change_password') }}</h2>
                <p class="text-sm text-muted-foreground mt-0.5">{{ __('security.change_password_desc') }}</p>
            </div>
        </div>

        <form action="{{ route('security.password.change') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="tf-label">{{ __('security.current_password') }}</label>
                <input type="password" name="current_password" autocomplete="current-password"
                       class="tf-input @error('current_password') border-red-500 @enderror">
                @error('current_password')<span class="tf-error">{{ $message }}</span>@enderror
            </div>
            <div>
                <label class="tf-label">{{ __('security.new_password') }}</label>
                <input type="password" name="password" autocomplete="new-password"
                       class="tf-input @error('password') border-red-500 @enderror">
                @error('password')<span class="tf-error">{{ $message }}</span>@enderror
            </div>
            <div>
                <label class="tf-label">{{ __('security.confirm_new_password') }}</label>
                <input type="password" name="password_confirmation" autocomplete="new-password" class="tf-input">
            </div>
            <button type="submit"
                    class="m-press w-full flex items-center justify-center gap-2 rounded-xl bg-primary text-white py-3 text-sm font-semibold active:bg-primary/90 transition-colors shadow-sm">
                <i class="bi bi-check-lg"></i> {{ __('security.update_password') }}
            </button>
        </form>
    </div>

    {{-- ===== Bottom-sheet: 2FA actions needing a code ===== --}}
    @if($twoFa)
    <div x-show="sheet" x-cloak class="fixed inset-0 z-[60]" style="display:none;">
        <div class="absolute inset-0 bg-black/50" @click="close()"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

        <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-3xl p-5 pb-8 shadow-2xl"
             x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full">
            <div class="w-10 h-1.5 rounded-full bg-gray-200 mx-auto mb-4"></div>

            {{-- Disable --}}
            <template x-if="mode === 'disable'">
                <form action="{{ route('security.2fa.disable') }}" method="POST">
                    @csrf
                    <h3 class="text-lg font-bold text-foreground">{{ __('security.disable_two_factor') }}</h3>
                    <p class="text-sm text-muted-foreground mt-1 mb-4">{{ __('security.disable_confirm_desc') }}</p>
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" x-ref="disableCode"
                           class="tf-input text-center tracking-widest @error('code') border-red-500 @enderror"
                           placeholder="{{ __('security.code_placeholder_long') }}">
                    @error('code')<span class="tf-error">{{ $message }}</span>@enderror
                    <div class="grid grid-cols-2 gap-3 mt-5">
                        <button type="button" @click="close()" class="m-press rounded-xl border border-border py-3 text-sm font-semibold text-foreground active:bg-muted">{{ __('security.cancel') }}</button>
                        <button type="submit" class="m-press rounded-xl bg-destructive text-white py-3 text-sm font-semibold active:opacity-90">{{ __('security.disable') }}</button>
                    </div>
                </form>
            </template>

            {{-- Regenerate --}}
            <template x-if="mode === 'regen'">
                <form action="{{ route('security.2fa.recovery-codes') }}" method="POST">
                    @csrf
                    <h3 class="text-lg font-bold text-foreground">{{ __('security.regenerate_codes_title') }}</h3>
                    <p class="text-sm text-muted-foreground mt-1 mb-4">{{ __('security.regenerate_confirm_desc') }}</p>
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" x-ref="regenCode"
                           class="tf-input text-center tracking-widest @error('code') border-red-500 @enderror"
                           placeholder="{{ __('security.code_placeholder') }}">
                    @error('code')<span class="tf-error">{{ $message }}</span>@enderror
                    <div class="grid grid-cols-2 gap-3 mt-5">
                        <button type="button" @click="close()" class="m-press rounded-xl border border-border py-3 text-sm font-semibold text-foreground active:bg-muted">{{ __('security.cancel') }}</button>
                        <button type="submit" class="m-press rounded-xl bg-primary text-white py-3 text-sm font-semibold active:bg-primary/90">{{ __('security.regenerate') }}</button>
                    </div>
                </form>
            </template>
        </div>
    </div>
    @endif
</div>

<script>
    function securityMobile(opts) {
        return {
            sheet: false,
            mode: null,
            init() {
                // A code validation error means the disable sheet was the one submitted — reopen it.
                if (opts.codeError) this.open('disable');
            },
            open(mode) {
                this.mode = mode;
                this.sheet = true;
                this.$nextTick(() => {
                    const ref = mode === 'disable' ? this.$refs.disableCode : this.$refs.regenCode;
                    ref && ref.focus();
                });
            },
            close() { this.sheet = false; },
        };
    }
</script>
@endsection
