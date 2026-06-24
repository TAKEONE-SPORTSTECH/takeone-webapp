{{--
    Mobile "Add member" flow for the club members page.

    Opened via the `open-add-member` window event (button beside the search bar).
    A bottom-sheet then offers three ways to add a member:
      • Scan QR     — camera scans a member's profile QR (/u/{slug}) → confirm → add
      • Register new — opens the existing walk-in registration wizard
      • Find one     — searches existing platform users → multi-select → add

    Every fixed overlay is teleported to <body> so it escapes #shell-content's
    transformed ancestor (.mobile-stagger) — see CLAUDE.md "Mobile Forms" rule.
--}}
<div x-data="addMemberFlow({
        searchUrl:   '{{ route('admin.club.members.search', $club->slug) }}',
        storeUrl:    '{{ route('admin.club.members.store', $club->slug) }}',
        resolveUrl:  '{{ route('admin.club.members.resolve-qr', $club->slug) }}',
        csrf:        '{{ csrf_token() }}',
     })"
     @open-add-member.window="menuOpen = true"
     x-cloak>

    {{-- ── Options menu (bottom-sheet) ───────────────────────────────── --}}
    <template x-teleport="body">
        <div>
            <div x-show="menuOpen" x-transition.opacity
                 class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm" @click="menuOpen = false"></div>

            <div x-show="menuOpen"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="fixed inset-x-0 bottom-0 z-[60] bg-white rounded-t-3xl shadow-2xl"
                 style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                <div class="flex justify-center pt-3 pb-1"><span class="w-10 h-1.5 rounded-full bg-gray-200"></span></div>
                <div class="px-5 pt-2 pb-1">
                    <h3 class="text-lg font-bold text-foreground">{{ __('admin.add_member') }}</h3>
                    <p class="text-sm text-muted-foreground">{{ __('admin.add_member_how') }}</p>
                </div>
                <div class="px-4 py-3 space-y-2.5">
                    <button type="button" @click="startScan()"
                            class="m-press w-full flex items-center gap-4 p-4 rounded-2xl border border-gray-100 bg-white hover:bg-accent transition-colors text-left">
                        <span class="w-11 h-11 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0"><i class="bi bi-qr-code-scan text-xl"></i></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-foreground">{{ __('admin.scan_qr') }}</span>
                            <span class="block text-xs text-muted-foreground">{{ __('admin.scan_qr_hint') }}</span>
                        </span>
                    </button>
                    <button type="button" @click="startRegister()"
                            class="m-press w-full flex items-center gap-4 p-4 rounded-2xl border border-gray-100 bg-white hover:bg-accent transition-colors text-left">
                        <span class="w-11 h-11 rounded-xl bg-green-100 text-green-700 flex items-center justify-center flex-shrink-0"><i class="bi bi-person-plus text-xl"></i></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-foreground">{{ __('admin.register_new') }}</span>
                            <span class="block text-xs text-muted-foreground">{{ __('admin.register_new_hint') }}</span>
                        </span>
                    </button>
                    <button type="button" @click="startFind()"
                            class="m-press w-full flex items-center gap-4 p-4 rounded-2xl border border-gray-100 bg-white hover:bg-accent transition-colors text-left">
                        <span class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center flex-shrink-0"><i class="bi bi-search text-xl"></i></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-foreground">{{ __('admin.find_member') }}</span>
                            <span class="block text-xs text-muted-foreground">{{ __('admin.find_member_hint') }}</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ── Register-new type choice: Guardian / Child ─────────────────── --}}
    <template x-teleport="body">
        <div>
            <div x-show="registerChoiceOpen" x-transition.opacity
                 class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm" @click="registerChoiceOpen = false"></div>

            <div x-show="registerChoiceOpen"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="fixed inset-x-0 bottom-0 z-[60] bg-white rounded-t-3xl shadow-2xl"
                 style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                <div class="flex justify-center pt-3 pb-1"><span class="w-10 h-1.5 rounded-full bg-gray-200"></span></div>
                <div class="px-5 pt-2 pb-1 flex items-center gap-2">
                    <button type="button" @click="registerChoiceOpen = false; menuOpen = true" class="m-press -ml-1 w-8 h-8 rounded-full flex items-center justify-center hover:bg-muted"><i class="bi bi-chevron-left"></i></button>
                    <div>
                        <h3 class="text-lg font-bold text-foreground">{{ __('admin.register_new') }}</h3>
                        <p class="text-sm text-muted-foreground">{{ __('admin.register_who') }}</p>
                    </div>
                </div>
                <div class="px-4 py-3 space-y-2.5">
                    <button type="button" @click="register('guardian')"
                            class="m-press w-full flex items-center gap-4 p-4 rounded-2xl border border-gray-100 bg-white hover:bg-accent transition-colors text-left">
                        <span class="w-11 h-11 rounded-xl bg-primary/10 text-primary flex items-center justify-center flex-shrink-0"><i class="bi bi-person-badge text-xl"></i></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-foreground">{{ __('admin.guardian') }}</span>
                            <span class="block text-xs text-muted-foreground">{{ __('admin.guardian_hint') }}</span>
                        </span>
                    </button>
                    <button type="button" @click="register('child')"
                            class="m-press w-full flex items-center gap-4 p-4 rounded-2xl border border-gray-100 bg-white hover:bg-accent transition-colors text-left">
                        <span class="w-11 h-11 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center flex-shrink-0"><i class="bi bi-balloon text-xl"></i></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-foreground">{{ __('admin.child') }}</span>
                            <span class="block text-xs text-muted-foreground">{{ __('admin.child_hint') }}</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ── Find existing member (bottom-sheet) ───────────────────────── --}}
    <template x-teleport="body">
        <div>
            <div x-show="findOpen" x-transition.opacity
                 class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm" @click="closeFind()"></div>

            <div x-show="findOpen"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="fixed inset-x-0 bottom-0 z-[60] bg-white rounded-t-3xl shadow-2xl max-h-[92vh] flex flex-col">
                {{-- Header --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-3 border-b border-gray-100">
                    <div class="flex justify-center pb-2"><span class="w-10 h-1.5 rounded-full bg-gray-200"></span></div>
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-foreground">{{ __('admin.find_member') }}</h3>
                        <button type="button" @click="closeFind()" class="m-press w-9 h-9 -mr-1 rounded-full flex items-center justify-center hover:bg-muted"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="relative mt-3">
                        <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none"></i>
                        <input type="search" x-model="findQuery" @input.debounce.300ms="doSearch()"
                               placeholder="{{ __('admin.search_email_phone') }}" autocomplete="off"
                               class="w-full pl-10 pr-3 py-2.5 bg-muted rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/40">
                    </div>
                </div>
                {{-- Scrollable results --}}
                <div class="flex-1 overflow-y-auto px-4 py-3 space-y-2.5">
                    <template x-if="searching">
                        <div class="text-center py-8 text-muted-foreground text-sm"><i class="bi bi-arrow-repeat animate-spin text-xl"></i></div>
                    </template>
                    <template x-if="!searching && findQuery.trim().length >= 2 && findResults.length === 0">
                        <div class="text-center py-10 text-muted-foreground">
                            <i class="bi bi-person-x text-3xl text-gray-300"></i>
                            <p class="text-sm mt-2">{{ __('admin.no_users_found') }}</p>
                        </div>
                    </template>
                    <template x-if="!searching && findQuery.trim().length < 2">
                        <div class="text-center py-10 text-muted-foreground">
                            <i class="bi bi-search text-3xl text-gray-300"></i>
                            <p class="text-sm mt-2">{{ __('admin.search_to_find') }}</p>
                        </div>
                    </template>
                    <template x-for="u in findResults" :key="u.id">
                        <button type="button" @click="!u.is_member && toggleSelect(u.id)"
                                class="m-press w-full flex items-center gap-3 p-3 rounded-2xl border transition-colors text-left"
                                :class="u.is_member ? 'border-gray-100 bg-gray-50 opacity-60' : (selected.includes(u.id) ? 'border-primary bg-accent' : 'border-gray-100 bg-white hover:bg-muted')"
                                :disabled="u.is_member">
                            <span class="w-11 h-11 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                                <template x-if="u.profile_picture"><img :src="u.profile_picture" alt="" class="w-11 h-11 object-cover"></template>
                                <template x-if="!u.profile_picture"><i class="bi bi-person text-muted-foreground text-lg"></i></template>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-foreground truncate" x-text="u.name"></span>
                                <span class="block text-xs text-muted-foreground truncate" x-text="u.email || (u.mobile ? ((u.mobile.code||'')+' '+(u.mobile.number||'')) : '{{ __('admin.no_contact_info') }}')"></span>
                            </span>
                            <template x-if="u.is_member">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-medium bg-green-100 text-green-700 flex-shrink-0">{{ __('admin.already_member') }}</span>
                            </template>
                            <template x-if="!u.is_member">
                                <span class="w-6 h-6 rounded-full border-2 flex items-center justify-center flex-shrink-0 transition-colors"
                                      :class="selected.includes(u.id) ? 'bg-primary border-primary text-white' : 'border-gray-300 text-transparent'">
                                    <i class="bi bi-check-lg text-xs"></i>
                                </span>
                            </template>
                        </button>
                    </template>
                </div>
                {{-- Sticky footer --}}
                <div class="flex-shrink-0 px-4 pt-3 border-t border-gray-100" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="addSelected()" :disabled="selected.length === 0 || adding"
                            class="w-full py-3 rounded-xl bg-primary text-white font-medium hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span x-show="!adding" x-text="selected.length ? '{{ __('admin.add_n_members') }}'.replace(':count', selected.length) : '{{ __('admin.select_members') }}'"></span>
                        <span x-show="adding"><i class="bi bi-arrow-repeat animate-spin mr-1"></i>{{ __('admin.adding') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ── QR scanner overlay ────────────────────────────────────────── --}}
    <template x-teleport="body">
        <div x-show="scanOpen" class="fixed inset-0 z-[70] bg-black flex flex-col"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
            <div class="flex items-center justify-between px-4 h-14 text-white flex-shrink-0">
                <span class="font-semibold">{{ __('admin.scan_qr') }}</span>
                <button type="button" @click="closeScan()" class="m-press w-10 h-10 -mr-2 rounded-full flex items-center justify-center hover:bg-white/10" aria-label="{{ __('shared.cancel') }}"><i class="bi bi-x-lg text-xl"></i></button>
            </div>
            <div class="flex-1 relative overflow-hidden">
                <video x-ref="scanVideo" playsinline muted class="absolute inset-0 w-full h-full object-cover"></video>
                <div class="absolute inset-0 grid place-items-center pointer-events-none">
                    <div class="w-64 h-64 max-w-[70vw] max-h-[70vw] rounded-3xl border-2 border-white/90" style="box-shadow: 0 0 0 100vmax rgba(0,0,0,.45);"></div>
                </div>
                <p class="absolute bottom-10 inset-x-0 text-center text-white/90 text-sm px-8">{{ __('admin.scan_member_hint') }}</p>
            </div>

            {{-- Confirm card (shown after a member QR resolves) --}}
            <div x-show="resolved" x-cloak
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 class="absolute inset-x-0 bottom-0 bg-white rounded-t-3xl shadow-2xl p-5" style="padding-bottom: calc(1.25rem + env(safe-area-inset-bottom));">
                <div class="flex items-center gap-3">
                    <span class="w-14 h-14 rounded-full bg-muted flex items-center justify-center overflow-hidden flex-shrink-0">
                        <template x-if="resolved && resolved.profile_picture"><img :src="resolved.profile_picture" alt="" class="w-14 h-14 object-cover"></template>
                        <template x-if="resolved && !resolved.profile_picture"><i class="bi bi-person text-muted-foreground text-2xl"></i></template>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-base font-bold text-foreground truncate" x-text="resolved?.name"></p>
                        <p class="text-xs text-muted-foreground truncate" x-text="resolved?.email || ''"></p>
                    </div>
                </div>
                <template x-if="resolved && resolved.is_member">
                    <p class="mt-4 text-sm text-center text-green-700 bg-green-50 rounded-xl py-3">{{ __('admin.already_a_member') }}</p>
                </template>
                <div class="mt-4 flex gap-3">
                    <button type="button" @click="rescan()" class="flex-1 py-3 rounded-xl border border-gray-200 text-foreground font-medium hover:bg-muted transition-colors">{{ __('admin.scan_again') }}</button>
                    <button type="button" x-show="resolved && !resolved.is_member" @click="confirmScan()" :disabled="adding"
                            class="flex-1 py-3 rounded-xl bg-primary text-white font-medium hover:bg-primary/90 transition-colors disabled:opacity-50">
                        <span x-show="!adding">{{ __('admin.add_to_club') }}</span>
                        <span x-show="adding"><i class="bi bi-arrow-repeat animate-spin mr-1"></i>{{ __('admin.adding') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

{{-- Inline (not @push) so the mobile shell's runScripts() re-defines the factory
     after each in-place navigation into the members page. --}}
<script>
    window.addMemberFlow = function (cfg) {
        return {
            // shared
            menuOpen: false,
            registerChoiceOpen: false,
            adding: false,
            // find
            findOpen: false,
            findQuery: '',
            findResults: [],
            selected: [],
            searching: false,
            // scan
            scanOpen: false,
            resolved: null,
            _stream: null,
            _detector: null,
            _raf: null,

            // ── Menu actions ──────────────────────────────────────────
            startRegister() {
                // Step the user through a Guardian / Child choice first.
                this.menuOpen = false;
                this.registerChoiceOpen = true;
            },
            register(type) {
                this.registerChoiceOpen = false;
                window.dispatchEvent(new CustomEvent('open-walkin-modal', { detail: { type } }));
            },
            startFind() {
                this.menuOpen = false;
                this.findQuery = '';
                this.findResults = [];
                this.selected = [];
                this.findOpen = true;
            },
            closeFind() { this.findOpen = false; },

            // ── Find existing user ────────────────────────────────────
            async doSearch() {
                const q = this.findQuery.trim();
                if (q.length < 2) { this.findResults = []; return; }
                this.searching = true;
                try {
                    const res = await fetch(cfg.searchUrl + '?query=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json();
                    // Flatten dependents in alongside their guardian.
                    const flat = [];
                    (data.users || []).forEach(u => {
                        flat.push(u);
                        (u.dependents || []).forEach(d => flat.push(d));
                    });
                    this.findResults = flat;
                } catch (e) {
                    window.showToast && window.showToast('error', @js(__('admin.search_failed')));
                } finally {
                    this.searching = false;
                }
            },
            toggleSelect(id) {
                const i = this.selected.indexOf(id);
                if (i === -1) this.selected.push(id); else this.selected.splice(i, 1);
            },
            async addSelected() {
                if (!this.selected.length) return;
                const ids = this.selected.slice();
                if (await this.addUsers(ids)) { this.closeFind(); }
            },

            // ── QR scan ───────────────────────────────────────────────
            async startScan() {
                this.menuOpen = false;
                if (!('BarcodeDetector' in window)) {
                    window.showToast && window.showToast('info', @js(__('admin.scan_unsupported')));
                    return;
                }
                this.resolved = null;
                this.scanOpen = true;
                await this.$nextTick();
                try {
                    this._detector = new BarcodeDetector({ formats: ['qr_code'] });
                    this._stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                    const v = this.$refs.scanVideo;
                    v.srcObject = this._stream;
                    await v.play();
                    this.scanLoop();
                } catch (e) {
                    window.showToast && window.showToast('error', @js(__('admin.scan_no_camera')));
                    this.closeScan();
                }
            },
            async scanLoop() {
                if (!this.scanOpen || !this._detector || this.resolved) return;
                try {
                    const codes = await this._detector.detect(this.$refs.scanVideo);
                    if (codes && codes.length && codes[0].rawValue) {
                        this.handleScan(codes[0].rawValue);
                        return;
                    }
                } catch (_) { /* transient — keep scanning */ }
                this._raf = requestAnimationFrame(() => this.scanLoop());
            },
            async handleScan(value) {
                this._stopCamera();
                try {
                    const res = await fetch(cfg.resolveUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                        body: JSON.stringify({ value }),
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        this.resolved = data.user;
                    } else {
                        window.showToast && window.showToast('error', data.message || @js(__('admin.qr_not_member')));
                        this.rescan();
                    }
                } catch (e) {
                    window.showToast && window.showToast('error', @js(__('admin.qr_not_member')));
                    this.rescan();
                }
            },
            async confirmScan() {
                if (!this.resolved) return;
                if (await this.addUsers([this.resolved.id])) { this.closeScan(); }
            },
            rescan() {
                this.resolved = null;
                if (!this.scanOpen) return;
                this.startScan();
            },
            closeScan() {
                this.scanOpen = false;
                this.resolved = null;
                this._stopCamera();
            },
            _stopCamera() {
                if (this._raf) { cancelAnimationFrame(this._raf); this._raf = null; }
                if (this._stream) { this._stream.getTracks().forEach(t => t.stop()); this._stream = null; }
                this._detector = null;
            },

            // ── Shared: add users to club + refresh roster ────────────
            async addUsers(ids) {
                this.adding = true;
                try {
                    const res = await fetch(cfg.storeUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                        body: JSON.stringify({ user_ids: ids }),
                    });
                    const data = await res.json();
                    if (res.ok && data.success) {
                        window.showToast && window.showToast('success', data.message || @js(__('admin.member_added')));
                        refreshRoster();
                        return true;
                    }
                    window.showToast && window.showToast('error', data.message || @js(__('admin.add_failed')));
                    return false;
                } catch (e) {
                    window.showToast && window.showToast('error', @js(__('admin.add_failed')));
                    return false;
                } finally {
                    this.adding = false;
                }
            },
        };
    };

    // Re-fetch the members roster in place through the mobile shell (no full reload).
    // Also wired as window.reloadMemberCards so the walk-in wizard refreshes here too.
    function refreshRoster() {
        const a = document.createElement('a');
        a.setAttribute('data-shell-link', '');
        a.href = window.location.href;
        document.body.appendChild(a);
        a.click();
        a.remove();
    }
    window.reloadMemberCards = refreshRoster;
</script>
