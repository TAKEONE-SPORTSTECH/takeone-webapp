@extends('layouts.admin')

@section('admin-content')
@include('forms::admin.builder.style')
@include('forms::admin.builder.runtime')
<div class="fb space-y-4" x-data="formBuilder(@js(['state' => $state, 'urls' => $urls, 'answers' => $answers]))">
    <x-admin-hero :eyebrow="__('forms::admin.eyebrow')" :title="$form ? __('forms::builder.edit_title') : __('forms::builder.new_title')" icon="bi-pencil-square"
                  :subtitle="__('forms::builder.subtitle')">
        <x-slot:actions>
            <a :href="urls.show || urls.index" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-white/20 backdrop-blur text-white"><i class="bi bi-chevron-left rtl:rotate-180"></i><span x-text="urls.show ? @js(__('forms::builder.to_results')) : @js(__('forms::admin.back'))"></span></a>
            <a x-show="urls.public" x-cloak :href="urls.public" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-white/20 backdrop-blur text-white"><i class="bi bi-box-arrow-up-right"></i>{{ __('forms::builder.preview') }}</a>
        </x-slot:actions>
    </x-admin-hero>

    @if($answers)
        <div class="fb-warn"><i class="bi bi-info-circle"></i><span>{{ __('forms::builder.has_answers', ['n' => $answers]) }}</span></div>
    @endif

    {{-- sticky toolbar: always within reach --}}
    <div class="fb-card" style="position:sticky;top:8px;z-index:30;padding:10px 14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <b style="font-size:15px;min-width:0;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" x-text="form.title || @js(__('forms::builder.untitled'))"></b>
        <span class="fb-chip num" style="margin:0" x-text="form.pages.length + ' ' + @js(__('forms::admin.pages')) + ' · ' + total + ' ' + @js(__('forms::admin.questions'))"></span>
        <span class="fb-dirty" x-show="dirty" x-cloak><i></i>{{ __('forms::builder.unsaved') }}</span>
        <button type="button" class="fb-save" @click="save()" :disabled="saving"><i class="bi" :class="saving ? 'bi-hourglass-split' : 'bi-check2'"></i><span x-text="saving ? @js(__('forms::builder.saving')) : @js(__('forms::builder.save'))"></span></button>
    </div>

    <div style="display:grid;grid-template-columns:250px minmax(0,1fr) 360px;gap:16px;align-items:start">
        {{-- pages --}}
        <aside class="fb-card" style="padding:8px;position:sticky;top:76px">
            <button type="button" class="fb-pg" :class="panel === 'settings' && 'on'" @click="panel = 'settings'; sel = null">
                <span class="n"><i class="bi bi-sliders"></i></span><span class="tx">{{ __('forms::builder.form_settings') }}</span></button>
            <div style="height:1px;background:var(--line);margin:6px 4px"></div>
            <template x-for="(p, i) in form.pages" :key="i">
                <div style="display:flex;align-items:center">
                    <button type="button" class="fb-pg" :class="panel !== 'settings' && page === i && 'on'" @click="page = i; sel = null; panel = 'page'">
                        <span class="n num" x-text="i + 1"></span>
                        <span class="tx"><span x-text="p.title"></span><small style="display:block" x-text="p.fields.length + ' ' + @js(__('forms::admin.questions'))"></small></span>
                        <i class="bi bi-lock-fill" x-show="p.private" style="color:#d97706;font-size:11px"></i>
                    </button>
                    <span x-show="panel !== 'settings' && page === i" style="display:flex;flex-direction:column">
                        <button type="button" class="fb-ib" style="width:24px;height:20px" @click="movePage(i, -1)" aria-label="{{ __('forms::builder.up') }}"><i class="bi bi-chevron-up"></i></button>
                        <button type="button" class="fb-ib" style="width:24px;height:20px" @click="movePage(i, 1)" aria-label="{{ __('forms::builder.down') }}"><i class="bi bi-chevron-down"></i></button>
                    </span>
                </div>
            </template>
            <button type="button" class="fb-add" style="padding:10px 12px" @click="addPage()"><i class="bi bi-plus-lg"></i>{{ __('forms::builder.add_page') }}</button>
        </aside>

        {{-- the canvas --}}
        <main style="min-width:0">
            <div x-show="panel === 'settings'" class="fb-card" style="padding:18px">
                <div style="font-size:16px;font-weight:900;margin-bottom:12px">{{ __('forms::builder.form_settings') }}</div>
                @include('forms::admin.builder.settings')
            </div>
            <div x-show="panel !== 'settings'" class="space-y-4">
                <div class="fb-card" style="padding:18px">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:12px">
                        <div style="font-size:16px;font-weight:900"><span x-text="@js(__('forms::builder.page')) + ' ' + (page + 1)"></span></div>
                        <button type="button" class="fb-ghost" style="color:#dc2626" x-show="form.pages.length > 1" @click="removePage(page)"><i class="bi bi-trash3"></i>{{ __('forms::builder.remove_page') }}</button>
                    </div>
                    @include('forms::admin.builder.page')
                </div>
                @include('forms::admin.builder.qlist')
                <div class="fb-card" style="padding:14px">
                    <div class="fb-lbl" style="margin-top:0">{{ __('forms::builder.add_question') }}</div>
                    @include('forms::admin.builder.types')
                </div>
            </div>
        </main>

        {{-- the selected question --}}
        <aside class="fb-card" style="padding:16px;position:sticky;top:76px;max-height:calc(100vh - 96px);overflow-y:auto">
            <template x-if="field">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
                    <span style="width:34px;height:34px;border-radius:10px;background:var(--acc);color:var(--p2);display:grid;place-items:center"><i class="bi" :class="typeOf(field.type).icon"></i></span>
                    <b style="font-size:15px" x-text="typeOf(field.type).label"></b>
                </div>
            </template>
            @include('forms::admin.builder.inspector')
            <div x-show="!field" class="fb-empty" style="border:0"><i class="bi bi-hand-index" style="font-size:22px;display:block;margin-bottom:6px"></i>{{ __('forms::builder.pick_question') }}</div>
        </aside>
    </div>
</div>
@endsection
