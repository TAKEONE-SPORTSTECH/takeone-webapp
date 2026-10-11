{{--
    What the selected element can be told, beyond where it sits and how big it is: the
    pattern's style, the artwork's picture and how it meets the page (the desktop-wallpaper
    choices), the frame's weight, and a text element's own words and style.
    Lives inside certDesign() (partials/script) and the canvas's "selected element" card;
    `$compact` is the phone's version. Nothing here decides anything — the server draws
    the page from these values after each change.
--}}
@php
    $compact = $compact ?? false;
    $tap = $compact ? 'h-11' : 'h-9';
    $fits = ['fill' => 'bi-arrows-fullscreen', 'fit' => 'bi-aspect-ratio', 'stretch' => 'bi-arrows-angle-expand', 'tile' => 'bi-grid-3x3', 'center' => 'bi-bullseye'];
    $styles = ['heading' => 'bi-type-h1', 'body' => 'bi-text-paragraph', 'script' => 'bi-type-italic', 'small' => 'bi-fonts'];
@endphp

{{-- Pattern: the drawn backgrounds --}}
<div x-show="sel === 'pattern'" class="space-y-2">
    <p class="text-[12px] text-muted-foreground">{{ __('cert::messages.pattern_gone_hint') }}</p>
    <label class="mt-1 inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-primary text-primary text-xs font-bold cursor-pointer hover:bg-primary hover:text-white transition-colors"
           :class="locked || uploading ? 'opacity-50 pointer-events-none' : ''">
        <i class="bi bi-image"></i>{{ __('cert::messages.use_own_picture') }}
        <input type="file" class="sr-only" accept="image/png,image/jpeg,image/webp" @change="pickArtwork($event.target)">
    </label>
</div>

{{-- Artwork: the organiser's own picture, full page --}}
<div x-show="sel === 'artwork'" class="space-y-3">
    <p x-show="! portrait" class="text-[12px] text-muted-foreground leading-relaxed">{{ __('cert::messages.artwork_rules_short') }}</p>
    <p x-show="portrait" class="text-[12px] text-muted-foreground leading-relaxed">{{ __('cert::messages.artwork_rules_short_portrait') }}</p>
    <div class="flex gap-2">
        <label class="flex-1 {{ $tap }} rounded-lg bg-primary text-white text-xs font-bold grid place-items-center cursor-pointer hover:bg-primary/90 transition-colors"
               :class="(locked || uploading) ? 'opacity-50 pointer-events-none' : ''">
            <span x-text="uploading === 'artwork' ? @js(__('cert::messages.uploading')) : (t.has_artwork ? @js(__('cert::messages.replace_artwork')) : @js(__('cert::messages.upload_artwork')))"></span>
            <input type="file" class="sr-only" accept="image/png,image/jpeg,image/webp" @change="upload('artwork', null, $event.target)">
        </label>
        <button type="button" x-show="t.has_artwork" @click="removeFile('artwork')" :disabled="locked"
                class="px-3 {{ $tap }} rounded-lg border border-red-300 text-red-600 hover:bg-red-50 text-xs font-bold">{{ __('cert::messages.remove_picture') }}</button>
    </div>
    <p x-show="! t.has_artwork" class="text-[12px] text-amber-700"><i class="bi bi-info-circle me-1"></i>{{ __('cert::messages.artwork_missing') }}</p>

    <div x-show="t.has_artwork">
        <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-1.5">{{ __('cert::messages.fit') }}</p>
        <div class="grid grid-cols-5 gap-1.5" role="radiogroup" aria-label="{{ __('cert::messages.fit') }}">
            @foreach($fits as $fit => $icon)
                <button type="button" @click="form.artwork_fit = '{{ $fit }}'; touch(150)" role="radio" :aria-checked="form.artwork_fit === '{{ $fit }}'"
                        class="rounded-xl border-2 py-2 px-1 flex flex-col items-center gap-1 transition-colors {{ $compact ? 'm-press' : '' }}"
                        :class="form.artwork_fit === '{{ $fit }}' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-100 text-gray-600 hover:border-gray-200'">
                    <i class="bi {{ $icon }} text-base"></i>
                    <span class="text-[11px] font-bold leading-tight text-center">{{ __('cert::messages.fit_'.$fit) }}</span>
                </button>
            @endforeach
        </div>
        <p class="text-[12px] text-muted-foreground mt-1.5" x-text="{
            fill: @js(__('cert::messages.fit_fill_hint')), fit: @js(__('cert::messages.fit_fit_hint')), stretch: @js(__('cert::messages.fit_stretch_hint')),
            tile: @js(__('cert::messages.fit_tile_hint')), center: @js(__('cert::messages.fit_center_hint')),
        }[form.artwork_fit] || @js(__('cert::messages.fit_none_hint'))"></p>
    </div>
</div>

{{-- Frame --}}
<div x-show="sel === 'frame'" class="grid grid-cols-2 gap-2">
    @foreach(['full', 'minimal'] as $o)
        <button type="button" @click="form.ornament_level = '{{ $o }}'; touch()" role="radio" :aria-checked="form.ornament_level === '{{ $o }}'"
                class="rounded-xl border-2 {{ $tap }} text-xs font-bold transition-colors {{ $compact ? 'm-press' : '' }}"
                :class="form.ornament_level === '{{ $o }}' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-100 text-gray-600 hover:border-gray-200'">{{ __('cert::messages.frame_'.$o) }}</button>
    @endforeach
</div>

{{-- The organiser's own text --}}
<p x-show="sel && isErase(sel)" class="text-[12px] text-muted-foreground leading-relaxed"><i class="bi bi-eraser me-1"></i>{{ __('cert::messages.erase_hint') }}</p>

<template x-if="sel && isCustom(sel) && custom(sel)">
    <div class="space-y-2">
        <label class="block text-[11px] font-bold uppercase tracking-wider text-muted-foreground" :for="'cert-x-' + sel">
            {{ __('cert::messages.text_words') }} <span class="normal-case font-semibold" x-text="'· ' + lang.toUpperCase()"></span>
        </label>
        <textarea :id="'cert-x-' + sel" rows="2" maxlength="300" :dir="lang === 'ar' ? 'rtl' : 'ltr'"
                  :value="custom(sel).text[lang] || ''" @input="customText(sel, $event.target.value)"
                  class="w-full px-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent leading-relaxed"></textarea>
        <p class="text-[12px] text-muted-foreground">{{ __('cert::messages.text_words_hint') }}</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach($styles as $st => $icon)
                <button type="button" @click="customStyle(sel, '{{ $st }}')" role="radio" :aria-checked="custom(sel).style === '{{ $st }}'"
                        class="{{ $tap }} px-3 rounded-lg border-2 text-xs font-bold inline-flex items-center gap-1.5 transition-colors"
                        :class="custom(sel).style === '{{ $st }}' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-100 text-gray-600 hover:border-gray-200'">
                    <i class="bi {{ $icon }}"></i>{{ __('cert::messages.style_'.$st) }}
                </button>
            @endforeach
        </div>
    </div>
</template>
