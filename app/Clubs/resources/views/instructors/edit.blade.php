<!-- Edit Instructor Modal -->
<div x-show="showEditInstructorModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50" @click="showEditInstructorModal = false"></div>

    <!-- Modal Content -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-2xl relative rounded-xl overflow-hidden"
             @click.stop>
            <!-- Header -->
            <div class="modal-header border-b border-gray-200 px-6 py-4">
                <h5 class="modal-title text-lg font-semibold">{{ __('admin.club_instructors_edit_title') }}</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" @click="showEditInstructorModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Progress Steps -->
            <div id="editWizardProgress" class="px-6 py-4 border-b border-gray-100">
                <div class="flex items-center justify-between w-full">
                    <!-- Rendered by JS -->
                </div>
            </div>

            <!-- Body -->
            <div class="modal-body px-6 py-6">
                <form id="editInstructorForm" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <!-- Step 1: Photo & Name -->
                    <div class="edit-wizard-step" data-step="1">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-person text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_edit_photo_name') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_edit_photo_name_sub') }}</p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_edit_full_name') }}</label>
                                <input type="text" id="editInstructorNameDisplay" name="name" placeholder="{{ __('admin.club_instructors_edit_full_name_ph') }}"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_edit_profile_photo') }}</label>
                                <p class="text-sm text-gray-500 mb-3">{{ __('admin.club_instructors_edit_upload_new_photo') }}</p>

                                <div class="flex items-center gap-6">
                                    <x-takeone-cropper
                                        id="editInstructorPhotoCropper"
                                        :width="200"
                                        :height="200"
                                        shape="circle"
                                        mode="form"
                                        inputName="photo"
                                        folder="instructors"
                                        :filename="'instructor_edit_' . time()"
                                        :previewWidth="128"
                                        :previewHeight="128"
                                        buttonText="{{ __('admin.club_instructors_edit_change_photo') }}"
                                        buttonClass="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors flex items-center justify-center gap-2"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Role & Experience -->
                    <div class="edit-wizard-step hidden" data-step="2">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-briefcase text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_edit_role_experience') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_edit_role_experience_sub') }}</p>
                            </div>
                        </div>

                        <div class="space-y-4" x-data="{ lang: 'en' }">
                            <x-lang-toggle class="mb-4" />
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_edit_role_specialty') }}</label>
                                <input type="text" id="editInstructorRole" name="role" placeholder="{{ __('admin.club_instructors_edit_role_ph') }}"
                                       x-show="lang==='en'"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                <input type="text" id="editInstructorRoleAr" name="translations[role][ar]" dir="rtl"
                                       x-show="lang==='ar'" x-cloak placeholder="المسمى بالعربية"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_edit_years_experience') }}</label>
                                <input type="number" id="editInstructorExperience" name="experience" min="0" placeholder="{{ __('admin.club_instructors_edit_years_ph') }}"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Skills -->
                    <div class="edit-wizard-step hidden" data-step="3">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-award text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_edit_skills_specialties') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_edit_skills_sub') }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_edit_skills') }}</label>
                                <p class="text-sm text-gray-500 mt-1 mb-3">{{ __('admin.club_instructors_edit_skills_hint') }}</p>

                                <div id="editSkillsContainer" class="flex flex-wrap gap-2 mb-4 p-4 bg-gray-50 rounded-lg min-h-[60px]">
                                    <span class="text-sm text-gray-400" id="editNoSkillsMsg">{{ __('admin.club_instructors_edit_no_skills') }}</span>
                                </div>

                                <div class="flex gap-2">
                                    <input type="text" id="editNewSkillInput" placeholder="{{ __('admin.club_instructors_edit_skill_ph') }}"
                                           class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <button type="button" id="editAddSkillBtn" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="skills" id="editSkillsHidden" value="[]">
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Bio -->
                    <div class="edit-wizard-step hidden" data-step="4">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-file-text text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">{{ __('admin.club_instructors_edit_bio') }}</h3>
                                <p class="text-sm text-gray-500">{{ __('admin.club_instructors_edit_bio_sub') }}</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">{{ __('admin.club_instructors_edit_your_story') }}</label>
                                <p class="text-sm text-gray-500 mb-2">{{ __('admin.club_instructors_edit_your_story_sub') }}</p>
                                <textarea id="editInstructorBio" name="bio" rows="5"
                                          placeholder="{{ __('admin.club_instructors_edit_bio_ph') }}"
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>
                        </div>
                    </div>

                    {{-- Persistent section (not part of the step wizard): staff type, compensation & classes --}}
                    <div class="mt-6 pt-5 border-t border-gray-100 space-y-5">
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-2"><i class="bi bi-person-badge me-1 text-primary"></i>{{ __('admin.ins_staff_type') }}</label>
                            <select name="staff_type" id="editStaffType" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                    onchange="document.getElementById('editPackageClassesSection').style.display = this.value === 'instructor' ? 'block' : 'none'">
                                <option value="instructor">{{ __('admin.ins_staff_type_instructor') }}</option>
                                <option value="secretary">{{ __('admin.ins_staff_type_secretary') }}</option>
                                <option value="operator">{{ __('admin.ins_staff_type_operator') }}</option>
                                <option value="cleaner">{{ __('admin.ins_staff_type_cleaner') }}</option>
                                <option value="other">{{ __('admin.ins_staff_type_other') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-base font-medium text-gray-700 mb-2"><i class="bi bi-cash-coin me-1 text-primary"></i>{{ __('admin.club_instructors_edit_compensation') }}</label>
                            <div class="flex flex-wrap items-end gap-3">
                                <div>
                                    <select name="compensation_type" id="editCompType" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                                            onchange="document.getElementById('editWageFields').style.display = this.value === 'paid' ? 'flex' : 'none'">
                                        <option value="volunteer">{{ __('admin.club_instructors_edit_volunteer') }}</option>
                                        <option value="paid">{{ __('admin.club_instructors_edit_paid') }}</option>
                                    </select>
                                </div>
                                <div id="editWageFields" class="flex items-end gap-3" style="display:none;">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">{{ __('admin.club_instructors_edit_amount') }}</label>
                                        <input type="number" min="0" step="0.01" name="wage_amount" id="editWageAmount" placeholder="0.00" class="w-32 px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1">{{ __('admin.club_instructors_edit_period') }}</label>
                                        <select name="wage_period" id="editWagePeriod" class="px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary">
                                            <option value="monthly">{{ __('admin.club_instructors_edit_per_month') }}</option>
                                            <option value="session">{{ __('admin.club_instructors_edit_per_session') }}</option>
                                            <option value="hourly">{{ __('admin.club_instructors_edit_per_hour') }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="editPackageClassesSection">
                            <label class="block text-base font-medium text-gray-700 mb-2"><i class="bi bi-calendar2-week me-1 text-primary"></i>{{ __('admin.club_instructors_edit_package_classes') }}</label>
                            @if($packageSlots->isEmpty())
                                <p class="text-sm text-gray-400">{{ __('admin.club_instructors_edit_no_packages') }}</p>
                            @else
                                <div class="space-y-3">
                                    @foreach($packageSlots->groupBy('package_id') as $slots)
                                        <div class="border border-gray-100 rounded-lg overflow-hidden">
                                            <p class="px-3 py-2 bg-gray-50 text-xs font-bold text-gray-700"><i class="bi bi-box text-primary me-1"></i>{{ $slots->first()->package_name }}</p>
                                            <div class="divide-y divide-gray-50">
                                                @foreach($slots as $slot)
                                                    <label class="flex items-center gap-3 px-3 py-2 cursor-pointer hover:bg-gray-50">
                                                        <input type="checkbox" name="package_slots[]" value="{{ $slot->id }}" class="edit-slot-cb rounded text-primary focus:ring-primary">
                                                        <span class="min-w-0">
                                                            <span class="block text-sm font-medium text-gray-800 truncate">{{ $slot->activity_name }}</span>
                                                            <span class="block text-xs text-gray-400 truncate">{{ $slot->schedule_label ?: __('admin.club_instructors_edit_no_schedule') }}@if($slot->instructor_name) · {{ $slot->instructor_name }}@endif</span>
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
            <div class="modal-footer border-t border-gray-200 px-6 py-4 grid grid-cols-3 items-center">
                <div class="flex justify-start">
                    <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors" @click="showEditInstructorModal = false">
                        {{ __('shared.cancel') }}
                    </button>
                </div>

                <div class="flex justify-center">
                    <button type="button" class="px-6 py-2 text-sm font-semibold text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors flex items-center gap-2"
                            onclick="document.getElementById('editInstructorForm').submit()">
                        <i class="bi bi-check-lg"></i> {{ __('admin.club_instructors_edit_update') }}
                    </button>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" id="editPrevStepBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors hidden flex items-center gap-1">
                        <i class="bi bi-chevron-left"></i> {{ __('admin.club_instructors_edit_previous') }}
                    </button>
                    <button type="button" id="editNextStepBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-1">
                        {{ __('admin.club_instructors_edit_next') }} <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
(function () {
    const TOTAL_STEPS = 4;
    let currentStep = 1;
    let editSkills = [];

    const progressContainer = document.getElementById('editWizardProgress');
    const prevBtn = document.getElementById('editPrevStepBtn');
    const nextBtn = document.getElementById('editNextStepBtn');

    function renderProgress() {
        let html = '<div class="flex items-center justify-between w-full">';
        for (let i = 1; i <= TOTAL_STEPS; i++) {
            const isCompleted = currentStep > i;
            const isCurrent = currentStep === i;
            html += `
                <div class="flex items-center ${i < TOTAL_STEPS ? 'flex-1' : ''}">
                    <div class="flex items-center justify-center w-10 h-10 rounded-full border-2 transition-all ${
                        isCompleted ? 'bg-primary border-primary text-white' :
                        isCurrent   ? 'border-primary text-primary font-bold' :
                                      'border-gray-300 text-gray-400'
                    }">
                        ${isCompleted ? '<i class="bi bi-check"></i>' : i}
                    </div>
                    ${i < TOTAL_STEPS ? `<div class="flex-1 h-0.5 mx-2 ${isCompleted ? 'bg-primary' : 'bg-gray-200'}"></div>` : ''}
                </div>
            `;
        }
        html += '</div>';
        progressContainer.innerHTML = html;
    }

    function showStep(step) {
        document.querySelectorAll('.edit-wizard-step').forEach(el => el.classList.add('hidden'));
        const target = document.querySelector(`.edit-wizard-step[data-step="${step}"]`);
        if (target) target.classList.remove('hidden');

        prevBtn.classList.toggle('hidden', step === 1);
        nextBtn.classList.toggle('hidden', step === TOTAL_STEPS);

        renderProgress();
    }

    prevBtn?.addEventListener('click', function () {
        if (currentStep > 1) { currentStep--; showStep(currentStep); }
    });

    nextBtn?.addEventListener('click', function () {
        if (currentStep < TOTAL_STEPS) { currentStep++; showStep(currentStep); }
    });

    // Skills management
    function renderEditSkills() {
        const container = document.getElementById('editSkillsContainer');
        const noMsg = document.getElementById('editNoSkillsMsg');
        container.innerHTML = '';
        if (editSkills.length === 0) {
            container.innerHTML = '<span class="text-sm text-gray-400" id="editNoSkillsMsg">{{ __("admin.club_instructors_edit_no_skills") }}</span>';
        } else {
            editSkills.forEach((skill, index) => {
                const tag = document.createElement('span');
                tag.className = 'inline-flex items-center gap-1 px-3 py-1.5 bg-primary/10 text-primary rounded-full text-sm font-medium';
                tag.innerHTML = `${skill} <button type="button" class="hover:text-red-500" data-index="${index}"><i class="bi bi-x"></i></button>`;
                tag.querySelector('button').addEventListener('click', function () {
                    editSkills.splice(index, 1);
                    renderEditSkills();
                });
                container.appendChild(tag);
            });
        }
        document.getElementById('editSkillsHidden').value = JSON.stringify(editSkills);
    }

    document.getElementById('editAddSkillBtn')?.addEventListener('click', function () {
        const input = document.getElementById('editNewSkillInput');
        const skill = input.value.trim();
        if (skill && !editSkills.includes(skill)) {
            editSkills.push(skill);
            input.value = '';
            renderEditSkills();
        }
    });

    document.getElementById('editNewSkillInput')?.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('editAddSkillBtn').click(); }
    });

    // Global function called when Edit button is clicked
    window.openEditModal = function (instructorId) {
        const data = window.instructorData?.[instructorId] || {};
        currentStep = 1;
        editSkills = Array.isArray(data.skills) ? [...data.skills] : [];

        // Set form action
        document.getElementById('editInstructorForm').action =
            `/admin/club/{{ $club->slug }}/instructors/${instructorId}`;

        // Populate fields
        document.getElementById('editInstructorNameDisplay').value = data.name || '';
        const staffType = data.staff_type || 'instructor';
        document.getElementById('editStaffType').value = staffType;
        document.getElementById('editPackageClassesSection').style.display = staffType === 'instructor' ? 'block' : 'none';
        document.getElementById('editInstructorRole').value = data.role || '';
        document.getElementById('editInstructorRoleAr').value = data.translations?.role?.ar || '';
        document.getElementById('editInstructorExperience').value = data.experience ?? '';
        document.getElementById('editInstructorBio').value = data.bio || '';

        // Compensation
        const compType = data.compensation_type === 'paid' ? 'paid' : 'volunteer';
        document.getElementById('editCompType').value = compType;
        document.getElementById('editWageAmount').value = (data.wage_amount ?? '') === null ? '' : (data.wage_amount ?? '');
        document.getElementById('editWagePeriod').value = data.wage_period || 'monthly';
        document.getElementById('editWageFields').style.display = compType === 'paid' ? 'flex' : 'none';

        // Package class/schedule slots
        const slotIds = Array.isArray(data.slot_ids) ? data.slot_ids.map(Number) : [];
        document.querySelectorAll('.edit-slot-cb').forEach(cb => { cb.checked = slotIds.includes(Number(cb.value)); });

        // Reset cropper preview to current photo
        const previewImg = document.querySelector('#editInstructorPhotoCropper-preview img, [id^="editInstructorPhotoCropper"] img.cropper-preview-image');
        if (previewImg && data.photo) {
            previewImg.src = data.photo;
        }

        renderEditSkills();
        showStep(1);
    };

    // Initialize on load
    showStep(1);
    renderEditSkills();
})();
}); // DOMContentLoaded
</script>
@endpush
