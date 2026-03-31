@extends('layouts.app')

@section('title', 'Recovery Codes')

@section('content')
<div class="tf-container">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-foreground">
            {{ $fresh ? 'Two-Factor Authentication Enabled' : 'New Recovery Codes' }}
        </h1>
        <p class="text-sm text-muted-foreground">
            {{ $fresh ? 'Your account is now protected. ' : '' }}Save these recovery codes somewhere safe.
        </p>
    </div>

    @if($fresh)
    <div class="alert alert-success mb-4">
        <i class="bi bi-shield-check mr-2"></i>
        Two-factor authentication has been successfully enabled on your account.
    </div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-6">
            <div class="flex items-start gap-3 mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                <i class="bi bi-exclamation-triangle text-yellow-600 mt-0.5 flex-shrink-0"></i>
                <div class="text-sm text-yellow-800">
                    <strong>Store these codes safely.</strong> Each code can only be used once.
                    If you lose access to your authenticator app, these are the only way to recover your account.
                    They will not be shown again.
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 mb-6">
                @foreach($recoveryCodes as $code)
                <code class="block bg-gray-50 border border-border rounded px-3 py-2 text-sm font-mono text-center tracking-widest">
                    {{ $code }}
                </code>
                @endforeach
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <button onclick="copyRecoveryCodes()" class="btn btn-outline-secondary">
                    <i class="bi bi-clipboard mr-2"></i>Copy All Codes
                </button>
                <a href="{{ route('security.show') }}" class="btn btn-primary">
                    <i class="bi bi-check-lg mr-1"></i>Done
                </a>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
function copyRecoveryCodes() {
    const codes = @json($recoveryCodes);
    navigator.clipboard.writeText(codes.join('\n')).then(() => {
        const btn = document.querySelector('button[onclick="copyRecoveryCodes()"]');
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check-lg mr-2"></i>Copied!';
        setTimeout(() => btn.innerHTML = original, 2000);
    });
}
</script>
@endpush

@endsection
