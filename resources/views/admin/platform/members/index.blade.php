@extends('layouts.admin')

@section('admin-content')
<div x-data>
    <!-- Page Header -->
    <div class="mb-4">
        <h1 class="text-2xl font-bold mb-2">All Members</h1>
        <p class="text-muted-foreground">Manage all platform members</p>
    </div>

    <!-- Search and Actions Bar -->
    <div class="flex justify-between items-center mb-4">
        <div class="grow mr-3">
            <input type="text" id="memberSearch" class="form-control" placeholder="Search members by name, phone, nationality, or gender..." value="{{ $search ?? '' }}">
        </div>
        <button class="btn btn-primary" @click="$dispatch('open-member-create-modal')">
            <i class="bi bi-plus-circle mr-2"></i>Add Member
        </button>
    </div>

    <!-- Members Grid + Pagination (swapped in place on search) -->
    <div id="membersResults">
        @include('admin.platform.members._results')
    </div>
</div>

{{-- Member Create Modal --}}
<x-profile-modal
    mode="create"
    title="Add Platform Member"
    subtitle="Fill in the details to add a new platform member"
    :showPasswordFields="true"
    :formAction="route('admin.platform.members.store')"
    formMethod="POST"
/>

{{-- Member quick-view popup --}}
@include('admin.club.members.partials.member-popup')

@include('admin.platform.members._scripts')
@endsection
