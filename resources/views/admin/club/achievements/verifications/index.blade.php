@extends('layouts.admin-club')

@section('club-admin-content')

<div x-data="verificationQueue()">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.club.achievements', $club->slug ?? $club->id) }}" data-shell-link data-route="admin.club.achievements"
                   class="w-8 h-8 rounded-lg flex items-center justify-center bg-card text-foreground hover:bg-accent transition-all border border-border" title="{{ __('Back to achievements') }}">
                    <i class="bi bi-arrow-left rtl:rotate-180"></i>
                </a>
                <h2 class="text-xl font-bold text-foreground">{{ __('Verification requests') }}</h2>
            </div>
            <p class="text-sm text-muted-foreground mt-0.5 ms-10">{{ __('Members asking your club to confirm achievements they earned here.') }}</p>
        </div>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200 self-start">
            <i class="bi bi-hourglass-split"></i>
            <span x-text="count"></span> {{ __('pending') }}
        </span>
    </div>

    {{-- Empty state --}}
    <div id="verificationsEmpty" class="card border-0 shadow-sm {{ $claims->isEmpty() ? '' : 'hidden' }}">
        <div class="text-center py-16 bg-white rounded-xl border border-gray-100">
            <i class="bi bi-patch-check text-muted-foreground" style="font-size:2.5rem;opacity:.3;"></i>
            <p class="mt-3 text-muted-foreground">{{ __('No pending verification requests.') }}</p>
        </div>
    </div>

    {{-- Claims --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4" id="verificationsGrid">
        @foreach($claims as $claim)
            @php
                $u = $claim['user'];
                $pic = $u['profile_picture'] ? asset('storage/'.$u['profile_picture']) : null;
                $isSkill = $claim['type'] === 'skill';
                $medalMap = ['1st' => ['🥇', __('member.templates_member_show_first_place')], '2nd' => ['🥈', __('member.templates_member_show_second_place')], '3rd' => ['🥉', __('member.templates_member_show_third_place')], 'special' => ['🏆', __('member.templates_member_show_special_award')]];
            @endphp
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 flex flex-col gap-3" data-claim="{{ $claim['uuid'] }}">
                <div class="flex items-start gap-3">
                    @if($pic)
                        <img src="{{ $pic }}" class="w-12 h-12 rounded-full object-cover flex-shrink-0" alt="">
                    @else
                        <x-gender-avatar :gender="$u['gender']" class="w-12 h-12 rounded-full flex-shrink-0" />
                    @endif
                    <div class="min-w-0 flex-1">
                        <a href="{{ $u['uuid'] ? route('member.show', $u['uuid']) : '#' }}" class="font-bold text-gray-900 hover:text-primary truncate block">{{ $u['name'] }}</a>
                        <p class="text-sm text-gray-500 truncate">{{ $claim['title'] }}</p>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium flex-shrink-0 {{ $isSkill ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800' }}">
                        <i class="bi {{ $isSkill ? 'bi-mortarboard' : 'bi-trophy' }}"></i>{{ $isSkill ? __('Skill') : __('Medal') }}
                    </span>
                </div>

                <div class="text-sm text-gray-600 flex flex-wrap gap-x-4 gap-y-1">
                    @if($claim['sport'])<span><i class="bi {{ $isSkill ? 'bi-activity' : 'bi-dribbble' }} me-1 text-gray-400"></i>{{ $claim['sport'] }}</span>@endif
                    @if($claim['date'])<span><i class="bi bi-calendar-event me-1 text-gray-400"></i>{{ $claim['date'] }}</span>@endif
                    @if($claim['meta'])<span><i class="bi bi-bar-chart me-1 text-gray-400"></i>{{ $claim['meta'] }}</span>@endif
                    <span><i class="bi bi-buildings me-1 text-gray-400"></i>{{ $claim['club_name'] }}</span>
                </div>

                @if(!empty($claim['medals']))
                    <div class="flex flex-wrap gap-2">
                        @foreach($claim['medals'] as $mt)
                            @php $medal = $medalMap[$mt] ?? ['🏅', $mt]; @endphp
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-800 border border-amber-100">{{ $medal[0] }} {{ $medal[1] }}</span>
                        @endforeach
                    </div>
                @endif

                @if($claim['evidence_url'])
                    <a href="{{ $claim['evidence_url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-sm text-primary hover:underline w-fit">
                        <i class="bi bi-paperclip"></i>{{ __('View evidence') }}
                    </a>
                @elseif(!$isSkill)
                    <span class="text-xs text-gray-400"><i class="bi bi-info-circle me-1"></i>{{ __('No evidence attached') }}</span>
                @endif

                {{-- Reject reason (revealed on demand) --}}
                <div x-data="{ rejecting: false, note: '' }">
                    <div class="flex items-center gap-2">
                        <button type="button" @click="confirmVerification($el.closest('[data-claim]'), '{{ $claim['confirm_url'] }}')"
                                class="flex-1 inline-flex items-center justify-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors">
                            <i class="bi bi-patch-check-fill"></i>{{ __('Verify') }}
                        </button>
                        <button type="button" @click="rejecting = !rejecting"
                                class="inline-flex items-center justify-center gap-2 border border-red-300 text-red-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors">
                            <i class="bi bi-x-lg"></i>{{ __('Reject') }}
                        </button>
                    </div>
                    <div x-show="rejecting" x-transition x-cloak class="mt-2">
                        <textarea x-model="note" rows="2" placeholder="{{ __('Reason (optional) — shared with the member') }}"
                                  class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-red-400 focus:border-transparent"></textarea>
                        <button type="button" @click="rejectVerification($el.closest('[data-claim]'), '{{ $claim['reject_url'] }}', note)"
                                class="mt-2 w-full bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
                            {{ __('Confirm rejection') }}
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
function verificationQueue() {
    return {
        count: {{ $claims->count() }},
        async confirmVerification(card, url) {
            const ok = await window.confirmAction({ title: '{{ __('Verify achievement?') }}', message: '{{ __('This confirms the member earned this at your club. It will show as verified on their profile.') }}', confirmText: '{{ __('Verify') }}' });
            if (!ok) return;
            await this.post(card, url, {});
        },
        async rejectVerification(card, url, note) {
            await this.post(card, url, { note: note || '' });
        },
        async post(card, url, body) {
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(body),
                });
                const data = await res.json();
                if (data.success) {
                    card.remove();
                    this.count = Math.max(0, this.count - 1);
                    if (this.count === 0) {
                        const grid = document.getElementById('verificationsGrid');
                        const empty = document.getElementById('verificationsEmpty');
                        if (grid) grid.classList.add('hidden');
                        if (empty) empty.classList.remove('hidden');
                    }
                    window.showToast && window.showToast('success', data.message);
                } else {
                    window.showToast && window.showToast('error', data.message || '{{ __('Action failed.') }}');
                }
            } catch (e) {
                window.showToast && window.showToast('error', '{{ __('Something went wrong.') }}');
            }
        },
    };
}
</script>
@endpush
@endsection
