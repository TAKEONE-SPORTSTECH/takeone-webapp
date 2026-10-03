@props([
    // FORM mode (the create / edit form): Alpine paths in the PARENT scope.
    'modelCategory' => null,    // e.g. "category_system"
    'modelBelt' => null,        // e.g. "belt_system"
    'sportModel' => null,       // e.g. "sport"
    'hostExpr' => null,         // an Alpine expression giving 'club:12' / 'federation:3'
    'tableModel' => null,       // optional: the chosen system's weight table is written here (for division presets)
    // CONSOLE mode: an existing event, changed through me.events.rulebook.
    'event' => null,            // ClubEvent
    'color' => '#7c3aed',
])

{{--
    The RULE BOOK an event runs under — its category system (age groups ×
    weight classes) and its belt system (2026-09-28).

    One component, two homes:
      · the create / edit form, bound to the form's own fields (`model*`);
      · the event console, where a change is saved at once and everybody
        already entered is re-sorted (App\Events\Support\CategorySystems::change).

    "Make your own" opens a sheet that builds a custom system — starting from
    any existing one — saved for the event's HOST (its club or federation) and
    reusable on its other events. The server validates every value
    (CategorySystems::cleanWeights / cleanBelts) and decides who may save.

    Standalone: its own state, requests and sheet; every server value is drawn
    with x-text / :style, never innerHTML.
--}}

@php
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';
    $console = $event instanceof \App\Events\Models\ClubEvent;
    $initial = $console ? [
        'sport' => (string) $event->sport,
        'host' => implode(':', array_map('strval', app(\App\Events\Support\CategorySystems::class)->hostOf($event))),
        'category' => $event->category_system,
        'belt' => $event->belt_system,
    ] : null;
    $palette = array_keys(\App\Sports\Combat\BeltLadder::HEX);
    $hex = \App\Sports\Combat\BeltLadder::HEX;
    $w = fn ($k) => __('events::events.'.$k);
@endphp

<div x-data="{
        console: @js($console),
        sportNow: @js($initial['sport'] ?? ''),
        hostNow: @js($initial['host'] ?? ''),
        cat: @js($initial['category'] ?? null),
        belt: @js($initial['belt'] ?? null),
        savedCat: @js($initial['category'] ?? null),
        savedBelt: @js($initial['belt'] ?? null),
        weights: [], belts: [], loading: false, saving: false,
        open: false, sheet: false, kind: 'weights', startFrom: '', draft: null,
        palette: @js($palette), hex: @js($hex),
        sportLabels: @js(collect(app(\App\Sports\Combat\SportRegistry::class)->all())->map(fn ($p) => $p->label())->all()),
        sportRead() { return {{ $sportModel ? $sportModel : 'this.sportNow' }}; },
        hostRead() { return {{ $hostExpr ? $hostExpr : 'this.hostNow' }}; },
        init() {
            @if(! $console && $modelCategory) this.cat = {{ $modelCategory }} || null; @endif
            @if(! $console && $modelBelt) this.belt = {{ $modelBelt }} || null; @endif
            this.$watch(() => this.sportRead() + '|' + this.hostRead(), () => this.load());
            this.load();
        },
        async load() {
            const sport = this.sportRead(); if (! sport) { this.weights = []; this.belts = []; return; }
            this.loading = true;
            try {
                const q = new URLSearchParams({ sport }); if (this.hostRead()) q.set('host', this.hostRead());
                const res = await fetch(@js(route('me.category-systems')) + '?' + q, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                const d = await res.json().catch(() => ({}));
                this.weights = d.weights || []; this.belts = d.belts || [];
            } catch (e) { this.weights = []; this.belts = []; }
            this.loading = false;
            this.publish();
        },
        chosen(list, key) { return list.find(s => s.key === key) || list[0] || null; },
        isOn(list, key, s) { return (key || (list[0] || {}).key) === s.key; },
        publish() {
            @if($modelCategory) {{ $modelCategory }} = this.cat || null; @endif
            @if($modelBelt) {{ $modelBelt }} = this.belt || null; @endif
            @if($tableModel) {{ $tableModel }} = (this.cat && this.chosen(this.weights, this.cat)) ? this.chosen(this.weights, this.cat).classes : null; @endif
        },
        async pick(kind, s) {
            const def = kind === 'weights' ? (this.weights[0] || {}).key : (this.belts[0] || {}).key;
            const key = s.key === def ? null : s.key;
            if (kind === 'weights') this.cat = key; else this.belt = key;
            if (! this.console) { this.publish(); return; }
            if (kind === 'weights' && key !== this.savedCat) {
                const ok = window.confirmAction ? await window.confirmAction({ title: @js($w('rulebook_confirm_title')), message: @js($w('rulebook_confirm_body')), type: 'warning', confirmText: @js($w('rulebook_apply')) }) : false;
                if (! ok) { this.cat = this.savedCat; return; }
            }
            this.save();
        },
        async save() {
            if (this.saving) return; this.saving = true;
            try {
                const res = await fetch(@js($console ? route('me.events.rulebook', $event->uuid) : ''), {
                    method: 'PUT', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    body: JSON.stringify({ category_system: this.cat, belt_system: this.belt }),
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));
                this.savedCat = this.cat = d.category_system; this.savedBelt = this.belt = d.belt_system;
                window.showToast?.('success', d.message);
                window.dispatchEvent(new CustomEvent('event-rulebook-changed', { detail: { category_system: d.category_system, belt_system: d.belt_system } }));
            } catch (e) { this.cat = this.savedCat; this.belt = this.savedBelt; window.showToast?.('error', e.message); }
            finally { this.saving = false; }
        },
        // ── The editor ──
        openEditor(kind) {
            this.kind = kind;
            const from = kind === 'weights' ? this.chosen(this.weights, this.cat) : this.chosen(this.belts, this.belt);
            this.startFrom = from ? from.key : '';
            this.seed();
            this.sheet = true;
        },
        seed() {
            const list = this.kind === 'weights' ? this.weights : this.belts;
            const src = list.find(s => s.key === this.startFrom);
            if (this.kind === 'weights') {
                const groups = (src && src.age_groups && src.age_groups.length) ? src.age_groups
                    : Object.keys((src && src.classes) || {}).map(k => ({ key: k, min: 0, max: null }));
                this.draft = { label: '', groups: groups.map(g => ({ key: g.key, min: g.min ?? 0, max: g.max ?? null,
                    male: (((src && src.classes) || {})[g.key] || {}).male?.map(c => ({ label: c.label, min: c.min, max: c.max })) || [],
                    female: (((src && src.classes) || {})[g.key] || {}).female?.map(c => ({ label: c.label, min: c.min, max: c.max })) || [] })) };
            } else {
                this.draft = { label: '', ladder: ((src && src.ladder) || []).map(b => ({ value: b.value, label: b.label || '', grades: (b.grades || []).join(', ') })) };
            }
        },
        addGroup() { this.draft.groups.push({ key: '', min: 0, max: null, male: [], female: [] }); },
        addClass(g, gender) { const list = g[gender]; const last = list[list.length - 1]; list.push({ label: '', min: 0, max: last ? (+last.max || 0) + 5 : 50 }); },
        addBelt() { this.draft.ladder.push({ value: 'white', label: '', grades: '' }); },
        async saveSystem() {
            if (this.saving || ! this.draft) return; this.saving = true;
            const data = this.kind === 'weights'
                ? { age_groups: this.draft.groups.map(g => ({ key: g.key, min: g.min, max: g.max === '' ? null : g.max })),
                    classes: Object.fromEntries(this.draft.groups.map(g => [g.key, { male: g.male, female: g.female }])) }
                : { ladder: this.draft.ladder.map(b => ({ value: b.value, label: b.label, grades: String(b.grades || '').split(',').map(x => x.trim()).filter(Boolean) })) };
            try {
                const res = await fetch(@js(route('me.category-systems.store')), {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    body: JSON.stringify({ sport: this.sportRead(), kind: this.kind, label: this.draft.label, host: this.hostRead(), data }),
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success || ! d.system) throw new Error(d.message || (d.errors ? Object.values(d.errors)[0][0] : @js(__('personal.event_show_action_failed'))));
                if (this.kind === 'weights') this.weights.push(d.system); else this.belts.push(d.system);
                this.sheet = false; this.saving = false;
                window.showToast?.('success', d.message);
                this.pick(this.kind, d.system);
            } catch (e) { window.showToast?.('error', e.message); this.saving = false; }
        },
     }" x-show="weights.length || belts.length || loading" x-cloak>

    @if ($console)
    {{-- CONSOLE: one row, like every other tool in the console, opening the
         choices in a bottom sheet. The row names what is chosen now. --}}
    <button type="button" @click="open = true"
            class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 text-white" style="background: {{ $c }};">
            <i class="bi bi-journal-bookmark-fill text-lg"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ $w('rulebook_title') }}</span>
            <span class="block text-[11px] text-muted-foreground mt-0.5 truncate"
                  x-text="[(chosen(weights, cat) || {}).label, (chosen(belts, belt) || {}).label].filter(Boolean).join(' · ') || @js($w('rulebook_sub'))"></span>
        </span>
        <i class="bi bi-arrow-repeat animate-spin text-muted-foreground flex-shrink-0" x-show="loading || saving" x-cloak></i>
        <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0 rtl:rotate-180"></i>
    </button>

    <template x-teleport="body" data-teleport-template="true">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center"
             @keydown.escape.window="if (! sheet) open = false" style="display:none;">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="open = false"></div>
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="relative w-full max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl overflow-hidden" style="max-width:640px;">
                {{-- gradient header band (Design Rule #8) --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi bi-journal-bookmark-fill text-xl"></i></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ $w('rulebook_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $event->title }}</p>
                        </div>
                        <i class="bi bi-arrow-repeat animate-spin text-white/85 mt-2" x-show="loading || saving" x-cloak></i>
                        <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <p class="relative mt-3 mb-0 text-[11.5px] text-white/85 leading-snug flex items-start gap-1.5">
                        <i class="bi bi-info-circle mt-0.5 flex-shrink-0"></i>{{ $w('rulebook_sub') }}
                    </p>
                </div>
                {{-- Read-only body has no footer: the band's ✕ closes it, so the
                     body carries the safe-area padding itself. --}}
                <div class="flex-1 overflow-y-auto px-5 pt-1 pb-4" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
    @else
    <div class="m-card rounded-2xl p-4">
        <div class="flex items-start gap-3">
            <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 text-white" style="background: {{ $c }};"><i class="bi bi-journal-bookmark-fill"></i></span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-foreground m-0">{{ $w('rulebook_title') }}</p>
                <p class="text-[11px] text-muted-foreground mt-0.5 leading-snug m-0">{{ $w('rulebook_sub') }}</p>
            </div>
            <i class="bi bi-arrow-repeat animate-spin text-muted-foreground" x-show="loading || saving" x-cloak></i>
        </div>
    @endif

        {{-- The rule-book VERSION this event is locked to (Sport Package Plan,
             phase 4). Read-only while each style has one version; a later
             version never reaches an event created before it was published. --}}
        @if ($console && ($edition = app(\App\Sports\Package\SportPackages::class)->forEvent($event)) && $edition->official)
            @php $editionLabel = app(\App\Sports\Package\SportPackages::class)->editions($edition->sport, $edition->style)[$edition->edition]['label'] ?? $edition->edition; @endphp
            <p class="mt-3 mb-0 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-muted text-[11px] font-semibold text-muted-foreground">
                <i class="bi bi-lock-fill"></i>{{ __('events::events.rulebook_version') }}: {{ $editionLabel }}
            </p>
        @endif

        {{-- Category system --}}
        <div class="mt-4" x-show="weights.length">
            <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2 m-0">{{ $w('rulebook_categories') }}</p>
            <div class="space-y-2">
                <template x-for="s in weights" :key="s.key">
                    <button type="button" @click="pick('weights', s)" :disabled="saving"
                            class="m-press w-full text-start rounded-xl border-2 px-3 py-2.5 flex items-center gap-3 transition-colors"
                            :class="isOn(weights, cat, s) ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'">
                        <span class="w-8 h-8 rounded-lg grid place-items-center flex-shrink-0"
                              :class="isOn(weights, cat, s) ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'">
                            <i class="bi" :class="s.custom ? 'bi-pencil-square' : 'bi-journal-text'"></i>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-foreground truncate" x-text="s.label"></span>
                            <span class="block text-[11px] text-muted-foreground truncate"
                                  x-text="(s.custom ? @js($w('rulebook_custom')) + ' · ' : '') + Object.keys(s.classes || {}).join(' · ')"></span>
                        </span>
                        <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
                              :class="isOn(weights, cat, s) ? 'border-primary bg-primary text-white' : 'border-gray-300'">
                            <i class="bi bi-check text-[11px]" x-show="isOn(weights, cat, s)"></i>
                        </span>
                    </button>
                </template>
                <button type="button" @click="openEditor('weights')"
                        class="m-press w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-[13px] font-bold text-muted-foreground flex items-center justify-center gap-2">
                    <i class="bi bi-plus-lg"></i>{{ $w('rulebook_make') }}
                </button>
            </div>
        </div>

        {{-- Belt system --}}
        <div class="mt-4" x-show="belts.length">
            <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground mb-2 m-0">{{ $w('rulebook_belts') }}</p>
            <div class="space-y-2">
                <template x-for="s in belts" :key="s.key">
                    <button type="button" @click="pick('belts', s)" :disabled="saving"
                            class="m-press w-full text-start rounded-xl border-2 px-3 py-2.5 flex items-center gap-3 transition-colors"
                            :class="isOn(belts, belt, s) ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'">
                        <span class="flex items-center gap-0.5 flex-shrink-0" style="width:64px;">
                            <template x-for="(b, i) in (s.ladder || []).slice(0, 8)" :key="i">
                                <span style="width:7px; height:18px; border-radius:2px; outline:1px solid rgba(30,44,79,.12);" :style="{ background: b.bg }"></span>
                            </template>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-foreground truncate" x-text="s.label"></span>
                            <span class="block text-[11px] text-muted-foreground truncate" x-text="(s.ladder || []).map(b => b.label).join(' · ')"></span>
                        </span>
                        <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
                              :class="isOn(belts, belt, s) ? 'border-primary bg-primary text-white' : 'border-gray-300'">
                            <i class="bi bi-check text-[11px]" x-show="isOn(belts, belt, s)"></i>
                        </span>
                    </button>
                </template>
                <button type="button" @click="openEditor('belts')"
                        class="m-press w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-[13px] font-bold text-muted-foreground flex items-center justify-center gap-2">
                    <i class="bi bi-plus-lg"></i>{{ $w('rulebook_make') }}
                </button>
            </div>
        </div>
    @if ($console)
                </div>
            </div>
        </div>
    </template>
    @else
    </div>
    @endif

    {{-- ===== The editor — a bottom sheet (Design Rule #8) ===== --}}
    <template x-teleport="body" data-teleport-template="true">
        <div x-show="sheet" x-cloak class="fixed inset-0 z-[75] flex items-end justify-center" style="display:none;" @keydown.escape.window="sheet = false">
            <div x-show="sheet" x-transition.opacity class="absolute inset-0 bg-black/40" @click="sheet = false"></div>
            <div x-show="sheet"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="relative w-full max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl overflow-hidden" style="max-width:640px;">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi text-xl" :class="kind === 'weights' ? 'bi-journal-bookmark-fill' : 'bi-award-fill'"></i></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight" x-text="kind === 'weights' ? @js($w('rulebook_make_weights')) : @js($w('rulebook_make_belts'))"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="sportLabels[sportRead()] || sportRead()"></p>
                        </div>
                        <button type="button" @click="sheet = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <template x-if="draft">
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $w('rulebook_name') }}</label>
                            <input type="text" x-model="draft.label" maxlength="120" placeholder="{{ $w('rulebook_name_ph') }}"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $w('rulebook_start_from') }}</label>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="s in (kind === 'weights' ? weights : belts)" :key="s.key">
                                    <button type="button" @click="startFrom = s.key; seed()" class="m-press px-2.5 py-1.5 rounded-full text-[11px] font-bold border transition-colors"
                                            :class="startFrom === s.key ? 'bg-primary text-white border-primary' : 'bg-white text-foreground border-gray-200'" x-text="s.label"></button>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- Weights: age groups, then each group's classes per gender --}}
                    <template x-if="kind === 'weights' && draft && draft.groups">
                        <div class="space-y-3">
                            <template x-for="(g, gi) in draft.groups" :key="gi">
                                <div class="rounded-2xl border border-gray-200 p-3 space-y-2.5">
                                    <div class="grid gap-2" style="grid-template-columns: 1fr 72px 72px auto;">
                                        <input type="text" x-model="g.key" maxlength="40" :placeholder="@js($w('rulebook_age_group'))"
                                               class="px-3 py-2 border border-gray-200 rounded-xl text-sm font-bold focus:ring-2 focus:ring-purple-500 outline-none min-w-0">
                                        <input type="number" x-model.number="g.min" min="0" max="120" :aria-label="@js($w('rulebook_age_from'))" :placeholder="@js($w('rulebook_age_from'))"
                                               class="px-2 py-2 border border-gray-200 rounded-xl text-sm text-center focus:ring-2 focus:ring-purple-500 outline-none">
                                        <input type="number" x-model="g.max" min="0" max="120" :aria-label="@js($w('rulebook_age_to'))" :placeholder="@js($w('rulebook_age_open'))"
                                               class="px-2 py-2 border border-gray-200 rounded-xl text-sm text-center focus:ring-2 focus:ring-purple-500 outline-none">
                                        <button type="button" @click="draft.groups.splice(gi, 1)" class="m-press w-9 h-9 rounded-xl grid place-items-center text-red-600 border border-red-200" aria-label="{{ __('shared.delete') }}"><i class="bi bi-trash3"></i></button>
                                    </div>
                                    <template x-for="gender in ['male', 'female']" :key="gender">
                                        <div>
                                            <p class="text-[11px] font-bold m-0 mb-1.5" :style="gender === 'female' ? 'color:#ec4899' : 'color:#3b82f6'"
                                               x-text="gender === 'female' ? @js($w('rulebook_female')) : @js($w('rulebook_male'))"></p>
                                            <div class="flex flex-wrap gap-1.5 items-center">
                                                <template x-for="(cl, ci) in g[gender]" :key="ci">
                                                    <span class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-1.5 py-1">
                                                        <input type="text" x-model="cl.label" maxlength="24" class="text-[12px] font-bold text-center outline-none bg-transparent" style="width:48px;">
                                                        <input type="number" x-model.number="cl.min" min="0" max="300" step="0.5" class="text-[11px] text-center outline-none bg-muted rounded" style="width:44px;" :title="@js($w('rulebook_over'))">
                                                        <span class="text-[10px] text-muted-foreground">–</span>
                                                        <input type="number" x-model.number="cl.max" min="0" max="300" step="0.5" class="text-[11px] text-center outline-none bg-muted rounded" style="width:44px;" :title="@js($w('rulebook_up_to'))">
                                                        <button type="button" @click="g[gender].splice(ci, 1)" class="text-muted-foreground" aria-label="{{ __('shared.delete') }}"><i class="bi bi-x"></i></button>
                                                    </span>
                                                </template>
                                                <button type="button" @click="addClass(g, gender)" class="m-press px-2 py-1 rounded-lg border border-dashed border-gray-300 text-[11px] font-bold text-muted-foreground">
                                                    <i class="bi bi-plus"></i> {{ $w('rulebook_add_class') }}
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <button type="button" @click="addGroup()" class="m-press w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-[13px] font-bold text-muted-foreground">
                                <i class="bi bi-plus-lg"></i> {{ $w('rulebook_add_group') }}
                            </button>
                        </div>
                    </template>

                    {{-- Belts: colours from the platform's own palette, with their grades --}}
                    <template x-if="kind === 'belts' && draft && draft.ladder">
                        <div class="space-y-2.5">
                            <template x-for="(b, bi) in draft.ladder" :key="bi">
                                <div class="rounded-2xl border border-gray-200 p-3 space-y-2">
                                    <div class="flex flex-wrap gap-1.5">
                                        <template x-for="p in palette" :key="p">
                                            <button type="button" @click="b.value = p" :aria-label="p"
                                                    class="m-press w-7 h-7 rounded-full border-2 transition-transform"
                                                    :class="b.value === p ? 'scale-110 border-primary' : 'border-gray-200'" :style="{ background: hex[p] }"></button>
                                        </template>
                                    </div>
                                    <div class="grid gap-2" style="grid-template-columns: 1fr 1.4fr auto;">
                                        <input type="text" x-model="b.label" maxlength="40" :placeholder="@js($w('rulebook_belt_label'))"
                                               class="px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none min-w-0">
                                        <input type="text" x-model="b.grades" maxlength="400" :placeholder="@js($w('rulebook_belt_grades'))"
                                               class="px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 outline-none min-w-0">
                                        <button type="button" @click="draft.ladder.splice(bi, 1)" class="m-press w-9 h-9 rounded-xl grid place-items-center text-red-600 border border-red-200" aria-label="{{ __('shared.delete') }}"><i class="bi bi-trash3"></i></button>
                                    </div>
                                </div>
                            </template>
                            <button type="button" @click="addBelt()" class="m-press w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-[13px] font-bold text-muted-foreground">
                                <i class="bi bi-plus-lg"></i> {{ $w('rulebook_add_belt') }}
                            </button>
                        </div>
                    </template>
                </div>
                </template>

                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-white" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="saveSystem()" :disabled="saving || ! draft || ! (draft.label || '').trim()"
                            class="m-press w-full py-3 rounded-xl text-white text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-50"
                            style="background: {{ $c }};">
                        <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>{{ $w('rulebook_save_system') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
