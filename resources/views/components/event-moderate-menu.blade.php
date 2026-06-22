@props(['id', 'name'])

{{-- Manager ⋯ menu for an event registrant. Calls the page-root Alpine moderate() method. --}}
<div class="relative flex-shrink-0" x-data="{ m: false }" @click.outside="m = false">
    <button type="button" @click="m = !m"
            class="m-press w-7 h-7 rounded-lg grid place-items-center text-muted-foreground hover:bg-muted transition-colors">
        <i class="bi bi-three-dots-vertical"></i>
    </button>
    <div x-show="m" x-cloak
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="absolute right-0 top-8 z-30 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 max-w-[calc(100vw-3rem)]">
        <button type="button" @click="m = false; moderate({{ (int) $id }}, @js($name), 'remove')"
                class="w-full px-3 py-2 text-xs font-semibold text-foreground hover:bg-muted flex items-center gap-2">
            <i class="bi bi-person-dash"></i> Remove from event
        </button>
        <button type="button" @click="m = false; moderate({{ (int) $id }}, @js($name), 'block')"
                class="w-full px-3 py-2 text-xs font-semibold text-amber-600 hover:bg-amber-50 flex items-center gap-2">
            <i class="bi bi-slash-circle"></i> Block this event
        </button>
        <button type="button" @click="m = false; moderate({{ (int) $id }}, @js($name), 'blacklist')"
                class="w-full px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 flex items-center gap-2">
            <i class="bi bi-ban"></i> Blacklist (all events)
        </button>
    </div>
</div>
