@extends('layouts.personal-mobile')

@section('title', 'Account Settings')

@section('personal-content')
<div class="space-y-4">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
        <h3 class="font-semibold text-foreground mb-3">Account</h3>
        <div class="space-y-3 text-sm">
            <div class="flex items-center gap-3"><i class="bi bi-person text-muted-foreground w-5"></i><span class="text-foreground">{{ $user->full_name }}</span></div>
            <div class="flex items-center gap-3"><i class="bi bi-envelope text-muted-foreground w-5"></i><span class="text-foreground truncate">{{ $user->email }}</span></div>
        </div>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 divide-y divide-gray-50">
        <a href="{{ route('member.show', $user->uuid) }}" class="flex items-center justify-between p-4"><span class="text-sm text-foreground"><i class="bi bi-person-gear mr-2 text-muted-foreground"></i>Edit profile</span><i class="bi bi-chevron-right text-muted-foreground"></i></a>
        <a href="{{ route('security.show') }}" class="flex items-center justify-between p-4"><span class="text-sm text-foreground"><i class="bi bi-shield-lock mr-2 text-muted-foreground"></i>Security &amp; password</span><i class="bi bi-chevron-right text-muted-foreground"></i></a>
        <a href="{{ route('me.payments') }}" data-shell-link data-route="me.payments" class="flex items-center justify-between p-4"><span class="text-sm text-foreground"><i class="bi bi-receipt mr-2 text-muted-foreground"></i>Invoices</span><i class="bi bi-chevron-right text-muted-foreground"></i></a>
    </div>
</div>
@endsection
