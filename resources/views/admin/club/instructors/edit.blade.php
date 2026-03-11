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
                <h5 class="modal-title text-lg font-semibold">Edit Instructor</h5>
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
                                <h3 class="font-bold text-xl">Photo & Name</h3>
                                <p class="text-sm text-gray-500">Update the instructor's profile photo</p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">Full Name</label>
                                <input type="text" id="editInstructorNameDisplay" name="name" placeholder="Full name"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">Profile Photo</label>
                                <p class="text-sm text-gray-500 mb-3">Upload a new photo or keep the current one</p>

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
                                        buttonText="Change Photo"
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
                                <h3 class="font-bold text-xl">Role & Experience</h3>
                                <p class="text-sm text-gray-500">Update their professional background</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">Role / Specialty</label>
                                <input type="text" id="editInstructorRole" name="role" placeholder="e.g., Martial Arts Instructor"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">Years of Experience</label>
                                <input type="number" id="editInstructorExperience" name="experience" min="0" placeholder="e.g., 5"
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
                                <h3 class="font-bold text-xl">Skills & Specialties</h3>
                                <p class="text-sm text-gray-500">Update their skills</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-base font-medium text-gray-700">Skills</label>
                                <p class="text-sm text-gray-500 mt-1 mb-3">Add skills like Taekwondo, Muay Thai, Judo, etc.</p>

                                <div id="editSkillsContainer" class="flex flex-wrap gap-2 mb-4 p-4 bg-gray-50 rounded-lg min-h-[60px]">
                                    <span class="text-sm text-gray-400" id="editNoSkillsMsg">No skills added yet</span>
                                </div>

                                <div class="flex gap-2">
                                    <input type="text" id="editNewSkillInput" placeholder="Type a skill and press Enter"
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
                                <h3 class="font-bold text-xl">Bio</h3>
                                <p class="text-sm text-gray-500">Update their story and introduction</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">Your Story</label>
                                <p class="text-sm text-gray-500 mb-2">A brief introduction about themselves</p>
                                <textarea id="editInstructorBio" name="bio" rows="5"
                                          placeholder="Tell members about their background, passion, and teaching philosophy..."
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-t border-gray-200 px-6 py-4 grid grid-cols-3 items-center">
                <div class="flex justify-start">
                    <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors" @click="showEditInstructorModal = false">
                        Cancel
                    </button>
                </div>

                <div class="flex justify-center">
                    <button type="button" class="px-6 py-2 text-sm font-semibold text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors flex items-center gap-2"
                            onclick="document.getElementById('editInstructorForm').submit()">
                        <i class="bi bi-check-lg"></i> Update
                    </button>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" id="editPrevStepBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors hidden flex items-center gap-1">
                        <i class="bi bi-chevron-left"></i> Previous
                    </button>
                    <button type="button" id="editNextStepBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-1">
                        Next <i class="bi bi-chevron-right"></i>
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
            container.innerHTML = '<span class="text-sm text-gray-400" id="editNoSkillsMsg">No skills added yet</span>';
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
        document.getElementById('editInstructorRole').value = data.role || '';
        document.getElementById('editInstructorExperience').value = data.experience ?? '';
        document.getElementById('editInstructorBio').value = data.bio || '';

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
