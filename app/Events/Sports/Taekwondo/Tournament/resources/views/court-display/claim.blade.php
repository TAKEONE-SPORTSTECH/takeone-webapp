@extends('layouts.app')

@section('content')
{{--
    Where the organiser lands after scanning a wall screen.

    They are standing in a hall, holding a phone, with a competition about to
    start — so this asks one question and offers only real answers: the events
    they can manage, and the mats those events actually run bouts on.
--}}
<div class="px-4 sm:px-6 lg:px-8 py-6 max-w-2xl mx-auto" x-data="{ picked: null, court: null }">

    <div class="flex items-center gap-3 mb-1">
        <span class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
            <i class="bi bi-display text-xl"></i>
        </span>
        <div>
            <h1 class="text-xl font-bold text-gray-900">{{ __('event-taekwondo_tournament::messages.claim_title') }}</h1>
            <p class="text-sm text-muted-foreground">
                {{ __('event-taekwondo_tournament::messages.claim_sub', ['code' => $code]) }}
            </p>
        </div>
    </div>

    @if ($events->isEmpty())
        <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center">
            <i class="bi bi-calendar-x text-3xl text-muted-foreground"></i>
            <p class="mt-3 text-sm text-muted-foreground">{{ __('event-taekwondo_tournament::messages.claim_no_events') }}</p>
        </div>
    @else
        <form method="POST" action="{{ route('court-display.claim.store', $code) }}" class="mt-6 space-y-6">
            @csrf

            <div class="space-y-3">
                @foreach ($events as $event)
                    <label class="block cursor-pointer">
                        <input type="radio" name="event" value="{{ $event['uuid'] }}" class="sr-only"
                               x-model="picked" @change="court = null" required>
                        <div class="rounded-xl border p-4 transition-colors"
                             :class="picked === '{{ $event['uuid'] }}' ? 'border-primary bg-primary/5' : 'border-gray-100 bg-white hover:bg-muted/60'">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-medium text-gray-900">{{ $event['title'] }}</span>
                                <span class="w-5 h-5 rounded-full border flex items-center justify-center flex-shrink-0"
                                      :class="picked === '{{ $event['uuid'] }}' ? 'border-primary' : 'border-gray-300'">
                                    <span class="w-2.5 h-2.5 rounded-full bg-primary" x-show="picked === '{{ $event['uuid'] }}'" x-cloak></span>
                                </span>
                            </div>

                            {{-- The mats this event genuinely has bouts on. --}}
                            <div class="mt-3 flex flex-wrap gap-2" x-show="picked === '{{ $event['uuid'] }}'" x-cloak
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0">
                                @forelse ($event['courts'] as $mat)
                                    <button type="button" @click.prevent="court = @js($mat)"
                                            class="px-3 py-1.5 rounded-full text-xs font-medium border transition-colors"
                                            :class="court === @js($mat) ? 'bg-primary text-white border-primary' : 'bg-white text-gray-700 border-gray-200 hover:bg-muted'">
                                        {{ $mat }}
                                    </button>
                                @empty
                                    <p class="text-xs text-muted-foreground">
                                        {{ __('event-taekwondo_tournament::messages.claim_no_courts') }}
                                    </p>
                                @endforelse
                            </div>
                        </div>
                    </label>
                @endforeach
            </div>

            <input type="hidden" name="court" :value="court">

            <button type="submit"
                    class="w-full bg-primary text-white px-4 py-3 rounded-lg hover:bg-primary/90 transition-colors font-medium disabled:opacity-40 disabled:cursor-not-allowed"
                    :disabled="!picked || !court">
                <i class="bi bi-display mr-2"></i>{{ __('event-taekwondo_tournament::messages.claim_submit') }}
            </button>
        </form>
    @endif
</div>
@endsection
