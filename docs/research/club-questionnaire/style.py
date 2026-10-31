F = '/var/www/takeone/public/fonts/designer'
css = f'''
@font-face{{font-family:Inter;src:url(file://{F}/inter/Inter-Regular.ttf);font-weight:400}}
@font-face{{font-family:Inter;src:url(file://{F}/inter/Inter-SemiBold.ttf);font-weight:600}}
@font-face{{font-family:Inter;src:url(file://{F}/inter/Inter-Bold.ttf);font-weight:700}}
@font-face{{font-family:Bricolage;src:url(file://{F}/bricolage-grotesque/BricolageGrotesque-VF.ttf);font-weight:200 800}}
@font-face{{font-family:Serif;src:url(file://{F}/instrument-serif/InstrumentSerif-Italic.ttf);font-style:italic}}
@font-face{{font-family:Plex;src:url(file://{F}/ibm-plex-sans-arabic/IBMPlexSansArabic-Regular.ttf);font-weight:400}}
@font-face{{font-family:Plex;src:url(file://{F}/ibm-plex-sans-arabic/IBMPlexSansArabic-Bold.ttf);font-weight:700}}
@font-face{{font-family:Alexandria;src:url(file://{F}/alexandria/Alexandria-VF.ttf);font-weight:100 900}}

:root{{--p:#7b68ee;--p2:#b3a8f6;--ink:#17133a;--mute:#6b6a80;--line:#d9d6ea;--soft:#f3f1fc;--amber:#d97706}}
@page{{size:A4;margin:15mm 14mm 17mm;
  @bottom-left{{content:"TAKEONE · Club Discovery Visit";font:600 7.5pt Inter;color:#9a97b3;letter-spacing:.06em}}
  @bottom-center{{content:counter(page);font:700 8pt Inter;color:#7b68ee}}
  @bottom-right{{content:"زيارة استكشاف احتياجات الأندية";font:400 7.5pt Plex;color:#9a97b3}}}}
@page cover{{margin:0;@bottom-left{{content:none}}@bottom-right{{content:none}}@bottom-center{{content:none}}}}
*{{box-sizing:border-box;margin:0;padding:0}}
html{{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
body{{font:400 9.6pt/1.45 Inter,Plex,sans-serif;color:var(--ink)}}
[dir=rtl]{{font-family:Plex,Inter,sans-serif;text-align:right}}
h1,h2{{font-family:Bricolage,Inter;font-weight:800;letter-spacing:-.02em}}
[dir=rtl] h2{{font-family:Alexandria,Plex;letter-spacing:0}}

/* cover */
.cover{{page:cover;height:297mm;position:relative;overflow:hidden;color:#fff;
  background:radial-gradient(120% 80% at 100% 0%,#9d8cf7 0%,transparent 55%),
             radial-gradient(90% 70% at 0% 100%,#3b2a9a 0%,transparent 60%),
             linear-gradient(160deg,#6a55e6,#2a1f78);break-after:page}}
.cover .ring{{position:absolute;border-radius:50%;border:1.5px solid rgba(255,255,255,.18)}}
.cover .r1{{width:150mm;height:150mm;right:-50mm;top:-40mm}}
.cover .r2{{width:95mm;height:95mm;right:-18mm;top:-8mm;background:rgba(255,255,255,.07);border:none}}
.cover .r3{{width:60mm;height:60mm;left:-20mm;bottom:70mm;background:rgba(255,255,255,.06);border:none}}
.cover .in{{position:absolute;inset:22mm 20mm 20mm}}
.brand{{font:800 11pt Bricolage;letter-spacing:.32em}}
.brand small{{display:block;font:600 7pt Inter;letter-spacing:.24em;opacity:.7;margin-top:1.5mm}}
.cover h1{{font-size:46pt;line-height:.98;margin-top:52mm}}
.cover h1 em{{font:italic 400 54pt Serif;letter-spacing:0;color:#e3dcff}}
.cover .arT{{font:800 30pt/1.3 Alexandria;margin-top:7mm;text-align:right}}
.cover .lead{{margin-top:8mm;max-width:120mm;font-size:10.5pt;opacity:.92}}
.cover .leadar{{max-width:120mm;margin-left:auto;font-size:10.5pt;opacity:.92;margin-top:4mm}}
.card{{position:absolute;left:0;right:0;bottom:0;background:#fff;color:var(--ink);border-radius:5mm;padding:5mm 7mm 6mm;
  display:grid;grid-template-columns:1fr 1fr;gap:3mm 8mm;box-shadow:0 10px 40px rgba(20,10,80,.35)}}
.card .f{{border-bottom:1px solid var(--line);padding-bottom:1.6mm;display:flex;justify-content:space-between;gap:3mm;font-size:8pt;color:var(--mute);height:12mm;align-items:flex-end}}
.card .f b{{color:var(--ink);font-weight:600}}

/* guide */
.guide{{break-after:page}}
.kicker{{font:700 7.5pt Inter;letter-spacing:.2em;text-transform:uppercase;color:var(--p)}}
.guide h2{{font-size:24pt;margin:2mm 0 0}}
.guide .arh{{font:800 18pt Alexandria;text-align:right;margin-top:-9mm}}
.two{{display:grid;grid-template-columns:1fr 1fr;gap:9mm;margin-top:7mm}}
.steps li{{list-style:none;display:flex;gap:3mm;margin-bottom:3.8mm;font-size:9.2pt}}
.steps li i{{flex:0 0 6.5mm;height:6.5mm;border-radius:50%;background:var(--soft);color:var(--p);font:700 8pt Inter;font-style:normal;display:grid;place-items:center}}
.steps b{{display:block;font-weight:700}}
.tip{{margin-top:5mm;border-radius:4mm;background:var(--ink);color:#fff;padding:5mm 6mm;display:grid;grid-template-columns:1fr 1fr;gap:8mm;font-size:9pt}}
.tip b{{color:#c9bfff}}
.legend{{margin-top:5mm;display:grid;grid-template-columns:repeat(4,1fr);gap:3mm}}
.legend div{{border:1px solid var(--line);border-radius:3mm;padding:3mm;font-size:8pt;color:var(--mute);display:flex;flex-direction:column;gap:1mm}}
.legend b{{color:var(--ink);font-size:8.6pt;display:flex;align-items:center;gap:1.6mm}}
.legend .dot{{width:5mm;height:5mm;font-size:6.5pt}}
.consent{{margin-top:5mm;border:1.5px dashed var(--p2);border-radius:4mm;padding:4mm 5mm;display:grid;grid-template-columns:1fr 1fr;gap:8mm;font-size:8.6pt}}

/* sections */
.sec{{break-before:page}}
.band{{position:relative;overflow:hidden;border-radius:5mm;color:#fff;padding:6mm 7mm;
  background:linear-gradient(150deg,#7b68ee,#7b68eeb0),#4b3bc4}}
.band .c1,.band .c2{{position:absolute;border-radius:50%;background:rgba(255,255,255,.12)}}
.band .c1{{width:44mm;height:44mm;right:-12mm;top:-16mm}}
.band .c2{{width:22mm;height:22mm;left:44%;bottom:-11mm}}
.bandrow{{position:relative;display:grid;grid-template-columns:auto 1fr 1fr;gap:6mm;align-items:center}}
.band .num{{font:300 34pt/1 Bricolage;opacity:.85;min-width:14mm}}
.band h2{{font-size:16pt;line-height:1.12}}
.band .ar h2{{font-size:15pt;font-weight:700;line-height:1.35}}
.band p{{font-size:8.5pt;opacity:.92;margin-top:1.5mm}}
.probe{{display:grid;grid-template-columns:auto 1fr 1fr;gap:4mm;align-items:start;margin:4mm 0 1mm;padding:3mm 4mm;border-radius:3mm;
  background:#fff7e8;border:1px solid #f5d9a6;font-size:8.3pt;color:#7a4a05}}
.probe b{{color:var(--amber);font-weight:700;margin-inline-end:1.2mm}}
.probe .pi{{color:var(--amber);font-size:9pt}}

.q{{break-inside:avoid;padding:3.6mm 0 3.2mm;border-bottom:1px solid #ebe9f4}}
.qh{{display:grid;grid-template-columns:9mm 1fr 1fr;gap:5mm;align-items:start}}
.qn{{font:700 8.5pt Inter;color:var(--p);background:var(--soft);border-radius:2mm;padding:.8mm 0;text-align:center}}
.qen{{font-weight:600;font-size:9.8pt}}
.qar{{font-weight:700;font-size:10pt;line-height:1.55}}
.hint{{display:block;font-weight:400;font-size:7.4pt;color:var(--mute);margin-top:.6mm}}
.opts,.other,.note,.scale,.nums,.q>.lines{{margin-left:14mm}}
.opts{{display:flex;flex-wrap:wrap;gap:2mm;margin-top:2.6mm}}
.opt{{display:inline-flex;align-items:center;gap:1.6mm;border:1px solid var(--line);border-radius:10mm;padding:1.1mm 3mm 1.1mm 2mm;font-size:8.3pt;background:#fff}}
.opt .ar{{font-size:8.4pt;color:var(--mute);padding-left:1.6mm;border-left:1px solid var(--line)}}
.box{{display:inline-block;width:3.2mm;height:3.2mm;border:1.3px solid var(--p);border-radius:.8mm;flex:none}}
.other,.note{{display:flex;align-items:flex-end;gap:2mm;margin-top:2.4mm;font-size:8.3pt;color:var(--mute)}}
.rule{{flex:1;border-bottom:1px solid var(--line);height:4mm}}
.lines span{{display:block;height:7.4mm;border-bottom:1px solid var(--line)}}
.scale{{display:flex;align-items:center;gap:5mm;margin-top:3mm}}
.scale .lo,.scale .hi{{display:flex;flex-direction:column;font-size:7.8pt;color:var(--mute);width:30mm}}
.scale .hi{{text-align:right}}
.scale b{{color:var(--ink);font-weight:600}}
.dots{{display:flex;gap:4.5mm;flex:1;justify-content:center}}
.dot{{width:8.5mm;height:8.5mm;border-radius:50%;border:1.3px solid var(--p2);display:grid;place-items:center;font:700 8.5pt Inter;color:var(--p);background:#fff}}
.nums{{display:grid;grid-template-columns:repeat(6,1fr);gap:2.5mm;margin-top:3mm}}
.cell{{border:1px solid var(--line);border-radius:2.5mm;padding:2mm;background:#fff;display:flex;flex-direction:column}}
.cell .v{{height:9mm;border-bottom:1px dashed var(--line);margin-bottom:1.5mm}}
.cell .en{{font-size:7.4pt;font-weight:600}}
.cell .ar{{font-size:7.6pt;color:var(--mute)}}
.obs{{break-inside:avoid;margin-top:4mm;padding:3.5mm 4mm 2mm;background:var(--soft);border-radius:3mm}}
.obs>.en{{font:700 7.6pt Inter;letter-spacing:.14em;text-transform:uppercase;color:var(--p)}}
.obs>.ar{{float:right;font:700 8.4pt Plex;color:var(--p)}}
.obs .lines span{{border-color:#d6d0f3}}

/* priority grid */
table.pri{{width:100%;border-collapse:separate;border-spacing:0;margin-top:4mm;font-size:8.6pt}}
.pri th{{font:700 7.2pt Inter;letter-spacing:.08em;text-transform:uppercase;color:var(--mute);padding:2mm 1.5mm;border-bottom:1.5px solid var(--ink);text-align:center}}
.pri th small{{display:block;font:700 7.8pt Plex;letter-spacing:0;text-transform:none}}
.pri td{{padding:1.75mm 1.5mm;border-bottom:1px solid #ebe9f4;vertical-align:middle}}
.pri tr:nth-child(even) td{{background:#faf9fe}}
.pri .n{{color:var(--p);font-weight:700;width:7mm}}
.pri .ar{{font-family:Plex;text-align:right}}
.pri .c{{text-align:center;width:15mm}}
.pri .c .box{{width:3.6mm;height:3.6mm}}
.pri .rk{{width:13mm;text-align:center}}
.pri .rk span{{display:inline-block;width:8mm;border-bottom:1px solid var(--line);height:4mm}}

/* speak your mind */
.speak .band{{background:linear-gradient(150deg,#17133a,#2c2470),#17133a}}
.speak .band .num{{font-size:26pt}}
.prompt{{break-inside:avoid;margin-top:4mm}}
.prompt .qn{{background:var(--ink);color:#fff}}
.prompt .lines{{margin-left:14mm}}
.free{{break-before:page}}
.free .ttl{{display:flex;justify-content:space-between;align-items:baseline;margin-top:1.5mm}}
.free .ttl h2{{font-size:20pt}}
.free .ttl span{{font:800 15pt Alexandria}}
.free>.lines{{margin-top:4mm}}
.free>.lines span{{height:8.6mm}}
.free .q{{border:none;padding-bottom:1mm}}
.score{{display:grid;grid-template-columns:repeat(5,1fr);gap:3mm;margin-top:4mm}}
.score div{{border:1px solid var(--line);border-radius:3mm;padding:3mm 2mm;text-align:center;font-size:8pt;line-height:1.6}}
.score div span{{display:block;color:var(--mute);font-family:Plex}}
.thanks{{margin-top:7mm;text-align:center;font:italic 400 20pt Serif;color:var(--p)}}
.thanks span{{display:block;text-align:center;font:700 14pt Alexandria;color:var(--ink);margin-top:1mm}}
'''

