{{--
    The scoring console's half of the mat's live link — JavaScript only, included
    INSIDE the console's own script scope so it can reach STATE, QUEUE, paint()
    and paintQueue(). The socket itself is the shared screen-link partial,
    included after the script with `pinned: 'console'`.

    Two things arrive (see ScreenChannel::notifyCourt):
      {state, queue} — the console re-read its own address because the running
                       order or the queue moved. The Bouts list repaints at once
                       if it is open, so a reorder lands while the official is
                       looking at it.
      a bare state   — the other console at this mat did something.

    A state older than the one already drawn is ignored: a command's own
    response and its echo can arrive in either order, and the console must
    never step backwards.
--}}
  window.CourtBoard = {
    pinned: 'console',
    stale: function () {},
    update: function (p) {
      if (!p || typeof p !== 'object') return;
      var next = ('mode' in p) ? p : (p.state || null);
      if (Array.isArray(p.queue)) {
        QUEUE = p.queue;
        if (!el('bouts').hidden) paintQueue();
      }
      if (next && !(next.at && STATE.at && next.at < STATE.at)) {
        STATE = next; received = performance.now();
        paint();
      }
    }
  };
