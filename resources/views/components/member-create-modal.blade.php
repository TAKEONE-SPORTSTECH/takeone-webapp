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
                            <h3 class="text-xl font-bold text-gray-900">Add Family Member</h3>
                            <p class="text-sm text-gray-500 mt-1">Fill in the details to add a new family member</p>
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
                                Full Name <span class="text-red-500">*</span>
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
                                Email Address <span class="text-gray-400">(Optional for children)</span>
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
                            <label for="mobile" class="tf-label">Mobile Number</label>
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
                                       placeholder="Phone number">
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
                                    label="Birthdate"
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
                                <label for="blood_type" class="tf-label">Blood Type</label>
                                <select class="tf-select @error('blood_type') border-red-500 @enderror"
                                        id="blood_type"
                                        name="blood_type">
                                    <option value="">Select Blood Type</option>
                                    <option value="A+" {{ old('blood_type') == 'A+' ? 'selected' : '' }}>A+</option>
                                    <option value="A-" {{ old('blood_type') == 'A-' ? 'selected' : '' }}>A-</option>
                                    <option value="B+" {{ old('blood_type') == 'B+' ? 'selected' : '' }}>B+</option>
                                    <option value="B-" {{ old('blood_type') == 'B-' ? 'selected' : '' }}>B-</option>
                                    <option value="AB+" {{ old('blood_type') == 'AB+' ? 'selected' : '' }}>AB+</option>
                                    <option value="AB-" {{ old('blood_type') == 'AB-' ? 'selected' : '' }}>AB-</option>
                                    <option value="O+" {{ old('blood_type') == 'O+' ? 'selected' : '' }}>O+</option>
                                    <option value="O-" {{ old('blood_type') == 'O-' ? 'selected' : '' }}>O-</option>
                                    <option value="Unknown" {{ old('blood_type') == 'Unknown' ? 'selected' : '' }}>Unknown</option>
                                </select>
                                @error('blood_type')
                                    <span class="tf-error">{{ $message }}</span>
                                @enderror
                            </div>
                            <div>
                                <x-country-dropdown
                                    name="nationality"
                                    id="nationality"
                                    label="Nationality"
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
                            <label for="motto" class="tf-label">Personal Motto</label>
                            <textarea class="tf-textarea @error('motto') border-red-500 @enderror"
                                      id="motto"
                                      name="motto"
                                      rows="3"
                                      placeholder="Enter personal motto or quote...">{{ old('motto') }}</textarea>
                            <p class="text-xs text-gray-400 mt-1">Share a personal motto or quote that inspires them.</p>
                            @error('motto')
                                <span class="tf-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Relationship -->
                        <div class="mb-4">
                            <label for="relationship_type" class="tf-label">
                                Relationship <span class="text-red-500">*</span>
                            </label>
                            <select class="tf-select @error('relationship_type') border-red-500 @enderror"
                                    id="relationship_type"
                                    name="relationship_type"
                                    required>
                                <option value="">Select Relationship</option>
                                <option value="son" {{ old('relationship_type') == 'son' ? 'selected' : '' }}>Son</option>
                                <option value="daughter" {{ old('relationship_type') == 'daughter' ? 'selected' : '' }}>Daughter</option>
                                <option value="spouse" {{ old('relationship_type') == 'spouse' ? 'selected' : '' }}>Wife</option>
                                <option value="sponsor" {{ old('relationship_type') == 'sponsor' ? 'selected' : '' }}>Sponsor</option>
                                <option value="other" {{ old('relationship_type') == 'other' ? 'selected' : '' }}>Other</option>
                            </select>
                            @error('relationship_type')
                                <span class="tf-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Is Billing Contact -->
                        <div class="mb-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox"
                                       class="w-4 h-4 rounded border-primary/30 text-primary focus:ring-primary/25"
                                       id="is_billing_contact"
                                       name="is_billing_contact"
                                       value="1"
                                       {{ old('is_billing_contact') ? 'checked' : '' }}>
                                <span class="text-sm text-gray-700">Is Billing Contact</span>
                            </label>
                        </div>
                    </form>
                </div>

                <!-- Modal Footer -->
                <div class="p-6 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button"
                            @click="close()"
                            class="px-6 py-2 text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            form="memberCreateForm"
                            class="px-6 py-2 text-white bg-primary rounded-xl hover:bg-primary/90 transition-colors">
                        Add Family Member
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

        init() {
            // Global function to open modal
            window.openMemberCreateModal = () => this.open = true;

            // Auto-open if validation errors
            @if ($errors->any())
                this.open = true;
            @endif
        },

        close() {
            this.open = false;
        },

    }
}
</script>
