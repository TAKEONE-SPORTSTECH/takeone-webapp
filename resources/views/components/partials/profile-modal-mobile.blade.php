{{-- Mobile chrome for the profile modal — full-height bottom sheet. --}}
{{-- Shares the same Alpine component (state, methods, IDs) as the desktop chrome; --}}
{{-- only the surrounding layout differs. Tab content comes from profile-modal-fields. --}}
@php
    // Short labels for the cramped mobile tab bar.
    $mTabs = [];
    if ($showPhotoTab) $mTabs[] = ['key' => 'photo',      'icon' => 'bi-camera',       'label' => 'Photo'];
    $mTabs[] = ['key' => 'personal',   'icon' => 'bi-person-badge', 'label' => 'Personal'];
    $mTabs[] = ['key' => 'social',     'icon' => 'bi-share',        'label' => 'Social'];
    $mTabs[] = ['key' => 'additional', 'icon' => 'bi-shield-plus',  'label' => 'Medical'];
    $mTabs[] = ['key' => 'docs', 'icon' => 'bi-file-earmark-person', 'label' => __('shared.components_profile_modal_tab_docs')];
    if ($showSecurityTab ?? false) $mTabs[] = ['key' => 'security', 'icon' => 'bi-shield-lock', 'label' => __('shared.components_profile_modal_tab_security')];
@endphp

<div class="flex min-h-full items-end justify-center sm:items-center sm:p-4">
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
         class="relative bg-white rounded-t-3xl sm:rounded-2xl shadow-xl w-full sm:max-w-lg flex flex-col"
         style="height: 92vh; max-height: 92vh;"
         @click.stop>

        {{-- Grab handle --}}
        <div class="pt-2.5 pb-1 flex justify-center sm:hidden flex-shrink-0">
            <span class="w-10 h-1.5 rounded-full bg-gray-300"></span>
        </div>

        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 bg-primary text-white rounded-t-3xl sm:rounded-t-2xl flex-shrink-0">
            <h5 class="text-base font-semibold flex items-center min-w-0">
                <i class="bi {{ $modalIcon }} mr-2 flex-shrink-0"></i>
                <span class="truncate">{{ $modalTitle }}</span>
            </h5>
            <button type="button" @click="closeModal()" class="text-white/90 hover:text-white text-2xl leading-none w-8 h-8 flex items-center justify-center -mr-1">&times;</button>
        </div>

        {{-- Tab bar — one row, no scrolling.
             Six tabs will not fit as labelled pills on a phone, and a bar that
             scrolls sideways hides its own contents: the tab you want is the one
             off-screen. So only the SELECTED pill carries its label and the rest
             collapse to their icon, which is enough to recognise them and leaves
             the whole set visible at once. The label slides in as a pill is
             chosen, so nothing appears or vanishes abruptly.

             Every pill keeps an aria-label and a title, because an icon on its
             own is not a name. --}}
        <div class="border-b border-gray-100 flex-shrink-0">
            <nav class="flex items-center gap-1 px-2.5 py-2" role="tablist">
                @foreach($mTabs as $t)
                <button type="button" @click="activeTab = '{{ $t['key'] }}'"
                        role="tab"
                        :aria-selected="activeTab === '{{ $t['key'] }}'"
                        aria-label="{{ $t['label'] }}" title="{{ $t['label'] }}"
                        :class="activeTab === '{{ $t['key'] }}'
                            ? 'bg-primary text-white shadow-sm shadow-primary/25 px-3'
                            : 'bg-gray-100 text-gray-500 px-0 w-9'"
                        class="h-8 rounded-full text-xs font-semibold whitespace-nowrap flex items-center justify-center gap-1.5 transition-all duration-200 m-press flex-shrink-0 overflow-hidden">
                    <i class="bi {{ $t['icon'] }} text-[13px]"></i>
                    <span x-show="activeTab === '{{ $t['key'] }}'"
                          x-transition:enter="transition ease-out duration-200"
                          x-transition:enter-start="opacity-0 max-w-0"
                          x-transition:enter-end="opacity-100 max-w-[7rem]"
                          class="max-w-[7rem]">{{ $t['label'] }}</span>
                </button>
                @endforeach
            </nav>
        </div>

        {{-- Form / scrollable content --}}
        <form method="POST" action="{{ $formAction }}" id="{{ $formId }}" class="profile-modal-form flex-1 overflow-y-auto overscroll-contain" @submit.prevent="submitForm()">
            @csrf
            @if(!$isCreate)
                @method($formMethod)
            @endif
            <div class="px-4 py-4">
                @include('components.partials.profile-modal-fields')
            </div>
        </form>

        {{-- Footer — sticky action bar --}}
        <div class="px-4 py-3 bg-gray-50 border-t flex-shrink-0" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
            <div class="flex items-center gap-2">
                <button type="button"
                        x-show="activeTab !== tabs[0]"
                        @click="prevTab()"
                        class="flex-shrink-0 w-11 h-11 rounded-xl flex items-center justify-center border border-gray-200 text-gray-600 bg-white hover:bg-gray-50 transition-colors"
                        title="Previous">
                    <i class="bi bi-arrow-left"></i>
                </button>

                <button type="button" class="flex-1 btn btn-success py-2.5" id="{{ $formId }}_submitBtn" @click="submitForm()" :disabled="isSubmitting">
                    <span x-show="!isSubmitting"><i class="bi {{ $submitIcon }} mr-1"></i>{{ $submitText }}</span>
                    <span x-show="isSubmitting"><span class="inline-block animate-spin mr-2">&#8635;</span>{{ $isCreate ? 'Creating...' : 'Updating...' }}</span>
                </button>

                {{-- Follows the tab list, so the conditional Security tab is reachable. --}}
                <button type="button"
                        x-show="activeTab !== tabs[tabs.length - 1]"
                        @click="nextTab()"
                        class="flex-shrink-0 btn btn-primary py-2.5 px-4">
                    Next<i class="bi bi-arrow-right ml-1"></i>
                </button>
            </div>
        </div>
    </div>
</div>
