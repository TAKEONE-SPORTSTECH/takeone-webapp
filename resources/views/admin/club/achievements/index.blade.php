@extends('layouts.admin-club')

@section('club-admin-content')

@php
$achievementsJson = $achievements->map(function($a) {
    $combined = array_values(array_unique(array_filter(array_merge(
        $a->image_path ? [$a->image_path] : [],
        $a->images ?? []
    ))));
    $combinedUrls = collect($combined)->map(fn($p) => asset('storage/' . $p))->values()->toArray();
    return [
        'id'          => $a->id,
        'title'       => $a->title,
        'description' => $a->description ?? '',
        'tag'         => $a->tag,
        'tag_icon'    => $a->tag_icon,
        'image_url'   => $combinedUrls[0] ?? null,
        'bg_from'     => $a->bg_from,
        'bg_to'       => $a->bg_to,
        'status'      => $a->status,
        'sort_order'  => $a->sort_order,
        'images'      => $combinedUrls,
        'images_paths'=> $combined,
    ];
});
@endphp

<div x-data="achievementsAdmin()">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-foreground">Latest Achievements</h2>
            <p class="text-sm text-muted-foreground mt-0.5">Manage club achievements and milestones shown on your public page (top 3 active shown)</p>
        </div>
        <button @click="openAdd()" class="btn btn-primary">
            <i class="bi bi-plus-lg mr-2"></i>Add Achievement
        </button>
    </div>

    {{-- Session messages --}}
    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif

    {{-- Achievements list --}}
    @if($achievements->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-16">
                <i class="bi bi-trophy text-muted-foreground" style="font-size:2.5rem;opacity:.3;"></i>
                <p class="mt-3 text-muted-foreground">No achievements yet. Add your first club achievement.</p>
                <button @click="openAdd()" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-lg mr-2"></i>Add Achievement
                </button>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($achievements as $achievement)
            @php
                $isInactive = $achievement->status === 'inactive';
                $achCardImages = collect($achievement->images ?? [])->map(fn($p) => asset('storage/'.$p))->values()->toArray();
                if (empty($achCardImages) && $achievement->image_path) $achCardImages = [asset('storage/'.$achievement->image_path)];
            @endphp
            <div class="card border-0 shadow-sm overflow-hidden {{ $isInactive ? 'opacity-60' : '' }} cursor-pointer"
                 @click="openDetail({{ $achievement->id }})">
                {{-- Card visual --}}
                <div class="relative" style="height:120px;"
                     @if(count($achCardImages) > 1) data-images="{{ json_encode($achCardImages) }}" @endif>
                    @if(count($achCardImages))
                        <img src="{{ $achCardImages[0] }}" class="ach-preview w-full h-full object-cover" alt="{{ $achievement->title }}">
                        @if(count($achCardImages) > 1)
                        <div class="ach-dots">
                            @foreach($achCardImages as $j => $_)
                            <span class="ach-dot{{ $j === 0 ? ' active' : '' }}"></span>
                            @endforeach
                        </div>
                        <span class="absolute bottom-2 right-2 bg-black/60 text-white text-xs px-2 py-0.5 rounded-full backdrop-blur-sm">
                            <i class="bi bi-images mr-1"></i>{{ count($achCardImages) }}
                        </span>
                        @endif
                    @else
                        <div class="w-full h-full flex items-center justify-center"
                             style="background: linear-gradient(135deg, {{ $achievement->bg_from }}, {{ $achievement->bg_to }});"></div>
                    @endif
                    {{-- Tag --}}
                    <span class="absolute bottom-2 left-2 text-xs font-semibold px-2 py-1 rounded-full bg-black/50 text-white">
                        <i class="bi {{ $achievement->tag_icon }} mr-1"></i>{{ $achievement->tag }}
                    </span>
                </div>
                {{-- Card body --}}
                <div class="card-body p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-semibold text-foreground truncate">{{ $achievement->title }}</div>
                            @if($achievement->description)
                            <div class="text-xs text-muted-foreground mt-0.5 truncate">{{ $achievement->description }}</div>
                            @endif
                        </div>
                        <div class="flex gap-1.5 flex-shrink-0" @click.stop>
                            <button @click="openEdit({{ $achievement->id }})"
                                    class="btn btn-sm btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button @click="deleteAchievement({{ $achievement->id }})"
                                    class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    @if($isInactive)
                    <span class="badge bg-gray-100 text-gray-500 text-xs mt-2">Inactive</span>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    @endif

    {{-- ===== DETAIL MODAL ===== --}}
    <div x-show="showDetail" x-cloak
         class="fixed inset-0 z-50 bg-black/50 flex items-start justify-center p-4 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click.self="showDetail = false">
        <div class="my-auto w-full max-w-lg">
            <div class="modal-content border-0 shadow-lg relative" @click.stop x-show="detailAchievement">
                <template x-if="detailAchievement">
                    <div>
                        {{-- Hero --}}
                        <div class="relative rounded-t-xl overflow-hidden bg-black" style="height:220px;">

                            {{-- Carousel when images[] present --}}
                            <div x-show="detailAchievement.images && detailAchievement.images.length > 0"
                                 x-data="{ imgIdx: 0 }"
                                 x-effect="imgIdx = 0"
                                 class="absolute inset-0">
                                <div class="flex h-full overflow-x-auto snap-x snap-mandatory"
                                     style="scroll-behavior:smooth;-webkit-overflow-scrolling:touch;scrollbar-width:none;cursor:grab;"
                                     x-ref="achStrip"
                                     @scroll.debounce.50ms="imgIdx = Math.round($el.scrollLeft / $el.offsetWidth)">
                                    <template x-for="img in detailAchievement.images" :key="img">
                                        <img :src="img" class="snap-start flex-shrink-0 w-full h-full object-cover select-none" draggable="false">
                                    </template>
                                </div>
                                <button x-show="detailAchievement.images.length > 1"
                                        @click.stop="$refs.achStrip.scrollTo({ left: ((imgIdx - 1 + detailAchievement.images.length) % detailAchievement.images.length) * $refs.achStrip.offsetWidth, behavior: 'smooth' })"
                                        class="carousel-arrow carousel-arrow--prev">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/></svg>
                                </button>
                                <button x-show="detailAchievement.images.length > 1"
                                        @click.stop="$refs.achStrip.scrollTo({ left: ((imgIdx + 1) % detailAchievement.images.length) * $refs.achStrip.offsetWidth, behavior: 'smooth' })"
                                        class="carousel-arrow carousel-arrow--next">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>
                                </button>
                                <div x-show="detailAchievement.images.length > 1"
                                     class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1.5 pointer-events-auto">
                                    <template x-for="(img, i) in detailAchievement.images" :key="i">
                                        <button @click.stop="$refs.achStrip.scrollTo({ left: i * $refs.achStrip.offsetWidth, behavior: 'smooth' })"
                                                :class="imgIdx === i ? 'bg-white w-4' : 'bg-white/50 w-2'"
                                                class="h-2 rounded-full transition-all duration-200"></button>
                                    </template>
                                </div>
                            </div>

                            {{-- Single image fallback --}}
                            <div x-show="(!detailAchievement.images || !detailAchievement.images.length) && detailAchievement.image_url"
                                 class="absolute inset-0">
                                <img :src="detailAchievement.image_url" class="w-full h-full object-cover">
                            </div>

                            {{-- Gradient fallback --}}
                            <div x-show="(!detailAchievement.images || !detailAchievement.images.length) && !detailAchievement.image_url"
                                 class="absolute inset-0 flex items-center justify-center"
                                 :style="'background: linear-gradient(135deg, ' + detailAchievement.bg_from + ', ' + detailAchievement.bg_to + ');'">
                                <i :class="'bi ' + detailAchievement.tag_icon" class="text-white" style="font-size:3rem;opacity:0.7;"></i>
                            </div>

                            {{-- Close --}}
                            <button @click.stop="showDetail = false"
                                    class="absolute top-2.5 right-2.5 w-8 h-8 rounded-full bg-black/50 hover:bg-black/75 text-white flex items-center justify-center transition-colors z-10">
                                <i class="bi bi-x-lg text-xs"></i>
                            </button>

                            {{-- Tag --}}
                            <span class="absolute bottom-2 left-3 text-xs font-semibold px-2.5 py-1 rounded-full bg-black/50 text-white pointer-events-none">
                                <i :class="'bi ' + detailAchievement.tag_icon + ' mr-1'"></i>
                                <span x-text="detailAchievement.tag"></span>
                            </span>
                        </div>

                        {{-- Body --}}
                        <div class="px-6 pt-5 pb-6">
                            <h4 class="text-lg font-bold text-foreground mb-2" x-text="detailAchievement.title"></h4>
                            <p x-show="detailAchievement.description"
                               class="text-sm text-muted-foreground leading-relaxed mb-4"
                               x-text="detailAchievement.description"></p>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        @click="showDetail = false; openEdit(detailAchievement.id)">
                                    <i class="bi bi-pencil mr-1"></i>Edit
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" @click="showDetail = false">Close</button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Achievement Image Cropper Modal --}}
    <div class="modal fade" id="achievementCropperModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:75%; width:900px;">
            <div class="modal-content shadow-lg">
                <div class="modal-body p-4">
                    <div class="mb-3 flex items-center gap-2">
                        <input type="file" id="achievementCropperFileInput" class="form-control form-control-sm" accept="image/*">
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div id="achievementCropperCanvas" class="takeone-canvas" style="height:380px;"></div>
                    <div class="grid grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Zoom</label>
                            <input type="range" id="achievementCropperZoom" class="form-range" min="0" max="100" step="1" value="0">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Rotation</label>
                            <input type="range" id="achievementCropperRot" class="form-range" min="-180" max="180" step="1" value="0">
                        </div>
                    </div>
                    <button type="button" id="achievementCropperSave"
                            class="btn btn-success btn-lg font-bold w-full py-3 mt-3">
                        Crop & Add
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== SINGLE MODAL (Add & Edit) ===== --}}
    <div x-show="showModal" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="modal-content border-0 shadow-lg w-full max-w-2xl relative" @click.stop>
                <div class="modal-header border-b border-border px-6 py-4">
                    <h5 class="modal-title text-lg font-semibold" x-text="isEdit ? 'Edit Achievement' : 'Add Achievement'"></h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="showModal = false">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form :action="formAction" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="_method" :value="isEdit ? 'PUT' : 'POST'">
                    <div class="modal-body px-6 py-4 max-h-[70vh] overflow-y-auto">
                        @include('admin.club.achievements.partials.form-fields')
                    </div>
                    <div class="modal-footer border-t border-border px-6 py-4 flex justify-end gap-3">
                        <button type="button" class="btn btn-outline-secondary" @click="showModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg mr-1"></i>
                            <span x-text="isEdit ? 'Update Achievement' : 'Save Achievement'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/cropme@1.4.1/dist/cropme.min.css">
<script src="https://unpkg.com/cropme@1.4.1/dist/cropme.min.js"></script>
@endpush

@push('scripts')
<script>
// ── Achievement extra-image cropper ──────────────────────────────────────
let achCropperInstance = null;
let achCropperModal    = null;
let achNewImages       = [];

function openAchievementCropper() {
    document.getElementById('achievementCropperFileInput').value = '';
    document.getElementById('achievementCropperZoom').value = 0;
    document.getElementById('achievementCropperRot').value  = 0;
    if (!achCropperModal) {
        achCropperModal = new bootstrap.Modal(document.getElementById('achievementCropperModal'));
    }
    achCropperModal.show();
}

function resetAchievementImages() {
    achNewImages = [];
    renderAchievementNewThumbnails();
}

let achExistingImages = [];

function renderAchievementExistingThumbnails(paths) {
    achExistingImages = Array.isArray(paths) ? [...paths] : [];
    const previews = document.getElementById('achievementExistingPreviews');
    const input    = document.getElementById('keepExtraImagesInput');
    const preview  = document.getElementById('achGradientPreview');
    if (!previews) return;

    previews.innerHTML = '';
    if (input) input.value = JSON.stringify(achExistingImages);
    if (preview) preview.style.display = achExistingImages.length ? 'none' : '';

    achExistingImages.forEach((path, idx) => {
        const wrap = document.createElement('div');
        wrap.className = 'relative group';
        wrap.innerHTML = `
            <img src="/storage/${path}" class="w-20 h-20 object-cover rounded-lg border border-border" onerror="this.parentElement.style.display='none'">
            <button type="button" class="absolute -top-1.5 -right-1.5 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                <i class="bi bi-x"></i>
            </button>`;
        wrap.querySelector('button').addEventListener('click', () => {
            achExistingImages.splice(idx, 1);
            renderAchievementExistingThumbnails(achExistingImages);
        });
        previews.appendChild(wrap);
    });
}

$(function() {
    const zoomMin = 0.01, zoomMax = 3;

    function initAchCropper(url) {
        if (achCropperInstance) {
            try { achCropperInstance.destroy(); } catch(e) {}
            achCropperInstance = null;
        }
        document.getElementById('achievementCropperCanvas').innerHTML = '';
        achCropperInstance = new Cropme(document.getElementById('achievementCropperCanvas'), {
            container: { width: '100%', height: 380 },
            viewport: { width: 400, height: 300, type: 'square', border: { enable: true, width: 2, color: '#fff' } },
            transformOrigin: 'viewport',
            zoom: { min: zoomMin, max: zoomMax, enable: true, mouseWheel: true, slider: false },
            rotation: { enable: true, slider: false }
        });
        achCropperInstance.bind({ url }).then(() => {
            $('#achievementCropperZoom').val(0);
            $('#achievementCropperRot').val(0);
        });
    }

    $('#achievementCropperModal').on('shown.bs.modal', function() {
        if (achCropperInstance) {
            try { achCropperInstance.destroy(); } catch(e) {}
            achCropperInstance = null;
        }
        document.getElementById('achievementCropperCanvas').innerHTML = '';
    });

    $('#achievementCropperFileInput').on('change', function() {
        if (!this.files[0]) return;
        const reader = new FileReader();
        reader.onload = e => initAchCropper(e.target.result);
        reader.readAsDataURL(this.files[0]);
    });

    $('#achievementCropperZoom').on('input', function() {
        if (!achCropperInstance?.properties?.image) return;
        const scale = zoomMin + (zoomMax - zoomMin) * (this.value / 100);
        achCropperInstance.properties.scale = Math.min(Math.max(scale, zoomMin), zoomMax);
        const p = achCropperInstance.properties;
        p.image.style.transform = `translate3d(${p.x}px,${p.y}px,0) scale(${p.scale}) rotate(${p.deg}deg)`;
    });

    $('#achievementCropperRot').on('input', function() {
        if (achCropperInstance) achCropperInstance.rotate(parseInt(this.value));
    });

    $('#achievementCropperSave').on('click', function() {
        if (!achCropperInstance || !achCropperInstance.properties?.image) {
            alert('Please select an image first.');
            return;
        }
        const btn = $(this);
        btn.prop('disabled', true).text('Processing...');
        achCropperInstance.crop({ type: 'base64' }).then(base64 => {
            achNewImages.push(base64);
            renderAchievementNewThumbnails();
            achCropperModal.hide();
            btn.prop('disabled', false).text('Crop & Add');
        }).catch(err => {
            console.error('Crop failed:', err);
            btn.prop('disabled', false).text('Crop & Add');
        });
    });
});

function renderAchievementNewThumbnails() {
    const previews = document.getElementById('achievementNewPreviews');
    const inputs   = document.getElementById('achievementBase64Inputs');
    if (!previews || !inputs) return;

    previews.innerHTML = '';
    inputs.innerHTML   = '';

    achNewImages.forEach((b64, idx) => {
        const wrap = document.createElement('div');
        wrap.className = 'relative group';
        wrap.innerHTML = `
            <img src="${b64}" class="w-20 h-20 object-cover rounded-lg border border-gray-200">
            <button type="button" class="absolute -top-1.5 -right-1.5 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                <i class="bi bi-x"></i>
            </button>`;
        wrap.querySelector('button').addEventListener('click', () => {
            achNewImages.splice(idx, 1);
            renderAchievementNewThumbnails();
        });
        previews.appendChild(wrap);

        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'achievement_images_base64[]';
        input.value = b64;
        inputs.appendChild(input);
    });
}
</script>
@endpush

@push('scripts')
<script>
// Achievement card hover slideshow
document.querySelectorAll('[data-images]').forEach(function(el) {
    const images = JSON.parse(el.dataset.images || '[]');
    if (images.length <= 1) return;
    const img  = el.querySelector('.ach-preview');
    const dots = el.querySelectorAll('.ach-dot');
    let idx = 0, timer = null;

    function goTo(i) {
        idx = (i + images.length) % images.length;
        img.src = images[idx];
        dots.forEach((d, j) => d.classList.toggle('active', j === idx));
    }

    el.addEventListener('mouseenter', function() {
        timer = setInterval(function() { goTo(idx + 1); }, 800);
    });
    el.addEventListener('mouseleave', function() {
        clearInterval(timer);
        goTo(0);
    });
});

const achievementsData = @json($achievementsJson);
const storeUrl         = '{{ route('admin.club.achievements.store', $club->slug) }}';
const baseEditUrl      = '{{ url('admin/club/' . $club->slug . '/achievements') }}';

const emptyForm = {
    title: '', description: '', tag: '', tag_icon: 'bi-trophy',
    image_path: '', remove_image: false,
    bg_from: '#f59e0b', bg_to: '#f97316',
    status: 'active', sort_order: 0,
    images: [], images_paths: [],
};

const achievementIcons = [
    { value: 'bi-trophy',          label: 'Trophy' },
    { value: 'bi-trophy-fill',     label: 'Trophy Fill' },
    { value: 'bi-award',           label: 'Award' },
    { value: 'bi-award-fill',      label: 'Award Fill' },
    { value: 'bi-star',            label: 'Star' },
    { value: 'bi-star-fill',       label: 'Star Fill' },
    { value: 'bi-medal',           label: 'Medal' },
    { value: 'bi-patch-check',     label: 'Verified' },
    { value: 'bi-patch-check-fill',label: 'Verified Fill' },
    { value: 'bi-patch-star',      label: 'Star Patch' },
    { value: 'bi-gem',             label: 'Gem' },
    { value: 'bi-crown',           label: 'Crown' },
    { value: 'bi-crown-fill',      label: 'Crown Fill' },
    { value: 'bi-shield-check',    label: 'Shield' },
    { value: 'bi-flag',            label: 'Flag' },
    { value: 'bi-flag-fill',       label: 'Flag Fill' },
    { value: 'bi-lightning',       label: 'Lightning' },
    { value: 'bi-lightning-fill',  label: 'Lightning Fill' },
    { value: 'bi-fire',            label: 'Fire' },
    { value: 'bi-rocket',          label: 'Rocket' },
    { value: 'bi-rocket-fill',     label: 'Rocket Fill' },
    { value: 'bi-bullseye',        label: 'Target' },
    { value: 'bi-graph-up-arrow',  label: 'Growth' },
    { value: 'bi-people',          label: 'Team' },
    { value: 'bi-people-fill',     label: 'Team Fill' },
    { value: 'bi-hand-thumbs-up',  label: 'Thumbs Up' },
    { value: 'bi-heart',           label: 'Heart' },
    { value: 'bi-heart-fill',      label: 'Heart Fill' },
    { value: 'bi-bookmark-star',   label: 'Bookmark Star' },
    { value: 'bi-emoji-smile',     label: 'Smile' },
];

function achievementsAdmin() {
    return {
        showModal:         false,
        showDetail:        false,
        detailAchievement: null,
        isEdit:            false,
        formAction:        storeUrl,
        formData:          { ...emptyForm },
        showIconPicker:    false,
        icons:             achievementIcons,

        openDetail(id) {
            this.detailAchievement = achievementsData.find(a => a.id === id) || null;
            this.showDetail = true;
        },

        openAdd() {
            this.isEdit         = false;
            this.formAction     = storeUrl;
            this.formData       = { ...emptyForm };
            this.showIconPicker = false;
            this.showModal      = true;
            resetAchievementImages();
            renderAchievementExistingThumbnails([]);
        },

        openEdit(id) {
            const a = achievementsData.find(a => a.id === id);
            if (!a) return;
            this.isEdit         = true;
            this.formAction     = baseEditUrl + '/' + id;
            this.formData       = { ...emptyForm, ...a, remove_image: false };
            this.showIconPicker = false;
            this.showModal      = true;
            resetAchievementImages();
            renderAchievementExistingThumbnails(a.images_paths || []);
        },

        deleteAchievement(id) {
            confirmAction({
                title:       'Delete Achievement',
                message:     'This achievement will be permanently removed.',
                confirmText: 'Delete',
                type:        'danger',
            }).then(confirmed => {
                if (!confirmed) return;
                fetch(baseEditUrl + '/' + id, {
                    method:  'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept':       'application/json',
                    },
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) location.reload();
                    else alert(data.message || 'Failed to delete achievement.');
                })
                .catch(() => alert('Failed to delete achievement.'));
            });
        },
    };
}
</script>
@endpush
@endsection
