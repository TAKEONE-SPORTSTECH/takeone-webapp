# TAKEONE — the public event pages (for redesign)

Three standalone pages that a **stranger with no account** opens from a link
shared on WhatsApp or Instagram. They are the entire product for that person:
there is no navigation bar, no sidebar, no bottom tabs and no site footer,
because there is nowhere else for them to go. The event is the page.

Everything below is **rendered HTML from the live templates** (2026-09-01), with
the compiled Tailwind 4 stylesheet omitted — the class names in the markup are
the design. Tokens and the custom motion classes are given in full further down.

---

## What these pages are, and the rules they live under

| | |
|---|---|
| **1. Public event page — mobile** | `GET /e/{uuid}` on a phone. The poster: what the competition is, when, where, what it costs, and the way in. |
| **2. Public event page — desktop** | The same page, wide. Two columns: the event card, and an aside with quick facts + the entry action. |
| **3. Enrolment flow — one file, both widths** | `GET /e/{uuid}/enter`. Three questions then a confirmation: who you are, your weight and belt, and the account that holds your place. |

**Hard constraints — a redesign must keep all of these.**

1. **White-labelled.** No TAKEONE mark anywhere. The tab icon, the home-screen
   icon, the share card and the footer all carry the EVENT and its host club.
   The person was sent a competition; a platform logo tells them they landed on
   somebody else's website.
2. **Installable as its own app.** Each page links a per-event web manifest and
   an icon generated from the club's logo, so adding the link to a home screen
   gives the competition's own name and mark, opening with no browser chrome.
3. **Nothing that names another person.** No roster, no attendees, no draw, no
   results, no contact details, no money owed by anybody. A head COUNT is a
   poster fact; who is entered is not. This is enforced server-side — a template
   cannot reach past `App\Events\Support\PublicEvent`.
4. **The event's own colour drives the page.** Every gradient, accent, focus
   ring and progress bar is `#b3121f` for this event and something else
   for the next one. Nothing may hard-code it.
5. **The detail card is SHARED with the signed-in member page** (`/me/events/{uuid}`).
   Redesigning that section changes both, which is deliberate — a competition
   should look the same whoever opens it. Say so explicitly if you want them to
   diverge.
6. Bootstrap Icons only (`bi bi-*`). Safe-area padding on anything fixed to the
   bottom edge. `prefers-reduced-motion` respected.

**Sample content:** this event has no run-of-show, divisions, requirements or
prize of its own, so those four sections carry realistic sample data — the pages
are shown at full height on purpose. Title, club, dates, venue, fee, capacity,
colour and logo are the real record.

---

## Design system

Tailwind 4, tokens declared in `resources/css/app.css`. Use the token names
(`bg-background`, `text-muted-foreground`, `bg-accent`, `text-primary`), never a
raw hex, except for the event colour which arrives as data.

```css
@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
        'Segoe UI Symbol', 'Noto Color Emoji';

    /* Base Colors */
    --color-background: hsl(220 15% 97%);
    --color-foreground: hsl(215 25% 27%);

    --color-card: hsl(0 0% 100%);
    --color-card-foreground: hsl(215 25% 27%);

    /* Primary - Purple */
    --color-primary: hsl(250 65% 65%);
    --color-primary-foreground: hsl(0 0% 100%);
    --color-brand-red: hsl(250 65% 60%);
    --color-brand-red-dark: hsl(250 65% 52%);

    /* Secondary - Soft Sage Green */
    --color-secondary: hsl(140 30% 75%);
    --color-secondary-foreground: hsl(140 45% 25%);

    /* Success - Green */
    --color-success: hsl(150 55% 38%);
    --color-success-foreground: hsl(150 55% 20%);

    /* Warning - Soft Peach */
    --color-warning: hsl(35 60% 80%);
    --color-warning-foreground: hsl(35 60% 30%);

    /* Info - Soft Sky Blue */
    --color-info: hsl(200 50% 75%);
    --color-info-foreground: hsl(200 60% 25%);

    --color-muted: hsl(220 15% 94%);
    --color-muted-foreground: hsl(215 15% 50%);

    --color-accent: hsl(250 60% 92%);
    --color-accent-foreground: hsl(250 60% 30%);

    --color-destructive: hsl(0 50% 75%);
    --color-destructive-foreground: hsl(0 0% 100%);

    --color-border: hsl(210 14% 80%);
    --color-input: hsl(220 15% 92%);
    --color-ring: hsl(250 65% 60%);
    --radius: 0.75rem;

    /* Sidebar */
    --color-sidebar-background: hsl(250 25% 96%);
    --color-sidebar-foreground: hsl(215 25% 35%);
    --color-sidebar-primary: hsl(250 65% 60%);
    --color-sidebar-primary-foreground: hsl(0 0% 100%);
    --color-sidebar-accent: hsl(250 25% 90%);
    --color-sidebar-accent-foreground: hsl(215 25% 40%);
    --color-sidebar-border: hsl(250 20% 85%);
    --color-sidebar-ring: hsl(250 65% 60%);
}

/*
 * Tailwind Component Classes
```

### Custom motion + surface classes used by these pages

```css
.mobile-stagger > * {
    opacity: 0;
    animation: m-rise .52s cubic-bezier(.22,.61,.36,1) both;
}
.mobile-stagger > *:nth-child(1){ animation-delay:.02s } .mobile-stagger > *:nth-child(2){ animation-delay:.07s }
.mobile-stagger > *:nth-child(3){ animation-delay:.12s } .mobile-stagger > *:nth-child(4){ animation-delay:.17s }
.mobile-stagger > *:nth-child(5){ animation-delay:.22s } .mobile-stagger > *:nth-child(6){ animation-delay:.27s }
.mobile-stagger > *:nth-child(7){ animation-delay:.32s } .mobile-stagger > *:nth-child(8){ animation-delay:.37s }
.mobile-stagger > *:nth-child(9){ animation-delay:.42s } .mobile-stagger > *:nth-child(n+10){ animation-delay:.47s }

/* Entrance primitives for one-off elements. */
.m-in       { animation: m-rise .52s cubic-bezier(.22,.61,.36,1) both; }

.m-in       { animation: m-rise .52s cubic-bezier(.22,.61,.36,1) both; }
.m-in-pop   { animation: m-pop  .5s  cubic-bezier(.22,.61,.36,1) both; }
.m-in-fade  { animation: m-fade .6s ease both; }

/* Drill-down panel entrance — used by the mobile hub-and-spoke pattern when a
   detail panel replaces the hub (see "Mobile Pattern Language" in CLAUDE.md). */
.m-panel-in { animation: m-panel .34s cubic-bezier(.22,.61,.36,1) both; }
@keyframes m-panel { from { opacity: 0; transform: translate3d(0, 10px, 0); } to { opacity: 1; transform: none; } }

.m-press { transition: transform .14s cubic-bezier(.22,.61,.36,1), box-shadow .2s ease; -webkit-tap-highlight-color: transparent; }
.m-press:active { transform: scale(.975); }


.m-card {
    position: relative;
    background: #fff;
    border-radius: 1rem;
    border: 1px solid hsl(210 14% 90%);
    box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 8px 24px -16px rgba(16,24,40,.22);
}

/* Hero with a living gradient glow (uses the brand primary, stays on-theme). */
.m-hero {

.m-hero {
    position: relative;
    overflow: hidden;
    background:
        radial-gradient(120% 120% at 0% 0%, hsl(250 65% 72%) 0%, transparent 55%),
        radial-gradient(120% 140% at 100% 0%, hsl(265 70% 62%) 0%, transparent 50%),
        hsl(var(--primary));
}
.m-hero::after {
    content: "";
    position: absolute; inset: 0;
    background: linear-gradient(115deg, transparent 0%, rgba(255,255,255,.18) 50%, transparent 60%);
    transform: translateX(-120%) skewX(-18deg);
    animation: m-sheen 5.5s ease-in-out 1s infinite;
    pointer-events: none;
}

/* Animated progress/stat bar fill (set width inline; animation grows it in). */
.m-bar-fill { transform-origin: left center; animation: m-bar .9s cubic-bezier(.22,.61,.36,1) .15s both; }


.m-bar-fill { transform-origin: left center; animation: m-bar .9s cubic-bezier(.22,.61,.36,1) .15s both; }

/* Skeleton shimmer for loading states. */

.m-float { animation: m-float 3.4s ease-in-out infinite; }

```

Typography is **Inter** (300–900). Headings `font-black`; page title
`text-2xl font-black leading-tight`; labels `text-[11px] uppercase tracking-wide
font-bold text-muted-foreground`.

---

## 1. Public event page — MOBILE

`resources/views/entry/public/mobile.blade.php` + `resources/views/entry/layout.blade.php`

```html
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="">
    <meta name="theme-color" content="#b3121f">
    <meta name="robots" content="index, follow">

    <title>Gulf Open Karate Championship 2026 · Al Hala Karate Club</title>
    <meta name="description" content="The Gulf&#039;s open karate championship, run over two days across four mats. Kumite and kata, cadet through senior, with the finals block on Sunday evening.

Entrie...">

    
    <link rel="icon" type="image/png" sizes="192x192" href="https://stage.takeone.bh/e/46bccfc8-e6a8-494c-a58d-72320dd6aa58/icon-192.png?v=d7adc9d2">
    <link rel="apple-touch-icon" href="https://stage.takeone.bh/e/46bccfc8-e6a8-494c-a58d-72320dd6aa58/icon-180.png?v=d7adc9d2">

    
    <!-- per-event web app manifest -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Gulf Open Karate C">
    <meta name="application-name" content="Gulf Open Karate C">

    
    <meta property="og:type" content="website">
    
    <meta property="og:site_name" content="Al Hala Karate Club">    <meta property="og:title" content="Gulf Open Karate Championship 2026">
    <meta property="og:description" content="The Gulf&#039;s open karate championship, run over two days across four mats. Kumite and kata, cadet through senior, with the finals block on Sunday evening.

Entries are made by clubs and by individual co...">
    <meta property="og:image" content="https://stage.takeone.bh/file/clubs/67/gallery/1517e572-b700-46e4-a909-8c52681b6bc8.jpg">    <meta property="og:url" content="https://stage.takeone.bh">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>

    
    <!-- Tailwind 4 build (305 KB) — omitted; see "Design system" above --><!-- Tailwind 4 build (305 KB) — omitted; see "Design system" above -->
    <style>
        :root { --ev: #b3121f; }
        body { font-family: 'Inter', sans-serif; }

        /* The same rule the app shell applies, so a component that measures the
           viewport behaves identically here. */
        html { -webkit-text-size-adjust: 100%; }
    </style>
    </head>
<body class="bg-background text-foreground antialiased">

    
    <main class="mobile-stagger px-4 py-4 min-h-[60vh]">
        <div x-data="publicEvent()" class="-mx-4 -mt-4 pb-4">

    
    <header class="m-hero px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, #b3121f, #b3121fb0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        
        <div class="flex items-center justify-end relative z-50">
            <div class="flex items-center gap-2">
                <button type="button" @click="share()"
                        class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center"
                        aria-label="Share this competition">
                    <i class="bi bi-share text-base"></i>
                </button>
            </div>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-trophy"></i> Karate Championship
                </span>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-dribbble"></i> Karate</span>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-cash-coin"></i> Paid entry</span>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-ticket-perforated"></i> Ticketed</span>
                            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">Gulf Open Karate Championship 2026</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-building"></i>Al Hala Karate Club
            </p>
        </div>
    </header>

    
    <div class="px-4 -mt-10 relative z-10">
        <div class="bg-white rounded-3xl shadow-lg border border-gray-100 p-4">
            <div class="grid grid-cols-3 gap-2 text-center">
                <button type="button" @click="jump(['run-start', 'how-it-runs'])"
                        class="m-press rounded-xl -m-1 p-1" aria-label="How the event runs">
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-calendar3"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5 truncate">Thu 01 Oct</p>
                    <p class="text-[10px] text-muted-foreground truncate">9:00 AM</p>
                </button>
                <button type="button" @click="jump('enter')"
                        class="m-press border-x border-gray-100 py-1" aria-label="To join">
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-cash-coin"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5 truncate">BHD 15</p>
                    <p class="text-[10px] text-muted-foreground truncate">To join</p>
                </button>
                <button type="button" @click="jump('where')"
                        class="m-press min-w-0 rounded-xl -m-1 p-1" aria-label="Location">
                    <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-geo-alt"></i></div>
                    <p class="text-xs font-bold text-foreground mt-1.5 truncate" title="Isa Sports City, Hall 2">Isa Sports City, Hall 2</p>
                    <p class="text-[10px] text-muted-foreground">Venue</p>
                </button>
            </div>

            
                            <div class="mt-5">
                    <div class="flex items-center justify-between text-[11px] mb-1.5">
                        <span class="font-semibold text-foreground">0 going</span>
                        <span class="text-muted-foreground">120 spots left</span>
                    </div>
                    <div class="h-2 rounded-full bg-muted overflow-hidden">
                        <div class="m-bar-fill h-full rounded-full"
                             style="width: 0%; background: #b3121f;"></div>
                    </div>
                </div>
                    </div>
    </div>

    
    <div class="px-4 mt-4">
        <div class="m-card rounded-2xl overflow-hidden">

            
            <div class="px-5 sm:px-6 py-4 text-white relative overflow-hidden"
     style="background: linear-gradient(135deg, #b3121f, #1f2937);">
    <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
    <div class="relative flex items-center gap-3">
        <i class="bi bi-info-circle text-2xl text-white/90 flex-shrink-0"></i>
        <div class="min-w-0">
                            <p class="text-[13px] font-black uppercase tracking-[0.16em] leading-none">About this event</p>
                    </div>
    </div>
</div>

            <div class="p-5">
                                    <p class="text-[15px] leading-relaxed text-foreground">The Gulf&#039;s open karate championship, run over two days across four mats. Kumite and kata, cadet through senior, with the finals block on Sunday evening.

Entries are made by clubs and by individual competitors. Weigh-in is the morning of each day.</p>
                
                                    <div class="flex flex-wrap gap-1.5 mt-4">
                                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold"
                                  style="background: #b3121f14; color: #b3121f;">#kumite</span>
                                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold"
                                  style="background: #b3121f14; color: #b3121f;">#kata</span>
                                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-bold"
                                  style="background: #b3121f14; color: #b3121f;">#open</span>
                                            </div>
                            </div>

            
                            <span id="how-it-runs" class="block"></span>
                <div class="px-5 sm:px-6 py-4 text-white relative overflow-hidden"
     style="background: linear-gradient(135deg, #b3121f, #1f2937);">
    <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
    <div class="relative flex items-center gap-3">
        <i class="bi bi-signpost-split text-2xl text-white/90 flex-shrink-0"></i>
        <div class="min-w-0">
                            <p class="text-[13px] font-black uppercase tracking-[0.16em] leading-none">How the event runs</p>
                    </div>
    </div>
</div>
                <div class="p-5">
                    <div>
                                                                                <div                                  
                                 class="flex gap-3.5 rounded-xl"
                                 style="--m-attn-color: #b3121f80;">

                                
                                <div class="w-11 shrink-0 text-center pt-0.5">
                                    <span class="block text-[10px] font-black uppercase tracking-wider text-muted-foreground">Sep</span>
                                    <span class="block text-[22px] font-black leading-none mt-0.5 text-foreground"
                                          style="">21</span>
                                    <span class="block text-[10px] font-semibold text-muted-foreground mt-0.5">Mon</span>
                                </div>

                                
                                <div class="flex flex-col items-center pt-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 "
                                          style="background: #d1d5db;"></span>
                                                                            <span class="w-px flex-1 my-1.5" style="background: #e5e7eb;"></span>
                                                                    </div>

                                
                                <div class="min-w-0 flex-1 pb-6">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-[15px] font-bold leading-tight text-foreground"
                                           style="">Entries close</p>
                                                                            </div>

                                    
                                                                            <p class="inline-flex items-center gap-1.5 mt-1.5 text-[12px] font-bold text-foreground">
                                            <i class="bi bi-clock text-[11px]" style="color: #b3121f;"></i>23:59
                                        </p>
                                    
                                    
                                    
                                    <p class="text-[12px] text-muted-foreground leading-snug mt-1">No entries accepted after this point.</p>
                                </div>
                            </div>
                                                                                <div                                  
                                 class="flex gap-3.5 rounded-xl"
                                 style="--m-attn-color: #b3121f80;">

                                
                                <div class="w-11 shrink-0 text-center pt-0.5">
                                    <span class="block text-[10px] font-black uppercase tracking-wider text-muted-foreground">Sep</span>
                                    <span class="block text-[22px] font-black leading-none mt-0.5 text-foreground"
                                          style="">30</span>
                                    <span class="block text-[10px] font-semibold text-muted-foreground mt-0.5">Wed</span>
                                </div>

                                
                                <div class="flex flex-col items-center pt-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 "
                                          style="background: #d1d5db;"></span>
                                                                            <span class="w-px flex-1 my-1.5" style="background: #e5e7eb;"></span>
                                                                    </div>

                                
                                <div class="min-w-0 flex-1 pb-6">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-[15px] font-bold leading-tight text-foreground"
                                           style="">Weigh-in</p>
                                                                            </div>

                                    
                                                                            <p class="inline-flex items-center gap-1.5 mt-1.5 text-[12px] font-bold text-foreground">
                                            <i class="bi bi-clock text-[11px]" style="color: #b3121f;"></i>17:00 – 20:00
                                        </p>
                                    
                                    
                                    
                                    <p class="text-[12px] text-muted-foreground leading-snug mt-1">Isa Sports City, Hall 2 reception. Photo ID required.</p>
                                </div>
                            </div>
                                                                                <div  id="run-start"                                  
                                 class="flex gap-3.5 rounded-xl"
                                 style="--m-attn-color: #b3121f80;">

                                
                                <div class="w-11 shrink-0 text-center pt-0.5">
                                    <span class="block text-[10px] font-black uppercase tracking-wider text-muted-foreground">Oct</span>
                                    <span class="block text-[22px] font-black leading-none mt-0.5 text-foreground"
                                          style="">1</span>
                                    <span class="block text-[10px] font-semibold text-muted-foreground mt-0.5">Thu</span>
                                </div>

                                
                                <div class="flex flex-col items-center pt-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 "
                                          style="background: #d1d5db;"></span>
                                                                            <span class="w-px flex-1 my-1.5" style="background: #e5e7eb;"></span>
                                                                    </div>

                                
                                <div class="min-w-0 flex-1 pb-6">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-[15px] font-bold leading-tight text-foreground"
                                           style="">Day 1 — Kumite</p>
                                                                            </div>

                                    
                                                                            <p class="inline-flex items-center gap-1.5 mt-1.5 text-[12px] font-bold text-foreground">
                                            <i class="bi bi-clock text-[11px]" style="color: #b3121f;"></i>09:00
                                        </p>
                                    
                                    
                                                                            <div class="mt-2 space-y-1">
                                                                                                                                            <p class="flex items-center gap-1.5 text-[12px] font-bold text-foreground">
                                                    <i class="bi bi-clock text-[11px]"
                                                       style="color: #b3121f;"></i>
                                                    <span class="w-10 flex-shrink-0 font-semibold text-muted-foreground">AM</span>
                                                    <span>09:00 – 12:30</span>
                                                </p>
                                                                                                                                            <p class="flex items-center gap-1.5 text-[12px] font-bold text-muted-foreground">
                                                    <i class="bi bi-cup-hot-fill text-[11px]"
                                                       style="color: #9ca3af;"></i>
                                                    <span class="w-10 flex-shrink-0 font-semibold ">Break</span>
                                                    <span>12:30 – 13:30</span>
                                                </p>
                                                                                                                                            <p class="flex items-center gap-1.5 text-[12px] font-bold text-foreground">
                                                    <i class="bi bi-clock text-[11px]"
                                                       style="color: #b3121f;"></i>
                                                    <span class="w-10 flex-shrink-0 font-semibold text-muted-foreground">PM</span>
                                                    <span>13:30 – 18:00</span>
                                                </p>
                                                                                    </div>
                                    
                                    <p class="text-[12px] text-muted-foreground leading-snug mt-1">Cadet and junior divisions across four mats.</p>
                                </div>
                            </div>
                                                                                <div                                  
                                 class="flex gap-3.5 rounded-xl"
                                 style="--m-attn-color: #b3121f80;">

                                
                                <div class="w-11 shrink-0 text-center pt-0.5">
                                    <span class="block text-[10px] font-black uppercase tracking-wider text-muted-foreground">Oct</span>
                                    <span class="block text-[22px] font-black leading-none mt-0.5 text-foreground"
                                          style="">2</span>
                                    <span class="block text-[10px] font-semibold text-muted-foreground mt-0.5">Fri</span>
                                </div>

                                
                                <div class="flex flex-col items-center pt-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 "
                                          style="background: #d1d5db;"></span>
                                                                    </div>

                                
                                <div class="min-w-0 flex-1 ">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-[15px] font-bold leading-tight text-foreground"
                                           style="">Day 2 — Kata &amp; finals</p>
                                                                            </div>

                                    
                                                                            <p class="inline-flex items-center gap-1.5 mt-1.5 text-[12px] font-bold text-foreground">
                                            <i class="bi bi-clock text-[11px]" style="color: #b3121f;"></i>09:00
                                        </p>
                                    
                                    
                                    
                                    <p class="text-[12px] text-muted-foreground leading-snug mt-1">Senior divisions, then the finals block and the podium.</p>
                                </div>
                            </div>
                                            </div>
                </div>
            
            
                                            <div class="px-5 sm:px-6 py-4 text-white relative overflow-hidden"
     style="background: linear-gradient(135deg, #b3121f, #1f2937);">
    <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
    <div class="relative flex items-center gap-3">
        <i class="bi bi-diagram-3-fill bracket-icon text-2xl text-white/90 flex-shrink-0"></i>
        <div class="min-w-0">
                            <p class="text-[13px] font-black uppercase tracking-[0.16em] leading-none">Divisions</p>
                    </div>
    </div>
</div>
                <div class="p-5">
                    <div class="space-y-3.5">
                                                                                <div>
                                <p class="flex items-center gap-1.5 text-[12px] font-black mb-2" style="color: #3b82f6;">
                                    <i class="bi bi-gender-male"></i>Cadet Men
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                                                            <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                              style="background: #3b82f614;">-52 kg</span>
                                                                            <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                              style="background: #3b82f614;">-57 kg</span>
                                                                            <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                              style="background: #3b82f614;">-63 kg</span>
                                                                    </div>
                            </div>
                                                                                <div>
                                <p class="flex items-center gap-1.5 text-[12px] font-black mb-2" style="color: #ec4899;">
                                    <i class="bi bi-gender-female"></i>Cadet Women
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                                                            <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                              style="background: #ec489914;">-47 kg</span>
                                                                            <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                              style="background: #ec489914;">-54 kg</span>
                                                                    </div>
                            </div>
                                                                                <div>
                                <p class="flex items-center gap-1.5 text-[12px] font-black mb-2" style="color: #3b82f6;">
                                    <i class="bi bi-gender-male"></i>Senior Men
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                                                            <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                              style="background: #3b82f614;">-67 kg</span>
                                                                            <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                              style="background: #3b82f614;">-75 kg</span>
                                                                            <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                              style="background: #3b82f614;">-84 kg</span>
                                                                            <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                              style="background: #3b82f614;">+84 kg</span>
                                                                    </div>
                            </div>
                                                                                <div>
                                <p class="flex items-center gap-1.5 text-[12px] font-black mb-2" style="color: #ec4899;">
                                    <i class="bi bi-gender-female"></i>Senior Women
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                                                            <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                              style="background: #ec489914;">-55 kg</span>
                                                                            <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                              style="background: #ec489914;">-61 kg</span>
                                                                            <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                              style="background: #ec489914;">-68 kg</span>
                                                                    </div>
                            </div>
                                            </div>
                </div>
            
            
                            <div class="px-5 sm:px-6 py-4 text-white relative overflow-hidden"
     style="background: linear-gradient(135deg, #b3121f, #1f2937);">
    <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
    <div class="relative flex items-center gap-3">
        <i class="bi bi-clipboard-check text-2xl text-white/90 flex-shrink-0"></i>
        <div class="min-w-0">
                            <p class="text-[13px] font-black uppercase tracking-[0.16em] leading-none">Requirements</p>
                    </div>
    </div>
</div>
                <div class="p-5">
                    <ul class="space-y-2">
                                                    <li class="flex items-start gap-2.5 text-[13px] text-foreground/85 leading-snug">
                                <i class="bi bi-check-circle-fill text-[13px] mt-0.5 flex-shrink-0" style="color: #b3121f;"></i>
                                <span>Valid federation licence</span>
                            </li>
                                                    <li class="flex items-start gap-2.5 text-[13px] text-foreground/85 leading-snug">
                                <i class="bi bi-check-circle-fill text-[13px] mt-0.5 flex-shrink-0" style="color: #b3121f;"></i>
                                <span>Own gi (white) and mitts</span>
                            </li>
                                                    <li class="flex items-start gap-2.5 text-[13px] text-foreground/85 leading-snug">
                                <i class="bi bi-check-circle-fill text-[13px] mt-0.5 flex-shrink-0" style="color: #b3121f;"></i>
                                <span>Medical certificate dated within 12 months</span>
                            </li>
                                                    <li class="flex items-start gap-2.5 text-[13px] text-foreground/85 leading-snug">
                                <i class="bi bi-check-circle-fill text-[13px] mt-0.5 flex-shrink-0" style="color: #b3121f;"></i>
                                <span>Photo ID at the weigh-in desk</span>
                            </li>
                                            </ul>
                </div>
            
            
            
            
            <span id="where" class="block"></span>
                            
                <div class="px-5 sm:px-6 py-4 text-white relative overflow-hidden"
     style="background: linear-gradient(135deg, #b3121f, #1f2937);">
    <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
    <div class="relative flex items-center gap-3">
        <i class="bi bi-geo-alt-fill text-2xl text-white/90 flex-shrink-0"></i>
        <div class="min-w-0">
                            <p class="text-[13px] font-black uppercase tracking-[0.16em] leading-none">Location</p>
                    </div>
    </div>
</div>
                <div class="px-5 py-4 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-black text-foreground leading-tight">Isa Sports City, Hall 2</p>
                    </div>
                                            <a href="https://www.google.com/maps/dir/?api=1&amp;destination=Isa+Sports+City%2C+Hall+2" target="_blank" rel="noopener"
                           class="m-press flex-shrink-0 px-3 py-2 rounded-xl text-white text-[12px] font-black flex items-center gap-1.5"
                           style="background: #b3121f;">
                            <i class="bi bi-cursor-fill"></i> Directions
                        </a>
                                    </div>
                    </div>
    </div>

    
    <span id="enter" class="block"></span>
    <div class="px-4 mt-4">
                    <a href="https://stage.takeone.bh/e/46bccfc8-e6a8-494c-a58d-72320dd6aa58/enter"
               class="m-press block rounded-2xl p-4 text-center text-white shadow-lg"
               style="background: #b3121f; box-shadow: 0 18px 40px -20px #b3121f;">
                <span class="text-[15px] font-black flex items-center justify-center gap-2">
                    <i class="bi bi-person-plus-fill"></i>Enter this competition
                </span>
                <span class="block text-[11.5px] text-white/80 mt-1">No account needed — you make one as you go</span>
            </a>
            </div>

    
    <div class="px-4 mt-3">
        <button type="button" @click="share()"
                class="m-card m-press w-full rounded-2xl p-4 flex items-center gap-3 text-start">
            <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-accent text-primary">
                <i class="bi bi-share-fill text-lg"></i>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-foreground">Share this competition</span>
                <span class="block text-[11px] text-muted-foreground mt-0.5">Send it to someone who should be here</span>
            </span>
            <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50 text-xs flex-shrink-0"></i>
        </button>
    </div>
</div>
    </main>

    
    <footer class="px-6 pb-[max(2rem,env(safe-area-inset-bottom))] pt-8 text-center">
        <div class="h-px mx-auto max-w-xs mb-6" style="background: linear-gradient(90deg, transparent, rgba(0,0,0,.10), transparent);"></div>
                    
            <span class="w-12 h-12 mx-auto block"><img src="https://stage.takeone.bh/file/clubs/BHR/al-hala-karate-club/branding/logo_1787132519.jpg" alt="" class="w-full h-full object-contain"></span>
                            <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-bold mt-2.5">Organised by</p>
            <p class="text-[12.5px] font-bold text-foreground/70 mt-0.5">Al Hala Karate Club</p>
            </footer>

    <script>
    /* The two behaviours the shared markup expects, and nothing else. This page
       is outside the app shell, so it cannot borrow the shell's helpers — but
       the quick-facts chips are doors to the sections below them, and a chip
       that does nothing reads as broken. */
    function publicEvent() {
        return {
            jump(id) {
                const ids = Array.isArray(id) ? id : [id];
                for (const one of ids) {
                    const el = document.getElementById(one);
                    if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
                }
            },
            share() {
                const data = { title: 'Gulf Open Karate Championship 2026', url: window.location.href };
                if (navigator.share) { navigator.share(data).catch(() => {}); return; }
                navigator.clipboard?.writeText(data.url).then(
                    () => notice('Link copied.'),
                    () => notice(data.url),
                );
            },
        };
    }

    /* window.showToast belongs to the app shell, which is not loaded here. One
       small on-palette notice instead — never a native dialog. */
    function notice(msg) {
        const n = document.createElement('div');
        n.textContent = msg;
        n.style.cssText = 'position:fixed;left:1rem;right:1rem;bottom:calc(1.5rem + env(safe-area-inset-bottom));z-index:60;'
            + 'background:#111827;color:#fff;font-size:12.5px;padding:.85rem 1rem;border-radius:1rem;text-align:center;'
            + 'box-shadow:0 20px 40px -20px rgba(0,0,0,.5);transition:opacity .3s;opacity:0';
        document.body.appendChild(n);
        requestAnimationFrame(() => n.style.opacity = '1');
        setTimeout(() => { n.style.opacity = '0'; setTimeout(() => n.remove(), 320); }, 2600);
    }
</script>
</body>
</html>
```

---

## 2. Public event page — DESKTOP

`resources/views/entry/public/desktop.blade.php` + the same shell.

```html
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="">
    <meta name="theme-color" content="#b3121f">
    <meta name="robots" content="index, follow">

    <title>Gulf Open Karate Championship 2026 · Al Hala Karate Club</title>
    <meta name="description" content="The Gulf&#039;s open karate championship, run over two days across four mats. Kumite and kata, cadet through senior, with the finals block on Sunday evening.

Entrie...">

    
    <link rel="icon" type="image/png" sizes="192x192" href="https://stage.takeone.bh/e/46bccfc8-e6a8-494c-a58d-72320dd6aa58/icon-192.png?v=d7adc9d2">
    <link rel="apple-touch-icon" href="https://stage.takeone.bh/e/46bccfc8-e6a8-494c-a58d-72320dd6aa58/icon-180.png?v=d7adc9d2">

    
    <!-- per-event web app manifest -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Gulf Open Karate C">
    <meta name="application-name" content="Gulf Open Karate C">

    
    <meta property="og:type" content="website">
    
    <meta property="og:site_name" content="Al Hala Karate Club">    <meta property="og:title" content="Gulf Open Karate Championship 2026">
    <meta property="og:description" content="The Gulf&#039;s open karate championship, run over two days across four mats. Kumite and kata, cadet through senior, with the finals block on Sunday evening.

Entries are made by clubs and by individual co...">
    <meta property="og:image" content="https://stage.takeone.bh/file/clubs/67/gallery/1517e572-b700-46e4-a909-8c52681b6bc8.jpg">    <meta property="og:url" content="https://stage.takeone.bh">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>

    
    <!-- Tailwind 4 build (305 KB) — omitted; see "Design system" above --><!-- Tailwind 4 build (305 KB) — omitted; see "Design system" above -->
    <style>
        :root { --ev: #b3121f; }
        body { font-family: 'Inter', sans-serif; }

        /* The same rule the app shell applies, so a component that measures the
           viewport behaves identically here. */
        html { -webkit-text-size-adjust: 100%; }
    </style>
    </head>
<body class="bg-background text-foreground antialiased">

    
    <main class="mobile-stagger px-4 py-4 min-h-[60vh]">
        <div x-data="publicEvent()" class="-mx-4 -my-4 px-4 sm:px-6 lg:px-8 py-6">

    
    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative"
         style="background: linear-gradient(150deg, #b3121f, #b3121fb0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            
            <div class="flex items-center justify-end gap-2 mb-4">
                <button type="button" @click="share()"
                        class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center hover:bg-white/25 transition-colors"
                        aria-label="Share this competition">
                    <i class="bi bi-share text-base"></i>
                </button>
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-trophy"></i> Karate Championship
                </span>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-dribbble"></i> Karate</span>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-cash-coin"></i> Paid entry</span>
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi bi-ticket-perforated"></i> Ticketed</span>
                            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">Gulf Open Karate Championship 2026</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-building"></i>Al Hala Karate Club
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 items-start">

        
        <div class="space-y-4 min-w-0">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

                
                <div class="px-5 sm:px-6 py-4 text-white relative overflow-hidden"
     style="background: linear-gradient(135deg, #b3121f, #1f2937);">
    <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
    <div class="relative flex items-center gap-3">
        <i class="bi bi-info-circle text-2xl text-white/90 flex-shrink-0"></i>
        <div class="min-w-0">
                            <p class="text-[13px] font-black uppercase tracking-[0.16em] leading-none">About this event</p>
                    </div>
    </div>
</div>

                <div class="p-6">
                                            <p class="text-[15px] leading-relaxed text-foreground max-w-prose">The Gulf&#039;s open karate championship, run over two days across four mats. Kumite and kata, cadet through senior, with the finals block on Sunday evening.

Entries are made by clubs and by individual competitors. Weigh-in is the morning of each day.</p>
                    
                                            <div class="flex flex-wrap gap-1.5 mt-4">
                                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold"
                                      style="background: #b3121f14; color: #b3121f;">#kumite</span>
                                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold"
                                      style="background: #b3121f14; color: #b3121f;">#kata</span>
                                                            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold"
                                      style="background: #b3121f14; color: #b3121f;">#open</span>
                                                    </div>
                                    </div>

                
                                    <span id="how-it-runs" class="block"></span>
                    <div class="px-5 sm:px-6 py-4 text-white relative overflow-hidden"
     style="background: linear-gradient(135deg, #b3121f, #1f2937);">
    <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
    <div class="relative flex items-center gap-3">
        <i class="bi bi-signpost-split text-2xl text-white/90 flex-shrink-0"></i>
        <div class="min-w-0">
                            <p class="text-[13px] font-black uppercase tracking-[0.16em] leading-none">How the event runs</p>
                    </div>
    </div>
</div>
                    <div class="p-6">
                        <div>
                                                                                            <div                                      
                                 class="flex gap-3.5 rounded-xl"
                                     style="--m-attn-color: #b3121f80;">

                                    
                                    <div class="w-11 shrink-0 text-center pt-0.5">
                                        <span class="block text-[10px] font-black uppercase tracking-wider text-muted-foreground">Sep</span>
                                        <span class="block text-[22px] font-black leading-none mt-0.5 text-foreground"
                                              style="">21</span>
                                        <span class="block text-[10px] font-semibold text-muted-foreground mt-0.5">Mon</span>
                                    </div>

                                    
                                    <div class="flex flex-col items-center pt-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 "
                                              style="background: #d1d5db;"></span>
                                                                                    <span class="w-px flex-1 my-1.5" style="background: #e5e7eb;"></span>
                                                                            </div>

                                    
                                    <div class="min-w-0 flex-1 pb-6">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-[15px] font-bold leading-tight text-foreground"
                                               style="">Entries close</p>
                                                                                    </div>

                                        
                                                                                    <p class="inline-flex items-center gap-1.5 mt-1.5 text-[12px] font-bold text-foreground">
                                                <i class="bi bi-clock text-[11px]" style="color: #b3121f;"></i>23:59
                                            </p>
                                        
                                        
                                        
                                        <p class="text-[12px] text-muted-foreground leading-snug mt-1">No entries accepted after this point.</p>
                                    </div>
                                </div>
                                                                                            <div                                      
                                 class="flex gap-3.5 rounded-xl"
                                     style="--m-attn-color: #b3121f80;">

                                    
                                    <div class="w-11 shrink-0 text-center pt-0.5">
                                        <span class="block text-[10px] font-black uppercase tracking-wider text-muted-foreground">Sep</span>
                                        <span class="block text-[22px] font-black leading-none mt-0.5 text-foreground"
                                              style="">30</span>
                                        <span class="block text-[10px] font-semibold text-muted-foreground mt-0.5">Wed</span>
                                    </div>

                                    
                                    <div class="flex flex-col items-center pt-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 "
                                              style="background: #d1d5db;"></span>
                                                                                    <span class="w-px flex-1 my-1.5" style="background: #e5e7eb;"></span>
                                                                            </div>

                                    
                                    <div class="min-w-0 flex-1 pb-6">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-[15px] font-bold leading-tight text-foreground"
                                               style="">Weigh-in</p>
                                                                                    </div>

                                        
                                                                                    <p class="inline-flex items-center gap-1.5 mt-1.5 text-[12px] font-bold text-foreground">
                                                <i class="bi bi-clock text-[11px]" style="color: #b3121f;"></i>17:00 – 20:00
                                            </p>
                                        
                                        
                                        
                                        <p class="text-[12px] text-muted-foreground leading-snug mt-1">Isa Sports City, Hall 2 reception. Photo ID required.</p>
                                    </div>
                                </div>
                                                                                            <div  id="run-start"                                      
                                 class="flex gap-3.5 rounded-xl"
                                     style="--m-attn-color: #b3121f80;">

                                    
                                    <div class="w-11 shrink-0 text-center pt-0.5">
                                        <span class="block text-[10px] font-black uppercase tracking-wider text-muted-foreground">Oct</span>
                                        <span class="block text-[22px] font-black leading-none mt-0.5 text-foreground"
                                              style="">1</span>
                                        <span class="block text-[10px] font-semibold text-muted-foreground mt-0.5">Thu</span>
                                    </div>

                                    
                                    <div class="flex flex-col items-center pt-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 "
                                              style="background: #d1d5db;"></span>
                                                                                    <span class="w-px flex-1 my-1.5" style="background: #e5e7eb;"></span>
                                                                            </div>

                                    
                                    <div class="min-w-0 flex-1 pb-6">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-[15px] font-bold leading-tight text-foreground"
                                               style="">Day 1 — Kumite</p>
                                                                                    </div>

                                        
                                                                                    <p class="inline-flex items-center gap-1.5 mt-1.5 text-[12px] font-bold text-foreground">
                                                <i class="bi bi-clock text-[11px]" style="color: #b3121f;"></i>09:00
                                            </p>
                                        
                                        
                                                                                    <div class="mt-2 space-y-1">
                                                                                                                                                        <p class="flex items-center gap-1.5 text-[12px] font-bold text-foreground">
                                                        <i class="bi bi-clock text-[11px]"
                                                           style="color: #b3121f;"></i>
                                                        <span class="w-10 flex-shrink-0 font-semibold text-muted-foreground">AM</span>
                                                        <span>09:00 – 12:30</span>
                                                    </p>
                                                                                                                                                        <p class="flex items-center gap-1.5 text-[12px] font-bold text-muted-foreground">
                                                        <i class="bi bi-cup-hot-fill text-[11px]"
                                                           style="color: #9ca3af;"></i>
                                                        <span class="w-10 flex-shrink-0 font-semibold ">Break</span>
                                                        <span>12:30 – 13:30</span>
                                                    </p>
                                                                                                                                                        <p class="flex items-center gap-1.5 text-[12px] font-bold text-foreground">
                                                        <i class="bi bi-clock text-[11px]"
                                                           style="color: #b3121f;"></i>
                                                        <span class="w-10 flex-shrink-0 font-semibold text-muted-foreground">PM</span>
                                                        <span>13:30 – 18:00</span>
                                                    </p>
                                                                                            </div>
                                        
                                        <p class="text-[12px] text-muted-foreground leading-snug mt-1">Cadet and junior divisions across four mats.</p>
                                    </div>
                                </div>
                                                                                            <div                                      
                                 class="flex gap-3.5 rounded-xl"
                                     style="--m-attn-color: #b3121f80;">

                                    
                                    <div class="w-11 shrink-0 text-center pt-0.5">
                                        <span class="block text-[10px] font-black uppercase tracking-wider text-muted-foreground">Oct</span>
                                        <span class="block text-[22px] font-black leading-none mt-0.5 text-foreground"
                                              style="">2</span>
                                        <span class="block text-[10px] font-semibold text-muted-foreground mt-0.5">Fri</span>
                                    </div>

                                    
                                    <div class="flex flex-col items-center pt-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 "
                                              style="background: #d1d5db;"></span>
                                                                            </div>

                                    
                                    <div class="min-w-0 flex-1 ">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="text-[15px] font-bold leading-tight text-foreground"
                                               style="">Day 2 — Kata &amp; finals</p>
                                                                                    </div>

                                        
                                                                                    <p class="inline-flex items-center gap-1.5 mt-1.5 text-[12px] font-bold text-foreground">
                                                <i class="bi bi-clock text-[11px]" style="color: #b3121f;"></i>09:00
                                            </p>
                                        
                                        
                                        
                                        <p class="text-[12px] text-muted-foreground leading-snug mt-1">Senior divisions, then the finals block and the podium.</p>
                                    </div>
                                </div>
                                                    </div>
                    </div>
                
                
                                                        <div class="px-5 sm:px-6 py-4 text-white relative overflow-hidden"
     style="background: linear-gradient(135deg, #b3121f, #1f2937);">
    <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
    <div class="relative flex items-center gap-3">
        <i class="bi bi-diagram-3-fill bracket-icon text-2xl text-white/90 flex-shrink-0"></i>
        <div class="min-w-0">
                            <p class="text-[13px] font-black uppercase tracking-[0.16em] leading-none">Divisions</p>
                    </div>
    </div>
</div>
                    <div class="p-6">
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                                                                                            <div>
                                    <p class="flex items-center gap-1.5 text-[12px] font-black mb-2" style="color: #3b82f6;">
                                        <i class="bi bi-gender-male"></i>Cadet Men
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                                                                    <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                                  style="background: #3b82f614;">-52 kg</span>
                                                                                    <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                                  style="background: #3b82f614;">-57 kg</span>
                                                                                    <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                                  style="background: #3b82f614;">-63 kg</span>
                                                                            </div>
                                </div>
                                                                                            <div>
                                    <p class="flex items-center gap-1.5 text-[12px] font-black mb-2" style="color: #ec4899;">
                                        <i class="bi bi-gender-female"></i>Cadet Women
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                                                                    <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                                  style="background: #ec489914;">-47 kg</span>
                                                                                    <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                                  style="background: #ec489914;">-54 kg</span>
                                                                            </div>
                                </div>
                                                                                            <div>
                                    <p class="flex items-center gap-1.5 text-[12px] font-black mb-2" style="color: #3b82f6;">
                                        <i class="bi bi-gender-male"></i>Senior Men
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                                                                    <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                                  style="background: #3b82f614;">-67 kg</span>
                                                                                    <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                                  style="background: #3b82f614;">-75 kg</span>
                                                                                    <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                                  style="background: #3b82f614;">-84 kg</span>
                                                                                    <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                                  style="background: #3b82f614;">+84 kg</span>
                                                                            </div>
                                </div>
                                                                                            <div>
                                    <p class="flex items-center gap-1.5 text-[12px] font-black mb-2" style="color: #ec4899;">
                                        <i class="bi bi-gender-female"></i>Senior Women
                                    </p>
                                    <div class="flex flex-wrap gap-1.5">
                                                                                    <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                                  style="background: #ec489914;">-55 kg</span>
                                                                                    <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                                  style="background: #ec489914;">-61 kg</span>
                                                                                    <span class="px-2.5 py-1 rounded-lg text-[12px] font-bold text-foreground"
                                                  style="background: #ec489914;">-68 kg</span>
                                                                            </div>
                                </div>
                                                    </div>
                    </div>
                
                
                                    <div class="px-5 sm:px-6 py-4 text-white relative overflow-hidden"
     style="background: linear-gradient(135deg, #b3121f, #1f2937);">
    <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
    <div class="relative flex items-center gap-3">
        <i class="bi bi-clipboard-check text-2xl text-white/90 flex-shrink-0"></i>
        <div class="min-w-0">
                            <p class="text-[13px] font-black uppercase tracking-[0.16em] leading-none">Requirements</p>
                    </div>
    </div>
</div>
                    <div class="p-6">
                        <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2">
                                                            <li class="flex items-start gap-2.5 text-[13px] text-foreground/85 leading-snug">
                                    <i class="bi bi-check-circle-fill text-[13px] mt-0.5 flex-shrink-0" style="color: #b3121f;"></i>
                                    <span>Valid federation licence</span>
                                </li>
                                                            <li class="flex items-start gap-2.5 text-[13px] text-foreground/85 leading-snug">
                                    <i class="bi bi-check-circle-fill text-[13px] mt-0.5 flex-shrink-0" style="color: #b3121f;"></i>
                                    <span>Own gi (white) and mitts</span>
                                </li>
                                                            <li class="flex items-start gap-2.5 text-[13px] text-foreground/85 leading-snug">
                                    <i class="bi bi-check-circle-fill text-[13px] mt-0.5 flex-shrink-0" style="color: #b3121f;"></i>
                                    <span>Medical certificate dated within 12 months</span>
                                </li>
                                                            <li class="flex items-start gap-2.5 text-[13px] text-foreground/85 leading-snug">
                                    <i class="bi bi-check-circle-fill text-[13px] mt-0.5 flex-shrink-0" style="color: #b3121f;"></i>
                                    <span>Photo ID at the weigh-in desk</span>
                                </li>
                                                    </ul>
                    </div>
                
                
                
                
                <span id="where" class="block"></span>
                                    
                    <div class="px-5 sm:px-6 py-4 text-white relative overflow-hidden"
     style="background: linear-gradient(135deg, #b3121f, #1f2937);">
    <div class="absolute -right-8 -top-8 w-28 h-28 rounded-full bg-white/10"></div>
    <div class="relative flex items-center gap-3">
        <i class="bi bi-geo-alt-fill text-2xl text-white/90 flex-shrink-0"></i>
        <div class="min-w-0">
                            <p class="text-[13px] font-black uppercase tracking-[0.16em] leading-none">Location</p>
                    </div>
    </div>
</div>
                    <div class="px-6 py-5 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-black text-foreground leading-tight">Isa Sports City, Hall 2</p>
                        </div>
                                                    <a href="https://www.google.com/maps/dir/?api=1&amp;destination=Isa+Sports+City%2C+Hall+2" target="_blank" rel="noopener"
                               class="flex-shrink-0 px-3 py-2 rounded-xl text-white text-[12px] font-black flex items-center gap-1.5 hover:opacity-90 transition-opacity"
                               style="background: #b3121f;">
                                <i class="bi bi-cursor-fill"></i> Directions
                            </a>
                                            </div>
                            </div>
        </div>

        
        <aside class="space-y-4">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                <div class="grid grid-cols-3 gap-2 text-center">
                    <button type="button" @click="jump(['run-start', 'how-it-runs'])"
                            class="rounded-xl -m-1 p-1 hover:bg-muted/50 transition-colors"
                            aria-label="How the event runs">
                        <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-calendar3"></i></div>
                        <p class="text-xs font-bold text-foreground mt-1.5 truncate">Thu 01 Oct</p>
                        <p class="text-[10px] text-muted-foreground truncate">9:00 AM</p>
                    </button>
                    <button type="button" @click="jump('enter')"
                            class="border-x border-gray-100 hover:bg-muted/50 transition-colors py-1"
                            aria-label="To join">
                        <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-cash-coin"></i></div>
                        <p class="text-xs font-bold text-foreground mt-1.5 truncate">BHD 15</p>
                        <p class="text-[10px] text-muted-foreground truncate">To join</p>
                    </button>
                    <button type="button" @click="jump('where')"
                            class="rounded-xl -m-1 p-1 hover:bg-muted/50 transition-colors"
                            aria-label="Location">
                        <div class="w-10 h-10 mx-auto rounded-xl bg-accent text-primary grid place-items-center"><i class="bi bi-geo-alt"></i></div>
                        <p class="text-xs font-bold text-foreground mt-1.5 truncate" title="Isa Sports City, Hall 2">Isa Sports City, Hall 2</p>
                        <p class="text-[10px] text-muted-foreground">Venue</p>
                    </button>
                </div>

                
                                    <div class="mt-4">
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <span class="font-semibold text-foreground">0 going</span>
                            <span class="text-muted-foreground">120 spots left</span>
                        </div>
                        <div class="h-2 rounded-full bg-muted overflow-hidden">
                            <div class="h-full rounded-full"
                                 style="width: 0%; background: #b3121f;"></div>
                        </div>
                    </div>
                            </div>

            
            <span id="enter" class="block"></span>
                            <a href="https://stage.takeone.bh/e/46bccfc8-e6a8-494c-a58d-72320dd6aa58/enter"
                   class="block rounded-2xl p-5 text-center text-white shadow-lg hover:opacity-95 transition-opacity"
                   style="background: #b3121f; box-shadow: 0 18px 40px -20px #b3121f;">
                    <span class="text-[15px] font-black flex items-center justify-center gap-2">
                        <i class="bi bi-person-plus-fill"></i>Enter this competition
                    </span>
                    <span class="block text-[11.5px] text-white/80 mt-1">No account needed — you make one as you go</span>
                </a>
            
            
            <button type="button" @click="share()"
                    class="w-full bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-3 text-start hover:bg-muted/40 transition-colors">
                <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-accent text-primary">
                    <i class="bi bi-share-fill text-lg"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-foreground">Share this competition</span>
                    <span class="block text-[11px] text-muted-foreground mt-0.5">Send it to someone who should be here</span>
                </span>
                <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50 text-xs flex-shrink-0"></i>
            </button>
        </aside>
    </div>
</div>
    </main>

    
    <footer class="px-6 pb-[max(2rem,env(safe-area-inset-bottom))] pt-8 text-center">
        <div class="h-px mx-auto max-w-xs mb-6" style="background: linear-gradient(90deg, transparent, rgba(0,0,0,.10), transparent);"></div>
                    
            <span class="w-12 h-12 mx-auto block"><img src="https://stage.takeone.bh/file/clubs/BHR/al-hala-karate-club/branding/logo_1787132519.jpg" alt="" class="w-full h-full object-contain"></span>
                            <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-bold mt-2.5">Organised by</p>
            <p class="text-[12.5px] font-bold text-foreground/70 mt-0.5">Al Hala Karate Club</p>
            </footer>

    <script>
    /* The two behaviours the shared markup expects, and nothing else. Outside
       the app shell there are no shell helpers to borrow — but the quick-facts
       chips are doors to the sections beside them, and a chip that does nothing
       reads as broken. */
    function publicEvent() {
        return {
            jump(id) {
                const ids = Array.isArray(id) ? id : [id];
                for (const one of ids) {
                    const el = document.getElementById(one);
                    if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }
                }
            },
            share() {
                const data = { title: 'Gulf Open Karate Championship 2026', url: window.location.href };
                if (navigator.share) { navigator.share(data).catch(() => {}); return; }
                navigator.clipboard?.writeText(data.url).then(
                    () => notice('Link copied.'),
                    () => notice(data.url),
                );
            },
        };
    }

    /* window.showToast belongs to the app shell, which is not loaded here. */
    function notice(msg) {
        const n = document.createElement('div');
        n.textContent = msg;
        n.style.cssText = 'position:fixed;left:50%;transform:translateX(-50%);bottom:2rem;z-index:60;'
            + 'background:#111827;color:#fff;font-size:12.5px;padding:.85rem 1.25rem;border-radius:1rem;'
            + 'box-shadow:0 20px 40px -20px rgba(0,0,0,.5);transition:opacity .3s;opacity:0';
        document.body.appendChild(n);
        requestAnimationFrame(() => n.style.opacity = '1');
        setTimeout(() => { n.style.opacity = '0'; setTimeout(() => n.remove(), 320); }, 2600);
    }
</script>
</body>
</html>
```

---

## 3. Enrolment flow

`resources/views/entry/public/enrol.blade.php`. One file for both widths — it is
a single narrow column of questions at every size. Alpine drives the four steps;
`step` is 1–3 plus a confirmation, and only step 1 (a name) and step 3 (email +
password) can block. A birthdate and a weight are **never** demanded of anyone —
a blank sends the competitor to the weigh-in desk, which is the real authority.

```html
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="">
    <meta name="theme-color" content="#b3121f">
    <meta name="robots" content="index, follow">

    <title>Gulf Open Karate Championship 2026 · Al Hala Karate Club</title>
    <meta name="description" content="The Gulf&#039;s open karate championship, run over two days across four mats. Kumite and kata, cadet through senior, with the finals block on Sunday evening.

Entrie...">

    
    <link rel="icon" type="image/png" sizes="192x192" href="https://stage.takeone.bh/e/46bccfc8-e6a8-494c-a58d-72320dd6aa58/icon-192.png?v=d7adc9d2">
    <link rel="apple-touch-icon" href="https://stage.takeone.bh/e/46bccfc8-e6a8-494c-a58d-72320dd6aa58/icon-180.png?v=d7adc9d2">

    
    <!-- per-event web app manifest -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Gulf Open Karate C">
    <meta name="application-name" content="Gulf Open Karate C">

    
    <meta property="og:type" content="website">
    
    <meta property="og:site_name" content="Al Hala Karate Club">    <meta property="og:title" content="Gulf Open Karate Championship 2026">
    <meta property="og:description" content="The Gulf&#039;s open karate championship, run over two days across four mats. Kumite and kata, cadet through senior, with the finals block on Sunday evening.

Entries are made by clubs and by individual co...">
    <meta property="og:image" content="https://stage.takeone.bh/file/clubs/67/gallery/1517e572-b700-46e4-a909-8c52681b6bc8.jpg">    <meta property="og:url" content="https://stage.takeone.bh">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak]{display:none!important}</style>

    
    <!-- Tailwind 4 build (305 KB) — omitted; see "Design system" above --><!-- Tailwind 4 build (305 KB) — omitted; see "Design system" above -->
    <style>
        :root { --ev: #b3121f; }
        body { font-family: 'Inter', sans-serif; }

        /* The same rule the app shell applies, so a component that measures the
           viewport behaves identically here. */
        html { -webkit-text-size-adjust: 100%; }
    </style>
    <style>
    /* Fields on the app's own ground, focused in the EVENT's colour — the one
       place this flow is allowed to differ from a form inside the product, and
       only because the whole page is that event's. */
    .e-field {
        background: #fff;
        border: 1px solid hsl(210 14% 88%);
        color: hsl(220 20% 15%);
    }
    .e-field::placeholder { color: hsl(220 10% 60%); }
    .e-field:focus {
        outline: none;
        border-color: #b3121f;
        box-shadow: 0 0 0 4px #b3121f2e;
    }

    /* Selection cards — never a dropdown inside a scrolling column
       (Mobile Pattern Language). */
    .e-pick { border: 1.5px solid hsl(210 14% 88%); background: #fff; }
    .e-pick.is-on {
        border-color: #b3121f;
        background: #b3121f14;
        box-shadow: 0 0 0 4px #b3121f1f;
    }

    input[type=range].e-range { accent-color: #b3121f; }

    .e-seg { background: hsl(220 15% 90%); }
    .e-seg > i {
        display:block; height:100%; width:0; border-radius:inherit; background: #b3121f;
        transition: width .55s cubic-bezier(.22,.61,.36,1);
    }
    .e-seg.is-done > i, .e-seg.is-now > i { width:100%; }

    /* Each step is re-created on entry, so its fields travel with the direction
       of the move rather than the page appearing to swap. */
    @keyframes e-inR { from { opacity:0; transform: translate3d(26px,0,0) } to { opacity:1; transform:none } }
    @keyframes e-inL { from { opacity:0; transform: translate3d(-26px,0,0) } to { opacity:1; transform:none } }
    .e-inR { animation: e-inR .42s cubic-bezier(.22,.61,.36,1) both }
    .e-inL { animation: e-inL .42s cubic-bezier(.22,.61,.36,1) both }

    @media (prefers-reduced-motion: reduce) {
        .e-inR, .e-inL { animation: none }
        .e-seg > i { transition: none }
    }
</style>
</head>
<body class="bg-background text-foreground antialiased">

    
    <main class="mobile-stagger px-4 py-4 min-h-[60vh]">
        <div x-data="enrolFlow()" class="-mx-4 -mt-4">

    
    <header class="m-hero px-5 pt-5 pb-14 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, #b3121f, #b3121fb0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        
        <div class="flex items-center justify-between relative z-10">
            <button type="button" @click="back()"
                    class="m-press inline-flex items-center gap-2 h-10 ps-3 pe-4 rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold">
                <i class="bi bi-arrow-left rtl:rotate-180"></i>
                <span x-text="step > 1 ? 'Back' : 'Event'"></span>
            </button>
            <span class="text-[11px] font-black text-white/80 flex-shrink-0" x-show="step < 4" x-text="`${step} / 3`"></span>
        </div>

        <div class="relative z-10 mt-6">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                <i class="bi bi-person-plus-fill"></i> <span x-text="stepLabel"></span>
            </span>
            <h1 class="text-2xl font-black mt-3 leading-tight">Gulf Open Karate Championship 2026</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-building"></i>Al Hala Karate Club
            </p>
        </div>
    </header>

    <div class="mx-auto w-full max-w-lg px-4 -mt-8 relative z-10">

        <div class="grid grid-cols-3 gap-1.5 mb-4" x-show="step < 4">
            <template x-for="i in 3" :key="i">
                <span class="e-seg h-1.5 rounded-full overflow-hidden"
                      :class="{ 'is-done': step > i, 'is-now': step === i }"><i></i></span>
            </template>
        </div>

        
        <div class="pb-[max(7rem,calc(6rem+env(safe-area-inset-bottom)))]">
        <template x-if="step === 1">
            <div :class="dir === 1 ? 'e-inR' : 'e-inL'">
                <h2 class="text-[22px] font-black leading-tight text-foreground">Who is competing?</h2>
                <p class="text-[13px] text-muted-foreground mt-1">Your name as it should appear on the draw.</p>

                <div class="mobile-stagger space-y-3.5 mt-5">
                    <div>
                        <label class="block text-[12px] font-bold text-foreground mb-1.5">Full name</label>
                        <input type="text" autocomplete="name" x-model="form.full_name" maxlength="120"
                               class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                    </div>

                    <div>
                        <label class="block text-[12px] font-bold text-foreground mb-1.5">Date of birth</label>
                        <div class="m-card rounded-2xl p-1.5">
                            <div x-data="{
                dOpen: false, mOpen: false,
        day: '', month: '', year: '',
        months: [ 'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec' ]
            .map((s, i) => ({ v: String(i + 1).padStart(2, '0'), s })),

        get min() { return null },
        get max() { return '2026-09-01' },
        get minYear() { return this.min ? Number(this.min.slice(0, 4)) : new Date().getFullYear() - 100 },
        get maxYear() { return this.max ? Number(this.max.slice(0, 4)) : new Date().getFullYear() + 10 },
        get days() {
            const n = (this.year && this.month)
                ? new Date(Number(this.year), Number(this.month), 0).getDate() : 31;
            return Array.from({ length: n }, (_, i) => String(i + 1).padStart(2, '0'));
        },
        get monthLabel() { return this.months.find(m => m.v === this.month)?.s || '' },

        // Disable a part when EVERY date it could still form is out of range.
        dayOut(d)   { const iso = this.compose(d, this.month, this.year); return iso ? this.oob(iso) : false },
        monthOut(m) { if (!this.year) return false; const lo = `${this.year}-${m}-01`; const hi = `${this.year}-${m}-31`; return (this.max && lo > this.max) || (this.min && hi < this.min) },

        oob(iso) { return !!((this.min && iso < this.min) || (this.max && iso > this.max)) },
        compose(d, m, y) {
            if (!/^\d{4}$/.test(String(y)) || !m || !d) return '';
            if (Number(y) < this.minYear || Number(y) > this.maxYear) return '';
            // Reject an impossible day for the month (e.g. Feb 30) rather than let the
            // string form a date that silently wraps.
            const dim = new Date(Number(y), Number(m), 0).getDate();
            if (Number(d) < 1 || Number(d) > dim) return '';
            return `${y}-${m}-${String(d).padStart(2, '0')}`;
        },

        seed(v) {
            if (v && /^\d{4}-\d{2}-\d{2}$/.test(v)) { [this.year, this.month, this.day] = v.split('-'); }
            else { this.year = ''; this.month = ''; this.day = ''; }
        },
        push() {
            const iso = this.compose(this.day, this.month, this.year);
            const next = (iso && !this.oob(iso)) ? iso : '';
            if (form.birthdate !== next) { form.birthdate = next; }
        },
        init() {
            this.seed(form.birthdate);
            this.$watch('form.birthdate', v => { if (v !== this.compose(this.day, this.month, this.year)) this.seed(v); });
        },
     }"
     class="w-full">

    <div class="grid grid-cols-3 gap-2">
        
        <div class="relative" @click.outside="dOpen = false" @keydown.escape.stop="dOpen = false">
            <button type="button" @click="dOpen = !dOpen; mOpen = false"
                    class="w-full flex items-center justify-between gap-1 px-3 py-2.5 bg-white border rounded-xl text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent border-gray-200"
                    :class="dOpen ? 'ring-2 ring-primary border-transparent' : ''">
                <span class="flex items-center gap-2 truncate">
                    <i class="bi bi-calendar-day text-primary/40" :class="day && 'text-primary'"></i>
                    <span :class="day ? 'text-foreground font-medium' : 'text-muted-foreground'" x-text="day || 'Day'"></span>
                </span>
                <i class="bi bi-chevron-down text-xs text-gray-400 transition-transform" :class="dOpen && 'rotate-180'"></i>
            </button>
            <div x-show="dOpen" x-cloak x-transition.opacity
                 class="absolute z-30 mt-1 w-full max-h-52 overflow-y-auto bg-white border border-gray-100 rounded-xl shadow-lg p-1">
                <template x-for="d in days" :key="d">
                    <button type="button" :disabled="dayOut(d)" @click="day = d; dOpen = false; push()"
                            class="w-full text-start px-3 py-1.5 rounded-lg text-sm transition-colors"
                            :class="day === d ? 'bg-primary/5 font-semibold text-primary' : (dayOut(d) ? 'text-gray-300 cursor-not-allowed' : 'text-foreground hover:bg-muted/60')"
                            x-text="d"></button>
                </template>
            </div>
        </div>

        
        <div class="relative" @click.outside="mOpen = false" @keydown.escape.stop="mOpen = false">
            <button type="button" @click="mOpen = !mOpen; dOpen = false"
                    class="w-full flex items-center justify-between gap-1 px-3 py-2.5 bg-white border rounded-xl text-sm transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent border-gray-200"
                    :class="mOpen ? 'ring-2 ring-primary border-transparent' : ''">
                <span class="flex items-center gap-2 truncate">
                    <i class="bi bi-calendar-month text-primary/40" :class="month && 'text-primary'"></i>
                    <span :class="month ? 'text-foreground font-medium' : 'text-muted-foreground'" x-text="monthLabel || 'Month'"></span>
                </span>
                <i class="bi bi-chevron-down text-xs text-gray-400 transition-transform" :class="mOpen && 'rotate-180'"></i>
            </button>
            <div x-show="mOpen" x-cloak x-transition.opacity
                 class="absolute z-30 mt-1 w-full max-h-52 overflow-y-auto bg-white border border-gray-100 rounded-xl shadow-lg p-1">
                <template x-for="mo in months" :key="mo.v">
                    <button type="button" :disabled="monthOut(mo.v)" @click="month = mo.v; if (Number(day) > Number(days.at(-1))) day = days.at(-1); mOpen = false; push()"
                            class="w-full text-start px-3 py-1.5 rounded-lg text-sm transition-colors"
                            :class="month === mo.v ? 'bg-primary/5 font-semibold text-primary' : (monthOut(mo.v) ? 'text-gray-300 cursor-not-allowed' : 'text-foreground hover:bg-muted/60')"
                            x-text="mo.s"></button>
                </template>
            </div>
        </div>

        
        <div class="relative">
            <i class="bi bi-calendar-event absolute start-3 top-1/2 -translate-y-1/2 text-primary/40 pointer-events-none"
               :class="year ? 'text-primary' : ((day || month) && 'text-amber-500')"></i>
            <input type="text" inputmode="numeric" maxlength="4" placeholder="Year"
                   :value="year"
                   @input="year = $event.target.value.replace(/\D/g, '').slice(0, 4); push()"
                   class="w-full ps-9 pe-3 py-2.5 bg-white border rounded-xl text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent transition-colors"
                   :class="(day || month) && !year ? 'border-amber-400 ring-1 ring-amber-300 placeholder:text-amber-500' : 'border-gray-200'">
        </div>
    </div>
    <p x-show="(day || month) && !year" x-cloak class="mt-1.5 text-[11px] text-amber-600 flex items-center gap-1">
        <i class="bi bi-arrow-up"></i>Enter the year to finish
    </p>

    <input type="hidden"
                      :value="form.birthdate" >
    </div>
                        </div>
                        
                        <p x-show="!form.birthdate" x-transition.opacity
                           class="text-[11.5px] text-amber-300/90 mt-2 flex items-start gap-1.5">
                            <i class="bi bi-info-circle-fill mt-0.5"></i>
                            <span>Without it we cannot place you in an age division — the desk will ask on the day.</span>
                        </p>
                    </div>

                    <div>
                        <label class="block text-[12px] font-bold text-foreground mb-1.5">Gender</label>
                        <div class="grid grid-cols-2 gap-3">
    <button type="button" @click="form.gender = 'Male'"
            class="flex items-center justify-center gap-2 py-3 rounded-xl border-2 transition-all font-semibold text-sm"
            :class="form.gender === 'Male' ? 'border-blue-500 bg-blue-50 text-blue-600' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
        <i class="bi bi-gender-male"></i><span x-text="&#039;Male&#039;"></span>
    </button>
    <button type="button" @click="form.gender = 'Female'"
            class="flex items-center justify-center gap-2 py-3 rounded-xl border-2 transition-all font-semibold text-sm"
            :class="form.gender === 'Female' ? 'border-pink-500 bg-pink-50 text-pink-600' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
        <i class="bi bi-gender-female"></i><span x-text="&#039;Female&#039;"></span>
    </button>
</div>
                    </div>
                </div>
            </div>
        </template>

        
        <template x-if="step === 2">
            <div :class="dir === 1 ? 'e-inR' : 'e-inL'">
                <h2 class="text-[22px] font-black leading-tight text-foreground">What you compete at</h2>
                <p class="text-[13px] text-muted-foreground mt-1">This decides your division. The scale on the day is the final word.</p>

                <div class="mobile-stagger space-y-4 mt-5">
                    <div class="m-card rounded-2xl p-4">
                        <div class="flex items-center justify-between">
                            <label class="text-[12px] font-bold text-foreground">Weight</label>
                            <button type="button" @click="form.weight = null" x-show="form.weight"
                                    class="m-press text-[11px] font-bold text-muted-foreground">Clear</button>
                        </div>
                        <div class="flex items-center justify-center gap-5 mt-2">
                            <button type="button" @click="bump(-0.5)"
                                    class="m-press w-11 h-11 rounded-full bg-muted border border-gray-200 grid place-items-center text-lg text-foreground">
                                <i class="bi bi-dash-lg"></i>
                            </button>
                            <div class="text-center min-w-[7rem]">
                                <span class="text-[40px] font-black leading-none tabular-nums text-foreground"
                                      x-text="form.weight ? form.weight.toFixed(1) : '—'"></span>
                                <span class="text-[13px] font-bold text-muted-foreground ms-1">kg</span>
                            </div>
                            <button type="button" @click="bump(0.5)"
                                    class="m-press w-11 h-11 rounded-full bg-muted border border-gray-200 grid place-items-center text-lg text-foreground">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                        </div>
                        <input type="range" min="20" max="140" step="0.5" class="e-range w-full mt-3"
                               :value="form.weight ?? 60" @input="form.weight = Number($event.target.value)">
                        <p x-show="!form.weight" class="text-[11.5px] text-muted-foreground mt-2 text-center">
                            Skip it and you will be weighed in at the desk.
                        </p>
                    </div>

                    
                    <div>
                        <label class="block text-[12px] font-bold text-foreground mb-2">Belt / rank</label>
                        <div class="grid grid-cols-3 gap-2">
                                                            <button type="button" @click="form.belt = 'white'"
                                        class="m-press e-pick rounded-2xl py-3 flex flex-col items-center gap-1.5"
                                        :class="form.belt === 'white' && 'is-on'">
                                    <span class="w-8 h-2.5 rounded-full border border-white/25" style="background: #e5e7eb"></span>
                                    <span class="text-[11.5px] font-bold">White</span>
                                </button>
                                                            <button type="button" @click="form.belt = 'yellow'"
                                        class="m-press e-pick rounded-2xl py-3 flex flex-col items-center gap-1.5"
                                        :class="form.belt === 'yellow' && 'is-on'">
                                    <span class="w-8 h-2.5 rounded-full border border-white/25" style="background: #facc15"></span>
                                    <span class="text-[11.5px] font-bold">Yellow</span>
                                </button>
                                                            <button type="button" @click="form.belt = 'green'"
                                        class="m-press e-pick rounded-2xl py-3 flex flex-col items-center gap-1.5"
                                        :class="form.belt === 'green' && 'is-on'">
                                    <span class="w-8 h-2.5 rounded-full border border-white/25" style="background: #22c55e"></span>
                                    <span class="text-[11.5px] font-bold">Green</span>
                                </button>
                                                            <button type="button" @click="form.belt = 'blue'"
                                        class="m-press e-pick rounded-2xl py-3 flex flex-col items-center gap-1.5"
                                        :class="form.belt === 'blue' && 'is-on'">
                                    <span class="w-8 h-2.5 rounded-full border border-white/25" style="background: #3b82f6"></span>
                                    <span class="text-[11.5px] font-bold">Blue</span>
                                </button>
                                                            <button type="button" @click="form.belt = 'brown'"
                                        class="m-press e-pick rounded-2xl py-3 flex flex-col items-center gap-1.5"
                                        :class="form.belt === 'brown' && 'is-on'">
                                    <span class="w-8 h-2.5 rounded-full border border-white/25" style="background: #92400e"></span>
                                    <span class="text-[11.5px] font-bold">Brown</span>
                                </button>
                                                            <button type="button" @click="form.belt = 'black'"
                                        class="m-press e-pick rounded-2xl py-3 flex flex-col items-center gap-1.5"
                                        :class="form.belt === 'black' && 'is-on'">
                                    <span class="w-8 h-2.5 rounded-full border border-white/25" style="background: #111827"></span>
                                    <span class="text-[11.5px] font-bold">Black</span>
                                </button>
                                                    </div>
                    </div>

                    
                                            <div class="m-card rounded-2xl p-4">
                            <p class="text-[11px] uppercase tracking-wide text-muted-foreground font-bold mb-2.5 flex items-center gap-1.5">
                                <i class="bi bi-diagram-3 bracket-icon"></i>Divisions
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                                                    <span class="px-2.5 py-1 rounded-lg text-[11.5px] font-bold text-foreground" style="background: #b3121f14;">Cadet Men -52 kg</span>
                                                                    <span class="px-2.5 py-1 rounded-lg text-[11.5px] font-bold text-foreground" style="background: #b3121f14;">Cadet Men -57 kg</span>
                                                                    <span class="px-2.5 py-1 rounded-lg text-[11.5px] font-bold text-foreground" style="background: #b3121f14;">Cadet Men -63 kg</span>
                                                                    <span class="px-2.5 py-1 rounded-lg text-[11.5px] font-bold text-foreground" style="background: #b3121f14;">Cadet Women -47 kg</span>
                                                                    <span class="px-2.5 py-1 rounded-lg text-[11.5px] font-bold text-foreground" style="background: #b3121f14;">Cadet Women -54 kg</span>
                                                                    <span class="px-2.5 py-1 rounded-lg text-[11.5px] font-bold text-foreground" style="background: #b3121f14;">Senior Men -67 kg</span>
                                                                    <span class="px-2.5 py-1 rounded-lg text-[11.5px] font-bold text-foreground" style="background: #b3121f14;">Senior Men -75 kg</span>
                                                                    <span class="px-2.5 py-1 rounded-lg text-[11.5px] font-bold text-foreground" style="background: #b3121f14;">Senior Men -84 kg</span>
                                                                    <span class="px-2.5 py-1 rounded-lg text-[11.5px] font-bold text-foreground" style="background: #b3121f14;">Senior Men +84 kg</span>
                                                                    <span class="px-2.5 py-1 rounded-lg text-[11.5px] font-bold text-foreground" style="background: #b3121f14;">Senior Women -55 kg</span>
                                                                    <span class="px-2.5 py-1 rounded-lg text-[11.5px] font-bold text-foreground" style="background: #b3121f14;">Senior Women -61 kg</span>
                                                                    <span class="px-2.5 py-1 rounded-lg text-[11.5px] font-bold text-foreground" style="background: #b3121f14;">Senior Women -68 kg</span>
                                                            </div>
                        </div>
                                    </div>
            </div>
        </template>

        
        <template x-if="step === 3">
            <div :class="dir === 1 ? 'e-inR' : 'e-inL'">
                <h2 class="text-[22px] font-black leading-tight text-foreground">Keep your place</h2>
                <p class="text-[13px] text-muted-foreground mt-1">An account holds your entry, your draw and your results.</p>

                <div class="mobile-stagger space-y-3.5 mt-5">
                    <div>
                        <label class="block text-[12px] font-bold text-foreground mb-1.5">Email</label>
                        <input type="email" inputmode="email" autocomplete="email" x-model="form.email"
                               placeholder="you@example.com"
                               class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                    </div>
                    <div>
                        <label class="block text-[12px] font-bold text-foreground mb-1.5">Password</label>
                        <div class="relative">
                            <input :type="reveal ? 'text' : 'password'" autocomplete="new-password" x-model="form.password"
                                   placeholder="At least 8 characters"
                                   class="e-field w-full h-12 ps-4 pe-12 rounded-2xl text-[15px]">
                            <button type="button" @click="reveal = !reveal" :aria-label="'Password'"
                                    class="absolute end-3 top-1/2 -translate-y-1/2 text-muted-foreground">
                                <i class="bi" :class="reveal ? 'bi-eye-slash' : 'bi-eye'"></i>
                            </button>
                        </div>
                    </div>

                    <a href="https://stage.takeone.bh/login"
                       class="m-press w-full m-card rounded-2xl px-4 py-3.5 flex items-center gap-3 text-start">
                        <i class="bi bi-box-arrow-in-right text-lg" style="color: #b3121f"></i>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[13px] font-black text-foreground">I already have an account</span>
                            <span class="block text-[11.5px] text-muted-foreground">Sign in and this entry joins your profile</span>
                        </span>
                        <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50"></i>
                    </a>

                    
                    <div class="m-card rounded-2xl px-4 py-3 flex items-start gap-2.5">
                        <i class="bi bi-info-circle-fill mt-0.5" style="color: #b3121f"></i>
                        <p class="text-[12px] text-muted-foreground leading-snug">
                            The organiser confirms every entry.
                             Entry: <span class="font-bold text-white">BHD 15</span>                        </p>
                    </div>
                </div>
            </div>
        </template>

        
        <template x-if="step === 4">
            <div class="text-center pt-6">
                <div class="mx-auto w-24 h-24 rounded-full grid place-items-center border-2"
                     style="border-color: #b3121f; background: #b3121f3d">
                    <i class="bi text-4xl" :class="accepted ? 'bi-check-lg' : 'bi-send-check-fill'"></i>
                </div>

                <h2 class="text-[24px] font-black mt-5 text-foreground"
                    x-text="accepted ? 'You are in' : 'Sent to the organiser'"></h2>
                <p class="text-[13.5px] text-muted-foreground mt-1.5 px-2"
                   x-text="accepted ? 'Verify your email address to keep your place.' : 'They confirm entries themselves. You will be told either way \u2014 check your email to verify your address in the meantime.'"></p>

                <div class="mobile-stagger mt-6 space-y-2.5 text-start">
                    <div class="m-card rounded-2xl px-4 py-3.5 flex items-center gap-3" x-show="accepted">
                        <i class="bi bi-diagram-3 bracket-icon text-lg" style="color: #b3121f"></i>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] uppercase tracking-wide text-muted-foreground font-bold">Your division</p>
                            <p class="text-[14px] font-black text-foreground" x-text="division || 'Set at the weigh-in'"></p>
                        </div>
                    </div>
                    <div class="m-card rounded-2xl px-4 py-3.5 flex items-center gap-3">
                        <i class="bi bi-envelope-check-fill text-lg" style="color: #b3121f"></i>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] uppercase tracking-wide text-muted-foreground font-bold">Check your email</p>
                            <p class="text-[13px] font-bold text-foreground">We sent a link to confirm your address</p>
                        </div>
                    </div>
                    <div class="m-card rounded-2xl px-4 py-3.5 flex items-center gap-3">
                        <i class="bi bi-flag-fill text-lg" style="color: #b3121f"></i>
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] uppercase tracking-wide text-muted-foreground font-bold">Competing for</p>
                            <p class="text-[13px] font-bold text-foreground">No club — competing on your own</p>
                        </div>
                    </div>
                </div>

                <a href="https://stage.takeone.bh/login"
                   class="m-press mt-6 inline-flex items-center justify-center gap-2 h-12 px-6 rounded-2xl font-black text-[14px]"
                   style="background: #b3121f">
                    Sign in any time to follow your draw<i class="bi bi-arrow-right rtl:rotate-180"></i>
                </a>
            </div>
        </template>
        </div>
    </div>

    
    <div x-show="step < 4" x-cloak
         class="fixed inset-x-0 bottom-0 z-30 px-5 pt-3 bg-white border-t border-gray-100"
         style="padding-bottom: calc(0.9rem + env(safe-area-inset-bottom));">
        <div class="mx-auto w-full max-w-lg">
            <button type="button" @click="next()" :disabled="!canContinue || saving"
                    class="m-press w-full h-14 rounded-2xl font-black text-[15px] flex items-center justify-center gap-2"
                    :class="(canContinue && !saving) ? '' : 'opacity-40'"
                    :class="canContinue ? 'text-white' : 'text-muted-foreground'"
                    :style="canContinue ? `background: #b3121f; box-shadow: 0 18px 40px -18px #b3121f` : 'background: var(--color-muted, #eceef2)'">
                <span x-text="saving ? '…' : (step === 3 ? 'Send my entry' : 'Continue')"></span>
                <i class="bi bi-arrow-right rtl:rotate-180" x-show="!saving"></i>
            </button>
        </div>
    </div>
</div>
    </main>

    
    <footer class="px-6 pb-[max(2rem,env(safe-area-inset-bottom))] pt-8 text-center">
        <div class="h-px mx-auto max-w-xs mb-6" style="background: linear-gradient(90deg, transparent, rgba(0,0,0,.10), transparent);"></div>
                    
            <span class="w-12 h-12 mx-auto block"><img src="https://stage.takeone.bh/file/clubs/BHR/al-hala-karate-club/branding/logo_1787132519.jpg" alt="" class="w-full h-full object-contain"></span>
                            <p class="text-[10px] uppercase tracking-wide text-muted-foreground font-bold mt-2.5">Organised by</p>
            <p class="text-[12.5px] font-bold text-foreground/70 mt-0.5">Al Hala Karate Club</p>
            </footer>

    <script>
function enrolFlow() {
    return {
        step: 1,
        dir: 1,
        reveal: false,
        saving: false,
        accepted: false,
        division: '',
        form: { full_name: '', birthdate: '', gender: '', weight: null, belt: '', email: '', password: '' },

        get stepLabel() {
            return ['', 'You', 'Division',
                    'Account', 'Done'][this.step] || '';
        },

        /* Only two things can block: a name, because a competitor without one
           breaks every listing and every draw; and the credentials, because
           without them there is nobody to hold the place. A birthdate and a
           weight are never demanded of anyone — a blank sends them to the
           weigh-in desk, which is the authority anyway. */
        get canContinue() {
            if (this.step === 1) return this.form.full_name.trim().length >= 2;
            if (this.step === 3) return /^\S+@\S+\.\S+$/.test(this.form.email) && this.form.password.length >= 8;
            return true;
        },

        bump(by) {
            const v = (this.form.weight ?? 60) + by;
            this.form.weight = Math.min(140, Math.max(20, Math.round(v * 2) / 2));
        },

        async next() {
            if (!this.canContinue || this.saving) return;
            if (this.step === 3) { await this.submit(); return; }

            this.dir = 1;
            this.step = Math.min(4, this.step + 1);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        back() {
            if (this.step >= 4) return;                          // it is sent; there is nothing behind it
            // Back OUT of the form goes to the event, by address — history.back()
            // lands on whatever the browser happens to remember, which for a
            // link opened from WhatsApp is nothing at all.
            if (this.step === 1) { window.location.href = 'https:\/\/stage.takeone.bh\/e\/46bccfc8-e6a8-494c-a58d-72320dd6aa58'; return; }
            this.dir = -1;
            this.step -= 1;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        async submit() {
            this.saving = true;
            try {
                const res = await fetch('https:\/\/stage.takeone.bh\/e\/46bccfc8-e6a8-494c-a58d-72320dd6aa58\/enter', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        full_name: this.form.full_name,
                        email: this.form.email,
                        password: this.form.password,
                        birthdate: this.form.birthdate || null,
                        gender: this.form.gender || null,
                        weight: this.form.weight,
                        belt: this.form.belt || null,
                    }),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || !d.success) throw new Error(d.message || 'Entries are closed for this competition.');

                this.accepted = d.state === 'accepted';
                this.division = d.division || '';
                this.dir = 1;
                this.step = 4;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (e) {
                notice(e.message);
            } finally {
                this.saving = false;
            }
        },
    };
}

/* Outside the app shell, so window.showToast does not exist here. One small
   on-palette notice instead of a native dialog. */
function notice(msg) {
    const n = document.createElement('div');
    n.textContent = msg;
    n.style.cssText = 'position:fixed;left:1rem;right:1rem;bottom:calc(6rem + env(safe-area-inset-bottom));z-index:60;'
        + 'background:#111827;color:#fff;font-size:12.5px;line-height:1.4;padding:.85rem 1rem;border-radius:1rem;'
        + 'box-shadow:0 20px 40px -20px rgba(0,0,0,.7);transition:opacity .3s,transform .3s;transform:translateY(8px);opacity:0';
    document.body.appendChild(n);
    requestAnimationFrame(() => { n.style.opacity = '1'; n.style.transform = 'none'; });
    setTimeout(() => { n.style.opacity = '0'; n.style.transform = 'translateY(8px)'; setTimeout(() => n.remove(), 320); }, 3600);
}
</script>
</body>
</html>
```

---

## Where a change lands

| Part of the page | File |
|---|---|
| Shell: head, manifest, white-label, footer | `resources/views/entry/layout.blade.php` |
| Public page, mobile / desktop | `resources/views/entry/public/{mobile,desktop}.blade.php` |
| Enrolment flow | `resources/views/entry/public/enrol.blade.php` |
| **The detail card (shared with the member page)** | `resources/views/partials/event-detail-card-{mobile,desktop}.blade.php` |
| What a stranger may be shown at all | `app/Events/Support/PublicEvent.php` |
| The app icon + manifest | `app/Events/Support/PublicBrand.php` |

A **new field** on the page has to be added to `PublicEvent.php` first. That
class is the gate that keeps competitor names off a public URL, and a template
cannot render what it will not produce.
