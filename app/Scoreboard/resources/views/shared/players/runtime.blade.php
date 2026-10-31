{{--
    The console Players tab — everything but the pane: its styles, its own
    3:4 photo cropper, the VS-screen framing editor, and the script that
    drives scoreboard::shared.players.pane. Include ONCE, inside the console's
    #stage (the 1920x1080 canvas the console scales), so its pop-ups scale with
    the console. ORGANISERS only — the console includes neither part otherwise.

    $players  [
      'list'       => the entrants (GET); one person is list.'/'.{registration}
                      (GET, PUT), their VS framing list.'/'.{registration}.'/vs-frame'
      'vsPreview'  => the shared VS preview (ConsolePlayers::vsPreview), or null
      'geo'        => where this device is, as a country — seeds a blank nationality
      'classDoors' => the platform's weight-class doors (signed-in only), or null
      'drawUrl'    => where a weight-class chip opens the draw, or null
      'mat'        => this console's mat
    ]

    Standalone: it reads no console global and binds to nothing outside its own
    ids (`cp…`). A console marks whatever OPENS the pane with `data-players-open`
    — a tab, a button — and the list is read the first time that is pressed.
    After a save it dispatches `console-players:changed` {player} on window,
    for a console that wants to re-read names it has drawn.

    Docs: docs/events/console-players.md
--}}
<style>
  .cpl, .cpm{--cp-gold:#ffe135;--cp-green:#7ae582;--cp-ink:#0a0b10;--cp-inset:#0e1017;--cp-panel:#111320;
       --cp-line:#1f2230;--cp-line-2:#2a2e40;--cp-fill:#1f2230;--cp-fill-alt:#12141d;--cp-text:#e8eaf2;
       --cp-second:#a7abbe;--cp-muted:#7d8296;--cp-faint:#5c6175;--cp-red:#ff6b78;--cp-blue:#8ab4ff;--cp-alarm:#ff3b47;
       font-family:'Inter',system-ui,sans-serif;color:var(--cp-text);}
  .cpl{flex:1;min-height:0;display:flex;flex-direction:column;}
  .cpl *, .cpm *{box-sizing:border-box;}
  .cpl [hidden], .cpm[hidden], .cpm [hidden]{display:none !important;}
  .cpl-split{flex:1;min-height:0;display:grid;grid-template-columns:500px minmax(0,1fr);gap:26px;}
  .cpl-index{min-height:0;display:flex;flex-direction:column;gap:12px;}
  .cpl-scroll{flex:1;min-height:0;overflow-y:auto;display:flex;flex-direction:column;gap:8px;padding-right:14px;}
  .cpl-find{width:100%;font-size:26px;background:var(--cp-inset);border:1px solid var(--cp-line);border-radius:10px;
       color:var(--cp-text);padding:14px 18px;flex-shrink:0;font-family:inherit;}
  .cpl-find:focus{outline:none;border-color:var(--cp-gold);}
  .cpl-hint{font-size:20px;color:var(--cp-faint);}
  .cpl-nothing{font-size:22px;color:var(--cp-faint);padding:18px 4px;}
  .cpl-rowbtn{display:flex;align-items:center;gap:14px;text-align:left;background:var(--cp-ink);color:var(--cp-text);
       border:1px solid var(--cp-line);border-radius:12px;padding:10px 16px;cursor:pointer;font-size:24px;font-family:inherit;}
  .cpl-rowbtn:hover{background:var(--cp-fill);}
  .cpl-rowbtn[aria-current="true"]{border-color:var(--cp-gold);background:var(--cp-fill);}
  .cpl-cell{display:flex;align-items:center;gap:12px;min-width:0;flex:1;}
  .cpl-face{width:48px;height:64px;flex:0 0 auto;border-radius:8px;background:#1f2230 center 15%/cover no-repeat;border:2px solid;
       display:flex;align-items:center;justify-content:center;font-family:'Anton',sans-serif;font-size:20px;}
  .cpl-who{display:flex;flex-direction:column;min-width:0;}
  .cpl-who b{font-weight:600;overflow-wrap:anywhere;}
  .cpl-who small{display:flex;align-items:center;gap:8px;font-size:16px;color:var(--cp-muted);overflow-wrap:anywhere;}
  .cpl-flag{width:24px;height:18px;flex:0 0 auto;background:center/100% 100% no-repeat;border:1px solid rgba(255,255,255,.2);border-radius:2px;}
  .cpl-facts{font-size:16px;color:var(--cp-muted);text-align:right;max-width:42%;overflow-wrap:anywhere;}
  .cpl-right{position:relative;min-height:0;min-width:0;display:flex;flex-direction:column;}
  .cpl-empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:var(--cp-faint);
       font-size:22px;letter-spacing:.08em;text-transform:uppercase;border:1px dashed var(--cp-line-2);border-radius:20px;}
  .cpl-edit{flex:1;min-height:0;display:flex;flex-direction:column;gap:12px;}
  .cpl-title{font-family:'Anton',sans-serif;font-size:30px;letter-spacing:.04em;color:var(--cp-text);min-width:0;overflow-wrap:anywhere;flex-shrink:0;}
  .cpl-classes{display:flex;flex-wrap:wrap;gap:6px;flex-shrink:0;font-family:'Inter',system-ui,sans-serif;}
  .cpl-class{display:inline-flex;align-items:center;gap:10px;border-radius:999px;padding:4px 12px;background:rgba(255,225,53,.12);
       border:1px solid rgba(255,225,53,.4);color:var(--cp-gold);white-space:nowrap;font-size:16px;font-family:inherit;}
  .cpl-class b{font-family:'Anton',sans-serif;font-weight:400;font-size:18px;letter-spacing:.04em;}
  .cpl-class span{color:var(--cp-text);font-size:16px;white-space:normal;}
  .cpl-class.go{cursor:pointer;}
  .cpl-class.go:hover{background:rgba(255,225,53,.22);border-color:var(--cp-gold);}
  /* Weight classes as cards under the name; one dashed card adds another. */
  .cpl-wcstrip{display:flex;flex-wrap:wrap;gap:12px;}
  .cpl-wccard{position:relative;display:flex;align-items:center;gap:16px;min-width:340px;max-width:100%;
       background:linear-gradient(180deg,#171a2b 0%,#111320 100%);border:1px solid var(--cp-line-2);border-radius:14px;
       padding:10px 12px 10px 22px;box-shadow:0 6px 18px rgba(0,0,0,.28);animation:cpdrop .14s ease-out;}
  .cpl-wccard::before{content:'';position:absolute;left:-1px;top:-1px;bottom:-1px;width:6px;border-radius:14px 0 0 14px;background:var(--cp-gold);}
  .cpl-wccard .w{font-family:'Anton',sans-serif;font-size:30px;line-height:1;color:var(--cp-gold);white-space:nowrap;}
  .cpl-wccard .n{min-width:0;flex:1;font-size:17px;font-weight:600;overflow-wrap:anywhere;}
  .cpl-wccard .n small{display:block;font-size:14px;font-weight:400;color:var(--cp-faint);margin-top:3px;}
  .cpl-wccard .acts{display:flex;gap:8px;flex:0 0 auto;}
  .cpl-wccard .cpl-xbtn{height:44px;min-width:0;padding:0 16px;font-size:16px;}
  .cpl-wccard.arm{border-color:rgba(255,107,120,.6);background:rgba(255,59,71,.08);}
  .cpl-wccard.arm::before{background:var(--cp-red);}
  .cpl-wccard.busy{opacity:.55;pointer-events:none;}
  .cpl-wcadd{display:inline-flex;align-items:center;gap:12px;min-height:66px;padding:0 22px;border-radius:14px;cursor:pointer;
       background:transparent;border:2px dashed rgba(255,225,53,.45);color:var(--cp-gold);font-family:'Anton',sans-serif;
       font-size:19px;letter-spacing:.1em;text-transform:uppercase;}
  .cpl-wcadd:hover:not(:disabled){background:rgba(255,225,53,.08);border-color:var(--cp-gold);}
  .cpl-wcadd:disabled{opacity:.4;cursor:not-allowed;}
  .cpl-wcadd .plus{font-size:28px;line-height:1;}
  .cpl-wcnone{display:flex;align-items:center;font-size:18px;color:var(--cp-red);padding:0 6px;}
  .cpl-wcpick{position:absolute;inset:0;z-index:30;display:flex;flex-direction:column;gap:14px;
       background:linear-gradient(180deg,#141726 0%,#0e1019 100%);border:1px solid var(--cp-line-2);border-radius:20px;
       padding:22px 26px;box-shadow:0 22px 60px rgba(0,0,0,.6);animation:cpdrop .16s ease-out;}
  .cpl-wcpick .head{display:flex;align-items:center;gap:16px;flex-shrink:0;}
  .cpl-wcpick .head h4{margin:0;flex:1;min-width:0;font-family:'Anton',sans-serif;font-weight:400;font-size:30px;letter-spacing:.04em;overflow-wrap:anywhere;}
  .cpl-wcpick .head h4 small{display:block;font-family:'Inter',system-ui,sans-serif;font-size:17px;letter-spacing:0;color:var(--cp-faint);margin-top:4px;}
  .cpl-wcopts{flex:1;min-height:0;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:10px;align-content:start;padding-right:8px;}
  .cpl-wcopt{position:relative;display:flex;flex-direction:column;gap:4px;text-align:left;cursor:pointer;font-family:inherit;
       background:var(--cp-inset);border:1px solid var(--cp-line);border-radius:12px;padding:12px 16px 12px 18px;color:var(--cp-text);}
  .cpl-wcopt:hover:not(:disabled){border-color:var(--cp-gold);}
  .cpl-wcopt:disabled{cursor:not-allowed;}
  .cpl-wcopt .w{font-family:'Anton',sans-serif;font-size:26px;line-height:1.05;color:var(--cp-gold);}
  .cpl-wcopt .nm{font-size:17px;font-weight:600;overflow-wrap:anywhere;}
  .cpl-wcopt .dt{font-size:14px;color:var(--cp-faint);}
  .cpl-wcopt .why{font-size:14px;color:#ff9a9a;}
  .cpl-wcopt .tag{position:absolute;top:10px;right:12px;font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
       border-radius:999px;padding:3px 10px;background:rgba(122,229,130,.12);color:var(--cp-green);border:1px solid rgba(122,229,130,.35);}
  .cpl-wcopt.out{opacity:.62;}
  .cpl-wcopt.out .tag{background:rgba(255,107,120,.1);color:var(--cp-red);border-color:rgba(255,107,120,.35);}
  .cpl-wcopt.here{opacity:.45;}
  .cpl-wcopt.here .tag{background:var(--cp-fill-alt);color:var(--cp-muted);border-color:var(--cp-line);}
  .cpl-wcopt[aria-pressed="true"]{border-color:var(--cp-gold);background:rgba(255,225,53,.08);box-shadow:inset 0 0 0 1px var(--cp-gold);}
  .cpl-wcpick .foot{display:flex;align-items:center;gap:14px;flex-shrink:0;}
  .cpl-wcpick .foot .msg{flex:1;min-width:0;font-size:18px;line-height:1.4;color:var(--cp-second);}
  @keyframes cpdrop{from{opacity:0;transform:translateY(-6px);}to{opacity:1;transform:none;}}
  @media (prefers-reduced-motion: reduce){.cpl-wccard,.cpl-wcpick{animation:none;}}
  /* One button family. */
  .cpl-xbtn{display:inline-flex;align-items:center;justify-content:center;gap:10px;height:50px;min-width:140px;padding:0 22px;
       border-radius:10px;cursor:pointer;white-space:nowrap;font-family:'Anton',sans-serif;font-size:19px;letter-spacing:.1em;
       text-transform:uppercase;background:var(--cp-fill);color:var(--cp-text);border:1px solid var(--cp-line-2);}
  .cpl-xbtn:hover:not(:disabled){border-color:var(--cp-gold);}
  .cpl-xbtn.primary{background:var(--cp-gold);color:#000;border-color:transparent;}
  .cpl-xbtn.ghost{background:transparent;color:var(--cp-second);}
  .cpl-xbtn.danger{background:transparent;color:var(--cp-red);border-color:rgba(255,107,120,.5);}
  .cpl-xbtn.danger.arm{background:var(--cp-alarm);color:#fff;border-color:transparent;}
  .cpl-xbtn:disabled{opacity:.4;cursor:not-allowed;}
  /* The sheet: the face on the left, two sections on the right. */
  .cpl-card{flex:0 0 auto;width:100%;display:flex;flex-direction:column;gap:18px;
       background:linear-gradient(180deg,#161929 0%,#111320 100%);border:1px solid var(--cp-line-2);
       border-radius:20px;padding:24px 28px;box-shadow:0 14px 40px rgba(0,0,0,.4);}
  .cpl-ed{display:grid;grid-template-columns:250px minmax(0,1fr);gap:22px;}
  .cpl-photocol{display:flex;flex-direction:column;align-items:center;gap:12px;align-self:start;
       background:var(--cp-inset);border:1px solid var(--cp-line);border-radius:16px;padding:16px 10px;}
  .cpl-photo{width:225px;height:300px;border-radius:12px;border:2px solid var(--cp-line-2);
       background:var(--cp-fill) center 15%/cover no-repeat;cursor:pointer;}
  .cpl-photo:hover:not(:disabled){border-color:var(--cp-gold);}
  .cpl-photo:disabled{cursor:default;}
  .cpl-btn{font-size:18px;letter-spacing:.08em;text-transform:uppercase;background:var(--cp-fill);color:var(--cp-blue);
       border:1px solid rgba(138,180,255,.4);border-radius:10px;padding:10px 18px;cursor:pointer;width:225px;font-family:inherit;}
  .cpl-btn-gold{color:var(--cp-gold);border-color:rgba(255,225,53,.45);}
  .cpl-btn:disabled{opacity:.4;cursor:not-allowed;}
  .cpl-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;align-content:start;}
  .cpl-sec{background:var(--cp-inset);border:1px solid var(--cp-line);border-radius:16px;padding:18px 22px 20px;
       display:flex;flex-direction:column;gap:18px;min-width:0;}
  .cpl-sech{margin:0;font-family:'Anton',sans-serif;font-weight:400;font-size:18px;letter-spacing:.18em;text-transform:uppercase;
       color:var(--cp-gold);padding-bottom:10px;border-bottom:1px solid var(--cp-line);}
  .cpl-pair{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px 22px;align-items:start;}
  .cpl-foot{display:flex;align-items:center;gap:16px;flex-shrink:0;margin-top:auto;}
  .cpl-row{display:flex;flex-direction:column;gap:8px;min-width:0;}
  .cpl-cap{font-size:16px;letter-spacing:.16em;text-transform:uppercase;color:var(--cp-muted);}
  .cpl-in{font-size:24px;background:var(--cp-inset);border:1px solid var(--cp-line);border-radius:10px;color:var(--cp-text);
       padding:10px 14px;min-width:0;font-family:inherit;}
  .cpl-in:focus{outline:none;border-color:var(--cp-gold);}
  .cpl-in:disabled{opacity:.45;}
  .cpl-why{font-size:15px;color:var(--cp-faint);letter-spacing:.02em;}
  .cpl-why:empty{display:none;}
  .cpl-seg{font-size:16px;letter-spacing:.14em;text-transform:uppercase;background:var(--cp-fill-alt);color:var(--cp-muted);
       border:1px solid var(--cp-line);border-radius:999px;padding:5px 14px;cursor:pointer;font-family:inherit;}
  .cpl-seg[aria-pressed="true"]{background:var(--cp-gold);color:#000;border-color:transparent;}
  .cpl-seg:disabled{opacity:.4;cursor:not-allowed;}
  .cpl-save{font-family:'Anton',sans-serif;font-size:28px;letter-spacing:.12em;text-transform:uppercase;background:var(--cp-green);
       color:#000;border:none;border-radius:12px;padding:12px 40px;cursor:pointer;}
  .cpl-save:disabled{opacity:.35;cursor:not-allowed;}
  /* A dropdown whose list floats under its trigger. */
  .cpl-dd{display:flex;flex-direction:column;gap:6px;min-width:0;position:relative;}
  .cpl-ddbtn{display:flex;align-items:center;gap:12px;font-size:22px;background:var(--cp-inset);border:1px solid var(--cp-line);
       border-radius:10px;color:var(--cp-text);padding:9px 14px;cursor:pointer;text-align:left;min-width:0;font-family:inherit;}
  .cpl-ddbtn:hover:not(:disabled){border-color:var(--cp-gold);}
  .cpl-ddbtn:disabled{opacity:.45;cursor:not-allowed;}
  .cpl-ddbtn .chev{margin-left:auto;color:var(--cp-muted);transition:transform .15s ease;}
  .cpl-ddbtn[aria-expanded="true"] .chev{transform:rotate(180deg);}
  .cpl-ddpanel{position:absolute;left:0;right:0;top:calc(100% + 6px);z-index:30;background:var(--cp-ink);border:1px solid var(--cp-line-2);
       border-radius:12px;padding:8px;display:flex;flex-direction:column;gap:6px;box-shadow:0 18px 40px rgba(0,0,0,.6);}
  .cpl-dd.up .cpl-ddpanel{top:auto;bottom:calc(100% + 6px);}
  .cpl-ddlist{max-height:260px;overflow-y:auto;display:flex;flex-direction:column;gap:2px;}
  .cpl-ddopt{display:flex;align-items:center;gap:12px;font-size:20px;background:none;border:none;color:var(--cp-text);
       padding:8px 10px;border-radius:8px;cursor:pointer;text-align:left;font-family:inherit;}
  .cpl-ddopt:hover{background:var(--cp-fill);}
  .cpl-ddopt[aria-selected="true"]{color:var(--cp-gold);}
  .cpl-ico{width:30px;height:30px;flex:0 0 auto;background:center/contain no-repeat;}
  .cpl-belt{width:34px;height:34px;flex:0 0 auto;}
  /* The shared gender toggle and country picker are written in Tailwind
     classes; consoles load no Tailwind, so their classes get their meaning
     HERE, on this tab's palette, and nowhere else. */
  #cpGender{max-width:380px;}
  #cpGender.locked, .cpl-nat.locked{opacity:.45;pointer-events:none;}
  #cpGender .grid{display:grid;} #cpGender .grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr));}
  #cpGender .gap-3{gap:12px;} #cpGender .flex{display:flex;} #cpGender .items-center{align-items:center;}
  #cpGender .justify-center{justify-content:center;} #cpGender .gap-2{gap:10px;}
  #cpGender .py-3{padding-top:10px;padding-bottom:10px;} #cpGender .rounded-xl{border-radius:12px;}
  #cpGender .border-2{border-width:2px;border-style:solid;} #cpGender .font-semibold{font-weight:600;}
  #cpGender .text-sm{font-size:22px;letter-spacing:.04em;}
  #cpGender button{background:var(--cp-fill-alt);cursor:pointer;font-family:inherit;color:var(--cp-muted);}
  #cpGender .border-gray-200{border-color:var(--cp-line);} #cpGender .text-gray-600{color:var(--cp-muted);}
  #cpGender .border-blue-500{border-color:#5aa9ff;} #cpGender .bg-blue-50{background:rgba(90,169,255,.16);} #cpGender .text-blue-600{color:#5aa9ff;}
  #cpGender .border-pink-500{border-color:#ff6fae;} #cpGender .bg-pink-50{background:rgba(255,111,174,.16);} #cpGender .text-pink-600{color:#ff6fae;}
  #cpGender .bi-gender-male::before{content:'♂';} #cpGender .bi-gender-female::before{content:'♀';} #cpGender i{font-style:normal;}
  .cpl-nat [x-cloak]{display:none !important;}
  .cpl-nat .cpl-cap{display:block;margin-bottom:8px;}
  .cpl-nat .relative{position:relative;}
  .cpl-nat .cpl-country-trigger{width:100%;justify-content:space-between;}
  .cpl-nat .flex{display:flex;} .cpl-nat .items-center{align-items:center;} .cpl-nat .gap-2{gap:12px;}
  .cpl-nat .text-sm{font-size:22px;} .cpl-nat .text-gray-400{color:var(--cp-faint);}
  .cpl-nat .fi{width:30px;height:22px;border-radius:3px;border:1px solid rgba(255,255,255,.2);background-size:100% 100%;display:inline-block;flex:0 0 auto;}
  .cpl-nat .bi-chevron-down{font-style:normal;color:var(--cp-muted);transition:transform .15s ease;}
  .cpl-nat .bi-chevron-down::before{content:'⌄';}
  .cpl-nat .rotate-180{transform:rotate(180deg);}
  .cpl-nat .tf-dropdown-panel{position:absolute;left:0;right:0;z-index:30;background:var(--cp-ink);border:1px solid var(--cp-line-2);
       border-radius:12px;overflow:hidden;box-shadow:0 18px 40px rgba(0,0,0,.6);}
  .cpl-nat .top-full{top:calc(100% + 6px);} .cpl-nat .bottom-full{bottom:calc(100% + 6px);}
  .cpl-nat .p-2{padding:8px;} .cpl-nat .border-b{border-bottom:1px solid var(--cp-line);}
  .cpl-nat .tf-dropdown-panel input{width:100%;font-size:20px;background:var(--cp-inset);border:1px solid var(--cp-line);
       border-radius:10px;color:var(--cp-text);padding:9px 12px;outline:none;font-family:inherit;}
  .cpl-nat .tf-dropdown-panel input:focus{border-color:var(--cp-gold);}
  .cpl-nat .max-h-60{max-height:260px;} .cpl-nat .overflow-y-auto{overflow-y:auto;}
  .cpl-nat .tf-dropdown-item-sm{display:flex;align-items:center;gap:12px;font-size:20px;color:var(--cp-text);padding:8px 12px;cursor:pointer;}
  .cpl-nat .tf-dropdown-item-sm:hover{background:var(--cp-fill);}
  .cpl-nat .mr-2{margin-right:0;} .cpl-nat .px-4{padding-left:12px;padding-right:12px;}
  .cpl-nat .py-2{padding-top:8px;padding-bottom:8px;} .cpl-nat .text-gray-500{color:var(--cp-faint);}
  /* The tab's own pop-ups. They move themselves to <body> (a console's own
     pop-ups may sit inside or outside its scaled canvas) and are authored in
     the console's 1920x1080 pixels, scaled by --cp-k to the same fit. */
  .cpm{position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.76);display:flex;align-items:center;justify-content:center;}
  .cpm-box{background:var(--cp-panel);border:1px solid var(--cp-line-2);border-radius:20px;padding:26px 30px;flex:0 0 auto;
       display:flex;flex-direction:column;gap:18px;box-shadow:0 30px 80px rgba(0,0,0,.6);
       transform:scale(var(--cp-k,1));transform-origin:center;}
  .cpm-head{display:flex;align-items:center;justify-content:space-between;gap:18px;}
  .cpm-title{font-family:'Anton',sans-serif;font-size:26px;letter-spacing:.06em;text-transform:uppercase;color:var(--cp-gold);
       min-width:0;overflow-wrap:anywhere;}
  .cpm-close{width:48px;height:48px;border-radius:50%;flex:0 0 auto;background:var(--cp-fill);color:var(--cp-text);
       border:1px solid var(--cp-line-2);font-size:22px;cursor:pointer;}
  .cpm-note{font-size:17px;color:var(--cp-second);line-height:1.4;}
  .cpm-side{display:flex;flex-direction:column;gap:14px;min-width:260px;}
  .cpm-range{display:flex;flex-direction:column;gap:8px;background:var(--cp-inset);border:1px solid var(--cp-line);border-radius:12px;padding:14px 16px;}
  .cpm-range span{font-size:15px;letter-spacing:.14em;text-transform:uppercase;color:var(--cp-muted);display:flex;justify-content:space-between;}
  .cpm-range b{font-family:'Anton',sans-serif;font-weight:400;font-size:20px;color:var(--cp-gold);}
  .cpm-range input{width:100%;accent-color:#ffe135;}
  .cpm-ghost{font-size:16px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;border-radius:10px;padding:11px 16px;cursor:pointer;
       background:transparent;color:var(--cp-muted);border:1px solid var(--cp-line-2);font-family:inherit;}
  .cpm-go{font-size:18px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;border-radius:12px;padding:14px 18px;cursor:pointer;
       background:var(--cp-green);color:#000;border:none;font-family:inherit;}
  .cpm-go:disabled{opacity:.4;cursor:not-allowed;}
  .cpm-say{font-size:16px;color:var(--cp-muted);min-height:22px;}
  /* The cropper: a 3:4 frame (every profile picture is portrait 3:4). */
  .cpm-crop{position:relative;width:450px;height:600px;border-radius:14px;overflow:hidden;background:#05060a;
       border:2px solid var(--cp-line-2);touch-action:none;cursor:grab;user-select:none;}
  .cpm-crop img{position:absolute;left:0;top:0;transform-origin:0 0;max-width:none;pointer-events:none;}
  .cpm-crop .empty{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;text-align:center;padding:24px;
       color:var(--cp-faint);font-size:20px;letter-spacing:.06em;text-transform:uppercase;}
</style>

{{-- Alpine, SELF-HOSTED, for the shared country picker and gender toggle the
     pane uses — only when the console has not got it already. --}}
<script>
  if (!window.Alpine && !document.getElementById('cpAlpine')) {
    var cpA = document.createElement('script');
    cpA.id = 'cpAlpine'; cpA.defer = true; cpA.src = @json(asset('vendor/alpinejs/3.17.4/alpine.min.js'));
    document.head.appendChild(cpA);
  }
</script>
{{-- The belt drawing, once, and the vendored flag sheet for the country picker. --}}
<x-belt-icon-defs />
<link rel="stylesheet" href="{{ asset('images/flags/flag-icons.css') }}">

{{-- ── Change photo: the tab's own cropper ──────────────────────────────── --}}
<div id="cpCrop" class="cpm" hidden>
  <div class="cpm-box">
    <div class="cpm-head">
      <div id="cpCropTitle" class="cpm-title">{{ __('scoreboard::players.crop_title') }}</div>
      <button type="button" id="cpCropClose" class="cpm-close" aria-label="✕">✕</button>
    </div>
    <div style="display:flex;gap:26px;align-items:flex-start;">
      <div id="cpCropFrame" class="cpm-crop">
        <img id="cpCropImg" alt="" hidden>
        <div id="cpCropEmpty" class="empty">{{ __('scoreboard::players.crop_empty') }}</div>
      </div>
      <div class="cpm-side">
        <div class="cpm-note">{{ __('scoreboard::players.crop_drag') }}</div>
        <label class="cpm-range">
          <span>{{ __('scoreboard::players.crop_zoom') }} <b id="cpCropZoomVal">100%</b></span>
          <input id="cpCropZoom" type="range" min="1" max="4" step="0.01" value="1">
        </label>
        <input id="cpCropFile" type="file" accept="image/jpeg,image/png,image/webp" hidden>
        <button type="button" id="cpCropPick" class="cpm-ghost">{{ __('scoreboard::players.crop_upload') }}</button>
        <button type="button" id="cpCropSave" class="cpm-go" disabled>{{ __('scoreboard::players.crop_save') }}</button>
        <div id="cpCropSay" class="cpm-say"></div>
      </div>
    </div>
  </div>
</div>

{{-- ── Adjust for VS screen: moved and zoomed over the ORIGINAL, never cut ─ --}}
<div id="cpVf" class="cpm" hidden>
  <div class="cpm-box" style="width:1420px;">
    <div class="cpm-head">
      <div id="cpVfTitle" class="cpm-title"></div>
      <button type="button" id="cpVfClose" class="cpm-close" aria-label="✕">✕</button>
    </div>
    <div class="cpm-note">{{ __('scoreboard::players.vf_hint') }}</div>
    <div style="display:grid;grid-template-columns:auto minmax(0,1fr);gap:26px;align-items:start;">
      <div style="position:relative;">
        <iframe id="cpVfLive" title="VS" style="display:block;width:1000px;height:563px;border:1px solid var(--cp-line-2);border-radius:10px;background:#050507;"></iframe>
        <div id="cpVfDrag" style="position:absolute;inset:0;cursor:grab;touch-action:none;user-select:none;"></div>
      </div>
      <div class="cpm-side">
        <label class="cpm-range">
          <span>{{ __('scoreboard::players.vf_zoom') }} <b id="cpVfZoomVal"></b></span>
          <input id="cpVfZoom" type="range" min="1" max="3" step="0.01" value="1">
        </label>
        <button type="button" id="cpVfReset" class="cpm-ghost">{{ __('scoreboard::players.reset') }}</button>
        <button type="button" id="cpVfSave" class="cpm-go">{{ __('scoreboard::players.vf_save') }}</button>
        <div id="cpVfSay" class="cpm-say"></div>
      </div>
    </div>
  </div>
</div>

@php
  $cpWords = collect(trans('scoreboard::players'))->only([
      'pl_none', 'reading', 'read_failed', 'pl_saving', 'pl_unattached', 'pl_bad_dob', 'pl_no_change', 'pl_search',
      'pl_nat_guess', 'pl_vs_btn', 'unsaved', 'failed', 'crop_title',
      'pl_wc_none', 'pl_wc_add', 'pl_wc_move', 'pl_wc_remove', 'pl_wc_remove_confirm', 'pl_wc_remove_hint', 'pl_wc_cancel',
      'pl_wc_add_title', 'pl_wc_move_title', 'pl_wc_find', 'pl_wc_find_none', 'pl_wc_here', 'pl_wc_fits', 'pl_wc_outside',
      'pl_wc_outside_say', 'pl_wc_add_go', 'pl_wc_move_go', 'pl_wc_add_say', 'pl_wc_move_say', 'pl_wc_added', 'pl_wc_moved',
      'pl_wc_removed', 'pl_wc_failed',
  ])->all();
@endphp
<script>
window.ConsolePlayers = window.ConsolePlayers || (function () {
  'use strict';

  var CFG = @json($players);
  var T = @json($cpWords);
  var FLAG_DIR = @json(asset(\App\Support\Flag::DIR));
  var LIST_URL = CFG.list;
  var MAT = CFG.mat || null;
  var CLASS_DOORS = CFG.classDoors || null;
  var DRAW_URL = CFG.drawUrl || null;

  function el(id) { return document.getElementById(id); }
  function text(id, v) { var e = el(id); if (e) e.textContent = v; }
  function fold(v) { return (v == null ? '' : String(v)).toLowerCase(); }
  function node(tag, cls, txt) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (txt != null) n.textContent = txt;
    return n;
  }
  function empty(host, message) { host.textContent = ''; host.appendChild(node('div', 'cpl-nothing', message)); }
  function csrf() { var m = document.querySelector('meta[name=csrf-token]'); return m ? m.content : ''; }
  function getJson(url) {
    return fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; });
  }
  function send(method, url, body) {
    return fetch(url, {
      method: method,
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) {
        if (!r.ok || !d.success) {
          var msg = d.message || T.failed;
          if (d.errors) { var f = Object.keys(d.errors)[0]; if (f) msg = d.errors[f][0]; }
          throw new Error(msg);
        }
        return d;
      });
    });
  }
  function say(id, msg, tone) {
    var s = el(id);
    if (!s) return;
    s.textContent = msg || '';
    s.style.color = tone === 'ok' ? 'var(--cp-green)' : (tone === 'bad' ? 'var(--cp-red)' : '');
  }
  function changed(p) {
    try { window.dispatchEvent(new CustomEvent('console-players:changed', { detail: { player: p || null } })); } catch (e) {}
  }

  /* ── One person, as a list row draws them ──────────────────────────── */
  function cell(p, colour) {
    var wrap = node('span', 'cpl-cell');
    var face = node('span', 'cpl-face');
    face.style.borderColor = colour;
    face.style.color = colour;
    var pic = p.photo || p.fallback;
    if (pic) {
      face.style.backgroundImage = 'url("' + pic + '")';
      if (!p.photo) face.style.opacity = '.72';
    } else {
      face.textContent = (p.name || '').split(/\s+/).filter(Boolean).slice(0, 2)
        .map(function (w) { return w.charAt(0).toUpperCase(); }).join('') || '—';
    }
    wrap.appendChild(face);
    var who = node('span', 'cpl-who');
    who.appendChild(node('b', null, p.name || '—'));
    var sub = node('small');
    if (p.flag && /^[a-z]{2}$/.test(p.flag)) {
      var f = node('span', 'cpl-flag');
      f.style.backgroundImage = 'url("' + FLAG_DIR + '/' + p.flag + '.svg")';
      sub.appendChild(f);
    }
    sub.appendChild(document.createTextNode(p.club || ''));
    who.appendChild(sub);
    wrap.appendChild(who);
    return wrap;
  }

  /* ── Players ─────────────────────────────────────────────────────────
     Read once per opening, searched locally; one person opens beside the
     list. Saving sends ONLY what changed — absent is never blank. */
  var PL = null, plState = 'idle', plOpen = null, plData = null, plDraft = null;

  function load(force) {
    if (!LIST_URL) return;
    if (plState === 'loading' || (plState === 'ready' && !force)) { paintList(); return; }
    plState = 'loading';
    paintList();
    getJson(LIST_URL)
      .then(function (j) {
        if (!j || !j.players) { plState = 'failed'; paintList(); return; }
        PL = j.players; plState = 'ready'; paintList();
      })
      .catch(function () { plState = 'failed'; paintList(); });
  }

  function facts(p) {
    var bits = [];
    if (!(p.classes && p.classes.length) && p.divisions && p.divisions.length) bits.push(p.divisions.join(' · '));
    if (p.age) bits.push(p.age + 'y');
    if (p.weight) bits.push((Math.round(p.weight * 10) / 10) + ' kg');
    return bits.join('  ·  ');
  }

  // A gold chip per weight class; on the sheet the division's name beside it.
  function chips(p, full) {
    var box = node('span', 'cpl-classes');
    (p.classes || []).forEach(function (c) {
      var chip = document.createElement(full && DRAW_URL ? 'button' : 'span');
      chip.className = 'cpl-class' + (DRAW_URL ? ' go' : '');
      chip.title = c.name;
      if (DRAW_URL && c.id) {
        if (full) chip.type = 'button';
        chip.onclick = function (e) {
          e.stopPropagation();
          window.location.href = DRAW_URL + (DRAW_URL.indexOf('?') === -1 ? '?' : '&') + 'category=' + encodeURIComponent(c.id);
        };
      }
      chip.appendChild(node('b', null, c.weight || c.name));
      if (full && c.weight) chip.appendChild(node('span', null, c.name));
      if (DRAW_URL && c.id) chip.appendChild(document.createTextNode('↗'));
      box.appendChild(chip);
    });
    return box;
  }

  function paintList() {
    var host = el('cpList');
    if (!host) return;
    host.textContent = '';
    if (plState !== 'ready') { empty(host, plState === 'failed' ? T.read_failed : T.reading); return; }

    var q = fold(el('cpFind') ? el('cpFind').value : '').trim();
    var rows = PL.filter(function (p) {
      return !q || fold(p.name).indexOf(q) !== -1 || fold(p.club).indexOf(q) !== -1
        || fold((p.divisions || []).join(' ')).indexOf(q) !== -1
        || fold((p.classes || []).map(function (c) { return c.name; }).join(' ')).indexOf(q) !== -1;
    });
    if (!rows.length) { empty(host, T.pl_none); return; }

    rows.forEach(function (p) {
      var row = node('button', 'cpl-rowbtn');
      row.type = 'button';
      var colour = p.gender === 'Female' ? '#ff6fae' : (p.gender === 'Male' ? '#5aa9ff' : '#7d8296');
      row.appendChild(cell(p, colour));
      if (p.classes && p.classes.length) row.appendChild(chips(p, false));
      row.appendChild(node('span', 'cpl-facts', facts(p)));
      row.setAttribute('aria-current', p.registration === plOpen ? 'true' : 'false');
      row.setAttribute('data-reg', p.registration);
      row.onclick = function () { openPlayer(p.registration); };
      host.appendChild(row);
    });
  }

  /* ── A player's weight classes ───────────────────────────────────────
     Add and Move open a picker listing every division with whether this
     person fits it — judged by the server, never here. Writes go through the
     platform's own doors (division members, LiveMove). Signed-in only. */
  var WC_PERSON = '00000000-0000-4000-8000-000000000000', WC_DIVISION = '987654321';
  var wcArm = null, wcPick = null, wcBusy = false;

  function xbtn(cls, label, fn) {
    var b = node('button', 'cpl-xbtn' + (cls ? ' ' + cls : ''), label);
    b.type = 'button';
    b.onclick = fn;
    return b;
  }
  function wcWeight(s) {
    var m = String(s || '').match(/[+\-−]\s?\d+(?:\.\d+)?\s?kg/i);
    return m ? m[0] : null;
  }

  function paintClasses(p) {
    var host = el('cpClasses');
    host.hidden = false;
    host.textContent = '';
    (p.classes || []).forEach(function (c) {
      var armed = wcArm === c.registration;
      var card = node('div', 'cpl-wccard' + (armed ? ' arm' : '') + (wcBusy ? ' busy' : ''));
      card.appendChild(node('span', 'w', c.weight || '—'));
      var n = node('span', 'n', c.name);
      if (armed) n.appendChild(node('small', null, T.pl_wc_remove_hint));
      card.appendChild(n);
      var acts = node('span', 'acts');
      if (armed) {
        acts.appendChild(xbtn('ghost', T.pl_wc_cancel, function () { wcArm = null; paintEditor(); }));
        acts.appendChild(xbtn('danger arm', T.pl_wc_remove_confirm, function () { wcRemove(p, c); }));
      } else {
        if (CLASS_DOORS.move) acts.appendChild(xbtn('', T.pl_wc_move, function () { wcOpen(p, c); }));
        acts.appendChild(xbtn('danger', T.pl_wc_remove, function () { wcArm = c.registration; paintEditor(); }));
      }
      card.appendChild(acts);
      host.appendChild(card);
    });
    if (!(p.classes || []).length) host.appendChild(node('span', 'cpl-wcnone', T.pl_wc_none));
    var add = node('button', 'cpl-wcadd');
    add.type = 'button';
    add.disabled = wcBusy;
    add.appendChild(node('span', 'plus', '+'));
    add.appendChild(document.createTextNode(T.pl_wc_add));
    add.onclick = function () { wcOpen(p, null); };
    host.appendChild(add);
  }

  function wcOpen(p, from) {
    wcArm = null;
    wcPick = { p: p, from: from, groups: null, entry: null, note: '', chosen: null, state: 'loading' };
    wcPaint();
    getJson(CLASS_DOORS.person.replace(WC_PERSON, encodeURIComponent(p.uuid)))
      .then(function (j) {
        if (!wcPick || wcPick.p !== p) return;
        if (!j || !j.groups) { wcPick.state = 'failed'; wcPaint(); return; }
        wcPick.groups = j.groups; wcPick.entry = j.entry; wcPick.note = j.note || ''; wcPick.state = 'ready';
        wcPaint();
      })
      .catch(function () { if (wcPick && wcPick.p === p) { wcPick.state = 'failed'; wcPaint(); } });
  }

  function wcClose() {
    wcPick = null;
    var h = el('cpClassPick');
    if (!h) return;
    h.hidden = true;
    h.textContent = '';
  }

  function wcPaint() {
    var host = el('cpClassPick');
    if (!wcPick) { wcClose(); return; }
    var k = wcPick;
    host.hidden = false;
    host.textContent = '';

    var head = node('div', 'head');
    var h = node('h4', null, k.from ? T.pl_wc_move_title.replace(':weight', k.from.weight || k.from.name) : T.pl_wc_add_title);
    var who = [k.p.name];
    if (k.p.weight) who.push((Math.round(k.p.weight * 10) / 10) + ' kg');
    if (k.p.age) who.push(k.p.age + 'y');
    h.appendChild(node('small', null, who.join('  ·  ')));
    head.appendChild(h);
    head.appendChild(xbtn('ghost', T.pl_wc_cancel, wcClose));
    host.appendChild(head);

    var find = document.createElement('input');
    find.type = 'search';
    find.className = 'cpl-find';
    find.autocomplete = 'off';
    find.spellcheck = false;
    find.placeholder = T.pl_wc_find;
    host.appendChild(find);

    var list = node('div', 'cpl-wcopts');
    host.appendChild(list);

    var foot = node('div', 'foot');
    var msg = node('div', 'msg', k.note);
    msg.id = 'cpWcSay';
    foot.appendChild(msg);
    var go = xbtn('primary', k.from ? T.pl_wc_move_go : T.pl_wc_add_go, wcCommit);
    go.id = 'cpWcGo';
    go.disabled = true;
    foot.appendChild(go);
    host.appendChild(foot);

    if (k.state !== 'ready') { empty(list, k.state === 'failed' ? T.read_failed : T.reading); return; }
    find.addEventListener('input', function () { wcFill(list, find.value); });
    wcFill(list, '');
    setTimeout(function () { try { find.focus(); } catch (e) {} }, 0);
  }

  function wcFill(list, q) {
    var k = wcPick;
    list.textContent = '';
    q = fold(q).trim();
    var rank = function (g) { return g.enrolled ? 2 : (g.compatible ? 0 : 1); };
    var rows = k.groups.filter(function (g) {
      return !q || fold(g.label).indexOf(q) !== -1 || fold(g.detail || '').indexOf(q) !== -1;
    }).map(function (g, i) { return [g, i]; }).sort(function (a, b) {
      return rank(a[0]) - rank(b[0]) || a[1] - b[1];
    });
    if (!rows.length) { empty(list, T.pl_wc_find_none); return; }

    rows.forEach(function (x) {
      var g = x[0];
      var b = node('button', 'cpl-wcopt' + (g.enrolled ? ' here' : (g.compatible ? '' : ' out')));
      b.type = 'button';
      b.disabled = g.enrolled || wcBusy;
      b.setAttribute('aria-pressed', k.chosen === g.id ? 'true' : 'false');
      b.appendChild(node('span', 'tag', g.enrolled ? T.pl_wc_here : (g.compatible ? T.pl_wc_fits : T.pl_wc_outside)));
      var w = wcWeight(g.label) || wcWeight(g.detail);
      if (w) b.appendChild(node('span', 'w', w));
      b.appendChild(node('span', 'nm', g.label));
      if (g.detail) b.appendChild(node('span', 'dt', g.detail));
      if (g.reason && !g.enrolled) b.appendChild(node('span', 'why', g.reason));
      b.onclick = function () {
        k.chosen = g.id;
        list.querySelectorAll('.cpl-wcopt').forEach(function (o2) { o2.setAttribute('aria-pressed', 'false'); });
        b.setAttribute('aria-pressed', 'true');
        wcSayChoice();
      };
      list.appendChild(b);
    });
  }

  function wcChosen() {
    var k = wcPick;
    return k && k.groups ? k.groups.filter(function (g) { return g.id === k.chosen; })[0] : null;
  }

  function wcSayChoice() {
    var k = wcPick, g = wcChosen(), s = el('cpWcSay');
    if (!g) return;
    var line = (k.from ? T.pl_wc_move_say.replace(':from', k.from.name) : T.pl_wc_add_say)
      .replace(':who', k.p.name).replace(':to', g.label);
    if (!g.compatible && g.reason) line += ' ' + T.pl_wc_outside_say.replace(':why', g.reason);
    if (k.note) line += ' ' + k.note;
    s.style.color = g.compatible ? '' : 'var(--cp-gold)';
    s.textContent = line;
    el('cpWcGo').disabled = wcBusy;
  }

  function wcCommit() {
    var k = wcPick, g = wcChosen();
    if (!k || !g || wcBusy) return;
    wcBusy = true;
    el('cpWcGo').disabled = true;
    say('cpWcSay', T.pl_saving);
    var req = k.from
      ? send('POST', CLASS_DOORS.move, { entry: k.from.registration, to: g.id })
      : send('PUT', CLASS_DOORS.members.replace(WC_DIVISION, g.id), { add: [k.entry] });
    req.then(function () {
      wcBusy = false;
      wcClose();
      wcDone(k.p.uuid, (k.from ? T.pl_wc_moved : T.pl_wc_added).replace(':name', g.label));
    }).catch(function (err) {
      wcBusy = false;
      if (wcPick !== k) return;
      say('cpWcSay', err.message, 'bad');
      el('cpWcGo').disabled = false;
    });
  }

  function wcRemove(p, c) {
    if (wcBusy) return;
    wcBusy = true;
    paintEditor();
    send('PUT', CLASS_DOORS.members.replace(WC_DIVISION, c.id), { remove: [c.registration] })
      .then(function () {
        wcBusy = false;
        wcArm = null;
        wcDone(p.uuid, T.pl_wc_removed.replace(':name', c.name));
      })
      .catch(function (err) {
        wcBusy = false;
        paintEditor();
        say('cpSay', err.message, 'bad');
      });
  }

  // Re-read the list (the rows behind the classes moved), then repaint this person.
  function wcDone(uuid, message) {
    changed(null);
    getJson(LIST_URL)
      .then(function (j) {
        if (j && j.players) PL = j.players;
        paintList();
        var me = (PL || []).filter(function (x) { return x.uuid === uuid; })[0];
        if (me && me.registration !== plOpen) openPlayer(me.registration);
        else if (plData) paintEditor();
        say('cpSay', message, 'ok');
      })
      .catch(function () { paintList(); });
  }

  function openPlayer(id) {
    plOpen = id;
    ddOpen = null;
    wcClose();
    wcArm = null;
    el('cpClasses').hidden = true;
    el('cpEmpty').hidden = true;
    el('cpEdit').hidden = false;
    document.querySelectorAll('#cpList .cpl-rowbtn').forEach(function (r) { r.setAttribute('aria-current', 'false'); });
    var hit = document.querySelector('#cpList .cpl-rowbtn[data-reg="' + id + '"]');
    if (hit) hit.setAttribute('aria-current', 'true');
    text('cpTitle', T.reading);
    text('cpSay', '');
    el('cpSave').disabled = true;

    getJson(LIST_URL + '/' + id)
      .then(function (j) {
        if (!j || !j.player || plOpen !== id) { if (plOpen === id) text('cpTitle', T.read_failed); return; }
        plData = j;
        plDraft = initial(j.player);
        text('cpNatNote', '');
        paintEditor();
        seedNationality(id);
      })
      .catch(function () { if (plOpen === id) text('cpTitle', T.read_failed); });
  }

  function initial(p) {
    var d = (p.birthdate || '').split('-');
    return { name: p.name || '', club: p.club_key || '', dobY: d[0] || '', dobM: d[1] || '', dobD: d[2] || '',
             age: p.declared_age != null ? String(p.declared_age) : '', gender: p.gender || '',
             nat: p.nationality || '',
             mode: p.birthdate ? 'dob' : (p.declared_age != null ? 'age' : 'dob'),
             weight: p.weight != null ? String(Math.round(p.weight * 100) / 100) : '',
             belt_colour: p.belt_colour || '', belt_grade: p.belt_grade || '' };
  }

  function editable(f) { return !!(plData && plData.fields[f] && plData.fields[f].editable); }

  function paintEditor() {
    var j = plData, p = j.player, d = plDraft;
    text('cpTitle', p.name || '');
    var listed = (PL || []).filter(function (x) { return x.registration === plOpen; })[0];
    if (CLASS_DOORS && listed && listed.uuid) {
      paintClasses(listed);
    } else if (listed && listed.classes && listed.classes.length) {
      var cls = chips(listed, true);
      cls.style.marginTop = '8px';
      el('cpTitle').appendChild(cls);
    }

    var ph = el('cpPhoto');
    ph.style.backgroundImage = 'url("' + (p.photo || p.fallback) + '")';
    ph.style.opacity = p.photo ? '1' : '.6';
    ph.disabled = el('cpPhotoBtn').disabled = !editable('photo');
    // Framing for the VS screen: only when there is a photo the VS screen shows,
    // only for whoever may change it, and only where the preview can be drawn.
    el('cpVsBtn').hidden = !(editable('photo') && p.vs_photo && CFG.vsPreview);

    ['photo', 'name', 'club', 'nationality', 'birthdate', 'gender', 'weight', 'belt_colour'].forEach(function (f) {
      text('cpWhy-' + f, j.fields[f] && !j.fields[f].editable ? (j.fields[f].reason || '') : '');
    });

    var nm = el('cpName'); nm.value = d.name; nm.disabled = !editable('name');

    dropdown('cpClub', [{ value: '', label: T.pl_unattached, logo: null }].concat((j.clubs || []).map(function (c) {
      return { value: c.slug, label: c.name, logo: c.logo };
    })), d.club, !editable('club'), false, function (v) { d.club = v; paintEditor(); });

    window.dispatchEvent(new CustomEvent('cp-nat-set', { detail: d.nat }));
    el('cpNatWrap').classList.toggle('locked', !editable('nationality'));

    var dobOn = editable('birthdate');
    document.querySelectorAll('#cpEdit .cpl-seg').forEach(function (b) {
      b.setAttribute('aria-pressed', d.mode === b.getAttribute('data-mode') ? 'true' : 'false');
      b.disabled = !dobOn;
    });
    el('cpDobBox').hidden = d.mode !== 'dob';
    el('cpAgeBox').hidden = d.mode !== 'age';
    [['cpDobD', 'dobD'], ['cpDobM', 'dobM'], ['cpDobY', 'dobY']].forEach(function (x) {
      el(x[0]).value = d[x[1]]; el(x[0]).disabled = !dobOn;
    });
    el('cpAge').value = d.age;
    el('cpAge').disabled = !dobOn;

    window.dispatchEvent(new CustomEvent('cp-gender-set', { detail: d.gender }));
    el('cpGender').classList.toggle('locked', !editable('gender'));

    var w = el('cpWeight'); w.value = d.weight; w.disabled = !editable('weight');

    // Belt — each belt of this event's system and each of its degrees, drawn as the belt.
    var beltOpts = [{ value: '|', label: '—', belt: null }];
    (j.belts || []).forEach(function (r) {
      var grades = r.grades || [];
      if (!grades.length) { beltOpts.push({ value: r.value + '|', label: r.label, belt: r.icon || null }); return; }
      grades.forEach(function (g) { beltOpts.push({ value: r.value + '|' + g, label: r.label + ' · ' + g, belt: r.icon || null }); });
    });
    var curBelt = d.belt_colour + '|' + d.belt_grade;
    if (d.belt_colour && !beltOpts.some(function (b) { return b.value === curBelt; })) {
      var known = (j.belts || []).filter(function (r) { return r.value === d.belt_colour; })[0];
      beltOpts.push({ value: curBelt, label: (known ? known.label : d.belt_colour) + (d.belt_grade ? ' · ' + d.belt_grade : ''),
                      belt: known ? known.icon : null });
    }
    dropdown('cpBelt', beltOpts, curBelt, !editable('belt_colour'), true, function (v) {
      var parts = v.split('|');
      d.belt_colour = parts[0] || '';
      d.belt_grade = parts.slice(1).join('|') || '';
      paintEditor();
    });

    el('cpSave').disabled = !Object.keys(changes() || {}).length;
  }

  /* A dropdown that opens under its trigger; crest, flag or belt beside each option. */
  var ddOpen = null;

  function ddIcon(opt) {
    if (opt.belt !== undefined) {
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('class', 'cpl-belt');
      svg.setAttribute('viewBox', '0 0 24 24');
      if (opt.belt) {
        svg.setAttribute('style', opt.belt);
        var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        use.setAttribute('href', '#belt-icon');
        svg.appendChild(use);
      } else { svg.style.visibility = 'hidden'; }
      return svg;
    }
    var i = node('span', 'cpl-ico');
    if (opt.logo) { i.style.backgroundImage = 'url("' + opt.logo + '")'; }
    else if (opt.value) {
      i.textContent = (opt.label || '?').trim().charAt(0).toUpperCase();
      i.style.cssText += "border-radius:50%;background:var(--cp-fill);display:flex;align-items:center;justify-content:center;font-family:'Anton',sans-serif;font-size:16px;color:var(--cp-gold);";
    } else { i.style.visibility = 'hidden'; }
    return i;
  }

  function dropdown(hostId, options, value, disabled, searchable, onPick) {
    var host = el(hostId);
    if (!host) return;
    var keepQuery = host.querySelector('input') ? host.querySelector('input').value : '';
    host.textContent = '';

    var cur = options.filter(function (o2) { return o2.value === value; })[0] || options[0];
    var btn = node('button', 'cpl-ddbtn');
    btn.type = 'button';
    btn.disabled = disabled;
    btn.setAttribute('aria-expanded', ddOpen === hostId ? 'true' : 'false');
    btn.appendChild(ddIcon(cur));
    var lab = node('span', null, cur.label);
    lab.style.cssText = 'min-width:0;overflow-wrap:anywhere;';
    btn.appendChild(lab);
    btn.appendChild(node('span', 'chev', '⌄'));
    btn.onclick = function () { ddOpen = ddOpen === hostId ? null : hostId; paintEditor(); };
    host.appendChild(btn);

    if (ddOpen !== hostId || disabled) return;

    var panel = node('div', 'cpl-ddpanel');
    var list = node('div', 'cpl-ddlist');

    function fill(q) {
      list.textContent = '';
      q = fold(q).trim();
      options.filter(function (o2) { return !q || fold(o2.label).indexOf(q) !== -1; }).slice(0, 300).forEach(function (o2) {
        var b = node('button', 'cpl-ddopt');
        b.type = 'button';
        b.setAttribute('aria-selected', o2.value === value ? 'true' : 'false');
        b.appendChild(ddIcon(o2));
        b.appendChild(node('span', null, o2.label));
        b.onclick = function () { ddOpen = null; onPick(o2.value); };
        list.appendChild(b);
      });
    }

    if (searchable) {
      var q = node('input', 'cpl-in');
      q.type = 'search';
      q.style.fontSize = '20px';
      q.autocomplete = 'off';
      q.value = keepQuery;
      q.placeholder = T.pl_search;
      q.addEventListener('input', function () { fill(q.value); });
      panel.appendChild(q);
      setTimeout(function () { try { q.focus(); } catch (e) {} }, 0);
    }

    panel.appendChild(list);
    host.appendChild(panel);
    fill(keepQuery);

    // Near the bottom of the sheet the list opens upwards instead.
    var frame = host.closest('.cpl-edit');
    host.classList.remove('up');
    if (frame && panel.getBoundingClientRect().bottom > frame.getBoundingClientRect().bottom - 16) host.classList.add('up');

    var sel = list.querySelector('[aria-selected="true"]');
    if (sel) sel.scrollIntoView({ block: 'nearest' });
  }

  /* A blank nationality is seeded from where this device is — only ever a
     blank, never one on file, and nothing is stored until Save. Remembered
     for a week so it is asked once, not per player. */
  function geoCountry() {
    return new Promise(function (resolve) {
      try {
        var raw = JSON.parse(localStorage.getItem('takeoneCountry') || 'null');
        if (raw && raw.code && (Date.now() - raw.at) < 7 * 864e5) return resolve(raw.code);
      } catch (e) { /* storage blocked — ask */ }
      if (!CFG.geo || !navigator.geolocation) return resolve(null);
      navigator.geolocation.getCurrentPosition(function (pos) {
        getJson(CFG.geo + '?lat=' + encodeURIComponent(pos.coords.latitude) + '&lng=' + encodeURIComponent(pos.coords.longitude))
          .then(function (d) {
            var code = d && d.country ? String(d.country).toUpperCase() : null;
            if (code) { try { localStorage.setItem('takeoneCountry', JSON.stringify({ code: code, at: Date.now() })); } catch (e) {} }
            resolve(code);
          })
          .catch(function () { resolve(null); });
      }, function () { resolve(null); }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 600000 });
    });
  }

  function seedNationality(id) {
    if (!plData || plData.player.nationality || !editable('nationality')) return;
    geoCountry().then(function (code) {
      if (!code || plOpen !== id || !plDraft || plDraft.nat) return;
      plDraft.nat = code;
      window.dispatchEvent(new CustomEvent('cp-nat-set', { detail: code }));
      text('cpNatNote', T.pl_nat_guess);
      el('cpSave').disabled = !Object.keys(changes() || {}).length;
    });
  }

  function changes() {
    if (!plData) return null;
    var a = initial(plData.player), d = plDraft, out = {};
    if (d.name.trim() !== a.name) out.name = d.name.trim();
    if (d.club !== a.club) out.club = d.club || null;
    var hadDob = !!a.dobY;
    if (d.mode === 'dob') {
      if (d.dobY + d.dobM + d.dobD !== a.dobY + a.dobM + a.dobD) {
        out.birthdate = (d.dobY || d.dobM || d.dobD)
          ? d.dobY + '-' + ('0' + d.dobM).slice(-2) + '-' + ('0' + d.dobD).slice(-2) : null;
      }
    } else {
      if (hadDob) out.birthdate = null;
      if (d.age !== a.age || hadDob) out.age = d.age === '' ? null : parseInt(d.age, 10);
    }
    if (d.nat !== a.nat) out.nationality = d.nat || null;
    if (d.gender !== a.gender) out.gender = d.gender || null;
    if (d.weight !== a.weight) out.weight = d.weight === '' ? null : parseFloat(d.weight);
    if (d.belt_colour !== a.belt_colour) out.belt_colour = d.belt_colour || null;
    if (d.belt_grade !== a.belt_grade) out.belt_grade = d.belt_grade || null;
    return out;
  }

  function saveBody(body, done) {
    say('cpSay', T.pl_saving);
    el('cpSave').disabled = true;
    return send('PUT', LIST_URL + '/' + plOpen, body)
      .then(function (d) {
        say('cpSay', d.message || '', 'ok');
        if (PL && d.player) {
          PL = PL.map(function (p) { return p.registration === d.player.registration ? Object.assign({}, p, d.player) : p; });
        }
        changed(d.player);
        paintList();
        if (done) done(d);
        openPlayer(plOpen);
      })
      .catch(function (err) {
        say('cpSay', err.message, 'bad');
        el('cpSave').disabled = false;
        if (done) done(null, err);
      });
  }

  function save() {
    var c = changes();
    if (!c || !Object.keys(c).length) { text('cpSay', T.pl_no_change); return; }
    if ('birthdate' in c && c.birthdate && !/^\d{4}-\d{2}-\d{2}$/.test(c.birthdate)) { say('cpSay', T.pl_bad_dob, 'bad'); return; }
    saveBody(c);
  }

  /* ── Change photo: the tab's own cropper ──────────────────────────────
     A 3:4 frame (every profile picture is portrait 3:4). The picture is
     dragged and zoomed under the frame; Save cuts exactly what the frame
     shows at 600x800 from the ORIGINAL pixels, never an upscale. */
  var crop = null;

  function cropOpen() {
    if (!editable('photo')) return;
    var p = plData.player;
    crop = { id: plOpen, img: null, url: null, base: 1, z: 1, x: 0, y: 0 };
    text('cpCropTitle', T.crop_title + ' — ' + (p.name || ''));
    el('cpCropTitle').style.color = p.gender === 'Female' ? '#ff6fae' : '#5aa9ff';
    el('cpCropZoom').value = 1;
    el('cpCropFile').value = '';
    say('cpCropSay', '');
    cropPaint();
    fitPopups();
    el('cpCrop').hidden = false;
    // Open on the photo they already have, so it can just be re-framed.
    if (p.photo) {
      fetch(p.photo, { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.blob() : null; })
        .then(function (b) { if (b && /^image\//.test(b.type) && crop && crop.id === plOpen && !crop.img) cropLoad(b); })
        .catch(function () {});
    }
  }

  function cropLoad(blob) {
    if (!crop) return;
    if (crop.url) URL.revokeObjectURL(crop.url);
    var url = URL.createObjectURL(blob), img = new Image();
    img.onload = function () {
      if (!crop) { URL.revokeObjectURL(url); return; }
      crop.img = img; crop.url = url;
      crop.base = Math.max(450 / img.naturalWidth, 600 / img.naturalHeight);
      crop.z = 1; crop.x = 0; crop.y = 0;
      el('cpCropZoom').value = 1;
      cropPaint();
    };
    img.onerror = function () { URL.revokeObjectURL(url); say('cpCropSay', T.failed, 'bad'); };
    img.src = url;
  }

  function cropClamp() {
    var s = crop.base * crop.z, w = crop.img.naturalWidth * s, h = crop.img.naturalHeight * s;
    var sx = Math.max(0, (w - 450) / 2), sy = Math.max(0, (h - 600) / 2);
    crop.x = Math.max(-sx, Math.min(sx, crop.x));
    crop.y = Math.max(-sy, Math.min(sy, crop.y));
    return { s: s, w: w, h: h };
  }

  function cropPaint() {
    var im = el('cpCropImg'), has = !!(crop && crop.img);
    im.hidden = !has;
    el('cpCropEmpty').hidden = has;
    el('cpCropSave').disabled = !has;
    el('cpCropZoomVal').textContent = Math.round((crop ? crop.z : 1) * 100) + '%';
    if (!has) return;
    var m = cropClamp();
    im.src = crop.url;
    im.style.width = m.w + 'px';
    im.style.height = m.h + 'px';
    im.style.left = (225 + crop.x - m.w / 2) + 'px';
    im.style.top = (300 + crop.y - m.h / 2) + 'px';
  }

  function cropClose() {
    el('cpCrop').hidden = true;
    if (crop && crop.url) URL.revokeObjectURL(crop.url);
    crop = null;
  }

  function cropSave() {
    if (!crop || !crop.img) return;
    var m = cropClamp();
    // The frame's top-left in the image's own pixels, and its size there.
    var left = (m.w / 2 - 225 - crop.x) / m.s, top = (m.h / 2 - 300 - crop.y) / m.s;
    var cw = 450 / m.s, ch = 600 / m.s;
    var c = document.createElement('canvas');
    c.width = 600; c.height = 800;
    var g = c.getContext('2d');
    g.imageSmoothingQuality = 'high';
    g.drawImage(crop.img, left, top, cw, ch, 0, 0, 600, 800);
    var dataUrl = c.toDataURL('image/jpeg', 0.92);
    el('cpCropSave').disabled = true;
    say('cpCropSay', T.pl_saving);
    saveBody({ photo: dataUrl }, function (d, err) {
      if (d) { cropClose(); return; }
      el('cpCropSave').disabled = false;
      say('cpCropSay', err ? err.message : T.failed, 'bad');
    });
  }

  (function () {
    var frame = el('cpCropFrame'), drag = null;
    if (!frame) return;
    // The frame is drawn at stage pixels; the console scales the stage, so a
    // pointer's screen movement is divided by that scale.
    function k() { var r = frame.getBoundingClientRect(); return r.width / 450 || 1; }
    frame.addEventListener('pointerdown', function (e) {
      if (!crop || !crop.img) return;
      drag = { x: e.clientX, y: e.clientY };
      try { frame.setPointerCapture(e.pointerId); } catch (err) {}
      frame.style.cursor = 'grabbing';
    });
    frame.addEventListener('pointermove', function (e) {
      if (!drag || !crop) return;
      var f = k();
      crop.x += (e.clientX - drag.x) / f;
      crop.y += (e.clientY - drag.y) / f;
      drag = { x: e.clientX, y: e.clientY };
      cropPaint();
    });
    ['pointerup', 'pointercancel'].forEach(function (ev) {
      frame.addEventListener(ev, function () { drag = null; frame.style.cursor = 'grab'; });
    });
    frame.addEventListener('wheel', function (e) {
      if (!crop || !crop.img) return;
      e.preventDefault();
      var z = Math.max(1, Math.min(4, crop.z * (e.deltaY < 0 ? 1.06 : 1 / 1.06))), r = z / crop.z;
      crop.z = z; crop.x *= r; crop.y *= r;
      el('cpCropZoom').value = z;
      cropPaint();
    }, { passive: false });
    el('cpCropZoom').addEventListener('input', function () {
      if (!crop || !crop.img) return;
      var z = Math.max(1, Math.min(4, parseFloat(this.value) || 1)), r = z / crop.z;
      crop.z = z; crop.x *= r; crop.y *= r;
      cropPaint();
    });
    el('cpCropPick').addEventListener('click', function () { el('cpCropFile').click(); });
    el('cpCropFile').addEventListener('change', function () {
      var f = this.files && this.files[0];
      if (f && /^image\/(jpeg|png|webp)$/.test(f.type)) cropLoad(f);
    });
    el('cpCropSave').addEventListener('click', cropSave);
    el('cpCropClose').addEventListener('click', cropClose);
  })();

  /* ── Adjust for VS screen (PhotoVsFrame) ──────────────────────────────
     Zoom and a focal point over the original on the VS panel (1075x1080 at
     the wall's 1920 width): zoom 1 at 50% 12% is the panel's own framing.
     Dragging moves the PICTURE under the finger, so the focal point moves the
     other way, by the share of the overflow dragged. The preview is THE
     shared VS screen in an iframe, driven over postMessage. */
  var vf = null, vfReady = false;

  function vfPost() {
    var f = el('cpVfLive');
    if (vfReady && vf && f && f.contentWindow) {
      f.contentWindow.postMessage({ type: 'vs-preview', aka: { vs_frame: { z: vf.z, x: vf.x, y: vf.y } } }, window.location.origin);
    }
  }
  function vfPaint() {
    vfPost();
    el('cpVfZoom').value = vf.z;
    el('cpVfZoomVal').textContent = Math.round(vf.z * 100) + '%';
  }
  window.addEventListener('message', function (e) {
    var f = el('cpVfLive');
    if (e.origin !== window.location.origin || !f || e.source !== f.contentWindow) return;
    if (e.data && e.data.type === 'vs-preview-ready') { vfReady = true; vfPost(); }
  });

  function vfOpen() {
    var p = plData && plData.player;
    if (!p || !p.vs_photo || !CFG.vsPreview) return;
    var f = p.vs_frame || {};
    vf = { id: plOpen, ratio: 4 / 3,
           z: isFinite(+f.z) ? +f.z : 1, x: isFinite(+f.x) ? +f.x : 50, y: isFinite(+f.y) ? +f.y : 12 };
    var probe = new Image();
    probe.onload = function () { if (vf && probe.naturalWidth) vf.ratio = probe.naturalHeight / probe.naturalWidth; };
    probe.src = p.vs_photo;
    text('cpVfTitle', T.pl_vs_btn + ' — ' + (p.name || ''));
    say('cpVfSay', '');
    vfReady = false;
    el('cpVfLive').setAttribute('src', CFG.vsPreview + (CFG.vsPreview.indexOf('?') === -1 ? '?' : '&') + 'reg=' + encodeURIComponent(vf.id));
    vfPaint();
    fitPopups();
    el('cpVf').hidden = false;
  }

  (function () {
    var stage = el('cpVfDrag'), drag = null;
    if (!stage) return;
    stage.addEventListener('pointerdown', function (e) {
      if (!vf) return;
      drag = { x: e.clientX, y: e.clientY };
      try { stage.setPointerCapture(e.pointerId); } catch (err) {}
      stage.style.cursor = 'grabbing';
    });
    stage.addEventListener('pointermove', function (e) {
      if (!drag || !vf) return;
      var r = el('cpVfLive').getBoundingClientRect(), k = r.width / 1920;
      var ox = 1075 * vf.z - 1075, oy = 1075 * vf.z * vf.ratio - 1080;
      if (ox > 0.5) vf.x = Math.min(100, Math.max(0, vf.x - (e.clientX - drag.x) / k / ox * 100));
      if (oy > 0.5) vf.y = Math.min(100, Math.max(0, vf.y - (e.clientY - drag.y) / k / oy * 100));
      drag = { x: e.clientX, y: e.clientY };
      vfPaint();
      say('cpVfSay', T.unsaved);
    });
    ['pointerup', 'pointercancel'].forEach(function (ev) {
      stage.addEventListener(ev, function () { drag = null; stage.style.cursor = 'grab'; });
    });
    el('cpVfZoom').addEventListener('input', function () {
      if (!vf) return;
      vf.z = Math.min(3, Math.max(1, parseFloat(this.value) || 1));
      vfPaint();
      say('cpVfSay', T.unsaved);
    });
    el('cpVfReset').addEventListener('click', function () {
      if (!vf) return;
      vf.z = 1; vf.x = 50; vf.y = 12;
      vfPaint();
      say('cpVfSay', T.unsaved);
    });
    el('cpVfClose').addEventListener('click', function () { el('cpVf').hidden = true; vf = null; });
    el('cpVfSave').addEventListener('click', function () {
      if (!vf) return;
      var btn = this, id = vf.id, isDefault = vf.z === 1 && vf.x === 50 && vf.y === 12;
      btn.disabled = true;
      send('POST', LIST_URL + '/' + id + '/vs-frame', isDefault ? { reset: true } : { zoom: vf.z, x: vf.x, y: vf.y })
        .then(function (d) {
          btn.disabled = false;
          if (plData && plData.player && plOpen === id) plData.player.vs_frame = d.frame;
          el('cpVf').hidden = true;
          vf = null;
          say('cpSay', d.message || '', 'ok');
        })
        .catch(function (err) { btn.disabled = false; say('cpVfSay', err.message || T.failed, 'bad'); });
    });
  })();

  /* ── The pop-ups: lifted to <body>, fitted like the console ──────────── */
  function fitPopups() {
    var k = Math.min(window.innerWidth / 1920, window.innerHeight / 1080) || 1;
    ['cpCrop', 'cpVf'].forEach(function (id) { var m = el(id); if (m) m.style.setProperty('--cp-k', k); });
  }
  ['cpCrop', 'cpVf'].forEach(function (id) { var m = el(id); if (m && m.parentNode !== document.body) document.body.appendChild(m); });
  window.addEventListener('resize', fitPopups);
  fitPopups();

  /* ── Wiring — only this tab's own ids, and whatever the console marks
     `data-players-open`. Delegated, so a console that re-renders its tab
     strip keeps working. */
  (function () {
    function light() { el('cpSave').disabled = !Object.keys(changes() || {}).length; }

    var find = el('cpFind'), ft = null;
    if (find) find.addEventListener('input', function () { clearTimeout(ft); ft = setTimeout(paintList, 140); });
    el('cpSave').addEventListener('click', save);
    el('cpPhoto').addEventListener('click', cropOpen);
    el('cpPhotoBtn').addEventListener('click', cropOpen);
    el('cpVsBtn').addEventListener('click', vfOpen);

    // Every input writes the draft; nothing is sent until Save.
    el('cpName').addEventListener('input', function () { if (plDraft) { plDraft.name = this.value; light(); } });
    [['cpDobD', 'dobD'], ['cpDobM', 'dobM'], ['cpDobY', 'dobY'], ['cpAge', 'age']].forEach(function (x) {
      var e = el(x[0]);
      e.addEventListener('input', function () {
        e.value = e.value.replace(/\D/g, '');
        if (plDraft) { plDraft[x[1]] = e.value; light(); }
      });
    });
    var w = el('cpWeight');
    w.addEventListener('input', function () {
      w.value = w.value.replace(/[^\d.]/g, '');
      if (plDraft) { plDraft.weight = w.value; light(); }
    });
    document.querySelectorAll('#cpEdit .cpl-seg').forEach(function (b) {
      b.addEventListener('click', function () { if (plDraft) { plDraft.mode = b.getAttribute('data-mode'); paintEditor(); } });
    });
    // The shared country picker and gender toggle tell the draft what was picked.
    window.addEventListener('cp-nat', function (e) {
      if (!plDraft || (e.detail || '') === plDraft.nat || !editable('nationality')) return;
      plDraft.nat = e.detail || '';
      text('cpNatNote', '');
      light();
    });
    window.addEventListener('cp-gender', function (e) {
      if (!plDraft || e.detail === plDraft.gender || !editable('gender')) return;
      plDraft.gender = e.detail || '';
      light();
    });

    // A floating list closes on a tap anywhere outside it, or on Escape.
    document.addEventListener('click', function (e) {
      // A target the repaint already removed was inside a dropdown a moment ago.
      if (!e.target.isConnected) return;
      if (ddOpen && !e.target.closest('.cpl-dd')) { ddOpen = null; if (plData) paintEditor(); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && ddOpen) { ddOpen = null; if (plData) paintEditor(); }
    });
    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-players-open]')) load(false);
    });
  })();

  return { load: load };
})();
</script>
