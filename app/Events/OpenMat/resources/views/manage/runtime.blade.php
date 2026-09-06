{{--
    Open Mat console — behaviour.

    One implementation behind both layouts. Everything that writes goes to the
    shared, throttled, authorised `me.events.action` endpoint; everything that
    reads after a realtime nudge goes to `me.events.openmat`.

    Inline rather than @push('scripts') because the mobile shell swaps content
    over AJAX. Guarded against redeclaration, and the realtime listener is
    stored on window so a re-entry replaces it instead of stacking a second one.
--}}
<script>
if (! window.openMatConsole) {
    window.openMatConsole = function (boot) {
        return {
            /* ---- state, straight off the server ---- */
            mats: boot.mats || [],
            mat: (boot.mats || [])[0] || '',
            corners: boot.corners || {},
            codes: boot.codes || {},
            bouts: boot.bouts || [],
            live: boot.live || {},
            sport: boot.sport || '',
            sports: boot.sports || [],
            sportLocked: !! boot.sport_locked,
            me: boot.me || null,
            controlUrls: boot.control_urls || {},
            commandUrl: boot.command_url || null,
            joinBase: boot.join_base || '',
            maxMats: boot.max_mats || 4,
            closed: !! boot.closed,

            actionBase: boot.action_base,
            stateUrl: boot.state_url,
            searchUrl: boot.search_url,
            qrBase: boot.qr_url,

            /* ---- the fill sheet ---- */
            sheet: false,
            side: 'aka',
            tab: 'search',
            q: '',
            results: [],
            searching: false,
            guestName: '',
            guestCountry: '',
            busy: false,

            init() {
                // Other consoles (and the opponent's own phone taking a corner)
                // push a refresh signal rather than a payload, because what each
                // holder may see differs. Dedup: the shell re-runs inline
                // scripts on every AJAX navigation.
                if (window.__openMatRealtime) {
                    window.removeEventListener('realtime:events', window.__openMatRealtime);
                }
                window.__openMatRealtime = (e) => {
                    if (e.detail?.action === 'open_mat') this.reload();
                };
                window.addEventListener('realtime:events', window.__openMatRealtime);
            },

            /* ---- derived ---- */
            get aka() { return this.corners[this.mat]?.aka || null; },
            get ao() { return this.corners[this.mat]?.ao || null; },
            get ready() { return !! (this.aka && this.ao); },
            get liveBout() { return this.live[this.mat] || null; },
            get code() { return this.codes[this.mat] || ''; },
            get joinUrl() { return this.code ? this.joinBase + this.code : ''; },
            get qrUrl() {
                return this.code
                    ? this.qrBase + '?mat=' + encodeURIComponent(this.mat) + '&v=' + this.code
                    : '';
            },
            get matBouts() { return (this.bouts || []).filter(b => b.mat === this.mat); },
            corner(side) { return side === 'aka' ? this.aka : this.ao; },
            /** Already standing in either corner of THIS mat. */
            inACorner(userId) {
                return [this.aka, this.ao].some(c => c && c.user_id === userId);
            },

            /* ---- reads ---- */
            async reload() {
                try {
                    const res = await fetch(this.stateUrl, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    if (! data.success) return;
                    const om = data.open_mat || {};
                    this.mats = om.mats || this.mats;
                    this.corners = om.corners || {};
                    this.codes = om.codes || {};
                    this.bouts = om.bouts || [];
                    this.live = om.live || {};
                    this.controlUrls = om.control_urls || {};
                    this.closed = !! om.closed;
                    this.sport = om.sport || this.sport;
                    this.sportLocked = !! om.sport_locked;
                    this.commandUrl = om.command_url || this.commandUrl;
                    if (! this.mats.includes(this.mat)) this.mat = this.mats[0] || '';
                } catch (e) { /* a console that missed one re-reads on the next */ }
            },

            async search() {
                const term = this.q.trim();
                if (term.length < 2) { this.results = []; return; }
                this.searching = true;
                try {
                    const res = await fetch(this.searchUrl + '?q=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    this.results = data.people || [];
                } catch (e) { this.results = []; }
                this.searching = false;
            },

            /* ---- writes ---- */
            async act(action, payload = {}) {
                if (this.busy) return null;
                this.busy = true;
                let data = null;
                try {
                    const res = await fetch(this.actionBase + action, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        },
                        body: JSON.stringify({ mat: this.mat, ...payload }),
                    });
                    data = await res.json();

                    if (! data.success) {
                        window.showToast?.('error', data.message || '');
                    } else if (data.message) {
                        window.showToast?.('success', data.message);
                    }

                    // Patch in place from whatever the action handed back.
                    if (data.corners) this.corners = { ...this.corners, [data.mat || this.mat]: data.corners };
                    if (data.bouts) this.bouts = data.bouts;
                    if (data.mats) this.mats = data.mats;
                    if (data.code) this.codes = { ...this.codes, [data.mat || this.mat]: data.code };
                    if (data.closed) this.closed = true;
                    if (data.sport) this.sport = data.sport;
                    if (data.control_urls) this.controlUrls = data.control_urls;
                    if (data.command_url) this.commandUrl = data.command_url;
                } catch (e) {
                    window.showToast?.('error', '');
                }
                this.busy = false;
                return data;
            },

            openSheet(side) {
                if (this.closed) return;
                this.side = side;
                this.tab = 'search';
                this.q = '';
                this.results = [];
                this.guestName = '';
                this.guestCountry = '';
                this.sheet = true;
            },

            async placeMember(person) {
                const data = await this.act('place_member', { side: this.side, user_id: person.id });
                if (data?.success) this.sheet = false;
            },

            async placeGuest() {
                if (! this.guestName.trim()) return;
                const data = await this.act('place_guest', {
                    side: this.side,
                    name: this.guestName.trim(),
                    country: this.guestCountry.trim() || null,
                });
                if (data?.success) this.sheet = false;
            },

            async clearCorner(side) {
                await this.act('clear_corner', { side });
                this.sheet = false;
            },

            swap() { return this.act('swap_corners'); },
            setSport(key) { return key === this.sport ? null : this.act('set_sport', { sport: key }); },
            newCode() { return this.act('new_code'); },
            addMat() { return this.act('set_mats', { mats: Math.min(this.maxMats, this.mats.length + 1) }); },

            /**
             * Start the fight.
             *
             * Two calls, deliberately. The first creates the bout through this
             * package; the second posts `load` to the SPORT'S OWN scoring
             * endpoint, so the table opens with the bout already on the mat
             * instead of with a queue to tap. Going through the real endpoint
             * rather than reaching into the sport's code is what keeps this
             * package from touching it at all — same validation, same screen
             * pushes, same authority.
             */
            async start() {
                if (! this.ready || this.closed) return;
                const data = await this.act('start_bout');
                if (! data?.success) return;
                this.sportLocked = true;

                const url = data.control_url;

                if (data.command_url && data.match_id) {
                    try {
                        await fetch(data.command_url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body: JSON.stringify({ mat: this.mat, command: 'load', match_id: data.match_id }),
                        });
                    } catch (e) { /* the table will still show it in the queue */ }
                }

                if (url) window.location.href = url;
            },

            async endMat() {
                const ok = await (window.confirmAction?.({
                    title: @js(__('event-open_mat::messages.action_close')),
                    message: @js(__('event-open_mat::messages.mat_ended')),
                    type: 'danger',
                    confirmText: @js(__('event-open_mat::messages.console_end')),
                }) ?? Promise.resolve(true));
                if (! ok) return;
                const data = await this.act('close_mat');
                if (data?.success) window.location.href = @js(route('me.events'));
            },

            copyJoin() {
                if (! this.joinUrl) return;
                navigator.clipboard?.writeText(this.joinUrl);
                window.showToast?.('success', @js(__('event-open_mat::messages.fill_code_copy')));
            },
        };
    };
}
</script>
