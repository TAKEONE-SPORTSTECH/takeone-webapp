{{-- The new Explore page body — shared by explore/desktop and explore/mobile,
     which differ only in the layout they sit in. --}}
@php
    $payload = $explore + [
        'l10n' => trans('ui::explore'),
        'leaflet' => ['css' => asset('vendor/leaflet/leaflet.css'), 'js' => asset('vendor/leaflet/leaflet.js')],
    ];
@endphp
<script type="application/json" id="t1-explore-data">@json($payload)</script>
@include('ui::explore.partials.runtime')

<div class="t1 t1-page" x-data="t1Explore()">
    {{-- Hero band: full-bleed, content BELOW it (owner's decision, NEW-UI.md). --}}
    <div class="t1-bleed">
        <header class="t1-hero">
            <span class="t1-c1"></span><span class="t1-c2"></span>
            <div class="t1-hero-in">
                <span class="t1-chipw"><i class="bi bi-compass"></i>{{ __('ui::explore.eyebrow') }}</span>
                <h1>{{ __('ui::explore.title') }}</h1>
                <p class="t1-sub">{{ __('ui::explore.sub') }}</p>
                <button type="button" class="t1-loc" :class="{ off: locState !== 'on' }" @click="locate(false)">
                    <span class="t1-ldot"></span>
                    <span x-text="locState === 'on' ? @js(__('ui::explore.near_you')) : (locState === 'asking' ? @js(__('ui::explore.loading')) : @js(__('ui::explore.use_location')))"></span>
                </button>
            </div>
        </header>
    </div>

    {{-- Search: What · Where --}}
    <form class="t1-search" role="search" @submit.prevent>
        <div class="t1-sf">
            <span class="t1-sic"><i class="bi bi-search"></i></span>
            <div style="min-width:0;flex:1">
                <label for="t1q">{{ __('ui::explore.what') }}</label>
                <input id="t1q" x-model.debounce.200ms="q" autocomplete="off"
                       :placeholder="tab === 'activities' ? @js(__('ui::explore.what_ph_acts', ['n' => count($explore['activities'])])) : @js(__('ui::explore.what_ph'))">
            </div>
        </div>
        <div class="t1-sf" @click.outside="radiusOpen = false">
            <span class="t1-sic"><i class="bi bi-geo-alt"></i></span>
            <div>
                <span class="t1-flab">{{ __('ui::explore.where') }}</span>
                <button type="button" class="t1-val" @click="radiusOpen = !radiusOpen" :aria-expanded="radiusOpen">
                    <span x-text="radius === 'any' ? @js(__('ui::explore.any_distance')) : @js(__('ui::explore.within', ['km' => '#'])).replace('#', radius)"></span>
                    <i class="bi bi-chevron-down" style="font-size:11px;transition:transform .2s" :style="radiusOpen ? 'transform:rotate(180deg)' : ''"></i>
                </button>
            </div>
            <div class="t1-dd" x-show="radiusOpen" x-cloak x-transition.opacity>
                <template x-for="r in ['any', 5, 10, 25, 50]" :key="r">
                    <button type="button" :class="{ on: radius == r }" @click="radius = r; radiusOpen = false; if (r !== 'any' && !pos) locate(false)">
                        <span x-text="r === 'any' ? @js(__('ui::explore.any_distance')) : @js(__('ui::explore.within', ['km' => '#'])).replace('#', r)"></span>
                        <i class="bi bi-check-lg" x-show="radius == r"></i>
                    </button>
                </template>
            </div>
        </div>
        <button class="t1-go" type="submit" @click="if (tab === 'all') go(q ? 'clubs' : 'all')"><i class="bi bi-search"></i>{{ __('ui::explore.search') }}</button>
    </form>

    {{-- What to explore --}}
    <nav class="t1-verts t1-bleed" style="margin-top:18px" x-ref="verts" aria-label="{{ __('ui::explore.eyebrow') }}">
        <div class="t1-verts-in" role="tablist">
            <template x-for="v in tabs()" :key="v.k">
                <button type="button" class="t1-vt" role="tab" :class="{ on: tab === v.k }" :aria-selected="tab === v.k" @click="go(v.k)">
                    <i class="bi" :class="v.icon"></i><span x-text="v.label"></span><span class="t1-n" x-text="v.n"></span>
                </button>
            </template>
        </div>
    </nav>

    {{-- Activity chips (every tab but Activities) · group chips (Activities) --}}
    <div class="t1-chips" x-show="tab !== 'activities'">
        <button type="button" class="t1-sp" :class="{ on: !act }" @click="act = null"><span class="t1-spi" style="--c:var(--t1-primary)"><i class="bi bi-star-fill"></i></span>{{ __('ui::explore.all_activities') }}</button>
        <template x-for="a in chipActs" :key="a.uuid">
            <button type="button" class="t1-sp" :class="{ on: act === a.uuid }" @click="act = (act === a.uuid ? null : a.uuid)"><span class="t1-spi" :style="'--c:' + a.color" x-text="a.initials"></span><span x-text="a.name"></span></button>
        </template>
    </div>
    <div class="t1-chips" x-show="tab === 'activities'" x-cloak>
        <button type="button" class="t1-sp plain" :class="{ on: !grp }" @click="grp = null">{{ __('ui::explore.all_groups') }}</button>
        <template x-for="g in D.groups" :key="g.key">
            <button type="button" class="t1-sp" :class="{ on: grp === g.key }" @click="grp = (grp === g.key ? null : g.key)"><span class="t1-spi" :style="'--c:' + g.color"></span><span x-text="g.label"></span></button>
        </template>
    </div>

    <main class="t1-body">
        {{-- ===== For you ===== --}}
        <div x-show="tab === 'all'">
            {{-- Map of everything listed below: clubs, events and coaches together. --}}
            <div class="t1-vhead">
                <div><p x-text="pos ? @js(__('ui::explore.sorted')) : @js(__('ui::explore.no_location'))"></p></div>
                <button type="button" class="t1-mswitch" role="switch" :aria-checked="map" @click="map = !map"><i class="bi bi-map"></i>{{ __('ui::explore.map') }}<span class="t1-trk"></span></button>
            </div>
            <div class="t1-split t1-allmap" :class="{ 'with-map': map }">
            <div class="t1-allrails">
            <section class="t1-rail" x-show="evs.length">
                <div class="t1-rail-h"><div><h3><span class="t1-live"></span>{{ __('ui::explore.rail_events') }}</h3><p>{{ __('ui::explore.rail_events_sub') }}</p></div>
                    <button type="button" class="t1-more" @click="go('events')"><span x-text="t('see_all', evs.length)"></span><i class="bi bi-chevron-right rtl:rotate-180"></i></button></div>
                <div class="t1-track"><template x-for="e in evs.slice(0, 4)" :key="e.key">@include('ui::explore.partials.card-event')</template></div>
            </section>
            <section class="t1-rail" x-show="clubs.length">
                <div class="t1-rail-h"><div><h3>{{ __('ui::explore.rail_clubs') }}</h3><p>{{ __('ui::explore.rail_clubs_sub') }}</p></div>
                    <button type="button" class="t1-more" @click="go('clubs')"><span x-text="t('see_all', clubs.length)"></span><i class="bi bi-chevron-right rtl:rotate-180"></i></button></div>
                <div class="t1-track"><template x-for="c in clubs.slice(0, 4)" :key="c.key">@include('ui::explore.partials.card-club')</template></div>
            </section>
            <section class="t1-rail">
                <div class="t1-rail-h"><div><h3>{{ __('ui::explore.rail_acts') }}</h3><p>{{ __('ui::explore.rail_acts_sub') }}</p></div>
                    <button type="button" class="t1-more" @click="go('activities')"><span x-text="t('all_n_activities', D.activities.length)"></span><i class="bi bi-chevron-right rtl:rotate-180"></i></button></div>
                <div class="t1-track acts"><template x-for="a in popular" :key="a.uuid">@include('ui::explore.partials.tile-activity')</template></div>
            </section>
            <section class="t1-rail" x-show="coaches.length">
                <div class="t1-rail-h"><div><h3>{{ __('ui::explore.rail_coaches') }}</h3><p>{{ __('ui::explore.rail_coaches_sub') }}</p></div>
                    <button type="button" class="t1-more" @click="go('coaches')"><span x-text="t('see_all', coaches.length)"></span><i class="bi bi-chevron-right rtl:rotate-180"></i></button></div>
                <div class="t1-track tall"><template x-for="c in coaches.slice(0, 5)" :key="c.key">@include('ui::explore.partials.card-coach')</template></div>
            </section>
            <section class="t1-rail" x-show="feds.length">
                <div class="t1-rail-h"><div><h3>{{ __('ui::explore.rail_feds') }}</h3><p>{{ __('ui::explore.rail_feds_sub') }}</p></div>
                    <button type="button" class="t1-more" @click="go('federations')"><span x-text="t('see_all', feds.length)"></span><i class="bi bi-chevron-right rtl:rotate-180"></i></button></div>
                <div class="t1-track"><template x-for="f in feds.slice(0, 4)" :key="f.key">@include('ui::explore.partials.card-federation')</template></div>
            </section>
            <section class="t1-rail" x-show="products.length">
                <div class="t1-rail-h"><div><h3>{{ __('ui::explore.rail_shop') }}</h3><p>{{ __('ui::explore.rail_shop_sub') }}</p></div>
                    <button type="button" class="t1-more" @click="go('shops')"><span x-text="t('see_all', products.length)"></span><i class="bi bi-chevron-right rtl:rotate-180"></i></button></div>
                <div class="t1-track"><template x-for="p in products.slice(0, 4)" :key="p.key">@include('ui::explore.partials.card-product')</template></div>
            </section>
            <template x-if="!evs.length && !clubs.length && eventsLoaded">
                <div class="t1-empty"><b>{{ __('ui::explore.none_title') }}</b>{{ __('ui::explore.none_hint') }}</div>
            </template>
            </div>
                <div class="t1-mapbox"><div class="t1-lmap" x-ref="lmap_all"></div></div>
            </div>

            <section class="t1-listband">
                <div><h3>{{ __('ui::explore.list_title') }}</h3><p>{{ __('ui::explore.list_sub') }}</p></div>
                <div style="display:grid;gap:12px">
                    <div class="t1-whogrid">
                        <div><i class="bi bi-building"></i>{{ __('ui::explore.tab_clubs') }}</div>
                        <div><i class="bi bi-bank"></i>{{ __('ui::explore.federations') }}</div>
                        <div><i class="bi bi-person-badge"></i>{{ __('ui::explore.tab_coaches') }}</div>
                        <div><i class="bi bi-calendar-event"></i>{{ __('ui::explore.tab_events') }}</div>
                    </div>
                    <a class="t1-cta" href="{{ route('business.setup') }}">{{ __('ui::explore.list_cta') }}</a>
                </div>
            </section>
            <button type="button" class="t1-fab" @click="map = !map"><i class="bi" :class="map ? 'bi-list' : 'bi-map'"></i><span x-text="map ? @js(__('ui::explore.hide_map')) : @js(__('ui::explore.show_map'))"></span></button>
        </div>

        {{-- ===== Activities ===== --}}
        <div x-show="tab === 'activities'" x-cloak>
            <template x-for="g in D.groups" :key="g.key">
                <div x-show="actsIn(g.key).length">
                    <div class="t1-subhead"><span x-text="g.label + ' · ' + actsIn(g.key).length"></span></div>
                    <div class="t1-acts"><template x-for="a in actsIn(g.key)" :key="a.uuid">@include('ui::explore.partials.tile-activity')</template></div>
                </div>
            </template>
            <template x-if="!acts.length"><div class="t1-empty"><b>{{ __('ui::explore.none_title') }}</b>{{ __('ui::explore.none_hint') }}</div></template>
        </div>

        {{-- ===== Clubs (list + switchable map) ===== --}}
        <div x-show="tab === 'clubs'" x-cloak>
            <div class="t1-vhead">
                <div><h3 x-text="clubs.length + ' ' + @js(__('ui::explore.tab_clubs')).toLowerCase() + (actName() ? ' · ' + actName() : '')"></h3>
                    <p x-text="pos ? @js(__('ui::explore.sorted')) : @js(__('ui::explore.no_location'))"></p>
                    <button type="button" class="t1-aichip" x-show="only" x-cloak @click="only = null">@include('ui::shell.robot', ['uid' => 'x']){{ __('ui::ask.title') }} <i class="bi bi-x-lg"></i></button></div>
                <button type="button" class="t1-mswitch" role="switch" :aria-checked="map" @click="map = !map"><i class="bi bi-map"></i>{{ __('ui::explore.map') }}<span class="t1-trk"></span></button>
            </div>
            <div class="t1-split" :class="{ 'with-map': map }">
                <div class="t1-grid">
                    <template x-for="c in clubs" :key="c.key">@include('ui::explore.partials.card-club')</template>
                    <template x-if="!clubs.length"><div class="t1-empty"><b>{{ __('ui::explore.none_title') }}</b>{{ __('ui::explore.none_hint') }}</div></template>
                </div>
                <div class="t1-mapbox"><div class="t1-lmap" x-ref="lmap_clubs"></div></div>
            </div>
            <button type="button" class="t1-fab" @click="map = !map"><i class="bi" :class="map ? 'bi-list' : 'bi-map'"></i><span x-text="map ? @js(__('ui::explore.hide_map')) : @js(__('ui::explore.show_map'))"></span></button>
        </div>

        {{-- ===== Events ===== --}}
        <div x-show="tab === 'events'" x-cloak>
            <div class="t1-vhead"><div><h3 x-text="evs.length + ' ' + @js(__('ui::explore.tab_events')).toLowerCase() + (actName() ? ' · ' + actName() : '')"></h3></div>
                <button type="button" class="t1-mswitch" role="switch" :aria-checked="map" @click="map = !map"><i class="bi bi-map"></i>{{ __('ui::explore.map') }}<span class="t1-trk"></span></button>
            </div>
            <div class="t1-split" :class="{ 'with-map': map }">
                <div class="t1-grid">
                    <template x-for="e in evs" :key="e.key">@include('ui::explore.partials.card-event')</template>
                    <template x-if="!eventsLoaded"><div class="t1-skel"></div></template>
                    <template x-if="eventsLoaded && !evs.length"><div class="t1-empty"><b>{{ __('ui::explore.none_title') }}</b>{{ __('ui::explore.none_hint') }}</div></template>
                </div>
                <div class="t1-mapbox"><div class="t1-lmap" x-ref="lmap_events"></div></div>
            </div>
            <button type="button" class="t1-fab" @click="map = !map"><i class="bi" :class="map ? 'bi-list' : 'bi-map'"></i><span x-text="map ? @js(__('ui::explore.hide_map')) : @js(__('ui::explore.show_map'))"></span></button>
        </div>

        {{-- ===== Coaches ===== --}}
        <div x-show="tab === 'coaches'" x-cloak>
            <div class="t1-vhead"><div><h3 x-text="coaches.length + ' ' + @js(__('ui::explore.tab_coaches')).toLowerCase()"></h3></div>
                <button type="button" class="t1-mswitch" role="switch" :aria-checked="map" @click="map = !map"><i class="bi bi-map"></i>{{ __('ui::explore.map') }}<span class="t1-trk"></span></button>
            </div>
            <div class="t1-split" :class="{ 'with-map': map }">
                <div class="t1-grid"><template x-for="c in coaches" :key="c.key">@include('ui::explore.partials.card-coach')</template></div>
                <div class="t1-mapbox"><div class="t1-lmap" x-ref="lmap_coaches"></div></div>
            </div>
            <button type="button" class="t1-fab" @click="map = !map"><i class="bi" :class="map ? 'bi-list' : 'bi-map'"></i><span x-text="map ? @js(__('ui::explore.hide_map')) : @js(__('ui::explore.show_map'))"></span></button>
        </div>

        {{-- ===== Federations ===== --}}
        <div x-show="tab === 'federations'" x-cloak>
            <div class="t1-vhead"><div><h3 x-text="feds.length + ' ' + @js(__('ui::explore.tab_federations')).toLowerCase()"></h3><p>{{ __('ui::explore.rail_feds_sub') }}</p></div></div>
            <div class="t1-grid">
                <template x-for="f in feds" :key="f.key">@include('ui::explore.partials.card-federation')</template>
                <template x-if="!feds.length"><div class="t1-empty"><b>{{ __('ui::explore.none_title') }}</b>{{ __('ui::explore.none_hint') }}</div></template>
            </div>
        </div>

        {{-- ===== Shop ===== --}}
        <div x-show="tab === 'shops'" x-cloak>
            <div class="t1-vhead"><div><h3 x-text="products.length + ' ' + @js(__('ui::explore.tab_shop')).toLowerCase()"></h3><p>{{ __('ui::explore.rail_shop_sub') }}</p></div>
                <button type="button" class="t1-mswitch" role="switch" :aria-checked="map" @click="map = !map"><i class="bi bi-map"></i>{{ __('ui::explore.map') }}<span class="t1-trk"></span></button>
            </div>
            <div class="t1-split" :class="{ 'with-map': map }">
                <div class="t1-grid">
                    <template x-for="p in products" :key="p.key">@include('ui::explore.partials.card-product')</template>
                    <template x-if="!products.length"><div class="t1-empty"><b>{{ __('ui::explore.none_title') }}</b>{{ __('ui::explore.none_hint') }}</div></template>
                </div>
                <div class="t1-mapbox"><div class="t1-lmap" x-ref="lmap_shops"></div></div>
            </div>
            <button type="button" class="t1-fab" @click="map = !map"><i class="bi" :class="map ? 'bi-list' : 'bi-map'"></i><span x-text="map ? @js(__('ui::explore.hide_map')) : @js(__('ui::explore.show_map'))"></span></button>
        </div>

        {{-- ===== Coming soon: Offers · Venues · Clinics · Specialists ===== --}}
        <template x-for="s in soonTabs" :key="s.k">
            <div x-show="tab === s.k" x-cloak>
                <section class="t1-soon" :style="'--c:' + s.color">
                    <span class="t1-soon-c1"></span><span class="t1-soon-c2"></span>
                    <div class="t1-soon-in">
                        <span class="t1-soon-ic"><i class="bi" :class="s.icon"></i></span>
                        <span class="t1-soon-chip"><i class="bi bi-hourglass-split"></i>{{ __('ui::explore.coming_soon') }}</span>
                        <h3 x-text="s.title"></h3>
                        <p x-text="s.sub"></p>
                        <div class="t1-soon-lbl">{{ __('ui::explore.soon_preview') }}</div>
                        <div class="t1-soon-f"><template x-for="f in s.filters" :key="f"><span x-text="f"></span></template></div>
                    </div>
                </section>
                <section class="t1-listband">
                    <div><h3>{{ __('ui::explore.soon_list') }}</h3><p>{{ __('ui::explore.list_sub') }}</p></div>
                    <div style="display:grid;gap:12px"><a class="t1-cta" href="{{ route('business.setup') }}">{{ __('ui::explore.list_cta') }}</a></div>
                </section>
            </div>
        </template>
    </main>
</div>
