<!-- Edit Package Modal -->
<div x-show="showEditPackageModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50" @click="showEditPackageModal = false"></div>

    <!-- Modal Content -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-5xl relative"
             x-data="{
                 currentTab: 'basic',
                 tabs: ['basic', 'schedules', 'trainers', 'pricing'],
                 tabNames: ['Basic Info', 'Schedules', 'Assign Trainers', 'Pricing & Confirm'],
                 get currentIndex() { return this.tabs.indexOf(this.currentTab) },
                 get isLastTab() { return this.currentTab === 'pricing' },
                 nextTab() {
                     const idx = this.tabs.indexOf(this.currentTab);
                     if (idx < this.tabs.length - 1) {
                         this.currentTab = this.tabs[idx + 1];
                         if (this.currentTab === 'trainers' && window.updateEditTrainerAssignmentsUI) {
                             window.updateEditTrainerAssignmentsUI();
                         }
                     }
                 }
             }"
             @click.stop>
            <!-- Header -->
            <div class="modal-header border-b border-border px-6 py-4">
                <div>
                    <h5 class="modal-title font-bold text-xl">Edit Package</h5>
                    <p class="text-muted-foreground text-sm mb-0">Update your membership package details</p>
                </div>
                <button type="button" class="btn-close" @click="showEditPackageModal = false"></button>
            </div>

            <!-- Tabs Navigation -->
            <div class="border-b border-border bg-muted/30 px-6">
                <ul class="nav nav-tabs border-0 flex" role="tablist">
                    <li class="flex-1" role="presentation">
                        <button class="nav-link w-full flex items-center justify-center px-3 py-3"
                                :class="{ 'active': currentTab === 'basic' }"
                                @click="currentTab = 'basic'"
                                type="button">
                            <span class="hidden sm:inline">Basic Info</span>
                            <span class="sm:hidden">Basic</span>
                        </button>
                    </li>
                    <li class="flex-1" role="presentation">
                        <button class="nav-link w-full flex items-center justify-center px-3 py-3"
                                :class="{ 'active': currentTab === 'schedules' }"
                                @click="currentTab = 'schedules'"
                                type="button">
                            <span class="hidden sm:inline">Schedules</span>
                            <span class="sm:hidden">Schedule</span>
                        </button>
                    </li>
                    <li class="flex-1" role="presentation">
                        <button class="nav-link w-full flex items-center justify-center px-3 py-3"
                                :class="{ 'active': currentTab === 'trainers' }"
                                @click="currentTab = 'trainers'; if(window.updateEditTrainerAssignmentsUI) updateEditTrainerAssignmentsUI();"
                                type="button">
                            <span>Trainers</span>
                        </button>
                    </li>
                    <li class="flex-1" role="presentation">
                        <button class="nav-link w-full flex items-center justify-center px-3 py-3"
                                :class="{ 'active': currentTab === 'pricing' }"
                                @click="currentTab = 'pricing'"
                                type="button">
                            <span>Pricing</span>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Body -->
            <div class="modal-body px-6 py-6 max-h-[65vh] overflow-y-auto">
                <form id="editPackageForm" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <!-- Tab 1: Basic Info -->
                    <div x-show="currentTab === 'basic'" x-cloak>
                        <!-- Package Image Section -->
                        <div class="card border-0 shadow-sm mb-4 overflow-hidden">
                            <div class="card-body p-4 bg-gradient-to-br from-primary/5 to-transparent">
                                <div class="flex items-center gap-2 mb-4">
                                    <div class="section-icon">
                                        <i class="bi bi-upload text-primary"></i>
                                    </div>
                                    <h6 class="mb-0 font-semibold">Package Image</h6>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div id="editPackageCropperContainer">
                                            <x-takeone-cropper
                                                id="editPackageImageCropper"
                                                :width="400"
                                                :height="225"
                                                shape="square"
                                                mode="form"
                                                inputName="image"
                                                folder="packages"
                                                :filename="'package_' . time()"
                                                :previewWidth="300"
                                                :previewHeight="169"
                                                buttonText="Change Image"
                                                buttonClass="btn btn-outline-secondary flex-1"
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <div class="rounded-lg p-3 mb-3 bg-muted/30">
                                            <p class="text-sm font-medium mb-2">Image Guidelines</p>
                                            <ul class="list-none text-sm text-muted-foreground mb-0">
                                                <li class="flex items-center gap-2 mb-1">
                                                    <span class="rounded-full bg-primary w-1.5 h-1.5"></span>
                                                    Recommended ratio: 16:9
                                                </li>
                                                <li class="flex items-center gap-2 mb-1">
                                                    <span class="rounded-full bg-primary w-1.5 h-1.5"></span>
                                                    Maximum file size: 5MB
                                                </li>
                                                <li class="flex items-center gap-2">
                                                    <span class="rounded-full bg-primary w-1.5 h-1.5"></span>
                                                    Formats: JPG, PNG, WebP
                                                </li>
                                            </ul>
                                        </div>

                                        <div class="flex items-center gap-3 p-3 rounded-lg border border-border bg-white">
                                            <div class="form-check form-switch mb-0">
                                                <input type="checkbox" id="editPackagePopular" name="is_popular" class="form-check-input" role="switch">
                                            </div>
                                            <div>
                                                <label for="editPackagePopular" class="form-label mb-0 font-medium cursor-pointer">Featured Package</label>
                                                <p class="text-muted-foreground text-sm mb-0">Highlight this package on the main page</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Package Details Section -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <div class="flex items-center gap-2 mb-4">
                                    <div class="section-icon">
                                        <i class="bi bi-tag text-primary"></i>
                                    </div>
                                    <h6 class="mb-0 font-semibold">Package Details</h6>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="mb-3">
                                            <label for="editPackageName" class="form-label font-medium">
                                                Package Name <span class="text-destructive">*</span>
                                            </label>
                                            <input type="text" id="editPackageName" name="name" required placeholder="e.g., Premium Monthly Membership" class="form-control">
                                        </div>

                                        <div class="mb-3">
                                            <label for="editPackageDuration" class="form-label font-medium">
                                                Duration (Months) <span class="text-destructive">*</span>
                                            </label>
                                            <input type="number" id="editPackageDuration" name="duration_months" required value="1" min="1" class="form-control">
                                        </div>
                                    </div>

                                    <div>
                                        <div class="mb-3">
                                            <label for="editPackageDescription" class="form-label font-medium">Description</label>
                                            <textarea id="editPackageDescription" name="description" rows="5" placeholder="Brief description of what this package includes..." class="form-control resize-none"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Member Restrictions Section -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <div class="flex items-center gap-2 mb-4">
                                    <div class="section-icon">
                                        <i class="bi bi-people text-primary"></i>
                                    </div>
                                    <h6 class="mb-0 font-semibold">Member Restrictions</h6>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <div>
                                        <label for="editPackageGender" class="form-label font-medium">Gender</label>
                                        <select id="editPackageGender" name="gender_restriction" class="form-select">
                                            <option value="mixed">Mixed (All Genders)</option>
                                            <option value="male">Male Only</option>
                                            <option value="female">Female Only</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="editPackageMinAge" class="form-label font-medium">Minimum Age</label>
                                        <input type="number" id="editPackageMinAge" name="age_min" min="0" placeholder="e.g., 5" class="form-control">
                                    </div>
                                    <div>
                                        <label for="editPackageMaxAge" class="form-label font-medium">Maximum Age</label>
                                        <input type="number" id="editPackageMaxAge" name="age_max" min="0" placeholder="e.g., 18" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Schedules -->
                    <div x-show="currentTab === 'schedules'" x-cloak>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="section-icon">
                                        <i class="bi bi-clock text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 font-semibold">Package Schedule <span class="text-destructive">*</span></h6>
                                        <p class="text-muted-foreground text-sm mb-0">Define when this package is available</p>
                                    </div>
                                </div>

                                <!-- Schedule Form -->
                                <div class="rounded-lg border border-border p-4 mb-4 mt-4 bg-muted/10">
                                    <div class="mb-4">
                                        <x-schedule-time-picker
                                            id="editPackageSchedule"
                                            :required="false"
                                        />
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                                        <div>
                                            <label for="editScheduleActivity" class="form-label font-medium">Activity <span class="text-destructive">*</span></label>
                                            <select id="editScheduleActivity" class="form-select">
                                                <option value="">Select activity</option>
                                                @if(isset($activities))
                                                    @foreach($activities as $activity)
                                                        <option value="{{ $activity->id }}" data-name="{{ $activity->title ?? $activity->name }}">{{ $activity->title ?? $activity->name }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>
                                        <div>
                                            <label for="editScheduleNotes" class="form-label font-medium">Notes (Optional)</label>
                                            <input type="text" id="editScheduleNotes" placeholder="Add any additional notes..." class="form-control">
                                        </div>
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="button" id="editAddScheduleBtn" class="btn btn-outline-primary px-4 py-2">
                                            <i class="bi bi-plus-lg mr-2"></i><span id="editAddScheduleBtnText">Add Schedule</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Schedules List -->
                                <div id="editSchedulesList"></div>

                                <div id="editNoSchedulesMessage" class="text-center py-12 border-2 border-dashed border-border rounded-lg">
                                    <i class="bi bi-clock text-muted-foreground text-5xl"></i>
                                    <p class="text-muted-foreground mb-1 mt-3">No schedules added yet</p>
                                    <p class="text-muted-foreground text-sm">Add at least one schedule to continue</p>
                                </div>

                                <input type="hidden" id="editSchedulesData" name="schedules">
                            </div>
                        </div>
                    </div>

                    <!-- Tab 3: Trainers -->
                    <div x-show="currentTab === 'trainers'" x-cloak>
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="section-icon">
                                        <i class="bi bi-person-check text-primary"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0 font-semibold">Assign Trainers</h6>
                                        <p class="text-muted-foreground text-sm mb-0">Assign instructors to scheduled activities</p>
                                    </div>
                                </div>

                                <div id="editTrainerAssignments" class="mt-4">
                                    <div class="text-center py-12 border-2 border-dashed border-border rounded-lg">
                                        <i class="bi bi-person-check text-muted-foreground text-5xl"></i>
                                        <p class="text-muted-foreground mb-1 mt-3">No activities scheduled yet</p>
                                        <p class="text-muted-foreground text-sm">Add schedules in the previous tab first</p>
                                    </div>
                                </div>

                                <input type="hidden" id="editTrainerAssignmentsData" name="trainer_assignments">
                            </div>
                        </div>
                    </div>

                    <!-- Tab 4: Pricing -->
                    <div x-show="currentTab === 'pricing'" x-cloak
                         x-data="{
                             basePrice: 0,
                             discountPercent: 0,
                             get finalPrice() { return this.basePrice * (1 - this.discountPercent / 100) },
                             get showPreview() { return this.basePrice > 0 && this.discountPercent > 0 }
                         }">
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <div class="flex items-center gap-2 mb-4">
                                    <div class="section-icon">
                                        <i class="bi bi-currency-dollar text-primary"></i>
                                    </div>
                                    <h6 class="mb-0 font-semibold">Base Price</h6>
                                </div>

                                <label for="editPackagePrice" class="form-label font-medium">
                                    Package Price ({{ $club->currency ?? 'BHD' }}) <span class="text-destructive">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-muted/30">
                                        <i class="bi bi-currency-dollar text-muted-foreground"></i>
                                    </span>
                                    <input type="number" id="editPackagePrice" name="price" required step="0.01" min="0" placeholder="199.99" class="form-control text-xl" x-model.number="basePrice">
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <div class="flex items-center gap-2 mb-4">
                                    <div class="section-icon">
                                        <i class="bi bi-tag text-primary"></i>
                                    </div>
                                    <h6 class="mb-0 font-semibold">Discount Options</h6>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="editDiscountCode" class="form-label font-medium">Discount Code</label>
                                        <input type="text" id="editDiscountCode" name="discount_code" placeholder="e.g., SAVE20" class="form-control uppercase font-mono">
                                        <p class="text-muted-foreground text-sm mt-1">Optional promo code for customers</p>
                                    </div>
                                    <div>
                                        <label for="editDiscountPercent" class="form-label font-medium">Discount Percentage</label>
                                        <div class="input-group">
                                            <input type="number" id="editDiscountPercent" name="discount_percentage" min="0" max="100" step="0.01" placeholder="20" class="form-control" x-model.number="discountPercent">
                                            <span class="input-group-text bg-muted/30 text-muted-foreground">%</span>
                                        </div>
                                        <p class="text-muted-foreground text-sm mt-1">Percentage off the base price</p>
                                    </div>
                                </div>

                                <div x-show="showPreview" x-cloak class="mt-4 p-4 rounded-xl border-2 border-primary/20 bg-gradient-to-br from-primary/10 to-primary/5">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-muted-foreground text-sm mb-1">Final Price</p>
                                            <p class="text-4xl font-bold text-primary mb-0">{{ $club->currency ?? 'BHD' }} <span x-text="finalPrice.toFixed(2)">0.00</span></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-muted-foreground line-through mb-1">{{ $club->currency ?? 'BHD' }} <span x-text="basePrice.toFixed(2)">0.00</span></p>
                                            <span class="badge bg-primary text-lg" x-text="discountPercent + '% OFF'">0% OFF</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-t border-border px-6 py-4">
                <div class="text-muted-foreground text-sm">
                    Step <span x-text="currentIndex + 1">1</span> of 4
                    <span x-text="' - ' + tabNames[currentIndex]"> - Basic Info</span>
                </div>

                <div class="ml-auto flex gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4" @click="showEditPackageModal = false">Cancel</button>
                    <button type="button" x-show="!isLastTab" class="btn btn-primary px-4" @click="nextTab()">
                        Next Step<i class="bi bi-arrow-right ml-2"></i>
                    </button>
                    <button type="submit" form="editPackageForm" x-show="isLastTab" x-cloak class="btn btn-primary px-4">
                        <i class="bi bi-check-lg mr-2"></i>Update Package
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let editSchedules = [];
    let editTrainerAssignments = {};
    let editEditingScheduleIndex = null;

    // Find the schedule picker inside the edit modal form
    const editForm = document.getElementById('editPackageForm');
    const editSchedulePickerEl = editForm?.querySelector('[data-picker-id]');
    const editSchedulePickerId = editSchedulePickerEl?.dataset.pickerId;

    const editInstructors = @json($instructors ?? []).map(i => ({
        id: i.id,
        name: i.user?.full_name || i.user?.name || 'Unknown'
    }));

    // Schedule management
    const editAddScheduleBtn = document.getElementById('editAddScheduleBtn');
    const editAddScheduleBtnText = document.getElementById('editAddScheduleBtnText');
    const editSchedulesList = document.getElementById('editSchedulesList');
    const editSchedulesDataInput = document.getElementById('editSchedulesData');
    const editNoSchedulesMessage = document.getElementById('editNoSchedulesMessage');

    editAddScheduleBtn?.addEventListener('click', function() {
        const selectedDays = ScheduleTimePicker.getSelectedDays(editSchedulePickerId);
        const startTime = ScheduleTimePicker.getStartTime(editSchedulePickerId);
        const endTime = ScheduleTimePicker.getEndTime(editSchedulePickerId);
        const activitySelect = document.getElementById('editScheduleActivity');
        const activityId = activitySelect.value;
        const activityName = activitySelect.options[activitySelect.selectedIndex]?.dataset.name || '';
        const notes = document.getElementById('editScheduleNotes').value;

        if (selectedDays.length === 0 || !startTime || !endTime || !activityId) {
            alert('Please select at least one day, activity, and specify start/end times');
            return;
        }

        if (endTime <= startTime) {
            alert('End time must be after start time');
            return;
        }

        const schedule = {
            id: editEditingScheduleIndex !== null ? editSchedules[editEditingScheduleIndex].id : Date.now(),
            days: selectedDays,
            startTime,
            endTime,
            activityId,
            activityName,
            notes
        };

        if (editEditingScheduleIndex !== null) {
            editSchedules[editEditingScheduleIndex] = schedule;
            editEditingScheduleIndex = null;
            editAddScheduleBtnText.textContent = 'Add Schedule';
            editAddScheduleBtn.classList.remove('btn-primary');
            editAddScheduleBtn.classList.add('btn-outline-primary');
        } else {
            editSchedules.push(schedule);
        }

        updateEditSchedulesUI();
        updateEditTrainerAssignmentsUI();
        resetEditScheduleForm();
    });

    function formatTimeTo12Hour(time) {
        const [hours, minutes] = time.split(':').map(Number);
        const period = hours >= 12 ? 'PM' : 'AM';
        const displayHours = hours % 12 || 12;
        return `${displayHours}:${minutes.toString().padStart(2, '0')} ${period}`;
    }

    function calcDurationMinutes(startTime, endTime) {
        const [sh, sm] = startTime.split(':').map(Number);
        const [eh, em] = endTime.split(':').map(Number);
        return (eh * 60 + em) - (sh * 60 + sm);
    }

    function updateEditSchedulesUI() {
        if (editSchedules.length === 0) {
            editSchedulesList.innerHTML = '';
            editNoSchedulesMessage.classList.remove('hidden');
            return;
        }

        editNoSchedulesMessage.classList.add('hidden');

        editSchedulesList.innerHTML = `
            <div class="mb-3">
                <label class="form-label font-medium">Added Schedules (${editSchedules.length})</label>
            </div>
            <div class="border border-border rounded-lg overflow-hidden">
                ${editSchedules.map((schedule, index) => `
                    <div class="flex items-start justify-between p-3 ${index < editSchedules.length - 1 ? 'border-b border-border' : ''} schedule-item hover:bg-muted/10 transition-colors">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                ${schedule.days.map(d => `<span class="badge bg-secondary">${d.name}</span>`).join('')}
                                <span class="font-medium">${formatTimeTo12Hour(schedule.startTime)} - ${formatTimeTo12Hour(schedule.endTime)}</span>
                                <span class="badge bg-secondary">${calcDurationMinutes(schedule.startTime, schedule.endTime)} min</span>
                                ${schedule.activityName ? `<span class="badge bg-primary/10 text-primary border border-primary/20"><i class="bi bi-activity mr-1"></i>${schedule.activityName}</span>` : ''}
                            </div>
                            ${schedule.notes ? `<p class="text-muted-foreground text-sm mb-0"><span class="font-medium">Note:</span> ${schedule.notes}</p>` : ''}
                        </div>
                        <div class="flex gap-1 ml-2">
                            <button type="button" class="btn btn-sm btn-light edit-edit-schedule" data-index="${index}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-light text-destructive delete-edit-schedule" data-index="${index}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;

        document.querySelectorAll('.edit-edit-schedule').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                editEditSchedule(index);
            });
        });

        document.querySelectorAll('.delete-edit-schedule').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                editSchedules.splice(index, 1);
                if (editEditingScheduleIndex === index) {
                    editEditingScheduleIndex = null;
                    editAddScheduleBtnText.textContent = 'Add Schedule';
                    editAddScheduleBtn.classList.remove('btn-primary');
                    editAddScheduleBtn.classList.add('btn-outline-primary');
                    resetEditScheduleForm();
                }
                updateEditSchedulesUI();
                updateEditTrainerAssignmentsUI();
            });
        });

        editSchedulesDataInput.value = JSON.stringify(editSchedules);
    }

    function editEditSchedule(index) {
        const schedule = editSchedules[index];
        editEditingScheduleIndex = index;

        ScheduleTimePicker.setSelectedDays(editSchedulePickerId, schedule.days);
        ScheduleTimePicker.setStartTime(editSchedulePickerId, schedule.startTime);
        ScheduleTimePicker.setEndTime(editSchedulePickerId, schedule.endTime);

        document.getElementById('editScheduleActivity').value = schedule.activityId;
        document.getElementById('editScheduleNotes').value = schedule.notes || '';

        editAddScheduleBtnText.textContent = 'Update Schedule';
        editAddScheduleBtn.classList.remove('btn-outline-primary');
        editAddScheduleBtn.classList.add('btn-primary');
    }

    function resetEditScheduleForm() {
        ScheduleTimePicker.reset(editSchedulePickerId);
        document.getElementById('editScheduleActivity').value = '';
        document.getElementById('editScheduleNotes').value = '';
    }

    // Trainer assignments
    const editTrainerContainer = document.getElementById('editTrainerAssignments');
    const editTrainerDataInput = document.getElementById('editTrainerAssignmentsData');

    window.updateEditTrainerAssignmentsUI = function() {
        const activityMap = {};
        editSchedules.forEach(schedule => {
            if (schedule.activityId && !activityMap[schedule.activityId]) {
                activityMap[schedule.activityId] = {
                    id: schedule.activityId,
                    name: schedule.activityName
                };
            }
        });

        const uniqueActivities = Object.values(activityMap);

        if (uniqueActivities.length === 0) {
            editTrainerContainer.innerHTML = `
                <div class="text-center py-12 border-2 border-dashed border-border rounded-lg">
                    <i class="bi bi-person-check text-muted-foreground text-5xl"></i>
                    <p class="text-muted-foreground mb-1 mt-3">No activities scheduled yet</p>
                    <p class="text-muted-foreground text-sm">Add schedules in the previous tab first</p>
                </div>
            `;
            return;
        }

        editTrainerContainer.innerHTML = `
            <p class="text-muted-foreground text-sm mb-4">Activities from your schedules:</p>
            ${uniqueActivities.map(activity => `
                <div class="flex items-center gap-3 p-4 border border-border rounded-lg mb-3 bg-muted/10">
                    <div class="flex-1">
                        <p class="font-semibold mb-0">${activity.name}</p>
                    </div>
                    <div class="w-64">
                        <select class="form-select edit-trainer-assignment" data-activity-id="${activity.id}">
                            <option value="">Select instructor</option>
                            ${editInstructors.map(i => `<option value="${i.id}" ${editTrainerAssignments[activity.id] == i.id ? 'selected' : ''}>${i.name}</option>`).join('')}
                        </select>
                    </div>
                </div>
            `).join('')}
        `;

        document.querySelectorAll('.edit-trainer-assignment').forEach(select => {
            select.addEventListener('change', function() {
                const activityId = this.dataset.activityId;
                if (this.value) {
                    editTrainerAssignments[activityId] = this.value;
                } else {
                    delete editTrainerAssignments[activityId];
                }
                editTrainerDataInput.value = JSON.stringify(editTrainerAssignments);
            });
        });
    };

    // Populate edit form with package data
    window.populateEditPackageForm = function(pkg) {
        // Set form action
        document.getElementById('editPackageForm').action =
            `{{ url('admin/club/' . $club->slug . '/packages') }}/${pkg.id}`;

        // Basic info
        document.getElementById('editPackageName').value = pkg.name || '';
        document.getElementById('editPackageDescription').value = pkg.description || '';
        document.getElementById('editPackageDuration').value = pkg.duration_months || 1;
        document.getElementById('editPackageGender').value = pkg.gender || 'mixed';
        document.getElementById('editPackageMinAge').value = pkg.age_min || '';
        document.getElementById('editPackageMaxAge').value = pkg.age_max || '';
        document.getElementById('editPackagePopular').checked = !!pkg.is_popular;

        // Pricing
        document.getElementById('editPackagePrice').value = pkg.price || '';
        document.getElementById('editPackagePrice').dispatchEvent(new Event('input'));

        // Image - update cropper preview
        const editPreviewContainer = $('#previewContainer_editPackageImageCropper');
        if (pkg.cover_image) {
            const imgUrl = '{{ asset("storage") }}/' + pkg.cover_image;
            editPreviewContainer.html(`
                <img src="${imgUrl}" id="preview_editPackageImageCropper" class="cropper-preview-image" style="width: 300px; height: 169px; border-radius: 8px;">
                <button type="button" class="cropper-remove-btn" id="removeBtn_editPackageImageCropper" onclick="removeImage_editPackageImageCropper()"><i class="bi bi-x"></i></button>
            `);
            editPreviewContainer.addClass('has-image');
        } else {
            editPreviewContainer.html(`
                <div id="preview_editPackageImageCropper" class="cropper-preview-placeholder" style="width: 300px; height: 169px; border-radius: 8px;">
                    <i class="bi bi-image" style="font-size: 2rem;"></i>
                </div>
            `);
            editPreviewContainer.removeClass('has-image');
        }
        // Clear hidden input so old image is kept unless user crops new one
        $('#hiddenInput_editPackageImageCropper').val('');

        // Reconstruct schedules from package activities
        editSchedules = [];
        editTrainerAssignments = {};

        if (pkg.activities && pkg.activities.length > 0) {
            const dayAbbr = {
                'saturday': 'Sat', 'sunday': 'Sun', 'monday': 'Mon',
                'tuesday': 'Tue', 'wednesday': 'Wed', 'thursday': 'Thu', 'friday': 'Fri'
            };

            pkg.activities.forEach(activity => {
                // Build trainer assignment
                if (activity.instructor_id) {
                    editTrainerAssignments[activity.id] = activity.instructor_id;
                }

                // Build schedule entries from activity's schedule data
                if (activity.schedule && activity.schedule.length > 0) {
                    const days = activity.schedule.map(s => {
                        const dayValue = s.day || s.day_of_week || '';
                        return {
                            value: dayValue.toLowerCase(),
                            name: dayAbbr[dayValue.toLowerCase()] || dayValue.substring(0, 3)
                        };
                    });

                    // Group by time
                    const timeGroups = {};
                    activity.schedule.forEach(s => {
                        const start = s.start_time || s.startTime || '';
                        const end = s.end_time || s.endTime || '';
                        const day = s.day || s.day_of_week || '';
                        const key = `${start}-${end}`;
                        if (!timeGroups[key]) {
                            timeGroups[key] = { days: [], startTime: start, endTime: end };
                        }
                        timeGroups[key].days.push({
                            value: day.toLowerCase(),
                            name: dayAbbr[day.toLowerCase()] || day.substring(0, 3)
                        });
                    });

                    Object.values(timeGroups).forEach(group => {
                        editSchedules.push({
                            id: Date.now() + Math.random(),
                            days: group.days,
                            startTime: group.startTime,
                            endTime: group.endTime,
                            activityId: String(activity.id),
                            activityName: activity.title || activity.name,
                            notes: ''
                        });
                    });
                } else {
                    // Activity has no schedule data — add a placeholder entry
                    editSchedules.push({
                        id: Date.now() + Math.random(),
                        days: [],
                        startTime: '',
                        endTime: '',
                        activityId: String(activity.id),
                        activityName: activity.title || activity.name,
                        notes: ''
                    });
                }
            });
        }

        // Update trainer assignments hidden input
        editTrainerDataInput.value = JSON.stringify(editTrainerAssignments);

        // Update UIs
        updateEditSchedulesUI();
    };
});
</script>
@endpush
