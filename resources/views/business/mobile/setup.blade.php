@extends('layouts.app')

@section('hide-navbar', true)
@section('title', $business->name ?? __('business.create_business'))

@section('content')
<div class="min-h-screen bg-background pb-20">

    {{-- ===== Header ===== --}}
    <header class="sticky top-0 z-40 bg-white border-b border-border">
        <div class="flex items-center gap-2 px-3 h-14">
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ route('me.profile') }}')"
                    class="m-press w-10 h-10 -ml-1 rounded-xl flex items-center justify-center text-foreground" aria-label="{{ __('shared.back') }}">
                <i class="bi bi-arrow-left text-xl"></i>
            </button>
            <p class="flex-1 min-w-0 text-base font-bold text-primary truncate">{{ $business ? __('business.your_business') : __('business.create_business') }}</p>
        </div>
    </header>

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-start justify-between gap-3 relative z-10">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ $business ? __('business.your_business') : __('business.create_business') }}</p>
                <h1 class="text-2xl font-black mt-0.5 leading-tight">{{ __('business.business_chain') }}</h1>
                <p class="mt-1.5 text-sm text-white/85">{{ __('business.business_chain_desc') }}</p>
            </div>
            <div class="w-12 h-12 shrink-0 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                <i class="bi bi-buildings text-xl m-float"></i>
            </div>
        </div>
    </header>

    <div class="px-4 pt-5 relative z-10 space-y-5 mobile-stagger">

        @if(!$business)
            {{-- ===== Benefits ===== --}}
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-gray-100 space-y-3">
                @foreach([
                    ['bi-collection', __('business.benefit_one_brand_title'), __('business.benefit_one_brand_desc')],
                    ['bi-speedometer2', __('business.benefit_one_dashboard_title'), __('business.benefit_one_dashboard_desc')],
                    ['bi-toggles', __('business.benefit_switch_title'), __('business.benefit_switch_desc')],
                ] as [$icon, $title, $desc])
                    <div class="flex items-start gap-3">
                        <span class="w-9 h-9 rounded-xl bg-accent text-primary flex items-center justify-center flex-shrink-0"><i class="bi {{ $icon }} text-lg"></i></span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-foreground">{{ $title }}</p>
                            <p class="text-[12px] text-muted-foreground leading-snug">{{ $desc }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- ===== Create form ===== --}}
            <form action="{{ route('business.store') }}" method="POST" class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 space-y-4">
                @csrf
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('business.business_name') }} <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="120"
                           placeholder="{{ __('business.business_name_placeholder') }}"
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl text-[15px] focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('business.description') }}</label>
                    <textarea id="description" name="description" rows="3" maxlength="1000"
                              placeholder="{{ __('business.description_placeholder') }}"
                              class="w-full px-4 py-3 border border-gray-200 rounded-xl text-[15px] focus:ring-2 focus:ring-primary/40 focus:border-transparent">{{ old('description') }}</textarea>
                    @error('description')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-start gap-2 rounded-xl bg-accent/40 px-3 py-2.5">
                    <i class="bi bi-info-circle text-primary mt-0.5"></i>
                    <p class="text-[12px] text-muted-foreground">{{ __('business.review_notice') }}</p>
                </div>
                <button type="submit" class="m-press w-full bg-primary text-white py-3.5 rounded-xl font-semibold hover:bg-primary/90 transition-colors flex items-center justify-center gap-2">
                    <i class="bi bi-send"></i> {{ __('business.submit_for_approval') }}
                </button>
            </form>

        @elseif($business->isPending())
            {{-- ===== Pending ===== --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 text-center">
                <span class="w-16 h-16 mx-auto rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center m-float"><i class="bi bi-hourglass-split text-3xl"></i></span>
                <h2 class="font-bold text-foreground mt-4 text-lg">{{ $business->name }}</h2>
                <span class="inline-block mt-2 px-3 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">{{ __('business.pending_approval') }}</span>
                <p class="text-sm text-muted-foreground mt-4 leading-relaxed">{{ __('business.pending_text') }}</p>
            </div>

        @elseif($business->status === \App\Models\Business::STATUS_REJECTED)
            {{-- ===== Rejected — resubmit ===== --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 text-center">
                <span class="w-16 h-16 mx-auto rounded-2xl bg-red-100 text-red-600 flex items-center justify-center m-float"><i class="bi bi-x-circle text-3xl"></i></span>
                <h2 class="font-bold text-foreground mt-4 text-lg">{{ $business->name }}</h2>
                <span class="inline-block mt-2 px-3 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">{{ __('business.rejected') }}</span>
                @if($business->rejection_reason)
                    <p class="text-sm text-foreground mt-4 text-left bg-red-50 rounded-xl px-3 py-2.5"><span class="font-semibold">{{ __('business.reason') }}</span> {{ $business->rejection_reason }}</p>
                @endif
            </div>

            <form action="{{ route('business.store') }}" method="POST" class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 space-y-4">
                @csrf
                <p class="text-sm text-muted-foreground">{{ __('business.update_and_resubmit') }}</p>
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('business.business_name') }} <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $business->name) }}" required maxlength="120"
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl text-[15px] focus:ring-2 focus:ring-primary/40 focus:border-transparent">
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('business.description') }}</label>
                    <textarea id="description" name="description" rows="3" maxlength="1000"
                              class="w-full px-4 py-3 border border-gray-200 rounded-xl text-[15px] focus:ring-2 focus:ring-primary/40 focus:border-transparent">{{ old('description', $business->description) }}</textarea>
                </div>
                <button type="submit" class="m-press w-full bg-primary text-white py-3.5 rounded-xl font-semibold hover:bg-primary/90 transition-colors flex items-center justify-center gap-2">
                    <i class="bi bi-arrow-repeat"></i> {{ __('business.resubmit') }}
                </button>
            </form>

        @else
            {{-- ===== Approved ===== --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 text-center">
                <span class="w-16 h-16 mx-auto rounded-2xl bg-green-100 text-green-600 flex items-center justify-center m-float"><i class="bi bi-check-circle text-3xl"></i></span>
                <h2 class="font-bold text-foreground mt-4 text-lg">{{ $business->name }}</h2>
                <span class="inline-block mt-2 px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">{{ __('business.approved') }}</span>
                <p class="text-sm text-muted-foreground mt-4 leading-relaxed">{{ __('business.approved_text') }}</p>
                <form action="{{ route('view.switch') }}" method="POST" class="mt-5">
                    @csrf
                    <input type="hidden" name="mode" value="business">
                    <button type="submit" class="m-press w-full bg-primary text-white py-3.5 rounded-xl font-semibold hover:bg-primary/90 transition-colors flex items-center justify-center gap-2">
                        <i class="bi bi-speedometer2"></i> {{ __('business.go_to_dashboard') }}
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
