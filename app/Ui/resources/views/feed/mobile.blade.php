@extends('members::layouts.personal-mobile')

@section('title', __('nav.tab_feed'))

@section('personal-content')
    @include('ui::feed.partials.body')
@endsection
