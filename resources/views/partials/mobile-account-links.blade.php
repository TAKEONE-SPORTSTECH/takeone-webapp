{{-- Account actions for the drawer footer (moved out of the header). --}}
{{-- $hideAdminPanel: pass true when the including shell already renders its own "Admin Panel" link (avoids a duplicate entry). --}}
@if(Auth::check() && Auth::user()->isSuperAdmin() && ! ($hideAdminPanel ?? false))
<a href="{{ route('admin.platform.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-foreground hover:bg-accent">
    <i class="bi bi-sliders2 text-lg w-5 text-center"></i>{{ __('nav.admin_panel') }}
</a>
@endif
<a href="{{ route('security.show') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-foreground hover:bg-accent">
    <i class="bi bi-shield-lock text-lg w-5 text-center"></i>{{ __('nav.security') }}
</a>
<a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('mobile-logout').submit();"
   class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-destructive hover:bg-accent">
    <i class="bi bi-box-arrow-right text-lg w-5 text-center"></i>{{ __('nav.sign_out') }}
</a>
<form id="mobile-logout" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
