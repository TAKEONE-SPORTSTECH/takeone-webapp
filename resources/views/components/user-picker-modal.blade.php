<!-- User Picker Modal -->
<div x-data="userPickerModal()" x-cloak>
    <!-- Modal Backdrop -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/50 z-[60]">
    </div>

    <!-- Modal Content -->
    <div x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-[60] overflow-y-auto"
         @click.self="close()">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="w-full max-w-2xl bg-white rounded-2xl shadow-xl" @click.stop>
                <!-- Modal Header -->
                <div class="p-6 border-b border-gray-100">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">Select Club Owner</h3>
                            <p class="text-sm text-gray-500 mt-1">Search and select a user to be the club owner</p>
                        </div>
                        <button @click="close()" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                            <i class="bi bi-x-lg text-gray-500"></i>
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <div class="p-6">
                    <!-- Search Input -->
                    <div class="mb-4">
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text"
                                   x-model="searchTerm"
                                   @input.debounce.300ms="filterUsers()"
                                   x-ref="searchInput"
                                   class="w-full pl-12 pr-4 py-3 text-base border-2 border-primary/20 rounded-xl bg-white transition-all duration-300 focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
                                   placeholder="Search by name, email, or phone..."
                                   autocomplete="off">
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div x-show="loading" class="text-center py-12">
                        <div class="inline-block w-8 h-8 border-4 border-primary/30 border-t-primary rounded-full animate-spin"></div>
                        <p class="text-gray-500 mt-3">Searching users...</p>
                    </div>

                    <!-- Users List -->
                    <div x-show="!loading && filteredUsers.length > 0" class="max-h-96 overflow-y-auto space-y-2">
                        <template x-for="user in filteredUsers" :key="user.id">
                            <div @click="selectUser(user)"
                                 class="border border-gray-200 rounded-xl p-4 cursor-pointer transition-all duration-200 hover:bg-gray-50 hover:translate-x-1 hover:border-primary/30">
                                <div class="flex items-center gap-4">
                                    <!-- Avatar -->
                                    <template x-if="user.profile_picture">
                                        <img :src="user.profile_picture"
                                             :alt="user.full_name"
                                             class="w-12 h-12 rounded-full object-cover">
                                    </template>
                                    <template x-if="!user.profile_picture">
                                        <div class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center text-xl font-semibold"
                                             x-text="user.full_name.charAt(0).toUpperCase()">
                                        </div>
                                    </template>

                                    <!-- User Info -->
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-gray-900" x-text="user.full_name"></div>
                                        <div class="text-sm text-gray-500 truncate">
                                            <i class="bi bi-envelope mr-1"></i>
                                            <span x-text="user.email"></span>
                                            <template x-if="user.mobile">
                                                <span class="ml-2">
                                                    <i class="bi bi-phone mr-1"></i>
                                                    <span x-text="user.mobile"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Check Icon -->
                                    <div>
                                        <i class="bi bi-check-circle text-primary text-2xl"></i>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- No Results -->
                    <div x-show="!loading && filteredUsers.length === 0 && searchTerm" class="text-center py-12">
                        <i class="bi bi-person-x text-6xl text-gray-300"></i>
                        <p class="text-gray-500 mt-3">No users found</p>
                        <p class="text-sm text-gray-400">Try a different search term</p>
                    </div>

                    <!-- Error State -->
                    <div x-show="error" class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4">
                        <i class="bi bi-exclamation-triangle mr-2"></i>
                        <span x-text="error"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function userPickerModal() {
    return {
        open: false,
        loading: false,
        error: null,
        searchTerm: '',
        allUsers: [],
        filteredUsers: [],

        init() {
            // Global function to open modal
            window.openUserPickerModal = () => {
                this.open = true;
                this.loadUsers();
                this.$nextTick(() => {
                    this.$refs.searchInput?.focus();
                });
            };
        },

        close() {
            this.open = false;
            this.searchTerm = '';
            this.error = null;
        },

        async loadUsers() {
            this.loading = true;
            this.error = null;

            try {
                const response = await fetch('/admin/api/users', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    this.allUsers = await response.json();
                    this.filteredUsers = this.allUsers;
                } else {
                    this.error = 'Failed to load users';
                }
            } catch (err) {
                console.error('Error loading users:', err);
                this.error = 'An error occurred while loading users';
            } finally {
                this.loading = false;
            }
        },

        filterUsers() {
            if (!this.searchTerm.trim()) {
                this.filteredUsers = this.allUsers;
                return;
            }

            const term = this.searchTerm.toLowerCase();
            this.filteredUsers = this.allUsers.filter(user => {
                return (
                    user.full_name.toLowerCase().includes(term) ||
                    user.email.toLowerCase().includes(term) ||
                    (user.mobile && user.mobile.toLowerCase().includes(term))
                );
            });
        },

        selectUser(user) {
            // Update hidden input
            const ownerInput = document.getElementById('owner_user_id');
            if (ownerInput) {
                ownerInput.value = user.id;
                ownerInput.dispatchEvent(new Event('change'));
            }

            // Update display
            const ownerDisplay = document.getElementById('ownerDisplay');
            if (ownerDisplay) {
                ownerDisplay.innerHTML = `
                    <div class="flex items-center gap-4">
                        ${user.profile_picture ? `
                            <img src="${user.profile_picture}"
                                 alt="${user.full_name}"
                                 class="w-12 h-12 rounded-full object-cover">
                        ` : `
                            <div class="w-12 h-12 rounded-full bg-primary text-white flex items-center justify-center text-xl font-semibold">
                                ${user.full_name.charAt(0).toUpperCase()}
                            </div>
                        `}
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-gray-900">${user.full_name}</div>
                            <div class="text-sm text-gray-500">
                                <i class="bi bi-envelope mr-1"></i>${user.email}
                                ${user.mobile ? `<span class="ml-2"><i class="bi bi-phone mr-1"></i>${user.mobile}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }

            // Dispatch custom event for external listeners
            window.dispatchEvent(new CustomEvent('user-selected', { detail: user }));

            this.close();
        }
    }
}
</script>
