{{-- Generic mobile chrome for platform-admin pages that render an
     @section('admin-content') body (e.g. the realtime plugin). Wraps the same
     responsive content in a sticky mobile header instead of the desktop sidebar. --}}
@extends('layouts.app')

@section('hide-navbar', true)

@section('content')
<div class="min-h-screen bg-background pb-20">
    <header class="sticky top-0 z-40 bg-white border-b border-border">
        <div class="flex items-center gap-2 px-3 h-14">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('admin.platform.index') }}')"
                    class="m-press w-10 h-10 -ml-1 rounded-xl flex items-center justify-center text-foreground" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-xl"></i>
            </button>
            <p class="flex-1 min-w-0 text-base font-bold text-primary truncate">@yield('admin-mobile-title', __('platform.admin'))</p>
        </div>
    </header>

    <div class="px-4 py-4">
        @yield('admin-content')
    </div>
</div>
@endsection
