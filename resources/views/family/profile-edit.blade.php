@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
    <x-edit-profile-modal
        :user="$user"
        :formAction="route('profile.update')"
        formMethod="PUT"
        :cancelUrl="route('profile.show')"
        :uploadUrl="route('profile.upload-picture')"
    />
</div>

@endsection
