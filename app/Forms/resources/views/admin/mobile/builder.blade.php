@extends('layouts.app')

@section('hide-navbar', true)
@section('title', $form ? __('forms::builder.edit_title') : __('forms::builder.new_title'))

@section('content')
@include('forms::admin.builder.style')
@include('forms::admin.builder.runtime')
{{-- A mobile-first settings hub with drill-down panels: the hub lists the
     form's settings and pages; a page panel lists its questions; a question
     opens in a bottom sheet. Save sits in every header. --}}
<div class="fb min-h-screen bg-background pb-24 px-4 pt-4" x-data="formBuilder(@js(['state' => $state, 'urls' => $urls, 'answers' => $answers]))">

    {{-- ── hub ── --}}
    <div x-show="panel === 'hub'">
        <header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-16 text-white relative overflow-hidden" style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
            <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
            <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
            <div class="flex items-center justify-between relative z-50">
                <a :href="urls.show || urls.index" aria-label="{{ __('forms::admin.back') }}" class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white"><i class="bi bi-chevron-left rtl:rotate-180"></i></a>
                <div class="flex items-center gap-2">
                    <a x-show="urls.public" x-cloak :href="urls.public" target="_blank" rel="noopener" aria-label="{{ __('forms::builder.preview') }}" class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center text-white"><i class="bi bi-box-arrow-up-right"></i></a>
                    <button type="button" @click="save()" :disabled="saving" class="m-press h-10 px-4 rounded-full bg-white text-primary font-bold text-sm inline-flex items-center gap-1.5"><i class="bi" :class="saving ? 'bi-hourglass-split' : 'bi-check2'"></i>{{ __('forms::builder.save') }}</button>
                </div>
            </div>
            <div class="relative z-10 mt-6">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-pencil-square"></i> {{ $form ? __('forms::builder.edit_title') : __('forms::builder.new_title') }}</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur" x-show="dirty" x-cloak>● {{ __('forms::builder.unsaved') }}</span>
                </div>
                <h1 class="text-2xl font-black mt-3 leading-tight" x-text="form.title || @js(__('forms::builder.untitled'))"></h1>
                <p class="text-sm text-white/85 mt-1.5" x-text="form.pages.length + ' ' + @js(__('forms::admin.pages')) + ' · ' + total + ' ' + @js(__('forms::admin.questions'))"></p>
            </div>
        </header>

        <div class="-mt-10 relative z-10 space-y-3">
            @if($answers)<div class="fb-warn"><i class="bi bi-info-circle"></i><span>{{ __('forms::builder.has_answers', ['n' => $answers]) }}</span></div>@endif
            <div class="fb-card" style="overflow:hidden">
                <button type="button" class="m-press flex items-center gap-3 px-4 py-3.5 w-full text-start" @click="panel = 'settings'">
                    <span class="w-10 h-10 rounded-xl bg-accent text-primary flex items-center justify-center flex-shrink-0"><i class="bi bi-sliders text-lg"></i></span>
                    <span class="min-w-0 flex-1"><span class="block text-sm font-semibold">{{ __('forms::builder.form_settings') }}</span>
                        <span class="block text-[12px] text-muted-foreground truncate">{{ __('forms::builder.form_settings_desc') }}</span></span>
                    <span class="w-2 h-2 rounded-full flex-shrink-0" :style="'background:' + (form.title && form.title_ar ? '#16a34a' : '#f59e0b')"></span>
                    <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground"></i>
                </button>
            </div>
            <p class="fb-flag" style="margin:16px 4px 6px;font-size:11px">{{ strtoupper(__('forms::admin.pages')) }}</p>
            <div class="fb-card" style="overflow:hidden">
                <template x-for="(p, i) in form.pages" :key="i">
                    <button type="button" class="m-press flex items-center gap-3 px-4 py-3 w-full text-start" style="border-top:1px solid var(--line)" @click="page = i; sel = null; panel = 'page'; window.scrollTo(0,0)">
                        <span class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-sm font-black" style="background:var(--acc);color:var(--p2)" x-text="i + 1"></span>
                        <span class="min-w-0 flex-1"><span class="block text-sm font-semibold truncate" x-text="p.title"></span>
                            <span class="block text-[12px] text-muted-foreground" x-text="p.fields.length + ' ' + @js(__('forms::admin.questions'))"></span></span>
                        <i class="bi bi-lock-fill" x-show="p.private" style="color:#d97706;font-size:12px"></i>
                        <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground"></i>
                    </button>
                </template>
            </div>
            <button type="button" class="m-press fb-ghost w-full justify-center" style="padding:12px" @click="addPage(); window.scrollTo(0,0)"><i class="bi bi-plus-lg"></i>{{ __('forms::builder.add_page') }}</button>
        </div>
    </div>

    {{-- ── a drill-down panel (settings or one page) ── --}}
    <div x-show="panel !== 'hub'" x-cloak class="m-panel-in">
        <div class="-mx-4 -mt-4 px-4 py-3 bg-white flex items-center gap-3" style="position:sticky;top:0;z-index:30;border-bottom:1px solid var(--line);padding-top:calc(12px + env(safe-area-inset-top))">
            <button type="button" class="w-10 h-10 rounded-full grid place-items-center" style="background:var(--mut)" @click="panel = 'hub'; sel = null" aria-label="{{ __('forms::admin.back') }}"><i class="bi bi-chevron-left rtl:rotate-180"></i></button>
            <b class="flex-1 min-w-0 truncate" style="font-size:15px" x-text="panel === 'settings' ? @js(__('forms::builder.form_settings')) : (@js(__('forms::builder.page')) + ' ' + (page + 1) + ' · ' + cur.title)"></b>
            <button type="button" class="fb-save" style="padding:8px 14px" @click="save()" :disabled="saving">{{ __('forms::builder.save') }}</button>
        </div>
        <div class="space-y-3" style="margin-top:14px">
            <div x-show="panel === 'settings'" class="fb-card" style="padding:16px">@include('forms::admin.builder.settings', ['cropInline' => true])</div>
            <template x-if="panel === 'page'">
                <div class="space-y-3">
                    <div class="fb-card" style="padding:16px">@include('forms::admin.builder.page')</div>
                    @include('forms::admin.builder.qlist')
                    <button type="button" class="m-press fb-save w-full justify-center" style="padding:13px" @click="sheet = 'types'"><i class="bi bi-plus-lg"></i>{{ __('forms::builder.add_question') }}</button>
                    <button type="button" class="m-press fb-ghost w-full justify-center" style="color:#dc2626" x-show="form.pages.length > 1" @click="removePage(page)"><i class="bi bi-trash3"></i>{{ __('forms::builder.remove_page') }}</button>
                </div>
            </template>
        </div>
    </div>

    {{-- ── bottom sheets: pick a question type · edit a question ── --}}
    <template x-teleport="body">
        <div class="fb" x-show="sheet" x-cloak style="position:fixed;inset:0;z-index:70" @keydown.escape.window="sheet = null">
            <div style="position:absolute;inset:0;background:rgba(15,16,23,.45)" @click="sheet = null" x-transition.opacity></div>
            <div style="position:absolute;inset-inline:0;bottom:0;max-height:92vh;display:flex;flex-direction:column;background:hsl(220 15% 97%);border-radius:24px 24px 0 0;overflow:hidden"
                 x-show="sheet" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                <div style="flex-shrink:0;padding:12px 20px 16px;color:#fff;position:relative;overflow:hidden;background:linear-gradient(150deg,#7c6bf5,#7c6bf5b0)">
                    <div style="position:absolute;inset-inline-end:-32px;top:-40px;width:144px;height:144px;border-radius:50%;background:rgba(255,255,255,.1)"></div>
                    <div style="margin:0 auto 12px;width:40px;height:4px;border-radius:99px;background:rgba(255,255,255,.4)"></div>
                    <div style="position:relative;display:flex;gap:12px;align-items:flex-start">
                        <span style="width:48px;height:48px;border-radius:16px;background:rgba(255,255,255,.2);display:grid;place-items:center;flex-shrink:0"><i class="bi" style="font-size:20px" :class="sheet === 'types' ? 'bi-plus-lg' : (field ? typeOf(field.type).icon : 'bi-pencil')"></i></span>
                        <div style="flex:1;min-width:0"><h3 style="font-size:18px;font-weight:900;line-height:1.2" x-text="sheet === 'types' ? @js(__('forms::builder.add_question')) : (field ? typeOf(field.type).label : '')"></h3>
                            <p style="font-size:12px;opacity:.85;margin-top:2px" x-text="cur ? cur.title : ''"></p></div>
                        <button type="button" @click="sheet = null" aria-label="{{ __('shared.close') }}" style="width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);display:grid;place-items:center;color:#fff;flex-shrink:0"><i class="bi bi-x-lg"></i></button>
                    </div>
                </div>
                <div style="flex:1;overflow-y:auto;padding:16px">
                    <div x-show="sheet === 'types'">@include('forms::admin.builder.types')</div>
                    <div x-show="sheet === 'field'">@include('forms::admin.builder.inspector')</div>
                </div>
                <div x-show="sheet === 'field'" style="flex-shrink:0;background:#fff;border-top:1px solid var(--line);padding:10px 16px calc(10px + env(safe-area-inset-bottom));display:flex;gap:8px">
                    <button type="button" class="fb-ghost" style="flex:0 0 auto" @click="sheet = null">{{ __('forms::builder.done') }}</button>
                    <button type="button" class="fb-save" style="flex:1;justify-content:center" @click="save()" :disabled="saving">{{ __('forms::builder.save') }}</button>
                </div>
            </div>
        </div>
    </template>
</div>
@endsection
