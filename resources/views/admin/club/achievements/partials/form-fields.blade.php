@php
    // Shared field styling — soft inputs that brighten and ring on focus.
    $field = 'w-full px-3.5 py-2.5 bg-gray-50/70 border border-gray-200 rounded-xl text-sm text-gray-900 placeholder-gray-400 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-colors';
    $lbl   = 'block text-[13px] font-semibold text-gray-700 mb-1.5';

    // Localised month names + year range for the date picker.
    $loc = app()->getLocale();
    $achMonths = [];
    for ($i = 1; $i <= 12; $i++) {
        $achMonths[] = ['v' => sprintf('%02d', $i), 'l' => \Illuminate\Support\Carbon::create(2000, $i, 1)->locale($loc)->translatedFormat('F')];
    }
@endphp

<div class="space-y-7" x-data="{ lang: 'en' }">

    <x-lang-toggle class="mb-4" />

    {{-- ════════ Basics ════════ --}}
    <section>
        <div class="flex items-center gap-2.5 mb-4">
            <span class="w-7 h-7 rounded-lg bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-trophy-fill text-sm"></i></span>
            <h6 class="text-sm font-bold text-gray-900 mb-0">{{ __('admin.ach_f_section_basics') }}</h6>
            <div class="flex-1 h-px bg-gradient-to-r from-gray-200 to-transparent"></div>
        </div>

        <div class="mb-4">
            <label class="{{ $lbl }}">{{ __('admin.ach_f_title') }} <span class="text-primary">*</span></label>
            <input type="text" name="title" required x-model="formData.title" x-show="lang==='en'"
                   class="{{ $field }}" placeholder="{{ __('admin.ach_f_title_ph') }}">
            <input type="text" name="translations[title][ar]" id="ach_tr_title_ar" dir="rtl" x-show="lang==='ar'" x-cloak
                   class="{{ $field }}" placeholder="العنوان بالعربية"
                   value="{{ old('translations.title.ar', data_get($achievement ?? null, 'translations.title.ar')) }}">
        </div>

        {{-- Icon — searchable dropdown --}}
        <div x-data="{
            open: false, q: '',
            emojis: [
                {e:'🏆',k:'trophy cup champion winner award'},{e:'🥇',k:'gold medal first place winner'},
                {e:'🥈',k:'silver medal second place'},{e:'🥉',k:'bronze medal third place'},
                {e:'🏅',k:'medal sports award'},{e:'🎖️',k:'medal military honor'},{e:'🎗️',k:'ribbon award cause'},
                {e:'⭐',k:'star favorite'},{e:'🌟',k:'star glowing shine'},{e:'✨',k:'sparkles shine'},
                {e:'🔥',k:'fire hot streak'},{e:'💪',k:'strength muscle power'},{e:'👑',k:'crown king queen champion'},
                {e:'💎',k:'gem diamond elite'},{e:'🚀',k:'rocket launch growth'},{e:'📈',k:'growth chart progress up'},
                {e:'🎯',k:'target bullseye dart aim goal'},{e:'🏁',k:'finish flag race checkered'},{e:'🎉',k:'party celebrate'},
                {e:'🙌',k:'celebrate hands praise'},{e:'🥋',k:'martial arts taekwondo judo karate belt'},
                {e:'🤺',k:'fencing sword'},{e:'🥊',k:'boxing glove fight'},{e:'🤼',k:'wrestling grapple'},
                {e:'🏋️',k:'weightlifting gym strong lift'},{e:'🤸',k:'gymnastics cartwheel flip'},{e:'🏃',k:'running run race athletics'},
                {e:'⚽',k:'soccer football'},{e:'🏀',k:'basketball'},{e:'🏈',k:'american football'},{e:'⚾',k:'baseball'},
                {e:'🎾',k:'tennis'},{e:'🏐',k:'volleyball'},{e:'🏉',k:'rugby'},{e:'🏸',k:'badminton'},{e:'🏓',k:'table tennis ping pong'},
                {e:'🥅',k:'goal net'},{e:'⛳',k:'golf flag'},{e:'🏊',k:'swimming swim'},{e:'🏄',k:'surfing surf'},
                {e:'🚴',k:'cycling bike'},{e:'🏇',k:'horse racing'},{e:'⛸️',k:'ice skating'},{e:'🛹',k:'skateboard'},
                {e:'🥏',k:'frisbee disc'},{e:'🎽',k:'running shirt sash'},{e:'🤾',k:'handball'},{e:'🧗',k:'climbing'}
            ],
            filtered() { const t = this.q.trim().toLowerCase(); return t ? this.emojis.filter(x => x.k.includes(t)) : this.emojis; }
        }" @click.outside="open = false" class="relative">
            <label class="{{ $lbl }}">{{ __('admin.ach_f_icon') }}</label>
            <input type="hidden" name="type_icon" :value="formData.type_icon">
            <button type="button" @click="open = !open" class="{{ $field }} flex items-center gap-3 text-left">
                <span class="text-2xl leading-none" x-text="formData.type_icon || '🏆'"></span>
                <span class="flex-1 text-sm" :class="formData.type_icon ? 'text-gray-500' : 'text-gray-400'" x-text="formData.type_icon ? '{{ __('admin.ach_f_tap_change') }}' : '{{ __('admin.ach_f_choose_icon') }}'"></span>
                <i class="bi bi-chevron-down text-xs text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''"></i>
            </button>
            <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                 class="absolute left-0 right-0 z-40 mt-1.5 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden">
                <div class="p-2 border-b border-gray-50">
                    <div class="relative">
                        <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" x-model="q" @click.stop placeholder="{{ __('admin.ach_f_search_icons') }}" autocomplete="off"
                               class="w-full pl-7 pr-2 py-1.5 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-primary focus:outline-none">
                    </div>
                </div>
                <div class="max-h-56 overflow-y-auto p-2">
                    <div class="grid grid-cols-8 gap-1">
                        <template x-for="x in filtered()" :key="x.e">
                            <button type="button" @click="formData.type_icon = x.e; open = false; q = ''" :title="x.k"
                                    :class="formData.type_icon === x.e ? 'bg-accent ring-2 ring-primary' : 'hover:bg-gray-100'"
                                    class="aspect-square rounded-lg text-xl grid place-items-center transition-colors" x-text="x.e"></button>
                        </template>
                    </div>
                    <div x-show="!filtered().length" class="text-center text-xs text-gray-400 py-4">{{ __('admin.ach_f_no_icons') }}</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ════════ When & where ════════ --}}
    <section>
        <div class="flex items-center gap-2.5 mb-4">
            <span class="w-7 h-7 rounded-lg bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-geo-alt-fill text-sm"></i></span>
            <h6 class="text-sm font-bold text-gray-900 mb-0">{{ __('admin.ach_f_section_when') }}</h6>
            <div class="flex-1 h-px bg-gradient-to-r from-gray-200 to-transparent"></div>
        </div>

        {{-- Date — localised day / month / year picker (reactive to formData) --}}
        <div class="mb-4">
            <label class="{{ $lbl }}">{{ __('admin.ach_f_date') }}</label>
            <input type="hidden" name="achievement_date" :value="formData.achievement_date">
            <div class="grid grid-cols-[0.8fr_1.4fr_1fr] gap-2"
                 x-data="{
                    d: '', m: '', y: '',
                    parse(v) { if (v && /^\d{4}-\d{2}-\d{2}$/.test(v)) { const p = v.split('-'); this.y = p[0]; this.m = p[1]; this.d = p[2]; } else { this.y=''; this.m=''; this.d=''; } },
                    write() { formData.achievement_date = (this.d && this.m && /^\d{4}$/.test(this.y)) ? (this.y + '-' + this.m + '-' + this.d) : ''; }
                 }"
                 x-init="parse(formData.achievement_date); $watch('formData.achievement_date', v => parse(v))">
                <x-select-menu model="d" change="write()" :placeholder="__('admin.ach_f_day')"
                    :options="collect(range(1, 31))->map(fn ($dd) => ['value' => sprintf('%02d', $dd), 'label' => (string) $dd])->all()" />
                <x-select-menu model="m" change="write()" :placeholder="__('admin.ach_f_month')"
                    :options="collect($achMonths)->map(fn ($mo) => ['value' => $mo['v'], 'label' => $mo['l']])->all()" />
                <input type="text" inputmode="numeric" maxlength="4" x-model="y"
                       @input="y = y.replace(/\D/g, '').slice(0, 4); write()"
                       placeholder="{{ __('admin.ach_f_year') }}" class="{{ $field }} text-center">
            </div>
        </div>

        <div class="mb-4">
            <label class="{{ $lbl }}">{{ __('admin.ach_f_location') }}</label>
            <input type="text" name="location" x-model="formData.location" x-show="lang==='en'" class="{{ $field }}" placeholder="{{ __('admin.ach_f_location_ph') }}">
            <input type="text" name="translations[location][ar]" id="ach_tr_location_ar" dir="rtl" x-show="lang==='ar'" x-cloak class="{{ $field }}" placeholder="الموقع بالعربية"
                   value="{{ old('translations.location.ar', data_get($achievement ?? null, 'translations.location.ar')) }}">
        </div>
        <div>
            <label class="{{ $lbl }}">{{ __('admin.ach_f_category') }} <span class="font-normal text-gray-400">— {{ __('admin.ach_f_category_hint') }}</span></label>
            <input type="text" name="category" x-model="formData.category" class="{{ $field }}" placeholder="{{ __('admin.ach_f_category_ph') }}">
        </div>
    </section>

    {{-- ════════ Story ════════ --}}
    <section>
        <div class="flex items-center gap-2.5 mb-4">
            <span class="w-7 h-7 rounded-lg bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-card-text text-sm"></i></span>
            <h6 class="text-sm font-bold text-gray-900 mb-0">{{ __('admin.ach_f_section_story') }}</h6>
            <div class="flex-1 h-px bg-gradient-to-r from-gray-200 to-transparent"></div>
        </div>
        <textarea name="description" rows="3" x-model="formData.description" x-show="lang==='en'" class="{{ $field }} resize-none"
                  placeholder="{{ __('admin.ach_f_story_ph') }}"></textarea>
        <textarea name="translations[description][ar]" id="ach_tr_description_ar" dir="rtl" rows="3" x-show="lang==='ar'" x-cloak class="{{ $field }} resize-none"
                  placeholder="القصة بالعربية">{{ old('translations.description.ar', data_get($achievement ?? null, 'translations.description.ar')) }}</textarea>
    </section>

    {{-- ════════ Athletes & awards ════════ --}}
    <section x-data="{
        athletes: [{ name: '', role: '', user_id: null }, { name: '', role: '', user_id: null }],
        awards: ['Gold','Silver','Bronze','Trophy','Participation'],
        activeRow: null, q: '', results: [], searching: false, _t: null,
        searchUrl: '{{ route('admin.club.members.search', $club->slug) }}',
        norm(p) { return { name: p.name || '', role: p.role || '', user_id: p.user_id ?? null }; },
        init() {
            const parse = val => { try { const p = JSON.parse(val || '[]'); return p.length ? p.map(x => this.norm(x)) : [this.norm({}), this.norm({})]; } catch(e) { return [this.norm({}), this.norm({})]; } };
            this.athletes = parse(formData.athletes);
            this.$watch('formData.athletes', val => { this.athletes = parse(val); });
        },
        add() { this.athletes.push(this.norm({})); this.sync(); },
        remove(i) { this.athletes.splice(i, 1); this.sync(); },
        sync() { formData.athletes = JSON.stringify(this.athletes.filter(a => a.name || a.role).map(a => this.norm(a))); },
        openRow(i) { this.activeRow = (this.activeRow === i ? null : i); this.q = ''; this.results = []; },
        doSearch() {
            clearTimeout(this._t);
            const term = this.q.trim();
            if (term.length < 2) { this.results = []; this.searching = false; return; }
            this.searching = true;
            this._t = setTimeout(async () => {
                try {
                    const res = await fetch(this.searchUrl + '?club_only=1&query=' + encodeURIComponent(term), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    const data = await res.json();
                    this.results = (data.users || []).map(u => ({ id: u.id, name: u.name || u.full_name, photo: u.profile_picture || null }));
                } catch (e) { this.results = []; }
                this.searching = false;
            }, 250);
        },
        pick(i, m) { this.athletes[i].name = m.name; this.athletes[i].user_id = m.id; this.activeRow = null; this.q = ''; this.results = []; this.sync(); },
        awardOptions(ath) { const o = this.awards.slice(); if (ath.role && !o.includes(ath.role)) o.unshift(ath.role); return o; },
        awardEmoji(r) { r = (r || '').toLowerCase(); let m = ''; if (r.includes('gold')) m += '🥇'; if (r.includes('silver')) m += '🥈'; if (r.includes('bronze')) m += '🥉'; if (m) return m; if (r.includes('trophy')) return '🏆'; if (r.includes('none')) return ''; return '🏅'; },
        get tally() { let g=0,s=0,b=0; this.athletes.forEach(a => { const r=(a.role||'').toLowerCase(); if(r.includes('gold'))g++; if(r.includes('silver'))s++; if(r.includes('bronze'))b++; }); return { g, s, b }; }
    }" x-init="init()">
        <div class="flex items-center gap-2.5 mb-1">
            <span class="w-7 h-7 rounded-lg bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-people-fill text-sm"></i></span>
            <h6 class="text-sm font-bold text-gray-900 mb-0">{{ __('admin.ach_f_section_athletes') }}</h6>
            <div class="flex-1 h-px bg-gradient-to-r from-gray-200 to-transparent"></div>
            <div class="flex items-center gap-2 text-[13px] font-bold flex-shrink-0">
                <span class="inline-flex items-center gap-0.5" title="Gold">🥇<span class="tabular-nums text-amber-600" x-text="tally.g"></span></span>
                <span class="inline-flex items-center gap-0.5" title="Silver">🥈<span class="tabular-nums text-gray-500" x-text="tally.s"></span></span>
                <span class="inline-flex items-center gap-0.5" title="Bronze">🥉<span class="tabular-nums text-orange-700" x-text="tally.b"></span></span>
            </div>
        </div>
        <p class="text-xs text-gray-400 mb-3 ltr:ml-9 rtl:mr-9">{{ __('admin.ach_f_athletes_hint') }}</p>

        <input type="hidden" name="athletes" :value="formData.athletes">
        <div class="space-y-2.5">
            <template x-for="(ath, i) in athletes" :key="i">
                <div class="rounded-xl border border-gray-200 bg-gray-50/50 p-2.5 space-y-2 transition-colors" :class="ath.user_id ? 'border-primary/30 bg-accent/20' : ''">
                    {{-- Member picker --}}
                    <div class="relative" @click.outside="if (activeRow === i) activeRow = null">
                        <button type="button" @click="openRow(i)"
                                class="w-full flex items-center gap-2.5 px-2.5 py-2 bg-white border border-gray-200 rounded-lg text-left hover:border-gray-300 transition-colors">
                            <span class="w-8 h-8 rounded-full bg-accent text-primary grid place-items-center overflow-hidden flex-shrink-0">
                                <i class="bi bi-person text-sm" x-show="!ath.user_id"></i>
                                <i class="bi bi-person-check-fill text-sm" x-show="ath.user_id" x-cloak></i>
                            </span>
                            <span class="flex-1 min-w-0 text-sm truncate" :class="ath.user_id ? 'font-medium text-gray-900' : 'text-gray-400'" x-text="ath.name || '{{ __('admin.ach_f_select_member') }}'"></span>
                            <i class="bi bi-chevron-down text-xs text-gray-400 flex-shrink-0 transition-transform" :class="activeRow === i ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="activeRow === i" x-cloak
                             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                             class="absolute left-0 right-0 z-40 mt-1.5 bg-white border border-gray-200 rounded-xl shadow-xl overflow-hidden">
                            <div class="p-2 border-b border-gray-50">
                                <div class="relative">
                                    <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                                    <input type="text" x-model="q" @input="doSearch()" @click.stop placeholder="{{ __('admin.ach_f_search_members') }}" autocomplete="off"
                                           class="w-full pl-7 pr-2 py-1.5 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:bg-white focus:border-primary focus:outline-none">
                                </div>
                            </div>
                            <div class="max-h-56 overflow-y-auto">
                                <template x-for="m in results" :key="m.id">
                                    <button type="button" @click="pick(i, m)" class="w-full flex items-center gap-2.5 px-3 py-2 hover:bg-accent/40 text-left transition-colors">
                                        <span class="w-7 h-7 rounded-full bg-accent text-primary grid place-items-center overflow-hidden flex-shrink-0">
                                            <template x-if="m.photo"><img :src="m.photo" class="w-7 h-7 object-cover"></template>
                                            <template x-if="!m.photo"><i class="bi bi-person text-xs"></i></template>
                                        </span>
                                        <span class="text-sm text-gray-800 truncate" x-text="m.name"></span>
                                    </button>
                                </template>
                                <div x-show="searching" class="px-3 py-4 text-xs text-gray-400 text-center"><i class="bi bi-arrow-repeat animate-spin"></i></div>
                                <div x-show="!searching && q.trim().length < 2" class="px-3 py-4 text-xs text-gray-400 text-center">{{ __('admin.ach_f_search_members') }}</div>
                                <div x-show="!searching && q.trim().length >= 2 && !results.length" class="px-3 py-4 text-xs text-gray-400 text-center">{{ __('admin.ach_f_no_members') }}</div>
                            </div>
                        </div>
                    </div>
                    {{-- Award + remove --}}
                    <div class="flex items-center gap-2 ltr:pl-1 rtl:pr-1">
                        <span class="text-base leading-none flex-shrink-0" x-show="ath.role" x-text="awardEmoji(ath.role)"></span>
                        <select x-model="ath.role" @change="sync()"
                                class="flex-1 px-3 py-2 ach-select cursor-pointer bg-white border border-gray-200 rounded-lg text-sm text-gray-800 focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none transition-colors">
                            <option value="">{{ __('admin.ach_f_choose_award') }}</option>
                            <template x-for="opt in awardOptions(ath)" :key="opt">
                                <option :value="opt" x-text="awardEmoji(opt) + '  ' + opt"></option>
                            </template>
                        </select>
                        <button type="button" @click="remove(i)" title="{{ __('admin.ach_delete') }}"
                                class="w-8 h-8 rounded-lg grid place-items-center text-gray-400 hover:text-red-500 hover:bg-red-50 transition-colors flex-shrink-0">
                            <i class="bi bi-x-lg text-xs"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>
        <button type="button" @click="add()"
                class="mt-2.5 w-full flex items-center justify-center gap-1.5 py-2 rounded-xl border border-dashed border-gray-300 text-sm font-medium text-gray-500 hover:border-primary hover:text-primary hover:bg-accent/20 transition-colors">
            <i class="bi bi-plus-lg"></i> {{ __('admin.ach_f_add_athlete') }}
        </button>
    </section>

    {{-- ════════ Status ════════ --}}
    <section>
        <div class="flex items-center gap-2.5 mb-4">
            <span class="w-7 h-7 rounded-lg bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-toggle-on text-sm"></i></span>
            <h6 class="text-sm font-bold text-gray-900 mb-0">{{ __('admin.ach_f_section_status') }}</h6>
            <div class="flex-1 h-px bg-gradient-to-r from-gray-200 to-transparent"></div>
        </div>
        <label class="{{ $lbl }}">{{ __('admin.ach_f_visibility') }} <span class="font-normal text-gray-400">— {{ __('admin.ach_f_status_hint') }}</span></label>
        <x-select-menu model="formData.status" name="status"
            :options="[
                ['value' => 'active', 'label' => __('admin.ach_f_active')],
                ['value' => 'inactive', 'label' => __('admin.ach_f_inactive')],
            ]" />
    </section>

    {{-- ════════ Gallery ════════ --}}
    <section>
        <div class="flex items-center gap-2.5 mb-1">
            <span class="w-7 h-7 rounded-lg bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-images text-sm"></i></span>
            <h6 class="text-sm font-bold text-gray-900 mb-0">{{ __('admin.ach_f_section_gallery') }}</h6>
            <div class="flex-1 h-px bg-gradient-to-r from-gray-200 to-transparent"></div>
        </div>
        <p class="text-xs text-gray-400 mb-3 ltr:ml-9 rtl:mr-9">{{ __('admin.ach_f_gallery_hint') }}</p>

        <div id="achievementExistingPreviews" class="flex flex-wrap gap-2 mb-2"></div>
        <input type="hidden" name="keep_extra_images" id="keepExtraImagesInput" value="[]">
        <div id="achievementNewPreviews" class="flex flex-wrap gap-2 mb-2"></div>
        <div id="achievementBase64Inputs"></div>

        <label class="group block cursor-pointer">
            <div class="flex flex-col items-center justify-center gap-1.5 py-7 px-4 rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50/50 text-center group-hover:border-primary group-hover:bg-accent/20 transition-colors">
                <span class="w-11 h-11 rounded-full bg-white border border-gray-200 grid place-items-center text-primary group-hover:scale-105 transition-transform"><i class="bi bi-cloud-arrow-up text-xl"></i></span>
                <span class="text-sm font-semibold text-gray-700">{{ __('admin.ach_f_add_photos') }}</span>
                <span class="text-xs text-gray-400">{{ __('admin.ach_f_browse_hint') }}</span>
            </div>
            <input type="file" id="achievementImagesInput" multiple accept="image/*" class="hidden" onchange="handleAchievementImages(this)">
        </label>
    </section>
</div>
