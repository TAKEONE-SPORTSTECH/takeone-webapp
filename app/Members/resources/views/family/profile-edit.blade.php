@extends('layouts.app')

@section('content')
<div class="tf-container">
    <x-profile-modal
        :user="$user"
        :formAction="route('member.update', $user->id)"
        formMethod="PUT"
        :cancelUrl="route('member.show', $user->uuid)"
        :uploadUrl="route('member.upload-picture', $user->id)"
    />
</div>

@endsection
