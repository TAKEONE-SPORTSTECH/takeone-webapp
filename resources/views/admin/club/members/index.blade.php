@extends('layouts.admin-club')


{{-- Styles moved to app.css (Phase 6) --}}

@section('club-admin-content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Members Management</h2>
            <p class="text-gray-500 mt-1">Manage club members and subscriptions</p>
        </div>
        <div class="flex gap-3 flex-wrap">
            <button onclick="openAddExistingUserModal()" class="btn btn-outline-primary">
                <i class="bi bi-person-plus mr-2"></i>Add Existing User
            </button>
            <button onclick="document.getElementById('importMembersModal').classList.remove('hidden')" class="btn btn-outline-light">
                <i class="bi bi-upload mr-2"></i>Import Members
            </button>
            <button onclick="openWalkInModal()" class="btn btn-primary">
                <i class="bi bi-people mr-2"></i>Walk-In Registration
            </button>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="space-y-3">

        {{-- Status overview — sparkline = monthly new registrations trend --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
            <x-stat-card size="sm"
                card-id="sc-total"
                label="Total Members"
                :value="$allCount"
                sub-label="all active memberships"
                icon="bi-people-fill"
                icon-bg="bg-violet-100"
                icon-color="text-violet-600"
                :spark-data="$monthlyNewMembers"
                :spark-labels="$monthlyLabels"
                spark-color="#7c3aed"
                :href="route('admin.club.members', [$club->slug, 'filter' => 'all'])"
            />
            <x-stat-card size="sm"
                card-id="sc-subscribed"
                label="Subscribed"
                :value="$activeCount"
                sub-label="with active package"
                icon="bi-patch-check-fill"
                icon-bg="bg-green-100"
                icon-color="text-green-600"
                :spark-data="$monthlyNewMembers"
                :spark-labels="$monthlyLabels"
                spark-color="#16a34a"
                :href="route('admin.club.members', [$club->slug, 'filter' => 'active'])"
            />
            <x-stat-card size="sm"
                card-id="sc-not-active"
                label="Not Active"
                :value="$notActiveCount"
                sub-label="no active package"
                icon="bi-person-dash-fill"
                icon-bg="bg-amber-100"
                icon-color="text-amber-600"
                :spark-data="$monthlyNewMembers"
                :spark-labels="$monthlyLabels"
                spark-color="#d97706"
                :href="route('admin.club.members', [$club->slug, 'filter' => 'not_active'])"
            />
            <x-stat-card size="sm"
                card-id="sc-former"
                label="Former"
                :value="$formerCount"
                sub-label="left the club"
                icon="bi-person-x-fill"
                icon-bg="bg-gray-100"
                icon-color="text-gray-500"
                :spark-data="array_fill(0, 12, 0)"
                spark-color="#6b7280"
            />
        </div>

        {{-- Demographics — each card filters the list when clicked --}}
        <div class="grid grid-cols-4 lg:grid-cols-7 gap-2">
            <x-stat-card size="sm" card-id="sc-male"    label="Male"    :value="$maleCount"                 sub-label="click to filter" icon="bi-gender-male"   icon-bg="bg-blue-100"    icon-color="text-blue-600"    spark-color="#2563eb" :spark-data="$monthlyMale"    :spark-labels="$monthlyLabels" on-click="window.dispatchEvent(new CustomEvent('filter-demo',{detail:{type:'gender',value:'male'}}))" />
            <x-stat-card size="sm" card-id="sc-female"  label="Female"  :value="$femaleCount"               sub-label="click to filter" icon="bi-gender-female" icon-bg="bg-rose-100"    icon-color="text-rose-500"    spark-color="#f43f5e" :spark-data="$monthlyFemale"  :spark-labels="$monthlyLabels" on-click="window.dispatchEvent(new CustomEvent('filter-demo',{detail:{type:'gender',value:'female'}}))" />
            <x-stat-card size="sm" card-id="sc-kids"    label="Kids"    :value="$ageGroupCounts['Kids']"    sub-label="age 6–11"        icon="bi-star-fill"      icon-bg="bg-emerald-100" icon-color="text-emerald-600" spark-color="#10b981" :spark-data="$monthlyKids"    :spark-labels="$monthlyLabels" on-click="window.dispatchEvent(new CustomEvent('filter-demo',{detail:{type:'category',value:'Kids'}}))" />
            <x-stat-card size="sm" card-id="sc-cadet"   label="Cadet"   :value="$ageGroupCounts['Cadet']"   sub-label="age 12–14"       icon="bi-lightning-fill" icon-bg="bg-yellow-100"  icon-color="text-yellow-600"  spark-color="#ca8a04" :spark-data="$monthlyCadet"   :spark-labels="$monthlyLabels" on-click="window.dispatchEvent(new CustomEvent('filter-demo',{detail:{type:'category',value:'Cadet'}}))" />
            <x-stat-card size="sm" card-id="sc-junior"  label="Junior"  :value="$ageGroupCounts['Junior']"  sub-label="age 15–17"       icon="bi-trophy-fill"    icon-bg="bg-orange-100"  icon-color="text-orange-600"  spark-color="#ea580c" :spark-data="$monthlyJunior"  :spark-labels="$monthlyLabels" on-click="window.dispatchEvent(new CustomEvent('filter-demo',{detail:{type:'category',value:'Junior'}}))" />
            <x-stat-card size="sm" card-id="sc-senior"  label="Senior"  :value="$ageGroupCounts['Senior']"  sub-label="age 18–30"       icon="bi-shield-fill"    icon-bg="bg-violet-100"  icon-color="text-violet-600"  spark-color="#7c3aed" :spark-data="$monthlySenior"  :spark-labels="$monthlyLabels" on-click="window.dispatchEvent(new CustomEvent('filter-demo',{detail:{type:'category',value:'Senior'}}))" />
            <x-stat-card size="sm" card-id="sc-masters" label="Masters" :value="$ageGroupCounts['Masters']" sub-label="age 31+"         icon="bi-crown-fill"     icon-bg="bg-gray-200"    icon-color="text-gray-600"    spark-color="#6b7280" :spark-data="$monthlyMasters" :spark-labels="$monthlyLabels" on-click="window.dispatchEvent(new CustomEvent('filter-demo',{detail:{type:'category',value:'Masters'}}))" />
        </div>

    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-8">
            <button id="members-tab-btn" onclick="switchTab('members')" class="py-4 px-1 border-b-2 border-purple-500 text-purple-600 font-medium text-sm whitespace-nowrap">
                Current Members
                <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-purple-100 text-purple-600" id="membersCount">{{ $statusCounts[$filter] ?? count($members) }}</span>
            </button>
            <button id="requests-tab-btn" onclick="switchTab('requests')" class="py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap">
                Pending Requests
                <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700" id="requestsCount">{{ $pendingRequests ?? 0 }}</span>
            </button>
            <button id="former-tab-btn" onclick="switchTab('former')" class="py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap">
                Former Members
                <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ $formerCount ?? 0 }}</span>
            </button>
        </nav>
    </div>

    <!-- Members Tab Content -->
    <div id="members-content">
        <!-- Search & Filter -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6"
             x-data="{
                showAdvanced: false,
                gender: '',
                category: '',
                weightClass: '',
                ageMin: '',
                ageMax: '',
                get activeCount() {
                    return [this.gender, this.category, this.weightClass, this.ageMin, this.ageMax].filter(v => v !== '').length;
                },
                get weightClasses() {
                    const map = {
                        Kids:    { male: ['-26 kg','-30 kg','-33 kg','-36 kg','-40 kg','-45 kg','-50 kg','+50 kg'], female: ['-26 kg','-30 kg','-33 kg','-36 kg','-40 kg','-45 kg','-50 kg','+50 kg'] },
                        Cadet:   { male: ['-33 kg','-37 kg','-41 kg','-45 kg','-49 kg','-53 kg','-57 kg','-61 kg','-65 kg','+65 kg'], female: ['-29 kg','-33 kg','-37 kg','-41 kg','-44 kg','-47 kg','-51 kg','-55 kg','-59 kg','+59 kg'] },
                        Junior:  { male: ['-45 kg','-48 kg','-51 kg','-55 kg','-59 kg','-63 kg','-68 kg','-73 kg','-78 kg','+78 kg'], female: ['-42 kg','-44 kg','-46 kg','-49 kg','-52 kg','-55 kg','-59 kg','-63 kg','-68 kg','+68 kg'] },
                        Senior:  { male: ['-54 kg','-58 kg','-63 kg','-68 kg','-74 kg','-80 kg','-87 kg','+87 kg'], female: ['-46 kg','-49 kg','-53 kg','-57 kg','-62 kg','-67 kg','-73 kg','+73 kg'] },
                        Masters: { male: ['-60 kg','-70 kg','-80 kg','+80 kg'], female: ['-55 kg','-63 kg','-72 kg','+72 kg'] },
                    };
                    if (!this.category) return [];
                    const entry = map[this.category];
                    if (!entry) return [];
                    if (this.gender) return entry[this.gender] || [];
                    return [...new Set([...(entry.male || []), ...(entry.female || [])])];
                },
                reset() {
                    this.gender = ''; this.category = ''; this.weightClass = ''; this.ageMin = ''; this.ageMax = '';
                    this.$nextTick(() => {
                        ['filterGender','filterCategory','filterWeightClass','filterAgeMin','filterAgeMax']
                            .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
                        applyFilters();
                    });
                },
                filterByDemo(type, value) {
                    this.gender      = type === 'gender'   ? value : '';
                    this.category    = type === 'category' ? value : '';
                    this.weightClass = '';
                    this.ageMin      = '';
                    this.ageMax      = '';
                    this.showAdvanced = true;
                    this.$nextTick(() => applyFilters());
                }
             }"
             @filter-change.window="applyFilters()"
             @filter-demo.window="filterByDemo($event.detail.type, $event.detail.value)">

            <!-- Row 1: search + status + advanced toggle -->
            <div class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <input type="text" id="searchMembers" placeholder="Search members by name, phone, or email..." autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly')" class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" @input="applyFilters()">
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-2">
                    <span class="text-sm font-medium text-gray-600 self-center">Status:</span>
                    <div class="inline-flex flex-wrap rounded-lg border border-gray-200 p-1 bg-gray-50 overflow-x-auto max-w-full">
                        <a href="{{ request()->fullUrlWithQuery(['filter' => 'active', 'page' => 1]) }}" class="status-btn px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ $filter === 'active' ? 'active' : 'text-gray-600 hover:bg-gray-100' }}">
                            Active <span class="ml-1 text-xs opacity-75">{{ $statusCounts['active'] }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['filter' => 'not_active', 'page' => 1]) }}" class="status-btn px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ $filter === 'not_active' ? 'active' : 'text-gray-600 hover:bg-gray-100' }}">
                            Not Active <span class="ml-1 text-xs opacity-75">{{ $statusCounts['not_active'] }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['filter' => 'all', 'page' => 1]) }}" class="status-btn px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ $filter === 'all' ? 'active' : 'text-gray-600 hover:bg-gray-100' }}">
                            All <span class="ml-1 text-xs opacity-75">{{ $statusCounts['all'] }}</span>
                        </a>
                    </div>
                    <button @click="showAdvanced = !showAdvanced"
                            class="inline-flex items-center gap-2 px-3 py-2 border rounded-lg text-sm font-medium transition-colors"
                            :class="activeCount > 0 ? 'border-purple-400 text-purple-600 bg-purple-50' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                        <i class="bi bi-sliders"></i>
                        Filters
                        <span x-show="activeCount > 0" x-text="activeCount"
                              class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-purple-600 text-white text-xs font-bold"></span>
                    </button>
                </div>
            </div>

            <!-- Row 2: advanced filters panel -->
            <div x-show="showAdvanced" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">

                <!-- Age Min -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Age Min</label>
                    <input type="number" id="filterAgeMin" min="0" max="100" placeholder="e.g. 12"
                           x-model="ageMin" @input="applyFilters()"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>

                <!-- Age Max -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Age Max</label>
                    <input type="number" id="filterAgeMax" min="0" max="100" placeholder="e.g. 17"
                           x-model="ageMax" @input="applyFilters()"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>

                <!-- Gender -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Gender</label>
                    <select id="filterGender" x-model="gender" @change="weightClass = ''; applyFilters()"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="">All</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>

                <!-- Category -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Category</label>
                    <select id="filterCategory" x-model="category" @change="weightClass = ''; applyFilters()"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="">All</option>
                        <option>Kids</option>
                        <option>Cadet</option>
                        <option>Junior</option>
                        <option>Senior</option>
                        <option>Masters</option>
                    </select>
                </div>

                <!-- Weight Class -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Weight Class</label>
                    <select id="filterWeightClass" x-model="weightClass" @change="applyFilters()"
                            :disabled="weightClasses.length === 0"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent disabled:opacity-40 disabled:cursor-not-allowed">
                        <option value="">All</option>
                        <template x-for="wc in weightClasses" :key="wc">
                            <option :value="wc" x-text="wc"></option>
                        </template>
                    </select>
                </div>

                <!-- Clear -->
                <div class="col-span-2 sm:col-span-3 lg:col-span-5 flex justify-end">
                    <button @click="reset()" x-show="activeCount > 0"
                            class="text-sm text-purple-600 hover:text-purple-800 font-medium flex items-center gap-1">
                        <i class="bi bi-x-circle"></i> Clear all filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Members Grid — cards loaded via AJAX after page ready -->
        <div id="membersGridWrap">
            <div id="membersGrid" class="grid gap-6" style="grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));">
                {{-- Skeleton loaders shown while AJAX is in flight --}}
                @for($i = 0; $i < 8; $i++)
                <div class="members-skeleton bg-white rounded-xl border border-gray-100 animate-pulse" style="height:280px;"></div>
                @endfor
            </div>
            <div id="membersEmpty" class="tf-empty hidden">
                <div class="tf-empty-icon">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <h5 class="text-lg font-semibold text-gray-900 mb-2">No members found</h5>
                <p class="text-gray-500 mb-4">Start adding members to your club</p>
                <button onclick="openWalkInModal()" class="btn btn-primary">
                    <i class="bi bi-plus-lg mr-2"></i>Add Member
                </button>
            </div>
            <x-client-paginator id="membersPagination" :per-page="20" />
        </div>
    </div>

    <!-- Requests Tab Content -->
    <div id="requests-content" class="hidden">
        @if(isset($membershipRequests) && count($membershipRequests) > 0)
        <div class="space-y-4">
            @foreach($membershipRequests as $request)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            @if($request->user->profile_picture)
                            <img src="{{ asset('storage/' . $request->user->profile_picture) }}" alt="" class="w-12 h-12 rounded-full object-cover">
                            @else
                            <div class="w-12 h-12 rounded-full bg-purple-500 flex items-center justify-center">
                                <span class="text-white font-bold">{{ strtoupper(substr($request->user->full_name ?? '?', 0, 1)) }}</span>
                            </div>
                            @endif
                            <div>
                                <h6 class="font-bold text-gray-900">{{ $request->user->full_name ?? 'Unknown' }}</h6>
                                <p class="text-sm text-gray-500 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    Requested on {{ $request->created_at->format('M d, Y') }}
                                </p>
                            </div>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Pending</span>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Review Notes</label>
                        <textarea class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none" rows="2" placeholder="Add notes about this request..." id="reviewNotes_{{ $request->id }}"></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button onclick="approveRequest({{ $request->id }}, {{ $request->user_id }})" class="flex-1 btn btn-success">
                            <i class="bi bi-check-lg mr-2"></i>Approve
                        </button>
                        <button onclick="rejectRequest({{ $request->id }})" class="flex-1 btn btn-danger">
                            <i class="bi bi-x-lg mr-2"></i>Reject
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
            <h5 class="text-lg font-semibold text-gray-900 mb-2">No pending requests</h5>
            <p class="text-gray-500">All membership requests have been processed</p>
        </div>
        @endif
    </div>
</div>

<!-- Former Members Tab Content — loaded via AJAX on first click -->
<div id="former-content" class="hidden">
    <div id="formerCardsWrap">
        {{-- content injected by JS --}}
    </div>
</div>

<!-- Add Existing User Modal -->
<div id="addExistingUserModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('addExistingUserModal')"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-xl font-bold text-gray-900">Add Existing User</h3>
                <p class="text-sm text-gray-500 mt-1">Search for a user by email or phone number to add them and their children as members.</p>
            </div>
            <div class="p-6 overflow-y-auto max-h-[60vh]">
                <div class="relative mb-6">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" id="searchUserInput" placeholder="Enter email or phone number..." autocomplete="new-password" class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div id="searchResults" class="hidden">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Select members to add:</label>
                    <div id="searchResultsList" class="space-y-3 max-h-96 overflow-y-auto"></div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                <button onclick="closeModal('addExistingUserModal')" class="btn btn-outline-light">Cancel</button>
                <button onclick="addSelectedMembers()" id="addMembersBtn" disabled class="btn btn-primary">
                    Add <span id="selectedCount">0</span> Member(s)
                </button>
            </div>
        </div>
    </div>
</div>

<x-registration-walkin :club="$club" :packages="$packages ?? []" />

<!-- Edit Member Modal -->
<div id="editMemberModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('editMemberModal')"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-xl overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-purple-500/10 to-purple-500/5 border-b border-gray-100">
                <h3 class="text-xl font-bold text-gray-900">Edit Member Profile</h3>
                <p class="text-sm text-gray-500 mt-1">Update details for <span id="editMemberName" class="font-medium">Member</span></p>
            </div>
            <div class="p-6">
                <input type="hidden" id="editMemberId">
                <div class="space-y-5">
                    <div>
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-2">
                            <span>&#127941;</span> Rank Level
                        </label>
                        <select id="editRank" class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-base">
                            <option value="Beginner">&#129353; Beginner</option>
                            <option value="Member">&#128100; Member</option>
                            <option value="Advanced">&#11088; Advanced</option>
                            <option value="Elite">&#128142; Elite</option>
                            <option value="Champion">&#127942; Champion</option>
                        </select>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-2">
                            <span>&#127942;</span> Achievements Count
                        </label>
                        <input type="number" id="editAchievements" min="0" class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-base" placeholder="Number of achievements earned">
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                <div class="flex flex-wrap gap-3">
                    <button onclick="closeModal('editMemberModal')" class="btn btn-outline-light">Cancel</button>
                    <button onclick="openEnrollModal()" class="flex-1 btn btn-dark">
                        <i class="bi bi-person-plus mr-2"></i>Enroll
                    </button>
                    <button onclick="openLeaveModal()" class="flex-1 btn btn-danger">
                        <i class="bi bi-box-arrow-right mr-2"></i>Leave
                    </button>
                    <button onclick="saveEditMember()" class="flex-1 btn btn-primary">
                        <i class="bi bi-check-lg mr-2"></i>Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enroll in Package Modal -->
<div id="enrollPackageModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('enrollPackageModal')"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-xl font-bold text-gray-900">Enroll in Package</h3>
                <p class="text-sm text-gray-500 mt-1">Select the perfect package for <span id="enrollMemberName" class="font-medium">member</span></p>
            </div>
            <div class="p-6 overflow-y-auto max-h-[60vh]">
                <div id="enrollMemberInfo" class="hidden mb-6"></div>
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-gray-900">Available Packages</h4>
                        <span class="px-2.5 py-1 bg-gray-100 text-gray-600 text-sm rounded-full" id="packagesCount">0 packages</span>
                    </div>
                    <div id="enrollPackagesList" class="space-y-4"></div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                <button onclick="closeModal('enrollPackageModal')" class="flex-1 btn btn-outline-light">Cancel</button>
                <button onclick="confirmEnrollment()" id="confirmEnrollBtn" disabled class="flex-1 btn btn-primary">
                    <i class="bi bi-check-lg mr-2"></i>Confirm Enrollment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Leave Club Modal -->
<div id="leaveClubModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('leaveClubModal')"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-xl font-bold text-gray-900">Confirm Member Leave</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4">Are you sure you want to process <strong id="leaveMemberName">this member</strong>'s departure from the club?</p>
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Leave Reason (Optional)</label>
                    <textarea id="leaveReason" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none" placeholder="Enter reason for leaving..."></textarea>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm font-semibold text-gray-700 mb-2">What will happen:</p>
                    <ul class="text-sm text-gray-500 space-y-1 list-disc list-inside">
                        <li>Member status will be set to inactive</li>
                        <li>All package enrollments will be deactivated</li>
                        <li>Membership history will be recorded</li>
                    </ul>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                <button onclick="closeModal('leaveClubModal')" class="flex-1 btn btn-outline-light">Cancel</button>
                <button onclick="confirmLeave()" class="flex-1 btn btn-danger">Confirm Leave</button>
            </div>
        </div>
    </div>
</div>

<!-- Graduate Child Modal -->
<div id="graduateChildModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('graduateChildModal')"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-xl font-bold text-gray-900">Graduate Child to Adult</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4"><strong id="graduateChildName">Child</strong> will become an independent adult member with their own account.</p>
                <input type="hidden" id="graduateChildId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email <span class="text-red-500">*</span></label>
                        <input type="email" id="graduateEmail" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="member@example.com">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Password <span class="text-red-500">*</span></label>
                        <input type="password" id="graduatePassword" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Create a strong password">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone <span class="text-red-500">*</span></label>
                        <div class="flex">
                            <select id="graduateCountryCode" class="px-3 py-2.5 border border-gray-200 rounded-l-lg bg-gray-50 text-sm">
                                <option value="+973">+973</option>
                                <option value="+966">+966</option>
                                <option value="+971">+971</option>
                            </select>
                            <input type="text" id="graduatePhone" class="flex-1 px-4 py-2.5 border-y border-r border-gray-200 rounded-r-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Phone number">
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                <button onclick="closeModal('graduateChildModal')" class="flex-1 btn btn-outline-light">Cancel</button>
                <button onclick="confirmGraduate()" class="flex-1 btn btn-primary">Graduate to Adult</button>
            </div>
        </div>
    </div>
</div>

<!-- Degrade to Child Modal -->
<div id="degradeToChildModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" onclick="closeModal('degradeToChildModal')"></div>
        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-xl font-bold text-gray-900">Move to Child Status</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4"><strong id="degradeMemberName">Member</strong> will be managed as a child by a parent member.</p>
                <input type="hidden" id="degradeMemberId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Parent Email or Phone <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <input type="text" id="parentSearchInput" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="parent@example.com" autocomplete="new-password">
                        <button onclick="searchParent()" class="btn btn-outline-primary">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
                <div id="parentSearchResults" class="hidden mb-4"></div>
                <div id="selectedParent" class="hidden mb-4 bg-purple-50 rounded-lg p-4"></div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                <button onclick="closeModal('degradeToChildModal')" class="flex-1 btn btn-outline-light">Cancel</button>
                <button onclick="confirmDegrade()" id="confirmDegradeBtn" disabled class="flex-1 btn btn-primary">Move to Child</button>
            </div>
        </div>
    </div>
</div>
@endsection

@include('admin.club.members.partials.member-popup')

{{-- Import Members Modal --}}
<div id="importMembersModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Import Members</h3>
                <p class="text-sm text-gray-500 mt-0.5">Upload an Excel file to bulk-add members</p>
            </div>
            <button onclick="closeImportModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="px-6 py-5 space-y-4">

            {{-- Step 1: Download template --}}
            <div class="bg-purple-50 border border-purple-100 rounded-lg p-4 flex items-start gap-3">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 font-bold text-sm">1</div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-800">Download the template</p>
                    <p class="text-xs text-gray-500 mt-0.5">Fill it in and upload below. Do not rename or reorder columns.</p>
                    <a href="{{ route('admin.club.members.import-template', $club->slug) }}"
                       class="inline-flex items-center mt-2 px-3 py-1.5 text-xs font-medium text-purple-700 bg-white border border-purple-200 rounded-md hover:bg-purple-50 transition-colors">
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Download Template (.xlsx)
                    </a>
                </div>
            </div>

            {{-- Step 2: Upload --}}
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 font-bold text-sm">2</div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-800 mb-2">Upload filled file</p>
                    <form id="importMembersForm" enctype="multipart/form-data">
                        @csrf
                        <label for="import_file"
                               id="importDropZone"
                               class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 hover:border-purple-400 transition-colors">
                            <div id="importDropLabel" class="flex flex-col items-center text-center px-4">
                                <svg class="w-7 h-7 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                <span class="text-sm text-gray-500">Click to browse or drag &amp; drop</span>
                                <span class="text-xs text-gray-400 mt-0.5">.xlsx or .csv — max 5 MB</span>
                            </div>
                            <input id="import_file" name="import_file" type="file" accept=".xlsx,.xls,.csv" class="hidden" onchange="handleImportFileSelect(this)">
                        </label>
                    </form>
                </div>
            </div>

            {{-- Result area --}}
            <div id="importResult" class="hidden rounded-lg p-3 text-sm"></div>
            <div id="importErrors" class="hidden rounded-lg bg-yellow-50 border border-yellow-200 p-3 text-xs text-yellow-800 space-y-1 max-h-28 overflow-y-auto"></div>

        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
            <button onclick="closeImportModal()" class="btn btn-outline-light">Cancel</button>
            <button id="importSubmitBtn" onclick="submitImport()" disabled class="btn btn-primary">
                <svg id="importSpinner" class="hidden w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                Import
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
const clubId = '{{ $club->id }}';
let selectedMembers = new Set();
let searchResults = [];
let currentEditingMember = null;
let selectedPackageId = null;
let selectedParentId = null;
let availablePackages = @json($packages ?? []);

document.addEventListener('DOMContentLoaded', function() {
    // Member card click → quick-view popup (delegate from grid)
    document.getElementById('membersGrid')?.addEventListener('click', function(e) {
        const wrapper = e.target.closest('.member-card-wrapper[data-member-id]');
        if (!wrapper) return;
        e.preventDefault();
        e.stopPropagation();
        const userId   = wrapper.getAttribute('data-member-id');
        const popupUrl = wrapper.getAttribute('data-popup-url');
        if (userId && popupUrl && window.openMemberPopup) {
            window.openMemberPopup(userId, popupUrl);
        }
    });

    loadMemberCards();
    loadNationalityFlags();
});

// Load countries and convert ISO3 to flag emoji
function loadNationalityFlags() {
    fetch('/data/countries.json')
        .then(response => response.json())
        .then(countries => {
            document.querySelectorAll('.nationality-display').forEach(element => {
                const iso3Code = element.getAttribute('data-iso3');
                if (!iso3Code) return;

                const country = countries.find(c => c.iso2 === iso3Code || c.iso3 === iso3Code);
                if (country) {
                    const flagEmoji = country.iso2
                        .toUpperCase()
                        .split('')
                        .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                        .join('');

                    element.textContent = `${flagEmoji} ${country.name}`;
                }
            });
        })
        .catch(error => console.error('Error loading countries:', error));
}

const _cardsUrl    = '{{ route('admin.club.members.cards', $club->slug) }}';
const _cardsFilter = '{{ $filter }}';
let _formerLoaded  = false;

function loadMemberCards() {
    const grid  = document.getElementById('membersGrid');
    const empty = document.getElementById('membersEmpty');
    if (!grid) return;

    fetch(_cardsUrl + '?filter=' + _cardsFilter)
        .then(r => r.text())
        .then(html => {
            // Remove skeletons
            grid.querySelectorAll('.members-skeleton').forEach(el => el.remove());
            grid.insertAdjacentHTML('beforeend', html);
            const hasCards = grid.querySelectorAll('.member-item').length > 0;
            if (!hasCards && empty) {
                grid.classList.add('hidden');
                empty.classList.remove('hidden');
            }
            initializeSearch();
            loadNationalityFlags();
        })
        .catch(() => {
            grid.querySelectorAll('.members-skeleton').forEach(el => el.remove());
        });
}

// Re-fetch and re-render the current members grid in place (no page reload).
// Reuses the exact server-rendered card markup from the cards partial.
window.reloadMemberCards = function reloadMemberCards() {
    const grid  = document.getElementById('membersGrid');
    const empty = document.getElementById('membersEmpty');
    if (!grid) return;

    return fetch(_cardsUrl + '?filter=' + _cardsFilter)
        .then(r => r.text())
        .then(html => {
            grid.querySelectorAll('.member-item, .members-skeleton').forEach(el => el.remove());
            grid.insertAdjacentHTML('beforeend', html);
            const hasCards = grid.querySelectorAll('.member-item').length > 0;
            if (hasCards) {
                grid.classList.remove('hidden');
                if (empty) empty.classList.add('hidden');
            } else if (empty) {
                grid.classList.add('hidden');
                empty.classList.remove('hidden');
            }
            initializeSearch();
            loadNationalityFlags();
        })
        .catch(() => {});
};

function loadFormerCards() {
    if (_formerLoaded) return;
    _formerLoaded = true;
    const wrap = document.getElementById('formerCardsWrap');
    if (!wrap) return;
    wrap.innerHTML = '<div class="py-16 text-center text-gray-400"><i class="bi bi-hourglass-split text-3xl animate-pulse block mb-2"></i><p class="text-sm">Loading...</p></div>';
    fetch(_cardsUrl + '?filter=former')
        .then(r => r.text())
        .then(html => { wrap.innerHTML = html; })
        .catch(() => { wrap.innerHTML = '<p class="text-center text-red-500 py-8">Failed to load former members</p>'; });
}

// Tab switching
function switchTab(tab) {
    const tabs = {
        members:  { btn: 'members-tab-btn',  content: 'members-content'  },
        requests: { btn: 'requests-tab-btn', content: 'requests-content' },
        former:   { btn: 'former-tab-btn',   content: 'former-content'   },
    };
    Object.entries(tabs).forEach(([key, { btn, content }]) => {
        const b = document.getElementById(btn);
        const c = document.getElementById(content);
        if (!b || !c) return;
        if (key === tab) {
            b.classList.add('border-purple-500', 'text-purple-600');
            b.classList.remove('border-transparent', 'text-gray-500');
            c.classList.remove('hidden');
        } else {
            b.classList.remove('border-purple-500', 'text-purple-600');
            b.classList.add('border-transparent', 'text-gray-500');
            c.classList.add('hidden');
        }
    });
    if (tab === 'former') loadFormerCards();
}

// Modal functions
function openModal(id) { document.getElementById(id).classList.remove('hidden'); }
function closeModal(id) { document.getElementById(id).classList.add('hidden'); }
function openAddExistingUserModal() {
    openModal('addExistingUserModal');
    document.getElementById('searchUserInput').value = '';
    document.getElementById('searchResults').classList.add('hidden');
    document.getElementById('searchResultsList').innerHTML = '';
    selectedMembers.clear();
    updateSelectedCount();
}
// Search existing users
async function searchUser() {
    const query = document.getElementById('searchUserInput').value.trim();
    if (query.length < 2) {
        document.getElementById('searchResults').classList.add('hidden');
        return;
    }


    try {
        const response = await fetch(`/admin/club/${clubId}/members/search?query=${encodeURIComponent(query)}`);
        const data = await response.json();

        const resultsContainer = document.getElementById('searchResults');
        const resultsList = document.getElementById('searchResultsList');
        resultsList.innerHTML = '';

        if (data.users && data.users.length > 0) {
            data.users.forEach(user => {
                const userCard = createUserCard(user);
                resultsList.appendChild(userCard);

                // Add dependents if any
                if (user.dependents && user.dependents.length > 0) {
                    user.dependents.forEach(dep => {
                        const depCard = createUserCard(dep, true);
                        resultsList.appendChild(depCard);
                    });
                }
            });
            resultsContainer.classList.remove('hidden');
        } else {
            resultsList.innerHTML = '<div class="text-center py-8 text-gray-500"><svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><p>No users found matching your search</p></div>';
            resultsContainer.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Search error:', error);
        window.showToast('error', 'Error searching users. Please try again.');
    } finally {}
}

function createUserCard(user, isDependent = false) {
    const card = document.createElement('div');
    const isMale = user.gender === 'Male';

    card.className = `search-result-card p-4 border-2 rounded-xl cursor-pointer transition-all ${user.is_member ? 'border-gray-200 bg-gray-50 opacity-60' : 'border-gray-200 hover:border-purple-400'} ${isDependent ? 'ml-8' : ''}`;
    card.dataset.userId = user.id;

    const initial = (user.name || 'U').charAt(0).toUpperCase();
    const gradientColor = isMale ? '#8b5cf6, #7c3aed' : '#d63384, #a61e4d';

    // Build contact info string
    let contactInfo = '';
    if (user.email) {
        contactInfo = user.email;
    } else if (user.mobile) {
        contactInfo = (user.mobile.code || '') + ' ' + (user.mobile.number || '');
    }

    // For children, show guardian info
    let guardianInfo = '';
    if (isDependent && user.is_child && user.guardian_name) {
        guardianInfo = `<span class="text-xs text-blue-600">Guardian: ${user.guardian_name}</span>`;
    }

    // Relationship badge color
    let badgeColor = 'bg-blue-500'; // default for children
    if (user.relationship_type === 'Spouse') {
        badgeColor = 'bg-pink-500';
    } else if (user.relationship_type === 'Son') {
        badgeColor = 'bg-blue-500';
    } else if (user.relationship_type === 'Daughter') {
        badgeColor = 'bg-purple-500';
    }

    card.innerHTML = `
        <div class="flex items-center gap-4">
            <div class="relative flex-shrink-0">
                ${user.profile_picture
                    ? `<img src="${user.profile_picture}" class="w-14 h-14 rounded-full object-cover border-2 border-white shadow">`
                    : `<div class="w-14 h-14 rounded-full flex items-center justify-center text-white font-bold text-xl shadow" style="background: linear-gradient(135deg, ${gradientColor});">${initial}</div>`
                }
                ${isDependent ? `<span class="absolute -top-1 -right-1 ${badgeColor} text-white text-xs px-1.5 py-0.5 rounded-full">${user.relationship_type || 'Family'}</span>` : ''}
            </div>
            <div class="flex-1 min-w-0">
                <h6 class="font-semibold text-gray-900 truncate">${user.name}</h6>
                <p class="text-sm text-gray-500">${contactInfo || 'No contact info'}</p>
                <div class="flex items-center gap-2">
                    ${user.age ? `<span class="text-xs text-gray-400">${user.age} years old</span>` : ''}
                    ${guardianInfo}
                </div>
            </div>
            <div class="flex-shrink-0">
                ${user.is_member
                    ? '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Already Member</span>'
                    : `<div class="check-circle w-6 h-6 rounded-full border-2 border-gray-300 flex items-center justify-center transition-all">
                        <svg class="w-4 h-4 text-white hidden" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>
                       </div>`
                }
            </div>
        </div>
    `;

    if (!user.is_member) {
        card.addEventListener('click', () => toggleUserSelection(user.id, card));
    }

    return card;
}

function toggleUserSelection(userId, card) {
    if (selectedMembers.has(userId)) {
        selectedMembers.delete(userId);
        card.classList.remove('selected');
        card.querySelector('.check-circle').classList.remove('checked');
        card.querySelector('.check-circle svg').classList.add('hidden');
    } else {
        selectedMembers.add(userId);
        card.classList.add('selected');
        card.querySelector('.check-circle').classList.add('checked');
        card.querySelector('.check-circle svg').classList.remove('hidden');
    }
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = selectedMembers.size;
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('addMembersBtn').disabled = count === 0;
}

async function addSelectedMembers() {
    if (selectedMembers.size === 0) return;

    const btn = document.getElementById('addMembersBtn');
    btn.disabled = true;
    btn.innerHTML = 'Adding...';

    try {
        const response = await fetch(`/admin/club/${clubId}/members`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ user_ids: Array.from(selectedMembers) }),
        });

        const data = await response.json();

        if (response.ok && data.success) {
            closeModal('addExistingUserModal');
            window.showToast('success', data.message || 'Member(s) added successfully.');
            reloadMemberCards();
        } else {
            window.showToast('error', data.message || 'Error adding members');
        }
    } catch (error) {
        console.error('Error:', error);
        window.showToast('error', 'Error adding members. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Add <span id="selectedCount">0</span> Member(s)';
    }
}

// Search as user types (debounced) + Enter key
let searchDebounce = null;
document.getElementById('searchUserInput')?.addEventListener('input', function() {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(searchUser, 350);
});
document.getElementById('searchUserInput')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { clearTimeout(searchDebounce); searchUser(); }
});

let membersPaginator;

function initializeSearch() {
    membersPaginator = new ClientPaginator({
        itemsSelector : '.member-item',
        containerId   : 'membersPagination',
        perPage       : 20,
        countBadgeId  : 'membersCount',
        scrollTargetId: 'membersGrid',
        labelSingular : 'member',
        labelPlural   : 'members',
        filterFn(item) {
            const query       = (document.getElementById('searchMembers')?.value || '').toLowerCase();
            const gender      = document.getElementById('filterGender')?.value      || '';
            const category    = document.getElementById('filterCategory')?.value    || '';
            const weightClass = document.getElementById('filterWeightClass')?.value || '';
            const ageMinRaw   = document.getElementById('filterAgeMin')?.value;
            const ageMaxRaw   = document.getElementById('filterAgeMax')?.value;
            const ageMin      = ageMinRaw ? parseInt(ageMinRaw) : null;
            const ageMax      = ageMaxRaw ? parseInt(ageMaxRaw) : null;

            const name            = item.dataset.name  || '';
            const phone           = item.dataset.phone || '';
            const email           = item.dataset.email || '';
            const age             = item.dataset.age !== '' ? parseInt(item.dataset.age) : null;
            const itemGender      = item.dataset.gender      || '';
            const itemCategory    = item.dataset.tkdCategory || '';
            const itemWeightClass = item.dataset.weightClass || '';

            return (!query       || name.includes(query) || phone.startsWith(query) || email.includes(query))
                && (!gender      || itemGender === gender)
                && (!category    || itemCategory === category)
                && (!weightClass || itemWeightClass === weightClass)
                && (ageMin === null || (age !== null && age >= ageMin))
                && (ageMax === null || (age !== null && age <= ageMax));
        },
    });
    window._pagers['membersPagination'] = membersPaginator;
    membersPaginator.refresh();
}

function applyFilters() {
    membersPaginator?.refresh();
}

// Edit Member
function openEditModal(member) {
    currentEditingMember = member;
    document.getElementById('editMemberId').value = member.id;
    document.getElementById('editMemberName').textContent = member.user?.full_name || member.name || 'Member';
    document.getElementById('editRank').value = member.rank || 'Member';
    document.getElementById('editAchievements').value = member.achievements || 0;
    openModal('editMemberModal');
}

async function saveEditMember() {
    const id = document.getElementById('editMemberId').value;
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ rank: document.getElementById('editRank').value, achievements: parseInt(document.getElementById('editAchievements').value) })
        });
        if (res.ok) { window.showToast('success', 'Member updated'); closeModal('editMemberModal'); window.reloadMemberCards(); }
        else throw new Error();
    } catch { window.showToast('error', 'Error updating member'); }
}

// Enroll
function openEnrollModal() {
    if (!currentEditingMember) return;
    document.getElementById('enrollMemberName').textContent = currentEditingMember.user?.full_name || 'member';
    displayPackages();
    closeModal('editMemberModal');
    openModal('enrollPackageModal');
}

function displayPackages() {
    const container = document.getElementById('enrollPackagesList');
    container.innerHTML = '';
    selectedPackageId = null;
    document.getElementById('confirmEnrollBtn').disabled = true;
    if (!availablePackages.length) {
        container.innerHTML = '<div class="text-center py-8 text-gray-500">No packages available</div>';
        return;
    }
    document.getElementById('packagesCount').textContent = `${availablePackages.length} package${availablePackages.length !== 1 ? 's' : ''}`;
    availablePackages.forEach(pkg => {
        const card = document.createElement('div');
        card.className = 'package-card bg-white border-2 border-gray-200 rounded-xl p-4 cursor-pointer transition-all hover:border-purple-400';
        card.dataset.id = pkg.id;
        card.onclick = () => selectPackage(pkg.id, card);
        card.innerHTML = `
            <div class="flex items-start gap-4">
                <div class="check-circle w-7 h-7 border-2 border-gray-300 rounded-full flex items-center justify-center transition-all flex-shrink-0">
                    <svg class="w-4 h-4 text-white hidden check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                        <h5 class="font-bold text-gray-900">${pkg.name}</h5>
                        ${pkg.is_popular ? '<span class="px-2 py-0.5 bg-purple-500 text-white text-xs rounded-full">Popular</span>' : ''}
                    </div>
                    ${pkg.description ? `<p class="text-sm text-gray-500 mb-3">${pkg.description}</p>` : ''}
                    <div class="flex gap-6 text-sm">
                        <div><span class="text-gray-400">Price:</span> <span class="font-bold text-purple-600">${pkg.currency || 'BHD'} ${parseFloat(pkg.price).toFixed(2)}</span></div>
                        <div><span class="text-gray-400">Duration:</span> <span class="font-semibold">${pkg.duration_days} days</span></div>
                    </div>
                </div>
            </div>`;
        container.appendChild(card);
    });
}

function selectPackage(id, card) {
    document.querySelectorAll('.package-card').forEach(c => {
        c.classList.remove('selected', 'border-purple-500', 'bg-purple-50');
        c.classList.add('border-gray-200');
        c.querySelector('.check-circle').classList.remove('checked', 'bg-purple-500', 'border-purple-500');
        c.querySelector('.check-circle').classList.add('border-gray-300');
        c.querySelector('.check-icon').classList.add('hidden');
    });
    selectedPackageId = id;
    card.classList.add('selected', 'border-purple-500', 'bg-purple-50');
    card.classList.remove('border-gray-200');
    card.querySelector('.check-circle').classList.add('checked', 'bg-purple-500', 'border-purple-500');
    card.querySelector('.check-circle').classList.remove('border-gray-300');
    card.querySelector('.check-icon').classList.remove('hidden');
    document.getElementById('confirmEnrollBtn').disabled = false;
}

async function confirmEnrollment() {
    if (!selectedPackageId || !currentEditingMember) return;
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${currentEditingMember.id}/enroll`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ package_id: selectedPackageId })
        });
        if (res.ok) { window.showToast('success', 'Member enrolled'); closeModal('enrollPackageModal'); window.reloadMemberCards(); }
        else throw new Error();
    } catch { window.showToast('error', 'Error enrolling member'); }
}

// Leave
function openLeaveModal() {
    if (!currentEditingMember) return;
    document.getElementById('leaveMemberName').textContent = currentEditingMember.user?.full_name || 'this member';
    document.getElementById('leaveReason').value = '';
    closeModal('editMemberModal');
    openModal('leaveClubModal');
}

async function confirmLeave() {
    if (!currentEditingMember) return;
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${currentEditingMember.id}/leave`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ leave_reason: document.getElementById('leaveReason').value })
        });
        if (res.ok) { window.showToast('success', 'Member left club'); closeModal('leaveClubModal'); window.reloadMemberCards(); }
        else throw new Error();
    } catch { window.showToast('error', 'Error processing leave'); }
}

// Graduate
function openGraduateModal(member) {
    currentEditingMember = member;
    document.getElementById('graduateChildId').value = member.id;
    document.getElementById('graduateChildName').textContent = member.user?.full_name || 'Child';
    openModal('graduateChildModal');
}

async function confirmGraduate() {
    const email = document.getElementById('graduateEmail').value;
    const password = document.getElementById('graduatePassword').value;
    const phone = document.getElementById('graduatePhone').value;
    if (!email || !password || !phone) { window.showToast('warning', 'Fill all fields'); return; }
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${currentEditingMember.id}/graduate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ email, password, phone, country_code: document.getElementById('graduateCountryCode').value })
        });
        if (res.ok) { window.showToast('success', 'Child graduated'); closeModal('graduateChildModal'); window.reloadMemberCards(); }
        else throw new Error();
    } catch { window.showToast('error', 'Error graduating child'); }
}

// Degrade
function openDegradateModal(member) {
    currentEditingMember = member;
    document.getElementById('degradeMemberId').value = member.id;
    document.getElementById('degradeMemberName').textContent = member.user?.full_name || 'Member';
    document.getElementById('parentSearchInput').value = '';
    document.getElementById('parentSearchResults').classList.add('hidden');
    document.getElementById('selectedParent').classList.add('hidden');
    selectedParentId = null;
    document.getElementById('confirmDegradeBtn').disabled = true;
    openModal('degradeToChildModal');
}

async function searchParent() {
    const q = document.getElementById('parentSearchInput').value.trim();
    if (!q) { window.showToast('warning', 'Enter parent email/phone'); return; }
    try {
        const res = await fetch(`/api/users/search?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (data.success && data.data.length) displayParentResults(data.data);
        else window.showToast('warning', 'No parent found');
    } catch { window.showToast('error', 'Error searching'); }
}

function displayParentResults(results) {
    const container = document.getElementById('parentSearchResults');
    container.innerHTML = results.map(r => `
        <div onclick="selectParent(${JSON.stringify(r).replace(/"/g, '&quot;')})" class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-pointer hover:bg-gray-100 transition-colors mb-2">
            <div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center text-white font-bold">${(r.name || '?').charAt(0).toUpperCase()}</div>
            <div><div class="font-semibold text-gray-900">${r.name}</div><div class="text-sm text-gray-500">${r.email || r.phone || ''}</div></div>
        </div>
    `).join('');
    container.classList.remove('hidden');
    document.getElementById('selectedParent').classList.add('hidden');
}

function selectParent(parent) {
    selectedParentId = parent.user_id || parent.id;
    document.getElementById('parentSearchResults').classList.add('hidden');
    const selected = document.getElementById('selectedParent');
    selected.innerHTML = `<div class="flex items-center gap-3"><div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center text-white font-bold">${(parent.name || '?').charAt(0).toUpperCase()}</div><div><div class="font-semibold text-gray-900">${parent.name}</div><div class="text-sm text-gray-500">${parent.email || ''}</div></div></div>`;
    selected.classList.remove('hidden');
    document.getElementById('confirmDegradeBtn').disabled = false;
}

async function confirmDegrade() {
    if (!selectedParentId || !currentEditingMember) return;
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${currentEditingMember.id}/degrade`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ parent_id: selectedParentId })
        });
        if (res.ok) { window.showToast('success', 'Moved to child'); closeModal('degradeToChildModal'); window.reloadMemberCards(); }
        else throw new Error();
    } catch { window.showToast('error', 'Error moving to child'); }
}

async function deleteMember(id) {
    const ok = await window.confirmAction({ title: 'Delete member?', message: 'Delete this member?', type: 'danger', confirmText: 'Delete' });
    if (!ok) return;
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
        if (res.ok) {
            window.showToast('success', 'Member deleted');
            // Remove the member's card in place; reveal empty-state if the grid empties.
            const card = document.querySelector(`[data-member-id="${id}"]`);
            card?.closest('.member-item')?.remove();
            const grid  = document.getElementById('membersGrid');
            const empty = document.getElementById('membersEmpty');
            if (grid && empty && grid.querySelectorAll('.member-item').length === 0) {
                grid.classList.add('hidden');
                empty.classList.remove('hidden');
            }
            window._pagers?.['membersPagination']?.refresh?.();
        }
        else throw new Error();
    } catch { window.showToast('error', 'Error deleting'); }
}

// Walk-In Registration is now handled by the registration-walkin component
// Notifications use the global window.showToast(type, message) toast.

// ── Import Members ──────────────────────────────────────────────────────────
function closeImportModal() {
    document.getElementById('importMembersModal').classList.add('hidden');
    document.getElementById('importMembersForm').reset();
    document.getElementById('importSubmitBtn').disabled = true;
    document.getElementById('importResult').classList.add('hidden');
    document.getElementById('importErrors').classList.add('hidden');
    document.getElementById('importDropLabel').innerHTML = `
        <svg class="w-7 h-7 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
        <span class="text-sm text-gray-500">Click to browse or drag &amp; drop</span>
        <span class="text-xs text-gray-400 mt-0.5">.xlsx or .csv — max 5 MB</span>`;
}

function handleImportFileSelect(input) {
    const btn = document.getElementById('importSubmitBtn');
    const label = document.getElementById('importDropLabel');
    if (input.files && input.files[0]) {
        label.innerHTML = `<svg class="w-5 h-5 text-purple-500 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <span class="text-sm font-medium text-gray-700">${input.files[0].name}</span>
            <span class="text-xs text-gray-400 mt-0.5">${(input.files[0].size / 1024).toFixed(1)} KB</span>`;
        btn.disabled = false;
    } else {
        btn.disabled = true;
    }
}

async function submitImport() {
    const form = document.getElementById('importMembersForm');
    const btn  = document.getElementById('importSubmitBtn');
    const spinner = document.getElementById('importSpinner');
    const result  = document.getElementById('importResult');
    const errBox  = document.getElementById('importErrors');

    const formData = new FormData(form);
    btn.disabled = true;
    spinner.classList.remove('hidden');
    result.classList.add('hidden');
    errBox.classList.add('hidden');

    try {
        const resp = await fetch('{{ route("admin.club.members.import", $club->slug) }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: formData,
        });
        const data = await resp.json();

        result.classList.remove('hidden');
        if (data.success) {
            result.className = 'rounded-lg p-3 text-sm bg-green-50 border border-green-200 text-green-800';
            result.textContent = data.message;
            if (data.errors && data.errors.length) {
                errBox.classList.remove('hidden');
                errBox.innerHTML = data.errors.map(e => `<div>⚠ ${e}</div>`).join('');
            }
            // Refresh the members grid in place so newly imported members appear
            if (data.imported > 0) reloadMemberCards();
        } else {
            result.className = 'rounded-lg p-3 text-sm bg-red-50 border border-red-200 text-red-800';
            result.textContent = data.message;
        }
    } catch (e) {
        result.classList.remove('hidden');
        result.className = 'rounded-lg p-3 text-sm bg-red-50 border border-red-200 text-red-800';
        result.textContent = 'Upload failed. Please try again.';
    } finally {
        btn.disabled = false;
        spinner.classList.add('hidden');
    }
}

// Close on backdrop click
document.getElementById('importMembersModal').addEventListener('click', function(e) {
    if (e.target === this) closeImportModal();
});
</script>
@endpush
