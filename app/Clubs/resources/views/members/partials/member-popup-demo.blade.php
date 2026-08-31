@extends('layouts.admin-club')

@section('club-admin-content')

{{-- Trigger the popup immediately on load using the first available member --}}
@php
    $firstMember = \App\Models\Membership::where('tenant_id', $club->id)
        ->whereHas('user')
        ->with('user')
        ->first();
@endphp

@if($firstMember)
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            openMemberPopup(
                {{ $firstMember->user->id }},
                '{{ route('admin.club.members.popup', [$club->slug, $firstMember->user->id]) }}'
            );
        });
    </script>
    @endpush
@else
    <p class="text-gray-500 text-sm p-6">No members found in this club.</p>
@endif

@include('admin.club.members.partials.member-popup')

@endsection
