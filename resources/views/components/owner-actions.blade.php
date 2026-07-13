@props([
    'id',                    // entity id
    'deleteUrl',             // DELETE endpoint
    'editEvent' => null,     // window event dispatched on Edit (carries {id, data}); omit to hide Edit
    'hideUrl'   => null,     // POST toggle-visibility endpoint; omit to hide the Hide option
    'hidden'    => false,    // current hidden state
    'label'     => 'item',   // used in the delete confirmation
    'data'      => null,     // optional array of the entity's editable fields, passed to the edit modal
])
{{-- Owner-only floating actions menu for a card (Edit / Hide / Delete). The
     panel is teleported to <body> so a card's overflow never clips it. Place
     inside a positioned card marked data-owner-card. Requires the shared
     window.ownerActions() component (partials/owner-controls). --}}
<div class="owner-actions"
     x-data="ownerActions({
        id: {{ (int) $id }},
        deleteUrl: @js($deleteUrl),
        hideUrl: @js($hideUrl),
        editEvent: @js($editEvent),
        hidden: {{ $hidden ? 'true' : 'false' }},
        label: @js($label),
        data: {{ $data !== null ? \Illuminate\Support\Js::from($data) : 'null' }},
     })"
     @keydown.escape.window="open=false">
    <button type="button" @click.stop.prevent="toggle($el)"
            class="w-7 h-7 rounded-full bg-white/90 backdrop-blur shadow-sm border border-gray-100 flex items-center justify-center text-gray-600 hover:bg-white transition-colors"
            aria-label="{{ __('Actions') }}">
        <i class="bi bi-three-dots text-sm"></i>
    </button>
    <template x-teleport="body">
        <div x-show="open" x-cloak>
            <div class="fixed inset-0 z-[65]" @click="open=false"></div>
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                 :style="`top:${y}px; right:${r}px; transform-origin: top right;`"
                 class="fixed z-[70] w-40 bg-white rounded-xl shadow-lg border border-gray-100 py-1">
                <template x-if="editEvent">
                    <button type="button" @click="edit()" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                        <i class="bi bi-pencil"></i> {{ __('Edit') }}
                    </button>
                </template>
                <template x-if="hideUrl">
                    <button type="button" @click="toggleHide()" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-amber-700 hover:bg-amber-50 transition-colors">
                        <i class="bi" :class="hidden ? 'bi-eye' : 'bi-eye-slash'"></i>
                        <span x-text="hidden ? @js(__('Show')) : @js(__('Hide'))"></span>
                    </button>
                </template>
                <button type="button" @click="remove()" class="w-full flex items-center gap-2.5 px-3 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                    <i class="bi bi-trash"></i> {{ __('Delete') }}
                </button>
            </div>
        </div>
    </template>
</div>
