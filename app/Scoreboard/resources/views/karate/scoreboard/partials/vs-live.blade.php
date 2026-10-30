{{--
    The real VS screen, live, inside a console editor: an iframe of
    ScoreboardController::vsPreview (the event's own mat page, frozen on the
    introduction), driven over postMessage through partials/vs-preview-bridge.

    $frameId  the iframe's id     $w  its width (16:9)     $query  extra query (optional)
    Loaded on demand — VsLive(frame).load() — so a console does not open a VS
    screen for every editor it might never use. The host keeps what it sends:
    send() merges each corner and re-sends everything when the page (re)loads.
--}}
@php
  $vsLiveBase = $vsPreviewUrl ?? null;
  if ($vsLiveBase) {
      $vsLiveBase .= (str_contains($vsLiveBase, '?') ? '&' : '?').'mat='.rawurlencode((string) ($court ?? ''));
  }
@endphp
@once
<script>
  window.VsLive = window.VsLive || function (frame) {
    var ready = false, last = {}, base = frame ? frame.getAttribute('data-src') : null;
    function flush() {
      if (ready && frame.contentWindow) frame.contentWindow.postMessage(Object.assign({ type: 'vs-preview' }, last), window.location.origin);
    }
    window.addEventListener('message', function (e) {
      if (e.origin !== window.location.origin || !frame || e.source !== frame.contentWindow) return;
      if (e.data && e.data.type === 'vs-preview-ready') { ready = true; flush(); }
    });
    return {
      frame: frame,
      // extra: a query string to add (e.g. 'reg=12'); reloads only when it changes.
      // force: load again even when the address is the same (a fresh screen).
      load: function (extra, force) {
        if (!frame || !base) return;
        var src = base + (extra ? '&' + extra : '');
        if (force || frame.getAttribute('src') !== src) { ready = false; frame.setAttribute('src', src); }
      },
      send: function (msg) {
        Object.keys(msg).forEach(function (k) {
          last[k] = (k === 'aka' || k === 'ao') ? Object.assign({}, last[k], msg[k]) : msg[k];
        });
        flush();
      },
      reset: function () { last = {}; },
    };
  };
</script>
@endonce
@if ($vsLiveBase)
  <iframe id="{{ $frameId }}" data-src="{{ $vsLiveBase }}" title="VS"
          style="display:block;width:{{ $w }}px;height:{{ round($w * 9 / 16) }}px;border:1px solid var(--line-2,#2a2e40);border-radius:10px;background:#050507;"></iframe>
@endif
