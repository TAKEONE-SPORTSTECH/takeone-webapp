<?php

namespace App\EventLab\Controllers;

use App\Http\Controllers\Controller;

use App\EventLab\Support\EntryEditor;
use App\Events\Support\EventFee;
use App\Support\StoragePath;
use App\Traits\StoresBase64Images;
use App\Events\Support\EntryService;
use App\Events\Support\EventAccess;
use App\Events\Support\PublicEvent;
use App\Events\Support\Withdrawal;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventWithdrawalRequest;
use App\Sports\Combat\BeltRank;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * "My entry" — the athlete's own control panel, and the organiser's twin of it.
 *
 * WHERE IT LIVES, AND WHY
 * -----------------------
 * The entrant half sits on the PUBLIC sealed surface (`/e/{uuid}/my-entry`)
 * rather than in the `me.` group that mirrors into `/e/{uuid}/admin/…`, and the
 * reason is not tidiness. The mirrored routes carry the platform's full stack —
 * `auth` + `verified` + `two-factor` — but an athlete who entered through the
 * public door has an account created UNVERIFIED on purpose ("verification is
 * what KEEPS the place, not what takes it", PublicEntry). Hanging their only
 * means of fixing a typo behind an email they have not opened yet would make
 * this panel unreachable by exactly the people who need it most. So: `auth`
 * alone, and every action re-checks authority in the service.
 *
 * The organiser half DOES belong in the `me.` group — an organiser is a
 * platform user with a verified account — so it is declared there and mirrors
 * into the sealed console for free.
 *
 * Both halves call the same `EntryEditor`. There is one set of rules about what
 * may be changed and when; this class only decides who is asking and hands back
 * JSON.
 */
/*
 * COPY — this file is `app/Http/Controllers/EntryPanelController.php`, copied verbatim into the
 * sandbox on 2026-09-05 and then rewritten ONLY where it had to be:
 *
 *   - the namespace, and an explicit import of the base Controller it used to
 *     inherit by being a neighbour of it;
 *   - `view('personal.…')` / `view('entry.…')` now name the sandbox's own copies
 *     of those templates (`eventlab::…`);
 *   - `route('me.events.…')` / `route('events.public…')` now name the sandbox's
 *     own routes, so a copied screen links to other copied screens.
 *
 * Nothing else was touched, so `diff` against the original still reads clean.
 * The original is untouched and still serves every real event; this copy is
 * reachable only under /testcode and only for a SANDBOX twin.
 */
class EntryPanelController extends Controller
{
    use StoresBase64Images;

    public function __construct(
        private EntryEditor $editor,
        private Withdrawal $withdrawals,
        private EntryService $entries,
        private EventAccess $access,
    ) {}

    /* ==================== The athlete ==================== */

    /**
     * The panel itself.
     *
     * Rendered inside the event's own skin, so an athlete who arrived from a
     * shared link never leaves the app that link belongs to.
     */
    public function show(Request $request, ClubEvent $event, PublicEvent $publisher)
    {
        abort_if(! $publisher->isPublic($event), 404);

        $me = Auth::user();
        $registration = $this->myRegistration($event, $me);

        // Not entered. Not an error to apologise for — the entry door is the
        // answer, and it is one tap away.
        if (! $registration) {
            return redirect()->route('testcode.e.enter', $event->uuid);
        }

        $permissions = $this->editor->permissions($event, $registration, $me);
        $pending = $this->withdrawals->pendingFor($event, $me);

        return view('eventlab::entry.public.my-entry', [
            'event' => $event,
            'entry' => $this->editor->present($event, $registration, $me),
            'payment' => $this->paymentFor($event, $registration),
            'permissions' => $permissions,
            'withdrawal' => $pending ? $this->withdrawals->present($pending) : null,
            'clubs' => $this->entries->representableClubs($me),
            'belts' => BeltRank::ladder(),
        ]);
    }

    /** The panel's state as JSON — what a live update re-fetches. */
    public function state(Request $request, ClubEvent $event, PublicEvent $publisher): JsonResponse
    {
        abort_if(! $publisher->isPublic($event), 404);

        $me = Auth::user();
        $registration = $this->myRegistration($event, $me);

        abort_unless($registration, 404);

        $pending = $this->withdrawals->pendingFor($event, $me);

        return response()->json([
            'success' => true,
            'entry' => $this->editor->present($event, $registration, $me),
            'payment' => $this->paymentFor($event, $registration),
            'permissions' => $this->editor->permissions($event, $registration, $me),
            'withdrawal' => $pending ? $this->withdrawals->present($pending) : null,
        ]);
    }

    /** Save a change the athlete made to their own entry. */
    public function update(Request $request, ClubEvent $event, PublicEvent $publisher): JsonResponse
    {
        abort_if(! $publisher->isPublic($event), 404);

        $me = Auth::user();
        $registration = $this->myRegistration($event, $me);

        abort_unless($registration, 404);

        return $this->applyEdit($request, $event, $registration, $me);
    }

    /** Ask to be taken out of the competition. */
    public function withdraw(Request $request, ClubEvent $event, PublicEvent $publisher): JsonResponse
    {
        abort_if(! $publisher->isPublic($event), 404);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:300'],
        ]);

        $result = $this->withdrawals->request($event, Auth::user(), $data['reason'] ?? null);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'withdrawal' => $result['request'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    /** Change their mind before anybody has answered. */
    public function withdrawCancel(Request $request, ClubEvent $event, PublicEvent $publisher): JsonResponse
    {
        abort_if(! $publisher->isPublic($event), 404);

        $result = $this->withdrawals->cancel($event, Auth::user());

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'withdrawal' => $result['request'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    /* ==================== The organiser ==================== */

    /**
     * Fix somebody else's entry.
     *
     * Route-model-bound on the registration, and `EntryEditor` re-checks that
     * it belongs to THIS event before it writes a thing — an entry id from
     * another competition must not be writable through this competition's URL.
     */
    public function updateEntrant(Request $request, ClubEvent $event, ClubEventRegistration $registration): JsonResponse
    {
        return $this->applyEdit($request, $event, $registration, Auth::user());
    }

    /** What one entry looks like, for the organiser's editor to open on. */
    public function entrant(Request $request, ClubEvent $event, ClubEventRegistration $registration): JsonResponse
    {
        $me = Auth::user();

        abort_unless($this->access->canManage($event, $me), 403);
        abort_unless((int) $registration->event_id === (int) $event->id, 404);

        return response()->json([
            'success' => true,
            'entry' => $this->editor->present($event, $registration, $me),
            'permissions' => $this->editor->permissions($event, $registration, $me),
        ]);
    }


    /**
     * What this entry costs, what has happened about it, and how to settle it.
     *
     * The panel used to say nothing at all about money: the amount was on the
     * poster, the "paid" flag existed on the row, and the entrant could see
     * neither. Somebody who has paid and cannot tell whether it landed phones
     * the organiser, which is the whole cost of leaving this out.
     *
     * There is no gateway and there is not going to be one (CLAUDE.md): the
     * flow is bank transfer, then a photograph of the receipt, then the
     * organiser approves. So this returns the amount, the state, and the club's
     * own account details — nothing about anybody else's entry.
     *
     * @return array<string, mixed>|null  null when the event charges nothing
     */
    private function paymentFor(ClubEvent $event, ClubEventRegistration $registration): ?array
    {
        $amount = EventFee::amount($event, $registration->role ?: 'participant');

        if (! $amount) {
            return null;
        }

        $currency = EventFee::currency($event);

        // approved → the organiser confirmed it. submitted → a receipt is with
        // them. owed → nothing has been sent yet.
        $state = $registration->paid && $registration->paid_by
            ? 'approved'
            : ($registration->payment_proof ? 'submitted' : ($registration->paid ? 'approved' : 'owed'));

        $bank = $event->tenant?->bankAccounts()
            ->orderByDesc('is_primary')->orderBy('id')->first();

        return [
            'amount' => (float) $amount,
            'currency' => $currency,
            'display' => EventFee::display($amount, $currency),
            'state' => $state,
            'paid_at' => $registration->paid_at?->toIso8601String(),
            'has_proof' => (bool) $registration->payment_proof,
            'club' => $event->tenant?->club_name,
            // Only the fields a payer actually needs to make the transfer.
            'bank' => $bank ? array_filter([
                'bank_name' => $bank->bank_name,
                'account_name' => $bank->account_name,
                'account_number' => $bank->account_number,
                'iban' => $bank->iban,
                'benefitpay' => $bank->benefitpay_account,
            ]) : null,
        ];
    }

    /**
     * The entrant hands in a receipt.
     *
     * It does NOT mark the entry paid — only an organiser does that, from the
     * console, after looking at it. This just puts the photograph where they
     * will see it and says so on the entrant's own screen, which is the part
     * that was missing.
     *
     * The file is validated by its real bytes and given a server-made name on
     * the PRIVATE disk (CLAUDE.md: "Image Uploads Must Validate Real Bytes",
     * "Upload Storage Structure"). A receipt carries an account number; it is
     * never public.
     */
    public function paymentProof(Request $request, ClubEvent $event, PublicEvent $publisher): JsonResponse
    {
        abort_if(! $publisher->isPublic($event), 404);

        $me = Auth::user();
        $registration = $this->myRegistration($event, $me);

        abort_unless($registration, 404);

        $data = $request->validate([
            'proof' => ['required', 'string', 'starts_with:data:image/'],
        ]);

        // Nothing to settle, or already settled by an organiser.
        $payment = $this->paymentFor($event, $registration);

        if (! $payment || $payment['state'] === 'approved') {
            return response()->json([
                'success' => false,
                'message' => __('eventlab::messages.pay_nothing_due'),
            ], 422);
        }

        /*
         * Under the PAYER, not under the event.
         *
         * `events/{uuid}/…` is readable by anybody the event is visible to —
         * `FileAccess::event()` ends in `EventAccess::visible()` — so a receipt
         * filed there could be opened by every other competitor. Under the
         * member it is the opposite by default: `FileAccess::member()` lets
         * through only the person themselves and whoever speaks for them, and
         * answers "never anyone else's business" for a payments folder.
         *
         * The organiser still sees it, through the console's own proof route,
         * which streams the file after checking that they may manage the event
         * — authorisation in code rather than in a guessable path.
         */
        $path = $this->storeBase64Image(
            $data['proof'],
            StoragePath::memberPayments($me),
            (string) Str::uuid(),
            'local'
        );

        if (! $path) {
            return response()->json([
                'success' => false,
                'message' => __('eventlab::messages.pay_proof_rejected'),
            ], 422);
        }

        $previous = $registration->payment_proof;

        $registration->forceFill(['payment_proof' => $path])->save();

        // Only once the new one is safely stored (CLAUDE.md, the REPLACE case).
        if ($previous && $previous !== $path) {
            rescue(fn () => \Illuminate\Support\Facades\Storage::disk('local')->delete($previous), null, false);
        }

        return response()->json([
            'success' => true,
            'message' => __('eventlab::messages.pay_proof_sent'),
            'payment' => $this->paymentFor($event, $registration->fresh()),
        ]);
    }

    /** The withdrawal queue. */
    public function withdrawals(Request $request, ClubEvent $event): JsonResponse
    {
        $me = Auth::user();

        abort_unless($this->access->canManage($event, $me), 403);

        return response()->json([
            'success' => true,
            'requests' => $this->withdrawals->queue($event, $me),
        ]);
    }

    /** Grant or refuse one. */
    public function decideWithdrawal(Request $request, ClubEvent $event, EventWithdrawalRequest $withdrawal): JsonResponse
    {
        $me = Auth::user();

        // Scoped before anything else: a request uuid from another event must
        // not be decidable through this event's URL.
        abort_unless((int) $withdrawal->event_id === (int) $event->id, 404);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['grant', 'refuse'])],
            'response' => ['nullable', 'string', 'max:300'],
        ]);

        $result = $data['decision'] === 'grant'
            ? $this->withdrawals->grant($withdrawal, $me, $data['response'] ?? null)
            : $this->withdrawals->refuse($withdrawal, $me, $data['response'] ?? null);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'withdrawal' => $result['request'] ?? null,
            'pending' => $this->withdrawals->queue($event, $me),
        ], $result['ok'] ? 200 : 422);
    }

    /* ==================== Shared ==================== */

    /**
     * Validate and hand to the editor.
     *
     * ABSENT IS NOT BLANK: only keys the request actually sent are passed on,
     * so a sheet that saves one field cannot wipe the other six. `validate()`
     * with `sometimes` gives exactly that — a key that is not there is not
     * validated and not returned.
     */
    private function applyEdit(Request $request, ClubEvent $event, ClubEventRegistration $registration, $actor): JsonResponse
    {
        $validated = $request->validate([
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:15', 'max:250'],
            'belt_colour' => ['sometimes', 'nullable', 'string', Rule::in(BeltRank::colours())],
            'belt_grade' => ['sometimes', 'nullable', 'string', 'max:32'],
            // Real bytes are checked by the trait; this only refuses anything
            // that is not a data URI before we spend time decoding it.
            'photo' => ['sometimes', 'nullable', 'string', 'starts_with:data:image/'],
            'club' => ['sometimes', 'nullable', 'string', 'max:120'],
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:120'],
            'birthdate' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', Rule::in(['Male', 'Female'])],
            // ISO-3166 alpha-2, the same shape the entry door takes.
            'nationality' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            // How they get back in. Format only — whether the address is free,
            // and whether removing one would strand the account, is decided in
            // EntryEditor where both fields are visible at once.
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:24'],
        ]);

        // Only the fields this editor owns, and only the ones actually sent.
        $data = array_intersect_key($validated, array_flip(EntryEditor::FIELDS));

        if (! $data) {
            return response()->json([
                'success' => false,
                'message' => __('events.entry_edit_nothing'),
            ], 422);
        }

        $result = $this->editor->apply($event, $registration, $data, $actor);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'field' => $result['field'] ?? null,
            'changed' => $result['changed'] ?? [],
            'division_changed' => $result['division_changed'] ?? false,
            'reweigh' => $result['reweigh'] ?? false,
            'entry' => $result['entry'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    /** Their own participant row for this event, or null. */
    private function myRegistration(ClubEvent $event, $me): ?ClubEventRegistration
    {
        return ClubEventRegistration::with('user')
            ->where('event_id', $event->id)
            ->where('user_id', $me->id)
            ->where('role', 'participant')
            ->first();
    }
}
