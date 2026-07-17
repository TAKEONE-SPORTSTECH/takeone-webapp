{{-- Inside the personal mobile shell: header (avatar → drawer), notifications,
     chat and bottom tabs come from the shell. -mx-4 -mt-4 cancels <main>'s padding. --}}
@extends('layouts.personal-mobile')

@section('title', __('personal.find_people'))

@section('personal-content')
<div class="-mx-4 -mt-4" x-data="peopleSearch(@js($suggestions))">
    @unless($hasConfirmedClub)
        <div class="px-4 pt-10 pb-6 text-center mobile-stagger">
            <i class="bi bi-people text-4xl text-gray-300"></i>
            <p class="font-bold text-foreground mt-4">{{ __('personal.people_no_club_title') }}</p>
            <p class="text-sm text-muted-foreground mt-1">{{ __('personal.people_no_club_desc') }}</p>
            <a href="{{ route('clubs.explore') }}" class="m-press inline-flex items-center gap-2 mt-5 bg-primary text-white px-4 py-2.5 rounded-xl font-medium">
                <i class="bi bi-plus-lg"></i>{{ __('personal.people_register_cta') }}
            </a>
        </div>
    @else
    {{-- The search bar keeps its own sticky row under the shell header. --}}
    <div class="sticky top-14 z-30 bg-white border-b border-border">
        <div class="px-3 py-3">
            <div class="relative">
                <i class="bi bi-search absolute left-3 rtl:left-auto rtl:right-3 top-1/2 -translate-y-1/2 text-muted-foreground"></i>
                <input type="search" x-model="q" @input.debounce.300ms="run()"
                       class="w-full pl-10 rtl:pl-3 rtl:pr-10 pr-3 py-2.5 rounded-xl border border-border bg-muted/40 text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                       placeholder="{{ __('personal.search_people_placeholder') }}" autocomplete="off">
            </div>
        </div>
    </div>

    <div class="px-3 pt-3 mobile-stagger">
        {{-- Section header + view toggle --}}
        <div class="flex items-center justify-between mb-2.5">
            <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground px-1"
               x-text="mode === 'search' ? '{{ __('personal.search_results') }}' : '{{ __('personal.suggested_for_you') }}'"></p>
            <div class="inline-flex bg-muted rounded-lg p-0.5" role="tablist">
                <button type="button" role="tab" @click="setView('list')" :aria-selected="view === 'list'"
                        :class="view === 'list' ? 'bg-white shadow text-primary' : 'text-muted-foreground'"
                        class="w-8 h-7 rounded-md flex items-center justify-center transition-colors" aria-label="{{ __('personal.view_list') }}">
                    <i class="bi bi-list-ul"></i>
                </button>
                <button type="button" role="tab" @click="setView('cards')" :aria-selected="view === 'cards'"
                        :class="view === 'cards' ? 'bg-white shadow text-primary' : 'text-muted-foreground'"
                        class="w-8 h-7 rounded-md flex items-center justify-center transition-colors" aria-label="{{ __('personal.view_cards') }}">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                </button>
            </div>
        </div>

        {{-- Loading (search) --}}
        <template x-if="loading">
            <div class="space-y-2.5">
                <template x-for="i in 5" :key="i">
                    <div class="bg-white rounded-2xl border border-gray-100 p-3 flex items-center gap-3 animate-pulse">
                        <div class="w-12 h-12 rounded-xl bg-muted"></div>
                        <div class="flex-1 space-y-2"><div class="h-3 bg-muted rounded w-1/2"></div><div class="h-2.5 bg-muted rounded w-1/4"></div></div>
                    </div>
                </template>
            </div>
        </template>

        {{-- Empty (search returned nothing) --}}
        <template x-if="!loading && mode === 'search' && people.length === 0">
            <div class="text-center py-16 text-muted-foreground">
                <i class="bi bi-search text-4xl text-gray-300"></i>
                <p class="text-sm mt-3">{{ __('personal.no_people_found') }}</p>
            </div>
        </template>

        {{-- LIST view --}}
        <template x-if="!loading && view === 'list'">
            <div class="space-y-2.5">
                <template x-for="p in people" :key="p.uuid">
                    <a :href="p.profile_url" class="m-press block bg-white rounded-2xl shadow-sm border border-gray-100 p-3 flex items-center gap-3">
                        <span class="w-12 h-12 rounded-xl overflow-hidden flex-shrink-0 grid place-items-center bg-accent">
                            <template x-if="p.avatar"><img :src="p.avatar" alt="" class="w-12 h-12 object-cover"></template>
                            <template x-if="!p.avatar"><i class="bi bi-person-fill text-2xl text-primary/60"></i></template>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-foreground text-[15px] leading-snug truncate" x-text="p.name"></p>
                            <template x-if="p.reason"><p class="text-[11px] text-muted-foreground truncate mt-0.5"><i class="bi bi-people mr-1"></i><span x-text="p.reason"></span></p></template>
                            <template x-if="!p.reason && p.is_trainer"><span class="inline-flex items-center gap-1 mt-0.5 text-[10px] font-semibold px-2 py-0.5 rounded-full bg-accent text-primary"><i class="bi bi-mortarboard-fill"></i>{{ __('personal.people_trainer') }}</span></template>
                        </div>
                        <button type="button" @click.prevent="toggleFollow(p)"
                                class="m-press flex-shrink-0 text-xs font-semibold px-3 py-1.5 rounded-full transition-colors"
                                :class="p.is_following ? 'bg-muted text-muted-foreground' : 'bg-primary text-white'"
                                x-text="p.is_following ? '{{ __('personal.following') }}' : '{{ __('personal.follow') }}'"></button>
                    </a>
                </template>
            </div>
        </template>

        {{-- CARDS view --}}
        <template x-if="!loading && view === 'cards'">
            <div class="grid grid-cols-2 gap-2.5">
                <template x-for="p in people" :key="p.uuid">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex flex-col items-center text-center">
                        <a :href="p.profile_url" class="no-underline w-full">
                            <span class="w-16 h-16 rounded-2xl overflow-hidden grid place-items-center bg-accent mx-auto">
                                <template x-if="p.avatar"><img :src="p.avatar" alt="" class="w-16 h-16 object-cover"></template>
                                <template x-if="!p.avatar"><i class="bi bi-person-fill text-3xl text-primary/60"></i></template>
                            </span>
                            <p class="font-bold text-foreground text-sm leading-snug truncate mt-2.5" x-text="p.name"></p>
                        </a>
                        <p class="text-[10px] text-muted-foreground truncate w-full mt-0.5 min-h-[14px]" x-text="p.reason || (p.is_trainer ? '{{ __('personal.people_trainer') }}' : '')"></p>
                        <button type="button" @click="toggleFollow(p)"
                                class="m-press w-full mt-2.5 text-xs font-semibold py-1.5 rounded-full transition-colors"
                                :class="p.is_following ? 'bg-muted text-muted-foreground' : 'bg-primary text-white'"
                                x-text="p.is_following ? '{{ __('personal.following') }}' : '{{ __('personal.follow') }}'"></button>
                    </div>
                </template>
            </div>
        </template>
    </div>
    @endunless
</div>
@endsection

@push('scripts')
<script>
    function peopleSearch(suggestions) {
        return {
            q: '', suggestions: suggestions || [], people: suggestions || [], loading: false, mode: 'suggested', _seq: 0,
            view: (localStorage.getItem('peopleView') || 'list'),
            setView(v) { this.view = v; localStorage.setItem('peopleView', v); },
            run() {
                if (this.q.trim() === '') { this.mode = 'suggested'; this.people = this.suggestions; this.loading = false; return; }
                this.mode = 'search';
                const seq = ++this._seq;
                this.loading = true;
                fetch(`{{ route('me.people.search') }}?q=` + encodeURIComponent(this.q), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(r => r.json())
                    .then(d => { if (seq !== this._seq) return; this.people = d.people || []; })
                    .catch(() => {})
                    .finally(() => { if (seq === this._seq) this.loading = false; });
            },
            toggleFollow(p) {
                const was = p.is_following;
                p.is_following = !was;
                fetch('{{ url('u') }}/' + p.slug + '/follow', {
                    method: was ? 'DELETE' : 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                }).then(r => { if (!r.ok) throw r; }).catch(() => { p.is_following = was; if (window.showToast) window.showToast('error', 'Could not update'); });
            },
        };
    }
</script>
@endpush
