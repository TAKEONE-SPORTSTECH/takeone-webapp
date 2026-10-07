@extends('layouts.app')

@section('title', __('ui::messages.explore'))

@section('content')
<div class="t1-desk px-4 sm:px-6 lg:px-8 py-6">
    @include('ui::explore.partials.page')
</div>
@endsection
