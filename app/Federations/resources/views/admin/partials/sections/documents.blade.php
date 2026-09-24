<div class="space-y-4">
    <div>
        <label class="{{ $lb }}" for="f-intl">{{ __('federations::messages.f_intl') }}</label>
        <input id="f-intl" name="international_federation" value="{{ $values['international_federation'] }}" maxlength="150" class="{{ $in }}" placeholder="{{ __('federations::messages.f_intl_ph') }}">
    </div>
    <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-colors" x-data="{ on: @js($values['olympic_recognised']) }"
           :class="on ? 'border-primary bg-accent' : 'border-gray-100 bg-white'">
        <input type="hidden" name="olympic_recognised" :value="on ? 1 : 0">
        <span class="w-6 h-6 rounded-md border flex items-center justify-center flex-shrink-0" :class="on ? 'bg-primary border-primary text-white' : 'border-gray-300 bg-white'"><i class="bi bi-check-lg text-sm" x-show="on"></i></span>
        <input type="checkbox" class="hidden" x-model="on">
        <span class="text-sm text-gray-900">{{ __('federations::messages.f_olympic') }}</span>
    </label>
    <div class="space-y-2">
        @foreach (['statutes', 'international_letter', 'olympic_letter'] as $kind)
            @include('federations::admin.partials.doc-row', ['kind' => $kind])
        @endforeach
        <p class="text-xs text-muted-foreground">{{ __('federations::messages.doc_hint') }}</p>
    </div>
</div>
