@extends('layouts.app')

@section('title', __('nav.tab_feed'))

@section('content')
<div class="t1-desk px-4 sm:px-6 lg:px-8 py-6">
    @include('ui::feed.partials.body')
</div>
@endsection
