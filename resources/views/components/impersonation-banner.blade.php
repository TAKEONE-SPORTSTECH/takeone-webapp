@if(session()->has('impersonate.original_id'))
@php
    $impersonatedName = auth()->user()->full_name ?? auth()->user()->name ?? 'this user';
@endphp
<form method="POST" action="{{ route('impersonate.leave') }}" class="shrink-0">
    @csrf
    <button type="submit"
        title="You are viewing as {{ $impersonatedName }} — click to return to your admin account"
        class="inline-flex items-center gap-1.5 bg-amber-500 text-amber-950 rounded-full px-3 py-1.5 text-xs font-semibold hover:bg-amber-400 transition-colors">
        <i class="bi bi-incognito text-sm"></i>
        <span class="hidden sm:inline">Exit impersonation</span>
        <i class="bi bi-box-arrow-left"></i>
    </button>
</form>
@endif
