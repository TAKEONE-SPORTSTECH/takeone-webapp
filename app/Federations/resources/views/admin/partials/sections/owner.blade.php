<div>
    <label class="{{ $lb }}" for="f-owner">{{ __('federations::messages.f_owner_email') }} <span class="text-destructive">*</span></label>
    {{-- Type the owner's email, or find them among TakeOne's members — both post owner_email. --}}
    <x-fields.person-picker id="f-owner" name="owner_email" :value="$values['owner_email']" :required="true"
                            :search-url="route('admin.federations.people')" :input-class="$in" placeholder="president@federation.bh" />
    <p x-show="hasErr('owner_email')" x-text="err('owner_email')" class="text-xs text-red-600 mt-1"></p>
    @if ($values['owner_name'])
        <p class="text-xs text-muted-foreground mt-2"><i class="bi bi-check-circle-fill text-green-600 me-1"></i>{{ $values['owner_name'] }}</p>
    @endif
    <p class="text-xs text-muted-foreground mt-2">{{ __('federations::messages.f_owner_help') }}</p>
</div>
