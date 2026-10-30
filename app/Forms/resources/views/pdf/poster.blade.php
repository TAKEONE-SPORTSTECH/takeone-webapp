<!doctype html>
<html lang="en"><head><meta charset="utf-8">
{{-- The printable / shareable QR poster (1080 × 1350). Everything is inside the
     page — fonts as local files, the QR as inline SVG, the artwork as a data
     URI — because the printer resolves no host names. --}}
<style>
@font-face{font-family:Bricolage;src:url(file://{{ $fonts }}/bricolage-grotesque/BricolageGrotesque-VF.ttf);font-weight:200 800}
@font-face{font-family:Inter;src:url(file://{{ $fonts }}/inter/Inter-Regular.ttf);font-weight:400}
@font-face{font-family:Inter;src:url(file://{{ $fonts }}/inter/Inter-Bold.ttf);font-weight:700}
@font-face{font-family:Alexandria;src:url(file://{{ $fonts }}/alexandria/Alexandria-VF.ttf);font-weight:100 900}
@font-face{font-family:Plex;src:url(file://{{ $fonts }}/ibm-plex-sans-arabic/IBMPlexSansArabic-Regular.ttf);font-weight:400}
@font-face{font-family:Serif;src:url(file://{{ $fonts }}/instrument-serif/InstrumentSerif-Italic.ttf);font-style:italic}
*{box-sizing:border-box;margin:0;padding:0}
html,body{width:1080px;height:1350px;overflow:hidden}
body{position:relative;color:#fff;font-family:Inter,sans-serif;
  background:radial-gradient(120% 80% at 100% 0%,#9d8cf7 0%,transparent 55%),radial-gradient(90% 70% at 0% 100%,#3b2a9a 0%,transparent 60%),linear-gradient(160deg,#6a55e6,#2a1f78)}
.art{position:absolute;left:0;top:0;width:1080px;height:608px;object-fit:cover;-webkit-mask-image:linear-gradient(180deg,#000 55%,transparent 100%);mask-image:linear-gradient(180deg,#000 55%,transparent 100%)}
.art-fade{position:absolute;left:0;top:0;width:1080px;height:300px;background:linear-gradient(180deg,rgba(23,19,58,.4) 0%,rgba(23,19,58,0) 100%)}
.ring{position:absolute;border-radius:50%;border:2px solid rgba(255,255,255,.18)}
.in{position:absolute;inset:84px 90px 0}
.brand{font:800 26px Bricolage;letter-spacing:.3em}
h1{font:800 76px/1.02 Bricolage;letter-spacing:-.02em;margin-top:{{ $art ? 360 : 70 }}px;max-width:900px}
h1 em{font:italic 400 84px Serif;letter-spacing:0;color:#e3dcff}
.ar{font:800 50px/1.35 Alexandria;direction:rtl;text-align:right;margin-top:18px}
.card{position:absolute;left:90px;right:90px;bottom:90px;background:#fff;border-radius:48px;padding:46px;display:flex;align-items:center;gap:46px;color:#17133a;box-shadow:0 30px 80px rgba(0,0,0,.22)}
.qr{width:400px;height:400px;flex:0 0 auto}
.qr svg{width:100%;height:100%;display:block}
.t b{display:block;font:800 46px/1.1 Bricolage}
.t span{display:block;font:800 40px Alexandria;margin-top:12px;color:#5b49e0;direction:rtl;text-align:left}
.t p{font:400 24px/1.5 Inter;color:#6b6a80;margin-top:24px}
.t small{display:block;font:400 18px Inter;color:#9a97b3;margin-top:18px;word-break:break-all}
</style></head>
<body>
@if($art)<img class="art" src="{{ $art }}" alt=""><div class="art-fade"></div>
@else<span class="ring" style="width:620px;height:620px;right:-200px;top:-180px"></span><span class="ring" style="width:300px;height:300px;right:60px;top:240px;background:rgba(255,255,255,.07);border:none"></span>@endif
<div class="in">
    <div class="brand">TAKEONE</div>
    <h1>{!! $titleHtml !!}</h1>
    @if($titleAr)<div class="ar">{{ $titleAr }}</div>@endif
</div>
<div class="card">
    <div class="qr">{!! $qr !!}</div>
    <div class="t"><b>Scan to answer</b><span>امسح للإجابة</span>
        <p>English or Arabic.<br>Saved as you go.<br>About {{ $minutes }} minutes.</p>
        <small>{{ $short }}</small></div>
</div>
</body></html>
