{{-- The account menu behind the Play face on desktop (new UI). Carries every
     destination the classic profile dropdown had, so nothing becomes
     unreachable when the new bar replaces the old one. --}}
@php $au = Auth::user(); @endphp
<div class="t1-acct" x-show="acct" x-cloak @click.outside="acct=false" @keydown.escape.window="acct=false"
     x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
     style="max-height:calc(100vh - 90px);overflow-y:auto">
    <a class="t1-who" href="{{ route('member.show', $au->uuid) }}">
        <span class="t1-face" style="width:40px;height:40px">
            @if($au->profile_picture)<img src="{{ file_url($au->profile_picture) }}?v={{ optional($au->updated_at)->timestamp }}" alt="">@else<x-gender-avatar :gender="$au->gender" class="w-full h-full" />@endif
        </span>
        <span style="min-width:0"><b>{{ $au->full_name ?? $au->name }}</b><small>{{ __('nav.view_profile') }}</small></span>
    </a>
    @include('ui::shell.theme-switch')
    <a href="{{ route('me.videos') }}"><i class="bi bi-camera-reels"></i>{{ __('nav.my_videos') }}</a>
    <a href="{{ route('openmat') }}"><i class="bi bi-fire"></i>{{ __('nav.open_mat') }}</a>
    <a href="{{ route('member.show', $au->uuid) }}#affiliations"><i class="bi bi-diagram-3"></i>{{ __('nav.affiliations') }}</a>
    <a href="{{ route('member.show', $au->uuid) }}#goals"><i class="bi bi-graph-up-arrow"></i>{{ __('nav.my_progress') }}</a>
    <a href="{{ route('members.index') }}"><i class="bi bi-people"></i>{{ __('nav.family') }}</a>
    <a href="{{ route('me.packages') }}"><i class="bi bi-box"></i>{{ __('nav.my_packages') }}</a>
    @if(config('certificates.enabled'))<a href="{{ route('me.certificates.index') }}"><i class="bi bi-patch-check"></i>{{ __('cert::messages.mine_title') }}</a>@endif
    <a href="{{ route('me.payments') }}"><i class="bi bi-credit-card"></i>{{ __('nav.payments') }}</a>
    <a href="{{ route('bills.index') }}"><i class="bi bi-receipt"></i>{{ __('nav.layouts_app_payments_subscriptions') }}</a>
    <a href="{{ route('me.people') }}"><i class="bi bi-people-fill"></i>{{ __('personal.find_people') }}</a>
    <button type="button" @click="acct=false; window.dispatchEvent(new CustomEvent('qr-scan:open', { detail: { manual: true, manualLength: 6, manualLabel: @js(__('header.scan_or_type')), manualPlaceholder: @js(__('header.scan_code_placeholder')) } }))"><i class="bi bi-qr-code-scan"></i>{{ __('header.scan_qr') }}</button>
    <a href="{{ $au->hasApprovedBusiness() ? route('business.dashboard') : route('business.setup') }}"><i class="bi bi-buildings"></i>{{ __('nav.business_account') }}</a>
    <a href="{{ route('me.app') }}"><i class="bi bi-phone"></i>{{ __('nav.get_app') }}</a>
    <a href="{{ route('security.show') }}"><i class="bi bi-shield-lock"></i>{{ __('nav.layouts_app_security') }}</a>
    <a href="{{ route('me.settings') }}"><i class="bi bi-gear"></i>{{ __('nav.account_settings') }}</a>
    @if($au->isSuperAdmin())<a href="{{ route('admin.platform.index') }}"><i class="bi bi-shield-check"></i>{{ __('nav.admin_panel') }}</a>@endif
    @if(\App\Ui\NewUi::mode() === 'preview' && \App\Ui\NewUi::canPreview())
        <form method="POST" action="{{ route('me.new-ui.off') }}">@csrf<button type="submit"><i class="bi bi-arrow-counterclockwise"></i>{{ __('ui::messages.classic_design') }}</button></form>
    @endif
    @if(session()->has('impersonate.original_id'))
        <form method="POST" action="{{ route('impersonate.leave') }}">@csrf<button type="submit" style="color:#b45309"><i class="bi bi-incognito"></i>{{ __('nav.exit_impersonation') }}</button></form>
    @endif
    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit"><i class="bi bi-box-arrow-right"></i>{{ __('nav.sign_out') }}</button></form>
</div>
