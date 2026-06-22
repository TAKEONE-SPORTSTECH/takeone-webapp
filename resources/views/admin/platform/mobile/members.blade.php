@extends('layouts.app')

@section('hide-navbar', true)
@section('title', 'All Members')

@section('content')
<div x-data class="min-h-screen bg-background pb-20">

    {{-- ===== Header ===== --}}
    <header class="sticky top-0 z-40 bg-white border-b border-border">
        <div class="flex items-center gap-2 px-3 h-14">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('admin.platform.index') }}')"
                    class="m-press w-10 h-10 -ml-1 rounded-xl flex items-center justify-center text-foreground" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-xl"></i>
            </button>
            <p class="flex-1 min-w-0 text-base font-bold text-primary truncate">{{ __('platform.all_members') }}</p>
            <button type="button" @click="$dispatch('open-member-create-modal')"
                    class="m-press w-10 h-10 rounded-xl flex items-center justify-center text-primary" aria-label="{{ __('platform.add_member') }}">
                <i class="bi bi-person-plus text-xl"></i>
            </button>
        </div>
    </header>

    <div class="px-4 pt-4">
        {{-- Search --}}
        <div class="relative mb-4">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" id="memberSearch" autocomplete="off" value="{{ $search ?? '' }}"
                   placeholder="{{ __('platform.search_members') }}"
                   class="w-full pl-10 pr-3 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/40 focus:border-transparent text-sm">
        </div>

        {{-- Results (swapped in place on search; mobile-native card layout) --}}
        <div id="membersResults">
            @include('admin.platform.members._results-mobile')
        </div>
    </div>
</div>

{{-- Member create modal --}}
<x-profile-modal
    mode="create"
    :title="__('platform.add_platform_member')"
    :subtitle="__('platform.add_platform_member_desc')"
    :showPasswordFields="true"
    :formAction="route('admin.platform.members.store')"
    formMethod="POST"
/>

{{-- Member quick-view popup --}}
@include('admin.club.members.partials.member-popup')

@include('admin.platform.members._scripts')
@endsection
