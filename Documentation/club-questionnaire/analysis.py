#!/usr/bin/env python3
"""ILLUSTRATIVE: 12 fictional club visits → coded data → scores → a build plan.

Nothing here is real data. It shows the METHOD the real answers will go through.
"""
import csv, json, random, statistics as st
from content import FEATURES
from sample_answers import SAMPLE

random.seed(26)

MARTIAL = {'Taekwondo', 'Karate', 'Jiu-Jitsu', 'Judo', 'Boxing / Muay Thai'}
CLUBS = [  # code, sport, members, branches
    ('C01', 'Taekwondo', 140, 1), ('C02', 'Karate', 220, 2), ('C03', 'Swimming', 310, 1),
    ('C04', 'Jiu-Jitsu', 60, 1), ('C05', 'Football', 400, 3), ('C06', 'Gymnastics', 180, 1),
    ('C07', 'Taekwondo', 90, 1), ('C08', 'Boxing / Muay Thai', 75, 1), ('C09', 'Karate', 55, 1),
    ('C10', 'Fitness / Gym', 260, 2), ('C11', 'Judo', 45, 1), ('C12', 'Swimming', 120, 1),
]

# What TAKEONE has today, from reading the club admin (our assessment), and the
# effort to close the gap: S=1, M=2, L=3.
STATUS = {
    1: ('exists', 1), 2: ('exists', 1), 3: ('exists', 1), 4: ('partial', 2), 5: ('partial', 1),
    6: ('exists', 1), 7: ('exists', 1), 8: ('missing', 2), 9: ('missing', 1), 10: ('missing', 2),
    11: ('exists', 1), 12: ('missing', 2), 13: ('partial', 2), 14: ('partial', 3), 15: ('partial', 2),
    16: ('exists', 1), 17: ('exists', 1), 18: ('exists', 1), 19: ('exists', 1), 20: ('partial', 2),
    21: ('exists', 1), 22: ('exists', 1),
}
GAP = {'missing': 1.0, 'partial': 0.6, 'exists': 0.2}
NOTE = {
    4: 'attendance is recorded on the profile; no check-in at the door',
    5: 'reminders exist in places; no automatic "who owes" run',
    13: 'WhatsApp channel set up per club; not wired to reminders',
    14: 'certificates exist; the belt-test event type is declared, not built',
    15: 'goals and attendance on the profile; no report a parent receives',
    20: 'Arabic interface phase 0+1 done; not every screen',
}

# Chance a club ticks MUST / NICE for each feature (martial arts clubs differ on 14, 16, 17).
P = {1: (.55, .35), 2: (.75, .2), 3: (.5, .4), 4: (.7, .25), 5: (.92, .08), 6: (.6, .3), 7: (.55, .35),
     8: (.75, .2), 9: (.7, .2), 10: (.35, .35), 11: (.6, .3), 12: (.5, .35), 13: (.85, .1), 15: (.55, .35),
     18: (.2, .5), 19: (.5, .35), 20: (.7, .2), 21: (.3, .5)}

THEMES = [  # code, label, chance, martial-only
    ('PAY', 'Chasing who has paid', .85, False), ('WA', 'Drowning in WhatsApp', .75, False),
    ('DROP', 'Members drop out unnoticed', .6, False), ('PAUSE', 'Pauses / freezes not recorded', .6, False),
    ('BELT', 'Belt-test admin by hand', .7, True), ('DISC', 'Discount maths every month', .45, False),
    ('DATA', 'Fear of moving old data', .5, False), ('COACH', 'Working out coach pay', .4, False),
    ('ENTRY', 'Tournament entry lists', .5, True), ('APP', 'Parents won’t use an app', .4, False),
]
SAMPLE_THEMES = {'PAY', 'PAUSE', 'DROP', 'WA', 'BELT', 'DISC', 'COACH', 'DATA', 'ENTRY'}

def tick(j, sport, branches):
    if j == 14:
        m, n = (.8, .15) if sport in MARTIAL else (.05, .15)
    elif j == 16:
        m, n = (.5, .35) if sport in MARTIAL else (.1, .3)
    elif j == 17:
        m, n = (.2, .45) if sport in MARTIAL else (.05, .2)
    elif j == 22:
        m, n = (.9, .1) if branches > 1 else (.05, .25)
    else:
        m, n = P[j]
    r = random.random()
    return 'must' if r < m else 'nice' if r < m + n else 'no'

rows = []
for code, sport, members, branches in CLUBS:
    if code == 'C01':      # the sample form IS club C01
        grid, top5, themes = dict(SAMPLE['grid']), dict(SAMPLE['top5']), set(SAMPLE_THEMES)
        scales = {'reg_pain': 4, 'chase': 5, 'parents_ask': 4, 'progress_value': 5, 'video_value': 3}
        wtp, pilot = 20, True
    else:
        grid = {j: tick(j, sport, branches) for j in range(1, 23)}
        musts = [j for j, v in grid.items() if v == 'must']
        musts.sort(key=lambda j: -(P.get(j, (.5, 0))[0] + random.random() * .35))
        top5 = {j: i + 1 for i, j in enumerate(musts[:5])}
        themes = {c for c, _, p, mo in THEMES if (not mo or sport in MARTIAL) and random.random() < p}
        scales = {'reg_pain': random.choice([2, 3, 3, 4, 4]), 'chase': random.choice([3, 4, 4, 5, 5]),
                  'parents_ask': random.choice([2, 3, 4, 4, 5]), 'progress_value': random.choice([3, 4, 4, 5]),
                  'video_value': random.choice([3, 4, 5]) if sport in MARTIAL else random.choice([1, 2, 2, 3])}
        wtp = int(round(max(10, min(60, members / 6 + random.uniform(-8, 8))) / 5) * 5)
        pilot = random.random() < .35
    rows.append({'club': code, 'sport': sport, 'members': members, 'branches': branches,
                 'segment': 'Martial arts' if sport in MARTIAL else 'Other sports',
                 'size': 'Large (150+)' if members >= 150 else 'Small (<150)',
                 'grid': grid, 'top5': top5, 'themes': sorted(themes), 'scales': scales, 'wtp': wtp, 'pilot': pilot})

N = len(rows)

# ---------------------------------------------------------------- the coded table (one row per club)
with open('coded-responses.csv', 'w', newline='', encoding='utf-8-sig') as f:
    w = csv.writer(f)
    w.writerow(['club', 'sport', 'members', 'branches', 'segment', 'size', 'pilot', 'wtp_bhd_month']
               + [f'F{j:02d} {FEATURES[j-1][0]}' for j in range(1, 23)]
               + [f'top5_F{j:02d}' for j in range(1, 23)]
               + [f'theme_{c}' for c, *_ in THEMES] + list(rows[0]['scales']))
    for r in rows:
        w.writerow([r['club'], r['sport'], r['members'], r['branches'], r['segment'], r['size'], 'yes' if r['pilot'] else 'no', r['wtp']]
                   + [r['grid'][j] for j in range(1, 23)] + [r['top5'].get(j, '') for j in range(1, 23)]
                   + [1 if c in r['themes'] else 0 for c, *_ in THEMES] + list(r['scales'].values()))

# ---------------------------------------------------------------- scores
features = []
for j, (en, ar) in enumerate(FEATURES, 1):
    must = sum(r['grid'][j] == 'must' for r in rows)
    nice = sum(r['grid'][j] == 'nice' for r in rows)
    top = sum(j in r['top5'] for r in rows)
    demand = round((must + .4 * nice) / N * 100)
    status, effort = STATUS[j]
    opp = round(demand * GAP[status])
    features.append({'id': j, 'en': en, 'ar': ar, 'must': must, 'nice': nice, 'top5': top, 'demand': demand,
                     'status': status, 'effort': effort, 'opportunity': opp,
                     'priority': round(opp / effort, 1), 'note': NOTE.get(j, '')})

def decide(f):
    if f['status'] == 'exists':
        return 'show' if f['demand'] >= 55 else 'keep'
    return 'build' if f['demand'] >= 45 else 'park'

for f in features:
    f['decision'] = decide(f)

build = sorted([f for f in features if f['decision'] == 'build'], key=lambda f: -f['priority'])
now, nxt, later, used = [], [], [], 0
for f in build:                      # capacity in effort points per phase
    if used + f['effort'] <= 4 and len(now) < 3:
        now.append(f); used += f['effort']
    elif sum(x['effort'] for x in nxt) + f['effort'] <= 6:
        nxt.append(f)
    else:
        later.append(f)
show = sorted([f for f in features if f['decision'] == 'show'], key=lambda f: -f['demand'])
park = [f for f in features if f['decision'] == 'park']

themes = []
for c, label, _, mo in THEMES:
    base = [r for r in rows if not mo or r['sport'] in MARTIAL]
    k = sum(c in r['themes'] for r in rows)
    themes.append({'code': c, 'label': label, 'n': k, 'of': len(base), 'share': round(k / len(base) * 100), 'martial_only': mo})
themes.sort(key=lambda t: -t['share'])

scales = {k: round(st.mean(r['scales'][k] for r in rows), 1) for k in rows[0]['scales']}
seg = {}
for key in ('segment', 'size'):
    for g in sorted({r[key] for r in rows}):
        sub = [r for r in rows if r[key] == g]
        seg[g] = {'n': len(sub), 'wtp': st.median(r['wtp'] for r in sub),
                  'top': sorted(range(1, 23), key=lambda j: -sum(r['grid'][j] == 'must' for r in sub))[:3]}

out = {'n': N, 'features': features, 'now': [f['id'] for f in now], 'next': [f['id'] for f in nxt],
       'later': [f['id'] for f in later], 'show': [f['id'] for f in show], 'park': [f['id'] for f in park],
       'themes': themes, 'scales': scales, 'segments': seg,
       'wtp_median': st.median(r['wtp'] for r in rows), 'wtp_range': [min(r['wtp'] for r in rows), max(r['wtp'] for r in rows)],
       'pilots': [r['club'] for r in rows if r['pilot']], 'clubs': [{k: r[k] for k in ('club', 'sport', 'members', 'branches')} for r in rows]}
json.dump(out, open('findings.json', 'w'), ensure_ascii=False, indent=1)
print('now', [f['en'] for f in now]); print('next', [f['en'] for f in nxt]); print('later', [f['en'] for f in later])
print('show', [f['en'] for f in show]); print('park', [f['en'] for f in park])
print('themes', [(t['label'], t['share']) for t in themes]); print('scales', scales, 'wtp', out['wtp_median'], out['wtp_range'], 'pilots', out['pilots'])
for f in sorted(features, key=lambda f: -f['demand']): print(f"{f['demand']:>3} {f['status']:<8} p={f['priority']:<5} {f['decision']:<6} {f['en']}")
