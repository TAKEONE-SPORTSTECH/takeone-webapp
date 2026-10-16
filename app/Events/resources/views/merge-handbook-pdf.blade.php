{{--
    The officials' handbook on merging small divisions — App\Events\Support\MergeHandbookPdf
    feeds this to headless Chromium (BracketPdf::render).

    The draw PDF's "Fight night" look — navy and gold, Barlow Condensed, the gold
    diagonal under the band, square edges and hairlines — so it reads as one set
    with the draw, entry-list and registration-form PDFs.

    Unlike those, this is prose, so the pages FLOW: Chromium paginates, the band
    sits on the first page only (@page :first has no top margin), and every
    block that must not split carries break-inside: avoid.

    Every value from the event is escaped ({{ }}); this page never talks to the
    server. Text source: Documentation/DIVISION-MERGING.md — keep in step.
--}}
@php
    $c = '#d8b25f';
    $navy = '#16213d';
    $goldInk = '#a8862f';
    $L = $limits;
    $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.');
    $tone = ['green' => ['#2f7a4f', '#e8f5ec', 'Safe'], 'amber' => ['#b45309', '#fff6e6', 'Needs consent'], 'red' => ['#b4483c', '#fcefed', 'Not safe']];
    $dot = ['green' => '#2f7a4f', 'amber' => '#d97706', 'red' => '#c0392b', 'info' => '#9aa3b2'];

    $rules = [
        ['Too small', 'Fewer than '.$L['below'].' entrants', 'WUKF minimum of 3; Ontario Judo "three competitors or fewer"'],
        ['Gender', 'Same gender, always', 'Every body surveyed'],
        ['Weight class', 'The adjoining class only', 'USA Taekwondo, IKU'],
        ['Weight gap', 'Above '.$n($L['consent_gap']).'% needs consent · above '.$n($L['max_gap']).'% never', 'Youth wrestling (12%) · Ontario Judo (15%)'],
        ['Age group', 'Same group; otherwise one adjacent group, the younger athlete moving up', 'IBJJF, AAU Karate'],
        ['Age gap', 'At most '.$L['max_months'].' months apart when crossing an age group', 'Ontario Boxing, Junior A vs Junior B'],
        ['Minors with adults', 'Never', 'WUKF, IBJJF'],
        ['Consent', 'Written consent from the guardian of every minor (the athlete if 18+). A coach alone cannot consent', 'Ontario Judo, IBJJF'],
        ['Who decides', 'The organiser or chief official approves every merge; the draw is marked as combined', 'WUKF, AAU, Ontario Judo'],
        ['Which weight', 'Weigh-in weight. Declared weight only before the weigh-in, re-checked after it', 'Youth wrestling, WKF weigh-in'],
    ];

    $steps = [
        ['Fewer than '.$L['below'].' entrants in the division?', 'No — leave it as it is.', null],
        ['Same gender, and the adjoining weight class?', 'No — not offered.', 'red'],
        ['A minor with an adult, or more than '.$L['max_months'].' months apart across age groups?', 'Yes — not offered.', 'red'],
        ['Weight gap above '.$n($L['max_gap']).'%?', 'Yes — not offered.', 'red'],
        ['Crosses an age group, or weight gap above '.$n($L['consent_gap']).'%?', 'Yes — only with written guardian consent.', 'amber'],
        ['Every check passed', 'Safe — the organiser approves; coaches are informed.', 'green'],
    ];

    $sources = [
        ['WUKF — Competition Rules, ver. 27 (2023), Art. 2.1 & 2.4', 'https://www.wukf-karate.org/wp-content/uploads/2023/05/WUKF-New-Competition-Rules-2023-03.05.2023_ver-27-ALL-BLACK.pdf',
            ['A WUKF category (kata and kumite) will be valid with a minimum of 3 competitors (3 for individual categories and 3 teams for team categories). The merging rules will be made by a team composed by the representatives of the Athletes and Coaches Commission, the Referees Commission and the Executive Committee (ExCom). In special circumstances when the merging cannot be made the decision will reside with the ExCom.',
             'Juniors can\'t compete in senior categories.'],
            '3 is the minimum; merging is a committee decision; juniors never into seniors.'],
        ['IKU — Competition Rules (March 2022), Art. 2.9 & 2.2–2.6', 'https://ikunion.org/wp-content/uploads/IKU-COMPETITION-RULES-MAR-2022.pdf',
            ['A category will be organized with a minimum of 4 competitors. In the lack of this minimum condition, the category will be merged with the next smaller one.',
             'A Juvenile contestant CAN ONLY compete in Juvenile categories.'],
            'Merge into the next weight class, never across age groups.'],
        ['AAU Karate — Part 8, Art. 16.17 & 16.17.1.2', 'https://image.aausports.org/handbooks/karate/VIII_types.pdf',
            ['Divisions may be combined, however, where there are not enough competitors to field the respective divisions.',
             'At the discretion of the Tournament Director a division may be sub-divided according to height and weight. In junior age divisions a single competitor who is significantly larger or smaller than the other competitors in his/her division may be moved up or down one and only one age division'],
            'The Tournament Director decides; a junior moves one age division at most.'],
        ['AAU Taekwondo — Rule Book 2023, II.K, II.B & I.B.1.a', 'https://image.aausports.org/dnn/tw/2023/2023AAUTKDHandbook.pdf',
            ['If the number of competitors for any one division is less than four, the sponsoring organization has the option of combining age, belt and weight groups into a single group',
             'If there is no competition in a competitor\'s age group, tournament directors reserve the right to move that competitor up in age.',
             'When combining weight divisions resulting in extreme size differences in the competitors, a competitor may be allowed to wear the PSS Electronic Protector appropriate for their weight class, at the discretion of the Head Referee'],
            'Fewer than 4 may be combined; even AAU admits combining creates "extreme size differences".'],
        ['USA Taekwondo — Kyorugi Rules 2026, Art. 6.1.1.1', 'https://assets.contentstack.io/v3/assets/blteb7d012fc7ebef7f/blta5faf747d0f05bc5/695fb747f3d40d4592598474/2026_USATKD_Kyorugi_Rules_010826.pdf',
            ['Individual competition shall normally be between contestants in the same weight class. When necessary, adjoining weight classes may be combined to create a single classification.'],
            'Only the adjoining weight class.'],
        ['WFMAF — Competition Rules', 'https://www.wfmaf.org/championship/competition-rules/',
            ['If there are less than 2 competitors in a weight class, the judge reserves the right to combine weight classes.'],
            'A lone entrant may be combined at the judges\' discretion.'],
        ['IBJJF — Help Desk, Championship', 'https://help.ibjjf.com/championship/',
            ['If they remain alone in their division on the Registration Checkday, they are allowed to move up one age division (Teen 3 athletes may not move up in age division), as long as their legal guardians request the change. However, Juveniles are not allowed to move into the Adult division.',
             'You can find the options for single-brackets on the event\'s page, under the "IMPORTANT INFO" section – "Athlete Alone in Division".'],
            'A lone child moves up one age division only on the guardian\'s request, never into adults; what a lone athlete receives is each event\'s published choice.'],
        ['Youth wrestling — Huntington Youth Pre-season Tournament (2021)', 'https://www.wvmat.com/youth/21HuntingtonOpen.pdf',
            ['Weight classes will be determined after weigh-ins. […] No wrestler will be paired with a wrestler more than 12% heavier without parental / coach permission.'],
            '12% is the line beyond which permission is needed, and classes are set after the weigh-in.'],
        ['Ontario Amateur Combative Sport Rules: Judo — Part V, s. 4 (2025)', 'https://www.ontario.ca/document/amateur-combative-sport-rules-judo/part-v-participants',
            ['Where three competitors or fewer appear in a division, the Draw Master, after consultation with the Tournament Director, has the option of combining divisions.',
             'The maximum weight difference between the lightest and heaviest competitor is not more than 15% of the lightest competitor.',
             'The maximum age range is two calendar years.',
             'A competitor, or parent/guardian if the competitor is under eighteen as of the date of the event, must consent in writing to the combining of his/her division. — A coach cannot consent to the combining of a division on behalf of his/her competitor.',
             'Draw sheets must clearly identify that this is not a standard division. — The Chief Official must approve in writing any combined divisions.'],
            '15% weight ceiling, two years\' age range, written guardian consent, chief official sign-off, the draw marked as combined.'],
        ['Ontario Amateur Combative Sport Rules: Boxing — Part V, s. 4 (2025)', 'https://www.ontario.ca/document/amateur-combative-sport-rules-boxing/part-v-participants',
            ['Junior A vs Junior B - 24-month age difference allowed',
             'Boxers under 54 kg (118.2 lbs) 3 kg: (6.6 lbs) difference allowed — Boxers over 54 kg (118.2 lbs) to 66 kg (145.2 lbs): 4 kg (8.8 lbs) difference allowed'],
            '24 months across adjacent youth groups; youth weight gaps of only 3–4 kg.'],
        ['WKF — Kumite Competition Rules 2026 (Rules Version 2026.01)', 'https://www.wkf.net/files/pdf/documents/WKF_2026_Kumite_Competition_Rules_MASTER_COPY_V13FINAL.pdf',
            ['3.2.2(d): The tolerances admitted are 0.2 kg for all male categories and 0.5 kg for all female categories.',
             '6.2: Individual Athletes or Teams that do not present themselves when called will be disqualified (KIKEN) from that category.',
             '3.7.2: 3-5 Athletes … Final between first and runner-up of the Group, and only one bronze medal bout.'],
            'No minimum per category, no merging rule, no lone-competitor rule: for a WKF-rules event these are the organiser\'s to decide — which is why we publish our own.'],
    ];
    $suggest = $plan['suggestions'] ?? [];
    $alone = $plan['alone'] ?? [];
    $wide = $plan['wide'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Merging Small Divisions · {{ $title }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,600;0,700;0,800;0,900;1,800;1,900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        @page { size: A4 portrait; margin: 16mm 0 18mm; }
        @page :first { margin-top: 0; }
        html, body { margin: 0; padding: 0; background: #fff; }
        body { font-family: 'Inter', system-ui, sans-serif; color: #1e2c4f; font-size: 9.2pt; line-height: 1.5;
               -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        * { box-sizing: border-box; }
        .cond { font-family: 'Barlow Condensed', 'Inter', sans-serif; }

        .band { position: relative; overflow: hidden; color: #fff; background: {{ $navy }};
                height: 54mm; padding: 11mm 15mm 0; display: flex; align-items: flex-start; gap: 8mm; }
        .band .stripes { position: absolute; right: 0; top: 0; bottom: 0; width: 90mm; opacity: .16;
                         background: repeating-linear-gradient(115deg, {{ $c }} 0 3mm, transparent 3mm 9mm); }
        .band .slab { position: absolute; left: 0; right: 0; bottom: 0; height: 9mm; background: {{ $c }};
                      clip-path: polygon(0 55%, 100% 0, 100% 100%, 0 100%); }
        .eye { position: relative; font-size: 7.5pt; font-weight: 800; letter-spacing: .28em; text-transform: uppercase; color: {{ $c }}; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .band h1 { position: relative; margin: 2mm 0 0; font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-style: italic;
                   text-transform: uppercase; font-size: 31pt; line-height: .9; }
        .band .sub { position: relative; font-size: 8.5pt; color: #c9d0e2; margin-top: 2.5mm; }
        .band .who { position: relative; min-width: 0; flex: 1; }
        .hlogo { position: relative; flex: 0 0 auto; width: 15mm; height: 15mm; margin-top: 3mm; display: block; }
        @if($hostLogo)
        .hlogo { background: url("{{ $hostLogo }}") center / contain no-repeat; }
        @endif
        .totals { position: relative; margin-inline-start: auto; display: flex; gap: 7mm; font-family: 'Barlow Condensed', 'Inter', sans-serif;
                  text-transform: uppercase; text-align: end; padding-top: 4mm; }
        .totals b { display: block; font-size: 28pt; font-weight: 900; line-height: .9; color: #fff; }
        .totals span { font-size: 8pt; font-weight: 700; letter-spacing: .16em; color: {{ $c }}; }

        .wrap { padding: 0 15mm; }
        .lead { margin: 7mm 0 0; font-size: 10.5pt; font-weight: 600; color: {{ $navy }}; line-height: 1.45; }

        h2 { margin: 9mm 0 3mm; display: flex; align-items: baseline; gap: 3mm; break-after: avoid; page-break-after: avoid;
             font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-style: italic; text-transform: uppercase;
             font-size: 17pt; letter-spacing: .02em; color: {{ $navy }}; border-bottom: .6mm solid {{ $navy }}; padding-bottom: 1.2mm; }
        h2 .no { font-size: 11pt; color: {{ $goldInk }}; font-style: normal; letter-spacing: .1em; }
        h3 { margin: 5mm 0 1.5mm; font-size: 9.5pt; font-weight: 800; color: {{ $navy }}; break-after: avoid; page-break-after: avoid; }
        p { margin: 0 0 2.5mm; }
        ul, ol { margin: 0 0 3mm; padding-inline-start: 5mm; }
        li { margin-bottom: 1.2mm; }
        b, strong { color: {{ $navy }}; }

        table { width: 100%; border-collapse: collapse; margin: 1mm 0 3mm; border: .35mm solid #cfd5e0; }
        th { text-align: start; font-size: 6.6pt; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; color: {{ $goldInk }};
             background: #f4f6fa; padding: 2mm 2.5mm; border-bottom: .6mm solid {{ $navy }}; }
        td { padding: 2mm 2.5mm; border-bottom: .25mm solid #e3e6ee; vertical-align: top; font-size: 8.4pt; }
        tr { break-inside: avoid; page-break-inside: avoid; }
        tr:nth-child(even) td { background: #fafbfd; }
        td.k { font-weight: 800; color: {{ $navy }}; white-space: nowrap; }
        td.src { color: #5d6880; font-size: 7.6pt; }
        td.num { font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 800; color: #9aa3b2; font-size: 10pt; width: 7mm; }

        .chip { display: inline-block; padding: .4mm 2.2mm; border-radius: 4mm; font-size: 6.6pt; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; white-space: nowrap; }

        .step { display: flex; gap: 3mm; align-items: stretch; break-inside: avoid; page-break-inside: avoid; margin-bottom: 1.6mm; }
        .step .n { flex: 0 0 8mm; display: grid; place-items: center; background: {{ $navy }}; color: {{ $c }};
                   font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 13pt; }
        .step .q { flex: 1; border: .35mm solid #cfd5e0; padding: 2mm 3mm; font-weight: 700; color: {{ $navy }}; }
        .step .a { flex: 0 0 66mm; padding: 2mm 3mm; font-size: 8.2pt; border: .35mm solid; }

        .card { border: .35mm solid #cfd5e0; border-inline-start: 1.4mm solid; padding: 2.6mm 3.4mm; margin-bottom: 2.6mm;
                break-inside: avoid; page-break-inside: avoid; }
        .card .t { font-weight: 800; color: {{ $navy }}; font-size: 9.2pt; margin: 1.2mm 0 1.4mm; }
        .card .meta { font-size: 7.4pt; color: #6b7590; margin-inline-start: 2mm; }
        .why { list-style: none; padding: 0; margin: 0; }
        .why li { display: flex; gap: 2mm; font-size: 8pt; color: #4b5568; margin-bottom: .6mm; }
        .why i { flex: 0 0 auto; width: 1.9mm; height: 1.9mm; border-radius: 50%; margin-top: 1.4mm; }

        .q-block { border-inline-start: 1.2mm solid {{ $c }}; background: #fbf8f0; padding: 2.2mm 3.4mm; margin: 0 0 1.8mm;
                   font-size: 8.3pt; font-style: italic; color: #2c3a5c; break-inside: avoid; page-break-inside: avoid; }
        .src-h { break-inside: avoid; page-break-inside: avoid; }
        .means { font-size: 8.2pt; color: #4b5568; margin: 0 0 4mm; }
        .means b { color: {{ $goldInk }}; text-transform: uppercase; font-size: 6.8pt; letter-spacing: .14em; margin-inline-end: 1.5mm; }
        .url { font-size: 6.8pt; color: #6b7590; word-break: break-all; }

        .note { border: .35mm solid #cfd5e0; background: #f4f6fa; padding: 2.6mm 3.4mm; font-size: 8.2pt; margin: 2mm 0 3mm; break-inside: avoid; }
        .empty { color: #6b7590; font-style: italic; }
        .end { margin: 10mm 0 0; padding-top: 3mm; border-top: .25mm solid #cfd5e0; font-size: 7.4pt; color: #6b7590; display: flex; justify-content: space-between; gap: 5mm; }
        .end b { color: {{ $goldInk }}; letter-spacing: .06em; }
    </style>
</head>
<body>
    <div class="band">
        <div class="stripes"></div>
        <div class="slab"></div>
        <span class="hlogo"></span>
        <div class="who">
            <div class="eye">Officials' handbook · {{ $title }}</div>
            <h1>Merging small<br>divisions</h1>
            <div class="sub">{{ implode(' · ', array_filter(['Safety first, then fairness', $host, $date])) }}</div>
        </div>
        <div class="totals">
            <div><b>{{ $plan['small'] ?? 0 }}</b><span>Small</span></div>
            <div><b>{{ count($suggest) }}</b><span>Merges</span></div>
            <div><b>{{ count($alone) }}</b><span>Alone</span></div>
        </div>
    </div>

    <div class="wrap">
        <p class="lead">A division with one entrant hands out a gold medal without a bout. We merge it into a nearby division only when the merge is safe — and safety always outranks fairness.</p>

        {{-- 1 --}}
        <h2><span class="no">01</span>Purpose</h2>
        <ul>
            <li><b>One entrant</b> is the real problem: no bout, yet a medal. Two entrants is a legitimate final, so a pair is merged only when a safe, larger pool is right beside it.</li>
            <li><b>Safety first.</b> A merge must never put a child against a much heavier or much older opponent. When no safe merge exists, the athlete stays alone and the published lone-athlete rule applies.</li>
            <li><b>Fairness second.</b> The merged pool should be one the athlete could reasonably have entered anyway, judged on weigh-in weight rather than the weight declared at entry.</li>
            <li><b>People decide; the platform suggests.</b> Every merge is approved by the organiser or the chief official and recorded with its reason.</li>
        </ul>
        <p>This handbook applies to every event on TakeOne — whether a club, a federation or an individual hosts it — and is written for organisers, referees and coaches.</p>

        {{-- 2 --}}
        <h2><span class="no">02</span>Our rules</h2>
        <p>A division with fewer than {{ $L['below'] }} entrants is a merge candidate. It may join another division only if every check below passes. These are the limits in force for this event; an organiser may set them stricter, never looser.</p>
        <table>
            <thead><tr><th>#</th><th>Check</th><th>Limit</th><th>Where it comes from</th></tr></thead>
            <tbody>
            @foreach($rules as $i => [$k, $v, $s])
                <tr><td class="num">{{ $i + 1 }}</td><td class="k">{{ $k }}</td><td>{{ $v }}</td><td class="src">{{ $s }}</td></tr>
            @endforeach
            </tbody>
        </table>

        {{-- 3 --}}
        <h2><span class="no">03</span>How to decide a merge</h2>
        <p>Run the checks in order; the first one that fails decides the outcome. Only a merge that clears them all is safe.</p>
        @foreach($steps as $i => [$q, $a, $g])
            @php $t = $g ? $tone[$g] : ['#5d6880', '#f4f6fa', '']; @endphp
            <div class="step">
                <span class="n">{{ $i + 1 }}</span>
                <span class="q">{{ $q }}</span>
                <span class="a" style="border-color: {{ $t[0] }}; background: {{ $t[1] }}; color: {{ $t[0] }}; font-weight: 700">{{ $a }}</span>
            </div>
        @endforeach
        <p style="margin-top:3mm">The weight gap is measured from each athlete who moves to the opponent furthest from them in weight in the group they join — in a knockout, any two may meet. Re-run the checks after the weigh-in: a merge judged on declared weight can change grade once real weights are in.</p>

        {{-- 4 --}}
        <h2><span class="no">04</span>This event</h2>
        <p>{{ $title }}@if($date), {{ $date }}@endif — what the merge tool finds today, printed {{ $printed }}. Re-print after the weigh-in.</p>

        <h3>Suggested merges</h3>
        @forelse($suggest as $j)
            @php $t = $tone[$j['grade']] ?? $tone['red']; @endphp
            <div class="card" style="border-inline-start-color: {{ $t[0] }}">
                <span class="chip" style="color: {{ $t[0] }}; background: {{ $t[1] }}">{{ $t[2] }}</span>
                <span class="meta">{{ $j['moving'] }} moving · {{ $j['after'] }} in the group after</span>
                <div class="t">{{ $j['from_names'] }} → {{ $j['into_name'] }}</div>
                <ul class="why">
                    @foreach($j['reasons'] as $r)
                        <li><i style="background: {{ $dot[$r['level']] ?? '#9aa3b2' }}"></i><span>{{ $r['text'] }}</span></li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="empty">No small division has a safe neighbour to join.</p>
        @endforelse

        <h3>Alone — no safe merge</h3>
        @if(count($alone))
            <table>
                <thead><tr><th>Division</th><th>Why it stays alone</th></tr></thead>
                <tbody>
                @foreach($alone as $a)
                    <tr><td class="k" style="white-space: normal">{{ $a['name'] }}</td><td>{{ $a['why'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @else
            <p class="empty">Nobody is alone without a safe merge.</p>
        @endif

        @if(count($wide))
            <h3>Already wider than the safe limit</h3>
            <p>Nothing to merge here — these divisions' own weight range is wider than {{ $n($L['max_gap']) }}%. Look at them before the draw.</p>
            <table>
                <thead><tr><th>Division</th><th>Lightest</th><th>Heaviest</th><th>Spread</th></tr></thead>
                <tbody>
                @foreach($wide as $w)
                    <tr><td class="k" style="white-space: normal">{{ $w['name'] }}</td><td>{{ $n($w['light']) }} kg</td><td>{{ $n($w['heavy']) }} kg</td><td><b>{{ $n($w['pct']) }}%</b></td></tr>
                @endforeach
                </tbody>
            </table>
        @endif

        {{-- 5 --}}
        <h2><span class="no">05</span>When no safe merge exists</h2>
        <p>An athlete with no safe pool stays alone. What happens to them is decided by a rule published before entries close — never on the day. No published WKF kumite rule covers a competitor with no opponent, so each event states its own:</p>
        <ol>
            <li><b>Walkover with medal</b> — weighed in, presents at the mat, awarded first place. Simple, but the outcome people find unfair.</li>
            <li><b>Walkover, no medal</b> — recorded as entered; no medal below a set number of entrants (for example 2).</li>
            <li><b>Exhibition bout</b> — against a willing, safely matched opponent from another division, outside the draw. Recorded, but it changes neither athlete's medal.</li>
            <li><b>Entry fee credit</b> — if no bout is possible, the fee is credited to a future event. A business decision for the host.</li>
        </ol>
        <div class="note"><b>Announce in advance</b>, in the event's rules and on the entry page: a division needs at least {{ $L['below'] }} entrants and smaller ones may be merged under this handbook; merges are decided after the weigh-in and coaches are told before the draw is published; a minor never moves age group without written guardian consent; and which of the four options applies to a lone athlete.</div>

        {{-- 6 --}}
        <h2><span class="no">06</span>What other competitions do</h2>
        <p>Every body we checked lets small divisions be combined — but only between neighbouring classes, under a named official, and with consent where age or size is stretched. None publishes a rule on medals for an athlete with no opponent. The words below are copied exactly from each source.</p>
        @foreach($sources as [$name, $url, $quotes, $means])
            <div class="src-h"><h3>{{ $name }}</h3></div>
            @foreach($quotes as $q)
                <div class="q-block">“{{ $q }}”</div>
            @endforeach
            <p class="means"><b>Meaning</b>{{ $means }}</p>
        @endforeach

        {{-- 7 --}}
        <h2><span class="no">07</span>Using the merge tool</h2>
        <ol>
            <li>Open the event's <b>Divisions</b> — the <b>Draw</b> tab on the federation dashboard. Club and personal events show a <b>Merge small divisions</b> row above the list; the federation dashboard shows a <b>⇄ Merge</b> button.</li>
            <li><b>Suggested merges</b> lists each small division with its best safe neighbour, graded <i>Safe</i> or <i>Needs consent</i>, every reason written out.</li>
            <li><b>Merge</b> is one tap. For a merge that needs consent, first tick the consent statement — written consent from the guardian of every minor moved, or the athlete if 18+.</li>
            <li><b>Alone — no safe merge</b> lists the athletes who stay alone and why. Apply the event's lone-athlete rule to them.</li>
            <li><b>Already wider than the safe limit</b> lists divisions whose own range breaks the {{ $n($L['max_gap']) }}% line.</li>
            <li><b>Pick groups yourself</b> grades any two or more divisions you choose, the same way, before it will merge them.</li>
        </ol>
        <p>A merge moves everyone into the group you keep (marked as placed by hand, so no re-sort undoes it), removes the emptied groups and their unfought draws, rebuilds the kept group's draw and records who merged, the grade, the reasons and whether consent was confirmed. A group with a fought bout can never be merged, and merging closes once the event starts. The event's AI budget advisor follows the same rules and never recommends an unsafe merge to save money.</p>

        {{-- 8 --}}
        <h2><span class="no">08</span>Sources</h2>
        <table>
            <thead><tr><th>Source</th><th>Link</th></tr></thead>
            <tbody>
            @foreach($sources as [$name, $url])
                <tr><td class="k" style="white-space: normal; width: 62mm">{{ $name }}</td><td class="url">{{ $url }}</td></tr>
            @endforeach
            </tbody>
        </table>
        <p class="means">All sources opened and quoted on 15 Oct 2026. Not used because they could not be opened: the AAU Taekwondo 2025 handbook and Boxing Ontario's 2025 club match guidelines.</p>

        <div class="end"><span>{{ $title }} · Merging small divisions</span><span>Printed {{ $printed }} · <b>TAKEONE</b></span></div>
    </div>
</body>
</html>
