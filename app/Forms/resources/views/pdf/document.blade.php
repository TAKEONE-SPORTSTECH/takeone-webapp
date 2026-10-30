<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
@include('forms::pdf.style')
</head>
<body>

{{-- ── Cover ── --}}
<div class="cover">
    <span class="ring r1"></span><span class="ring r2"></span><span class="ring r3"></span>
    <div class="in">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div class="brand">TAKEONE<small>SPORTSTECH</small></div>
            <div class="brand" dir="rtl" style="font-family:Alexandria;letter-spacing:0;font-size:11pt">تيك ون<small style="font-family:Plex;letter-spacing:0">للتقنيات الرياضية</small></div>
        </div>
        <h1>{!! $titleHtml !!}</h1>
        @if($titleAr)<div class="arT" dir="rtl">{{ $titleAr }}</div>@endif
        @if($lead)<p class="lead">{{ $lead }}</p>@endif
        @if($leadAr)<p class="leadar" dir="rtl">{{ $leadAr }}</p>@endif
        <div class="card">
            @foreach($cover['fields'] as $f)
                <div class="f"><b>{{ $f['label'] ?? '' }}</b><span dir="rtl">{{ $f['label_ar'] ?? '' }}</span></div>
            @endforeach
        </div>
    </div>
</div>

{{-- ── The visitor's guide (forms with a visitor link) ── --}}
@if($interviewer)
<div class="guide">
    <div class="kicker">Before you start · قبل أن تبدأ</div>
    <h2>A guide for the visit</h2>
    <div class="arh" dir="rtl">دليل الزيارة</div>
    <div class="two">
        <ol class="steps">
            <li><i>1</i><div><b>Listen, don’t sell.</b>We are here to learn how they work — not to demo or promise anything.</div></li>
            <li><i>2</i><div><b>30–45 minutes.</b>Skip sections that don’t apply. Depth beats coverage.</div></li>
            <li><i>3</i><div><b>Ask for stories.</b>“Tell me about the last time…” gives truer answers than “Do you…?”</div></li>
            <li><i>4</i><div><b>Write their words.</b>Exact phrases and complaints are gold. Use the observation boxes.</div></li>
            <li><i>5</i><div><b>Ask to see things.</b>A register, a receipt, a list, a circular — how they keep it tells you more than any answer.</div></li>
            <li><i>6</i><div><b>End open.</b>The “Speak your mind” pages matter most. Don’t rush them.</div></li>
        </ol>
        <ol class="steps" dir="rtl">
            <li><i>١</i><div><b>استمع ولا تبع.</b>نحن هنا لنتعلم طريقة عملهم — لا لنعرض أو نعد بشيء.</div></li>
            <li><i>٢</i><div><b>٣٠–٤٥ دقيقة.</b>تجاوز الأقسام غير المناسبة. العمق أهم من الشمول.</div></li>
            <li><i>٣</i><div><b>اطلب قصصاً.</b>«حدّثني عن آخر مرة…» يعطي إجابات أصدق من «هل تفعلون…؟»</div></li>
            <li><i>٤</i><div><b>اكتب كلماتهم.</b>العبارات والشكاوى كما قيلت كنز. استخدم مربعات الملاحظات.</div></li>
            <li><i>٥</i><div><b>اطلب أن ترى.</b>سجل، إيصال، قائمة، تعميم — طريقة حفظها تخبرك أكثر من أي إجابة.</div></li>
            <li><i>٦</i><div><b>اختم بحوار مفتوح.</b>صفحات «قل رأيك بحرية» هي الأهم. لا تستعجلها.</div></li>
        </ol>
    </div>
    @if($lead || $leadAr)
    <div class="tip">
        <div><b>Opening line.</b> “{{ $lead }} There are no right answers — tell us how it really is.”</div>
        <div dir="rtl"><b>جملة البداية.</b> «{{ $leadAr }} لا توجد إجابات صحيحة أو خاطئة — أخبرونا كيف هو الواقع.»</div>
    </div>
    @endif
    <div class="legend">
        <div><b><i class="box"></i> Tick all</b><span dir="rtl">اختر كل ما ينطبق</span></div>
        <div><b><i class="box"></i> Tick one</b><span dir="rtl">اختر واحداً</span></div>
        <div><b><span class="dot">3</span> Circle</b><span dir="rtl">ضع دائرة على رقم</span></div>
        <div><b><span style="color:#d97706">◆</span> Interviewer</b><span dir="rtl">إرشاد للمحاور فقط</span></div>
    </div>
    @if($cover['consent'])
    <div class="consent">
        <div><b>Consent.</b> {{ $cover['consent']['label'] ?? '' }}<br><br><span class="sig">Signature &nbsp;____________________</span></div>
        <div dir="rtl"><b>الموافقة.</b> {{ $cover['consent']['label_ar'] ?? '' }}<br><br>التوقيع ____________________</div>
    </div>
    @endif
</div>
@endif

{{-- ── The pages ── --}}
@foreach($blocks as $b)
    @if($b['kind'] === 'section')
        <section class="sec">
            @include('forms::pdf.band', ['num' => $b['num'], 'p' => $b['page']])
            @if($b['obs'] && ! empty($b['obs']['help']))
                <div class="probe"><span class="pi">◆</span><div><b>Interviewer</b> {{ $b['obs']['help'] }}</div><div dir="rtl"><b>للمحاور</b> {{ $b['obs']['help_ar'] ?? '' }}</div></div>
            @endif
            @foreach($b['qs'] as $q)
                @include('forms::pdf.question', ['n' => $q['n'], 'f' => $q['f']])
            @endforeach
            @if($b['obs'])
                <div class="obs"><span class="ar" dir="rtl">{{ $b['obs']['label_ar'] ?? 'ملاحظات المحاور' }}</span><span class="en">{{ $b['obs']['label'] ?? 'Interviewer observations' }}</span>
                    <div class="lines">@for($i = 0; $i < 9; $i++)<span></span>@endfor</div></div>
            @endif
        </section>
    @elseif($b['kind'] === 'grid')
        @php $g = $b['grid']; $r = $b['rank']; @endphp
        <section class="sec">
            @include('forms::pdf.band', ['num' => $b['num'], 'p' => array_merge($b['page'], [
                'intro' => trim(($b['page']['intro'] ?? '') ?: 'One tick per row.'),
            ])])
            <table class="pri">
                <thead><tr><th></th><th style="text-align:left">{{ $g['label'] ?? 'Feature' }}</th><th style="text-align:right"><small>{{ $g['label_ar'] ?? '' }}</small></th>
                    @foreach(($g['columns'] ?? []) as $c)<th>{{ $c['label'] }}<small>{{ $c['label_ar'] ?? '' }}</small></th>@endforeach
                    @if($r)<th>Top {{ (int) ($r['max'] ?? 5) }}<small>الأهم</small></th>@endif</tr></thead>
                <tbody>
                    @foreach(($g['rows'] ?? []) as $i => $row)
                        <tr><td class="n">{{ sprintf('%02d', $i + 1) }}</td><td>{{ $row['label'] }}</td><td class="ar" dir="rtl">{{ $row['label_ar'] ?? '' }}</td>
                            @foreach(($g['columns'] ?? []) as $c)<td class="c"><i class="box"></i></td>@endforeach
                            @if($r)<td class="rk"><span></span></td>@endif</tr>
                    @endforeach
                </tbody>
            </table>
            @foreach($b['extra'] as $x)
                <div class="other" style="margin:4mm 0 0"><i class="box"></i><span class="en">{{ $x['label'] }}</span><span class="rule"></span><span class="ar" dir="rtl">{{ $x['label_ar'] ?? '' }}</span></div>
            @endforeach
        </section>
    @elseif($b['kind'] === 'speak')
        <section class="sec speak">
            @include('forms::pdf.band', ['num' => '✦', 'p' => $b['page']])
            @foreach($b['prompts'] as $k => $f)
                <div class="prompt"><div class="qh"><span class="qn">{{ sprintf('%02d', $k + 1) }}</span>
                    <div class="qen">{{ $f['label'] ?? '' }}</div><div class="qar" dir="rtl">{{ $f['label_ar'] ?? '' }}</div></div>
                    <div class="lines">@for($i = 0; $i < 4; $i++)<span></span>@endfor</div></div>
            @endforeach
        </section>
    @endif
@endforeach

{{-- ── Open notes ── --}}
@if($free)
<section class="free">
    <div class="kicker">Open notes · ملاحظات مفتوحة</div>
    <div class="ttl"><h2>{{ $free['label'] ?? 'In their own words' }}</h2><span dir="rtl">{{ $free['label_ar'] ?? 'بكلماتهم' }}</span></div>
    <div class="lines">@for($i = 0; $i < 27; $i++)<span></span>@endfor</div>
</section>
@endif

{{-- ── Visit summary ── --}}
@if($summary)
<section class="free">
    <div class="kicker">After the visit — interviewer only · بعد الزيارة — للمحاور فقط</div>
    <div class="ttl"><h2>{{ $summary['page']['title'] ?? 'Visit summary' }}</h2><span dir="rtl">{{ $summary['page']['title_ar'] ?? 'ملخص الزيارة' }}</span></div>
    @foreach($summary['lines'] as $l)
        <div class="q"><div class="qh"><span class="qn">{{ sprintf('%02d', $l['n']) }}</span>
            <div class="qen">{{ $l['f']['label'] ?? '' }}</div><div class="qar" dir="rtl">{{ $l['f']['label_ar'] ?? '' }}</div></div>
            <div class="lines">@for($i = 0; $i < max(2, min(4, (int) ($l['f']['rows'] ?? 2))); $i++)<span></span>@endfor</div></div>
    @endforeach
    @if($summary['scores'])
        <div class="score" style="grid-template-columns:repeat({{ min(5, count($summary['scores'])) }},1fr)">
            @foreach($summary['scores'] as $f)
                <div><b>{{ $f['label'] ?? '' }}</b><span>{{ $f['label_ar'] ?? '' }}</span>
                    @switch($f['type'] ?? '')
                        @case('scale')@for($i = 1; $i <= 5; $i++)<i class="mini">{{ $i }}</i> @endfor @break
                        @case('radio')@foreach(($f['options'] ?? []) as $o){{ $o['label'] }} <i class="box"></i> &nbsp;@endforeach @break
                        @case('checkbox')<i class="box"></i> @break
                        @default ____ / ____
                    @endswitch
                </div>
            @endforeach
        </div>
    @endif
    <div class="thanks">{{ $thanks }}<span dir="rtl">{{ $thanksAr }}</span></div>
</section>
@else
<div class="thanks" style="margin-top:10mm">{{ $thanks }}<span dir="rtl">{{ $thanksAr }}</span></div>
@endif
</body>
</html>
