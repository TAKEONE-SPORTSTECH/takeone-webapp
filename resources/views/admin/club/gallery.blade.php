@extends('layouts.admin-club')

@section('club-admin-content')
<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold mb-1">Gallery</h2>
            <p class="text-muted mb-0">Manage your club photos and media</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadImageModal">
            <i class="bi bi-plus-lg me-2"></i>Add Images
        </button>
    </div>

    @if(isset($images) && count($images) > 0)
    <div class="row g-3">
        @foreach($images as $image)
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="position-relative">
                    <img src="{{ asset('storage/' . $image->path) }}" alt="{{ $image->caption ?? 'Gallery image' }}" class="card-img-top" style="height: 200px; object-fit: cover;">
                    <div class="position-absolute top-0 end-0 p-2">
                        <button class="btn btn-sm btn-danger rounded-circle" onclick="deleteImage({{ $image->id }})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                @if($image->caption)
                <div class="card-body py-2">
                    <p class="small mb-0">{{ $image->caption }}</p>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-images text-muted" style="font-size: 4rem;"></i>
            <h5 class="mt-3 mb-2">No images yet</h5>
            <p class="text-muted mb-3">Upload photos to showcase your club</p>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadImageModal">
                <i class="bi bi-plus-lg me-2"></i>Add Images
            </button>
        </div>
    </div>
    @endif
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadImageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">Upload Images</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.club.gallery.upload', $club->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Select Images</label>
                        <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Caption (optional)</label>
                        <input type="text" name="caption" class="form-control" placeholder="Enter caption...">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Upload</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
