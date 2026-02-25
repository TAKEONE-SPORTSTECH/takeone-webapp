@extends('layouts.admin-club')

@section('club-admin-content')
<div x-data="{
    showPackageModal: false,
    packageModalMode: 'add',
    openAddModal() {
        this.packageModalMode = 'add';
        this.showPackageModal = true;
        this.$nextTick(() => window.resetPackageForm && window.resetPackageForm());
    },
    openEditModal(pkg) {
        this.packageModalMode = 'edit';
        this.showPackageModal = true;
        this.$nextTick(() => window.populatePackageForm && window.populatePackageForm(pkg));
    }
}">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="tf-section-title">Packages Management</h2>
            <p class="text-muted-foreground mb-0">Create and manage membership packages</p>
        </div>
        <button class="btn btn-primary" @click="openAddModal()">
            <i class="bi bi-plus-lg mr-2"></i>Add Package
        </button>
    </div>

    @if(isset($packages) && count($packages) > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($packages as $package)
        <x-package-card :package="$package" :club="$club" :instructors-map="$instructorsMap">
            <x-slot:actions>
                <button class="btn btn-sm btn-outline-primary" title="Edit"
                        @click="openEditModal(packagesData.find(p => p.id === {{ $package->id }}))">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-sm btn-secondary" title="Duplicate">
                    <i class="bi bi-copy"></i>
                </button>
                <form action="{{ route('admin.club.packages.destroy', [$club->slug, $package->id]) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this package?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </x-slot:actions>
        </x-package-card>
        @endforeach
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-16">
            <div class="tf-empty-icon">
                <i class="bi bi-box text-gray-400 text-4xl"></i>
            </div>
            <h5 class="text-xl font-semibold mb-2">No packages yet</h5>
            <p class="text-muted-foreground mb-4">Create membership packages for your club to get started</p>
            <button class="btn btn-primary" @click="openAddModal()">
                <i class="bi bi-plus-lg mr-2"></i>Add Package
            </button>
        </div>
    </div>
    @endif

    @include('admin.club.packages.partials.modal')
</div>

@php
    $packagesJson = $packages->map(function($p) {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'price' => $p->price,
            'duration_months' => $p->duration_months,
            'gender' => $p->gender ?? 'mixed',
            'age_min' => $p->age_min,
            'age_max' => $p->age_max,
            'cover_image' => $p->cover_image,
            'is_popular' => $p->is_popular ?? false,
            'activities' => $p->activities->map(function($a) {
                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'title' => $a->name,
                    'duration_minutes' => $a->duration_minutes,
                    'schedule' => is_string($a->pivot->schedule) ? json_decode($a->pivot->schedule, true) : ($a->pivot->schedule ?? $a->schedule),
                    'instructor_id' => $a->pivot->instructor_id,
                ];
            }),
        ];
    });
@endphp

@push('scripts')
<script>
    const packagesData = @json($packagesJson);
</script>
@endpush
@endsection
