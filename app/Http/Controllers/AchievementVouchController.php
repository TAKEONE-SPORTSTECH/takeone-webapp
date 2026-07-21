<?php

namespace App\Http\Controllers;

use App\Http\Requests\VouchRequest;
use App\Models\AchievementVouch;
use App\Models\SkillAcquisition;
use App\Models\TournamentEvent;
use App\Services\AchievementVerificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

class AchievementVouchController extends Controller
{
    /** Verifiable record types a peer can vouch for, keyed by the route `{type}` slug. */
    private const TYPES = [
        'achievement' => TournamentEvent::class,
        'skill' => SkillAcquisition::class,
    ];

    /**
     * Record a peer/coach vouch (or dispute) for a member's self-claimed record
     * (a tournament medal or an acquired skill), then let the service recompute
     * the record's status. Bound by the record's public uuid.
     */
    public function vouch(VouchRequest $request, string $type, string $uuid, AchievementVerificationService $service)
    {
        $class = self::TYPES[$type] ?? null;
        abort_unless($class, 404);

        /** @var Model $record */
        $record = $class::where('uuid', $uuid)->firstOrFail();

        try {
            $service->addVouch(
                $record,
                $request->user(),
                $request->input('stance', AchievementVouch::STANCE_VOUCH),
                $request->input('relationship'),
                $request->input('note'),
            );
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }

        $fresh = $record->fresh('verifiedByTenant');

        return response()->json([
            'success' => true,
            'message' => __('Thanks — your attestation was recorded.'),
            'verification' => [
                'event_uuid' => $fresh->uuid,
                'status' => $fresh->verification_status,
                'method' => $fresh->verification_method,
            ],
        ]);
    }
}
