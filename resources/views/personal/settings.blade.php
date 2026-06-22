@extends('layouts.personal-mobile')

@section('title', __('settings.title'))

{{-- Full-bleed (Facebook-style): edge-to-edge white blocks separated by gray gutters. --}}
@section('personal-content')
<div class="-mx-4 -mt-4">
    <div class="bg-white px-4 py-4 mb-2">
        <h3 class="font-semibold text-foreground mb-3">{{ __('settings.account') }}</h3>
        <div class="space-y-3 text-sm">
            <div class="flex items-center gap-3"><i class="bi bi-person text-muted-foreground w-5"></i><span class="text-foreground">{{ $user->full_name }}</span></div>
            <div class="flex items-center gap-3"><i class="bi bi-envelope text-muted-foreground w-5"></i><span class="text-foreground truncate">{{ $user->email }}</span></div>
        </div>
    </div>

    {{-- Language --}}
    <div class="bg-white px-4 py-4 mb-2">
        <h3 class="font-semibold text-foreground mb-0.5">{{ __('settings.language') }}</h3>
        <p class="text-xs text-muted-foreground mb-3">{{ __('settings.language_hint') }}</p>
        <div class="space-y-2">
            @foreach(config('locales') as $code => $meta)
                @php $isCurrent = app()->getLocale() === $code; @endphp
                <button type="button" onclick="window.switchLocale('{{ $code }}')"
                        class="m-press w-full flex items-center justify-between p-3 rounded-xl border transition-colors {{ $isCurrent ? 'border-primary bg-accent/40' : 'border-border hover:bg-accent/30' }}">
                    <span class="flex items-center gap-3">
                        <span class="fi fi-{{ $meta['flag'] }} rounded-sm shadow-sm"></span>
                        <span class="text-sm font-medium text-foreground">{{ $meta['native'] }}</span>
                    </span>
                    @if($isCurrent)
                        <i class="bi bi-check-circle-fill text-primary text-lg"></i>
                    @else
                        <i class="bi bi-circle text-muted-foreground/40"></i>
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    <div class="bg-white divide-y divide-gray-50">
        <a href="{{ route('member.show', $user->uuid) }}" class="flex items-center justify-between p-4"><span class="text-sm text-foreground"><i class="bi bi-person-gear me-2 text-muted-foreground"></i>{{ __('settings.edit_profile') }}</span><i class="bi bi-chevron-right text-muted-foreground"></i></a>
        <a href="{{ route('security.show') }}" class="flex items-center justify-between p-4"><span class="text-sm text-foreground"><i class="bi bi-shield-lock me-2 text-muted-foreground"></i>{{ __('settings.security_password') }}</span><i class="bi bi-chevron-right text-muted-foreground"></i></a>
        <a href="{{ route('me.payments') }}" data-shell-link data-route="me.payments" class="flex items-center justify-between p-4"><span class="text-sm text-foreground"><i class="bi bi-receipt me-2 text-muted-foreground"></i>{{ __('settings.invoices') }}</span><i class="bi bi-chevron-right text-muted-foreground"></i></a>
    </div>
</div>

{{-- Inline so it survives the mobile shell's AJAX content swap (re-run on navigate). --}}
<script>
window.switchLocale = function (code) {
    if (window.__localeSaving) return;
    window.__localeSaving = true;
    fetch(@js(route('me.locale.update')), {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
        },
        body: JSON.stringify({ locale: code }),
    })
    .then(function (r) { return r.json(); })
    .then(function (d) {
        if (d && d.success) {
            window.showToast && window.showToast('success', d.message);
            // Full reload so <html dir>/font apply globally (shell only swaps content).
            setTimeout(function () { window.location.reload(); }, 350);
        } else {
            window.__localeSaving = false;
            window.showToast && window.showToast('error', (d && d.message) || @js(__('shared.error')));
        }
    })
    .catch(function () {
        window.__localeSaving = false;
        window.showToast && window.showToast('error', @js(__('shared.error')));
    });
};
</script>
@endsection
