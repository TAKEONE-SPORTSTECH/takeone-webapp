{{--
    Budget & profit — the page's behaviour, ONE implementation for the mobile
    and the desktop (the page is drawn in the event's branded shell on both).

    The server is the authority: every write answers with the whole fresh
    budget (BudgetPlan::payload) and the page re-renders from it. The only
    arithmetic done here is the live "tune it" preview, which mirrors
    BudgetPlan::compute() so the sliders answer instantly; saving a scenario
    asks the server, which recomputes and stores its own result.

    Registered with Alpine.data() because the sheets live in
    <template x-teleport="body">, where an inline <script> would be inert.
--}}
@once
@push('scripts')
<script>
(function () {
    function register() {
        if (! window.Alpine || window.Alpine.data.__eventBudget) { return; }
        window.Alpine.data.__eventBudget = true;

        window.Alpine.data('eventBudget', function (cfg) {
            return {
                cfg: cfg,
                b: cfg.budget,
                busy: false,
                // The live what-if — seeded from the plan, never saved by moving it.
                tune: { fee: 0, entrants: 0, medals_mode: 'entries', sponsor: 0, items: {} },
                sheet: null,          // 'item' | 'scenario' | 'approve' | 'start' | null
                item: null,           // the cost being edited (null uuid = new)
                scenarioName: '',
                approving: null,
                applyFee: true,
                sources: null,
                // The advisor — a conversation held in the browser only; the server keeps nothing.
                chat: [],
                question: '',
                asking: false,

                init() { this.seedTune(); },

                seedTune() {
                    const p = this.b.plan;
                    this.tune = { fee: p.fee, entrants: p.entrants, medals_mode: p.medals_mode, sponsor: p.sponsor, items: {} };
                },

                // ---- formatting ----
                money(x) {
                    if (x === null || x === undefined || isNaN(x)) { return '—'; }
                    return Number(x).toLocaleString('en-GB', { minimumFractionDigits: 0, maximumFractionDigits: 3 });
                },
                signed(x) { return (x > 0 ? '+' : (x < 0 ? '−' : '')) + this.money(Math.abs(x)); },

                // ---- the live preview (mirrors BudgetPlan::compute) ----
                get live() {
                    const s = this.b.settings, items = this.b.items || [];
                    const n = Math.max(0, parseInt(this.tune.entrants || 0, 10));
                    const fee = Math.max(0, parseFloat(this.tune.fee || 0));
                    const sponsor = Math.max(0, parseFloat(this.tune.sponsor || 0));
                    const medals = this.b.medal_options[this.tune.medals_mode] || this.b.medal_options.entries;
                    // Each medal type at its own price; a type left blank costs the single price.
                    const unit = s.medal_unit_cost || 0;
                    const price = { gold: s.medal_gold_cost ?? unit, silver: s.medal_silver_cost ?? unit, bronze: s.medal_bronze_cost ?? unit };
                    const medalCost = medals.gold * price.gold + medals.silver * price.silver + medals.bronze * price.bronze;
                    const perDivision = items.filter(i => i.basis === 'per_division').reduce((a, i) => a + this.amountOf(i), 0) * (medals.divisions_running || 0);
                    const fixed = items.filter(i => i.basis === 'fixed').reduce((a, i) => a + this.amountOf(i), 0) + perDivision;
                    const per = items.filter(i => i.basis === 'per_entrant').reduce((a, i) => a + this.amountOf(i), 0);
                    const costs = fixed + per * n + medalCost;
                    const covered = Math.min(sponsor, costs);
                    const expenses = costs - covered;
                    const revenue = fee * n;
                    const net = revenue - expenses;
                    const margin = fee - per;
                    const target = s.target_profit || 0, reserve = s.reserve_percent || 0, step = s.round_to || 0;
                    const raw = n > 0 ? (expenses * (1 + reserve / 100) + target) / n : null;
                    const suggested = raw === null ? null : (step > 0 ? Math.ceil(Math.round(raw / step * 1e6) / 1e6) * step : raw);
                    const status = net < 0 ? 'loss' : ((target > 0 && net < target) ? 'thin' : 'profitable');
                    const top = Math.max(revenue, expenses, 1);
                    return {
                        n, fee, revenue, expenses, net, medals, medalCost, status,
                        breakEven: n > 0 ? expenses / n : null,
                        needed: margin > 0 ? Math.ceil(Math.max(0, fixed + medalCost - covered) / margin) : null,
                        suggested,
                        revW: Math.round(revenue / top * 100), expW: Math.round(expenses / top * 100),
                    };
                },
                statusLabel(s) { return ({ loss: @js(__('events::events.budget_status_loss')), thin: @js(__('events::events.budget_status_thin')), profitable: @js(__('events::events.budget_status_profitable')) })[s]; },
                statusClass(s) { return ({ loss: 'bg-red-50 text-red-700', thin: 'bg-amber-50 text-amber-700', profitable: 'bg-green-50 text-green-700' })[s]; },
                nudge(field, by, min, max) {
                    const v = (parseFloat(this.tune[field] || 0) + by);
                    this.tune[field] = Math.min(max, Math.max(min, Math.round(v * 1000) / 1000));
                },
                get feeMax() { return Math.max(50, Math.ceil((this.b.plan.fee || 10) * 3), Math.ceil(parseFloat(this.tune.fee || 0))); },
                get entrantsMax() { return Math.max(200, (this.b.turnout.entered || 0) * 2, (this.b.plan.entrants || 0) * 2, parseInt(this.tune.entrants || 0, 10)); },

                // A cost line's amount as previewed on screen (the advisor may re-price it).
                medalPrice(type) { const s = this.b.settings; const v = s['medal_' + type + '_cost']; return (v === null || v === undefined) ? (s.medal_unit_cost || 0) : v; },
                amountOf(i) { const o = (this.tune.items || {})[i.uuid]; return (o !== undefined && o !== null) ? Number(o) : i.amount; },
                isPreviewed(i) { const o = (this.tune.items || {})[i.uuid]; return o !== undefined && o !== null && Number(o) !== Number(i.amount); },
                lineCost(i) {
                    if (i.basis === 'per_entrant') { return this.amountOf(i) * (parseInt(this.tune.entrants || 0, 10)); }
                    if (i.basis === 'per_division') { return this.amountOf(i) * ((this.b.medal_options[this.tune.medals_mode] || {}).divisions_running || 0); }
                    return this.amountOf(i);
                },
                groupTotal(g) { return g.rows.reduce((a, r) => a + this.lineCost(r), 0) + (g.key === 'awards' ? this.live.medalCost : 0); },
                get previewing() { return Object.keys(this.tune.items || {}).length > 0; },

                // ---- grouping for the cost list ----
                categories: @js(collect(\App\Events\Models\EventBudgetItem::CATEGORIES)->mapWithKeys(fn ($c) => [$c => __('events::events.budget_cat_'.$c)])->all()),
                groups() {
                    const out = [];
                    for (const [key, label] of Object.entries(this.categories)) {
                        const rows = (this.b.items || []).filter(i => i.category === key);
                        const total = (this.b.plan.by_category || {})[key] || 0;
                        if (rows.length || (key === 'awards' && this.b.plan.medals.cost > 0)) { out.push({ key, label, rows, total }); }
                    }
                    return out;
                },
                shares() {
                    const total = Object.values(this.b.plan.by_category || {}).reduce((a, v) => a + v, 0) || 1;
                    const colours = { people: '#7c6bf5', awards: '#d97706', venue: '#0f766e', running: '#94a3b8', other: '#db2777' };
                    return Object.entries(this.b.plan.by_category || {}).filter(([, v]) => v > 0)
                        .map(([k, v]) => ({ key: k, label: this.categories[k], value: v, pct: Math.max(2, Math.round(v / total * 100)), colour: colours[k] }));
                },

                // ---- server calls ----
                async call(method, url, body) {
                    if (this.busy) { return null; }
                    this.busy = true;
                    try {
                        const res = await fetch(url, {
                            method: method,
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                                       'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                            body: body ? JSON.stringify(body) : undefined,
                        });
                        const data = await res.json().catch(() => ({}));
                        if (! res.ok || ! data.success) {
                            const first = data.errors ? Object.values(data.errors)[0][0] : null;
                            window.showToast && window.showToast('error', first || data.message || @js(__('events::events.budget_failed')));
                            return null;
                        }
                        if (data.budget) { this.b = data.budget; }
                        if (data.message && window.showToast) { window.showToast('success', data.message); }
                        window.dispatchEvent(new CustomEvent('event-budget-updated', { detail: this.b }));
                        return data;
                    } catch (e) {
                        window.showToast && window.showToast('error', @js(__('events::events.budget_failed')));
                        return null;
                    } finally {
                        this.busy = false;
                    }
                },
                url(path) { return this.cfg.base + (path || ''); },

                saveSetting(field, value) {
                    const v = (value === '' || value === null) ? null : value;
                    return this.call('PUT', this.url(''), { [field]: v });
                },
                async usePlan() {
                    const ok = await this.call('PUT', this.url(''), { planned_fee: this.tune.fee, expected_entrants: this.tune.entrants, medals_mode: this.tune.medals_mode, sponsor_amount: this.tune.sponsor || 0 });
                    if (ok) { this.seedTune(); }
                },

                openItem(row) {
                    this.item = row
                        ? { ...row, quantity: row.quantity ?? '', unit_cost: row.unit_cost ?? '', units: row.quantity !== null && row.quantity !== undefined && row.category !== 'people' }
                        : { uuid: null, category: 'other', role: null, label: '', amount: '', quantity: '', unit_cost: '', units: false, basis: 'fixed', status: 'planned' };
                    this.sheet = 'item';
                },
                async saveItem() {
                    const body = { category: this.item.category, label: (this.item.label || '').trim(), amount: parseFloat(this.item.amount || 0), basis: this.item.basis, status: this.item.status };
                    // A people line is how many × what each costs; the server works out the total.
                    if (this.item.category === 'people') {
                        body.role = this.item.role || 'other';
                        body.quantity = parseInt(this.item.quantity || 0, 10);
                        body.unit_cost = parseFloat(this.item.unit_cost || 0);
                        body.basis = 'fixed';
                        delete body.amount;
                    } else if (this.item.units) {
                        // Any line priced per unit: how many × the price of one.
                        body.quantity = parseInt(this.item.quantity || 0, 10);
                        body.unit_cost = parseFloat(this.item.unit_cost || 0);
                        delete body.amount;
                    }
                    const ok = this.item.uuid
                        ? await this.call('PUT', this.url('/items/' + this.item.uuid), body)
                        : await this.call('POST', this.url('/items'), body);
                    if (ok) { this.sheet = null; }
                },
                pickRole(role) {
                    this.item.role = role;
                    // Name the line for them if they have not.
                    if (! (this.item.label || '').trim()) { this.item.label = this.roleNames[role] || ''; }
                    if (role === 'volunteer' && this.item.unit_cost === '') { this.item.unit_cost = 0; }
                },
                roleNames: @js(collect(\App\Events\Models\EventBudgetItem::ROLES)->mapWithKeys(fn ($r) => [$r => __('events::events.budget_role_'.$r.'_plural')])->all()),
                async removeItem() {
                    if (! this.item?.uuid) { return; }
                    const sure = window.confirmAction ? await window.confirmAction({ title: @js(__('events::events.budget_remove_item_title')), message: this.item.label, type: 'danger', confirmText: @js(__('events::events.budget_remove')) }) : true;
                    if (! sure) { return; }
                    if (await this.call('DELETE', this.url('/items/' + this.item.uuid))) { this.sheet = null; }
                },

                openScenario() { this.scenarioName = ''; this.sheet = 'scenario'; },
                async saveScenario() {
                    const ok = await this.call('POST', this.url('/scenarios'), { name: this.scenarioName.trim(), fee: this.tune.fee, entrants: this.tune.entrants, medals_mode: this.tune.medals_mode, sponsor: this.tune.sponsor || 0 });
                    if (ok) { this.sheet = null; }
                },
                loadScenario(s) { this.tune = { fee: s.fee, entrants: s.entrants, medals_mode: s.medals_mode, sponsor: s.sponsor, items: {} }; },
                async removeScenario(s) {
                    const sure = window.confirmAction ? await window.confirmAction({ title: @js(__('events::events.budget_remove_scenario_title')), message: s.name, type: 'danger', confirmText: @js(__('events::events.budget_remove')) }) : true;
                    if (sure) { await this.call('DELETE', this.url('/scenarios/' + s.uuid)); }
                },
                openApprove(s) { this.approving = s; this.applyFee = true; this.sheet = 'approve'; },
                async approve() {
                    const ok = await this.call('POST', this.url('/scenarios/' + this.approving.uuid + '/approve'), { apply_fee: this.applyFee });
                    if (ok) { this.sheet = null; this.seedTune(); }
                },

                chatLoaded: false,
                async openAdvisor() {
                    this.sheet = 'advisor';
                    // Restore the conversation the server remembers.
                    if (! this.chatLoaded) {
                        try {
                            const res = await fetch(this.url('/advisor'), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                            const data = await res.json();
                            if (data.success) {
                                this.chat = (data.messages || []).map(m => ({ role: m.role, text: m.content, actions: m.actions || [], reasoning: [], kept: [], tools: [], proposal: null, done: true }));
                                this.chatLoaded = true;
                            }
                        } catch (e) { /* a fresh chat */ }
                    }
                    this.$nextTick(() => this.scrollChat());
                },
                async newChat() {
                    try {
                        await fetch(this.url('/advisor'), { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' } });
                    } catch (e) {}
                    this.chat = [];
                },
                scrollChat() { const el = document.getElementById('bud-chat'); if (el) { el.scrollTop = el.scrollHeight; } },
                async ask(text) {
                    const message = (text ?? this.question).trim();
                    if (! message || this.asking) { return; }
                    const history = this.chat.filter(m => m.role === 'user' || m.role === 'assistant').slice(-8).map(m => ({
                        role: m.role,
                        // What it proposed travels with what it said, so it never offers the same thing twice.
                        content: m.text + (m.role === 'assistant' && m.proposal ? ' [Proposed: ' + m.proposal.changes.map(c => c.field + ' ' + c.from + '→' + c.to).join(', ') + (m.applied ? ', applied' : ', undone') + ']' : ''),
                    }));
                    this.chat.push({ role: 'user', text: message });
                    this.question = '';
                    this.asking = true;
                    this.$nextTick(() => this.scrollChat());
                    const ctrl = new AbortController();
                    const timer = setTimeout(() => ctrl.abort(), 110000);
                    try {
                        const res = await fetch(this.url('/advisor'), {
                            method: 'POST', signal: ctrl.signal,
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                                       'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                            body: JSON.stringify({ message, screen: { fee: this.tune.fee, entrants: parseInt(this.tune.entrants || 0, 10), medals_mode: this.tune.medals_mode, sponsor: this.tune.sponsor || 0 } }),
                        });
                        const data = await res.json().catch(() => ({}));
                        if (! res.ok || ! data.success) {
                            this.chat.push({ role: 'error', text: res.status === 429 ? @js(__('events::events.budget_advisor_slow_down')) : @js(__('events::events.budget_advisor_failed')) });
                        } else {
                            // The agent saved something: the page re-reads the budget in place.
                            if (data.budget) { this.b = data.budget; window.dispatchEvent(new CustomEvent('event-budget-updated', { detail: this.b })); }
                            const msg = { role: 'assistant', text: data.reply, reasoning: data.reasoning || [], kept: data.kept || [], tools: data.tools || [], actions: data.actions || [], proposal: data.proposal, source: data.source, done: false, applied: false, undo: null };
                            this.chat.push(msg);
                            // The advisor's change goes straight onto the sliders, so the
                            // organiser sees the result in front of them — undoable.
                            if (data.proposal) { this.applyProposal(this.chat[this.chat.length - 1]); }
                        }
                    } catch (e) {
                        this.chat.push({ role: 'error', text: @js(__('events::events.budget_advisor_failed')) });
                    } finally {
                        clearTimeout(timer);
                        this.asking = false;
                        this.$nextTick(() => this.scrollChat());
                    }
                },
                changeLabel(c) {
                    const names = { fee: @js(__('events::events.budget_fee')), entrants: @js(__('events::events.budget_entrants')), medals_mode: @js(__('events::events.budget_medals_rule')), sponsor: @js(__('events::events.budget_sponsor')), target_profit: @js(__('events::events.budget_target_profit')) };
                    const v = (x) => c.field === 'medals_mode' ? (x === 'full' ? @js(__('events::events.budget_medals_full')) : @js(__('events::events.budget_medals_entries'))) : this.money(x);
                    return { name: c.field === 'item' ? c.label : (names[c.field] || c.field), from: v(c.from), to: v(c.to) };
                },
                async applyProposal(msg) {
                    const p = msg.proposal;
                    msg.undo = { tune: { ...this.tune, items: { ...(this.tune.items || {}) } }, target: this.b.settings.target_profit };
                    this.tune = { fee: p.fee, entrants: p.entrants, medals_mode: p.medals_mode, sponsor: p.sponsor, items: { ...(this.tune.items || {}), ...(p.item_amounts || {}) } };
                    msg.applied = true;
                    // The profit target is a saved setting — moved only when the advisor
                    // was asked to change it, and put back by Undo.
                    if (p.target_profit !== undefined && Number(p.target_profit) !== Number(this.b.settings.target_profit)) {
                        await this.saveSetting('target_profit', p.target_profit);
                    }
                },
                async undoProposal(msg) {
                    if (! msg.undo) { return; }
                    this.tune = { ...msg.undo.tune, items: { ...(msg.undo.tune.items || {}) } };
                    if (Number(msg.undo.target) !== Number(this.b.settings.target_profit)) {
                        await this.saveSetting('target_profit', msg.undo.target);
                    }
                    msg.applied = false;
                    msg.undo = null;
                },
                showOnPage() {
                    this.sheet = null;
                    this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
                },
                tryProposal(msg) { if (! msg.applied) { this.applyProposal(msg); } this.showOnPage(); },
                hasLineChanges(msg) { return !! (msg.proposal && msg.proposal.changes.some(c => c.field === 'item')); },
                // Keep a plan that re-prices cost lines: the lines are updated and the
                // sliders become the plan — both through the ordinary endpoints.
                async keepChanges(msg) {
                    const p = msg.proposal;
                    for (const c of p.changes.filter(c => c.field === 'item')) {
                        const row = (this.b.items || []).find(i => i.uuid === c.uuid);
                        if (! row) { continue; }
                        const ok = await this.call('PUT', this.url('/items/' + row.uuid), { category: row.category, label: row.label, amount: c.to, basis: row.basis, status: row.status });
                        if (! ok) { return; }
                        delete this.tune.items[row.uuid];
                    }
                    if (await this.call('PUT', this.url(''), { planned_fee: p.fee, expected_entrants: p.entrants, medals_mode: p.medals_mode, sponsor_amount: p.sponsor || 0 })) {
                        msg.done = true;
                        this.tune.items = {};
                    }
                },
                clearPreview() { this.tune.items = {}; },
                async saveProposal(msg) {
                    const p = msg.proposal;
                    const ok = await this.call('POST', this.url('/scenarios'), { name: p.name, fee: p.fee, entrants: p.entrants, medals_mode: p.medals_mode, sponsor: p.sponsor || 0 });
                    if (ok) { msg.done = true; }
                },
                dismissProposal(msg) { msg.proposal = null; },
                // What the advisor looked at, in plain words — so the organiser can see it read their data.
                toolNames: @js(collect(['get_overview', 'list_costs', 'get_divisions', 'list_scenarios', 'cost_breakdown', 'risk_check', 'fee_vs_turnout', 'people_plan', 'list_divisions', 'divisions_what_if', 'solve_for_goal', 'ways_to_save', 'ways_to_earn_more', 'lowest_fee', 'what_if', 'apply_plan', 'propose_change'])->mapWithKeys(fn ($t) => [$t => __('events::events.budget_tool_'.$t)])->all()),
                toolsLine(m) { return (m.tools || []).map(t => this.toolNames[t]).filter(Boolean).join(' · '); },

                // ---- divisions: list, suggested merges, the organiser's own picks ----
                div: { list: null, merges: [], picked: [], impact: null, open: false },
                async loadDivisions(merges) {
                    try {
                        const res = await fetch(this.url('/divisions'), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                                       'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                            body: JSON.stringify({ merges: merges || [], screen: { fee: this.tune.fee, entrants: parseInt(this.tune.entrants || 0, 10), medals_mode: this.tune.medals_mode, sponsor: this.tune.sponsor || 0 } }),
                        });
                        const data = await res.json();
                        if (data.success) { this.div.list = data.list; this.div.impact = data.impact; }
                    } catch (e) { /* the card keeps its last answer */ }
                },
                key(ids) { return [...ids].map(Number).sort((a, b) => a - b).join('-'); },
                isMerged(ids) { const k = this.key(ids); return this.div.merges.some(m => this.key(m) === k); },
                mergedWith(id) { return this.div.merges.some(m => m.map(Number).includes(Number(id))); },
                toggleMerge(ids) {
                    const k = this.key(ids);
                    if (this.isMerged(ids)) { this.div.merges = this.div.merges.filter(m => this.key(m) !== k); }
                    else { this.div.merges = this.div.merges.filter(m => ! m.some(id => ids.includes(id))).concat([ids]); }
                    this.loadDivisions(this.div.merges);
                },
                pickDivision(id) {
                    if (this.mergedWith(id)) { this.div.merges = this.div.merges.filter(m => ! m.map(Number).includes(Number(id))); this.loadDivisions(this.div.merges); return; }
                    this.div.picked = this.div.picked.includes(id) ? this.div.picked.filter(x => x !== id) : this.div.picked.concat([id]);
                },
                mergePicked() {
                    if (this.div.picked.length < 2) { return; }
                    this.div.merges = this.div.merges.concat([this.div.picked]);
                    this.div.picked = [];
                    this.loadDivisions(this.div.merges);
                },
                clearMerges() { this.div.merges = []; this.div.picked = []; this.loadDivisions([]); },

                async openStart() {
                    this.sheet = 'start';
                    if (this.sources === null) {
                        try {
                            const res = await fetch(this.url('/sources'), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                            const data = await res.json();
                            this.sources = data.sources || [];
                        } catch (e) { this.sources = []; }
                    }
                },
                async copyFrom(src) {
                    if (await this.call('POST', this.url('/copy'), { from: src.uuid })) { this.sheet = null; this.seedTune(); }
                },
            };
        });
    }

    if (window.Alpine) { register(); }
    document.addEventListener('alpine:init', register);
})();
</script>
@endpush
@endonce
