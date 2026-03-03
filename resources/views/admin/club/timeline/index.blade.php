@extends('layouts.admin-club')

@section('club-admin-content')

@php
$postsJson = $posts->map(function($p) {
    return [
        'id'          => $p->id,
        'body'        => $p->body,
        'category'    => $p->category,
        'image_path'  => $p->image_path ?? '',
        'posted_at'   => $p->posted_at ? $p->posted_at->format('Y-m-d\TH:i') : '',
        'status'      => $p->status,
        'likes_count' => $p->likes_count,
        'comments_count' => $p->comments_count,
    ];
});
@endphp

<div x-data="timelineAdmin()">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl font-bold text-foreground">Timeline</h2>
            <p class="text-sm text-muted-foreground mt-0.5">Manage posts shown on your club's public timeline</p>
        </div>
        <button @click="openAdd()" class="btn btn-primary">
            <i class="bi bi-plus-lg mr-2"></i>New Post
        </button>
    </div>

    {{-- Session messages --}}
    @if(session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mb-4">{{ session('error') }}</div>
    @endif

    {{-- Posts list --}}
    @if($posts->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-16">
                <i class="bi bi-newspaper text-muted-foreground" style="font-size:2.5rem;opacity:.3;"></i>
                <p class="mt-3 text-muted-foreground">No posts yet. Create your first timeline post.</p>
                <button @click="openAdd()" class="btn btn-primary mt-2">
                    <i class="bi bi-plus-lg mr-2"></i>New Post
                </button>
            </div>
        </div>
    @else
        <div class="flex flex-col gap-3">
            @foreach($posts as $post)
            @php
                $isDraft = $post->status === 'draft';
            @endphp
            <div class="card border-0 shadow-sm overflow-hidden {{ $isDraft ? 'opacity-60' : '' }}">
                <div class="card-body p-4">
                    <div class="flex items-start gap-4">

                        {{-- Category badge & date --}}
                        <div class="flex-shrink-0 text-center min-w-[72px]">
                            <span class="inline-block text-xs font-semibold px-2 py-1 rounded-full mb-1
                                {{ $post->category === 'Highlight'     ? 'bg-yellow-100 text-yellow-700' :
                                   ($post->category === 'Community'    ? 'bg-green-100 text-green-700' :
                                   ($post->category === 'Update'       ? 'bg-blue-100 text-blue-700' :
                                                                         'bg-purple-100 text-purple-700')) }}">
                                {{ $post->category }}
                            </span>
                            <div class="text-xs text-muted-foreground mt-1">
                                {{ $post->posted_at?->format('d M Y') }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ $post->posted_at?->format('g:i A') }}
                            </div>
                        </div>

                        {{-- Main content --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-foreground mb-2 line-clamp-2">{{ $post->body }}</p>
                            <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
                                @if($post->image_path)
                                    <span><i class="bi bi-image mr-1"></i>Has image</span>
                                @endif
                                <span><i class="bi bi-heart mr-1"></i>{{ $post->likes_count }} likes</span>
                                <span><i class="bi bi-chat mr-1"></i>{{ $post->comments_count }} comments</span>
                                @if($isDraft)
                                    <span class="badge bg-gray-100 text-gray-500">Draft</span>
                                @endif
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex gap-2 flex-shrink-0">
                            <button @click="openEdit({{ $post->id }})"
                                    class="btn btn-sm btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button @click="deletePost({{ $post->id }})"
                                    class="btn btn-sm btn-outline-danger" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Image thumbnail if present --}}
                    @if($post->image_path)
                    <div class="mt-3 pl-[88px]">
                        <img src="{{ asset('storage/' . $post->image_path) }}"
                             class="rounded-lg object-cover" style="max-height:120px;" alt="Post image">
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    @endif

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
                    <h5 class="modal-title text-lg font-semibold" x-text="isEdit ? 'Edit Post' : 'New Post'"></h5>
                    <button type="button" class="text-muted-foreground hover:text-foreground" @click="showModal = false">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <form :action="formAction" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="_method" :value="isEdit ? 'PUT' : 'POST'">
                    <div class="modal-body px-6 py-4">
                        @include('admin.club.timeline.partials.form-fields')
                    </div>
                    <div class="modal-footer border-t border-border px-6 py-4 flex justify-end gap-3">
                        <button type="button" class="btn btn-outline-secondary" @click="showModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg mr-1"></i>
                            <span x-text="isEdit ? 'Update Post' : 'Save Post'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
const timelineData  = @json($postsJson);
const storeUrl      = '{{ route('admin.club.timeline.store', $club->slug) }}';
const baseEditUrl   = '{{ url('admin/club/' . $club->slug . '/timeline') }}';

const emptyForm = {
    body: '', category: 'Announcement', posted_at: '',
    status: 'published', image_path: '', image_preview: '', remove_image: false,
};

function timelineAdmin() {
    return {
        showModal:  false,
        isEdit:     false,
        formAction: storeUrl,
        formData:   { ...emptyForm },

        openAdd() {
            this.isEdit     = false;
            this.formAction = storeUrl;
            this.formData   = { ...emptyForm, posted_at: new Date().toISOString().slice(0,16) };
            this.showModal  = true;
        },

        openEdit(id) {
            const p = timelineData.find(p => p.id === id);
            if (!p) return;
            this.isEdit     = true;
            this.formAction = baseEditUrl + '/' + id;
            this.formData   = { ...emptyForm, ...p, image_preview: '' };
            this.showModal  = true;
        },

        handleImageChange(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => { this.formData.image_preview = e.target.result; };
            reader.readAsDataURL(file);
            this.formData.remove_image = false;
        },

        removeImage() {
            this.formData.image_preview = '';
            this.formData.image_path    = '';
            this.formData.remove_image  = true;
            // Clear the file input
            const input = document.querySelector('input[name="image"]');
            if (input) input.value = '';
        },

        deletePost(id) {
            confirmAction({
                title:       'Delete Post',
                message:     'This post and all its likes and comments will be permanently removed.',
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
                    else alert(data.message || 'Failed to delete post.');
                })
                .catch(() => alert('Failed to delete post.'));
            });
        },
    };
}
</script>
@endpush
@endsection
