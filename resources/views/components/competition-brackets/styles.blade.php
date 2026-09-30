{{--
    <competition-brackets> — stylesheet. Emitted ONCE per page by the component
    (@once), however many boards the page draws. Every class is prefixed `cb-`
    so nothing here can reach outside the component.

    The Poppins faces are the twelve woff2 files the design file shipped,
    already self-hosted at public/bracket/fonts (the live draw page uses the
    same files). No CDN.
--}}
<style>
@font-face { font-family: 'Poppins'; font-style: normal; font-weight: 400; font-display: swap; src: url("{{ asset('bracket/fonts/a8205188-277b-4d07-a93b-10b008a6a108.woff2') }}") format('woff2'); unicode-range: U+0900-097F, U+1CD0-1CF9, U+200C-200D, U+20A8, U+20B9, U+20F0, U+25CC, U+A830-A839, U+A8E0-A8FF, U+11B00-11B09; }
@font-face { font-family: 'Poppins'; font-style: normal; font-weight: 400; font-display: swap; src: url("{{ asset('bracket/fonts/54319b01-48d0-46a7-8624-443f5c63d29b.woff2') }}") format('woff2'); unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF; }
@font-face { font-family: 'Poppins'; font-style: normal; font-weight: 400; font-display: swap; src: url("{{ asset('bracket/fonts/384294e9-7632-4129-a9c1-8ebad6fcfc6a.woff2') }}") format('woff2'); unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD; }
@font-face { font-family: 'Poppins'; font-style: normal; font-weight: 500; font-display: swap; src: url("{{ asset('bracket/fonts/10f487a6-6225-40f5-b7d8-9e0344d5baba.woff2') }}") format('woff2'); unicode-range: U+0900-097F, U+1CD0-1CF9, U+200C-200D, U+20A8, U+20B9, U+20F0, U+25CC, U+A830-A839, U+A8E0-A8FF, U+11B00-11B09; }
@font-face { font-family: 'Poppins'; font-style: normal; font-weight: 500; font-display: swap; src: url("{{ asset('bracket/fonts/a6df239e-2e0e-4e42-b796-8d5995f3bbb3.woff2') }}") format('woff2'); unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF; }
@font-face { font-family: 'Poppins'; font-style: normal; font-weight: 500; font-display: swap; src: url("{{ asset('bracket/fonts/576db428-a4cd-4630-a590-7493e631d958.woff2') }}") format('woff2'); unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD; }
@font-face { font-family: 'Poppins'; font-style: normal; font-weight: 600; font-display: swap; src: url("{{ asset('bracket/fonts/a288cd69-76ab-4a10-8be2-98e6b26a650f.woff2') }}") format('woff2'); unicode-range: U+0900-097F, U+1CD0-1CF9, U+200C-200D, U+20A8, U+20B9, U+20F0, U+25CC, U+A830-A839, U+A8E0-A8FF, U+11B00-11B09; }
@font-face { font-family: 'Poppins'; font-style: normal; font-weight: 600; font-display: swap; src: url("{{ asset('bracket/fonts/4e250ff1-fd2d-4287-8ae5-4fa324d43907.woff2') }}") format('woff2'); unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF; }
@font-face { font-family: 'Poppins'; font-style: normal; font-weight: 600; font-display: swap; src: url("{{ asset('bracket/fonts/189ba13f-7d30-4dc0-8c14-635c5c0d08e3.woff2') }}") format('woff2'); unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD; }
@font-face { font-family: 'Poppins'; font-style: normal; font-weight: 700; font-display: swap; src: url("{{ asset('bracket/fonts/eb500dce-05af-4c84-8c26-a8081c3c6041.woff2') }}") format('woff2'); unicode-range: U+0900-097F, U+1CD0-1CF9, U+200C-200D, U+20A8, U+20B9, U+20F0, U+25CC, U+A830-A839, U+A8E0-A8FF, U+11B00-11B09; }
@font-face { font-family: 'Poppins'; font-style: normal; font-weight: 700; font-display: swap; src: url("{{ asset('bracket/fonts/2f337839-cafe-4f8f-8ff7-2cab100b9852.woff2') }}") format('woff2'); unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF; }
@font-face { font-family: 'Poppins'; font-style: normal; font-weight: 700; font-display: swap; src: url("{{ asset('bracket/fonts/81156588-8d78-4bd6-a251-c16d2f4c8b8e.woff2') }}") format('woff2'); unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD; }
.cb-root{position:relative;overflow:hidden;background:var(--cb-bg,#0b132b);font-family:'Poppins',system-ui,sans-serif;color:#1e2c4f;cursor:grab;touch-action:none;-webkit-user-select:none;user-select:none;box-sizing:border-box}
.cb-root *,.cb-root *::before,.cb-root *::after{box-sizing:border-box}
.cb-root input{font-family:inherit;-webkit-user-select:text;user-select:text}
.cb-root input:focus{outline:2px solid #caa14f}
.cb-inner{display:flex;gap:36px;width:max-content;transform-origin:0 0;direction:ltr;will-change:transform}
.cb-col{flex:none;display:flex;flex-direction:column;position:relative}
.cb-colhead{height:40px;font-size:1.05rem;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:#d8b25f;text-align:center;white-space:nowrap}
.cb-rows{flex:1;display:flex;flex-direction:column;justify-content:space-around}
.cb-pair{flex:1;position:relative;display:flex;flex-direction:column;justify-content:space-around;padding:11px 0;gap:22px}

/* ── A bout ─────────────────────────────────────────────────────────── */
.cb-card{border-radius:10px;overflow:hidden;background:#fff;color:#1e2c4f;box-shadow:0 8px 24px rgba(11,19,43,.35)}
.cb-tag{display:flex;justify-content:space-between;gap:8px;padding:4px 10px;font-size:.58rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:#5d6880;background:#eef1f6;border-bottom:1px solid #d5dae3}
.cb-tag .cb-mat{color:#8a6d20}
.cb-card--bronze{background:#faf1e6;box-shadow:0 0 0 2px #b07a45,0 8px 24px rgba(11,19,43,.35)}
.cb-card--bronze .cb-tag{color:#7a4f22;background:#ecd9bd;border-bottom-color:#d8bd93}
.cb-card--bronze .cb-tag .cb-mat{color:#7a4f22}
.cb-note{padding:5px 10px;font-size:.6rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#8a6d20;background:#fdf6e6;border-top:1px solid #eef1f6}

/* ── A corner. Red is always the upper half, blue the lower. ─────────── */
.cb-slot{display:flex;align-items:center;gap:8px;padding:8px 10px;min-height:72px;cursor:pointer;outline-offset:-2px;border-left:3px solid #c0392b;transition:background-color .15s ease}
.cb-slot[draggable="true"]{cursor:grab}
.cb-slot > .cb-x{display:contents}
.cb-photo{width:39px;height:52px;flex:none;position:relative;border:2px solid #d5dae3;border-radius:8px;background-color:#e7eaf0;background-size:cover;background-position:center}
.cb-tick{position:absolute;right:-7px;top:-7px;width:20px;height:20px;border-radius:50%;background:#d8b25f;color:#1e2c4f;font-size:.72rem;font-weight:800;display:grid;place-items:center;line-height:1;box-shadow:0 0 0 2px #fff}
.cb-who{flex:1;min-width:0}
.cb-line{display:flex;align-items:center;gap:6px;min-width:0}
.cb-name{font-size:.78rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cb-flag{width:18px;height:13px;flex:none;border-radius:2px;box-shadow:0 0 0 1px #d5dae3;background-size:cover;background-position:center}
.cb-clubline{display:flex;align-items:center;gap:5px;margin-top:2px;min-width:0}
.cb-ab{width:14px;height:14px;flex:none;border-radius:50%;background:linear-gradient(100deg,#edcd85,#d0a854);color:#1e2c4f;font-size:.5rem;font-weight:700;line-height:1;display:grid;place-items:center;text-align:center;overflow:hidden}
.cb-club{font-size:.62rem;color:#5d6880;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cb-score{width:48px;flex:none;text-align:center;font-size:.9rem;font-weight:700;padding:4px 0}
.cb-score-in{width:48px;flex:none;text-align:center;font-size:.9rem;font-weight:700;border:1px solid;border-radius:6px;padding:4px 0;background:#fff}
.cb-name-in{flex:1;min-width:0;font-size:.78rem;font-weight:600;color:#1e2c4f;border:1px solid #caa14f;border-radius:6px;padding:5px 7px}
.cb-remove{width:24px;height:24px;flex:none;border:1px solid #d5dae3;border-radius:6px;cursor:pointer;background:#fff;color:#b4483c;font-size:.7rem;font-weight:700;line-height:1;padding:0}
.cb-empty-photo{width:39px;height:52px;flex:none;border-radius:8px;border:2px dashed #d5dae3}
.cb-empty{font-size:.75rem;font-weight:500;color:#97a1b4}

/* ── The podium ─────────────────────────────────────────────────────── */
.cb-champ{position:relative;overflow:hidden;border-radius:14px;background:linear-gradient(100deg,#edcd85,#d0a854);color:#1e2c4f;padding:18px;animation:cb-champ .5s ease both}
.cb-champ-head{display:flex;align-items:center;gap:8px;font-size:.62rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase}
.cb-champ-body{display:flex;align-items:center;gap:12px;margin-top:10px}
.cb-champ-photo{width:57px;height:76px;flex:none;border:2px solid #1e2c4f;border-radius:12px;background-size:cover;background-position:center}
.cb-champ-name{font-size:1.05rem;font-weight:700;line-height:1.2}
.cb-champ-club{display:flex;align-items:center;gap:6px;margin-top:3px;font-size:.68rem;font-weight:500}
.cb-champ-club .cb-flag{box-shadow:0 0 0 1px #1e2c4f}
.cb-conf{position:absolute;top:0;animation:cb-conf 2s ease-in infinite}
.cb-champ-empty{border:2px dashed #42527d;border-radius:14px;padding:22px 18px;text-align:center;font-size:.8rem;color:#97a1b4}
.cb-chip{display:flex;align-items:center;gap:10px;background:#fff;border-radius:10px;padding:8px 12px;color:#1e2c4f}
.cb-medal{width:26px;height:26px;flex:none;border-radius:50%;color:#fff;font-size:.72rem;font-weight:700;display:grid;place-items:center}
.cb-chip-photo{width:30px;height:40px;flex:none;border-radius:7px;border:1px solid #d5dae3;background-size:cover;background-position:center}
.cb-chip-name{font-size:.76rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cb-chip-club{font-size:.62rem;color:#5d6880;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cb-tbd{font-size:.76rem;color:#97a1b4}

/* ── Chrome over the canvas: toolbar, menu, notice, basket, add panel ── */
.cb-tools{position:absolute;top:12px;right:12px;z-index:70;display:flex;align-items:center;gap:8px;cursor:default}
.cb-btn{width:40px;height:40px;border-radius:50%;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.12);color:#fff;display:grid;place-items:center;cursor:pointer;font-size:1rem;padding:0;transition:background-color .15s ease,transform .1s ease;-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px)}
.cb-btn:hover{background:rgba(255,255,255,.22)}
.cb-btn:active{transform:scale(.92)}
.cb-btn.cb-on{background:linear-gradient(100deg,#edcd85,#d0a854);border-color:#caa14f;color:#1e2c4f}
.cb-menu{position:absolute;top:calc(100% + 10px);right:0;width:280px;max-width:calc(100vw - 2rem);background:#fff;color:#1e2c4f;border-radius:14px;box-shadow:0 16px 40px rgba(0,0,0,.45);padding:12px;z-index:80}
.cb-menu-h{font-size:.6rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#8a6d20;margin:4px 2px 6px}
.cb-seg{display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap}
.cb-seg button{flex:1;min-width:0;border:1px solid #d5dae3;border-radius:9px;padding:7px 6px;font-size:.72rem;font-weight:600;cursor:pointer;background:#fff;color:#5d6880;font-family:inherit}
.cb-seg button.cb-on{background:#d8b25f;border-color:#caa14f;color:#1e2c4f}
.cb-item{display:flex;align-items:center;gap:10px;width:100%;border:none;background:transparent;border-radius:9px;padding:9px 8px;font-size:.78rem;font-weight:600;color:#1e2c4f;cursor:pointer;text-align:start;font-family:inherit}
.cb-item:hover{background:#f4f1ea}
.cb-item i{width:18px;text-align:center;color:#8a6d20}
.cb-item.cb-danger,.cb-item.cb-danger i{color:#b4483c}
.cb-sep{height:1px;background:#eef1f6;margin:6px 0}
.cb-notice{position:absolute;top:64px;right:12px;z-index:90;display:flex;align-items:flex-start;gap:12px;background:#fdf6e6;border-left:4px solid #d8b25f;color:#1e2c4f;border-radius:0 12px 12px 0;padding:12px 14px;font-size:.78rem;line-height:1.5;max-width:min(420px,calc(100% - 24px));box-shadow:0 16px 40px rgba(0,0,0,.45);animation:cb-toast .35s ease both;cursor:default}
.cb-notice button{flex:none;border:none;border-radius:6px;cursor:pointer;background:transparent;color:#8a6d20;font-size:.8rem;font-weight:700;line-height:1;padding:3px 4px;font-family:inherit}
.cb-basket{position:absolute;bottom:14px;right:14px;z-index:60;max-width:min(340px,calc(100% - 28px));border:2px dashed #d8b25f;border-radius:14px;padding:12px 14px;background:rgba(11,19,43,.94);box-shadow:0 16px 40px rgba(0,0,0,.45);cursor:default}
.cb-basket-h{display:flex;align-items:center;gap:10px}
.cb-basket-t{flex:1;font-size:.62rem;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:#d8b25f}
.cb-basket-min{width:24px;height:24px;flex:none;border:1px solid #d8b25f;border-radius:6px;cursor:pointer;background:transparent;color:#d8b25f;font-size:.85rem;font-weight:700;line-height:1;padding:0}
.cb-basket-list{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px}
.cb-person{display:flex;align-items:center;gap:8px;background:#fff;color:#1e2c4f;border-radius:10px;padding:6px 12px 6px 6px;cursor:grab;box-shadow:0 4px 12px rgba(11,19,43,.3);min-width:0}
.cb-person .cb-photo{width:33px;height:44px;border-color:#d8b25f}
.cb-hint{margin-top:8px;font-size:.62rem;color:#97a1b4}
.cb-add{position:absolute;top:12px;left:12px;z-index:75;width:330px;max-width:calc(100% - 24px);max-height:calc(100% - 24px);display:flex;flex-direction:column;background:#fff;color:#1e2c4f;border-radius:16px;box-shadow:0 20px 50px rgba(0,0,0,.5);overflow:hidden;cursor:default}
.cb-add-h{flex:none;display:flex;align-items:center;gap:10px;padding:12px 14px;background:linear-gradient(150deg,#1e2c4f,#1e2c4fb0);color:#fff}
.cb-add-h b{flex:1;font-size:.85rem}
.cb-add-h button{width:30px;height:30px;border-radius:50%;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.12);color:#fff;cursor:pointer;display:grid;place-items:center;padding:0}
.cb-add-b{flex:1;overflow-y:auto;padding:12px 14px}
.cb-field{width:100%;border:1px solid #d5dae3;border-radius:10px;padding:9px 11px;font-size:.8rem;color:#1e2c4f;background:#fff}
.cb-label{display:block;font-size:.62rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#5d6880;margin:10px 0 5px}
.cb-dd{position:relative}
.cb-dd-btn{width:100%;display:flex;align-items:center;gap:8px;border:1px solid #d5dae3;border-radius:10px;padding:9px 11px;font-size:.8rem;color:#1e2c4f;background:#fff;cursor:pointer;text-align:start;font-family:inherit}
.cb-dd-btn .bi-chevron-down{margin-inline-start:auto;transition:transform .15s ease}
.cb-dd-btn.cb-open .bi-chevron-down{transform:rotate(180deg)}
.cb-dd-panel{margin-top:6px;border:1px solid #eef1f6;border-radius:12px;max-height:200px;overflow-y:auto;box-shadow:0 8px 20px rgba(11,19,43,.12)}
.cb-dd-row{display:flex;align-items:center;gap:8px;width:100%;border:none;background:#fff;padding:8px 11px;font-size:.78rem;cursor:pointer;text-align:start;color:#1e2c4f;font-family:inherit}
.cb-dd-row:hover{background:#fdf6e6}
.cb-dd-row.cb-sel{background:#fdf6e6;font-weight:600}
.cb-roster-row{display:flex;align-items:center;gap:8px;padding:6px;border-radius:10px;cursor:grab}
.cb-roster-row:hover{background:#f7f5f0}
.cb-roster-row .cb-photo{width:30px;height:40px}
.cb-mini{flex:none;border:1px solid #caa14f;background:#fdf6e6;color:#8a6d20;border-radius:8px;padding:5px 9px;font-size:.68rem;font-weight:700;cursor:pointer;font-family:inherit}
.cb-primary{width:100%;margin-top:14px;border:none;border-radius:11px;padding:11px;font-size:.82rem;font-weight:700;background:linear-gradient(100deg,#edcd85,#d0a854);color:#1e2c4f;cursor:pointer;font-family:inherit}
.cb-primary:disabled{opacity:.5;cursor:not-allowed}

@keyframes cb-in{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:none}}
@keyframes cb-pulse{
  0%,100%{box-shadow:0 0 0 3px #d8b25f,0 0 16px 4px rgba(216,178,95,.55),0 8px 24px rgba(11,19,43,.35)}
  50%{box-shadow:0 0 0 6px #edcd85,0 0 44px 14px rgba(216,178,95,.95),0 8px 24px rgba(11,19,43,.35)}
}
@keyframes cb-conf{0%{transform:translateY(-12px) rotate(0deg);opacity:1}100%{transform:translateY(160px) rotate(320deg);opacity:0}}
@keyframes cb-champ{from{opacity:0;transform:scale(.92)}to{opacity:1;transform:none}}
@keyframes cb-toast{from{opacity:0;transform:translateX(24px)}to{opacity:1;transform:none}}
@media (prefers-reduced-motion: reduce){
  .cb-root *{animation:none !important;transition:none !important}
}
</style>
