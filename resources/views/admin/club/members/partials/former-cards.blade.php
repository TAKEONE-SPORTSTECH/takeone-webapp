@if($formerMembers->total() > 0)
<div class="mb-4 flex items-center gap-2 px-1">
    <i class="bi bi-person-dash text-gray-400"></i>
    <span class="text-sm text-gray-500">{{ trans_choice('admin.partials_former_cards_summary', $formerMembers->total(), ['count' => $formerMembers->total()]) }}</span>
</div>
<div class="grid gap-6" style="grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));">
    @foreach($formerMembers as $member)
    @php
        $user = $member->user;
        if (!$user) continue;
        $guardian    = $user->guardians->first()?->guardian ?? null;
        $userSubs    = $subscriptions->get((string) $user->id) ?? collect();
        $phoneNumber = is_array($user->mobile) ? ($user->mobile['number'] ?? '') : preg_replace('/^\+?\d{1,3}/', '', $user->mobile ?? '');
    @endphp
    <x-member-card
        :member="$user"
        :href="route('member.show', $user->uuid)"
        :guardian="$guardian"
        :memberSince="$member->created_at"
        footerLabel="{{ __('admin.partials_former_cards_footer_label') }}"
        footerStyle="translucent"
        cardClass="member-card opacity-75"
        class="member-item"
        data-name="{{ strtolower($user->full_name ?? '') }}"
        data-phone="{{ $phoneNumber }}"
        data-email="{{ strtolower($user->email ?? '') }}"
        data-status="former"
        data-member-id="{{ $user->id }}"
        data-popup-url="{{ route('admin.club.members.popup', [$club->slug, $user->id]) }}"
    />
    @endforeach
</div>
<div class="mt-6">{{ $formerMembers->links() }}</div>
@else
<div class="text-center py-16 text-gray-400">
    <i class="bi bi-person-check text-4xl mb-3 block"></i>
    <p class="text-sm">{{ __('admin.partials_former_cards_empty') }}</p>
</div>
@endif
