<!-- Add Package Modal -->
<div class="modal fade" id="addPackageModal" tabindex="-1" aria-labelledby="addPackageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content border-0 shadow-lg">
            <!-- Header -->
            <div class="modal-header border-bottom px-4 py-3">
                <div>
                    <h5 class="modal-title fw-bold fs-4" id="addPackageModalLabel">Create New Package</h5>
                    <p class="text-muted small mb-0">Configure your membership package with activities, pricing, and availability</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Tabs Navigation -->
            <div class="border-bottom bg-light px-4">
                <ul class="nav nav-tabs border-0" id="packageTabs" role="tablist">
                    <li class="nav-item flex-fill" role="presentation">
                        <button class="nav-link active d-flex align-items-center justify-content-center px-3 py-3 w-100" id="basic-tab" data-bs-toggle="tab" data-bs-target="#tab-basic" type="button" role="tab">
                            <span class="d-none d-sm-inline">Basic Info</span>
                            <span class="d-sm-none">Basic</span>
                        </button>
                    </li>
                    <li class="nav-item flex-fill" role="presentation">
                        <button class="nav-link d-flex align-items-center justify-content-center px-3 py-3 w-100" id="schedules-tab" data-bs-toggle="tab" data-bs-target="#tab-schedules" type="button" role="tab">
                            <span class="d-none d-sm-inline">Schedules</span>
                            <span class="d-sm-none">Schedule</span>
                        </button>
                    </li>
                    <li class="nav-item flex-fill" role="presentation">
                        <button class="nav-link d-flex align-items-center justify-content-center px-3 py-3 w-100" id="trainers-tab" data-bs-toggle="tab" data-bs-target="#tab-trainers" type="button" role="tab">
                            <span>Trainers</span>
                        </button>
                    </li>
                    <li class="nav-item flex-fill" role="presentation">
                        <button class="nav-link d-flex align-items-center justify-content-center px-3 py-3 w-100" id="pricing-tab" data-bs-toggle="tab" data-bs-target="#tab-pricing" type="button" role="tab">
                            <span>Pricing</span>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Body -->
            <div class="modal-body px-4 py-4" style="max-height: 65vh; overflow-y: auto;">
                <form id="addPackageForm" action="{{ route('admin.club.packages.store', $club->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="tab-content" id="packageTabContent">
                        <!-- Tab 1: Basic Info -->
                        <div class="tab-pane fade show active" id="tab-basic" role="tabpanel">
                            <!-- Package Image Section -->
                            <div class="card border-0 shadow-sm mb-4 overflow-hidden">
                                <div class="card-body p-4" style="background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), 0.05) 0%, transparent 100%);">
                                    <div class="d-flex align-items-center gap-2 mb-4">
                                        <div class="section-icon">
                                            <i class="bi bi-upload text-primary"></i>
                                        </div>
                                        <h6 class="mb-0 fw-semibold">Package Image</h6>
                                    </div>

                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <div id="packageImagePreview" class="position-relative w-100 rounded-4 overflow-hidden border-2 bg-light d-flex align-items-center justify-content-center" style="aspect-ratio: 16/9; border: 2px dashed #dee2e6;">
                                                <div class="text-center text-muted">
                                                    <i class="bi bi-image" style="font-size: 2.5rem;"></i>
                                                    <p class="small mb-0 mt-2">No image uploaded</p>
                                                </div>
                                            </div>
                                            <input type="file" id="packageImage" name="image" accept="image/*" class="d-none">
                                            <div class="d-flex gap-2 mt-3">
                                                <button type="button" onclick="document.getElementById('packageImage').click()" class="btn btn-outline-secondary flex-grow-1">
                                                    <i class="bi bi-upload me-2"></i>Upload
                                                </button>
                                                <button type="button" id="removeImageBtn" class="btn btn-outline-danger d-none">
                                                    Remove
                                                </button>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="rounded-3 p-3 mb-3" style="background: rgba(var(--bs-secondary-rgb), 0.1);">
                                                <p class="small fw-medium mb-2">Image Guidelines</p>
                                                <ul class="list-unstyled small text-muted mb-0">
                                                    <li class="d-flex align-items-center gap-2 mb-1">
                                                        <span class="rounded-circle bg-primary" style="width: 6px; height: 6px;"></span>
                                                        Recommended ratio: 16:9
                                                    </li>
                                                    <li class="d-flex align-items-center gap-2 mb-1">
                                                        <span class="rounded-circle bg-primary" style="width: 6px; height: 6px;"></span>
                                                        Maximum file size: 5MB
                                                    </li>
                                                    <li class="d-flex align-items-center gap-2">
                                                        <span class="rounded-circle bg-primary" style="width: 6px; height: 6px;"></span>
                                                        Formats: JPG, PNG, WebP
                                                    </li>
                                                </ul>
                                            </div>

                                            <div class="d-flex align-items-center gap-3 p-3 rounded-3 border bg-white">
                                                <div class="form-check form-switch mb-0">
                                                    <input type="checkbox" id="packagePopular" name="is_popular" class="form-check-input" role="switch">
                                                </div>
                                                <div>
                                                    <label for="packagePopular" class="form-label mb-0 fw-medium" style="cursor: pointer;">Featured Package</label>
                                                    <p class="text-muted small mb-0">Highlight this package on the main page</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Package Details Section -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center gap-2 mb-4">
                                        <div class="section-icon">
                                            <i class="bi bi-tag text-primary"></i>
                                        </div>
                                        <h6 class="mb-0 fw-semibold">Package Details</h6>
                                    </div>

                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="packageName" class="form-label fw-medium">
                                                    Package Name <span class="text-danger">*</span>
                                                </label>
                                                <input type="text" id="packageName" name="name" required placeholder="e.g., Premium Monthly Membership" class="form-control form-control-lg">
                                            </div>

                                            <div class="mb-3">
                                                <label for="packageDuration" class="form-label fw-medium">
                                                    Duration (Months) <span class="text-danger">*</span>
                                                </label>
                                                <input type="number" id="packageDuration" name="duration_months" required value="1" min="1" class="form-control form-control-lg">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="packageDescription" class="form-label fw-medium">Description</label>
                                                <textarea id="packageDescription" name="description" rows="5" placeholder="Brief description of what this package includes..." class="form-control" style="resize: none;"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Facility & Capacity Section -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center gap-2 mb-4">
                                        <div class="section-icon">
                                            <i class="bi bi-geo-alt text-primary"></i>
                                        </div>
                                        <h6 class="mb-0 fw-semibold">Facility & Capacity</h6>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="packageFacility" class="form-label fw-medium">Facility</label>
                                            <select id="packageFacility" name="facility_id" class="form-select form-select-lg">
                                                <option value="">Select facility</option>
                                                @if(isset($facilities))
                                                    @foreach($facilities as $facility)
                                                        <option value="{{ $facility->id }}">{{ $facility->name }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="packageCapacity" class="form-label fw-medium">Max Capacity</label>
                                            <input type="number" id="packageCapacity" name="max_capacity" min="1" placeholder="e.g., 20" class="form-control form-control-lg">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="packageScheduleType" class="form-label fw-medium">Schedule Type</label>
                                            <select id="packageScheduleType" name="schedule_type" class="form-select form-select-lg">
                                                <option value="fixed">Fixed</option>
                                                <option value="flexible">Flexible</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Member Restrictions Section -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center gap-2 mb-4">
                                        <div class="section-icon">
                                            <i class="bi bi-people text-primary"></i>
                                        </div>
                                        <h6 class="mb-0 fw-semibold">Member Restrictions</h6>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="packageGender" class="form-label fw-medium">Gender</label>
                                            <select id="packageGender" name="gender_restriction" class="form-select form-select-lg">
                                                <option value="mixed">Mixed (All Genders)</option>
                                                <option value="male">Male Only</option>
                                                <option value="female">Female Only</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="packageMinAge" class="form-label fw-medium">Minimum Age</label>
                                            <input type="number" id="packageMinAge" name="age_min" min="0" placeholder="e.g., 5" class="form-control form-control-lg">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="packageMaxAge" class="form-label fw-medium">Maximum Age</label>
                                            <input type="number" id="packageMaxAge" name="age_max" min="0" placeholder="e.g., 18" class="form-control form-control-lg">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Availability Period Section -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="section-icon">
                                                <i class="bi bi-calendar-event text-primary"></i>
                                            </div>
                                            <h6 class="mb-0 fw-semibold">Availability Period</h6>
                                        </div>

                                        <div class="d-flex align-items-center gap-2">
                                            <div class="form-check form-switch mb-0">
                                                <input type="checkbox" id="alwaysAvailable" name="always_available" class="form-check-input" role="switch" checked>
                                            </div>
                                            <label for="alwaysAvailable" class="form-label mb-0 fw-medium" style="cursor: pointer;">Always Available</label>
                                        </div>
                                    </div>

                                    <div id="dateRangeFields" class="row g-4 d-none">
                                        <div class="col-md-6">
                                            <label for="packageStartDate" class="form-label fw-medium">Start Date</label>
                                            <input type="date" id="packageStartDate" name="start_date" class="form-control form-control-lg">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="packageEndDate" class="form-label fw-medium">End Date</label>
                                            <input type="date" id="packageEndDate" name="end_date" class="form-control form-control-lg">
                                        </div>
                                    </div>

                                    <div id="alwaysAvailableMessage" class="text-center p-4 rounded-3 border" style="background: rgba(var(--bs-primary-rgb), 0.05); border-color: rgba(var(--bs-primary-rgb), 0.2) !important;">
                                        <p class="text-muted mb-0">This package is available year-round with no date restrictions</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 2: Schedules -->
                        <div class="tab-pane fade" id="tab-schedules" role="tabpanel">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="section-icon">
                                            <i class="bi bi-clock text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0 fw-semibold">Package Schedule <span class="text-danger">*</span></h6>
                                            <p class="text-muted small mb-0">Define when this package is available</p>
                                        </div>
                                    </div>

                                    <!-- Schedule Form -->
                                    <div class="rounded-3 border p-4 mb-4 mt-4" style="background: rgba(var(--bs-secondary-rgb), 0.05);">
                                        <!-- Days, Start Time, End Time - Using Component -->
                                        <div class="mb-4">
                                            <x-schedule-time-picker
                                                id="packageSchedule"
                                                :required="true"
                                            />
                                        </div>

                                        <!-- Activity & Notes - 2 column grid -->
                                        <div class="row g-3 mb-4">
                                            <div class="col-md-6">
                                                <label for="scheduleActivity" class="form-label fw-medium">Activity <span class="text-danger">*</span></label>
                                                <select id="scheduleActivity" class="form-select form-select-lg">
                                                    <option value="">Select activity</option>
                                                    @if(isset($activities))
                                                        @foreach($activities as $activity)
                                                            <option value="{{ $activity->id }}" data-name="{{ $activity->title ?? $activity->name }}">{{ $activity->title ?? $activity->name }}</option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="scheduleNotes" class="form-label fw-medium">Notes (Optional)</label>
                                                <input type="text" id="scheduleNotes" placeholder="Add any additional notes..." class="form-control form-control-lg">
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end">
                                            <button type="button" id="addScheduleBtn" class="btn btn-outline-primary px-4 py-2">
                                                <i class="bi bi-plus-lg me-2"></i><span id="addScheduleBtnText">Add Schedule</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Schedules List -->
                                    <div id="schedulesList">
                                        <!-- Schedules will be added here dynamically -->
                                    </div>

                                    <div id="noSchedulesMessage" class="text-center py-5 border-2 rounded-3" style="border: 2px dashed #dee2e6;">
                                        <i class="bi bi-clock text-muted" style="font-size: 3rem;"></i>
                                        <p class="text-muted mb-1 mt-3">No schedules added yet</p>
                                        <p class="text-muted small">Add at least one schedule to continue</p>
                                    </div>

                                    <!-- Hidden input for schedules data -->
                                    <input type="hidden" id="schedulesData" name="schedules">
                                </div>
                            </div>
                        </div>

                        <!-- Tab 3: Trainers -->
                        <div class="tab-pane fade" id="tab-trainers" role="tabpanel">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="section-icon">
                                            <i class="bi bi-person-check text-primary"></i>
                                        </div>
                                        <div>
                                            <h6 class="mb-0 fw-semibold">Assign Trainers</h6>
                                            <p class="text-muted small mb-0">Assign instructors to scheduled activities</p>
                                        </div>
                                    </div>

                                    <div id="trainerAssignments" class="mt-4">
                                        <!-- Will be populated based on schedules -->
                                        <div class="text-center py-5 border-2 rounded-3" style="border: 2px dashed #dee2e6;">
                                            <i class="bi bi-person-check text-muted" style="font-size: 3rem;"></i>
                                            <p class="text-muted mb-1 mt-3">No activities scheduled yet</p>
                                            <p class="text-muted small">Add schedules in the previous tab first</p>
                                        </div>
                                    </div>

                                    <!-- Hidden input for trainer assignments data -->
                                    <input type="hidden" id="trainerAssignmentsData" name="trainer_assignments">
                                </div>
                            </div>
                        </div>

                        <!-- Tab 4: Pricing -->
                        <div class="tab-pane fade" id="tab-pricing" role="tabpanel">
                            <!-- Base Price Section -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center gap-2 mb-4">
                                        <div class="section-icon">
                                            <i class="bi bi-currency-dollar text-primary"></i>
                                        </div>
                                        <h6 class="mb-0 fw-semibold">Base Price</h6>
                                    </div>

                                    <label for="packagePrice" class="form-label fw-medium">
                                        Package Price ({{ $club->currency ?? 'BHD' }}) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group input-group-lg">
                                        <span class="input-group-text bg-light">
                                            <i class="bi bi-currency-dollar text-muted"></i>
                                        </span>
                                        <input type="number" id="packagePrice" name="price" required step="0.01" min="0" placeholder="199.99" class="form-control form-control-lg fs-4">
                                    </div>
                                </div>
                            </div>

                            <!-- Discount Section -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center gap-2 mb-4">
                                        <div class="section-icon">
                                            <i class="bi bi-tag text-primary"></i>
                                        </div>
                                        <h6 class="mb-0 fw-semibold">Discount Options</h6>
                                    </div>

                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <label for="discountCode" class="form-label fw-medium">Discount Code</label>
                                            <input type="text" id="discountCode" name="discount_code" placeholder="e.g., SAVE20" class="form-control form-control-lg text-uppercase font-monospace">
                                            <p class="text-muted small mt-1">Optional promo code for customers</p>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="discountPercent" class="form-label fw-medium">Discount Percentage</label>
                                            <div class="input-group input-group-lg">
                                                <input type="number" id="discountPercent" name="discount_percentage" min="0" max="100" step="0.01" placeholder="20" class="form-control form-control-lg">
                                                <span class="input-group-text bg-light text-muted">%</span>
                                            </div>
                                            <p class="text-muted small mt-1">Percentage off the base price</p>
                                        </div>
                                    </div>

                                    <!-- Final Price Preview -->
                                    <div id="finalPricePreview" class="d-none mt-4 p-4 rounded-4 border-2" style="background: linear-gradient(135deg, rgba(var(--bs-primary-rgb), 0.1) 0%, rgba(var(--bs-primary-rgb), 0.05) 100%); border: 2px solid rgba(var(--bs-primary-rgb), 0.2);">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <p class="text-muted small mb-1">Final Price</p>
                                                <p id="displayFinalPrice" class="fs-1 fw-bold text-primary mb-0">{{ $club->currency ?? 'BHD' }} 0.00</p>
                                            </div>
                                            <div class="text-end">
                                                <p id="displayOriginalPrice" class="text-muted text-decoration-line-through mb-1">{{ $club->currency ?? 'BHD' }} 0.00</p>
                                                <span id="displayDiscountBadge" class="badge bg-primary fs-6">0% OFF</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-top px-4 py-3">
                <div class="text-muted small">
                    Step <span id="currentStep">1</span> of 4
                    <span id="stepName"> - Basic Info</span>
                </div>

                <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="nextTabBtn" class="btn btn-primary px-4">
                        Next Step<i class="bi bi-arrow-right ms-2"></i>
                    </button>
                    <button type="submit" form="addPackageForm" id="submitPackageBtn" class="btn btn-primary px-4 d-none">
                        <i class="bi bi-plus-lg me-2"></i>Create Package
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#packageTabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    color: #6c757d;
    font-weight: 500;
}
#packageTabs .nav-link:hover {
    color: var(--bs-primary);
}
#packageTabs .nav-link.active {
    border-bottom-color: var(--bs-primary);
    color: var(--bs-primary);
}
.form-control-lg, .form-select-lg {
    padding: 0.75rem 1rem;
}
/* Ensure section header icons display properly */
.section-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    background-color: rgba(var(--bs-primary-rgb), 0.1);
}
.section-icon i {
    font-size: 1.125rem;
}
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('addPackageModal');
    const tabButtons = document.querySelectorAll('#packageTabs button[data-bs-toggle="tab"]');
    const nextBtn = document.getElementById('nextTabBtn');
    const submitBtn = document.getElementById('submitPackageBtn');
    const currentStepEl = document.getElementById('currentStep');
    const stepNameEl = document.getElementById('stepName');
    const tabOrder = ['basic', 'schedules', 'trainers', 'pricing'];
    const stepNames = [' - Basic Info', ' - Schedules', ' - Assign Trainers', ' - Pricing & Confirm'];
    let currentTabIndex = 0;
    let schedules = [];
    let trainerAssignments = {};
    let editingScheduleIndex = null;
    const currency = '{{ $club->currency ?? "BHD" }}';

    // Update navigation buttons
    function updateNavButtons() {
        nextBtn.classList.toggle('d-none', currentTabIndex === tabOrder.length - 1);
        submitBtn.classList.toggle('d-none', currentTabIndex !== tabOrder.length - 1);
        currentStepEl.textContent = currentTabIndex + 1;
        stepNameEl.textContent = stepNames[currentTabIndex];
    }

    // Tab change event
    tabButtons.forEach((btn, index) => {
        btn.addEventListener('shown.bs.tab', function() {
            currentTabIndex = index;
            updateNavButtons();

            if (tabOrder[index] === 'trainers') {
                updateTrainerAssignmentsUI();
            }
        });
    });

    nextBtn.addEventListener('click', () => {
        if (currentTabIndex < tabOrder.length - 1) {
            const nextTab = new bootstrap.Tab(tabButtons[currentTabIndex + 1]);
            nextTab.show();
        }
    });

    // Package Image Preview
    const packageImageInput = document.getElementById('packageImage');
    const packageImagePreview = document.getElementById('packageImagePreview');
    const removeImageBtn = document.getElementById('removeImageBtn');

    packageImageInput?.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                packageImagePreview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
                packageImagePreview.style.borderStyle = 'solid';
                removeImageBtn.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        }
    });

    removeImageBtn?.addEventListener('click', function() {
        packageImageInput.value = '';
        packageImagePreview.innerHTML = `
            <div class="text-center text-muted">
                <i class="bi bi-image" style="font-size: 2.5rem;"></i>
                <p class="small mb-0 mt-2">No image uploaded</p>
            </div>
        `;
        packageImagePreview.style.borderStyle = 'dashed';
        this.classList.add('d-none');
    });

    // Always Available Toggle
    const alwaysAvailableCheckbox = document.getElementById('alwaysAvailable');
    const dateRangeFields = document.getElementById('dateRangeFields');
    const alwaysAvailableMessage = document.getElementById('alwaysAvailableMessage');

    alwaysAvailableCheckbox?.addEventListener('change', function() {
        if (this.checked) {
            dateRangeFields.classList.add('d-none');
            alwaysAvailableMessage.classList.remove('d-none');
            document.getElementById('packageStartDate').value = '';
            document.getElementById('packageEndDate').value = '';
        } else {
            dateRangeFields.classList.remove('d-none');
            alwaysAvailableMessage.classList.add('d-none');
        }
    });

    // Schedule Time Picker ID
    const schedulePickerId = document.querySelector('[data-picker-id]')?.dataset.pickerId;

    // Add Schedule
    const addScheduleBtn = document.getElementById('addScheduleBtn');
    const addScheduleBtnText = document.getElementById('addScheduleBtnText');
    const schedulesList = document.getElementById('schedulesList');
    const schedulesDataInput = document.getElementById('schedulesData');
    const noSchedulesMessage = document.getElementById('noSchedulesMessage');

    addScheduleBtn?.addEventListener('click', function() {
        const selectedDays = ScheduleTimePicker.getSelectedDays(schedulePickerId);
        const startTime = ScheduleTimePicker.getStartTime(schedulePickerId);
        const endTime = ScheduleTimePicker.getEndTime(schedulePickerId);
        const activitySelect = document.getElementById('scheduleActivity');
        const activityId = activitySelect.value;
        const activityName = activitySelect.options[activitySelect.selectedIndex]?.dataset.name || '';
        const notes = document.getElementById('scheduleNotes').value;

        if (selectedDays.length === 0 || !startTime || !endTime || !activityId) {
            alert('Please select at least one day, activity, and specify start/end times');
            return;
        }

        if (endTime <= startTime) {
            alert('End time must be after start time');
            return;
        }

        const schedule = {
            id: editingScheduleIndex !== null ? schedules[editingScheduleIndex].id : Date.now(),
            days: selectedDays,
            startTime,
            endTime,
            activityId,
            activityName,
            notes
        };

        if (editingScheduleIndex !== null) {
            schedules[editingScheduleIndex] = schedule;
            editingScheduleIndex = null;
            addScheduleBtnText.textContent = 'Add Schedule';
            addScheduleBtn.classList.remove('btn-primary');
            addScheduleBtn.classList.add('btn-outline-primary');
        } else {
            schedules.push(schedule);
        }

        updateSchedulesUI();
        resetScheduleForm();
    });

    function formatTimeTo12Hour(time) {
        const [hours, minutes] = time.split(':').map(Number);
        const period = hours >= 12 ? 'PM' : 'AM';
        const displayHours = hours % 12 || 12;
        return `${displayHours}:${minutes.toString().padStart(2, '0')} ${period}`;
    }

    function updateSchedulesUI() {
        if (schedules.length === 0) {
            schedulesList.innerHTML = '';
            noSchedulesMessage.classList.remove('d-none');
            return;
        }

        noSchedulesMessage.classList.add('d-none');

        schedulesList.innerHTML = `
            <div class="mb-3">
                <label class="form-label fw-medium">Added Schedules (${schedules.length})</label>
            </div>
            <div class="border rounded-3 overflow-hidden">
                ${schedules.map((schedule, index) => `
                    <div class="d-flex align-items-start justify-content-between p-3 ${index < schedules.length - 1 ? 'border-bottom' : ''} schedule-item" style="transition: background 0.2s;">
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                ${schedule.days.map(d => `<span class="badge bg-secondary">${d.name}</span>`).join('')}
                                <span class="fw-medium">${formatTimeTo12Hour(schedule.startTime)} - ${formatTimeTo12Hour(schedule.endTime)}</span>
                                ${schedule.activityName ? `<span class="badge bg-primary bg-opacity-10 text-primary border"><i class="bi bi-activity me-1"></i>${schedule.activityName}</span>` : ''}
                            </div>
                            ${schedule.notes ? `<p class="text-muted small mb-0"><span class="fw-medium">Note:</span> ${schedule.notes}</p>` : ''}
                        </div>
                        <div class="d-flex gap-1 ms-2">
                            <button type="button" class="btn btn-sm btn-light edit-schedule" data-index="${index}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-light text-danger delete-schedule" data-index="${index}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;

        // Add hover effect
        document.querySelectorAll('.schedule-item').forEach(item => {
            item.addEventListener('mouseenter', () => item.style.background = 'rgba(var(--bs-secondary-rgb), 0.05)');
            item.addEventListener('mouseleave', () => item.style.background = '');
        });

        // Add edit handlers
        document.querySelectorAll('.edit-schedule').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                editSchedule(index);
            });
        });

        // Add delete handlers
        document.querySelectorAll('.delete-schedule').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                const scheduleId = schedules[index].id;
                schedules.splice(index, 1);
                delete trainerAssignments[scheduleId];
                if (editingScheduleIndex === index) {
                    editingScheduleIndex = null;
                    addScheduleBtnText.textContent = 'Add Schedule';
                    addScheduleBtn.classList.remove('btn-primary');
                    addScheduleBtn.classList.add('btn-outline-primary');
                    resetScheduleForm();
                }
                updateSchedulesUI();
            });
        });

        schedulesDataInput.value = JSON.stringify(schedules);
    }

    function editSchedule(index) {
        const schedule = schedules[index];
        editingScheduleIndex = index;

        // Use ScheduleTimePicker helper functions
        ScheduleTimePicker.setSelectedDays(schedulePickerId, schedule.days);
        ScheduleTimePicker.setStartTime(schedulePickerId, schedule.startTime);
        ScheduleTimePicker.setEndTime(schedulePickerId, schedule.endTime);

        document.getElementById('scheduleActivity').value = schedule.activityId;
        document.getElementById('scheduleNotes').value = schedule.notes || '';

        addScheduleBtnText.textContent = 'Update Schedule';
        addScheduleBtn.classList.remove('btn-outline-primary');
        addScheduleBtn.classList.add('btn-primary');
    }

    function resetScheduleForm() {
        ScheduleTimePicker.reset(schedulePickerId);
        document.getElementById('scheduleActivity').value = '';
        document.getElementById('scheduleNotes').value = '';
    }

    // Trainer Assignments
    const trainerAssignmentsContainer = document.getElementById('trainerAssignments');
    const trainerAssignmentsDataInput = document.getElementById('trainerAssignmentsData');
    const instructors = @json($instructors ?? []);

    function updateTrainerAssignmentsUI() {
        // Get unique activities from schedules
        const activityMap = {};
        schedules.forEach(schedule => {
            if (schedule.activityId && !activityMap[schedule.activityId]) {
                activityMap[schedule.activityId] = {
                    id: schedule.activityId,
                    name: schedule.activityName
                };
            }
        });

        const uniqueActivities = Object.values(activityMap);

        if (uniqueActivities.length === 0) {
            trainerAssignmentsContainer.innerHTML = `
                <div class="text-center py-5 border-2 rounded-3" style="border: 2px dashed #dee2e6;">
                    <i class="bi bi-person-check text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mb-1 mt-3">No activities scheduled yet</p>
                    <p class="text-muted small">Add schedules in the previous tab first</p>
                </div>
            `;
            return;
        }

        trainerAssignmentsContainer.innerHTML = `
            <p class="text-muted small mb-4">Activities from your schedules:</p>
            ${uniqueActivities.map(activity => `
                <div class="d-flex align-items-center gap-3 p-4 border rounded-3 mb-3" style="background: rgba(var(--bs-secondary-rgb), 0.05);">
                    <div class="flex-grow-1">
                        <p class="fw-semibold mb-0">${activity.name}</p>
                    </div>
                    <div style="width: 250px;">
                        <select class="form-select trainer-assignment" data-activity-id="${activity.id}">
                            <option value="">Select instructor</option>
                            ${instructors.map(i => `<option value="${i.id}" ${trainerAssignments[activity.id] == i.id ? 'selected' : ''}>${i.name}</option>`).join('')}
                        </select>
                    </div>
                </div>
            `).join('')}
        `;

        // Add change handlers
        document.querySelectorAll('.trainer-assignment').forEach(select => {
            select.addEventListener('change', function() {
                const activityId = this.dataset.activityId;
                if (this.value) {
                    trainerAssignments[activityId] = this.value;
                } else {
                    delete trainerAssignments[activityId];
                }
                trainerAssignmentsDataInput.value = JSON.stringify(trainerAssignments);
            });
        });
    }

    // Pricing
    const basePriceInput = document.getElementById('packagePrice');
    const discountPercentInput = document.getElementById('discountPercent');
    const finalPricePreview = document.getElementById('finalPricePreview');
    const displayFinalPrice = document.getElementById('displayFinalPrice');
    const displayOriginalPrice = document.getElementById('displayOriginalPrice');
    const displayDiscountBadge = document.getElementById('displayDiscountBadge');

    function updatePriceDisplay() {
        const basePrice = parseFloat(basePriceInput.value) || 0;
        const discountPercent = parseFloat(discountPercentInput.value) || 0;

        if (basePrice > 0 && discountPercent > 0) {
            const finalPrice = basePrice * (1 - discountPercent / 100);
            finalPricePreview.classList.remove('d-none');
            displayFinalPrice.textContent = `${currency} ${finalPrice.toFixed(2)}`;
            displayOriginalPrice.textContent = `${currency} ${basePrice.toFixed(2)}`;
            displayDiscountBadge.textContent = `${discountPercent}% OFF`;
        } else {
            finalPricePreview.classList.add('d-none');
        }
    }

    basePriceInput?.addEventListener('input', updatePriceDisplay);
    discountPercentInput?.addEventListener('input', updatePriceDisplay);

    // Reset form on modal close
    modal.addEventListener('hidden.bs.modal', function() {
        document.getElementById('addPackageForm').reset();
        packageImagePreview.innerHTML = `
            <div class="text-center text-muted">
                <i class="bi bi-image" style="font-size: 2.5rem;"></i>
                <p class="small mb-0 mt-2">No image uploaded</p>
            </div>
        `;
        packageImagePreview.style.borderStyle = 'dashed';
        removeImageBtn.classList.add('d-none');
        schedules = [];
        trainerAssignments = {};
        editingScheduleIndex = null;
        updateSchedulesUI();
        finalPricePreview.classList.add('d-none');

        // Reset availability
        alwaysAvailableCheckbox.checked = true;
        dateRangeFields.classList.add('d-none');
        alwaysAvailableMessage.classList.remove('d-none');

        // Reset to first tab
        const firstTab = new bootstrap.Tab(tabButtons[0]);
        firstTab.show();
        currentTabIndex = 0;
        updateNavButtons();

        // Reset schedule form button and component
        addScheduleBtnText.textContent = 'Add Schedule';
        addScheduleBtn.classList.remove('btn-primary');
        addScheduleBtn.classList.add('btn-outline-primary');
        if (typeof ScheduleTimePicker !== 'undefined') {
            ScheduleTimePicker.reset(schedulePickerId);
        }
    });

    // Initialize
    updateNavButtons();
});
</script>
@endpush
