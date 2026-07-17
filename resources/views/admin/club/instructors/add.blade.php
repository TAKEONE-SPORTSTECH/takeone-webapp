<!-- Add Instructor Modal -->
@php
    // Skills an existing instructor can be tagged with = the distinct activities
    // offered by this club's packages. (Once member activity-history exists, this
    // will be scoped to skills the member actually earned.)
    $existingSkillOptions = $packageSlots->pluck('activity_name')->filter()->unique()->values();
@endphp
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
        <div class="modal-content bg-white border-0 shadow-2xl w-full max-w-2xl relative rounded-2xl overflow-hidden"
             @click.stop>
            <!-- Header (gradient hero) -->
            <div class="relative overflow-hidden px-6 py-5 text-white"
                 style="background: linear-gradient(135deg, hsl(250 65% 62%), hsl(266 58% 50%));">
                <div class="absolute -end-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                <div class="absolute end-16 -bottom-12 w-28 h-28 rounded-full bg-white/5"></div>
                <div class="relative z-10 flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0">
                        <i class="bi bi-person-badge text-xl"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h5 class="text-lg font-bold leading-tight">{{ __('admin.club_instructors_add_title') }}</h5>
                        <p class="text-xs text-white/75 mt-0.5">{{ __('admin.club_instructors_add_subtitle') }}</p>
                    </div>
                    <button type="button" class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 transition-colors grid place-items-center flex-shrink-0" @click="showAddInstructorModal = false; resetInstructorForm()" aria-label="{{ __('shared.cancel') }}">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>

            <!-- Progress Steps (rendered by JS) -->
            <div id="wizardProgress" class="px-6 py-3.5 bg-gray-50/70 border-b border-gray-100"></div>

            <!-- Body -->
            <div class="modal-body px-6 py-6">
                <form id="addInstructorForm" action="{{ route('admin.club.instructors.store', $club->slug) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="creation_type" id="creationType" value="new">
                    <input type="hidden" name="selected_member_id" id="selectedMemberId" value="">

                    <!-- Step 1: Staff type + Choose Creation Type -->
                    <div class="wizard-step" data-step="1">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-person-badge text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.ins_staff_type') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_add_staff_type_sub') }}</p>
                            </div>
                        </div>

                        <input type="hidden" name="staff_type" id="staffTypeHidden" value="instructor">
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 mb-6">
                            @foreach(['instructor' => 'bi-person-badge', 'secretary' => 'bi-person-vcard', 'operator' => 'bi-gear', 'cleaner' => 'bi-stars', 'other' => 'bi-person-workspace'] as $type => $icon)
                                <button type="button" class="staff-type-option flex flex-col items-center gap-1.5 p-3 border-2 rounded-xl transition-colors {{ $type === 'instructor' ? 'border-primary bg-primary/5' : 'border-gray-200 hover:bg-gray-50' }}" data-staff-type="{{ $type }}">
                                    <i class="bi {{ $icon }} text-lg {{ $type === 'instructor' ? 'text-primary' : 'text-gray-400' }}"></i>
                                    <span class="text-xs font-medium text-center">{{ __('admin.ins_staff_type_'.$type) }}</span>
                                </button>
                            @endforeach
                        </div>

                        <div class="flex items-center gap-3 mb-4 pt-2 border-t border-gray-100">
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

                    <!-- Step 3 EXISTING: Role, skills & experience (combined) -->
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

                        <div class="space-y-5" x-data="{ lang: 'en' }">
                            {{-- Role --}}
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_role_q') }} <span class="text-red-500">*</span></label>
                                <x-lang-toggle class="mb-4" />
                                <input type="text" name="specialty_existing" placeholder="{{ __('admin.club_instructors_add_role_placeholder') }}" x-show="lang==='en'" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <input type="text" name="translations[role][ar]" dir="rtl" x-show="lang==='ar'" x-cloak placeholder="المسمى بالعربية" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent" value="{{ old('translations.role.ar') }}">
                            </div>

                            {{-- Skills — chosen BEFORE the years of experience, since experience is tied to the selected skill --}}
                            <div>
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_add_skills_label') }}</label>
                                <p class="text-sm text-gray-500 mt-1 mb-3">{{ __('admin.club_instructors_add_skills_hint') }}</p>

                                <div id="skillsContainerExisting" class="flex flex-wrap gap-2 mb-4 p-4 bg-gray-50 rounded-lg min-h-[60px]">
                                    <span class="text-sm text-gray-400" id="noSkillsMsgExisting">{{ __('admin.club_instructors_add_no_skills_yet') }}</span>
                                </div>

                                {{-- Multi-select from the club's package activities (not free text) --}}
                                <div x-data="{ open:false, skills: @js($existingSkillOptions), pick(s){ window.addInstructorSkillExisting && window.addInstructorSkillExisting(s); this.open=false; } }" class="relative">
                                    <button type="button" @click="open=!open" @click.away="open=false"
                                            class="w-full flex items-center justify-between px-4 py-3 border border-gray-300 rounded-lg text-base text-left text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary">
                                        <span>{{ __('admin.club_instructors_add_skill_select') }}</span>
                                        <i class="bi bi-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                                    </button>
                                    <div x-show="open" x-cloak
                                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                         class="absolute z-20 mt-1 w-full bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden max-h-56 overflow-y-auto">
                                        <template x-for="skill in skills" :key="skill">
                                            <div @click="pick(skill)" class="px-4 py-2.5 text-sm hover:bg-muted/60 cursor-pointer flex items-center justify-between">
                                                <span x-text="skill" class="text-gray-800"></span>
                                                <i class="bi bi-plus-lg text-primary text-xs"></i>
                                            </div>
                                        </template>
                                        <div x-show="skills.length === 0" class="px-4 py-3 text-sm text-gray-400">{{ __('admin.club_instructors_add_no_packages') }}</div>
                                    </div>
                                </div>
                                <input type="hidden" name="skills_existing" id="skillsHiddenExisting" value="[]">
                            </div>

                            {{-- Years of experience — kept, placed after the skills it relates to --}}
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

                    {{-- (Existing-member Skills were merged into step 3 "Role, skills & experience" above.) --}}

                    <!-- Step 5 NEW: Skills -->
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

                    <!-- Step 4 EXISTING / Step 6 NEW: Bio & Achievements -->
                    <div class="wizard-step hidden" data-step="4" data-type="existing">
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

                            {{-- Achievements & certifications for existing members are derived from
                                 their record/events, not entered by hand. --}}
                            <div class="rounded-xl bg-muted/40 border border-gray-100 p-4 flex items-start gap-3">
                                <i class="bi bi-trophy text-primary text-lg mt-0.5"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-700">{{ __('admin.club_instructors_add_your_achievements') }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ __('admin.club_instructors_add_from_record') }}</p>
                                </div>
                            </div>

                            <div class="rounded-xl bg-muted/40 border border-gray-100 p-4 flex items-start gap-3">
                                <i class="bi bi-patch-check text-primary text-lg mt-0.5"></i>
                                <div>
                                    <p class="text-sm font-medium text-gray-700">{{ __('admin.club_instructors_add_your_certifications') }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ __('admin.club_instructors_add_from_events') }}</p>
                                </div>
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

                    {{-- Step: Compensation (shared by new/existing; data-step/type set in JS) --}}
                    <div class="wizard-step hidden" id="stepCompensation">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-cash-coin text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_add_compensation') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_add_compensation_sub') }}</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.club_instructors_add_compensation') }}</label>
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

                    {{-- Step: Package Classes & Schedule (shared by new/existing) --}}
                    <div class="wizard-step hidden" id="stepPackages">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-calendar2-week text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_add_package_classes') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_add_package_classes_sub') }}</p>
                            </div>
                        </div>

                        @if($packageSlots->isEmpty())
                            <div class="text-center py-10 border-2 border-dashed border-gray-200 rounded-2xl">
                                <i class="bi bi-calendar-x text-3xl text-gray-300"></i>
                                <p class="text-sm text-gray-400 mt-2">{{ __('admin.club_instructors_add_no_packages') }}</p>
                            </div>
                        @else
                            <div class="space-y-3 max-h-[48vh] overflow-y-auto pr-1 -mr-1">
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
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-t border-gray-100 bg-white px-6 py-4 flex justify-between items-center gap-3">
                <button type="button" class="px-4 py-2.5 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors" @click="showAddInstructorModal = false; resetInstructorForm()">
                    {{ __('shared.cancel') }}
                </button>

                <div class="flex gap-2">
                    <button type="button" id="prevStepBtn" class="px-4 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 active:scale-[.98] transition-all hidden flex items-center gap-1.5">
                        <i class="bi bi-chevron-left text-xs"></i> {{ __('admin.club_instructors_add_previous') }}
                    </button>

                    <button type="button" id="nextStepBtn" class="px-5 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 active:scale-[.98] transition-all shadow-sm shadow-primary/20 flex items-center gap-1.5">
                        {{ __('admin.club_instructors_add_next') }} <i class="bi bi-chevron-right text-xs"></i>
                    </button>

                    <button type="button" id="submitBtn" class="px-5 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary/90 active:scale-[.98] transition-all shadow-sm shadow-primary/20 hidden flex items-center gap-2">
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
    let staffType = 'instructor';
    let totalSteps = 8; // new: 8 (incl. compensation + package steps), existing: 6 — minus 1 if staffType isn't 'instructor' (package classes step skipped)
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
        const pct = Math.round((currentStep / totalSteps) * 100);
        let dots = '<div class="flex items-center w-full">';
        for (let i = 1; i <= totalSteps; i++) {
            const isCompleted = currentStep > i;
            const isCurrent = currentStep === i;

            dots += `
                <div class="flex items-center ${i < totalSteps ? 'flex-1' : ''}">
                    <div class="flex items-center justify-center w-8 h-8 rounded-full text-xs font-bold transition-all duration-300 ${
                        isCompleted ? 'bg-primary text-white shadow-sm shadow-primary/30' :
                        isCurrent ? 'bg-primary/10 text-primary ring-2 ring-primary' :
                        'bg-gray-100 text-gray-400'
                    }">
                        ${isCompleted ? '<i class="bi bi-check-lg"></i>' : i}
                    </div>
                    ${i < totalSteps ? `<div class="flex-1 h-1 mx-1.5 rounded-full transition-all duration-300 ${isCompleted ? 'bg-primary' : 'bg-gray-200'}"></div>` : ''}
                </div>`;
        }
        dots += '</div>';

        const label = `<div class="flex items-center justify-between mb-2.5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Step ${currentStep} / ${totalSteps}</span>
            <span class="text-[11px] font-bold text-primary">${pct}%</span>
        </div>`;

        progressContainer.innerHTML = label + dots;
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

    // Compensation + Package-classes are shared steps appended to the end of the
    // wizard. Their position differs by flow (new = 7,8 · existing = 5,6), so we
    // stamp their data-step/data-type to match the current creation type. The
    // Package Classes step only makes sense for instructors — non-instructor staff
    // (secretary/operator/cleaner/other) skip it entirely.
    function assignSharedSteps() {
        const comp = document.getElementById('stepCompensation');
        const pkg  = document.getElementById('stepPackages');
        if (!comp || !pkg) return;
        if (creationType === 'new') { comp.dataset.step = '7'; pkg.dataset.step = '8'; }
        else                        { comp.dataset.step = '5'; pkg.dataset.step = '6'; }
        comp.dataset.type = creationType;
        pkg.dataset.type  = creationType;
    }

    function recomputeTotalSteps() {
        const base = creationType === 'new' ? 8 : 6;
        totalSteps = staffType === 'instructor' ? base : base - 1;
    }

    // Staff type selection (Step 1)
    document.querySelectorAll('.staff-type-option').forEach(option => {
        option.addEventListener('click', function() {
            staffType = this.dataset.staffType;
            document.getElementById('staffTypeHidden').value = staffType;
            recomputeTotalSteps();

            document.querySelectorAll('.staff-type-option').forEach(opt => {
                opt.classList.remove('border-primary', 'bg-primary/5');
                opt.classList.add('border-gray-200');
                opt.querySelector('i').classList.remove('text-primary');
                opt.querySelector('i').classList.add('text-gray-400');
            });
            this.classList.remove('border-gray-200');
            this.classList.add('border-primary', 'bg-primary/5');
            this.querySelector('i').classList.remove('text-gray-400');
            this.querySelector('i').classList.add('text-primary');
        });
    });

    // Handle creation type selection
    document.querySelectorAll('.creation-type-option').forEach(option => {
        option.addEventListener('click', function() {
            creationType = this.dataset.type;
            document.getElementById('creationType').value = creationType;
            recomputeTotalSteps();
            assignSharedSteps();

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

    // The existing-member skills step picks from a dropdown of club activities
    // (see the x-data picker in the view), which calls this to add a chip.
    window.addInstructorSkillExisting = function (skill) {
        skill = (skill || '').trim();
        if (skill && !skillsExisting.includes(skill)) {
            skillsExisting.push(skill);
            renderSkillsExisting();
        }
    };

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
        staffType = 'instructor';
        recomputeTotalSteps();
        skills = [];
        skillsExisting = [];
        selectedMemberId = null;
        form.reset();
        document.getElementById('creationType').value = 'new';
        document.getElementById('staffTypeHidden').value = 'instructor';
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

        // Reset staff type selection visuals
        document.querySelectorAll('.staff-type-option').forEach(opt => {
            const isInstructor = opt.dataset.staffType === 'instructor';
            opt.classList.toggle('border-primary', isInstructor);
            opt.classList.toggle('bg-primary/5', isInstructor);
            opt.classList.toggle('border-gray-200', !isInstructor);
            opt.querySelector('i').classList.toggle('text-primary', isInstructor);
            opt.querySelector('i').classList.toggle('text-gray-400', !isInstructor);
        });

        renderSkills();
        renderSkillsExisting();
        assignSharedSteps();
        showStep(1);
    };

    // Initialize
    recomputeTotalSteps();
    assignSharedSteps();
    showStep(1);
    renderSkills();
    renderSkillsExisting();
});
</script>
@endpush
