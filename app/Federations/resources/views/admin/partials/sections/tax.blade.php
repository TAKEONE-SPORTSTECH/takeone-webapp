<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="{{ $lb }}" for="f-tax">{{ __('federations::messages.f_tax_number') }}</label>
        <input id="f-tax" name="tax_number" value="{{ $values['tax_number'] }}" maxlength="40" class="{{ $in }} fedx-iban">
        <p x-show="hasErr('tax_number')" x-text="err('tax_number')" class="text-xs text-red-600 mt-1"></p>
    </div>
    <div>
        <label class="{{ $lb }}" for="f-inv-name">{{ __('federations::messages.f_invoice_name') }}</label>
        <input id="f-inv-name" name="invoice_name" value="{{ $values['invoice_name'] }}" maxlength="150" class="{{ $in }}" :placeholder="v.name">
    </div>
    <div class="md:col-span-2">
        <label class="{{ $lb }}" for="f-inv-addr">{{ __('federations::messages.f_invoice_address') }}</label>
        <textarea id="f-inv-addr" name="invoice_address" rows="2" maxlength="300" class="{{ $in }}">{{ $values['invoice_address'] }}</textarea>
        <p class="text-xs text-muted-foreground mt-1">{{ __('federations::messages.f_invoice_help') }}</p>
    </div>
</div>
