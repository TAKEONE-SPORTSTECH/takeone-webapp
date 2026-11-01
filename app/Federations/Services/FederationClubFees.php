<?php

namespace App\Federations\Services;

use App\Events\Support\DocumentUpload;
use App\Federations\Models\Federation;
use App\Federations\Models\FederationClub;
use App\Federations\Models\FederationFee;
use App\Federations\Models\FederationFeeSeason;
use App\Federations\Support\ClubRecognition;
use App\Members\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The club yearly federation fee: seasons, the fees they issue, a club's
 * proof of payment, and the federation's decision. (Whether a club IS
 * recognised on a day is the public ClubRecognition.)
 *
 * Callers authorise: every lookup takes the TOP body ($root) the caller has
 * been checked against, and refuses a season or fee from any other tree — a
 * uuid from another federation behaves exactly like one that does not exist.
 *
 * No card is ever taken: a club uploads a transfer / BenefitPay receipt (real
 * bytes checked, stored privately under a generated name) and a federation
 * manager approves it.
 */
class FederationClubFees
{
    public const PROOF_MIMES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
    public const MAX_PROOF_BYTES = 10 * 1024 * 1024;

    public function __construct(private FederationDirectory $directory) {}

    /** @return \Illuminate\Support\Collection<int, FederationFeeSeason> newest first */
    public function seasons(Federation $root)
    {
        return FederationFeeSeason::where('federation_id', $root->id)->orderByDesc('starts_on')->get();
    }

    public function season(Federation $root, ?string $uuid): ?FederationFeeSeason
    {
        return $uuid ? FederationFeeSeason::where('federation_id', $root->id)->where('uuid', $uuid)->first() : null;
    }

    /**
     * Create or change a season. Returns [season, null] or [null, error].
     *
     * @return array{0:?FederationFeeSeason, 1:?string}
     */
    public function saveSeason(User $by, Federation $root, array $data, ?FederationFeeSeason $season = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $start = rescue(fn () => CarbonImmutable::parse((string) ($data['starts_on'] ?? '')), null, report: false);
        $end = rescue(fn () => CarbonImmutable::parse((string) ($data['ends_on'] ?? '')), null, report: false);
        $fee = $data['club_fee'] ?? null;
        if ($name === '' || mb_strlen($name) > 60 || ! $start || ! $end || ! is_numeric($fee) || $fee < 0 || $fee > 100000) {
            return [null, 'Give the season a name, its start and end dates, and a fee of 0 or more.'];
        }
        if ($end->lte($start) || $start->diffInDays($end) > 731) {
            return [null, 'A season ends after it starts, and lasts at most two years.'];
        }
        $clash = FederationFeeSeason::where('federation_id', $root->id)->where('name', $name)
            ->when($season, fn ($q) => $q->whereKeyNot($season->id))->exists();
        if ($clash) {
            return [null, 'There is already a season with that name.'];
        }
        $licence = (bool) ($data['athlete_licence'] ?? false);
        $payer = $data['athlete_payer'] ?? null;
        if ($licence && (! is_numeric($data['athlete_fee'] ?? null) || $data['athlete_fee'] < 0 || ! in_array($payer, [FederationFeeSeason::PAYER_CLUB, FederationFeeSeason::PAYER_ATHLETE], true))) {
            return [null, 'For the athlete licence, give its fee and who pays it (the club or the athlete).'];
        }

        $season ??= new FederationFeeSeason(['federation_id' => $root->id, 'created_by' => $by->id]);
        abort_unless((int) $season->federation_id === (int) $root->id, 404);
        $season->fill([
            'name' => $name,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'due_on' => ! empty($data['due_on']) ? CarbonImmutable::parse((string) $data['due_on'])->toDateString() : null,
            'grace_days' => max(0, min(365, (int) ($data['grace_days'] ?? 30))),
            'club_fee' => round((float) $fee, 3),
            'currency' => strtoupper(substr((string) ($data['currency'] ?? $root->currency ?? 'BHD'), 0, 3)) ?: 'BHD',
            'athlete_licence' => $licence,
            'athlete_fee' => $licence ? round((float) $data['athlete_fee'], 3) : null,
            'athlete_payer' => $licence ? $payer : null,
        ])->save();
        ClubRecognition::forget();
        Log::info('federation-fees: season saved', ['federation' => $root->id, 'season' => $season->uuid, 'by' => $by->id]);

        return [$season, null];
    }

    /**
     * Issue the season's club fee to every approved club in the tree that does
     * not have one yet. A fee already issued is never changed.
     */
    public function issue(FederationFeeSeason $season): int
    {
        $root = $season->federation;
        $ids = collect($this->directory->subtree($root))->pluck('id')->all();
        $clubs = FederationClub::whereIn('federation_id', $ids)->where('status', FederationClub::STATUS_APPROVED)->get(['id']);
        $have = FederationFee::where('season_id', $season->id)->where('kind', FederationFee::KIND_CLUB)->pluck('federation_club_id')->all();

        $made = 0;
        foreach ($clubs->whereNotIn('id', $have) as $club) {
            FederationFee::create([
                'season_id' => $season->id,
                'kind' => FederationFee::KIND_CLUB,
                'federation_club_id' => $club->id,
                'amount' => $season->club_fee,
                'currency' => $season->currency,
                'status' => (float) $season->club_fee > 0 ? FederationFee::DUE : FederationFee::WAIVED,
            ]);
            $made++;
        }

        return $made;
    }

    /** A fee of this tree by uuid, or null. */
    public function fee(Federation $root, ?string $uuid): ?FederationFee
    {
        return $uuid ? FederationFee::where('uuid', $uuid)->whereHas('season', fn ($q) => $q->where('federation_id', $root->id))->first() : null;
    }

    /**
     * A club sends its proof of payment. Only a fee that is due or was
     * rejected takes one; a rejected proof is replaced.
     *
     * @return array{ok:bool, message:string}
     */
    public function submitProof(User $by, FederationFee $fee, UploadedFile $proof): array
    {
        $info = $proof->getSize() <= self::MAX_PROOF_BYTES ? app(DocumentUpload::class)->inspect($proof) : null;
        if ($info === null || ! in_array($info['mime'], self::PROOF_MIMES, true)) {
            return ['ok' => false, 'message' => 'Upload the receipt as a PDF or a photo (JPG, PNG or WebP), up to 10 MB.'];
        }

        $old = null;
        $done = DB::transaction(function () use ($by, $fee, $proof, &$old) {
            $row = FederationFee::whereKey($fee->id)->lockForUpdate()->first();
            if (! $row || ! in_array($row->status, [FederationFee::DUE, FederationFee::REJECTED], true)) {
                return false;
            }
            $season = $row->season()->with('federation')->first();
            $stored = app(DocumentUpload::class)->store($proof, 'federations/'.$season->federation->uuid.'/club-fees/'.$season->uuid, 'local');
            if ($stored === null) {
                return false;
            }
            $old = $row->proof_path;
            $row->forceFill([
                'proof_path' => $stored['path'],
                'proof_mime' => $stored['mime'],
                'status' => FederationFee::SUBMITTED,
                'submitted_at' => now(),
                'submitted_by' => $by->id,
                'reason' => null,
            ])->save();

            return true;
        });

        if (! $done) {
            return ['ok' => false, 'message' => 'This fee is not waiting for a payment.'];
        }
        if ($old) {
            rescue(fn () => Storage::disk('local')->delete($old), null, report: false);
        }
        Log::info('federation-fees: proof submitted', ['fee' => $fee->uuid, 'by' => $by->id]);

        return ['ok' => true, 'message' => 'Thank you — the federation will check your payment.'];
    }

    /**
     * The federation decides: approve or reject a submitted proof (a reason is
     * required to reject), or waive a fee that is due.
     *
     * @return array{ok:bool, message:string}
     */
    public function decide(User $by, FederationFee $fee, string $decision, ?string $reason = null): array
    {
        $reason = trim((string) $reason);
        $allowed = [
            'approve' => [FederationFee::SUBMITTED],
            'reject' => [FederationFee::SUBMITTED],
            'waive' => [FederationFee::DUE, FederationFee::REJECTED],
        ];
        if (! isset($allowed[$decision])) {
            return ['ok' => false, 'message' => 'Unknown decision.'];
        }
        if ($decision === 'reject' && $reason === '') {
            return ['ok' => false, 'message' => 'Say why the payment is not accepted, so the club can fix it.'];
        }

        $done = DB::transaction(function () use ($by, $fee, $decision, $reason, $allowed) {
            $row = FederationFee::whereKey($fee->id)->lockForUpdate()->first();
            if (! $row || ! in_array($row->status, $allowed[$decision], true)) {
                return false;
            }
            $row->forceFill([
                'status' => ['approve' => FederationFee::APPROVED, 'reject' => FederationFee::REJECTED, 'waive' => FederationFee::WAIVED][$decision],
                'decided_at' => now(),
                'decided_by' => $by->id,
                'reason' => $decision === 'reject' ? mb_substr($reason, 0, 500) : null,
            ])->save();

            return true;
        });

        if (! $done) {
            return ['ok' => false, 'message' => 'This fee has already been decided.'];
        }
        Log::info('federation-fees: decided', ['fee' => $fee->uuid, 'decision' => $decision, 'by' => $by->id]);

        return ['ok' => true, 'message' => [
            'approve' => 'Payment approved — the club is recognised for the season.',
            'reject' => 'Payment returned to the club with your reason.',
            'waive' => 'Fee waived — the club is recognised for the season.',
        ][$decision]];
    }
}
