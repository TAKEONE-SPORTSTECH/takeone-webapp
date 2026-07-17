@extends('layouts.admin-club')


{{-- Styles moved to app.css (Phase 6) --}}

@section('club-admin-content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">{{ __('admin.club_members_index_title') }}</h2>
            <p class="text-gray-500 mt-1">{{ __('admin.club_members_index_subtitle') }}</p>
        </div>
        <div class="flex gap-2 flex-wrap" x-data="{ open: false }">
            <div class="relative">
                <button type="button" @click="open = !open" @click.outside="open = false" @keydown.escape="open = false"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary/90 transition-colors">
                    <i class="bi bi-person-plus"></i>{{ __('admin.club_members_index_add_member') }}
                    <i class="bi bi-chevron-down text-xs transition-transform duration-200" :class="open && 'rotate-180'"></i>
                </button>
                <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                     class="absolute end-0 mt-2 w-60 max-w-[calc(100vw-2rem)] rounded-xl bg-white border border-gray-100 shadow-lg overflow-hidden z-50">
                    <button type="button" @click="open = false; openWalkInModal()"
                            class="w-full text-start flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-800 hover:bg-muted/60 transition-colors">
                        <i class="bi bi-people text-primary w-4"></i>{{ __('admin.club_members_index_walkin_registration') }}
                    </button>
                    <button type="button" @click="open = false; openAddExistingUserModal()"
                            class="w-full text-start flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-800 hover:bg-muted/60 transition-colors">
                        <i class="bi bi-person-check text-primary w-4"></i>{{ __('admin.club_members_index_add_existing_user') }}
                    </button>
                    <button type="button" @click="open = false; document.getElementById('importMembersModal').classList.remove('hidden')"
                            class="w-full text-start flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-800 hover:bg-muted/60 transition-colors">
                        <i class="bi bi-upload text-primary w-4"></i>{{ __('admin.club_members_index_import_members') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="space-y-3">

        {{-- Status overview — sparkline = monthly new registrations trend --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
            <x-stat-card size="sm"
                card-id="sc-total"
                :label="__('admin.club_members_index_stat_total_label')"
                :value="$allCount"
                :sub-label="__('admin.club_members_index_stat_total_sub')"
                icon="bi-people-fill"
                icon-bg="bg-violet-100"
                icon-color="text-violet-600"
                :spark-data="$monthlyNewMembers"
                :spark-labels="$monthlyLabels"
                spark-color="hsl(250 65% 60%)"
                :href="route('admin.club.members', [$club->slug, 'filter' => 'all'])"
            />
            <x-stat-card size="sm"
                card-id="sc-subscribed"
                :label="__('admin.club_members_index_stat_subscribed_label')"
                :value="$activeCount"
                :sub-label="__('admin.club_members_index_stat_subscribed_sub')"
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
                :label="__('admin.club_members_index_stat_not_active_label')"
                :value="$notActiveCount"
                :sub-label="__('admin.club_members_index_stat_not_active_sub')"
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
                :label="__('admin.club_members_index_stat_former_label')"
                :value="$formerCount"
                :sub-label="__('admin.club_members_index_stat_former_sub')"
                icon="bi-person-x-fill"
                icon-bg="bg-gray-100"
                icon-color="text-gray-500"
                :spark-data="array_fill(0, 12, 0)"
                spark-color="#6b7280"
            />
        </div>

        {{-- Demographics — compact quick-filter chips (fire the same filter-demo events the cards did) --}}
        @php
            $genderChips = [
                ['male',   'Male',   'bi-gender-male',   'text-blue-500', $maleCount],
                ['female', 'Female', 'bi-gender-female', 'text-rose-500', $femaleCount],
            ];
            $ageChips = [
                ['Kids',    'bi-star-fill',      'text-emerald-500'],
                ['Cadet',   'bi-lightning-fill', 'text-amber-500'],
                ['Junior',  'bi-trophy-fill',    'text-orange-500'],
                ['Senior',  'bi-shield-fill',    'text-violet-500'],
                ['Masters', 'bi-crown-fill',     'text-gray-500'],
            ];
        @endphp
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 px-3 py-2.5 flex items-center gap-2 overflow-x-auto max-w-full">
            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 me-1 shrink-0">{{ __('admin.club_members_index_quick_filter') }}</span>

            @foreach($genderChips as [$val, $label, $icon, $color, $count])
                <button type="button"
                        onclick="window.dispatchEvent(new CustomEvent('filter-demo',{detail:{type:'gender',value:'{{ $val }}'}}))"
                        class="shrink-0 inline-flex items-center gap-1.5 ps-3 pe-2 py-1.5 rounded-full border border-gray-200 text-sm font-medium text-gray-700 whitespace-nowrap leading-none hover:border-primary/40 hover:bg-accent/40 hover:text-primary transition-colors">
                    <i class="bi {{ $icon }} {{ $color }}"></i>{{ __('admin.club_members_index_gender_' . $val) }}
                    <span class="text-[11px] font-bold bg-gray-100 text-gray-500 rounded-full px-1.5 min-w-[1.25rem] text-center">{{ $count }}</span>
                </button>
            @endforeach

            <span class="w-px h-5 bg-gray-200 mx-0.5 shrink-0"></span>

            @foreach($ageChips as [$cat, $icon, $color])
                <button type="button"
                        onclick="window.dispatchEvent(new CustomEvent('filter-demo',{detail:{type:'category',value:'{{ $cat }}'}}))"
                        class="shrink-0 inline-flex items-center gap-1.5 ps-3 pe-2 py-1.5 rounded-full border border-gray-200 text-sm font-medium text-gray-700 whitespace-nowrap leading-none hover:border-primary/40 hover:bg-accent/40 hover:text-primary transition-colors">
                    <i class="bi {{ $icon }} {{ $color }}"></i>{{ __('admin.club_members_index_cat_' . strtolower($cat)) }}
                    <span class="text-[11px] font-bold bg-gray-100 text-gray-500 rounded-full px-1.5 min-w-[1.25rem] text-center">{{ $ageGroupCounts[$cat] }}</span>
                </button>
            @endforeach
        </div>

    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-8">
            <button id="members-tab-btn" onclick="switchTab('members')" class="py-4 px-1 border-b-2 border-purple-500 text-purple-600 font-medium text-sm whitespace-nowrap">
                {{ __('admin.club_members_index_tab_current') }}
                <span class="ms-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-purple-100 text-purple-600" id="membersCount">{{ $statusCounts[$filter] ?? count($members) }}</span>
            </button>
            <button id="requests-tab-btn" onclick="switchTab('requests')" class="py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap">
                {{ __('admin.club_members_index_tab_pending') }}
                <span class="ms-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700" id="requestsCount">{{ $pendingRequests ?? 0 }}</span>
            </button>
            <button id="former-tab-btn" onclick="switchTab('former')" class="py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap">
                {{ __('admin.club_members_index_tab_former') }}
                <span class="ms-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ $formerCount ?? 0 }}</span>
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
                        <svg class="absolute start-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <input type="text" id="searchMembers" placeholder="{{ __('admin.club_members_index_search_placeholder') }}" autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly')" class="w-full ps-10 pe-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" @input="applyFilters()">
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-2">
                    <span class="text-sm font-medium text-gray-600 self-center">{{ __('admin.club_members_index_status_label') }}</span>
                    <div class="inline-flex flex-wrap rounded-lg border border-gray-200 p-1 bg-gray-50 overflow-x-auto max-w-full">
                        <a href="{{ request()->fullUrlWithQuery(['filter' => 'active', 'page' => 1]) }}" class="status-btn px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ $filter === 'active' ? 'active' : 'text-gray-600 hover:bg-gray-100' }}">
                            {{ __('admin.club_members_index_filter_active') }} <span class="ms-1 text-xs opacity-75">{{ $statusCounts['active'] }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['filter' => 'not_active', 'page' => 1]) }}" class="status-btn px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ $filter === 'not_active' ? 'active' : 'text-gray-600 hover:bg-gray-100' }}">
                            {{ __('admin.club_members_index_filter_not_active') }} <span class="ms-1 text-xs opacity-75">{{ $statusCounts['not_active'] }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['filter' => 'all', 'page' => 1]) }}" class="status-btn px-3 py-1.5 text-sm font-medium rounded-md transition-colors {{ $filter === 'all' ? 'active' : 'text-gray-600 hover:bg-gray-100' }}">
                            {{ __('admin.club_members_index_all') }} <span class="ms-1 text-xs opacity-75">{{ $statusCounts['all'] }}</span>
                        </a>
                    </div>
                    <button @click="showAdvanced = !showAdvanced"
                            class="inline-flex items-center gap-2 px-3 py-2 border rounded-lg text-sm font-medium transition-colors"
                            :class="activeCount > 0 ? 'border-purple-400 text-purple-600 bg-purple-50' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                        <i class="bi bi-sliders"></i>
                        {{ __('admin.club_members_index_filters') }}
                        <span x-show="activeCount > 0" x-text="activeCount"
                              class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-purple-600 text-white text-xs font-bold"></span>
                    </button>

                    {{-- Cards / Table view toggle --}}
                    <div class="inline-flex items-center p-1 rounded-lg bg-gray-100 shrink-0" role="tablist" aria-label="{{ __('admin.club_members_index_view_mode') }}"
                         x-data="{ view: localStorage.getItem('clubMembersView') || 'cards', set(v) { this.view = v; localStorage.setItem('clubMembersView', v); window.setMembersView?.(v); } }"
                         x-init="window.setMembersView?.(view)">
                        <button type="button" role="tab" @click="set('cards')" :aria-selected="view === 'cards'"
                                :class="view === 'cards' ? 'bg-white shadow text-purple-600' : 'text-gray-500 hover:text-gray-700'"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-colors">
                            <i class="bi bi-grid-3x3-gap-fill"></i><span class="hidden sm:inline">{{ __('admin.club_members_index_view_cards') }}</span>
                        </button>
                        <button type="button" role="tab" @click="set('table')" :aria-selected="view === 'table'"
                                :class="view === 'table' ? 'bg-white shadow text-purple-600' : 'text-gray-500 hover:text-gray-700'"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium transition-colors">
                            <i class="bi bi-table"></i><span class="hidden sm:inline">{{ __('admin.club_members_index_view_table') }}</span>
                        </button>
                    </div>

                    {{-- Bulk-select toggle for batch package enrollment --}}
                    <button type="button" onclick="window.toggleMembersSelectMode()" id="membersSelectModeBtn"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors shrink-0">
                        <i class="bi bi-check2-square"></i>
                        <span class="hidden sm:inline" id="membersSelectModeLabel">{{ __('admin.club_members_index_select') }}</span>
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
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.club_members_index_age_min') }}</label>
                    <input type="number" id="filterAgeMin" min="0" max="100" placeholder="{{ __('admin.club_members_index_age_min_ph') }}"
                           x-model="ageMin" @input="applyFilters()"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>

                <!-- Age Max -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.club_members_index_age_max') }}</label>
                    <input type="number" id="filterAgeMax" min="0" max="100" placeholder="{{ __('admin.club_members_index_age_max_ph') }}"
                           x-model="ageMax" @input="applyFilters()"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>

                <!-- Gender -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.club_members_index_gender') }}</label>
                    <select id="filterGender" x-model="gender" @change="weightClass = ''; applyFilters()"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="">{{ __('admin.club_members_index_all') }}</option>
                        <option value="male">{{ __('admin.club_members_index_gender_male') }}</option>
                        <option value="female">{{ __('admin.club_members_index_gender_female') }}</option>
                    </select>
                </div>

                <!-- Category -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.club_members_index_category') }}</label>
                    <select id="filterCategory" x-model="category" @change="weightClass = ''; applyFilters()"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="">{{ __('admin.club_members_index_all') }}</option>
                        <option>Kids</option>
                        <option>Cadet</option>
                        <option>Junior</option>
                        <option>Senior</option>
                        <option>Masters</option>
                    </select>
                </div>

                <!-- Weight Class -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.club_members_index_weight_class') }}</label>
                    <select id="filterWeightClass" x-model="weightClass" @change="applyFilters()"
                            :disabled="weightClasses.length === 0"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent disabled:opacity-40 disabled:cursor-not-allowed">
                        <option value="">{{ __('admin.club_members_index_all') }}</option>
                        <template x-for="wc in weightClasses" :key="wc">
                            <option :value="wc" x-text="wc"></option>
                        </template>
                    </select>
                </div>

                <!-- Clear -->
                <div class="col-span-2 sm:col-span-3 lg:col-span-5 flex justify-end">
                    <button @click="reset()" x-show="activeCount > 0"
                            class="text-sm text-purple-600 hover:text-purple-800 font-medium flex items-center gap-1">
                        <i class="bi bi-x-circle"></i> {{ __('admin.club_members_index_clear_filters') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Members Grid — cards loaded via AJAX after page ready -->
        <div id="membersGridWrap">
            <div id="membersGrid" data-filter="{{ $filter }}" class="grid gap-6" style="grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));">
                {{-- Skeleton loaders shown while AJAX is in flight --}}
                @for($i = 0; $i < 8; $i++)
                <div class="members-skeleton bg-white rounded-xl border border-gray-100 animate-pulse" style="height:280px;"></div>
                @endfor
            </div>
            <div id="membersEmpty" class="tf-empty hidden">
                <div class="tf-empty-icon">
                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                </div>
                <h5 class="text-lg font-semibold text-gray-900 mb-2">{{ __('admin.club_members_index_empty_title') }}</h5>
                <p class="text-gray-500 mb-4">{{ __('admin.club_members_index_empty_sub') }}</p>
                <button onclick="openWalkInModal()" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-2"></i>{{ __('admin.club_members_index_add_member') }}
                </button>
            </div>
            <x-client-paginator id="membersPagination" :per-page="20" />
        </div>

        <!-- Members Table — built client-side from the loaded cards, respects the same search/filters -->
        <div id="membersTableWrap" class="hidden bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-start text-xs uppercase tracking-wide text-gray-500 bg-gray-50 border-b border-gray-100">
                            <th class="px-4 py-3 font-medium w-8 member-select-toggle-cell" style="display:none;"></th>
                            <th class="px-4 py-3 font-medium">{{ __('admin.club_members_index_th_member') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('admin.club_members_index_th_phone') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('admin.club_members_index_th_gender') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('admin.club_members_index_th_age') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('admin.club_members_index_th_nationality') }}</th>
                            <th class="px-4 py-3 font-medium">{{ __('admin.club_members_index_th_status') }}</th>
                        </tr>
                    </thead>
                    <tbody id="membersTableBody" class="divide-y divide-gray-100"></tbody>
                </table>
            </div>
            <div id="membersTableEmpty" class="hidden text-center py-12 text-gray-500">
                <i class="bi bi-search text-3xl"></i>
                <p class="mt-2 mb-0">{{ __('admin.club_members_index_table_empty') }}</p>
            </div>
        </div>
    </div>

    <!-- Bulk-select floating action bar (batch package enrollment) -->
    <div id="membersBulkBar" class="hidden fixed bottom-6 inset-x-0 z-40 justify-center px-4">
        <div class="flex items-center gap-3 bg-white shadow-lg border border-gray-100 rounded-xl px-4 py-3 max-w-full overflow-x-auto">
            <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer whitespace-nowrap">
                <input type="checkbox" id="membersSelectAllInput" onchange="window.toggleSelectAllBulkMembers(this.checked)"
                       class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">
                {{ __('admin.club_members_index_select_all') }}
            </label>
            <span class="w-px h-5 bg-gray-200 shrink-0"></span>
            <span class="text-sm text-gray-600 whitespace-nowrap" id="membersBulkCount">{{ __('admin.club_members_index_bulk_selected', ['count' => 0]) }}</span>
            <button type="button" onclick="window.clearBulkSelection()" class="text-sm text-gray-500 hover:text-gray-700 font-medium whitespace-nowrap">
                {{ __('admin.club_members_index_bulk_clear') }}
            </button>
            <button type="button" id="membersBulkEnrollBtn" onclick="window.openBulkEnrollModal()" disabled
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary/90 disabled:opacity-40 disabled:cursor-not-allowed transition-colors whitespace-nowrap">
                <i class="bi bi-person-plus"></i>{{ __('admin.club_members_index_bulk_enroll') }}
            </button>
            <button type="button" onclick="window.toggleMembersSelectMode(false)"
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-gray-100 transition-colors shrink-0"
                    aria-label="{{ __('admin.club_members_index_select_done') }}">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>

    <!-- Batch Enroll Modal -->
    <div id="bulkEnrollModal" class="fixed inset-0 z-50 hidden overflow-y-auto" x-data="bulkEnrollModalData()">
        <div class="flex min-h-screen items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50 transition-opacity" @click="if (!submitting) closeModal('bulkEnrollModal')"></div>
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-gray-100 rounded-t-2xl">
                    <h3 class="text-xl font-bold text-gray-900">{{ __('admin.club_members_index_bulk_enroll_modal_title') }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ __('admin.club_members_index_bulk_enroll_modal_subtitle') }}</p>
                </div>
                <div class="p-6 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('admin.club_members_index_bulk_package_label') }}</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 max-h-72 overflow-y-auto pr-1 -mr-1">
                            @forelse(collect($packages ?? [])->where('is_active', true) as $pkg)
                                <button type="button" @click="packageId = {{ $pkg->id }}"
                                        class="text-left bg-white border-2 rounded-xl p-3.5 transition-all"
                                        :class="String(packageId) === '{{ $pkg->id }}' ? 'border-purple-500 bg-purple-50' : 'border-gray-200 hover:border-purple-300'">
                                    <div class="flex items-start justify-between gap-2">
                                        <h5 class="font-bold text-gray-900 text-sm truncate">{{ $pkg->name }}</h5>
                                        <span class="w-6 h-6 rounded-full border-2 flex items-center justify-center flex-shrink-0 transition-colors"
                                              :class="String(packageId) === '{{ $pkg->id }}' ? 'bg-purple-500 border-purple-500' : 'border-gray-300'">
                                            <i class="bi bi-check-lg text-white text-[11px]" x-show="String(packageId) === '{{ $pkg->id }}'"></i>
                                        </span>
                                    </div>
                                    @if($pkg->description)
                                        <p class="text-xs text-gray-500 mt-1 line-clamp-1">{{ $pkg->description }}</p>
                                    @endif
                                    <div class="flex items-center gap-4 mt-2.5 text-xs">
                                        <div>
                                            <span class="text-gray-400">{{ __('admin.club_members_index_js_price') }}</span>
                                            <span class="font-bold text-purple-600 ms-1">{{ $club->currency ?? 'BHD' }} {{ number_format((float) $pkg->price, 2) }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-400">{{ __('admin.club_members_index_js_duration') }}</span>
                                            <span class="font-semibold text-gray-700 ms-1">{{ $pkg->duration_months }} {{ Str::plural(__('admin.club_members_index_bulk_month'), $pkg->duration_months) }}</span>
                                        </div>
                                    </div>
                                </button>
                            @empty
                                <p class="text-sm text-gray-400 col-span-2 text-center py-6">{{ __('admin.club_members_index_js_no_packages') }}</p>
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700 cursor-pointer">
                            <input type="checkbox" x-model="backdate" class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary">
                            {{ __('admin.club_members_index_bulk_backdate_toggle') }}
                        </label>
                        <div x-show="backdate" x-cloak class="mt-2" @keydown.escape="calOpen = false">
                            <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('admin.club_members_index_bulk_start_date_label') }}</label>
                            <button type="button" @click="calOpen = !calOpen"
                                    class="w-full px-3 py-2.5 border rounded-xl text-sm bg-white text-start flex items-center gap-2 outline-none transition-colors"
                                    :class="calOpen ? 'ring-2 ring-purple-500 border-transparent rounded-b-none' : 'border-gray-200'">
                                <i class="bi bi-calendar-event text-gray-400 flex-shrink-0"></i>
                                <span class="flex-1 truncate" :class="startDate ? 'text-foreground' : 'text-gray-400'" x-text="startDate ? fmt(startDate) : '{{ __('admin.club_members_index_bulk_package_placeholder') }}'"></span>
                                <i class="bi bi-chevron-down text-gray-400 text-xs transition-transform flex-shrink-0" :class="calOpen ? 'rotate-180' : ''"></i>
                            </button>
                            {{-- Inline expanding panel (not an absolutely-positioned popover) — a floating
                                 popover here would get clipped by the modal's own scrollable/rounded bounds. --}}
                            <div x-show="calOpen" x-cloak
                                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                 class="w-full bg-white border border-t-0 border-gray-200 rounded-b-xl shadow-inner overflow-hidden p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <button type="button" @click="prev()" class="w-8 h-8 rounded-lg grid place-items-center text-muted-foreground hover:bg-muted/60 transition-colors"><i class="bi bi-chevron-left text-sm"></i></button>
                                    <p class="text-sm font-bold text-foreground" x-text="months[view.m] + ' ' + view.y"></p>
                                    <button type="button" @click="next()" class="w-8 h-8 rounded-lg grid place-items-center text-muted-foreground hover:bg-muted/60 transition-colors"><i class="bi bi-chevron-right text-sm"></i></button>
                                </div>
                                <div class="grid grid-cols-7 gap-1 mb-1">
                                    <template x-for="dw in dows" :key="dw"><span class="text-[10px] font-bold text-muted-foreground text-center py-1" x-text="dw"></span></template>
                                </div>
                                <div class="grid grid-cols-7 gap-1">
                                    <template x-for="(d, i) in grid" :key="i">
                                        <button type="button" :disabled="!d || isFuture(d)"
                                                @click="if (d && !isFuture(d)) { startDate = iso(d); calOpen = false }"
                                                class="h-9 rounded-lg text-sm grid place-items-center transition-colors"
                                                :class="!d ? 'invisible' : (iso(d)===startDate ? 'bg-primary text-white font-bold' : (isFuture(d) ? 'text-gray-300 cursor-not-allowed' : (isToday(d) ? 'text-primary font-bold ring-1 ring-primary/40 hover:bg-muted/60' : 'text-foreground hover:bg-muted/60')))"
                                                x-text="d"></button>
                                    </template>
                                </div>
                                <div class="flex items-center justify-between mt-2 pt-2 border-t border-gray-100">
                                    <button type="button" @click="startDate=''; calOpen=false" class="text-[11px] font-semibold text-muted-foreground hover:text-foreground transition-colors">{{ __('admin.club_members_index_bulk_cal_clear') }}</button>
                                    <button type="button" @click="const t=new Date(); view={y:t.getFullYear(),m:t.getMonth()}; startDate=todayIso(); calOpen=false" class="text-[11px] font-semibold text-primary">{{ __('admin.club_members_index_bulk_cal_today') }}</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400" id="bulkEnrollTargetCount"></p>
                </div>
                <div class="px-6 py-4 border-t border-gray-100 flex gap-3 rounded-b-2xl">
                    <button type="button" onclick="closeModal('bulkEnrollModal')" :disabled="submitting" class="flex-1 btn btn-outline-light">{{ __('shared.cancel') }}</button>
                    <button type="button" @click="submit()" :disabled="!packageId || submitting" class="flex-1 btn btn-primary">
                        <span x-show="!submitting">{{ __('admin.club_members_index_bulk_submit') }}</span>
                        <span x-show="submitting">{{ __('admin.club_members_index_bulk_submitting') }}</span>
                    </button>
                </div>
            </div>
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
                                <span class="text-white font-bold">{{ mb_strtoupper(mb_substr($request->user->full_name ?? '?', 0, 1, 'UTF-8'), 'UTF-8') }}</span>
                            </div>
                            @endif
                            <div>
                                <h6 class="font-bold text-gray-900">{{ $request->user->full_name ?? __('admin.club_members_index_unknown') }}</h6>
                                <p class="text-sm text-gray-500 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    {{ __('admin.club_members_index_requested_on', ['date' => $request->created_at->format('M d, Y')]) }}
                                </p>
                            </div>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">{{ __('admin.club_members_index_pending_badge') }}</span>
                    </div>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('admin.club_members_index_review_notes') }}</label>
                        <textarea class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none" rows="2" placeholder="{{ __('admin.club_members_index_review_notes_ph') }}" id="reviewNotes_{{ $request->id }}"></textarea>
                    </div>
                    <div class="flex gap-3">
                        <button onclick="approveRequest({{ $request->id }}, {{ $request->user_id }})" class="flex-1 btn btn-success">
                            <i class="bi bi-check-lg me-2"></i>{{ __('admin.club_members_index_approve') }}
                        </button>
                        <button onclick="rejectRequest({{ $request->id }})" class="flex-1 btn btn-danger">
                            <i class="bi bi-x-lg me-2"></i>{{ __('admin.club_members_index_reject') }}
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
            <h5 class="text-lg font-semibold text-gray-900 mb-2">{{ __('admin.club_members_index_no_requests_title') }}</h5>
            <p class="text-gray-500">{{ __('admin.club_members_index_no_requests_sub') }}</p>
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
                <h3 class="text-xl font-bold text-gray-900">{{ __('admin.club_members_index_add_existing_user') }}</h3>
                <p class="text-sm text-gray-500 mt-1">{{ __('admin.club_members_index_add_existing_sub') }}</p>
            </div>
            <div class="p-6 overflow-y-auto max-h-[60vh]">
                <div class="relative mb-6">
                    <svg class="absolute start-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" id="searchUserInput" placeholder="{{ __('admin.club_members_index_search_user_ph') }}" autocomplete="new-password" class="w-full ps-10 pe-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div id="searchResults" class="hidden">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">{{ __('admin.club_members_index_select_members') }}</label>
                    <div id="searchResultsList" class="space-y-3 max-h-96 overflow-y-auto"></div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                <button onclick="closeModal('addExistingUserModal')" class="btn btn-outline-light">{{ __('shared.cancel') }}</button>
                <button onclick="addSelectedMembers()" id="addMembersBtn" disabled class="btn btn-primary">
                    {{ __('admin.club_members_index_add') }} <span id="selectedCount">0</span> {{ __('admin.club_members_index_members_paren') }}
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
                <h3 class="text-xl font-bold text-gray-900">{{ __('admin.club_members_index_edit_title') }}</h3>
                <p class="text-sm text-gray-500 mt-1">{{ __('admin.club_members_index_update_details_for') }} <span id="editMemberName" class="font-medium">{{ __('admin.club_members_index_member_default') }}</span></p>
            </div>
            <div class="p-6">
                <input type="hidden" id="editMemberId">
                <div class="space-y-5">
                    <div>
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-2">
                            <span>&#127941;</span> {{ __('admin.club_members_index_rank_level') }}
                        </label>
                        <select id="editRank" class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-base">
                            <option value="Beginner">&#129353; {{ __('admin.club_members_index_rank_beginner') }}</option>
                            <option value="Member">&#128100; {{ __('admin.club_members_index_rank_member') }}</option>
                            <option value="Advanced">&#11088; {{ __('admin.club_members_index_rank_advanced') }}</option>
                            <option value="Elite">&#128142; {{ __('admin.club_members_index_rank_elite') }}</option>
                            <option value="Champion">&#127942; {{ __('admin.club_members_index_rank_champion') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-2">
                            <span>&#127942;</span> {{ __('admin.club_members_index_achievements_count') }}
                        </label>
                        <input type="number" id="editAchievements" min="0" class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent text-base" placeholder="{{ __('admin.club_members_index_achievements_ph') }}">
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                <div class="flex flex-wrap gap-3">
                    <button onclick="closeModal('editMemberModal')" class="btn btn-outline-light">{{ __('shared.cancel') }}</button>
                    <button onclick="openEnrollModal()" class="flex-1 btn btn-dark">
                        <i class="bi bi-person-plus me-2"></i>{{ __('admin.club_members_index_enroll') }}
                    </button>
                    <button onclick="openLeaveModal()" class="flex-1 btn btn-danger">
                        <i class="bi bi-box-arrow-right me-2"></i>{{ __('admin.club_members_index_leave') }}
                    </button>
                    <button onclick="saveEditMember()" class="flex-1 btn btn-primary">
                        <i class="bi bi-check-lg me-2"></i>{{ __('shared.save') }}
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
                <h3 class="text-xl font-bold text-gray-900">{{ __('admin.club_members_index_enroll_title') }}</h3>
                <p class="text-sm text-gray-500 mt-1">{{ __('admin.club_members_index_enroll_sub') }} <span id="enrollMemberName" class="font-medium">{{ __('admin.club_members_index_member_lc') }}</span></p>
            </div>
            <div class="p-6 overflow-y-auto max-h-[60vh]">
                <div id="enrollMemberInfo" class="hidden mb-6"></div>
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-gray-900">{{ __('admin.club_members_index_available_packages') }}</h4>
                        <span class="px-2.5 py-1 bg-gray-100 text-gray-600 text-sm rounded-full" id="packagesCount">0 packages</span>
                    </div>
                    <div id="enrollPackagesList" class="space-y-4"></div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                <button onclick="closeModal('enrollPackageModal')" class="flex-1 btn btn-outline-light">{{ __('shared.cancel') }}</button>
                <button onclick="confirmEnrollment()" id="confirmEnrollBtn" disabled class="flex-1 btn btn-primary">
                    <i class="bi bi-check-lg me-2"></i>{{ __('admin.club_members_index_confirm_enrollment') }}
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
                <h3 class="text-xl font-bold text-gray-900">{{ __('admin.club_members_index_confirm_leave_title') }}</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4">{{ __('admin.club_members_index_leave_confirm_pre') }}<strong id="leaveMemberName">{{ __('admin.club_members_index_this_member') }}</strong>{{ __('admin.club_members_index_leave_confirm_post') }}</p>
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">{{ __('admin.club_members_index_leave_reason') }}</label>
                    <textarea id="leaveReason" rows="3" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none" placeholder="{{ __('admin.club_members_index_leave_reason_ph') }}"></textarea>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <p class="text-sm font-semibold text-gray-700 mb-2">{{ __('admin.club_members_index_what_happens') }}</p>
                    <ul class="text-sm text-gray-500 space-y-1 list-disc list-inside">
                        <li>{{ __('admin.club_members_index_leave_bullet1') }}</li>
                        <li>{{ __('admin.club_members_index_leave_bullet2') }}</li>
                        <li>{{ __('admin.club_members_index_leave_bullet3') }}</li>
                    </ul>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                <button onclick="closeModal('leaveClubModal')" class="flex-1 btn btn-outline-light">{{ __('shared.cancel') }}</button>
                <button onclick="confirmLeave()" class="flex-1 btn btn-danger">{{ __('admin.club_members_index_confirm_leave_btn') }}</button>
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
                <h3 class="text-xl font-bold text-gray-900">{{ __('admin.club_members_index_graduate_title') }}</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4"><strong id="graduateChildName">{{ __('admin.club_members_index_child_default') }}</strong>{{ __('admin.club_members_index_graduate_desc') }}</p>
                <input type="hidden" id="graduateChildId">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('admin.club_members_index_email') }} <span class="text-red-500">*</span></label>
                        <input type="email" id="graduateEmail" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="{{ __('admin.club_members_index_email_ph') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('admin.club_members_index_password') }} <span class="text-red-500">*</span></label>
                        <input type="password" id="graduatePassword" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="{{ __('admin.club_members_index_password_ph') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('admin.club_members_index_phone') }} <span class="text-red-500">*</span></label>
                        <div class="flex">
                            <select id="graduateCountryCode" class="px-3 py-2.5 border border-gray-200 rounded-s-lg bg-gray-50 text-sm">
                                <option value="+973">+973</option>
                                <option value="+966">+966</option>
                                <option value="+971">+971</option>
                            </select>
                            <input type="text" id="graduatePhone" class="flex-1 px-4 py-2.5 border-y border-e border-gray-200 rounded-e-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="{{ __('admin.club_members_index_phone_ph') }}">
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                <button onclick="closeModal('graduateChildModal')" class="flex-1 btn btn-outline-light">{{ __('shared.cancel') }}</button>
                <button onclick="confirmGraduate()" class="flex-1 btn btn-primary">{{ __('admin.club_members_index_graduate_btn') }}</button>
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
                <h3 class="text-xl font-bold text-gray-900">{{ __('admin.club_members_index_degrade_title') }}</h3>
            </div>
            <div class="p-6">
                <p class="text-gray-600 mb-4"><strong id="degradeMemberName">{{ __('admin.club_members_index_member_default') }}</strong>{{ __('admin.club_members_index_degrade_desc') }}</p>
                <input type="hidden" id="degradeMemberId">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('admin.club_members_index_parent_email_phone') }} <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <input type="text" id="parentSearchInput" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="{{ __('admin.club_members_index_parent_ph') }}" autocomplete="new-password">
                        <button onclick="searchParent()" class="btn btn-outline-primary">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
                <div id="parentSearchResults" class="hidden mb-4"></div>
                <div id="selectedParent" class="hidden mb-4 bg-purple-50 rounded-lg p-4"></div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                <button onclick="closeModal('degradeToChildModal')" class="flex-1 btn btn-outline-light">{{ __('shared.cancel') }}</button>
                <button onclick="confirmDegrade()" id="confirmDegradeBtn" disabled class="flex-1 btn btn-primary">{{ __('admin.club_members_index_degrade_btn') }}</button>
            </div>
        </div>
    </div>
</div>

@include('admin.club.members.partials.member-popup')

{{-- Import Members Modal --}}
<div id="importMembersModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">{{ __('admin.club_members_index_import_members') }}</h3>
                <p class="text-sm text-gray-500 mt-0.5">{{ __('admin.club_members_index_import_sub') }}</p>
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
                    <p class="text-sm font-medium text-gray-800">{{ __('admin.club_members_index_download_template') }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('admin.club_members_index_import_step1_hint') }}</p>
                    <a href="{{ route('admin.club.members.import-template', $club->slug) }}" data-no-shell
                       class="inline-flex items-center mt-2 px-3 py-1.5 text-xs font-medium text-purple-700 bg-white border border-purple-200 rounded-md hover:bg-purple-50 transition-colors">
                        <svg class="w-3.5 h-3.5 me-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        {{ __('admin.club_members_index_download_template_btn') }}
                    </a>
                </div>
            </div>

            {{-- Step 2: Upload --}}
            <div class="flex items-start gap-3">
                <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 font-bold text-sm">2</div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-800 mb-2">{{ __('admin.club_members_index_upload_filled') }}</p>
                    <form id="importMembersForm" enctype="multipart/form-data">
                        @csrf
                        <label for="import_file"
                               id="importDropZone"
                               class="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100 hover:border-purple-400 transition-colors">
                            <div id="importDropLabel" class="flex flex-col items-center text-center px-4">
                                <svg class="w-7 h-7 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                                <span class="text-sm text-gray-500">{{ __('admin.club_members_index_drop_hint') }}</span>
                                <span class="text-xs text-gray-400 mt-0.5">{{ __('admin.club_members_index_drop_formats') }}</span>
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
            <button onclick="closeImportModal()" class="btn btn-outline-light">{{ __('shared.cancel') }}</button>
            <button id="importSubmitBtn" onclick="submitImport()" disabled class="btn btn-primary">
                <svg id="importSpinner" class="hidden w-4 h-4 me-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                {{ __('admin.club_members_index_import_btn') }}
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

// --- Batch enroll: separate selection set from `selectedMembers` (used by the
// "Add Existing User" modal) so the two flows never clobber each other. ---
window._membersSelectMode = window._membersSelectMode || false;
window.bulkEnrollSelected = window.bulkEnrollSelected || new Set();

window.toggleMembersSelectMode = function toggleMembersSelectMode(force) {
    const on = typeof force === 'boolean' ? force : !window._membersSelectMode;
    window._membersSelectMode = on;
    document.querySelectorAll('.member-select-toggle-card').forEach(el => { el.style.display = on ? 'flex' : 'none'; });
    document.querySelectorAll('.member-select-toggle-cell').forEach(el => { el.style.display = on ? 'table-cell' : 'none'; });
    const bar = document.getElementById('membersBulkBar');
    if (bar) bar.classList.toggle('hidden', !on);
    const label = document.getElementById('membersSelectModeLabel');
    if (label) label.textContent = on ? '{{ __('admin.club_members_index_select_done') }}' : '{{ __('admin.club_members_index_select') }}';
    if (!on) window.clearBulkSelection();
};

window.clearBulkSelection = function clearBulkSelection() {
    window.bulkEnrollSelected.clear();
    document.querySelectorAll('.member-select-checkbox').forEach(cb => { cb.checked = false; });
    const selectAll = document.getElementById('membersSelectAllInput');
    if (selectAll) selectAll.checked = false;
    updateBulkBar();
};

window.toggleBulkMemberSelected = function toggleBulkMemberSelected(userId, checked) {
    if (checked) window.bulkEnrollSelected.add(userId); else window.bulkEnrollSelected.delete(userId);
    updateBulkBar();
};

window.toggleSelectAllBulkMembers = function toggleSelectAllBulkMembers(checked) {
    document.querySelectorAll('#membersGrid .member-item').forEach(item => {
        if (!memberFilterFn(item)) return;
        const id = parseInt(item.dataset.memberId, 10);
        const cb = item.closest('.member-select-wrap')?.querySelector('.member-select-checkbox');
        if (cb) cb.checked = checked;
        if (checked) window.bulkEnrollSelected.add(id); else window.bulkEnrollSelected.delete(id);
    });
    updateBulkBar();
};

function updateBulkBar() {
    const count = window.bulkEnrollSelected.size;
    const countEl = document.getElementById('membersBulkCount');
    if (countEl) countEl.textContent = '{{ __('admin.club_members_index_bulk_selected', ['count' => ':count']) }}'.replace(':count', count);
    const enrollBtn = document.getElementById('membersBulkEnrollBtn');
    if (enrollBtn) enrollBtn.disabled = count === 0;
}

window.openBulkEnrollModal = function openBulkEnrollModal() {
    if (window.bulkEnrollSelected.size === 0) return;
    const el = document.getElementById('bulkEnrollModal');
    if (window.Alpine && el) {
        const data = window.Alpine.$data(el);
        data.packageId = '';
        data.backdate = false;
        data.startDate = '';
        data.calOpen = false;
        const t = new Date();
        data.view = { y: t.getFullYear(), m: t.getMonth() };
    }
    const targetCount = document.getElementById('bulkEnrollTargetCount');
    if (targetCount) {
        targetCount.textContent = '{{ __('admin.club_members_index_bulk_selected', ['count' => ':count']) }}'.replace(':count', window.bulkEnrollSelected.size);
    }
    openModal('bulkEnrollModal');
};

function bulkEnrollModalData() {
    return {
        packageId: '',
        backdate: false,
        startDate: '',
        calOpen: false,
        submitting: false,
        view: { y: (new Date()).getFullYear(), m: (new Date()).getMonth() },
        months: ['January','February','March','April','May','June','July','August','September','October','November','December'],
        dows: ['Su','Mo','Tu','We','Th','Fr','Sa'],
        get grid() {
            const start = new Date(this.view.y, this.view.m, 1).getDay();
            const days  = new Date(this.view.y, this.view.m + 1, 0).getDate();
            const cells = [];
            for (let i = 0; i < start; i++) cells.push(null);
            for (let d = 1; d <= days; d++) cells.push(d);
            return cells;
        },
        iso(d) { return this.view.y + '-' + String(this.view.m + 1).padStart(2,'0') + '-' + String(d).padStart(2,'0'); },
        todayIso() { const t = new Date(); return t.getFullYear() + '-' + String(t.getMonth()+1).padStart(2,'0') + '-' + String(t.getDate()).padStart(2,'0'); },
        isFuture(d) { if (!d) return false; const t = new Date(); t.setHours(0,0,0,0); return new Date(this.view.y, this.view.m, d) > t; },
        isToday(d) { const t = new Date(); return d && this.view.y===t.getFullYear() && this.view.m===t.getMonth() && d===t.getDate(); },
        prev() { this.view = this.view.m === 0 ? { y: this.view.y - 1, m: 11 } : { y: this.view.y, m: this.view.m - 1 }; },
        next() { this.view = this.view.m === 11 ? { y: this.view.y + 1, m: 0 } : { y: this.view.y, m: this.view.m + 1 }; },
        fmt(val) { if (!val) return ''; const d = new Date(val + 'T00:00:00'); return d.toLocaleDateString(undefined, { weekday:'short', day:'numeric', month:'short', year:'numeric' }); },
        async submit() {
            if (!this.packageId || window.bulkEnrollSelected.size === 0 || this.submitting) return;
            this.submitting = true;
            try {
                const res = await fetch('{{ route('admin.club.members.enroll-batch', $club->slug) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        member_ids: Array.from(window.bulkEnrollSelected),
                        package_id: this.packageId,
                        start_date: this.backdate && this.startDate ? this.startDate : null,
                    }),
                });
                const data = await res.json();
                if (data.success) {
                    window.showToast('success', data.message);
                    closeModal('bulkEnrollModal');
                    window.clearBulkSelection();
                    window.reloadMemberCards();
                } else {
                    window.showToast('error', data.message || '{{ __('admin.club_members_index_js_err_enrolling') }}');
                }
            } catch (e) {
                window.showToast('error', '{{ __('admin.club_members_index_js_err_enrolling') }}');
            } finally {
                this.submitting = false;
            }
        },
    };
}

// Member card / table row click → quick-view popup.
// Delegated on document (not #membersGrid/#membersTableBody) so it survives the
// admin-shell SPA nav swapping those elements out, and guarded so it's only bound once
// even if this script re-runs after a shell navigation.
if (!window.__membersCardClickBound) {
    window.__membersCardClickBound = true;
    document.addEventListener('click', function(e) {
        const wrapper = e.target.closest('.member-card-wrapper[data-member-id]');
        if (!wrapper) return;
        if (!wrapper.closest('#membersGrid') && !wrapper.closest('#membersTableBody')) return;
        e.preventDefault();
        e.stopPropagation();
        const userId   = wrapper.getAttribute('data-member-id');
        const popupUrl = wrapper.getAttribute('data-popup-url');
        if (userId && popupUrl && window.openMemberPopup) {
            window.openMemberPopup(userId, popupUrl);
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
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
let _formerLoaded  = false;

// The active filter is read from #membersGrid's data-filter attribute (not a
// top-level const) because the admin-shell SPA nav swaps <main> in place without
// re-running already-declared top-level scripts — a const baked with the old
// page's filter value would go stale (or throw "already declared") on every tab
// switch. Reading the live DOM keeps this correct across every swap.
function currentMembersFilter() {
    return document.getElementById('membersGrid')?.dataset.filter || 'active';
}

function loadMemberCards() {
    const grid  = document.getElementById('membersGrid');
    const empty = document.getElementById('membersEmpty');
    if (!grid) return;

    fetch(_cardsUrl + '?filter=' + currentMembersFilter())
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
            if (window._membersView === 'table') window.setMembersView('table');
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

    return fetch(_cardsUrl + '?filter=' + currentMembersFilter())
        .then(r => r.text())
        .then(html => {
            grid.querySelectorAll('.member-select-wrap, .member-item, .members-skeleton').forEach(el => el.remove());
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
            if (window._membersView === 'table') window.setMembersView('table');
        })
        .catch(() => {});
};

function loadFormerCards() {
    if (_formerLoaded) return;
    _formerLoaded = true;
    const wrap = document.getElementById('formerCardsWrap');
    if (!wrap) return;
    wrap.innerHTML = '<div class="py-16 text-center text-gray-400"><i class="bi bi-hourglass-split text-3xl animate-pulse block mb-2"></i><p class="text-sm">{{ __('admin.club_members_index_js_loading') }}</p></div>';
    fetch(_cardsUrl + '?filter=former')
        .then(r => r.text())
        .then(html => { wrap.innerHTML = html; })
        .catch(() => { wrap.innerHTML = '<p class="text-center text-red-500 py-8">{{ __('admin.club_members_index_js_load_former_failed') }}</p>'; });
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
            resultsList.innerHTML = '<div class="text-center py-8 text-gray-500"><svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg><p>{{ __('admin.club_members_index_js_no_users') }}</p></div>';
            resultsContainer.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Search error:', error);
        window.showToast('error', '{{ __('admin.club_members_index_js_err_search_users') }}');
    } finally {}
}

function createUserCard(user, isDependent = false) {
    const card = document.createElement('div');
    const isMale = user.gender === 'Male';

    card.className = `search-result-card p-4 border-2 rounded-xl cursor-pointer transition-all ${user.is_member ? 'border-gray-200 bg-gray-50 opacity-60' : 'border-gray-200 hover:border-purple-400'} ${isDependent ? 'ms-8' : ''}`;
    card.dataset.userId = user.id;

    const initial = (user.name || 'U').charAt(0).toUpperCase();
    const gradientColor = isMale ? 'hsl(250 65% 65%), hsl(250 65% 60%)' : '#d63384, #a61e4d';

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
        guardianInfo = `<span class="text-xs text-blue-600">{{ __('admin.club_members_index_js_guardian') }} ${user.guardian_name}</span>`;
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
                ${isDependent ? `<span class="absolute -top-1 -end-1 ${badgeColor} text-white text-xs px-1.5 py-0.5 rounded-full">${user.relationship_type || '{{ __('admin.club_members_index_js_family') }}'}</span>` : ''}
            </div>
            <div class="flex-1 min-w-0">
                <h6 class="font-semibold text-gray-900 truncate">${user.name}</h6>
                <p class="text-sm text-gray-500">${contactInfo || '{{ __('admin.club_members_index_js_no_contact') }}'}</p>
                <div class="flex items-center gap-2">
                    ${user.age ? `<span class="text-xs text-gray-400">${user.age} {{ __('admin.club_members_index_js_years_old') }}</span>` : ''}
                    ${guardianInfo}
                </div>
            </div>
            <div class="flex-shrink-0">
                ${user.is_member
                    ? '<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">{{ __('admin.club_members_index_js_already_member') }}</span>'
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
    btn.innerHTML = '{{ __('admin.club_members_index_js_adding') }}';

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
            if (data.count > 0) {
                // Added members have no package yet, so they're "Not Active" and would be
                // hidden by the default Active filter. Switch to "All" so they're visible.
                window.showToast('success', (data.message || '{{ __('admin.club_members_index_js_members_added') }}') + '{{ __('admin.club_members_index_js_no_package_note') }}');
                window.location.href = `/admin/club/${clubId}/members?filter=all`;
            } else {
                window.showToast('info', data.message || '{{ __('admin.club_members_index_js_already_members_club') }}');
            }
        } else {
            window.showToast('error', data.message || '{{ __('admin.club_members_index_js_err_adding') }}');
        }
    } catch (error) {
        console.error('Error:', error);
        window.showToast('error', '{{ __('admin.club_members_index_js_err_adding_retry') }}');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '{{ __('admin.club_members_index_add') }} <span id="selectedCount">0</span> {{ __('admin.club_members_index_members_paren') }}';
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
        filterFn: memberFilterFn,
    });
    window._pagers['membersPagination'] = membersPaginator;
    membersPaginator.refresh();
}

// Shared filter predicate — used by both the cards paginator and the table view.
function memberFilterFn(item) {
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
}

function applyFilters() {
    membersPaginator?.refresh();
    if (window._membersView === 'table') renderMembersTable();
}

// --- Cards / Table view switching ---
window._membersView = localStorage.getItem('clubMembersView') || 'cards';

window.setMembersView = function setMembersView(view) {
    window._membersView = view;
    const gridWrap  = document.getElementById('membersGridWrap');
    const tableWrap = document.getElementById('membersTableWrap');
    if (!gridWrap || !tableWrap) return;
    if (view === 'table') {
        gridWrap.classList.add('hidden');
        tableWrap.classList.remove('hidden');
        renderMembersTable();
    } else {
        tableWrap.classList.add('hidden');
        gridWrap.classList.remove('hidden');
    }
};

// Build the table rows from the currently-loaded cards, applying the active filters.
function renderMembersTable() {
    const tbody = document.getElementById('membersTableBody');
    const empty = document.getElementById('membersTableEmpty');
    if (!tbody) return;
    const cards = Array.from(document.querySelectorAll('#membersGrid .member-item')).filter(memberFilterFn);
    tbody.innerHTML = '';

    cards.forEach(card => {
        const d        = card.dataset;
        const name     = d.memberName || d.name || '{{ __('admin.club_members_index_unknown') }}';
        const phone    = d.memberPhone || '';
        const email    = card.dataset.email || '';
        const gender   = d.memberGender || '';
        const isMale   = gender.toLowerCase() === 'male';
        const age      = d.age && d.age !== '' ? d.age + ' {{ __('admin.club_members_index_js_yrs') }}' : '—';
        const nat      = d.memberNationality || '';
        const hasEnr   = d.hasEnrollment === '1';
        const img      = card.querySelector('img');
        const avatar   = img
            ? `<img src="${img.src}" alt="" class="w-full h-full object-cover">`
            : (name.charAt(0).toUpperCase());

        const tr = document.createElement('tr');
        tr.className = 'member-card-wrapper hover:bg-gray-50 transition-colors cursor-pointer';
        tr.dataset.memberId = d.memberId || '';
        tr.dataset.popupUrl = d.popupUrl || '';
        tr.innerHTML = `
            <td class="px-4 py-3 member-select-toggle-cell" style="display:${window._membersSelectMode ? 'table-cell' : 'none'};">
                <input type="checkbox" class="member-select-checkbox w-4 h-4 rounded border-gray-300 text-primary focus:ring-primary focus:ring-offset-0"
                       data-member-id="${d.memberId || ''}" onclick="event.stopPropagation()"
                       ${window.bulkEnrollSelected && window.bulkEnrollSelected.has(parseInt(d.memberId, 10)) ? 'checked' : ''}
                       onchange="window.toggleBulkMemberSelected(${d.memberId || 0}, this.checked)">
            </td>
            <td class="px-4 py-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="w-9 h-9 rounded-full overflow-hidden shrink-0 grid place-items-center text-white font-bold text-xs"
                          style="background:${isMale ? 'hsl(250 60% 60%)' : '#d63384'};">${avatar}</span>
                    <span class="font-semibold text-gray-900 truncate">${name}</span>
                </div>
            </td>
            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">${phone || '—'}</td>
            <td class="px-4 py-3">
                <span class="inline-flex items-center gap-1 ${isMale ? 'text-purple-600' : 'text-pink-600'}">
                    <i class="bi ${isMale ? 'bi-gender-male' : 'bi-gender-female'}"></i>${gender || '—'}
                </span>
            </td>
            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">${age}</td>
            <td class="px-4 py-3 text-gray-500"><span class="nationality-display" data-iso3="${nat}">${nat || '—'}</span></td>
            <td class="px-4 py-3">
                <span class="px-2.5 py-1 rounded-full text-xs font-medium ${hasEnr ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}">
                    ${hasEnr ? '{{ __('admin.club_members_index_filter_active') }}' : '{{ __('admin.club_members_index_js_no_package') }}'}
                </span>
            </td>`;
        tbody.appendChild(tr);
    });

    if (empty) empty.classList.toggle('hidden', cards.length > 0);
    loadNationalityFlags();
}

// Edit Member
function openEditModal(member) {
    currentEditingMember = member;
    document.getElementById('editMemberId').value = member.id;
    document.getElementById('editMemberName').textContent = member.user?.full_name || member.name || '{{ __('admin.club_members_index_member_default') }}';
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
        if (res.ok) { window.showToast('success', '{{ __('admin.club_members_index_js_member_updated') }}'); closeModal('editMemberModal'); window.reloadMemberCards(); }
        else throw new Error();
    } catch { window.showToast('error', '{{ __('admin.club_members_index_js_err_updating') }}'); }
}

// Enroll
function openEnrollModal() {
    if (!currentEditingMember) return;
    document.getElementById('enrollMemberName').textContent = currentEditingMember.user?.full_name || '{{ __('admin.club_members_index_member_lc') }}';
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
        container.innerHTML = '<div class="text-center py-8 text-gray-500">{{ __('admin.club_members_index_js_no_packages') }}</div>';
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
                        ${pkg.is_popular ? '<span class="px-2 py-0.5 bg-purple-500 text-white text-xs rounded-full">{{ __('admin.club_members_index_js_popular') }}</span>' : ''}
                    </div>
                    ${pkg.description ? `<p class="text-sm text-gray-500 mb-3">${pkg.description}</p>` : ''}
                    <div class="flex gap-6 text-sm">
                        <div><span class="text-gray-400">{{ __('admin.club_members_index_js_price') }}</span> <span class="font-bold text-purple-600">${pkg.currency || 'BHD'} ${parseFloat(pkg.price).toFixed(2)}</span></div>
                        <div><span class="text-gray-400">{{ __('admin.club_members_index_js_duration') }}</span> <span class="font-semibold">${pkg.duration_days} {{ __('admin.club_members_index_js_days') }}</span></div>
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
        if (res.ok) { window.showToast('success', '{{ __('admin.club_members_index_js_member_enrolled') }}'); closeModal('enrollPackageModal'); window.reloadMemberCards(); }
        else throw new Error();
    } catch { window.showToast('error', '{{ __('admin.club_members_index_js_err_enrolling') }}'); }
}

// Leave
function openLeaveModal() {
    if (!currentEditingMember) return;
    document.getElementById('leaveMemberName').textContent = currentEditingMember.user?.full_name || '{{ __('admin.club_members_index_this_member') }}';
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
        if (res.ok) { window.showToast('success', '{{ __('admin.club_members_index_js_member_left') }}'); closeModal('leaveClubModal'); window.reloadMemberCards(); }
        else throw new Error();
    } catch { window.showToast('error', '{{ __('admin.club_members_index_js_err_leave') }}'); }
}

// Graduate
function openGraduateModal(member) {
    currentEditingMember = member;
    document.getElementById('graduateChildId').value = member.id;
    document.getElementById('graduateChildName').textContent = member.user?.full_name || '{{ __('admin.club_members_index_child_default') }}';
    openModal('graduateChildModal');
}

async function confirmGraduate() {
    const email = document.getElementById('graduateEmail').value;
    const password = document.getElementById('graduatePassword').value;
    const phone = document.getElementById('graduatePhone').value;
    if (!email || !password || !phone) { window.showToast('warning', '{{ __('admin.club_members_index_js_fill_all') }}'); return; }
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${currentEditingMember.id}/graduate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ email, password, phone, country_code: document.getElementById('graduateCountryCode').value })
        });
        if (res.ok) { window.showToast('success', '{{ __('admin.club_members_index_js_child_graduated') }}'); closeModal('graduateChildModal'); window.reloadMemberCards(); }
        else throw new Error();
    } catch { window.showToast('error', '{{ __('admin.club_members_index_js_err_graduate') }}'); }
}

// Degrade
function openDegradateModal(member) {
    currentEditingMember = member;
    document.getElementById('degradeMemberId').value = member.id;
    document.getElementById('degradeMemberName').textContent = member.user?.full_name || '{{ __('admin.club_members_index_member_default') }}';
    document.getElementById('parentSearchInput').value = '';
    document.getElementById('parentSearchResults').classList.add('hidden');
    document.getElementById('selectedParent').classList.add('hidden');
    selectedParentId = null;
    document.getElementById('confirmDegradeBtn').disabled = true;
    openModal('degradeToChildModal');
}

async function searchParent() {
    const q = document.getElementById('parentSearchInput').value.trim();
    if (!q) { window.showToast('warning', '{{ __('admin.club_members_index_js_enter_parent') }}'); return; }
    try {
        const res = await fetch(`/api/users/search?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (data.success && data.data.length) displayParentResults(data.data);
        else window.showToast('warning', '{{ __('admin.club_members_index_js_no_parent') }}');
    } catch { window.showToast('error', '{{ __('admin.club_members_index_js_err_searching') }}'); }
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
        if (res.ok) { window.showToast('success', '{{ __('admin.club_members_index_js_moved_child') }}'); closeModal('degradeToChildModal'); window.reloadMemberCards(); }
        else throw new Error();
    } catch { window.showToast('error', '{{ __('admin.club_members_index_js_err_moving') }}'); }
}

async function deleteMember(id) {
    const ok = await window.confirmAction({ title: '{{ __('admin.club_members_index_js_delete_title') }}', message: '{{ __('admin.club_members_index_js_delete_msg') }}', type: 'danger', confirmText: '{{ __('shared.delete') }}' });
    if (!ok) return;
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
        if (res.ok) {
            window.showToast('success', '{{ __('admin.club_members_index_js_member_deleted') }}');
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
    } catch { window.showToast('error', '{{ __('admin.club_members_index_js_err_deleting') }}'); }
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
        <span class="text-sm text-gray-500">{{ __('admin.club_members_index_drop_hint') }}</span>
        <span class="text-xs text-gray-400 mt-0.5">{{ __('admin.club_members_index_drop_formats') }}</span>`;
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
        result.textContent = '{{ __('admin.club_members_index_js_upload_failed') }}';
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
@endsection
