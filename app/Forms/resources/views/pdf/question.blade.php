{{-- One printed question (blank), in the hand-made questionnaire's shape. --}}
@php
    $t = $f['type'] ?? 'text';
    $hint = match ($t) { 'checkboxes' => ['Tick all that apply', 'اختر كل ما ينطبق'], 'radio' => ['Tick one', 'اختر واحداً'], 'scale' => ['Circle one', 'ضع دائرة'], default => ['', ''] };
    if (! empty($f['help'])) { $hint = [$f['help'], $f['help_ar'] ?? '']; }
@endphp
<div class="q">
    <div class="qh"><span class="qn">{{ sprintf('%02d', $n) }}</span>
        <div class="qen">{{ $f['label'] ?? '' }}@if($hint[0])<span class="hint">{{ $hint[0] }}</span>@endif</div>
        <div class="qar" dir="rtl">{{ $f['label_ar'] ?? '' }}@if($hint[1])<span class="hint">{{ $hint[1] }}</span>@endif</div></div>
    @switch($t)
        @case('checkboxes')
        @case('radio')
            <div class="opts">@foreach(($f['options'] ?? []) as $o)<span class="opt"><i class="box"></i><span class="en">{{ $o['label'] }}</span>@if(! empty($o['label_ar']))<span class="ar" dir="rtl">{{ $o['label_ar'] }}</span>@endif</span>@endforeach</div>
            @if(! empty($f['other']))<div class="other"><i class="box"></i><span class="en">Other</span><span class="rule"></span><span class="ar" dir="rtl">أخرى</span></div>@endif
            @if(! empty($f['note']))<div class="note"><span class="en">Note</span><span class="rule"></span><span class="ar" dir="rtl">ملاحظة</span></div>@endif
            @break
        @case('scale')
            <div class="scale"><span class="lo"><b>{{ $f['low'] ?? '' }}</b><span dir="rtl" style="text-align:left">{{ $f['low_ar'] ?? '' }}</span></span>
                <span class="dots">@for($i = (int) ($f['min'] ?? 1); $i <= (int) ($f['max'] ?? 5); $i++)<span class="dot">{{ $i }}</span>@endfor</span>
                <span class="hi"><b>{{ $f['high'] ?? '' }}</b><span dir="rtl">{{ $f['high_ar'] ?? '' }}</span></span></div>
            @break
        @case('numbers')
            <div class="nums" style="grid-template-columns:repeat({{ max(2, min(6, count($f['rows'] ?? []))) }},1fr)">@foreach(($f['rows'] ?? []) as $r)<div class="cell"><span class="v"></span><span class="en">{{ $r['label'] }}</span><span class="ar" dir="rtl">{{ $r['label_ar'] ?? '' }}</span></div>@endforeach</div>
            @break
        @case('checkbox')
            <div class="check"><i class="box"></i><span>{{ __('I agree') }} <span class="ar" dir="rtl">· أوافق</span></span></div>
            @break
        @case('rank')
            <div class="opts">@foreach(($f['rows'] ?? []) as $r)<span class="opt"><span style="display:inline-block;width:6mm;border-bottom:1px solid var(--line);height:3.4mm"></span><span class="en">{{ $r['label'] }}</span>@if(! empty($r['label_ar']))<span class="ar" dir="rtl">{{ $r['label_ar'] }}</span>@endif</span>@endforeach</div>
            @break
        @default
            <div class="lines">@for($i = 0; $i < max(1, min(8, (int) ($f['rows'] ?? ($t === 'textarea' ? 3 : 1)))); $i++)<span></span>@endfor</div>
    @endswitch
</div>
