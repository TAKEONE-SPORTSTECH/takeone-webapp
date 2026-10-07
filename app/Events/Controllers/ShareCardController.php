<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Support\ClubEntry\ClubEntryDesks;
use App\Events\Support\PublicEvent;
use App\Events\Support\ShareCard;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * The drawn link-preview picture of an event (App\Events\Support\ShareCard),
 * fetched by WhatsApp, Instagram and the rest when a link is shared — so it is
 * public, for an event that is published OR takes entries by invitation (a
 * club holding the invitation shares it before it has an account). It carries
 * the event's colour, the organiser's logo, its poster and its name — nothing
 * a reader of the invitation would not see on the page itself.
 */
class ShareCardController extends Controller
{
    public function show(ClubEvent $event, ShareCard $cards): Response
    {
        abort_if($event->is_archived, 404);
        abort_unless(app(PublicEvent::class)->isPublic($event) || ClubEntryDesks::for($event) !== null, 404);

        $file = $cards->file($event);
        abort_if($file === null, 404);

        return response()->file($file, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
