@extends('layouts.app')

@section('title', __('ui::ask.title'))

@section('content')
<div class="t1-desk px-4 sm:px-6 lg:px-8 py-6">
    @include('ui::ask.partials.body')
</div>
@endsection
