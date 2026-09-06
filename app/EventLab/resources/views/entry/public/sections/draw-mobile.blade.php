{{-- The board owns no division switcher of its own here
     (show-divisions="false"); the chips below drive it through
     window.BracketBoard.show(). --}}
<div x-data="{ divisions: [], division: null }"
     @bracket:loaded="divisions = $event.detail.divisions; division = $event.detail.division"
     @bracket:state="divisions = $event.detail.divisions; division = $event.detail.division">
    @php $drawDivisions = ($e['draw']['published'] ?? false) ? $e['draw']['divisions'] : count($e['divisions']); @endphp
    <div class="px-4 pt-3">
        <p class="text-[13px] text-muted-foreground leading-relaxed">{{ ($e['draw']['published'] ?? false) ? __('events.public_draw_lead') : __('events.public_draw_empty_body') }}</p>

        <div class="flex flex-wrap gap-1.5 mt-3">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold"
                  style="color: {{ $ev }}; background: {{ $evSoft }};">
                <i class="bi bi-diagram-3 bracket-icon"></i>{{ trans_choice('events.public_draw_divisions', $drawDivisions, ['n' => $drawDivisions]) }}
            </span>
            @if($e['draw']['published'] ?? false)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-bold bg-muted text-muted-foreground">
                    <i class="bi bi-people"></i>{{ trans_choice('events.public_draw_competitors', $e['draw']['entrants'], ['n' => $e['draw']['entrants']]) }}
                </span>
            @endif
        </div>

        {{-- Division chips in the EVENT's colour rather than the
             board's own purple — what the component's
             `show-divisions="false"` seam exists for. --}}
        <div x-show="divisions.length > 1" x-cloak class="mt-4 -mx-1 px-1 overflow-x-auto pb-0.5">
            <div class="flex items-center gap-2 w-max">
                <template x-for="d in divisions" :key="d.id">
                    <button type="button" @click="window.BracketBoard.show(d.id)"
                            class="m-press flex items-center gap-2 px-3.5 py-2 rounded-full border text-xs font-bold whitespace-nowrap transition-colors"
                            :class="d.id === division ? 'text-white shadow-sm' : 'bg-white text-foreground border-gray-200'"
                            :style="d.id === division ? 'background: {{ $e['color'] }}; border-color: {{ $e['color'] }};' : ''">
                        <span x-text="d.name"></span>
                        <span x-text="d.entrants"
                              class="px-1.5 py-0.5 rounded-full text-[0.6rem] font-extrabold"
                              :class="d.id === division ? 'bg-white/20 text-white' : 'bg-muted text-muted-foreground'"></span>
                    </button>
                </template>
            </div>
        </div>
    </div>

    @if($e['draw']['published'] ?? false)
        <div class="px-2 pt-3 pb-2">
            <x-eventlab::tournament-bracket
                id="public-draw"
                :data-url="$e['draw_url']"
                :event-uuid="$e['key']"
                :show-divisions="false"
                :bare="true"
                height="72vh" />
        </div>

        <p class="px-4 pb-4 text-[11.5px] text-muted-foreground/80 leading-relaxed">{{ __('events.public_draw_note') }}</p>
    @else
        {{-- Not drawn yet. A 60vh board saying "no draw" is a lot of
             empty page; one honest line is the whole answer. --}}
        @include('eventlab::entry.public.partials.empty', [
            'ev' => $ev, 'icon' => 'bi-diagram-3 bracket-icon',
            'title' => __('events.public_draw_empty_title'),
        ])
    @endif
</div>
