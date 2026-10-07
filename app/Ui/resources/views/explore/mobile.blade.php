{{-- Inside the personal mobile shell (header + bottom tabs), whose <main>
     already supplies px-4 py-4 pb-24. --}}
@extends('members::layouts.personal-mobile')

@section('title', __('ui::messages.explore'))

@section('personal-content')
    @include('ui::explore.partials.page')
@endsection
