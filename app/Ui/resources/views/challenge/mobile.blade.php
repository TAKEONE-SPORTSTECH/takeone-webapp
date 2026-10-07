@extends('members::layouts.personal-mobile')

@section('title', __('nav.tab_challenge'))

@section('personal-content')
    @include('ui::challenge.partials.body')
@endsection
