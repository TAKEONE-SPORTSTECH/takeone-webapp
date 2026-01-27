@extends('layouts.admin-club')

@section('club-admin-content')
<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold mb-1">Messages</h2>
            <p class="text-muted mb-0">Communicate with your members</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMessageModal">
            <i class="bi bi-plus-lg me-2"></i>New Message
        </button>
    </div>

    <div class="row g-4">
        <!-- Conversations List -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0">
                    <input type="text" class="form-control" placeholder="Search conversations...">
                </div>
                <div class="card-body p-0" style="max-height: 500px; overflow-y: auto;">
                    @if(isset($conversations) && count($conversations) > 0)
                        @foreach($conversations as $conversation)
                        <div class="d-flex align-items-center gap-3 p-3 border-bottom conversation-item {{ $conversation->unread ? 'bg-light' : '' }}" style="cursor: pointer;">
                            @if($conversation->user->profile_picture)
                            <img src="{{ asset('storage/' . $conversation->user->profile_picture) }}" alt="" class="rounded-circle" style="width: 45px; height: 45px; object-fit: cover;">
                            @else
                            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                <span class="text-white fw-bold">{{ strtoupper(substr($conversation->user->full_name ?? 'U', 0, 1)) }}</span>
                            </div>
                            @endif
                            <div class="flex-grow-1 min-width-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <p class="fw-semibold mb-0 text-truncate">{{ $conversation->user->full_name ?? 'Unknown' }}</p>
                                    <small class="text-muted">{{ $conversation->last_message_at ? $conversation->last_message_at->diffForHumans(null, true) : '' }}</small>
                                </div>
                                <p class="text-muted small mb-0 text-truncate">{{ $conversation->last_message ?? 'No messages yet' }}</p>
                            </div>
                            @if($conversation->unread_count > 0)
                            <span class="badge bg-primary rounded-pill">{{ $conversation->unread_count }}</span>
                            @endif
                        </div>
                        @endforeach
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-chat-dots text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2 mb-0">No conversations yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column" style="min-height: 500px;">
                    <div class="text-center my-auto">
                        <i class="bi bi-chat-square-text text-muted" style="font-size: 4rem;"></i>
                        <h5 class="mt-3 mb-2">Select a conversation</h5>
                        <p class="text-muted">Choose a conversation from the list to start messaging</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Message Modal -->
<div class="modal fade" id="newMessageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">New Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('admin.club.messages.send', $club->id) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">To</label>
                        <select name="recipient_id" class="form-select" required>
                            <option value="">Select member...</option>
                            @foreach($members ?? [] as $member)
                            <option value="{{ $member->user_id }}">{{ $member->user->full_name ?? 'Unknown' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="message" class="form-control" rows="4" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Send Message</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.conversation-item:hover {
    background-color: hsl(var(--muted)) !important;
}
</style>
@endsection
