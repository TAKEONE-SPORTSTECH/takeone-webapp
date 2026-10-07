<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Support\ClubEntry\ClubEntryDesks;
use App\Events\Support\ClubEntry\EntryConsent;
use App\Events\Support\ClubEntry\PlatformClubEntry;
use App\Events\Support\ClubEntry\PlatformClubEntryDesk;
use App\Events\Support\EventAccess;
use App\Events\Support\EventInvitePass;
use App\Events\Support\PublicEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The JSON doors behind "Register your club" on a CLUB-hosted event — the same
 * verbs and shapes as a federation invitation's (club-entry-store.js speaks to
 * both). Public like the screens (a club arriving by link may have no account),
 * behind the screens' own reading gate, CSRF from the web stack, and throttles.
 * Every answer about a club is read from the server's session, never the
 * request (PlatformClubEntry).
 */
class ClubEntryApiController extends Controller
{
    private function entry(Request $request, ClubEvent $event): PlatformClubEntry
    {
        abort_unless(config('events.club_entry', false) && ! $event->is_archived, 404);
        $me = $request->user();
        abort_unless(app(PublicEvent::class)->isPublic($event)
            || EventInvitePass::has($request, $event)
            || ($me && app(EventAccess::class)->visible($event, $me)), 404);
        $desk = ClubEntryDesks::for($event);
        abort_unless($desk instanceof PlatformClubEntryDesk, 404);

        return $desk->service();
    }

    public function clubs(Request $request, ClubEvent $event): JsonResponse
    {
        $q = (string) ($request->validate(['q' => 'nullable|string|max:80'])['q'] ?? '');

        return response()->json(['success' => true, 'clubs' => $this->entry($request, $event)->searchClubs($q)]);
    }

    /** Always the same answer, whether or not the contact or club exists (no probing). */
    public function code(Request $request, ClubEvent $event): JsonResponse
    {
        $v = $request->validate(['contact' => 'nullable|string|max:120', 'club' => 'nullable|string|max:40']);
        $entry = $this->entry($request, $event);
        if (! empty($v['club'])) {
            $entry->sendCodeToClub($v['club']);
        } elseif (! empty($v['contact'])) {
            $entry->sendCode($v['contact']);
        }

        return response()->json(['success' => true], 202);
    }

    public function verify(Request $request, ClubEvent $event): JsonResponse
    {
        $v = $request->validate(['contact' => 'nullable|string|max:120', 'club' => 'nullable|string|max:40', 'code' => 'required|string|max:12']);
        $entry = $this->entry($request, $event);
        $subject = ! empty($v['club']) ? 'club:'.$v['club'] : 'contact:'.(str_contains((string) ($v['contact'] ?? ''), '@')
            ? strtolower(trim((string) $v['contact'])) : preg_replace('/\D/', '', (string) ($v['contact'] ?? '')));
        $r = $entry->verify($subject, $v['code']);
        if (! $r['ok']) {
            return response()->json(['success' => false, 'message' => __('events::club_entry.ui.idBadCode')], 422);
        }
        if (! empty($r['choices'])) {
            return response()->json(['success' => true, 'choices' => $r['choices']]);
        }

        return response()->json(['success' => true, 'store' => $entry->store()]);
    }

    public function choose(Request $request, ClubEvent $event): JsonResponse
    {
        $v = $request->validate(['club' => 'required|string|max:40']);
        $entry = $this->entry($request, $event);
        abort_unless($entry->choose($v['club']), 422);

        return response()->json(['success' => true, 'store' => $entry->store()]);
    }

    public function checkClub(Request $request, ClubEvent $event): JsonResponse
    {
        $v = $request->validate(['name' => 'nullable|string|max:120', 'email' => 'nullable|string|max:120', 'phone' => 'nullable|string|max:40']);

        return response()->json(['success' => true, 'match' => $this->entry($request, $event)->checkClub((string) ($v['name'] ?? ''), (string) ($v['email'] ?? ''), (string) ($v['phone'] ?? ''))]);
    }

    public function submit(Request $request, ClubEvent $event): JsonResponse
    {
        $p = $request->validate([
            'submissionId' => 'required|string|max:64',
            'clubMode' => 'nullable|in:existing,new',
            'club' => 'nullable|array',
            'club.name' => 'nullable|string|max:120', 'club.email' => 'nullable|string|max:120', 'club.phone' => 'nullable|string|max:40',
            'club.logo' => 'nullable|string|max:3000000', 'club.distinct' => 'nullable|boolean',
            'entries' => 'required|array|max:100',
            'entries.*.athleteId' => 'nullable|string|max:60', 'entries.*.name' => 'nullable|string|max:120',
            'entries.*.gender' => 'nullable|in:Male,Female', 'entries.*.dob' => 'nullable|string|max:10',
            'entries.*.weight' => 'nullable|numeric|min:15|max:200', 'entries.*.nat' => 'nullable|string|max:3',
            'entries.*.photo' => 'nullable|string|max:3000000', 'entries.*.types' => 'nullable|array|max:10', 'entries.*.types.*' => 'string|max:120',
        ]);
        $entry = $this->entry($request, $event);
        $desk = ClubEntryDesks::for($event);
        $consented = EntryConsent::current($request, $event, EntryConsent::rulesHash((array) ($desk->page($request)['e'] ?? []))) !== null;

        $r = $entry->submit($p, $consented);
        if (! $r['ok']) {
            return response()->json(['success' => false, 'message' => $r['message'] ?? '', 'code' => $r['code'] ?? null, 'match' => $r['match'] ?? null], $r['status'] ?? 422);
        }
        EntryConsent::attach($request, $event, (array) $desk->subject($request));

        return response()->json(['success' => true, 'ref' => $r['ref'], 'balance' => $r['balance'], 'added' => $r['added'], 'skipped' => $r['skipped'], 'store' => $entry->store()]);
    }

    public function withdraw(Request $request, ClubEvent $event): JsonResponse
    {
        $v = $request->validate(['athleteId' => 'required|string|max:60']);
        $entry = $this->entry($request, $event);
        abort_unless($entry->withdraw($v['athleteId']), 404);

        return response()->json(['success' => true, 'store' => $entry->store()]);
    }

    /** The club's proof of payment for what it owes (a picture; real bytes checked). */
    public function proof(Request $request, ClubEvent $event): JsonResponse
    {
        $v = $request->validate(['proof' => 'required|string|starts_with:data:image/|max:12000000']);
        $entry = $this->entry($request, $event);
        $r = $entry->attachProof($v['proof']);
        if (! $r['ok']) {
            return response()->json(['success' => false, 'message' => $r['message'] ?? ''], 422);
        }

        return response()->json(['success' => true, 'message' => $r['message'], 'store' => $entry->store()]);
    }
}
