@extends('layouts.admin-club')

@section('club-admin-content')
<div x-data="{ showAddPackageModal: false }">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h2 class="text-2xl font-bold mb-1">Packages</h2>
            <p class="text-muted-foreground mb-0">Manage membership packages</p>
        </div>
        <button class="btn btn-primary" @click="showAddPackageModal = true">
            <i class="bi bi-plus-lg mr-2"></i>Add Package
        </button>
    </div>

    @if(isset($packages) && count($packages) > 0)
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($packages as $package)
        <div class="card border-0 shadow-sm h-full">
            <div class="card-body">
                <div class="flex justify-between items-start mb-3">
                    <div>
                        <h5 class="font-semibold mb-1">{{ $package->name }}</h5>
                        <span class="badge bg-primary">{{ $package->duration_days ?? 30 }} days</span>
                    </div>
                    @if($package->is_popular ?? false)
                    <span class="badge bg-warning text-dark">Popular</span>
                    @endif
                </div>

                <p class="text-muted-foreground text-sm mb-3">{{ Str::limit($package->description, 100) }}</p>

                <div class="mb-3">
                    <span class="text-2xl font-bold text-primary">{{ $club->currency ?? 'BHD' }} {{ number_format($package->price, 2) }}</span>
                    <span class="text-muted-foreground">/ {{ $package->duration_days ?? 30 }} days</span>
                </div>

                @if($package->features)
                <ul class="list-none text-sm mb-3">
                    @foreach(json_decode($package->features, true) ?? [] as $feature)
                    <li class="mb-1">
                        <i class="bi bi-check-circle text-success mr-2"></i>{{ $feature }}
                    </li>
                    @endforeach
                </ul>
                @endif

                <div class="flex gap-2">
                    <button class="btn btn-sm btn-outline-primary flex-1">
                        <i class="bi bi-pencil mr-1"></i>Edit
                    </button>
                    <button class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-12">
            <i class="bi bi-box text-muted-foreground text-6xl"></i>
            <h5 class="mt-3 mb-2">No packages yet</h5>
            <p class="text-muted-foreground mb-3">Create membership packages for your club</p>
            <button class="btn btn-primary" @click="showAddPackageModal = true">
                <i class="bi bi-plus-lg mr-2"></i>Add Package
            </button>
        </div>
    </div>
    @endif

    @include('admin.club.packages.add')
</div>
@endsection
