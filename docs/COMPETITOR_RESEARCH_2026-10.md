# Competitor Research — TakeOne vs the market

**Date:** 31 Oct 2026 · **Method:** our own capabilities verified in code (not docs); competitors from their own sites, pricing APIs and support articles. Anything marked UNVERIFIED could not be confirmed from a first-party source.

---

## 1. The headline

**Smoothcomp is no longer just a tournament tool.** It was acquired by **Gymdesk** (US gym-management SaaS, backed by Five Elms Capital) — announced 6 Oct 2026. One company now sells all three things TakeOne sells: **competitions, federations, and club management.** The earlier summary we had (organizer credits only) was out of date.

**Where we honestly stand:** in the Gulf, on Arabic and on "one person record across club → federation → event", we are ahead of everyone found. On payments, sport breadth, bracket formats, federation licensing, iOS and track record, Smoothcomp+Gymdesk is clearly ahead.

---

## 2. Smoothcomp + Gymdesk — full profile

Sources: live pricing JSON `smoothcomp.com/en/pricing/federation/plans/pricing`, the site's public JS bundle, support.smoothcomp.com, gymdesk.com.

### Product lines
- **Organizer (Starter)** — registration, scheduling, brackets, payments, check-in/weigh-in, scorekeeping, results, top lists.
- **Federation Platform (Basic / Premium)** — `name.smoothcomp.com` subdomain, memberships, rankings, CMS. ([support](https://support.smoothcomp.com/article/178-how-to-create-your-federation-company-platform))
- **Gym management (Gymdesk)** — memberships with ranks/programs, family accounts, automatic billing with failed-payment recovery, kiosk/barcode/phone check-in, class booking, POS, member app, smart-lock door access, email/SMS automations, lead management, website builder, reports. ([gymdesk.com/pricing](https://gymdesk.com/pricing))
- **Smoothcomp Connect** — links the gym to events: event discovery, coach "Academy Approved" endorsements, pre-filled registration, chasing unpaid/unregistered members, results synced to member profiles. **Batch-synced, not live.** ([gymdesk.com/smoothcomp-connect](https://gymdesk.com/smoothcomp-connect))
- **Coach Companion App** — free, iOS/Android: today's matches in run order, coach assignment, flags athletes with no coach.
- **Athlete app** — free; "Smoothcomp Pro" $24.99 in-app (live countdown on lock screen, notifications). iOS rating **2.9★ (35 ratings)**.
- **Smoothcomp TV / Smoothstreamer** — livestream + pay-per-view, scoreboard overlays, "Cut & connect" auto-splits the stream into per-match clips linked to bracket and athlete.

### Pricing
| Product | Price |
|---|---|
| Organizer | €0/month + **€12.50** one-time; credits **€1.25** pay-as-you-go, **€0.90** (1,000 pack), **€0.60** (5,000 pack). 1 credit = 1 athlete per event regardless of divisions; credit kept if athlete drops. |
| Federation Basic | **€29/mo** (€20/mo yearly) — exclusive subdomain listing, affiliate organizers, event templates, academy memberships. Credits still charged on top. |
| Federation Premium | **€129/mo** (€90/mo yearly) — adds athlete memberships, rankings, CMS, custom membership plans, custom member fields + document upload. |
| Gymdesk (clubs) | ≤50 members $100 ($75 with Gymdesk Payments) · ≤100 $125/$100 · ≤150 $175/$150 · ≤250 $240/$200 · 251+ custom. Extra location $50–100, door access $100, branded app $100/mo. Gymdesk Payments = US/Canada only. |
| Pay-per-view | Organizer **60%** / Smoothcomp 40%, no upfront cost, payout via PayPal. |
| Athlete fees | Smoothcomp says it takes no percentage on registration payments. |

### Competition depth
- **27 rule sets:** BJJ, Judo, Karate (generic/WKF/AAU/Kyokushin), Thai boxing, Wrestling (+Folkstyle), UWW Grappling, JJIF Fighting/Ne-waza, Taekwondo WT **and ITF**, Boxing, MMA, Kickboxing (+WAKO), Sambo, Wushu Sanda/Taolu, Kudo, Sumo, Submission-only, and more.
- **Brackets:** single elim ± bronze match; **double elimination** (wrestleback, loser-to-final, 2-match guarantee, true double); round robin; 3-player comeback; **best-of-3**; multistage pools; **judges' voting** (forms/kata); placement brackets. ([support](https://support.smoothcomp.com/category/228-our-different-bracket-types))
- QR/barcode check-in, **smart-scale weigh-in**, drag-and-drop scheduling with live time estimates, tablet scoring → TV, **coupons, early/late fees, refunds, waivers**, team competitions.
- Rankings with seasons, offset points, **points decay**, **seeding brackets from rankings**; by athlete, academy, nation, US state.

### Federation depth
- Academy memberships (Basic) and athlete memberships (Premium); federation sets fees and renewal; **event entry gated on active membership**; printable credentials. ([support](https://support.smoothcomp.com/article/183-control-who-particpates-in-your-events-membership))
- Affiliate organizers via organization code; master statistics export; iframe embeds. Insurance: UNVERIFIED.

### Payments & region
- Stripe, PayPal, Authorize.net, Safe2Pay, Paystack, Mercado Pago, PagSeguro; money goes straight to the organizer's account.
- **No BHD, Benefit, Tap, PayTabs or Telr found.** Stripe payout bank must be EUR/USD/GBP. AJP UAE events priced in USD.
- Site in 18 languages incl. Arabic, but the Arabic page has **no `dir="rtl"`** — real RTL doubtful. iOS app English only.

### Scale & customers
- ~150 events per average weekend, 200+ peak; ~17 staff.
- **ADCC, NAGA, Grappling Industries, UFC BJJ, AJP (ex-UAEJJF — ajptour.com and events.uaejjf.org run on it)**, USA Judo, USJF; UWW signed an MoU to evaluate it for grappling/pankration/amateur MMA.

### Weaknesses
- Athlete app 2.9★: poor filtering/search, login failures, paywall resentment.
- Athletes are sent to the organizer for support, not to Smoothcomp.
- Connect is batch-synced; Gymdesk Payments US/CA only.
- Thin Middle East fit: no local gateway/currency, doubtful RTL.

---

## 3. Other competition & federation players

| Product | Covers | Sports | Pricing | Notable | Arabic |
|---|---|---|---|---|---|
| **SimplyCompete** | Federation membership, events, accreditation, rankings | Taekwondo mainly | Contract, not published | **World Taekwondo's official GMS** (524 events, 250k registrants); Jordan, Kazakhstan, Canada, Kenya | UNVERIFIED |
| **Sportdata (SET)** | Entries, draws, scoreboards, results, rankings | Karate (**WKF**), TKD, kickboxing (WAKO), boxing | SET licence ~€164/yr or ~€439/3 yrs; event platform, app, video review licensed separately | WKF Premier League & Series A, WAKO | UNVERIFIED |
| **JudoManager** | Draw, offline desktop app + cloud | Judo | ~€2/entry (UNVERIFIED) | **IJF**, Paris 2024, Worlds Abu Dhabi | UNVERIFIED |
| **UWW Arena / Athena** | Scoring/results; licences & entries | Wrestling | Free to national federations; licences CHF 0–100 | All UWW events | UNVERIFIED |
| **TrackWrestling / FloArena** | Brackets, scoring, streaming | Wrestling (US) | Bundled with FloSports | USA Wrestling | No |
| **MartialMatch** | Registration, brackets, scoreboards | BJJ, judo, wrestling, kickboxing, TKD | **€0.70 per registration** | Central/Eastern Europe | **No** |
| **Kihapp** | Registration, draws, scoring, stream graphics | Karate, TKD | **5% of gross entry fees** | Small European opens | UNVERIFIED |
| **JustGo** | Governing-body/club membership, licensing | Multi-sport | Tiered by member count, quote | 110+ governing bodies | UNVERIFIED |
| **Sport:80** | Membership, licences, events, finance | Multi-sport | % of every transaction | British Fencing, USA Climbing | UNVERIFIED |
| **SportsEngine HQ** | Registration & payments | US youth sports | ~$69–129/mo + 3.25% + $2 per transaction | USA Volleyball | No |
| Others | Fitofan $1–3/athlete; NinjaPanel $2–5/athlete; Taikai $0.25–1; Tournify €40–120/tournament | | | | |

**Key point:** the international federations mandate their own platforms (WT → SimplyCompete, WKF → Sportdata, IJF → JudoManager). TakeOne cannot replace them at international ranking events — but **national and club events in the GCC are open.**

### GCC / Middle East
- **UAEJJF / AJP** — run on Smoothcomp (see above). Strongest regional benchmark.
- **Bahrain Olympic Committee** — National Sports Database (2024): athletes, coaches, officials registry. No competitions or entries.
- **UAE** — Ministry of Sports "Sportifai" and a UAE NOC platform: governance/data, not competition (UNVERIFIED).
- **Saudi** — OpenTrack runs Saudi Athletics; Arabic "being added". Nothing found for Saudi karate/TKD.
- **No Arabic-first combat-sports competition + federation vendor was found anywhere.**

---

## 4. Club-management players

| Product | Pricing (monthly) | Txn fees | Belts | App | Arabic / GCC pay |
|---|---|---|---|---|---|
| **Gymdesk** | $75–240 by members | not published | yes | +$100 branded | none |
| **Kicksite** | $49–199 by students | flat, unpublished | yes | — | none |
| **Zen Planner** | $99 / $198 / $348 | "may apply" | yes | +$39 | none |
| **PushPress** | $0 / $159 / $229 | 4.99% → 2.75% + $0.30 | no | +$97 | none |
| **Spark Membership** | $199 | — | yes | included | none |
| **Clubworx** | AUD 0–169 | Ezidebit | yes (gradings +$10) | +$100–200 | none |
| **Mindbody** | from $79, quote | rate + marketplace 20% of first purchase | no | add-on | none |
| **TeamUp** | $189 (101–200) | — | no | +£79 | none |
| **Spond** | free core | ~2.5% + fixed | no | yes | none |
| **Rekaz (KSA)** | SAR 99 / 299 / 499; app +9,200 SAR/yr | — | no | add-on | **Arabic, mada, Apple Pay, Tabby, Tamara** |
| **Tamarran (GCC)** | quote via WhatsApp | — | no | yes | **Arabic, KSA/BH/QA/AE/KW/OM** |
| **Playtomic** | commission per booking | ~10% (UNVERIFIED) | no | big player network | strong in UAE |

- Western martial-arts tools: belts are table stakes, member app is the usual upsell, pricing by active members — **none publishes Arabic or BenefitPay/mada.**
- GCC tools have Arabic + local payment rails but **no belts, no competitions, no federations.**
- **No club software confirmed native BenefitPay.** Bahrain clubs fall back to manual proof of payment — as TakeOne does.

---

## 5. TakeOne — what we actually offer (verified in code)

Status: **LIVE** · **FLAG** (behind a switch) · **PARTIAL** · **ABSENT**.

### Clubs
- Packages + class timetables, expiry reminders — LIVE
- Billing — **PARTIAL: manual proof-of-payment only**, no gateway, no recurring charge
- Attendance — LIVE (coach marks in class window); kiosk/self check-in ABSENT
- Schedule with cancellations, substitutes, make-ups, ratings — LIVE; per-class booking/capacity ABSENT
- Trainers, reviews, public trainer pages — LIVE
- Shop with variants, stock, perks — LIVE; **POS ABSENT**
- Member app — **PARTIAL: Android WebView only, debug-signed, not on any store; no iOS**
- Comms — in-app, email, chat LIVE; WhatsApp settings only; **SMS and native push ABSENT**
- Financial ledger + analytics — LIVE; export ABSENT
- Family accounts — LIVE · Belts/grading — LIVE (RANKS_ENABLED)

### Federations (FEDERATIONS_ENABLED)
- Club affiliation, approval, athlete registry (import/export/merge), transfers — LIVE
- **Licensing and athlete/club membership fees — ABSENT** ("still to be decided")
- Rankings — LIVE, **WKF formula only**
- Federation events, invitations, club entry, results PDF, competition report — LIVE
- Analytics/statistics — LIVE
- Branding — PARTIAL; **subdomain, public site, CMS/news ABSENT**

### Competitions
- Event creation, public + club entry — LIVE
- Fees: base, add-ons, late penalty — PARTIAL; **early-bird, discounts, coupons ABSENT**; refunds manual
- Auto-classification (Karate, TKD), auto-place, import, merge — LIVE
- Weigh-in + payment desk — LIVE; separate check-in ABSENT
- Brackets: single elim, groups → knockout, both-semi-loser or 3rd-place bout — LIVE. **Double elimination and true repechage ABSENT.**
- Fair draw (belt seeding + club separation), tablet draw, draw reveal — LIVE
- Running order, mats, live moves — LIVE
- Scoreboards — LIVE for **Karate, Taekwondo, BJJ only**; **kata/poomsae judge scoring ABSENT**
- Hall screens (TV app + web) — LIVE · live public brackets/results — LIVE
- Athlete calls — in-app only (guardian copied), no OS push
- Podium, QR-verified certificates, officials' badges — LIVE
- Video — bouts recorded, HLS, scoreboard timeline, share to WhatsApp — LIVE; **live streaming removed**
- PDFs (brackets, participants, officials, forms), bout stats — LIVE
- League, Sparring, Open Mat event types — LIVE
- Event budget with AI advisor — LIVE
- **32 "recipe" sports (Judo, Wrestling, Padel…): the engine is behind EVENT_SPORT_CHAMPIONSHIPS = OFF everywhere** — today they get listing + registration only, no brackets, no scoreboard.

### Platform
- 12 UI languages, AI translation of interface and event content — LIVE
- 8 Android APKs (member, TV, tablet, camera, …) — debug-signed
- MCP server, 82 tools — LIVE; **public REST API ABSENT**
- AI club Copilot (web search, speech) — LIVE
- Offline mode — ABSENT

---

## 6. Head to head — Smoothcomp + Gymdesk vs TakeOne

| | Smoothcomp + Gymdesk | TakeOne |
|---|---|---|
| Competitions | €1.25/athlete (€0.60 bulk) | price not set |
| Federations | €20–90/mo + credits | price not set |
| Clubs | $75–240/mo | price not set |
| Sports with real scoring | 27 rule sets | **3** |
| Bracket formats | double elim ×4, repechage, RR, pools, Bo3, judges | single elim, groups→KO |
| Online payments | Stripe/PayPal + coupons, early/late, refunds | **manual only** |
| Federation licensing | yes, gates entry | **absent** |
| Branded subdomain + CMS | yes | absent |
| Rankings | seasons, decay, seeding | WKF only |
| Livestream / PPV | yes, 60/40 | removed |
| Per-bout recorded video | auto-cut from stream | **yes, with officiating timeline** |
| Apps | iOS + Android athlete & coach | Android only, unsigned |
| Kata/poomsae | judges' voting | absent |
| Arabic / RTL | Arabic text, no RTL | **full Arabic RTL** |
| GCC payments | none | manual (BenefitPay/IBAN shown) |
| One person record across club→federation→event | batch sync | **native, single record** |
| Hall screens, AI budget, badges, QR certificates | not found | **yes** |
| Track record | ~150 events/weekend; ADCC, AJP, USA Judo | a handful of events |

---

## 7. What it means

**Where we win:** the Gulf. Arabic done properly, one record per person from club to federation to event, and things nobody else bundles (hall screens, bout video with officiating timeline, AI budget, certificates, badges). National and club-level events in Bahrain/GCC are open ground; international ranking events are not (WT/WKF/IJF mandate their own vendors).

**Where we lose deals today, in priority order:**
1. **Payments** — manual proof for 300 athletes is a full-time job. A GCC gateway (BenefitPay / Tap / mada) would be a real differentiator, since nobody else has one either.
2. **Reliability / track record** — one bad championship day ends the sale.
3. **Federation licensing** — membership fees and gating entry on an active licence are what federations pay Smoothcomp Premium for.
4. **Sport breadth** — Judo and Wrestling are recipe-only; the championship engine is switched off.
5. **Bracket formats** — double elimination, true repechage, kata/poomsae judges' scoring.
6. **iOS + store-signed apps.**
7. Smaller: coupons/early-bird, SMS/push, POS, subdomain/CMS, livestream.

**Pitch:** "The Gulf's club-and-federation platform with competitions, video and Arabic built in" — not "a cheaper Smoothcomp". At €1.25/athlete they are already cheap.

---

## 8. Found during research — security

`GET /bills/{id}/pay` and `GET /bills/pay-all` (`app/Members/Controllers/InvoiceController.php:122`) let a logged-in member mark their own invoices **paid** with no payment, and change state on a GET. Nothing creates those invoices today, so risk is low, but the routes are live. **Not yet fixed.**
