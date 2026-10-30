{{-- What every TakeNote page puts in its <head>: the app's identity for the phone (manifest, icons, colours), its fonts and icons. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#160f24">
<meta name="color-scheme" content="dark">
{{-- TakeNote paints its own colours: no browser dark mode or extension may re-colour it (takenote/styles) --}}
<meta name="darkreader-lock">
<meta name="application-name" content="TakeNote">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="TakeNote">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="manifest" href="{{ route('takenote.manifest') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/takenote/icon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/takenote/apple-touch-icon.png') }}">
<title>TakeNote</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
{{-- Inside the TakeNote / TAKEONE Android app the shell already keeps the page clear of the
     status and navigation bars, yet newer Android WebViews report those bars to the page too.
     Adding them twice is the empty band at the top and bottom, so in the app the page's own
     safe-area spacing is turned off. The app announces itself through its Capacitor-shaped bridge. --}}
<script>
try { if (window.Capacitor && window.Capacitor.isNativePlatform && window.Capacitor.isNativePlatform()) document.documentElement.classList.add('in-shell'); } catch (e) {}
</script>
<script>
// The font pair chosen on this device, before the first paint (takenote/fonts writes it; same-origin files only).
try {
    var tnf = JSON.parse(localStorage.getItem('takenote.fonts') || 'null'), tnOk = function (n) { return typeof n === 'string' && /^[A-Za-z0-9 ]{1,60}$/.test(n); };
    if (tnf && tnf.en && tnf.ar && tnOk(tnf.en.family) && tnOk(tnf.ar.family)) {
        [tnf.en, tnf.ar].forEach(function (f) {
            (f.faces || []).forEach(function (face) {
                var u = new URL(face.url, location.href);
                if (u.origin !== location.origin || u.pathname.indexOf('/fonts/designer/') !== 0 || !window.FontFace) return;
                var ff = new FontFace(f.family, 'url(' + JSON.stringify(u.href) + ')', { weight: String(face.weight || '400'), style: face.style === 'italic' ? 'italic' : 'normal', display: 'swap' });
                document.fonts.add(ff); ff.load().catch(function () {});
            });
        });
        document.documentElement.style.setProperty('--note', JSON.stringify(tnf.en.family) + ', ' + JSON.stringify(tnf.ar.family) + ", system-ui, -apple-system, 'Segoe UI', Tahoma, sans-serif");
    }
} catch (e) {}
</script>
<style>
:root { --sa-t: env(safe-area-inset-top, 0px); --sa-b: env(safe-area-inset-bottom, 0px); --sa-l: env(safe-area-inset-left, 0px); --sa-r: env(safe-area-inset-right, 0px); }
html.in-shell { --sa-t: 0px; --sa-b: 0px; --sa-l: 0px; --sa-r: 0px; }
@font-face { font-family: 'Instrument Serif'; font-style: normal; font-weight: 400; font-display: swap;
    src: url('{{ asset('vendor/instrument-serif/instrument-serif-latin-400-normal.woff2') }}') format('woff2'); }
@font-face { font-family: 'Instrument Serif'; font-style: italic; font-weight: 400; font-display: swap;
    src: url('{{ asset('vendor/instrument-serif/instrument-serif-latin-400-italic.woff2') }}') format('woff2'); }
/* TakeNote IS its dark theme, and says so in the way every browser's "dark mode for websites" looks for: a
   color-scheme of dark (Chrome) and a real prefers-color-scheme: dark rule (Samsung Internet skips sites that
   have one). Without them the browser re-colours the page itself and the note paper turns a muddy yellow. */
@media (prefers-color-scheme: dark) {
    :root { color-scheme: dark; --desk: #160f24; --paper: #fbf7ef; --ink: #1d1530; }
    html, body { background-color: #160f24; }
}
:root {
    --desk: #160f24; --desk-2: #261c3d; --primary: #7c6bf5; --violet: #7c6bf5;
    --paper: #fbf7ef; --paper-2: #f3ecdf; --ink: #1d1530; --ink-soft: #6b6280;
    --rose: #e0457b; --amber: #e89a1c; --teal: #16a394; --sky: #3b9ae1;
    /* The note's own type: the English + Arabic pair chosen in the Fonts sheet (takenote/fonts). */
    --note: 'Inter', 'IBM Plex Sans Arabic', system-ui, -apple-system, 'Segoe UI', Tahoma, sans-serif;
    --serif: var(--note);
    --sans: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    --spring: cubic-bezier(.2,.9,.25,1.15); --ease: cubic-bezier(.2,.8,.2,1);
    --safe-t: var(--sa-t, env(safe-area-inset-top, 0px)); --safe-b: var(--sa-b, env(safe-area-inset-bottom, 0px));
}
* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
html, body { margin: 0; background: var(--desk); }
body { font-family: var(--sans); color: #fff; font-size: 14px; -webkit-font-smoothing: antialiased; }
button { font: inherit; color: inherit; background: none; border: 0; padding: 0; cursor: pointer; }
[dir=rtl] .flip { display: inline-block; transform: scaleX(-1); }
@keyframes rise { from { opacity: 0; transform: translateY(8px) scale(.98); } to { opacity: 1; transform: none; } }
@keyframes shimmer { to { background-position: -300% 0; } }
@media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
</style>
