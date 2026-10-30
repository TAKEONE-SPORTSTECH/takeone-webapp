#!/usr/bin/env python3
"""Findings & action plan (ILLUSTRATIVE) — built from findings.json, which analysis.py computes."""
import html, json
from style import F

def e(s): return html.escape(str(s))
D = json.load(open('findings.json'))
FE = {f['id']: f for f in D['features']}
N = D['n']

ST = {'exists': ('Exists', '✓', '#2e9d6a', '#e8f6ef'), 'partial': ('Partial', '◐', '#a86f00', '#fdf3e0'),
      'missing': ('Missing', '✕', '#c23a30', '#fbeceb')}
EFF = {1: 'S', 2: 'M', 3: 'L'}

def chip(status):
    l, ic, ink, bg = ST[status]
    return f'<span class="st" style="color:{ink};background:{bg}"><b>{ic}</b> {l}</span>'

THEME_OF = {5: 'PAY', 9: 'DISC', 8: 'PAUSE', 12: 'COACH', 13: 'WA', 20: 'APP', 4: 'DROP', 14: 'BELT', 10: 'PAY'}
TH = {t['code']: t for t in D['themes']}
METRIC = {
    5: 'Owner’s hours chasing payments: 2–3 h a week → under 30 min (re-ask Q21)',
    9: 'Sibling discounts worked out by hand each month → never',
    8: 'Wrong charges after a pause → zero; every pause on record',
    12: 'Month-end coach pay: an evening → five minutes',
    13: 'Owner’s WhatsApp replies a day cut by half; owner approves every automatic message',
    20: 'Every member and parent screen readable in Arabic',
    10: 'A club can offer a 3-part payment plan without a spreadsheet (manual, no gateway)',
    4: 'Attendance taken in every class; a member absent 3 weeks is flagged',
    15: 'Parents asking “how is my child doing?” goes down (re-ask Q29)',
    14: 'A grading run end to end in the app, certificate included',
}
WHAT = {
    5: 'One red “who owes” list, and reminders that go out on their own — never to someone who paid in cash.',
    9: 'Sibling and special discounts set once on the family, applied every month.',
    8: 'Pause a subscription with a reason and dates; billing skips it and restarts by itself.',
    12: 'Per-class coach pay worked out from the classes actually taught.',
    13: 'Club messages on WhatsApp, with the owner deciding what is ever sent automatically.',
    20: 'Finish the Arabic member app — every screen a parent sees.',
    10: 'Split a fee into dated parts, each with its own proof-of-payment and approval.',
    4: 'Check-in at the door from a tablet or QR; absence alerts.',
    15: 'A monthly progress card a parent receives: attendance, belt, coach note.',
    14: 'The belt-test event type: candidates, scoring sheet, results onto the profile, certificates.',
}

def why(f):
    t = TH.get(THEME_OF.get(f['id'], ''), None)
    s = f'Must for {f["must"]} of {N} · in {f["top5"]} clubs’ top 5'
    if t:
        s += f' · pain “{t["label"]}” heard at {t["n"]} of {t["of"]}'
    return s

CSS = f'''
@font-face{{font-family:Inter;src:url(file://{F}/inter/Inter-Regular.ttf);font-weight:400}}
@font-face{{font-family:Inter;src:url(file://{F}/inter/Inter-SemiBold.ttf);font-weight:600}}
@font-face{{font-family:Inter;src:url(file://{F}/inter/Inter-Bold.ttf);font-weight:700}}
@font-face{{font-family:Bricolage;src:url(file://{F}/bricolage-grotesque/BricolageGrotesque-VF.ttf);font-weight:200 800}}
@font-face{{font-family:Serif;src:url(file://{F}/instrument-serif/InstrumentSerif-Italic.ttf);font-style:italic}}
@font-face{{font-family:Plex;src:url(file://{F}/ibm-plex-sans-arabic/IBMPlexSansArabic-Regular.ttf);font-weight:400}}
@font-face{{font-family:Plex;src:url(file://{F}/ibm-plex-sans-arabic/IBMPlexSansArabic-Bold.ttf);font-weight:700}}
@font-face{{font-family:Alexandria;src:url(file://{F}/alexandria/Alexandria-VF.ttf);font-weight:100 900}}
@font-face{{font-family:Hand;src:url(file://{F}/caveat/Caveat-VF.ttf);font-weight:400 700}}
:root{{--p:#7b68ee;--p2:#b3a8f6;--deep:#2a1f78;--ink:#17133a;--mute:#6b6a80;--line:#e4e1f0;--soft:#f3f1fc;--amber:#d97706}}
@page{{size:A4;margin:14mm 13mm 16mm;
  @bottom-left{{content:"TAKEONE · From answers to action · ILLUSTRATIVE DATA";font:600 7pt Inter;color:#9a97b3;letter-spacing:.06em}}
  @bottom-right{{content:counter(page);font:700 8pt Inter;color:#7b68ee}}}}
@page info{{margin:0;@bottom-left{{content:none}}@bottom-right{{content:none}}}}
*{{box-sizing:border-box;margin:0;padding:0}}
html{{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
body{{font:400 9pt/1.45 Inter,Plex,sans-serif;color:var(--ink)}}
[dir=rtl]{{font-family:Plex,Inter;text-align:right}}
h1,h2,h3{{font-family:Bricolage,Inter;font-weight:800;letter-spacing:-.02em}}
.page{{break-before:page}}
.kicker{{font:700 7.2pt Inter;letter-spacing:.2em;text-transform:uppercase;color:var(--p)}}
.head{{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:1.5px solid var(--ink);padding-bottom:2.5mm;margin-bottom:5mm}}
.head h2{{font-size:21pt;line-height:1.05}}
.head .ar{{font:800 15pt Alexandria}}
.lede{{font-size:9.4pt;color:#3d3a5c;max-width:150mm;margin-bottom:5mm}}
.illus{{display:inline-block;font:700 6.8pt Inter;letter-spacing:.14em;color:#c2410c;background:#fff1e6;border-radius:1.5mm;padding:.8mm 2mm;margin-bottom:3mm}}

/* ---------- infographic ---------- */
.info{{page:info;height:297mm;position:relative;overflow:hidden;color:#fff;padding:16mm 15mm 12mm;
  background:radial-gradient(110% 60% at 100% 0%,#9d8cf7 0%,transparent 55%),radial-gradient(80% 50% at 0% 100%,#3b2a9a 0%,transparent 60%),linear-gradient(165deg,#5b47d6,#21185f)}}
.info .ring{{position:absolute;border-radius:50%;background:rgba(255,255,255,.06)}}
.info .top{{display:flex;justify-content:space-between;align-items:flex-start}}
.brand{{font:800 10pt Bricolage;letter-spacing:.32em}}
.info h1{{font-size:34pt;line-height:1;margin-top:9mm}}
.info h1 em{{font:italic 400 38pt Serif;color:#e3dcff;letter-spacing:0}}
.info .arT{{font:800 20pt/1.3 Alexandria;text-align:right;margin-top:2mm}}
.info .sub{{font-size:9.5pt;opacity:.88;max-width:120mm;margin-top:3mm}}
.flow{{position:relative;margin-top:8mm;display:grid;gap:3.2mm}}
.stage{{position:relative;display:grid;grid-template-columns:15mm 1fr 58mm;gap:4mm;align-items:center;
  background:rgba(255,255,255,.96);color:var(--ink);border-radius:4.5mm;padding:3.6mm 4.5mm}}
.stage .no{{width:13mm;height:13mm;border-radius:3.5mm;display:grid;place-items:center;background:var(--soft);color:var(--p)}}
.stage .no svg{{width:7mm;height:7mm}}
.stage h3{{font-size:11.5pt;line-height:1.15}}
.stage h3 small{{font:700 9pt Alexandria;color:var(--mute);margin-left:3mm}}
.stage p{{font-size:8pt;color:#4a4766;margin-top:.8mm}}
.stage .out{{font:600 7.2pt Inter;color:var(--p);margin-top:1.2mm;letter-spacing:.02em}}
.trace{{border-left:2px dashed #cfc7fb;padding-left:3.5mm;font:500 12pt/1.05 Hand;color:#1f3fae}}
.trace b{{display:block;font:700 6.4pt Inter;letter-spacing:.14em;color:var(--mute);text-transform:uppercase;margin-bottom:.8mm}}
.stage:not(:last-child)::after{{content:"";position:absolute;left:11.5mm;bottom:-3.4mm;width:2px;height:3.4mm;background:rgba(255,255,255,.6)}}
.foot{{position:absolute;left:15mm;right:15mm;bottom:11mm;display:flex;justify-content:space-between;font:600 7pt Inter;letter-spacing:.12em;opacity:.75}}
.legend3{{display:flex;gap:3mm;margin-top:4mm}}
.legend3 span{{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25);border-radius:10mm;padding:1.2mm 3mm;font:600 7.6pt Inter}}

/* ---------- method ---------- */
.cols{{display:grid;grid-template-columns:1fr 1fr;gap:6mm}}
.card{{border:1px solid var(--line);border-radius:3.5mm;padding:4mm 4.5mm;background:#fff;break-inside:avoid}}
.card h3{{font-size:11pt;margin-bottom:2mm}}
.card h3 span{{font:700 9pt Alexandria;color:var(--mute);float:right}}
.formula{{background:var(--soft);border-radius:2.5mm;padding:3mm 3.5mm;margin:2mm 0;font:600 8.6pt Inter}}
.formula code{{font:700 9pt Inter;color:var(--deep)}}
table.t{{width:100%;border-collapse:collapse;font-size:8pt}}
.t th{{font:700 6.8pt Inter;letter-spacing:.1em;text-transform:uppercase;color:var(--mute);text-align:left;padding:1.6mm 1.5mm;border-bottom:1.2px solid var(--ink)}}
.t td{{padding:1.7mm 1.5mm;border-bottom:1px solid var(--line);vertical-align:top}}
.t td.num,.t th.num{{text-align:right;font-variant-numeric:tabular-nums}}
.st{{display:inline-flex;align-items:center;gap:1mm;font:600 7.4pt Inter;border-radius:10mm;padding:.5mm 2.2mm;white-space:nowrap}}
.rule-row{{display:grid;grid-template-columns:24mm 1fr;gap:3mm;padding:2mm 0;border-bottom:1px solid var(--line);font-size:8.2pt}}
.rule-row b{{font:700 8.4pt Inter}}

/* ---------- data ---------- */
.tiles{{display:grid;grid-template-columns:repeat(4,1fr);gap:3.5mm;margin-bottom:5mm}}
.tile{{border-radius:3.5mm;padding:3.5mm 4mm;background:var(--soft)}}
.tile .v{{font:800 22pt/1 Bricolage;color:var(--deep)}}
.tile .l{{font:600 7.6pt Inter;color:var(--mute);margin-top:1.2mm}}
.bars .r{{display:grid;grid-template-columns:62mm 1fr 18mm;gap:3mm;align-items:center;padding:1.05mm 0;font-size:8pt}}
.bars .r .nm{{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}}
.track{{position:relative;height:4.2mm;background:#f4f3f9;border-radius:1mm}}
.fill{{position:absolute;left:0;top:0;bottom:0;border-radius:0 1.2mm 1.2mm 0;background:var(--p)}}
.track .val{{position:absolute;top:50%;transform:translateY(-50%);font:700 7.4pt Inter;color:var(--ink);padding-left:1.4mm}}
.axisnote{{font:600 7pt Inter;color:var(--mute);margin:1mm 0 2.5mm 65mm}}
.quote{{border-left:3px solid var(--p);padding:1.5mm 0 1.5mm 3.5mm;margin:2.5mm 0;font:500 13.5pt/1.15 Hand;color:#1f3fae}}
.quote small{{display:block;font:600 7pt Inter;color:var(--mute);margin-top:1mm}}

/* ---------- plan ---------- */
.lanes{{display:grid;grid-template-columns:repeat(3,1fr);gap:4mm}}
.lane h3{{font-size:12pt;display:flex;justify-content:space-between;align-items:baseline}}
.lane h3 small{{font:600 7.4pt Inter;color:var(--mute)}}
.lane .bar{{height:1.6mm;border-radius:1mm;margin:1.8mm 0 3mm}}
.item{{border:1px solid var(--line);border-radius:3mm;padding:3mm 3.2mm;margin-bottom:3mm;background:#fff;break-inside:avoid}}
.item .t1{{display:flex;justify-content:space-between;gap:2mm;align-items:flex-start}}
.item b{{font:700 8.8pt Inter;line-height:1.25}}
.eff{{flex:none;font:700 7pt Inter;color:var(--deep);background:var(--soft);border-radius:1.2mm;padding:.4mm 1.6mm}}
.item p{{font-size:7.6pt;color:#4a4766;margin-top:1.2mm}}
.item .why{{font:600 7pt Inter;color:var(--p);margin-top:1.4mm}}
.item .kpi{{font-size:7.3pt;margin-top:1.4mm;padding-top:1.4mm;border-top:1px dashed var(--line)}}
.item .kpi::before{{content:"Done when  ";font:700 6.6pt Inter;letter-spacing:.1em;color:var(--mute)}}
.showlane{{margin-top:2mm;border-radius:3.5mm;background:#e8f6ef;padding:4mm 4.5mm}}
.showlane h3{{font-size:11pt;color:#1d6b47}}
.pills{{display:flex;flex-wrap:wrap;gap:1.6mm;margin-top:2mm}}
.pills span{{background:#fff;border-radius:10mm;padding:.8mm 2.6mm;font:600 7.6pt Inter;color:#1d6b47}}
.loop{{display:grid;grid-template-columns:repeat(4,1fr);gap:3mm;margin-top:4mm}}
.loop div{{border-radius:3mm;background:var(--soft);padding:3mm;font-size:7.8pt}}
.loop b{{display:block;font:700 8.4pt Inter;margin-bottom:.8mm;color:var(--deep)}}
'''

ICON = {
 'visit': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 3v2h6V3M8.5 11l2 2 4-4M8.5 17h7"/></svg>',
 'grid': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M3 14h18M9 4v16M15 4v16"/></svg>',
 'tag': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12l-8 8-9-9V3h8z"/><circle cx="7.5" cy="7.5" r="1.4"/></svg>',
 'scale': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M5 21h14M6 7h12M6 7l-3 6a3 3 0 006 0zM18 7l-3 6a3 3 0 006 0z"/></svg>',
 'rank': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>',
 'plan': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h7M3 12h11M3 18h5"/><path d="M15 16l2.5 2.5L22 14"/></svg>',
}

f5, f8 = FE[5], FE[8]
STAGES = [
 ('visit', 'Collect', 'جمع', 'A visitor fills one questionnaire per club: 47 questions, the 22-line priority grid and the open “speak your mind” notes.',
  f'{N} paper forms · photos of what they were shown', 'Falcon Kicks · Q21 chasing = 5 · “2–3 hours every week”'),
 ('grid', 'Digitise', 'رقمنة', 'Each form becomes ONE row in a spreadsheet: every tick, circle and number in its own column. Same day as the visit.',
  'coded-responses.csv — 1 row per club', 'C01 · F05 = must · top-5 rank 1'),
 ('tag', 'Code the words', 'ترميز الكلام', 'Open answers are tagged with pain themes from a fixed codebook (PAY, PAUSE, WA…), by two people, then compared.',
  'theme count per club', '“who has NOT paid” → PAY · “30 kids paused” → PAUSE'),
 ('scale', 'Score demand', 'قياس الطلب', 'Demand = (Must + 0.4 × Nice) ÷ clubs. Top-5 votes and pain themes back it up; segments (sport, size) are split out.',
  'demand 0–100 per feature', f'Payment reminders · demand {f5["demand"]} · top-5 in {f5["top5"]} clubs'),
 ('rank', 'Compare with TAKEONE', 'المقارنة مع تيك ون', 'Each feature is marked Exists / Partial / Missing in the platform today. Opportunity = demand × gap; priority = opportunity ÷ effort.',
  'priority score per feature', f'Reminders · Partial · effort S → priority {f5["priority"]:g}'),
 ('plan', 'Plan & prove', 'الخطة والتحقق', 'The ranking fills NOW / NEXT / LATER by effort. What already exists becomes an onboarding job, not a build. Pilot clubs re-answer the same questions after 30 days.',
  'roadmap + a target per item', 'NOW: reminders + “who owes” list → re-ask Q21 at C01'),
]

def infographic():
    st = ''.join(f'''<div class="stage"><div class="no">{ICON[ic]}</div>
      <div><h3>{i}. {e(t)}<small dir="rtl">{e(ta)}</small></h3><p>{e(p)}</p><div class="out">→ {e(o)}</div></div>
      <div class="trace"><b>One answer, followed</b>{e(tr)}</div></div>''' for i, (ic, t, ta, p, o, tr) in enumerate(STAGES, 1))
    return f'''<section class="info">
  <span class="ring" style="width:120mm;height:120mm;right:-45mm;top:-40mm"></span>
  <span class="ring" style="width:70mm;height:70mm;left:-25mm;bottom:30mm"></span>
  <div class="top"><div class="brand">TAKEONE</div><div style="font:600 7pt Inter;letter-spacing:.14em;opacity:.8">ILLUSTRATIVE · FICTIONAL CLUBS</div></div>
  <h1>From a club visit<br>to a <em>build plan</em></h1>
  <div class="arT" dir="rtl">من زيارة النادي إلى خطة التنفيذ</div>
  <p class="sub">Six steps turn what clubs tell us into what we build next — and a way to prove it worked. On the right, one answer from one club, followed all the way through.</p>
  <div class="flow">{st}</div>
  <div class="legend3"><span>✓ Exists → show it</span><span>◐ Partial → finish it</span><span>✕ Missing → build it</span><span>Low demand → park it</span></div>
  <div class="foot"><span>TAKEONE · CLUB DISCOVERY</span><span dir="rtl" style="font-family:Plex;letter-spacing:0">اكتشاف احتياجات الأندية</span></div>
</section>'''

def method():
    codebook = ''.join(f'<tr><td><b>{t["code"]}</b></td><td>{e(t["label"])}</td><td>{"martial-arts clubs only" if t["martial_only"] else "all clubs"}</td></tr>' for t in sorted(D['themes'], key=lambda t: t['code']))
    return f'''<section class="page">
  <div class="head"><div><div class="kicker">Step 2–5 · How the answers are processed</div><h2>The method</h2></div><div class="ar" dir="rtl">طريقة المعالجة</div></div>
  <p class="lede">The same rules run over every form, so two people processing the same visits get the same answer. Nothing below needs special software — a spreadsheet does it; the scripts that built this report do it automatically.</p>
  <div class="cols">
    <div class="card"><h3>One row per club <span dir="rtl">صف لكل نادٍ</span></h3>
      <table class="t"><tr><th>Columns</th><th>From</th></tr>
      <tr><td>club code, sport, members, branches</td><td>cover + Q01–Q02</td></tr>
      <tr><td>segment (martial arts / other), size (small / large)</td><td>derived</td></tr>
      <tr><td>every tick, circle and number</td><td>Q01–Q47</td></tr>
      <tr><td>F01–F22 = must · nice · no, and the top-5 rank</td><td>priority grid</td></tr>
      <tr><td>pain themes (0/1 each)</td><td>open answers, coded</td></tr>
      <tr><td>interest, urgency, pilot, monthly price</td><td>visit summary + Q47</td></tr></table>
      <p style="font-size:7.6pt;color:var(--mute);margin-top:2mm">Club names stay on the paper form; the spreadsheet uses codes (C01…). Quotes are kept word for word.</p>
    </div>
    <div class="card"><h3>The scores <span dir="rtl">المؤشرات</span></h3>
      <div class="formula"><code>Demand</code> = (clubs saying Must + 0.4 × clubs saying Nice) ÷ clubs × 100</div>
      <div class="formula"><code>Gap</code> = Missing 1.0 · Partial 0.6 · Exists 0.2 &nbsp;— what TAKEONE has today</div>
      <div class="formula"><code>Opportunity</code> = Demand × Gap &nbsp;&nbsp; <code>Priority</code> = Opportunity ÷ Effort (S 1 · M 2 · L 3)</div>
      <p style="font-size:7.8pt;margin-top:2mm">Top-5 votes and pain themes are the <b>cross-check</b>: a feature with high demand but no pain behind it is questioned before it is built.</p>
    </div>
    <div class="card"><h3>Codebook for open answers <span dir="rtl">دليل الترميز</span></h3>
      <table class="t"><tr><th>Code</th><th>Theme</th><th>Asked of</th></tr>{codebook}</table>
    </div>
    <div class="card"><h3>Decision rules <span dir="rtl">قواعد القرار</span></h3>
      <div class="rule-row"><b>{chip("missing")}</b><span>Demand ≥ 45 → <b>build</b>, ordered by priority.</span></div>
      <div class="rule-row"><b>{chip("partial")}</b><span>Demand ≥ 45 → <b>finish</b> — usually the cheapest win.</span></div>
      <div class="rule-row"><b>{chip("exists")}</b><span>Demand ≥ 55 → <b>show &amp; onboard</b>: clubs want it and we have it; the gap is that they don’t know.</span></div>
      <div class="rule-row"><b>Low demand</b><span>Below the line → <b>park</b> or keep as is; re-check at the next round.</span></div>
      <div class="rule-row"><b>Capacity</b><span>NOW holds ≤ 4 effort points (≈ 4 weeks), NEXT ≤ 6, the rest is LATER.</span></div>
    </div>
  </div>
</section>'''

def data1():
    rows = sorted(D['features'], key=lambda f: -f['demand'])
    bars = ''.join(f'''<div class="r"><span class="nm">{f["id"]:02d} · {e(f["en"])}</span>
      <span class="track"><span class="fill" style="width:{f["demand"]}%"></span><span class="val" style="left:{f["demand"]}%">{f["demand"]}</span></span>
      {chip(f["status"])}</div>''' for f in rows)
    total = sum(c['members'] for c in D['clubs'])
    return f'''<section class="page">
  <div class="head"><div><div class="kicker">The processed data · 1 of 2</div><h2>What clubs asked for</h2></div><div class="ar" dir="rtl">ما طلبته الأندية</div></div>
  <span class="illus">ILLUSTRATIVE — 12 FICTIONAL CLUBS · THE REAL VISITS WILL REPLACE THESE NUMBERS</span>
  <div class="tiles">
    <div class="tile"><div class="v">{N}</div><div class="l">clubs visited</div></div>
    <div class="tile"><div class="v">{total:,}</div><div class="l">members between them</div></div>
    <div class="tile"><div class="v">{len(D["pilots"])}</div><div class="l">offered to pilot</div></div>
    <div class="tile"><div class="v">{D["wtp_median"]:g} BD</div><div class="l">median fair monthly price (range {D["wtp_range"][0]}–{D["wtp_range"][1]})</div></div>
  </div>
  <h3 style="font-size:11pt;margin-bottom:1mm">Demand per feature, and what TAKEONE has today</h3>
  <div class="axisnote">Demand 0–100 = (Must + 0.4 × Nice) ÷ {N} clubs</div>
  <div class="bars">{bars}</div>
</section>'''

def data2():
    th = ''.join(f'''<div class="r"><span class="nm">{e(t["label"])}{" *" if t["martial_only"] else ""}</span>
      <span class="track"><span class="fill" style="width:{t["share"]}%"></span><span class="val" style="left:{t["share"]}%">{t["share"]}%</span></span>
      <span style="font:600 7.4pt Inter;color:var(--mute)">{t["n"]} of {t["of"]}</span></div>''' for t in D['themes'])
    sc = D['scales']
    lab = {'reg_pain': 'Registering a member is painful (Q10)', 'chase': 'Time chasing payments (Q21)',
           'parents_ask': 'Parents ask “how is my child?” (Q29)', 'progress_value': 'Value of a progress report (Q34)',
           'video_value': 'Value of bout videos (Q38)'}
    tiles = ''.join(f'<div class="tile"><div class="v">{sc[k]:g}<span style="font-size:10pt;color:var(--mute)"> /5</span></div><div class="l">{lab[k]}</div></div>' for k in ('chase', 'progress_value', 'parents_ask', 'reg_pain'))
    seg = D['segments']
    segrows = ''.join(f'<tr><td><b>{e(g)}</b></td><td class="num">{v["n"]}</td><td>{" · ".join(e(FE[j]["en"]) for j in v["top"])}</td><td class="num">{v["wtp"]:g} BD</td></tr>' for g, v in seg.items())
    return f'''<section class="page">
  <div class="head"><div><div class="kicker">The processed data · 2 of 2</div><h2>What hurts, and who</h2></div><div class="ar" dir="rtl">ما يؤلمهم، ومن</div></div>
  <span class="illus">ILLUSTRATIVE — 12 FICTIONAL CLUBS</span>
  <h3 style="font-size:11pt;margin-bottom:1mm">Pain themes heard in the open answers</h3>
  <div class="axisnote">Share of clubs where the theme came up · * asked of martial-arts clubs only</div>
  <div class="bars">{th}</div>
  <h3 style="font-size:11pt;margin:5mm 0 2.5mm">The scales, averaged</h3>
  <div class="tiles">{tiles}</div>
  <div class="cols">
    <div class="card"><h3>Segments differ <span dir="rtl">الشرائح</span></h3>
      <table class="t"><tr><th>Segment</th><th class="num">Clubs</th><th>Top 3 musts</th><th class="num">Price</th></tr>{segrows}</table>
      <p style="font-size:7.6pt;color:var(--mute);margin-top:2mm">Belt tests and tournament entry matter to martial-arts clubs and almost nobody else — so they are sold to that segment, not built for everyone first.</p>
    </div>
    <div class="card"><h3>In their words <span dir="rtl">بكلماتهم</span></h3>
      <div class="quote">“If I could see one red list of who owes me, I would sleep.”<small>C01 · Taekwondo · owner</small></div>
      <div class="quote">“We lose them quietly.”<small>C01 · on members who stop coming</small></div>
      <div class="quote" style="font-family:Plex;font-size:10pt" dir="rtl">«أبي شي بسيط، الوالدين ما يبون تطبيق معقد»<small style="direction:ltr;text-align:left">C01 · “keep it simple, parents don’t want a complicated app”</small></div>
    </div>
  </div>
</section>'''

def priority():
    order = [(i, 'NOW') for i in D['now']] + [(i, 'NEXT') for i in D['next']] + [(i, 'LATER') for i in D['later']]
    rows = ''.join(f'<tr><td><b>{FE[i]["en"]}</b><br><span style="color:var(--mute);font-size:7.2pt">{e(FE[i]["note"])}</span></td><td class="num">{FE[i]["demand"]}</td>'
                   f'<td>{chip(FE[i]["status"])}</td><td class="num">{FE[i]["opportunity"]}</td><td class="num">{EFF[FE[i]["effort"]]}</td>'
                   f'<td class="num"><b>{FE[i]["priority"]:g}</b></td><td><b style="color:var(--p)">{ph}</b></td></tr>' for i, ph in order)
    show = ''.join(f'<tr><td>{e(FE[i]["en"])}</td><td class="num">{FE[i]["demand"]}</td><td>{chip("exists")}</td><td>Show &amp; onboard</td></tr>' for i in D['show'])
    return f'''<section class="page">
  <div class="head"><div><div class="kicker">Step 5 · The ranking</div><h2>What to build, in order</h2></div><div class="ar" dir="rtl">ترتيب الأولويات</div></div>
  <span class="illus">ILLUSTRATIVE — COMPUTED FROM THE 12 FICTIONAL CLUBS</span>
  <table class="t"><tr><th>Feature · what TAKEONE has today</th><th class="num">Demand</th><th>Today</th><th class="num">Opportunity</th><th class="num">Effort</th><th class="num">Priority</th><th>Phase</th></tr>{rows}</table>
  <h3 style="font-size:11pt;margin:6mm 0 2mm">Wanted, and already built — the job is to show it</h3>
  <table class="t"><tr><th>Feature</th><th class="num">Demand</th><th>Today</th><th>Action</th></tr>{show}</table>
  <h3 style="font-size:11pt;margin:6mm 0 0">How we prove it worked</h3>
  <div class="loop">
    <div><b>1 · Pilot</b>3 of the {len(D["pilots"])} volunteers — one small martial-arts club, one large club, one other sport.</div>
    <div><b>2 · Ship NOW</b>Pilot clubs get each NOW item first, with a short call to set it up.</div>
    <div><b>3 · Re-ask</b>After 30 days, the same questions again (Q10, Q21, Q29) — the scales are the before/after.</div>
    <div><b>4 · Re-rank</b>New answers go into the same sheet; the ranking updates; NEXT is confirmed or changed.</div>
  </div>
  <p style="font-size:7.4pt;color:var(--mute);margin-top:3mm">Not on the plan, on purpose: an online payment gateway (deferred by decision). Installments are planned as dated parts with proof-of-payment, the way payments already work.</p>
</section>'''

def lane(title, ar, weeks, ids, color):
    items = ''.join(f'''<div class="item"><div class="t1"><b>{e(FE[i]["en"])}</b><span class="eff">{EFF[FE[i]["effort"]]}</span></div>
      <p>{e(WHAT[i])}</p><div class="why">{e(why(FE[i]))}</div><div class="kpi">{e(METRIC[i])}</div></div>''' for i in ids)
    return f'''<div class="lane"><h3>{title} <small>{weeks}</small></h3><div style="font:700 9pt Alexandria;color:var(--mute)" dir="rtl">{ar}</div>
      <div class="bar" style="background:{color}"></div>{items}</div>'''

def plan():
    show = ''.join(f'<span>✓ {e(FE[i]["en"])}</span>' for i in D['show'])
    data = TH['DATA']
    return f'''<section class="page">
  <div class="head"><div><div class="kicker">Step 6 · The execution plan</div><h2>The action plan</h2></div><div class="ar" dir="rtl">خطة التنفيذ</div></div>
  <span class="illus">ILLUSTRATIVE — WHAT THE REAL ANSWERS WOULD PRODUCE</span>
  <div class="lanes">
    {lane("NOW", "الآن", "weeks 1–4", D["now"], "#7b68ee")}
    {lane("NEXT", "التالي", "weeks 5–10", D["next"], "#a99bf5")}
    {lane("LATER", "لاحقاً", "weeks 11–20", D["later"], "#d9d3fb")}
  </div>
  <div class="showlane"><h3>Alongside, from week 1: show what already exists</h3>
    <p style="font-size:8pt;color:#285c43">{len(D["show"])} features clubs rated as must-haves already exist in TAKEONE. Build an onboarding kit and demo each one with the club’s OWN member list — the member import already exists, and “fear of moving old data” came up at {data["n"]} of {data["of"]} clubs.</p>
    <div class="pills">{show}</div></div>
</section>'''

doc = (f'<!doctype html><html lang="en"><head><meta charset="utf-8"><title>TAKEONE — From answers to action (illustrative)</title>'
       f'<style>{CSS}</style></head><body>{infographic()}{method()}{data1()}{data2()}{priority()}{plan()}</body></html>')
open('report.html', 'w').write(doc)
info = (f'<!doctype html><html><head><meta charset="utf-8"><style>{CSS} @page{{size:210mm 297mm;margin:0}} body{{margin:0}}</style></head>'
        f'<body>{infographic()}</body></html>')
open('infographic.html', 'w').write(info)
print('ok')
