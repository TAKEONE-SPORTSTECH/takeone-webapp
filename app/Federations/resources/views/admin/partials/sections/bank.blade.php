<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="{{ $lb }}" for="f-acc">{{ __('federations::messages.f_bank_account_name') }}</label>
        <input id="f-acc" name="bank_account_name" value="{{ $values['bank_account_name'] }}" maxlength="150" class="{{ $in }}" :placeholder="v.name">
        <p class="text-xs text-muted-foreground mt-1">{{ __('federations::messages.f_bank_account_help') }}</p>
    </div>
    <div class="md:col-span-2">
        <label class="{{ $lb }}" for="f-bank">{{ __('federations::messages.f_bank_name') }}</label>
        <input id="f-bank" name="bank_name" value="{{ $values['bank_name'] }}" maxlength="150" class="{{ $in }}" placeholder="National Bank of Bahrain">
    </div>
    <div>
        <label class="{{ $lb }}" for="f-iban">{{ __('federations::messages.f_iban') }}</label>
        <input id="f-iban" name="iban" value="{{ $values['iban'] }}" maxlength="42" autocomplete="off" spellcheck="false"
               oninput="this.value=this.value.toUpperCase()" class="{{ $in }} fedx-iban" :class="hasErr('iban') && 'border-red-400'" placeholder="BH67 BMAG 0000 1299 1234 56">
        <p x-show="hasErr('iban')" x-text="err('iban')" class="text-xs text-red-600 mt-1"></p>
    </div>
    <div>
        <label class="{{ $lb }}" for="f-swift">{{ __('federations::messages.f_swift') }}</label>
        <input id="f-swift" name="swift" value="{{ $values['swift'] }}" maxlength="11" autocomplete="off" spellcheck="false"
               oninput="this.value=this.value.toUpperCase()" class="{{ $in }} fedx-iban" :class="hasErr('swift') && 'border-red-400'" placeholder="NBOBBHBM">
        <p x-show="hasErr('swift')" x-text="err('swift')" class="text-xs text-red-600 mt-1"></p>
    </div>
</div>
