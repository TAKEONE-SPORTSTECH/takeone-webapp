# Karate Tournament — package screens

Blade views placed in this folder are registered automatically under the
`event-karate_tournament::` namespace by `App\Events\EventPackageServiceProvider`
(no path is wired by hand anywhere).

```blade
{{-- app/Events/Sports/Karate/Tournament/resources/views/mobile/show.blade.php --}}
→ view('event-karate_tournament::mobile.show')
```

Declare a screen from the package's `views()` method to take it over:

```php
public function views(): array
{
    return [
        'show' => [
            'mobile'  => 'event-karate_tournament::mobile.show',
            'desktop' => 'event-karate_tournament::desktop.show',
        ],
        'run' => [
            'mobile'  => 'event-karate_tournament::mobile.bracket',
            'desktop' => 'event-karate_tournament::desktop.bracket',
        ],
    ];
}
```

A declared view is used **only when it exists** (`PersonalEventController::packageView()`),
so screens can be taken over one at a time — and one device at a time — without
ever pointing the app at a view that hasn't shipped.

## Status — Phase 2, not started

The championship currently renders through the shared screens
(`resources/views/personal/{mobile,desktop}/event-show.blade.php` and
`resources/views/personal/event-bracket.blade.php`), driven entirely by data this
package returns from `viewData()`, `rosterRows()`, `results()` and `timeline()`.

Note when moving the bracket screen in: `personal/event-bracket.blade.php` is
**currently shared** — a non-combat event with divisions (e.g. a race) links to it
too. Either give that type its own run screen first, or keep a generic fallback,
so moving this one doesn't strip the bracket page from those events.

Screens must follow the Design System, the Mobile Pattern Language, and the
mobile/desktop split (`CLAUDE.md`).
