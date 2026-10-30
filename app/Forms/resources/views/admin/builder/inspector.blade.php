{{-- The selected question's settings — shared by the desktop side panel and
     the phone's bottom sheet. Bound to `field` from formBuilder. --}}
<template x-if="field">
<div>
    <label class="fb-lbl" for="fb-label">{{ __('forms::builder.question') }}</label>
    <div class="fb-pair">
        <div><span class="fb-flag">EN</span><textarea id="fb-label" class="fb-in" rows="2" maxlength="500" x-model="field.label"></textarea></div>
        <div><span class="fb-flag">عربي</span><textarea class="fb-in ar" rows="2" maxlength="500" x-model="field.label_ar" dir="rtl"></textarea></div>
    </div>
    <p class="fb-err" x-show="errors['pages.'+page+'.fields.'+sel+'.label']" x-text="(errors['pages.'+page+'.fields.'+sel+'.label']||[])[0]"></p>

    <label class="fb-lbl">{{ __('forms::builder.help') }}</label>
    <div class="fb-pair two">
        <input class="fb-in" maxlength="500" x-model="field.help" placeholder="{{ __('forms::builder.optional') }}">
        <input class="fb-in ar" dir="rtl" maxlength="500" x-model="field.help_ar" placeholder="اختياري">
    </div>

    {{-- answers to pick / lines / things to rank / numbers --}}
    <template x-if="hasList(field)">
        <div>
            <label class="fb-lbl" x-text="field.type === 'grid' ? @js(__('forms::builder.lines')) : (field.type === 'rank' ? @js(__('forms::builder.to_rank')) : (field.type === 'numbers' ? @js(__('forms::builder.numbers')) : @js(__('forms::builder.answers'))))"></label>
            <template x-for="(o, i) in field[listKey(field)]" :key="i">
                <div class="fb-item">
                    <input class="fb-in" data-item-input maxlength="150" x-model="o.label" placeholder="English">
                    <input class="fb-in ar" dir="rtl" maxlength="150" x-model="o.label_ar" placeholder="عربي">
                    <span class="acts">
                        <button type="button" class="fb-ib" @click="moveItem(field[listKey(field)], i, -1)" aria-label="{{ __('forms::builder.up') }}"><i class="bi bi-arrow-up"></i></button>
                        <button type="button" class="fb-ib danger" @click="field[listKey(field)].splice(i, 1)" aria-label="{{ __('forms::builder.remove') }}"><i class="bi bi-x-lg"></i></button>
                    </span>
                </div>
            </template>
            <div style="display:flex;gap:14px;flex-wrap:wrap" x-data="{ paste: false }">
                <button type="button" class="fb-add" @click="addItem(field[listKey(field)])"><i class="bi bi-plus-lg"></i>{{ __('forms::builder.add_item') }}</button>
                <button type="button" class="fb-add" style="color:var(--ink3)" @click="paste = !paste"><i class="bi bi-clipboard-plus"></i>{{ __('forms::builder.paste') }}</button>
                <div x-show="paste" x-cloak style="width:100%">
                    <textarea class="fb-in" rows="4" x-model="bulk" placeholder="{{ __('forms::builder.paste_hint') }}"></textarea>
                    <button type="button" class="fb-ghost" style="margin-top:6px" @click="pasteItems(field[listKey(field)]); paste = false">{{ __('forms::builder.paste_add') }}</button>
                </div>
            </div>
            <p class="fb-err" x-show="errors['pages.'+page+'.fields.'+sel+'.options'] || errors['pages.'+page+'.fields.'+sel+'.rows']"
               x-text="(errors['pages.'+page+'.fields.'+sel+'.options'] || errors['pages.'+page+'.fields.'+sel+'.rows'] || [])[0]"></p>
        </div>
    </template>

    <template x-if="field.type === 'grid'">
        <div>
            <label class="fb-lbl">{{ __('forms::builder.columns') }}</label>
            <template x-for="(o, i) in field.columns" :key="i">
                <div class="fb-item">
                    <input class="fb-in" maxlength="150" x-model="o.label" placeholder="English">
                    <input class="fb-in ar" dir="rtl" maxlength="150" x-model="o.label_ar" placeholder="عربي">
                    <span class="acts"><button type="button" class="fb-ib danger" @click="field.columns.length > 2 && field.columns.splice(i, 1)" aria-label="{{ __('forms::builder.remove') }}"><i class="bi bi-x-lg"></i></button></span>
                </div>
            </template>
            <button type="button" class="fb-add" x-show="field.columns.length < 5" @click="addItem(field.columns)"><i class="bi bi-plus-lg"></i>{{ __('forms::builder.add_column') }}</button>
            <p class="fb-sub" style="font-size:11.5px;color:var(--ink3)">{{ __('forms::builder.columns_hint') }}</p>
        </div>
    </template>

    <template x-if="field.type === 'scale'">
        <div>
            <label class="fb-lbl">{{ __('forms::builder.scale_ends') }}</label>
            <div class="fb-pair two">
                <div><span class="fb-flag">1 · EN</span><input class="fb-in" maxlength="60" x-model="field.low"></div>
                <div><span class="fb-flag">5 · EN</span><input class="fb-in" maxlength="60" x-model="field.high"></div>
                <div><span class="fb-flag">1 · عربي</span><input class="fb-in ar" dir="rtl" maxlength="60" x-model="field.low_ar"></div>
                <div><span class="fb-flag">5 · عربي</span><input class="fb-in ar" dir="rtl" maxlength="60" x-model="field.high_ar"></div>
            </div>
        </div>
    </template>

    <template x-if="field.type === 'rank'">
        <div>
            <label class="fb-lbl">{{ __('forms::builder.rank_max') }}</label>
            <div style="display:flex;gap:6px">
                <template x-for="n in [3,5,10]" :key="n">
                    <button type="button" class="fb-ghost" :style="field.max === n ? 'border-color:var(--p);color:var(--p2);background:hsl(250 65% 65% / .08)' : ''" @click="field.max = n" x-text="n"></button>
                </template>
            </div>
            <template x-if="grids.length">
                <div>
                    <label class="fb-lbl">{{ __('forms::builder.rank_from') }}</label>
                    <div style="display:flex;flex-direction:column;gap:6px">
                        <button type="button" class="fb-ghost" :style="!field.from ? 'border-color:var(--p);color:var(--p2)' : ''" @click="field.from = ''">{{ __('forms::builder.rank_all') }}</button>
                        <template x-for="g in grids" :key="g.key">
                            <button type="button" class="fb-ghost" style="text-align:start" :style="field.from === g.key ? 'border-color:var(--p);color:var(--p2)' : ''"
                                    @click="field.from = g.key; field.fromValue = g.columns[0]?.value || 'must'"
                                    x-text="@js(__('forms::builder.rank_from_grid')).replace(':q', g.label).replace(':c', g.columns[0]?.label || '')"></button>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <div style="margin-top:14px">
        <label class="fb-sw" x-show="field.type !== 'note'" @click.prevent="field.required = !field.required">
            <span>{{ __('forms::builder.required') }}<small>{{ __('forms::builder.required_hint') }}</small></span><span class="fb-tog" :class="field.required && 'on'"></span></label>
        <label class="fb-sw" x-show="field.type !== 'note'" @click.prevent="field.private = !field.private">
            <span>{{ __('forms::builder.private') }}<small>{{ __('forms::builder.private_hint') }}</small></span><span class="fb-tog" :class="field.private && 'on'"></span></label>
        <label class="fb-sw" x-show="field.type === 'checkboxes'" @click.prevent="field.other = !field.other">
            <span>{{ __('forms::builder.other') }}<small>{{ __('forms::builder.other_hint') }}</small></span><span class="fb-tog" :class="field.other && 'on'"></span></label>
        <label class="fb-sw" x-show="['radio','scale'].includes(field.type)" @click.prevent="field.note = !field.note">
            <span>{{ __('forms::builder.note') }}<small>{{ __('forms::builder.note_hint') }}</small></span><span class="fb-tog" :class="field.note && 'on'"></span></label>
    </div>

    <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:12px;padding-top:12px;border-top:1px solid var(--line)">
        <button type="button" class="fb-ghost" @click="moveField(sel, -1)"><i class="bi bi-arrow-up"></i>{{ __('forms::builder.up') }}</button>
        <button type="button" class="fb-ghost" @click="moveField(sel, 1)"><i class="bi bi-arrow-down"></i>{{ __('forms::builder.down') }}</button>
        <button type="button" class="fb-ghost" @click="dupField(sel)"><i class="bi bi-copy"></i>{{ __('forms::builder.duplicate') }}</button>
        <button type="button" class="fb-ghost" style="color:#dc2626" @click="removeField(sel)"><i class="bi bi-trash3"></i>{{ __('forms::builder.remove') }}</button>
    </div>
</div>
</template>
