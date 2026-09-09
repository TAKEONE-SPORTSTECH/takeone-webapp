@extends('layouts.admin-mobile')

{{--
    The platform's languages, on a phone.

    Hub and drill-down per the Mobile Pattern Language: the list of languages is
    the hub, one language's strings is the panel, and the panel is an in-page
    `x-show` section with a sticky header — not `position: fixed`, which the
    mobile shell's transformed ancestor would size against a wrapper instead of
    the screen.

    Shares `platformLanguages()` with the desktop screen, so the two cannot
    drift in what they do — only in how they are laid out (CLAUDE.md → Mobile /
    Desktop Separation, Shared Stays Shared).
--}}

@section('admin-content')
<div x-data="platformLanguages(@js($languages), @js($tier))" class="mobile-stagger">

    {{-- ===== Hub ===== --}}
    <div x-show="! open" x-cloak>
        <header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-16 text-white relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
            <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

            <div class="relative z-10">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                        <i class="bi bi-translate"></i> {{ __('platform.admin_languages_eyebrow') }}
                    </span>
                </div>
                <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('platform.admin_languages_title') }}</h1>
                <p class="text-sm text-white/85 mt-1.5">{{ __('platform.admin_languages_subtitle') }}</p>
            </div>
        </header>

        <div class="-mt-10 relative z-10 space-y-3">
            <template x-for="row in languages" :key="row.code">
                <div class="m-card bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
                    <div class="flex items-start gap-3">
                        <span class="w-11 h-11 rounded-2xl bg-muted grid place-items-center flex-shrink-0">
                            <template x-if="row.flag">
                                <span class="fi" :class="'fi-' + row.flag" style="font-size:20px;"></span>
                            </template>
                            <template x-if="! row.flag">
                                <span class="text-[11px] font-black text-muted-foreground" x-text="row.code.slice(0,2).toUpperCase()"></span>
                            </template>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-foreground leading-tight" :dir="row.dir" x-text="row.native"></p>
                            <p class="text-[11px] text-muted-foreground mt-0.5" x-text="row.name"></p>
                        </div>
                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 mt-1"
                              :class="row.missing === 0 ? 'bg-green-500' : 'bg-amber-400'"></span>
                    </div>

                    <div class="mt-3">
                        <div class="flex items-center justify-between text-[11px] mb-1">
                            <span class="font-semibold"
                                  :class="row.missing === 0 ? 'text-green-600' : 'text-muted-foreground'"
                                  x-text="row.missing === 0
                                      ? @js(__('platform.admin_languages_complete'))
                                      : @js(__('platform.admin_languages_missing', ['n' => ':n'])).replace(':n', row.missing.toLocaleString())"></span>
                            <span class="text-muted-foreground" x-text="row.stored.toLocaleString() + ' / ' + row.expected.toLocaleString()"></span>
                        </div>
                        <div class="h-1.5 rounded-full bg-muted overflow-hidden">
                            <div class="h-full rounded-full m-bar-fill"
                                 :class="row.missing === 0 ? 'bg-green-500' : 'bg-primary'"
                                 :style="'width:' + Math.min(100, Math.round(100 * row.stored / Math.max(1, row.expected))) + '%'"></div>
                        </div>
                    </div>

                    <div class="mt-3.5 flex gap-2">
                        <button type="button" @click="openLocale(row)"
                                class="m-press flex-1 text-[13px] font-semibold border border-primary text-primary px-3 py-2.5 rounded-xl">
                            <i class="bi bi-pencil-square mr-1"></i>{{ __('platform.admin_languages_translation') }}
                        </button>
                        <button type="button" @click="translate(row)" :disabled="row.run?.state === 'running'"
                                class="m-press flex-1 text-[13px] font-semibold bg-primary text-white px-3 py-2.5 rounded-xl disabled:opacity-60">
                            <template x-if="row.run?.state === 'running'">
                                <span><i class="bi bi-arrow-repeat animate-spin"></i></span>
                            </template>
                            <template x-if="row.run?.state !== 'running'">
                                <span x-text="row.missing === 0
                                    ? @js(__('platform.admin_languages_retranslate'))
                                    : @js(__('platform.admin_languages_translate'))"></span>
                            </template>
                        </button>
                    </div>

                    <p class="text-[11px] mt-2 font-semibold" x-cloak
                       x-show="row.run?.state === 'done' && row.run?.complete === false" style="color:#b45309;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        {{ __('platform.admin_languages_incomplete_after_run') }}
                    </p>
                </div>
            </template>
        </div>
    </div>

    {{-- ===== One language ===== --}}
    <div x-show="open" x-cloak class="m-panel-in">
        <div class="sticky top-0 z-20 bg-background -mx-4 px-4 py-3 border-b border-gray-100 flex items-center gap-3">
            <button type="button" @click="open = null"
                    class="w-9 h-9 rounded-full bg-muted grid place-items-center flex-shrink-0">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </button>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-foreground leading-tight text-sm" x-text="open?.native"></p>
                <p class="text-[11px] text-muted-foreground" x-text="open?.name"></p>
            </div>
        </div>

        <div class="py-3 space-y-3">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="search" x-model.debounce.400ms="search" @input="page = 1; load()"
                       placeholder="{{ __('platform.admin_languages_search') }}"
                       class="w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-xl text-[15px] focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>

            <div class="flex gap-2 overflow-x-auto pb-1">
                @foreach(['all' => 'admin_languages_filter_all', 'missing' => 'admin_languages_filter_missing', 'human' => 'admin_languages_filter_human'] as $value => $key)
                    <button type="button" @click="only = @js($value); page = 1; load()"
                            class="px-3 py-1.5 rounded-full text-xs font-medium flex-shrink-0 transition-colors"
                            :class="only === @js($value) ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'">
                        {{ __('platform.'.$key) }}
                    </button>
                @endforeach
            </div>

            <template x-if="! rows.length && ! loading">
                <p class="py-10 text-center text-sm text-muted-foreground">{{ __('platform.admin_languages_empty') }}</p>
            </template>

            <template x-for="row in rows" :key="row.file + '.' + row.key">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground">
                        {{ __('platform.admin_languages_source') }}
                    </p>
                    <p class="text-[13px] text-foreground mt-0.5" x-text="row.source"></p>

                    <p class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground mt-3">
                        {{ __('platform.admin_languages_translation') }}
                        <span x-show="row.origin === 'human'" class="text-primary normal-case">
                            · {{ __('platform.admin_languages_filter_human') }}
                        </span>
                    </p>
                    <textarea rows="2" :dir="open?.dir" x-model="row.value" @blur="save(row)" :placeholder="row.source"
                              class="w-full mt-1 px-3 py-2 border rounded-xl text-[15px] focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                              :class="row.value ? 'border-gray-200' : 'border-amber-300 bg-amber-50/40'"></textarea>
                </div>
            </template>

            <div class="flex items-center justify-between pt-1" x-show="pages > 1">
                <button type="button" @click="page = Math.max(1, page - 1); load()" :disabled="page === 1"
                        class="m-press px-4 py-2.5 rounded-xl border border-border text-sm disabled:opacity-40">
                    <i class="bi bi-chevron-left rtl:rotate-180"></i>
                </button>
                <span class="text-sm text-muted-foreground" x-text="page + ' / ' + pages"></span>
                <button type="button" @click="page = Math.min(pages, page + 1); load()" :disabled="page === pages"
                        class="m-press px-4 py-2.5 rounded-xl border border-border text-sm disabled:opacity-40">
                    <i class="bi bi-chevron-right rtl:rotate-180"></i>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('admin.languages.runtime')
@endpush
