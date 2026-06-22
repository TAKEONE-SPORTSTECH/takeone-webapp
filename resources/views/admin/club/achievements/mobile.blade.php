@extends('layouts.admin-club-mobile')

@section('title', ($club->club_name ?? __('admin.club')) . ' · ' . __('admin.nav_achievements'))

@section('club-admin-content')
@php
$achievementsJson = $achievements->map(function ($a) {
    $combined = array_values(array_unique(array_filter(array_merge(
        $a->image_path ? [$a->image_path] : [],
        $a->images ?? []
    ))));
    $combinedUrls = collect($combined)->map(fn ($p) => asset('storage/' . $p))->values()->toArray();
    return [
        'id'               => $a->id,
        'title'            => $a->title,
        'short_title'      => $a->short_title ?? '',
        'type_icon'        => $a->type_icon ?? '🏆',
        'description'      => $a->description ?? '',
        'location'         => $a->location ?? '',
        'date_label'       => $a->date_label ?? '',
        'achievement_date' => $a->achievement_date ? $a->achievement_date->format('Y-m-d') : '',
        'category'         => $a->category ?? '',
        'medals_gold'      => $a->medals_gold ?? 0,
        'medals_silver'    => $a->medals_silver ?? 0,
        'medals_bronze'    => $a->medals_bronze ?? 0,
        'bouts_count'      => $a->bouts_count ?? 0,
        'wins_count'       => $a->wins_count ?? 0,
        'chips'            => is_array($a->chips) ? json_encode($a->chips) : ($a->chips ?? '[]'),
        'athletes'         => is_array($a->athletes) ? json_encode($a->athletes) : ($a->athletes ?? '[]'),
        'tag'              => $a->tag,
        'tag_icon'         => $a->tag_icon,
        'image_url'        => $combinedUrls[0] ?? null,
        'bg_from'          => $a->bg_from,
        'bg_to'            => $a->bg_to,
        'status'           => $a->status,
        'sort_order'       => $a->sort_order,
        'images'           => $combinedUrls,
        'images_paths'     => $combined,
        'translations'     => $a->translations ?? [],
    ];
});
@endphp

<div class="space-y-4 mobile-stagger" x-data="achievementsAdmin()">

    {{-- Add --}}
    <button type="button" @click="openAdd()"
            class="m-press w-full flex items-center justify-center gap-2 rounded-2xl bg-primary text-white py-3.5 font-semibold shadow-sm">
        <i class="bi bi-plus-lg text-lg"></i>{{ __('admin.ach_add') }}
    </button>

    @if($achievements->isEmpty())
        <div class="m-card p-8 text-center">
            <i class="bi bi-trophy text-3xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-2">{{ __('admin.ach_no_achievements') }}</p>
        </div>
    @else
        @php
            $achYearOf = function ($a) {
                if ($a->achievement_date) return $a->achievement_date->format('Y');
                if (preg_match('/(\d{4})/', (string) ($a->date_label ?? ''), $m)) return $m[1];
                return '';
            };
            $achTextOf = function ($a) {
                return mb_strtolower(trim(implode(' ', array_filter([$a->title, $a->short_title, $a->location, $a->description, $a->tag]))));
            };
            $achYears = $achievements->map($achYearOf)->filter()->unique()->sortDesc()->values();
            $achFilterItems = $achievements->map(fn ($a) => ['text' => $achTextOf($a), 'year' => $achYearOf($a)])->values();
        @endphp
        <div class="space-y-4"
             x-data="{ q: '', year: 'all', items: @js($achFilterItems),
                       show(t, y) { const okQ = this.q.trim() === '' || t.includes(this.q.trim().toLowerCase()); const okY = this.year === 'all' || String(y) === String(this.year); return okQ && okY; },
                       get hasResults() { return this.items.some(it => this.show(it.text, it.year)); } }">

            {{-- Search --}}
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground pointer-events-none"></i>
                <input type="search" x-model="q" placeholder="{{ __('admin.ach_search') }}"
                       class="w-full pl-10 pr-3 py-2.5 bg-muted rounded-xl text-sm text-foreground placeholder-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/40">
            </div>

            {{-- Year filter — go back in time --}}
            @if($achYears->isNotEmpty())
                <div class="flex gap-2 overflow-x-auto scrollbar-hide -mx-4 px-4 pb-1">
                    <button type="button" @click="year = 'all'" :class="year === 'all' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'" class="m-press px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-colors">{{ __('admin.ach_all_years') }}</button>
                    @foreach($achYears as $y)
                        <button type="button" @click="year = '{{ $y }}'" :class="year === '{{ $y }}' ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'" class="m-press px-3.5 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-colors">{{ $y }}</button>
                    @endforeach
                </div>
            @endif

        @foreach($achievements as $ach)
            @php $img = $ach->image_path ?? (is_array($ach->images ?? null) ? ($ach->images[0] ?? null) : null); @endphp
            <div x-show="show(@js($achTextOf($ach)), @js($achYearOf($ach)))" x-cloak class="m-card overflow-hidden {{ $ach->status === 'inactive' ? 'opacity-60' : '' }}" id="achievement-{{ $ach->id }}">
                {{-- Media with the title overlaid --}}
                <div class="relative h-40">
                    @if($img)
                        <img src="{{ asset('storage/'.$img) }}" alt="" class="w-full h-40 object-cover">
                    @else
                        <div class="w-full h-40 flex items-center justify-center" style="background:linear-gradient(135deg,{{ $ach->bg_from ?: '#f59e0b' }},{{ $ach->bg_to ?: '#f97316' }});">
                            <span class="text-5xl opacity-40">{{ $ach->type_icon ?: '🏆' }}</span>
                        </div>
                    @endif
                    <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/15 to-transparent"></div>

                    {{-- Actions (top-right) --}}
                    <div class="absolute top-2.5 right-2.5 flex items-center gap-1.5">
                        <button type="button" @click="openEdit({{ $ach->id }})" title="{{ __('admin.ach_edit') }}"
                                class="m-press w-7 h-7 rounded-full bg-white/85 backdrop-blur text-gray-700 flex items-center justify-center text-xs shadow-sm hover:bg-white transition-colors">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" @click="deleteAchievement({{ $ach->id }})" title="{{ __('admin.ach_delete') }}"
                                class="m-press w-7 h-7 rounded-full bg-white/85 backdrop-blur text-red-600 flex items-center justify-center text-xs shadow-sm hover:bg-white transition-colors">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>

                    {{-- Title (bottom) --}}
                    <h3 class="absolute bottom-2.5 inset-x-3 text-white font-bold text-sm leading-snug line-clamp-2 drop-shadow">{{ $ach->type_icon ?? '' }} {{ $ach->short_title ?: $ach->title }}</h3>
                </div>
                <div class="p-4">
                    @if($ach->location || $ach->date_label || $ach->achievement_date)
                        <p class="text-xs text-muted-foreground mt-1">
                            @if($ach->location)<i class="bi bi-geo-alt mr-1"></i>{{ $ach->location }}@endif
                            @if($ach->date_label) · {{ $ach->date_label }}@elseif($ach->achievement_date) · {{ optional($ach->achievement_date)->format('d M Y') }}@endif
                        </p>
                    @endif
                    @if(($ach->medals_gold ?? 0) || ($ach->medals_silver ?? 0) || ($ach->medals_bronze ?? 0))
                        <div class="flex items-center gap-3 mt-2 text-sm font-semibold">
                            <span class="text-amber-500">🥇 {{ $ach->medals_gold ?? 0 }}</span>
                            <span class="text-gray-400">🥈 {{ $ach->medals_silver ?? 0 }}</span>
                            <span class="text-orange-700">🥉 {{ $ach->medals_bronze ?? 0 }}</span>
                        </div>
                    @endif
                    @if($ach->description)<p class="text-xs text-muted-foreground mt-2 line-clamp-2">{{ $ach->description }}</p>@endif
                </div>
            </div>
        @endforeach

            <p x-show="!hasResults" x-cloak class="m-card p-8 text-center text-sm text-muted-foreground">{{ __('admin.ach_no_results') }}</p>
        </div>
    @endif

    {{-- ===== Add / Edit bottom sheet (teleported to body) ===== --}}
    <template x-teleport="body">
        <div x-show="showModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" @keydown.escape.window="showModal = false">
            <div x-show="showModal" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="fixed inset-0 bg-black/50" @click="showModal = false"></div>
            <div class="flex min-h-full items-end justify-center sm:items-center sm:p-4">
                <div x-show="showModal"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-full sm:translate-y-4 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 translate-y-full"
                     class="relative bg-white rounded-t-3xl sm:rounded-2xl shadow-xl w-full sm:max-w-lg flex flex-col"
                     style="height: 92vh; max-height: 92vh;" @click.stop>

                    <div class="pt-2.5 pb-1 flex justify-center sm:hidden flex-shrink-0"><span class="w-10 h-1.5 rounded-full bg-gray-300"></span></div>

                    <div class="flex items-center justify-between px-4 py-3 bg-primary text-white rounded-t-3xl sm:rounded-t-2xl flex-shrink-0">
                        <h5 class="text-base font-semibold flex items-center"><i class="bi bi-trophy mr-2"></i><span x-text="isEdit ? '{{ __('admin.ach_edit') }}' : '{{ __('admin.ach_add') }}'"></span></h5>
                        <button type="button" @click="showModal = false" class="text-white/90 hover:text-white text-2xl leading-none w-8 h-8 flex items-center justify-center -mr-1">&times;</button>
                    </div>

                    <form :action="formAction" method="POST" enctype="multipart/form-data" id="achievementMobileForm" class="flex-1 overflow-y-auto overscroll-contain px-4 py-4">
                        @csrf
                        <input type="hidden" name="_method" :value="isEdit ? 'PUT' : 'POST'">
                        @include('admin.club.achievements.partials.form-fields')
                    </form>

                    <div class="px-4 py-3 bg-gray-50 border-t flex-shrink-0 flex items-center gap-2" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
                        <button type="button" @click="showModal = false" class="px-4 py-2.5 rounded-xl border border-gray-200 text-gray-600 bg-white text-sm font-medium">{{ __('admin.cancel') }}</button>
                        <button type="button" @click="document.getElementById('achievementMobileForm').requestSubmit()"
                                class="flex-1 btn btn-primary py-2.5">
                            <i class="bi bi-check-lg mr-1"></i><span x-text="isEdit ? '{{ __('admin.ach_update') }}' : '{{ __('admin.ach_save') }}'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    {{-- Inline (inside #shell-content) so it runs on full load AND after an in-shell AJAX swap. --}}
    <script>
    (function () {
        var achNewImagesFiles = [];
        var achExistingImages = [];

        function fileToBase64(file) {
            return new Promise(function (resolve) {
                var reader = new FileReader();
                reader.onload = function (e) { resolve(e.target.result); };
                reader.readAsDataURL(file);
            });
        }

        window.handleAchievementImages = function (input) {
            Array.from(input.files).forEach(function (file) { achNewImagesFiles.push(file); });
            input.value = '';
            window.renderAchievementNewPreviews();
        };

        window.renderAchievementNewPreviews = async function () {
            var previews = document.getElementById('achievementNewPreviews');
            var inputs = document.getElementById('achievementBase64Inputs');
            if (!previews || !inputs) return;
            previews.innerHTML = '';
            inputs.innerHTML = '';
            for (var idx = 0; idx < achNewImagesFiles.length; idx++) {
                var b64 = await fileToBase64(achNewImagesFiles[idx]);
                var capturedIdx = idx;
                var wrap = document.createElement('div');
                wrap.className = 'relative group';
                wrap.innerHTML = '<img src="' + b64 + '" class="w-20 h-20 object-cover rounded-lg border border-gray-200">' +
                    '<button type="button" class="absolute -top-1.5 -right-1.5 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center"><i class="bi bi-x"></i></button>';
                (function (ci) {
                    wrap.querySelector('button').addEventListener('click', function () { achNewImagesFiles.splice(ci, 1); window.renderAchievementNewPreviews(); });
                })(capturedIdx);
                previews.appendChild(wrap);
                var hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'achievement_images_base64[]';
                hidden.value = b64;
                inputs.appendChild(hidden);
            }
        };

        window.resetAchievementImages = function () {
            achNewImagesFiles = [];
            var previews = document.getElementById('achievementNewPreviews');
            var inputs = document.getElementById('achievementBase64Inputs');
            if (previews) previews.innerHTML = '';
            if (inputs) inputs.innerHTML = '';
        };

        window.renderAchievementExistingThumbnails = function (paths) {
            achExistingImages = Array.isArray(paths) ? paths.slice() : [];
            var previews = document.getElementById('achievementExistingPreviews');
            var input = document.getElementById('keepExtraImagesInput');
            if (!previews) return;
            previews.innerHTML = '';
            if (input) input.value = JSON.stringify(achExistingImages);
            achExistingImages.forEach(function (path, idx) {
                var wrap = document.createElement('div');
                wrap.className = 'relative group';
                wrap.innerHTML = '<img src="/storage/' + path + '" class="w-20 h-20 object-cover rounded-lg border border-border" onerror="this.parentElement.style.display=\'none\'">' +
                    '<button type="button" class="absolute -top-1.5 -right-1.5 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center"><i class="bi bi-x"></i></button>';
                wrap.querySelector('button').addEventListener('click', function () { achExistingImages.splice(idx, 1); window.renderAchievementExistingThumbnails(achExistingImages); });
                previews.appendChild(wrap);
            });
        };

        var achievementsData = @json($achievementsJson);
        var storeUrl = '{{ route('admin.club.achievements.store', $club->slug) }}';
        var baseEditUrl = '{{ url('admin/club/' . $club->slug . '/achievements') }}';

        var emptyForm = {
            title: '', short_title: '', type_icon: '🏆',
            description: '', location: '', achievement_date: '', date_label: '',
            medals_gold: 0, medals_silver: 0, medals_bronze: 0,
            bouts_count: 0, wins_count: 0, category: '',
            chips: '[]', athletes: '[]',
            tag: '', tag_icon: 'bi-trophy',
            image_path: '', remove_image: false,
            bg_from: '#f59e0b', bg_to: '#f97316',
            status: 'active', sort_order: 0,
            images: [], images_paths: [],
        };

        var achievementIcons = [
            { value: 'bi-trophy', label: 'Trophy' }, { value: 'bi-trophy-fill', label: 'Trophy Fill' },
            { value: 'bi-award', label: 'Award' }, { value: 'bi-award-fill', label: 'Award Fill' },
            { value: 'bi-star', label: 'Star' }, { value: 'bi-star-fill', label: 'Star Fill' },
            { value: 'bi-medal', label: 'Medal' }, { value: 'bi-patch-check', label: 'Verified' },
            { value: 'bi-patch-check-fill', label: 'Verified Fill' }, { value: 'bi-patch-star', label: 'Star Patch' },
            { value: 'bi-gem', label: 'Gem' }, { value: 'bi-crown', label: 'Crown' }, { value: 'bi-crown-fill', label: 'Crown Fill' },
            { value: 'bi-shield-check', label: 'Shield' }, { value: 'bi-flag', label: 'Flag' }, { value: 'bi-flag-fill', label: 'Flag Fill' },
            { value: 'bi-lightning', label: 'Lightning' }, { value: 'bi-lightning-fill', label: 'Lightning Fill' },
            { value: 'bi-fire', label: 'Fire' }, { value: 'bi-rocket', label: 'Rocket' }, { value: 'bi-rocket-fill', label: 'Rocket Fill' },
            { value: 'bi-bullseye', label: 'Target' }, { value: 'bi-graph-up-arrow', label: 'Growth' },
            { value: 'bi-people', label: 'Team' }, { value: 'bi-people-fill', label: 'Team Fill' },
            { value: 'bi-hand-thumbs-up', label: 'Thumbs Up' }, { value: 'bi-heart', label: 'Heart' }, { value: 'bi-heart-fill', label: 'Heart Fill' },
            { value: 'bi-bookmark-star', label: 'Bookmark Star' }, { value: 'bi-emoji-smile', label: 'Smile' },
        ];

        window.achievementsAdmin = function () {
            return {
                showModal: false,
                isEdit: false,
                formAction: storeUrl,
                formData: Object.assign({}, emptyForm),
                showIconPicker: false,
                icons: achievementIcons,

                setAchievementTranslations(t) {
                    t = t || {};
                    var set = function (id, val) { var el = document.getElementById(id); if (el) el.value = val || ''; };
                    this.$nextTick(function () {
                        set('ach_tr_title_ar', t.title && t.title.ar);
                        set('ach_tr_location_ar', t.location && t.location.ar);
                        set('ach_tr_description_ar', t.description && t.description.ar);
                    });
                },

                openAdd() {
                    this.isEdit = false;
                    this.formAction = storeUrl;
                    this.formData = Object.assign({}, emptyForm);
                    this.showIconPicker = false;
                    this.showModal = true;
                    window.resetAchievementImages();
                    window.renderAchievementExistingThumbnails([]);
                    this.setAchievementTranslations({});
                },

                openEdit(id) {
                    var a = achievementsData.find(function (x) { return x.id === id; });
                    if (!a) return;
                    this.isEdit = true;
                    this.formAction = baseEditUrl + '/' + id;
                    this.formData = Object.assign({}, emptyForm, a, { remove_image: false });
                    this.showIconPicker = false;
                    this.showModal = true;
                    window.resetAchievementImages();
                    window.renderAchievementExistingThumbnails(a.images_paths || []);
                    this.setAchievementTranslations(a.translations);
                },

                deleteAchievement(id) {
                    window.confirmAction({
                        title: '{{ __('admin.ach_delete_title') }}',
                        message: '{{ __('admin.ach_delete_msg') }}',
                        confirmText: '{{ __('admin.ach_delete') }}',
                        type: 'danger',
                    }).then(function (confirmed) {
                        if (!confirmed) return;
                        fetch(baseEditUrl + '/' + id, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                        })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            if (data.success) {
                                document.getElementById('achievement-' + id)?.remove();
                                window.showToast('success', data.message || 'Achievement deleted.');
                            } else {
                                window.showToast('error', data.message || 'Failed to delete achievement.');
                            }
                        })
                        .catch(function () { window.showToast('error', 'Failed to delete achievement.'); });
                    });
                },
            };
        };
    })();

    @if($errors->any())
    document.addEventListener('DOMContentLoaded', function () { window.showToast && window.showToast('error', @json($errors->first())); });
    @endif
    </script>
</div>
@endsection
