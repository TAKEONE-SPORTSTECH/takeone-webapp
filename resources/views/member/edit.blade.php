@extends('layouts.app')

@section('content')
<div class="container py-4">
    <x-edit-profile-modal
        :user="$relationship->dependent"
        :formAction="$relationship->relationship_type === 'admin_view' ? route('admin.platform.members.update', $relationship->dependent->id) : route('member.update', $relationship->dependent->id)"
        formMethod="PUT"
        :cancelUrl="$relationship->relationship_type === 'admin_view' ? route('admin.platform.members') : route('members.index')"
        :uploadUrl="$relationship->relationship_type === 'admin_view' ? route('admin.platform.members.upload-picture', $relationship->dependent->id) : route('member.upload-picture', $relationship->dependent->id)"
        :showRelationshipFields="$relationship->relationship_type !== 'admin_view' && $relationship->relationship_type !== 'self'"
        :relationship="$relationship"
    />
</div>

@endsection
