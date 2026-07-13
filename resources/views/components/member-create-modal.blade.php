<!-- Member Create Modal -->
<div x-data="memberCreateModal()" x-cloak>
    <!-- Modal Backdrop -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50 z-50"
         @click="close()">
    </div>

    <!-- Modal Content -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-50 overflow-y-auto"
         @click.self="close()">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="w-full max-w-2xl bg-white rounded-2xl shadow-xl" @click.stop>
                <!-- Modal Header -->
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">{{ __('shared.member_create_modal_title') }}</h3>
                            <p class="text-sm text-gray-500 mt-1">{{ __('shared.member_create_modal_subtitle') }}</p>
                        </div>
                        <button @click="close()" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                            <i class="bi bi-x-lg text-gray-500"></i>
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="p-6 max-h-[60vh] overflow-y-auto">
                    <form method="POST" action="{{ route('family.store') }}" id="memberCreateForm">
                        @csrf

                        <!-- Full Name -->
                        <div class="mb-4">
                            <label for="full_name" class="tf-label">
                                {{ __('shared.member_create_modal_full_name') }} <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   class="tf-input @error('full_name') border-red-500 @enderror"
                                   id="full_name"
                                   name="full_name"
                                   value="{{ old('full_name') }}"
                                   required>
                            @error('full_name')
                                <span class="tf-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div class="mb-4">
                            <label for="email" class="tf-label">
                                {{ __('shared.member_create_modal_email') }} <span class="text-gray-400">{{ __('shared.member_create_modal_email_optional') }}</span>
                            </label>
                            <input type="email"
                                   class="tf-input @error('email') border-red-500 @enderror"
                                   id="email"
                                   name="email"
                                   value="{{ old('email') }}">
                            @error('email')
                                <span class="tf-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Mobile Number -->
                        <div class="mb-4">
                            <label for="mobile" class="tf-label">{{ __('shared.member_create_modal_mobile') }}</label>
                            <x-country-code-dropdown
                                name="mobile_code"
                                id="country_code"
                                :value="old('mobile_code', '+973')"
                                :required="false"
                                :error="$errors->first('mobile_code')">
                                <input id="mobile_number" type="tel"
                                       class="w-full px-4 py-3 text-base bg-transparent focus:outline-none @error('mobile') border-red-500 @enderror"
                                       name="mobile"
                                       value="{{ old('mobile') }}"
                                       autocomplete="tel"
                                       placeholder="{{ __('shared.member_create_modal_phone_placeholder') }}">
                            </x-country-code-dropdown>
                            @error('mobile')
                                <span class="tf-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Gender & Birthdate -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <x-gender-dropdown
                                    name="gender"
                                    id="gender"
                                    :value="old('gender')"
                                    :required="true"
                                    :error="$errors->first('gender')" />
                            </div>
                            <div>
                                <x-birthdate-dropdown
                                    name="birthdate"
                                    id="birthdate"
                                    label="{{ __('shared.member_create_modal_birthdate') }}"
                                    :value="old('birthdate')"
                                    :required="true"
                                    :min-age="0"
                                    :max-age="120"
                                    :error="$errors->first('birthdate')" />
                            </div>
                        </div>

                        <!-- Blood Type & Nationality -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <x-blood-type-dropdown
                                    name="blood_type"
                                    id="blood_type"
                                    label="{{ __('shared.member_create_modal_blood_type') }}"
                                    :value="old('blood_type')"
                                    :error="$errors->first('blood_type')" />
                            </div>
                            <div>
                                <x-country-dropdown
                                    name="nationality"
                                    id="nationality"
                                    label="{{ __('shared.member_create_modal_nationality') }}"
                                    :value="old('nationality')"
                                    :required="true"
                                    :error="$errors->first('nationality')" />
                            </div>
                        </div>

                        <!-- Social Media Links -->
                        <div class="mb-4">
                            @php
                                $existingLinks = old('social_links', []);
                                if (!is_array($existingLinks)) $existingLinks = [];
                                $createFormLinks = [];
                                foreach ($existingLinks as $platform => $url) {
                                    $createFormLinks[] = ['platform' => $platform, 'url' => $url];
                                }
                            @endphp
                            <x-social-links-editor :links="$createFormLinks" containerId="memberCreateSocialLinksContainer" />
                        </div>

                        <!-- Motto -->
                        <div class="mb-4">
                            <label for="motto" class="tf-label">{{ __('shared.member_create_modal_motto') }}</label>
                            <textarea class="tf-textarea @error('motto') border-red-500 @enderror"
                                      id="motto"
                                      name="motto"
                                      rows="3"
                                      placeholder="{{ __('shared.member_create_modal_motto_placeholder') }}">{{ old('motto') }}</textarea>
                            <p class="text-xs text-gray-400 mt-1">{{ __('shared.member_create_modal_motto_hint') }}</p>
                            @error('motto')
                                <span class="tf-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Emergency Contacts -->
                        <div class="mb-4">
                            <input type="hidden" name="emergency_contacts_json" id="createEmergencyContactsJson">
                            <div class="flex justify-between items-center mb-2">
                                <label class="tf-label mb-0">
                                    <i class="bi bi-telephone-fill text-red-500 me-1"></i>{{ __('shared.member_create_modal_emergency_contacts') }}
                                </label>
                                <button type="button"
                                        @click="contacts.push({name:'',relationship:'',phone_code:'+973',phone:''})"
                                        class="text-xs text-primary hover:underline flex items-center gap-1">
                                    <i class="bi bi-plus-circle"></i> {{ __('shared.member_create_modal_add_contact') }}
                                </button>
                            </div>
                            <div class="flex flex-col gap-2">
                                <template x-for="(contact, i) in contacts" :key="i">
                                    <div class="grid grid-cols-12 gap-2 p-3 bg-gray-50 rounded-lg items-center">
                                        <div class="col-span-3">
                                            <input type="text" x-model="contact.name" placeholder="{{ __('shared.member_create_modal_full_name_placeholder') }}" class="tf-input text-sm py-2">
                                        </div>
                                        <div class="col-span-3">
                                            <x-select-menu model="contact.relationship"
                                                placeholder="{{ __('shared.member_create_modal_relationship_placeholder') }}"
                                                :options="[
                                                    ['value' => 'parent', 'label' => __('shared.member_create_modal_rel_parent')],
                                                    ['value' => 'spouse', 'label' => __('shared.member_create_modal_rel_spouse')],
                                                    ['value' => 'sibling', 'label' => __('shared.member_create_modal_rel_sibling')],
                                                    ['value' => 'child', 'label' => __('shared.member_create_modal_rel_child')],
                                                    ['value' => 'friend', 'label' => __('shared.member_create_modal_rel_friend')],
                                                    ['value' => 'other', 'label' => __('shared.member_create_modal_other')],
                                                ]" />
                                        </div>
                                        <div class="col-span-5">
                                            <div class="tf-input-group"
                                                 x-data="{
                                                    open: false, search: '',
                                                    get flag() { const c = (window._phoneCodes||[]).find(x => x.c === contact.phone_code); return c ? c.f : 'bh'; },
                                                    get list() { const a = window._phoneCodes||[]; if (!this.search) return a; const t = this.search.toLowerCase(); return a.filter(c => c.n.toLowerCase().includes(t)||c.c.includes(t)); },
                                                    pick(c) { contact.phone_code = c.c; this.open = false; this.search = ''; }
                                                 }">
                                                <div class="relative flex-shrink-0">
                                                    <button type="button" @click="open = !open" @click.outside="open = false"
                                                            class="h-full px-2 py-1 flex items-center gap-1 border-e border-primary/20 bg-transparent hover:bg-gray-50 transition-colors cursor-pointer rounded-s-xl whitespace-nowrap">
                                                        <span :class="'fi fi-' + flag"></span>
                                                        <span x-text="contact.phone_code || '+973'" class="text-xs font-medium text-gray-700"></span>
                                                        <i class="bi bi-chevron-down text-xs" :class="{'rotate-180': open}"></i>
                                                    </button>
                                                    <div x-show="open" x-cloak
                                                         class="absolute start-0 z-50 mt-1 w-56 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden"
                                                         style="top:100%">
                                                        <div class="p-2 border-b border-gray-100">
                                                            <input type="text" x-model="search" @click.stop placeholder="{{ __('shared.member_create_modal_search_placeholder') }}"
                                                                   class="w-full px-2 py-1 text-xs border border-gray-200 rounded focus:outline-none focus:border-primary">
                                                        </div>
                                                        <div class="max-h-44 overflow-y-auto">
                                                            <template x-for="c in list" :key="c.f + c.c">
                                                                <div @click="pick(c)"
                                                                     class="flex items-center gap-2 px-3 py-1.5 hover:bg-gray-50 cursor-pointer"
                                                                     :class="contact.phone_code === c.c ? 'bg-primary/5 font-semibold' : ''">
                                                                    <span :class="'fi fi-' + c.f"></span>
                                                                    <span class="text-xs" x-text="c.n + ' (' + c.c + ')'"></span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-1">
                                                    <input type="tel" x-model="contact.phone" placeholder="{{ __('shared.member_create_modal_phone_placeholder') }}"
                                                           class="w-full px-2 py-1 text-sm bg-transparent focus:outline-none">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-span-1 flex justify-center">
                                            <button type="button" @click="contacts.splice(i, 1)" class="text-red-400 hover:text-red-600 transition-colors">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <p x-show="contacts.length === 0" class="text-gray-400 text-xs text-center py-2 border border-dashed border-gray-200 rounded-lg">
                                {{ __('shared.member_create_modal_contacts_hint') }}
                            </p>
                        </div>

                        <!-- Health Conditions -->
                        <div class="mb-4">
                            <input type="hidden" name="health_conditions_json" id="createHealthConditionsJson">
                            <div class="flex justify-between items-center mb-2">
                                <label class="tf-label mb-0">
                                    <i class="bi bi-clipboard2-pulse-fill text-amber-500 me-1"></i>{{ __('shared.member_create_modal_health_conditions') }}
                                </label>
                                <button type="button"
                                        @click="conditions.push({condition:'', noted_at: new Date().toISOString().split('T')[0], notes:''})"
                                        class="text-xs text-primary hover:underline flex items-center gap-1">
                                    <i class="bi bi-plus-circle"></i> {{ __('shared.member_create_modal_add_condition') }}
                                </button>
                            </div>
                            <div class="flex flex-col gap-2">
                                <template x-for="(cond, i) in conditions" :key="i">
                                    <div class="p-3 bg-amber-50 border border-amber-100 rounded-lg">
                                        <div class="grid grid-cols-12 gap-2 items-start">
                                            <div class="col-span-5">
                                                <input type="text" x-model="cond.condition" placeholder="{{ __('shared.member_create_modal_condition_placeholder') }}" class="tf-input text-sm py-2">
                                            </div>
                                            <div class="col-span-3">
                                                <input type="date" x-model="cond.noted_at" class="tf-input text-sm py-2">
                                            </div>
                                            <div class="col-span-3">
                                                <input type="text" x-model="cond.notes" placeholder="{{ __('shared.member_create_modal_notes_placeholder') }}" class="tf-input text-sm py-2">
                                            </div>
                                            <div class="col-span-1 flex justify-center pt-1">
                                                <button type="button" @click="conditions.splice(i, 1)" class="text-red-400 hover:text-red-600 transition-colors">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <p x-show="conditions.length === 0" class="text-gray-400 text-xs text-center py-2 border border-dashed border-gray-200 rounded-lg">
                                {{ __('shared.member_create_modal_conditions_hint') }}
                            </p>
                        </div>

                        <!-- Identity Documents (type + number on create; upload file after saving) -->
                        <div class="mb-4">
                            <input type="hidden" name="documents_json" id="createDocumentsJson">
                            <div class="flex justify-between items-center mb-2">
                                <label class="tf-label mb-0">
                                    <i class="bi bi-file-earmark-person-fill text-primary me-1"></i>{{ __('shared.member_create_modal_identity_documents') }}
                                </label>
                                <button type="button"
                                        @click="docs.push({type:'',number:''})"
                                        class="text-xs text-primary hover:underline flex items-center gap-1">
                                    <i class="bi bi-plus-circle"></i> {{ __('shared.member_create_modal_add_document') }}
                                </button>
                            </div>
                            <div class="flex flex-col gap-2">
                                <template x-for="(doc, i) in docs" :key="i">
                                    <div class="grid grid-cols-12 gap-2 p-3 bg-gray-50 rounded-lg items-center">
                                        <div class="col-span-5">
                                            <x-select-menu model="doc.type"
                                                placeholder="{{ __('shared.member_create_modal_document_type_placeholder') }}"
                                                :options="[
                                                    ['value' => 'National ID', 'label' => __('shared.member_create_modal_doc_national_id')],
                                                    ['value' => 'Passport', 'label' => __('shared.member_create_modal_doc_passport')],
                                                    ['value' => 'CPR', 'label' => __('shared.member_create_modal_doc_cpr')],
                                                    ['value' => 'Driving Licence', 'label' => __('shared.member_create_modal_doc_driving_licence')],
                                                    ['value' => 'Residence Permit', 'label' => __('shared.member_create_modal_doc_residence_permit')],
                                                    ['value' => 'Other', 'label' => __('shared.member_create_modal_other')],
                                                ]" />
                                        </div>
                                        <div class="col-span-6">
                                            <input type="text" x-model="doc.number" placeholder="{{ __('shared.member_create_modal_document_number_placeholder') }}" class="tf-input text-sm py-2 font-mono">
                                        </div>
                                        <div class="col-span-1 flex justify-center">
                                            <button type="button" @click="docs.splice(i, 1)" class="text-red-400 hover:text-red-600 transition-colors">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            <p x-show="docs.length === 0" class="text-gray-400 text-xs text-center py-2 border border-dashed border-gray-200 rounded-lg">
                                {{ __('shared.member_create_modal_documents_hint') }}
                            </p>
                        </div>

                        <!-- Relationship -->
                        <x-relationship-dropdown
                            name="relationship_type"
                            id="relationship_type"
                            label="{{ __('shared.member_create_modal_relationship') }}"
                            :value="old('relationship_type')"
                            :required="true"
                            :error="$errors->first('relationship_type')" />

                        <!-- Is Billing Contact -->
                        <div class="mb-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox"
                                       class="w-4 h-4 rounded border-primary/30 text-primary focus:ring-primary/25"
                                       id="is_billing_contact"
                                       name="is_billing_contact"
                                       value="1"
                                       {{ old('is_billing_contact') ? 'checked' : '' }}>
                                <span class="text-sm text-gray-700">{{ __('shared.member_create_modal_is_billing_contact') }}</span>
                            </label>
                        </div>
                    </form>
                </div>

                <!-- Modal Footer -->
                <div class="p-6 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button"
                            @click="close()"
                            class="px-6 py-2 text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">
                        {{ __('shared.cancel') }}
                    </button>
                    <button type="button"
                            @click="submitWithData()"
                            class="px-6 py-2 text-white bg-primary rounded-xl hover:bg-primary/90 transition-colors">
                        {{ __('shared.member_create_modal_title') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function memberCreateModal() {
    return {
        open: false,
        contacts: [],
        conditions: [],
        docs: [],

        init() {
            window.openMemberCreateModal = () => this.open = true;
            @if ($errors->any())
                this.open = true;
            @endif
        },

        close() {
            this.open = false;
        },

        submitWithData() {
            // Inject JSON into hidden inputs right before submit — no binding tricks
            document.getElementById('createEmergencyContactsJson').value = JSON.stringify(this.contacts);
            document.getElementById('createHealthConditionsJson').value  = JSON.stringify(this.conditions);
            document.getElementById('createDocumentsJson').value         = JSON.stringify(this.docs);
            document.getElementById('memberCreateForm').submit();
        },
    }
}
</script>
