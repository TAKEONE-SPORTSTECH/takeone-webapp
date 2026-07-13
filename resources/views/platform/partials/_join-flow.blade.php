{{-- Self-contained package-first join/enrol flow. Include once on any club page
     that needs the "Select Package" → register modal. Requires $club + $familyMembers
     in scope. Exposes window function openSelectPackageModal(packageId). --}}
@php
    // Equipment catalog per package (computed on a separate collection so the
    // schedule/instructor mapping below keeps its own relations intact).
    $joinCostSvc = app(\App\Services\RegistrationCostService::class);
    $eqPackages  = \App\Models\ClubPackage::where('tenant_id', $club->id)->get();
    $joinCostSvc->attachEquipmentToPackages($eqPackages, $club->id, null);
    $equipmentByPkg = $eqPackages->pluck('equipment', 'id');

    $joinOwnedMap = [];
    if (auth()->check()) {
        $joinMemberIds = collect([auth()->id()])
            ->merge(\App\Models\UserRelationship::where('guardian_user_id', auth()->id())->pluck('dependent_user_id'))
            ->unique();
        foreach ($joinMemberIds as $mid) {
            $joinOwnedMap[$mid] = [
                'products' => $joinCostSvc->ownedProductIds($club->id, $mid),
                'variants' => $joinCostSvc->ownedVariantIds($club->id, $mid),
            ];
        }
    }

    $packagesForJs = $club->packages->map(function ($p) use ($equipmentByPkg) {
        return [
            'id'              => $p->id,
            'name'            => $p->name,
            'price'           => $p->price,
            'registration_fee'=> $p->registration_fee,
            'equipment'       => $equipmentByPkg[$p->id] ?? [],
            'duration_months' => $p->duration_months,
            'age_min'         => $p->age_min,
            'age_max'         => $p->age_max,
            'gender'          => $p->gender,
            'activity_type'   => $p->activity_type ?? null,
            'schedules'       => collect($p->packageActivities)->map(function ($pa) {
                $slots = is_string($pa->schedule) ? json_decode($pa->schedule, true) : $pa->schedule;
                if (!is_array($slots) || empty($slots)) return null;
                $days  = collect($slots)->pluck('day')->map(fn($d) => ucfirst($d))->unique()->join(', ');
                $times = collect($slots)->map(fn($s) => ($s['start_time'] ?? '') . ' – ' . ($s['end_time'] ?? ''))->first();
                return ['days' => $days, 'time' => $times];
            })->filter()->values(),
            'instructors'     => collect($p->packageActivities)->map(function ($pa) {
                if (!$pa->instructor?->user) return null;
                return [
                    'name'      => $pa->instructor->user->full_name ?? $pa->instructor->user->name,
                    'image_url' => $pa->instructor->user->profile_picture ? asset('storage/' . $pa->instructor->user->profile_picture) : null,
                ];
            })->filter()->unique('name')->values(),
        ];
    })->values();
@endphp

<div id="selectPackageModalWrap" x-data="selectPackageApp()" x-cloak>
    @include('platform.partials.join-club-modal-mobile')
    <x-toast-notification />
</div>

@push('scripts')
<script>
const _clubPackagesData = @json($packagesForJs);

function selectPackageApp() {
    return {
        joinModal: {
            open: false,
            step: 'select-members',
            clubId: {{ $club->id }},
            clubSlug: '{{ $club->slug }}',
            clubName: @json($club->club_name),
            clubCountry: '{{ $club->country_code ?? "bh" }}',
            currency: @json($club->currency ?? 'BHD'),
            enrollmentFee: {{ $club->enrollment_fee ?? 0 }},
            registrationFee: {{ $club->registration_fee ?? 0 }},
            ownedMap: @json($joinOwnedMap),
            vatPercentage: {{ $club->vat_percentage ?? 0 }},
            vatRegNumber: @json($club->vat_reg_number ?? null),
            familyMembers: @json($familyMembers),
            _allFamilyMembers: @json($familyMembers),
            selectedMemberIds: [],
            registrants: [],
            packages: _clubPackagesData,
            loadingPackages: false,
            payLater: false,
            paymentScreenshot: false,
            submitting: false,
            _preselectPackageId: null,

            show(clubId, clubSlug, clubName, clubCountry) {
                this._preselectPackageId = null;
                this.open = true; this.step = 'select-members';
                this.selectedMemberIds = []; this.registrants = [];
                this.payLater = false; this.paymentScreenshot = false; this.submitting = false;
                window._joinModal = this; document.body.style.overflow = 'hidden';
            },

            showForPackage(packageId) {
                const pkg = this.packages.find(p => p.id == packageId);
                if (!pkg) return;
                const eligible = this._allFamilyMembers.filter(m => {
                    const age = this.calculateAge(m.birthdate);
                    if (pkg.age_min !== null && pkg.age_min !== undefined && age !== null && age < pkg.age_min) return false;
                    if (pkg.age_max !== null && pkg.age_max !== undefined && age !== null && age > pkg.age_max) return false;
                    if (pkg.gender && pkg.gender !== 'mixed' && m.gender) {
                        if (pkg.gender === 'male'   && m.gender !== 'Male') return false;
                        if (pkg.gender === 'female' && m.gender !== 'Female') return false;
                    }
                    return true;
                });
                if (eligible.length === 0) {
                    Toast.warning('Not Eligible', 'None of your family members are eligible for this package.');
                    return;
                }
                this.familyMembers = eligible;
                this._preselectPackageId = packageId;
                this.open = true; this.step = 'select-members';
                this.selectedMemberIds = []; this.registrants = [];
                this.payLater = false; this.paymentScreenshot = false; this.submitting = false;
                window._joinModal = this; document.body.style.overflow = 'hidden';
            },

            close() {
                this.open = false;
                this.familyMembers = this._allFamilyMembers;
                this._preselectPackageId = null;
                document.body.style.overflow = '';
            },

            toggleMember(member) {
                const idx = this.selectedMemberIds.indexOf(member.id);
                if (idx === -1) this.selectedMemberIds.push(member.id);
                else this.selectedMemberIds.splice(idx, 1);
            },
            isMemberSelected(memberId) { return this.selectedMemberIds.includes(memberId); },

            buildRegistrants() {
                this.registrants = this.familyMembers
                    .filter(m => this.selectedMemberIds.includes(m.id))
                    .map(m => ({
                        id: this._uid(), userId: m.id,
                        type: m.type === 'guardian' ? 'self' : 'child',
                        packageId: this._preselectPackageId || '',
                        name: m.name, gender: m.gender || '', dateOfBirth: m.birthdate || '',
                        avatarUrl: m.profile_picture ? '/storage/' + m.profile_picture : null,
                        relationship: m.relationship, isMember: m.is_member || false,
                        equipment: [],
                    }));
            },

            // Equipment offering per registrant — required gear ticked unless owned.
            buildEquipment() {
                this.registrants = this.registrants.map(reg => {
                    const pkg = this.getPackageForRegistrant(reg);
                    const ownedFor = this.ownedMap[reg.userId] || { products: [], variants: [] };
                    const items = (pkg?.equipment || []).map(e => {
                        const owned = e.has_variants ? false : (e.already_owned || (ownedFor.products || []).includes(e.product_id));
                        return {
                            id: e.id, product_id: e.product_id, name: e.name,
                            price: Number(e.price || 0), image: e.image || null,
                            is_required: !!e.is_required, has_variants: !!e.has_variants,
                            variants: e.variants || [], owned: owned, variantId: '',
                            selected: !!e.is_required && !owned,
                        };
                    });
                    return { ...reg, equipment: items };
                });
            },
            hasAnyEquipment() { return this.registrants.some(r => (r.equipment || []).length > 0); },
            toggleEquip(reg, item) { item.selected = !item.selected; },
            setEquipVariant(item, variantId) { item.variantId = variantId; item.selected = true; },
            equipItemPrice(item) {
                if (item.has_variants && item.variantId) {
                    const v = (item.variants || []).find(x => x.id == item.variantId);
                    return v ? Number(v.price || 0) : 0;
                }
                return Number(item.price || 0);
            },
            equipmentTotal() {
                let total = 0;
                this.registrants.forEach(reg => (reg.equipment || []).forEach(item => { if (item.selected) total += this.equipItemPrice(item); }));
                return total;
            },
            registrationFeeFor(reg) {
                const pkg = this.getPackageForRegistrant(reg);
                const override = pkg && pkg.registration_fee != null ? Number(pkg.registration_fee) : null;
                let fee = override != null ? override : Number(this.registrationFee || 0);
                if (fee <= 0) fee = Number(this.enrollmentFee || 0);
                return fee;
            },
            registrationTotal() {
                return this.registrants.reduce((s, reg) => s + (reg.isMember ? 0 : this.registrationFeeFor(reg)), 0);
            },

            selectPackage(registrantId, packageId) {
                this.registrants = this.registrants.map(r => r.id === registrantId
                    ? { ...r, packageId: r.packageId == packageId ? '' : packageId } : r);
            },
            getPackageForRegistrant(reg) { return this.packages.find(p => p.id == reg.packageId) || null; },
            getEligiblePackages(reg) {
                const age = this.calculateAge(reg.dateOfBirth);
                return this.packages.filter(pkg => {
                    if (pkg.age_min !== null && pkg.age_min !== undefined && age !== null && age < pkg.age_min) return false;
                    if (pkg.age_max !== null && pkg.age_max !== undefined && age !== null && age > pkg.age_max) return false;
                    if (pkg.gender && pkg.gender !== 'mixed' && reg.gender) {
                        if (pkg.gender === 'male'   && reg.gender !== 'Male') return false;
                        if (pkg.gender === 'female' && reg.gender !== 'Female') return false;
                    }
                    return true;
                });
            },

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
                if (this.step === 'select-members') this.close();
                else if (this.step === 'package-selection') this.step = 'select-members';
                else if (this.step === 'equipment') this.step = this._preselectPackageId ? 'select-members' : 'package-selection';
                else if (this.step === 'payment-review') this.step = this.hasAnyEquipment() ? 'equipment' : (this._preselectPackageId ? 'select-members' : 'package-selection');
            },
            goNext() {
                if (this.step === 'select-members') {
                    if (this.selectedMemberIds.length === 0) { Toast.warning('No Members Selected', 'Please select at least one person to register.'); return; }
                    this.buildRegistrants();
                    this.buildEquipment();
                    this.step = this.hasAnyEquipment() ? 'equipment' : (this._preselectPackageId ? 'payment-review' : 'package-selection');
                } else if (this.step === 'package-selection') {
                    const missing = this.registrants.filter(r => !r.packageId);
                    if (missing.length > 0) { Toast.warning('Packages Required', 'Please select a package for all registrants.'); return; }
                    this.buildEquipment();
                    this.step = this.hasAnyEquipment() ? 'equipment' : 'payment-review';
                } else if (this.step === 'equipment') {
                    const missingVariant = this.registrants.some(r => (r.equipment || []).some(i => i.selected && i.has_variants && !i.variantId));
                    if (missingVariant) { Toast.warning('Choose an option', 'Please pick a size/variant for each selected item.'); return; }
                    this.step = 'payment-review';
                } else if (this.step === 'payment-review') { this.handleSubmit(); }
            },

            async handleSubmit() {
                if (!this.payLater && !this.paymentScreenshot) { Toast.warning('Payment Required', 'Please upload a payment screenshot or select "Pay Later".'); return; }
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
                                if (item.has_variants && item.variantId) formData.append(`registrants[${i}][variants][${item.id}]`, item.variantId);
                            } else if (item.is_required && !item.owned) {
                                formData.append(`registrants[${i}][owned_equipment][]`, item.id);
                            }
                        });
                    });
                    if (!this.payLater) {
                        const proofBase64 = document.getElementById('hiddenInput_joinPaymentProofCropper')?.value;
                        if (proofBase64) formData.append('payment_proof_base64', proofBase64);
                    }
                    const response = await fetch(`/${this.clubCountry}/clubs/join`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Accept': 'application/json' },
                        body: formData
                    });
                    const data = await response.json();
                    if (data.success) { this.close(); Toast.success('Registration Submitted', 'Your registration has been submitted successfully!'); }
                    else { Toast.error('Registration Failed', data.message || 'Please try again.'); }
                } catch (error) {
                    console.error('Registration error:', error);
                    Toast.error('Error', 'An error occurred during registration. Please try again.');
                } finally { this.submitting = false; }
            },

            formatCurrency(amount) {
                try {
                    const hasDecimals = amount % 1 !== 0;
                    return new Intl.NumberFormat('en-US', { style: 'currency', currency: this.currency, minimumFractionDigits: hasDecimals ? 2 : 0, maximumFractionDigits: hasDecimals ? 2 : 0 }).format(amount);
                } catch { return amount + ' ' + this.currency; }
            },
            calculateSubtotal() {
                const packageTotal = this.registrants.reduce((sum, reg) => { const pkg = this.packages.find(p => p.id == reg.packageId); return sum + Number(pkg?.price || 0); }, 0);
                return (packageTotal + this.registrationTotal() + this.equipmentTotal()).toFixed(2);
            },
            calculateVat() { if (!this.vatPercentage || this.vatPercentage <= 0) return '0.00'; return (parseFloat(this.calculateSubtotal()) * this.vatPercentage / 100).toFixed(2); },
            calculateTotal() { return (parseFloat(this.calculateSubtotal()) + parseFloat(this.calculateVat())).toFixed(2); },
            firstTimerCount() { return this.registrants.filter(r => !r.isMember).length; },
            firstTimerNames() { return this.registrants.map(r => r.name).join(', '); },
            genderLabel(g) { return g ? g.charAt(0).toUpperCase() + g.slice(1) : ''; },
            calculateAge(dateOfBirth) {
                if (!dateOfBirth) return 0;
                const today = new Date(); const birth = new Date(dateOfBirth);
                let age = today.getFullYear() - birth.getFullYear();
                const m = today.getMonth() - birth.getMonth();
                if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
                return age;
            },
            _uid() { return Math.random().toString(36).substr(2, 9); }
        },
        init() { window._joinModal = this.joinModal; }
    };
}

function openSelectPackageModal(packageId) {
    const wrap = document.getElementById('selectPackageModalWrap');
    if (wrap && wrap._x_dataStack) {
        const app = wrap._x_dataStack[0];
        if (app && app.joinModal) app.joinModal.showForPackage(packageId);
    }
}
</script>
@endpush
