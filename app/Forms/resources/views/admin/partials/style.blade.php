{{-- Results-page styles, scoped under .fa so nothing leaks into the admin
     shell. Written out because the Tailwind bundle is prebuilt. Charts are
     plain HTML/CSS — no chart library, nothing loaded from another host. --}}
<style>
.fa{--p:hsl(250 65% 65%);--p2:hsl(250 62% 56%);--pl:hsl(250 70% 90%);--acc:hsl(250 60% 95%);--mut:hsl(220 15% 94%);--line:hsl(220 14% 91%);--ink:hsl(232 21% 14%);--ink2:hsl(229 13% 38%);--ink3:hsl(229 11% 58%);--ok:#16a34a;--now:#6d5ce8;--next:#0f9488;--later:#d97706;--show:#db2777;--keep:#9aa0b4;color:var(--ink)}
.fa .num{font-variant-numeric:tabular-nums}
.fa-card{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 1px 2px rgba(27,29,41,.04),0 6px 18px rgba(27,29,41,.05)}
.fa-pad{padding:18px}
.fa-eb{font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--ink3)}
[dir=rtl] .fa-eb{letter-spacing:0;font-size:12px}
.fa-h{font-size:15px;font-weight:800;line-height:1.35}
.fa-sub{font-size:12px;color:var(--ink3);margin-top:2px}
.fa-kpis{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
@media(min-width:900px){.fa-kpis{grid-template-columns:repeat(4,1fr);gap:14px}}
.fa-kpi{padding:14px 16px}
.fa-kpi b{display:block;font-size:26px;font-weight:900;letter-spacing:-.02em;line-height:1.15;margin-top:2px}
.fa-kpi small{font-size:12px;color:var(--ink2)}
.fa-tl{display:flex;align-items:flex-end;gap:3px;height:64px;margin-top:12px}
.fa-tl i{flex:1;background:var(--pl);border-radius:3px 3px 0 0;min-height:2px;position:relative}
.fa-tl i.has{background:var(--p)}
.fa-tl-ax{display:flex;justify-content:space-between;font-size:10px;color:var(--ink3);margin-top:4px}
.fa-sec{display:flex;align-items:center;gap:10px;margin:26px 2px 10px}
.fa-sec span{font-size:12px;font-weight:800;color:var(--p2);background:var(--acc);border-radius:8px;padding:3px 9px}
.fa-sec b{font-size:16px;font-weight:900}
.fa-qs{display:grid;grid-template-columns:1fr;gap:12px}
@media(min-width:1100px){.fa-qs{grid-template-columns:1fr 1fr}.fa-qs .wide{grid-column:1/-1}}
.fa-q{padding:16px 18px}
.fa-q .meta{display:flex;align-items:center;gap:8px;margin-top:6px;font-size:11.5px;color:var(--ink3)}
.fa-tag{font-style:normal;display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:800;padding:2px 8px;border-radius:999px;background:var(--mut);color:var(--ink2)}
.fa-tag.warn{background:hsl(38 92% 50% / .13);color:#b45309}
.fa-bars{margin-top:12px;display:flex;flex-direction:column;gap:8px}
.fa-bar .t{display:flex;justify-content:space-between;gap:10px;font-size:13px}
.fa-bar .t span:first-child{min-width:0}
.fa-bar .t b{font-weight:800;white-space:nowrap}
.fa-bar .t em{font-style:normal;color:var(--ink3);font-weight:500;margin-inline-start:4px}
.fa-track{height:8px;border-radius:99px;background:var(--mut);margin-top:4px;overflow:hidden}
.fa-track i{display:block;height:100%;border-radius:99px;background:var(--p);transition:width .5s ease}
.fa-bar:not(:first-child) .fa-track i{background:hsl(250 65% 72%)}
.fa-scale{display:flex;align-items:flex-end;gap:14px;margin-top:12px}
.fa-mean{flex:0 0 auto;text-align:center;padding-inline-end:14px;border-inline-end:1px solid var(--line)}
.fa-mean b{display:block;font-size:30px;font-weight:900;line-height:1}
.fa-mean small{font-size:11px;color:var(--ink3)}
.fa-cols{flex:1;display:grid;grid-template-columns:repeat(5,1fr);gap:6px;align-items:end;height:80px}
.fa-cols div{display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;gap:3px}
.fa-cols i{width:100%;max-width:38px;background:var(--pl);border-radius:6px 6px 2px 2px;min-height:3px}
.fa-cols i.top{background:var(--p)}
.fa-cols span{font-size:11px;font-weight:700;color:var(--ink2)}
.fa-cols em{font-style:normal;font-size:10px;color:var(--ink3)}
.fa-ends{display:flex;justify-content:space-between;font-size:11px;color:var(--ink3);margin-top:6px;padding-inline-start:84px}
.fa-nums{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:12px}
@media(min-width:600px){.fa-nums{grid-template-columns:repeat(3,1fr)}}
.fa-nums div{background:var(--mut);border-radius:12px;padding:10px 12px}
.fa-nums span{display:block;font-size:11.5px;color:var(--ink3)}
.fa-nums b{font-size:20px;font-weight:900}
.fa-nums small{display:block;font-size:11px;color:var(--ink3)}
.fa-legend{display:flex;gap:12px;flex-wrap:wrap;font-size:11.5px;color:var(--ink2);margin-top:8px}
.fa-legend i{display:inline-block;width:10px;height:10px;border-radius:3px;margin-inline-end:5px;vertical-align:-1px}
.fa-grid{margin-top:10px;display:flex;flex-direction:column}
.fa-grow{display:grid;grid-template-columns:minmax(0,1fr) 34px;gap:4px 10px;padding:7px 0;border-top:1px solid var(--line);align-items:center}
.fa-grow:first-child{border-top:0}
.fa-grow .l{font-size:13px;font-weight:500}
.fa-grow .s{font-size:13px;font-weight:900;text-align:end;grid-row:1/3;grid-column:2}
.fa-stack{display:flex;gap:2px;height:8px;border-radius:99px;overflow:hidden;background:var(--mut)}
.fa-stack i{display:block;height:100%}
.fa-rank{margin-top:12px;display:flex;flex-direction:column;gap:7px}
.fa-rank div{display:grid;grid-template-columns:26px minmax(0,1fr) auto;gap:10px;align-items:center;font-size:13px}
.fa-rank .n{width:26px;height:26px;border-radius:8px;background:var(--mut);display:grid;place-items:center;font-size:12px;font-weight:800;color:var(--ink2)}
.fa-rank div:first-child .n{background:var(--p);color:#fff}
.fa-rank small{display:block;font-size:11px;color:var(--ink3)}
.fa-quotes{margin-top:12px;display:flex;flex-direction:column;gap:8px}
.fa-quote{background:var(--mut);border-radius:12px;padding:10px 12px;font-size:13px;line-height:1.55;white-space:pre-line}
.fa-quote small{display:block;margin-top:4px;color:var(--ink3);font-size:11px;white-space:normal}
.fa-sub2{font-size:11.5px;font-weight:700;color:var(--ink3);margin-top:14px}
.fa-hl{display:grid;grid-template-columns:1fr;gap:10px}
@media(min-width:900px){.fa-hl{grid-template-columns:repeat(3,1fr)}}
.fa-hl div{padding:14px 16px;border-radius:14px;background:linear-gradient(150deg,hsl(250 65% 65% / .1),hsl(250 65% 65% / .03));border:1px solid hsl(250 65% 65% / .18)}
.fa-hl b{display:block;font-size:20px;font-weight:900;color:var(--p2)}
.fa-hl p{font-size:13px;margin-top:4px;line-height:1.45}
/* decisions */
.fa-dec-h{padding:20px;border-radius:18px 18px 0 0;background:linear-gradient(150deg,#262056,#3d3489);color:#fff;position:relative;overflow:hidden}
.fa-dec-h::after{content:"";position:absolute;inset-inline-end:-40px;top:-60px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,.07)}
.fa-dec-h h3{font-size:19px;font-weight:900;margin-top:8px}
.fa-dec-h p{font-size:13px;opacity:.8;margin-top:4px;max-width:70ch;position:relative}
.fa-dec-h code{display:inline-block;margin-top:10px;font-size:11px;background:rgba(255,255,255,.12);padding:4px 8px;border-radius:8px;font-family:inherit}
.fa-lanes{display:flex;gap:12px;overflow-x:auto;scroll-snap-type:x mandatory;padding:14px;scrollbar-width:none}
.fa-lanes::-webkit-scrollbar{display:none}
.fa-lane{flex:0 0 min(280px,80vw);scroll-snap-align:start;background:hsl(220 15% 97%);border-radius:14px;padding:12px}
@media(min-width:1000px){.fa-lanes{display:grid;grid-template-columns:repeat(4,1fr);overflow:visible}.fa-lane{flex:none}}
.fa-lane .lh{display:flex;align-items:center;gap:8px;font-weight:900;font-size:14px}
.fa-lane .lh i{width:10px;height:10px;border-radius:50%;background:var(--c)}
.fa-lane .lh em{margin-inline-start:auto;font-style:normal;font-size:11px;font-weight:800;color:#fff;background:var(--c);border-radius:99px;padding:1px 8px}
.fa-lane .ls{font-size:11.5px;color:var(--ink3);margin-top:1px}
.fa-dc{margin-top:9px;background:#fff;border:1px solid var(--line);border-top:3px solid var(--c);border-radius:12px;padding:10px 12px}
.fa-dc b{display:block;font-size:13px}
.fa-dc p{font-size:12px;color:var(--ink2);margin-top:4px}
.fa-dc .m{display:flex;gap:5px;flex-wrap:wrap;margin-top:7px}
.fa-dc .m span{font-size:10px;font-weight:700;background:var(--mut);border-radius:99px;padding:2px 8px;color:var(--ink2)}
.fa-empty-lane{font-size:12px;color:var(--ink3);margin-top:10px}
.fa-table{width:100%;border-collapse:collapse;font-size:13px}
.fa-table th{font-size:11px;font-weight:700;color:var(--ink3);text-align:start;padding:8px 10px;border-bottom:1px solid var(--line);white-space:nowrap}
.fa-table td{padding:8px 10px;border-bottom:1px solid var(--line)}
.fa-table tr:last-child td{border-bottom:0}
.fa-pill{display:inline-block;font-size:10.5px;font-weight:800;color:#fff;border-radius:99px;padding:2px 8px;white-space:nowrap}
/* empty + example */
.fa-empty{text-align:center;padding:40px 20px}
.fa-empty .ic{width:56px;height:56px;border-radius:18px;background:var(--acc);color:var(--p2);display:grid;place-items:center;margin:0 auto;font-size:24px}
.fa-empty h3{font-size:17px;font-weight:900;margin-top:14px}
.fa-empty p{font-size:13px;color:var(--ink2);margin-top:4px;max-width:46ch;margin-inline:auto}
.fa-example{display:flex;align-items:center;gap:10px;background:hsl(38 92% 50% / .1);color:#92400e;border:1px solid hsl(38 92% 50% / .25);border-radius:14px;padding:10px 14px;font-size:13px;font-weight:600}
.fa-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:12px;padding:9px 14px;font-size:13px;font-weight:700;border:1px solid var(--p);color:var(--p2);background:#fff;transition:background .15s,color .15s;cursor:pointer;text-decoration:none}
.fa-btn:hover{background:var(--p);color:#fff}
.fa-btn.solid{background:var(--p);color:#fff}
.fa-btn.solid:hover{background:var(--p2)}
.fa-btn.ghost{border-color:var(--line);color:var(--ink2)}
.fa-btn.ghost:hover{background:var(--mut);color:var(--ink)}
.fa-live{display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:800}
.fa-live i{width:8px;height:8px;border-radius:50%;background:#4ade80;box-shadow:0 0 0 0 rgba(74,222,128,.6);animation:faPulse 1.8s infinite}
@keyframes faPulse{70%{box-shadow:0 0 0 8px rgba(74,222,128,0)}100%{box-shadow:0 0 0 0 rgba(74,222,128,0)}}
/* responses */
.fa-resp{display:flex;align-items:center;gap:12px;width:100%;text-align:start;padding:12px 14px;border-top:1px solid var(--line);transition:background .12s}
.fa-resp:first-child{border-top:0}
.fa-resp:hover{background:hsl(220 15% 97%)}
.fa-resp .av{width:38px;height:38px;border-radius:12px;background:var(--acc);color:var(--p2);display:grid;place-items:center;font-weight:900;flex:0 0 auto}
.fa-resp .tx{flex:1;min-width:0}
.fa-resp .tx b{display:block;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fa-resp .tx span{font-size:12px;color:var(--ink3)}
.fa-ring{width:34px;height:34px;flex:0 0 auto}
.fa-ans{padding:12px 0;border-top:1px solid var(--line)}
.fa-ans:first-child{border-top:0}
.fa-ans p{font-size:12px;color:var(--ink3)}
.fa-ans div{font-size:14px;margin-top:2px;white-space:pre-line}
.fa-qr{background:#fff;border:1px solid var(--line);border-radius:18px;padding:14px;width:200px;max-width:100%}
.fa-qr svg{display:block;width:100%;height:auto}
.fa-link{display:flex;align-items:center;gap:8px;border:1px solid var(--line);border-radius:12px;padding:9px 10px 9px 12px;margin-top:8px;background:hsl(220 15% 98%)}
.fa-link code{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12.5px;direction:ltr;text-align:left}
@media (prefers-reduced-motion:reduce){.fa *{animation:none!important;transition:none!important}}
</style>
<style>
.fa-fcard-main{display:flex;gap:14px;align-items:center;padding:16px 60px 16px 16px;text-decoration:none;color:inherit;border-radius:18px;transition:background .12s}
[dir=rtl] .fa-fcard-main{padding:16px 16px 16px 60px}
.fa-fcard-main:hover{background:hsl(220 15% 98.5%)}
.fa-fcard-ic{width:46px;height:46px;border-radius:14px;background:linear-gradient(150deg,#7c6bf5,#7c6bf5b0);color:#fff;display:grid;place-items:center;font-size:20px;flex:0 0 auto}
.fa-fcard-ic img{width:100%;height:100%;object-fit:cover;border-radius:inherit}
.fa-fcard-ic:has(img){width:72px;height:46px}
.fa-fcard-t{display:block;font-size:15px;font-weight:800;margin-top:6px;line-height:1.3}
.fa-fcard-n{text-align:center;flex:0 0 auto;min-width:70px}
.fa-fcard-n b{display:block;font-size:24px;font-weight:900;line-height:1}
.fa-fcard-n small{font-size:11px;color:var(--ink3)}
.fa-fcard-acts{display:flex;border-top:1px solid var(--line)}
.fa-fcard-acts>*{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:10px 4px;font-size:12px;font-weight:700;color:var(--ink2);text-decoration:none;background:none;border:0;cursor:pointer;transition:background .12s,color .12s}
.fa-fcard-acts>*+*{border-inline-start:1px solid var(--line)}
.fa-fcard-acts>*:hover{background:hsl(220 15% 97.5%);color:var(--p2)}
.fa-fcard-acts .wa:hover{color:#128c4b}
.fa-fcard-acts i{font-size:14px}
.fa-wa{border-color:#25d366;color:#128c4b}
.fa-wa:hover{background:#25d366;color:#fff}
.fa-fcard-edit{position:absolute;top:14px;inset-inline-end:14px;width:36px;height:36px;border-radius:12px;border:1px solid var(--line);display:grid;place-items:center;color:var(--ink2);background:#fff;transition:all .12s}
.fa-fcard-edit:hover{background:var(--p);color:#fff;border-color:var(--p)}
@media(max-width:640px){.fa-fcard-main{flex-wrap:wrap}.fa-fcard-n{text-align:start;display:flex;gap:6px;align-items:baseline;min-width:0;padding-inline-start:60px}.fa-fcard-n b{display:inline;font-size:16px}.fa-fcard-n small{display:inline!important}}
</style>
