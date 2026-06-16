@foreach($members as $member)
@php
    $user = $member->user;
    if (!$user) continue;
    $guardian = $user->guardians->first()?->guardian ?? null;
    $userSubs = $subscriptions->get((string) $user->id) ?? $subscriptions->get($user->id, collect());
    $isOwner = $userSubs->where('type', 'owner')->isNotEmpty();
    $memberPackages = $userSubs->where('type', 'regular')->pluck('package')->filter();
    $phoneNumber = is_array($user->mobile) ? ($user->mobile['number'] ?? '') : preg_replace('/^\+?\d{1,3}/', '', $user->mobile ?? '');
    $hasActivePackage = $isOwner || $userSubs->where('type', 'regular')->whereIn('status', ['active', 'pending'])->isNotEmpty();
    $footerLabel = $isOwner ? 'OWNER' : ($hasActivePackage ? 'ACTIVE MEMBER' : 'CLUB MEMBER');
@endphp
<x-member-card
    :member="$user"
    :href="route('member.show', $user->uuid)"
    :guardian="$guardian"
    :footerLabel="$footerLabel"
    footerStyle="translucent"
    :memberSince="$member->created_at"
    cardClass="member-card"
    class="member-item"
    data-name="{{ strtolower($user->full_name ?? '') }}"
    data-rank="member"
    data-phone="{{ $phoneNumber }}"
    data-email="{{ strtolower($user->email ?? '') }}"
    data-status="{{ $member->status }}"
    data-has-enrollment="{{ $hasActivePackage ? '1' : '0' }}"
    data-member-id="{{ $user->id }}"
    data-popup-url="{{ route('admin.club.members.popup', [$club->slug, $user->id]) }}"
>
    <x-slot:badges>
        @if($isOwner)
            <span class="badge bg-warning text-dark">&#128081; Owner</span>
        @endif
        @foreach($memberPackages as $pkg)
            <span class="badge bg-primary">{{ $pkg->name }}</span>
        @endforeach
        @if($member->achievements > 0)
            <span class="badge bg-warning text-dark">{{ $member->achievements }} &#127942;</span>
        @endif
    </x-slot:badges>
</x-member-card>
@endforeach
