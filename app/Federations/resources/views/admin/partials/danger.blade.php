{{--
    Delete this federation — the last card on its record, both layouts. A soft
    delete: it leaves the list and its owner's dashboard, and everything it
    holds is kept, so it can be restored from the federations list. Asks first
    through window.confirmAction (never a native dialog). Needs $federation.
--}}
<div x-data="{
        busy: false,
        async remove() {
            if (this.busy) return;
            const ok = window.confirmAction ? await window.confirmAction({
                title: @js(__('federations::messages.delete_confirm_title', ['name' => $federation->name])),
                message: @js(__('federations::messages.delete_confirm_body')),
                type: 'danger',
                confirmText: @js(__('federations::messages.delete_confirm_button')),
            }) : false;
            if (!ok) return;
            this.busy = true;
            try {
                const r = await fetch(@js(route('admin.federations.destroy', $federation->uuid)), { method: 'DELETE', headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const j = await r.json().catch(() => ({}));
                if (!r.ok || !j.success) { window.showToast && showToast('error', j.message || 'Error'); return; }
                window.showToast && showToast('success', j.message);
                setTimeout(() => { window.location.href = j.redirect; }, 700);
            } catch (e) { window.showToast && showToast('error', 'Network error — nothing was deleted.'); }
            finally { this.busy = false; }
        },
     }" class="rounded-2xl border border-red-100 bg-white shadow-sm overflow-hidden fedx-card-in {{ $dangerClass ?? '' }}">
    <div class="p-5 flex flex-col sm:flex-row sm:items-center gap-4">
        <span class="w-11 h-11 rounded-xl bg-red-50 text-red-600 grid place-items-center flex-shrink-0"><i class="bi bi-trash3 text-lg"></i></span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-bold text-gray-900 mb-0.5">{{ __('federations::messages.delete_title') }}</p>
            <p class="text-xs text-muted-foreground mb-0">{{ __('federations::messages.delete_sub') }}</p>
        </div>
        <button type="button" @click="remove()" :disabled="busy"
                class="px-4 py-2 rounded-lg text-sm font-medium border border-red-300 text-red-600 hover:bg-red-50 transition-colors disabled:opacity-60 inline-flex items-center justify-center gap-2 flex-shrink-0">
            <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-trash3'"></i>{{ __('federations::messages.delete_button') }}
        </button>
    </div>
</div>
