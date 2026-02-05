<!-- Edit Facility Modal -->
<div class="modal fade" id="editFacilityModal" tabindex="-1" aria-labelledby="editFacilityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-xl overflow-hidden">
            <!-- Header -->
            <div class="modal-header border-b border-gray-200 px-6 py-4">
                <h5 class="modal-title text-lg font-semibold" id="editFacilityModalLabel">Edit Facility</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 transition-colors" data-bs-dismiss="modal" aria-label="Close">
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

                            <!-- Address -->
                            <div class="space-y-2">
                                <label for="editFacilityAddress" class="block text-sm font-medium text-gray-700">Address</label>
                                <div class="flex gap-2">
                                    <input type="text"
                                           id="editFacilityAddress"
                                           name="address"
                                           placeholder="Enter the facility address (e.g., Bahrain, Manama)"
                                           class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                    <button type="button"
                                            id="searchEditAddressBtn"
                                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors flex items-center gap-2">
                                        <i class="bi bi-search"></i>
                                        Search
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500">Enter an address and click Search to find it on the map</p>
                            </div>

                            <!-- Map -->
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">Location on Map <span class="text-xs text-gray-500">(Click or drag marker to set)</span></label>
                                <div id="editFacilityMap" class="h-64 rounded-lg overflow-hidden border border-gray-300 bg-gray-100"></div>
                            </div>

                            <!-- Lat/Lng Inputs -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label for="editFacilityLatitude" class="block text-xs font-medium text-gray-500">
                                        <i class="bi bi-geo mr-1"></i>Latitude
                                    </label>
                                    <input type="number"
                                           id="editFacilityLatitude"
                                           name="gps_lat"
                                           step="any"
                                           placeholder="25.2048"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                                <div class="space-y-2">
                                    <label for="editFacilityLongitude" class="block text-xs font-medium text-gray-500">
                                        <i class="bi bi-geo mr-1"></i>Longitude
                                    </label>
                                    <input type="number"
                                           id="editFacilityLongitude"
                                           name="gps_long"
                                           step="any"
                                           placeholder="55.2708"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
                                </div>
                            </div>
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
                                <div class="flex flex-col gap-3">
                                    <input type="file"
                                           id="editFacilityImage"
                                           name="image"
                                           accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary file:text-white hover:file:bg-primary/90 cursor-pointer border border-gray-300 rounded-lg">
                                    <p class="text-xs text-gray-500">
                                        Upload a new image to replace the current one. Supported: JPEG, PNG, GIF, WebP. Max: 10MB
                                    </p>
                                </div>
                            </div>

                            <!-- New Image Preview -->
                            <div id="editImagePreviewSection" class="space-y-2 hidden">
                                <label class="block text-sm font-medium text-gray-700">New Image Preview</label>
                                <img id="editImagePreview" src="" alt="New image preview" class="w-full h-40 object-cover rounded-lg border border-gray-200">
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Footer -->
            <div class="modal-footer border-t border-gray-200 px-6 py-4 flex justify-end gap-3">
                <button type="button"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        data-bs-dismiss="modal">
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
