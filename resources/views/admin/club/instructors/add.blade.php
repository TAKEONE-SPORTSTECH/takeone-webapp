<!-- Add Instructor Modal -->
<div x-show="showAddInstructorModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50" @click="showAddInstructorModal = false; resetInstructorWizard()"></div>

    <!-- Modal Content -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-2xl relative rounded-xl overflow-hidden"
             @click.stop>
            <!-- Header -->
            <div class="modal-header border-b border-gray-200 px-6 py-4">
                <h5 class="modal-title text-lg font-semibold">{{ __('admin.club_instructors_add_title') }}</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" @click="showAddInstructorModal = false; resetInstructorForm()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Progress Steps -->
            <div id="wizardProgress" class="px-6 py-4 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <!-- Steps will be rendered by JS -->
                </div>
            </div>

            <!-- Body -->
            <div class="modal-body px-6 py-6">
                <form id="addInstructorForm" action="{{ route('admin.club.instructors.store', $club->slug) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="creation_type" id="creationType" value="new">
                    <input type="hidden" name="selected_member_id" id="selectedMemberId" value="">

                    <!-- Step 1: Choose Creation Type -->
                    <div class="wizard-step" data-step="1">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-plus-lg text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_add_how_to_add') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_add_how_to_add_sub') }}</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label class="flex items-center gap-4 p-4 border-2 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors creation-type-option" data-type="new">
                                <input type="radio" name="creation_type_radio" value="new" checked class="w-5 h-5 text-primary">
                                <div class="flex-1">
                                    <div class="font-semibold">{{ __('admin.club_instructors_add_create_new') }}</div>
                                    <div class="text-sm text-gray-500">{{ __('admin.club_instructors_add_create_new_desc') }}</div>
                                </div>
                            </label>

                            <label class="flex items-center gap-4 p-4 border-2 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors creation-type-option" data-type="existing">
                                <input type="radio" name="creation_type_radio" value="existing" class="w-5 h-5 text-primary">
                                <div class="flex-1">
                                    <div class="font-semibold">{{ __('admin.club_instructors_add_select_existing') }}</div>
                                    <div class="text-sm text-gray-500">{{ __('admin.club_instructors_add_select_existing_desc') }}</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Step 2 NEW: Create Platform Member -->
                    <div class="wizard-step hidden" data-step="2" data-type="new">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-person-plus text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_add_create_platform_member') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_add_create_platform_member_sub') }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">{{ __('admin.club_instructors_add_email') }} <span class="text-red-500">*</span></label>
                                    <input type="email" name="email" placeholder="{{ __('admin.club_instructors_add_email_placeholder') }}" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">{{ __('admin.club_instructors_add_password') }} <span class="text-red-500">*</span></label>
                                    <input type="password" name="password" placeholder="{{ __('admin.club_instructors_add_password_placeholder') }}" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="tf-label">{{ __('admin.club_instructors_add_phone_number') }} <span class="text-red-500">*</span></label>
                                <x-country-code-dropdown
                                    name="country_code"
                                    id="instructor_country_code"
                                    value="+973"
                                    :required="true"
                                >
                                    <input type="tel" name="phone" placeholder="{{ __('admin.club_instructors_add_phone_placeholder') }}" required
                                           class="w-full px-4 py-3 text-base bg-transparent focus:outline-none">
                                </x-country-code-dropdown>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <x-gender-dropdown
                                    name="gender"
                                    id="instructor_gender"
                                    label="{{ __('admin.club_instructors_add_gender') }}"
                                    :required="true"
                                />
                                <x-birthdate-dropdown
                                    name="birthdate"
                                    id="instructor_birthdate"
                                    label="{{ __('admin.club_instructors_add_date_of_birth') }}"
                                    :required="true"
                                    :minAge="16"
                                    :maxAge="80"
                                />
                            </div>

                            <x-country-dropdown
                                name="nationality"
                                id="instructor_nationality"
                                label="{{ __('admin.club_instructors_add_nationality') }}"
                                :required="true"
                            />
                        </div>
                    </div>

                    <!-- Step 2 EXISTING: Search Member -->
                    <div class="wizard-step hidden" data-step="2" data-type="existing">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-search text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_add_find_club_member') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_add_find_club_member_sub') }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="relative">
                                <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <input type="text" id="memberSearchInput" placeholder="{{ __('admin.club_instructors_add_search_placeholder') }}" class="w-full ps-10 pe-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>

                            <div id="searchResults" class="border rounded-lg divide-y max-h-64 overflow-y-auto hidden">
                                <!-- Search results will be inserted here -->
                            </div>

                            <div id="noResultsMsg" class="text-center py-8 text-gray-500 hidden">
                                <i class="bi bi-person text-4xl mb-2 opacity-50"></i>
                                <p>{{ __('admin.club_instructors_add_no_members_found') }}</p>
                            </div>

                            <div id="selectedMemberCard" class="p-4 bg-primary/10 rounded-lg border-2 border-primary hidden">
                                <p class="text-sm font-semibold mb-1">{{ __('admin.club_instructors_add_selected_member') }}</p>
                                <p id="selectedMemberName" class="text-lg font-bold"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 NEW: Name & Photo -->
                    <div class="wizard-step hidden" data-step="3" data-type="new">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-person text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_add_get_to_know') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_add_get_to_know_sub') }}</p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_full_name_q') }} <span class="text-red-500">*</span></label>
                                <input type="text" name="name" placeholder="{{ __('admin.club_instructors_add_full_name_placeholder') }}" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_share_photo') }}</label>
                                <p class="text-sm text-gray-500 mb-3">{{ __('admin.club_instructors_add_share_photo_sub') }}</p>

                                <div class="flex items-center gap-6">
                                    <x-takeone-cropper
                                        id="instructorPhotoCropper"
                                        :width="200"
                                        :height="200"
                                        shape="circle"
                                        mode="form"
                                        inputName="photo"
                                        folder="instructors"
                                        :filename="'instructor_' . time()"
                                        :previewWidth="128"
                                        :previewHeight="128"
                                        buttonText="{{ __('admin.club_instructors_add_upload_photo') }}"
                                        buttonClass="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors flex items-center justify-center gap-2"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 EXISTING / Step 4 NEW: Role & Experience -->
                    <div class="wizard-step hidden" data-step="3" data-type="existing">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-briefcase text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_add_what_you_do') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_add_what_you_do_sub') }}</p>
                            </div>
                        </div>

                        <div class="space-y-4" x-data="{ lang: 'en' }">
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_role_q') }} <span class="text-red-500">*</span></label>
                                <x-lang-toggle class="mb-4" />
                                <input type="text" name="specialty_existing" placeholder="{{ __('admin.club_instructors_add_role_placeholder') }}" x-show="lang==='en'" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <input type="text" name="translations[role][ar]" dir="rtl" x-show="lang==='ar'" x-cloak placeholder="المسمى بالعربية" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" value="{{ old('translations.role.ar') }}">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_experience_q') }} <span class="text-red-500">*</span></label>
                                <input type="number" name="experience_existing" min="0" placeholder="{{ __('admin.club_instructors_add_experience_placeholder') }}" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <div class="wizard-step hidden" data-step="4" data-type="new">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-briefcase text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_add_what_you_do') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_add_what_you_do_sub') }}</p>
                            </div>
                        </div>

                        <div class="space-y-4" x-data="{ lang: 'en' }">
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_role_q') }} <span class="text-red-500">*</span></label>
                                <x-lang-toggle class="mb-4" />
                                <input type="text" name="specialty" placeholder="{{ __('admin.club_instructors_add_role_placeholder') }}" x-show="lang==='en'" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <input type="text" name="translations[role][ar]" dir="rtl" x-show="lang==='ar'" x-cloak placeholder="المسمى بالعربية" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" value="{{ old('translations.role.ar') }}">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_experience_q') }} <span class="text-red-500">*</span></label>
                                <input type="number" name="experience" min="0" placeholder="{{ __('admin.club_instructors_add_experience_placeholder') }}" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Step 4 EXISTING / Step 5 NEW: Skills -->
                    <div class="wizard-step hidden" data-step="4" data-type="existing">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-award text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_add_good_at') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_add_good_at_sub') }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_skills_label') }}</label>
                                <p class="text-sm text-gray-500 mt-1 mb-3">{{ __('admin.club_instructors_add_skills_hint') }}</p>

                                <div id="skillsContainerExisting" class="flex flex-wrap gap-2 mb-4 p-4 bg-gray-50 rounded-lg min-h-[60px]">
                                    <!-- Skills tags will be added here -->
                                    <span class="text-sm text-gray-400" id="noSkillsMsgExisting">{{ __('admin.club_instructors_add_no_skills_yet') }}</span>
                                </div>

                                <div class="flex gap-2">
                                    <input type="text" id="newSkillInputExisting" placeholder="{{ __('admin.club_instructors_add_skill_placeholder') }}" class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <button type="button" id="addSkillBtnExisting" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="skills_existing" id="skillsHiddenExisting" value="[]">
                            </div>
                        </div>
                    </div>

                    <div class="wizard-step hidden" data-step="5" data-type="new">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-award text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_add_good_at') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_add_good_at_sub') }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_skills_label') }}</label>
                                <p class="text-sm text-gray-500 mt-1 mb-3">{{ __('admin.club_instructors_add_skills_hint') }}</p>

                                <div id="skillsContainer" class="flex flex-wrap gap-2 mb-4 p-4 bg-gray-50 rounded-lg min-h-[60px]">
                                    <!-- Skills tags will be added here -->
                                    <span class="text-sm text-gray-400" id="noSkillsMsg">{{ __('admin.club_instructors_add_no_skills_yet') }}</span>
                                </div>

                                <div class="flex gap-2">
                                    <input type="text" id="newSkillInput" placeholder="{{ __('admin.club_instructors_add_skill_placeholder') }}" class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <button type="button" id="addSkillBtn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="skills" id="skillsHidden" value="[]">
                            </div>
                        </div>
                    </div>

                    <!-- Step 5 EXISTING / Step 6 NEW: Bio & Achievements -->
                    <div class="wizard-step hidden" data-step="5" data-type="existing">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-file-text text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_add_tell_more') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_add_tell_more_sub') }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_your_story') }}</label>
                                <p class="text-sm text-gray-500 mb-2">{{ __('admin.club_instructors_add_your_story_sub') }}</p>
                                <textarea name="bio_existing" rows="3" placeholder="{{ __('admin.club_instructors_add_bio_placeholder') }}" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_your_achievements') }}</label>
                                <p class="text-sm text-gray-500 mb-2">{{ __('admin.club_instructors_add_your_achievements_sub') }}</p>
                                <textarea name="achievements_existing" rows="3" placeholder="{{ __('admin.club_instructors_add_achievements_placeholder') }}" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_your_certifications') }}</label>
                                <p class="text-sm text-gray-500 mb-2">{{ __('admin.club_instructors_add_your_certifications_sub') }}</p>
                                <textarea name="certifications_existing" rows="3" placeholder="{{ __('admin.club_instructors_add_certifications_placeholder') }}" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-step hidden" data-step="6" data-type="new">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-file-text text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_add_tell_more') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_add_tell_more_sub') }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_your_story') }}</label>
                                <p class="text-sm text-gray-500 mb-2">{{ __('admin.club_instructors_add_your_story_sub') }}</p>
                                <textarea name="bio" rows="3" placeholder="{{ __('admin.club_instructors_add_bio_placeholder') }}" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_your_achievements') }}</label>
                                <p class="text-sm text-gray-500 mb-2">{{ __('admin.club_instructors_add_your_achievements_sub') }}</p>
                                <textarea name="achievements" rows="3" placeholder="{{ __('admin.club_instructors_add_achievements_placeholder') }}" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_your_certifications') }}</label>
                                <p class="text-sm text-gray-500 mb-2">{{ __('admin.club_instructors_add_your_certifications_sub') }}</p>
                                <textarea name="certifications" rows="3" placeholder="{{ __('admin.club_instructors_add_certifications_placeholder') }}" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>
                        </div>
                    </div>

                    {{-- Persistent section (not part of the step wizard): compensation & classes --}}
                    <div class="mt-6 pt-5 border-t border-gray-100 space-y-5">
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-2"><i class="bi bi-cash-coin me-1 text-primary"></i>{{ __('admin.club_instructors_add_compensation') }}</label>
                            <div class="flex flex-wrap items-end gap-3">
                                <div>
                                    <select name="compensation_type" id="addCompType" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                            onchange="document.getElementById('addWageFields').style.display = this.value === 'paid' ? 'flex' : 'none'">
                                        <option value="volunteer">{{ __('admin.club_instructors_add_volunteer') }}</option>
                                        <option value="paid">{{ __('admin.club_instructors_add_paid') }}</option>
                                    </select>
                                </div>
                                <div id="addWageFields" class="flex items-end gap-3" style="display:none;">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">{{ __('admin.club_instructors_add_amount') }}</label>
                                        <input type="number" min="0" step="0.01" name="wage_amount" placeholder="0.00" class="w-32 px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">{{ __('admin.club_instructors_add_period') }}</label>
                                        <x-select-menu name="wage_period" :value="'monthly'" :options="[
                                            ['value' => 'monthly', 'label' => __('admin.club_instructors_add_per_month')],
                                            ['value' => 'session', 'label' => __('admin.club_instructors_add_per_session')],
                                            ['value' => 'hourly',  'label' => __('admin.club_instructors_add_per_hour')],
                                        ]" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-2"><i class="bi bi-calendar2-week me-1 text-primary"></i>{{ __('admin.club_instructors_add_package_classes') }}</label>
                            @if($packageSlots->isEmpty())
                                <p class="text-sm text-gray-400">{{ __('admin.club_instructors_add_no_packages') }}</p>
                            @else
                                <div class="space-y-3">
                                    @foreach($packageSlots->groupBy('package_id') as $slots)
                                        <div class="border border-gray-100 rounded-lg overflow-hidden">
                                            <p class="px-3 py-2 bg-gray-50 text-xs font-bold text-gray-700"><i class="bi bi-box text-primary me-1"></i>{{ $slots->first()->package_name }}</p>
                                            <div class="divide-y divide-gray-50">
                                                @foreach($slots as $slot)
                                                    <label class="flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-gray-50">
                                                        <input type="checkbox" name="package_slots[]" value="{{ $slot->id }}" class="rounded text-primary focus:ring-primary">
                                                        <span class="min-w-0">
                                                            <span class="block text-sm font-medium text-gray-800 truncate">{{ $slot->activity_name }}</span>
                                                            <span class="block text-xs text-gray-400 truncate">{{ $slot->schedule_label ?: __('admin.club_instructors_add_no_schedule_set') }}@if($slot->instructor_name) · {{ $slot->instructor_name }}@endif</span>
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-t border-gray-200 px-6 py-4 flex justify-between">
                <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors" @click="showAddInstructorModal = false; resetInstructorForm()">
                    {{ __('shared.cancel') }}
                </button>

                <div class="flex gap-2">
                    <button type="button" id="prevStepBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors hidden flex items-center gap-1">
                        <i class="bi bi-chevron-left"></i> {{ __('admin.club_instructors_add_previous') }}
                    </button>

                    <button type="button" id="nextStepBtn" class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors flex items-center gap-1">
                        {{ __('admin.club_instructors_add_next') }} <i class="bi bi-chevron-right"></i>
                    </button>

                    <button type="button" id="submitBtn" class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors hidden flex items-center gap-2">
                        <i class="bi bi-check-lg"></i> {{ __('admin.club_instructors_add_create_instructor') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
// Global reset function for Alpine.js
window.resetInstructorForm = function() {
    if (window.instructorWizardReset) {
        window.instructorWizardReset();
    }
};

document.addEventListener('DOMContentLoaded', function() {
    let currentStep = 1;
    let creationType = 'new';
    let totalSteps = 6; // 6 for new, 5 for existing
    let skills = [];
    let skillsExisting = [];
    let selectedMemberId = null;

    const progressContainer = document.getElementById('wizardProgress');
    const prevBtn = document.getElementById('prevStepBtn');
    const nextBtn = document.getElementById('nextStepBtn');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('addInstructorForm');

    // Render progress steps
    function renderProgress() {
        let html = '<div class="flex items-center justify-between w-full">';
        for (let i = 1; i <= totalSteps; i++) {
            const isCompleted = currentStep > i;
            const isCurrent = currentStep === i;

            html += `
                <div class="flex items-center ${i < totalSteps ? 'flex-1' : ''}">
                    <div class="flex items-center justify-center w-10 h-10 rounded-full border-2 transition-all ${
                        isCompleted ? 'bg-primary border-primary text-white' :
                        isCurrent ? 'border-primary text-primary font-bold' :
                        'border-gray-300 text-gray-400'
                    }">
                        ${isCompleted ? '<i class="bi bi-check"></i>' : i}
                    </div>
                    ${i < totalSteps ? `<div class="flex-1 h-0.5 mx-2 ${isCompleted ? 'bg-primary' : 'bg-gray-200'}"></div>` : ''}
                </div>
            `;
        }
        html += '</div>';
        progressContainer.innerHTML = html;
    }

    // Show current step
    function showStep(step) {
        document.querySelectorAll('.wizard-step').forEach(el => el.classList.add('hidden'));

        // Find the correct step based on step number and creation type
        let targetStep;
        if (step === 1) {
            targetStep = document.querySelector(`.wizard-step[data-step="1"]`);
        } else {
            targetStep = document.querySelector(`.wizard-step[data-step="${step}"][data-type="${creationType}"]`);
        }

        if (targetStep) {
            targetStep.classList.remove('hidden');
        }

        // Update buttons
        prevBtn.classList.toggle('hidden', step === 1);
        nextBtn.classList.toggle('hidden', step === totalSteps);
        submitBtn.classList.toggle('hidden', step !== totalSteps);

        renderProgress();
    }

    // Handle creation type selection
    document.querySelectorAll('.creation-type-option').forEach(option => {
        option.addEventListener('click', function() {
            creationType = this.dataset.type;
            document.getElementById('creationType').value = creationType;
            totalSteps = creationType === 'new' ? 6 : 5;

            document.querySelectorAll('.creation-type-option').forEach(opt => {
                opt.classList.remove('border-primary', 'bg-primary/5');
                opt.classList.add('border-gray-200');
            });
            this.classList.remove('border-gray-200');
            this.classList.add('border-primary', 'bg-primary/5');
        });
    });

    // Next button
    nextBtn.addEventListener('click', function() {
        if (currentStep < totalSteps) {
            currentStep++;
            showStep(currentStep);
        }
    });

    // Previous button
    prevBtn.addEventListener('click', function() {
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
        }
    });

    // Submit button
    submitBtn.addEventListener('click', function() {
        form.submit();
    });

    // Skills management for NEW
    const skillsContainer = document.getElementById('skillsContainer');
    const newSkillInput = document.getElementById('newSkillInput');
    const addSkillBtn = document.getElementById('addSkillBtn');
    const skillsHidden = document.getElementById('skillsHidden');
    const noSkillsMsg = document.getElementById('noSkillsMsg');

    function renderSkills() {
        skillsContainer.innerHTML = '';
        if (skills.length === 0) {
            skillsContainer.innerHTML = '<span class="text-sm text-gray-400">{{ __("admin.club_instructors_add_no_skills_yet") }}</span>';
        } else {
            skills.forEach((skill, index) => {
                const tag = document.createElement('span');
                tag.className = 'inline-flex items-center gap-1 px-3 py-1.5 bg-primary/10 text-primary rounded-full text-sm font-medium';
                tag.innerHTML = `${skill} <button type="button" class="hover:text-red-500" data-index="${index}"><i class="bi bi-x"></i></button>`;
                tag.querySelector('button').addEventListener('click', function() {
                    skills.splice(index, 1);
                    renderSkills();
                });
                skillsContainer.appendChild(tag);
            });
        }
        skillsHidden.value = JSON.stringify(skills);
    }

    addSkillBtn?.addEventListener('click', function() {
        const skill = newSkillInput.value.trim();
        if (skill && !skills.includes(skill)) {
            skills.push(skill);
            newSkillInput.value = '';
            renderSkills();
        }
    });

    newSkillInput?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addSkillBtn.click();
        }
    });

    // Skills management for EXISTING
    const skillsContainerExisting = document.getElementById('skillsContainerExisting');
    const newSkillInputExisting = document.getElementById('newSkillInputExisting');
    const addSkillBtnExisting = document.getElementById('addSkillBtnExisting');
    const skillsHiddenExisting = document.getElementById('skillsHiddenExisting');

    function renderSkillsExisting() {
        skillsContainerExisting.innerHTML = '';
        if (skillsExisting.length === 0) {
            skillsContainerExisting.innerHTML = '<span class="text-sm text-gray-400">{{ __("admin.club_instructors_add_no_skills_yet") }}</span>';
        } else {
            skillsExisting.forEach((skill, index) => {
                const tag = document.createElement('span');
                tag.className = 'inline-flex items-center gap-1 px-3 py-1.5 bg-primary/10 text-primary rounded-full text-sm font-medium';
                tag.innerHTML = `${skill} <button type="button" class="hover:text-red-500" data-index="${index}"><i class="bi bi-x"></i></button>`;
                tag.querySelector('button').addEventListener('click', function() {
                    skillsExisting.splice(index, 1);
                    renderSkillsExisting();
                });
                skillsContainerExisting.appendChild(tag);
            });
        }
        skillsHiddenExisting.value = JSON.stringify(skillsExisting);
    }

    addSkillBtnExisting?.addEventListener('click', function() {
        const skill = newSkillInputExisting.value.trim();
        if (skill && !skillsExisting.includes(skill)) {
            skillsExisting.push(skill);
            newSkillInputExisting.value = '';
            renderSkillsExisting();
        }
    });

    newSkillInputExisting?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addSkillBtnExisting.click();
        }
    });

    // Member search
    const memberSearchInput = document.getElementById('memberSearchInput');
    const searchResults = document.getElementById('searchResults');
    const noResultsMsg = document.getElementById('noResultsMsg');
    const selectedMemberCard = document.getElementById('selectedMemberCard');
    const selectedMemberName = document.getElementById('selectedMemberName');

    let searchTimeout;
    memberSearchInput?.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 2) {
            searchResults.classList.add('hidden');
            noResultsMsg.classList.add('hidden');
            return;
        }

        searchTimeout = setTimeout(async () => {
            try {
                const response = await fetch(`/admin/club/{{ $club->slug }}/members/search?query=${encodeURIComponent(query)}`);
                const data = await response.json();
                const users = data.users ?? data;

                if (users.length > 0) {
                    searchResults.innerHTML = users.map(member => `
                        <div class="p-4 hover:bg-gray-50 cursor-pointer flex items-center gap-3 member-result" data-id="${member.id}" data-name="${member.name || member.full_name}">
                            ${member.profile_picture ?
                                `<img src="${member.profile_picture}" class="w-12 h-12 rounded-full object-cover">` :
                                `<div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center"><i class="bi bi-person text-primary"></i></div>`
                            }
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold truncate">${member.name || member.full_name}</p>
                                <p class="text-sm text-gray-500 truncate">${member.email || '{{ __("admin.club_instructors_add_member_fallback") }}'}</p>
                            </div>
                        </div>
                    `).join('');

                    searchResults.classList.remove('hidden');
                    noResultsMsg.classList.add('hidden');

                    // Add click handlers
                    document.querySelectorAll('.member-result').forEach(el => {
                        el.addEventListener('click', function() {
                            selectedMemberId = this.dataset.id;
                            document.getElementById('selectedMemberId').value = selectedMemberId;
                            selectedMemberName.textContent = this.dataset.name;
                            selectedMemberCard.classList.remove('hidden');
                            searchResults.classList.add('hidden');
                            memberSearchInput.value = '';
                        });
                    });
                } else {
                    searchResults.classList.add('hidden');
                    noResultsMsg.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Search error:', error);
            }
        }, 300);
    });

    // Global reset function for Alpine.js modal close
    window.instructorWizardReset = function() {
        currentStep = 1;
        creationType = 'new';
        totalSteps = 6;
        skills = [];
        skillsExisting = [];
        selectedMemberId = null;
        form.reset();
        document.getElementById('creationType').value = 'new';
        document.getElementById('selectedMemberId').value = '';
        // Reset cropper preview
        if (typeof removeImage_instructorPhotoCropper === 'function') {
            removeImage_instructorPhotoCropper();
        }
        selectedMemberCard?.classList.add('hidden');

        // Reset creation type selection visuals
        document.querySelectorAll('.creation-type-option').forEach(opt => {
            opt.classList.remove('border-primary', 'bg-primary/5');
            opt.classList.add('border-gray-200');
        });
        const newOption = document.querySelector('.creation-type-option[data-type="new"]');
        if (newOption) {
            newOption.classList.remove('border-gray-200');
            newOption.classList.add('border-primary', 'bg-primary/5');
            const radio = newOption.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        }

        renderSkills();
        renderSkillsExisting();
        showStep(1);
    };

    // Initialize
    showStep(1);
    renderSkills();
    renderSkillsExisting();
});
</script>
@endpush
