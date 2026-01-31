<!-- User Picker Modal -->
<div class="modal fade" id="userPickerModal" tabindex="-1" aria-labelledby="userPickerModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 1rem; border: none;">
            <!-- Modal Header -->
            <div class="modal-header border-0">
                <div>
                    <h5 class="modal-title fw-bold" id="userPickerModalLabel">Select Club Owner</h5>
                    <p class="text-muted small mb-0">Search and select a user to be the club owner</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <!-- Search Input -->
                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text"
                               class="form-control"
                               id="userSearchInput"
                               placeholder="Search by name, email, or phone..."
                               autocomplete="off">
                    </div>
                </div>

                <!-- Loading State -->
                <div id="userPickerLoading" class="text-center py-5" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="text-muted mt-2">Searching users...</p>
                </div>

                <!-- Users List -->
                <div id="userPickerResults" style="max-height: 400px; overflow-y: auto;">
                    <!-- Results will be populated here -->
                </div>

                <!-- No Results -->
                <div id="userPickerNoResults" class="text-center py-5" style="display: none;">
                    <i class="bi bi-person-x fs-1 text-muted mb-3"></i>
                    <p class="text-muted mb-0">No users found</p>
                    <small class="text-muted">Try a different search term</small>
                </div>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
    (function() {
        const userPickerModal = document.getElementById('userPickerModal');
        if (!userPickerModal) return;

        const searchInput = document.getElementById('userSearchInput');
        const resultsContainer = document.getElementById('userPickerResults');
        const loadingDiv = document.getElementById('userPickerLoading');
        const noResultsDiv = document.getElementById('userPickerNoResults');

        let searchTimeout;
        let allUsers = [];

        // Prevent club modal from closing when user picker opens
        userPickerModal.addEventListener('show.bs.modal', function() {
            const clubModal = document.getElementById('clubModal');
            if (clubModal) {
                clubModal.style.display = 'block';
            }
        });

        // Load all users when modal opens
        userPickerModal.addEventListener('shown.bs.modal', function() {
            searchInput.value = '';
            searchInput.focus();
            loadUsers();
        });

        // Ensure club modal stays visible when user picker closes
        userPickerModal.addEventListener('hidden.bs.modal', function() {
            const clubModal = document.getElementById('clubModal');
            if (clubModal && clubModal.classList.contains('show')) {
                // Keep club modal visible
                document.body.classList.add('modal-open');
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.style.display = 'block';
                }
            }
        });

        // Search with debounce
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                filterUsers(this.value);
            }, 300);
        });

        // Load users from server
        async function loadUsers() {
            showLoading();

            try {
                const response = await fetch('/admin/api/users', {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    allUsers = await response.json();
                    displayUsers(allUsers);
                } else {
                    showError('Failed to load users');
                }
            } catch (error) {
                console.error('Error loading users:', error);
                showError('An error occurred while loading users');
            }
        }

        // Filter users based on search term
        function filterUsers(searchTerm) {
            if (!searchTerm.trim()) {
                displayUsers(allUsers);
                return;
            }

            const term = searchTerm.toLowerCase();
            const filtered = allUsers.filter(user => {
                return (
                    user.full_name.toLowerCase().includes(term) ||
                    user.email.toLowerCase().includes(term) ||
                    (user.mobile && user.mobile.toLowerCase().includes(term))
                );
            });

            displayUsers(filtered);
        }

        // Display users in the list
        function displayUsers(users) {
            hideLoading();

            if (users.length === 0) {
                resultsContainer.style.display = 'none';
                noResultsDiv.style.display = 'block';
                return;
            }

            resultsContainer.style.display = 'block';
            noResultsDiv.style.display = 'none';

            resultsContainer.innerHTML = users.map(user => `
                <div class="user-card border rounded p-3 mb-2"
                     style="cursor: pointer; transition: all 0.2s;"
                     data-user-id="${user.id}"
                     data-user-name="${user.full_name}"
                     data-user-email="${user.email}"
                     data-user-mobile="${user.mobile || ''}"
                     data-user-picture="${user.profile_picture || ''}"
                     onmouseover="this.style.backgroundColor='hsl(var(--muted) / 0.5)'; this.style.transform='translateX(4px)';"
                     onmouseout="this.style.backgroundColor=''; this.style.transform='';">
                    <div class="d-flex align-items-center gap-3">
                        ${user.profile_picture ? `
                            <img src="${user.profile_picture}"
                                 alt="${user.full_name}"
                                 class="rounded-circle"
                                 style="width: 50px; height: 50px; object-fit: cover;">
                        ` : `
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                                 style="width: 50px; height: 50px; font-size: 1.25rem; font-weight: 600;">
                                ${user.full_name.charAt(0).toUpperCase()}
                            </div>
                        `}
                        <div class="flex-grow-1">
                            <div class="fw-semibold">${user.full_name}</div>
                            <div class="small text-muted">
                                <i class="bi bi-envelope me-1"></i>${user.email}
                                ${user.mobile ? `<span class="ms-2"><i class="bi bi-phone me-1"></i>${user.mobile}</span>` : ''}
                            </div>
                        </div>
                        <div>
                            <i class="bi bi-check-circle text-primary" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>
                </div>
            `).join('');

            // Attach click handlers
            resultsContainer.querySelectorAll('.user-card').forEach(card => {
                card.addEventListener('click', function() {
                    selectUser({
                        id: this.dataset.userId,
                        name: this.dataset.userName,
                        email: this.dataset.userEmail,
                        mobile: this.dataset.userMobile,
                        picture: this.dataset.userPicture
                    });
                });
            });
        }

        // Select a user
        function selectUser(user) {
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
                    <div class="d-flex align-items-center gap-3">
                        ${user.picture ? `
                            <img src="${user.picture}"
                                 alt="${user.name}"
                                 class="rounded-circle"
                                 style="width: 50px; height: 50px; object-fit: cover;">
                        ` : `
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                                 style="width: 50px; height: 50px; font-size: 1.25rem; font-weight: 600;">
                                ${user.name.charAt(0).toUpperCase()}
                            </div>
                        `}
                        <div class="flex-grow-1">
                            <div class="fw-semibold">${user.name}</div>
                            <div class="small text-muted">
                                <i class="bi bi-envelope me-1"></i>${user.email}
                                ${user.mobile ? `<span class="ms-2"><i class="bi bi-phone me-1"></i>${user.mobile}</span>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            }

            // Close modal
            bootstrap.Modal.getInstance(userPickerModal).hide();
        }

        // Show loading state
        function showLoading() {
            loadingDiv.style.display = 'block';
            resultsContainer.style.display = 'none';
            noResultsDiv.style.display = 'none';
        }

        // Hide loading state
        function hideLoading() {
            loadingDiv.style.display = 'none';
        }

        // Show error
        function showError(message) {
            hideLoading();
            resultsContainer.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>${message}
                </div>
            `;
            resultsContainer.style.display = 'block';
        }
    })();
</script>
@endpush
@endonce
