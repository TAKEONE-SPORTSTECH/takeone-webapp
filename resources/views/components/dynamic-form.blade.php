@props(['form'])
@php
    $pages    = $form->schema['pages'] ?? [];
    $settings = $form->settings ?? [];
    // Field meta (for client-side required validation + visibility) and default values.
    $meta = [];
    $defaults = [];
    foreach ($pages as $pi => $page) {
        foreach (($page['fields'] ?? []) as $f) {
            $type = $f['type'] ?? 'text';
            if (in_array($type, ['heading', 'paragraph'], true)) continue;
            $key = $f['key'] ?? '';
            $meta[] = ['page' => $pi, 'key' => $key, 'type' => $type, 'required' => ! empty($f['required']), 'visibleIf' => $f['visibleIf'] ?? null];
            $defaults[$key] = $type === 'checkboxes' ? [] : (in_array($type, ['checkbox', 'terms'], true) ? false : '');
        }
    }
@endphp

<div x-data="dynamicForm({
        pageCount: {{ max(1, count($pages)) }},
        fields: {{ \Illuminate\Support\Js::from($meta) }},
        values: {{ \Illuminate\Support\Js::from($defaults) }},
        action: @js(route('forms.submit', $form->uuid)),
        success: @js($settings['successMessage'] ?? 'Thank you — your response was submitted.'),
     })" class="max-w-xl mx-auto">

    {{-- success state --}}
    <div x-show="done" x-cloak class="text-center py-12">
        <div class="w-16 h-16 mx-auto rounded-2xl grid place-items-center bg-green-100 text-green-600"><i class="bi bi-check-lg text-3xl"></i></div>
        <p class="text-lg font-bold text-gray-900 mt-3" x-text="successMsg"></p>
    </div>

    <form x-show="!done" @submit.prevent="submit()" class="space-y-5">
        @foreach($pages as $pi => $page)
        <div x-show="page === {{ $pi }}" class="space-y-5">
            @if(count($pages) > 1)
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-gray-900">{{ $page['title'] ?? ('Step ' . ($pi + 1)) }}</h2>
                    <span class="text-xs text-gray-400">{{ $pi + 1 }} / {{ count($pages) }}</span>
                </div>
            @endif

            @foreach(($page['fields'] ?? []) as $field)
                @php $type = $field['type'] ?? 'text'; $key = $field['key'] ?? ''; $label = $field['label'] ?? ''; $req = ! empty($field['required']); $ph = $field['placeholder'] ?? ''; $help = $field['help'] ?? ''; @endphp
                <div @if(!empty($field['visibleIf'])) x-show="vis({{ \Illuminate\Support\Js::from($field['visibleIf']) }})" x-cloak @endif>
                    @switch($type)
                        @case('heading')
                            <h3 class="text-base font-bold text-gray-900 pt-2 border-t border-gray-100">{{ $label }}</h3>
                            @if($help)<p class="text-sm text-gray-500 mt-0.5">{{ $help }}</p>@endif
                            @break

                        @case('paragraph')
                            <p class="text-sm text-gray-600 whitespace-pre-line">{{ $help ?: $label }}</p>
                            @break

                        @case('textarea')
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }} @if($req)<span class="text-red-500">*</span>@endif</label>
                            @if($help)<p class="text-xs text-gray-500 mb-1">{{ $help }}</p>@endif
                            <textarea x-model="values['{{ $key }}']" rows="3" placeholder="{{ $ph }}" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none resize-none"></textarea>
                            @break

                        @case('select')
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }} @if($req)<span class="text-red-500">*</span>@endif</label>
                            @if($help)<p class="text-xs text-gray-500 mb-1">{{ $help }}</p>@endif
                            <div class="relative" :style="open ? 'z-index:50' : ''" x-data="{ open:false, opts: {{ \Illuminate\Support\Js::from($field['options'] ?? []) }} }" @click.outside="open=false" @keydown.escape="open=false">
                                <button type="button" @click="open=!open" class="w-full px-3 py-2.5 border rounded-xl text-sm bg-white text-left flex items-center justify-between gap-2 outline-none transition-colors" :class="open ? 'ring-2 ring-purple-500 border-transparent' : 'border-gray-200'">
                                    <span class="truncate" :class="values['{{ $key }}'] ? 'text-gray-900' : 'text-gray-400'" x-text="(opts.find(o => String(o.value)===String(values['{{ $key }}']))||{}).label || '{{ $ph ?: 'Choose…' }}'"></span>
                                    <i class="bi bi-chevron-down text-gray-400 text-xs transition-transform flex-shrink-0" :class="open ? 'rotate-180' : ''"></i>
                                </button>
                                <div x-show="open" x-cloak class="absolute z-50 mt-1.5 w-full bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden py-1 max-h-56 overflow-y-auto">
                                    <template x-for="o in opts" :key="o.value">
                                        <button type="button" @click="values['{{ $key }}']=o.value; open=false" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 flex items-center justify-between gap-2" :class="String(values['{{ $key }}'])===String(o.value) ? 'text-purple-600 font-semibold bg-purple-50' : 'text-gray-800'">
                                            <span x-text="o.label"></span>
                                            <i class="bi bi-check-lg text-purple-600 text-xs" x-show="String(values['{{ $key }}'])===String(o.value)"></i>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            @break

                        @case('radio')
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }} @if($req)<span class="text-red-500">*</span>@endif</label>
                            <div class="space-y-2">
                                @foreach(($field['options'] ?? []) as $opt)
                                    <label class="flex items-center gap-2.5 cursor-pointer">
                                        <input type="radio" x-model="values['{{ $key }}']" value="{{ $opt['value'] ?? '' }}" class="w-4 h-4 text-purple-600 focus:ring-purple-500">
                                        <span class="text-sm text-gray-700">{{ $opt['label'] ?? $opt['value'] ?? '' }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @break

                        @case('checkboxes')
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }} @if($req)<span class="text-red-500">*</span>@endif</label>
                            <div class="space-y-2">
                                @foreach(($field['options'] ?? []) as $opt)
                                    <label class="flex items-center gap-2.5 cursor-pointer">
                                        <input type="checkbox" x-model="values['{{ $key }}']" value="{{ $opt['value'] ?? '' }}" class="w-4 h-4 rounded text-purple-600 focus:ring-purple-500">
                                        <span class="text-sm text-gray-700">{{ $opt['label'] ?? $opt['value'] ?? '' }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @break

                        @case('checkbox')
                        @case('terms')
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="checkbox" x-model="values['{{ $key }}']" class="w-4 h-4 mt-0.5 rounded text-purple-600 focus:ring-purple-500 flex-shrink-0">
                                <span class="text-sm text-gray-700 leading-relaxed">{{ $label }} @if($req)<span class="text-red-500">*</span>@endif @if($help)<span class="block text-xs text-gray-500 mt-1 whitespace-pre-line">{{ $help }}</span>@endif</span>
                            </label>
                            @break

                        @case('file')
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }} @if($req)<span class="text-red-500">*</span>@endif</label>
                            @if($help)<p class="text-xs text-gray-500 mb-1">{{ $help }}</p>@endif
                            <label class="m-press cursor-pointer flex items-center gap-2 px-3 py-2.5 border-2 border-dashed border-gray-200 rounded-xl text-sm text-gray-600 hover:border-purple-400">
                                <i class="bi bi-paperclip"></i>
                                <span x-text="files['{{ $key }}'] ? files['{{ $key }}'].name : 'Choose a file (image or PDF)'"></span>
                                <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="hidden" @change="setFile('{{ $key }}', $event)">
                            </label>
                            @break

                        @default {{-- text, email, number, phone, date --}}
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }} @if($req)<span class="text-red-500">*</span>@endif</label>
                            @if($help)<p class="text-xs text-gray-500 mb-1">{{ $help }}</p>@endif
                            <input type="{{ $type === 'number' ? 'number' : ($type === 'email' ? 'email' : ($type === 'date' ? 'date' : ($type === 'phone' ? 'tel' : 'text'))) }}"
                                   x-model="values['{{ $key }}']" placeholder="{{ $ph }}"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    @endswitch

                    @unless(in_array($type, ['heading', 'paragraph'], true))
                        <p class="text-xs text-red-500 mt-1" x-show="errs['{{ $key }}']" x-cloak x-text="errs['{{ $key }}']"></p>
                    @endunless
                </div>
            @endforeach
        </div>
        @endforeach

        {{-- navigation --}}
        <div class="flex items-center gap-2 pt-3">
            <button type="button" x-show="page > 0" @click="back()" class="m-press px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 text-sm font-bold">Back</button>
            <button type="button" x-show="page < pageCount - 1" @click="next()" class="m-press ml-auto px-5 py-2.5 rounded-xl bg-purple-600 text-white text-sm font-bold">Next</button>
            <button type="submit" x-show="page === pageCount - 1" :disabled="busy" class="m-press ml-auto px-5 py-2.5 rounded-xl bg-purple-600 text-white text-sm font-bold disabled:opacity-50 inline-flex items-center gap-2">
                <i class="bi" :class="busy ? 'bi-arrow-repeat animate-spin' : 'bi-send-fill'"></i> {{ $settings['submitText'] ?? 'Submit' }}
            </button>
        </div>
    </form>
</div>

@once
<script>
window.dynamicForm = function (cfg) {
    return {
        page: 0, pageCount: cfg.pageCount, fields: cfg.fields, values: cfg.values,
        files: {}, action: cfg.action, successMsg: cfg.success, busy: false, done: false, errs: {},
        vis(cond) {
            if (!cond || !cond.field) return true;
            const v = this.values[cond.field], t = cond.value;
            switch (cond.op) {
                case 'not_equals': return String(v) !== String(t);
                case 'in': return Array.isArray(v) ? v.includes(t) : false;
                case 'filled': return !this._empty(v);
                case 'not_filled': return this._empty(v);
                default: return String(v) === String(t);
            }
        },
        _empty(v) { return v === '' || v === null || v === undefined || v === false || (Array.isArray(v) && v.length === 0); },
        validatePage(p) {
            this.errs = {};
            let ok = true;
            for (const f of this.fields.filter(x => x.page === p)) {
                if (!this.vis(f.visibleIf) || !f.required) continue;
                const empty = f.type === 'file' ? !this.files[f.key] : this._empty(this.values[f.key]);
                if (empty) { this.errs[f.key] = 'This field is required'; ok = false; }
            }
            return ok;
        },
        next() { if (this.validatePage(this.page)) this.page = Math.min(this.page + 1, this.pageCount - 1); },
        back() { this.page = Math.max(this.page - 1, 0); },
        setFile(key, e) { this.files[key] = e.target.files[0] || null; },
        async submit() {
            for (let p = 0; p < this.pageCount; p++) if (!this.validatePage(p)) { this.page = p; return; }
            if (this.busy) return;
            this.busy = true;
            try {
                const fd = new FormData();
                for (const [k, v] of Object.entries(this.values)) {
                    if (Array.isArray(v)) v.forEach(x => fd.append('fields[' + k + '][]', x));
                    else if (typeof v === 'boolean') fd.append('fields[' + k + ']', v ? '1' : '');
                    else fd.append('fields[' + k + ']', v ?? '');
                }
                for (const [k, file] of Object.entries(this.files)) if (file) fd.append('fields[' + k + ']', file);
                const res = await fetch(this.action, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '', 'Accept': 'application/json' },
                    body: fd,
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    if (data.errors) { this.errs = {}; for (const k in data.errors) this.errs[k.replace(/^fields\./, '').replace(/\.\*$/, '')] = data.errors[k][0]; }
                    throw new Error(data.message || 'Submission failed');
                }
                this.successMsg = data.message || this.successMsg;
                this.done = true;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (e) { (window.showToast ? window.showToast('error', e.message) : alert(e.message)); } finally { this.busy = false; }
        },
    };
};
</script>
@endonce
