<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="{{ $lb }}" for="f-reg-auth">{{ __('federations::messages.f_reg_authority') }}</label>
        <input id="f-reg-auth" name="registration_authority" value="{{ $values['registration_authority'] }}" maxlength="150" class="{{ $in }}" placeholder="General Sports Authority">
    </div>
    <div>
        <label class="{{ $lb }}" for="f-reg-no">{{ __('federations::messages.f_reg_number') }}</label>
        <input id="f-reg-no" name="registration_number" value="{{ $values['registration_number'] }}" maxlength="60" class="{{ $in }}">
    </div>
    <div class="md:col-span-2">
        @include('federations::admin.partials.doc-row', ['kind' => 'registration_certificate'])
        <p class="text-xs text-muted-foreground mt-2">{{ __('federations::messages.doc_hint') }}</p>
    </div>
</div>
