<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label class="{{ $lb }}">{{ __('federations::messages.f_logo') }}</label>
        <div class="flex items-center gap-4">
            {{-- Bare logo on a transparent box — never a white tile (Design Rule #5). --}}
            <span class="w-20 h-20 flex-shrink-0 flex items-center justify-center">
                <template x-if="logoPreview"><img :src="logoPreview" alt="" class="w-full h-full object-contain"></template>
                <template x-if="!logoPreview"><i class="bi bi-image text-3xl text-gray-300"></i></template>
            </span>
            <label class="px-4 py-2 rounded-lg text-sm font-medium border border-primary text-primary hover:bg-primary hover:text-white transition-colors cursor-pointer">
                <i class="bi bi-upload me-1"></i><span x-text="logoPreview ? @js(__('federations::messages.doc_replace')) : @js(__('federations::messages.doc_upload'))"></span>
                <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="hidden" @change="pickLogo($event)">
            </label>
        </div>
        <p class="text-xs text-muted-foreground mt-2">{{ __('federations::messages.f_logo_hint') }}</p>
        <p x-show="hasErr('logo')" x-text="err('logo')" class="text-xs text-red-600 mt-1"></p>
    </div>
    <div>
        <label class="{{ $lb }}">{{ __('federations::messages.f_color') }}</label>
        <div class="flex flex-wrap items-center gap-2">
            @foreach (['#1e2c4f', '#0b132b', '#b91c1c', '#b45309', '#047857', '#0e7490', '#1d4ed8', '#7c6bf5', '#be185d'] as $sw)
                <button type="button" @click="v.color = @js($sw)" class="w-9 h-9 rounded-full border-2 transition-transform" style="background: {{ $sw }}"
                        :class="v.color === @js($sw) ? 'border-gray-900 scale-110' : 'border-white shadow-sm'" aria-label="{{ $sw }}"></button>
            @endforeach
        </div>
        <input name="color" x-model="v.color" maxlength="7" class="{{ $in }} fedx-iban mt-3" style="max-width: 9rem" placeholder="#1e2c4f">
        <p x-show="hasErr('color')" x-text="err('color')" class="text-xs text-red-600 mt-1"></p>
    </div>
</div>
