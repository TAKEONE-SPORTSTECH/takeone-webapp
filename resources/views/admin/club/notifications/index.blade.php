@extends('layouts.admin-club')

@section('club-admin-content')
<div>
    {{-- Header --}}
    <div class="flex flex-wrap gap-3 items-center justify-between mb-6">
        <div>
            <h2 class="tf-section-title">Notifications</h2>
            <p class="text-muted-foreground mb-0">History of all notifications sent to members</p>
        </div>
        <button @click="showNotificationModal = true" class="btn btn-primary">
            <i class="bi bi-send me-2"></i>Send Notification
        </button>
    </div>

    {{-- Table --}}
    <div class="bg-white border border-border rounded-xl overflow-hidden shadow-sm">
        @if($notifications->isEmpty())
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center mb-4">
                    <i class="bi bi-bell text-2xl text-primary"></i>
                </div>
                <h3 class="text-gray-600 font-medium mb-1">No notifications sent yet</h3>
                <p class="text-gray-400 text-sm">Click "Send Notification" to send your first message to members.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 border-b border-border">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wide">Sent At</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wide">Subject</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wide">Sent By</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wide">Recipients</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wide"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($notifications as $notif)
                        <tr x-data="{ expanded: false }" class="hover:bg-muted/20 transition-colors">
                            <td class="px-5 py-4 text-gray-500 whitespace-nowrap">
                                {{ $notif->sent_at->format('M d, Y') }}<br>
                                <span class="text-xs text-gray-400">{{ $notif->sent_at->format('H:i') }}</span>
                            </td>
                            <td class="px-5 py-4 text-gray-800 font-medium max-w-xs">
                                <span title="{{ $notif->subject }}">
                                    {{ Str::limit($notif->subject, 50) }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-gray-600 whitespace-nowrap">
                                {{ $notif->sender->full_name ?? 'Unknown' }}
                            </td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                                    {{ $notif->recipient_type === 'all' ? 'bg-primary/10 text-primary' : 'bg-blue-50 text-blue-600' }}">
                                    <i class="bi {{ $notif->recipient_type === 'all' ? 'bi-people' : 'bi-person-check' }}"></i>
                                    {{ $notif->recipient_count }} {{ $notif->recipient_type === 'all' ? 'All Members' : 'Selected' }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <button @click="expanded = !expanded"
                                        class="text-xs text-primary hover:underline cursor-pointer bg-transparent border-0 p-0"
                                        x-text="expanded ? 'Hide' : 'View Message'">
                                </button>
                            </td>
                        </tr>
                        {{-- Expanded Message Row --}}
                        <tr x-show="expanded" x-cloak class="bg-muted/10">
                            <td colspan="5" class="px-5 py-4">
                                <div class="bg-primary/5 border-l-4 border-primary rounded-r-lg p-4 text-sm text-gray-700 whitespace-pre-line leading-relaxed">
                                    {{ $notif->message }}
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($notifications->hasPages())
                <div class="px-5 py-4 border-t border-border">
                    {{ $notifications->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
