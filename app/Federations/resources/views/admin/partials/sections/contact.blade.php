<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    {{-- No official contact yet? Borrow a TakeOne member's email and phone.
         Fills the fields below; they stay editable and post as typed. --}}
    <div class="md:col-span-2" x-data="{
            open: false, used: '',
            use(p) {
                const box = $el.parentElement;
                const email = box.querySelector('[name=email]');
                if (email && p.email) { email.value = p.email; email.dispatchEvent(new Event('input', { bubbles: true })); }
                const num = box.querySelector('[name=phone_number]');
                if (num && p.phone_number) { num.value = p.phone_number; num.dispatchEvent(new Event('input', { bubbles: true })); }
                const group = box.querySelector('[data-phone-group]');
                const code = String(p.phone_code || '').trim();
                if (group && code && window.Alpine) {
                    const d = Alpine.$data(group);
                    const c = (d.countries || []).find(c => c.code === code);
                    if (c) { d.selectedCode = c.code; d.selectedFlag = c.flag; }
                }
                this.used = p.name; this.open = false;
            },
         }" @member-picked="use($event.detail)">
        <button type="button" @click="open = !open" x-show="!open"
                class="px-3 py-1.5 rounded-full text-xs font-medium border border-primary text-primary hover:bg-primary hover:text-white transition-colors inline-flex items-center gap-1.5">
            <i class="bi bi-person-lines-fill"></i>{{ __('federations::messages.contact_use_member') }}
        </button>
        <div x-show="open" x-cloak class="rounded-xl border border-gray-100 bg-muted/30 p-3">
            <div class="flex items-center justify-between gap-2 mb-2">
                <p class="text-xs text-muted-foreground mb-0">{{ __('federations::messages.contact_use_member_help') }}</p>
                <button type="button" @click="open = false" class="w-7 h-7 rounded-full grid place-items-center text-muted-foreground hover:bg-muted flex-shrink-0" aria-label="{{ __('shared.close') }}"><i class="bi bi-x-lg text-xs"></i></button>
            </div>
            <x-fields.member-search :search-url="route('admin.federations.people')" :input-class="$in" />
        </div>
        <p x-show="used" x-cloak class="text-xs text-green-600 mt-2 mb-0"><i class="bi bi-check-circle-fill me-1"></i><span x-text="@js(__('federations::messages.contact_used_member')).replace(':name', used)"></span></p>
    </div>
    <div>
        <label class="{{ $lb }}" for="f-email">{{ __('federations::messages.f_email') }}</label>
        <input id="f-email" name="email" type="email" value="{{ $values['email'] }}" class="{{ $in }}" :class="hasErr('email') && 'border-red-400'" placeholder="info@federation.bh">
        <p x-show="hasErr('email')" x-text="err('email')" class="text-xs text-red-600 mt-1"></p>
    </div>
    <div>
        <label class="{{ $lb }}" for="f-phone">{{ __('federations::messages.f_phone') }}</label>
        <x-fields.phone-code name="phone_code" id="f_phone_code" :value="$values['phone_code']">
            <input type="text" id="f-phone" name="phone_number" value="{{ $values['phone_number'] }}" inputmode="tel" class="{{ $in }}" :class="hasErr('phone_number') && 'border-red-400'" placeholder="17000000">
        </x-fields.phone-code>
        <p x-show="hasErr('phone_number')" x-text="err('phone_number')" class="text-xs text-red-600 mt-1"></p>
    </div>
    <div>
        <label class="{{ $lb }}" for="f-hours">{{ __('federations::messages.f_office_hours') }}</label>
        <input id="f-hours" name="office_hours" value="{{ $values['office_hours'] }}" maxlength="120" class="{{ $in }}" placeholder="{{ __('federations::messages.f_office_hours_ph') }}">
    </div>
    <div>
        <label class="{{ $lb }}" for="f-web">{{ __('federations::messages.f_website') }}</label>
        <input id="f-web" name="website" type="url" value="{{ $values['website'] }}" class="{{ $in }}" placeholder="https://">
        <p x-show="hasErr('website')" x-text="err('website')" class="text-xs text-red-600 mt-1"></p>
    </div>
    <div class="md:col-span-2">
        {{-- The map component labels its own address field. --}}
        <x-fields.location-map id="fedHqMap{{ $mapSuffix ?? '' }}" addressName="address" latName="lat" lngName="lng"
            :lat="$values['lat']" :lng="$values['lng']" :address="$values['address'] ?? ''"
            :defaultLat="26.2235" :defaultLng="50.5876" height="18rem" :required="false"
            mapsUrlName="maps_url" :mapsUrl="$values['maps_url'] ?? ''" />
        <p x-show="hasErr('address')" x-text="err('address')" class="text-xs text-red-600 mt-1"></p>
        <p x-show="hasErr('maps_url')" x-text="err('maps_url')" class="text-xs text-red-600 mt-1"></p>
    </div>
    <div class="md:col-span-2">
        <label class="{{ $lb }}">{{ __('federations::messages.f_socials') }}</label>
        <x-fields.social-links :links="$values['social_links']" containerId="fedSocials{{ $mapSuffix ?? '' }}" />
    </div>
</div>
