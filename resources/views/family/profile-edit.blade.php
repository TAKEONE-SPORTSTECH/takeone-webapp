@extends('layouts.app')

@section('content')
<div class="tf-container">
    <x-profile-modal
        :user="$user"
        :formAction="route('profile.update')"
        formMethod="PUT"
        :cancelUrl="route('profile.show')"
        :uploadUrl="route('profile.upload-picture')"
    />
</div>

@endsection
