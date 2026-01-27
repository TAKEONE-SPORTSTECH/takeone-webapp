@extends('layouts.admin-club')

@section('club-admin-content')
<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold mb-1">Instructors</h2>
            <p class="text-muted mb-0">Manage your club instructors and trainers</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInstructorModal">
            <i class="bi bi-plus-lg me-2"></i>Add Instructor
        </button>
    </div>

    @if(isset($instructors) && count($instructors) > 0)
    <div class="row g-4">
        @foreach($instructors as $instructor)
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    @if($instructor->photo)
                    <img src="{{ asset('storage/' . $instructor->photo) }}" alt="{{ $instructor->name }}" class="rounded-circle mb-3" style="width: 100px; height: 100px; object-fit: cover;">
                    @else
                    <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center mb-3" style="width: 100px; height: 100px;">
                        <span class="text-white fw-bold" style="font-size: 2rem;">{{ strtoupper(substr($instructor->name, 0, 1)) }}</span>
                    </div>
                    @endif
                    <h5 class="fw-semibold mb-1">{{ $instructor->name }}</h5>
                    <p class="text-muted small mb-2">{{ $instructor->specialization ?? 'Trainer' }}</p>
                    @if($instructor->bio)
                    <p class="text-muted small mb-3">{{ Str::limit($instructor->bio, 80) }}</p>
                    @endif
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <x-takeone-cropper
                            id="instructor_{{ $instructor->id }}"
                            :width="200"
                            :height="200"
                            shape="circle"
                            folder="clubs/{{ $club->id }}/instructors"
                            filename="instructor_{{ $instructor->id }}"
                            uploadUrl="{{ route('admin.club.instructors.upload-photo', [$club->id, $instructor->id]) }}"
                            :currentImage="$instructor->photo ? asset('storage/' . $instructor->photo) : ''"
                            buttonText="Change Photo"
                            buttonClass="btn btn-sm btn-outline-success"
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
            <i class="bi bi-people text-muted" style="font-size: 4rem;"></i>
            <h5 class="mt-3 mb-2">No instructors yet</h5>
            <p class="text-muted mb-3">Add instructors to your club</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addInstructorModal">
                <i class="bi bi-plus-lg me-2"></i>Add Instructor
            </button>
        </div>
    </div>
    @endif
</div>

<!-- Add Instructor Modal -->
<div class="modal fade" id="addInstructorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">Add Instructor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.club.instructors.store', $club->id) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Specialization</label>
                        <input type="text" name="specialization" class="form-control" placeholder="e.g., Yoga, CrossFit, Swimming">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bio</label>
                        <textarea name="bio" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3 text-center">
                        <label class="form-label d-block">Photo</label>
                        <x-takeone-cropper
                            id="add_instructor_photo"
                            mode="form"
                            inputName="photo"
                            :width="200"
                            :height="200"
                            :previewWidth="120"
                            :previewHeight="120"
                            shape="circle"
                            folder="clubs/{{ $club->id }}/instructors"
                            filename="instructor_{{ time() }}"
                            buttonText="Select Photo"
                            buttonClass="btn btn-outline-success btn-sm"
                        />
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Add Instructor</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
