@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_members'))

@section('club-admin-content')
@php
    $memberNames = $mobileMembers->map(fn($m) => mb_strtolower(optional($m->user)->full_name ?? ''))->filter()->values();
@endphp
<div class="-mx-4 -mt-4"
     x-data="membersMobileData({
        memberNames: @js($memberNames),
        enrollBatchUrl: '{{ route('admin.club.members.enroll-batch', $club->slug) }}',
        csrf: '{{ csrf_token() }}',
     })">

    {{-- ===== Hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70 truncate">{{ $club->club_name ?? __('admin.club') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('admin.nav_members') }}</h1>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="selectMode = !selectMode; selected = []"
                        class="m-press w-12 h-12 rounded-2xl border backdrop-blur grid place-items-center active:scale-95 transition-transform"
                        :class="selectMode ? 'bg-white/30 border-white/50' : 'bg-white/20 border-white/30'"
                        aria-label="{{ __('admin.club_members_index_select') }}">
                    <i class="text-xl" :class="selectMode ? 'bi bi-x-lg' : 'bi bi-check2-square'"></i>
                </button>
                <button type="button" @click="$dispatch('open-add-member')"
                        class="m-press w-12 h-12 rounded-2xl bg-white/20 border border-white/30 backdrop-blur grid place-items-center active:scale-95 transition-transform" aria-label="{{ __('admin.add_member') }}">
                    <i class="bi bi-person-plus text-xl"></i>
                </button>
                <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                    <i class="bi bi-people text-xl m-float"></i>
                </div>
            </div>
        </div>
        <div class="flex gap-2 mt-5 relative z-10">
            @php
                $pills = [
                    ['active', __('admin.active'), $activeCount ?? 0],
                    ['not_active', __('admin.not_active'), $notActiveCount ?? 0],
                    ['all', __('admin.all'), $allCount ?? 0],
                ];
            @endphp
            @foreach($pills as [$key, $label, $count])
                <a href="{{ route('admin.club.members', $club->slug) }}?filter={{ $key }}"
                   class="m-press flex-1 rounded-2xl border backdrop-blur px-3 py-2.5 no-underline {{ ($filter ?? 'active') === $key ? 'bg-white/25 border-white/40' : 'bg-white/12 border-white/20' }}">
                    <p class="text-lg font-black leading-none text-white">{{ $count }}</p>
                    <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ $label }}</p>
                </a>
            @endforeach
        </div>
    </header>

    <div class="px-4 pt-5 space-y-4">

    {{-- Roster --}}
    @if($mobileMembers->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-people text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">{{ __('admin.no_members_in_filter') }}</p>
            <button type="button" @click="$dispatch('open-add-member')"
                    class="m-press mt-4 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-medium hover:bg-primary/90 transition-colors">
                <i class="bi bi-person-plus-fill"></i>{{ __('admin.add_member') }}
            </button>
        </div>
    @else
        {{-- Search + Add member --}}
        <div class="flex items-stretch gap-2">
            <div class="relative flex-1">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none"></i>
                <input type="search" x-model="q" placeholder="{{ __('admin.search_members') }}"
                       class="w-full pl-10 pr-3 py-2.5 bg-muted rounded-xl text-sm text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/40">
            </div>
            <button type="button" @click="$dispatch('open-add-member')"
                    class="m-press flex-shrink-0 px-4 rounded-xl bg-primary text-white flex items-center justify-center hover:bg-primary/90 transition-colors"
                    aria-label="{{ __('admin.add_member') }}">
                <i class="bi bi-person-plus-fill text-lg"></i>
            </button>
        </div>
        <div class="space-y-2.5 mobile-stagger">
            @foreach($mobileMembers as $m)
                @php
                    $u = $m->user;
                    if (!$u) continue;
                    $age = $u->birthdate ? \Carbon\Carbon::parse($u->birthdate)->age : null;
                    $subs = $mobileSubscriptions->get($u->id);
                    $sub = $subs ? $subs->first() : null;
                    $pkgName = $sub && $sub->package ? $sub->package->name : null;
                @endphp
                <a href="{{ route('member.show', $u->uuid) }}"
                   x-show="show(@js(mb_strtolower($u->full_name)))" x-cloak
                   @click="if (selectMode) { $event.preventDefault(); toggleSelect({{ $u->id }}); }"
                   class="m-press flex items-center gap-3 m-card p-3 active:bg-muted/40 transition-colors">
                    <template x-if="selectMode">
                        <span class="w-6 h-6 rounded-full border-2 flex items-center justify-center flex-shrink-0 transition-colors"
                              :class="selected.includes({{ $u->id }}) ? 'bg-primary border-primary text-white' : 'border-gray-300 text-transparent'">
                            <i class="bi bi-check-lg text-xs"></i>
                        </span>
                    </template>
                    <span class="w-11 h-11 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                        @if($u->profile_picture)
                            <img src="{{ asset('storage/'.$u->profile_picture) }}?v={{ optional($u->updated_at)->timestamp }}" alt="" class="w-11 h-11 object-cover">
                        @else
                            <i class="bi bi-person text-muted-foreground text-lg"></i>
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-foreground truncate">{{ $u->full_name }}</p>
                        <p class="text-xs text-muted-foreground truncate">
                            @if($age){{ $age }} {{ __('admin.yrs') }} @endif
                            @if($u->gender) · {{ ucfirst($u->gender) }}@endif
                            @if($pkgName) · {{ $pkgName }}@endif
                        </p>
                    </div>
                    @if($pkgName)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-green-100 text-green-700 flex-shrink-0">{{ __('admin.active') }}</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-100 text-amber-700 flex-shrink-0">{{ __('admin.no_sub') }}</span>
                    @endif
                    <i class="bi bi-chevron-right text-muted-foreground flex-shrink-0"></i>
                </a>
            @endforeach
        </div>
        <p x-show="!hasResults" x-cloak class="text-sm text-muted-foreground text-center py-6">{{ __('admin.no_search_results') }}</p>
        <p x-show="hasResults" class="text-[11px] text-muted-foreground text-center">{{ __('admin.count_shown', ['count' => $mobileMembers->count()]) }}</p>
    @endif

    {{-- Demographics --}}
    @if(!empty($ageGroupCounts) && array_sum($ageGroupCounts) > 0)
    <div class="m-card p-4">
        <h3 class="font-semibold text-foreground mb-3">{{ __('admin.age_groups') }}</h3>
        @php $maxAg = max(1, collect($ageGroupCounts)->max() ?: 1); @endphp
        <div class="space-y-2.5 mobile-stagger">
            @foreach($ageGroupCounts as $label => $count)
                <div>
                    <div class="flex justify-between text-xs mb-1"><span class="text-muted-foreground">{{ $label }}</span><span class="font-semibold text-foreground">{{ $count }}</span></div>
                    <div class="h-2 rounded-full bg-muted overflow-hidden"><div class="m-bar-fill h-full bg-primary rounded-full" style="width: {{ $count > 0 ? max(4, round($count/$maxAg*100)) : 0 }}%"></div></div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    <p class="text-xs text-muted-foreground text-center px-4">{!! __('admin.enrolment_desktop_note') !!}</p>
    </div>{{-- /content --}}

    {{-- Bulk-select floating bar (batch package enrollment) — teleported to
         <body> so it escapes #shell-content's transformed .mobile-stagger ancestor. --}}
    <template x-teleport="body">
        <div x-show="selectMode && selected.length > 0" x-cloak x-transition.opacity
             class="fixed inset-x-0 z-[60] flex justify-center px-4" style="bottom: calc(1rem + env(safe-area-inset-bottom));">
            <div class="flex items-center gap-3 bg-white shadow-2xl border border-gray-100 rounded-2xl px-4 py-3 max-w-full overflow-x-auto">
                <span class="text-sm font-medium text-foreground whitespace-nowrap" x-text="bulkCountLabel"></span>
                <button type="button" @click="selected = []" class="text-sm text-muted-foreground font-medium whitespace-nowrap">{{ __('admin.club_members_index_bulk_clear') }}</button>
                <button type="button" @click="openBulkEnroll()"
                        class="m-press inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-white text-sm font-medium whitespace-nowrap">
                    <i class="bi bi-person-plus"></i>{{ __('admin.club_members_index_bulk_enroll') }}
                </button>
            </div>
        </div>
    </template>

    {{-- Batch enroll bottom-sheet --}}
    <template x-teleport="body">
        <div>
            <div x-show="bulkSheetOpen" x-transition.opacity
                 class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm" @click="if (!bulkSubmitting) bulkSheetOpen = false"></div>

            <div x-show="bulkSheetOpen"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="fixed inset-x-0 bottom-0 z-[60] bg-white rounded-t-3xl shadow-2xl max-h-[92vh] flex flex-col">
                <div class="flex-shrink-0 px-5 pt-3 pb-3 border-b border-gray-100">
                    <div class="flex justify-center pb-2"><span class="w-10 h-1.5 rounded-full bg-gray-200"></span></div>
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-foreground">{{ __('admin.club_members_index_bulk_enroll_modal_title') }}</h3>
                        <button type="button" @click="if (!bulkSubmitting) bulkSheetOpen = false" class="m-press w-9 h-9 -mr-1 rounded-full flex items-center justify-center hover:bg-muted"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <p class="text-sm text-muted-foreground mt-1">{{ __('admin.club_members_index_bulk_enroll_modal_subtitle') }}</p>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('admin.club_members_index_bulk_package_label') }}</label>
                        <div class="space-y-2.5">
                            @forelse(collect($packages ?? [])->where('is_active', true) as $pkg)
                                <button type="button" @click="bulkPackageId = {{ $pkg->id }}"
                                        class="w-full text-left bg-white border-2 rounded-2xl p-3.5 transition-all"
                                        :class="String(bulkPackageId) === '{{ $pkg->id }}' ? 'border-purple-500 bg-purple-50' : 'border-gray-200'">
                                    <div class="flex items-start justify-between gap-2">
                                        <h5 class="font-bold text-foreground text-sm truncate">{{ $pkg->name }}</h5>
                                        <span class="w-6 h-6 rounded-full border-2 flex items-center justify-center flex-shrink-0 transition-colors"
                                              :class="String(bulkPackageId) === '{{ $pkg->id }}' ? 'bg-purple-500 border-purple-500' : 'border-gray-300'">
                                            <i class="bi bi-check-lg text-white text-[11px]" x-show="String(bulkPackageId) === '{{ $pkg->id }}'"></i>
                                        </span>
                                    </div>
                                    @if($pkg->description)
                                        <p class="text-xs text-muted-foreground mt-1 line-clamp-1">{{ $pkg->description }}</p>
                                    @endif
                                    <div class="flex items-center gap-4 mt-2.5 text-xs">
                                        <div>
                                            <span class="text-muted-foreground">{{ __('admin.club_members_index_js_price') }}</span>
                                            <span class="font-bold text-purple-600 ms-1">{{ $club->currency ?? 'BHD' }} {{ number_format((float) $pkg->price, 2) }}</span>
                                        </div>
                                        <div>
                                            <span class="text-muted-foreground">{{ __('admin.club_members_index_js_duration') }}</span>
                                            <span class="font-semibold text-foreground ms-1">{{ $pkg->duration_months }} {{ Str::plural(__('admin.club_members_index_bulk_month'), $pkg->duration_months) }}</span>
                                        </div>
                                    </div>
                                </button>
                            @empty
                                <p class="text-sm text-muted-foreground text-center py-6">{{ __('admin.club_members_index_js_no_packages') }}</p>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                            <input type="checkbox" x-model="bulkBackdate" class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">
                            {{ __('admin.club_members_index_bulk_backdate_toggle') }}
                        </label>
                        <div x-show="bulkBackdate" x-cloak class="mt-2" @keydown.escape="bulkCalOpen = false">
                            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.club_members_index_bulk_start_date_label') }}</label>
                            <button type="button" @click="bulkCalOpen = !bulkCalOpen"
                                    class="w-full px-3 py-2.5 border rounded-xl text-sm bg-white text-start flex items-center gap-2 outline-none transition-colors"
                                    :class="bulkCalOpen ? 'ring-2 ring-purple-500 border-transparent rounded-b-none' : 'border-gray-200'">
                                <i class="bi bi-calendar-event text-gray-400 flex-shrink-0"></i>
                                <span class="flex-1 truncate" :class="bulkStartDate ? 'text-foreground' : 'text-gray-400'" x-text="bulkStartDate ? bulkFmt(bulkStartDate) : '{{ __('admin.club_members_index_bulk_package_placeholder') }}'"></span>
                                <i class="bi bi-chevron-down text-gray-400 text-xs transition-transform flex-shrink-0" :class="bulkCalOpen ? 'rotate-180' : ''"></i>
                            </button>
                            {{-- Inline expanding panel (not an absolutely-positioned popover) — a floating
                                 popover here would get clipped by the sheet's scrollable body. --}}
                            <div x-show="bulkCalOpen" x-cloak
                                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                 class="w-full bg-white border border-t-0 border-gray-200 rounded-b-xl shadow-inner overflow-hidden p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <button type="button" @click="bulkPrev()" class="w-8 h-8 rounded-lg grid place-items-center text-muted-foreground hover:bg-muted/60 transition-colors"><i class="bi bi-chevron-left text-sm"></i></button>
                                    <p class="text-sm font-bold text-foreground" x-text="bulkMonths[bulkView.m] + ' ' + bulkView.y"></p>
                                    <button type="button" @click="bulkNext()" class="w-8 h-8 rounded-lg grid place-items-center text-muted-foreground hover:bg-muted/60 transition-colors"><i class="bi bi-chevron-right text-sm"></i></button>
                                </div>
                                <div class="grid grid-cols-7 gap-1 mb-1">
                                    <template x-for="dw in bulkDows" :key="dw"><span class="text-[10px] font-bold text-muted-foreground text-center py-1" x-text="dw"></span></template>
                                </div>
                                <div class="grid grid-cols-7 gap-1">
                                    <template x-for="(d, i) in bulkGrid" :key="i">
                                        <button type="button" :disabled="!d || bulkIsFuture(d)"
                                                @click="if (d && !bulkIsFuture(d)) { bulkStartDate = bulkIso(d); bulkCalOpen = false }"
                                                class="h-9 rounded-lg text-sm grid place-items-center transition-colors"
                                                :class="!d ? 'invisible' : (bulkIso(d)===bulkStartDate ? 'bg-primary text-white font-bold' : (bulkIsFuture(d) ? 'text-gray-300 cursor-not-allowed' : (bulkIsToday(d) ? 'text-primary font-bold ring-1 ring-primary/40 hover:bg-muted/60' : 'text-foreground hover:bg-muted/60')))"
                                                x-text="d"></button>
                                    </template>
                                </div>
                                <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                                    <button type="button" @click="bulkStartDate=''; bulkCalOpen=false" class="text-[11px] font-semibold text-muted-foreground hover:text-foreground transition-colors">{{ __('admin.club_members_index_bulk_cal_clear') }}</button>
                                    <button type="button" @click="const t=new Date(); bulkView={y:t.getFullYear(),m:t.getMonth()}; bulkStartDate=bulkTodayIso(); bulkCalOpen=false" class="text-[11px] font-semibold text-primary">{{ __('admin.club_members_index_bulk_cal_today') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex-shrink-0 px-4 pt-3 border-t border-gray-100" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="submitBulkEnroll()" :disabled="!bulkPackageId || bulkSubmitting"
                            class="w-full py-3 rounded-xl bg-primary text-white font-medium hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!bulkSubmitting">{{ __('admin.club_members_index_bulk_submit') }}</span>
                        <span x-show="bulkSubmitting"><i class="bi bi-arrow-repeat animate-spin mr-1"></i>{{ __('admin.club_members_index_bulk_submitting') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </template>
    </div>

{{-- Inline (not @push) so the mobile shell's runScripts() re-defines the factory
     after each in-place navigation into the members page. --}}
<script>
window.membersMobileData = function (cfg) {
    return {
        q: '',
        names: cfg.memberNames,
        selectMode: false,
        selected: [],
        bulkPackageId: '',
        bulkBackdate: false,
        bulkStartDate: '',
        bulkCalOpen: false,
        bulkSheetOpen: false,
        bulkSubmitting: false,
        bulkView: { y: (new Date()).getFullYear(), m: (new Date()).getMonth() },
        bulkMonths: ['January','February','March','April','May','June','July','August','September','October','November','December'],
        bulkDows: ['Su','Mo','Tu','We','Th','Fr','Sa'],
        show(n) { return this.q.trim() === '' || n.includes(this.q.trim().toLowerCase()); },
        get hasResults() { return this.q.trim() === '' || this.names.some(n => n.includes(this.q.trim().toLowerCase())); },
        get bulkCountLabel() { return @js(__('admin.club_members_index_bulk_selected', ['count' => ':count'])).replace(':count', this.selected.length); },
        get bulkGrid() {
            const start = new Date(this.bulkView.y, this.bulkView.m, 1).getDay();
            const days  = new Date(this.bulkView.y, this.bulkView.m + 1, 0).getDate();
            const cells = [];
            for (let i = 0; i < start; i++) cells.push(null);
            for (let d = 1; d <= days; d++) cells.push(d);
            return cells;
        },
        toggleSelect(id) {
            const i = this.selected.indexOf(id);
            if (i === -1) this.selected.push(id); else this.selected.splice(i, 1);
        },
        bulkIso(d) { return this.bulkView.y + '-' + String(this.bulkView.m + 1).padStart(2,'0') + '-' + String(d).padStart(2,'0'); },
        bulkTodayIso() { const t = new Date(); return t.getFullYear() + '-' + String(t.getMonth()+1).padStart(2,'0') + '-' + String(t.getDate()).padStart(2,'0'); },
        bulkIsFuture(d) { if (!d) return false; const t = new Date(); t.setHours(0,0,0,0); return new Date(this.bulkView.y, this.bulkView.m, d) > t; },
        bulkIsToday(d) { const t = new Date(); return d && this.bulkView.y===t.getFullYear() && this.bulkView.m===t.getMonth() && d===t.getDate(); },
        bulkPrev() { this.bulkView = this.bulkView.m === 0 ? { y: this.bulkView.y - 1, m: 11 } : { y: this.bulkView.y, m: this.bulkView.m - 1 }; },
        bulkNext() { this.bulkView = this.bulkView.m === 11 ? { y: this.bulkView.y + 1, m: 0 } : { y: this.bulkView.y, m: this.bulkView.m + 1 }; },
        bulkFmt(val) { if (!val) return ''; const d = new Date(val + 'T00:00:00'); return d.toLocaleDateString(undefined, { weekday:'short', day:'numeric', month:'short', year:'numeric' }); },
        openBulkEnroll() {
            if (!this.selected.length) return;
            this.bulkPackageId = '';
            this.bulkBackdate = false;
            this.bulkStartDate = '';
            this.bulkCalOpen = false;
            const t = new Date(); this.bulkView = { y: t.getFullYear(), m: t.getMonth() };
            this.bulkSheetOpen = true;
        },
        async submitBulkEnroll() {
            if (!this.bulkPackageId || !this.selected.length || this.bulkSubmitting) return;
            this.bulkSubmitting = true;
            try {
                const res = await fetch(cfg.enrollBatchUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                    body: JSON.stringify({
                        member_ids: this.selected,
                        package_id: this.bulkPackageId,
                        start_date: this.bulkBackdate && this.bulkStartDate ? this.bulkStartDate : null,
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    window.showToast && window.showToast('success', data.message);
                    this.bulkSheetOpen = false;
                    this.selectMode = false;
                    this.selected = [];
                    window.reloadMemberCards && window.reloadMemberCards();
                } else {
                    window.showToast && window.showToast('error', data.message || @js(__('admin.club_members_index_js_err_enrolling')));
                }
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('admin.club_members_index_js_err_enrolling')));
            } finally {
                this.bulkSubmitting = false;
            }
        },
    };
};
</script>

{{-- Add-member flow: FAB → bottom-sheet (Scan QR · Register new · Find one) --}}
@include('admin.club.members.partials.mobile-add-member')

{{-- Register-new path reuses the existing walk-in registration wizard --}}
<x-registration-walkin :club="$club" :packages="$packages ?? []" />
@endsection
