@extends('layouts.admin')

@section('admin-content')
<div x-data>
    <!-- Page Header -->
    <div class="mb-4">
        <h1 class="text-2xl font-bold mb-2">All Members</h1>
        <p class="text-muted-foreground">Manage all platform members</p>
    </div>

    <!-- Search and Actions Bar -->
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="grow min-w-[16rem] relative" x-data="{ q: @js($search ?? '') }">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 flex items-center justify-center text-gray-400 pointer-events-none"></i>
            <input type="text" id="memberSearch" x-ref="memberSearch" x-model="q"
                   class="w-full pl-10 pr-10 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                   placeholder="Search members by name, email, phone, nationality, club, or gender..."
                   value="{{ $search ?? '' }}">
            <button type="button" x-show="q.length" x-cloak
                    @click="q=''; $nextTick(() => $refs.memberSearch.dispatchEvent(new Event('input')))"
                    title="Clear search"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                <i class="bi bi-x-circle-fill"></i>
            </button>
        </div>
        {{-- Sort dropdown (custom Alpine — no native select) --}}
        <div class="relative shrink-0"
             x-data="{
                open: false,
                sort: @js($sort ?? 'newest'),
                labels: {
                    gender: 'Gender',
                    newest: 'Newest first',
                    oldest: 'Oldest first',
                    name_asc: 'Name (A–Z)',
                    name_desc: 'Name (Z–A)',
                    clubs: 'Most clubs',
                    age_young: 'Age (youngest)',
                    age_old: 'Age (oldest)'
                },
                pick(key) { this.sort = key; this.open = false; window.memberSetSort?.(key); }
             }"
             @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" @click="open = !open"
                    class="flex items-center gap-2 px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors whitespace-nowrap">
                <i class="bi bi-sort-down text-gray-400"></i>
                <span class="hidden sm:inline text-muted-foreground">Sort:</span>
                <span x-text="labels[sort]"></span>
                <i class="bi bi-chevron-down text-xs text-gray-400 transition-transform" :class="open && 'rotate-180'"></i>
            </button>
            <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                 class="absolute right-0 mt-2 w-56 max-w-[calc(100vw-2rem)] bg-white border border-gray-100 rounded-xl shadow-lg overflow-hidden z-50 py-1">
                <template x-for="(label, key) in labels" :key="key">
                    <button type="button" @click="pick(key)"
                            class="w-full flex items-center justify-between px-4 py-2.5 text-sm hover:bg-muted/60 transition-colors"
                            :class="sort === key ? 'text-primary font-semibold' : 'text-gray-700'">
                        <span x-text="label"></span>
                        <i class="bi bi-check-lg" x-show="sort === key"></i>
                    </button>
                </template>
            </div>
        </div>

        {{-- Cards / Table view toggle (persists across AJAX reloads via a class on #membersResults) --}}
        <div class="inline-flex items-center p-1 rounded-lg bg-muted shrink-0" role="tablist" aria-label="View mode"
             x-data="{
                view: localStorage.getItem('membersView') || 'cards',
                apply() { document.getElementById('membersResults')?.classList.toggle('show-table', this.view === 'table'); },
                set(v) { this.view = v; localStorage.setItem('membersView', v); this.apply(); }
             }"
             x-init="apply()">
            <button type="button" role="tab" @click="set('cards')" :aria-selected="view === 'cards'"
                    :class="view === 'cards' ? 'bg-white shadow text-primary' : 'text-muted-foreground hover:text-foreground'"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-colors">
                <i class="bi bi-grid-3x3-gap-fill"></i><span class="hidden sm:inline">Cards</span>
            </button>
            <button type="button" role="tab" @click="set('table')" :aria-selected="view === 'table'"
                    :class="view === 'table' ? 'bg-white shadow text-primary' : 'text-muted-foreground hover:text-foreground'"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-colors">
                <i class="bi bi-table"></i><span class="hidden sm:inline">Table</span>
            </button>
        </div>

        <button class="btn btn-primary shrink-0" @click="$dispatch('open-member-create-modal')">
            <i class="bi bi-plus-circle mr-2"></i>Add Member
        </button>
    </div>

    <!-- Members Grid + Pagination (swapped in place on search) -->
    <div id="membersResults">
        @include('admin.platform.members._results')
    </div>
</div>

{{-- Member Create Modal --}}
<x-profile-modal
    mode="create"
    title="Add Platform Member"
    subtitle="Fill in the details to add a new platform member"
    :showPasswordFields="true"
    :formAction="route('admin.platform.members.store')"
    formMethod="POST"
/>

{{-- Member quick-view popup --}}
@include('admin.club.members.partials.member-popup')

@include('admin.platform.members._scripts')
@endsection
