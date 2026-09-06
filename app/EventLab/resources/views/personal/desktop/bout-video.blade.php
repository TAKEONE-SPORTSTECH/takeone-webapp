<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $bout['a']['name'] }} vs {{ $bout['b']['name'] }} | {{ $e['title'] }}</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css">
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#0a0a0a; --card:#111111; --card-2:#161616; --line:#1f1f1f; --line-2:#262626;
    --ink:#ededed; --ink-2:#a3a3a3; --muted:#757575;
    --red:#e61e1e; --red-soft:#ff6b6b; --blue:#2563eb; --blue-soft:#6ea8ff;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--ink);font-family:'Archivo',system-ui,sans-serif}
  a{color:var(--ink);text-decoration:none} a:hover{color:#ff4d4d}
  ::selection{background:rgba(230,30,30,.35)}
  *{scrollbar-width:thin;scrollbar-color:#2e2e2e transparent}
  ::-webkit-scrollbar{width:6px;height:6px}
  ::-webkit-scrollbar-track{background:transparent}
  ::-webkit-scrollbar-thumb{background:#2e2e2e;border-radius:999px}
  ::-webkit-scrollbar-thumb:hover{background:var(--red)}

  /* Edge to edge, like every other desktop page.
     This screen kept a 1360px centred cap after that was removed from the rest
     of the platform in July 2026, so on any wide monitor it sat in the middle
     with gutters either side while the pages around it filled the glass. */
  .wrap{margin:0;padding:20px 32px 48px}
  .mono{font-family:'JetBrains Mono',ui-monospace,monospace}

  /* ── Event strip ── */
  .event-strip{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:0 2px 16px}
  .event-id{display:flex;align-items:center;gap:12px;min-width:0}
  .event-logo{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--red),#7f1212);display:grid;place-items:center;flex-shrink:0;font-weight:900;font-size:15px;letter-spacing:.5px}
  .event-name{font-size:14px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .event-meta{font-size:11.5px;color:var(--muted);font-weight:500;letter-spacing:.3px}
  .event-stats{display:flex;align-items:center;gap:10px;font-size:12px;color:var(--muted);font-weight:500}
  .dot-sep{width:3px;height:3px;border-radius:50%;background:#404040}

  /* ── Player row ── */
  .player-row{display:flex;gap:20px;flex-wrap:wrap;align-items:stretch}
  .player-col{flex:1 1 0;min-width:340px;display:flex;flex-direction:column;gap:16px}
  /* Full width is not "as large as the monitor allows": 16:9 across an
     ultrawide is taller than the viewport, so the controls fall off the bottom
     and every bout starts with a scroll. Capped to what fits; the video is
     object-fit:contain, so a container off the ratio letterboxes rather than
     crops anybody out of frame. */
  .player{aspect-ratio:16/9;max-height:calc(100vh - 240px);background:#000;border-radius:14px;overflow:hidden;border:1px solid var(--line);display:flex;align-items:stretch}
  .player.fs{position:fixed;inset:0;z-index:9999;border-radius:0;aspect-ratio:auto}
  .video-area{position:relative;flex:1;min-width:0}
  .video-el{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;background:transparent;z-index:1}
  .f-photo img{width:100%;height:100%;object-fit:cover;display:block}
  .video-bg{position:absolute;inset:0;background:radial-gradient(ellipse at 50% 42%,#242424 0%,#050505 75%)}
  /* Real integration: put your <video> here, object-fit: contain */


  .play-btn{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:76px;height:76px;border-radius:50%;border:none;cursor:pointer;background:rgba(230,30,30,.92);color:#fff;font-size:26px;display:grid;place-items:center;box-shadow:0 8px 32px rgba(230,30,30,.4);transition:opacity .18s ease,transform .18s ease}
  /* Out of the way while it is playing. A red disc parked over the middle of a
     bout hides the one thing the page exists to show; it comes back the moment
     the video pauses, which is when somebody actually wants it. */
  .player.playing .play-btn{opacity:0;transform:translate(-50%,-50%) scale(.85);pointer-events:none}

  /* Deleting the footage. Platform staff only — the button is not rendered for
   anyone else, and the endpoint refuses them regardless of what is rendered.

   This screen is one of the standalone review designs: it has its own shell and
   does NOT load the app's Alpine, its toast container or the shared confirm
   dialog component. So
   the confirmation is built here, in this page's own language — what it must not be is
   a native confirm(), which the project bans outright and which would look like
   somebody else's browser sitting on top of the bout.

   Two steps on purpose: this is the one control on the page with nothing behind
   it. A bout is filmed once. */
(function(){
  const btn = document.getElementById('delVideo');
  if (!btn) return;

  function ask(){
    return new Promise(resolve => {
      const wrap = document.createElement('div');
      wrap.className = 'confirm-wrap';
      wrap.innerHTML =
        '<div class="confirm-box" role="dialog" aria-modal="true">' +
          '<div class="confirm-title">' + @js(__('events.bout_video_delete_title')) + '</div>' +
          '<div class="confirm-msg">' + @js(__('events.bout_video_delete_warning')) + '</div>' +
          '<div class="confirm-row">' +
            '<button class="confirm-no">' + @js(__('shared.cancel')) + '</button>' +
            '<button class="confirm-yes">' + @js(__('events.bout_video_delete_confirm')) + '</button>' +
          '</div>' +
        '</div>';

      function close(answer){
        document.removeEventListener('keydown', onKey);
        wrap.remove();
        resolve(answer);
      }
      function onKey(e){ if (e.key === 'Escape') close(false); }

      wrap.querySelector('.confirm-no').onclick = () => close(false);
      wrap.querySelector('.confirm-yes').onclick = () => close(true);
      wrap.addEventListener('click', e => { if (e.target === wrap) close(false); });
      document.addEventListener('keydown', onKey);

      document.body.appendChild(wrap);
      wrap.querySelector('.confirm-yes').focus();
    });
  }

  function say(msg){
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(t._h);
    t._h = setTimeout(() => t.classList.remove('show'), 3000);
  }

  btn.addEventListener('click', async function(){
    if (!await ask()) return;

    btn.disabled = true;

    try {
      const res = await fetch(btn.dataset.url, {
        method: 'DELETE',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      const data = await res.json().catch(() => ({}));

      if (!res.ok || !data.success) {
        say(data.message || @js(__('events.bout_video_delete_failed')));
        btn.disabled = false;
        return;
      }

      say(data.message);

      // The thing this page exists to show is gone, so staying on it would be a
      // page about nothing. Back to the gallery it came from.
      setTimeout(() => { window.location.href = data.redirect; }, 800);
    } catch (e) {
      say(@js(__('events.bout_video_delete_failed')));
      btn.disabled = false;
    }
  });
})();

/* ── The walk-on ─────────────────────────────────────────────────────────
     Five seconds of who is fighting, before the footage. Both corners come in
     from their own side, the VS lands between them, and it gets out of the way
     on its own — or sooner, from the skip. */
  .vs{position:absolute;inset:0;z-index:30;display:flex;align-items:center;justify-content:center;gap:34px;padding:0 40px;background:radial-gradient(ellipse at 50% 45%,#1c1c1c 0%,#050505 78%);opacity:1;transition:opacity .45s ease}
  .vs.gone{opacity:0;pointer-events:none}
  .vs-side{flex:1 1 0;min-width:0;display:flex;flex-direction:column;align-items:center;gap:12px;text-align:center}
  .vs-side.a{animation:vsInA .62s cubic-bezier(.16,.84,.44,1) both}
  .vs-side.b{animation:vsInB .62s cubic-bezier(.16,.84,.44,1) both}
  .vs-photo{width:148px;height:148px;border-radius:50%;overflow:hidden;background:#141414;display:grid;place-items:center;font-family:'Archivo',sans-serif;font-weight:900;font-size:26px;color:#666;flex:0 0 auto}
  .vs-photo img{width:100%;height:100%;object-fit:cover;display:block}
  .vs-photo.a{border:3px solid var(--red);box-shadow:0 0 48px rgba(230,30,30,.42)}
  .vs-photo.b{border:3px solid var(--blue);box-shadow:0 0 48px rgba(40,110,240,.42)}
  .vs-name{font-family:'Archivo',sans-serif;font-weight:800;font-size:23px;letter-spacing:.4px;color:var(--ink);line-height:1.15;text-transform:uppercase;max-width:100%}
  .vs-team{font-size:13px;color:var(--ink-2);display:flex;align-items:center;justify-content:center;gap:7px;max-width:100%;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
  .vs-mid{flex:0 0 auto;display:flex;flex-direction:column;align-items:center;gap:9px;animation:vsPop .5s .26s cubic-bezier(.2,1.5,.4,1) both}
  .vs-word{font-family:'Archivo',sans-serif;font-weight:900;font-size:64px;line-height:1;color:#e9c46a;text-shadow:0 0 46px rgba(233,196,106,.45)}
  .vs-meta{font-family:'Archivo',sans-serif;font-size:11px;font-weight:800;letter-spacing:2px;color:var(--ink-2);text-transform:uppercase;text-align:center;line-height:1.6}
  .vs-skip{position:absolute;top:14px;right:14px;z-index:32;display:inline-flex;align-items:center;gap:7px;padding:10px 16px;border-radius:9px;border:1px solid rgba(255,255,255,.2);background:rgba(0,0,0,.55);color:var(--ink);font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;backdrop-filter:blur(6px);letter-spacing:.3px}
  .del-btn{position:absolute;top:14px;right:14px;z-index:21;display:inline-flex;align-items:center;gap:7px;padding:10px 15px;border-radius:9px;border:1px solid rgba(230,30,30,.45);background:rgba(30,0,0,.6);color:#ff8080;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;backdrop-filter:blur(6px);letter-spacing:.3px}
  /* Shares the corner with the back button, so it steps aside for the
     intro and for fullscreen exactly as that one does. */
  .player.intro .del-btn{opacity:0;pointer-events:none}
  .player.fs .del-btn,.player.fs-rot .del-btn{display:none}
  /* The confirmation this page builds for itself — it has no app shell to
     borrow one from, and a native confirm() is banned and would look like
     somebody else's browser sitting on the bout. */
  .confirm-wrap{position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.72);backdrop-filter:blur(3px);display:grid;place-items:center;padding:24px}
  .confirm-box{width:100%;max-width:360px;background:var(--card);border:1px solid var(--line-2);border-radius:14px;padding:20px;box-shadow:0 24px 60px rgba(0,0,0,.6)}
  .confirm-title{font-family:'Archivo',sans-serif;font-weight:900;font-size:15px;color:var(--ink);margin-bottom:8px}
  .confirm-msg{font-size:12.5px;line-height:1.55;color:var(--ink-2);margin-bottom:18px}
  .confirm-row{display:flex;gap:9px;justify-content:flex-end}
  .confirm-row button{font-family:inherit;font-size:12.5px;font-weight:700;padding:9px 15px;border-radius:9px;cursor:pointer;border:1px solid var(--line-2);background:var(--card-2);color:var(--ink)}
  .confirm-row .confirm-yes{background:var(--red);border-color:var(--red);color:#fff}
  /* The intro owns the top-right corner while it is up, so the two do not stack. */
  .player.intro .back-btn{opacity:0;pointer-events:none}
  @keyframes vsInA{from{opacity:0;transform:translateX(-48px)}to{opacity:1;transform:none}}
  @keyframes vsInB{from{opacity:0;transform:translateX(48px)}to{opacity:1;transform:none}}
  @keyframes vsPop{from{opacity:0;transform:scale(.72)}to{opacity:1;transform:scale(1)}}
  @media (prefers-reduced-motion:reduce){
    .vs-side.a,.vs-side.b,.vs-mid{animation:none}
    .vs,.play-btn{transition:none}
  }
  .play-btn:hover{background:#ff2e2e;transform:translate(-50%,-50%) scale(1.06)}

  .toast{position:absolute;left:50%;bottom:56px;transform:translateX(-50%);background:rgba(0,0,0,.72);border:1px solid rgba(255,255,255,.14);border-radius:8px;padding:7px 14px;font-size:13px;font-weight:600;backdrop-filter:blur(4px);white-space:nowrap;display:none}
  .toast.show{display:block}

  .ctrl{position:absolute;left:0;right:0;bottom:0;padding:10px 16px 12px;background:linear-gradient(to top,rgba(0,0,0,.8),transparent);display:flex;flex-direction:column;gap:8px}
  .ctrl-track{height:4px;border-radius:2px;background:rgba(255,255,255,.18);position:relative}
  .ctrl-fill{position:absolute;left:0;top:0;bottom:0;background:var(--red);border-radius:2px}
  .ctrl-knob{position:absolute;top:50%;transform:translate(-50%,-50%);width:12px;height:12px;border-radius:50%;background:var(--red)}
  .ctrl-row{display:flex;align-items:center;justify-content:space-between;font-family:'JetBrains Mono',monospace;font-size:11.5px;color:#ccc}
  .ctrl-right{display:inline-flex;align-items:center;gap:14px}
  .ctrl-quality{font-family:'Archivo',sans-serif;font-weight:600;letter-spacing:.5px;color:var(--ink-2)}
  .fs-btn{background:none;border:none;color:#ccc;font-size:17px;line-height:1;cursor:pointer;padding:2px 4px;transition:.12s}
  .fs-btn:hover{color:#fff;transform:scale(1.15)}

  /* Fullscreen highlights */
  .fs-hl-btn{position:absolute;top:14px;right:14px;z-index:20;display:none;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;border:1px solid rgba(255,255,255,.2);background:rgba(0,0,0,.55);color:var(--ink);font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;backdrop-filter:blur(6px);letter-spacing:.3px}
  .fs-hl-btn:hover{filter:brightness(1.25)}
  .player.fs .fs-hl-btn{display:inline-flex}
  .fs-hl-btn.on{border-color:rgba(230,30,30,.5);background:rgba(230,30,30,.25);color:var(--red-soft)}
  .fs-panel{width:min(380px,40%);flex-shrink:0;display:none;flex-direction:column;background:#0d0d0d;border-left:1px solid var(--line-2);overflow:hidden}
  .player.fs .fs-panel.open{display:flex}

  /* ── Rail (side panel) ── */
  .rail-slot{flex:0 1 340px;min-width:280px;max-width:480px;position:relative;min-height:420px}
  .rail-slot.hidden{display:none}
  .rail{position:absolute;inset:0;display:flex;flex-direction:column;border:1px solid var(--line);background:var(--card);border-radius:14px;overflow:hidden}
  .tabs{display:flex;border-bottom:1px solid var(--line);flex-shrink:0}
  .tab{flex:1;padding:14px 8px;background:none;border:none;border-bottom:2px solid transparent;color:var(--muted);font-family:inherit;font-size:13.5px;font-weight:700;cursor:pointer;letter-spacing:.3px}
  .tab.on{color:var(--ink);border-bottom-color:var(--red)}
  .fs-panel .tab{padding:12px 8px;font-size:12.5px}
  .pane{flex:1;min-height:0;overflow-y:auto;padding:4px 16px 16px;display:none}
  .pane.on{display:block}

  .rhead{position:sticky;top:0;z-index:2;background:var(--card);display:flex;align-items:center;justify-content:space-between;padding:12px 0 8px;border-bottom:1px solid var(--line)}
  .fs-panel .rhead{background:#0d0d0d}
  .rname{font-size:11px;font-weight:800;letter-spacing:1.8px}
  .rcount{font-size:11px;color:var(--muted);font-weight:500}

  .pt-row{display:grid;grid-template-columns:54px 14px 1fr auto;align-items:center;gap:10px;padding:12px 2px;border-bottom:1px solid var(--card-2);cursor:pointer}
  .pt-row:hover{background:var(--card-2)}
  .pt-time{font-family:'JetBrains Mono',monospace;font-size:11.5px;font-weight:600;color:var(--ink-2);background:#181818;border:1px solid var(--line-2);border-radius:6px;padding:5px 0;text-align:center}
  .pt-dot{width:8px;height:8px;border-radius:50%;justify-self:center}
  .pt-dot.aka{background:var(--red)} .pt-dot.ao{background:var(--blue)}
  /* Both fighters scored in the same instant — one moment, two colours. */
  .pt-dot.both{background:linear-gradient(180deg,var(--red) 0 50%,var(--blue) 50% 100%)}
  .pt-action{font-size:13px;font-weight:600;line-height:1.3}
  .pt-pts{font-size:10.5px;font-weight:800;color:var(--muted);background:rgba(255,255,255,.06);border-radius:4px;padding:1px 6px;margin-left:4px}
  .pt-who{font-size:11px;color:var(--muted);font-weight:500;margin-top:2px}
  .pt-score{font-family:'JetBrains Mono',monospace;font-size:12.5px;font-weight:600;color:var(--ink-2)}

  .rv-wrap{position:relative;padding-left:26px;margin-top:14px}
  .rv-spine{position:absolute;left:8px;top:6px;bottom:6px;width:2px;background:rgba(255,255,255,.07)}
  .rv-item{position:relative;margin-bottom:20px}
  .rv-item::before{content:'';position:absolute;left:-23px;top:3px;width:12px;height:12px;border-radius:50%;background:var(--red);box-shadow:0 0 0 3px rgba(230,30,30,.2)}
  .rv-range{font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:600;letter-spacing:.5px;color:var(--red-soft);margin-bottom:6px}
  .rv-card{display:flex;gap:10px;align-items:flex-start;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:10px;padding:11px 13px;cursor:pointer}
  .rv-card:hover{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.14)}
  .rv-emoji{font-size:18px;line-height:1.3;flex-shrink:0}
  .rv-note{font-size:13.5px;font-weight:500;line-height:1.45}
  .rv-foot{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:7px}
  .rv-author{font-size:11px;color:var(--muted);font-weight:600}
  .rv-slowmo{display:inline-flex;align-items:center;gap:5px;font-size:10.5px;font-weight:800;letter-spacing:.5px;color:var(--red-soft);background:rgba(230,30,30,.12);border-radius:6px;padding:4px 9px}

  /* ── Below the player ── */
  .below{display:flex;flex-direction:column;gap:16px;margin-top:16px}
  .title-row{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap}
  .v-title{margin:0;font-size:24px;font-weight:800;letter-spacing:.3px}
  .v-sub{margin-top:4px;font-size:13px;color:var(--muted);font-weight:500}
  .actions{display:flex;gap:8px;flex-shrink:0}
  .btn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;border:1px solid var(--line-2);background:var(--card-2);color:var(--ink);font-family:inherit;font-size:13px;font-weight:600;cursor:pointer}
  .btn:hover{background:#1a1a1a;border-color:#404040}
  .btn.accent{border-color:rgba(230,30,30,.4);background:rgba(230,30,30,.12);color:var(--red-soft)}
  .btn.accent:hover{background:rgba(230,30,30,.22)}
  .btn.toggled{border-color:rgba(230,30,30,.4);background:rgba(230,30,30,.12);color:var(--red-soft)}

  .card{border:1px solid var(--line);background:var(--card);border-radius:14px;padding:20px}
  .match-grid{display:grid;grid-template-columns:1fr auto 1fr;gap:12px;align-items:center}
  .fighter{display:flex;align-items:center;gap:14px;min-width:0}
  .fighter.rev{flex-direction:row-reverse;text-align:right}
  .f-photo{width:84px;height:112px;flex-shrink:0;border-radius:10px;background:#1a1a1a;border:1px dashed #333;display:grid;place-items:center;font-size:10px;color:var(--muted);overflow:hidden}
  .f-photo img{width:100%;height:100%;object-fit:cover}
  .corner-tag{display:inline-flex;align-items:center;gap:6px;font-size:10px;font-weight:800;letter-spacing:1.5px;margin-bottom:5px}
  .corner-sq{width:8px;height:8px;border-radius:2px}
  .f-name{font-size:16px;font-weight:700;line-height:1.25}
  .f-team{font-size:12px;color:var(--muted);font-weight:500;margin-top:3px}
  .score-c{text-align:center;padding:0 18px}
  .score-lbl{font-size:10px;font-weight:800;letter-spacing:2px;color:var(--muted);margin-bottom:6px}
  .score-nums{display:flex;align-items:baseline;gap:10px;justify-content:center;font-family:'JetBrains Mono',monospace}
  .score-nums .aka{font-size:40px;font-weight:600;color:var(--red-soft)}
  .score-nums .ao{font-size:40px;font-weight:600;color:var(--blue-soft)}
  .score-nums .dash{font-size:20px;color:#404040}
  .winner{margin-top:6px;display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:700;color:var(--blue-soft);background:rgba(37,99,235,.12);border:1px solid rgba(37,99,235,.3);border-radius:999px;padding:4px 12px}

  .officials{margin-top:18px;padding-top:16px;border-top:1px solid var(--line)}
  .off-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:14px}
  .sec-lbl{font-size:10px;font-weight:800;letter-spacing:2px;color:var(--muted)}
  .off-meta{display:flex;align-items:center;gap:22px;font-size:12.5px;color:var(--ink-2);font-weight:500}
  .off-meta b{color:var(--muted);font-weight:500}
  .off-row{display:flex;gap:28px;flex-wrap:wrap;justify-content:center}
  .official{display:flex;flex-direction:column;align-items:center;gap:8px;text-align:center}
  .o-photo{width:60px;height:80px;border-radius:8px;background:#1a1a1a;border:1px dashed #333;overflow:hidden}
  .o-photo img{width:100%;height:100%;object-fit:cover}
  .o-name{font-size:13px;font-weight:700;white-space:nowrap}
  .o-role{font-size:11px;color:var(--muted);font-weight:500;margin-top:2px}

  /* ── Comments ── */
  .cm-head{display:flex;align-items:center;gap:10px;margin-bottom:16px}
  .cm-title{font-size:15px;font-weight:800;letter-spacing:.3px}
  .cm-count{font-size:12px;color:var(--muted);font-weight:600}
  .cm-compose{display:flex;gap:12px;margin-bottom:20px}
  .cm-avatar{width:36px;height:48px;flex-shrink:0;border-radius:8px;display:grid;place-items:center;font-size:13px;font-weight:800;color:var(--ink)}
  .cm-input{width:100%;background:var(--card-2);border:1px solid var(--line-2);border-radius:9px;padding:11px 14px;color:var(--ink);font-family:inherit;font-size:13.5px;outline:none}
  .cm-input:focus{border-color:var(--red)}
  .cm-post{padding:8px 16px;border-radius:8px;border:none;background:var(--red);color:#fff;font-family:inherit;font-size:12.5px;font-weight:700;cursor:pointer}
  .cm-post:hover{background:#ff2e2e}
  .cm-list{display:flex;flex-direction:column;gap:18px}
  .cm-item{display:flex;gap:12px}
  .cm-name{font-size:13px;font-weight:700}
  .cm-when{font-size:11px;color:var(--muted);font-weight:500;margin-left:8px}
  .cm-text{font-size:13.5px;line-height:1.5;margin-top:4px;color:#ccc}
  .cm-stamp{display:inline-flex;align-items:center;border:1px solid rgba(62,166,255,.4);background:rgba(62,166,255,.12);color:#7dd3fc;border-radius:999px;padding:1px 9px;font-family:'JetBrains Mono',monospace;font-size:11.5px;font-weight:600;cursor:pointer;margin-right:6px;vertical-align:1px}
  .cm-stamp:hover{background:rgba(62,166,255,.25)}
  .cm-tools{display:flex;align-items:center;gap:14px;margin-top:7px;font-size:11.5px;font-weight:600;color:var(--muted)}
  .cm-tools span{cursor:pointer}
</style>
</head>
<body>
<div class="wrap">

  <!-- Event strip -->
  <div class="event-strip">
    <div class="event-id">
      <div class="event-logo">T1</div>
      <div style="min-width:0">
        <div class="event-name">{{ $e['title'] }}</div>
        <div class="event-meta">{{ collect([$e['sport_label'] ?? null, $bout['division'], __('events.bout_gallery_bout', ['n' => $bout['match_no']]), $bout['court'] ? __('events.bout_card_court').' '.$bout['court'] : null])->filter()->unique()->implode(' · ') }}</div>
      </div>
    </div>
    <div class="event-stats"><a href="{{ $bout['gallery_url'] }}" style="color:inherit;text-decoration:none">&#8249; {{ __('events.bout_gallery_title') }}</a><span class="dot-sep"></span><span>{{ $e['date'] }}</span></div>
  </div>

  <!-- Player + highlights rail -->
  <div class="player-row">
    <div class="player-col">
      <div class="player" id="player">
        <div class="video-area">
          <div class="video-bg"></div>
@if ($angles)
          {{-- The real picture; .video-bg stays as the ground behind it. --}}
          <video id="vid" class="video-el" playsinline preload="metadata"
                 @if ($angles[0]['poster']) poster="{{ $angles[0]['poster'] }}" @endif></video>
@endif
          <button class="play-btn" id="playBtn">&#9654;</button>
@if ($angles)
          {{-- Who is fighting, before the fight. Taken out of the DOM once it
               has played, so it can never intercept a click later. --}}
          <div class="vs" id="vsIntro">
            <div class="vs-side a">
              <div class="vs-photo a">@if ($bout['a']['photo'])<img src="{{ $bout['a']['photo'] }}" alt="">@else<span>{{ $bout['a']['corner_label'] }}</span>@endif</div>
              <div class="vs-name">{{ $bout['a']['name'] ?: '—' }}</div>
              <div class="vs-team">@if ($bout['a']['country'])<span class="fi fi-{{ strtolower($bout['a']['country']) }}"></span>@endif<span>{{ $bout['a']['club'] }}</span></div>
            </div>
            <div class="vs-mid">
              <div class="vs-word">VS</div>
              <div class="vs-meta">{{ $bout['division'] }}@if ($bout['round'])<br>{{ $bout['round'] }}@endif</div>
            </div>
            <div class="vs-side b">
              <div class="vs-photo b">@if ($bout['b']['photo'])<img src="{{ $bout['b']['photo'] }}" alt="">@else<span>{{ $bout['b']['corner_label'] }}</span>@endif</div>
              <div class="vs-name">{{ $bout['b']['name'] ?: '—' }}</div>
              <div class="vs-team">@if ($bout['b']['country'])<span class="fi fi-{{ strtolower($bout['b']['country']) }}"></span>@endif<span>{{ $bout['b']['club'] }}</span></div>
            </div>
          </div>
          <button class="vs-skip" id="vsSkip">{{ __('events.bout_video_skip') }} &#8250;</button>
@endif
@if ($may_delete_video)
        {{-- Platform staff only. The controller re-checks; this just decides
             whether anyone is shown a button that destroys footage. --}}
        <button class="del-btn" id="delVideo" data-url="{{ $delete_video_url }}">&#128465; {{ __('events.bout_video_delete') }}</button>
@endif
          <button class="fs-hl-btn" id="fsHlBtn">&#9776; Highlights</button>
          <div class="toast" id="toast"></div>
          <div class="ctrl">
            <div class="ctrl-track"><div class="ctrl-fill" id="fill" style="width:0%"></div><div class="ctrl-knob" id="knob" style="left:0%"></div></div>
            <div class="ctrl-row">
              <span id="clock">00:00 / 00:00</span>
              <span class="ctrl-right">
                <span class="ctrl-quality">1080p &middot; 1&times;</span>
                <button class="fs-btn" id="fsBtn" title="Fullscreen">&#x26F6;</button>
              </span>
            </div>
          </div>
        </div>
        <!-- Fullscreen side panel (filled by JS with the same tabs) -->
        <div class="fs-panel" id="fsPanel"></div>
      </div>
    </div>

    <div class="rail-slot" id="railSlot">
      <div class="rail" id="railMount"></div>
    </div>
  </div>

  <!-- Full-width details -->
  <div class="below">

    <div class="title-row">
      <div style="min-width:0">
        <h1 class="v-title">{{ $bout['a']['name'] ?: '—' }} vs {{ $bout['b']['name'] ?: '—' }}</h1>
        <div class="v-sub">{{ collect([$bout['round'], $bout['court'] ? __('events.bout_card_court').' '.$bout['court'] : null])->filter()->implode(' · ') }}</div>
      </div>
      <div class="actions">
        <button class="btn toggled" id="railBtn">&#9776; Hide Highlights</button>
        <button class="btn">&#9825; Like</button>
        <button class="btn">&#8599; Share</button>
        <button class="btn accent">&#8681; Download</button>
      </div>
    </div>

    <!-- Match result + officials -->
    <div class="card">
      <div class="match-grid">
        <div class="fighter">
          <div class="f-photo">@if ($bout['a']['photo'])<img src="{{ $bout['a']['photo'] }}" alt="">@else<span>{{ $bout['a']['corner_label'] }}</span>@endif</div>
          <div style="min-width:0">
            <div class="corner-tag" style="color:var(--{{ $bout['a']['colour'] === 'red' ? 'red' : 'blue' }}-soft)"><span class="corner-sq" style="background:var(--{{ $bout['a']['colour'] === 'red' ? 'red' : 'blue' }})"></span>{{ $bout['a']['corner_label'] }}</div>
            <div class="f-name">{{ $bout['a']['name'] ?: '—' }}</div>
            <div class="f-team">@if ($bout['a']['country'])<span class="fi fi-{{ strtolower($bout['a']['country']) }}"></span> @endif{{ $bout['a']['club'] }}</div>
          </div>
        </div>
        <div class="score-c">
          <div class="score-lbl">{{ $bout['winner'] ? __('events.bout_video_final') : __('events.bout_video_score') }}</div>
          <div class="score-nums"><span class="aka">{{ $bout['a']['score'] ?? '0' }}</span><span class="dash">&ndash;</span><span class="ao">{{ $bout['b']['score'] ?? '0' }}</span></div>
@if ($bout['winner'])
          <div class="winner">&#127942; {{ ($bout['winner'] === 'a' ? $bout['a']['name'] : $bout['b']['name']) }}</div>
@endif
        </div>
        <div class="fighter rev">
          <div class="f-photo">@if ($bout['b']['photo'])<img src="{{ $bout['b']['photo'] }}" alt="">@else<span>{{ $bout['b']['corner_label'] }}</span>@endif</div>
          <div style="min-width:0">
            <div class="corner-tag" style="color:var(--{{ $bout['b']['colour'] === 'red' ? 'red' : 'blue' }}-soft)">{{ $bout['b']['corner_label'] }}<span class="corner-sq" style="background:var(--{{ $bout['b']['colour'] === 'red' ? 'red' : 'blue' }})"></span></div>
            <div class="f-name">{{ $bout['b']['name'] ?: '—' }}</div>
            <div class="f-team">@if ($bout['b']['country'])<span class="fi fi-{{ strtolower($bout['b']['country']) }}"></span> @endif{{ $bout['b']['club'] }}</div>
          </div>
        </div>
      </div>
      <div class="officials">
        <div class="off-head">
          <div class="sec-lbl">OFFICIALS</div>
          <div class="off-meta">@if ($bout['court'])<span><b>{{ __('events.bout_card_court') }}&nbsp;</b>{{ $bout['court'] }}</span>@endif</div>
        </div>
        <div class="off-row" id="officials"></div>
      </div>
    </div>

    <!-- Comments -->
    <div class="card">
      <div class="cm-head"><span class="cm-title">Comments</span><span class="cm-count" id="cmCount"></span></div>
      <div class="cm-compose">
        <div class="cm-avatar" style="background:linear-gradient(135deg,#333,#1f1f1f)">You</div>
        <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:8px">
          <input class="cm-input" id="cmInput" placeholder="Add a comment&hellip;">
          <div style="display:none;justify-content:flex-end;gap:8px" id="cmActions">
            <button class="btn" id="cmCancel" style="padding:8px 14px;font-size:12.5px">Cancel</button>
            <button class="cm-post" id="cmPost">Comment</button>
          </div>
        </div>
      </div>
      <div class="cm-list" id="cmList"></div>
    </div>
  </div>
</div>

<script>
/* ══════════ DATA — from the platform, not typed by anyone ══════════ */
const ROUNDS  = @json($rounds);
const REVIEWS = @json($reviews);
const OFFICIALS = @json($officials);
const COMMENTS  = @json($comments);
const DURATION  = @json($duration);
const ENDPOINTS = {
  comment: @json(route('testcode.me.events.bout.comments.store', ['event' => $e['key'], 'matchNo' => $bout['match_no']])),
  csrf: document.querySelector('meta[name=csrf-token]')?.content || '',
};
const SOURCES = @json(collect($angles)->map(fn ($a) => ['label' => $a['label'], 'hls' => $a['hls'], 'mp4' => $a['mp4']])->values());
const HLS_LIB = @json(asset('vendor/hls/hls.min.js'));

/* ══════════ Escaping ══════════
   Every value below is written by a person — a competitor's name typed on the
   entry sheet, a coach's note, somebody's comment — and every one of them ends
   up inside an innerHTML string. Without this, a comment containing an image
   tag with an onerror handler would execute for every viewer of the bout. Do
   not write a literal example here: prose in a script is still bytes in the
   page, and both scanners and reviewers have to treat it as real. The DATA
   arrives safely — the Blade json directive hex-escapes it into the script — so
   this closes
   the second half of the path: safe data, unsafely re-assembled. */
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
/* A class name is not free text: only the three the stylesheet knows. */
const side = s => (['aka','ao','both'].includes(s) ? s : 'both');


/* ══════════ Player — the real <video>, driving the design's own chrome ══════════ */
const vid = document.getElementById('vid');
const fmt = s => String(Math.floor(s/60)).padStart(2,'0') + ':' + String(s%60).padStart(2,'0');

/* An HLS ladder needs hls.js everywhere but Safari. Fetched on demand — a
   deferred <script> would lose the race with this file. */
function attachSource(){
  if (!vid || !SOURCES.length) return;
  const s = SOURCES[0];
  if (!s.hls) { vid.src = s.mp4; return; }
  if (vid.canPlayType('application/vnd.apple.mpegurl')) { vid.src = s.hls; return; }
  const go = () => {
    if (!window.Hls || !window.Hls.isSupported()) { vid.src = s.mp4; return; }
    const h = new window.Hls(); h.loadSource(s.hls); h.attachMedia(vid);
  };
  if (window.Hls) { go(); return; }
  const tag = document.createElement('script');
  tag.src = HLS_LIB; tag.onload = go; tag.onerror = () => { vid.src = s.mp4; };
  document.head.appendChild(tag);
}
attachSource();

function duration(){ return (vid && isFinite(vid.duration) && vid.duration) ? vid.duration : DURATION; }

function paint(){
  if (!vid) return;
  const t = vid.currentTime || 0, d = duration();
  const pct = (d ? t/d*100 : 0).toFixed(1) + '%';
  document.getElementById('fill').style.width = pct;
  document.getElementById('knob').style.left = pct;
  document.getElementById('clock').textContent = fmt(Math.floor(t)) + ' / ' + fmt(Math.floor(d));
}

function seek(secs, label){
  if (vid) { vid.currentTime = Math.max(0, secs); vid.play().catch(()=>{}); }
  paint();
  const toast = document.getElementById('toast');
  if(label){ toast.textContent = label; toast.classList.add('show'); clearTimeout(toast._h); toast._h = setTimeout(()=>toast.classList.remove('show'), 2500); }
}

/* ── The walk-on ───────────────────────────────────────────────────────────
   Five seconds of the two competitors, then the bout. The skip is there
   because somebody reviewing twenty bouts should never have to sit through it
   twenty times.

   Autoplay is ASKED FOR, not assumed: a browser may refuse to start a video
   the viewer has not gestured at, and refusing silently would leave a black
   frame where the intro used to be. If play() is rejected the centre button
   comes back and the picture waits for a click — which is the same place the
   page was before this existed. */
(function(){
  const intro = document.getElementById('vsIntro');
  const skip  = document.getElementById('vsSkip');
  const player = document.querySelector('.player');
  if (!intro || !player) return;

  player.classList.add('intro');

  let done = false;

  function start(){
    if (done) return;
    done = true;

    clearTimeout(timer);
    intro.classList.add('gone');
    if (skip) skip.remove();
    player.classList.remove('intro');

    // Off the page once faded, so it can never sit invisibly over the picture.
    setTimeout(() => intro.remove(), 500);

    if (vid) vid.play().catch(() => {});
  }

  const timer = setTimeout(start, 5000);
  if (skip) skip.addEventListener('click', start);
})();

document.getElementById('playBtn').onclick = function(){
  if (!vid) return;
  if (vid.paused) { vid.play().catch(()=>{}); } else { vid.pause(); }
};
if (vid) {
  // The class is what hides the disc; the glyph still flips, because the
  // button is the pause control the moment the picture is clicked.
  vid.addEventListener('play',  () => {
    document.getElementById('playBtn').innerHTML = '&#10074;&#10074;';
    document.querySelector('.player').classList.add('playing');
  });
  vid.addEventListener('pause', () => {
    document.getElementById('playBtn').innerHTML = '&#9654;';
    document.querySelector('.player').classList.remove('playing');
  });
  vid.addEventListener('ended', () => document.querySelector('.player').classList.remove('playing'));
  vid.addEventListener('timeupdate', paint);
  vid.addEventListener('loadedmetadata', paint);
  document.querySelector('.ctrl-track').addEventListener('click', e => {
    const r = e.currentTarget.getBoundingClientRect();
    seek(((e.clientX - r.left) / r.width) * duration());
  });
}


/* ══════════ Highlights panel (shared renderer) ══════════ */
function renderPanel(mount, sticky){
  mount.innerHTML = '';
  const tabs = document.createElement('div'); tabs.className = 'tabs';
  const tp = document.createElement('button'); tp.className = 'tab on'; tp.textContent = 'Points';
  const tr = document.createElement('button'); tr.className = 'tab'; tr.textContent = 'Coach Review';
  tabs.append(tp, tr); mount.append(tabs);

  const pPts = document.createElement('div'); pPts.className = 'pane on';
  ROUNDS.forEach(r => {
    const h = document.createElement('div'); h.className = 'rhead';
    h.innerHTML = '<span class="rname">'+esc(r.name)+'</span><span class="rcount">'+r.points.length+' points</span>';
    pPts.append(h);
    r.points.forEach(p => {
      const row = document.createElement('div'); row.className = 'pt-row';
      row.innerHTML = '<span class="pt-time">'+esc(p.time)+'</span><span class="pt-dot '+side(p.side)+'"></span>'
        + '<span style="min-width:0"><span class="pt-action">'+esc(p.action)+'<span class="pt-pts">'+esc(p.pts)+'</span></span>'
        + '<span class="pt-who" style="display:block">'+esc(p.who)+'</span></span>'
        + '<span class="pt-score">'+esc(p.score)+'</span>';
      row.onclick = () => seek(p.secs, p.action + ' · ' + p.who.split(' ·')[0]);
      pPts.append(row);
    });
  });

  const pRev = document.createElement('div'); pRev.className = 'pane';
  const wrap = document.createElement('div'); wrap.className = 'rv-wrap';
  wrap.innerHTML = '<div class="rv-spine"></div>';
  REVIEWS.forEach(rv => {
    const it = document.createElement('div'); it.className = 'rv-item';
    it.innerHTML = '<div class="rv-range">'+esc(rv.range)+'</div>'
      + '<div class="rv-card"><span class="rv-emoji">'+esc(rv.emoji)+'</span>'
      + '<span style="min-width:0;flex:1"><span class="rv-note">'+esc(rv.note)+'</span>'
      + '<span class="rv-foot"><span class="rv-author">'+esc(rv.author)+'</span>'
      + '<span class="rv-slowmo">&#9654; SLOW-MO</span></span></span></div>';
    it.querySelector('.rv-card').onclick = () => seek(rv.secs, 'Coach review · ' + rv.range);
    wrap.append(it);
  });
  pRev.append(wrap);
  mount.append(pPts, pRev);

  tp.onclick = () => { tp.classList.add('on'); tr.classList.remove('on'); pPts.classList.add('on'); pRev.classList.remove('on'); };
  tr.onclick = () => { tr.classList.add('on'); tp.classList.remove('on'); pRev.classList.add('on'); pPts.classList.remove('on'); };
}
renderPanel(document.getElementById('railMount'));
renderPanel(document.getElementById('fsPanel'));

/* ══════════ Rail toggle ══════════ */
const railBtn = document.getElementById('railBtn'), railSlot = document.getElementById('railSlot');
railBtn.onclick = () => {
  const hidden = railSlot.classList.toggle('hidden');
  railBtn.classList.toggle('toggled', !hidden);
  railBtn.innerHTML = hidden ? '&#9776; Highlights' : '&#9776; Hide Highlights';
};

/* ══════════ Fullscreen ══════════ */
const player = document.getElementById('player');
document.getElementById('fsBtn').onclick = () => {
  if (document.fullscreenElement) { document.exitFullscreen(); return; }
  if (player.classList.contains('fs')) { player.classList.remove('fs'); return; }
  if (document.fullscreenEnabled && player.requestFullscreen) {
    player.requestFullscreen().then(()=>player.classList.add('fs')).catch(()=>player.classList.add('fs'));
  } else player.classList.add('fs');
};
document.addEventListener('fullscreenchange', () => { if(!document.fullscreenElement) player.classList.remove('fs'); });
document.addEventListener('keydown', e => { if(e.key === 'Escape') player.classList.remove('fs'); });
const fsHlBtn = document.getElementById('fsHlBtn'), fsPanel = document.getElementById('fsPanel');
fsHlBtn.onclick = () => {
  const open = fsPanel.classList.toggle('open');
  fsHlBtn.classList.toggle('on', open);
};

/* ══════════ Officials ══════════ */
document.getElementById('officials').innerHTML = OFFICIALS.map(o =>
  '<div class="official"><div class="o-photo"></div><div><div class="o-name">'+esc(o.name)+'</div><div class="o-role">'+esc(o.role)+'</div></div></div>'
).join('');

/* ══════════ Comments ══════════ */
const cmList = document.getElementById('cmList');
let replyOpenKey = null;

/* One door to the server for every comment write. */
function send(payload){
  return fetch(ENDPOINTS.comment, {
    method: 'POST', credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
               'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': ENDPOINTS.csrf },
    body: JSON.stringify(payload),
  }).then(r => r.json()).then(d => (d && d.success) ? d : null).catch(() => null);
}

function renderComments(){
  document.getElementById('cmCount').textContent = COMMENTS.length;
  cmList.innerHTML = '';
  COMMENTS.forEach(c => {
    const el = document.createElement('div'); el.className = 'cm-item';

    /* Replies, indented. The design's list is flat, but the data has one level
       of them and hiding a reply would lose half the conversation — drawn with
       the same cm-* pieces so nothing new enters the stylesheet. */
    const replies = (c.replies || []).map(r =>
      '<div class="cm-item" style="margin-top:12px">'
      + '<div class="cm-avatar" style="background:'+esc(r.bg)+';width:30px;height:30px;font-size:10.5px">'+esc(r.initials)+'</div>'
      + '<div style="min-width:0;flex:1"><span class="cm-name">'+esc(r.name)+'</span><span class="cm-when">'+esc(r.when)+'</span>'
      + '<div class="cm-text">'+(r.mention ? '<span style="color:var(--blue-soft);font-weight:700">'+esc(r.mention)+'</span> ' : '')+esc(r.text)+'</div>'
      + '</div></div>').join('');

    el.innerHTML = '<div class="cm-avatar" style="background:'+esc(c.bg)+'">'+esc(c.initials)+'</div>'
      + '<div style="min-width:0;flex:1"><span class="cm-name">'+esc(c.name)+'</span><span class="cm-when">'+esc(c.when)+'</span>'
      + '<div class="cm-text">'+(c.stamp ? '<button class="cm-stamp">'+esc(c.stamp)+'</button>' : '')+esc(c.text)+'</div>'
      + '<div class="cm-tools"><span class="cm-like"'+(c.liked?' style="color:var(--red-soft)"':'')+'>&#9825; '+Number(c.likes || 0)+'</span><span class="reply-btn">Reply</span></div>'
      + (replyOpenKey === c.key
          ? '<div style="display:flex;gap:8px;margin-top:10px"><input class="cm-input rp-in" placeholder="Reply to '+esc(c.name)+'&hellip;"><button class="cm-post rp-go">Reply</button></div>'
          : '')
      + (replies ? '<div style="margin-top:6px;padding-left:14px;border-left:1px solid var(--line-2)">'+replies+'</div>' : '')
      + '</div>';

    const st = el.querySelector('.cm-stamp');
    if (st) st.onclick = () => seek(c.secs, 'Comment · ' + c.stamp);

    el.querySelector('.cm-like').onclick = () => {
      fetch(ENDPOINTS.comment + '/' + c.key + '/like', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': ENDPOINTS.csrf },
      }).then(r => r.json()).then(d => { if (d && d.success) { c.likes = d.likes; c.liked = d.liked; renderComments(); } }).catch(()=>{});
    };

    el.querySelector('.reply-btn').onclick = () => {
      replyOpenKey = replyOpenKey === c.key ? null : c.key; renderComments();
    };

    const rin = el.querySelector('.rp-in');
    if (rin) {
      const go = () => {
        const txt = rin.value.trim(); if (!txt) return;
        send({ body: txt, parent: c.key }).then(res => {
          if (!res) return;
          c.replies = c.replies || [];
          c.replies.push(Object.assign({}, res.comment, { mention: '@' + c.name.split(' ')[0] }));
          replyOpenKey = null; renderComments();
        });
      };
      el.querySelector('.rp-go').onclick = go;
      rin.onkeydown = e => { if (e.key === 'Enter') go(); };
    }

    cmList.append(el);
  });
}
renderComments();

const cmInput = document.getElementById('cmInput'), cmActions = document.getElementById('cmActions');
cmInput.oninput = () => cmActions.style.display = cmInput.value ? 'flex' : 'none';
function postComment(){
  const txt = cmInput.value.trim(); if(!txt) return;
  /* Comment on the moment being watched: the design shows a timestamp chip and
     the only honest value for it is where the playhead actually is. */
  const at = vid && vid.currentTime > 1 ? Math.floor(vid.currentTime) : null;
  send({ body: txt, stamp_seconds: at }).then(res => {
    if (!res) return;
    COMMENTS.unshift(Object.assign({}, res.comment, { replies: [] }));
    cmInput.value = ''; cmActions.style.display = 'none'; renderComments();
  });
}
document.getElementById('cmPost').onclick = postComment;
document.getElementById('cmCancel').onclick = () => { cmInput.value=''; cmActions.style.display='none'; };
cmInput.onkeydown = e => { if(e.key === 'Enter') postComment(); };

/* First paint: the chrome must never show a score nobody has reached. */
paint();
</script>
</body>
</html>
