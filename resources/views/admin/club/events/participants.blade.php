@extends('layouts.admin-club')

@section('club-admin-content')

@php
    $isPaidEvent = filled(trim((string) $event->participant_fee));
    $participantCount = $registrations->where('role', 'participant')->count();
@endphp

<div>
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div class="min-w-0">
            <a href="{{ route('admin.club.events', $club->slug) }}"
               class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground mb-1">
                <i class="bi bi-arrow-left"></i>{{ __('admin.evt_back_to_events') }}
            </a>
            <h2 class="text-xl font-bold text-foreground truncate">{{ $event->title }}</h2>
            <p class="text-sm text-muted-foreground mt-0.5">
                <i class="bi bi-people me-1"></i>{{ __('admin.evt_participants') }}
                @if($isPaidEvent)
                    <span class="ms-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-accent text-primary">
                        <i class="bi bi-cash-coin me-1"></i>{{ $event->participant_fee }}
                    </span>
                @else
                    <span class="ms-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-muted text-muted-foreground">{{ __('admin.evt_fee_free') }}</span>
                @endif
            </p>
        </div>
        <div class="text-end">
            <div class="text-2xl font-extrabold text-foreground">
                {{ $participantCount }}@if($event->max_capacity)<span class="text-base font-medium text-muted-foreground"> / {{ $event->max_capacity }}</span>@endif
            </div>
            <div class="text-xs text-muted-foreground">{{ __('admin.evt_registered') }}</div>
        </div>
    </div>

    @if($registrations->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-16">
                <i class="bi bi-people text-muted-foreground" style="font-size:2.5rem;opacity:.3;"></i>
                <p class="mt-3 text-muted-foreground">{{ __('admin.evt_no_participants') }}</p>
            </div>
        </div>
    @else
        <div class="flex flex-col gap-3" id="rosterList">
            @foreach($registrations as $r)
            @php $isParticipant = $r->role === 'participant'; @endphp
            <div class="card border-0 shadow-sm" id="reg-{{ $r->id }}">
                <div class="card-body p-4">
                    <div class="flex items-center gap-4">
                        {{-- Avatar --}}
                        <div class="w-12 h-12 rounded-full overflow-hidden flex-shrink-0 bg-muted flex items-center justify-center">
                            @if($r->user && $r->user->profile_picture)
                                <img src="{{ asset('storage/' . $r->user->profile_picture) }}" alt="" class="w-full h-full object-cover">
                            @else
                                <span class="text-lg font-bold text-muted-foreground">{{ mb_strtoupper(mb_substr($r->user->full_name ?? '?', 0, 1, 'UTF-8'), 'UTF-8') }}</span>
                            @endif
                        </div>

                        {{-- Name + role --}}
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-foreground truncate">{{ $r->user->full_name ?? __('shared.components_member_card_unknown') }}</div>
                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $isParticipant ? 'bg-accent text-primary' : 'bg-muted text-muted-foreground' }}">
                                    <i class="bi {{ $isParticipant ? 'bi-person-check' : 'bi-eye' }} me-1"></i>{{ $isParticipant ? __('admin.evt_role_participant') : __('admin.evt_role_spectator') }}
                                </span>
                                @if($isPaidEvent)
                                    <span class="reg-paid-pill px-2 py-0.5 rounded-full text-xs font-medium {{ $r->paid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600' }}" data-reg="{{ $r->id }}">
                                        <i class="bi {{ $r->paid ? 'bi-check-circle' : 'bi-exclamation-circle' }} me-1"></i>{{ $r->paid ? __('admin.evt_paid') : __('admin.evt_unpaid') }}
                                    </span>
                                @endif
                                @if($isPaidEvent && $r->payment_proof)
                                    <a href="{{ route('admin.club.events.participants.proof', [$club->slug, $event->id, $r->id]) }}" target="_blank" rel="noopener"
                                       class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-accent text-primary hover:bg-primary hover:text-white transition-colors" title="{{ __('admin.evt_proof_uploaded') }}">
                                        <i class="bi bi-paperclip me-1"></i>{{ __('admin.evt_view_proof') }}
                                    </a>
                                @endif
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center gap-2 flex-shrink-0">
                            @if($isPaidEvent)
                                <button type="button" onclick="toggleParticipantPaid({{ $r->id }})"
                                        class="reg-paid-btn btn btn-sm {{ $r->paid ? 'btn-outline-secondary' : 'btn-primary' }}" data-reg="{{ $r->id }}">
                                    <span class="reg-paid-label">{{ $r->paid ? __('admin.evt_mark_unpaid') : __('admin.evt_mark_paid') }}</span>
                                </button>
                            @endif
                            <button type="button" onclick="removeParticipant({{ $r->id }})"
                                    class="btn btn-sm btn-outline-danger" title="{{ __('shared.delete') }}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif
</div>

@push('scripts')
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
                pill.className = 'reg-paid-pill px-2 py-0.5 rounded-full text-xs font-medium ' + (d.paid ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600');
                pill.setAttribute('data-reg', id);
                pill.innerHTML = `<i class="bi ${d.paid ? 'bi-check-circle' : 'bi-exclamation-circle'} me-1"></i>${d.paid ? T.paid : T.unpaid}`;
            }
            if (btn) {
                btn.className = 'reg-paid-btn btn btn-sm ' + (d.paid ? 'btn-outline-secondary' : 'btn-primary');
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
@endpush
@endsection
