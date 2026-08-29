@extends('layouts.app')

@section('content')
{{--
    Where an organiser lands after scanning a waiting screen.

    They are standing in a hall holding a phone with a competition about to
    start, so this asks three things and offers only real answers: the events
    they can manage that drive screens, the mats those events actually run bouts
    on, and the jobs that event's package can serve.

    The one rule the form makes visible rather than enforcing after the fact:
    a mat may have as many scoreboards and as many upcoming-matches boards as it
    has walls, but only ONE scoring table. A mat whose control is taken shows it
    as taken.
--}}
<div class="px-4 sm:px-6 lg:px-8 py-6 max-w-2xl mx-auto"
     x-data="{
        picked: null,
        court: null,
        surface: null,
        events: @js($events),
        ev() { return this.events.find(e => e.uuid === this.picked) || null; },
        allows(key) { return (this.ev()?.surfaces || []).includes(key); },
        controlTaken() { return (this.ev()?.controls || []).includes(this.court); },
        // Four lenses per mat. Shown as 'two free' rather than refused after
        // the fact, the same courtesy the scoring table gets.
        camerasUsed() { return (this.ev()?.cameras || {})[this.court] || 0; },
        camerasFull() { return this.camerasUsed() >= 4; },
        unavailable(key) {
            return (key === 'control' && this.controlTaken()) || (key === 'camera' && this.camerasFull());
        },
        // The hint becomes the REASON when a slot cannot be taken: a disabled
        // row with no explanation reads as a bug.
        reason(key, hint) {
            if (key === 'control' && this.controlTaken()) return @js(__('events.screen_role_control_taken'));
            if (key === 'camera') {
                return this.camerasFull()
                    ? @js(__('events.camera_role_full'))
                    : @js(__('events.camera_role_free')).replace(':free', 4 - this.camerasUsed());
            }
            return hint;
        },
        ok() {
            return this.picked && this.court && this.surface
                && ! (this.surface === 'control' && this.controlTaken())
                && ! (this.surface === 'camera' && this.camerasFull());
        },
     }">

    <div class="flex items-center gap-3 mb-1">
        <span class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
            <i class="bi {{ ($isCamera ?? false) ? 'bi-camera-video' : 'bi-display' }} text-xl"></i>
        </span>
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ ($isCamera ?? false) ? __('events.camera_claim_title') : __('events.screen_claim_title') }}</h1>
            <p class="text-sm text-muted-foreground">{{ __('events.screen_claim_sub', ['code' => $code]) }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    @if ($events->isEmpty())
        <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
            <i class="bi bi-calendar-x text-3xl text-muted-foreground"></i>
            <p class="mt-3 text-sm text-muted-foreground">{{ __('events.screen_claim_no_events') }}</p>
        </div>
    @else
        <form method="POST" action="{{ route('screen.claim.store', $code) }}" class="mt-6 space-y-6">
            @csrf

            {{-- 1 · the event --}}
            <div class="space-y-3">
                <p class="text-sm font-medium text-gray-700">{{ __('events.screen_claim_event') }}</p>

                @foreach ($events as $event)
                    <label class="block cursor-pointer">
                        <input type="radio" name="event" value="{{ $event['uuid'] }}" class="sr-only"
                               x-model="picked" @change="court = null; surface = null" required>
                        <div class="rounded-xl border p-4 transition-colors"
                             :class="picked === '{{ $event['uuid'] }}' ? 'border-primary bg-primary/5' : 'border-gray-100 bg-white hover:bg-muted/60'">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-medium text-gray-900">{{ $event['title'] }}</span>
                                <span class="w-5 h-5 rounded-full border flex items-center justify-center flex-shrink-0"
                                      :class="picked === '{{ $event['uuid'] }}' ? 'border-primary' : 'border-gray-300'">
                                    <span class="w-2.5 h-2.5 rounded-full bg-primary" x-show="picked === '{{ $event['uuid'] }}'" x-cloak></span>
                                </span>
                            </div>

                            {{-- The mats this event genuinely has bouts on --}}
                            <div class="mt-3 flex flex-wrap gap-2" x-show="picked === '{{ $event['uuid'] }}'" x-cloak
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0">
                                @forelse ($event['courts'] as $mat)
                                    <button type="button" @click.prevent="court = @js($mat); if (surface === 'control' && controlTaken()) surface = null"
                                            class="px-3 py-1.5 rounded-full text-xs font-medium border transition-colors"
                                            :class="court === @js($mat) ? 'bg-primary text-white border-primary' : 'bg-white text-gray-700 border-gray-200 hover:bg-muted'">
                                        {{ $mat }}
                                    </button>
                                @empty
                                    <p class="text-xs text-muted-foreground">{{ __('events.screen_claim_no_mats') }}</p>
                                @endforelse
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>

            <input type="hidden" name="court" :value="court">

            {{-- 2 · the job --}}
            <div x-show="picked && court" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0">
                <p class="text-sm font-medium text-gray-700 mb-2">{{ __('events.screen_claim_surface') }}</p>

                <div class="space-y-2">
                    @foreach ([
                        ['bout', 'bi-trophy', 'screen_role_bout', 'screen_role_bout_hint'],
                        ['queue', 'bi-list-ol', 'screen_role_queue', 'screen_role_queue_hint'],
                        ['control', 'bi-sliders', 'screen_role_control', 'screen_role_control_hint'],
                        ['camera', 'bi-camera-video', 'camera_role', 'camera_role_hint'],
                    ] as [$value, $icon, $label, $hint])
                        <template x-if="allows(@js($value))">
                            <label class="flex items-center gap-3 p-3 rounded-xl border transition-colors"
                                   :class="{
                                        'border-primary bg-primary/5': surface === @js($value),
                                        'border-gray-200 bg-white hover:bg-muted/60 cursor-pointer': surface !== @js($value) && ! unavailable(@js($value)),
                                        'border-gray-200 bg-muted/40 opacity-60 cursor-not-allowed': unavailable(@js($value)),
                                   }">
                                <input type="radio" name="surface" value="{{ $value }}" class="sr-only"
                                       x-model="surface" :disabled="unavailable(@js($value))">
                                <span class="w-9 h-9 rounded-lg grid place-items-center flex-shrink-0"
                                      :class="surface === @js($value) ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'">
                                    <i class="bi {{ $icon }}"></i>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-gray-900">{{ __('events.'.$label) }}</span>
                                    {{-- The hint becomes the REASON when the slot
                                         is unavailable: a disabled control with no
                                         explanation reads as a bug. --}}
                                    <span class="block text-xs text-muted-foreground"
                                          x-text="reason(@js($value), @js(__('events.'.$hint)))"></span>
                                </span>
                                <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
                                      :class="surface === @js($value) ? 'border-primary' : 'border-gray-300'">
                                    <span class="w-2.5 h-2.5 rounded-full bg-primary" x-show="surface === @js($value)" x-cloak></span>
                                </span>
                            </label>
                        </template>
                    @endforeach
                </div>
            </div>

            <button type="submit"
                    class="w-full bg-primary text-white px-4 py-3 rounded-lg hover:bg-primary/90 transition-colors font-medium disabled:opacity-40 disabled:cursor-not-allowed"
                    :disabled="! ok()">
                <i class="bi bi-display mr-2"></i>{{ __('events.screen_claim_submit') }}
            </button>
        </form>
    @endif
</div>
@endsection
