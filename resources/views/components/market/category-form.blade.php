@props([
    'mode'      => 'create',                  // create | edit
    'action'    => '',                        // optional POST url; form UI only by default
    'eventName' => 'market-category-saved',   // CustomEvent dispatched on (demo) save
    'category'  => null,                       // existing category (edit prefill)
])

@php
    $init = [
        'label' => $category['label'] ?? '',
        'key'   => $category['key']   ?? '',
        'icon'  => $category['icon']  ?? 'bi-grid-1x2',
        'keyTouched' => isset($category['key']),
    ];
    $iconChoices = ['bi-grid-1x2','bi-bag','bi-bicycle','bi-cup-hot','bi-ticket-perforated','bi-person-arms-up','bi-trophy','bi-droplet-half','bi-basket','bi-box-seam','bi-lightning-charge-fill','bi-shield-check','bi-heart-pulse','bi-water','bi-stars','bi-tag'];
@endphp

<div x-data="marketCategoryForm({{ Illuminate\Support\Js::from($init) }}, {{ Illuminate\Support\Js::from(['action' => $action, 'event' => $eventName, 'mode' => $mode]) }})"
     class="space-y-5">

    {{-- Signature: live category chip (exactly as it renders in the market) --}}
    <div>
        <p class="text-xs font-medium text-muted-foreground mb-2 flex items-center gap-1.5"><i class="bi bi-eye"></i> {{ __('market.live_preview') }}</p>
        <span class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-xs font-semibold bg-primary text-white border border-primary shadow-sm">
            <i class="bi" :class="icon"></i> <span x-text="label || '{{ __('market.category') }}'"></span>
        </span>
    </div>

    <form @submit.prevent="save()" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.category_name') }} <span class="text-red-500">*</span></label>
            <input type="text" x-model="label" name="label" required maxlength="30"
                   @input="if(!keyTouched) key = slug(label)"
                   placeholder="{{ __('market.category_name_ph') }}"
                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('market.category_key') }}</label>
            <input type="text" x-model="key" name="key" maxlength="30" @input="keyTouched = true; key = slug(key)"
                   placeholder="auto"
                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm font-mono">
            <p class="text-[11px] text-muted-foreground mt-1">{{ __('market.category_key_hint') }}</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('market.icon') }}</label>
            <div class="flex flex-wrap gap-1.5">
                @foreach($iconChoices as $ic)
                    <button type="button" @click="icon='{{ $ic }}'"
                            class="w-9 h-9 rounded-lg grid place-items-center transition-colors"
                            :class="icon==='{{ $ic }}' ? 'bg-accent text-primary ring-2 ring-primary' : 'bg-muted text-muted-foreground hover:bg-accent'">
                        <i class="bi {{ $ic }}"></i>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-1">
            <button type="button" @click="$dispatch('market-form-cancel')"
                    class="px-4 py-2.5 rounded-lg text-sm font-medium text-muted-foreground hover:bg-muted transition-colors">{{ __('shared.cancel') }}</button>
            <button type="submit" :disabled="!label.trim() || saving"
                    class="px-5 py-2.5 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary/90 transition-colors disabled:opacity-50 inline-flex items-center gap-2">
                <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-plus-lg'"></i>
                <span x-text="mode==='edit' ? @js(__('market.save_changes')) : @js(__('market.add_category'))"></span>
            </button>
        </div>
    </form>
</div>

@once
<script>
window.marketCategoryForm = function (init, opts) {
    return {
        ...init,
        saving: false,
        mode: opts.mode || 'create',
        _action: opts.action || '',
        _event: opts.event || 'market-category-saved',
        slug(s) { return (s || '').toString().toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); },
        async save() {
            if (!this.label.trim() || this.saving) return;
            const data = { label: this.label.trim(), key: this.key || this.slug(this.label), icon: this.icon };

            if (!this._action) {
                this.$dispatch(this._event, data);
                window.showToast && window.showToast('success',
                    this.mode === 'edit' ? @js(__('market.category_updated')) : @js(__('market.category_added')));
                return;
            }
            this.saving = true;
            try {
                const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';
                const res = await fetch(this._action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    credentials: 'same-origin', body: JSON.stringify(data),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || d.success === false) throw new Error(d.message || @js(__('shared.error')));
                this.$dispatch(this._event, d.category || data);
                window.showToast && window.showToast('success', d.message || @js(__('market.category_added')));
            } catch (e) {
                window.showToast && window.showToast('error', e.message);
            } finally {
                this.saving = false;
            }
        },
    };
};
</script>
@endonce
