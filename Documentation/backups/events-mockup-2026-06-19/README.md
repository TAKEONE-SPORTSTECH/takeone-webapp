# `/me/events` mockup — backup snapshot (2026-06-19)

A verbatim copy of the **mobile Events** mockup (dummy/demo content) as it stood on
2026-06-19, kept here so it can be restored if later work breaks it.

> This is the **dummy/demo** version of Events — curated sample data rendered
> straight from the controller (no database). It is the starting point before
> Events is wired to real `ClubEvent` data.

## What's in this folder

| File | Restores to | Notes |
|------|-------------|-------|
| `events.blade.php` | `resources/views/personal/events.blade.php` | Events hub: hero, featured spotlight, filter, event cards with fee/ticket pills |
| `event-show.blade.php` | `resources/views/personal/event-show.blade.php` | Event detail: cover, pricing & tickets, participants, requirements, prize/divisions, tournament timeline, brackets entry, schedule |
| `event-bracket.blade.php` | `resources/views/personal/event-bracket.blade.php` | Tournament brackets: per weight category — rounds/matches, podium, enrolling roster + open slots |
| `PersonalMobileController.events-methods.php` | methods inside `app/Http/Controllers/PersonalMobileController.php` | `events()`, `eventShow()`, `eventBracket()`, `demoEvents()`, `demoTkdCategories()` (wrapped in a throwaway class here for valid PHP) |

## Routes these rely on (already in `routes/web.php`, `me.` group)

```php
Route::get('/events',                  [PersonalMobileController::class, 'events'])->name('events');
Route::get('/events/{event}',          [PersonalMobileController::class, 'eventShow'])->name('events.show')->whereNumber('event');
Route::get('/events/{event}/brackets', [PersonalMobileController::class, 'eventBracket'])->name('events.bracket')->whereNumber('event');
```

## How to restore

1. **Views** — copy the three `*.blade.php` files back over their originals:
   ```bash
   cp Documentation/backups/events-mockup-2026-06-19/events.blade.php        resources/views/personal/events.blade.php
   cp Documentation/backups/events-mockup-2026-06-19/event-show.blade.php    resources/views/personal/event-show.blade.php
   cp Documentation/backups/events-mockup-2026-06-19/event-bracket.blade.php resources/views/personal/event-bracket.blade.php
   ```
2. **Controller** — open `PersonalMobileController.events-methods.php`, copy the five
   methods (ignore the wrapping `class EventsMockupSnapshot {}`) and paste them back
   into `app/Http/Controllers/PersonalMobileController.php`, replacing the current
   `events()` / `eventShow()` / `eventBracket()` / `demoEvents()` / `demoTkdCategories()`.
3. Clear caches: `php artisan view:clear`.

## Demo content summary (so you know what "correct" looks like)

- **7 events** in `demoEvents()`: Summer Sprint Cup (competition, free), Karate Belt
  Grading (paid BHD 10 + free spectators), Strength & Conditioning (free class),
  Club Boxing Championship (paid BHD 15 + BHD 5 ticket), Open Padel Tournament
  (BHD 20/team), Grand Championship Finals Night (qualification-only + BHD 8 ticket),
  **World Taekwondo Championship** (paid BHD 25 + BHD 10 ticket, lifecycle phases,
  brackets).
- **3 weight categories** in `demoTkdCategories()`: Men −58 kg (live bracket),
  Men −68 kg (completed + podium/prizes), Women −49 kg (enrolling roster + open slots).
