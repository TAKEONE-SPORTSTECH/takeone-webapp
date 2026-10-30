{{-- The current page's questions as cards; tap one to edit it. --}}
<div style="display:flex;flex-direction:column;gap:8px">
    <template x-for="(f, i) in cur.fields" :key="i + ':' + (f.key || 'n')">
        <button type="button" class="fb-q" :class="[sel === i && 'on', Object.keys(errors).some(k => k.startsWith('pages.'+page+'.fields.'+i+'.')) && 'err']" @click="pick(i)">
            <span class="ic"><i class="bi" :class="typeOf(f.type).icon"></i></span>
            <span class="tx">
                <b x-text="f.label || '—'"></b>
                <span class="ar" x-show="f.label_ar" x-text="f.label_ar"></span>
                <span>
                    <em class="fb-chip" x-text="typeOf(f.type).label"></em>
                    <em class="fb-chip" x-show="hasList(f)" x-text="(f[listKey(f)] || []).length + ' ' + @js(__('forms::builder.items'))"></em>
                    <em class="fb-chip req" x-show="f.required">{{ __('forms::builder.required') }}</em>
                    <em class="fb-chip priv" x-show="f.private">{{ __('forms::builder.private') }}</em>
                    <em class="fb-chip" style="background:#fee2e2;color:#b91c1c" x-show="!f.label_ar">{{ __('forms::builder.no_arabic') }}</em>
                </span>
            </span>
            <i class="bi bi-grip-vertical" style="color:var(--ink3)"></i>
        </button>
    </template>
    <div class="fb-empty" x-show="!cur.fields.length">{{ __('forms::builder.no_questions') }}</div>
</div>
