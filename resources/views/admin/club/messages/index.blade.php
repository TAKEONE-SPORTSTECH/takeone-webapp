@extends('layouts.admin-club')

@section('club-admin-content')
<div x-data="{ showNewMessageModal: false }">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h2 class="tf-section-title">Messages</h2>
            <p class="text-muted-foreground mb-0">Communicate with your members</p>
        </div>
        <button class="btn btn-primary" @click="showNewMessageModal = true">
            <i class="bi bi-plus-lg mr-2"></i>New Message
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Conversations List -->
        <div class="lg:col-span-1">
            <div class="card border-0 shadow-sm h-full">
                <div class="card-header bg-white border-0">
                    <input type="text" class="form-control" placeholder="Search conversations...">
                </div>
                <div class="card-body p-0 max-h-[500px] overflow-y-auto">
                    @if(isset($conversations) && count($conversations) > 0)
                        @foreach($conversations as $conversation)
                        <div class="flex items-center gap-3 p-3 border-b border-border conversation-item {{ $conversation->unread ? 'bg-muted/30' : '' }} cursor-pointer hover:bg-muted/50 transition-colors">
                            @if($conversation->user->profile_picture)
                            <img src="{{ asset('storage/' . $conversation->user->profile_picture) }}" alt="" class="rounded-full w-11 h-11 object-cover">
                            @else
                            <div class="rounded-full bg-primary flex items-center justify-center w-11 h-11">
                                <span class="text-white font-bold">{{ strtoupper(substr($conversation->user->full_name ?? 'U', 0, 1)) }}</span>
                            </div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-center">
                                    <p class="font-semibold mb-0 truncate">{{ $conversation->user->full_name ?? 'Unknown' }}</p>
                                    <small class="text-muted-foreground">{{ $conversation->last_message_at ? $conversation->last_message_at->diffForHumans(null, true) : '' }}</small>
                                </div>
                                <p class="text-muted-foreground text-sm mb-0 truncate">{{ $conversation->last_message ?? 'No messages yet' }}</p>
                            </div>
                            @if($conversation->unread_count > 0)
                            <span class="badge bg-primary rounded-full">{{ $conversation->unread_count }}</span>
                            @endif
                        </div>
                        @endforeach
                    @else
                        <div class="text-center py-12">
                            <i class="bi bi-chat-dots text-muted-foreground text-5xl"></i>
                            <p class="text-muted-foreground mt-2 mb-0">No conversations yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="lg:col-span-2">
            <div class="card border-0 shadow-sm h-full">
                <div class="card-body flex flex-col min-h-[500px]">
                    <div class="text-center my-auto">
                        <i class="bi bi-chat-square-text text-muted-foreground text-6xl"></i>
                        <h5 class="mt-3 mb-2">Select a conversation</h5>
                        <p class="text-muted-foreground">Choose a conversation from the list to start messaging</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- New Message Modal -->
    <div x-show="showNewMessageModal"
         x-cloak
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="fixed inset-0 bg-black/50" @click="showNewMessageModal = false"></div>

        <div class="flex min-h-full items-center justify-center p-4">
            <div class="modal-content border-0 shadow-lg w-full max-w-md relative" @click.stop>
                <div class="modal-header border-0 px-6 py-4">
                    <h5 class="modal-title font-bold">New Message</h5>
                    <button type="button" class="btn-close" @click="showNewMessageModal = false"></button>
                </div>
                <div class="modal-body px-6 pb-6">
                    <form action="{{ route('admin.club.messages.send', $club->slug) }}" method="POST">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label">To</label>
                            <select name="recipient_id" class="form-select" required>
                                <option value="">Select member...</option>
                                @foreach($members ?? [] as $member)
                                <option value="{{ $member->user_id }}">{{ $member->user->full_name ?? 'Unknown' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-full">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
