# karate-shotukan-weightclasses

The federation's **Karate (Shotokan) age categories and kumite weight classes**, season 2025-2026 — one table, used everywhere.

| File | What it is |
|---|---|
| `weightclasses.json` | **The table — the only place the numbers live.** Age bands (`ageCategories`, by maximum age) and weight classes per gender (`weights.M` / `weights.F`). |
| `karate-shotukan-weightclasses.js` | The page library → `window.KarateShotukanWeightclasses`. Carries no copy of the table. |
| `app/Support/KarateShotukanWeightclasses.php` | The same rules for PHP, reading the same JSON. |
| `resources/views/components/karate-shotukan-weightclasses.blade.php` | `<x-karate-shotukan-weightclasses />` — include the library on any Blade page. |

## Rules
- Age category: the first band whose `maxAge` is ≥ the athlete's age (whole years); below `minAge` (4) there is none.
- A `-X` class holds weights **up to and including** X; anything above the last `-X` is the `+` class.
- Kata is not weight-classed — this table is for kumite.

## Using it
**Blade page:** put `<x-karate-shotukan-weightclasses />` in the page, then in JS:
```js
KarateShotukanWeightclasses.classify({gender: 'Male', dob: '2011-05-01', weight: 52})
// {ageGroup: 'Cadets', weightClass: '-52', label: 'Kumite -52 kg'}
KarateShotukanWeightclasses.ageCategoryOf(14)            // 'Cadets'
KarateShotukanWeightclasses.weights('F', 'Juniors')      // ['-48','-53','-59','-66','+66']
KarateShotukanWeightclasses.label('F', 'Juniors', 55)    // 'Kumite -59 kg'
```
**Plain HTML page:** `<script src="/libraries/karate-shotukan-weightclasses/karate-shotukan-weightclasses.js"></script>`, then `await KarateShotukanWeightclasses.ready()` before calling it (it fetches the JSON).

**PHP:**
```php
use App\Support\KarateShotukanWeightclasses as KSW;
KSW::classify('Female', '2012-03-04', 41);   // ['ageGroup' => 'Cadets', 'weightClass' => '-47', 'label' => 'Kumite -47 kg']  (on 26 Sep 2026)
KSW::ageCategoryOf(16);                        // 'Juniors'
```

## Changing a class or a band
Edit `weightclasses.json` only. Every page and every PHP call picks it up; the script URL is versioned by the file's date, so browsers fetch the new table.

## Used by
- Federation dashboard (`app/Federations/resources/app/Events Dashboard.dc.html`): registrations, divisions, draw filters.
- Club invitation page (`Club Invitation.dc.html`): the category shown as an athlete is added.

Not the platform's own karate events — they use the WKF table in `app/Events/config/karate_divisions.php`, a different rulebook.
