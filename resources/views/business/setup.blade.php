@extends('layouts.app')

@section('title', __('business.business_setup_page_title'))

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ __('business.business_setup_heading') }}</h1>
        <p class="text-sm text-muted-foreground">{{ __('business.business_setup_subheading') }}</p>
    </div>

    @if(!$business)
        {{-- No business yet — show creation form --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start gap-4 mb-6">
                <div class="w-12 h-12 rounded-full bg-accent flex items-center justify-center flex-shrink-0">
                    <i class="bi bi-buildings text-primary text-xl"></i>
                </div>
                <div>
                    <h2 class="font-semibold text-foreground">{{ __('business.business_setup_create_title') }}</h2>
                    <p class="text-sm text-muted-foreground">{{ __('business.business_setup_create_desc') }}</p>
                </div>
            </div>

            <form action="{{ route('business.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('business.business_setup_name_label') }} <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="120"
                           placeholder="{{ __('business.business_setup_name_placeholder') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">{{ __('business.business_setup_description_label') }}</label>
                    <textarea id="description" name="description" rows="3" maxlength="1000"
                              placeholder="{{ __('business.business_setup_description_placeholder') }}"
                              class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">{{ old('description') }}</textarea>
                    @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="pt-2">
                    <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium">
                        <i class="bi bi-send me-2"></i>{{ __('business.business_setup_submit_button') }}
                    </button>
                </div>
            </form>
        </div>

    @elseif($business->isPending())
        {{-- Pending approval --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0">
                    <i class="bi bi-hourglass-split text-amber-600 text-xl"></i>
                </div>
                <div>
                    <h2 class="font-semibold text-foreground">{{ $business->name }}</h2>
                    <p class="text-sm text-muted-foreground mt-1">
                        <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">{{ __('business.business_setup_status_pending') }}</span>
                    </p>
                    <p class="text-sm text-muted-foreground mt-3">{{ __('business.business_setup_pending_desc') }}</p>
                </div>
            </div>
        </div>

    @elseif($business->status === \App\Models\Business::STATUS_REJECTED)
        {{-- Rejected — allow resubmission --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start gap-4 mb-4">
                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                    <i class="bi bi-x-circle text-red-600 text-xl"></i>
                </div>
                <div>
                    <h2 class="font-semibold text-foreground">{{ $business->name }}</h2>
                    <p class="text-sm text-muted-foreground mt-1">
                        <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">{{ __('business.business_setup_status_rejected') }}</span>
                    </p>
                    @if($business->rejection_reason)
                        <p class="text-sm text-foreground mt-3"><span class="font-medium">{{ __('business.business_setup_reason_label') }}</span> {{ $business->rejection_reason }}</p>
                    @endif
                </div>
            </div>
            <form action="{{ route('business.store') }}" method="POST" class="space-y-4 border-t border-gray-100 pt-4">
                @csrf
                <p class="text-sm text-muted-foreground">{{ __('business.business_setup_resubmit_hint') }}</p>
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('business.business_setup_name_label') }} <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $business->name) }}" required maxlength="120"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">{{ __('business.business_setup_description_label') }}</label>
                    <textarea id="description" name="description" rows="3" maxlength="1000"
                              class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">{{ old('description', $business->description) }}</textarea>
                </div>
                <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium">
                    <i class="bi bi-arrow-repeat me-2"></i>{{ __('business.business_setup_resubmit_button') }}
                </button>
            </form>
        </div>

    @else
        {{-- Approved --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                    <i class="bi bi-check-circle text-green-600 text-xl"></i>
                </div>
                <div class="flex-1">
                    <h2 class="font-semibold text-foreground">{{ $business->name }}</h2>
                    <p class="text-sm text-muted-foreground mt-1">
                        <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">{{ __('business.business_setup_status_approved') }}</span>
                    </p>
                    <p class="text-sm text-muted-foreground mt-3">{{ __('business.business_setup_approved_desc') }}</p>
                    <form action="{{ route('view.switch') }}" method="POST" class="mt-4">
                        @csrf
                        <input type="hidden" name="mode" value="business">
                        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium">
                            <i class="bi bi-speedometer2 me-2"></i>{{ __('business.business_setup_dashboard_button') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

</div>
@endsection
