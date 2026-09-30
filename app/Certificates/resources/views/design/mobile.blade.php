@extends('members::layouts.personal-mobile')

@section('title', __('cert::messages.design_title'))

@section('personal-content')
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
    $bgLabels = [];
    foreach ($backgrounds as $b) {
        $bgLabels[$b] = __('cert::messages.bg_'.$b);
    }
    $frameLabels = ['full' => __('cert::messages.frame_full'), 'minimal' => __('cert::messages.frame_minimal'), 'off' => __('cert::messages.frame_off')];
    $panelTitles = ['scope' => __('cert::messages.scope_title'), 'wording' => __('cert::messages.wording'), 'signatures' => __('cert::messages.signatures'), 'background' => __('cert::messages.background'), 'finish' => __('cert::messages.frame')];
    $panels = [
        'scope' => ['bi-diagram-2', 'bg-amber-50 text-amber-700', 'cert::messages.scope_title'],
        'wording' => ['bi-fonts', 'bg-accent text-primary', 'cert::messages.wording'],
        'signatures' => ['bi-pen', 'bg-sky-50 text-sky-700', 'cert::messages.signatures'],
        'background' => ['bi-image', 'bg-emerald-50 text-emerald-700', 'cert::messages.background'],
        'finish' => ['bi-bounding-box', 'bg-rose-50 text-rose-700', 'cert::messages.frame'],
    ];
@endphp
@include('cert::design.partials.script')

<div class="-mx-4 -mt-4" x-data="certDesign(@js($state), @js($endpoints), @js($strings))">

    {{-- Hero band (Design Rule #6) --}}
    <header class="m-hero px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $color }}, {{ $color }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-50">
            <a href="{{ $backUrl }}" aria-label="{{ __('cert::messages.back') }}" title="{{ __('cert::messages.back') }}"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>
        </div>
        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-patch-check-fill"></i> {{ __('cert::messages.console_title') }}</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-palette-fill"></i> {{ __('cert::messages.design_chip') }}</span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('cert::messages.design_title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5"><i class="bi bi-trophy"></i>{{ $headline }}</p>
        </div>
    </header>

    <template x-if="form">
    <div class="-mt-10 relative z-10 px-4 pb-8 space-y-4">

        {{-- ============ the hub ============ --}}
        <div x-show="! panel" class="space-y-4 mobile-stagger">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1.5 flex gap-1">
                <template x-for="tt in state.templates" :key="tt.type">
                    <button type="button" @click="dirty ? window.showToast('info', strings.save_first) : pickType(tt.type)"
                            class="m-press flex-1 h-10 rounded-xl text-sm font-bold transition-colors"
                            :class="type === tt.type ? 'bg-primary text-white' : 'text-muted-foreground'" x-text="tt.label"></button>
                </template>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-3">
                @include('cert::design.partials.canvas', ['compact' => true])
                <div class="flex items-center justify-between mt-2.5 gap-2">
                    <div class="inline-flex bg-muted rounded-full p-0.5">
                        @foreach($state['locales'] as $l)
                            <button type="button" @click="lang = '{{ $l }}'; refreshPreview(true)" class="h-8 px-3.5 rounded-full text-xs font-bold transition-colors"
                                    :class="lang === '{{ $l }}' ? 'bg-primary text-white' : 'text-muted-foreground'">{{ $l === 'ar' ? 'العربية' : 'English' }}</button>
                        @endforeach
                    </div>
                    <label x-show="form.background === 'blank'" class="inline-flex items-center gap-2 text-xs text-gray-600">
                        <input type="checkbox" x-model="preview.print" @change="refreshPreview(true)" class="w-[18px] h-[18px] rounded border-gray-300 text-primary focus:ring-primary">{{ __('cert::messages.preview_print') }}
                    </label>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden divide-y divide-gray-100">
                @foreach($panels as $key => [$icon, $tile, $label])
                    <button type="button" @click="panel = '{{ $key }}'; window.scrollTo({ top: 0, behavior: 'smooth' })"
                            class="m-press w-full text-start flex items-center gap-3 p-3.5 min-h-[60px]">
                        <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 {{ $tile }}"><i class="bi {{ $icon }}"></i></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-foreground">{{ __($label) }}</span>
                            <span class="block text-[12px] text-muted-foreground truncate" x-text="{
                                scope: scopeLabel,
                                wording: form.title[lang] || '—',
                                signatures: [1, 2].filter(n => t.has_signature[n]).length + ' / 2',
                                background: @js($bgLabels)[form.background],
                                finish: @js($frameLabels)[form.ornament_level],
                            }['{{ $key }}']"></span>
                        </span>
                        <i class="bi bi-chevron-right rtl:rotate-180 text-xs text-muted-foreground/60"></i>
                    </button>
                @endforeach
            </div>

            <button type="button" @click="save()" :disabled="saving || ! dirty"
                    class="m-press w-full py-3 rounded-xl bg-primary text-white text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-50">
                <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check2'"></i>{{ __('cert::messages.save_design') }}
            </button>
        </div>

        {{-- ============ drill-down panels ============ --}}
        <div x-show="panel" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden m-panel-in">
            <div class="sticky top-14 z-20 bg-white border-b border-gray-100 flex items-center gap-2 px-2 py-2">
                <button type="button" @click="panel = null" aria-label="{{ __('cert::messages.back') }}" class="m-press w-10 h-10 rounded-full grid place-items-center text-foreground"><i class="bi bi-chevron-left rtl:rotate-180"></i></button>
                <p class="flex-1 text-sm font-bold text-foreground" x-text="@js($panelTitles)[panel]"></p>
                <button type="button" @click="save()" :disabled="saving || ! dirty" class="m-press h-9 px-4 rounded-full bg-primary text-white text-xs font-bold disabled:opacity-50">{{ __('cert::messages.save') }}</button>
            </div>

            <div class="p-4 space-y-4">
                {{-- Whose design --}}
                <div x-show="panel === 'scope'" class="space-y-2">
                    <p class="text-[12px] text-muted-foreground">{{ __('cert::messages.scope_hint') }}</p>
                    <template x-for="sc in state.scopes" :key="sc.value">
                        <button type="button" @click="if (sc.allowed) { form.scope = sc.value; touch(); }" :disabled="! sc.allowed" role="radio" :aria-checked="form.scope === sc.value"
                                class="m-press w-full text-start rounded-2xl border-2 p-3 flex items-start gap-3 min-h-[60px] disabled:opacity-60"
                                :class="form.scope === sc.value ? 'border-primary bg-primary/5' : 'border-gray-100'">
                            <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0" :class="sc.value === 'event' ? 'bg-accent text-primary' : 'bg-amber-50 text-amber-700'"><i class="bi" :class="sc.value === 'event' ? 'bi-calendar-event' : 'bi-building'"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground" x-text="sc.label"></span>
                                <span class="block text-[12px] text-muted-foreground mt-0.5" x-text="sc.allowed ? (sc.value === 'event' ? @js(__('cert::messages.scope_event_hint')) : @js(__('cert::messages.scope_host_hint'))) : @js(__('cert::messages.scope_locked'))"></span>
                            </span>
                            <i class="bi mt-1" :class="sc.allowed ? (form.scope === sc.value ? 'bi-check-circle-fill text-primary' : 'bi-circle text-gray-300') : 'bi-lock-fill text-gray-400'"></i>
                        </button>
                    </template>
                </div>

                {{-- Wording --}}
                <div x-show="panel === 'wording'" class="space-y-3">
                    <div class="inline-flex bg-muted rounded-full p-0.5">
                        @foreach($state['locales'] as $l)
                            <button type="button" @click="lang = '{{ $l }}'; refreshPreview(true)" class="h-8 px-3.5 rounded-full text-xs font-bold" :class="lang === '{{ $l }}' ? 'bg-primary text-white' : 'text-muted-foreground'">{{ $l === 'ar' ? 'العربية' : 'English' }}</button>
                        @endforeach
                    </div>
                    @foreach($state['locales'] as $l)
                        <div x-show="lang === '{{ $l }}'" class="space-y-3" dir="{{ $l === 'ar' ? 'rtl' : 'ltr' }}">
                            <div>
                                <label for="m-cert-title-{{ $l }}" class="block text-sm font-medium text-gray-700 mb-1">{{ __('cert::messages.field_title') }}</label>
                                <input id="m-cert-title-{{ $l }}" type="text" maxlength="150" x-model="form.title['{{ $l }}']" @input="touch()" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="m-cert-body-{{ $l }}" class="block text-sm font-medium text-gray-700 mb-1">{{ __('cert::messages.field_body') }}</label>
                                <textarea id="m-cert-body-{{ $l }}" x-ref="body_{{ $l }}" rows="5" maxlength="800" x-model="form.body['{{ $l }}']" @input="touch()" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent leading-relaxed"></textarea>
                            </div>
                        </div>
                    @endforeach
                    <p class="text-[12px] text-muted-foreground">{{ __('cert::messages.tokens_hint') }}</p>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="tk in state.placeholders" :key="tk">
                            <button type="button" @click="insert(tk)" class="m-press px-3 py-1.5 rounded-full border border-purple-200 bg-purple-50 text-purple-700 text-xs font-semibold font-mono" x-text="'{' + tk + '}'"></button>
                        </template>
                    </div>
                </div>

                {{-- Signatures --}}
                <div x-show="panel === 'signatures'" class="space-y-3">
                    <p class="text-[12px] text-muted-foreground">{{ __('cert::messages.signatures_hint') }}</p>
                    @foreach([1, 2] as $slot)
                        <div class="rounded-2xl border border-gray-100 p-3 space-y-2">
                            <div class="h-16 rounded-xl bg-muted/50 grid place-items-center overflow-hidden">
                                <template x-if="t.has_signature[{{ $slot }}]"><img :src="sigSrc({{ $slot }})" alt="" class="max-w-full object-contain" style="max-height: 3.5rem;"></template>
                                <template x-if="! t.has_signature[{{ $slot }}]"><span class="text-xs text-muted-foreground"><i class="bi bi-pen me-1"></i>{{ __('cert::messages.no_signature') }}</span></template>
                            </div>
                            <div class="flex gap-2">
                                <label class="m-press flex-1 h-11 rounded-xl border border-primary text-primary text-sm font-bold grid place-items-center" :class="(locked || uploading) ? 'opacity-50 pointer-events-none' : ''">
                                    <span x-text="uploading === 'signature{{ $slot }}' ? @js(__('cert::messages.uploading')) : (t.has_signature[{{ $slot }}] ? @js(__('cert::messages.replace')) : @js(__('cert::messages.upload_signature')))"></span>
                                    <input type="file" class="sr-only" accept="image/png,image/jpeg" @change="upload('signature', {{ $slot }}, $event.target)">
                                </label>
                                <button type="button" x-show="t.has_signature[{{ $slot }}]" @click="removeFile('signature', {{ $slot }})" :disabled="locked" class="m-press w-11 h-11 rounded-xl border border-red-300 text-red-600 grid place-items-center" aria-label="{{ __('cert::messages.remove') }}"><i class="bi bi-trash3"></i></button>
                            </div>
                            <input type="text" maxlength="120" x-model="form.signature{{ $slot }}_name" @input="touch()" placeholder="{{ __('cert::messages.signer_name') }}" aria-label="{{ __('cert::messages.signer_name') }}" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <input type="text" maxlength="120" x-model="form.signature{{ $slot }}_title" @input="touch()" placeholder="{{ __('cert::messages.signer_title') }}" aria-label="{{ __('cert::messages.signer_title') }}" class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                    @endforeach
                </div>

                {{-- Background --}}
                <div x-show="panel === 'background'" class="space-y-3">
                    <p class="text-[12px] text-muted-foreground">{{ __('cert::messages.background_hint') }}</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($backgrounds as $bg)
                            <button type="button" @click="form.background = '{{ $bg }}'; touch()" role="radio" :aria-checked="form.background === '{{ $bg }}'"
                                    class="m-press text-start rounded-2xl border-2 p-2" :class="form.background === '{{ $bg }}' ? 'border-primary bg-primary/5' : 'border-gray-100'">
                                @include('cert::design.partials.thumb', ['bg' => $bg, 'color' => $color])
                                <span class="flex items-center gap-1.5 mt-2">
                                    <span class="flex-1 text-[13px] font-bold text-foreground">{{ __('cert::messages.bg_'.$bg) }}</span>
                                    <i class="bi" :class="form.background === '{{ $bg }}' ? 'bi-check-circle-fill text-primary' : 'bi-circle text-gray-300'"></i>
                                </span>
                            </button>
                        @endforeach
                    </div>
                    <div x-show="form.background === 'artwork'" x-transition class="rounded-2xl border border-gray-100 p-3 space-y-3">
                        <p class="text-[12px] text-muted-foreground leading-relaxed">{{ __('cert::messages.artwork_rules', ['w' => \App\Certificates\Support\CertificateImages::MIN_ARTWORK[0], 'h' => \App\Certificates\Support\CertificateImages::MIN_ARTWORK[1]]) }}</p>
                        <div class="flex gap-2">
                            <label class="m-press flex-1 h-11 rounded-xl bg-primary text-white text-sm font-bold grid place-items-center" :class="(locked || uploading) ? 'opacity-50 pointer-events-none' : ''">
                                <span x-text="uploading === 'artwork' ? @js(__('cert::messages.uploading')) : (t.has_artwork ? @js(__('cert::messages.replace_artwork')) : @js(__('cert::messages.upload_artwork')))"></span>
                                <input type="file" class="sr-only" accept="image/png,image/jpeg,image/webp" @change="upload('artwork', null, $event.target)">
                            </label>
                            <button type="button" x-show="t.has_artwork" @click="removeFile('artwork')" :disabled="locked" class="m-press px-4 h-11 rounded-xl border border-red-300 text-red-600 text-sm font-bold">{{ __('cert::messages.remove') }}</button>
                        </div>
                        <p x-show="! t.has_artwork" class="text-[12px] text-amber-700"><i class="bi bi-info-circle me-1"></i>{{ __('cert::messages.artwork_missing') }}</p>
                    </div>
                    <div x-show="form.background === 'blank'" x-transition class="rounded-2xl border border-gray-100 p-3 space-y-3">
                        <p class="text-sm font-bold text-foreground">{{ __('cert::messages.line_up') }}</p>
                        @foreach(['y' => 'cert::messages.move_down', 'x' => 'cert::messages.move_right'] as $axis => $label)
                            <div class="flex items-center gap-2">
                                <span class="flex-1 text-sm text-gray-700">{{ __($label) }}</span>
                                <button type="button" @click="nudgePrint('{{ $axis }}', -1)" class="m-press w-11 h-11 rounded-xl border border-gray-200 text-lg font-bold" aria-label="−">−</button>
                                <span class="w-16 text-center text-sm font-bold" x-text="(form.print_offset_{{ $axis }} || 0) + ' mm'"></span>
                                <button type="button" @click="nudgePrint('{{ $axis }}', 1)" class="m-press w-11 h-11 rounded-xl border border-gray-200 text-lg font-bold" aria-label="+">+</button>
                            </div>
                        @endforeach
                        <p class="text-[12px] text-muted-foreground leading-relaxed">{{ __('cert::messages.blank_note') }}</p>
                    </div>
                </div>

                {{-- The finish --}}
                <div x-show="panel === 'finish'" class="space-y-3">
                    @foreach(['full', 'minimal', 'off'] as $o)
                        <button type="button" @click="form.ornament_level = '{{ $o }}'; touch()" role="radio" :aria-checked="form.ornament_level === '{{ $o }}'"
                                class="m-press w-full text-start rounded-2xl border-2 p-3 flex items-center gap-3 min-h-[60px]" :class="form.ornament_level === '{{ $o }}' ? 'border-primary bg-primary/5' : 'border-gray-100'">
                            <span class="flex-1 text-sm font-bold text-foreground">{{ __('cert::messages.frame_'.$o) }}</span>
                            <i class="bi" :class="form.ornament_level === '{{ $o }}' ? 'bi-check-circle-fill text-primary' : 'bi-circle text-gray-300'"></i>
                        </button>
                    @endforeach
                    <label class="flex items-start gap-3 rounded-2xl border border-gray-100 p-3">
                        <input type="checkbox" x-model="form.show_photo" @change="touch()" class="mt-0.5 w-[18px] h-[18px] rounded border-gray-300 text-primary focus:ring-primary">
                        <span class="text-sm text-gray-700">{{ __('cert::messages.show_photo') }}</span>
                    </label>
                </div>

                <button type="button" @click="save()" :disabled="saving || ! dirty"
                        class="m-press w-full py-3 rounded-xl bg-primary text-white text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-50">
                    <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check2'"></i>{{ __('cert::messages.save_design') }}
                </button>
            </div>
        </div>
    </div>
    </template>
</div>
@endsection
