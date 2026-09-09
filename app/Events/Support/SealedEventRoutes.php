<?php

namespace App\Events\Support;

use App\Http\Middleware\SealEventPage;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * The management screens, served a second time INSIDE the event.
 *
 * The public event page is a standalone app (see PublicEventSkin). The
 * organiser who signs in through it has to be able to run the competition
 * without ever being handed to the platform — which means every screen under
 * `/me/events/{uuid}/…` needs an address under `/e/{uuid}/admin/…`.
 *
 * Those screens are MIRRORED, never copied. Each mirrored route reuses:
 *   · the same controller action,
 *   · the same middleware stack, in the same order, with SealEventPage added,
 *   · the same parameter constraints and binding fields.
 *
 * So there is one implementation of the event console, one of the entry list,
 * one of the draw — and adding a route to the platform space puts it in the
 * sealed space too, with nothing to remember. A hand-written mirror of sixty
 * routes would have started drifting the same afternoon (Shared Stays Shared).
 *
 * ⚠️ This widens no permission. `SealEventPage` runs INSIDE the same stack the
 * platform route carries, plus one gate of its own: the event's public page has
 * to be switched on, or the mirror 404s.
 *
 * Registered from AppServiceProvider, on the router's `Routing` event — the
 * moment before it matches. NOT on `booted()`: ModuleServiceProvider loads the
 * module route files in a `booted()` callback of its own, and two callbacks in
 * one queue is a race this lost under php-fpm while winning it in the console —
 * so the mirror was there in every test and in none of the browser's requests.
 */
class SealedEventRoutes
{
    /** The platform space that gets mirrored. */
    private const FROM = 'me/events/{event}';

    /** Where it is mirrored to. `{event:uuid}` keeps the uuid binding. */
    private const TO = 'e/{event:uuid}/admin';

    /**
     * Registered once per process. The `Routing` event fires on every match,
     * including an internal dispatch, and mirroring twice would register every
     * route (and every name) a second time.
     */
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        /** @var Router $router */
        $router = RouteFacade::getFacadeRoot();

        // Snapshot first: registering inside the loop would mutate the
        // collection being walked, and the mirror would try to mirror itself.
        $originals = [];

        foreach ($router->getRoutes() as $route) {
            $uri = $route->uri();

            if ($uri === self::FROM || str_starts_with($uri, self::FROM.'/')) {
                $originals[] = $route;

                continue;
            }

            self::refuseStrangerInThisSpace($uri);
        }

        foreach ($originals as $route) {
            self::mirror($router, $route);
        }

        // A person's public profile is mirrored too — but the route is declared
        // by the MEMBERS module, in app/Members/routes.php, because it points at
        // that module's own controller. Declaring it here would reach into
        // another module's private Controllers/, which ModuleBoundaryTest
        // forbids and which was exactly the mistake this comment replaces.

        // The collection is asked for a route BY NAME a moment from now (the
        // router is already matching), so the lookup table has to know about
        // everything just added.
        $router->getRoutes()->refreshNameLookups();
    }

    /**
     * A route under `me/events/` that this mirror cannot see.
     *
     * ⚠️ The prefix match above is LITERAL — `me/events/{event}` — so a route
     * declared with any other parameter name silently fails to mirror while
     * every link to it is still rewritten into the sealed space by
     * SealEventPage. The result is a 404 that looks like a broken feature
     * rather than a routing mistake, and it cost a day: the translation routes
     * shipped as `me/events/{uuid}/translations` and were the only four of a
     * hundred and sixteen that the organiser could not reach from inside an
     * event.
     *
     * So a stranger in this space is refused at boot, loudly, in local and
     * testing — where the person who just wrote it is standing — and merely
     * recorded in production, because a mirror that cannot register a route is
     * never a reason to take the platform down (RULE #1).
     *
     * `me/events/create` and its siblings are not strangers: a literal segment
     * is not a parameter, and they are correctly outside the mirror.
     */
    private static function refuseStrangerInThisSpace(string $uri): void
    {
        if (! str_starts_with($uri, 'me/events/{') || str_starts_with($uri, self::FROM)) {
            return;
        }

        $message = "SealedEventRoutes: `{$uri}` lives under me/events/ but does not use the `{event}` "
            .'parameter, so it will NOT be mirrored into /e/{uuid}/admin while links to it still are. '
            .'Rename the route parameter to {event}.';

        if (app()->environment(['local', 'testing'])) {
            throw new \LogicException($message);
        }

        \Illuminate\Support\Facades\Log::warning($message);
    }

    private static function mirror(Router $router, Route $route): void
    {
        $name = $route->getName();

        // An unnamed route cannot be mirrored safely: the clone would need a
        // name of its own to be addressable, and there is nothing to derive one
        // from. Nothing in this space is unnamed today.
        if (! $name) {
            return;
        }

        $tail = substr($route->uri(), strlen(self::FROM));
        $action = $route->getAction();

        $action['as'] = 'sealed.'.$name;
        $action['middleware'] = self::stack((array) ($action['middleware'] ?? []));

        // The prefix is baked into the new URI; leaving the old one in the
        // action would have Laravel prepend `/me` to it.
        unset($action['prefix']);

        foreach ($route->methods() as $method) {
            // HEAD is registered by Laravel alongside GET.
            if ($method === 'HEAD') {
                continue;
            }

            $clone = $router->addRoute($method, self::TO.$tail, $action);

            // Constraints (whereUuid, whereNumber, the action allowlist on
            // `events.action`) are part of what makes the platform route safe,
            // so they are part of the mirror too.
            if ($route->wheres) {
                $clone->setWheres($route->wheres);
            }
        }
    }

    /**
     * The original stack with the seal added — after `web` so the session and
     * device detection have run, and BEFORE auth/verified/two-factor so their
     * redirects are caught on the way out and turned into somewhere inside the
     * event.
     *
     * @param  array<int, mixed>  $middleware
     * @return array<int, mixed>
     */
    private static function stack(array $middleware): array
    {
        $rest = array_values(array_filter($middleware, fn ($m) => $m !== 'web'));

        return array_merge(['web', SealEventPage::class], $rest);
    }
}
