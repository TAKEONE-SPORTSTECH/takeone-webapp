{{--
    Shared "manage relationships" list body (Alpine state from ftManageData).
    Lists every edge connecting the selected person to the currently-loaded
    tree window, each removable — for correcting a mistaken or duplicate
    relative. Used inside the mobile bottom-sheet and the desktop modal.
--}}
<template x-if="edges.length === 0">
    <p class="text-sm text-muted-foreground text-center py-6">{{ __('No relationships to manage here.') }}</p>
</template>

<div class="space-y-2">
    <template x-for="edge in edges" :key="edge.edge_type + ':' + edge.edge_id">
        <div class="flex items-center gap-3 rounded-xl border border-gray-100 p-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs text-muted-foreground" x-text="edge.label"></p>
                <p class="text-sm font-semibold text-gray-900 truncate" x-text="edge.name"></p>
                <span x-show="edge.status === 'pending'" class="inline-flex items-center gap-1 text-[10px] font-semibold text-amber-600 mt-0.5">
                    <i class="bi bi-hourglass-split"></i>{{ __('Pending confirmation') }}
                </span>
            </div>
            <button type="button" @click="remove(edge)" :disabled="removingId === (edge.edge_type + ':' + edge.edge_id)"
                class="flex-shrink-0 px-3 py-2 rounded-lg border border-red-200 text-red-600 text-xs font-semibold hover:bg-red-50 transition disabled:opacity-50 flex items-center gap-1.5">
                <i class="bi" :class="removingId === (edge.edge_type + ':' + edge.edge_id) ? 'bi-arrow-repeat animate-spin' : 'bi-x-lg'"></i>
                {{ __('Remove') }}
            </button>
        </div>
    </template>
</div>
