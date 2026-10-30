{{--
    The Karate console's Bouts card — four ways into the same draw.

    A port of the BJJ console's card (bjj/scoreboard/desktop/control.blade.php
    + runtime.blade.php), at the user's request 2026-10-09: the same size (it
    fills the console with a 48px inset), the same four tabs, the same rows.

      ARRANGED      this mat, in bout-number order (the console's QUEUE)
      WEIGHT CLASS  the event's divisions, then one division's bouts
      MEMBER        type a name or a club, get every bout they are in
      ARCADE        pick a competitor on each side; the console finds the bout
                    the DRAW already has between them — it never invents one
      EXHIBITIONS   organisers only: every exhibition fought here, the draw
                    bouts each could really have been, and the link that files
                    its result into one (App\Events\Support\ExhibitionLink)

    Shared by every Karate console (the five styles and the default), so it is
    written once here rather than six times. Read-only end to end: the three
    browsing tabs read `catalogueUrl` (App\Scoreboard\Support\BoutCatalogue,
    a GET behind the same canScore() as the page), and pressing any bout is the
    console's existing `load` command.

    The console hands its own pieces in through window.KarateBoutsCard(opts):
    el, send, boutSeconds, competitorCell, LOOK, MAT, and getters for STATE and
    QUEUE. The card owns everything drawn inside itself.

    Lives inside #stage, so it is authored in the stage's 1920x1080 pixels and
    scales with the console.
--}}
<style>
  #bouts .modal{width:1824px;height:984px;max-width:none;max-height:94%;gap:16px;}
  #bouts .mtitle{flex-shrink:0;}

  /* Pills, not underlines — the tabs ride IN the head, beside the word BOUTS. */
  #bouts .btabs{display:flex;align-items:center;gap:6px;flex:1;min-width:0;
        overflow-x:auto;scrollbar-width:none;-ms-overflow-style:none;}
  #bouts .btabs::-webkit-scrollbar{display:none;}
  #bouts .btab{font-family:'Anton',sans-serif;font-size:21px;letter-spacing:.08em;text-transform:uppercase;
        background:var(--fill-alt);border:1px solid var(--line);border-radius:999px;color:var(--faint);
        padding:7px 16px;cursor:pointer;display:flex;align-items:center;gap:8px;white-space:nowrap;
        flex:0 0 auto;transition:color .14s ease,background .14s ease,border-color .14s ease;}
  #bouts .btab:hover{color:var(--muted);background:var(--fill);}
  #bouts .btab[aria-selected="true"]{background:var(--gold);color:#000;border-color:transparent;}
  #bouts .btab .n{font-family:'Inter',system-ui,sans-serif;font-size:14px;font-weight:700;letter-spacing:0;
           background:var(--fill);color:var(--muted);border-radius:999px;padding:1px 8px;}
  #bouts .btab[aria-selected="true"] .n{background:rgba(0,0,0,.22);color:#000;}

  /* A pane owns the remaining height and scrolls INSIDE itself. */
  #bouts .bpane{flex:1;min-height:0;display:flex;flex-direction:column;gap:12px;}
  #bouts .bscroll{flex:1;min-height:0;overflow-y:auto;display:flex;flex-direction:column;gap:8px;padding-right:14px;}
  #bouts .bhint{font-size:20px;color:var(--faint);flex-shrink:0;}

  #bouts .bfind{width:100%;font-size:26px;background:var(--inset);border:1px solid var(--line);
         border-radius:10px;color:var(--text);padding:14px 18px;flex-shrink:0;}
  #bouts .bfind:focus{outline:none;border-color:var(--gold);}

  #bouts .bdiv{display:grid;grid-template-columns:1fr auto;align-items:center;gap:18px;text-align:left;
        background:var(--ink);color:var(--text);border:1px solid var(--line);border-radius:12px;
        padding:18px 22px;cursor:pointer;font-size:28px;}
  #bouts .bdiv:hover{background:var(--fill);}
  #bouts .bdiv .sub{display:block;font-size:16px;color:var(--faint);letter-spacing:.04em;margin-top:4px;
             overflow-wrap:anywhere;}
  #bouts .bdiv .cnt{font-family:'Anton',sans-serif;font-size:26px;color:var(--gold);white-space:nowrap;}

  /* Back out of a drill-down. A chevron, never a tailed arrow. */
  #bouts .bback{display:inline-flex;align-items:center;gap:10px;background:none;border:none;
         color:var(--muted);font-size:22px;cursor:pointer;padding:0;flex-shrink:0;
         letter-spacing:.08em;text-transform:uppercase;font-family:'Anton',sans-serif;}
  #bouts .bback:hover{color:var(--text);}

  /* Arcade — two rosters facing each other across the FIGHT button. */
  #bouts .arc{flex:1;min-height:0;display:grid;grid-template-columns:1fr 300px 1fr;gap:16px;}
  #bouts .arccol{min-height:0;display:flex;flex-direction:column;gap:10px;}
  #bouts .arccap{font-family:'Anton',sans-serif;font-size:22px;letter-spacing:.1em;text-transform:uppercase;
          flex-shrink:0;}
  #bouts .arclist{flex:1;min-height:0;overflow-y:auto;display:flex;flex-direction:column;gap:6px;
           padding-right:10px;}
  #bouts .arcrow{display:flex;align-items:center;gap:12px;text-align:left;background:var(--ink);
          color:var(--text);border:1px solid var(--line);border-radius:10px;padding:10px 14px;
          cursor:pointer;font-size:24px;}
  #bouts .arcrow:hover{background:var(--fill);}
  #bouts .arcrow[aria-pressed="true"]{background:var(--fill);}
  #bouts .arcmid{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;
          min-height:0;}
  #bouts .arcfight{width:100%;height:96px;border-radius:14px;font-family:'Anton',sans-serif;
            font-size:42px;letter-spacing:.12em;text-transform:uppercase;cursor:pointer;
            background:var(--fill-alt);color:var(--faint);border:1px solid var(--line);
            transition:background .14s ease,color .14s ease;}
  #bouts .arcfight:not(:disabled){background:var(--gold);color:#000;border-color:transparent;
            background-image:linear-gradient(rgba(255,255,255,.28),rgba(255,255,255,0) 60%,rgba(0,0,0,.14));}
  #bouts .arcfight:disabled{cursor:not-allowed;}
  #bouts .arcsay{font-size:19px;color:var(--faint);text-align:center;line-height:1.35;}

  /* Exhibitions — each fought exhibition, with the draw bouts it could be.
     One card each: a status rail down the leading edge, the fight drawn the
     way the board draws it (AKA · score · AO), the bouts it could become,
     then the card's actions in a footer. Every button is one .exbtn. */
  #bouts .exbar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;flex-shrink:0;}
  #bouts .exbar .bhint{flex:1;min-width:0;}
  #bouts .excard{--exind:128px;position:relative;flex-shrink:0;display:flex;flex-direction:column;gap:14px;
          background:linear-gradient(180deg,#141726 0%,#10121c 100%);border:1px solid var(--line-2);
          border-radius:16px;padding:18px 22px 18px 28px;
          box-shadow:0 8px 24px rgba(0,0,0,.28);transition:border-color .15s ease;}
  #bouts .excard::before{content:'';position:absolute;left:-1px;top:-1px;bottom:-1px;width:7px;
          border-radius:16px 0 0 16px;background:var(--line-2);}
  #bouts .excard.st-go::before{background:var(--gold);}
  #bouts .excard.st-ok::before{background:var(--green);}
  #bouts .excard.st-live::before{background:var(--aka-ink);}
  #bouts .excard.open{border-color:rgba(255,225,53,.55);}
  #bouts .exhead{display:grid;grid-template-columns:110px minmax(0,1fr) auto;align-items:center;gap:18px;}
  #bouts .excode{font-family:'Anton',sans-serif;font-size:34px;color:var(--gold);line-height:1;}
  #bouts .excode small{display:block;font-family:'Inter',system-ui,sans-serif;font-size:14px;color:var(--faint);
          letter-spacing:.06em;margin-top:6px;}
  /* The fight as a scoreboard strip: AKA left, the score plate, AO right. */
  #bouts .expair{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:stretch;
          background:var(--inset);border:1px solid var(--line);border-radius:12px;overflow:hidden;min-width:0;}
  #bouts .exside{display:flex;align-items:center;gap:12px;padding:12px 18px;min-width:0;font-size:25px;
          color:var(--muted);}
  #bouts .exside.a{justify-content:flex-end;text-align:right;box-shadow:inset 5px 0 0 var(--aka);}
  #bouts .exside.b{box-shadow:inset -5px 0 0 var(--ao);}
  #bouts .exside .nm{overflow-wrap:anywhere;min-width:0;}
  #bouts .exside.won{color:var(--text);font-weight:700;}
  #bouts .exwin{width:28px;height:28px;border-radius:50%;flex:0 0 auto;display:inline-flex;align-items:center;
          justify-content:center;font-size:16px;font-weight:800;color:#fff;}
  #bouts .exside.a .exwin{background:var(--aka);}
  #bouts .exside.b .exwin{background:var(--ao);}
  #bouts .exscore{display:flex;align-items:center;gap:12px;padding:0 22px;background:var(--ink);white-space:nowrap;
          border-left:1px solid var(--line);border-right:1px solid var(--line);
          font-family:'Anton',sans-serif;font-size:34px;line-height:1;}
  #bouts .exscore .a{color:var(--aka-ink);}
  #bouts .exscore .b{color:var(--ao-ink);}
  #bouts .exscore .sep{color:var(--faint);font-size:24px;}
  #bouts .exchip{display:inline-flex;align-items:center;gap:8px;font-size:14px;font-weight:700;letter-spacing:.12em;
          text-transform:uppercase;border-radius:999px;padding:6px 14px;white-space:nowrap;
          background:var(--fill-alt);color:var(--muted);border:1px solid var(--line);}
  #bouts .exchip::before{content:'';width:8px;height:8px;border-radius:50%;background:currentColor;flex:0 0 auto;}
  #bouts .exchip.go{background:rgba(255,225,53,.12);color:var(--gold);border-color:rgba(255,225,53,.35);}
  #bouts .exchip.ok{background:rgba(122,229,130,.12);color:var(--green);border-color:rgba(122,229,130,.35);}
  #bouts .exchip.bad{background:rgba(255,107,120,.12);color:var(--aka-ink);border-color:rgba(255,107,120,.35);}
  /* Rows of the "choose any bout" list — the whole row is the button. */
  #bouts .excand{display:grid;grid-template-columns:92px minmax(0,1fr) auto;align-items:center;gap:16px;width:100%;
          text-align:left;font-family:inherit;color:var(--text);cursor:pointer;
          background:var(--inset);border:1px solid var(--line);border-radius:12px;padding:10px 14px 10px 10px;
          font-size:21px;transition:border-color .14s ease,background .14s ease;}
  #bouts .excand:hover:not(:disabled){border-color:var(--gold);}
  #bouts .excand.arm{border-color:var(--gold);background:rgba(255,225,53,.06);}
  #bouts .excand:disabled{opacity:.5;cursor:not-allowed;}
  #bouts .excand .no{font-family:'Anton',sans-serif;font-size:24px;color:var(--gold);text-align:center;
          background:var(--fill-alt);border:1px solid var(--line-2);border-radius:10px;padding:8px 0;}
  #bouts .excand .who{min-width:0;color:var(--text);overflow-wrap:anywhere;}
  #bouts .excand .who small{display:block;font-size:15px;color:var(--faint);letter-spacing:.04em;margin-top:3px;}
  #bouts .excand .vs, #bouts .exitem .vs{color:var(--faint);font-size:15px;letter-spacing:.12em;
          text-transform:uppercase;margin:0 10px;}
  /* The card's one Actions menu. */
  #bouts .excard.menu{z-index:5;}
  #bouts .exend{display:flex;align-items:center;gap:12px;}
  #bouts .exmenu{position:relative;}
  #bouts .exmenubtn{min-width:0;gap:12px;}
  #bouts .exmenubtn .chev{font-family:'Inter',system-ui,sans-serif;font-size:16px;transition:transform .15s ease;}
  #bouts .exmenubtn.on{border-color:var(--gold);}
  #bouts .exmenubtn.on .chev{transform:rotate(180deg);}
  #bouts .expanel{position:absolute;right:0;top:calc(100% + 8px);z-index:40;width:760px;max-height:520px;overflow-y:auto;
          display:flex;flex-direction:column;gap:2px;padding:8px;background:#151827;border:1px solid var(--line-2);
          border-radius:14px;box-shadow:0 22px 50px rgba(0,0,0,.6);animation:exdrop .14s ease-out;}
  #bouts .expanel.up{top:auto;bottom:calc(100% + 8px);}
  @keyframes exdrop{from{opacity:0;transform:translateY(-6px);}to{opacity:1;transform:none;}}
  #bouts .exitem{display:flex;align-items:center;gap:14px;width:100%;text-align:left;background:none;border:none;
          border-radius:10px;padding:12px 14px;color:var(--text);font-size:21px;font-family:inherit;cursor:pointer;}
  #bouts .exitem:hover:not(:disabled){background:var(--fill);}
  #bouts .exitem:disabled{opacity:.45;cursor:not-allowed;}
  #bouts .exitem .lbl{flex:1;min-width:0;overflow-wrap:anywhere;}
  #bouts .exitem .lbl small{display:block;font-size:15px;color:var(--faint);letter-spacing:.03em;margin-top:3px;}
  #bouts .exitem .no{font-family:'Anton',sans-serif;font-weight:400;color:var(--gold);margin-right:14px;}
  #bouts .exitem .arr{color:var(--muted);font-size:20px;}
  #bouts .exitem.danger{color:var(--aka-ink);}
  #bouts .exitem.danger:hover:not(:disabled){background:rgba(255,107,120,.1);}
  #bouts .exrule{height:1px;background:var(--line-2);margin:6px 8px;}
  #bouts .exinfo{font-size:17px;line-height:1.4;color:var(--second);padding:10px 14px;}
  /* One action armed: say what it does, then Cancel / Confirm. */
  #bouts .exconfirm{display:flex;align-items:center;gap:18px;margin-left:var(--exind);padding:12px 12px 12px 18px;
          background:rgba(255,225,53,.07);border:1px solid rgba(255,225,53,.4);border-radius:12px;
          animation:exdrop .14s ease-out;}
  #bouts .exconfirm.danger{background:rgba(255,59,71,.08);border-color:rgba(255,107,120,.45);}
  #bouts .exconfirm .msg{flex:1;min-width:0;font-size:18px;line-height:1.4;color:var(--text);}
  #bouts .exconfirm .acts{display:flex;gap:10px;flex:0 0 auto;}
  @media (prefers-reduced-motion: reduce){#bouts .expanel,#bouts .exconfirm{animation:none;}}
  /* One button family for the whole tab: same height, same type. */
  #bouts .exbtn{display:inline-flex;align-items:center;justify-content:center;gap:10px;height:50px;min-width:140px;
          padding:0 22px;border-radius:10px;cursor:pointer;white-space:nowrap;
          font-family:'Anton',sans-serif;font-size:19px;letter-spacing:.1em;text-transform:uppercase;
          background:var(--fill);color:var(--text);border:1px solid var(--line-2);
          transition:background .14s ease,color .14s ease,border-color .14s ease,filter .14s ease;}
  #bouts .exbtn:hover:not(:disabled){border-color:var(--gold);}
  #bouts .exbtn.primary{background:var(--gold);color:#000;border-color:transparent;}
  #bouts .exbtn.primary:hover:not(:disabled){filter:brightness(1.08);}
  #bouts .exbtn.ghost{background:transparent;color:var(--second);}
  #bouts .exbtn.ghost:hover:not(:disabled){color:var(--text);}
  #bouts .exbtn.danger{background:transparent;color:var(--aka-ink);border-color:rgba(255,107,120,.5);}
  #bouts .exbtn.danger:hover:not(:disabled){border-color:var(--aka-ink);background:rgba(255,107,120,.08);}
  #bouts .exbtn.danger.arm{background:var(--alarm);color:#fff;border-color:transparent;}
  #bouts .exbtn:disabled{opacity:.4;cursor:not-allowed;}
  #bouts .expick{display:flex;flex-direction:column;gap:8px;margin-left:var(--exind);}
  #bouts .expick .bfind{font-size:21px;padding:10px 14px;}
  #bouts .expicklist{display:flex;flex-direction:column;gap:6px;max-height:420px;overflow-y:auto;padding-right:6px;}
  #bouts .exnote{font-size:18px;line-height:1.4;color:var(--second);margin-left:var(--exind);background:var(--inset);
          border:1px solid var(--line);border-left:3px solid var(--line-2);border-radius:10px;padding:10px 14px;}
  #bouts .exnote.warn{color:var(--gold);border-left-color:var(--gold);}
  #bouts .exnote.bad{color:#ff7a7a;border-left-color:#ff7a7a;}

  /* Players — a list, then one person's sheet: photo left, facts right. */
  #bouts .plrowbtn{display:flex;align-items:center;gap:14px;text-align:left;background:var(--ink);
          color:var(--text);border:1px solid var(--line);border-radius:12px;padding:10px 16px;
          cursor:pointer;font-size:24px;}
  #bouts .plrowbtn:hover{background:var(--fill);}
  /* Players: the list beside the sheet. */
  #bouts .plsplit{flex:1;min-height:0;display:grid;grid-template-columns:500px minmax(0,1fr);gap:26px;}
  #bouts #plIndex{min-height:0;}
  #bouts .plright{min-height:0;min-width:0;display:flex;flex-direction:column;}
  #bouts .plempty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;
          color:var(--faint);font-size:22px;letter-spacing:.08em;text-transform:uppercase;
          border:1px dashed var(--line-2);border-radius:20px;}
  #bouts .pltitle{font-family:'Anton',sans-serif;font-size:30px;letter-spacing:.04em;color:var(--text);
          min-width:0;overflow-wrap:anywhere;flex-shrink:0;}
  #bouts .plrowbtn[aria-current="true"]{border-color:var(--gold);background:var(--fill);}
  /* A player's weight class: a gold chip; on the sheet the division's full name rides beside it. */
  #bouts .plclasses{display:flex;flex-wrap:wrap;gap:6px;flex-shrink:0;}
  #bouts .plclass{display:inline-flex;align-items:center;gap:10px;border-radius:999px;padding:4px 12px;
          background:rgba(255,225,53,.12);border:1px solid rgba(255,225,53,.4);color:var(--gold);white-space:nowrap;
          font-family:'Inter',system-ui,sans-serif;font-size:16px;letter-spacing:0;}
  #bouts .plclass b{font-family:'Anton',sans-serif;font-weight:400;font-size:18px;letter-spacing:.04em;}
  #bouts .plclass span{color:var(--text);font-size:16px;white-space:normal;}
  #bouts .pltitle .plclasses{font-family:'Inter',system-ui,sans-serif;}
  #bouts .plclass.go{cursor:pointer;transition:background .14s ease,border-color .14s ease;}
  #bouts .plclass.go:hover{background:rgba(255,225,53,.22);border-color:var(--gold);}
  /* A player's weight classes, as cards under the name: the class big, the
     division's full name and bounds beside it, Move / Remove on the card, and
     one dashed card that adds another. Signed-in organisers only. */
  #bouts .plright{position:relative;}
  #bouts .wcstrip{display:flex;flex-wrap:wrap;gap:12px;margin-top:12px;font-family:'Inter',system-ui,sans-serif;letter-spacing:0;}
  #bouts .wccard{position:relative;display:flex;align-items:center;gap:16px;min-width:340px;max-width:100%;
          background:linear-gradient(180deg,#171a2b 0%,#111320 100%);border:1px solid var(--line-2);border-radius:14px;
          padding:10px 12px 10px 22px;box-shadow:0 6px 18px rgba(0,0,0,.28);transition:border-color .15s ease,background .15s ease;
          animation:exdrop .14s ease-out;}
  #bouts .wccard::before{content:'';position:absolute;left:-1px;top:-1px;bottom:-1px;width:6px;border-radius:14px 0 0 14px;background:var(--gold);}
  #bouts .wccard .w{font-family:'Anton',sans-serif;font-size:30px;line-height:1;color:var(--gold);white-space:nowrap;}
  #bouts .wccard .n{min-width:0;flex:1;font-size:17px;font-weight:600;color:var(--text);overflow-wrap:anywhere;}
  #bouts .wccard .n small{display:block;font-size:14px;font-weight:400;color:var(--faint);margin-top:3px;}
  #bouts .wccard .acts{display:flex;gap:8px;flex:0 0 auto;}
  #bouts .wccard .exbtn{height:44px;min-width:0;padding:0 16px;font-size:16px;}
  #bouts .wccard.arm{border-color:rgba(255,107,120,.6);background:rgba(255,59,71,.08);}
  #bouts .wccard.arm::before{background:var(--aka-ink);}
  #bouts .wccard.busy{opacity:.55;pointer-events:none;}
  #bouts .wcadd{display:inline-flex;align-items:center;gap:12px;min-height:66px;padding:0 22px;border-radius:14px;cursor:pointer;
          background:transparent;border:2px dashed rgba(255,225,53,.45);color:var(--gold);
          font-family:'Anton',sans-serif;font-size:19px;letter-spacing:.1em;text-transform:uppercase;transition:background .14s ease,border-color .14s ease;}
  #bouts .wcadd:hover:not(:disabled){background:rgba(255,225,53,.08);border-color:var(--gold);}
  #bouts .wcadd:disabled{opacity:.4;cursor:not-allowed;}
  #bouts .wcadd .plus{font-size:28px;line-height:1;}
  #bouts .wcnone{display:flex;align-items:center;font-size:18px;color:var(--aka-ink);padding:0 6px;}
  /* The picker covers the sheet: every division, the fits first. */
  #bouts .wcpick{position:absolute;inset:0;z-index:30;display:flex;flex-direction:column;gap:14px;
          background:linear-gradient(180deg,#141726 0%,#0e1019 100%);border:1px solid var(--line-2);border-radius:20px;
          padding:22px 26px;box-shadow:0 22px 60px rgba(0,0,0,.6);animation:exdrop .16s ease-out;}
  #bouts .wcpick .head{display:flex;align-items:center;gap:16px;flex-shrink:0;}
  #bouts .wcpick .head h4{margin:0;flex:1;min-width:0;font-family:'Anton',sans-serif;font-weight:400;font-size:30px;
          letter-spacing:.04em;color:var(--text);overflow-wrap:anywhere;}
  #bouts .wcpick .head h4 small{display:block;font-family:'Inter',system-ui,sans-serif;font-size:17px;letter-spacing:0;color:var(--faint);margin-top:4px;}
  #bouts .wcopts{flex:1;min-height:0;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));
          gap:10px;align-content:start;padding-right:8px;}
  #bouts .wcopt{position:relative;display:flex;flex-direction:column;gap:4px;text-align:left;cursor:pointer;font-family:inherit;
          background:var(--inset);border:1px solid var(--line);border-radius:12px;padding:12px 16px 12px 18px;color:var(--text);
          transition:border-color .14s ease,background .14s ease;}
  #bouts .wcopt:hover:not(:disabled){border-color:var(--gold);}
  #bouts .wcopt:disabled{cursor:not-allowed;}
  #bouts .wcopt .w{font-family:'Anton',sans-serif;font-size:26px;line-height:1.05;color:var(--gold);}
  #bouts .wcopt .nm{font-size:17px;font-weight:600;overflow-wrap:anywhere;}
  #bouts .wcopt .dt{font-size:14px;color:var(--faint);}
  #bouts .wcopt .why{font-size:14px;color:#ff9a9a;}
  #bouts .wcopt .tag{position:absolute;top:10px;right:12px;font-size:12px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;
          border-radius:999px;padding:3px 10px;background:rgba(122,229,130,.12);color:var(--green);border:1px solid rgba(122,229,130,.35);}
  #bouts .wcopt.out{opacity:.62;}
  #bouts .wcopt.out .tag{background:rgba(255,107,120,.1);color:var(--aka-ink);border-color:rgba(255,107,120,.35);}
  #bouts .wcopt.here{opacity:.45;}
  #bouts .wcopt.here .tag{background:var(--fill-alt);color:var(--muted);border-color:var(--line);}
  #bouts .wcopt[aria-pressed="true"]{border-color:var(--gold);background:rgba(255,225,53,.08);box-shadow:inset 0 0 0 1px var(--gold);}
  #bouts .wcpick .foot{display:flex;align-items:center;gap:14px;flex-shrink:0;}
  #bouts .wcpick .foot .msg{flex:1;min-width:0;font-size:18px;line-height:1.4;color:var(--second);}
  @media (prefers-reduced-motion: reduce){#bouts .wccard,#bouts .wcpick{animation:none;}}
  /* A crest is a transparent PNG: shown bare on a checker, never on a tile. */
  #bouts .cllogo{width:200px;height:200px;border-radius:14px;border:2px dashed var(--line-2);cursor:pointer;
          background-color:var(--ink);background-position:center;background-size:contain;background-repeat:no-repeat;}
  #bouts .cllogo:hover:not(:disabled){border-color:var(--gold);}
  #bouts .cllogo:disabled{cursor:default;}
  #bouts .clmark{width:44px;height:44px;flex:0 0 auto;background:center/contain no-repeat;border-radius:50%;
          display:flex;align-items:center;justify-content:center;font-family:'Anton',sans-serif;font-size:20px;color:var(--gold);}
  #bouts .clath{display:flex;flex-direction:column;gap:6px;max-height:360px;overflow-y:auto;padding-right:6px;}
  #bouts .clathrow{display:flex;align-items:center;gap:12px;background:var(--ink);border:1px solid var(--line);
          border-radius:10px;padding:8px 12px;cursor:pointer;color:var(--text);font-size:20px;text-align:left;}
  #bouts .clathrow:hover{border-color:var(--gold);}
  #bouts .clbadge{font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:#000;background:var(--ao-ink);
          border-radius:999px;padding:2px 10px;}
  /* The player's sheet: one card, never wider than it reads well. */
  #bouts .plcard{flex:0 0 auto;width:100%;margin:0;display:flex;flex-direction:column;
          gap:18px;background:linear-gradient(180deg,#161929 0%,#111320 100%);border:1px solid var(--line-2);
          border-radius:20px;padding:24px 28px;box-shadow:0 14px 40px rgba(0,0,0,.4);}
  #bouts .pled{display:grid;grid-template-columns:250px minmax(0,1fr);gap:22px;}
  #bouts .plphotocol{display:flex;flex-direction:column;align-items:center;gap:12px;align-self:start;
          background:var(--inset);border:1px solid var(--line);border-radius:16px;padding:16px 10px;}
  #bouts .plphoto{width:225px;height:300px;border-radius:12px;border:2px solid var(--line-2);
          background:var(--fill) center 15%/cover no-repeat;cursor:pointer;transition:border-color .15s ease;}
  #bouts .plphoto:hover{border-color:var(--gold);}
  #bouts .plphotocol .plbtn{width:225px;}
  /* Not a scroller: the sheet is sized to fit the stage, and a scrolling
     column would clip the dropdown lists that open over it. */
  #bouts .plform{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;align-content:start;}
  #bouts .plsec{background:var(--inset);border:1px solid var(--line);border-radius:16px;padding:18px 22px 20px;
          display:flex;flex-direction:column;gap:18px;min-width:0;}
  #bouts .plsech{margin:0;font-family:'Anton',sans-serif;font-weight:400;font-size:18px;letter-spacing:.18em;
          text-transform:uppercase;color:var(--gold);padding-bottom:10px;border-bottom:1px solid var(--line);}
  #bouts .plpair{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px 22px;align-items:start;}
  /* Pinned to the bottom-right corner of the pop-up, whatever the sheet's height. */
  #bouts .plfoot{display:flex;align-items:center;gap:16px;flex-shrink:0;margin-top:auto;}
  #bouts .plrow{display:flex;flex-direction:column;gap:8px;min-width:0;}
  #bouts .plcap{font-size:16px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted);}
  #bouts .plin{font-size:24px;background:var(--inset);border:1px solid var(--line);border-radius:10px;
          color:var(--text);padding:10px 14px;min-width:0;}
  #bouts .plin:focus{outline:none;border-color:var(--gold);}
  #bouts .plin:disabled{opacity:.45;}
  #bouts .plchips{display:flex;flex-wrap:wrap;gap:8px;}
  #bouts .plchip{font-size:19px;letter-spacing:.06em;text-transform:uppercase;background:var(--fill-alt);
          color:var(--muted);border:1px solid var(--line);border-radius:999px;padding:7px 16px;cursor:pointer;
          display:inline-flex;align-items:center;gap:8px;}
  #bouts .plchip[aria-pressed="true"]{background:var(--gold);color:#000;border-color:transparent;}
  #bouts .plchip:disabled{opacity:.4;cursor:not-allowed;}
  #bouts .plsw{width:18px;height:18px;border-radius:50%;border:1px solid rgba(255,255,255,.35);flex:0 0 auto;}
  /* The shared gender toggle is written in Tailwind classes, and this console
     loads neither Tailwind nor the icon font — so its classes are given their
     meaning HERE, on the console's dark palette, and nowhere else. */
  #plGender{max-width:380px;}
  #plGender.locked{opacity:.45;pointer-events:none;}
  #plGender .grid{display:grid;}
  #plGender .grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr));}
  #plGender .gap-3{gap:12px;}
  #plGender .flex{display:flex;}
  #plGender .items-center{align-items:center;}
  #plGender .justify-center{justify-content:center;}
  #plGender .gap-2{gap:10px;}
  #plGender .py-3{padding-top:10px;padding-bottom:10px;}
  #plGender .rounded-xl{border-radius:12px;}
  #plGender .border-2{border-width:2px;border-style:solid;}
  #plGender .transition-all{transition:all .15s ease;}
  #plGender .font-semibold{font-weight:600;}
  #plGender .text-sm{font-size:22px;letter-spacing:.04em;}
  #plGender button{background:var(--fill-alt);cursor:pointer;font-family:inherit;}
  #plGender .border-gray-200{border-color:var(--line);}
  #plGender .text-gray-600{color:var(--muted);}
  #plGender .hover\:border-gray-300:hover{border-color:var(--line-2);}
  #plGender .border-blue-500{border-color:#5aa9ff;}
  #plGender .bg-blue-50{background:rgba(90,169,255,.16);}
  #plGender .text-blue-600{color:#5aa9ff;}
  #plGender .border-pink-500{border-color:#ff6fae;}
  #plGender .bg-pink-50{background:rgba(255,111,174,.16);}
  #plGender .text-pink-600{color:#ff6fae;}
  #plGender .bi-gender-male::before{content:'♂';font-style:normal;}
  #plGender .bi-gender-female::before{content:'♀';font-style:normal;}
  #plGender i{font-style:normal;}
  #bouts .plbtn{font-size:18px;letter-spacing:.08em;text-transform:uppercase;background:var(--fill);color:var(--ao-ink);
          border:1px solid rgba(138,180,255,.4);border-radius:10px;padding:10px 18px;cursor:pointer;width:240px;}
  #bouts .plbtn:disabled{opacity:.4;cursor:not-allowed;}
  #bouts .plwhy{font-size:15px;color:var(--faint);letter-spacing:.02em;}
  #bouts .plwhy:empty{display:none;}
  /* A dropdown whose list floats under its trigger. Club and nationality sit
     at the top of the form, so the list always has room below them. */
  #bouts .pldd{display:flex;flex-direction:column;gap:6px;min-width:0;position:relative;}
  #bouts .plddbtn{display:flex;align-items:center;gap:12px;font-size:22px;background:var(--inset);
          border:1px solid var(--line);border-radius:10px;color:var(--text);padding:9px 14px;cursor:pointer;
          text-align:left;min-width:0;}
  #bouts .plddbtn:hover:not(:disabled){border-color:var(--gold);}
  #bouts .plddbtn:disabled{opacity:.45;cursor:not-allowed;}
  #bouts .plddbtn .chev{margin-left:auto;color:var(--muted);transition:transform .15s ease;}
  #bouts .plddbtn[aria-expanded="true"] .chev{transform:rotate(180deg);}
  /* Floats over the fields below rather than pushing them down. */
  #bouts .plddpanel{position:absolute;left:0;right:0;top:calc(100% + 6px);z-index:30;
          background:var(--ink);border:1px solid var(--line-2);border-radius:12px;padding:8px;
          display:flex;flex-direction:column;gap:6px;box-shadow:0 18px 40px rgba(0,0,0,.6);}
  #bouts .plddlist{max-height:260px;overflow-y:auto;display:flex;flex-direction:column;gap:2px;}
  #bouts .plddopt{display:flex;align-items:center;gap:12px;font-size:20px;background:none;border:none;
          color:var(--text);padding:8px 10px;border-radius:8px;cursor:pointer;text-align:left;}
  #bouts .plddopt:hover{background:var(--fill);}
  #bouts .plddopt[aria-selected="true"]{color:var(--gold);}
  #bouts .plico{width:30px;height:30px;flex:0 0 auto;background:center/contain no-repeat;}
  #bouts .plbelt{width:34px;height:34px;flex:0 0 auto;}
  #bouts .pldd.up .plddpanel{top:auto;bottom:calc(100% + 6px);}
  #bouts .plflag{width:30px;height:22px;flex:0 0 auto;background:center/100% 100% no-repeat;
          border:1px solid rgba(255,255,255,.2);border-radius:3px;}
  #bouts .plseg{font-size:16px;letter-spacing:.14em;text-transform:uppercase;background:var(--fill-alt);
          color:var(--muted);border:1px solid var(--line);border-radius:999px;padding:5px 14px;cursor:pointer;}
  #bouts .plseg[aria-pressed="true"]{background:var(--gold);color:#000;border-color:transparent;}
  #bouts .plseg:disabled{opacity:.4;cursor:not-allowed;}
  /* The shared country picker is written in the platform's classes; this
     console loads neither Tailwind nor app.css, so they are given their
     meaning HERE, on the console's palette, and nowhere else. */
  .plnat [x-cloak]{display:none !important;}
  .plnat .plcap{display:block;margin-bottom:8px;}
  .plnat .relative{position:relative;}
  .plnat .pl-country-trigger{width:100%;justify-content:space-between;}
  .plnat .flex{display:flex;}
  .plnat .items-center{align-items:center;}
  .plnat .gap-2{gap:12px;}
  .plnat .text-sm{font-size:22px;}
  .plnat .text-gray-400{color:var(--faint);}
  .plnat .fi{width:30px;height:22px;border-radius:3px;border:1px solid rgba(255,255,255,.2);
          background-size:100% 100%;display:inline-block;flex:0 0 auto;}
  .plnat .bi-chevron-down{font-style:normal;color:var(--muted);transition:transform .15s ease;}
  .plnat .bi-chevron-down::before{content:'⌄';}
  .plnat .rotate-180{transform:rotate(180deg);}
  .plnat .tf-dropdown-panel{position:absolute;left:0;right:0;z-index:30;background:var(--ink);
          border:1px solid var(--line-2);border-radius:12px;overflow:hidden;box-shadow:0 18px 40px rgba(0,0,0,.6);}
  .plnat .top-full{top:calc(100% + 6px);}
  .plnat .bottom-full{bottom:calc(100% + 6px);}
  .plnat .p-2{padding:8px;}
  .plnat .border-b{border-bottom:1px solid var(--line);}
  .plnat .tf-dropdown-panel input{width:100%;font-size:20px;background:var(--inset);border:1px solid var(--line);
          border-radius:10px;color:var(--text);padding:9px 12px;outline:none;font-family:inherit;}
  .plnat .tf-dropdown-panel input:focus{border-color:var(--gold);}
  .plnat .max-h-60{max-height:260px;}
  .plnat .overflow-y-auto{overflow-y:auto;}
  .plnat .tf-dropdown-item-sm{display:flex;align-items:center;gap:12px;font-size:20px;color:var(--text);
          padding:8px 12px;cursor:pointer;}
  .plnat .tf-dropdown-item-sm:hover{background:var(--fill);}
  .plnat .mr-2{margin-right:0;}
  .plnat .px-4{padding-left:12px;padding-right:12px;}
  .plnat .py-2{padding-top:8px;padding-bottom:8px;}
  .plnat .text-gray-500{color:var(--faint);}
  .plnat.locked{opacity:.45;pointer-events:none;}
  #bouts .plsave{font-family:'Anton',sans-serif;font-size:28px;letter-spacing:.12em;text-transform:uppercase;
          background:var(--green);color:#000;border:none;border-radius:12px;padding:12px 40px;cursor:pointer;}
  #bouts .plsave:disabled{opacity:.35;cursor:not-allowed;}
</style>

{{-- Alpine, SELF-HOSTED (public/vendor/alpinejs — see its README), for the
     shared components the Players tab uses. Only where that tab exists; the
     Karate consoles run no other Alpine. Deferred, so it starts once the
     whole page is parsed. --}}
@if (! empty($playersUrl))
<script defer src="{{ asset('vendor/alpinejs/3.17.4/alpine.min.js') }}"></script>
@endif

{{-- The belt drawing, once, for belts this card draws in script. --}}
<x-belt-icon-defs />

{{-- The vendored flag sheet, for the country picker's `fi fi-xx` flags. --}}
@if (! empty($playersUrl))
<link rel="stylesheet" href="{{ asset('images/flags/flag-icons.css') }}">
@endif

<div id="bouts" hidden class="scrim" style="z-index:20;">
  <div class="modal">
    <div class="mhead">
      <div class="mtitle" style="font-size:26px;color:var(--green);">{{ __('scoreboard::karate_messages.ctl_bouts') }}</div>

      <div class="btabs" role="tablist">
        <button type="button" class="btab" role="tab" data-btab="arranged" aria-selected="true"
                aria-controls="bpaneArranged">{{ __('scoreboard::karate_messages.ctl_tab_arranged') }}</button>
        <button type="button" class="btab" role="tab" data-btab="division" aria-selected="false"
                aria-controls="bpaneDivision">{{ __('scoreboard::karate_messages.ctl_tab_division') }}<span class="n" id="btabDivisionCount" hidden></span></button>
        @if (! empty($playersUrl))
        <button type="button" class="btab" role="tab" data-btab="member" aria-selected="false"
                aria-controls="bpaneMember">{{ __('scoreboard::karate_messages.pl_tab') }}</button>
        @endif
        @if (! empty($clubsUrl))
        <button type="button" class="btab" role="tab" data-btab="clubs" aria-selected="false"
                aria-controls="bpaneClubs">{{ __('scoreboard::karate_messages.cl_tab') }}</button>
        @endif
        <button type="button" class="btab" role="tab" data-btab="arcade" aria-selected="false"
                aria-controls="bpaneArcade">{{ __('scoreboard::karate_messages.ctl_tab_arcade') }}</button>
        @if (! empty($exhibitionsUrl))
        <button type="button" class="btab" role="tab" data-btab="exhibitions" aria-selected="false"
                aria-controls="bpaneExhibitions">{{ __('scoreboard::karate_messages.ctl_tab_ex') }}<span class="n" id="btabExCount" hidden></span></button>
        @endif
      </div>

      <button data-close="bouts" class="mclose">✕</button>
    </div>

    {{-- ARRANGED — this mat, in bout-number order. --}}
    <div class="bpane" id="bpaneArranged" role="tabpanel">
      {{-- Empty: this mat, in order. Typed: every bout of the event matching a
           name, a club, a weight class or a bout number — on any mat. --}}
      <input id="arrFind" class="bfind" type="search" autocomplete="off" spellcheck="false"
             placeholder="{{ __('scoreboard::karate_messages.ctl_arr_find') }}"
             aria-label="{{ __('scoreboard::karate_messages.ctl_arr_find') }}">
      <div id="boutsList" class="bscroll" style="min-height:160px;"></div>
      <div class="bhint">{{ __('scoreboard::karate_messages.ctl_bouts_hint') }}</div>
    </div>

    {{-- WEIGHT CLASS — the divisions, then one division's bouts. --}}
    <div class="bpane" id="bpaneDivision" role="tabpanel" hidden>
      <div id="divIndex" class="bpane">
        <div id="divList" class="bscroll"></div>
        <div class="bhint">{{ __('scoreboard::karate_messages.ctl_division_hint') }}</div>
      </div>
      <div id="divDetail" class="bpane" hidden>
        <div style="display:flex;align-items:center;gap:18px;flex-shrink:0;min-width:0;">
          <button type="button" id="divBack" class="bback">
            <i style="font-style:normal;">‹</i>{{ __('scoreboard::karate_messages.ctl_all_divisions') }}
          </button>
          <div id="divTitle" style="font-size:22px;color:var(--text);min-width:0;overflow-wrap:anywhere;"></div>
        </div>
        <div id="divBouts" class="bscroll"></div>
      </div>
    </div>

    {{-- MEMBER — every bout of the event, not just this mat's. --}}
    {{-- PLAYERS — find an entrant and correct them: name, club, date of birth
         or age, gender, weight, belt, photo. Organisers only; every save goes
         through the platform's entrant editor (see ScoreboardController::players). --}}
    <div class="bpane" id="bpaneMember" role="tabpanel" hidden>
      {{-- List on the left, the chosen player's sheet on the right. --}}
      <div class="plsplit">
      <div id="plIndex" class="bpane">
        <input id="plFind" class="bfind" type="search" autocomplete="off" spellcheck="false"
               placeholder="{{ __('scoreboard::karate_messages.pl_find') }}"
               aria-label="{{ __('scoreboard::karate_messages.pl_find') }}">
        <div id="plList" class="bscroll"></div>
      </div>

      <div class="plright">
      <div id="plEmpty" class="plempty">
        <span style="font-size:44px;color:var(--gold);">◎</span>
        {{ __('scoreboard::karate_messages.pl_pick') }}
      </div>

      <div id="plEdit" class="bpane" hidden>
        <div id="plTitle" class="pltitle"></div>
        {{-- Their weight classes, as cards — add, move, remove (signed-in organisers). --}}
        <div id="plClasses" class="wcstrip" hidden></div>

        {{-- One card: the face on the left, two sections on the right — who
             they are, and what they compete as. Fields keep their natural
             width inside a section rather than stretching across the stage. --}}
        <div class="plcard">
          <div class="pled">
          <div class="plphotocol">
            <button type="button" id="plPhoto" class="plphoto" aria-label="{{ __('scoreboard::karate_messages.pl_photo') }}"></button>
            <button type="button" id="plPhotoBtn" class="plbtn">{{ __('scoreboard::karate_messages.pl_photo') }}</button>
            <button type="button" id="plVsBtn" class="plbtn" hidden style="color:var(--gold);border-color:rgba(255,225,53,.45);">{{ __('scoreboard::karate_messages.pl_vs_btn') }}</button>
            <div class="plwhy" id="plWhy-photo"></div>
          </div>

            <div class="plform">
              <section class="plsec">
                <h4 class="plsech">{{ __('scoreboard::karate_messages.pl_sec_identity') }}</h4>
            <div class="plrow">
              <span class="plcap">{{ __('scoreboard::karate_messages.pl_name') }}</span>
              <input id="plName" class="plin" type="text" maxlength="120" autocomplete="off">
              <div class="plwhy" id="plWhy-name"></div>
            </div>
              <div class="plrow">
                <span class="plcap">{{ __('scoreboard::karate_messages.pl_club') }}</span>
                <div id="plClub" class="pldd"></div>
                <div class="plwhy" id="plWhy-club"></div>
              </div>
              <div class="plrow">
                {{-- The platform's own country picker (fields/country), bridged
                     to this editor by two window events. --}}
                <div id="plNatWrap" class="plnat" x-data="{ nat: '' }"
                     x-init="$watch('nat', v => window.dispatchEvent(new CustomEvent('pl-nat', { detail: v || '' })))"
                     @pl-nat-set.window="nat = $event.detail || ''">
                  <x-fields.country id="plNationality" name="pl_nationality" model="nat" :inline="true"
                      :label="__('scoreboard::karate_messages.pl_nationality')"
                      wrapper-class="" label-class="plcap" trigger-class="plddbtn pl-country-trigger" />
                </div>
                <div class="plwhy" id="plNatNote"></div>
                <div class="plwhy" id="plWhy-nationality"></div>
              </div>
              </section>

              <section class="plsec">
                <h4 class="plsech">{{ __('scoreboard::karate_messages.pl_sec_competition') }}</h4>
              {{-- Date of birth OR age: one or the other, never both. --}}
              <div class="plrow">
                <div style="display:flex;align-items:center;gap:8px;">
                  <button type="button" class="plseg" data-mode="dob">{{ __('scoreboard::karate_messages.pl_dob') }}</button>
                  <button type="button" class="plseg" data-mode="age">{{ __('scoreboard::karate_messages.pl_age') }}</button>
                </div>
                <div id="plDobBox" style="display:flex;gap:8px;align-items:center;">
                  <input id="plDobD" class="plin" style="width:74px;text-align:center;" inputmode="numeric" maxlength="2" placeholder="DD">
                  <input id="plDobM" class="plin" style="width:74px;text-align:center;" inputmode="numeric" maxlength="2" placeholder="MM">
                  <input id="plDobY" class="plin" style="width:110px;text-align:center;" inputmode="numeric" maxlength="4" placeholder="YYYY">
                </div>
                <div id="plAgeBox" style="display:flex;gap:8px;align-items:center;" hidden>
                  <input id="plAge" class="plin" style="width:110px;text-align:center;" inputmode="numeric" maxlength="2">
                  <span style="font-size:20px;color:var(--muted);">{{ __('scoreboard::karate_messages.pl_years') }}</span>
                </div>
                <div class="plwhy" id="plWhy-birthdate"></div>
              </div>
                <div class="plpair">
              <div class="plrow">
                <span class="plcap">{{ __('scoreboard::karate_messages.pl_gender') }}</span>
                {{-- The platform's own gender toggle (fields/gender-toggle), in an
                     Alpine scope bridged to this editor by two window events. --}}
                <div id="plGender" x-data="{ g: '' }"
                     x-init="$watch('g', v => window.dispatchEvent(new CustomEvent('pl-gender', { detail: v })))"
                     @pl-gender-set.window="g = $event.detail">
                  <x-fields.gender-toggle model="g"
                      :male-label="\Illuminate\Support\Js::from(__('scoreboard::karate_messages.ctl_gender_male'))"
                      :female-label="\Illuminate\Support\Js::from(__('scoreboard::karate_messages.ctl_gender_female'))" />
                </div>
                <div class="plwhy" id="plWhy-gender"></div>
              </div>
              <div class="plrow">
                <span class="plcap">{{ __('scoreboard::karate_messages.pl_weight') }}</span>
                <div style="display:flex;gap:8px;align-items:center;">
                  <input id="plWeight" class="plin" style="width:130px;text-align:center;" inputmode="decimal" maxlength="6">
                  <span style="font-size:22px;color:var(--muted);">kg</span>
                </div>
                <div class="plwhy" id="plWhy-weight"></div>
              </div>
                </div>
            <div class="plrow">
              <span class="plcap">{{ __('scoreboard::karate_messages.pl_belt') }}</span>
              {{-- One list: every belt of this event's system and each of its
                   degrees, each drawn as the belt itself. --}}
              <div id="plBelt" class="pldd"></div>
              <div class="plwhy" id="plWhy-belt_colour"></div>
            </div>
              </section>
            </div>
          </div>
        </div>

        {{-- The action sits under the sheet, not inside it. --}}
        <div class="plfoot">
          <div id="plSay" class="bhint" style="flex:1;"></div>
          <button type="button" id="plSave" class="plsave" disabled>{{ __('scoreboard::karate_messages.pl_save') }}</button>
        </div>
      </div>
      {{-- Choosing a weight class to add them to, or to move one to. --}}
      <div id="plClassPick" class="wcpick" hidden></div>
      </div>{{-- .plright --}}
      </div>{{-- .plsplit --}}
    </div>

    {{-- CLUBS — the event's club list on the left, one club on the right.
         Organisers only; saving goes through the event's own club editor. --}}
    <div class="bpane" id="bpaneClubs" role="tabpanel" hidden>
      <div class="plsplit">
        <div class="bpane">
          <input id="clFind" class="bfind" type="search" autocomplete="off" spellcheck="false"
                 placeholder="{{ __('scoreboard::karate_messages.cl_find') }}"
                 aria-label="{{ __('scoreboard::karate_messages.cl_find') }}">
          <div id="clList" class="bscroll"></div>
        </div>

        <div class="plright">
          <div id="clEmpty" class="plempty">
            <span style="font-size:44px;color:var(--gold);">◎</span>
            {{ __('scoreboard::karate_messages.cl_pick') }}
          </div>

          <div id="clEdit" class="bpane" hidden>
            <div id="clTitle" class="pltitle"></div>

            <div class="plcard">
              <div class="pled">
                <div class="plphotocol">
                  <button type="button" id="clLogo" class="cllogo" aria-label="{{ __('scoreboard::karate_messages.cl_logo') }}"></button>
                  <label id="clLogoBtn" class="plbtn" style="text-align:center;cursor:pointer;">
                    {{ __('scoreboard::karate_messages.cl_logo') }}
                    <input id="clLogoFile" type="file" accept="image/png,image/jpeg,image/webp" style="display:none;">
                  </label>
                  <div class="plwhy" id="clLogoNote"></div>
                </div>

                <div class="plform">
                  <section class="plsec">
                    <h4 class="plsech">{{ __('scoreboard::karate_messages.cl_sec_club') }}</h4>
                    <div class="plrow">
                      <span class="plcap">{{ __('scoreboard::karate_messages.cl_name') }}</span>
                      <input id="clName" class="plin" type="text" maxlength="120" autocomplete="off">
                    </div>
                    <div class="plrow">
                      <div id="clNatWrap" class="plnat" x-data="{ nat: '' }"
                           x-init="$watch('nat', v => window.dispatchEvent(new CustomEvent('cl-nat', { detail: v || '' })))"
                           @cl-nat-set.window="nat = $event.detail || ''">
                        <x-fields.country id="clCountry" name="cl_country" model="nat" :inline="true"
                            :label="__('scoreboard::karate_messages.cl_country')"
                            wrapper-class="" label-class="plcap" trigger-class="plddbtn pl-country-trigger" />
                      </div>
                    </div>
                    <div class="plrow">
                      <span class="plcap">Instagram</span>
                      <input id="clInsta" class="plin" type="text" maxlength="200" autocomplete="off" placeholder="@club">
                    </div>
                    <div class="plwhy" id="clLocked"></div>
                  </section>

                  <section class="plsec">
                    <h4 class="plsech">{{ __('scoreboard::karate_messages.cl_sec_athletes') }} <span id="clCount" style="color:var(--muted);"></span></h4>
                    <div id="clAthletes" class="clath"></div>
                  </section>
                </div>
              </div>
            </div>

            <div class="plfoot">
              <div id="clSay" class="bhint" style="flex:1;"></div>
              <button type="button" id="clSave" class="plsave" disabled>{{ __('scoreboard::karate_messages.pl_save') }}</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ARCADE — pick each corner; the draw's own bout between them is offered. --}}
    <div class="bpane" id="bpaneArcade" role="tabpanel" hidden>
      <div class="arc">
        <div class="arccol">
          <div class="arccap" id="arcCapA"></div>
          <input id="arcFindA" class="bfind" type="search" autocomplete="off" spellcheck="false"
                 style="font-size:21px;padding:10px 14px;"
                 placeholder="{{ __('scoreboard::karate_messages.ctl_find_member') }}"
                 aria-label="{{ __('scoreboard::karate_messages.ctl_find_member') }}">
          <div id="arcListA" class="arclist"></div>
        </div>

        <div class="arcmid">
          <div id="arcVs" class="arcsay"></div>
          <button type="button" id="arcFight" class="arcfight" disabled>{{ __('scoreboard::karate_messages.ctl_fight') }}</button>
          <div id="arcSay" class="arcsay">{{ __('scoreboard::karate_messages.ctl_arcade_hint') }}</div>
        </div>

        <div class="arccol">
          <div class="arccap" id="arcCapB"></div>
          <input id="arcFindB" class="bfind" type="search" autocomplete="off" spellcheck="false"
                 style="font-size:21px;padding:10px 14px;"
                 placeholder="{{ __('scoreboard::karate_messages.ctl_find_member') }}"
                 aria-label="{{ __('scoreboard::karate_messages.ctl_find_member') }}">
          <div id="arcListB" class="arclist"></div>
        </div>
      </div>
    </div>

    @if (! empty($exhibitionsUrl))
    {{-- EXHIBITIONS — link a fought exhibition to the draw bout it really was. --}}
    <div class="bpane" id="bpaneExhibitions" role="tabpanel" hidden>
      <div class="exbar">
        <div id="exSay" class="bhint"></div>
        <button type="button" class="plseg" data-exf="open" aria-pressed="true">{{ __('scoreboard::karate_messages.ctl_ex_filter_open') }}</button>
        <button type="button" class="plseg" data-exf="linked" aria-pressed="false">{{ __('scoreboard::karate_messages.ctl_ex_filter_linked') }}</button>
        <button type="button" class="plseg" data-exf="all" aria-pressed="false">{{ __('scoreboard::karate_messages.ctl_ex_filter_all') }}</button>
      </div>
      <div id="exList" class="bscroll"></div>
    </div>
    @endif
  </div>
</div>

{{-- ── A person's photo on the VS screen ─────────────────────────────────
     Moved and zoomed over the ORIGINAL picture, never cropped — the cropper
     edits the photo itself, this only says how the VS screen places it
     (App\Scoreboard\Models\PhotoVsFrame). The preview is the shared
     partials/vs-preview: the mat's own panel at its own pixels. --}}
<div id="vf" hidden class="scrim" style="z-index:26;background:rgba(0,0,0,.72);">
  <div class="modal" style="width:1420px;gap:18px;">
    <div class="mhead">
      <div id="vfTitle" class="mtitle" style="color:var(--gold);font-size:26px;"></div>
      <button type="button" id="vfClose" class="mclose">✕</button>
    </div>
    <div style="font-size:17px;color:var(--second);line-height:1.4;">{{ __('scoreboard::karate_messages.vf_hint') }}</div>
    <div style="display:grid;grid-template-columns:auto minmax(0,1fr);gap:26px;align-items:start;">
      {{-- The VS screen itself, this person in the red corner; a clear layer
           over it takes the drag, so the page underneath stays the wall's. --}}
      <div id="vfStage" style="position:relative;">
        @include('scoreboard::karate.scoreboard.partials.vs-live', ['frameId' => 'vfLive', 'w' => 1000])
        <div id="vfDrag" style="position:absolute;inset:0;cursor:grab;touch-action:none;user-select:none;"></div>
      </div>
      <div style="display:flex;flex-direction:column;gap:14px;">
        <label style="display:flex;flex-direction:column;gap:8px;background:var(--inset);border:1px solid var(--line);border-radius:12px;padding:14px 16px;">
          <span style="font-size:15px;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);display:flex;justify-content:space-between;">{{ __('scoreboard::karate_messages.vf_zoom') }} <b id="vfZoomVal" style="font-family:'Anton',sans-serif;font-weight:400;font-size:20px;color:var(--gold);"></b></span>
          <input id="vfZoom" type="range" min="1" max="3" step="0.01" value="1" style="width:100%;accent-color:#ffe135;">
        </label>
        <button type="button" id="vfReset" style="font-size:16px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;border-radius:10px;padding:11px 16px;cursor:pointer;background:transparent;color:var(--muted);border:1px solid var(--line-2);">{{ __('scoreboard::karate_messages.pg_reset') }}</button>
        <button type="button" id="vfSave" style="font-size:18px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;border-radius:12px;padding:14px 18px;cursor:pointer;background:var(--green);color:#000;border:none;">{{ __('scoreboard::karate_messages.vf_save') }}</button>
        <div id="vfSay" style="font-size:16px;color:var(--muted);min-height:22px;"></div>
      </div>
    </div>
  </div>
</div>

@php
  $boutsCardWords = [
      'no_queue' => __('scoreboard::karate_messages.ctl_no_queue'),
      'bouts_hint' => __('scoreboard::karate_messages.ctl_bouts_hint'),
      'bout_done' => __('scoreboard::karate_messages.ctl_bout_finished'),
      'waiting_feeder' => __('scoreboard::karate_messages.ctl_waiting_feeder'),
      'no_bouts' => __('scoreboard::karate_messages.ctl_no_bouts'),
      'find_none' => __('scoreboard::karate_messages.ctl_find_none'),
      'find_hint' => __('scoreboard::karate_messages.ctl_find_hint'),
      'reading_draw' => __('scoreboard::karate_messages.ctl_reading_draw'),
      'draw_failed' => __('scoreboard::karate_messages.ctl_draw_failed'),
      'arcade_hint' => __('scoreboard::karate_messages.ctl_arcade_hint'),
      'arc_none' => __('scoreboard::karate_messages.ctl_arc_none'),
      'arc_done' => __('scoreboard::karate_messages.ctl_arc_done'),
      'arc_found' => __('scoreboard::karate_messages.ctl_arc_found'),
      'arc_swapped' => __('scoreboard::karate_messages.ctl_arc_swapped'),
      'arc_same' => __('scoreboard::karate_messages.ctl_arc_same'),
      'male' => __('scoreboard::karate_messages.ctl_gender_male'),
      'female' => __('scoreboard::karate_messages.ctl_gender_female'),
      'ex_hint' => __('scoreboard::karate_messages.ex_fight_hint'),
      'pl_none' => __('scoreboard::karate_messages.pl_none'),
      'pl_reading' => __('scoreboard::karate_messages.ctl_reading_draw'),
      'pl_failed' => __('scoreboard::karate_messages.ctl_draw_failed'),
      'wc_none' => __('scoreboard::karate_messages.pl_wc_none'),
      'wc_add' => __('scoreboard::karate_messages.pl_wc_add'),
      'wc_move' => __('scoreboard::karate_messages.pl_wc_move'),
      'wc_remove' => __('scoreboard::karate_messages.pl_wc_remove'),
      'wc_remove_confirm' => __('scoreboard::karate_messages.pl_wc_remove_confirm'),
      'wc_remove_hint' => __('scoreboard::karate_messages.pl_wc_remove_hint'),
      'wc_cancel' => __('scoreboard::karate_messages.pl_wc_cancel'),
      'wc_add_title' => __('scoreboard::karate_messages.pl_wc_add_title'),
      'wc_move_title' => __('scoreboard::karate_messages.pl_wc_move_title'),
      'wc_find' => __('scoreboard::karate_messages.pl_wc_find'),
      'wc_find_none' => __('scoreboard::karate_messages.pl_wc_find_none'),
      'wc_here' => __('scoreboard::karate_messages.pl_wc_here'),
      'wc_fits' => __('scoreboard::karate_messages.pl_wc_fits'),
      'wc_outside' => __('scoreboard::karate_messages.pl_wc_outside'),
      'wc_outside_say' => __('scoreboard::karate_messages.pl_wc_outside_say'),
      'wc_add_go' => __('scoreboard::karate_messages.pl_wc_add_go'),
      'wc_move_go' => __('scoreboard::karate_messages.pl_wc_move_go'),
      'wc_add_say' => __('scoreboard::karate_messages.pl_wc_add_say'),
      'wc_move_say' => __('scoreboard::karate_messages.pl_wc_move_say'),
      'wc_added' => __('scoreboard::karate_messages.pl_wc_added'),
      'wc_moved' => __('scoreboard::karate_messages.pl_wc_moved'),
      'wc_removed' => __('scoreboard::karate_messages.pl_wc_removed'),
      'wc_failed' => __('scoreboard::karate_messages.pl_wc_failed'),
      'pl_saving' => __('scoreboard::karate_messages.pl_saving'),
      'pl_unattached' => __('scoreboard::karate_messages.pl_unattached'),
      'pl_bad_dob' => __('scoreboard::karate_messages.pl_bad_dob'),
      'pl_no_change' => __('scoreboard::karate_messages.pl_no_change'),
      'pl_search' => __('scoreboard::karate_messages.pl_search'),
      'pl_nat_guess' => __('scoreboard::karate_messages.pl_nat_guess'),
      'cl_none' => __('scoreboard::karate_messages.cl_none'),
      'cl_locked' => __('scoreboard::karate_messages.cl_locked'),
      'cl_logo_new' => __('scoreboard::karate_messages.cl_logo_new'),
      'cl_logo_bad' => __('scoreboard::karate_messages.cl_logo_bad'),
      'cl_platform' => __('scoreboard::karate_messages.cl_platform'),
      'cl_no_athletes' => __('scoreboard::karate_messages.cl_no_athletes'),
      'cl_one' => __('scoreboard::karate_messages.cl_one'),
      'cl_many' => __('scoreboard::karate_messages.cl_many'),
      'ex_matched' => __('scoreboard::karate_messages.ctl_ex_matched'),
      'ex_none' => __('scoreboard::karate_messages.ctl_ex_none'),
      'ex_none_here' => __('scoreboard::karate_messages.ctl_ex_none_here'),
      'ex_st_done' => __('scoreboard::karate_messages.ctl_ex_st_done'),
      'ex_st_running' => __('scoreboard::karate_messages.ctl_ex_st_running'),
      'ex_st_abandoned' => __('scoreboard::karate_messages.ctl_ex_st_abandoned'),
      'ex_st_linked' => __('scoreboard::karate_messages.ctl_ex_st_linked'),
      'ex_exact' => __('scoreboard::karate_messages.ctl_ex_exact'),
      'ex_seat' => __('scoreboard::karate_messages.ctl_ex_seat'),
      'ex_no_fit' => __('scoreboard::karate_messages.ctl_ex_no_fit'),
      'ex_unfinished' => __('scoreboard::karate_messages.ctl_ex_unfinished'),
      'ex_link' => __('scoreboard::karate_messages.ctl_ex_link'),
      'ex_confirm' => __('scoreboard::karate_messages.ctl_ex_confirm'),
      'ex_confirm_hint' => __('scoreboard::karate_messages.ctl_ex_confirm_hint'),
      'ex_linking' => __('scoreboard::karate_messages.ctl_ex_linking'),
      'ex_recorded' => __('scoreboard::karate_messages.ctl_ex_recorded'),
      'ex_other_winner' => __('scoreboard::karate_messages.ctl_ex_other_winner'),
      'ex_draw_score' => __('scoreboard::karate_messages.ctl_ex_draw_score'),
      'ex_confirm_recorded' => __('scoreboard::karate_messages.ctl_ex_confirm_recorded'),
      'ex_disagree_hint' => __('scoreboard::karate_messages.ctl_ex_disagree_hint'),
      'ex_on_draw' => __('scoreboard::karate_messages.ctl_ex_on_draw'),
      'ex_actions' => __('scoreboard::karate_messages.ctl_ex_actions'),
      'ex_cancel' => __('scoreboard::karate_messages.ctl_ex_cancel'),
      'ex_unlink' => __('scoreboard::karate_messages.ctl_ex_unlink'),
      'ex_unlink_confirm' => __('scoreboard::karate_messages.ctl_ex_unlink_confirm'),
      'ex_unlink_hint' => __('scoreboard::karate_messages.ctl_ex_unlink_hint'),
      'ex_pick_open' => __('scoreboard::karate_messages.ctl_ex_pick_open'),
      'ex_pick_close' => __('scoreboard::karate_messages.ctl_ex_pick_close'),
      'ex_pick_find' => __('scoreboard::karate_messages.ctl_ex_pick_find'),
      'ex_pick_hint' => __('scoreboard::karate_messages.ctl_ex_pick_hint'),
      'ex_attach' => __('scoreboard::karate_messages.ctl_ex_attach'),
      'ex_confirm_attach' => __('scoreboard::karate_messages.ctl_ex_confirm_attach'),
  ];
@endphp
<script>
/*
 * window.KarateBoutsCard(opts) → { open(), paint() }
 *
 * opts: el, send, boutSeconds, competitorCell, look, mat, state(), queue(),
 *       catalogueUrl. Built by the console on first use; everything inside the
 *       card is painted here.
 */
window.KarateBoutsCard = window.KarateBoutsCard || function (o) {
  'use strict';

  var el = o.el;
  var LOOK = o.look;
  var MAT = o.mat;
  var CATALOGUE_URL = o.catalogueUrl || null;
  // FIGHT always puts the two picked entrants on the mat as a side
  // exhibition, outside the draw. Deliberately NOT gated on a value baked into
  // the page when it loaded: a tablet that has been open all day would keep a
  // stale answer. The server decides (events.exhibitions) on every press, and
  // a refusal arrives as the console's ordinary error bar.
  var EXHIBITIONS = true;
  // Organisers only — null for an official who may score but not manage.
  var PLAYERS_URL = @json($playersUrl ?? null);
  // The platform's one country list (App\Support\Countries) and flag set.
  var COUNTRIES = @json($countries ?? []);
  var FLAG_DIR = @json(asset(\App\Support\Flag::DIR));
  // Where this device is, as a country (App\Support\GeoCountry) — organisers only.
  var GEO_URL = @json($geoCountryUrl ?? null);
  // The Clubs tab — organisers only.
  var CLUBS_URL = @json($clubsUrl ?? null);
  // The Exhibitions tab — organisers only, and only while exhibitions exist.
  var EX_URL = @json($exhibitionsUrl ?? null);
  // The organiser's draw page, opened in LINK MODE for one exhibition.
  var EX_DRAW_URL = @json($exhibitionDrawUrl ?? null);
  // The console's own draw door (signed in: the event's draw, which opens on
  // ?category=; a table: its tablet draw). A weight-class chip opens it there.
  var PL_DRAW_URL = @json($drawUrl ?? null);
  // The platform's doors for a player's weight classes (person · members ·
  // move) — the signed-in organiser's console only; null makes them read-only.
  var CLASS_DOORS = @json($classDoors ?? null);
  var T = @json($boutsCardWords);

  function text(id, v) { var e = el(id); if (e) e.textContent = v; }
  function card() { return el('bouts'); }
  function isOpen() { var c = card(); return !!(c && !c.hidden); }
  function close() { var c = card(); if (c) c.hidden = true; }

  /* ── One bout, as every list on the card prints it ─────────────────── */
  function boutRow(b) {
    var STATE = o.state();
    var row = document.createElement('button');
    row.type = 'button';
    row.style.cssText = 'display:grid;grid-template-columns:78px 164px 1fr 44px 1fr;align-items:center;gap:14px;font-size:30px;' +
      'background:' + (b.id === STATE.matchId ? '#1f2230' : '#0a0b10') + ';color:#e8eaf2;border:1px solid #1f2230;' +
      'border-radius:12px;padding:20px 22px;cursor:pointer;text-align:left;';

    var no = document.createElement('span');
    no.style.cssText = "font-family:'Anton',sans-serif;color:#ffe135;";
    no.textContent = '#' + (b.number == null ? '—' : b.number);

    var stage = document.createElement('span');
    stage.style.cssText = 'min-width:0;display:flex;flex-direction:column;gap:2px;';
    var st = document.createElement('span');
    st.style.cssText = 'color:#7d8296;text-transform:uppercase;font-size:22px;letter-spacing:.08em;line-height:1.1;';
    st.textContent = b.stage || '';
    var cat = document.createElement('span');
    cat.style.cssText = 'font-size:16px;color:#5c6175;letter-spacing:.04em;overflow-wrap:anywhere;';
    // The mat only when the row can be on a mat this console is not at.
    cat.textContent = (b.division || '') + (b.court && b.court !== MAT ? ' · ' + b.court : '');
    stage.appendChild(st); stage.appendChild(cat);

    var vs = document.createElement('span');
    vs.style.cssText = 'color:#5c6175;text-align:center;';
    vs.textContent = 'vs';

    row.appendChild(no);
    row.appendChild(stage);
    // AKA is mirrored so the two portraits meet either side of the "vs".
    row.appendChild(o.competitorCell(b.aka, b.akaClub, b.akaFlag, b.akaPhoto, b.akaFallback, LOOK.aka.colour, true));
    row.appendChild(vs);
    row.appendChild(o.competitorCell(b.ao, b.aoClub, b.aoFlag, b.aoPhoto, b.aoFallback, LOOK.ao.colour, false));

    // Already fought: listed, never reloaded — that would put a decided
    // result back on the wall as an idle bout about to start.
    if (b.done || !b.runnable) {
      row.style.opacity = b.done ? '.5' : '.45';
      row.style.cursor = 'not-allowed';
      row.disabled = true;
      row.title = b.done ? T.bout_done : T.waiting_feeder;
      return row;
    }

    row.onclick = function () {
      o.send('load', { match_id: b.id, seconds: o.boutSeconds() }).then(close);
    };
    return row;
  }

  function boutsEmpty(host, message) {
    var empty = document.createElement('div');
    empty.style.cssText = 'font-size:21px;color:#5c6175;padding:16px 4px;';
    empty.textContent = message;
    host.appendChild(empty);
  }

  function paintArranged() {
    var host = el('boutsList');
    if (!host) return;

    // A search runs over the whole event's draw (every mat), read once.
    var input = el('arrFind');
    var q = input ? fold(input.value).trim() : '';
    if (q.length >= 2 || /^#?\d+$/.test(q)) {
      if (catState !== 'ready') { catLoad(function () { if (boutsTab === 'arranged') paintArranged(); }); }
      if (catNotReady(host)) return;
      host.textContent = '';

      var num = q.replace(/^#/, '');
      var rows = CAT.bouts.filter(function (b) {
        // A bare number is a bout number, nothing else.
        if (/^\d+$/.test(num)) return String(b.number) === num;
        return boutNames(b).concat([fold(b.division)]).some(function (n) { return n.indexOf(q) !== -1; });
      });
      if (!rows.length) { boutsEmpty(host, T.find_none); return; }
      rows.forEach(function (b) { host.appendChild(boutRow(catBout(b))); });
      return;
    }

    host.textContent = '';

    var queue = o.queue() || [];
    if (!queue.length) { boutsEmpty(host, T.no_queue); return; }

    queue.forEach(function (b) { host.appendChild(boutRow(b)); });
  }

  /* ── The whole draw, read once ─────────────────────────────────────── */
  var CAT = null;
  var catState = 'idle';   // idle | loading | ready | failed
  var arcPick = { a: null, b: null };

  // Where the operator was — tab and open weight class — kept per mat so a
  // reload or a reopen lands them back where they were. Never sent anywhere.
  var MEM_KEY = 'karate.bouts.' + MAT;
  var BOUT_TABS = ['arranged', 'division', 'member', 'clubs', 'arcade', 'exhibitions'];

  function memRead() {
    try {
      var raw = window.localStorage.getItem(MEM_KEY);
      var m = raw ? JSON.parse(raw) : null;
      return (m && typeof m === 'object') ? m : {};
    } catch (e) { return {}; }
  }

  function memWrite(patch) {
    try {
      var m = memRead();
      Object.keys(patch).forEach(function (k) { m[k] = patch[k]; });
      window.localStorage.setItem(MEM_KEY, JSON.stringify(m));
    } catch (e) { /* private mode, a full store — never worth an error */ }
  }

  var boutsTab = (function () {
    var t = memRead().tab;
    return BOUT_TABS.indexOf(t) >= 0 ? t : 'arranged';
  })();
  var divOpen = (function () {
    var d = memRead().division;
    return (d === 0 || d) ? d : null;
  })();

  /** A corner in the console's row shape, rebuilt from the roster. */
  function catBout(b) {
    function side(id, name) {
      var r = (id != null && CAT) ? CAT.roster[String(id)] : null;
      return r ? { name: name || r.name, club: r.club, flag: r.flag, photo: r.photo, fallback: r.fallback }
               : { name: name || null };
    }
    var a = side(b.a, b.a_name), z = side(b.b, b.b_name);
    return { id: b.id, number: b.number, stage: b.stage, division: b.division, court: b.court,
             done: b.done, runnable: b.runnable,
             aka: a.name, akaClub: a.club, akaFlag: a.flag, akaPhoto: a.photo, akaFallback: a.fallback,
             ao: z.name, aoClub: z.club, aoFlag: z.flag, aoPhoto: z.photo, aoFallback: z.fallback };
  }

  function catLoad(then) {
    if (catState === 'ready') { if (then) then(); return; }
    if (catState === 'loading') return;
    if (!CATALOGUE_URL) { catState = 'failed'; if (then) then(); return; }

    catState = 'loading';
    if (then) then();

    fetch(CATALOGUE_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j || !j.bouts) { catState = 'failed'; if (then) then(); return; }
        CAT = j;
        catState = 'ready';
        if (then) then();
      })
      .catch(function () { catState = 'failed'; if (then) then(); });
  }

  function catNotReady(host) {
    if (catState === 'ready') return false;
    host.textContent = '';
    boutsEmpty(host, catState === 'failed' ? T.draw_failed : T.reading_draw);
    return true;
  }

  /* ── Weight class ──────────────────────────────────────────────────── */
  function paintDivisions() {
    var host = el('divList');
    if (!host) return;

    if (divOpen === null) {
      el('divIndex').hidden = false;
      el('divDetail').hidden = true;
    }

    var chip = el('btabDivisionCount');
    if (catNotReady(host)) { if (chip) chip.hidden = true; return; }

    host.textContent = '';
    if (chip) {
      chip.textContent = CAT.divisions.length;
      chip.hidden = CAT.divisions.length === 0;
    }

    if (!CAT.divisions.length) { boutsEmpty(host, T.no_bouts); return; }

    CAT.divisions.forEach(function (d) {
      var row = document.createElement('button');
      row.type = 'button';
      row.className = 'bdiv';

      var left = document.createElement('span');
      left.style.cssText = 'min-width:0;';
      var name = document.createElement('span');
      name.style.cssText = 'overflow-wrap:anywhere;';
      name.textContent = d.label;
      left.appendChild(name);

      if (d.name && d.name !== d.label) {
        var sub = document.createElement('span');
        sub.className = 'sub';
        sub.textContent = d.name;
        left.appendChild(sub);
      }

      // Left to fight / total — how much of the class is still to come.
      var cnt = document.createElement('span');
      cnt.className = 'cnt';
      cnt.textContent = (d.bouts - d.done) + '/' + d.bouts;

      row.appendChild(left);
      row.appendChild(cnt);
      row.onclick = function () { openDivision(d); };
      host.appendChild(row);
    });

    if (divOpen !== null) {
      var want = null;
      CAT.divisions.forEach(function (d) { if (d.id === divOpen) want = d; });

      if (want) { openDivision(want); }
      else {
        divOpen = null;
        memWrite({ division: null });
        el('divIndex').hidden = false;
        el('divDetail').hidden = true;
      }
    }
  }

  function openDivision(d) {
    divOpen = d.id;
    memWrite({ division: d.id });

    el('divIndex').hidden = true;
    el('divDetail').hidden = false;
    text('divTitle', d.label);

    var host = el('divBouts');
    host.textContent = '';

    var rows = CAT.bouts.filter(function (b) { return b.division_id === d.id; });
    if (!rows.length) { boutsEmpty(host, T.no_bouts); return; }

    rows.forEach(function (b) { host.appendChild(boutRow(catBout(b))); });
  }

  /* ── One member's bouts ────────────────────────────────────────────── */
  function fold(v) { return (v == null ? '' : String(v)).toLowerCase(); }

  /** Every name a bout can be found by: the draw's, the roster's, the club. */
  function boutNames(b) {
    var out = [];
    [[b.a, b.a_name], [b.b, b.b_name]].forEach(function (pair) {
      if (pair[1]) out.push(fold(pair[1]));
      var r = pair[0] != null && CAT ? CAT.roster[String(pair[0])] : null;
      if (r) {
        if (r.name) out.push(fold(r.name));
        if (r.club) out.push(fold(r.club));
      }
    });
    return out;
  }

  /* ── Players ─────────────────────────────────────────────────────────
     Read once per opening of the tab, searched locally; one person opens in
     the same pane. Saving sends ONLY what changed — absent is never blank. */
  var PL = null, plState = 'idle', plOpen = null, plData = null, plDraft = null;

  function csrfToken() {
    var m = document.querySelector('meta[name=csrf-token]');
    return m ? m.content : '';
  }

  function plLoad(force) {
    if (!PLAYERS_URL) return;
    if (plState === 'loading' || (plState === 'ready' && !force)) { paintPlayers(); return; }
    plState = 'loading';
    paintPlayers();
    fetch(PLAYERS_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j || !j.players) { plState = 'failed'; paintPlayers(); return; }
        PL = j.players; plState = 'ready'; paintPlayers();
        if (clOpen) paintClubEditor();
      })
      .catch(function () { plState = 'failed'; paintPlayers(); });
  }

  function plFacts(p) {
    var bits = [];
    // The weight class has its own chip (plClasses); without one, the old line.
    if (!(p.classes && p.classes.length) && p.divisions && p.divisions.length) bits.push(p.divisions.join(' · '));
    if (p.age) bits.push(p.age + 'y');
    if (p.weight) bits.push((Math.round(p.weight * 10) / 10) + ' kg');
    return bits.join('  ·  ');
  }

  // The divisions a player is in: a gold chip per weight class, the
  // division's full name in its title — or, `full`, written out beside it.
  function plClasses(p, full) {
    var box = document.createElement('span');
    box.className = 'plclasses';
    (p.classes || []).forEach(function (c) {
      // On the sheet a real button; in a list row (itself a button) a span
      // that keeps its tap from opening the player.
      var chip = document.createElement(full && PL_DRAW_URL ? 'button' : 'span');
      chip.className = 'plclass' + (PL_DRAW_URL ? ' go' : '');
      chip.title = c.name;
      if (PL_DRAW_URL && c.id) {
        if (full) chip.type = 'button';
        chip.onclick = function (e) {
          e.stopPropagation();
          window.location.href = PL_DRAW_URL + (PL_DRAW_URL.indexOf('?') === -1 ? '?' : '&') + 'category=' + encodeURIComponent(c.id);
        };
      }
      var w = document.createElement('b');
      w.textContent = c.weight || c.name;
      chip.appendChild(w);
      if (full && c.weight) {
        var n = document.createElement('span');
        n.textContent = c.name;
        chip.appendChild(n);
      }
      if (PL_DRAW_URL && c.id) chip.appendChild(document.createTextNode('↗'));
      box.appendChild(chip);
    });
    return box;
  }

  function paintPlayers() {
    var host = el('plList');
    if (!host) return;
    host.textContent = '';

    if (plState !== 'ready') { boutsEmpty(host, plState === 'failed' ? T.pl_failed : T.pl_reading); return; }

    var q = fold(el('plFind') ? el('plFind').value : '').trim();
    var rows = PL.filter(function (p) {
      return !q || fold(p.name).indexOf(q) !== -1 || fold(p.club).indexOf(q) !== -1
        || fold((p.divisions || []).join(' ')).indexOf(q) !== -1
        || fold((p.classes || []).map(function (c) { return c.name; }).join(' ')).indexOf(q) !== -1;
    });
    if (!rows.length) { boutsEmpty(host, T.pl_none); return; }

    rows.forEach(function (p) {
      var row = document.createElement('button');
      row.type = 'button';
      row.className = 'plrowbtn';
      var colour = p.gender === 'Female' ? '#ff6fae' : (p.gender === 'Male' ? '#5aa9ff' : '#7d8296');
      var cell = o.competitorCell(p.name, p.club, p.flag, p.photo, p.fallback, colour, false);
      cell.style.flex = '1';
      row.appendChild(cell);
      if (p.classes && p.classes.length) row.appendChild(plClasses(p, false));
      var f = document.createElement('span');
      f.style.cssText = 'font-size:16px;color:var(--muted);text-align:right;max-width:42%;overflow-wrap:anywhere;';
      f.textContent = plFacts(p);
      row.appendChild(f);
      row.setAttribute('aria-current', p.registration === plOpen ? 'true' : 'false');
      row.setAttribute('data-reg', p.registration);
      row.onclick = function () { plOpenPlayer(p.registration); };
      host.appendChild(row);
    });
  }

  /* ── A player's weight classes ───────────────────────────────────────
     Cards under the name. Add and Move open a picker over the sheet listing
     every division with whether this person fits it — judged by the server
     (the participant card's enrolment sheet), never here. Writes go through
     the platform's own doors: the division's members (add / remove) and
     LiveMove (move — the bracket keeps its shape). Each re-reads the list. */
  var WC_PERSON = '00000000-0000-4000-8000-000000000000', WC_DIVISION = '987654321';
  var wcArm = null, wcPick = null, wcBusy = false;

  function wcNode(tag, cls, txt) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (txt != null) n.textContent = txt;
    return n;
  }

  function wcBtn(cls, label, fn) {
    var b = wcNode('button', 'exbtn' + (cls ? ' ' + cls : ''), label);
    b.type = 'button';
    b.onclick = fn;
    return b;
  }

  // "-45 kg" out of a division's name or bounds, as the cards print it.
  function wcWeight(s) {
    var m = String(s || '').match(/[+\-\u2212]\s?\d+(?:\.\d+)?\s?kg/i);
    return m ? m[0] : null;
  }

  function plPaintClasses(p) {
    var host = el('plClasses');
    host.hidden = false;
    host.textContent = '';
    (p.classes || []).forEach(function (c) {
      var armed = wcArm === c.registration;
      var card = wcNode('div', 'wccard' + (armed ? ' arm' : '') + (wcBusy ? ' busy' : ''));
      card.appendChild(wcNode('span', 'w', c.weight || '—'));
      var n = wcNode('span', 'n', c.name);
      if (armed) n.appendChild(wcNode('small', null, T.wc_remove_hint));
      card.appendChild(n);
      var acts = wcNode('span', 'acts');
      if (armed) {
        acts.appendChild(wcBtn('ghost', T.wc_cancel, function () { wcArm = null; paintEditor(); }));
        acts.appendChild(wcBtn('danger arm', T.wc_remove_confirm, function () { wcRemove(p, c); }));
      } else {
        if (CLASS_DOORS.move) acts.appendChild(wcBtn('', T.wc_move, function () { wcOpen(p, c); }));
        acts.appendChild(wcBtn('danger', T.wc_remove, function () { wcArm = c.registration; paintEditor(); }));
      }
      card.appendChild(acts);
      host.appendChild(card);
    });
    if (!(p.classes || []).length) host.appendChild(wcNode('span', 'wcnone', T.wc_none));
    var add = wcNode('button', 'wcadd');
    add.type = 'button';
    add.disabled = wcBusy;
    add.appendChild(wcNode('span', 'plus', '+'));
    add.appendChild(document.createTextNode(T.wc_add));
    add.onclick = function () { wcOpen(p, null); };
    host.appendChild(add);
  }

  function wcOpen(p, from) {
    wcArm = null;
    wcPick = { p: p, from: from, groups: null, entry: null, note: '', chosen: null, state: 'loading' };
    wcPaintPick();
    fetch(CLASS_DOORS.person.replace(WC_PERSON, encodeURIComponent(p.uuid)), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!wcPick || wcPick.p !== p) return;
        if (!j || !j.groups) { wcPick.state = 'failed'; wcPaintPick(); return; }
        wcPick.groups = j.groups; wcPick.entry = j.entry; wcPick.note = j.note || ''; wcPick.state = 'ready';
        wcPaintPick();
      })
      .catch(function () { if (wcPick && wcPick.p === p) { wcPick.state = 'failed'; wcPaintPick(); } });
  }

  function wcClose() {
    wcPick = null;
    var h = el('plClassPick');
    if (!h) return;
    h.hidden = true;
    h.textContent = '';
  }

  function wcPaintPick() {
    var host = el('plClassPick');
    if (!wcPick) { wcClose(); return; }
    var k = wcPick;
    host.hidden = false;
    host.textContent = '';

    var head = wcNode('div', 'head');
    var h = wcNode('h4', null, k.from ? T.wc_move_title.replace(':weight', k.from.weight || k.from.name) : T.wc_add_title);
    var who = [k.p.name];
    if (k.p.weight) who.push((Math.round(k.p.weight * 10) / 10) + ' kg');
    if (k.p.age) who.push(k.p.age + 'y');
    h.appendChild(wcNode('small', null, who.join('  ·  ')));
    head.appendChild(h);
    head.appendChild(wcBtn('ghost', T.wc_cancel, wcClose));
    host.appendChild(head);

    var find = document.createElement('input');
    find.type = 'search';
    find.className = 'bfind';
    find.autocomplete = 'off';
    find.spellcheck = false;
    find.placeholder = T.wc_find;
    host.appendChild(find);

    var list = wcNode('div', 'wcopts');
    host.appendChild(list);

    var foot = wcNode('div', 'foot');
    var msg = wcNode('div', 'msg', k.note);
    msg.id = 'wcSay';
    foot.appendChild(msg);
    var go = wcBtn('primary', k.from ? T.wc_move_go : T.wc_add_go, wcCommit);
    go.id = 'wcGo';
    go.disabled = true;
    foot.appendChild(go);
    host.appendChild(foot);

    if (k.state !== 'ready') { boutsEmpty(list, k.state === 'failed' ? T.pl_failed : T.pl_reading); return; }
    find.addEventListener('input', function () { wcFill(list, find.value); });
    wcFill(list, '');
    setTimeout(function () { try { find.focus(); } catch (e) {} }, 0);
  }

  function wcFill(list, q) {
    var k = wcPick;
    list.textContent = '';
    q = fold(q).trim();
    // The fits first, then the rest, then the ones they are already in.
    var rank = function (g) { return g.enrolled ? 2 : (g.compatible ? 0 : 1); };
    var rows = k.groups.filter(function (g) {
      return !q || fold(g.label).indexOf(q) !== -1 || fold(g.detail || '').indexOf(q) !== -1;
    }).map(function (g, i) { return [g, i]; }).sort(function (a, b) {
      return rank(a[0]) - rank(b[0]) || a[1] - b[1];
    });
    if (!rows.length) { boutsEmpty(list, T.wc_find_none); return; }

    rows.forEach(function (x) {
      var g = x[0];
      var b = wcNode('button', 'wcopt' + (g.enrolled ? ' here' : (g.compatible ? '' : ' out')));
      b.type = 'button';
      b.disabled = g.enrolled || wcBusy;
      b.setAttribute('aria-pressed', k.chosen === g.id ? 'true' : 'false');
      b.appendChild(wcNode('span', 'tag', g.enrolled ? T.wc_here : (g.compatible ? T.wc_fits : T.wc_outside)));
      var w = wcWeight(g.label) || wcWeight(g.detail);
      if (w) b.appendChild(wcNode('span', 'w', w));
      b.appendChild(wcNode('span', 'nm', g.label));
      if (g.detail) b.appendChild(wcNode('span', 'dt', g.detail));
      if (g.reason && !g.enrolled) b.appendChild(wcNode('span', 'why', g.reason));
      b.onclick = function () {
        k.chosen = g.id;
        list.querySelectorAll('.wcopt').forEach(function (o2) { o2.setAttribute('aria-pressed', 'false'); });
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

  // What Confirm will do, said before it is pressed.
  function wcSayChoice() {
    var k = wcPick, g = wcChosen(), say = el('wcSay');
    if (!g) return;
    var line = (k.from ? T.wc_move_say.replace(':from', k.from.name) : T.wc_add_say)
      .replace(':who', k.p.name).replace(':to', g.label);
    if (!g.compatible && g.reason) line += ' ' + T.wc_outside_say.replace(':why', g.reason);
    if (k.note) line += ' ' + k.note;
    say.style.color = g.compatible ? '' : 'var(--gold)';
    say.textContent = line;
    el('wcGo').disabled = wcBusy;
  }

  function wcSend(method, url, body) {
    return fetch(url, {
      method: method,
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (d) {
        if (!r.ok || !d.success) {
          var msg = d.message || T.wc_failed;
          if (d.errors) { var f = Object.keys(d.errors)[0]; if (f) msg = d.errors[f][0]; }
          throw new Error(msg);
        }
        return d;
      });
    });
  }

  function wcCommit() {
    var k = wcPick, g = wcChosen();
    if (!k || !g || wcBusy) return;
    wcBusy = true;
    el('wcGo').disabled = true;
    var say = el('wcSay');
    say.style.color = '';
    say.textContent = T.pl_saving;
    var req = k.from
      ? wcSend('POST', CLASS_DOORS.move, { entry: k.from.registration, to: g.id })
      : wcSend('PUT', CLASS_DOORS.members.replace(WC_DIVISION, g.id), { add: [k.entry] });
    req.then(function (d) {
      wcBusy = false;
      wcClose();
      wcDone(k.p.uuid, (k.from ? T.wc_moved : T.wc_added).replace(':name', g.label));
    }).catch(function (err) {
      wcBusy = false;
      if (wcPick !== k) return;
      say.style.color = 'var(--aka-ink)';
      say.textContent = err.message;
      el('wcGo').disabled = false;
    });
  }

  function wcRemove(p, c) {
    if (wcBusy) return;
    wcBusy = true;
    paintEditor();
    wcSend('PUT', CLASS_DOORS.members.replace(WC_DIVISION, c.id), { remove: [c.registration] })
      .then(function () {
        wcBusy = false;
        wcArm = null;
        wcDone(p.uuid, T.wc_removed.replace(':name', c.name));
      })
      .catch(function (err) {
        wcBusy = false;
        paintEditor();
        var say = el('plSay'); say.style.color = 'var(--aka-ink)'; say.textContent = err.message;
      });
  }

  // Re-read the list (the classes and the rows behind them moved), then
  // repaint this person — reopened if the row they were opened by is gone.
  function wcDone(uuid, message) {
    catStale();
    fetch(PLAYERS_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (j && j.players) PL = j.players;
        paintPlayers();
        var me = (PL || []).filter(function (x) { return x.uuid === uuid; })[0];
        if (me && me.registration !== plOpen) plOpenPlayer(me.registration);
        else if (plData) paintEditor();
        var say = el('plSay'); say.style.color = 'var(--green)'; say.textContent = message;
      })
      .catch(function () { paintPlayers(); });
  }

  function plShowIndex() {
    plOpen = null; plData = null;
    wcArm = null; wcClose(); el('plClasses').hidden = true;
    el('plEdit').hidden = true;
    el('plEmpty').hidden = false;
    paintPlayers();
  }

  function plOpenPlayer(id) {
    plOpen = id;
    ddOpen = null;
    wcClose();
    wcArm = null;
    el('plClasses').hidden = true;
    el('plEmpty').hidden = true;
    el('plEdit').hidden = false;
    // The chosen row lights up; the list stays where the operator left it.
    document.querySelectorAll('#plList .plrowbtn').forEach(function (r, i) {
      r.setAttribute('aria-current', 'false');
    });
    var hit = document.querySelector('#plList .plrowbtn[data-reg="' + id + '"]');
    if (hit) hit.setAttribute('aria-current', 'true');
    text('plTitle', T.pl_reading);
    text('plSay', '');
    el('plSave').disabled = true;

    fetch(PLAYERS_URL + '/' + id, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j || !j.player || plOpen !== id) { if (plOpen === id) text('plTitle', T.pl_failed); return; }
        plData = j;
        plDraft = plInitial(j.player);
        text('plNatNote', '');
        paintEditor();
        plSeedNationality(id);
      })
      .catch(function () { if (plOpen === id) text('plTitle', T.pl_failed); });
  }

  function plInitial(p) {
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
    text('plTitle', p.name || '');
    // Which division(s) they are in, under the name — read from the list row.
    var listed = (PL || []).filter(function (x) { return x.registration === plOpen; })[0];
    if (CLASS_DOORS && listed && listed.uuid) {
      plPaintClasses(listed);
    } else if (listed && listed.classes && listed.classes.length) {
      var cls = plClasses(listed, true);
      cls.style.marginTop = '8px';
      el('plTitle').appendChild(cls);
    }

    // Photo
    var ph = el('plPhoto');
    ph.style.backgroundImage = 'url("' + (p.photo || p.fallback) + '")';
    ph.style.opacity = p.photo ? '1' : '.6';
    el('plPhoto').disabled = el('plPhotoBtn').disabled = !editable('photo');
    // Framing for the VS screen: only when there is a photo the VS screen shows, and only for whoever may change it.
    el('plVsBtn').hidden = !(editable('photo') && p.vs_photo);

    // Why a field is locked, beside it.
    ['photo', 'name', 'club', 'nationality', 'birthdate', 'gender', 'weight', 'belt_colour'].forEach(function (f) {
      text('plWhy-' + f, j.fields[f] && !j.fields[f].editable ? (j.fields[f].reason || '') : '');
    });

    var nm = el('plName'); nm.value = d.name; nm.disabled = !editable('name');

    // Club — a dropdown with each club's crest.
    plDropdown('plClub', [{ value: '', label: T.pl_unattached, logo: null }].concat((j.clubs || []).map(function (c) {
      return { value: c.slug, label: c.name, logo: c.logo };
    })), d.club, !editable('club'), false, function (v) { d.club = v; paintEditor(); });

    // Nationality — the shared country picker holds its own state; tell it
    // what this person is.
    window.dispatchEvent(new CustomEvent('pl-nat-set', { detail: d.nat }));
    el('plNatWrap').classList.toggle('locked', !editable('nationality'));

    // Date of birth OR age.
    var dobOn = editable('birthdate');
    document.querySelectorAll('#plEdit .plseg').forEach(function (b) {
      b.setAttribute('aria-pressed', d.mode === b.getAttribute('data-mode') ? 'true' : 'false');
      b.disabled = !dobOn;
    });
    el('plDobBox').hidden = d.mode !== 'dob';
    el('plAgeBox').hidden = d.mode !== 'age';
    [['plDobD', 'dobD'], ['plDobM', 'dobM'], ['plDobY', 'dobY']].forEach(function (x) {
      el(x[0]).value = d[x[1]]; el(x[0]).disabled = !dobOn;
    });
    el('plAge').value = d.age;
    el('plAge').disabled = !dobOn;

    // The shared toggle holds its own state; tell it what this person is.
    window.dispatchEvent(new CustomEvent('pl-gender-set', { detail: d.gender }));
    el('plGender').classList.toggle('locked', !editable('gender'));

    var w = el('plWeight'); w.value = d.weight; w.disabled = !editable('weight');

    // Belt — one dropdown: each belt of this event's system, and each of its
    // degrees, drawn as the belt.
    var beltOpts = [{ value: '|', label: '—', belt: null }];
    (j.belts || []).forEach(function (r) {
      var grades = r.grades || [];
      if (!grades.length) {
        beltOpts.push({ value: r.value + '|', label: r.label, belt: r.icon || null });
        return;
      }
      grades.forEach(function (g) {
        beltOpts.push({ value: r.value + '|' + g, label: r.label + ' · ' + g, belt: r.icon || null });
      });
    });
    var curBelt = d.belt_colour + '|' + d.belt_grade;
    // A belt on file whose degree is not one this system lists still shows as itself.
    if (d.belt_colour && !beltOpts.some(function (b) { return b.value === curBelt; })) {
      var known = (j.belts || []).filter(function (r) { return r.value === d.belt_colour; })[0];
      beltOpts.push({ value: curBelt, label: (known ? known.label : d.belt_colour) + (d.belt_grade ? ' · ' + d.belt_grade : ''),
                      belt: known ? known.icon : null });
    }
    plDropdown('plBelt', beltOpts, curBelt, !editable('belt_colour'), true, function (v) {
      var parts = v.split('|');
      d.belt_colour = parts[0] || '';
      d.belt_grade = parts.slice(1).join('|') || '';
      paintEditor();
    });

    el('plSave').disabled = !Object.keys(plChanges() || {}).length;
  }

  /*
   * A dropdown that opens in the flow under its trigger. Options carry a crest
   * (`logo`) or a flag (`flag`). Only one is open at a time; it stays open
   * across a repaint and closes when something is picked.
   */
  var ddOpen = null;

  function flagBg(code) {
    return /^[a-z]{2}$/.test(code || '') ? 'url("' + FLAG_DIR + '/' + code + '.svg")' : 'none';
  }

  function ddIcon(opt) {
    if (opt.belt !== undefined) {
      // The platform's belt, drawn from the shared belt-icon symbol with the
      // server's colours — never re-drawn here.
      var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('class', 'plbelt');
      svg.setAttribute('viewBox', '0 0 24 24');
      if (opt.belt) {
        svg.setAttribute('style', opt.belt);
        var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        use.setAttribute('href', '#belt-icon');
        svg.appendChild(use);
      } else { svg.style.visibility = 'hidden'; }
      return svg;
    }
    var i = document.createElement('span');
    if (opt.flag !== undefined) {
      i.className = 'plflag';
      i.style.backgroundImage = flagBg(opt.flag);
      if (!opt.flag) i.style.visibility = 'hidden';
    } else {
      i.className = 'plico';
      if (opt.logo) { i.style.backgroundImage = 'url("' + opt.logo + '")'; }
      else if (opt.value) {
        // No crest on file: the club's initial, so the row still reads as a club.
        i.textContent = (opt.label || '?').trim().charAt(0).toUpperCase();
        i.style.cssText += "border-radius:50%;background:var(--fill);display:flex;align-items:center;justify-content:center;font-family:'Anton',sans-serif;font-size:16px;color:var(--gold);";
      } else { i.style.visibility = 'hidden'; }
    }
    return i;
  }

  function plDropdown(hostId, options, value, disabled, searchable, onPick) {
    var host = el(hostId);
    if (!host) return;
    var keepQuery = host.querySelector('input') ? host.querySelector('input').value : '';
    host.textContent = '';

    var cur = options.filter(function (o2) { return o2.value === value; })[0] || options[0];
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'plddbtn';
    btn.disabled = disabled;
    btn.setAttribute('aria-expanded', ddOpen === hostId ? 'true' : 'false');
    btn.appendChild(ddIcon(cur));
    var lab = document.createElement('span');
    lab.style.cssText = 'min-width:0;overflow-wrap:anywhere;';
    lab.textContent = cur.label;
    btn.appendChild(lab);
    var chev = document.createElement('span');
    chev.className = 'chev';
    chev.textContent = '⌄';
    btn.appendChild(chev);
    btn.onclick = function () { ddOpen = ddOpen === hostId ? null : hostId; paintEditor(); };
    host.appendChild(btn);

    if (ddOpen !== hostId || disabled) return;

    var panel = document.createElement('div');
    panel.className = 'plddpanel';
    var list = document.createElement('div');
    list.className = 'plddlist';

    function fill(q) {
      list.textContent = '';
      q = fold(q).trim();
      options.filter(function (o2) { return !q || fold(o2.label).indexOf(q) !== -1; }).slice(0, 300).forEach(function (o2) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'plddopt';
        b.setAttribute('aria-selected', o2.value === value ? 'true' : 'false');
        b.appendChild(ddIcon(o2));
        var t = document.createElement('span');
        t.textContent = o2.label;
        b.appendChild(t);
        b.onclick = function () { ddOpen = null; onPick(o2.value); };
        list.appendChild(b);
      });
    }

    if (searchable) {
      var q = document.createElement('input');
      q.type = 'search';
      q.className = 'plin';
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

    // Near the bottom of the form the list opens UPWARDS instead, so it is
    // never cut off by the form's own edge.
    var frame = host.closest('.modal');
    host.classList.remove('up');
    if (frame && panel.getBoundingClientRect().bottom > frame.getBoundingClientRect().bottom - 16) host.classList.add('up');

    var sel = list.querySelector('[aria-selected="true"]');
    if (sel) sel.scrollIntoView({ block: 'nearest' });
  }

  /*
   * A blank nationality is seeded from WHERE THIS DEVICE IS — its location
   * (GPS, or the Wi-Fi/network position the browser gives), turned into a
   * country by our own server. Only ever a blank: a nationality already on
   * file is never touched, and the guess is a change like any other — nothing
   * is stored until Save. The answer is remembered for a week, so it is asked
   * once, not per player.
   */
  function plGeoCountry() {
    return new Promise(function (resolve) {
      try {
        var raw = JSON.parse(localStorage.getItem('takeoneCountry') || 'null');
        if (raw && raw.code && (Date.now() - raw.at) < 7 * 864e5) return resolve(raw.code);
      } catch (e) { /* storage blocked — ask */ }

      if (!GEO_URL || !navigator.geolocation) return resolve(null);

      navigator.geolocation.getCurrentPosition(function (pos) {
        fetch(GEO_URL + '?lat=' + encodeURIComponent(pos.coords.latitude) + '&lng=' + encodeURIComponent(pos.coords.longitude),
          { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) {
            var code = d && d.country ? String(d.country).toUpperCase() : null;
            if (code) { try { localStorage.setItem('takeoneCountry', JSON.stringify({ code: code, at: Date.now() })); } catch (e) {} }
            resolve(code);
          })
          .catch(function () { resolve(null); });
      }, function () { resolve(null); }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 600000 });
    });
  }

  function plSeedNationality(id) {
    if (!plData || plData.player.nationality || !editable('nationality')) return;
    plGeoCountry().then(function (code) {
      if (!code || plOpen !== id || !plDraft || plDraft.nat) return;
      plDraft.nat = code;
      window.dispatchEvent(new CustomEvent('pl-nat-set', { detail: code }));
      text('plNatNote', T.pl_nat_guess);
      el('plSave').disabled = !Object.keys(plChanges() || {}).length;
    });
  }

  /* ── Clubs ───────────────────────────────────────────────────────────
     The event's club list, and one club's sheet. A club with its own
     account is shown, never edited — it keeps its own name, crest and
     country. Athletes come from the Players list, matched by club name. */
  var CL = null, clState = 'idle', clOpen = null, clDraft = null, clLogoData = null;

  function clLoad(force) {
    if (!CLUBS_URL) return;
    if (PLAYERS_URL && plState !== 'ready') plLoad();
    if (clState === 'loading' || (clState === 'ready' && !force)) { paintClubs(); return; }
    clState = 'loading';
    paintClubs();
    fetch(CLUBS_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j || !j.clubs) { clState = 'failed'; paintClubs(); return; }
        CL = j.clubs; clState = 'ready'; paintClubs();
        if (clOpen) clOpenClub(clOpen);
      })
      .catch(function () { clState = 'failed'; paintClubs(); });
  }

  function clMark(c) {
    var m = document.createElement('span');
    m.className = 'clmark';
    if (c.logo) m.style.backgroundImage = 'url("' + c.logo + '")';
    else { m.textContent = (c.name || '?').trim().charAt(0).toUpperCase(); m.style.background = 'var(--fill)'; }
    return m;
  }

  function paintClubs() {
    var host = el('clList');
    if (!host) return;
    host.textContent = '';
    if (clState !== 'ready') { boutsEmpty(host, clState === 'failed' ? T.pl_failed : T.pl_reading); return; }

    var q = fold(el('clFind') ? el('clFind').value : '').trim();
    var rows = CL.filter(function (c) { return !q || fold(c.name).indexOf(q) !== -1; });
    if (!rows.length) { boutsEmpty(host, T.cl_none); return; }

    rows.forEach(function (c) {
      var row = document.createElement('button');
      row.type = 'button';
      row.className = 'plrowbtn';
      row.setAttribute('aria-current', c.uuid === clOpen ? 'true' : 'false');
      row.appendChild(clMark(c));

      var mid = document.createElement('span');
      mid.style.cssText = 'flex:1;min-width:0;display:flex;flex-direction:column;gap:3px;';
      var nm = document.createElement('span');
      nm.style.cssText = 'overflow-wrap:anywhere;';
      nm.textContent = c.name;
      var sub = document.createElement('span');
      sub.style.cssText = 'display:flex;align-items:center;gap:8px;font-size:16px;color:var(--muted);';
      if (c.country) {
        var fl = document.createElement('span');
        fl.className = 'plflag';
        fl.style.cssText += 'width:24px;height:17px;';
        fl.style.backgroundImage = flagBg(c.country.toLowerCase());
        sub.appendChild(fl);
      }
      var cnt = document.createElement('span');
      cnt.textContent = c.athletes + ' ' + (c.athletes === 1 ? T.cl_one : T.cl_many);
      sub.appendChild(cnt);
      mid.appendChild(nm); mid.appendChild(sub);
      row.appendChild(mid);

      if (c.on_platform) {
        var b = document.createElement('span');
        b.className = 'clbadge';
        b.textContent = T.cl_platform;
        row.appendChild(b);
      }

      row.onclick = function () { clOpenClub(c.uuid); };
      host.appendChild(row);
    });
  }

  function clOpenClub(uuid) {
    var c = (CL || []).filter(function (x) { return x.uuid === uuid; })[0];
    if (!c) return;
    clOpen = uuid;
    clLogoData = null;
    clDraft = { name: c.name || '', nat: c.country || '', insta: c.instagram ? c.instagram.replace(/^https?:\/\/(www\.)?instagram\.com\//, '@') : '' };
    el('clEmpty').hidden = true;
    el('clEdit').hidden = false;
    text('clSay', '');
    text('clLogoNote', '');
    paintClubs();
    paintClubEditor();
  }

  function clCurrent() { return (CL || []).filter(function (x) { return x.uuid === clOpen; })[0]; }

  function paintClubEditor() {
    var c = clCurrent();
    if (!c) return;
    var on = !!c.editable;
    text('clTitle', c.name);

    var lg = el('clLogo');
    lg.style.backgroundImage = clLogoData ? 'url("' + clLogoData + '")' : (c.logo ? 'url("' + c.logo + '")' : 'none');
    lg.disabled = !on;
    el('clLogoBtn').hidden = !on;

    el('clName').value = clDraft.name; el('clName').disabled = !on;
    el('clInsta').value = clDraft.insta; el('clInsta').disabled = !on;
    window.dispatchEvent(new CustomEvent('cl-nat-set', { detail: clDraft.nat }));
    el('clNatWrap').classList.toggle('locked', !on);
    text('clLocked', on ? '' : T.cl_locked);

    // Its athletes, from the Players list.
    var host = el('clAthletes');
    host.textContent = '';
    var mine = (PL || []).filter(function (p) { return fold(p.club) === fold(c.name); });
    text('clCount', '· ' + (PL ? mine.length : c.athletes));
    if (PL && !mine.length) boutsEmpty(host, T.cl_no_athletes);
    mine.forEach(function (p) {
      var r = document.createElement('button');
      r.type = 'button';
      r.className = 'clathrow';
      var colour = p.gender === 'Female' ? '#ff6fae' : (p.gender === 'Male' ? '#5aa9ff' : '#7d8296');
      var cell = o.competitorCell(p.name, (p.divisions || []).join(' · '), p.flag, p.photo, p.fallback, colour, false);
      cell.style.flex = '1';
      r.appendChild(cell);
      // Tap an athlete to open them on the Players tab.
      r.onclick = function () { show('member'); setTimeout(function () { plOpenPlayer(p.registration); }, 0); };
      host.appendChild(r);
    });

    el('clSave').disabled = !on || !clChanged();
  }

  function clChanged() {
    var c = clCurrent();
    if (!c || !clDraft) return false;
    var insta0 = c.instagram ? c.instagram.replace(/^https?:\/\/(www\.)?instagram\.com\//, '@') : '';
    return clDraft.name.trim() !== (c.name || '') || clDraft.nat !== (c.country || '')
      || clDraft.insta.trim() !== insta0 || !!clLogoData;
  }

  function clSaveNow() {
    var c = clCurrent();
    if (!c || !c.editable || !clChanged()) return;
    var body = { name: clDraft.name.trim(), mat: MAT };
    if (clDraft.nat !== (c.country || '')) body.country = clDraft.nat || null;
    body.instagram = clDraft.insta.trim() || null;
    if (clLogoData) body.logo = clLogoData;

    var say = el('clSay');
    say.style.color = ''; say.textContent = T.pl_saving;
    el('clSave').disabled = true;

    fetch(CLUBS_URL + '/' + c.uuid, {
      method: 'PUT',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        if (!d.success) {
          var msg = d.message || T.pl_failed;
          if (d.errors) { var k = Object.keys(d.errors)[0]; if (k) msg = d.errors[k][0]; }
          throw new Error(msg);
        }
        var oldName = c.name;
        CL = CL.map(function (x) { return x.uuid === d.club.uuid ? d.club : x; });
        // The players of a renamed club carry the new name too.
        if (PL && d.club.name !== oldName) {
          PL = PL.map(function (p) { return fold(p.club) === fold(oldName) ? Object.assign({}, p, { club: d.club.name }) : p; });
        }
        catStale();
        clLogoData = null;
        clOpenClub(d.club.uuid);
        say.style.color = 'var(--green)'; say.textContent = d.message || '';
      })
      .catch(function (err) {
        say.style.color = 'var(--aka-ink)'; say.textContent = err.message;
        el('clSave').disabled = false;
      });
  }

  /* A new crest, read from a file and kept transparent: drawn onto a canvas
     no larger than 512px and sent as PNG. The server checks the real bytes. */
  function clPickLogo(file) {
    if (!file) return;
    if (file.size > 10 * 1024 * 1024) { text('clLogoNote', T.cl_logo_bad); return; }
    var url = URL.createObjectURL(file);
    var img = new Image();
    img.onload = function () {
      var k = Math.min(1, 512 / Math.max(img.naturalWidth, img.naturalHeight));
      var cv = document.createElement('canvas');
      cv.width = Math.max(1, Math.round(img.naturalWidth * k));
      cv.height = Math.max(1, Math.round(img.naturalHeight * k));
      cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height);
      clLogoData = cv.toDataURL('image/png');
      try { URL.revokeObjectURL(url); } catch (e) {}
      text('clLogoNote', T.cl_logo_new);
      paintClubEditor();
    };
    img.onerror = function () { text('clLogoNote', T.cl_logo_bad); };
    img.src = url;
  }

  /** What differs from what was loaded — the only fields that are sent. */
  function plChanges() {
    if (!plData) return null;
    var a = plInitial(plData.player), d = plDraft, out = {};
    if (d.name.trim() !== a.name) out.name = d.name.trim();
    if (d.club !== a.club) out.club = d.club || null;
    var hadDob = !!a.dobY;
    if (d.mode === 'dob') {
      if (d.dobY + d.dobM + d.dobD !== a.dobY + a.dobM + a.dobD) {
        out.birthdate = (d.dobY || d.dobM || d.dobD)
          ? d.dobY + '-' + ('0' + d.dobM).slice(-2) + '-' + ('0' + d.dobD).slice(-2) : null;
      }
    } else {
      // Switching to an age clears the date of birth in the same save.
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

  function plSend(body, done) {
    text('plSay', T.pl_saving);
    el('plSave').disabled = true;
    return fetch(PLAYERS_URL + '/' + plOpen, {
      method: 'PUT',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      credentials: 'same-origin',
      body: JSON.stringify(Object.assign({ mat: MAT }, body)),
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        if (!d.success) {
          var msg = d.message || T.pl_failed;
          if (d.errors) { var k = Object.keys(d.errors)[0]; if (k) msg = d.errors[k][0]; }
          throw new Error(msg);
        }
        var say = el('plSay'); say.style.color = 'var(--green)'; say.textContent = d.message || '';
        // The list row, and the draw's names and clubs, are now stale.
        if (PL && d.player) {
          PL = PL.map(function (p) { return p.registration === d.player.registration ? Object.assign({}, p, d.player) : p; });
        }
        catStale();
        paintPlayers();
        if (done) done(d);
        plOpenPlayer(plOpen);
      })
      .catch(function (err) {
        var say = el('plSay'); say.style.color = 'var(--aka-ink)'; say.textContent = err.message;
        el('plSave').disabled = false;
      });
  }

  function plSave() {
    var c = plChanges();
    if (!c || !Object.keys(c).length) { text('plSay', T.pl_no_change); return; }
    if ('birthdate' in c && c.birthdate && !/^\d{4}-\d{2}-\d{2}$/.test(c.birthdate)) {
      var say = el('plSay'); say.style.color = 'var(--aka-ink)'; say.textContent = T.pl_bad_dob; return;
    }
    plSend(c);
  }

  /* ── A person's photo on the VS screen (PhotoVsFrame) ──────────────────
     Zoom and a focal point over the original: background-size {z*100}% auto
     at {x}% {y}% on the mat's 1075x1080 panel — zoom 1 at 50% 12% is the
     panel's own framing. Dragging moves the PICTURE under the finger, so the
     focal point moves the other way, by the share of the overflow dragged. */
  var vf = null, vfLive = null;
  function vfPaint() {
    if (vfLive) vfLive.send({ aka: { vs_frame: { z: vf.z, x: vf.x, y: vf.y } } });
    el('vfZoom').value = vf.z;
    el('vfZoomVal').textContent = Math.round(vf.z * 100) + '%';
  }
  function vfSay(msg, tone) {
    var s2 = el('vfSay');
    s2.textContent = msg || '';
    s2.style.color = tone === 'ok' ? 'var(--green)' : (tone === 'bad' ? 'var(--aka-ink)' : 'var(--muted)');
  }
  function vfOpen() {
    var p = plData && plData.player;
    if (!p || !p.vs_photo) return;
    var f = p.vs_frame || {};
    vf = { id: plOpen, url: p.vs_photo, ratio: 4 / 3,
           z: isFinite(+f.z) ? +f.z : 1, x: isFinite(+f.x) ? +f.x : 50, y: isFinite(+f.y) ? +f.y : 12 };
    // The real shape of the photo, so a drag moves it by the right amount.
    var probe = new Image();
    probe.onload = function () { if (vf && probe.naturalWidth) vf.ratio = probe.naturalHeight / probe.naturalWidth; };
    probe.src = vf.url;
    el('vfTitle').textContent = @json(__('scoreboard::karate_messages.pl_vs_btn')) + ' — ' + (p.name || '');
    vfSay('');
    // The VS screen with THIS person in the red corner (their real name,
    // club, flag and photo), then their framing on top.
    if (!vfLive && window.VsLive) vfLive = window.VsLive(el('vfLive'));
    if (vfLive) { vfLive.reset(); vfLive.load('reg=' + encodeURIComponent(vf.id)); }
    vfPaint();
    el('vf').hidden = false;
  }
  (function () {
    var stage = el('vfDrag'), drag = null;
    if (!stage) return;
    stage.addEventListener('pointerdown', function (e) {
      if (!vf) return;
      drag = { x: e.clientX, y: e.clientY };
      try { stage.setPointerCapture(e.pointerId); } catch (err) {}
      stage.style.cursor = 'grabbing';
    });
    stage.addEventListener('pointermove', function (e) {
      if (!drag || !vf) return;
      // Screen px per VS-screen px: the preview is the whole 1920-wide wall.
      var r = el('vfLive').getBoundingClientRect(), k = r.width / 1920;
      var ox = 1075 * vf.z - 1075, oy = 1075 * vf.z * vf.ratio - 1080;       // how far the photo overhangs
      if (ox > 0.5) vf.x = Math.min(100, Math.max(0, vf.x - (e.clientX - drag.x) / k / ox * 100));
      if (oy > 0.5) vf.y = Math.min(100, Math.max(0, vf.y - (e.clientY - drag.y) / k / oy * 100));
      drag = { x: e.clientX, y: e.clientY };
      vfPaint();
      vfSay(@json(__('scoreboard::karate_messages.pg_unsaved')));
    });
    ['pointerup', 'pointercancel'].forEach(function (ev) {
      stage.addEventListener(ev, function () { drag = null; stage.style.cursor = 'grab'; });
    });
    el('vfZoom').addEventListener('input', function () {
      if (!vf) return;
      vf.z = Math.min(3, Math.max(1, parseFloat(this.value) || 1));
      vfPaint();
      vfSay(@json(__('scoreboard::karate_messages.pg_unsaved')));
    });
    el('vfReset').addEventListener('click', function () {
      if (!vf) return;
      vf.z = 1; vf.x = 50; vf.y = 12;
      vfPaint();
      vfSay(@json(__('scoreboard::karate_messages.pg_unsaved')));
    });
    el('vfClose').addEventListener('click', function () { el('vf').hidden = true; vf = null; });
    el('vfSave').addEventListener('click', function () {
      if (!vf || !PLAYERS_URL) return;
      var btn = this, id = vf.id, isDefault = vf.z === 1 && vf.x === 50 && vf.y === 12;
      btn.disabled = true;
      fetch(PLAYERS_URL + '/' + id + '/vs-frame', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        credentials: 'same-origin',
        body: JSON.stringify(isDefault ? { reset: true } : { zoom: vf.z, x: vf.x, y: vf.y }),
      })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (x) {
          btn.disabled = false;
          if (!x.ok || !x.d.success) { vfSay((x.d && x.d.message) || @json(__('scoreboard::karate_messages.pg_failed')), 'bad'); return; }
          if (plData && plData.player && plOpen === id) plData.player.vs_frame = x.d.frame;
          el('vf').hidden = true;
          vf = null;
          var say = el('plSay'); say.style.color = 'var(--green)'; say.textContent = x.d.message || '';
        })
        .catch(function () { btn.disabled = false; vfSay(@json(__('scoreboard::karate_messages.pg_failed')), 'bad'); });
    });
  })();

  function plPhoto() {
    if (!editable('photo') || typeof window.__karateCropFor !== 'function') return;
    var p = plData.player;
    // For the cropper's VS preview: this entry, in the red corner.
    window.__cropReg = plOpen; window.__cropSide = 'aka';
    window.__karateCropFor({
      title: p.name || '',
      colour: p.gender === 'Female' ? '#ff6fae' : '#5aa9ff',
      save: function (dataUrl) {
        plSend({ photo: dataUrl }, function () { var c = el('crop'); if (c) c.hidden = true; });
      },
    });
    // Open on the photo they already have, so it can just be re-framed. It
    // goes in through the cropper's own file input — the same path a picked
    // file takes, in every console style — and Upload still swaps it.
    if (p.photo) plCropCurrent(p.photo, plOpen);
  }

  function plCropCurrent(url, id) {
    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.blob() : null; })
      .then(function (b) {
        var input = el('cropFile'), box = el('crop');
        // Still open, still this player, nothing picked meanwhile.
        if (!b || !/^image\//.test(b.type) || !input || !box || box.hidden || plOpen !== id
            || (input.files && input.files.length)) return;
        var dt = new DataTransfer();
        dt.items.add(new File([b], 'current.' + (b.type.split('/')[1] || 'jpg'), { type: b.type }));
        input.files = dt.files;
        input.dispatchEvent(new Event('change'));
      })
      .catch(function () {});
  }

    /* ── Arcade ────────────────────────────────────────────────────────── */
  function arcRoster() {
    if (!CAT) return [];
    return Object.keys(CAT.roster).map(function (id) {
      var r = CAT.roster[id];
      return { id: parseInt(id, 10), name: r.name || '', club: r.club || '',
               photo: r.photo, fallback: r.fallback, flag: r.flag,
               gender: r.gender || null, weight: r.weight, divisions: r.divisions || [] };
    }).sort(function (x, y) { return x.name.localeCompare(y.name); });
  }

  function paintArcSide(side) {
    var host = el(side === 'a' ? 'arcListA' : 'arcListB');
    var input = el(side === 'a' ? 'arcFindA' : 'arcFindB');
    if (!host) return;
    if (catNotReady(host)) return;

    host.textContent = '';

    var q = fold(input ? input.value : '').trim();
    var people = arcRoster().filter(function (p) {
      return !q || fold(p.name).indexOf(q) !== -1 || fold(p.club).indexOf(q) !== -1
        || fold(p.divisions.join(' ')).indexOf(q) !== -1;
    });

    if (!people.length) { boutsEmpty(host, T.find_none); return; }

    var colour = side === 'a' ? LOOK.aka.colour : LOOK.ao.colour;

    people.forEach(function (p) {
      var row = document.createElement('button');
      row.type = 'button';
      row.className = 'arcrow';
      row.setAttribute('aria-pressed', arcPick[side] === p.id ? 'true' : 'false');
      if (arcPick[side] === p.id) row.style.borderColor = colour;

      var who = o.competitorCell(p.name, p.club, p.flag, p.photo, p.fallback, colour, false);
      who.style.flex = '1';
      row.appendChild(who);
      row.appendChild(arcFacts(p));

      row.onclick = function () {
        // Pressing the chosen one again clears it.
        arcPick[side] = arcPick[side] === p.id ? null : p.id;
        paintArcSide('a');
        paintArcSide('b');
        paintArcVerdict();
      };

      host.appendChild(row);
    });
  }

  /**
   * What an official checks before calling two people up, at the row's end:
   * the weight class(es) they are drawn in, then gender · recorded weight.
   * Anything not on file is simply left out — never guessed.
   */
  function arcFacts(p) {
    var box = document.createElement('span');
    box.style.cssText = 'flex:0 1 auto;max-width:48%;min-width:0;display:flex;flex-direction:column;align-items:flex-end;gap:4px;text-align:right;';

    if (p.divisions.length) {
      var dv = document.createElement('span');
      dv.style.cssText = 'font-size:18px;color:var(--text);letter-spacing:.03em;line-height:1.15;overflow-wrap:anywhere;';
      dv.textContent = p.divisions.join(' · ');
      box.appendChild(dv);
    }

    // Gender in its own colour — blue for male, pink for female — and the
    // weight in the console's gold, so the two read apart at a glance.
    var line = document.createElement('span');
    line.style.cssText = "font-family:'Anton',sans-serif;font-size:18px;letter-spacing:.06em;white-space:nowrap;";

    function part(txt, ink) {
      if (line.childNodes.length) {
        var dot = document.createElement('span');
        dot.style.color = 'var(--faint)';
        dot.textContent = '  ·  ';
        line.appendChild(dot);
      }
      var sp = document.createElement('span');
      sp.style.color = ink;
      sp.textContent = txt;
      line.appendChild(sp);
    }

    if (p.gender === 'Male') part('♂ ' + T.male, '#5aa9ff');
    else if (p.gender === 'Female') part('♀ ' + T.female, '#ff6fae');
    if (p.weight != null && p.weight > 0) part((Math.round(p.weight * 10) / 10) + ' kg', 'var(--gold)');

    if (line.childNodes.length) box.appendChild(line);

    return box;
  }

  /** The draw's own bout between the pair — the console never makes one. */
  function arcFound() {
    if (catState !== 'ready' || arcPick.a == null || arcPick.b == null) return null;
    if (arcPick.a === arcPick.b) return { same: true };

    var hits = CAT.bouts.filter(function (b) {
      return (b.a === arcPick.a && b.b === arcPick.b) || (b.a === arcPick.b && b.b === arcPick.a);
    });
    if (!hits.length) return { none: true };

    var live = hits.filter(function (b) { return !b.done; });
    return live.length
      ? { bout: live[0], swapped: live[0].a !== arcPick.a }
      : { bout: hits[0], done: true };
  }

  function paintArcVerdict() {
    var btn = el('arcFight');
    var say = el('arcSay');
    var vs = el('arcVs');
    if (!btn) return;

    var f = arcFound();

    if (vs) {
      var a = arcPick.a != null && CAT ? CAT.roster[String(arcPick.a)] : null;
      var b = arcPick.b != null && CAT ? CAT.roster[String(arcPick.b)] : null;
      vs.textContent = (a && b) ? ((a.name || '') + ' · ' + (b.name || '')) : '';
    }

    btn.disabled = true;
    btn.onclick = null;

    if (!f) { if (say) say.textContent = T.arcade_hint; return; }
    if (f.same) { if (say) say.textContent = T.arc_same; return; }

    // Exhibitions on: whoever is picked can fight. It is recorded as a side
    // exhibition of this event and never touches the draw — so it is offered
    // whatever the draw says about the pair, which is only mentioned.
    if (EXHIBITIONS) {
      var note = '';
      if (f.bout) {
        note = T.arc_found
          .replace(':no', f.bout.number == null ? '—' : f.bout.number)
          .replace(':stage', f.bout.stage || f.bout.division || '') + (f.done ? ' ✓' : '') + '\n';
      }
      if (say) { say.style.whiteSpace = 'pre-line'; say.textContent = note + T.ex_hint; }

      var pa = arcPick.a, pb = arcPick.b;
      btn.disabled = false;
      btn.onclick = function () {
        o.send('exhibition', { a: pa, b: pb, seconds: o.boutSeconds() }).then(function () {
          // Closed only when the mat actually took it.
          if (o.state().exhibition) close();
        });
      };
      return;
    }

    if (f.none) { if (say) say.textContent = T.arc_none; return; }

    if (f.done) {
      if (say) say.textContent = T.arc_done.replace(':no', f.bout.number == null ? '—' : f.bout.number);
      return;
    }

    var line = T.arc_found
      .replace(':no', f.bout.number == null ? '—' : f.bout.number)
      .replace(':stage', f.bout.stage || f.bout.division || '');

    if (!f.bout.runnable) {
      if (say) say.textContent = line + ' — ' + T.waiting_feeder;
      return;
    }

    if (say) say.textContent = f.swapped ? line + ' — ' + T.arc_swapped : line;

    btn.disabled = false;
    btn.onclick = function () {
      o.send('load', { match_id: f.bout.id, seconds: o.boutSeconds() }).then(close);
    };
  }

  function paintArcade() {
    text('arcCapA', LOOK.aka.label);
    text('arcCapB', LOOK.ao.label);
    var ca = el('arcCapA'), cb = el('arcCapB');
    // The corners' own name ink — the colour the board writes each name in.
    if (ca) ca.style.color = LOOK.aka.nameInk || LOOK.aka.colour;
    if (cb) cb.style.color = LOOK.ao.nameInk || LOOK.ao.colour;
    paintArcSide('a');
    paintArcSide('b');
    paintArcVerdict();
  }

  /* ── Exhibitions → the draw ──────────────────────────────────────────
     Every exhibition fought at this event and the draw bouts each one could
     really have been (App\Events\Support\ExhibitionLink, server-side — this
     only draws what it is handed). Linking files the exhibition's result into
     that bout: two taps, the second one naming the bout. */
  var EX = null, exState = 'idle', exFilter = 'open', exArmed = null, exBusy = false;
  // The "choose any bout" picker: which exhibition it is open on, and its query.
  var exPick = null;
  // Which card's Actions menu is open (an exhibition uuid), if any.
  var exMenu = null;
  document.addEventListener('pointerdown', function (e) {
    if (exMenu && !(e.target.closest && e.target.closest('#bouts .exmenu'))) { exMenu = null; paintExhibitions(true); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && exMenu) { exMenu = null; paintExhibitions(true); }
  });

  function exLoad(force) {
    if (!EX_URL) return;
    if (exState === 'loading' || (exState === 'ready' && !force)) { paintExhibitions(); return; }
    exState = 'loading';
    paintExhibitions();
    fetch(EX_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j || !j.exhibitions) { exState = 'failed'; paintExhibitions(); return; }
        EX = j; exState = 'ready'; paintExhibitions();
      })
      .catch(function () { exState = 'failed'; paintExhibitions(); });
  }

  function exCount() {
    var n = el('btabExCount');
    if (!n) return;
    var k = EX ? EX.matched : 0;
    n.textContent = k ? String(k) : '';
    n.hidden = !k;
  }

  function exSay(msg, tone) {
    var say = el('exSay');
    if (!say) return;
    say.style.color = tone === 'bad' ? '#ff7a7a' : (tone === 'ok' ? 'var(--green)' : (tone === 'warn' ? 'var(--gold)' : ''));
    say.textContent = msg || '';
  }

  function exNode(tag, cls, txt) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (txt != null) n.textContent = txt;
    return n;
  }

  function paintExhibitions(keepSay) {
    var host = el('exList');
    if (!host) return;
    exCount();
    document.querySelectorAll('#bpaneExhibitions [data-exf]').forEach(function (b) {
      b.setAttribute('aria-pressed', b.getAttribute('data-exf') === exFilter ? 'true' : 'false');
    });

    host.textContent = '';
    if (exState === 'loading' || exState === 'idle') { boutsEmpty(host, T.reading_draw); return; }
    if (exState === 'failed') { boutsEmpty(host, T.draw_failed); return; }

    var all = EX.exhibitions || [];
    if (!keepSay) exSay(all.length ? T.ex_matched.replace(':n', EX.matched) : '');
    if (!all.length) { boutsEmpty(host, T.ex_none); return; }

    var rows = all.filter(function (x) {
      if (exFilter === 'linked') return x.status === 'linked';
      if (exFilter === 'open') return x.status === 'done';
      return true;
    });
    // To link: the ones the draw can take first — that is what the tab is for.
    if (exFilter === 'open') rows.sort(function (p, q) { return (q.candidates.length ? 1 : 0) - (p.candidates.length ? 1 : 0); });
    if (!rows.length) { boutsEmpty(host, T.ex_none_here); return; }

    rows.forEach(function (x) { host.appendChild(exCard(x)); });

    // An open menu that would run past the list's bottom opens upward.
    var pn = host.querySelector('.expanel');
    if (pn) {
      var hr = host.getBoundingClientRect(), pr = pn.getBoundingClientRect();
      var btn = pn.parentNode.getBoundingClientRect();
      if (pr.bottom > hr.bottom && btn.top - hr.top > pr.height) pn.classList.add('up');
    }
  }

  // "Name  VS  Name" for a bout row, the VS set small and faint.
  function exVs(host, a, b) {
    host.appendChild(document.createTextNode(a || '?'));
    host.appendChild(exNode('span', 'vs', 'vs'));
    host.appendChild(document.createTextNode(b || '?'));
    return host;
  }

  function exKind(m) {
    return m.kind === 'exact' ? T.ex_exact : (m.kind === 'recorded' ? T.ex_recorded : T.ex_seat);
  }

  // Arm one action on one card. The card then shows ONE confirm bar saying
  // what will happen; nothing is sent until Confirm.
  function exArm(x, a) {
    a.ex = x.uuid;
    exArmed = a;
    exMenu = null;
    paintExhibitions(true);
  }

  function exCard(x) {
    var rail = x.status === 'linked' ? ' st-ok'
      : (x.status === 'running' ? ' st-live' : (x.status === 'done' && x.candidates.length ? ' st-go' : ''));
    var c = exNode('div', 'excard' + rail + (exArmed && exArmed.ex === x.uuid ? ' open' : '')
      + (exMenu === x.uuid ? ' menu' : ''));

    var head = exNode('div', 'exhead');
    var code = exNode('div', 'excode', x.code);
    var when = '';
    try { when = x.fought_at ? new Date(x.fought_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : ''; } catch (e) {}
    code.appendChild(exNode('small', null, (x.court || '') + (when ? ' · ' + when : '')));
    head.appendChild(code);

    // AKA · score · AO, the winner lit and marked in their corner's colour.
    var pair = exNode('div', 'expair');
    var sa = exNode('div', 'exside a' + (x.winner === 'a' ? ' won' : ''));
    if (x.winner === 'a') sa.appendChild(exNode('span', 'exwin', '✓'));
    sa.appendChild(exNode('span', 'nm', x.a.name || '—'));
    var score = exNode('div', 'exscore');
    score.appendChild(exNode('span', 'a', x.a.score == null ? '–' : x.a.score));
    score.appendChild(exNode('span', 'sep', ':'));
    score.appendChild(exNode('span', 'b', x.b.score == null ? '–' : x.b.score));
    var sb = exNode('div', 'exside b' + (x.winner === 'b' ? ' won' : ''));
    sb.appendChild(exNode('span', 'nm', x.b.name || '—'));
    if (x.winner === 'b') sb.appendChild(exNode('span', 'exwin', '✓'));
    pair.appendChild(sa); pair.appendChild(score); pair.appendChild(sb);
    head.appendChild(pair);

    var chip;
    if (x.status === 'linked' && x.linked) {
      chip = exNode('span', 'exchip ok', T.ex_st_linked.replace(':no', x.linked.number == null ? '—' : x.linked.number)
        .replace(':round', x.linked.round || x.linked.division || ''));
    } else if (x.status === 'done') {
      chip = exNode('span', 'exchip' + (x.candidates.length ? ' go' : ''), T.ex_st_done);
    } else {
      chip = exNode('span', 'exchip', x.status === 'running' ? T.ex_st_running : T.ex_st_abandoned);
    }
    var end = exNode('div', 'exend');
    end.appendChild(chip);
    var menu = exMenuFor(x);
    if (menu) end.appendChild(menu);
    head.appendChild(end);
    c.appendChild(head);

    if (exArmed && exArmed.ex === x.uuid) c.appendChild(exConfirmBar(x));
    if (x.status === 'running') c.appendChild(exNode('div', 'exnote', T.ex_unfinished));
    if (exPick && exPick.ex === x.uuid) c.appendChild(exPicker(x));
    return c;
  }

  /*
   * The card's one Actions menu: the draw bouts this exhibition could be,
   * then "pick it on the draw" and "choose any bout"; or, once linked, Unlink.
   */
  function exMenuFor(x) {
    if (x.status === 'running') return null;
    var open = exMenu === x.uuid;
    var wrap = exNode('div', 'exmenu');
    var btn = exNode('button', 'exbtn exmenubtn' + (open ? ' on' : ''));
    btn.type = 'button';
    btn.disabled = exBusy;
    btn.setAttribute('aria-haspopup', 'menu');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.appendChild(exNode('span', null, T.ex_actions));
    btn.appendChild(exNode('span', 'chev', '▾'));
    btn.onclick = function () { exMenu = open ? null : x.uuid; paintExhibitions(true); };
    wrap.appendChild(btn);
    if (!open) return wrap;

    var panel = exNode('div', 'expanel');
    panel.setAttribute('role', 'menu');
    function item(label, sub, tag, tone, disabled, go, title) {
      var it = exNode('button', 'exitem' + (tone ? ' ' + tone : ''));
      it.type = 'button';
      it.setAttribute('role', 'menuitem');
      it.disabled = !!disabled || exBusy;
      if (title) it.title = title;
      var main = exNode('span', 'lbl');
      if (typeof label === 'string') main.textContent = label; else main.appendChild(label);
      if (sub) main.appendChild(exNode('small', null, sub));
      it.appendChild(main);
      if (tag) it.appendChild(tag);
      it.onclick = go;
      panel.appendChild(it);
    }

    if (x.status === 'linked') {
      item(T.ex_unlink, null, null, 'danger', false, function () {
        exArm(x, { match: 'unlink', act: 'unlink', tone: 'danger', label: T.ex_unlink_confirm,
          hint: T.ex_unlink_hint.replace(':code', x.code).replace(':no', x.linked && x.linked.number != null ? x.linked.number : '—') });
      });
    } else {
      var cands = x.status === 'done' ? x.candidates : [];
      cands.forEach(function (m) {
        var blocked = m.kind === 'recorded' && !m.agrees;
        var no = m.number == null ? '—' : m.number;
        var lbl = exNode('span');
        lbl.appendChild(exNode('b', 'no', '#' + no));
        exVs(lbl, m.a, m.b);
        var sub = [m.round, m.division, m.court];
        // What the draw already says — so the organiser can check it against
        // what the exhibition scored before linking.
        if (m.kind === 'recorded' && m.recorded) {
          var r = m.recorded;
          sub.push(T.ex_draw_score.replace(':score', (r.a == null ? '–' : r.a) + ' : ' + (r.b == null ? '–' : r.b)
            + ' (' + (r.winner === 'a' ? (m.a || 'A') : (m.b || 'B')) + ' ✓' + (r.reason ? ', ' + r.reason : '') + ')'));
        }
        var tag = exNode('span', 'exchip ' + (blocked ? 'bad' : (m.kind === 'seat' ? 'go' : 'ok')),
          blocked ? T.ex_other_winner : exKind(m));
        // A bout chosen here opens the draw with THAT bout selected, so it is
        // confirmed where it sits; with no draw to open, it is confirmed here.
        item(lbl, sub.filter(Boolean).join(' · '), tag, null, blocked, function () {
          if (EX_DRAW_URL) { exOpenDraw(x, m.division_id, m.id); return; }
          var hint = m.kind === 'recorded' ? T.ex_confirm_recorded : T.ex_confirm_hint;
          var sc = m.swap ? (x.b.score + ' : ' + x.a.score) : (x.a.score + ' : ' + x.b.score);
          exArm(x, { match: m.id, act: 'link', m: m, label: T.ex_confirm,
            hint: hint.replace(':code', x.code).replace(':no', no).replace(':score', sc) });
        }, blocked ? T.ex_disagree_hint : null);
      });
      if (!cands.length) panel.appendChild(exNode('div', 'exinfo', x.status === 'done' ? T.ex_no_fit : T.ex_unfinished));
      panel.appendChild(exNode('div', 'exrule'));

      // The draw itself — tap the bout there, say yes or no.
      if (EX_DRAW_URL) {
        item(T.ex_on_draw, null, exNode('span', 'arr', '↗'), null, false, function () { exOpenDraw(x, x.division_id, null); });
      }
      var picking = exPick && exPick.ex === x.uuid;
      item(picking ? T.ex_pick_close : T.ex_pick_open, null, null, null, false, function () {
        exPick = picking ? null : { ex: x.uuid, q: '', fresh: true };
        exArmed = null;
        exMenu = null;
        paintExhibitions(true);
        if (exPick) catLoad(function () { paintExhibitions(true); });
      });
    }
    wrap.appendChild(panel);
    return wrap;
  }

  // The organiser's draw page in LINK MODE for one exhibition — on one
  // division, and with one bout already selected when `bout` is given.
  function exOpenDraw(x, division, bout) {
    var q = ['link_exhibition=' + encodeURIComponent(x.uuid), 'mat=' + encodeURIComponent(MAT)];
    if (division) q.push('division=' + encodeURIComponent(division));
    if (bout) q.push('bout=' + encodeURIComponent(bout));
    window.location.href = EX_DRAW_URL + (EX_DRAW_URL.indexOf('?') === -1 ? '?' : '&') + q.join('&');
  }

  function exConfirmBar(x) {
    var a = exArmed;
    var danger = a.tone === 'danger';
    var bar = exNode('div', 'exconfirm' + (danger ? ' danger' : ''));
    bar.appendChild(exNode('div', 'msg', a.hint));
    var acts = exNode('div', 'acts');
    var no = exNode('button', 'exbtn ghost', T.ex_cancel);
    no.type = 'button';
    no.disabled = exBusy;
    no.onclick = function () { exArmed = null; paintExhibitions(true); };
    var yes = exNode('button', 'exbtn ' + (danger ? 'danger arm' : 'primary'), exBusy ? T.ex_linking : a.label);
    yes.type = 'button';
    yes.disabled = exBusy;
    yes.onclick = function () {
      if (a.act === 'unlink') exUnlink(x);
      else if (a.act === 'attach') exLink(x, { id: a.match }, true);
      else exLink(x, a.m);
    };
    acts.appendChild(no); acts.appendChild(yes);
    bar.appendChild(acts);
    return bar;
  }

  /*
   * Any bout of the draw, chosen by hand. A bout the system already offered
   * links as above; any other is ATTACHED only — the exhibition, its video and
   * its score log become that bout's, and the draw itself is not changed.
   */
  function exPicker(x) {
    var box = exNode('div', 'expick');
    var input = document.createElement('input');
    input.type = 'search'; input.className = 'bfind'; input.autocomplete = 'off'; input.spellcheck = false;
    input.placeholder = T.ex_pick_find; input.setAttribute('aria-label', T.ex_pick_find);
    input.value = exPick.q;
    var hint = exNode('div', 'bhint', T.ex_pick_hint);
    hint.style.fontSize = '16px';
    var list = exNode('div', 'expicklist');
    box.appendChild(input); box.appendChild(hint); box.appendChild(list);

    var t = null;
    input.addEventListener('input', function () {
      clearTimeout(t);
      t = setTimeout(function () { exPick.q = input.value; paintPickList(x, list); }, 140);
    });
    paintPickList(x, list);
    if (exPick.fresh) {
      exPick.fresh = false;
      setTimeout(function () { try { input.focus(); } catch (e) {} }, 0);
    }
    return box;
  }

  function paintPickList(x, host) {
    if (catNotReady(host)) return;
    host.textContent = '';

    var q = fold(exPick ? exPick.q : '').trim();
    var num = q.replace(/^#/, '');
    var offered = {};
    (x.candidates || []).forEach(function (m) { offered[m.id] = m; });

    var rows = CAT.bouts.filter(function (b) {
      if (!q) return true;
      if (/^\d+$/.test(num)) return String(b.number) === num;
      return boutNames(b).concat([fold(b.division), fold(b.stage)]).some(function (n) { return n.indexOf(q) !== -1; });
    }).slice(0, 60);

    if (!rows.length) { boutsEmpty(host, T.find_none); return; }

    rows.forEach(function (b) {
      var nm = function (id, n) { var r = id != null ? CAT.roster[String(id)] : null; return n || (r && r.name) || '?'; };
      var known = offered[b.id];
      var blocked = !!(known && known.kind === 'recorded' && !known.agrees);
      var armed = exArmed && exArmed.ex === x.uuid && exArmed.match === b.id;
      // The whole row is the button: tap it, then Confirm on the card.
      var row = exNode('button', 'excand pick' + (armed ? ' arm' : '') + (blocked ? ' off' : ''));
      row.type = 'button';
      row.disabled = exBusy || blocked;
      if (blocked) row.title = T.ex_disagree_hint;
      row.appendChild(exNode('span', 'no', '#' + (b.number == null ? '—' : b.number)));
      var who = exVs(exNode('span', 'who'), nm(b.a, b.a_name), nm(b.b, b.b_name));
      who.appendChild(exNode('small', null, [b.stage, b.division, b.court, b.done ? '✓' : ''].filter(Boolean).join(' · ')));
      row.appendChild(who);
      row.appendChild(exNode('span', 'exchip ' + (known ? 'ok' : ''), known ? exKind(known) : T.ex_attach));
      row.onclick = function () {
        var no = b.number == null ? '—' : b.number;
        exArm(x, known
          ? { match: b.id, act: 'link', m: known, label: T.ex_confirm, hint: T.ex_confirm_hint.replace(':code', x.code).replace(':no', no) }
          : { match: b.id, act: 'attach', label: T.ex_confirm, hint: T.ex_confirm_attach.replace(':code', x.code).replace(':no', no) });
      };
      host.appendChild(row);
    });
  }

  function exUnlink(x) {
    if (exBusy) return;
    exBusy = true;
    exSay(T.ex_linking);
    paintExhibitions(true);

    fetch(EX_URL + '/' + encodeURIComponent(x.uuid) + '/unlink', {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      credentials: 'same-origin',
      body: '{}',
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        exBusy = false;
        exArmed = null;
        if (!d.success) { paintExhibitions(true); exSay(d.message || T.draw_failed, 'bad'); return; }
        EX = { exhibitions: d.exhibitions, matched: d.matched };
        exState = 'ready';
        CAT = null;
        catState = 'idle';
        paintExhibitions(true);
        exSay(d.message || '', 'ok');
      })
      .catch(function () {
        exBusy = false;
        exArmed = null;
        paintExhibitions(true);
        exSay(T.draw_failed, 'bad');
      });
  }

  function exLink(x, m, attachOnly) {
    if (exBusy) return;
    exBusy = true;
    exSay(T.ex_linking);
    paintExhibitions(true);

    fetch(EX_URL + '/' + encodeURIComponent(x.uuid) + '/link', {
      method: 'POST',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      credentials: 'same-origin',
      body: JSON.stringify(attachOnly ? { match_id: m.id, attach_only: true } : { match_id: m.id }),
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (d) {
        exBusy = false;
        exArmed = null;
        if (d.success) exPick = null;
        if (!d.success) {
          var msg = d.message || T.draw_failed;
          if (d.errors) { var k = Object.keys(d.errors)[0]; if (k) msg = d.errors[k][0]; }
          paintExhibitions(true);
          exSay(msg, 'bad');
          // The draw may have moved under us — read the list again.
          exLoad(true);
          return;
        }
        EX = { exhibitions: d.exhibitions, matched: d.matched };
        exState = 'ready';
        // The bracket changed: the other tabs must not list the bout as unfought.
        CAT = null;
        catState = 'idle';
        paintExhibitions(true);
        exSay(d.message || '', 'ok');
      })
      .catch(function () {
        exBusy = false;
        exArmed = null;
        paintExhibitions(true);
        exSay(T.draw_failed, 'bad');
      });
  }

  /* ── Tabs ──────────────────────────────────────────────────────────── */
  var PANE = { arranged: 'bpaneArranged', division: 'bpaneDivision',
               member: 'bpaneMember', clubs: 'bpaneClubs', arcade: 'bpaneArcade',
               exhibitions: 'bpaneExhibitions' };

  function catRepaint() {
    if (boutsTab === 'division') paintDivisions();
    else if (boutsTab === 'member') plLoad();
    else if (boutsTab === 'arcade') paintArcade();
  }

  function show(name) {
    if (!PANE[name] || (name === 'member' && !PLAYERS_URL) || (name === 'clubs' && !CLUBS_URL)
        || (name === 'exhibitions' && !EX_URL)) name = 'arranged';
    boutsTab = name;
    memWrite({ tab: name });

    document.querySelectorAll('#bouts .btab').forEach(function (t) {
      t.setAttribute('aria-selected', t.getAttribute('data-btab') === name ? 'true' : 'false');
    });
    Object.keys(PANE).forEach(function (k) { var pn = el(PANE[k]); if (pn) pn.hidden = k !== name; });

    if (name === 'member' && !PLAYERS_URL) name = 'arranged';
    if (name === 'arranged') { paintArranged(); return; }
    if (name === 'member') { plLoad(); return; }
    if (name === 'clubs') { clLoad(); return; }
    if (name === 'exhibitions') { exLoad(true); return; }
    catLoad(catRepaint);
  }

  /*
   * The draw has moved (a bout loaded, a result recorded) — drop the cached
   * copy, so the browsing tabs never list a fought bout as still to come.
   * Re-read at once only if the card is open on one of them.
   */
  function catStale() {
    CAT = null;
    catState = 'idle';
    // A result just landed: which exhibitions the draw can still take moved too.
    if (EX_URL && exState === 'ready') exState = 'idle';
    if (isOpen() && boutsTab === 'exhibitions') { exLoad(true); return; }
    if (isOpen() && boutsTab !== 'arranged') catLoad(catRepaint);
  }

  var lastSig = null;
  function signature() {
    var s = o.state() || {};
    return (s.matchId || '') + '|' + (s.winner || '') + '|' +
      (o.queue() || []).map(function (b) { return b.id + (b.done ? 'd' : ''); }).join(',');
  }

  (function wire() {
    var bar = document.querySelector('#bouts .btabs');
    if (bar) {
      bar.addEventListener('click', function (e) {
        var tab = e.target.closest('.btab');
        if (tab) show(tab.getAttribute('data-btab'));
      });
    }

    var back = el('divBack');
    if (back) {
      back.addEventListener('click', function () {
        divOpen = null;
        memWrite({ division: null });
        paintDivisions();
      });
    }

    // Debounced so a tablet keyboard never feels refused.
    function debounced(id, fn) {
      var input = el(id);
      if (!input) return;
      var t = null;
      input.addEventListener('input', function () {
        clearTimeout(t);
        t = setTimeout(fn, 140);
      });
    }
    debounced('plFind', paintPlayers);

    // Players editor wiring — every input writes the draft, nothing is sent
    // until Save.
    function paintEditorLight() {
      el('plSave').disabled = !Object.keys(plChanges() || {}).length;
    }
    var nmEl = el('plName');
    if (nmEl) nmEl.addEventListener('input', function () { if (plDraft) { plDraft.name = nmEl.value; paintEditorLight(); } });
    [['plDobD', 'dobD'], ['plDobM', 'dobM'], ['plDobY', 'dobY'], ['plAge', 'age']].forEach(function (x) {
      var e = el(x[0]);
      if (e) e.addEventListener('input', function () {
        e.value = e.value.replace(/\D/g, '');
        if (plDraft) { plDraft[x[1]] = e.value; paintEditorLight(); }
      });
    });
    var wEl = el('plWeight');
    if (wEl) wEl.addEventListener('input', function () {
      wEl.value = wEl.value.replace(/[^\d.]/g, '');
      if (plDraft) { plDraft.weight = wEl.value; paintEditorLight(); }
    });
    // Exhibitions tab wiring.
    document.querySelectorAll('#bpaneExhibitions [data-exf]').forEach(function (b) {
      b.addEventListener('click', function () {
        exFilter = b.getAttribute('data-exf');
        exArmed = null;
        paintExhibitions();
      });
    });
    // Clubs tab wiring.
    debounced('clFind', paintClubs);
    if (el('clName')) el('clName').addEventListener('input', function () { if (clDraft) { clDraft.name = this.value; el('clSave').disabled = !clChanged(); } });
    if (el('clInsta')) el('clInsta').addEventListener('input', function () { if (clDraft) { clDraft.insta = this.value; el('clSave').disabled = !clChanged(); } });
    window.addEventListener('cl-nat', function (e) {
      if (!clDraft || (e.detail || '') === clDraft.nat) return;
      var c = clCurrent(); if (!c || !c.editable) return;
      clDraft.nat = e.detail || '';
      el('clSave').disabled = !clChanged();
    });
    if (el('clLogoFile')) el('clLogoFile').addEventListener('change', function () { clPickLogo(this.files && this.files[0]); this.value = ''; });
    if (el('clLogo')) el('clLogo').addEventListener('click', function () { if (el('clLogoFile')) el('clLogoFile').click(); });
    if (el('clSave')) el('clSave').addEventListener('click', clSaveNow);

    // The country picker tells this editor what was picked.
    window.addEventListener('pl-nat', function (e) {
      if (!plDraft || (e.detail || '') === plDraft.nat) return;
      if (!editable('nationality')) return;
      plDraft.nat = e.detail || '';
      text('plNatNote', '');
      el('plSave').disabled = !Object.keys(plChanges() || {}).length;
    });

    // …and the toggle tells this editor what was picked.
    window.addEventListener('pl-gender', function (e) {
      if (!plDraft || e.detail === plDraft.gender) return;
      if (!editable('gender')) return;
      plDraft.gender = e.detail || '';
      paintEditorLight();
    });
    document.querySelectorAll('#plEdit .plseg').forEach(function (b) {
      b.addEventListener('click', function () {
        if (!plDraft) return;
        plDraft.mode = b.getAttribute('data-mode');
        paintEditor();
      });
    });


    // A floating list closes on a tap anywhere outside it, or on Escape.
    var cardEl = card();
    if (cardEl) cardEl.addEventListener('click', function (e) {
      // A target the repaint already removed was inside a dropdown a moment ago.
      if (!e.target.isConnected) return;
      if (ddOpen && !e.target.closest('.pldd')) { ddOpen = null; if (plData) paintEditor(); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && ddOpen) { ddOpen = null; if (plData) paintEditor(); }
    });
    if (el('plSave')) el('plSave').addEventListener('click', plSave);
    if (el('plPhoto')) el('plPhoto').addEventListener('click', plPhoto);
    if (el('plPhotoBtn')) el('plPhotoBtn').addEventListener('click', plPhoto);
    if (el('plVsBtn')) el('plVsBtn').addEventListener('click', vfOpen);
    debounced('arrFind', paintArranged);
    debounced('arcFindA', function () { paintArcSide('a'); });
    debounced('arcFindB', function () { paintArcSide('b'); });

    // A tap on the scrim outside the card closes it.
    var c = card();
    if (c) c.addEventListener('click', function (e) { if (e.target === c) close(); });
  })();

  return {
    /** Open the card on the tab the operator last left it on. */
    open: function () {
      card().hidden = false;
      show(boutsTab);
      // The tab's count, even when the card opens on another tab.
      if (EX_URL && exState === 'idle' && boutsTab !== 'exhibitions') exLoad();
    },
    /** The console's state or queue changed. */
    paint: function () {
      var sig = signature();
      if (sig !== lastSig) {
        if (lastSig !== null) catStale();
        lastSig = sig;
      }
      if (isOpen() && boutsTab === 'arranged') paintArranged();
    },
  };
};
</script>
