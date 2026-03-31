@extends('layouts.app')

@section('title', 'Security Settings')

@section('content')
<div class="tf-container">

    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-foreground">Security Settings</h1>
            <p class="text-sm text-muted-foreground">Manage your account security and two-factor authentication</p>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success mb-4" x-data="{ show: true }" x-show="show">
        <i class="bi bi-check-circle mr-2"></i>{{ session('success') }}
        <button type="button" class="float-end" @click="show = false"><i class="bi bi-x-lg"></i></button>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger mb-4" x-data="{ show: true }" x-show="show">
        <i class="bi bi-exclamation-triangle mr-2"></i>{{ session('error') }}
        <button type="button" class="float-end" @click="show = false"><i class="bi bi-x-lg"></i></button>
    </div>
    @endif

    {{-- Two-Factor Authentication Card --}}
    <div class="card border-0 shadow-sm mb-6">
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
                            <h5 class="font-semibold text-foreground mb-0.5">Two-Factor Authentication</h5>
                            <p class="text-sm text-muted-foreground mb-0">
                                @if($user->hasTwoFactorEnabled())
                                    <span class="text-green-600 font-medium"><i class="bi bi-check-circle mr-1"></i>Enabled</span>
                                    — Your account is protected with Google Authenticator.
                                @else
                                    <span class="text-muted-foreground font-medium"><i class="bi bi-dash-circle mr-1"></i>Disabled</span>
                                    — Add an extra layer of security to your account.
                                @endif
                            </p>
                        </div>
                        @if($user->hasTwoFactorEnabled())
                            <button class="btn btn-sm btn-outline-danger shrink-0" data-bs-toggle="modal" data-bs-target="#disable2faModal">
                                <i class="bi bi-shield-x mr-1"></i>Disable 2FA
                            </button>
                        @else
                            <form action="{{ route('security.2fa.setup') }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary shrink-0">
                                    <i class="bi bi-shield-plus mr-1"></i>Enable 2FA
                                </button>
                            </form>
                        @endif
                    </div>

                    @if($user->hasTwoFactorEnabled())
                    <hr class="my-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-foreground mb-0.5">Recovery Codes</p>
                            <p class="text-sm text-muted-foreground mb-0">One-time backup codes if you lose access to your authenticator app.</p>
                        </div>
                        <button class="btn btn-sm btn-outline-secondary shrink-0" data-bs-toggle="modal" data-bs-target="#regenCodesModal">
                            <i class="bi bi-arrow-repeat mr-1"></i>Regenerate
                        </button>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Change Password Card --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-6">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center">
                        <i class="bi bi-key text-gray-400" style="font-size:1.4rem;"></i>
                    </div>
                </div>
                <div class="flex-1">
                    <h5 class="font-semibold text-foreground mb-0.5">Change Password</h5>
                    <p class="text-sm text-muted-foreground mb-4">All other active sessions will be signed out when you change your password.</p>

                    <form action="{{ route('security.password.change') }}" method="POST" class="space-y-4 max-w-sm">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">Current Password</label>
                            <input type="password" name="current_password" autocomplete="current-password"
                                   class="tf-input @error('current_password') border-red-500 @enderror">
                            @error('current_password')
                                <span class="tf-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">New Password</label>
                            <input type="password" name="password" autocomplete="new-password"
                                   class="tf-input @error('password') border-red-500 @enderror">
                            @error('password')
                                <span class="tf-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">Confirm New Password</label>
                            <input type="password" name="password_confirmation" autocomplete="new-password"
                                   class="tf-input">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg mr-1"></i>Update Password
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
                <h5 class="modal-title font-semibold">Disable Two-Factor Authentication</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('security.2fa.disable') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <p class="text-sm text-muted-foreground mb-4">
                        Enter your current authenticator code (or a recovery code) to confirm.
                    </p>
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                           class="tf-input @error('code') border-red-500 @enderror"
                           placeholder="6-digit code or recovery code" autofocus>
                    @error('code')
                        <span class="tf-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="modal-footer border-t border-border">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Disable 2FA</button>
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
                <h5 class="modal-title font-semibold">Regenerate Recovery Codes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('security.2fa.recovery-codes') }}" method="POST">
                @csrf
                <div class="modal-body p-4">
                    <p class="text-sm text-muted-foreground mb-4">
                        Your existing recovery codes will be invalidated. Enter your authenticator code to confirm.
                    </p>
                    <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                           class="tf-input @error('code') border-red-500 @enderror"
                           placeholder="6-digit code">
                    @error('code')
                        <span class="tf-error">{{ $message }}</span>
                    @enderror
                </div>
                <div class="modal-footer border-t border-border">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Regenerate Codes</button>
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
