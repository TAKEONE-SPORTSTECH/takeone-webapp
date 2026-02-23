@extends('layouts.admin-club')


{{-- Styles moved to app.css (Phase 6) --}}

@section('club-admin-content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Members Management</h2>
            <p class="text-gray-500 mt-1">Manage club members and subscriptions</p>
        </div>
        <div class="flex gap-3 flex-wrap">
            <button onclick="openAddExistingUserModal()" class="inline-flex items-center px-4 py-2 border border-purple-500 text-purple-600 rounded-lg hover:bg-purple-50 transition-colors font-medium">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                Add Existing User
            </button>
            <button onclick="openWalkInModal()" class="inline-flex items-center px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors font-medium">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                Walk-In Registration
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <nav class="-mb-px flex gap-8">
            <button id="members-tab-btn" onclick="switchTab('members')" class="py-4 px-1 border-b-2 border-purple-500 text-purple-600 font-medium text-sm whitespace-nowrap">
                Current Members
                <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-purple-100 text-purple-600" id="membersCount">{{ $members->total() ?? 0 }}</span>
            </button>
            <button id="requests-tab-btn" onclick="switchTab('requests')" class="py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium text-sm whitespace-nowrap">
                Pending Requests
                <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700" id="requestsCount">{{ $pendingRequests ?? 0 }}</span>
            </button>
        </nav>
    </div>

    <!-- Members Tab Content -->
    <div id="members-content">
        <!-- Search & Filter -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
            <div class="flex flex-col lg:flex-row gap-4">
                <div class="flex-1">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <input type="text" id="searchMembers" placeholder="Search members by name or rank..." class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-2">
                    <span class="text-sm font-medium text-gray-600 self-center">Status:</span>
                    <div class="inline-flex flex-wrap rounded-lg border border-gray-200 p-1 bg-gray-50 overflow-x-auto max-w-full">
                        <button type="button" class="status-btn active px-3 py-1.5 text-sm font-medium rounded-md transition-colors" data-status="active">
                            Active <span class="ml-1 text-xs opacity-75" id="activeCount">0</span>
                        </button>
                        <button type="button" class="status-btn px-3 py-1.5 text-sm font-medium rounded-md text-gray-600 hover:bg-gray-100 transition-colors" data-status="not_active">
                            Not Active <span class="ml-1 text-xs opacity-75" id="notActiveCount">0</span>
                        </button>
                        <button type="button" class="status-btn px-3 py-1.5 text-sm font-medium rounded-md text-gray-600 hover:bg-gray-100 transition-colors" data-status="all">
                            All <span class="ml-1 text-xs opacity-75" id="allCount">0</span>
                        </button>
                        <button type="button" class="status-btn px-3 py-1.5 text-sm font-medium rounded-md text-gray-600 hover:bg-gray-100 transition-colors" data-status="former">
                            Former <span class="ml-1 text-xs opacity-75" id="formerCount">0</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Members Grid -->
        @if(isset($members) && count($members) > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="membersGrid">
            @foreach($members as $member)
            @php
                $user = $member->user;
                $guardian = $user->guardians->first()?->guardian;
                $footerLabel = $member->status === 'inactive' ? 'INACTIVE' : ($member->status === 'active' ? 'ACTIVE MEMBER' : 'CLUB MEMBER');
            @endphp
            <x-member-card
                :member="$user"
                :guardian="$guardian"
                :footerLabel="$footerLabel"
                footerStyle="translucent"
                :memberSince="$member->created_at"
                cardClass="member-card"
                class="member-item"
                data-name="{{ strtolower($user->full_name ?? '') }}"
                data-rank="member"
                data-status="{{ $member->status }}"
                data-has-enrollment="{{ $member->status === 'active' ? '1' : '0' }}"
            >
                <x-slot:badges>
                    <span class="badge bg-primary">Member</span>
                    @if($member->achievements > 0)
                        <span class="badge bg-warning text-dark">{{ $member->achievements }} &#127942;</span>
                    @endif
                </x-slot:badges>
            </x-member-card>
            @endforeach
        </div>

        <!-- Pagination -->
        @if($members instanceof \Illuminate\Pagination\LengthAwarePaginator && $members->hasPages())
        <div class="flex justify-center mt-8">
            {{ $members->links() }}
        </div>
        @endif
        @else
        <div class="tf-empty">
            <div class="tf-empty-icon">
                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            </div>
            <h5 class="text-lg font-semibold text-gray-900 mb-2">No members found</h5>
            <p class="text-gray-500 mb-4">Start adding members to your club</p>
            <button onclick="openWalkInModal()" class="inline-flex items-center px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 transition-colors font-medium">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Add Member
            </button>
        </div>
        @endif
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
                        <button onclick="approveRequest({{ $request->id }}, {{ $request->user_id }})" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 bg-green-500 text-white font-medium rounded-lg hover:bg-green-600 transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Approve
                        </button>
                        <button onclick="rejectRequest({{ $request->id }})" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 bg-red-500 text-white font-medium rounded-lg hover:bg-red-600 transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            Reject
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
                    <input type="text" id="searchUserInput" placeholder="Enter email or phone number..." class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div id="searchResults" class="hidden">
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Select members to add:</label>
                    <div id="searchResultsList" class="space-y-3 max-h-96 overflow-y-auto"></div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                <button onclick="closeModal('addExistingUserModal')" class="px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                <button onclick="addSelectedMembers()" id="addMembersBtn" disabled class="px-4 py-2.5 bg-purple-500 text-white font-medium rounded-lg hover:bg-purple-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
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
                    <button onclick="closeModal('editMemberModal')" class="px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-100 transition-colors">Cancel</button>
                    <button onclick="openEnrollModal()" class="flex-1 px-4 py-2.5 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 transition-colors">
                        <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                        Enroll
                    </button>
                    <button onclick="openLeaveModal()" class="flex-1 px-4 py-2.5 bg-red-500 text-white font-medium rounded-lg hover:bg-red-600 transition-colors">
                        <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        Leave
                    </button>
                    <button onclick="saveEditMember()" class="flex-1 px-4 py-2.5 bg-purple-500 text-white font-medium rounded-lg hover:bg-purple-600 transition-colors">
                        <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Save
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
                <button onclick="closeModal('enrollPackageModal')" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                <button onclick="confirmEnrollment()" id="confirmEnrollBtn" disabled class="flex-1 px-4 py-2.5 bg-purple-500 text-white font-medium rounded-lg hover:bg-purple-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Confirm Enrollment
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
                <button onclick="closeModal('leaveClubModal')" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                <button onclick="confirmLeave()" class="flex-1 px-4 py-2.5 bg-red-500 text-white font-medium rounded-lg hover:bg-red-600 transition-colors">Confirm Leave</button>
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
                <button onclick="closeModal('graduateChildModal')" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                <button onclick="confirmGraduate()" class="flex-1 px-4 py-2.5 bg-purple-500 text-white font-medium rounded-lg hover:bg-purple-600 transition-colors">Graduate to Adult</button>
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
                        <input type="text" id="parentSearchInput" class="flex-1 px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="parent@example.com">
                        <button onclick="searchParent()" class="px-4 py-2.5 border border-purple-500 text-purple-600 rounded-lg hover:bg-purple-50 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </button>
                    </div>
                </div>
                <div id="parentSearchResults" class="hidden mb-4"></div>
                <div id="selectedParent" class="hidden mb-4 bg-purple-50 rounded-lg p-4"></div>
            </div>
            <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                <button onclick="closeModal('degradeToChildModal')" class="flex-1 px-4 py-2.5 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                <button onclick="confirmDegrade()" id="confirmDegradeBtn" disabled class="flex-1 px-4 py-2.5 bg-purple-500 text-white font-medium rounded-lg hover:bg-purple-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">Move to Child</button>
            </div>
        </div>
    </div>
</div>
@endsection

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
    updateStatusCounts();
    initializeSearch();
    initializeStatusFilters();
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

                const country = countries.find(c => c.iso3 === iso3Code);
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

// Tab switching
function switchTab(tab) {
    const membersTab = document.getElementById('members-tab-btn');
    const requestsTab = document.getElementById('requests-tab-btn');
    const membersContent = document.getElementById('members-content');
    const requestsContent = document.getElementById('requests-content');

    if (tab === 'members') {
        membersTab.classList.add('border-purple-500', 'text-purple-600');
        membersTab.classList.remove('border-transparent', 'text-gray-500');
        requestsTab.classList.remove('border-purple-500', 'text-purple-600');
        requestsTab.classList.add('border-transparent', 'text-gray-500');
        membersContent.classList.remove('hidden');
        requestsContent.classList.add('hidden');
    } else {
        requestsTab.classList.add('border-purple-500', 'text-purple-600');
        requestsTab.classList.remove('border-transparent', 'text-gray-500');
        membersTab.classList.remove('border-purple-500', 'text-purple-600');
        membersTab.classList.add('border-transparent', 'text-gray-500');
        requestsContent.classList.remove('hidden');
        membersContent.classList.add('hidden');
    }
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
        alert('Error searching users. Please try again.');
    } finally {}
}

function createUserCard(user, isDependent = false) {
    const card = document.createElement('div');
    const isMale = user.gender === 'm';

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
            window.location.reload();
        } else {
            alert(data.message || 'Error adding members');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error adding members. Please try again.');
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

// Status Filters
function initializeStatusFilters() {
    document.querySelectorAll('.status-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.status-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            filterMembers();
        });
    });
}

function updateStatusCounts() {
    const items = document.querySelectorAll('.member-item');
    let active = 0, notActive = 0, all = 0, former = 0;
    items.forEach(item => {
        const status = item.dataset.status;
        const hasEnrollment = item.dataset.hasEnrollment === '1';
        if (status === 'inactive') former++;
        else if (status === 'active') {
            all++;
            if (hasEnrollment) active++; else notActive++;
        }
    });
    document.getElementById('activeCount').textContent = active;
    document.getElementById('notActiveCount').textContent = notActive;
    document.getElementById('allCount').textContent = all;
    document.getElementById('formerCount').textContent = former;
}

function initializeSearch() {
    const input = document.getElementById('searchMembers');
    if (input) input.addEventListener('input', filterMembers);
}

function filterMembers() {
    const query = document.getElementById('searchMembers').value.toLowerCase();
    const activeBtn = document.querySelector('.status-btn.active');
    const statusFilter = activeBtn ? activeBtn.dataset.status : 'active';
    document.querySelectorAll('.member-item').forEach(item => {
        const name = item.dataset.name;
        const rank = item.dataset.rank;
        const status = item.dataset.status;
        const hasEnrollment = item.dataset.hasEnrollment === '1';
        const matchesSearch = name.includes(query) || rank.includes(query);
        let matchesStatus = false;
        switch(statusFilter) {
            case 'active': matchesStatus = status === 'active' && hasEnrollment; break;
            case 'not_active': matchesStatus = status === 'active' && !hasEnrollment; break;
            case 'all': matchesStatus = status === 'active'; break;
            case 'former': matchesStatus = status === 'inactive'; break;
        }
        item.style.display = matchesSearch && matchesStatus ? '' : 'none';
    });
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
        if (res.ok) { showToast('Member updated', 'success'); closeModal('editMemberModal'); location.reload(); }
        else throw new Error();
    } catch { showToast('Error updating member', 'error'); }
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
        if (res.ok) { showToast('Member enrolled', 'success'); closeModal('enrollPackageModal'); location.reload(); }
        else throw new Error();
    } catch { showToast('Error enrolling member', 'error'); }
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
        if (res.ok) { showToast('Member left club', 'success'); closeModal('leaveClubModal'); location.reload(); }
        else throw new Error();
    } catch { showToast('Error processing leave', 'error'); }
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
    if (!email || !password || !phone) { showToast('Fill all fields', 'warning'); return; }
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${currentEditingMember.id}/graduate`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ email, password, phone, country_code: document.getElementById('graduateCountryCode').value })
        });
        if (res.ok) { showToast('Child graduated', 'success'); closeModal('graduateChildModal'); location.reload(); }
        else throw new Error();
    } catch { showToast('Error graduating child', 'error'); }
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
    if (!q) { showToast('Enter parent email/phone', 'warning'); return; }
    try {
        const res = await fetch(`/api/users/search?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (data.success && data.data.length) displayParentResults(data.data);
        else showToast('No parent found', 'warning');
    } catch { showToast('Error searching', 'error'); }
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
        if (res.ok) { showToast('Moved to child', 'success'); closeModal('degradeToChildModal'); location.reload(); }
        else throw new Error();
    } catch { showToast('Error moving to child', 'error'); }
}

async function deleteMember(id) {
    if (!confirm('Delete this member?')) return;
    try {
        const res = await fetch(`/admin/club/${clubId}/members/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
        if (res.ok) { showToast('Member deleted', 'success'); location.reload(); }
        else throw new Error();
    } catch { showToast('Error deleting', 'error'); }
}

// Walk-In Registration is now handled by the registration-walkin component

function showToast(msg, type = 'info') {
    const colors = { success: 'bg-green-500', error: 'bg-red-500', warning: 'bg-yellow-500', info: 'bg-blue-500' };
    const toast = document.createElement('div');
    toast.className = `fixed top-4 right-4 z-[9999] px-6 py-3 rounded-lg text-white font-medium shadow-lg ${colors[type]} animate-fade-in`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>
@endpush
