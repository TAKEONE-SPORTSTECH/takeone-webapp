@extends('layouts.app')

@section('title', __('security.security_index_title'))

@section('content')
<div class="tf-container">

    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-foreground">{{ __('security.security_index_title') }}</h1>
            <p class="text-sm text-muted-foreground">{{ __('security.security_index_subtitle') }}</p>
        </div>
    </div>

    {{-- Two-Factor Authentication Card (full-bleed on mobile, inset on desktop) --}}
    <div class="card border-0 shadow-sm mb-6 -mx-4 sm:mx-0 rounded-none sm:rounded-xl">
        <div class="card-body p-6">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center
                        {{ $user->hasTwoFactorEnabled() ? 'bg-green-100' : 'bg-gray-100' }}">
                        <i class="bi bi-shield-{{ $user->hasTwoFactorEnabled() ? 'check text-green-600' : 'lock text-gray-400' }}"
                           style="font-size:1.4rem;"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <h5 class="font-semibold text-foreground mb-0.5">{{ __('security.security_index_2fa_title') }}</h5>
                            <p class="text-sm text-muted-foreground mb-0">
                                @if($user->hasTwoFactorEnabled())
                                    <span class="text-green-600 font-medium"><i class="bi bi-check-circle me-1"></i>{{ __('security.security_index_enabled') }}</span>
                                    — {{ __('security.security_index_2fa_enabled_desc') }}
                                @else
                                    <span class="text-muted-foreground font-medium"><i class="bi bi-dash-circle me-1"></i>{{ __('security.security_index_disabled') }}</span>
                                    — {{ __('security.security_index_2fa_disabled_desc') }}
                                @endif
                            </p>
                        </div>
                        @if($user->hasTwoFactorEnabled())
                            <button class="btn btn-sm btn-outline-danger shrink-0" data-bs-toggle="modal" data-bs-target="#disable2faModal">
                                <i class="bi bi-shield-x me-1"></i>{{ __('security.security_index_disable_2fa') }}
                            </button>
                        @else
                            <form action="{{ route('security.2fa.setup') }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary shrink-0">
                                    <i class="bi bi-shield-plus me-1"></i>{{ __('security.security_index_enable_2fa') }}
                                </button>
                            </form>
                        @endif
                    </div>

                    @if($user->hasTwoFactorEnabled())
                    <hr class="my-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-foreground mb-0.5">{{ __('security.security_index_recovery_codes') }}</p>
                            <p class="text-sm text-muted-foreground mb-0">{{ __('security.security_index_recovery_codes_desc') }}</p>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary shrink-0" data-bs-toggle="modal" data-bs-target="#regenCodesModal">
                            <i class="bi bi-arrow-repeat me-1"></i>{{ __('security.security_index_regenerate') }}
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Change Password Card (full-bleed on mobile, inset on desktop) --}}
    <div class="card border-0 shadow-sm -mx-4 sm:mx-0 rounded-none sm:rounded-xl">
        <div class="card-body p-6">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center">
                        <i class="bi bi-key text-gray-400" style="font-size:1.4rem;"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <h5 class="font-semibold text-foreground mb-0.5">{{ __('security.security_index_change_password') }}</h5>
                    <p class="text-sm text-muted-foreground mb-4">{{ __('security.security_index_change_password_desc') }}</p>

                    <form action="{{ route('security.password.change') }}" method="POST" class="space-y-4 max-w-sm">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">{{ __('security.security_index_current_password') }}</label>
                            <input type="password" name="current_password" autocomplete="current-password"
                                   class="tf-input @error('current_password') border-red-500 @enderror">
                            @error('current_password')
                                <span class="tf-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">{{ __('security.security_index_new_password') }}</label>
                            <input type="password" name="password" autocomplete="new-password"
                                   class="tf-input @error('password') border-red-500 @enderror">
                            @error('password')
                                <span class="tf-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">{{ __('security.security_index_confirm_new_password') }}</label>
                            <input type="password" name="password_confirmation" autocomplete="new-password"
                                   class="tf-input">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>{{ __('security.security_index_update_password') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Disable 2FA Modal --}}
@if($user->hasTwoFactorEnabled())
<div class="modal fade" id="disable2faModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-b border-border">
                <h5 class="modal-title font-semibold">{{ __('security.security_index_disable_2fa_modal_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('security.2fa.disable') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <p class="text-sm text-muted-foreground mb-4">
                        {{ __('security.security_index_disable_2fa_modal_desc') }}
                    </p>
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                           class="tf-input @error('code') border-red-500 @enderror"
                           placeholder="{{ __('security.security_index_code_or_recovery_placeholder') }}" autofocus>
                    @error('code')
                        <span class="tf-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="modal-footer border-t border-border">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('shared.cancel') }}</button>
                    <button type="submit" class="btn btn-danger">{{ __('security.security_index_disable_2fa') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Regenerate Recovery Codes Modal --}}
<div class="modal fade" id="regenCodesModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-b border-border">
                <h5 class="modal-title font-semibold">{{ __('security.security_index_regen_codes_modal_title') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('security.2fa.recovery-codes') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <p class="text-sm text-muted-foreground mb-4">
                        {{ __('security.security_index_regen_codes_modal_desc') }}
                    </p>
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                           class="tf-input @error('code') border-red-500 @enderror"
                           placeholder="{{ __('security.security_index_code_placeholder') }}">
                    @error('code')
                        <span class="tf-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="modal-footer border-t border-border">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('shared.cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('security.security_index_regenerate_codes') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if($errors->any())
<script>
    document.addEventListener('DOMContentLoaded', function () {
        @if($errors->has('code'))
            @php $open = request()->routeIs('security.show') ? 'disable2faModal' : null; @endphp
            @if($open)
            var modal = new bootstrap.Modal(document.getElementById('{{ $open }}'));
            modal.show();
            @endif
        @endif
    });
</script>
@endif

@endsection
