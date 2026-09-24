<?php

namespace App\Support\Modules\Surfaces;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A module whose pages are a sealed product of their own — nothing a visitor
 * can click, or be sent to by an error, leaves it. The platform's global error
 * handlers ask it first (bootstrap/app.php), so a 404, an expired form or a
 * missing sign-in inside the surface is answered inside the surface.
 *
 * (The public event pages predate this and keep their own
 * App\Events\Support\SealedRequest.)
 */
interface SealsItsSurface
{
    /** Is this request inside the sealed surface? */
    public function sealsRequest(Request $request): bool;

    /** The surface's answer to an error status, rendered IN PLACE (never a redirect that could loop). */
    public function sealedErrorResponse(Request $request, int $status): Response;

    /** Where a guest signs in, or null if the surface is already at its sign-in. */
    public function sealedSignIn(Request $request): ?string;
}
