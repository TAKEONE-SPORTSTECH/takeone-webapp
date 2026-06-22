@props([
    'formAction' => null,
])

@php
    $action = $formAction ?? route('family.store');
    // Stable id prefix for this sheet's fields (family-create-mobile).
    $p = 'fcm';
@endphp

{{--
    Mobile-only "Add Family Member" sheet.
    A full-height bottom sheet (not the dense desktop 4-tab modal). Fields are
    grouped into scannable sections with the heavy/optional stuff tucked into a
    collapsible "More details" block. Submits to family.store exactly like the
    desktop profile-modal create flow (same field names + JSON contract).
--}}
<div x-data="memberCreateSheetMobile()" x-cloak
     x-on:open-member-create-modal.window="openSheet()"
     @keydown.escape.window="close()">

    {{-- Backdrop --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[60] bg-black/50" @click="close()" style="display:none;"></div>

    {{-- Sheet --}}
    <div x-show="open" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-250" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
         class="fixed inset-x-0 bottom-0 z-[61] flex flex-col bg-background rounded-t-3xl shadow-2xl max-h-[94vh] h-[94vh]"
         style="display:none;" @click.stop>

        {{-- Grab handle + header --}}
        <div class="flex-shrink-0 rounded-t-3xl bg-white border-b border-border">
            <div class="flex justify-center pt-2.5 pb-1">
                <span class="h-1.5 w-10 rounded-full bg-gray-300"></span>
            </div>
            <div class="flex items-center gap-3 px-4 pb-3">
                <span class="w-10 h-10 rounded-2xl bg-accent flex items-center justify-center flex-shrink-0">
                    <i class="bi bi-person-plus text-primary text-lg"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-bold text-foreground leading-tight">{{ __('member.add_family_member') }}</p>
                    <p class="text-[11px] text-muted-foreground leading-tight">{{ __('member.add_member_subtitle') }}</p>
                </div>
                <button type="button" @click="close()" class="m-press w-9 h-9 rounded-xl flex items-center justify-center text-muted-foreground hover:bg-muted" aria-label="{{ __('member.close') }}">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        {{-- Scrollable body --}}
        <div class="flex-1 overflow-y-auto overscroll-contain px-4 py-4">
            <form method="POST" action="{{ $action }}" id="{{ $p }}Form" @submit.prevent="submit()">
                @csrf

                {{-- ===== Identity ===== --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-3">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="bi bi-person-lines-fill text-primary text-sm"></i>
                        <span class="text-[11px] font-bold text-muted-foreground uppercase tracking-wide">{{ __('member.who_are_they') }}</span>
                    </div>

                    <div class="mb-3">
                        <label for="{{ $p }}_full_name" class="tf-label">{{ __('member.full_name') }} <span class="text-red-500">*</span></label>
                        <input type="text" id="{{ $p }}_full_name" name="full_name" value="{{ old('full_name') }}"
                               class="tf-input" placeholder="{{ __('member.enter_full_name') }}" required>
                    </div>

                    <div class="mb-1">
                        <label class="tf-label">{{ __('member.mobile_number') }}</label>
                        <x-country-code-dropdown
                            name="mobile_code"
                            :id="$p . '_country_code'"
                            :value="old('mobile_code', '+973')"
                            :required="false">
                            <input id="{{ $p }}_mobile_number" type="tel" name="mobile" value="{{ old('mobile') }}"
                                   class="w-full px-4 py-3 text-base bg-transparent focus:outline-none"
                                   autocomplete="tel" placeholder="{{ __('member.phone_number') }}">
                        </x-country-code-dropdown>
                    </div>
                </div>

                {{-- ===== Relationship ===== --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-3">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="bi bi-people text-primary text-sm"></i>
                        <span class="text-[11px] font-bold text-muted-foreground uppercase tracking-wide">{{ __('member.relationship_to_you') }}</span>
                    </div>
                    <div class="mb-3">
                        <x-relationship-dropdown
                            name="relationship_type"
                            :id="$p . '_relationship_type'"
                            :label="__('member.relationship')"
                            :value="old('relationship_type')"
                            :required="true" />
                    </div>
                    <label class="flex items-center gap-2.5 cursor-pointer select-none">
                        <input type="checkbox" id="{{ $p }}_is_billing_contact" name="is_billing_contact" value="1"
                               class="w-4 h-4 rounded border-primary/30 text-primary focus:ring-primary/25" {{ old('is_billing_contact') ? 'checked' : '' }}>
                        <span class="text-sm text-foreground">{{ __('member.set_as_billing_contact') }}</span>
                    </label>
                </div>

                {{-- ===== Demographics ===== --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-3">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="bi bi-person-badge text-primary text-sm"></i>
                        <span class="text-[11px] font-bold text-muted-foreground uppercase tracking-wide">{{ __('member.details') }}</span>
                    </div>

                    <div class="mb-3">
                        <x-gender-dropdown name="gender" :id="$p . '_gender'" :label="__('member.gender_label')" :value="old('gender')" :required="true" />
                    </div>
                    <div class="mb-3">
                        <x-birthdate-dropdown
                            name="birthdate"
                            :id="$p . '_birthdate'"
                            :label="__('member.date_of_birth')"
                            :value="old('birthdate')"
                            :required="true"
                            :min-age="0"
                            :max-age="120" />
                    </div>
                    <div class="mb-3">
                        <x-country-dropdown name="nationality" :id="$p . '_nationality'" :label="__('member.nationality_label')" :value="old('nationality')" :required="true" />
                    </div>
                    <div class="mb-3">
                        <x-blood-type-dropdown name="blood_type" :id="$p . '_blood_type'" :label="__('member.blood_type_label')" :value="old('blood_type')" />
                    </div>
                    <div>
                        <x-marital-status-dropdown name="marital_status" :id="$p . '_marital_status'" :label="__('member.marital_status_label')" :value="old('marital_status')" />
                    </div>
                </div>

                {{-- ===== More details (collapsible / optional) ===== --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-3 overflow-hidden">
                    <button type="button" @click="more = !more"
                            class="w-full flex items-center gap-3 p-4 text-left">
                        <span class="w-9 h-9 rounded-xl bg-accent flex items-center justify-center flex-shrink-0">
                            <i class="bi bi-sliders text-primary"></i>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-semibold text-foreground">{{ __('member.more_details') }}</span>
                            <span class="block text-[11px] text-muted-foreground">{{ __('member.more_details_subtitle') }}</span>
                        </span>
                        <i class="bi bi-chevron-down text-muted-foreground transition-transform" :class="{ 'rotate-180': more }"></i>
                    </button>

                    <div x-show="more" x-cloak
                         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         class="px-4 pb-4 pt-1 space-y-5 border-t border-gray-100">

                        {{-- Motto --}}
                        <div>
                            <label for="{{ $p }}_motto" class="tf-label">{{ __('member.personal_motto') }}</label>
                            <textarea id="{{ $p }}_motto" name="motto" rows="2" class="tf-textarea"
                                      placeholder="{{ __('member.motto_placeholder') }}">{{ old('motto') }}</textarea>
                        </div>

                        {{-- Emergency contacts --}}
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="tf-label mb-0"><i class="bi bi-telephone-fill text-red-500 mr-1"></i>{{ __('member.emergency_contacts_label') }}</label>
                                <button type="button" @click="addContact()" class="text-xs text-primary font-medium flex items-center gap-1">
                                    <i class="bi bi-plus-circle"></i> {{ __('member.add') }}
                                </button>
                            </div>
                            <div class="flex flex-col gap-2">
                                <template x-for="(contact, i) in contacts" :key="i">
                                    <div class="p-3 bg-gray-50 rounded-xl space-y-2">
                                        <div class="flex items-center gap-2">
                                            <input type="text" x-model="contact.name" placeholder="{{ __('member.full_name_short') }}" class="tf-input text-sm py-2 flex-1">
                                            <button type="button" @click="contacts.splice(i,1)" class="text-red-400 hover:text-red-600 flex-shrink-0 w-8 h-8 flex items-center justify-center">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                        <select x-model="contact.relationship" class="tf-select text-sm py-2">
                                            <option value="">{{ __('member.relationship') }}</option>
                                            <option value="parent">{{ __('member.rel_parent') }}</option>
                                            <option value="spouse">{{ __('member.rel_spouse') }}</option>
                                            <option value="sibling">{{ __('member.rel_sibling') }}</option>
                                            <option value="child">{{ __('member.rel_child') }}</option>
                                            <option value="friend">{{ __('member.rel_friend') }}</option>
                                            <option value="other">{{ __('member.rel_other') }}</option>
                                        </select>
                                        <div class="flex items-center gap-2">
                                            <input type="text" x-model="contact.phone_code" placeholder="+973" class="tf-input text-sm py-2 w-20 text-center">
                                            <input type="tel" x-model="contact.phone" placeholder="{{ __('member.phone_number') }}" class="tf-input text-sm py-2 flex-1">
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <p x-show="contacts.length === 0" class="text-gray-400 text-xs text-center py-2.5 border border-dashed border-gray-200 rounded-xl">
                                {{ __('member.no_contacts') }}
                            </p>
                        </div>

                        {{-- Health conditions --}}
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="tf-label mb-0"><i class="bi bi-clipboard2-pulse-fill text-amber-500 mr-1"></i>{{ __('member.chronic_conditions_label') }}</label>
                                <button type="button" @click="addCondition()" class="text-xs text-primary font-medium flex items-center gap-1">
                                    <i class="bi bi-plus-circle"></i> {{ __('member.add') }}
                                </button>
                            </div>
                            <div class="flex flex-col gap-2">
                                <template x-for="(cond, i) in conditions" :key="i">
                                    <div class="p-3 bg-amber-50 border border-amber-100 rounded-xl space-y-2">
                                        <div class="flex items-center gap-2">
                                            <input type="text" x-model="cond.condition" placeholder="{{ __('member.condition_placeholder') }}" class="tf-input text-sm py-2 flex-1">
                                            <button type="button" @click="conditions.splice(i,1)" class="text-red-400 hover:text-red-600 flex-shrink-0 w-8 h-8 flex items-center justify-center">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <input type="date" x-model="cond.noted_at" class="tf-input text-sm py-2 flex-1">
                                            <input type="text" x-model="cond.notes" placeholder="{{ __('member.notes') }}" class="tf-input text-sm py-2 flex-1">
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <p x-show="conditions.length === 0" class="text-gray-400 text-xs text-center py-2.5 border border-dashed border-gray-200 rounded-xl">
                                {{ __('member.no_conditions') }}
                            </p>
                        </div>

                        {{-- Identity documents (type + number; files can be uploaded after saving) --}}
                        <div>
                            <div class="flex justify-between items-center mb-2">
                                <label class="tf-label mb-0"><i class="bi bi-file-earmark-person-fill text-primary mr-1"></i>{{ __('member.identity_documents') }}</label>
                                <button type="button" @click="addDoc()" class="text-xs text-primary font-medium flex items-center gap-1">
                                    <i class="bi bi-plus-circle"></i> {{ __('member.add') }}
                                </button>
                            </div>
                            <div class="flex flex-col gap-2">
                                <template x-for="(doc, i) in docs" :key="i">
                                    <div class="p-3 bg-gray-50 rounded-xl space-y-2">
                                        <div class="flex items-center gap-2">
                                            <select x-model="doc.type" class="tf-select text-sm py-2 flex-1">
                                                <option value="">{{ __('member.document_type') }}</option>
                                                <option value="National ID">{{ __('member.doc_national_id') }}</option>
                                                <option value="Passport">{{ __('member.doc_passport') }}</option>
                                                <option value="CPR">{{ __('member.doc_cpr') }}</option>
                                                <option value="Driving Licence">{{ __('member.doc_driving_licence') }}</option>
                                                <option value="Residence Permit">{{ __('member.doc_residence_permit') }}</option>
                                                <option value="Other">{{ __('member.doc_other') }}</option>
                                            </select>
                                            <button type="button" @click="docs.splice(i,1)" class="text-red-400 hover:text-red-600 flex-shrink-0 w-8 h-8 flex items-center justify-center">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                        <input type="text" x-model="doc.number" placeholder="{{ __('member.document_number') }}" class="tf-input text-sm py-2 font-mono">
                                    </div>
                                </template>
                            </div>
                            <p x-show="docs.length === 0" class="text-gray-400 text-xs text-center py-2.5 border border-dashed border-gray-200 rounded-xl">
                                {{ __('member.no_documents_added') }}
                            </p>
                        </div>

                    </div>
                </div>
            </form>
        </div>

        {{-- Sticky footer --}}
        <div class="flex-shrink-0 bg-white border-t border-border px-4 py-3 flex items-center gap-3"
             style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
            <button type="button" @click="close()"
                    class="m-press px-5 py-3 rounded-xl text-sm font-semibold text-foreground bg-muted hover:bg-gray-200 transition-colors">
                {{ __('member.cancel') }}
            </button>
            <button type="button" @click="submit()" :disabled="submitting"
                    class="m-press flex-1 px-5 py-3 rounded-xl text-sm font-bold text-white bg-primary hover:bg-primary/90 transition-colors flex items-center justify-center gap-2 disabled:opacity-60">
                <span x-show="!submitting" class="flex items-center gap-2"><i class="bi bi-person-plus"></i> {{ __('member.add_member') }}</span>
                <span x-show="submitting" class="flex items-center gap-2"><span class="inline-block animate-spin">↻</span> {{ __('member.adding') }}</span>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function memberCreateSheetMobile() {
    return {
        open: false,
        more: false,
        submitting: false,
        contacts: [],
        conditions: [],
        docs: [],

        openSheet() {
            this.open = true;
            document.body.style.overflow = 'hidden';
        },
        close() {
            this.open = false;
            document.body.style.overflow = '';
        },

        addContact()   { this.contacts.push({ name: '', relationship: '', phone_code: '+973', phone: '' }); },
        addCondition() { this.conditions.push({ condition: '', noted_at: new Date().toISOString().split('T')[0], notes: '' }); },
        addDoc()       { this.docs.push({ type: '', number: '' }); },

        // Mark a custom tf-dropdown trigger (hidden input) or a plain input invalid.
        flag(id) {
            const el = document.getElementById(id);
            if (!el) return;
            if (el.type === 'hidden') {
                const wrap = el.closest('[x-data]');
                const trigger = wrap && wrap.querySelector('.tf-dropdown-trigger');
                if (trigger) { trigger.classList.add('border-red-500'); trigger.classList.remove('border-primary/20'); }
            } else {
                el.classList.add('border-red-500');
            }
        },
        unflag(id) {
            const el = document.getElementById(id);
            if (!el) return;
            if (el.type === 'hidden') {
                const wrap = el.closest('[x-data]');
                const trigger = wrap && wrap.querySelector('.tf-dropdown-trigger');
                if (trigger) { trigger.classList.remove('border-red-500'); trigger.classList.add('border-primary/20'); }
            } else {
                el.classList.remove('border-red-500');
            }
        },

        validate() {
            const required = [
                ['{{ $p }}_full_name', @js(__('member.js_full_name_req'))],
                ['{{ $p }}_gender', @js(__('member.js_gender_req'))],
                ['{{ $p }}_birthdate', @js(__('member.js_birthdate_req'))],
                ['{{ $p }}_nationality', @js(__('member.js_nationality_req'))],
                ['{{ $p }}_relationship_type', @js(__('member.js_relationship_req'))],
            ];
            let ok = true, firstMsg = '';
            required.forEach(([id, msg]) => {
                const el = document.getElementById(id);
                const empty = !el || !String(el.value || '').trim();
                if (empty) { this.flag(id); if (ok) firstMsg = msg; ok = false; }
                else { this.unflag(id); }
            });
            if (!ok && window.showToast) window.showToast('error', firstMsg || @js(__('member.js_fill_required')));
            return ok;
        },

        submit() {
            if (this.submitting) return;
            if (!this.validate()) return;

            this.submitting = true;
            const form = document.getElementById('{{ $p }}Form');
            const fd = new FormData(form);
            fd.set('emergency_contacts_json', JSON.stringify(this.contacts));
            fd.set('health_conditions_json',  JSON.stringify(this.conditions));
            fd.set('documents_json',          JSON.stringify(this.docs));

            fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            })
            .then(async (res) => {
                const data = await res.json().catch(() => ({}));
                if (res.ok && data.success) {
                    if (window.showToast) window.showToast('success', data.message || @js(__('member.js_member_added')));
                    setTimeout(() => {
                        this.close();
                        window.location.href = data.redirect || '{{ route('members.index') }}';
                    }, 700);
                } else if (res.status === 422 && data.errors) {
                    const first = Object.values(data.errors)[0];
                    if (window.showToast) window.showToast('error', Array.isArray(first) ? first[0] : String(first));
                    this.submitting = false;
                } else {
                    throw new Error(data.message || @js(__('member.js_could_not_add')));
                }
            })
            .catch((err) => {
                if (window.showToast) window.showToast('error', err.message || @js(__('member.js_went_wrong')));
                this.submitting = false;
            });
        },
    };
}
</script>
@endpush
