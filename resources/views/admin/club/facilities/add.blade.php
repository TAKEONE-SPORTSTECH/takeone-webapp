<!-- Add Facility Modal -->
<div x-show="showAddFacilityModal"
     x-cloak
     id="addFacilityModal"
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50" @click="showAddFacilityModal = false; if(window.resetAddFacilityForm) resetAddFacilityForm()"></div>

    <!-- Modal Content -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-3xl relative rounded-xl overflow-hidden" @click.stop>
            <!-- Header -->
            <div class="modal-header border-b border-gray-200 px-6 py-4">
                <h5 class="modal-title text-lg font-semibold" id="addFacilityModalLabel">Add New Facility</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" @click="showAddFacilityModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="modal-body px-6 py-4">
                <form id="addFacilityForm" action="{{ route('admin.club.facilities.store', $club->slug) }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="space-y-6">
                        <!-- Basic Info Section -->
                        <div class="space-y-4">
                            <h6 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Basic Information</h6>

                            <!-- Facility Name -->
                            <div class="space-y-2">
                                <label for="facilityName" class="block text-sm font-medium text-gray-700">Facility Name <span class="text-red-500">*</span></label>
                                <input type="text"
                                       id="facilityName"
                                       name="name"
                                       required
                                       placeholder="e.g., Main Swimming Pool"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>

                            <!-- Description -->
                            <div class="space-y-2">
                                <label for="facilityDescription" class="block text-sm font-medium text-gray-700">Description</label>
                                <textarea id="facilityDescription"
                                          name="description"
                                          rows="3"
                                          placeholder="Describe the facility, its features and amenities..."
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent resize-none"></textarea>
                            </div>
                        </div>

                        <!-- Location Section -->
                        <div class="space-y-4">
                            <h6 class="text-sm font-semibold text-gray-700 uppercase tracking-wide flex items-center gap-2">
                                <i class="bi bi-geo-alt"></i>
                                Location
                            </h6>
                            <x-location-map
                                id="addFacility"
                                latName="latitude"
                                lngName="longitude"
                                addressName="address"
                                :defaultLat="$club->latitude ?? 25.2048"
                                :defaultLng="$club->longitude ?? 55.2708"
                                :required="true"
                            />
                            <input type="hidden" id="facilityMapZoom" name="map_zoom" value="13">
                        </div>

                        <!-- Operating Hours Section -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h6 class="text-sm font-semibold text-gray-700 uppercase tracking-wide flex items-center gap-2">
                                    <i class="bi bi-clock"></i>
                                    Operating Hours <span class="text-red-500">*</span>
                                </h6>
                                <button type="button" id="addOperatingHourBtn" class="px-3 py-1.5 text-xs font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors flex items-center gap-1">
                                    <i class="bi bi-plus"></i> Add
                                </button>
                            </div>
                            <div id="operatingHoursContainer" class="space-y-3">
                                <!-- Operating hours will be added here dynamically -->
                            </div>
                            <p id="noOperatingHoursMsg" class="text-sm text-gray-500 text-center py-4 border-2 border-dashed border-gray-200 rounded-lg">
                                No operating hours added yet. Click "Add" to set your hours.
                            </p>
                        </div>

                        <!-- Availability Options -->
                        <div class="space-y-4">
                            <h6 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Availability</h6>

                            <div class="grid grid-cols-2 gap-4">
                                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="checkbox" id="isAvailable" name="is_available" value="1" checked class="w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary">
                                    <div>
                                        <span class="block text-sm font-medium text-gray-700">Currently Available</span>
                                        <span class="text-xs text-gray-500">Facility is open for use</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="checkbox" id="isRentable" name="is_rentable" value="1" class="w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary">
                                    <div>
                                        <span class="block text-sm font-medium text-gray-700">Available for Rent</span>
                                        <span class="text-xs text-gray-500">Can be booked privately</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Rentable Times Section (Hidden by default) -->
                        <div id="rentableTimesSection" class="space-y-4 hidden">
                            <div class="flex items-center justify-between">
                                <h6 class="text-sm font-semibold text-gray-700 uppercase tracking-wide flex items-center gap-2">
                                    <i class="bi bi-calendar-check"></i>
                                    Rentable Times <span class="text-red-500">*</span>
                                </h6>
                                <button type="button" id="addRentableTimeBtn" class="px-3 py-1.5 text-xs font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors flex items-center gap-1">
                                    <i class="bi bi-plus"></i> Add
                                </button>
                            </div>
                            <div id="rentableTimesContainer" class="space-y-3">
                                <!-- Rentable times will be added here dynamically -->
                            </div>
                            <p id="noRentableTimesMsg" class="text-sm text-gray-500 text-center py-4 border-2 border-dashed border-gray-200 rounded-lg">
                                No rentable times added yet. Click "Add" to set rental availability.
                            </p>
                        </div>

                        <!-- Image Upload Section -->
                        <div class="space-y-4">
                            <h6 class="text-sm font-semibold text-gray-700 uppercase tracking-wide flex items-center gap-2">
                                <i class="bi bi-images"></i>
                                Facility Image
                            </h6>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">Upload Image <span class="text-xs text-gray-500">(4:3 recommended)</span></label>
                                <x-takeone-cropper
                                    id="facilityAddImageCropper"
                                    :width="400"
                                    :height="300"
                                    shape="square"
                                    mode="form"
                                    inputName="image"
                                    :folder="'clubs/' . $club->id . '/facilities'"
                                    :filename="'facility_' . time()"
                                    :previewWidth="200"
                                    :previewHeight="150"
                                    buttonText="Upload Image"
                                    buttonClass="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors flex items-center gap-2"
                                />
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-t border-gray-200 px-6 py-4 flex justify-end gap-3">
                <button type="button"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        @click="showAddFacilityModal = false">
                    Cancel
                </button>
                <button type="submit"
                        form="addFacilityForm"
                        id="submitFacilityBtn"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors flex items-center gap-2">
                    <i class="bi bi-plus-lg"></i>
                    <span>Create Facility</span>
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Styles moved to app.css (Phase 6) --}}

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let operatingHourIndex = 0;
    let rentableTimeIndex = 0;

    const days = [
        { key: 'sunday', label: 'S' },
        { key: 'monday', label: 'M' },
        { key: 'tuesday', label: 'T' },
        { key: 'wednesday', label: 'W' },
        { key: 'thursday', label: 'T' },
        { key: 'friday', label: 'F' },
        { key: 'saturday', label: 'S' }
    ];

    // Initialize map when modal opens
    const addModal = document.getElementById('addFacilityModal');
    const addMapObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                const isVisible = addModal.style.display !== 'none' && !addModal.hasAttribute('hidden');
                if (isVisible) {
                    LocationMap.init('addFacility', {{ $club->latitude ?? 25.2048 }}, {{ $club->longitude ?? 55.2708 }});
                }
            }
        });
    });
    addMapObserver.observe(addModal, { attributes: true });

    // Operating Hours Management
    const operatingHoursContainer = document.getElementById('operatingHoursContainer');
    const noOperatingHoursMsg = document.getElementById('noOperatingHoursMsg');

    document.getElementById('addOperatingHourBtn').addEventListener('click', function() {
        addTimeSlot('operating', operatingHoursContainer, noOperatingHoursMsg);
    });

    // Rentable Times Management
    const rentableTimesContainer = document.getElementById('rentableTimesContainer');
    const noRentableTimesMsg = document.getElementById('noRentableTimesMsg');
    const rentableTimesSection = document.getElementById('rentableTimesSection');

    document.getElementById('addRentableTimeBtn').addEventListener('click', function() {
        addTimeSlot('rentable', rentableTimesContainer, noRentableTimesMsg);
    });

    // Toggle rentable times section
    document.getElementById('isRentable').addEventListener('change', function() {
        rentableTimesSection.classList.toggle('hidden', !this.checked);
    });

    function addTimeSlot(type, container, noMsg) {
        const index = type === 'operating' ? operatingHourIndex++ : rentableTimeIndex++;
        const prefix = type === 'operating' ? 'operating_hours' : 'rentable_times';

        noMsg.classList.add('hidden');

        const row = document.createElement('div');
        row.className = 'time-slot-row bg-gray-50 p-4 rounded-lg space-y-3';
        row.innerHTML = `
            <div class="flex flex-wrap gap-1.5 justify-center">
                ${days.map((day, i) => `
                    <input type="checkbox" id="${type}_day_${index}_${day.key}" name="${prefix}[${index}][days][]" value="${day.key}" class="day-checkbox">
                    <label for="${type}_day_${index}_${day.key}" class="day-label">${day.label}</label>
                `).join('')}
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="block text-xs font-medium text-gray-500">Start Time</label>
                    <input type="time" name="${prefix}[${index}][start_time]" value="08:00" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-medium text-gray-500">End Time</label>
                    <input type="time" name="${prefix}[${index}][end_time]" value="22:00" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                </div>
            </div>
            <div class="flex justify-end">
                <button type="button" class="remove-time-slot text-xs text-red-500 hover:text-red-700 px-2 py-1 rounded hover:bg-red-50 transition-colors flex items-center gap-1">
                    <i class="bi bi-x-lg"></i> Remove
                </button>
            </div>
        `;

        row.querySelector('.remove-time-slot').addEventListener('click', function() {
            row.remove();
            if (container.children.length === 0) {
                noMsg.classList.remove('hidden');
            }
        });

        container.appendChild(row);
    }

    // Reset form when modal is closed (watch for Alpine hiding it)
    window.resetAddFacilityForm = function() {
        document.getElementById('addFacilityForm').reset();
        operatingHoursContainer.innerHTML = '';
        rentableTimesContainer.innerHTML = '';
        noOperatingHoursMsg.classList.remove('hidden');
        noRentableTimesMsg.classList.remove('hidden');
        rentableTimesSection.classList.add('hidden');
        if (typeof removeImage_facilityAddImageCropper === 'function') {
            removeImage_facilityAddImageCropper();
        }
        operatingHourIndex = 0;
        rentableTimeIndex = 0;
    };
});
</script>
@endpush
