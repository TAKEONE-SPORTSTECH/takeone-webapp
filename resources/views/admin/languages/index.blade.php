@extends('layouts.admin')

{{--
    The platform's own words, in every language it serves.

    ── What this screen is for ──────────────────────────────────────────────────

    Three questions nobody could answer from inside the product until the
    interface strings became rows (see the interface_translations migration):

      · how much of this language is actually translated?
      · which words are still English?
      · how do I fix one, without a developer and a deploy?

    Hub and drill-down, the same shape the event console's Languages sheet uses:
    the list of languages, and one language's strings inside it. Not tabs —
    sixty-eight languages is not a tab strip.

    ⚠️ A correction saved here is stored as a PERSON's words and no machine run
    will ever overwrite it. That is the whole reason correcting one is worth the
    effort, and the translation module enforces it on the way in.
--}}

@section('admin-content')
<div x-data="platformLanguages(@js($languages), @js($tier))">

    <x-admin-hero eyebrow="{{ __('platform.admin_languages_eyebrow') }}"
                  title="{{ __('platform.admin_languages_title') }}"
                  subtitle="{{ __('platform.admin_languages_subtitle') }}"
                  icon="bi-translate"
                  :count="count($languages)"
                  countLabel="{{ __('platform.admin_languages_title') }}" />

    {{-- ===== The list of languages ===== --}}
    <div x-show="! open" x-cloak>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <template x-for="row in languages" :key="row.code">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-col">

                    <div class="flex items-start gap-3">
                        <span class="w-10 h-10 rounded-xl bg-muted grid place-items-center flex-shrink-0 overflow-hidden">
                            <template x-if="row.flag">
                                <span class="fi" :class="'fi-' + row.flag" style="font-size:20px;"></span>
                            </template>
                            <template x-if="! row.flag">
                                <span class="text-[11px] font-black text-muted-foreground" x-text="row.code.slice(0,2).toUpperCase()"></span>
                            </template>
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-gray-900 leading-tight" :dir="row.dir" x-text="row.native"></p>
                            <p class="text-xs text-muted-foreground mt-0.5" x-text="row.name"></p>
                        </div>

                        {{-- Offered as an ACCOUNT language, or only inside events.
                             The distinction is the whole of config/locales.php
                             versus config/content_locales.php. --}}
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold flex-shrink-0"
                              :class="row.interface ? 'bg-green-100 text-green-700' : 'bg-muted text-muted-foreground'"
                              x-text="row.interface
                                  ? @js(__('platform.admin_languages_served'))
                                  : @js(__('platform.admin_languages_event_only'))"></span>
                    </div>

                    {{-- Coverage. A real rule, not a Tailwind arbitrary width:
                         the compiled bundle has no CSS for a class nobody used
                         before, so the fill is an inline style. --}}
                    <div class="mt-3">
                        <div class="flex items-center justify-between text-[11px] mb-1">
                            <span class="font-semibold"
                                  :class="row.missing === 0 ? 'text-green-600' : 'text-muted-foreground'"
                                  x-text="row.missing === 0
                                      ? @js(__('platform.admin_languages_complete'))
                                      : @js(__('platform.admin_languages_missing', ['n' => ':n'])).replace(':n', row.missing.toLocaleString())"></span>
                            <span class="text-muted-foreground"
                                  x-text="@js(__('platform.admin_languages_of', ['done' => ':done', 'total' => ':total']))
                                      .replace(':done', row.stored.toLocaleString())
                                      .replace(':total', row.expected.toLocaleString())"></span>
                        </div>
                        <div class="h-1.5 rounded-full bg-muted overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500"
                                 :class="row.missing === 0 ? 'bg-green-500' : 'bg-primary'"
                                 :style="'width:' + Math.min(100, Math.round(100 * row.stored / Math.max(1, row.expected))) + '%'"></div>
                        </div>
                        <p class="text-[11px] text-muted-foreground mt-1.5" x-show="row.human > 0"
                           x-text="@js(__('platform.admin_languages_corrections', ['n' => ':n'])).replace(':n', row.human)"></p>
                    </div>

                    <div class="mt-4 pt-3 border-t border-gray-100 flex items-center gap-2">
                        <button type="button" @click="openLocale(row)"
                                class="flex-1 text-sm font-medium border border-primary text-primary bg-transparent px-3 py-2 rounded-lg hover:bg-primary hover:text-white transition-colors">
                            <i class="bi bi-pencil-square mr-1"></i>{{ __('platform.admin_languages_translation') }}
                        </button>

                        <button type="button" @click="translate(row)"
                                :disabled="row.run?.state === 'running'"
                                class="flex-1 text-sm font-medium bg-primary text-white px-3 py-2 rounded-lg hover:bg-primary/90 transition-colors disabled:opacity-60">
                            <template x-if="row.run?.state === 'running'">
                                <span><i class="bi bi-arrow-repeat mr-1 animate-spin"></i>{{ __('platform.admin_languages_running') }}</span>
                            </template>
                            <template x-if="row.run?.state !== 'running'">
                                <span x-text="row.missing === 0
                                    ? @js(__('platform.admin_languages_retranslate'))
                                    : @js(__('platform.admin_languages_translate'))"></span>
                            </template>
                        </button>
                    </div>

                    <p class="text-[11px] mt-2 font-semibold" x-cloak
                       x-show="row.run?.state === 'done' && row.run?.complete === false"
                       style="color:#b45309;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        {{ __('platform.admin_languages_incomplete_after_run') }}
                    </p>
                    <p class="text-[11px] mt-2 font-semibold text-red-600" x-cloak x-show="row.run?.state === 'failed'">
                        <i class="bi bi-x-octagon-fill"></i> {{ __('platform.admin_languages_failed') }}
                    </p>
                </div>
            </template>
        </div>
    </div>

    {{-- ===== One language's strings ===== --}}
    <div x-show="open" x-cloak>
        <div class="flex items-center gap-3 mb-4">
            <button type="button" @click="open = null"
                    class="w-9 h-9 rounded-lg flex items-center justify-center bg-card text-foreground hover:bg-accent hover:shadow-sm transition-all border border-border">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </button>
            <div class="min-w-0">
                <p class="font-bold text-gray-900 leading-tight" x-text="open?.native"></p>
                <p class="text-xs text-muted-foreground" x-text="open?.name"></p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-4">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input type="search" x-model.debounce.400ms="search" @input="page = 1; load()"
                           placeholder="{{ __('platform.admin_languages_search') }}"
                           class="w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div class="flex gap-2">
                    @foreach(['all' => 'admin_languages_filter_all', 'missing' => 'admin_languages_filter_missing', 'human' => 'admin_languages_filter_human'] as $value => $key)
                        <button type="button" @click="only = @js($value); page = 1; load()"
                                class="px-3 py-1.5 rounded-full text-xs font-medium transition-colors"
                                :class="only === @js($value) ? 'bg-primary text-white' : 'bg-muted text-muted-foreground hover:bg-accent'">
                            {{ __('platform.'.$key) }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <template x-if="! rows.length && ! loading">
                <p class="p-8 text-center text-sm text-muted-foreground">{{ __('platform.admin_languages_empty') }}</p>
            </template>

            <template x-for="(row, i) in rows" :key="row.file + '.' + row.key">
                <div class="p-4 border-b border-gray-100 last:border-0 grid grid-cols-1 lg:grid-cols-2 gap-3">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground mb-1">
                            {{ __('platform.admin_languages_source') }}
                            <span class="font-mono normal-case tracking-normal opacity-60" x-text="row.file + '.' + row.key"></span>
                        </p>
                        <p class="text-sm text-foreground" x-text="row.source"></p>
                    </div>

                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-muted-foreground mb-1">
                            {{ __('platform.admin_languages_translation') }}
                            <span x-show="row.origin === 'human'" class="text-primary">
                                · {{ __('platform.admin_languages_filter_human') }}
                            </span>
                        </p>
                        <textarea rows="2" :dir="open?.dir"
                                  x-model="row.value"
                                  @blur="save(row)"
                                  :placeholder="row.source"
                                  class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                  :class="row.value ? 'border-gray-200' : 'border-amber-300 bg-amber-50/40'"></textarea>
                    </div>
                </div>
            </template>
        </div>

        <div class="flex items-center justify-between mt-4" x-show="pages > 1">
            <button type="button" @click="page = Math.max(1, page - 1); load()" :disabled="page === 1"
                    class="px-4 py-2 rounded-lg border border-border text-sm disabled:opacity-40">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </button>
            <span class="text-sm text-muted-foreground" x-text="page + ' / ' + pages"></span>
            <button type="button" @click="page = Math.min(pages, page + 1); load()" :disabled="page === pages"
                    class="px-4 py-2 rounded-lg border border-border text-sm disabled:opacity-40">
                <i class="bi bi-chevron-right rtl:rotate-180"></i>
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('admin.languages.runtime')
@endpush
