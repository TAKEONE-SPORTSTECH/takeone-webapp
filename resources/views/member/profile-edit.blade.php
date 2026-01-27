@extends('layouts.app')

@section('content')
<div class="container py-4">
    <x-edit-profile-modal
        :user="$user"
        :formAction="route('profile.update')"
        formMethod="PUT"
        :cancelUrl="route('profile.show')"
        :uploadUrl="route('profile.upload-picture')"
    />
</div>

@endsection
