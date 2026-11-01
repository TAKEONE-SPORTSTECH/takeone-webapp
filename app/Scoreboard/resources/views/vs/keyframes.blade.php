{{-- The VS screen's animations — included INSIDE the host page's <style>. --}}
  /* ── The VS arena's loops (design/vs-arena.source.html) ─────────────────── */
  @keyframes vsPulse { 0%,100% { text-shadow:0 0 30px rgba(255,215,120,0.55), 0 0 90px rgba(255,170,60,0.3); transform:scale(1); } 50% { text-shadow:0 0 55px rgba(255,225,150,0.85), 0 0 140px rgba(255,180,70,0.5); transform:scale(1.03); } }
  @keyframes slowDrift { 0% { transform:translate3d(0,0,0) scale(1.02); } 50% { transform:translate3d(0,-1.2%,0) scale(1.05); } 100% { transform:translate3d(0,0,0) scale(1.02); } }
  @keyframes shineSweep { 0% { transform:translateX(-130%) skewX(-18deg); } 60%,100% { transform:translateX(230%) skewX(-18deg); } }
  @keyframes panelL { from { transform:translateX(-105%); } to { transform:translateX(0); } }
  @keyframes panelR { from { transform:translateX(105%); } to { transform:translateX(0); } }
  @keyframes riseUp { from { opacity:0; transform:translateY(43.2px); } to { opacity:1; transform:translateY(0); } }
  @keyframes dropIn { from { opacity:0; transform:translate(-50%,-32.4px); } to { opacity:1; transform:translate(-50%,0); } }
  @keyframes vsSlam { 0% { opacity:0; transform:translate(-50%,-52%) scale(3.4) rotate(-6deg); } 60% { opacity:1; transform:translate(-50%,-52%) scale(0.92) rotate(2deg); } 100% { opacity:1; transform:translate(-50%,-52%) scale(1) rotate(0); } }
  @keyframes flashOut { 0% { opacity:0; } 12% { opacity:0.85; } 100% { opacity:0; } }
  @keyframes riseC { from { opacity:0; transform:translate(-50%,43.2px); } to { opacity:1; transform:translate(-50%,0); } }

  /* The wipe that hands the screen from the introduction to the bout. This is
     the transition the whole single-document design exists for. */
  @keyframes vsExit { from { opacity:1; transform:scale(1); } to { opacity:0; transform:scale(1.08); } }

