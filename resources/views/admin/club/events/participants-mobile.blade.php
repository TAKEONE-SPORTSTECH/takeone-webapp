@extends('layouts.admin-club-mobile')

@section('title', ($event->title ?? __('admin.nav_events')) . ' · ' . __('admin.evt_participants'))

@section('club-admin-content')
@php
    $isPaidEvent = filled(trim((string) $event->participant_fee));
    $participantCount = $registrations->where('role', 'participant')->count();
@endphp
<div class="-mx-4 -mt-4">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="relative z-10">
            <a href="{{ route('admin.club.events', $club->slug) }}"
               class="inline-flex items-center gap-1.5 text-[13px] font-medium text-white/80 mb-3">
                <i class="bi bi-arrow-left"></i>{{ __('admin.evt_back_to_events') }}
            </a>
            <div class="flex items-end justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70 truncate">{{ __('admin.evt_participants') }}</p>
                    <h1 class="text-2xl font-black mt-0.5 truncate">{{ $event->title }}</h1>
                    <p class="text-[13px] text-white/80 mt-1">
                        @if($isPaidEvent)
                            <i class="bi bi-cash-coin"></i> {{ $event->participant_fee }}
                        @else
                            <i class="bi bi-unlock"></i> {{ __('admin.evt_fee_free') }}
                        @endif
                    </p>
                </div>
                <div class="text-end flex-shrink-0">
                    <div class="text-3xl font-black leading-none">{{ $participantCount }}@if($event->max_capacity)<span class="text-base font-medium text-white/70">/{{ $event->max_capacity }}</span>@endif</div>
                    <div class="text-[11px] text-white/70">{{ __('admin.evt_registered') }}</div>
                </div>
            </div>
        </div>
    </header>

    <div class="px-4 pt-5">
        @if($registrations->isEmpty())
            <div class="m-card p-8 text-center">
                <i class="bi bi-people text-3xl text-gray-300 m-float"></i>
                <p class="text-sm text-muted-foreground mt-2">{{ __('admin.evt_no_participants') }}</p>
            </div>
        @else
            <div class="space-y-3 mobile-stagger" id="rosterList">
                @foreach($registrations as $r)
                @php $isParticipant = $r->role === 'participant'; @endphp
                <div class="m-card p-4" id="reg-{{ $r->id }}">
                    <div class="flex items-center gap-3">
                        {{-- Avatar --}}
                        <div class="w-12 h-12 rounded-full overflow-hidden flex-shrink-0 bg-muted flex items-center justify-center">
                            @if($r->user && $r->user->profile_picture)
                                <img src="{{ asset('storage/' . $r->user->profile_picture) }}" alt="" class="w-full h-full object-cover">
                            @else
                                <span class="text-lg font-bold text-muted-foreground">{{ mb_strtoupper(mb_substr($r->user->full_name ?? '?', 0, 1, 'UTF-8'), 'UTF-8') }}</span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-foreground truncate">{{ $r->user->full_name ?? __('shared.components_member_card_unknown') }}</div>
                            <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-medium {{ $isParticipant ? 'bg-accent text-primary' : 'bg-muted text-muted-foreground' }}">
                                    <i class="bi {{ $isParticipant ? 'bi-person-check' : 'bi-eye' }} mr-1"></i>{{ $isParticipant ? __('admin.evt_role_participant') : __('admin.evt_role_spectator') }}
                                </span>
                                @if($isPaidEvent)
                                    <span class="reg-paid-pill px-2 py-0.5 rounded-full text-[11px] font-medium {{ $r->paid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}" data-reg="{{ $r->id }}">
                                        <i class="bi {{ $r->paid ? 'bi-check-circle' : 'bi-exclamation-circle' }} mr-1"></i>{{ $r->paid ? __('admin.evt_paid') : __('admin.evt_unpaid') }}
                                    </span>
                                @endif
                                @if($isPaidEvent && $r->payment_proof)
                                    <a href="{{ route('admin.club.events.participants.proof', [$club->slug, $event->id, $r->id]) }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-accent text-primary">
                                        <i class="bi bi-paperclip mr-1"></i>{{ __('admin.evt_view_proof') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                        {{-- Remove --}}
                        <button type="button" onclick="removeParticipant({{ $r->id }})"
                                class="m-press w-9 h-9 rounded-full flex items-center justify-center text-red-600 bg-red-50 flex-shrink-0" aria-label="{{ __('shared.delete') }}">
                            <i class="bi bi-trash text-sm"></i>
                        </button>
                    </div>
                    @if($isPaidEvent)
                        <button type="button" onclick="toggleParticipantPaid({{ $r->id }})"
                                class="reg-paid-btn m-press mt-3 w-full py-2 rounded-xl text-sm font-semibold {{ $r->paid ? 'bg-muted text-foreground' : 'bg-primary text-white' }}" data-reg="{{ $r->id }}">
                            <span class="reg-paid-label">{{ $r->paid ? __('admin.evt_mark_unpaid') : __('admin.evt_mark_paid') }}</span>
                        </button>
                    @endif
                </div>
                @endforeach
            </div>
        @endif
    </div>

    <script>
    (function () {
        const CSRF = '{{ csrf_token() }}';
        const paidUrl   = id => `{{ url('admin/club/' . $club->slug . '/events/' . $event->id . '/participants') }}/${id}/paid`;
        const removeUrl = id => `{{ url('admin/club/' . $club->slug . '/events/' . $event->id . '/participants') }}/${id}`;

        const T = {
            paid:   @json(__('admin.evt_paid')),
            unpaid: @json(__('admin.evt_unpaid')),
            markPaid:   @json(__('admin.evt_mark_paid')),
            markUnpaid: @json(__('admin.evt_mark_unpaid')),
            removeTitle:   @json(__('admin.evt_remove_participant')),
            removeConfirm: @json(__('admin.evt_remove_participant_confirm')),
            removeAction:  @json(__('shared.delete')),
            error: @json(__('admin.club_facilities_add_unexpected_error')),
        };

        window.toggleParticipantPaid = function (id) {
            fetch(paidUrl(id), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            })
            .then(r => r.ok ? r.json() : Promise.reject())
            .then(d => {
                if (!d.success) return window.showToast('error', d.message || T.error);
                const pill = document.querySelector(`.reg-paid-pill[data-reg="${id}"]`);
                const btn  = document.querySelector(`.reg-paid-btn[data-reg="${id}"]`);
                if (pill) {
                    pill.className = 'reg-paid-pill px-2 py-0.5 rounded-full text-[11px] font-medium ' + (d.paid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600');
                    pill.setAttribute('data-reg', id);
                    pill.innerHTML = `<i class="bi ${d.paid ? 'bi-check-circle' : 'bi-exclamation-circle'} mr-1"></i>${d.paid ? T.paid : T.unpaid}`;
                }
                if (btn) {
                    btn.className = 'reg-paid-btn m-press mt-3 w-full py-2 rounded-xl text-sm font-semibold ' + (d.paid ? 'bg-muted text-foreground' : 'bg-primary text-white');
                    btn.setAttribute('data-reg', id);
                    const label = btn.querySelector('.reg-paid-label');
                    if (label) label.textContent = d.paid ? T.markUnpaid : T.markPaid;
                }
                window.showToast('success', d.message);
            })
            .catch(() => window.showToast('error', T.error));
        };

        window.removeParticipant = function (id) {
            confirmAction({
                title: T.removeTitle,
                message: T.removeConfirm,
                confirmText: T.removeAction,
                type: 'danger',
            }).then(confirmed => {
                if (!confirmed) return;
                fetch(removeUrl(id), {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                })
                .then(r => r.ok ? r.json() : Promise.reject())
                .then(d => {
                    if (!d.success) return window.showToast('error', d.message || T.error);
                    document.getElementById('reg-' + id)?.remove();
                    window.showToast('success', d.message);
                })
                .catch(() => window.showToast('error', T.error));
            });
        };
    })();
    </script>
</div>
@endsection
