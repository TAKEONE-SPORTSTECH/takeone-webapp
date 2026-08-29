{{--
    Watching a mat.

    Two ways in, tried in this order:

      WHEP    — WebRTC, sub-second. What somebody in the hall wants, because a
                score appearing on the board four seconds after the point was
                scored is worse than useless to a coach.
      LL-HLS  — the fallback, and the one that works from anywhere: it is plain
                HTTPS through the same proxy as the rest of the site, so it
                crosses networks that WebRTC cannot. Played by a SELF-HOSTED
                hls.js (Safari does HLS natively; nothing else does), because a
                hall's wifi is captive or filtered as often as not and a viewer
                must never depend on a CDN to watch a bout.

    When the broadcast is over and its recording has been transcoded, this same
    page plays the video instead. A viewer who arrives late does not hit a dead
    end — which is the whole reason the stream is recorded.
--}}
@extends('layouts.app')

@section('hide-navbar', true)
@section('title', $stream->label)

@section('content')
{{-- Self-hosted, not a CDN: see the note at the top of this file. --}}
<script src="{{ asset('vendor/hls/hls.min.js') }}"></script>

<div class="min-h-screen bg-background pb-16" x-data="liveWatch()">

    {{-- Header.

         A slim sticky bar rather than the house hero band, and deliberately: on
         every other page the subject is introduced and then read, but here the
         subject IS the picture directly underneath, and a tall gradient band
         above a 16:9 player pushes the fight itself off a phone screen. The bar
         earns its place by staying — it is what tells somebody scrolling the
         facts below that the mat is still live.

         Back is resolved server-side and may be ABSENT. This page is public: a
         spectator who followed a shared link has no event page to return to, and
         a Back button that lands on a login screen is worse than no Back button.
         They get the mark instead, which at least says whose product this is. --}}
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-border">
        <div class="flex items-center gap-2 px-3 h-14">
            @if ($backUrl)
                {{-- A chevron, not an arrow, and no word beside it: the title is
                     right next to it and says what this screen is, so a label on
                     the control would only repeat furniture. The accessible name
                     carries what the glyph cannot say out loud. --}}
                <a href="{{ $backUrl }}"
                   class="m-press flex-shrink-0 w-9 h-9 -ms-1 rounded-full grid place-items-center text-foreground hover:bg-muted transition-colors"
                   aria-label="{{ __('shared.back') }}">
                    <i class="bi bi-chevron-left text-xl rtl:rotate-180"></i>
                </a>
            @else
                <a href="{{ url('/') }}" class="m-press flex-shrink-0 -ms-1 h-9 px-1 grid place-items-center" aria-label="TAKEONE">
                    <img src="{{ asset('images/fullLogo.png') }}" alt="TAKEONE" class="h-6 w-auto" draggable="false">
                </a>
            @endif

            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-foreground truncate">{{ $stream->label }}</p>
                <p class="text-[11px] text-muted-foreground truncate">{{ $stream->event->title }}</p>
            </div>

            {{-- The state, and only the state. `live` and `ended` come from the
                 status poll, so this keeps up with the mat without a reload. --}}
            <span class="flex-shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold transition-colors"
                  :class="live ? 'bg-red-600 text-white' : 'bg-muted text-muted-foreground'">
                <span class="w-1.5 h-1.5" :class="live ? 'm-live-dot' : 'rounded-full bg-current'"></span>
                <span x-text="live ? '{{ __('shared.live') }}' : (ended ? '{{ __('shared.ended') }}' : '{{ __('shared.off_air') }}')"></span>
            </span>
        </div>
    </header>

    {{-- The picture. Black, edge to edge, 16:9 — a player, not a card. --}}
    <div class="relative bg-black aspect-video w-full">
        <video id="player" class="w-full h-full object-contain bg-black"
               playsinline controls autoplay muted></video>

        {{-- Nothing playing yet --}}
        <div x-show="!playing" class="absolute inset-0 grid place-items-center text-center px-6 pointer-events-none">
            <div>
                <i class="bi text-4xl text-white/40" :class="ended ? 'bi-camera-video-off' : 'bi-broadcast'"></i>
                <p class="text-white/80 text-sm font-semibold mt-3" x-text="statusLine"></p>
                <p class="text-white/50 text-xs mt-1" x-show="!ended">This page will start playing by itself.</p>
            </div>
        </div>

        {{-- Which path is carrying the picture. Worth showing: it explains why
             one viewer is a second behind another. --}}
        <div x-show="playing" x-cloak
             class="absolute bottom-2 left-2 px-2 py-1 rounded-lg bg-black/60 backdrop-blur text-[10px] font-bold text-white/80 uppercase tracking-wide">
            <span x-text="mode"></span>
        </div>
    </div>

    <div class="px-4 pt-4 space-y-4">

        {{-- Facts --}}
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-semibold">Watching</p>
                    <p class="text-lg font-black tabular-nums" x-text="viewers"></p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-semibold">Running</p>
                    <p class="text-lg font-black tabular-nums" x-text="clock"></p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-semibold">Mat</p>
                    <p class="text-lg font-black">{{ $stream->court ?: '—' }}</p>
                </div>
            </div>
        </div>

        @if ($canManage)
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-primary/10 text-primary grid place-items-center flex-shrink-0">
                    <i class="bi bi-phone"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-foreground">You run this event</p>
                    <p class="text-[11px] text-muted-foreground">Film it on this device, or show the code to whoever is holding the phone.</p>
                </div>

                {{-- The QR is how the camera is HANDED OVER. A volunteer at the
                     mat should not have to be sent a link or type one — the
                     organiser shows them a screen and they scan it, exactly the
                     way a hall screen is paired. --}}
                <x-qr-code :url="route('live.broadcast', $stream)"
                           :title="$stream->label"
                           caption="Scan with the phone that will film this mat"
                           label="Camera QR"
                           icon="bi-qr-code"
                           :size="320"
                           :icon-only="true"
                           button-class="flex-shrink-0 w-10 h-10 rounded-xl border border-gray-200 text-foreground grid place-items-center" />

                <a href="{{ route('live.broadcast', $stream) }}"
                   class="flex-shrink-0 px-3 py-2 rounded-xl bg-primary text-white text-xs font-bold">Broadcast</a>
            </div>
        @endif

        {{-- The recording, once there is one --}}
        <div x-show="ended" x-cloak class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                      :class="replay ? 'bg-green-100 text-green-700' : 'bg-muted text-muted-foreground'">
                    <i class="bi" :class="replay ? 'bi-play-circle-fill' : 'bi-hourglass-split'"></i>
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-foreground" x-text="replay ? 'Recording ready' : 'Recording being prepared'"></p>
                    <p class="text-[11px] text-muted-foreground"
                       x-text="replay ? 'Kept with the bout, on this platform.' : 'The broadcast was recorded — it appears here once it has been processed.'"></p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

{{-- Inline, not @push: the mobile shell re-runs inline scripts on every AJAX
     navigation, and a pushed script would not be re-registered after a swap. --}}
<script>
function liveWatch() {
    return {
        live: @json($stream->isLive()),
        ended: @json($stream->status === \App\Models\LiveStream::STATUS_ENDED),
        viewers: @json($stream->current_viewers),
        seconds: @json($stream->duration_seconds ?? 0),
        playing: false,
        mode: '',
        replay: @json($replayUrl),
        pc: null,
        hls: null,
        poll: null,
        tick: null,

        get statusLine() {
            if (this.ended) return 'This broadcast has ended.';
            return this.live ? 'Connecting…' : 'This mat is not broadcasting right now.';
        },

        get clock() {
            var s = Math.max(0, this.seconds | 0);
            return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
        },

        init() {
            // A finished broadcast whose recording is ready is just a video.
            if (this.ended && this.replay) { this.playRecording(); }
            else if (this.live) { this.connect(); }

            this.poll = setInterval(() => this.refresh(), 5000);
            this.tick = setInterval(() => { if (this.live) this.seconds++; }, 1000);

            // The mobile shell swaps content without a page load, so listeners
            // and peer connections have to be torn down explicitly.
            window.addEventListener('pagehide', () => this.teardown(), { once: true });
        },

        teardown() {
            if (this.poll) clearInterval(this.poll);
            if (this.tick) clearInterval(this.tick);
            if (this.pc) { try { this.pc.close(); } catch (e) {} this.pc = null; }
            if (this.hls) { try { this.hls.destroy(); } catch (e) {} this.hls = null; }
        },

        async refresh() {
            try {
                const res = await fetch(@json(route('live.status', $stream)), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store',
                });
                if (!res.ok) return;
                const d = await res.json();

                this.viewers = d.viewers;
                if (typeof d.duration === 'number') this.seconds = d.duration;

                const wasLive = this.live;
                this.live = d.live;
                this.ended = d.status === 'ended';

                // Went live while somebody was sitting on the page.
                if (!wasLive && this.live && !this.playing) this.connect();

                // Ended, and the recording has finished processing — reload so
                // the page comes back as the video rather than a dead player.
                if (this.ended && d.recording && d.recording.status === 'ready' && !this.replay) {
                    window.location.reload();
                }
            } catch (e) { /* offline; the next poll will do */ }
        },

        /* ── WHEP first ───────────────────────────────────────────────────
           WebRTC playback: one POST with an offer, one answer back. Falls
           through to LL-HLS on any failure at all, because a viewer does not
           care why — they care that it plays. */
        async connect() {
            if (!this.live) return;

            try {
                const pc = new RTCPeerConnection({ iceServers: @json($iceServers) });
                this.pc = pc;

                pc.addTransceiver('video', { direction: 'recvonly' });
                pc.addTransceiver('audio', { direction: 'recvonly' });

                pc.ontrack = (e) => {
                    const el = document.getElementById('player');
                    el.srcObject = e.streams[0];
                    el.play().catch(() => {});
                    this.playing = true;
                    this.mode = 'live · webrtc';
                };

                pc.onconnectionstatechange = () => {
                    if (['failed', 'closed'].includes(pc.connectionState) && !this.ended) this.fallback();
                };

                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);

                const res = await fetch(@json($whepUrl), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/sdp' },
                    body: pc.localDescription.sdp,
                });

                if (!res.ok) throw new Error('whep ' + res.status);

                await pc.setRemoteDescription({ type: 'answer', sdp: await res.text() });

                // If WebRTC has not produced a picture in a few seconds it is not
                // going to — usually no UDP path out of the viewer's network.
                setTimeout(() => { if (!this.playing) this.fallback(); }, 6000);
            } catch (e) {
                this.fallback();
            }
        },

        /* ── LL-HLS ───────────────────────────────────────────────────────
           Safari plays HLS natively; every other browser needs hls.js, which is
           served from this application rather than a CDN.

           Only attempted when a stream actually EXISTS. Pointing a <video> at a
           playlist that is not there produces the browser's own "no video with
           supported format" error, which tells the viewer nothing true — the mat
           simply is not broadcasting, and that is what it should say. */
        fallback() {
            if (this.pc) { try { this.pc.close(); } catch (e) {} this.pc = null; }

            if (!this.live) { this.playing = false; this.mode = ''; return; }

            const el = document.getElementById('player');
            const url = @json($hlsUrl);

            el.srcObject = null;

            // Native HLS (Safari, iOS).
            if (el.canPlayType('application/vnd.apple.mpegurl')) {
                el.src = url;
                el.play().then(() => { this.playing = true; this.mode = 'live · hls'; }).catch(() => {});
                return;
            }

            if (typeof Hls === 'undefined' || !Hls.isSupported()) {
                this.playing = false;
                this.mode = '';
                return;
            }

            if (this.hls) { try { this.hls.destroy(); } catch (e) {} }

            // lowLatencyMode: this is LL-HLS with 200ms parts, and without it
            // hls.js buffers whole segments and gives away the low latency the
            // media server went to the trouble of producing.
            this.hls = new Hls({ lowLatencyMode: true, backBufferLength: 30 });
            this.hls.loadSource(url);
            this.hls.attachMedia(el);

            this.hls.on(Hls.Events.MANIFEST_PARSED, () => {
                el.play().then(() => { this.playing = true; this.mode = 'live · hls'; }).catch(() => {});
            });

            this.hls.on(Hls.Events.ERROR, (_e, data) => {
                // A fatal network error usually means the publisher has gone.
                // Stop rather than retrying into a hole; the poll will notice.
                if (data && data.fatal) {
                    try { this.hls.destroy(); } catch (e) {}
                    this.hls = null;
                    this.playing = false;
                    this.mode = '';
                }
            });
        },

        playRecording() {
            const el = document.getElementById('player');
            el.srcObject = null;
            el.src = this.replay;
            el.muted = false;
            this.mode = 'recording';
            this.playing = true;
        },
    };
}
</script>
