#!/usr/bin/env python3
"""Renders the club questionnaire — blank, or filled in from an answers dict (a SAMPLE)."""
import html, re
from content import SECTIONS, FEATURES, SPEAK
from style import css as BASE_CSS, F

def e(s): return html.escape(str(s))
AR = re.compile('[؀-ۿ]')

def arabic(t):
    return len(AR.findall(t)) > len(re.findall('[A-Za-z]', t))

def ink(text, cls='ink'):
    """Handwriting: Ruqaa for an Arabic line, Caveat for the rest — line by line."""
    if not text:
        return ''
    if 'block' in cls:
        rows = ''.join(
            f'<span class="row ar-ink" dir="rtl">{e(l)}</span>' if arabic(l) else f'<span class="row">{e(l) or "&nbsp;"}</span>'
            for l in text.split('\n'))
        return f'<span class="{cls}">{rows}</span>'
    if arabic(text):
        return f'<span class="{cls} ar-ink" dir="rtl">{e(text)}</span>'
    return f'<span class="{cls}">{e(text)}</span>'

# ---------------------------------------------------------------- pieces
def boxes(opts, picked=()):
    out = []
    for a, b in opts:
        on = a in picked
        out.append(f'<span class="opt{" picked" if on else ""}"><i class="box{" on" if on else ""}"></i>'
                   f'<span class="en">{e(a)}</span><span class="ar" dir="rtl">{e(b)}</span></span>')
    return '<div class="opts">' + ''.join(out) + '</div>'

def other(text='', en='Other', ar='أخرى', style=''):
    on = ' on' if text else ''
    return (f'<div class="other"{style}><i class="box{on}"></i><span class="en">{en}</span>'
            f'<span class="rule">{ink(text)}</span><span class="ar" dir="rtl">{ar}</span></div>')

def note(text=''):
    return (f'<div class="note"><span class="en">Note</span><span class="rule">{ink(text)}</span>'
            f'<span class="ar" dir="rtl">ملاحظة</span></div>')

def lines(n, text=''):
    if not text:
        return '<div class="lines">' + '<span></span>' * n + '</div>'
    return f'<div class="hw" style="min-height:{n * 7.4}mm">{ink(text, "ink block")}</div>'

def qhead(n, en, ar, hint_en='', hint_ar=''):
    he = f'<span class="hint">{e(hint_en)}</span>' if hint_en else ''
    ha = f'<span class="hint">{e(hint_ar)}</span>' if hint_ar else ''
    return (f'<div class="qh"><span class="qn">{n:02d}</span>'
            f'<div class="qen">{e(en)}{he}</div><div class="qar" dir="rtl">{e(ar)}{ha}</div></div>')

def render_q(n, q, A):
    a = A.get(f'q{n}', {})
    k = q[0]
    if k == 'text':
        _, en, ar, ln = q
        return f'<div class="q">{qhead(n, en, ar)}{lines(ln, a.get("text", ""))}</div>'
    if k == 'choice':
        _, en, ar, opts, oth = q
        return (f'<div class="q">{qhead(n, en, ar, "Tick all that apply", "اختر كل ما ينطبق")}'
                f'{boxes(opts, a.get("pick", ()))}{other(a.get("other", "")) if oth else ""}</div>')
    if k == 'one':
        _, en, ar, opts = q
        return (f'<div class="q">{qhead(n, en, ar, "Tick one", "اختر واحداً")}{boxes(opts, a.get("pick", ()))}'
                f'{note(a.get("note", ""))}</div>')
    if k == 'scale':
        _, en, ar, lo, hi = q
        v = a.get('value')
        dots = ''.join(f'<span class="dot{" on" if v == i else ""}">{i}</span>' for i in range(1, 6))
        return (f'<div class="q">{qhead(n, en, ar, "Circle one", "ضع دائرة")}'
                f'<div class="scale"><span class="lo"><b>{e(lo[0])}</b><span dir="rtl" style="text-align:left">{e(lo[1])}</span></span>'
                f'<span class="dots">{dots}</span>'
                f'<span class="hi"><b>{e(hi[0])}</b><span dir="rtl">{e(hi[1])}</span></span></div>'
                f'{note(a.get("note", "")) if a.get("note") else ""}</div>')
    if k == 'num':
        _, en, ar, cells = q
        vals = a.get('values', [''] * len(cells))
        c = ''.join(f'<div class="cell"><span class="v">{ink(v)}</span><span class="en">{e(x)}</span><span class="ar" dir="rtl">{e(y)}</span></div>'
                    for (x, y), v in zip(cells, vals))
        return f'<div class="q">{qhead(n, en, ar)}<div class="nums">{c}</div></div>'
    raise ValueError(k)

def band(num, en, ien, ar, iar):
    return f'''<header class="band"><span class="c1"></span><span class="c2"></span>
    <div class="bandrow"><div class="num">{num}</div>
      <div class="t"><h2>{e(en)}</h2><p>{e(ien)}</p></div>
      <div class="t ar" dir="rtl"><h2>{e(ar)}</h2><p>{e(iar)}</p></div>
    </div></header>'''

def circled(v, top=5):
    return ' '.join(f'<i class="mini{" on" if v == i else ""}">{i}</i>' for i in range(1, top + 1))

# ---------------------------------------------------------------- extra css
EXTRA = f'''
@font-face{{font-family:Hand;src:url(file://{F}/caveat/Caveat-VF.ttf);font-weight:400 700}}
@font-face{{font-family:HandAr;src:url(file://{F}/aref-ruqaa/ArefRuqaa-Regular.ttf)}}
.ink{{font:500 14pt/1 Hand;color:#1f3fae;letter-spacing:.01em}}
.ink.ar-ink{{font:400 12.5pt/1 HandAr}}
.ink.block{{display:block;white-space:pre-line;line-height:7.4mm;font-size:14.5pt}}
.ink.block .row{{display:block;height:7.4mm}}
.ink.block .row.ar-ink{{font:400 13pt/7.4mm HandAr;text-align:right}}
.score div span.ink{{display:block;color:#1f3fae;font:500 16pt Hand;margin-top:1mm}}
.mini{{font-style:normal}}
.card .f{{height:15mm!important}}
.hw{{margin-left:14mm;padding:0 1mm;background:repeating-linear-gradient(to bottom,transparent 0,transparent calc(7.4mm - 1px),var(--line) calc(7.4mm - 1px),var(--line) 7.4mm)}}
.hw .ink.block{{transform:translateY(1.1mm) rotate(-.25deg)}}
.obs .hw{{margin-left:0;background:repeating-linear-gradient(to bottom,transparent 0,transparent calc(7.4mm - 1px),#d6d0f3 calc(7.4mm - 1px),#d6d0f3 7.4mm)}}
.free>.hw{{margin:4mm 0 0}}
.prompt .hw{{margin-left:14mm}}
.rule{{position:relative}}
.rule .ink{{position:absolute;left:2mm;right:0;bottom:-.6mm;white-space:nowrap}}
.rule .ink.ar-ink{{left:auto;right:2mm}}
.box{{position:relative}}
.box.on::after{{content:"✓";position:absolute;left:-.6mm;top:-3.6mm;font:700 15pt Hand;color:#1f3fae;transform:rotate(-6deg)}}
.opt.picked{{border-color:#9fb0e6}}
.dot{{position:relative}}
.dot.on::after{{content:"";position:absolute;inset:-1.7mm -2.3mm;border:1.8px solid #1f3fae;border-radius:50%;transform:rotate(-9deg)}}
.cell .v{{display:flex;align-items:flex-end;justify-content:center}}
.cell .v .ink{{font-size:17pt}}
.card .f{{position:relative}}
.card .f .ink{{position:absolute;left:1mm;top:0;transform:rotate(-1deg);font-size:16pt;white-space:nowrap}}
.mini{{display:inline-grid;place-items:center;width:5mm;height:5mm;border-radius:50%;border:1px solid var(--p2);font:700 7pt Inter;color:var(--p);position:relative}}
.mini.on::after{{content:"";position:absolute;inset:-1mm -1.3mm;border:1.6px solid #1f3fae;border-radius:50%;transform:rotate(-9deg)}}
.pri .rk .ink{{font-size:15pt}}
.sig .ink{{font-size:17pt}}
.wm{{position:fixed;inset:0;display:grid;place-items:center;pointer-events:none;z-index:50}}
.wm span{{font:800 68pt Bricolage;color:rgba(123,104,238,.07);transform:rotate(-28deg);letter-spacing:.1em;white-space:nowrap}}
'''

GUIDE = '''
  <div class="kicker">Before you start · قبل أن تبدأ</div>
  <h2>A guide for the visit</h2>
  <div class="arh" dir="rtl">دليل الزيارة</div>
  <div class="two">
    <ol class="steps">
      <li><i>1</i><div><b>Listen, don’t sell.</b>We are here to learn how they work — not to demo or promise anything.</div></li>
      <li><i>2</i><div><b>30–45 minutes.</b>Skip sections that don’t apply. Depth beats coverage.</div></li>
      <li><i>3</i><div><b>Ask for stories.</b>“Tell me about the last time…” gives truer answers than “Do you…?”</div></li>
      <li><i>4</i><div><b>Write their words.</b>Exact phrases and complaints are gold. Use the observation boxes.</div></li>
      <li><i>5</i><div><b>Ask to see things.</b>Their register, a receipt, the WhatsApp group, a grading sheet.</div></li>
      <li><i>6</i><div><b>End open.</b>The “Speak your mind” pages matter most. Don’t rush them.</div></li>
    </ol>
    <ol class="steps" dir="rtl">
      <li><i>١</i><div><b>استمع ولا تبع.</b>نحن هنا لنتعلم طريقة عملهم — لا لنعرض أو نعد بشيء.</div></li>
      <li><i>٢</i><div><b>٣٠–٤٥ دقيقة.</b>تجاوز الأقسام غير المناسبة. العمق أهم من الشمول.</div></li>
      <li><i>٣</i><div><b>اطلب قصصاً.</b>«حدّثني عن آخر مرة…» يعطي إجابات أصدق من «هل تفعلون…؟»</div></li>
      <li><i>٤</i><div><b>اكتب كلماتهم.</b>العبارات والشكاوى كما قيلت كنز. استخدم مربعات الملاحظات.</div></li>
      <li><i>٥</i><div><b>اطلب أن ترى.</b>سجل الأعضاء، إيصال، مجموعة الواتساب، ورقة تقييم.</div></li>
      <li><i>٦</i><div><b>اختم بحوار مفتوح.</b>صفحات «قل رأيك بحرية» هي الأهم. لا تستعجلها.</div></li>
    </ol>
  </div>
  <div class="tip">
    <div><b>Opening line.</b> “We’re building a system for clubs in Bahrain and we want it shaped by the people who actually run them. There are no right answers — tell us how it really is.”</div>
    <div dir="rtl"><b>جملة البداية.</b> «نحن نبني نظاماً للأندية في البحرين، ونريده أن يُصمَّم بآراء من يديرونها فعلاً. لا توجد إجابات صحيحة — أخبرونا كيف هو الواقع.»</div>
  </div>
  <div class="legend">
    <div><b><i class="box"></i> Tick all</b><span dir="rtl">اختر كل ما ينطبق</span></div>
    <div><b><i class="box"></i> Tick one</b><span dir="rtl">اختر واحداً</span></div>
    <div><b><span class="dot">3</span> Circle</b><span dir="rtl">ضع دائرة على رقم</span></div>
    <div><b><span style="color:#d97706">◆</span> Interviewer</b><span dir="rtl">إرشاد للمحاور فقط</span></div>
  </div>'''

# ---------------------------------------------------------------- page
def page(A=None, title='TAKEONE — Club Discovery Questionnaire'):
    A = A or {}
    sample = bool(A)
    cv = A.get('cover', {})
    def f(label_en, label_ar, key):
        return f'<div class="f"><b>{label_en}</b>{ink(cv.get(key, ""))}<span dir="rtl">{label_ar}</span></div>'

    parts = []
    if sample:
        parts.append('<div class="wm"><span>SAMPLE · مثال</span></div>')
    badge = ('<p style="margin-top:8mm;display:inline-block;padding:2mm 4mm;border-radius:2mm;background:#ffffff33;'
             'font:700 9pt Inter;letter-spacing:.12em">SAMPLE — FILLED IN FOR A FICTIONAL CLUB · مثال لنادٍ افتراضي</p>') if sample else ''
    parts.append(f'''
<div class="cover">
  <span class="ring r1"></span><span class="ring r2"></span><span class="ring r3"></span>
  <div class="in">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <div class="brand">TAKEONE<small>SPORTSTECH</small></div>
      <div class="brand" dir="rtl" style="font-family:Alexandria;letter-spacing:0;font-size:11pt">تيك ون<small style="font-family:Plex;letter-spacing:0">للتقنيات الرياضية</small></div>
    </div>
    <h1>What does your<br>club <em>really</em> need?</h1>
    <div class="arT" dir="rtl">ما الذي يحتاجه ناديك فعلاً؟</div>
    <p class="lead">A conversation with club owners, managers and coaches — about how the club runs today, what hurts, and what would make the work easier.</p>
    <p class="leadar" dir="rtl">حوار مع ملاك الأندية ومديريها ومدربيها — عن طريقة إدارة النادي اليوم، وما يُتعبهم، وما الذي يجعل العمل أسهل.</p>
    {badge}
    <div class="card">
      {f("Club name", "اسم النادي", "club")}
      {f("Date", "التاريخ", "date")}
      {f("Person interviewed · role", "الشخص · الصفة", "person")}
      {f("Phone / WhatsApp", "الهاتف / واتساب", "phone")}
      {f("Area / branch", "المنطقة / الفرع", "area")}
      {f("Interviewer", "المحاور", "interviewer")}
    </div>
  </div>
</div>''')

    sig = ink(A.get('signature', ''))
    parts.append(f'''
<div class="guide">{GUIDE}
  <div class="consent">
    <div><b>Consent.</b> The club agrees that TAKEONE may keep these notes to design its product and may contact them again. Nothing is published under the club’s name.<br><br><span class="sig">Signature &nbsp;{sig or "____________________"}</span></div>
    <div dir="rtl"><b>الموافقة.</b> يوافق النادي على احتفاظ تيك ون بهذه الملاحظات لتصميم منتجها، وعلى التواصل معه لاحقاً. لا يُنشر شيء باسم النادي.<br><br>التوقيع ____________________</div>
  </div>
</div>''')

    n = 1
    for i, (en, ar, ien, iar, probe, qs) in enumerate(SECTIONS, 1):
        body = ''.join(render_q(n + j, q, A) for j, q in enumerate(qs))
        n += len(qs)
        parts.append(f'''
<section class="sec">
  {band(f"{i:02d}", en, ien, ar, iar)}
  <div class="probe"><span class="pi">◆</span><div><b>Interviewer</b> {e(probe[0])}</div><div dir="rtl"><b>للمحاور</b> {e(probe[1])}</div></div>
  {body}
  <div class="obs"><span class="ar" dir="rtl">ملاحظات المحاور</span><span class="en">Interviewer observations</span>{lines(3, A.get(f"obs{i}", ""))}</div>
</section>''')

    grid, top5 = A.get('grid', {}), A.get('top5', {})
    rows = ''
    for j, (a, b) in enumerate(FEATURES, 1):
        g = grid.get(j)
        cells = ''.join(f'<td class="c"><i class="box{" on" if g == k else ""}"></i></td>' for k in ('must', 'nice', 'no'))
        rk = ink(str(top5[j])) if j in top5 else ''
        rows += (f'<tr><td class="n">{j:02d}</td><td>{e(a)}</td><td class="ar" dir="rtl">{e(b)}</td>{cells}'
                 f'<td class="rk"><span>{rk}</span></td></tr>')
    parts.append(f'''
<section class="sec">
  {band(f"{len(SECTIONS)+1:02d}", "What matters most", "Read each line aloud. One tick per row — then rank their top 5 (1 = most important).",
        "ما الأهم لديكم", "اقرأ كل سطر بصوت عالٍ. علامة واحدة لكل سطر — ثم رتّب أهم ٥ (١ = الأهم).")}
  <table class="pri">
    <thead><tr><th></th><th style="text-align:left">Feature</th><th style="text-align:right"><small>الميزة</small></th>
      <th>Must<small>ضروري</small></th><th>Nice<small>جيد</small></th><th>No need<small>غير لازم</small></th><th>Top 5<small>الأهم</small></th></tr></thead>
    <tbody>{rows}</tbody>
  </table>
  {other(A.get("grid_other", ""), "Something missing from this list", "شيء غير موجود في القائمة", ' style="margin:4mm 0 0"')}
</section>''')

    prompts = ''.join(f'<div class="prompt">{qhead(k, a, b)}{lines(4, A.get(f"speak{k}", ""))}</div>' for k, (a, b) in enumerate(SPEAK, 1))
    sm, sc = A.get('summary', {}), A.get('scores', {})
    yes = ' on' if sc.get('pilot') is True else ''
    no = ' on' if sc.get('pilot') is False else ''
    parts.append(f'''
<section class="sec speak">
  {band("✦", "Speak your mind", "Put the form down and let them talk — these prompts only start the conversation. Write what they say, in their words.",
        "قل رأيك بحرية", "ضع الاستمارة جانباً ودعهم يتحدثون — هذه الأسئلة لبدء الحديث فقط. اكتب ما يقولونه بكلماتهم.")}
  {prompts}
</section>
<section class="free">
  <div class="kicker">Open notes · ملاحظات مفتوحة</div>
  <div class="ttl"><h2>In their own words</h2><span dir="rtl">بكلماتهم</span></div>
  {lines(27, A.get("free", ""))}
</section>
<section class="free">
  <div class="kicker">After the visit — interviewer only · بعد الزيارة — للمحاور فقط</div>
  <div class="ttl"><h2>Visit summary</h2><span dir="rtl">ملخص الزيارة</span></div>
  <div class="q">{qhead(1, "The three biggest pains you heard", "أكبر ثلاث مشكلات سمعتها")}{lines(4, sm.get("pains", ""))}</div>
  <div class="q">{qhead(2, "Their best quote (word for word)", "أفضل عبارة قالوها (حرفياً)")}{lines(3, sm.get("quote", ""))}</div>
  <div class="q">{qhead(3, "Features they asked for that we don’t have", "ميزات طلبوها وليست لدينا")}{lines(3, sm.get("missing", ""))}</div>
  <div class="q">{qhead(4, "Next step agreed", "الخطوة التالية المتفق عليها")}{lines(2, sm.get("next", ""))}</div>
  <div class="score">
    <div><b>Interest</b><span>الاهتمام</span>{circled(sc.get("interest"))}</div>
    <div><b>Tech comfort</b><span>الراحة مع التقنية</span>{circled(sc.get("tech"))}</div>
    <div><b>Urgency</b><span>مدى الإلحاح</span>{circled(sc.get("urgency"))}</div>
    <div><b>Pilot club?</b><span>نادٍ تجريبي؟</span>Yes <i class="box{yes}"></i> &nbsp; No <i class="box{no}"></i></div>
    <div><b>Follow-up date</b><span>موعد المتابعة</span>{ink(sc.get("followup", "")) or "____ / ____"}</div>
  </div>
  <div class="thanks">Thank you for your time<span dir="rtl">شكراً لوقتكم</span></div>
</section>''')

    return (f'<!doctype html><html lang="en"><head><meta charset="utf-8"><title>{e(title)}</title>'
            f'<style>{BASE_CSS}{EXTRA}</style></head><body>{"".join(parts)}</body></html>')
