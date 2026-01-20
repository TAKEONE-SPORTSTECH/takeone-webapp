@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0">Add Family Member</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('family.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" value="{{ old('full_name') }}" required>
                            @error('full_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-muted">(Optional for children)</span></label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}">
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>Male</option>
                                    <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Female</option>
                                </select>
                                @error('gender')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="birthdate" class="form-label">Birthdate</label>
                                <input type="date" class="form-control @error('birthdate') is-invalid @enderror" id="birthdate" name="birthdate" value="{{ old('birthdate') }}" required>
                                @error('birthdate')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="blood_type" class="form-label">Blood Type <span class="text-muted">(Optional)</span></label>
                                <input type="text" class="form-control @error('blood_type') is-invalid @enderror" id="blood_type" name="blood_type" value="{{ old('blood_type') }}">
                                @error('blood_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="nationality" class="form-label">Nationality</label>
                                <input type="text" class="form-control @error('nationality') is-invalid @enderror" id="nationality" name="nationality" value="{{ old('nationality') }}" required>
                                @error('nationality')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="relationship_type" class="form-label">Relationship</label>
                                <select class="form-select @error('relationship_type') is-invalid @enderror" id="relationship_type" name="relationship_type" required>
                                    <option value="">Select Relationship</option>
                                    <option value="son" {{ old('relationship_type') == 'son' ? 'selected' : '' }}>Son</option>
                                    <option value="daughter" {{ old('relationship_type') == 'daughter' ? 'selected' : '' }}>Daughter</option>
                                    <option value="spouse" {{ old('relationship_type') == 'spouse' ? 'selected' : '' }}>Spouse</option>
                                    <option value="sponsor" {{ old('relationship_type') == 'sponsor' ? 'selected' : '' }}>Sponsor</option>
                                    <option value="other" {{ old('relationship_type') == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                                @error('relationship_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_billing_contact" name="is_billing_contact" value="1" {{ old('is_billing_contact') ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_billing_contact">Is Billing Contact</label>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('family.dashboard') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Add Family Member</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
