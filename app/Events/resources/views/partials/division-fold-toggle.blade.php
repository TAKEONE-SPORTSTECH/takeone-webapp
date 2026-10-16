{{--
    The "Show all divisions" line under a long divisions list — both detail
    cards (mobile + desktop) include it inside an `x-data="{ divAll: false }"`
    scope; which groups fold is decided by DivisionSections::fold().
    Draws nothing when nothing is folded.
--}}
@php $fc = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($color ?? '')) ? $color : '#7c3aed'; @endphp
@if(($hidden ?? 0) > 0)
    <button type="button" @click="divAll = !divAll"
            class="m-press w-full mt-3 flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-[12px] font-bold transition-colors hover:brightness-95"
            style="background: {{ $fc }}0f; color: {{ $fc }};">
        <span x-show="!divAll">{{ trans_choice('events::events.divisions_more', $hidden, ['count' => $hidden]) }}</span>
        <span x-show="divAll" x-cloak>{{ __('events::events.divisions_less') }}</span>
        <i class="bi bi-chevron-down text-[10px] transition-transform" :class="divAll && 'rotate-180'"></i>
    </button>
@endif
