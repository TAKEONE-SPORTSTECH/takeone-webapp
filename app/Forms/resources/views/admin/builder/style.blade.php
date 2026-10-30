<style>
.fb{--p:hsl(250 65% 65%);--p2:hsl(250 62% 56%);--acc:hsl(250 60% 95%);--mut:hsl(220 15% 94%);--line:hsl(220 14% 90%);--ink:hsl(232 21% 14%);--ink2:hsl(229 13% 38%);--ink3:hsl(229 11% 58%);color:var(--ink)}
.fb [x-cloak]{display:none!important}
.fb-card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 1px 2px rgba(27,29,41,.04),0 6px 18px rgba(27,29,41,.05)}
.fb-lbl{display:block;font-size:12px;font-weight:700;color:var(--ink2);margin:12px 0 5px}
.fb-lbl:first-child{margin-top:0}
.fb-in{width:100%;border:1px solid hsl(220 14% 86%);border-radius:10px;padding:8px 10px;font-size:14px;outline:none;background:#fff;transition:border-color .12s,box-shadow .12s}
.fb-in:focus{border-color:var(--p);box-shadow:0 0 0 3px hsl(250 65% 65% / .15)}
.fb-in.ar{direction:rtl;text-align:right;font-family:"IBM Plex Sans Arabic",Tahoma,sans-serif}
textarea.fb-in{resize:vertical;min-height:62px}
.fb-pair{display:grid;grid-template-columns:1fr;gap:6px}
@media(min-width:640px){.fb-pair.two{grid-template-columns:1fr 1fr}}
.fb-flag{font-size:10px;font-weight:800;color:var(--ink3);letter-spacing:.06em;margin-bottom:2px;display:block}
.fb-sw{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-top:1px solid var(--line);font-size:13.5px;cursor:pointer}
.fb-sw small{display:block;font-size:11.5px;color:var(--ink3)}
.fb-tog{width:38px;height:22px;border-radius:99px;background:hsl(220 14% 84%);position:relative;flex:0 0 auto;transition:background .15s}
.fb-tog::after{content:"";position:absolute;top:3px;inset-inline-start:3px;width:16px;height:16px;border-radius:50%;background:#fff;transition:inset-inline-start .15s;box-shadow:0 1px 2px rgba(0,0,0,.2)}
.fb-tog.on{background:var(--p)}
.fb-tog.on::after{inset-inline-start:19px}
.fb-item{display:grid;grid-template-columns:1fr 1fr auto;gap:6px;align-items:center;margin-top:6px}
.fb-item .acts{display:flex;gap:2px}
.fb-ib{width:30px;height:30px;border-radius:8px;display:grid;place-items:center;color:var(--ink3);transition:all .12s;flex:0 0 auto}
.fb-ib:hover{background:var(--mut);color:var(--ink)}
.fb-ib.danger:hover{background:#fee2e2;color:#dc2626}
.fb-add{display:inline-flex;align-items:center;gap:6px;font-size:13px;font-weight:700;color:var(--p2);padding:8px 2px}
.fb-q{display:flex;gap:12px;align-items:flex-start;padding:12px 14px;border:1.5px solid var(--line);border-radius:14px;background:#fff;cursor:pointer;text-align:start;width:100%;transition:border-color .12s,box-shadow .12s}
.fb-q:hover{border-color:hsl(220 14% 80%)}
.fb-q.on{border-color:var(--p);box-shadow:0 0 0 4px hsl(250 65% 65% / .13)}
.fb-q.err{border-color:#dc2626}
.fb-q .ic{width:34px;height:34px;border-radius:10px;background:var(--acc);color:var(--p2);display:grid;place-items:center;flex:0 0 auto;font-size:15px}
.fb-q .tx{flex:1;min-width:0}
.fb-q .tx b{display:block;font-size:14px;font-weight:700;line-height:1.35}
.fb-q .tx span{display:block;font-size:12px;color:var(--ink3);margin-top:2px}
.fb-q .tx .ar{direction:rtl;text-align:right}
.fb-chip{font-style:normal;display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:800;padding:2px 7px;border-radius:99px;background:var(--mut);color:var(--ink2);margin-top:5px;margin-inline-end:4px}
.fb-chip.req{background:#fee2e2;color:#b91c1c}
.fb-chip.priv{background:hsl(38 92% 50% / .14);color:#b45309}
.fb-types{display:grid;grid-template-columns:repeat(2,1fr);gap:6px}
.fb-type{display:flex;align-items:center;gap:10px;padding:9px 10px;border:1px solid var(--line);border-radius:12px;background:#fff;font-size:13px;font-weight:600;text-align:start;transition:all .12s}
.fb-type:hover{border-color:var(--p);background:hsl(250 65% 65% / .05)}
.fb-type i{width:28px;height:28px;border-radius:8px;background:var(--acc);color:var(--p2);display:grid;place-items:center;flex:0 0 auto}
.fb-pg{display:flex;align-items:center;gap:10px;width:100%;text-align:start;padding:10px 12px;border-radius:12px;transition:background .12s}
.fb-pg:hover{background:var(--mut)}
.fb-pg.on{background:hsl(250 65% 65% / .1)}
.fb-pg .n{width:26px;height:26px;border-radius:8px;background:var(--mut);display:grid;place-items:center;font-size:12px;font-weight:800;color:var(--ink2);flex:0 0 auto}
.fb-pg.on .n{background:var(--p);color:#fff}
.fb-pg .tx{flex:1;min-width:0;font-size:13.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fb-pg small{font-size:11px;color:var(--ink3)}
.fb-err{font-size:12px;color:#dc2626;font-weight:600;margin-top:6px}
.fb-save{display:inline-flex;align-items:center;gap:8px;padding:9px 18px;border-radius:12px;background:var(--p);color:#fff;font-weight:800;font-size:14px;box-shadow:0 6px 16px hsl(250 65% 65% / .3);transition:background .12s,opacity .12s}
.fb-save:hover{background:var(--p2)}
.fb-save:disabled{opacity:.6}
.fb-ghost{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;color:var(--ink2);font-weight:700;font-size:13px;text-decoration:none}
.fb-ghost:hover{background:var(--mut)}
.fb-dirty{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;color:#b45309}
.fb-dirty i{width:7px;height:7px;border-radius:50%;background:#f59e0b}
.fb-empty{text-align:center;padding:30px 16px;border:1.5px dashed hsl(220 14% 84%);border-radius:14px;color:var(--ink3);font-size:13px}
.fb-warn{display:flex;gap:8px;align-items:flex-start;background:hsl(38 92% 50% / .1);border:1px solid hsl(38 92% 50% / .25);color:#92400e;border-radius:12px;padding:10px 12px;font-size:12.5px}
.fb-cover{position:relative;aspect-ratio:16/9;border-radius:14px;overflow:hidden;background:linear-gradient(150deg,#7c6bf5,#7c6bf5b0)}
.fb-cover img{width:100%;height:100%;object-fit:cover;display:block}
.fb-cover-empty{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;color:rgba(255,255,255,.9);font-size:12.5px;font-weight:600;text-align:center;padding:16px}
.fb-cover-empty i{font-size:28px}
</style>
