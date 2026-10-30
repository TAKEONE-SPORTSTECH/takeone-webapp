{{-- The form's own actions: open/close, duplicate, delete. Each posts with
     CSRF and patches in place (open/close) or navigates where it must
     (a copy opens in the builder; a deleted form returns to the list). --}}
@php
    $actT = [
        'confirmDelete' => __('forms::admin.confirm_delete', ['title' => $form->title]),
        'confirmDeleteBody' => __('forms::admin.confirm_delete_body', ['n' => $realCount]),
        'delete' => __('forms::admin.delete'),
        'failed' => __('forms::admin.failed'),
    ];
@endphp
<div x-data="{
        active: @js((bool) $form->is_active), busy: false, T: @js($actT),
        async post(url, method = 'POST') {
            const r = await fetch(url, { method, headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const j = await r.json().catch(() => ({}));
            if (!r.ok || !j.success) throw new Error(j.message || '');
            return j;
        },
        async toggle() {
            if (this.busy) return; this.busy = true;
            try { const j = await this.post(@js(route('admin.forms.toggle', $form->uuid))); this.active = j.form.active; window.showToast?.('success', j.message); }
            catch (e) { window.showToast?.('error', e.message || this.T.failed); }
            this.busy = false;
        },
        async copy() {
            if (this.busy) return; this.busy = true;
            try { const j = await this.post(@js(route('admin.forms.duplicate', $form->uuid))); window.showToast?.('success', j.message); location.href = j.form.edit_url; }
            catch (e) { window.showToast?.('error', e.message || this.T.failed); this.busy = false; }
        },
        async remove() {
            const ok = await (window.confirmAction ? window.confirmAction({ title: this.T.confirmDelete, message: this.T.confirmDeleteBody, type: 'danger', confirmText: this.T.delete }) : Promise.resolve(false));
            if (!ok) return;
            try { const j = await this.post(@js(route('admin.forms.destroy', $form->uuid)), 'DELETE'); window.showToast?.('success', j.message); location.href = @js(route('admin.forms.index')); }
            catch (e) { window.showToast?.('error', e.message || this.T.failed); }
        },
     }" class="{{ $actionsClass ?? '' }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    {{ $slot ?? '' }}
    <a href="{{ route('admin.forms.edit', $form->uuid) }}" data-no-shell class="{{ $btn }}" title="{{ __('forms::admin.edit') }}"><i class="bi bi-pencil-square"></i>@if(empty($iconOnly)) {{ __('forms::admin.edit') }}@endif</a>
    <button type="button" class="{{ $btn }}" @click="toggle()" :title="active ? @js(__('forms::admin.close_form')) : @js(__('forms::admin.open_again'))">
        <i class="bi" :class="active ? 'bi-pause-circle' : 'bi-play-circle'"></i>@if(empty($iconOnly)) <span x-text="active ? @js(__('forms::admin.close_form')) : @js(__('forms::admin.open_again'))"></span>@endif</button>
    <button type="button" class="{{ $btn }}" @click="copy()" title="{{ __('forms::admin.duplicate') }}"><i class="bi bi-copy"></i>@if(empty($iconOnly)) {{ __('forms::admin.duplicate') }}@endif</button>
    <button type="button" class="{{ $btn }}" @click="remove()" title="{{ __('forms::admin.delete') }}"><i class="bi bi-trash3"></i>@if(empty($iconOnly)) {{ __('forms::admin.delete') }}@endif</button>
</div>
