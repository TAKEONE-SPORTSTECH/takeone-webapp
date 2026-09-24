<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="{{ $lb }}" for="f-name">{{ __('federations::messages.f_name') }} <span class="text-destructive">*</span></label>
        <input id="f-name" name="name" x-model="v.name" maxlength="150" class="{{ $in }}" :class="hasErr('name') && 'border-red-400'" placeholder="Bahrain Karate Federation" required>
        <p x-show="hasErr('name')" x-text="err('name')" class="text-xs text-red-600 mt-1"></p>
    </div>
    <div>
        <label class="{{ $lb }}" for="f-short">{{ __('federations::messages.f_short') }}</label>
        <input id="f-short" name="short_name" x-model="v.short_name" maxlength="20" class="{{ $in }}" :class="hasErr('short_name') && 'border-red-400'" :placeholder="(v.name || '').split(/\s+/).filter(w => w && !['of','the','and','for','&'].includes(w.toLowerCase())).map(w => w[0].toUpperCase()).join('').slice(0, 20) || 'BKF'" placeholder="BKF">
        <p x-show="hasErr('short_name')" x-text="err('short_name')" class="text-xs text-red-600 mt-1"></p>
    </div>
    <div>
        <x-fields.country name="country" id="f_country" :value="$values['country']" :label="__('federations::messages.f_country')" :required="true" />
        <p x-show="hasErr('country')" x-text="err('country')" class="text-xs text-red-600 mt-1"></p>
    </div>
    <div class="md:col-span-2">
        <label class="{{ $lb }}">{{ __('federations::messages.f_sports') }} <span class="text-destructive">*</span></label>
        <div class="flex flex-wrap gap-2">
            @foreach ($sports as $sport)
                <button type="button" @click="toggleSport(@js($sport))"
                        class="fedx-chip-toggle px-3 py-1.5 rounded-full text-xs font-medium border"
                        :class="v.sports.includes(@js($sport)) ? 'bg-primary text-white border-primary' : 'bg-white text-gray-700 border-gray-200 hover:border-primary'">
                    <i class="bi me-1" :class="v.sports.includes(@js($sport)) ? 'bi-check-lg' : 'bi-plus'"></i>{{ $sport }}
                </button>
            @endforeach
        </div>
        <template x-for="s in v.sports" :key="s"><input type="hidden" name="sports[]" :value="s"></template>
        <p x-show="hasErr('sports')" x-text="err('sports')" class="text-xs text-red-600 mt-1"></p>
    </div>
    <div><x-fields.timezone name="timezone" id="f_tz" :value="$values['timezone']" :label="__('federations::messages.f_timezone')" /></div>
    <div><x-fields.currency name="currency" id="f_cur" :value="$values['currency']" :label="__('federations::messages.f_currency')" /></div>
    <div>
        <label class="{{ $lb }}" for="f-founded">{{ __('federations::messages.f_founded') }}</label>
        <input id="f-founded" name="founded_year" type="number" inputmode="numeric" min="1850" max="{{ now()->year }}" value="{{ $values['founded_year'] }}" class="{{ $in }}" placeholder="1976">
        <p x-show="hasErr('founded_year')" x-text="err('founded_year')" class="text-xs text-red-600 mt-1"></p>
    </div>
    <div>
        <label class="{{ $lb }}">{{ __('federations::messages.f_languages') }}</label>
        <div class="flex flex-wrap gap-2">
            @foreach ($languages as $code => $label)
                <button type="button" @click="toggleLang(@js($code))"
                        class="fedx-chip-toggle px-3 py-1.5 rounded-full text-xs font-medium border"
                        :class="v.languages.includes(@js($code)) ? 'bg-primary text-white border-primary' : 'bg-white text-gray-700 border-gray-200 hover:border-primary'">{{ $label }}</button>
            @endforeach
        </div>
        <template x-for="l in v.languages" :key="l"><input type="hidden" name="languages[]" :value="l"></template>
    </div>
</div>
