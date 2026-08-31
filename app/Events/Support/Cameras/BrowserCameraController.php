<?php

namespace App\Events\Support\Cameras;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Filming a mat with the phone already in somebody's hand.
 *
 * The camera app is the better instrument — it survives the screen locking, it
 * keeps its own copy of the footage, it can be told to upload later. But it has
 * to be installed, from the right host, before any of that is true, and on a
 * competition morning that is a wall: three separate sessions were lost to a
 * phone that was enrolled on the other server and a console that could only say
 * "no camera is waiting with that code".
 *
 * So there is a second door, and it needs nothing. Open this page, show the code,
 * an organiser pairs it, and the phone films. It speaks the SAME four endpoints
 * the app speaks (`/camera/enroll`, `/camera/{token}/config`, `/camera/{token}/clip`
 * and the chunked upload), holds the SAME kind of token, and appears in the
 * console as an ordinary camera — because to the server it IS one. Nothing about
 * the mat, the bout or the clip knows which door the lens came through.
 *
 * ── Why it is open, like /screen ────────────────────────────────────────────
 * The page grants nothing. Enrolling yields an UNCLAIMED camera that can read no
 * event, no draw, no other camera, and can film nothing until an authenticated
 * organiser who can manage the event puts it on a mat. The credential is the
 * organiser's session at that moment, exactly as it is for a wall screen.
 */
class BrowserCameraController extends Controller
{
    /**
     * The viewfinder — or the pairing code, until somebody claims it.
     *
     * Deliberately one page for both states rather than a redirect: a phone
     * being held up to a mat must never navigate on its own, and the transition
     * from "waiting" to "filming" is a claim made by someone else, elsewhere.
     */
    public function show(Request $request)
    {
        return view('events.camera.web', [
            // Read off the glass and typed into a sideloader when somebody
            // decides they want the real app after all. Null when no build is
            // published, so the page never offers a download that 404s.
            'appUrl' => ScreenPairingControllerAppLink::camAvailable()
                ? preg_replace('#^https?://#', '', route('screen.app.cam'))
                : null,
            'host' => $request->getHost(),
        ]);
    }
}

/**
 * A one-line shim so this controller does not reach into the screen pairing
 * controller's privates just to ask whether an APK exists.
 */
final class ScreenPairingControllerAppLink
{
    public static function camAvailable(): bool
    {
        return \App\Events\Support\ScreenPairingController::appAvailable('cam');
    }
}
