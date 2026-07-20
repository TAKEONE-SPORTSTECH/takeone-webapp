@push('styles')
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""/>

{{-- Styles moved to app.css (Phase 6) --}}
@endpush

@push('scripts')
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""></script>

<script>
function exploreApp() {
    return {
        mapModalOpen: false,
        showAlert: false,
        map: null,
        userMarker: null,
        userLocation: null,
        watchId: null,
        currentCategory: 'all',
        allClubs: [],
        allTrainers: @json($instructors),
        countriesData: [],

        // Join Club Modal
        joinModal: {
            open: false,
            step: 'select-members', // 'select-members' | 'package-selection' | 'payment-review'
            clubId: null,
            clubSlug: '',
            clubName: '',
            currency: 'USD',
            enrollmentFee: 0,
            registrationFee: 0,
            ownedMap: {},
            familyMembers: @json($familyMembers),
            selectedMemberIds: [],
            registrants: [],
            packages: [],
            loadingPackages: false,
            payLater: false,
            paymentScreenshot: false,
            addChildOpen: false,
            newChild: { name: '', dateOfBirth: '', gender: 'Male', nationality: '', bloodType: '' },
            submitting: false,
            vatPercentage: 0,
            vatRegNumber: null,

            show(clubId, clubSlug, clubName, clubCountry) {
                this.open = true;
                this.step = 'select-members';
                this.clubId = clubId;
                this.clubSlug = clubSlug;
                this.clubName = clubName;
                this.clubCountry = clubCountry || 'bh';
                this.selectedMemberIds = [];
                this.registrants = [];
                this.packages = [];
                this.payLater = false;
                this.paymentScreenshot = false;
                this.submitting = false;
                window._joinModal = this;
                document.body.style.overflow = 'hidden';
                this.fetchClubPackages(clubSlug);
            },

            close() {
                this.open = false;
                document.body.style.overflow = '';
            },

            async fetchClubPackages(clubSlug) {
                this.loadingPackages = true;
                try {
                    const response = await fetch(`/${this.clubCountry}/clubs/${clubSlug}/packages-json`, {
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content }
                    });
                    if (response.ok) {
                        const data = await response.json();
                        this.packages = data.packages || [];
                        this.currency = data.currency || 'USD';
                        this.enrollmentFee = Number(data.enrollment_fee || 0);
                        this.registrationFee = Number(data.registration_fee || 0);
                        this.ownedMap = data.owned || {};
                        this.vatPercentage = Number(data.vat_percentage || 0);
                        this.vatRegNumber = data.vat_reg_number || null;
                    }
                } catch (error) {
                    console.error('Error fetching packages:', error);
                } finally {
                    this.loadingPackages = false;
                }
            },

            toggleMember(member) {
                const idx = this.selectedMemberIds.indexOf(member.id);
                if (idx === -1) {
                    this.selectedMemberIds.push(member.id);
                } else {
                    this.selectedMemberIds.splice(idx, 1);
                }
            },

            isMemberSelected(memberId) {
                return this.selectedMemberIds.includes(memberId);
            },

            buildRegistrants() {
                this.registrants = this.familyMembers
                    .filter(m => this.selectedMemberIds.includes(m.id))
                    .map(m => ({
                        id: this._uid(),
                        userId: m.id,
                        type: m.type === 'guardian' ? 'self' : 'child',
                        packageId: '',
                        name: m.name,
                        gender: m.gender || '',
                        dateOfBirth: m.birthdate || '',
                        avatarUrl: m.profile_picture ? '/storage/' + m.profile_picture : null,
                        relationship: m.relationship,
                        isMember: m.is_member || false,
                        equipment: []
                    }));
            },

            // Build each registrant's equipment offering from their chosen package's
            // catalog. Required gear is ticked by default unless the member already
            // owns it (pre-unticked from the ownership map).
            buildEquipment() {
                this.registrants = this.registrants.map(reg => {
                    const pkg = this.getPackageForRegistrant(reg);
                    const ownedFor = this.ownedMap[reg.userId] || { products: [], variants: [] };
                    const items = (pkg?.equipment || []).map(e => {
                        const owned = e.has_variants
                            ? false
                            : (e.already_owned || (ownedFor.products || []).includes(e.product_id));
                        return {
                            id: e.id,
                            product_id: e.product_id,
                            name: e.name,
                            price: Number(e.price || 0),
                            image: e.image || null,
                            is_required: !!e.is_required,
                            has_variants: !!e.has_variants,
                            variants: e.variants || [],
                            owned: owned,
                            variantId: '',
                            selected: !!e.is_required && !owned,
                        };
                    });
                    return { ...reg, equipment: items };
                });
            },

            hasAnyEquipment() {
                return this.registrants.some(r => (r.equipment || []).length > 0);
            },

            toggleEquip(reg, item) {
                item.selected = !item.selected;
            },

            setEquipVariant(item, variantId) {
                item.variantId = variantId;
                item.selected = true;   // choosing a variant implies buying it
            },

            equipItemPrice(item) {
                if (item.has_variants && item.variantId) {
                    const v = (item.variants || []).find(x => x.id == item.variantId);
                    return v ? Number(v.price || 0) : 0;
                }
                return Number(item.price || 0);
            },

            equipmentTotal() {
                let total = 0;
                this.registrants.forEach(reg => {
                    (reg.equipment || []).forEach(item => {
                        if (item.selected) total += this.equipItemPrice(item);
                    });
                });
                return total;
            },

            addChild() {
                this.newChild = { name: '', dateOfBirth: '', gender: 'Male', nationality: '', bloodType: '' };
                this.addChildOpen = true;
            },

            confirmAddChild() {
                if (!this.newChild.name || !this.newChild.dateOfBirth) return;
                const newMember = {
                    id: 'new_' + this._uid(),
                    name: this.newChild.name,
                    gender: this.newChild.gender,
                    birthdate: this.newChild.dateOfBirth,
                    age: this.calculateAge(this.newChild.dateOfBirth),
                    profile_picture: null,
                    type: 'dependent',
                    relationship: 'New',
                    nationality: this.newChild.nationality,
                    bloodType: this.newChild.bloodType,
                    isNew: true
                };
                this.familyMembers.push(newMember);
                this.selectedMemberIds.push(newMember.id);
                this.addChildOpen = false;
            },

            selectPackage(registrantId, packageId) {
                this.registrants = this.registrants.map(r => {
                    if (r.id === registrantId) {
                        return { ...r, packageId: r.packageId == packageId ? '' : packageId };
                    }
                    return r;
                });
            },

            getPackageForRegistrant(reg) {
                return this.packages.find(p => p.id == reg.packageId) || null;
            },

            formatCurrency(amount) {
                try {
                    const hasDecimals = amount % 1 !== 0;
                    return new Intl.NumberFormat('en-US', {
                        style: 'currency',
                        currency: this.currency,
                        minimumFractionDigits: hasDecimals ? 2 : 0,
                        maximumFractionDigits: hasDecimals ? 2 : 0
                    }).format(amount);
                } catch {
                    return amount + ' ' + this.currency;
                }
            },

            calculateTotal() {
                const subtotal = parseFloat(this.calculateSubtotal());
                const vat = parseFloat(this.calculateVat());
                return (subtotal + vat).toFixed(2);
            },

            // Dynamic step model — equipment is only a step when there's gear to show.
            _steps() {
                const s = ['select-members'];
                if (!this._preselectPackageId) s.push('package-selection');
                if (this.hasAnyEquipment()) s.push('equipment');
                s.push('payment-review');
                return s;
            },
            stepIndex() { const i = this._steps().indexOf(this.step); return i < 0 ? 1 : i + 1; },
            stepCount() { return this._steps().length; },

            goBack() {
                if (this.step === 'select-members') {
                    this.close();
                } else if (this.step === 'package-selection') {
                    this.step = 'select-members';
                } else if (this.step === 'equipment') {
                    this.step = 'package-selection';
                } else if (this.step === 'payment-review') {
                    this.step = this.hasAnyEquipment() ? 'equipment' : 'package-selection';
                }
            },

            goNext() {
                if (this.step === 'select-members') {
                    if (this.selectedMemberIds.length === 0) {
                        Toast.warning('{{ __("explore.no_members_selected_title") }}', '{{ __("explore.no_members_selected_body") }}');
                        return;
                    }
                    this.buildRegistrants();
                    this.step = 'package-selection';
                } else if (this.step === 'package-selection') {
                    const missing = this.registrants.filter(r => !r.packageId);
                    if (missing.length > 0) {
                        Toast.warning('{{ __("explore.packages_required_title") }}', '{{ __("explore.packages_required_body") }}');
                        return;
                    }
                    this.buildEquipment();
                    this.step = this.hasAnyEquipment() ? 'equipment' : 'payment-review';
                } else if (this.step === 'equipment') {
                    const missingVariant = this.registrants.some(r =>
                        (r.equipment || []).some(i => i.selected && i.has_variants && !i.variantId));
                    if (missingVariant) {
                        Toast.warning('{{ __("explore.choose_option_title") }}', '{{ __("explore.choose_option_body") }}');
                        return;
                    }
                    this.step = 'payment-review';
                } else if (this.step === 'payment-review') {
                    this.handleSubmit();
                }
            },

            async handleSubmit() {
                if (!this.payLater && !this.paymentScreenshot) {
                    Toast.warning('{{ __("explore.payment_required_title") }}', '{{ __("explore.payment_required_body") }}');
                    return;
                }
                if (this.submitting) return;
                this.submitting = true;

                try {
                    const formData = new FormData();
                    formData.append('club_id', this.clubId);
                    formData.append('pay_later', this.payLater ? '1' : '0');

                    this.registrants.forEach((reg, i) => {
                        formData.append(`registrants[${i}][type]`, reg.type);
                        formData.append(`registrants[${i}][name]`, reg.name);
                        formData.append(`registrants[${i}][user_id]`, reg.userId || '');
                        formData.append(`registrants[${i}][package_id]`, reg.packageId);
                        formData.append(`registrants[${i}][gender]`, reg.gender || '');
                        if (reg.dateOfBirth) formData.append(`registrants[${i}][date_of_birth]`, reg.dateOfBirth);

                        (reg.equipment || []).forEach(item => {
                            if (item.selected) {
                                formData.append(`registrants[${i}][equipment][]`, item.id);
                                if (item.has_variants && item.variantId) {
                                    formData.append(`registrants[${i}][variants][${item.id}]`, item.variantId);
                                }
                            } else if (item.is_required && !item.owned) {
                                // Required gear the member says they already have.
                                formData.append(`registrants[${i}][owned_equipment][]`, item.id);
                            }
                        });
                    });

                    if (!this.payLater) {
                        const proofBase64 = document.getElementById('hiddenInput_joinPaymentProofCropper')?.value;
                        if (proofBase64) {
                            formData.append('payment_proof_base64', proofBase64);
                        }
                    }

                    const response = await fetch(`/${this.clubCountry}/clubs/join`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    const data = await response.json();
                    if (data.success) {
                        this.close();
                        Toast.success('{{ __("explore.registration_submitted_title") }}', '{{ __("explore.registration_submitted_body") }}');
                    } else {
                        Toast.error('{{ __("explore.registration_failed_title") }}', data.message || '{{ __("explore.please_try_again") }}');
                    }
                } catch (error) {
                    console.error('Registration error:', error);
                    Toast.error('{{ __("shared.error") }}', '{{ __("explore.registration_error_body") }}');
                } finally {
                    this.submitting = false;
                }
            },

            getEligiblePackages(reg) {
                const age = this.calculateAge(reg.dateOfBirth);
                return this.packages.filter(pkg => {
                    // Age filter
                    if (pkg.age_min !== null && pkg.age_min !== undefined && age !== null) {
                        if (age < pkg.age_min) return false;
                    }
                    if (pkg.age_max !== null && pkg.age_max !== undefined && age !== null) {
                        if (age > pkg.age_max) return false;
                    }
                    // Gender filter
                    if (pkg.gender && pkg.gender !== 'mixed' && reg.gender) {
                        if (pkg.gender === 'male' && reg.gender !== 'Male') return false;
                        if (pkg.gender === 'female' && reg.gender !== 'Female') return false;
                    }
                    return true;
                });
            },

            calculateAge(dateOfBirth) {
                if (!dateOfBirth) return 0;
                const today = new Date();
                const birth = new Date(dateOfBirth);
                let age = today.getFullYear() - birth.getFullYear();
                const m = today.getMonth() - birth.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
                return age;
            },

            // One-time registration fee for a registrant: the package override,
            // else the club registration fee, else the legacy enrollment fee.
            registrationFeeFor(reg) {
                const pkg = this.getPackageForRegistrant(reg);
                const override = pkg && pkg.registration_fee != null ? Number(pkg.registration_fee) : null;
                let fee = override != null ? override : Number(this.registrationFee || 0);
                if (fee <= 0) fee = Number(this.enrollmentFee || 0);
                return fee;
            },

            registrationTotal() {
                return this.registrants.reduce((sum, reg) =>
                    sum + (reg.isMember ? 0 : this.registrationFeeFor(reg)), 0);
            },

            calculateSubtotal() {
                let packageTotal = this.registrants.reduce((sum, reg) => {
                    const pkg = this.packages.find(p => p.id == reg.packageId);
                    return sum + Number(pkg?.price || 0);
                }, 0);
                return (packageTotal + this.registrationTotal() + this.equipmentTotal()).toFixed(2);
            },

            calculateVat() {
                if (!this.vatPercentage || this.vatPercentage <= 0) return '0.00';
                const subtotal = parseFloat(this.calculateSubtotal());
                return (subtotal * this.vatPercentage / 100).toFixed(2);
            },

            firstTimerCount() {
                return this.registrants.filter(r => !r.isMember).length;
            },

            firstTimerNames() {
                return this.registrants.filter(r => !r.isMember).map(r => r.name).join(', ');
            },

            genderLabel(g) {


                return g ? g.charAt(0).toUpperCase() + g.slice(1) : '';
            },

            _uid() {
                return Math.random().toString(36).substr(2, 9);
            }
        },

        init() {
            // Expose join modal opener globally so dynamically rendered buttons can use it
            const self = this;
            window.openJoinModal = function(clubId, clubSlug, clubName, clubCountry) {
                self.joinModal.show(clubId, clubSlug, clubName, clubCountry);
            };

            // Load countries from JSON file
            fetch('/data/countries.json')
                .then(response => response.json())
                .then(countries => {
                    this.countriesData = countries;
                })
                .catch(error => console.error('Error loading countries:', error));

            // Always load clubs immediately, then refine with location if available
            this.fetchAllClubs();

            if (!navigator.geolocation) {
                this.showAlertMessage('{{ __("explore.geolocation_not_supported") }}', 'danger');
            } else {
                this.startWatchingLocation();
            }

            // Category buttons
            document.querySelectorAll('.category-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    document.querySelectorAll('.category-btn').forEach(b => {
                        b.classList.remove('active', 'btn-primary');
                        b.classList.add('btn-outline-primary');
                    });
                    e.target.classList.remove('btn-outline-primary');
                    e.target.classList.add('active', 'btn-primary');

                    this.currentCategory = e.target.dataset.category;

                    if (this.currentCategory === 'all' || this.currentCategory === 'sports-clubs') {
                        this.fetchAllClubs();
                    } else if (this.userLocation) {
                        this.fetchNearbyClubs(this.userLocation.latitude, this.userLocation.longitude);
                    } else {
                        this.fetchAllClubs();
                    }
                });
            });

            // Search input
            document.getElementById('searchInput').addEventListener('input', () => {
                this.filterClubs();
            });
        },

        openMapModal() {
            this.mapModalOpen = true;
            document.body.style.overflow = 'hidden';

            this.$nextTick(() => {
                setTimeout(() => {
                    if (this.userLocation) {
                        this.initMap(this.userLocation.latitude, this.userLocation.longitude);
                        this.updateModalLocation(this.userLocation.latitude, this.userLocation.longitude);
                    } else {
                        this.initMap(25.276987, 55.296249);
                        this.updateModalLocation(25.276987, 55.296249);
                    }
                }, 100);
            });
        },

        closeMapModal() {
            this.mapModalOpen = false;
            document.body.style.overflow = '';
            if (this.map) {
                this.map.remove();
                this.map = null;
            }
        },

        applyLocation() {
            this.closeMapModal();
            if (this.userLocation) {
                this.updateLocationDisplay(this.userLocation.latitude, this.userLocation.longitude);
                if (this.currentCategory === 'all') {
                    this.fetchAllClubs();
                } else {
                    this.fetchNearbyClubs(this.userLocation.latitude, this.userLocation.longitude);
                }
            }
        },

        startWatchingLocation() {
            this.watchId = navigator.geolocation.watchPosition(
                (position) => {
                    this.userLocation = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude
                    };

                    this.updateLocationDisplay(this.userLocation.latitude, this.userLocation.longitude);
                    this.fetchAllClubs();

                    if (this.watchId) {
                        navigator.geolocation.clearWatch(this.watchId);
                        this.watchId = null;
                    }
                },
                (error) => {
                    let errorMessage = '{{ __("explore.unable_to_get_location") }}';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage += '{{ __("explore.location_access_denied") }}';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage += '{{ __("explore.location_unavailable") }}';
                            break;
                        case error.TIMEOUT:
                            errorMessage += '{{ __("explore.location_request_timed_out") }}';
                            break;
                    }
                    this.showAlertMessage(errorMessage, 'warning');
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        },

        updateLocationDisplay(lat, lng) {
            const el = document.getElementById('currentLocation');
            if (!el) return;
            el.innerHTML = `<i class="bi bi-geo-alt-fill me-1"></i>${lat.toFixed(4)}, ${lng.toFixed(4)}`;
        },

        // 5-star strip shown opposite the distance line on a club card.
        // Half-stars are rounded to the nearest 0.5.
        renderStars(rating, reviewsCount) {
            const value = Math.round((Number(rating) || 0) * 2) / 2;

            let stars = '';
            for (let i = 1; i <= 5; i++) {
                if (value >= i) {
                    stars += '<i class="bi bi-star-fill"></i>';
                } else if (value >= i - 0.5) {
                    stars += '<i class="bi bi-star-half"></i>';
                } else {
                    stars += '<i class="bi bi-star"></i>';
                }
            }

            const count = Number(reviewsCount) || 0;
            const label = count > 0 ? `<span class="text-muted-foreground text-xs">(${count})</span>` : '';

            return `<div class="flex items-center gap-1 shrink-0">
                        <span class="flex items-center gap-0.5 text-xs ${count > 0 ? 'text-warning' : 'text-muted-foreground'}">${stars}</span>
                        ${label}
                    </div>`;
        },

        // Single place where a map-picked point becomes the active location:
        // stops the geolocation watch so a late GPS fix can't overwrite the pick.
        setPickedLocation(latlng) {
            if (this.watchId) {
                navigator.geolocation.clearWatch(this.watchId);
                this.watchId = null;
            }
            this.userLocation = { latitude: latlng.lat, longitude: latlng.lng };
            this.updateLocationDisplay(latlng.lat, latlng.lng);
            this.updateModalLocation(latlng.lat, latlng.lng);
        },

        initMap(lat, lng) {
            if (this.map) {
                this.map.remove();
            }

            this.map = L.map('map', { attributionControl: false }).setView([lat, lng], 13);

            L.tileLayer('http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(this.map);

            this.userMarker = L.marker([lat, lng], {
                draggable: true,
                icon: L.divIcon({
                    className: 'user-location-marker',
                    html: '<i class="bi bi-geo-alt-fill pulse-marker" style="font-size: 36px; color: #667eea; filter: drop-shadow(0 3px 6px rgba(0,0,0,0.4));"></i>',
                    iconSize: [36, 36],
                    iconAnchor: [18, 36]
                })
            }).addTo(this.map);

            this.userMarker.on('drag', (event) => {
                this.setPickedLocation(event.target.getLatLng());
            });

            // Tapping the map moves the pin there — dragging a 36px marker is
            // fiddly on touch, so a plain tap is the primary mobile gesture.
            this.map.on('click', (event) => {
                this.userMarker.setLatLng(event.latlng);
                this.setPickedLocation(event.latlng);
            });

            setTimeout(() => this.map.invalidateSize(), 100);
        },

        fetchNearbyClubs(lat, lng) {
            document.getElementById('loadingSpinner').style.display = 'block';
            document.getElementById('clubsGrid').style.display = 'none';

            fetch(`{{ route('clubs.nearby') }}?latitude=${lat}&longitude=${lng}&radius=50`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingSpinner').style.display = 'none';
                document.getElementById('clubsGrid').style.display = 'flex';

                if (data.success) {
                    this.allClubs = data.clubs;
                    this.displayClubs(this.allClubs);
                } else {
                    this.showAlertMessage('{{ __("explore.failed_to_fetch_clubs") }}', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('loadingSpinner').style.display = 'none';
                this.showAlertMessage('{{ __("explore.error_fetching_clubs") }}', 'danger');
            });
        },

        fetchAllClubs() {
            document.getElementById('loadingSpinner').style.display = 'block';
            document.getElementById('clubsGrid').style.display = 'none';

            let url = `{{ route('clubs.all') }}`;
            if (this.userLocation) {
                url += `?latitude=${this.userLocation.latitude}&longitude=${this.userLocation.longitude}`;
            }

            fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingSpinner').style.display = 'none';
                document.getElementById('clubsGrid').style.display = 'flex';

                if (data.success) {
                    this.allClubs = data.clubs;
                    this.displayClubs(this.allClubs);
                } else {
                    this.showAlertMessage('{{ __("explore.failed_to_fetch_clubs") }}', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('loadingSpinner').style.display = 'none';
                this.showAlertMessage('{{ __("explore.error_fetching_clubs") }}', 'danger');
            });
        },

        displayClubs(clubs, trainers = null) {
            const container = document.getElementById('clubsContainer');
            const noResultsContainer = document.getElementById('noResultsContainer');

            container.innerHTML = '';

            let trainerAdded = false;

            // Add real trainer cards if category is 'all' or 'personal-trainers'
            if (this.currentCategory === 'all' || this.currentCategory === 'personal-trainers') {
                (trainers ?? this.allTrainers).forEach(trainer => {
                    const coverHtml = trainer.profile_picture
                        ? `<img src="/storage/${trainer.profile_picture}" alt="${trainer.name}" loading="lazy" class="w-full h-full object-cover transition-transform duration-300">`
                        : `<div class="w-full h-full flex items-center justify-center" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                               <i class="bi bi-person-fill text-white text-5xl opacity-50"></i>
                           </div>`;

                    const clubLine = trainer.club_name
                        ? `<div class="flex items-center text-muted-foreground text-sm">
                               <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 shrink-0">
                                   <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path>
                                   <circle cx="12" cy="10" r="3"></circle>
                               </svg>
                               <span class="truncate">${trainer.club_name}</span>
                           </div>`
                        : '';

                    const ratingDisplay = trainer.rating > 0 ? trainer.rating : '{{ __("explore.na") }}';
                    const starIcon = trainer.rating > 0
                        ? `<i class="bi bi-star-fill text-warning"></i>`
                        : `<i class="bi bi-star text-muted-foreground"></i>`;

                    const trainerCard = document.createElement('div');
                    trainerCard.innerHTML = `
                        <div class="card border border-gray-100 shadow-sm overflow-hidden club-card cursor-pointer rounded-2xl h-full flex flex-col" onclick="window.location.href='${trainer.url}'">
                            <!-- Cover Image -->
                            <div class="relative overflow-hidden bg-gray-50" style="aspect-ratio: 16 / 9;">
                                ${coverHtml}
                                <!-- Personal Trainer Badge -->
                                <div class="absolute top-2 start-2">
                                    <span class="badge text-white px-3 py-1 bg-destructive rounded-full text-xs font-semibold"><i class="bi bi-person-fill me-1"></i>{{ __('explore.personal_trainer_badge') }}</span>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="p-4 bg-white flex-1 flex flex-col">
                                <div class="mb-3" style="min-height:6.25rem;">
                                    <h3 class="font-semibold mb-2 club-title text-lg text-foreground" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:3.5rem;">${trainer.name}</h3>
                                    <div class="flex items-center mb-1 text-sm text-primary">
                                        <i class="bi bi-patch-check-fill me-1"></i>
                                        <span class="font-semibold">${trainer.role}</span>
                                    </div>
                                    ${clubLine}
                                </div>

                                <div class="grid grid-cols-3 gap-2 text-center mb-3 text-xs">
                                    <div class="p-2 rounded bg-primary/5">
                                        <i class="bi bi-calendar3 mb-1 text-muted-foreground text-base"></i>
                                        <p class="font-semibold mb-0 text-foreground">${trainer.experience_years}</p>
                                        <p class="text-muted-foreground mb-0">{{ __('explore.years_exp') }}</p>
                                    </div>
                                    <div class="p-2 rounded bg-primary/5">
                                        <i class="bi bi-chat-dots mb-1 text-muted-foreground text-base"></i>
                                        <p class="font-semibold mb-0 text-foreground">${trainer.reviews_count}</p>
                                        <p class="text-muted-foreground mb-0">{{ __('explore.reviews') }}</p>
                                    </div>
                                    <div class="p-2 rounded bg-primary/5">
                                        ${starIcon}
                                        <p class="font-semibold mb-0 text-foreground">${ratingDisplay}</p>
                                        <p class="text-muted-foreground mb-0">{{ __('explore.rating') }}</p>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex gap-2 mt-auto">
                                    <a href="${trainer.url}" class="btn btn-primary flex-1 font-semibold text-sm text-center" onclick="event.stopPropagation()">
                                        <i class="bi bi-calendar-plus me-1"></i>{{ __('explore.book_session') }}
                                    </a>
                                    <a href="${trainer.url}" class="btn btn-outline-primary flex-1 font-semibold text-sm text-center" onclick="event.stopPropagation()">
                                        {{ __('explore.view_details') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                    container.appendChild(trainerCard);
                    trainerAdded = true;
                });
            }

            if (clubs.length === 0 && !trainerAdded) {
                noResultsContainer.style.display = 'flex';
                return;
            }

            noResultsContainer.style.display = 'none';

            clubs.forEach(club => {
                const card = document.createElement('div');

                // Prepare cover image
                let coverImageHtml = '';
                if (club.cover_image) {
                    coverImageHtml = `<img src="/storage/${club.cover_image}" alt="${club.club_name}" loading="lazy" class="w-full h-full object-cover club-cover-img transition-transform duration-300">`;
                } else {
                    coverImageHtml = `<div class="w-full h-full flex items-center justify-center" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <i class="bi bi-image text-white text-5xl opacity-30"></i>
                    </div>`;
                }

                // Prepare logo
                let logoHtml = '';
                if (club.logo) {
                    logoHtml = `<img src="/storage/${club.logo}" alt="${club.club_name} logo" loading="lazy" class="w-full h-full rounded-full object-contain">`;
                } else {
                    logoHtml = `<div class="w-full h-full rounded-full bg-primary flex items-center justify-center">
                        <span class="text-white font-bold text-2xl">${club.club_name.charAt(0)}</span>
                    </div>`;
                }

                card.innerHTML = `
                    <div class="card border border-gray-100 shadow-sm overflow-hidden club-card cursor-pointer rounded-2xl h-full flex flex-col" onclick="window.location.href='${club.url}'">
                        <!-- Cover Image -->
                        <div class="relative overflow-hidden bg-gray-50" style="aspect-ratio: 16 / 9;">
                            ${coverImageHtml}

                            <!-- Club Logo - Bottom Left -->
                            <div class="absolute bottom-2 start-2">
                                <div class="bg-white border border-gray-200 p-0.5 w-20 h-20 rounded-full">
                                    ${logoHtml}
                                </div>
                            </div>

                            <!-- Sports Club Badge - Top Left -->
                            <div class="absolute top-2 start-2">
                                <span class="badge text-white px-3 py-1 bg-red-600 rounded-full text-xs font-semibold"><i class="bi bi-building me-1"></i>{{ __('explore.sports_club_badge') }}</span>
                            </div>
                        </div>

                        <!-- Card Body -->
                        <div class="p-4 bg-white flex-1 flex flex-col">
                            <div class="mb-3" style="min-height:6.25rem;">
                                <h3 class="font-semibold mb-2 club-title text-lg text-foreground" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:3.5rem;">${club.club_name}</h3>
                                <div class="flex items-center justify-between gap-2 mb-1 text-sm">
                                    <div class="flex items-center min-w-0 text-primary">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 shrink-0">
                                            <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path>
                                            <circle cx="12" cy="10" r="3"></circle>
                                        </svg>
                                        <span class="font-semibold truncate">${club.distance ? club.distance + ' {{ __("explore.km_away") }}' : '{{ __("explore.location_available") }}'}</span>
                                    </div>
                                    ${this.renderStars(club.rating, club.reviews_count)}
                                </div>
                                <div class="flex items-center text-muted-foreground text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 shrink-0">
                                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    <span class="truncate">${club.owner_name || '{{ __("explore.na") }}'}</span>
                                </div>
                            </div>

                            <!-- Stats Grid -->
                            <div class="grid grid-cols-3 gap-2 text-center mb-3 text-xs">
                                <div class="p-2 rounded bg-primary/5">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1 text-primary">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                    <p class="font-semibold mb-0 text-foreground">${club.members_count ?? 0}</p>
                                    <p class="text-muted-foreground mb-0">{{ __('explore.members') }}</p>
                                </div>
                                <div class="p-2 rounded bg-primary/5">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1 text-primary">
                                        <path d="M14.4 14.4 9.6 9.6"></path>
                                        <path d="M18.657 21.485a2 2 0 1 1-2.829-2.828l-1.767 1.768a2 2 0 1 1-2.829-2.829l6.364-6.364a2 2 0 1 1 2.829 2.829l-1.768 1.767a2 2 0 1 1 2.828 2.829z"></path>
                                        <path d="m21.5 21.5-1.4-1.4"></path>
                                        <path d="M3.9 3.9 2.5 2.5"></path>
                                        <path d="M6.404 12.768a2 2 0 1 1-2.829-2.829l1.768-1.767a2 2 0 1 1-2.828-2.829l2.828-2.828a2 2 0 1 1 2.829 2.828l1.767-1.768a2 2 0 1 1 2.829 2.829z"></path>
                                    </svg>
                                    <p class="font-semibold mb-0 text-foreground">${club.packages_count ?? 0}</p>
                                    <p class="text-muted-foreground mb-0">{{ __('explore.packages') }}</p>
                                </div>
                                <div class="p-2 rounded bg-primary/5">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mx-auto mb-1 text-primary">
                                        <path d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z"></path>
                                    </svg>
                                    <p class="font-semibold mb-0 text-foreground">${club.instructors_count ?? 0}</p>
                                    <p class="text-muted-foreground mb-0">{{ __('explore.trainers') }}</p>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-2 mt-auto">
                                <button class="btn btn-primary flex-1 font-semibold text-sm" onclick="event.stopPropagation(); event.preventDefault(); window.openJoinModal(${club.id}, '${club.slug}', '${club.club_name.replace(/'/g, "\\\\'")}', '${club.country_code}')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <line x1="19" x2="19" y1="8" y2="14"></line>
                                        <line x1="22" x2="16" y1="11" y2="11"></line>
                                    </svg>
                                    {{ __('explore.join_club') }}
                                </button>
                                <a href="${club.url}" class="btn btn-outline-primary flex-1 font-semibold text-sm text-center">{{ __('explore.view_details') }}</a>
                            </div>
                        </div>
                    </div>
                `;
                container.appendChild(card);
            });
        },

        filterClubs() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();

            let filtered = this.allClubs.filter(club => {
                const matchesSearch = club.club_name.toLowerCase().includes(searchTerm) ||
                                    (club.owner_name && club.owner_name.toLowerCase().includes(searchTerm));
                return matchesSearch;
            });

            // Trainers must honour the search term too, otherwise they stay
            // pinned above the (shrinking) club list on every keystroke.
            const trainers = this.allTrainers.filter(trainer => {
                return trainer.name.toLowerCase().includes(searchTerm) ||
                       (trainer.club_name && trainer.club_name.toLowerCase().includes(searchTerm));
            });

            this.displayClubs(filtered, trainers);
        },

        showAlertMessage(message, type = 'danger') {
            // Route through the global toast — never render an inline alert on the page.
            window.showToast(type === 'danger' ? 'error' : type, message);
        },

        async reverseGeocode(lat, lng) {
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18`);
                const data = await response.json();
                return data.address || null;
            } catch (error) {
                console.error('Reverse geocoding error:', error);
                return null;
            }
        },

        getCountryInfo(address) {
            if (!address || !this.countriesData.length) return null;
            const iso2 = address.country_code?.toUpperCase();
            if (!iso2) return null;
            const country = this.countriesData.find(c => c.iso2 === iso2);
            if (country) {
                const flag = iso2.split('').map(char => String.fromCodePoint(127397 + char.charCodeAt(0))).join('');
                return { flag, name: country.name, iso3: country.iso3 };
            }
            return null;
        },

        async updateModalLocation(lat, lng) {
            const address = await this.reverseGeocode(lat, lng);
            const info = this.getCountryInfo(address);
            const coords = `Latitude: ${lat.toFixed(6)}, Longitude: ${lng.toFixed(6)}`;
            const area = address?.suburb || address?.town || address?.city || address?.state || address?.county || '';
            if (info) {
                document.getElementById('modalLocationCoordinates').innerHTML = `${info.name}${area ? ', ' + area : ''} - ${coords}`;
            } else {
                document.getElementById('modalLocationCoordinates').textContent = `${area ? area + ' - ' : ''}${coords}`;
            }
        }
    }
}
</script>
@endpush
