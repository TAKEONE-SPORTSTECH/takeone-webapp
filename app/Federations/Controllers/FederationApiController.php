<?php

namespace App\Federations\Controllers;

use App\Federations\Models\Federation;
use App\Federations\Services\FederationBilling;
use App\Federations\Services\FederationDirectory;
use App\Federations\Services\FederationDesk;
use App\Federations\Services\FederationDraws;
use App\Federations\Services\FederationSchedule;
use App\Federations\Services\FederationMessages;
use App\Federations\Services\FederationStrings;
use App\Federations\Services\FederationEvents;
use App\Federations\Models\FederationEvent;
use App\Federations\Models\FederationEventDocument;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The dashboard's writes. The designed page saves whole lists to what it
 * thinks is its own storage; takeone-bridge.js forwards those saves here, and
 * FederationDirectory decides what of each actually happens. Every answer
 * carries the server's copy, which the page redraws from.
 */
class FederationApiController extends Controller
{
    public function __construct(private FederationDirectory $directory) {}

    public function orgs(Request $request): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['orgs' => ['present', 'array', 'max:500']]);

        return response()->json([
            'success' => true,
            'orgs' => $this->directory->syncOrgs($request->user(), $access, $data['orgs']),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['users' => ['present', 'array', 'max:500']]);
        $result = $this->directory->syncUsers($request->user(), $access, $data['users']);

        return response()->json(['success' => true, 'users' => $result['users'], 'message' => $result['message']]);
    }

    public function region(Request $request): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate([
            'region' => ['required', 'array'],
            'region.country' => ['nullable', 'string', 'max:3'],
            'region.tz' => ['nullable', 'string', 'max:64'],
            'region.cur' => ['nullable', 'string', 'size:3'],
        ]);

        return response()->json([
            'success' => true,
            'region' => $this->directory->syncRegion($access, $data['region']),
        ]);
    }

    public function billing(Request $request, FederationBilling $billing): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate([
            'account' => ['required', 'uuid'],
            'billing' => ['required', 'array'],
            'billing.cycle' => ['nullable', 'in:monthly,annual'],
            'billing.invoices' => ['nullable', 'array', 'max:500'],
        ]);

        return response()->json([
            'success' => true,
            'billing' => $billing->sync($request->user(), $access, $data['account'], (array) $request->input('billing')),
        ]);
    }

    public function event(Request $request, FederationEvents $events): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate([
            'event' => ['required', 'array'],
            'event.id' => ['required', 'uuid'],
            'orgId' => ['nullable', 'uuid'],
        ]);
        // validate() keeps only the nested keys that carry a rule; the event
        // is a whole form, so the service reads — and cleans — all of it.
        $saved = $events->save($request->user(), $access, (array) $request->input('event'), $data['orgId'] ?? null);

        return $saved
            ? response()->json(['success' => true, 'event' => $saved])
            : response()->json(['success' => false, 'message' => 'This event could not be saved.'], 422);
    }

    public function eventDelete(Request $request, FederationEvents $events): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['event' => ['required', 'uuid']]);

        return $events->delete($request->user(), $access, $data['event'])
            ? response()->json(['success' => true, 'message' => 'Event deleted.', 'event' => ['id' => $data['event']]])
            : response()->json(['success' => false, 'message' => 'This event could not be deleted.'], 422);
    }

    /** Test mode for one event: on records everything as test data, off deletes it. */
    public function eventTestMode(Request $request, FederationEvents $events): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['event' => ['required', 'uuid'], 'on' => ['required', 'boolean']]);
        $r = $events->setTestMode($request->user(), $access, $data['event'], (bool) $data['on']);

        return response()->json(['success' => $r['ok'], 'message' => $r['message'], 'event' => ['id' => $data['event'], 'testMode' => $r['testMode'] ?? null], 'removed' => $r['removed'] ?? null], $r['ok'] ? 200 : 422);
    }

    /** The rule book one event runs under: its category system and belt system. */
    public function eventRuleBook(Request $request, FederationEvents $events): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate([
            'event' => ['required', 'uuid'],
            'category' => ['nullable', 'string', 'max:64'],
            'belt' => ['nullable', 'string', 'max:64'],
        ]);
        $r = $events->setRuleBook($request->user(), $access, $data['event'], $data['category'] ?? null, $data['belt'] ?? null);

        return response()->json(['success' => $r['ok'], 'message' => $r['message'], 'event' => ['id' => $data['event']], 'rulebook' => $r['rulebook'] ?? null], $r['ok'] ? 200 : 422);
    }

    /** Paid featuring on the members' Events page: send the proof of payment for one event. */
    public function eventFeature(Request $request, FederationEvents $events): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate([
            'event' => ['required', 'uuid'],
            'proof' => ['required', 'file', 'max:10240'],
        ]);
        $r = $events->requestFeature($request->user(), $access, $data['event'], $request->file('proof'));

        return response()->json(['success' => $r['ok'], 'message' => $r['message'], 'event' => ['id' => $data['event']], 'feature' => $r['state'] ?? null], $r['ok'] ? 200 : 422);
    }

    public function upload(Request $request, FederationEvents $events): JsonResponse
    {
        $access = $this->access($request);
        $request->validate(['file' => ['required', 'file', 'max:10240']]);
        $token = $events->stage($request->user(), $access, $request->file('file'));

        return $token
            ? response()->json(['success' => true, 'token' => $token])
            : response()->json(['success' => false, 'message' => 'Only PDF, Word, Excel and image files can be attached.'], 422);
    }

    public function entryConfirm(Request $request, FederationDesk $desk): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['entry' => ['required', 'uuid']]);
        $ok = $desk->confirmEntryPayment($request->user(), $access, $data['entry']);

        return response()->json(['success' => $ok, 'entriesByEvent' => $desk->store($access)['entriesByEvent']], $ok ? 200 : 422);
    }

    public function transferReply(Request $request, FederationDesk $desk): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['transfer' => ['required', 'uuid'], 'released' => ['required', 'boolean']]);
        $ok = $desk->transferReply($access, $data['transfer'], (bool) $data['released']);

        return response()->json(['success' => $ok] + ($ok ? ['transfers' => $desk->store($access)['transfers']] : []), $ok ? 200 : 422);
    }

    public function transferDecide(Request $request, FederationDesk $desk): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['transfer' => ['required', 'uuid'], 'approve' => ['required', 'boolean']]);
        $ok = $desk->decideTransfer($request->user(), $access, $data['transfer'], (bool) $data['approve']);
        $store = $ok ? $desk->store($access) : [];

        return response()->json(['success' => $ok] + ($ok ? ['transfers' => $store['transfers'], 'athletes' => $store['athletes'], 'clubs' => $store['clubs']] : []), $ok ? 200 : 422);
    }

    public function regsApprove(Request $request, FederationDesk $desk): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['entry' => ['required', 'uuid'], 'athlete' => ['required', 'string', 'max:16']]);
        $ok = $desk->approveReg($access, $data['entry'], $data['athlete']);

        return response()->json(['success' => $ok, 'regs' => $desk->regs($access)], $ok ? 200 : 422);
    }

    /** Athletes tab → an athlete's edit dialog: their editable details. */
    public function regsAthlete(Request $request, \App\Federations\Services\FederationAthleteEditor $editor): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['entry' => ['required', 'uuid'], 'athlete' => ['required', 'string', 'max:16']]);
        $shown = $editor->show($access, $data['entry'], $data['athlete']);
        // One answer for "not yours" and "not there" (anti-enumeration).
        abort_unless($shown, 404);

        return response()->json(['success' => true, 'athlete' => $shown, 'canEdit' => (bool) ($access['manager'] ?? false)]);
    }

    /** Athletes tab → save an athlete's details and photo. Answers with the registrations again. */
    public function regsAthleteUpdate(Request $request, FederationDesk $desk, \App\Federations\Services\FederationAthleteEditor $editor): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate([
            'entry' => ['required', 'uuid'],
            'athlete' => ['required', 'string', 'max:16'],
            // A name is the one thing a person record cannot be without.
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'gender' => ['sometimes', 'nullable', 'in:Male,Female'],
            'dob' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01'],
            // Date of birth OR a stated age, never both (FederationAthleteEditor).
            'ageMode' => ['sometimes', 'nullable', 'in:dob,age'],
            'age' => ['sometimes', 'nullable', 'integer', 'min:3', 'max:99'],
            'club' => ['sometimes', 'nullable', 'string', 'regex:/^(fc|fa)-[0-9a-f-]{36}$/'],
            // ISO-3 from the dashboard, ISO-2 from the platform's country picker.
            'nat' => ['sometimes', 'nullable', 'string', 'between:2,3', 'alpha'],
            'natId' => ['sometimes', 'nullable', 'string', 'max:40'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:10', 'max:250'],
            // Already cropped to 3:4 in the browser; the bytes are checked
            // for real by ProfilePhoto (StoresBase64Images).
            'photo' => ['sometimes', 'nullable', 'string', 'starts_with:data:image/', 'max:8000000'],
        ]);
        $r = $editor->update($request->user(), $access, $data['entry'], $data['athlete'], $data);

        return response()->json(['success' => $r['ok'], 'message' => $r['message'], 'athlete' => $r['athlete'] ?? null, 'entry' => $r['entry'] ?? null] + ($r['ok'] ? ['regs' => $desk->regs($access)] : []), $r['ok'] ? 200 : 422);
    }

    /** Officials tab → the person behind one appointment, for the edit window. */
    public function officialOne(Request $request, \App\Federations\Services\FederationOfficialEditor $editor): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['event' => ['required', 'uuid'], 'official' => ['required', 'integer', 'min:1']]);
        $shown = $editor->show($access, $request->user(), $data['event'], (int) $data['official']);
        // One answer for "not yours" and "not there" (anti-enumeration).
        abort_unless($shown, 404);

        return response()->json(['success' => true, 'official' => $shown], 200, ['Cache-Control' => 'no-store, private']);
    }

    /** Officials tab → save the person behind an appointment: name, gender, nationality, photo. */
    public function officialPersonUpdate(Request $request, \App\Federations\Services\FederationOfficialEditor $editor): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate([
            'event' => ['required', 'uuid'],
            'official' => ['required', 'integer', 'min:1'],
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:150'],
            'gender' => ['sometimes', 'nullable', 'in:Male,Female'],
            'nat' => ['sometimes', 'nullable', 'string', 'between:2,3', 'alpha'],
            // Cropped to 3:4 in the browser; the bytes are checked by ProfilePhoto.
            'photo' => ['sometimes', 'nullable', 'string', 'starts_with:data:image/', 'max:8000000'],
        ]);
        $r = $editor->update($access, $request->user(), $data['event'], (int) $data['official'], $data);

        return response()->json(['success' => $r['ok'], 'message' => $r['message'], 'official' => $r['official'] ?? null], $r['ok'] ? 200 : 422);
    }

    /** Registrations → "Enroll athlete": one person entered by hand. Answers with the registrations again. */
    public function regsEnrol(Request $request, FederationDesk $desk, \App\Federations\Services\FederationEnrolment $enrolment): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($enrolment->mayEnrol($access), 404);
        $data = $request->validate([
            'event' => ['required', 'uuid'],
            'person' => ['nullable', 'string', 'max:40', 'regex:/^([A-Z]{3}-[0-9A-Z]{1,10}|m-[0-9a-f-]{36})$/'],
            'club' => ['required_without_all:person,newClub', 'nullable', 'string', 'regex:/^(fc|fa)-[0-9a-f-]{36}$/'],
            'newClub' => ['nullable', 'array'],
            'newClub.name' => ['required_with:newClub', 'nullable', 'string', 'min:2', 'max:150'],
            'newClub.coach' => ['nullable', 'string', 'max:150'],
            'new' => ['required_without:person', 'nullable', 'array'],
            'new.name' => ['required_without:person', 'nullable', 'string', 'min:2', 'max:150'],
            'new.gender' => ['nullable', 'in:Male,Female'],
            'new.dob' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'new.nat' => ['nullable', 'string', 'max:60'],
            'new.natId' => ['nullable', 'string', 'max:40'],
            'weight' => ['nullable', 'numeric', 'min:10', 'max:250'],
            'types' => ['present', 'array', 'max:20'],
            'types.*' => ['string', 'max:60'],
        ]);
        $r = $enrolment->enrol($request->user(), $access, $data);

        return response()->json(['success' => $r['ok'], 'message' => $r['message'], 'takeoneId' => $r['takeoneId'] ?? null, 'regs' => $desk->regs($access)], $r['ok'] ? 200 : 422);
    }

    /** Registrations → "Import from Excel": this event's template. */
    public function regsTemplate(Request $request, \App\Federations\Services\FederationEnrolment $enrolment, \App\Federations\Services\FederationBulkImport $import): \Symfony\Component\HttpFoundation\Response
    {
        $access = $this->access($request);
        abort_unless($enrolment->mayEnrol($access), 404);
        $data = $request->validate(['event' => ['required', 'uuid']]);
        $event = $import->event($access, $data['event']);
        abort_unless($event, 404);
        $name = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $event->name), '-') ?: 'event';

        return response($import->template($access, $event), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.mb_substr($name, 0, 60).'-athletes-template.xlsx"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Read an uploaded file and say, row by row, what importing it would do. Nothing is written. */
    public function regsImportPreview(Request $request, \App\Federations\Services\FederationEnrolment $enrolment, \App\Federations\Services\FederationBulkImport $import): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($enrolment->mayEnrol($access), 404);
        $data = $request->validate([
            'event' => ['required', 'uuid'],
            'file' => ['required', 'file', 'max:4096', 'mimes:xlsx,xls,csv', 'mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/vnd.ms-excel,application/octet-stream,text/csv,text/plain,application/csv'],
        ]);
        $event = $import->event($access, $data['event']);
        abort_unless($event, 404);
        $r = $import->preview($access, $event, $request->file('file'));

        return response()->json(['success' => $r['ok'], 'message' => $r['message'] ?? '', 'rows' => $r['rows'] ?? []], $r['ok'] ? 200 : 422, ['Cache-Control' => 'no-store, private']);
    }

    /** Enter the rows the preview found ready — each checked again by FederationEnrolment. */
    public function regsImport(Request $request, FederationDesk $desk, \App\Federations\Services\FederationEnrolment $enrolment, \App\Federations\Services\FederationBulkImport $import): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($enrolment->mayEnrol($access), 404);
        $data = $request->validate([
            'event' => ['required', 'uuid'],
            'rows' => ['required', 'array', 'min:1', 'max:'.\App\Federations\Services\FederationBulkImport::MAX_ROWS],
            'rows.*.line' => ['required', 'integer', 'min:1', 'max:100000'],
            'rows.*.person' => ['nullable', 'string', 'max:16', 'regex:/^[A-Z]{3}-[0-9A-Z]{1,10}$/'],
            'rows.*.club' => ['nullable', 'string', 'regex:/^(fc|fa)-[0-9a-f-]{36}$/'],
            'rows.*.name' => ['nullable', 'string', 'max:150'],
            'rows.*.clubName' => ['nullable', 'string', 'max:150'],
            'rows.*.coach' => ['nullable', 'string', 'max:150'],
            'rows.*.gender' => ['nullable', 'in:Male,Female'],
            'rows.*.dob' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'rows.*.nat' => ['nullable', 'string', 'size:2'],
            'rows.*.natId' => ['nullable', 'string', 'max:40'],
            'rows.*.weight' => ['nullable', 'numeric', 'min:10', 'max:250'],
            'rows.*.types' => ['present', 'array', 'max:20'],
            'rows.*.types.*' => ['string', 'max:60'],
        ]);
        $event = $import->event($access, $data['event']);
        abort_unless($event, 404);
        $out = $import->import($request->user(), $access, $event, $data['rows']);
        $ok = collect($out['results'])->where('ok', true)->count();

        return response()->json(['success' => true, 'imported' => $ok, 'failed' => count($out['results']) - $ok, 'newClubs' => $out['clubs'],
            'results' => $out['results'], 'regs' => $desk->regs($access)]);
    }

    public function regsWithdraw(Request $request, FederationDesk $desk): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['athletes' => ['required', 'array', 'max:1000']]);
        $ok = $desk->withdrawRegs($access, $data['athletes']);

        return response()->json(['success' => $ok, 'regs' => $desk->regs($access)], $ok ? 200 : 403);
    }

    /** Event → Clubs tab: take a whole club's athletes off the event. Answers with the registrations again. */
    public function regsWithdrawClub(Request $request, FederationDesk $desk): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate([
            'event' => ['required', 'uuid'],
            'club' => ['required', 'string', 'regex:/^(fc|fa)-[0-9a-f-]{36}$/'],
        ]);
        $n = $desk->withdrawClub($access, $data['event'], $data['club']);
        // One answer for "not yours" and "not there" (anti-enumeration).
        abort_if($n === null, 404);

        return response()->json(['success' => true, 'withdrawn' => $n,
            'message' => $n === 1 ? '1 athlete was removed from the event.' : $n.' athletes were removed from the event.',
            'regs' => $desk->regs($access)]);
    }

    /** The chat dock: every thread in scope (read), or write to one club (send). */
    public function messages(Request $request, FederationMessages $messages): JsonResponse
    {
        $access = $this->access($request);
        if ($request->isMethod('get')) {
            return response()->json(['success' => true, 'messages' => $messages->threads($access), 'clubs' => $messages->clubMap($access)]);
        }
        $data = $request->validate([
            'club' => ['required', 'string', 'regex:/^fc-[0-9a-f-]{36}$/'],
            'text' => ['required', 'string', 'max:5000'],
        ]);
        $threads = $messages->send($request->user(), $access, $data['club'], trim($data['text']));

        return $threads
            ? response()->json(['success' => true, 'messages' => $threads])
            : response()->json(['success' => false, 'message' => 'This club cannot receive messages.', 'messages' => $messages->threads($access)], 422);
    }

    /** Settings → Translations: the whole strings table, as the page saves it. */
    public function strings(Request $request, FederationStrings $strings): JsonResponse
    {
        $access = $this->access($request);
        $request->validate(['strings' => ['present', 'array', 'max:'.FederationStrings::MAX_ROWS]]);

        return response()->json(['success' => true, 'strings' => $strings->sync($request->user(), $access, (array) $request->input('strings'))]);
    }

    /** Record or clear one bout on the Draw tab. Answers with the division as kept. */
    public function draw(Request $request, FederationDraws $draws): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate([
            'event' => ['required', 'uuid'],
            'division' => ['required', 'string', 'max:191'],
            'match' => ['required', 'string', 'regex:/^\d{1,2}-\d{1,3}$/'],
            'winner' => ['nullable', 'string', 'max:16'],
            'by' => ['nullable', 'required_with:winner', 'in:'.implode(',', FederationDraws::METHODS)],
            'slots' => ['present', 'array', 'max:512'],
            'slots.*' => ['nullable', 'string', 'max:16'],
        ]);
        $out = $draws->record($request->user(), $access, $data['event'], $data['division'], $data['match'],
            $data['winner'] ?? null, isset($data['winner']) ? $data['by'] : null, array_values($data['slots']));

        return response()->json(['success' => $out['ok'], 'division' => $out['division']], $out['ok'] ? 200 : 422);
    }

    /**
     * The Schedule tab: add, rename, reorder or remove a day; add, edit or
     * remove a session on it. Any staff member who can open the event may
     * (FederationSchedule). Answers with that event's whole schedule.
     */
    public function schedule(Request $request, FederationSchedule $schedule): JsonResponse
    {
        $access = $this->access($request);
        $time = ['regex:/^([01]\d|2[0-3]):[0-5]\d$/'];
        $op = (string) $request->input('op');
        $rules = ['op' => ['required', 'in:'.implode(',', FederationSchedule::OPS)]];
        $rules += match ($op) {
            'day.add' => ['event' => ['required', 'uuid'], 'title' => ['nullable', 'string', 'max:120']],
            'day.update' => ['day' => ['required', 'uuid'], 'title' => ['nullable', 'string', 'max:120']],
            'day.move' => ['day' => ['required', 'uuid'], 'dir' => ['required', 'in:up,down']],
            'day.delete' => ['day' => ['required', 'uuid']],
            'slot.add', 'slot.update' => [
                ($op === 'slot.add' ? 'day' : 'slot') => ['required', 'uuid'],
                'time' => ['required', 'string', ...$time],
                'end' => ['nullable', 'string', ...$time, 'after:time'],
                'what' => ['required', 'string', 'max:160'],
                'where' => ['nullable', 'string', 'max:160'],
            ],
            'slot.delete' => ['slot' => ['required', 'uuid']],
            default => [],
        };
        $data = $request->validate($rules, [
            'time.regex' => 'Enter the start time as HH:MM, for example 09:30.',
            'end.regex' => 'Enter the end time as HH:MM, for example 11:00.',
            'end.after' => 'The end time must be after the start time.',
            'what.required' => 'Say what happens in this session.',
        ]);
        foreach (['title', 'what', 'where'] as $k) {
            if (isset($data[$k])) {
                $data[$k] = trim(preg_replace('/\s+/u', ' ', $data[$k])) ?: null;
            }
        }
        if (in_array($op, ['slot.add', 'slot.update'], true) && empty($data['what'])) {
            return response()->json(['success' => false, 'message' => 'Say what happens in this session.'], 422);
        }

        $out = $schedule->apply($request->user(), $access, $op, $data);

        return response()->json([
            'success' => $out['ok'],
            'message' => $out['message'] ?? ($out['ok'] ? 'Schedule saved.' : ''),
            'event' => $out['event'],
            'days' => $out['days'],
        ], $out['ok'] ? 200 : 422);
    }

    /** An event's logo or artwork — public: it is the poster clubs are invited with. */
    public function eventMedia(FederationEvent $event, string $kind): Response
    {
        abort_unless(config('federations.enabled'), 404);
        $path = match ($kind) {
            'logo' => $event->logo,
            'art-p' => $event->art_portrait,
            'art-l' => $event->art_landscape,
            default => null,
        };
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The QR code for an event's club invitation — public, like the link it
     * encodes (the invitation page is what a club opens without signing in).
     *
     * The address inside it is built HERE from the event's own uuid, never
     * taken from the request: this can only ever draw a TakeOne invitation,
     * so it is not an open QR service anyone could point anywhere. The per-club
     * personal links carry a secret token and are deliberately not offered.
     */
    public function inviteQr(Request $request, FederationEvent $event, string $format): Response
    {
        abort_unless(config('federations.enabled'), 404);
        $url = route('federations.app.invitation').'?event='.rawurlencode($event->uuid);

        $headers = ['Cache-Control' => 'public, max-age=86400', 'X-Content-Type-Options' => 'nosniff'];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="invitation-qr-'.(Str::slug((string) $event->name) ?: 'event').'.'.$format.'"';
        }

        if ($format === 'png') {
            $png = (new \BaconQrCode\Writer(new \BaconQrCode\Renderer\GDLibRenderer(1024, 4)))->writeString($url);

            return response($png, 200, ['Content-Type' => 'image/png'] + $headers);
        }

        return response(\App\Support\Qr::svg($url, 512, 2), 200, ['Content-Type' => 'image/svg+xml; charset=utf-8'] + $headers);
    }

    /**
     * A document the organiser attached for clubs to read. Public by design —
     * the invitation page lists it before any club has signed in — reached
     * only by its own uuid, never by a path.
     */
    public function eventDocument(string $document): Response
    {
        abort_unless(config('federations.enabled'), 404);
        $doc = FederationEventDocument::where('uuid', $document)->first();
        abort_unless($doc && $doc->event && Storage::disk('local')->exists($doc->path), 404);

        $ext = pathinfo($doc->path, PATHINFO_EXTENSION);
        $name = (Str::slug($doc->title) ?: 'document').'.'.$ext;

        return Storage::disk('local')->download($doc->path, $name, [
            'Content-Type' => $doc->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * A federation's logo. Public on purpose: it is the body's own branding,
     * printed on the invitation clubs open without signing in.
     */
    public function logo(Federation $federation): Response
    {
        abort_unless(config('federations.enabled') && $federation->isApproved() && $federation->logo, 404);
        abort_unless(Storage::disk('public')->exists($federation->logo), 404);

        return Storage::disk('public')->response($federation->logo, null, [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array{root: Federation, manager: bool, sports: array, role: string} */
    /** The Statistics page's figures — read-only, the viewer's own scope (FederationStatistics). */
    public function statistics(Request $request, \App\Federations\Services\FederationStatistics $statistics): JsonResponse
    {
        $access = $this->access($request);

        // The body picked in the dashboard's switcher: that body and everything
        // under it. Only a body inside the viewer's own tree is honoured — any
        // other value (unknown, outside, malformed) reads the whole tree, the
        // same answer as no value at all, so nothing is learnt by probing.
        $body = $request->query('body');
        if (is_string($body) && Str::isUuid($body)) {
            $picked = collect($this->directory->subtree($access['root']))->first(fn (Federation $f) => $f->uuid === $body);
            if ($picked) {
                $access['root'] = $picked;
            }
        }

        return response()->json(['success' => true, 'stats' => $statistics->payload($access)], 200, ['Cache-Control' => 'no-store, private']);
    }

    /**
     * Where a pasted Google Maps link points — the event editor's pin follows
     * it. Coordinates only; anything that is not Google Maps, or cannot be
     * followed, answers null. The following is App\Support\MapLink, which never
     * leaves Google's own hosts.
     */
    public function mapLink(Request $request): JsonResponse
    {
        $this->access($request);
        $url = trim((string) $request->validate(['url' => ['required', 'string', 'max:2000']])['url']);

        $at = \App\Support\MapLink::isGoogle($url)
            ? \Illuminate\Support\Facades\Cache::remember('geo:maplink:'.sha1($url), 60 * 60 * 24 * 30, fn () => \App\Support\MapLink::resolve($url) ?? [])
            : [];

        return response()->json(['success' => true, 'lat' => $at['lat'] ?? null, 'lng' => $at['lng'] ?? null], 200, ['Cache-Control' => 'no-store, private']);
    }

    /** Clubs page → "Add club": existing platform clubs, by club name or owner name, phone or email. */
    public function clubSearch(Request $request, \App\Federations\Services\FederationClubPicker $picker): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($picker->mayAdd($access), 403);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']]);

        return response()->json(['success' => true, 'clubs' => $picker->search($access, $data['q'])], 200, ['Cache-Control' => 'no-store, private']);
    }

    /** Affiliate an existing club with the viewer's federation; answers with the refreshed club list. */
    public function clubAdd(Request $request, \App\Federations\Services\FederationClubPicker $picker): JsonResponse
    {
        $access = $this->access($request);
        $data = $request->validate(['club' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9\-]+$/']]);
        $ok = $picker->add($request->user(), $access, $data['club']);

        return $ok
            ? response()->json(['success' => true, 'message' => 'Club added.'] + $picker->clubs($access))
            : response()->json(['success' => false, 'message' => 'That club could not be added.'], 422);
    }

    /** The club list again — what a `clubs` refresh signal re-fetches. */
    public function clubList(Request $request, \App\Federations\Services\FederationClubPicker $picker): JsonResponse
    {
        return response()->json(['success' => true] + $picker->clubs($this->access($request)), 200, ['Cache-Control' => 'no-store, private']);
    }

    /** Clubs page → a club's edit / decide sheet: its details, and for an application, look-alike platform clubs. */
    public function clubDetail(Request $request, \App\Federations\Services\FederationClubManager $manager): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($manager->mayManage($access), 404);
        $data = $request->validate(['club' => ['required', 'string', 'regex:/^(fc|fa)-[0-9a-f-]{36}$/']]);
        $detail = $manager->detail($access, $data['club']);
        abort_unless($detail, 404);

        return response()->json(['success' => true, 'club' => $detail], 200, ['Cache-Control' => 'no-store, private']);
    }

    /** Edit a pending application, or a club this federation created. Answers with the refreshed club list. */
    public function clubUpdate(Request $request, \App\Federations\Services\FederationClubManager $manager, \App\Federations\Services\FederationClubPicker $picker): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($manager->mayManage($access), 404);
        $data = $request->validate([
            'club' => ['required', 'string', 'regex:/^(fc|fa)-[0-9a-f-]{36}$/'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'coach' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+\-\s().]*$/'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
        $error = $manager->update($request->user(), $access, $data['club'], $data);

        return $this->clubAnswer($error, 'Club saved.', $access, $picker);
    }

    /** A new logo for a club the federation may edit. Answers with the refreshed club list. */
    public function clubLogo(Request $request, \App\Federations\Services\FederationClubManager $manager, \App\Federations\Services\FederationClubPicker $picker): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($manager->mayManage($access), 404);
        $data = $request->validate([
            'club' => ['required', 'string', 'regex:/^(fc|fa)-[0-9a-f-]{36}$/'],
            'logo' => ['required', 'string', 'starts_with:data:image/', 'max:8000000'],
        ]);
        $error = $manager->replaceLogo($request->user(), $access, $data['club'], $data['logo']);

        return $this->clubAnswer($error, 'Logo saved.', $access, $picker);
    }

    /** End a registered club's affiliation. The club itself and its history stay. */
    public function clubUnlink(Request $request, \App\Federations\Services\FederationClubManager $manager, \App\Federations\Services\FederationClubPicker $picker): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($manager->mayManage($access), 404);
        $data = $request->validate(['club' => ['required', 'string', 'regex:/^fc-[0-9a-f-]{36}$/']]);
        $error = $manager->unlink($request->user(), $access, $data['club']);

        return $this->clubAnswer($error, 'Club unlinked.', $access, $picker);
    }

    /** A pending application: approve as a new club, link it to an existing one, or refuse it. */
    public function clubDecide(Request $request, \App\Federations\Services\FederationClubManager $manager, \App\Federations\Services\FederationClubPicker $picker): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($manager->mayManage($access), 404);
        $data = $request->validate([
            'club' => ['required', 'string', 'regex:/^fa-[0-9a-f-]{36}$/'],
            'decision' => ['required', 'in:new,link,reject'],
            'target' => ['required_if:decision,link', 'nullable', 'string', 'max:120', 'regex:/^[a-z0-9\-]+$/'],
        ]);
        $error = $manager->decide($request->user(), $access, $data['club'], $data['decision'], $data['target'] ?? null);
        $done = ['new' => 'Club approved.', 'link' => 'Linked to the existing club.', 'reject' => 'Application refused.'][$data['decision']];

        return $this->clubAnswer($error, $done, $access, $picker);
    }

    /** Delete a club that came in through the registration link and was never approved. */
    public function clubDelete(Request $request, \App\Federations\Services\FederationClubManager $manager, \App\Federations\Services\FederationClubPicker $picker): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($manager->mayManage($access), 404);
        $data = $request->validate(['club' => ['required', 'string', 'regex:/^fa-[0-9a-f-]{36}$/']]);
        $error = $manager->deleteApplication($request->user(), $access, $data['club']);

        return $this->clubAnswer($error, 'Club deleted.', $access, $picker);
    }

    /** Athletes page → "Same person as…": what linking / merging would do, before it is done. */
    public function athleteMergePreview(Request $request, \App\Federations\Services\FederationAthleteMerge $merge): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($merge->mayMerge($access), 404);
        $data = $request->validate($this->mergeRules());

        return response()->json(['success' => true] + $merge->preview($access, $data['athlete'], $data['into']), 200, ['Cache-Control' => 'no-store, private']);
    }

    /** Link a registration-link athlete to an existing member, or merge it into an existing athlete. */
    public function athleteMerge(Request $request, \App\Federations\Services\FederationAthleteMerge $merge, \App\Federations\Services\FederationDesk $desk): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($merge->mayMerge($access), 404);
        $data = $request->validate($this->mergeRules());
        $error = $merge->merge($request->user(), $access, $data['athlete'], $data['into']);
        if ($error !== null) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }
        $store = $desk->store($access, $request->user());

        return response()->json(['success' => true, 'message' => 'Merged.', 'clubs' => $store['clubs'], 'athletes' => $store['athletes'], 'directory' => $store['directory'], 'transfers' => $store['transfers']]);
    }

    /** Athletes page → a club member with no TakeOne ID gets one. */
    public function athleteIssueId(Request $request, \App\Federations\Services\FederationAthleteMerge $merge, \App\Federations\Services\FederationDesk $desk): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($merge->mayMerge($access), 404);
        $data = $request->validate(['member' => ['required', 'string', 'regex:/^m-[0-9a-f-]{36}$/']]);
        $r = $merge->issue($request->user(), $access, $data['member']);
        if (isset($r['error'])) {
            return response()->json(['success' => false, 'message' => $r['error']], 422);
        }
        $store = $desk->store($access, $request->user());

        return response()->json(['success' => true, 'message' => 'TakeOne ID issued.', 'takeoneId' => $r['takeoneId'],
            'clubs' => $store['clubs'], 'athletes' => $store['athletes'], 'directory' => $store['directory'], 'transfers' => $store['transfers']]);
    }

    /** Athletes page → delete one athlete or many. Answers per athlete, with the directory again. */
    public function athletesDelete(Request $request, \App\Federations\Services\FederationAthleteMerge $merge, \App\Federations\Services\FederationDesk $desk): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($merge->mayMerge($access), 404);
        $data = $request->validate([
            'athletes' => ['required', 'array', 'min:1', 'max:200'],
            'athletes.*' => ['string', 'max:16', 'regex:/^[A-Z]{3}-[0-9A-Z]{1,10}$/'],
            // The word the person TYPED in the confirmation box — no delete without it.
            'confirm' => ['required', 'string', 'in:DELETE'],
        ], ['confirm.in' => 'Type DELETE to confirm.', 'confirm.required' => 'Type DELETE to confirm.']);
        $results = $merge->remove($request->user(), $access, $data['athletes']);
        $store = $desk->store($access, $request->user());
        $ok = collect($results)->where('ok', true)->count();

        return response()->json(['success' => true, 'deleted' => $ok, 'failed' => count($results) - $ok, 'results' => $results,
            'clubs' => $store['clubs'], 'athletes' => $store['athletes'], 'directory' => $store['directory'], 'transfers' => $store['transfers']]);
    }

    /** Athletes page → export: one athlete as a .tkathlete file, several as a zip of them. */
    public function athletesExport(Request $request, \App\Federations\Services\FederationAthleteMerge $merge, \App\Federations\Services\FederationAthleteArchive $archive): \Symfony\Component\HttpFoundation\Response
    {
        $access = $this->access($request);
        abort_unless($merge->mayMerge($access), 404);
        $data = $request->validate([
            'athletes' => ['required', 'array', 'min:1', 'max:100'],
            'athletes.*' => ['string', 'max:16', 'regex:/^[A-Z]{3}-[0-9A-Z]{1,10}$/'],
        ]);
        $files = [];
        foreach (array_values(array_unique($data['athletes'])) as $tk) {
            if ($f = $archive->export($request->user(), $access, $tk)) {
                $files[] = $f;
            }
        }
        abort_if($files === [], 404);
        $headers = ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff'];
        $disposition = fn (string $name) => 'attachment; filename="'.str_replace('"', '', \Illuminate\Support\Str::ascii($name)).'"; filename*=UTF-8\'\''.rawurlencode($name);

        if (count($files) === 1) {
            return response($files[0]['bytes'], 200, $headers + ['Content-Type' => 'application/octet-stream', 'Content-Disposition' => $disposition($files[0]['name'])]);
        }
        $tmp = tempnam(sys_get_temp_dir(), 'tkz');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $used = [];
        foreach ($files as $f) {
            $n = $f['name'];
            for ($i = 2; isset($used[$n]); $i++) {
                $n = preg_replace('/\.tkathlete$/', '', $f['name']).' ('.$i.').tkathlete';
            }
            $used[$n] = true;
            $zip->addFromString($n, $f['bytes']);
        }
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return response($bytes, 200, $headers + ['Content-Type' => 'application/zip', 'Content-Disposition' => $disposition('athletes-export-'.now()->format('Y-m-d').'.zip')]);
    }

    /** Athletes page → import one or more .tkathlete files; each is restored on its own. */
    public function athletesImport(Request $request, \App\Federations\Services\FederationAthleteMerge $merge, \App\Federations\Services\FederationAthleteArchive $archive, \App\Federations\Services\FederationDesk $desk): JsonResponse
    {
        $access = $this->access($request);
        abort_unless($merge->mayMerge($access), 404);
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'max:61440'],
        ]);
        // .tkathlete files, or zips of them (what "Export N" downloads) — opened here.
        $items = [];
        $refused = [];
        foreach ($request->file('files') as $f) {
            $name = mb_substr($f->getClientOriginalName(), 0, 150);
            $raw = (string) file_get_contents($f->getRealPath());
            if (str_starts_with($raw, "PK\x03\x04")) {
                foreach ($this->athleteFilesInZip($f->getRealPath(), $name, $refused) as $it) {
                    $items[] = $it;
                }
            } else {
                $items[] = [$name, $raw];
            }
            if (count($items) > 100) {
                break;
            }
        }
        $results = $refused;
        foreach (array_slice($items, 0, 100) as [$name, $raw]) {
            $results[] = ['file' => $name] + $archive->import($request->user(), $access, $raw);
        }
        if (count($items) > 100) {
            $results[] = ['file' => '', 'ok' => false, 'message' => 'Only the first 100 athletes were imported — import the rest in another go.'];
        }
        $store = $desk->store($access, $request->user());
        $ok = collect($results)->where('ok', true)->count();

        return response()->json(['success' => true, 'imported' => $ok, 'failed' => count($results) - $ok, 'results' => $results,
            'clubs' => $store['clubs'], 'athletes' => $store['athletes'], 'directory' => $store['directory'], 'transfers' => $store['transfers']]);
    }

    /**
     * The athlete files inside an uploaded zip. Guarded against hostile
     * archives: only *.tkathlete entries at any depth are read (no nested zips,
     * no paths used), at most 100, each at most 60 MB uncompressed, 400 MB in all.
     *
     * @param  array<int, array<string, mixed>>  $refused  gets a line per problem
     * @return array<int, array{0: string, 1: string}>
     */
    private function athleteFilesInZip(string $path, string $zipName, array &$refused): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            $refused[] = ['file' => $zipName, 'ok' => false, 'message' => $zipName.' could not be opened as a zip.'];

            return [];
        }
        $out = [];
        $total = 0;
        for ($i = 0; $i < min($zip->numFiles, 1000); $i++) {
            $st = $zip->statIndex($i);
            $entry = (string) ($st['name'] ?? '');
            if (! preg_match('/\.tkathlete$/i', $entry) || str_contains($entry, '__MACOSX')) {
                continue;
            }
            $size = (int) ($st['size'] ?? 0);
            $total += $size;
            if ($size > 60 * 1024 * 1024 || $total > 400 * 1024 * 1024) {
                $refused[] = ['file' => basename($entry), 'ok' => false, 'message' => basename($entry).' is too large to import.'];

                continue;
            }
            $out[] = [mb_substr(basename($entry), 0, 150), (string) $zip->getFromIndex($i)];
            if (count($out) > 100) {
                break;
            }
        }
        $zip->close();
        if ($out === [] && $refused === []) {
            $refused[] = ['file' => $zipName, 'ok' => false, 'message' => $zipName.' has no athlete files (.tkathlete) inside.'];
        }

        return $out;
    }

    private function mergeRules(): array
    {
        return [
            'athlete' => ['required', 'string', 'max:16', 'regex:/^[A-Z]{3}-[0-9A-Z]{1,10}$/'],
            'into' => ['required', 'string', 'max:40', 'regex:/^([A-Z]{3}-[0-9A-Z]{1,10}|m-[0-9a-f-]{36})$/'],
        ];
    }

    private function clubAnswer(?string $error, string $done, array $access, \App\Federations\Services\FederationClubPicker $picker): JsonResponse
    {
        return $error === null
            ? response()->json(['success' => true, 'message' => $done] + $picker->clubs($access))
            : response()->json(['success' => false, 'message' => $error], 422);
    }

    private function access(Request $request): array
    {
        abort_unless(config('federations.enabled'), 404);
        $access = $this->directory->access($request->user());
        abort_unless($access, 404);

        return $access;
    }
}
