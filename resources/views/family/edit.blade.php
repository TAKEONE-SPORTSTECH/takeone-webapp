@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
    <x-profile-modal
        :user="$relationship->dependent"
        :formAction="$relationship->relationship_type === 'admin_view' ? route('admin.platform.members.update', $relationship->dependent->id) : route('family.update', $relationship->dependent->id)"
        formMethod="PUT"
        :cancelUrl="$relationship->relationship_type === 'admin_view' ? route('admin.platform.members') : route('members.index')"
        :uploadUrl="$relationship->relationship_type === 'admin_view' ? route('admin.platform.members.upload-picture', $relationship->dependent->id) : route('family.upload-picture', $relationship->dependent->id)"
        :showRelationshipFields="$relationship->relationship_type !== 'admin_view' && $relationship->relationship_type !== 'self'"
        :relationship="$relationship"
    />
</div>

@endsection
