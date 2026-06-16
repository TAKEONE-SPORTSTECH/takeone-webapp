@extends('layouts.admin')

@section('title', 'Businesses')

@php
    use App\Models\Business;
@endphp

@section('admin-content')
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Businesses</h1>
            <p class="text-sm text-muted-foreground">Review, edit and manage chains that group multiple clubs.</p>
        </div>
        @if($pendingCount > 0)
            <span id="bizPendingBadge" class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                <i class="bi bi-hourglass-split mr-1"></i>{{ $pendingCount }} pending
            </span>
        @endif
    </div>

    {{-- Search + status filters --}}
    <div class="flex flex-col gap-3">
        <div class="relative">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" id="bizSearch" autocomplete="off"
                   placeholder="Search by business name, owner name or email..."
                   class="w-full pl-10 pr-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
        </div>
        <div class="flex flex-wrap gap-2" id="bizFilters">
            @php
                $filterDefs = [
                    'all'      => ['All', 'bg-gray-100 text-gray-700', $counts['all']],
                    'pending'  => ['Pending', 'bg-amber-100 text-amber-700', $counts['pending']],
                    'approved' => ['Approved', 'bg-green-100 text-green-700', $counts['approved']],
                    'rejected' => ['Rejected', 'bg-red-100 text-red-700', $counts['rejected']],
                ];
            @endphp
            @foreach($filterDefs as $key => $def)
                <button type="button" data-status="{{ $key }}"
                        class="biz-filter-btn px-3 py-1.5 rounded-full text-xs font-medium border transition-colors {{ $key === 'all' ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50' }}">
                    {{ $def[0] }}
                    <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] px-1 py-0.5 rounded-full text-[10px] {{ $key === 'all' ? 'bg-white/20' : $def[1] }} biz-filter-count" data-status-count="{{ $key }}">{{ $def[2] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Empty state (no businesses at all) --}}
    <div id="bizEmpty" class="bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center {{ $businesses->isEmpty() ? '' : 'hidden' }}">
        <i class="bi bi-buildings text-4xl text-gray-300"></i>
        <p class="text-sm text-muted-foreground mt-3">No businesses have been created yet.</p>
    </div>

    {{-- No-results state (filtered out) --}}
    <div id="bizNoResults" class="hidden bg-white rounded-xl shadow-sm border border-gray-100 p-10 text-center">
        <i class="bi bi-search text-4xl text-gray-300"></i>
        <p class="text-sm text-muted-foreground mt-3">No businesses match your search.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 {{ $businesses->isEmpty() ? 'hidden' : '' }}" id="bizGrid">
        @foreach($businesses as $business)
            @include('admin.platform.businesses._card', ['business' => $business])
        @endforeach
    </div>

</div>

@php
    $bizData = $businesses->mapWithKeys(fn($b) => [$b->id => [
        'id'               => $b->id,
        'name'             => $b->name,
        'description'      => $b->description,
        'status'           => $b->status,
        'rejection_reason' => $b->rejection_reason,
        'logo'             => $b->logo,
        'logo_url'         => $b->logo ? asset('storage/' . $b->logo) : null,
        'owner_name'       => $b->owner?->full_name,
        'owner_email'      => $b->owner?->email,
        'clubs_count'      => $b->clubs_count,
    ]]);
@endphp
<script>window.__bizData = @json((object) $bizData->toArray());</script>

<x-business-edit-modal />
<x-user-picker-modal title="Select Business Owner" subtitle="Search and select a user to be the business owner" />

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const grid = document.getElementById('bizGrid');
    const searchInput = document.getElementById('bizSearch');
    const filterBtns = document.querySelectorAll('.biz-filter-btn');
    let activeStatus = 'all';

    // ── Filtering ──────────────────────────────────────────────
    function applyFilters() {
        const term = (searchInput.value || '').toLowerCase().trim();
        let visible = 0;
        document.querySelectorAll('.biz-card').forEach(card => {
            const name  = (card.dataset.name  || '').toLowerCase();
            const owner = (card.dataset.owner || '').toLowerCase();
            const email = (card.dataset.email || '').toLowerCase();
            const status = card.dataset.status || '';
            const matchTerm = !term || name.includes(term) || owner.includes(term) || email.includes(term);
            const matchStatus = activeStatus === 'all' || status === activeStatus;
            const show = matchTerm && matchStatus;
            card.classList.toggle('hidden', !show);
            if (show) visible++;
        });
        const anyCards = document.querySelectorAll('.biz-card').length > 0;
        document.getElementById('bizNoResults').classList.toggle('hidden', visible > 0 || !anyCards);
    }

    searchInput.addEventListener('input', applyFilters);

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            activeStatus = btn.dataset.status;
            filterBtns.forEach(b => {
                const on = b === btn;
                b.classList.toggle('bg-primary', on);
                b.classList.toggle('text-white', on);
                b.classList.toggle('border-primary', on);
                b.classList.toggle('bg-white', !on);
                b.classList.toggle('text-gray-600', !on);
                b.classList.toggle('border-gray-200', !on);
            });
            applyFilters();
        });
    });

    // ── Status counts / pending badge refresh ──────────────────
    function refreshCounts() {
        const counts = { all: 0, pending: 0, approved: 0, rejected: 0 };
        document.querySelectorAll('.biz-card').forEach(card => {
            counts.all++;
            const s = card.dataset.status;
            if (counts[s] !== undefined) counts[s]++;
        });
        document.querySelectorAll('.biz-filter-count').forEach(el => {
            const k = el.dataset.statusCount;
            if (counts[k] !== undefined) el.textContent = counts[k];
        });
        const badge = document.getElementById('bizPendingBadge');
        if (badge) {
            if (counts.pending > 0) {
                badge.classList.remove('hidden');
                badge.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i>' + counts.pending + ' pending';
            } else {
                badge.classList.add('hidden');
            }
        }
    }

    // ── AJAX helper ────────────────────────────────────────────
    async function send(url, method, body) {
        const opts = {
            method,
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        };
        if (body) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        const res = await fetch(url, opts);
        let data = {};
        try { data = await res.json(); } catch (e) {}
        if (!res.ok || !data.success) throw new Error(data.message || 'Something went wrong.');
        return data;
    }

    // ── Card actions (event delegation) ────────────────────────
    grid.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-biz-action]');
        if (!btn) return;
        const card = btn.closest('.biz-card');
        const id = card.dataset.id;
        const action = btn.dataset.bizAction;

        if (action === 'edit') {
            window.dispatchEvent(new CustomEvent('open-business-edit', { detail: window.__bizData[id] }));
            return;
        }
        if (action === 'reject') {
            // Reject with an optional reason → open the modal pre-set to "rejected".
            window.dispatchEvent(new CustomEvent('open-business-edit', { detail: { ...window.__bizData[id], status: 'rejected' } }));
            return;
        }
        if (action === 'approve') {
            btn.disabled = true;
            try {
                const data = await send(`/admin/businesses/${id}/approve`, 'POST');
                window.showToast('success', data.message);
                patchCard(data.business);
            } catch (err) {
                window.showToast('error', err.message);
                btn.disabled = false;
            }
            return;
        }
        if (action === 'delete') {
            const ok = await window.confirmAction({
                title: 'Delete business',
                message: `Delete “${card.dataset.name}”? Its clubs will be unlinked from the chain. This cannot be undone.`,
                type: 'danger',
                confirmText: 'Delete',
            });
            if (!ok) return;
            try {
                const data = await send(`/admin/businesses/${id}`, 'DELETE');
                window.showToast('success', data.message);
                card.remove();
                delete window.__bizData[id];
                refreshCounts();
                applyFilters();
                if (!document.querySelectorAll('.biz-card').length) {
                    document.getElementById('bizGrid').classList.add('hidden');
                    document.getElementById('bizEmpty').classList.remove('hidden');
                }
            } catch (err) {
                window.showToast('error', err.message);
            }
        }
    });

    // ── Patch a card in place from a returned business payload ──
    window.patchBusinessCard = patchCard;
    function patchCard(b) {
        if (!b) return;
        window.__bizData[b.id] = b;
        const card = document.querySelector(`.biz-card[data-id="${b.id}"]`);
        if (!card) return;

        card.dataset.name = b.name || '';
        card.dataset.owner = b.owner_name || '';
        card.dataset.email = b.owner_email || '';
        card.dataset.status = b.status || '';

        const nameEl = card.querySelector('.biz-name');
        if (nameEl) nameEl.textContent = b.name || '';

        const ownerEl = card.querySelector('.biz-owner');
        if (ownerEl) ownerEl.textContent = (b.owner_name || 'Unknown owner') + (b.owner_email ? ' · ' + b.owner_email : '');

        const clubsEl = card.querySelector('.biz-clubs');
        if (clubsEl) clubsEl.innerHTML = '<i class="bi bi-diagram-3 mr-1"></i>' + b.clubs_count + ' ' + (b.clubs_count === 1 ? 'club' : 'clubs');

        // Description
        const descWrap = card.querySelector('.biz-desc');
        if (descWrap) {
            if (b.description) { descWrap.textContent = b.description; descWrap.classList.remove('hidden'); }
            else { descWrap.textContent = ''; descWrap.classList.add('hidden'); }
        }

        // Rejection reason
        const rejWrap = card.querySelector('.biz-reject-reason');
        if (rejWrap) {
            if (b.status === 'rejected' && b.rejection_reason) {
                rejWrap.innerHTML = '<span class="font-medium">Rejection reason:</span> ' + escapeHtml(b.rejection_reason);
                rejWrap.classList.remove('hidden');
            } else {
                rejWrap.classList.add('hidden');
            }
        }

        // Logo
        const logoWrap = card.querySelector('.biz-logo');
        if (logoWrap) {
            if (b.logo_url) {
                logoWrap.innerHTML = '<img src="' + b.logo_url + '?t=' + Date.now() + '" alt="" class="w-11 h-11 rounded-lg object-cover">';
            } else {
                logoWrap.innerHTML = '<i class="bi bi-buildings text-primary text-lg"></i>';
            }
        }

        // Status badge
        const badge = card.querySelector('.biz-status-badge');
        if (badge) {
            badge.className = 'biz-status-badge inline-block px-2.5 py-0.5 rounded-full text-xs font-medium flex-shrink-0 capitalize ' + statusBadgeClass(b.status);
            badge.textContent = b.status;
        }

        // Actions
        const actions = card.querySelector('.biz-actions');
        if (actions) actions.innerHTML = actionsHtml(b);

        refreshCounts();
        applyFilters();
    }

    function statusBadgeClass(status) {
        if (status === 'approved') return 'bg-green-100 text-green-700';
        if (status === 'rejected') return 'bg-red-100 text-red-700';
        return 'bg-amber-100 text-amber-700';
    }

    function actionsHtml(b) {
        let primary = '';
        if (b.status === 'pending') {
            primary =
                '<button type="button" data-biz-action="approve" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm"><i class="bi bi-check-lg mr-1"></i>Approve</button>' +
                '<button type="button" data-biz-action="reject" class="border border-red-300 text-red-600 hover:bg-red-50 px-4 py-2 rounded-lg transition-colors font-medium text-sm"><i class="bi bi-x-lg mr-1"></i>Reject</button>';
        } else if (b.status === 'rejected') {
            primary =
                '<button type="button" data-biz-action="approve" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm"><i class="bi bi-check-lg mr-1"></i>Approve anyway</button>';
        }
        return primary +
            '<button type="button" data-biz-action="edit" class="border border-gray-200 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg transition-colors font-medium text-sm"><i class="bi bi-pencil mr-1"></i>Edit</button>' +
            '<button type="button" data-biz-action="delete" class="border border-red-200 text-red-600 hover:bg-red-50 px-4 py-2 rounded-lg transition-colors font-medium text-sm"><i class="bi bi-trash mr-1"></i>Delete</button>';
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : s;
        return d.innerHTML;
    }

    // Listen for saves from the edit modal.
    window.addEventListener('business-saved', (e) => patchCard(e.detail));

    refreshCounts();
})();
</script>
@endpush
@endsection
