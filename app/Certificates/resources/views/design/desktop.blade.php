@extends('layouts.app')

@section('title', __('cert::messages.design_title'))

@section('content')
@php
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $accent) ? $accent : '#6d5bd0';
    $strings = [
        'failed' => __('cert::messages.failed'),
        'save_first' => __('cert::messages.save_first'),
        'remove_title' => __('cert::messages.remove_title'),
        'remove_message' => __('cert::messages.remove_message'),
        'remove' => __('cert::messages.remove'),
    ];
    $backgrounds = ['plain', 'guilloche', 'watermark', 'motif', 'artwork', 'blank'];
@endphp
@include('cert::design.partials.script')

<div class="px-4 sm:px-6 lg:px-8 py-6" x-data="certDesign(@js($state), @js($endpoints), @js($strings))">

    {{-- Hero band (Design Rule #6, desktop) --}}
    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative"
         style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex items-center justify-between gap-2 mb-4">
                <a href="{{ $backUrl }}" aria-label="{{ __('cert::messages.back') }}" title="{{ __('cert::messages.back') }}"
                   class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white hover:bg-white/25 transition-colors">
                    <i class="bi bi-chevron-left rtl:rotate-180"></i>
                </a>
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-patch-check-fill"></i> {{ __('cert::messages.console_title') }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-palette-fill"></i> {{ __('cert::messages.design_chip') }}
                </span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('cert::messages.design_title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5"><i class="bi bi-trophy"></i>{{ $headline }}</p>
        </div>
    </div>

    <template x-if="form">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

        {{-- ===================== the form ===================== --}}
        <div class="space-y-4">

            {{-- Which certificate --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="border-b border-gray-200">
                    <nav class="nav -mb-px flex gap-8">
                        <template x-for="tt in state.templates" :key="tt.type">
                            <button type="button" @click="dirty ? window.showToast('info', strings.save_first) : pickType(tt.type)"
                                    class="border-b-2 pb-3 font-medium text-sm transition-colors"
                                    :class="type === tt.type ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    x-text="tt.label"></button>
                        </template>
                    </nav>
                </div>

                {{-- Whose design --}}
                <p class="text-sm font-bold text-foreground mt-5">{{ __('cert::messages.scope_title') }}</p>
                <p class="text-xs text-muted-foreground mt-0.5">{{ __('cert::messages.scope_hint') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-3">
                    <template x-for="sc in state.scopes" :key="sc.value">
                        <button type="button" @click="if (sc.allowed) { form.scope = sc.value; touch(); }" :disabled="! sc.allowed" role="radio" :aria-checked="form.scope === sc.value"
                                class="text-start rounded-xl border-2 p-3 flex items-start gap-3 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                                :class="form.scope === sc.value ? 'border-primary bg-primary/5' : 'border-gray-100 hover:border-gray-200'">
                            <span class="w-9 h-9 rounded-lg grid place-items-center flex-shrink-0" :class="sc.value === 'event' ? 'bg-accent text-primary' : 'bg-amber-50 text-amber-700'">
                                <i class="bi" :class="sc.value === 'event' ? 'bi-calendar-event' : 'bi-building'"></i>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground" x-text="sc.label"></span>
                                <span class="block text-[12px] text-muted-foreground mt-0.5"
                                      x-text="sc.allowed ? (sc.value === 'event' ? @js(__('cert::messages.scope_event_hint')) : @js(__('cert::messages.scope_host_hint'))) : @js(__('cert::messages.scope_locked'))"></span>
                            </span>
                            <i class="bi mt-1" :class="sc.allowed ? (form.scope === sc.value ? 'bi-check-circle-fill text-primary' : 'bi-circle text-gray-300') : 'bi-lock-fill text-gray-400'"></i>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Wording --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm font-bold text-foreground">{{ __('cert::messages.wording') }}</p>
                    <div class="inline-flex bg-muted rounded-full p-0.5">
                        <template x-for="l in state.locales" :key="l">
                            <button type="button" @click="lang = l; refreshPreview(true)"
                                    class="h-8 px-4 rounded-full text-xs font-bold transition-colors"
                                    :class="lang === l ? 'bg-primary text-white' : 'text-muted-foreground'"
                                    x-text="l === 'ar' ? 'العربية' : 'English'"></button>
                        </template>
                    </div>
                </div>
                @foreach($state['locales'] as $l)
                    <div x-show="lang === '{{ $l }}'" class="space-y-3" dir="{{ $l === 'ar' ? 'rtl' : 'ltr' }}">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="cert-title-{{ $l }}">{{ __('cert::messages.field_title') }}</label>
                            <input id="cert-title-{{ $l }}" type="text" maxlength="150" x-model="form.title['{{ $l }}']" @input="touch()"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="cert-body-{{ $l }}">{{ __('cert::messages.field_body') }}</label>
                            <textarea id="cert-body-{{ $l }}" x-ref="body_{{ $l }}" rows="4" maxlength="800" x-model="form.body['{{ $l }}']" @input="touch()"
                                      class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent leading-relaxed"></textarea>
                        </div>
                    </div>
                @endforeach
                <div>
                    <p class="text-xs text-muted-foreground mb-2">{{ __('cert::messages.tokens_hint') }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="tk in state.placeholders" :key="tk">
                            <button type="button" @click="insert(tk)"
                                    class="px-2.5 py-1 rounded-full border border-purple-200 bg-purple-50 text-purple-700 text-xs font-semibold font-mono hover:border-primary transition-colors"
                                    x-text="'{' + tk + '}'"></button>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Signatures --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm font-bold text-foreground">{{ __('cert::messages.signatures') }}</p>
                <p class="text-xs text-muted-foreground mt-0.5">{{ __('cert::messages.signatures_hint') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                    @foreach([1, 2] as $slot)
                        <div class="rounded-xl border border-gray-100 p-3 space-y-2">
                            <div class="h-16 rounded-lg bg-muted/50 grid place-items-center overflow-hidden">
                                <template x-if="t.has_signature[{{ $slot }}]"><img :src="sigSrc({{ $slot }})" alt="" class="max-w-full object-contain" style="max-height: 3.5rem;"></template>
                                <template x-if="! t.has_signature[{{ $slot }}]"><span class="text-xs text-muted-foreground"><i class="bi bi-pen me-1"></i>{{ __('cert::messages.no_signature') }}</span></template>
                            </div>
                            <div class="flex gap-2">
                                <label class="flex-1 h-9 rounded-lg border border-primary text-primary text-xs font-bold grid place-items-center cursor-pointer hover:bg-primary hover:text-white transition-colors"
                                       :class="(locked || uploading) ? 'opacity-50 pointer-events-none' : ''">
                                    <span x-text="uploading === 'signature{{ $slot }}' ? @js(__('cert::messages.uploading')) : (t.has_signature[{{ $slot }}] ? @js(__('cert::messages.replace')) : @js(__('cert::messages.upload_signature')))"></span>
                                    <input type="file" class="sr-only" accept="image/png,image/jpeg" @change="upload('signature', {{ $slot }}, $event.target)">
                                </label>
                                <button type="button" x-show="t.has_signature[{{ $slot }}]" @click="removeFile('signature', {{ $slot }})" :disabled="locked"
                                        class="w-9 h-9 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 grid place-items-center" aria-label="{{ __('cert::messages.remove') }}"><i class="bi bi-trash3"></i></button>
                            </div>
                            <input type="text" maxlength="120" x-model="form.signature{{ $slot }}_name" @input="touch()" placeholder="{{ __('cert::messages.signer_name') }}" aria-label="{{ __('cert::messages.signer_name') }}"
                                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <input type="text" maxlength="120" x-model="form.signature{{ $slot }}_title" @input="touch()" placeholder="{{ __('cert::messages.signer_title') }}" aria-label="{{ __('cert::messages.signer_title') }}"
                                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Background --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-sm font-bold text-foreground">{{ __('cert::messages.background') }}</p>
                <p class="text-xs text-muted-foreground mt-0.5">{{ __('cert::messages.background_hint') }}</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-3">
                    @foreach($backgrounds as $bg)
                        <button type="button" @click="form.background = '{{ $bg }}'; touch()" role="radio" :aria-checked="form.background === '{{ $bg }}'"
                                class="text-start rounded-xl border-2 p-2 transition-colors"
                                :class="form.background === '{{ $bg }}' ? 'border-primary bg-primary/5' : 'border-gray-100 hover:border-gray-200'">
                            @include('cert::design.partials.thumb', ['bg' => $bg, 'color' => $color])
                            <span class="flex items-center gap-1.5 mt-2">
                                <span class="flex-1 text-xs font-bold text-foreground">{{ __('cert::messages.bg_'.$bg) }}</span>
                                <i class="bi text-sm" :class="form.background === '{{ $bg }}' ? 'bi-check-circle-fill text-primary' : 'bi-circle text-gray-300'"></i>
                            </span>
                        </button>
                    @endforeach
                </div>

                {{-- Your own artwork --}}
                <div x-show="form.background === 'artwork'" x-transition class="mt-4 rounded-xl border border-gray-100 p-4 space-y-3">
                    <p class="text-xs text-muted-foreground leading-relaxed">{{ __('cert::messages.artwork_rules', ['w' => \App\Certificates\Support\CertificateImages::MIN_ARTWORK[0], 'h' => \App\Certificates\Support\CertificateImages::MIN_ARTWORK[1]]) }}</p>
                    <div class="flex gap-2">
                        <label class="flex-1 h-10 rounded-lg bg-primary text-white text-sm font-bold grid place-items-center cursor-pointer hover:bg-primary/90 transition-colors"
                               :class="(locked || uploading) ? 'opacity-50 pointer-events-none' : ''">
                            <span x-text="uploading === 'artwork' ? @js(__('cert::messages.uploading')) : (t.has_artwork ? @js(__('cert::messages.replace_artwork')) : @js(__('cert::messages.upload_artwork')))"></span>
                            <input type="file" class="sr-only" accept="image/png,image/jpeg,image/webp" @change="upload('artwork', null, $event.target)">
                        </label>
                        <button type="button" x-show="t.has_artwork" @click="removeFile('artwork')" :disabled="locked"
                                class="px-4 h-10 rounded-lg border border-red-300 text-red-600 hover:bg-red-50 text-sm font-medium">{{ __('cert::messages.remove') }}</button>
                    </div>
                    <p x-show="! t.has_artwork" class="text-xs text-amber-700"><i class="bi bi-info-circle me-1"></i>{{ __('cert::messages.artwork_missing') }}</p>
                </div>

                {{-- Blank, for pre-printed paper --}}
                <div x-show="form.background === 'blank'" x-transition class="mt-4 rounded-xl border border-gray-100 p-4 space-y-3">
                    <p class="text-sm font-bold text-foreground">{{ __('cert::messages.line_up') }}</p>
                    @foreach(['y' => 'cert::messages.move_down', 'x' => 'cert::messages.move_right'] as $axis => $label)
                        <div class="flex items-center gap-3">
                            <span class="flex-1 text-sm text-gray-700">{{ __($label) }}</span>
                            <button type="button" @click="nudgePrint('{{ $axis }}', -1)" class="w-9 h-9 rounded-lg border border-gray-200 font-bold hover:bg-muted/60" aria-label="−">−</button>
                            <span class="w-16 text-center text-sm font-bold" x-text="(form.print_offset_{{ $axis }} || 0) + ' mm'"></span>
                            <button type="button" @click="nudgePrint('{{ $axis }}', 1)" class="w-9 h-9 rounded-lg border border-gray-200 font-bold hover:bg-muted/60" aria-label="+">+</button>
                        </div>
                    @endforeach
                    <p class="text-xs text-muted-foreground leading-relaxed">{{ __('cert::messages.blank_note') }}</p>
                </div>
            </div>

            {{-- The finish --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-4">
                <div>
                    <p class="text-sm font-bold text-foreground">{{ __('cert::messages.frame') }}</p>
                    <div class="grid grid-cols-3 gap-2 mt-2">
                        @foreach(['full', 'minimal', 'off'] as $o)
                            <button type="button" @click="form.ornament_level = '{{ $o }}'; touch()"
                                    class="rounded-xl border-2 py-2.5 text-sm font-bold transition-colors"
                                    :class="form.ornament_level === '{{ $o }}' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-100 text-gray-600 hover:border-gray-200'">{{ __('cert::messages.frame_'.$o) }}</button>
                        @endforeach
                    </div>
                </div>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" x-model="form.show_photo" @change="touch()" class="mt-0.5 w-[18px] h-[18px] rounded border-gray-300 text-primary focus:ring-primary">
                    <span class="text-sm text-gray-700">{{ __('cert::messages.show_photo') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-3">
                <span x-show="dirty" class="text-xs text-amber-700"><i class="bi bi-dot"></i>{{ __('cert::messages.unsaved') }}</span>
                <button type="button" @click="save()" :disabled="saving || ! dirty"
                        class="bg-primary text-white px-5 py-2.5 rounded-lg hover:bg-primary/90 transition-colors font-medium disabled:opacity-50 inline-flex items-center gap-2">
                    <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check2'"></i>{{ __('cert::messages.save_design') }}
                </button>
            </div>
        </div>

        {{-- ===================== the preview ===================== --}}
        <div class="lg:sticky lg:top-20">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <div class="flex items-center justify-between gap-3 mb-3">
                    <p class="text-sm font-bold text-foreground">{{ __('cert::messages.preview') }}</p>
                    <label x-show="form.background === 'blank'" class="inline-flex items-center gap-2 text-xs text-gray-600 cursor-pointer">
                        <input type="checkbox" x-model="preview.print" @change="refreshPreview(true)" class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">{{ __('cert::messages.preview_print') }}
                    </label>
                </div>
                @include('cert::design.partials.canvas', ['compact' => false])
                <p class="text-xs text-muted-foreground mt-3">{{ __('cert::messages.preview_note') }}</p>
            </div>
        </div>
    </div>
    </template>
</div>
@endsection
