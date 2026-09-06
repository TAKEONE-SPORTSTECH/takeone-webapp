{{--
    The workbench.

    Deliberately plain-spoken rather than decorated: this page's job is to say
    what is in the sandbox and let a real event be copied into it. It uses the
    platform's hero band and card tokens so it feels like the product, and
    nothing else.
--}}
@extends('layouts.app')

@section('title', 'Event sandbox')

@section('content')
<div x-data="labHome()" class="px-4 sm:px-6 lg:px-8 py-4">

    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-4 overflow-hidden shadow-sm mb-6 text-white relative"
         style="background: linear-gradient(150deg, #7c3aed, #7c3aedb0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-beaker"></i> Sandbox
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-shield-lock"></i> Super-admin only
                </span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">Event sandbox</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-database"></i>Its own tables. Real events are read, never written.
            </p>
        </div>
    </div>

    {{-- What is in the sandbox --}}
    <div class="mb-8">
        <h2 class="text-sm font-bold text-foreground mb-3">In the sandbox</h2>

        @forelse($events as $event)
            <a href="{{ route('testcode.event', $event->uuid) }}"
               class="block bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-3 hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-accent text-primary">
                        <i class="bi bi-trophy-fill text-lg"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-foreground truncate">{{ $event->title }}</p>
                        <p class="text-xs text-muted-foreground mt-0.5">
                            {{ $event->entrants_count }} entrants ·
                            {{ $event->variants_count }} variants ·
                            {{ $event->clubs_count }} clubs
                        </p>
                    </div>
                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs rtl:rotate-180"></i>
                </div>
            </a>
        @empty
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center">
                <p class="text-sm text-muted-foreground">Nothing copied in yet. Pick a real event below.</p>
            </div>
        @endforelse
    </div>


    {{-- Sandbox twins: real events, so every existing screen can be tried. --}}
    <div class="mb-8">
        <h2 class="text-sm font-bold text-foreground mb-1">Sandbox twins</h2>
        <p class="text-xs text-muted-foreground mb-3">
            Real events with competitors who are nobody — the poster, the draw and the console all open on them.
        </p>

        @forelse($twins as $twin)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-2 flex items-center gap-3"
                 id="twin-{{ $twin->uuid }}">
                <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-amber-50 text-amber-600">
                    <i class="bi bi-files text-lg"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-foreground truncate">{{ $twin->title }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        {{ $twin->registrations_count }} entrants ·
                        {{ $twin->date?->format('d M Y') }}
                    </p>
                </div>
                <a href="{{ route('testcode.me.events.show', $twin->uuid) }}"
                   class="border border-primary text-primary bg-transparent px-3 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors">
                    Open
                </a>
                <a href="{{ route('testcode.e', $twin->uuid) }}" target="_blank" rel="noopener"
                   title="The copied public page"
                   class="w-9 h-9 rounded-lg flex items-center justify-center bg-card text-foreground hover:bg-accent hover:shadow-sm transition-all border border-border">
                    <i class="bi bi-box-arrow-up-right"></i>
                </a>
                <button type="button"
                        @click="purge('{{ $twin->uuid }}', @js($twin->title))"
                        :disabled="busy"
                        class="border border-red-300 text-red-600 hover:bg-red-50 px-3 py-2 rounded-md text-sm font-medium transition-colors disabled:opacity-50">
                    Remove
                </button>
            </div>
        @empty
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center">
                <p class="text-sm text-muted-foreground">No twins yet. Twin one of the events below.</p>
            </div>
        @endforelse
    </div>

    {{-- Copy a real event in. Read-only on the source, always. --}}
    <div>
        <h2 class="text-sm font-bold text-foreground mb-1">Copy a real event in</h2>
        <p class="text-xs text-muted-foreground mb-3">
            Reads the event and its entrants into sandbox tables. The real event is never modified.
        </p>

        @foreach($sources as $source)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-2 flex items-center gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-foreground truncate">{{ $source->title }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        {{ $source->date?->format('d M Y') }} · {{ $source->sport ?: '—' }}
                    </p>
                </div>
                <button type="button"
                        @click="seed('{{ $source->uuid }}')"
                        :disabled="busy"
                        class="border border-primary text-primary bg-transparent px-4 py-2 rounded-md text-sm font-medium hover:bg-primary hover:text-white transition-colors disabled:opacity-50">
                    <span x-show="busy !== 'seed:{{ $source->uuid }}'">Copy in</span>
                    <span x-show="busy === 'seed:{{ $source->uuid }}'" x-cloak>Copying…</span>
                </button>
                <button type="button"
                        @click="twin('{{ $source->uuid }}')"
                        :disabled="busy"
                        class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors font-medium text-sm disabled:opacity-50">
                    <span x-show="busy !== 'twin:{{ $source->uuid }}'">Twin it</span>
                    <span x-show="busy === 'twin:{{ $source->uuid }}'" x-cloak>Twinning…</span>
                </button>
            </div>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
function labHome() {
    return {
        busy: null,

        async post(url, body, method = 'POST') {
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: body ? JSON.stringify(body) : undefined,
            });
            return [res, await res.json().catch(() => ({}))];
        },

        // Make a real twin, then go straight to it: seeing the event screens
        // open on it is the whole reason for making one.
        async twin(uuid) {
            if (this.busy) return;
            this.busy = 'twin:' + uuid;

            try {
                const [res, data] = await this.post(@json(route('testcode.twin')), { source: uuid });

                if (!res.ok || !data.success) {
                    window.showToast?.('error', data.message || 'Could not twin that event.');
                    return;
                }

                window.showToast?.('success', data.message);
                window.location.href = data.twin.url;
            } catch (e) {
                window.showToast?.('error', 'Could not twin that event.');
            } finally {
                this.busy = null;
            }
        },

        // Removing a twin deletes rows, so it asks first — and the server
        // refuses anything that is not a twin regardless of the answer.
        async purge(uuid, title) {
            if (this.busy) return;

            const ok = await window.confirmAction?.({
                title: 'Remove this twin?',
                message: title + ' and the sandbox people it invented will be deleted. The real event is untouched.',
                type: 'danger',
                confirmText: 'Remove',
            });
            if (!ok) return;

            this.busy = 'purge:' + uuid;

            try {
                const [res, data] = await this.post(
                    @json(url('testcode/twin')) + '/' + uuid, null, 'DELETE'
                );

                if (!res.ok || !data.success) {
                    window.showToast?.('error', data.message || 'Could not remove that twin.');
                    return;
                }

                window.showToast?.('success', data.message);
                document.getElementById('twin-' + uuid)?.remove();
            } catch (e) {
                window.showToast?.('error', 'Could not remove that twin.');
            } finally {
                this.busy = null;
            }
        },

        async seed(uuid) {
            if (this.busy) return;
            this.busy = 'seed:' + uuid;

            try {
                const [res, data] = await this.post(@json(route('testcode.seed')), { source: uuid });

                if (!res.ok || !data.success) {
                    window.showToast?.('error', data.message || 'Could not copy that event in.');
                    return;
                }

                window.showToast?.('success', data.message);
                window.location.href = data.event.url;
            } catch (e) {
                window.showToast?.('error', 'Could not copy that event in.');
            } finally {
                this.busy = null;
            }
        },
    };
}
</script>
@endpush
@endsection
