<!-- Edit Facility Modal -->
<div x-show="showEditFacilityModal"
     x-cloak
     id="editFacilityModal"
     class="fixed inset-0 z-50 overflow-y-auto"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/50" @click="showEditFacilityModal = false"></div>

    <!-- Modal Content -->
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="modal-content border-0 shadow-lg w-full max-w-3xl relative rounded-xl overflow-hidden" @click.stop>
            <!-- Header -->
            <div class="modal-header border-b border-gray-200 px-6 py-4">
                <h5 class="modal-title text-lg font-semibold" id="editFacilityModalLabel">Edit Facility</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" @click="showEditFacilityModal = false">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="modal-body px-6 py-4">
                <form id="editFacilityForm" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <input type="hidden" id="editFacilityId" name="facility_id">

                    <div class="space-y-6">
                        <!-- Basic Info Section -->
                        <div class="space-y-4">
                            <h6 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Basic Information</h6>

                            <!-- Facility Name -->
                            <div class="space-y-2">
                                <label for="editFacilityName" class="block text-sm font-medium text-gray-700">Facility Name <span class="text-red-500">*</span></label>
                                <input type="text"
                                       id="editFacilityName"
                                       name="name"
                                       required
                                       placeholder="e.g., Main Swimming Pool"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>
                        </div>

                        <!-- Location Section -->
                        <div class="space-y-4">
                            <h6 class="text-sm font-semibold text-gray-700 uppercase tracking-wide flex items-center gap-2">
                                <i class="bi bi-geo-alt"></i>
                                Location
                            </h6>
                            <x-location-map
                                id="editFacility"
                                latName="gps_lat"
                                lngName="gps_long"
                                addressName="address"
                                :defaultLat="$club->latitude ?? 25.2048"
                                :defaultLng="$club->longitude ?? 55.2708"
                            />
                        </div>

                        <!-- Availability Options -->
                        <div class="space-y-4">
                            <h6 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Availability</h6>

                            <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                <input type="checkbox" id="editIsAvailable" name="is_available" value="1" class="w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary">
                                <div>
                                    <span class="block text-sm font-medium text-gray-700">Currently Available</span>
                                    <span class="text-xs text-gray-500">Facility is open for use</span>
                                </div>
                            </label>
                        </div>

                        <!-- Current Image Preview -->
                        <div class="space-y-4">
                            <h6 class="text-sm font-semibold text-gray-700 uppercase tracking-wide flex items-center gap-2">
                                <i class="bi bi-image"></i>
                                Current Image
                            </h6>
                            <div id="editCurrentImageContainer" class="hidden">
                                <img id="editCurrentImage" src="" alt="Current facility image" class="w-full h-40 object-cover rounded-lg border border-gray-200">
                            </div>
                            <div id="editNoImagePlaceholder" class="text-center py-4 border-2 border-dashed border-gray-200 rounded-lg">
                                <p class="text-sm text-gray-500">No image uploaded</p>
                            </div>
                        </div>

                        <!-- Image Upload Section -->
                        <div class="space-y-4">
                            <h6 class="text-sm font-semibold text-gray-700 uppercase tracking-wide flex items-center gap-2">
                                <i class="bi bi-upload"></i>
                                Upload New Image
                            </h6>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">Replace Image <span class="text-xs text-gray-500">(4:3 recommended)</span></label>
                                <x-takeone-cropper
                                    id="facilityEditImageCropper"
                                    :width="400"
                                    :height="300"
                                    shape="square"
                                    mode="form"
                                    inputName="image"
                                    folder="facilities"
                                    :filename="'facility_edit_' . time()"
                                    :previewWidth="200"
                                    :previewHeight="150"
                                    buttonText="Upload New Image"
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
                        @click="showEditFacilityModal = false">
                    Cancel
                </button>
                <button type="submit"
                        form="editFacilityForm"
                        id="submitEditFacilityBtn"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary/90 transition-colors flex items-center gap-2">
                    <i class="bi bi-check-lg"></i>
                    <span>Update Facility</span>
                </button>
            </div>
        </div>
    </div>
</div>
