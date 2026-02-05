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
                <h5 class="modal-title text-lg font-semibold">Let's Add a New Instructor</h5>
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
                <form id="addInstructorForm" action="{{ route('admin.club.instructors.store', $club->id) }}" method="POST" enctype="multipart/form-data">
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
                                <h3 class="font-bold text-xl">How would you like to add the instructor?</h3>
                                <p class="text-sm text-gray-500">Choose to create a new member or select an existing one</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <label class="flex items-center gap-4 p-4 border-2 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors creation-type-option" data-type="new">
                                <input type="radio" name="creation_type_radio" value="new" checked class="w-5 h-5 text-primary">
                                <div class="flex-1">
                                    <div class="font-semibold">Create new instructor</div>
                                    <div class="text-sm text-gray-500">Create a new TakeOne platform member and link as instructor</div>
                                </div>
                            </label>

                            <label class="flex items-center gap-4 p-4 border-2 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors creation-type-option" data-type="existing">
                                <input type="radio" name="creation_type_radio" value="existing" class="w-5 h-5 text-primary">
                                <div class="flex-1">
                                    <div class="font-semibold">Select from existing members</div>
                                    <div class="text-sm text-gray-500">Search and link an existing club member as instructor</div>
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
                                <h3 class="font-bold text-xl">Create TakeOne Platform Member</h3>
                                <p class="text-sm text-gray-500">First, let's create their member account</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                                    <input type="email" name="email" placeholder="member@example.com" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                                    <input type="password" name="password" placeholder="Minimum 6 characters" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">Phone Number <span class="text-red-500">*</span></label>
                                <div class="flex gap-2">
                                    <select name="country_code" class="w-24 px-2 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="+973">+973</option>
                                        <option value="+971">+971</option>
                                        <option value="+966">+966</option>
                                        <option value="+1">+1</option>
                                        <option value="+44">+44</option>
                                    </select>
                                    <input type="tel" name="phone" placeholder="Phone number" class="flex-1 px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">Gender <span class="text-red-500">*</span></label>
                                    <select name="gender" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700">Date of Birth <span class="text-red-500">*</span></label>
                                    <input type="date" name="birthdate" max="{{ date('Y-m-d') }}" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">Nationality <span class="text-red-500">*</span></label>
                                <select name="nationality" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <option value="">Select country...</option>
                                    <option value="BH">Bahrain</option>
                                    <option value="AE">United Arab Emirates</option>
                                    <option value="SA">Saudi Arabia</option>
                                    <option value="KW">Kuwait</option>
                                    <option value="QA">Qatar</option>
                                    <option value="OM">Oman</option>
                                    <option value="IN">India</option>
                                    <option value="PK">Pakistan</option>
                                    <option value="PH">Philippines</option>
                                    <option value="EG">Egypt</option>
                                    <option value="JO">Jordan</option>
                                    <option value="LB">Lebanon</option>
                                    <option value="US">United States</option>
                                    <option value="GB">United Kingdom</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 EXISTING: Search Member -->
                    <div class="wizard-step hidden" data-step="2" data-type="existing">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-search text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">Find Club Member</h3>
                                <p class="text-sm text-gray-500">Search by name, email, or phone number</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="relative">
                                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <input type="text" id="memberSearchInput" placeholder="Search by name, email, or phone..." class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>

                            <div id="searchResults" class="border rounded-lg divide-y max-h-64 overflow-y-auto hidden">
                                <!-- Search results will be inserted here -->
                            </div>

                            <div id="noResultsMsg" class="text-center py-8 text-gray-500 hidden">
                                <i class="bi bi-person text-4xl mb-2 opacity-50"></i>
                                <p>No members found</p>
                            </div>

                            <div id="selectedMemberCard" class="p-4 bg-primary/10 rounded-lg border-2 border-primary hidden">
                                <p class="text-sm font-semibold mb-1">Selected Member</p>
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
                                <h3 class="font-bold text-xl">First, let's get to know you</h3>
                                <p class="text-sm text-gray-500">Tell us your name and share a photo</p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">What's your full name? <span class="text-red-500">*</span></label>
                                <input type="text" name="name" placeholder="Enter your full name" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">Share your photo</label>
                                <p class="text-sm text-gray-500 mb-3">This helps members recognize you</p>

                                <div class="flex items-center gap-6">
                                    <div id="photoPreviewContainer" class="relative">
                                        <div id="photoPlaceholder" class="w-32 h-32 border-2 border-dashed border-gray-300 rounded-full flex flex-col items-center justify-center cursor-pointer hover:border-primary/50 transition-colors">
                                            <i class="bi bi-camera text-3xl text-gray-400 mb-1"></i>
                                            <span class="text-xs text-gray-500">Upload</span>
                                        </div>
                                        <img id="photoPreview" src="" alt="Preview" class="w-32 h-32 rounded-full object-cover border-4 border-primary/20 hidden">
                                    </div>

                                    <div class="flex-1">
                                        <input type="file" id="instructorPhotoInput" name="photo" accept="image/*" class="hidden">
                                        <button type="button" id="uploadPhotoBtn" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors flex items-center justify-center gap-2">
                                            <i class="bi bi-upload"></i>
                                            <span>Upload Photo</span>
                                        </button>
                                    </div>
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
                                <h3 class="font-bold text-xl">What do you do?</h3>
                                <p class="text-sm text-gray-500">Tell us about your professional background</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">What's your role? <span class="text-red-500">*</span></label>
                                <input type="text" name="specialty_existing" placeholder="e.g., Martial Arts Instructor" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">How many years of experience? <span class="text-red-500">*</span></label>
                                <input type="number" name="experience_existing" min="0" placeholder="e.g., 5" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>
                    </div>

                    <div class="wizard-step hidden" data-step="4" data-type="new">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-briefcase text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">What do you do?</h3>
                                <p class="text-sm text-gray-500">Tell us about your professional background</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">What's your role? <span class="text-red-500">*</span></label>
                                <input type="text" name="specialty" placeholder="e.g., Martial Arts Instructor" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">How many years of experience? <span class="text-red-500">*</span></label>
                                <input type="number" name="experience" min="0" placeholder="e.g., 5" class="w-full px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
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
                                <h3 class="font-bold text-xl">What are you good at?</h3>
                                <p class="text-sm text-gray-500">Share your skills and specialties</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-base font-medium text-gray-700">Your Skills & Specialties</label>
                                <p class="text-sm text-gray-500 mt-1 mb-3">Add skills like Taekwondo, Muay Thai, Judo, etc.</p>

                                <div id="skillsContainerExisting" class="flex flex-wrap gap-2 mb-4 p-4 bg-gray-50 rounded-lg min-h-[60px]">
                                    <!-- Skills tags will be added here -->
                                    <span class="text-sm text-gray-400" id="noSkillsMsgExisting">No skills added yet</span>
                                </div>

                                <div class="flex gap-2">
                                    <input type="text" id="newSkillInputExisting" placeholder="Type a skill and press Enter" class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
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
                                <h3 class="font-bold text-xl">What are you good at?</h3>
                                <p class="text-sm text-gray-500">Share your skills and specialties</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-base font-medium text-gray-700">Your Skills & Specialties</label>
                                <p class="text-sm text-gray-500 mt-1 mb-3">Add skills like Taekwondo, Muay Thai, Judo, etc.</p>

                                <div id="skillsContainer" class="flex flex-wrap gap-2 mb-4 p-4 bg-gray-50 rounded-lg min-h-[60px]">
                                    <!-- Skills tags will be added here -->
                                    <span class="text-sm text-gray-400" id="noSkillsMsg">No skills added yet</span>
                                </div>

                                <div class="flex gap-2">
                                    <input type="text" id="newSkillInput" placeholder="Type a skill and press Enter" class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
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
                                <h3 class="font-bold text-xl">Tell us more about yourself</h3>
                                <p class="text-sm text-gray-500">Share your story, achievements, and qualifications</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">Your Story</label>
                                <p class="text-sm text-gray-500 mb-2">A brief introduction about yourself</p>
                                <textarea name="bio_existing" rows="3" placeholder="Tell members about your background, passion, and teaching philosophy..." class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">Your Achievements</label>
                                <p class="text-sm text-gray-500 mb-2">Notable accomplishments and awards</p>
                                <textarea name="achievements_existing" rows="3" placeholder="e.g., National Champion 2023, 10+ years competition experience..." class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">Your Certifications</label>
                                <p class="text-sm text-gray-500 mb-2">Professional qualifications and training</p>
                                <textarea name="certifications_existing" rows="3" placeholder="e.g., Black Belt 3rd Dan, Certified Personal Trainer..." class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-step hidden" data-step="6" data-type="new">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-3 bg-primary/10 rounded-full">
                                <i class="bi bi-file-text text-xl text-primary"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-xl">Tell us more about yourself</h3>
                                <p class="text-sm text-gray-500">Share your story, achievements, and qualifications</p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">Your Story</label>
                                <p class="text-sm text-gray-500 mb-2">A brief introduction about yourself</p>
                                <textarea name="bio" rows="3" placeholder="Tell members about your background, passion, and teaching philosophy..." class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">Your Achievements</label>
                                <p class="text-sm text-gray-500 mb-2">Notable accomplishments and awards</p>
                                <textarea name="achievements" rows="3" placeholder="e.g., National Champion 2023, 10+ years competition experience..." class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>

                            <div class="space-y-2">
                                <label class="block text-base font-medium text-gray-700">Your Certifications</label>
                                <p class="text-sm text-gray-500 mb-2">Professional qualifications and training</p>
                                <textarea name="certifications" rows="3" placeholder="e.g., Black Belt 3rd Dan, Certified Personal Trainer..." class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-t border-gray-200 px-6 py-4 flex justify-between">
                <button type="button" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors" @click="showAddInstructorModal = false; resetInstructorForm()">
                    Cancel
                </button>

                <div class="flex gap-2">
                    <button type="button" id="prevStepBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors hidden flex items-center gap-1">
                        <i class="bi bi-chevron-left"></i> Previous
                    </button>

                    <button type="button" id="nextStepBtn" class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors flex items-center gap-1">
                        Next <i class="bi bi-chevron-right"></i>
                    </button>

                    <button type="button" id="submitBtn" class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors hidden flex items-center gap-2">
                        <i class="bi bi-check-lg"></i> Create Instructor
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

    // Photo upload
    const photoInput = document.getElementById('instructorPhotoInput');
    const uploadBtn = document.getElementById('uploadPhotoBtn');
    const photoPreview = document.getElementById('photoPreview');
    const photoPlaceholder = document.getElementById('photoPlaceholder');

    uploadBtn?.addEventListener('click', () => photoInput?.click());
    photoPlaceholder?.addEventListener('click', () => photoInput?.click());

    photoInput?.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreview.src = e.target.result;
                photoPreview.classList.remove('hidden');
                photoPlaceholder.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }
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
            skillsContainer.innerHTML = '<span class="text-sm text-gray-400">No skills added yet</span>';
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
            skillsContainerExisting.innerHTML = '<span class="text-sm text-gray-400">No skills added yet</span>';
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
                const response = await fetch(`/admin/club/{{ $club->id }}/members/search?q=${encodeURIComponent(query)}`);
                const data = await response.json();

                if (data.length > 0) {
                    searchResults.innerHTML = data.map(member => `
                        <div class="p-4 hover:bg-gray-50 cursor-pointer flex items-center gap-3 member-result" data-id="${member.id}" data-name="${member.name || member.full_name}">
                            ${member.photo || member.profile_picture ?
                                `<img src="/storage/${member.photo || member.profile_picture}" class="w-12 h-12 rounded-full object-cover">` :
                                `<div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center"><i class="bi bi-person text-primary"></i></div>`
                            }
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold truncate">${member.name || member.full_name}</p>
                                <p class="text-sm text-gray-500 truncate">${member.email || 'Member'}</p>
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
        photoPreview?.classList.add('hidden');
        photoPlaceholder?.classList.remove('hidden');
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
