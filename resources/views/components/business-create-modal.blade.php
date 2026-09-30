{{-- Super-admin: create a business (chain) for an owner. Opens on the `open-business-create`
     window event; on success dispatches `business-created` {business, html} and then opens
     `open-business-edit` so clubs can be added straight away. Owner comes from
     <x-user-picker-modal> (must be on the same page). AJAX, no reload. --}}
<div x-data="businessCreateModal()" x-cloak>
    <div x-show="open" class="fixed inset-0 z-[60] flex items-center justify-center p-4 sm:p-6"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

        <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="close()"></div>

        <div class="relative bg-card rounded-3xl shadow-2xl ring-1 ring-black/5 w-full max-w-lg max-h-[calc(100vh-3rem)] flex flex-col overflow-hidden"
             x-show="open" @keydown.escape.window="open && !picking && close()"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3 scale-[0.98]" x-transition:enter-end="opacity-100 translate-y-0 scale-100">

            {{-- Gradient header band --}}
            <div class="flex-shrink-0 px-5 pt-5 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                 style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="relative flex items-start gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                        <i class="bi bi-buildings-fill text-xl"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg font-black leading-tight" x-text="form.name.trim() || '{{ __('platform.business_create_title') }}'"></h3>
                        <p class="text-[12px] text-white/85 mt-0.5">{{ __('platform.business_create_subtitle') }}</p>
                    </div>
                    <button type="button" @click="close()" aria-label="{{ __('shared.close') }}"
                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            {{-- Body --}}
            <form @submit.prevent="submit()" class="flex-1 overflow-y-auto px-5 py-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.business_create_name') }}</label>
                    <input type="text" x-model="form.name" x-ref="name" maxlength="120" required
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                           :class="errors.name && 'border-red-300'">
                    <p x-show="errors.name" x-text="errors.name" class="text-xs text-red-600 mt-1"></p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.business_create_description') }}</label>
                    <textarea x-model="form.description" rows="3" maxlength="1000"
                              class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.business_create_owner') }}</label>
                    <button type="button" @click="pickOwner()"
                            class="w-full flex items-center gap-3 text-start rounded-xl border px-3 py-2.5 transition-colors hover:border-primary/40 hover:bg-accent/30"
                            :class="errors.owner_user_id ? 'border-red-300' : (owner ? 'border-primary bg-primary/5' : 'border-gray-200')">
                        <span class="w-9 h-9 rounded-lg bg-accent grid place-items-center flex-shrink-0">
                            <i class="bi text-primary" :class="owner ? 'bi-person-check-fill' : 'bi-person-plus'"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-foreground truncate" x-text="owner ? owner.full_name : '{{ __('platform.business_create_pick_owner') }}'"></span>
                            <span class="block text-[11px] text-muted-foreground truncate" x-show="owner" x-text="owner?.email"></span>
                        </span>
                        <i class="bi bi-chevron-right rtl:rotate-180 text-gray-400"></i>
                    </button>
                    <p x-show="errors.owner_user_id" x-text="errors.owner_user_id" class="text-xs text-red-600 mt-1"></p>
                </div>

                <p class="flex items-start gap-1.5 text-xs text-muted-foreground">
                    <i class="bi bi-info-circle mt-0.5"></i>
                    <span>{{ __('platform.business_create_note') }}</span>
                </p>
            </form>

            {{-- Footer --}}
            <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 flex items-center justify-end gap-2"
                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                <button type="button" @click="close()"
                        class="border border-gray-200 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg transition-colors font-medium text-sm">
                    {{ __('shared.cancel') }}
                </button>
                <button type="button" @click="submit()" :disabled="saving"
                        class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm disabled:opacity-60">
                    <span x-show="!saving"><i class="bi bi-plus-lg me-1"></i>{{ __('platform.business_create_submit') }}</span>
                    <span x-show="saving" x-cloak><i class="bi bi-arrow-repeat me-1 animate-spin inline-block"></i>{{ __('platform.business_create_saving') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function businessCreateModal() {
    return {
        open: false,
        saving: false,
        picking: false,
        owner: null,
        form: { name: '', description: '' },
        errors: {},

        init() {
            window.addEventListener('open-business-create', () => this.show());
            window.addEventListener('user-selected', (e) => {
                if (!this.picking) return;
                this.picking = false;
                this.owner = e.detail;
                delete this.errors.owner_user_id;
            });
        },

        show() {
            this.form = { name: '', description: '' };
            this.owner = null;
            this.errors = {};
            this.saving = false;
            this.open = true;
            this.$nextTick(() => this.$refs.name?.focus());
        },

        close() { this.open = false; this.picking = false; },

        pickOwner() {
            if (typeof window.openUserPickerModal !== 'function') {
                window.showToast('error', '{{ __("shared.business_edit_modal_picker_unavailable") }}');
                return;
            }
            this.picking = true;
            window.openUserPickerModal();
        },

        async submit() {
            if (this.saving) return;
            this.errors = {};
            if (!this.form.name.trim()) this.errors.name = '{{ __('platform.business_create_name_required') }}';
            if (!this.owner) this.errors.owner_user_id = '{{ __('platform.business_create_owner_required') }}';
            if (Object.keys(this.errors).length) return;

            this.saving = true;
            try {
                const res = await fetch('{{ route('admin.platform.businesses.store') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        name: this.form.name.trim(),
                        description: this.form.description.trim() || null,
                        owner_user_id: this.owner.id,
                    }),
                });
                let data = {};
                try { data = await res.json(); } catch (e) {}
                if (res.status === 422 && data.errors) {
                    for (const [k, v] of Object.entries(data.errors)) this.errors[k] = v[0];
                    return;
                }
                if (!res.ok || !data.success) throw new Error(data.message || '{{ __('platform.business_create_failed') }}');

                window.showToast('success', data.message);
                window.dispatchEvent(new CustomEvent('business-created', { detail: data }));
                this.open = false;
                // Straight on to the clubs: the edit modal owns the club picker.
                window.dispatchEvent(new CustomEvent('open-business-edit', { detail: data.business }));
            } catch (err) {
                window.showToast('error', err.message);
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
@endonce
