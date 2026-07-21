<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AchievementRequest;
use App\Models\ClubAchievement;
use App\Models\SkillAcquisition;
use App\Models\Tenant;
use App\Models\TournamentEvent;
use App\Services\AchievementVerificationService;
use App\Traits\HandlesClubAuthorization;
use App\Traits\PersistsTranslations;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ClubAchievementController extends Controller
{
    use HandlesClubAuthorization;
    use PersistsTranslations;

    public function achievements(Tenant $club)
    {
        $this->authorizeClub($club);
        $achievements = ClubAchievement::where('tenant_id', $club->id)->orderBy('sort_order')->orderBy('id')->get();

        return view(\App\Support\ClubView::pick('achievements'), compact('club', 'achievements'));
    }

    /**
     * Member self-claimed records (tournament medals AND acquired skills) awaiting
     * this club's attestation. Only records whose affiliation names THIS club show.
     * Returns a normalized `$claims` list the view renders uniformly.
     */
    public function verifications(Tenant $club)
    {
        $this->authorizeClub($club);

        $medals = TournamentEvent::whereHas('clubAffiliation', fn ($q) => $q->where('tenant_id', $club->id))
            ->where('verification_status', TournamentEvent::STATUS_PENDING)
            ->with(['user:id,uuid,name,full_name,gender,profile_picture,updated_at', 'performanceResults', 'clubAffiliation'])
            ->orderByDesc('updated_at')->get()
            ->map(fn ($m) => $this->normalizeClaim($club, 'achievement', $m));

        $skills = SkillAcquisition::whereHas('clubAffiliation', fn ($q) => $q->where('tenant_id', $club->id))
            ->where('verification_status', SkillAcquisition::STATUS_PENDING)
            ->with(['user:id,uuid,name,full_name,gender,profile_picture,updated_at', 'activity', 'clubAffiliation'])
            ->orderByDesc('updated_at')->get()
            ->map(fn ($s) => $this->normalizeClaim($club, 'skill', $s));

        $claims = $medals->concat($skills)->values();

        return view(\App\Support\ClubView::pick('achievements.verifications'), compact('club', 'claims'));
    }

    public function confirmVerification(Tenant $club, string $type, string $uuid, AchievementVerificationService $service)
    {
        $this->authorizeClub($club);
        $record = $this->resolveClubClaim($club, $type, $uuid);

        try {
            $service->clubConfirm($record, Auth::user());
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }

        return response()->json(['success' => true, 'message' => __('Verified.'), 'uuid' => $record->uuid, 'status' => $record->verification_status]);
    }

    public function rejectVerification(Request $request, Tenant $club, string $type, string $uuid, AchievementVerificationService $service)
    {
        $this->authorizeClub($club);
        $record = $this->resolveClubClaim($club, $type, $uuid);
        $note = $request->validate(['note' => 'nullable|string|max:500'])['note'] ?? null;

        try {
            $service->clubReject($record, Auth::user(), $note);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }

        return response()->json(['success' => true, 'message' => __('Marked as not verified.'), 'uuid' => $record->uuid, 'status' => $record->verification_status]);
    }

    /** Verifiable record types actionable from the queue, keyed by route `{type}`. */
    private const CLAIM_TYPES = ['achievement' => TournamentEvent::class, 'skill' => SkillAcquisition::class];

    /** Resolve a pending record of the given type that names THIS club, or 404. */
    private function resolveClubClaim(Tenant $club, string $type, string $uuid): \Illuminate\Database\Eloquent\Model
    {
        $class = self::CLAIM_TYPES[$type] ?? abort(404);

        return $class::where('uuid', $uuid)
            ->whereHas('clubAffiliation', fn ($q) => $q->where('tenant_id', $club->id))
            ->with('clubAffiliation.tenant')
            ->firstOrFail();
    }

    /** Flatten a medal/skill into the uniform shape the verification queue view renders. */
    private function normalizeClaim(Tenant $club, string $type, \Illuminate\Database\Eloquent\Model $r): array
    {
        $u = $r->user;
        $isMedal = $type === 'achievement';

        return [
            'type' => $type,
            'uuid' => $r->uuid,
            'user' => ['name' => $u?->full_name ?: $u?->name, 'uuid' => $u?->uuid, 'gender' => $u?->gender, 'profile_picture' => $u?->profile_picture],
            'title' => $isMedal ? $r->title : $r->skill_name,
            'sport' => $isMedal ? $r->sport : ($r->activity?->tr('name') ?? $r->activity_name),
            'date' => $isMedal ? optional($r->date)->format('M j, Y') : ($r->start_date ? $r->start_date->format('M Y') : null),
            'meta' => $isMedal ? null : ucfirst($r->proficiency_level ?? ''),
            'club_name' => $r->clubAffiliation?->club_name,
            'medals' => $isMedal ? $r->performanceResults->pluck('medal_type')->all() : [],
            'evidence_url' => ($isMedal && $r->evidence_path) ? route('member.tournament.evidence', [$r->user_id, $r->uuid]) : null,
            'confirm_url' => route('admin.club.achievements.verifications.confirm', [$club->slug ?? $club->id, $type, $r->uuid]),
            'reject_url' => route('admin.club.achievements.verifications.reject', [$club->slug ?? $club->id, $type, $r->uuid]),
        ];
    }

    public function storeAchievement(AchievementRequest $request, Tenant $club)
    {
        $this->authorizeClub($club);

        $images = $this->saveAchievementBase64Images($request->input('achievement_images_base64', []), $club->id);

        try {
            $athletes = $request->athletes ? json_decode($request->athletes, true, 512, JSON_THROW_ON_ERROR) : null;
        } catch (\JsonException) {
            return back()->withErrors(['athletes' => 'Invalid data format.']);
        }

        $achievement = ClubAchievement::create([
            'tenant_id' => $club->id,
            'title' => $request->title,
            'short_title' => null,
            'type_icon' => $request->type_icon,
            'description' => $request->description,
            'location' => $request->location,
            'achievement_date' => $request->achievement_date,
            'date_label' => $this->deriveDateLabel($request->achievement_date),
            'category' => $request->category,
            'chips' => null,
            'athletes' => $athletes,
            'tag' => $request->tag ?: 'Achievement',
            'tag_icon' => 'bi-trophy',
            'image_path' => null,
            'images' => $images ?: null,
            'bg_from' => '#f59e0b',
            'bg_to' => '#f97316',
            'status' => $request->status,
            'sort_order' => $request->sort_order ?? 0,
        ] + $this->deriveMedals($athletes));

        $this->applyTranslations($achievement, $request);

        return back()->with('success', 'Achievement created successfully.');
    }

    public function updateAchievement(AchievementRequest $request, Tenant $club, ClubAchievement $achievement)
    {
        $this->authorizeClub($club);
        abort_if($achievement->tenant_id !== $club->id, 403);

        try {
            $athletes = $request->athletes ? json_decode($request->athletes, true, 512, JSON_THROW_ON_ERROR) : null;
            $kept = json_decode($request->input('keep_extra_images', '[]'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return back()->withErrors(['athletes' => 'Invalid data format.']);
        }

        // Only the fields the form still collects; medals & date label are derived. Other
        // legacy columns (chips, bouts/wins, tag_icon, gradient) are left untouched.
        $data = $request->only([
            'title', 'type_icon', 'description', 'location',
            'achievement_date', 'category', 'status',
        ]);
        $data['short_title'] = null;
        $data['date_label'] = $this->deriveDateLabel($request->achievement_date);
        $data['athletes'] = $athletes;
        $data += $this->deriveMedals($athletes);

        $newExtra = $this->saveAchievementBase64Images($request->input('achievement_images_base64', []), $club->id);
        $data['images'] = array_merge($kept, $newExtra) ?: null;

        if ($achievement->image_path && ! in_array($achievement->image_path, $kept)) {
            Storage::disk('public')->delete($achievement->image_path);
            $data['image_path'] = null;
        }

        $achievement->update($data);

        $this->applyTranslations($achievement, $request);

        return back()->with('success', 'Achievement updated successfully.');
    }

    public function destroyAchievement(Tenant $club, ClubAchievement $achievement)
    {
        $this->authorizeClub($club);
        abort_if($achievement->tenant_id !== $club->id, 403);

        foreach ($achievement->images ?? [] as $imgPath) {
            Storage::disk('public')->delete($imgPath);
        }
        if ($achievement->image_path) {
            Storage::disk('public')->delete($achievement->image_path);
        }
        $achievement->delete();

        return response()->json(['success' => true, 'message' => 'Achievement deleted successfully.']);
    }

    /**
     * Medal totals are derived from the athletes' awards — an athlete whose award mentions
     * a medal counts once per medal (e.g. "Gold &amp; Silver" = +1 gold, +1 silver).
     */
    private function deriveMedals(?array $athletes): array
    {
        $gold = $silver = $bronze = 0;
        foreach ($athletes ?? [] as $athlete) {
            $role = mb_strtolower((string) ($athlete['role'] ?? ''));
            if (str_contains($role, 'gold')) {
                $gold++;
            }
            if (str_contains($role, 'silver')) {
                $silver++;
            }
            if (str_contains($role, 'bronze')) {
                $bronze++;
            }
        }

        return ['medals_gold' => $gold, 'medals_silver' => $silver, 'medals_bronze' => $bronze];
    }

    /** Card date label is derived from the date picker (e.g. "Feb 2026"). */
    private function deriveDateLabel($date): ?string
    {
        return $date ? \Illuminate\Support\Carbon::parse($date)->format('M Y') : null;
    }

    private function saveAchievementBase64Images(array $base64List, int $clubId): array
    {
        $paths = [];
        foreach ($base64List as $base64) {
            if (! str_starts_with($base64, 'data:image')) {
                continue;
            }
            [$meta, $imageData] = explode(',', $base64, 2);
            preg_match('/image\/(\w+)/', $meta, $m);
            $ext = $m[1] ?? 'jpg';
            $path = 'clubs/'.$clubId.'/achievements/'.uniqid('ach_').'.'.$ext;
            Storage::disk('public')->put($path, base64_decode($imageData));
            $paths[] = $path;
        }

        return $paths;
    }
}
