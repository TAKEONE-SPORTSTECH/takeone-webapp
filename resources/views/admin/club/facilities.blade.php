@extends('layouts.admin-club')

@section('club-admin-content')
<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold mb-1">Facilities</h2>
            <p class="text-muted mb-0">Manage your club facilities</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFacilityModal">
            <i class="bi bi-plus-lg me-2"></i>Add Facility
        </button>
    </div>

    @if(isset($facilities) && count($facilities) > 0)
    <div class="row g-4">
        @foreach($facilities as $facility)
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                @if($facility->image)
                <img src="{{ asset('storage/' . $facility->image) }}" alt="{{ $facility->name }}" class="card-img-top" style="height: 180px; object-fit: cover;">
                @else
                <div class="bg-light d-flex align-items-center justify-content-center" style="height: 180px;">
                    <i class="bi bi-geo-alt text-muted" style="font-size: 3rem;"></i>
                </div>
                @endif
                <div class="card-body">
                    <h5 class="fw-semibold mb-2">{{ $facility->name }}</h5>
                    <p class="text-muted small mb-3">{{ Str::limit($facility->description, 100) }}</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <x-takeone-cropper
                            id="facility_{{ $facility->id }}"
                            :width="400"
                            :height="300"
                            shape="square"
                            folder="clubs/{{ $club->id }}/facilities"
                            filename="facility_{{ $facility->id }}"
                            uploadUrl="{{ route('admin.club.facilities.upload-image', [$club->id, $facility->id]) }}"
                            :currentImage="$facility->image ? asset('storage/' . $facility->image) : ''"
                            buttonText="Change Image"
                            buttonClass="btn btn-sm btn-outline-success flex-grow-1"
                        />
                        <button class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-geo-alt text-muted" style="font-size: 4rem;"></i>
            <h5 class="mt-3 mb-2">No facilities yet</h5>
            <p class="text-muted mb-3">Add facilities to showcase what your club offers</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFacilityModal">
                <i class="bi bi-plus-lg me-2"></i>Add Facility
            </button>
        </div>
    </div>
    @endif
</div>

<!-- Add Facility Modal -->
<div class="modal fade" id="addFacilityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">Add Facility</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.club.facilities.store', $club->id) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Facility Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label d-block">Image</label>
                        <x-takeone-cropper
                            id="add_facility_image"
                            mode="form"
                            inputName="image"
                            :width="400"
                            :height="300"
                            :previewWidth="200"
                            :previewHeight="150"
                            shape="square"
                            folder="clubs/{{ $club->id }}/facilities"
                            filename="facility_{{ time() }}"
                            buttonText="Select Image"
                            buttonClass="btn btn-outline-success btn-sm"
                        />
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Add Facility</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
