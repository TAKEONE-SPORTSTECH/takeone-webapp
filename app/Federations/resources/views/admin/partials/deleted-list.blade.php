{{--
    Recently deleted federations, with Restore — under the list, both layouts.
    Collapsed by default; a restore lands on the record it brings back. Needs
    $deleted (uuid, name, short_name, deleted_at). Names are drawn escaped.
--}}
@if ($deleted->isNotEmpty())
<div x-data="{
        open: false, busy: null,
        async restore(uuid, url) {
            if (this.busy) return;
            this.busy = uuid;
            try {
                const r = await fetch(url, { method: 'POST', headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const j = await r.json().catch(() => ({}));
                if (!r.ok || !j.success) { window.showToast && showToast('error', j.message || 'Error'); return; }
                window.showToast && showToast('success', j.message);
                setTimeout(() => { window.location.href = j.redirect; }, 600);
            } catch (e) { window.showToast && showToast('error', 'Network error.'); }
            finally { this.busy = null; }
        },
     }" class="mt-8 {{ $deletedClass ?? '' }}">
    <button type="button" @click="open = !open"
            class="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-gray-700 transition-colors">
        <i class="bi bi-trash3"></i>{{ __('federations::messages.deleted_title') }}
        <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-muted text-gray-700">{{ $deleted->count() }}</span>
        <i class="bi bi-chevron-down text-xs transition-transform" :class="open && 'rotate-180'"></i>
    </button>
    <div x-show="open" x-cloak x-transition.opacity class="mt-3 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden divide-y divide-gray-100">
        @foreach ($deleted as $d)
            <div class="flex items-center gap-3 px-4 py-3">
                <span class="w-9 h-9 rounded-xl bg-muted text-muted-foreground grid place-items-center text-xs font-black flex-shrink-0">{{ mb_substr($d->short_name ?: $d->name, 0, 3) }}</span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-900 mb-0 break-words">{{ $d->name }}</p>
                    <p class="text-xs text-muted-foreground mb-0">{{ __('federations::messages.deleted_when', ['when' => $d->deleted_at?->diffForHumans()]) }}</p>
                </div>
                <button type="button" @click="restore(@js($d->uuid), @js(route('admin.federations.restore', $d->uuid)))" :disabled="busy"
                        class="px-3 py-1.5 rounded-full text-xs font-medium border border-primary text-primary hover:bg-primary hover:text-white transition-colors disabled:opacity-60 inline-flex items-center gap-1.5 flex-shrink-0">
                    <i class="bi" :class="busy === @js($d->uuid) ? 'bi-arrow-repeat animate-spin' : 'bi-arrow-counterclockwise'"></i>{{ __('federations::messages.deleted_restore') }}
                </button>
            </div>
        @endforeach
    </div>
</div>
@endif
