@extends('layouts.admin-club')

@section('club-admin-content')

@php
$eventsJson = $events->map(function($e) {
    return [
        'id'           => $e->id,
        'title'        => $e->title,
        'date'         => $e->date->format('Y-m-d'),
        'date_label'   => $e->date->format('d M Y') . ($e->end_date ? ' – ' . $e->end_date->format('d M Y') : ''),
        'end_date'     => $e->end_date ? $e->end_date->format('Y-m-d') : '',
        'start_time'   => substr($e->start_time, 0, 5),
        'end_time'     => $e->end_time ? substr($e->end_time, 0, 5) : '',
        'time_label'   => \Carbon\Carbon::parse($e->start_time)->format('g:i A') . ($e->end_time ? ' – ' . \Carbon\Carbon::parse($e->end_time)->format('g:i A') : ''),
        'location'     => $e->location ?? '',
        'level'        => $e->level ?? '',
        'description'  => $e->description ?? '',
        'max_capacity' => $e->max_capacity,
        'tags_str'     => $e->tags ? implode(', ', $e->tags) : '',
        'tags'         => $e->tags ?? [],
        'color'        => $e->color ?? '#1d4ed8',
        'is_archived'  => (bool) $e->is_archived,
        'images'       => collect($e->images ?? [])->map(fn($p) => str_starts_with($p, 'http') ? $p : asset('storage/' . $p))->values()->toArray(),
        'images_paths' => $e->images ?? [],
    ];
});
@endphp

@php
    $activeEvents   = $events->filter(fn($e) => !$e->is_archived && !$e->hasEnded());
    $archivedEvents = $events->filter(fn($e) =>  $e->is_archived ||  $e->hasEnded());
@endphp

<div x-data="eventsAdmin()">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-foreground">Events</h2>
            <p class="text-sm text-muted-foreground mt-0.5">Manage events shown on your public club page</p>
        </div>
        <div class="flex gap-2">
            <button @click="showArchiveModal = true" class="btn btn-outline-secondary">
                <i class="bi bi-archive mr-2"></i>Archived
                @if($archivedEvents->isNotEmpty())
                <span class="badge bg-muted text-muted-foreground ms-1">{{ $archivedEvents->count() }}</span>
                @endif
            </button>
            <button @click="openAdd()" class="btn btn-primary">
                <i class="bi bi-plus-lg mr-2"></i>Add Event
            </button>
        </div>
    </div>

    {{-- Session messages --}}
    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif

    {{-- Events list --}}
    @if($activeEvents->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-16">
                <i class="bi bi-calendar-x text-muted-foreground" style="font-size:2.5rem;opacity:.3;"></i>
                <p class="mt-3 text-muted-foreground">No events yet. Add your first event to display it on your club page.</p>
                <button @click="openAdd()" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-lg mr-2"></i>Add Event
                </button>
            </div>
        </div>
    @else
        <div class="flex flex-col gap-3">
            @foreach($activeEvents as $event)
            @php
                $isPast    = $event->date->isPast();
                $pillColor = $event->color ?: '#1d4ed8';
                $tagsArr   = is_array($event->tags) ? $event->tags : [];
            @endphp
            <div class="card border-0 shadow-sm overflow-hidden cursor-pointer {{ $event->isOngoing() ? 'border border-green-200 bg-green-50' : ($isPast ? 'opacity-60' : '') }}"
                 @click="openDetail({{ $event->id }})">
                <div class="card-body p-4">
                    <div class="flex items-start gap-4">

                        {{-- Date pill --}}
                        <div class="flex-shrink-0 rounded-xl text-white text-center px-3 py-2 min-w-[56px]"
                             style="background:{{ $pillColor }};">
                            <div class="text-xs font-semibold uppercase">{{ $event->date->format('D') }}</div>
                            <div class="text-2xl font-extrabold leading-none">{{ $event->date->format('d') }}</div>
                            <div class="text-xs font-semibold uppercase">{{ $event->date->format('M') }}</div>
                        </div>

                        {{-- Main info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="font-semibold text-foreground">{{ $event->title }}</span>
                                @if($event->isOngoing())
                                    <span class="status-chip status-ongoing"><span class="live-dot"></span> Ongoing</span>
                                @endif
                            </div>
                            <div class="flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-muted-foreground mb-1">
                                <span><i class="bi bi-calendar mr-1"></i>{{ $event->date->format('d M Y') }}{{ $event->end_date ? ' – ' . $event->end_date->format('d M Y') : '' }}</span>
                                <span><i class="bi bi-clock mr-1"></i>{{ \Carbon\Carbon::parse($event->start_time)->format('g:i A') }}{{ $event->end_time ? ' – ' . \Carbon\Carbon::parse($event->end_time)->format('g:i A') : '' }}</span>
                                @if($event->location)<span><i class="bi bi-geo-alt mr-1"></i>{{ $event->location }}</span>@endif
                                @if($event->level)<span><i class="bi bi-bar-chart mr-1"></i>{{ $event->level }}</span>@endif
                                @if($event->max_capacity)<span><i class="bi bi-people mr-1"></i>{{ $event->max_capacity }} spots</span>@endif
                            </div>
                            @if($tagsArr)
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @foreach($tagsArr as $tag)
                                        <span class="badge bg-muted/40 text-foreground text-xs border border-border">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Actions --}}
                        <div class="flex gap-2 flex-shrink-0" @click.stop>
                            <button @click="openEdit({{ $event->id }})"
                                    class="btn btn-sm btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="{{ route('admin.club.events.archive', [$club->slug, $event->id]) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Archive">
                                    <i class="bi bi-archive"></i>
                                </button>
                            </form>
                            <button @click="deleteEvent({{ $event->id }})"
                                    class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>

                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif

    {{-- ===== ARCHIVED EVENTS MODAL ===== --}}
    <div x-show="showArchiveModal" x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showArchiveModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="modal-content border-0 shadow-lg w-full max-w-xl relative" @click.stop>
                <div class="modal-header border-b border-border px-6 py-4">
                    <h5 class="modal-title text-lg font-semibold">
                        <i class="bi bi-archive mr-2"></i>Archived Events
                    </h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="showArchiveModal = false">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="modal-body px-6 py-4 space-y-3 max-h-[70vh] overflow-y-auto">
                    @forelse($archivedEvents as $event)
                    @php $pillColor = $event->color ?: '#1d4ed8'; @endphp
                    <div class="flex items-center gap-3 p-3 rounded-lg border border-border bg-muted/20">
                        <div class="flex-shrink-0 rounded-lg text-white text-center px-2 py-1.5 min-w-[46px]"
                             style="background:{{ $pillColor }};">
                            <div class="text-[10px] font-semibold uppercase">{{ $event->date->format('D') }}</div>
                            <div class="text-lg font-extrabold leading-none">{{ $event->date->format('d') }}</div>
                            <div class="text-[10px] font-semibold uppercase">{{ $event->date->format('M') }}</div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm text-foreground mb-0.5">{{ $event->title }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ \Carbon\Carbon::parse($event->start_time)->format('g:i A') }}
                                @if($event->location) · {{ $event->location }}@endif
                            </p>
                        </div>
                        <div class="flex gap-2 flex-shrink-0">
                            <form method="POST" action="{{ route('admin.club.events.archive', [$club->slug, $event->id]) }}">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Unarchive">
                                    <i class="bi bi-arrow-counterclockwise mr-1"></i>Unarchive
                                </button>
                            </form>
                            <button @click="deleteEvent({{ $event->id }})"
                                    class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-10 text-muted-foreground">
                        <i class="bi bi-archive" style="font-size:2rem;opacity:.3;"></i>
                        <p class="mt-2 text-sm">No archived events.</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

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
        <div class="my-auto w-full max-w-2xl">
            <div class="modal-content border-0 shadow-lg relative" @click.stop @mousedown.stop x-show="detailEvent">

                <template x-if="detailEvent">
                    <div>
                        {{-- Hero images scroll strip --}}
                        <div x-show="detailEvent.images && detailEvent.images.length > 0"
                             x-data="{ imgIdx: 0 }"
                             x-effect="imgIdx = 0"
                             class="relative rounded-t-xl bg-black overflow-hidden" style="height:260px;">
                            <div class="flex h-full overflow-x-auto snap-x snap-mandatory"
                                 style="scroll-behavior:smooth; -webkit-overflow-scrolling:touch; scrollbar-width:none; cursor:grab;"
                                 @scroll.debounce.50ms="imgIdx = Math.round($el.scrollLeft / $el.offsetWidth)"
                                 @click.stop
                                 @mousedown.stop.prevent="initStripDrag($event, $el)"
                                 x-ref="strip">
                                <template x-for="img in detailEvent.images" :key="img">
                                    <img :src="img" class="snap-start flex-shrink-0 w-full h-full object-cover select-none" draggable="false">
                                </template>
                            </div>
                            {{-- Prev / Next arrows --}}
                            <button x-show="detailEvent.images.length > 1"
                                    @mousedown.stop @click.stop="$refs.strip.scrollTo({ left: (imgIdx - 1 + detailEvent.images.length) % detailEvent.images.length * $refs.strip.offsetWidth, behavior: 'smooth' })"
                                    class="carousel-arrow carousel-arrow--prev">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/></svg>
                            </button>
                            <button x-show="detailEvent.images.length > 1"
                                    @mousedown.stop @click.stop="$refs.strip.scrollTo({ left: (imgIdx + 1) % detailEvent.images.length * $refs.strip.offsetWidth, behavior: 'smooth' })"
                                    class="carousel-arrow carousel-arrow--next">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>
                            </button>
                            {{-- Dots --}}
                            <div x-show="detailEvent.images.length > 1"
                                 class="absolute bottom-2 left-1/2 -translate-x-1/2 flex gap-1.5 pointer-events-auto">
                                <template x-for="(img, i) in detailEvent.images" :key="i">
                                    <button @click.stop="$refs.strip.scrollTo({ left: i * $refs.strip.offsetWidth, behavior: 'smooth' })"
                                            :class="imgIdx === i ? 'bg-white w-4' : 'bg-white/50 w-2'"
                                            class="h-2 rounded-full transition-all duration-200"></button>
                                </template>
                            </div>
                            {{-- Close button on image --}}
                            <button @mousedown.stop @click.stop="showDetail = false"
                                    class="absolute top-2.5 right-2.5 w-8 h-8 rounded-full bg-black/50 hover:bg-black/75 text-white flex items-center justify-center transition-colors z-10">
                                <i class="bi bi-x-lg text-xs"></i>
                            </button>
                        </div>

                        {{-- Header --}}
                        <div class="px-6 pt-5 pb-0 flex items-start gap-3">
                            <div class="flex items-center gap-3 flex-1">
                                <div class="rounded-xl text-white text-center px-2.5 py-1.5 flex-shrink-0"
                                     :style="'background:' + detailEvent.color">
                                    <div class="text-[10px] font-bold uppercase" x-text="new Date(detailEvent.date).toLocaleDateString('en',{weekday:'short'})"></div>
                                    <div class="text-xl font-extrabold leading-none" x-text="new Date(detailEvent.date).getDate()"></div>
                                    <div class="text-[10px] font-bold uppercase" x-text="new Date(detailEvent.date).toLocaleDateString('en',{month:'short'})"></div>
                                </div>
                                <div>
                                    <h4 class="text-lg font-bold text-foreground mb-0" x-text="detailEvent.title"></h4>
                                    <p class="text-xs text-muted-foreground mb-0" x-text="detailEvent.date_label"></p>
                                </div>
                            </div>
                            <button type="button" class="text-muted-foreground hover:text-foreground flex-shrink-0 opacity-0 pointer-events-none" @click="showDetail = false">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>

                        {{-- Meta --}}
                        <div class="px-6 py-4 flex flex-wrap gap-x-5 gap-y-2 text-sm text-muted-foreground border-b border-border">
                            <span><i class="bi bi-clock mr-1"></i><span x-text="detailEvent.time_label"></span></span>
                            <span x-show="detailEvent.location"><i class="bi bi-geo-alt mr-1"></i><span x-text="detailEvent.location"></span></span>
                            <span x-show="detailEvent.level"><i class="bi bi-bar-chart mr-1"></i><span x-text="detailEvent.level"></span></span>
                            <span x-show="detailEvent.max_capacity"><i class="bi bi-people mr-1"></i><span x-text="detailEvent.max_capacity + ' spots'"></span></span>
                        </div>

                        {{-- Description --}}
                        <div class="px-6 py-4" x-show="detailEvent.description">
                            <p class="text-sm text-foreground leading-relaxed mb-0" x-text="detailEvent.description"></p>
                        </div>

                        {{-- Tags --}}
                        <div class="px-6 pb-4 flex flex-wrap gap-2" x-show="detailEvent.tags && detailEvent.tags.length > 0">
                            <template x-for="tag in detailEvent.tags" :key="tag">
                                <span class="badge bg-muted/40 text-foreground text-xs border border-border" x-text="tag"></span>
                            </template>
                        </div>

                        {{-- Footer actions --}}
                        <div class="px-6 py-4 border-t border-border flex justify-end gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" @click="showDetail = false; openEdit(detailEvent.id)">
                                <i class="bi bi-pencil mr-1"></i> Edit
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" @click="showDetail = false">Close</button>
                        </div>
                    </div>
                </template>

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
            <div class="modal-content border-0 shadow-lg w-full max-w-xl relative" @click.stop>
                <div class="modal-header border-b border-border px-6 py-4">
                    <h5 class="modal-title text-lg font-semibold" x-text="isEdit ? 'Edit Event' : 'Add Event'"></h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="showModal = false">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form :action="formAction" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="_method" :value="isEdit ? 'PUT' : 'POST'">
                    <div class="modal-body px-6 py-4 space-y-4">
                        @include('admin.club.events.partials.form-fields')
                    </div>
                    <div class="modal-footer border-t border-border px-6 py-4 flex justify-end gap-3">
                        <button type="button" class="btn btn-outline-secondary" @click="showModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg mr-1"></i>
                            <span x-text="isEdit ? 'Update Event' : 'Save Event'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

{{-- Event Image Cropper Modal --}}
<div class="modal fade" id="eventCropperModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width:75%; width:900px;">
        <div class="modal-content shadow-lg">
            <div class="modal-body p-4">
                <div class="mb-3 flex items-center gap-2">
                    <input type="file" id="eventCropperFileInput" class="form-control form-control-sm" accept="image/*">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div id="eventCropperCanvas" class="takeone-canvas" style="height:380px;"></div>
                <div class="grid grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Zoom</label>
                        <input type="range" id="eventCropperZoom" class="form-range" min="0" max="100" step="1" value="0">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Rotation</label>
                        <input type="range" id="eventCropperRot" class="form-range" min="-180" max="180" step="1" value="0">
                    </div>
                </div>
                <button type="button" id="eventCropperSave"
                        class="btn btn-success btn-lg font-bold w-full py-3 mt-3">
                    Crop & Add
                </button>
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
// ── Event multi-image cropper ──────────────────────────────────────────
let eventCropperInstance = null;
let eventCropperModal    = null;
let eventNewImages       = [];

function openEventCropper() {
    document.getElementById('eventCropperFileInput').value = '';
    document.getElementById('eventCropperZoom').value = 0;
    document.getElementById('eventCropperRot').value  = 0;
    if (!eventCropperModal) {
        eventCropperModal = new bootstrap.Modal(document.getElementById('eventCropperModal'));
    }
    eventCropperModal.show();
}

function resetEventImages() {
    eventNewImages = [];
    renderEventNewThumbnails();
}

$(function() {
    const zoomMin = 0.01, zoomMax = 3;

    function initEventCropper(url) {
        if (eventCropperInstance) {
            try { eventCropperInstance.destroy(); } catch(e) {}
            eventCropperInstance = null;
        }
        document.getElementById('eventCropperCanvas').innerHTML = '';
        eventCropperInstance = new Cropme(document.getElementById('eventCropperCanvas'), {
            container: { width: '100%', height: 380 },
            viewport: { width: 400, height: 300, type: 'square', border: { enable: true, width: 2, color: '#fff' } },
            transformOrigin: 'viewport',
            zoom: { min: zoomMin, max: zoomMax, enable: true, mouseWheel: true, slider: false },
            rotation: { enable: true, slider: false }
        });
        eventCropperInstance.bind({ url }).then(() => {
            $('#eventCropperZoom').val(0);
            $('#eventCropperRot').val(0);
        });
    }

    $('#eventCropperModal').on('shown.bs.modal', function() {
        if (eventCropperInstance) {
            try { eventCropperInstance.destroy(); } catch(e) {}
            eventCropperInstance = null;
        }
        document.getElementById('eventCropperCanvas').innerHTML = '';
    });

    $('#eventCropperFileInput').on('change', function() {
        if (!this.files[0]) return;
        const reader = new FileReader();
        reader.onload = e => initEventCropper(e.target.result);
        reader.readAsDataURL(this.files[0]);
    });

    $('#eventCropperZoom').on('input', function() {
        if (!eventCropperInstance?.properties?.image) return;
        const scale = zoomMin + (zoomMax - zoomMin) * (this.value / 100);
        eventCropperInstance.properties.scale = Math.min(Math.max(scale, zoomMin), zoomMax);
        const p = eventCropperInstance.properties;
        p.image.style.transform = `translate3d(${p.x}px,${p.y}px,0) scale(${p.scale}) rotate(${p.deg}deg)`;
    });

    $('#eventCropperRot').on('input', function() {
        if (eventCropperInstance) eventCropperInstance.rotate(parseInt(this.value));
    });

    $('#eventCropperSave').on('click', function() {
        if (!eventCropperInstance || !eventCropperInstance.properties?.image) {
            alert('Please select an image first.');
            return;
        }
        const btn = $(this);
        btn.prop('disabled', true).text('Processing...');
        eventCropperInstance.crop({ type: 'base64' }).then(base64 => {
            eventNewImages.push(base64);
            renderEventNewThumbnails();
            eventCropperModal.hide();
            btn.prop('disabled', false).text('Crop & Add');
        }).catch(err => {
            console.error('Crop failed:', err);
            btn.prop('disabled', false).text('Crop & Add');
        });
    });
});

function renderEventNewThumbnails() {
    const previews = document.getElementById('eventNewPreviews');
    const inputs   = document.getElementById('eventBase64Inputs');
    if (!previews || !inputs) return;

    previews.innerHTML = '';
    inputs.innerHTML   = '';

    eventNewImages.forEach((b64, idx) => {
        const wrap = document.createElement('div');
        wrap.className = 'relative group';
        wrap.innerHTML = `
            <img src="${b64}" class="w-20 h-20 object-cover rounded-lg border border-gray-200">
            <button type="button" class="absolute -top-1.5 -right-1.5 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                <i class="bi bi-x"></i>
            </button>`;
        wrap.querySelector('button').addEventListener('click', () => {
            eventNewImages.splice(idx, 1);
            renderEventNewThumbnails();
        });
        previews.appendChild(wrap);

        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'event_images_base64[]';
        input.value = b64;
        inputs.appendChild(input);
    });
}
</script>
@endpush

@push('scripts')
<script>
function initStripDrag(e, el) {
    const startX     = e.pageX;
    const baseScroll = el.scrollLeft;
    document.body.style.cursor    = 'grabbing';
    document.body.style.userSelect = 'none';

    const onMove = ev => {
        el.scrollLeft = baseScroll - (ev.pageX - startX);
    };
    const onUp = () => {
        document.body.style.cursor    = '';
        document.body.style.userSelect = '';
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup',   onUp);
    };
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup',   onUp);
}

const eventsData    = @json($eventsJson);
const storeUrl      = '{{ route('admin.club.events.store', $club->slug) }}';
const baseEditUrl   = '{{ url('admin/club/' . $club->slug . '/events') }}';

const emptyForm = {
    title: '', date: '', end_date: '', start_time: '', end_time: '',
    location: '', level: '', description: '',
    max_capacity: '', tags_str: '', tags: [],
    color: '#1d4ed8', images: [], images_paths: [],
};

function eventsAdmin() {
    return {
        showModal:        false,
        showArchiveModal: false,
        showDetail:       false,
        detailEvent:      null,
        isEdit:           false,
        formAction:       storeUrl,
        formData:         { ...emptyForm },
        locationTab:      'facility',

        openDetail(id) {
            this.detailEvent = eventsData.find(e => e.id === id) || null;
            this.showDetail  = true;
        },

        openAdd() {
            this.isEdit      = false;
            this.formAction  = storeUrl;
            this.formData    = { ...emptyForm };
            this.locationTab = 'facility';
            this.showModal   = true;
            resetEventImages();
        },

        openEdit(id) {
            const ev = eventsData.find(e => e.id === id);
            if (!ev) return;
            this.isEdit      = true;
            this.formAction  = baseEditUrl + '/' + id;
            this.formData    = { ...emptyForm, ...ev };
            this.locationTab = ev.location?.startsWith('http') ? 'url' : 'facility';
            this.showModal   = true;
            resetEventImages();
        },

        deleteEvent(id) {
            confirmAction({
                title:       'Delete Event',
                message:     'This event will be permanently removed.',
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
                    else alert(data.message || 'Failed to delete event.');
                })
                .catch(() => alert('Failed to delete event.'));
            });
        },
    };
}
</script>
@endpush
@endsection
