@extends('layouts.app')

@section('title', __('settings.title'))

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-6 space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">{{ __('settings.title') }}</h1>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-foreground mb-3">{{ __('settings.account') }}</h3>
        <div class="space-y-3 text-sm">
            <div class="flex items-center gap-3"><i class="bi bi-person text-muted-foreground w-5"></i><span class="text-foreground">{{ $user->full_name }}</span></div>
            <div class="flex items-center gap-3"><i class="bi bi-envelope text-muted-foreground w-5"></i><span class="text-foreground truncate">{{ $user->email }}</span></div>
        </div>
    </div>

    {{-- Language --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h3 class="font-semibold text-foreground mb-0.5">{{ __('settings.language') }}</h3>
        <p class="text-xs text-muted-foreground mb-3">{{ __('settings.language_hint') }}</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            @foreach(config('locales') as $code => $meta)
                @php $isCurrent = app()->getLocale() === $code; @endphp
                <button type="button" onclick="window.switchLocale('{{ $code }}')"
                        class="w-full flex items-center justify-between p-3 rounded-xl border transition-colors {{ $isCurrent ? 'border-primary bg-accent/40' : 'border-border hover:bg-accent/30' }}">
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

    {{-- Privacy — people discovery --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6" x-data="{ on: {{ $user->isDiscoverable() ? 'true' : 'false' }}, saving: false }">
        <h3 class="font-semibold text-foreground mb-0.5">{{ __('personal.discoverable_label') }}</h3>
        <div class="flex items-center justify-between gap-4">
            <p class="text-xs text-muted-foreground flex-1">{{ __('personal.discoverable_help') }}</p>
            <button type="button" role="switch" :aria-checked="on" :disabled="saving"
                    @click="
                        const prev = on; on = !prev; saving = true;
                        fetch(@js(route('me.discoverable.update')), { method: 'PUT', headers: { 'Content-Type':'application/json','X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,'Accept':'application/json' }, credentials:'same-origin', body: JSON.stringify({ is_discoverable: on }) })
                            .then(r => r.json())
                            .then(d => { if (!d.success) throw d; if (window.showToast) window.showToast('success', d.message); })
                            .catch(() => { on = prev; if (window.showToast) window.showToast('error', 'Could not update'); })
                            .finally(() => { saving = false; });"
                    class="relative inline-flex h-6 w-11 flex-shrink-0 rounded-full transition-colors"
                    :class="on ? 'bg-primary' : 'bg-gray-300'">
                <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform mt-0.5"
                      :class="on ? 'translate-x-5 rtl:-translate-x-5' : 'translate-x-0.5 rtl:-translate-x-0.5'"></span>
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 divide-y divide-gray-50 overflow-hidden">
        <a href="{{ route('member.show', $user->uuid) }}" class="flex items-center justify-between p-4 hover:bg-accent/30 transition-colors"><span class="text-sm text-foreground"><i class="bi bi-person-gear me-2 text-muted-foreground"></i>{{ __('settings.edit_profile') }}</span><i class="bi bi-chevron-right text-muted-foreground"></i></a>
        <a href="{{ route('security.show') }}" class="flex items-center justify-between p-4 hover:bg-accent/30 transition-colors"><span class="text-sm text-foreground"><i class="bi bi-shield-lock me-2 text-muted-foreground"></i>{{ __('settings.security_password') }}</span><i class="bi bi-chevron-right text-muted-foreground"></i></a>
        <a href="{{ route('bills.index') }}" class="flex items-center justify-between p-4 hover:bg-accent/30 transition-colors"><span class="text-sm text-foreground"><i class="bi bi-receipt me-2 text-muted-foreground"></i>{{ __('settings.invoices') }}</span><i class="bi bi-chevron-right text-muted-foreground"></i></a>
    </div>
</div>

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
            if (d.dir) document.documentElement.dir = d.dir;
            if (d.locale) {
                document.documentElement.lang = d.locale;
                try { localStorage.setItem('takeone:locale', d.locale); } catch (e) {}
            }
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
