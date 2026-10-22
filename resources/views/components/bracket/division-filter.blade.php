{{-- ===== The division menu's quick filter — ONE copy =====

     Name search, a gender tab, age and weight ranges (owner, 2026-10-17). Used
     by the main board's division menu (x-draw-stage) and the second board's
     picker (x-draw-split), so the two cannot drift apart. The host's Alpine
     scope supplies `flt`, `fltOn` and `fltClear()`; the rows it filters go
     through DrawDivisionLook.filter (components/bracket/division-look).

     @include('components.bracket.division-filter', ['count' => '<Alpine expression for "shown / total">', 'personUrl' => null])

     `personUrl` (optional — the organiser's draw menu passes it) adds a
     switch beside the search: ⚖ searches divisions, 🧍 searches PEOPLE by
     name, phone, email or CPR and narrows the list to the divisions they are
     in. The server answers names and division ids only; the keys typed here
     are never sent back. Without it the filter is exactly as before. --}}
@php
    $personUrl = $personUrl ?? null;
@endphp
{{-- Quick filter (owner, 2026-10-17): name, gender, age and weight
     ranges. Sticky, so it stays while the list scrolls. --}}
<div class="sticky top-0 z-10 bg-white border-b border-gray-100 px-2.5 pt-1.5 pb-2.5 space-y-2" @click.stop>
    <div class="flex items-center" style="gap:6px">
    <div class="relative" style="flex:1 1 auto;min-width:0">
        <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
        @if($personUrl)
            <input type="search" x-model="flt.q" autocomplete="off"
                   :placeholder="flt.mode === 'person' ? @js(__('events::events.div_filter_person_search')) : @js(__('events::events.div_filter_search'))"
                   :inputmode="flt.mode === 'person' ? 'search' : 'text'"
                   x-init="$watch('flt.q', v => { if (flt.mode !== 'person') return; clearTimeout($el._t); $el._t = setTimeout(() => window.DrawPersonSearch.run(flt, @js($personUrl)), 300); })"
                   class="w-full ps-8 pe-3 py-2 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        @else
            <input type="search" x-model="flt.q" autocomplete="off" placeholder="{{ __('events::events.div_filter_search') }}"
                   class="w-full ps-8 pe-3 py-2 border border-gray-200 rounded-lg text-xs focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        @endif
    </div>
    @if($personUrl)
        {{-- ⚖ divisions · 🧍 a person. Icons only; the words ride in title /
             aria-label. Bootstrap Icons has no scale, so it is drawn here. --}}
        <div class="flex shrink-0" role="group" aria-label="{{ __('events::events.div_filter_search_by') }}"
             style="padding:2px;border-radius:9px;background:#f1f5f9;border:1px solid #e5e7eb;gap:2px">
            <template x-for="m in [['div', @js(__('events::events.div_filter_mode_division'))], ['person', @js(__('events::events.div_filter_mode_person'))]]" :key="m[0]">
                <button type="button" :aria-pressed="flt.mode === m[0]" :title="m[1]" :aria-label="m[1]"
                        @click="if (flt.mode !== m[0]) { flt.mode = m[0]; flt.q = ''; flt.ids = null; flt.people = null; }"
                        class="grid place-items-center transition-colors"
                        :style="'width:30px;height:28px;border-radius:7px;' + (flt.mode === m[0] ? 'background:#6d4bd8;color:#fff;box-shadow:0 1px 4px #6d4bd840;' : 'background:transparent;color:#64748b;')">
                    <svg x-show="m[0] === 'div'" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <rect x="1.5" y="1.5" width="13" height="13" rx="3" stroke="currentColor" stroke-width="1.4"/>
                        <path d="M4.6 6.6a3.4 3.4 0 0 1 6.8 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                        <path d="M8 6.8 9.3 4.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                        <path d="M4.5 11.5h7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                    </svg>
                    <i x-show="m[0] === 'person'" class="bi bi-person-standing" style="font-size:15px"></i>
                </button>
            </template>
        </div>
    @endif
    </div>
    {{-- One row (owner, 2026-10-17): gender · age range · weight range. All /
         Male / Female only — coloured, the chosen one filled. Compact and on
         ONE line, phone included (owner, 2026-10-18: "too big … one line").
         Sizes are inline: the Tailwind bundle is prebuilt and had no rule for
         the arbitrary text sizes this row used, so it rendered full-size. --}}
    @php
        $fltInput = 'flex:1 1 0;width:0;padding:3px 2px;font-size:11px;line-height:1.2;border:1px solid #e5e7eb;border-radius:6px;text-align:center;min-width:0;';
        $fltLabel = 'font-size:9px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#9ca3af;margin-inline-end:1px;';
    @endphp
    {{-- Spread to the menu's full width (owner, 2026-10-18): the gender tabs
         keep their size, the two ranges share the rest and their boxes grow. --}}
    <div class="flex items-center w-full" style="gap:5px;flex-wrap:nowrap;" x-show="flt.mode !== 'person'">
        <div class="flex shrink-0" style="gap:3px" role="group" aria-label="{{ __('events::events.div_filter_all') }}">
            <template x-for="g in [['all', @js(__('events::events.div_filter_all')), '#6d4bd8', '#f2effe'], ['male', @js(__('events::events.div_filter_male')), '#2563eb', '#eff6ff'], ['female', @js(__('events::events.div_filter_female')), '#db2777', '#fdf2f8']]" :key="g[0]">
                <button type="button" @click="flt.g = g[0]" :aria-pressed="flt.g === g[0]"
                        class="font-bold transition-colors whitespace-nowrap"
                        :style="'padding:3px 7px;border-radius:6px;font-size:11px;line-height:1.2;' + (flt.g === g[0] ? 'background:' + g[2] + ';color:#fff;border:1px solid ' + g[2] + ';' : 'background:' + g[3] + ';color:' + g[2] + ';border:1px solid ' + g[2] + '33;')"
                        x-text="g[1]"></button>
            </template>
        </div>
        <span class="shrink-0" style="width:1px;height:16px;background:#e5e7eb"></span>
        <div class="flex items-center" style="gap:2px;flex:1 1 0;min-width:0">
            <span style="{{ $fltLabel }}">{{ __('events::events.div_filter_age') }}</span>
            <input type="text" inputmode="numeric" x-model="flt.a1" placeholder="{{ __('events::events.div_filter_min') }}" aria-label="{{ __('events::events.div_filter_age') }} {{ __('events::events.div_filter_min') }}" class="focus:ring-2 focus:ring-purple-500 focus:border-transparent" style="{{ $fltInput }}">
            <span style="font-size:10px;color:#d1d5db">–</span>
            <input type="text" inputmode="numeric" x-model="flt.a2" placeholder="{{ __('events::events.div_filter_max') }}" aria-label="{{ __('events::events.div_filter_age') }} {{ __('events::events.div_filter_max') }}" class="focus:ring-2 focus:ring-purple-500 focus:border-transparent" style="{{ $fltInput }}">
        </div>
        <span class="shrink-0" style="width:1px;height:16px;background:#e5e7eb"></span>
        <div class="flex items-center" style="gap:2px;flex:1 1 0;min-width:0">
            {{-- "kg" alone: the unit is the label, and it is the same word in
                 every language this platform speaks. The full label stays for
                 screen readers. --}}
            <span style="{{ $fltLabel }}text-transform:none;" aria-hidden="true">kg</span>
            <input type="text" inputmode="decimal" x-model="flt.w1" placeholder="{{ __('events::events.div_filter_min') }}" aria-label="{{ __('events::events.div_filter_weight') }} {{ __('events::events.div_filter_min') }}" class="focus:ring-2 focus:ring-purple-500 focus:border-transparent" style="{{ $fltInput }}">
            <span style="font-size:10px;color:#d1d5db">–</span>
            <input type="text" inputmode="decimal" x-model="flt.w2" placeholder="{{ __('events::events.div_filter_max') }}" aria-label="{{ __('events::events.div_filter_weight') }} {{ __('events::events.div_filter_max') }}" class="focus:ring-2 focus:ring-purple-500 focus:border-transparent" style="{{ $fltInput }}">
        </div>
    </div>
    @if($personUrl)
        {{-- Person mode: who the search found, and where — tapping a division
             row below opens it. Names only; nothing typed is echoed back. --}}
        <div x-show="flt.mode === 'person'" x-cloak style="font-size:11px;line-height:1.35">
            <p x-show="flt.busy" style="color:#94a3b8">{{ __('events::events.div_filter_person_busy') }}</p>
            <p x-show="! flt.busy && flt.people === null" style="color:#94a3b8">{{ __('events::events.div_filter_person_hint') }}</p>
            <p x-show="! flt.busy && Array.isArray(flt.people) && flt.people.length === 0" style="color:#94a3b8">{{ __('events::events.div_filter_person_none') }}</p>
            <div x-show="! flt.busy && Array.isArray(flt.people) && flt.people.length" class="flex flex-wrap" style="gap:4px">
                <template x-for="p in (flt.people || [])" :key="p.name + '|' + p.divisions.join(',')">
                    <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:999px;background:#f2effe;color:#4c1d95;font-weight:700">
                        <i class="bi bi-person-standing"></i>
                        <span x-text="p.name"></span>
                        <span x-show="p.club" style="font-weight:500;color:#6b7280" x-text="'· ' + p.club"></span>
                    </span>
                </template>
            </div>
        </div>
        @once
        <script>
        /* The person search's one request. Latest wins: a reply to an older
           query is dropped. Reads only names and division ids back. */
        window.DrawPersonSearch = window.DrawPersonSearch || {
            seq: 0,
            async run(flt, url) {
                const q = String(flt.q || '').trim();
                const my = ++this.seq;
                if (q.length < 2) { flt.ids = null; flt.people = null; flt.busy = false; return; }
                flt.busy = true;
                try {
                    const res = await fetch(url + '?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    const data = await res.json().catch(() => ({}));
                    if (my !== this.seq) return;
                    const people = res.ok && Array.isArray(data.people) ? data.people : [];
                    flt.people = people;
                    flt.ids = people.flatMap(p => p.divisions || []);
                    if (! res.ok && res.status === 429) window.showToast?.('warning', @js(__('events::events.div_filter_person_slow')));
                } catch (e) {
                    if (my === this.seq) { flt.people = []; flt.ids = []; }
                } finally {
                    if (my === this.seq) flt.busy = false;
                }
            },
        };
        </script>
        @endonce
    @endif
    <div class="flex items-center justify-between text-[0.65rem] font-bold text-gray-500">
        <span x-text="{{ $count }}"></span>
        <button type="button" x-show="fltOn" @click="fltClear()" class="text-primary hover:underline">{{ __('events::events.div_filter_clear') }}</button>
    </div>
</div>
