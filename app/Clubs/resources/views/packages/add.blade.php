<!-- Add Package Modal -->
<div x-show="showAddPackageModal"
     x-cloak
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50" @click="showAddPackageModal = false"></div>

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
                         if (this.currentTab === 'trainers' && window.updateTrainerAssignmentsUI) {
                             window.updateTrainerAssignmentsUI();
                         }
                     }
                 }
             }"
             @click.stop>
            <!-- Header -->
            <div class="modal-header border-b border-border px-6 py-4">
                <div>
                    <h5 class="modal-title font-bold text-xl">{{ __('admin.club_packages_add_title') }}</h5>
                    <p class="text-muted-foreground text-sm mb-0">{{ __('admin.club_packages_add_subtitle') }}</p>
                </div>
                <button type="button" class="btn-close" @click="showAddPackageModal = false"></button>
            </div>

            <!-- Tabs Navigation -->
            <div class="border-b border-border bg-muted/30 px-6">
                <ul class="nav nav-tabs border-0 flex" role="tablist">
                    <li class="flex-1" role="presentation">
                        <button class="nav-link w-full flex items-center justify-center px-3 py-3"
                                :class="{ 'active': currentTab === 'basic' }"
                                @click="currentTab = 'basic'"
                                type="button">
                            <span class="hidden sm:inline">{{ __('admin.club_packages_add_tab_basic') }}</span>
                            <span class="sm:hidden">{{ __('admin.club_packages_add_tab_basic_short') }}</span>
                        </button>
                    </li>
                    <li class="flex-1" role="presentation">
                        <button class="nav-link w-full flex items-center justify-center px-3 py-3"
                                :class="{ 'active': currentTab === 'schedules' }"
                                @click="currentTab = 'schedules'"
                                type="button">
                            <span class="hidden sm:inline">{{ __('admin.club_packages_add_tab_schedules') }}</span>
                            <span class="sm:hidden">{{ __('admin.club_packages_add_tab_schedules_short') }}</span>
                        </button>
                    </li>
                    <li class="flex-1" role="presentation">
                        <button class="nav-link w-full flex items-center justify-center px-3 py-3"
                                :class="{ 'active': currentTab === 'trainers' }"
                                @click="currentTab = 'trainers'; if(window.updateTrainerAssignmentsUI) updateTrainerAssignmentsUI();"
                                type="button">
                            <span>{{ __('admin.club_packages_add_tab_trainers') }}</span>
                        </button>
                    </li>
                    <li class="flex-1" role="presentation">
                        <button class="nav-link w-full flex items-center justify-center px-3 py-3"
                                :class="{ 'active': currentTab === 'pricing' }"
                                @click="currentTab = 'pricing'"
                                type="button">
                            <span>{{ __('admin.club_packages_add_tab_pricing') }}</span>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Body -->
            <div class="modal-body px-6 py-6 max-h-[65vh] overflow-y-auto">
                <form id="addPackageForm" x-data="{ lang: 'en' }" action="{{ route('admin.club.packages.store', $club->slug) }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <!-- Tab 1: Basic Info -->
                    <div x-show="currentTab === 'basic'" x-cloak>
                        <x-lang-toggle class="mb-4" />
                        <!-- Package Image Section -->
                        <div class="card border-0 shadow-sm mb-4 overflow-hidden">
                            <div class="card-body p-4 bg-gradient-to-br from-primary/5 to-transparent">
                                <div class="flex items-center gap-2 mb-4">
                                    <div class="section-icon">
                                        <i class="bi bi-upload text-primary"></i>
                                    </div>
                                    <h6 class="mb-0 font-semibold">{{ __('admin.club_packages_add_section_package_image') }}</h6>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <x-takeone-cropper
                                            id="packageImageCropper"
                                            :width="400"
                                            :height="225"
                                            shape="square"
                                            mode="form"
                                            inputName="image"
                                            folder="packages"
                                            :filename="'package_' . time()"
                                            :previewWidth="300"
                                            :previewHeight="169"
                                            buttonText="{{ __('admin.club_packages_add_upload_image') }}"
                                            buttonClass="btn btn-outline-secondary flex-1"
                                        />
                                    </div>

                                    <div>
                                        <div class="rounded-lg p-3 mb-3 bg-muted/30">
                                            <p class="text-sm font-medium mb-2">{{ __('admin.club_packages_add_image_guidelines') }}</p>
                                            <ul class="list-none text-sm text-muted-foreground mb-0">
                                                <li class="flex items-center gap-2 mb-1">
                                                    <span class="rounded-full bg-primary w-1.5 h-1.5"></span>
                                                    {{ __('admin.club_packages_add_guideline_ratio') }}
                                                </li>
                                                <li class="flex items-center gap-2 mb-1">
                                                    <span class="rounded-full bg-primary w-1.5 h-1.5"></span>
                                                    {{ __('admin.club_packages_add_guideline_max_size') }}
                                                </li>
                                                <li class="flex items-center gap-2">
                                                    <span class="rounded-full bg-primary w-1.5 h-1.5"></span>
                                                    {{ __('admin.club_packages_add_guideline_formats') }}
                                                </li>
                                            </ul>
                                        </div>

                                        <div class="flex items-center gap-3 p-3 rounded-lg border border-border bg-white">
                                            <div class="form-check form-switch mb-0">
                                                <input type="checkbox" id="packagePopular" name="is_popular" class="form-check-input" role="switch">
                                            </div>
                                            <div>
                                                <label for="packagePopular" class="form-label mb-0 font-medium cursor-pointer">{{ __('admin.club_packages_add_featured_package') }}</label>
                                                <p class="text-muted-foreground text-sm mb-0">{{ __('admin.club_packages_add_featured_package_hint') }}</p>
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
                                    <h6 class="mb-0 font-semibold">{{ __('admin.club_packages_add_section_package_details') }}</h6>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="mb-3">
                                            <label for="packageName" class="form-label font-medium">
                                                {{ __('admin.club_packages_add_label_package_name') }} <span class="text-destructive">*</span>
                                            </label>
                                            <input type="text" id="packageName" name="name" :required="lang==='en'" x-show="lang==='en'" placeholder="{{ __('admin.club_packages_add_placeholder_package_name') }}" class="form-control">
                                            <input type="text" name="translations[name][ar]" dir="rtl" x-show="lang==='ar'" x-cloak placeholder="الاسم بالعربية" class="form-control" value="{{ old('translations.name.ar') }}">
                                        </div>

                                        <div class="mb-3">
                                            <label for="packageDuration" class="form-label font-medium">
                                                {{ __('admin.club_packages_add_label_duration') }} <span class="text-destructive">*</span>
                                            </label>
                                            <input type="number" id="packageDuration" name="duration_months" required value="1" min="1" class="form-control">
                                        </div>
                                    </div>

                                    <div>
                                        <div class="mb-3">
                                            <label for="packageDescription" class="form-label font-medium">{{ __('admin.club_packages_add_label_description') }}</label>
                                            <textarea id="packageDescription" name="description" rows="5" x-show="lang==='en'" placeholder="{{ __('admin.club_packages_add_placeholder_description') }}" class="form-control resize-none"></textarea>
                                            <textarea name="translations[description][ar]" dir="rtl" x-show="lang==='ar'" x-cloak rows="5" class="form-control resize-none" placeholder="الوصف بالعربية">{{ old('translations.description.ar') }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Facility & Capacity Section -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <div class="flex items-center gap-2 mb-4">
                                    <div class="section-icon">
                                        <i class="bi bi-people-fill text-primary"></i>
                                    </div>
                                    <h6 class="mb-0 font-semibold">{{ __('admin.club_packages_add_section_capacity') }}</h6>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label for="packageCapacity" class="form-label font-medium">{{ __('admin.club_packages_add_label_max_capacity') }}</label>
                                        <input type="number" id="packageCapacity" name="max_capacity" min="1" placeholder="{{ __('admin.club_packages_add_placeholder_max_capacity') }}" class="form-control">
                                    </div>
                                    <div>
                                        <label for="packageScheduleType" class="form-label font-medium">{{ __('admin.club_packages_add_label_schedule_type') }}</label>
                                        <x-select-menu name="schedule_type" :value="old('schedule_type', 'fixed')"
                                            :options="[
                                                ['value' => 'fixed', 'label' => __('admin.club_packages_add_option_fixed')],
                                                ['value' => 'flexible', 'label' => __('admin.club_packages_add_option_flexible')],
                                            ]" />
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
                                    <h6 class="mb-0 font-semibold">{{ __('admin.club_packages_add_section_member_restrictions') }}</h6>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <div>
                                        <label for="packageGender" class="form-label font-medium">{{ __('admin.club_packages_add_label_gender') }}</label>
                                        <x-select-menu name="gender_restriction" :value="old('gender_restriction', 'mixed')"
                                            :options="[
                                                ['value' => 'mixed', 'label' => __('admin.club_packages_add_option_mixed')],
                                                ['value' => 'male', 'label' => __('admin.club_packages_add_option_male_only')],
                                                ['value' => 'female', 'label' => __('admin.club_packages_add_option_female_only')],
                                            ]" />
                                    </div>
                                    <div>
                                        <label for="packageMinAge" class="form-label font-medium">{{ __('admin.club_packages_add_label_min_age') }}</label>
                                        <input type="number" id="packageMinAge" name="age_min" min="0" placeholder="{{ __('admin.club_packages_add_placeholder_min_age') }}" class="form-control">
                                    </div>
                                    <div>
                                        <label for="packageMaxAge" class="form-label font-medium">{{ __('admin.club_packages_add_label_max_age') }}</label>
                                        <input type="number" id="packageMaxAge" name="age_max" min="0" placeholder="{{ __('admin.club_packages_add_placeholder_max_age') }}" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Availability Period Section -->
                        <div class="card border-0 shadow-sm"
                             x-data="{ alwaysAvailable: true }">
                            <div class="card-body p-4">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-2">
                                        <div class="section-icon">
                                            <i class="bi bi-calendar-event text-primary"></i>
                                        </div>
                                        <h6 class="mb-0 font-semibold">{{ __('admin.club_packages_add_section_availability') }}</h6>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <div class="form-check form-switch mb-0">
                                            <input type="checkbox" id="alwaysAvailable" name="always_available" class="form-check-input" role="switch" checked x-model="alwaysAvailable">
                                        </div>
                                        <label for="alwaysAvailable" class="form-label mb-0 font-medium cursor-pointer">{{ __('admin.club_packages_add_always_available') }}</label>
                                    </div>
                                </div>

                                <div x-show="!alwaysAvailable" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="packageStartDate" class="form-label font-medium">{{ __('admin.club_packages_add_label_start_date') }}</label>
                                        <input type="date" id="packageStartDate" name="start_date" class="form-control">
                                    </div>
                                    <div>
                                        <label for="packageEndDate" class="form-label font-medium">{{ __('admin.club_packages_add_label_end_date') }}</label>
                                        <input type="date" id="packageEndDate" name="end_date" class="form-control">
                                    </div>
                                </div>

                                <div x-show="alwaysAvailable" class="text-center p-4 rounded-lg border border-primary/20 bg-primary/5">
                                    <p class="text-muted-foreground mb-0">{{ __('admin.club_packages_add_availability_year_round') }}</p>
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
                                        <h6 class="mb-0 font-semibold">{{ __('admin.club_packages_add_section_package_schedule') }} <span class="text-destructive">*</span></h6>
                                        <p class="text-muted-foreground text-sm mb-0">{{ __('admin.club_packages_add_package_schedule_hint') }}</p>
                                    </div>
                                </div>

                                <!-- Schedule Form -->
                                <div class="rounded-lg border border-border p-4 mb-4 mt-4 bg-muted/10">
                                    <!-- Days, Start Time, End Time - Using Component -->
                                    <div class="mb-4">
                                        <x-schedule-time-picker
                                            id="packageSchedule"
                                            :required="false"
                                        />
                                    </div>

                                    <!-- Activity, Facility & Notes - 3 column grid -->
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                                        <div>
                                            <label for="scheduleActivity" class="tf-label">{{ __('admin.club_packages_add_label_activity') }} <span class="text-red-500">*</span></label>
                                            <select id="scheduleActivity" class="tf-time">
                                                <option value="">{{ __('admin.club_packages_add_option_select_activity') }}</option>
                                                @if(isset($activities))
                                                    @foreach($activities as $activity)
                                                        <option value="{{ $activity->id }}" data-name="{{ $activity->title ?? $activity->name }}">{{ $activity->title ?? $activity->name }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>
                                        <div>
                                            <label for="scheduleFacility" class="tf-label">{{ __('admin.club_packages_add_label_facility') }}</label>
                                            <select id="scheduleFacility" class="tf-time">
                                                <option value="">{{ __('admin.club_packages_add_option_select_facility') }}</option>
                                                @if(isset($facilities))
                                                    @foreach($facilities as $facility)
                                                        <option value="{{ $facility->id }}" data-name="{{ $facility->name }}">{{ $facility->name }}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>
                                        <div>
                                            <label for="scheduleNotes" class="tf-label">{{ __('admin.club_packages_add_label_notes') }}</label>
                                            <input type="text" id="scheduleNotes" placeholder="{{ __('admin.club_packages_add_placeholder_notes') }}" class="tf-time">
                                        </div>
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="button" id="addScheduleBtn" class="btn btn-outline-primary px-4 py-2">
                                            <i class="bi bi-plus-lg me-2"></i><span id="addScheduleBtnText">{{ __('admin.club_packages_add_btn_add_schedule') }}</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Schedules List -->
                                <div id="schedulesList">
                                    <!-- Schedules will be added here dynamically -->
                                </div>

                                <div id="noSchedulesMessage" class="text-center py-12 border-2 border-dashed border-border rounded-lg">
                                    <i class="bi bi-clock text-muted-foreground text-5xl"></i>
                                    <p class="text-muted-foreground mb-1 mt-3">{{ __('admin.club_packages_add_no_schedules') }}</p>
                                    <p class="text-muted-foreground text-sm">{{ __('admin.club_packages_add_no_schedules_hint') }}</p>
                                </div>

                                <!-- Hidden input for schedules data -->
                                <input type="hidden" id="schedulesData" name="schedules">
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
                                        <h6 class="mb-0 font-semibold">{{ __('admin.club_packages_add_section_assign_trainers') }}</h6>
                                        <p class="text-muted-foreground text-sm mb-0">{{ __('admin.club_packages_add_assign_trainers_hint') }}</p>
                                    </div>
                                </div>

                                <div id="trainerAssignments" class="mt-4">
                                    <!-- Will be populated based on schedules -->
                                    <div class="text-center py-12 border-2 border-dashed border-border rounded-lg">
                                        <i class="bi bi-person-check text-muted-foreground text-5xl"></i>
                                        <p class="text-muted-foreground mb-1 mt-3">{{ __('admin.club_packages_add_no_activities') }}</p>
                                        <p class="text-muted-foreground text-sm">{{ __('admin.club_packages_add_no_activities_hint') }}</p>
                                    </div>
                                </div>

                                <!-- Hidden input for trainer assignments data -->
                                <input type="hidden" id="trainerAssignmentsData" name="trainer_assignments">
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
                        <!-- Base Price Section -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <div class="flex items-center gap-2 mb-4">
                                    <div class="section-icon">
                                        <i class="bi bi-currency-dollar text-primary"></i>
                                    </div>
                                    <h6 class="mb-0 font-semibold">{{ __('admin.club_packages_add_section_base_price') }}</h6>
                                </div>

                                <label for="packagePrice" class="form-label font-medium">
                                    {{ __('admin.club_packages_add_label_package_price') }} ({{ $club->currency ?? 'BHD' }}) <span class="text-destructive">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-muted/30">
                                        <i class="bi bi-currency-dollar text-muted-foreground"></i>
                                    </span>
                                    <input type="number" id="packagePrice" name="price" required step="0.01" min="0" placeholder="199.99" class="form-control text-xl" x-model.number="basePrice">
                                </div>
                            </div>
                        </div>

                        <!-- Discount Section -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-body p-4">
                                <div class="flex items-center gap-2 mb-4">
                                    <div class="section-icon">
                                        <i class="bi bi-tag text-primary"></i>
                                    </div>
                                    <h6 class="mb-0 font-semibold">{{ __('admin.club_packages_add_section_discount_options') }}</h6>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="discountCode" class="form-label font-medium">{{ __('admin.club_packages_add_label_discount_code') }}</label>
                                        <input type="text" id="discountCode" name="discount_code" placeholder="{{ __('admin.club_packages_add_placeholder_discount_code') }}" class="form-control uppercase font-mono">
                                        <p class="text-muted-foreground text-sm mt-1">{{ __('admin.club_packages_add_discount_code_hint') }}</p>
                                    </div>
                                    <div>
                                        <label for="discountPercent" class="form-label font-medium">{{ __('admin.club_packages_add_label_discount_percentage') }}</label>
                                        <div class="input-group">
                                            <input type="number" id="discountPercent" name="discount_percentage" min="0" max="100" step="0.01" placeholder="20" class="form-control" x-model.number="discountPercent">
                                            <span class="input-group-text bg-muted/30 text-muted-foreground">%</span>
                                        </div>
                                        <p class="text-muted-foreground text-sm mt-1">{{ __('admin.club_packages_add_discount_percentage_hint') }}</p>
                                    </div>
                                </div>

                                <!-- Final Price Preview -->
                                <div x-show="showPreview" x-cloak class="mt-4 p-4 rounded-xl border-2 border-primary/20 bg-gradient-to-br from-primary/10 to-primary/5">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-muted-foreground text-sm mb-1">{{ __('admin.club_packages_add_final_price') }}</p>
                                            <p class="text-4xl font-bold text-primary mb-0">{{ $club->currency ?? 'BHD' }} <span x-text="finalPrice.toFixed(2)">0.00</span></p>
                                        </div>
                                        <div class="text-end">
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
                    {{ __('admin.club_packages_add_step') }} <span x-text="currentIndex + 1">1</span> {{ __('admin.club_packages_add_of_4') }}
                    <span x-text="' - ' + tabNames[currentIndex]"> - Basic Info</span>
                </div>

                <div class="ms-auto flex gap-2">
                    <button type="button" class="btn btn-outline-secondary px-4" @click="showAddPackageModal = false">{{ __('shared.cancel') }}</button>
                    <button type="button" x-show="!isLastTab" class="btn btn-primary px-4" @click="nextTab()">
                        {{ __('admin.club_packages_add_btn_next_step') }}<i class="bi bi-arrow-right ms-2"></i>
                    </button>
                    <button type="submit" form="addPackageForm" x-show="isLastTab" x-cloak class="btn btn-primary px-4">
                        <i class="bi bi-plus-lg me-2"></i>{{ __('admin.club_packages_add_btn_create_package') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Styles moved to app.css (Phase 6) --}}

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let schedules = [];
    let trainerAssignments = {};
    let editingScheduleIndex = null;
    const currency = '{{ $club->currency ?? "BHD" }}';

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
        const facilitySelect = document.getElementById('scheduleFacility');
        const facilityId = facilitySelect.value;
        const facilityName = facilitySelect.options[facilitySelect.selectedIndex]?.dataset.name || '';
        const notes = document.getElementById('scheduleNotes').value;

        if (selectedDays.length === 0 || !startTime || !endTime || !activityId) {
            window.showToast('error', '{{ __("admin.club_packages_add_js_select_day_activity") }}');
            return;
        }

        if (endTime <= startTime) {
            window.showToast('error', '{{ __("admin.club_packages_add_js_end_after_start") }}');
            return;
        }

        const schedule = {
            id: editingScheduleIndex !== null ? schedules[editingScheduleIndex].id : Date.now(),
            days: selectedDays,
            startTime,
            endTime,
            activityId,
            activityName,
            facilityId,
            facilityName,
            notes
        };

        if (editingScheduleIndex !== null) {
            schedules[editingScheduleIndex] = schedule;
            editingScheduleIndex = null;
            addScheduleBtnText.textContent = '{{ __("admin.club_packages_add_btn_add_schedule") }}';
            addScheduleBtn.classList.remove('btn-primary');
            addScheduleBtn.classList.add('btn-outline-primary');
        } else {
            schedules.push(schedule);
        }

        updateSchedulesUI();
        updateTrainerAssignmentsUI();
        resetScheduleForm();
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

    function updateSchedulesUI() {
        if (schedules.length === 0) {
            schedulesList.innerHTML = '';
            noSchedulesMessage.classList.remove('hidden');
            return;
        }

        noSchedulesMessage.classList.add('hidden');

        schedulesList.innerHTML = `
            <div class="mb-3">
                <label class="form-label font-medium">{{ __('admin.club_packages_add_added_schedules') }} (${schedules.length})</label>
            </div>
            <div class="border border-border rounded-lg overflow-hidden">
                ${schedules.map((schedule, index) => `
                    <div class="flex items-start justify-between p-3 ${index < schedules.length - 1 ? 'border-b border-border' : ''} schedule-item hover:bg-muted/10 transition-colors">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                ${schedule.days.map(d => `<span class="badge bg-secondary">${d.name}</span>`).join('')}
                                <span class="font-medium">${formatTimeTo12Hour(schedule.startTime)} - ${formatTimeTo12Hour(schedule.endTime)}</span>
                                <span class="badge bg-secondary">${calcDurationMinutes(schedule.startTime, schedule.endTime)} {{ __('admin.club_packages_add_js_min') }}</span>
                                ${schedule.activityName ? `<span class="badge bg-primary/10 text-primary border border-primary/20"><i class="bi bi-activity me-1"></i>${schedule.activityName}</span>` : ''}
                                ${schedule.facilityName ? `<span class="badge bg-sky-50 text-sky-700 border border-sky-200"><i class="bi bi-geo-alt me-1"></i>${schedule.facilityName}</span>` : ''}
                            </div>
                            ${schedule.notes ? `<p class="text-muted-foreground text-sm mb-0"><span class="font-medium">{{ __('admin.club_packages_add_js_note_label') }}</span> ${schedule.notes}</p>` : ''}
                        </div>
                        <div class="flex gap-1 ms-2">
                            <button type="button" class="btn btn-sm btn-light edit-schedule" data-index="${index}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-light text-destructive delete-schedule" data-index="${index}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;

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
                    addScheduleBtnText.textContent = '{{ __("admin.club_packages_add_btn_add_schedule") }}';
                    addScheduleBtn.classList.remove('btn-primary');
                    addScheduleBtn.classList.add('btn-outline-primary');
                    resetScheduleForm();
                }
                updateSchedulesUI();
                updateTrainerAssignmentsUI();
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
        document.getElementById('scheduleFacility').value = schedule.facilityId || '';
        document.getElementById('scheduleNotes').value = schedule.notes || '';

        addScheduleBtnText.textContent = '{{ __("admin.club_packages_add_js_update_schedule") }}';
        addScheduleBtn.classList.remove('btn-outline-primary');
        addScheduleBtn.classList.add('btn-primary');
    }

    function resetScheduleForm() {
        ScheduleTimePicker.reset(schedulePickerId);
        document.getElementById('scheduleActivity').value = '';
        document.getElementById('scheduleFacility').value = '';
        document.getElementById('scheduleNotes').value = '';
    }

    // Trainer Assignments
    const trainerAssignmentsContainer = document.getElementById('trainerAssignments');
    const trainerAssignmentsDataInput = document.getElementById('trainerAssignmentsData');
    const instructors = @json($instructors ?? []).map(i => ({
        id: i.id,
        name: i.user?.full_name || i.user?.name || '{{ __("admin.club_packages_add_js_unknown") }}'
    }));

    // Watch for tab changes to update trainer assignments
    document.addEventListener('alpine:initialized', () => {
        // Update trainer UI when switching to trainers tab
    });

    window.updateTrainerAssignmentsUI = function() {
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
                <div class="text-center py-12 border-2 border-dashed border-border rounded-lg">
                    <i class="bi bi-person-check text-muted-foreground text-5xl"></i>
                    <p class="text-muted-foreground mb-1 mt-3">{{ __('admin.club_packages_add_no_activities') }}</p>
                    <p class="text-muted-foreground text-sm">{{ __('admin.club_packages_add_no_activities_hint') }}</p>
                </div>
            `;
            return;
        }

        trainerAssignmentsContainer.innerHTML = `
            <p class="text-muted-foreground text-sm mb-4">{{ __('admin.club_packages_add_js_activities_from_schedules') }}</p>
            ${uniqueActivities.map(activity => `
                <div class="flex flex-wrap items-center gap-3 p-4 border border-border rounded-lg mb-3 bg-muted/10">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold mb-0">${activity.name}</p>
                    </div>
                    <div class="w-full sm:w-64">
                        <select class="form-select trainer-assignment" data-activity-id="${activity.id}">
                            <option value="">{{ __('admin.club_packages_add_js_select_instructor') }}</option>
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
    };
});
</script>
@endpush
